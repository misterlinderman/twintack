<?php

namespace SolidAffiliate\Lib\Integrations;

use Exception;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Models\Affiliate;

class MailChimpIntegration
{

    /**
     * @return void
     */
    public static function register_hooks()
    {
        // Check if the User has enabled this integration.
        if (!(bool)Settings::get(Settings::KEY_INTEGRATIONS_MAILCHIMP_IS_ACTIVE)) {
            return;
        }

        add_action('solid_affiliate/Affiliate/insert', [self::class, 'sync_affiliate_to_contacts']);
    }

    /**
     * @return string|null
     */
    public static function get_api_key()
    {
        $maybe_api_key = (string)Settings::get(Settings::KEY_INTEGRATIONS_MAILCHIMP_API_KEY_LIVE);

        if (empty($maybe_api_key)) {
            return null;
        } else {
            return $maybe_api_key;
        }
    }

    /**
     * Undocumented function
     *
     * @return MailChimp|null
     */
    public static function get_instance()
    {
        $maybe_api_key = self::get_api_key();
        if (is_null($maybe_api_key)) {
            return null;
        } else {
            try {
                return new MailChimp($maybe_api_key);
            } catch (Exception $e) {
                return null;
            }
        }
    }

    /**
     * @param int $affiliate_id
     * @return void
     */
    public static function sync_affiliate_to_contacts($affiliate_id)
    {
        $affiliate = Affiliate::find($affiliate_id);
        if (is_null($affiliate)) {
            // todo?
        } else {
            $maybe_user = $affiliate->user();
            if ($maybe_user instanceof \WP_User) {
                $email = (string)$maybe_user->user_email;
                $MailChimp = self::get_instance();
                if (is_null($MailChimp)) {
                } else {
                    //////////////////////////////////////////
                    $list_id = self::get_list_id($MailChimp);
                    /////////////////////////////////////////

                    // TODO this should be an upsert not an insert. Sync them up, unique on email.
                    $mailchimp_user_id = self::get_mailchimp_user_id_for_affiliate($affiliate_id);
                    if ($mailchimp_user_id) {
                        $result = $MailChimp->patch("lists/$list_id/members/$mailchimp_user_id", [
                            'email_address' => $email,
                            'merge_fields' => self::mailchimp_merge_fields_for_affiliate($affiliate_id),
                            'status'        => 'subscribed',
                            'tags' => ['affiliate'],
                        ]);

                        if (isset($result['id'])) {
                            self::set_mailchimp_user_id_for_affiliate($affiliate_id, (string)$result['id']);
                        }
                    } else {
                        $result = $MailChimp->post("lists/$list_id/members", [
                            'email_address' => $email,
                            'merge_fields' => self::mailchimp_merge_fields_for_affiliate($affiliate_id),
                            'status'        => 'subscribed',
                            'tags' => ['affiliate']
                        ]);
                        if (isset($result['id'])) {
                            self::set_mailchimp_user_id_for_affiliate($affiliate_id, (string)$result['id']);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param int $affiliate_id
     * @return array
     */
    public static function mailchimp_merge_fields_for_affiliate($affiliate_id)
    {
        $affiliate_or_null = Affiliate::find($affiliate_id);
        if (is_null($affiliate_or_null)) {
            return [];
        } else {
            return [
                'FNAME' => (string)$affiliate_or_null->first_name,
                'LNAME' => (string)$affiliate_or_null->last_name,
            ];
        }
    }


    /**
     * @param int $affiliate_id
     * @param string $mailchimp_user_id
     * 
     * @return int
     */
    public static function set_mailchimp_user_id_for_affiliate($affiliate_id, $mailchimp_user_id)
    {
        $maybe_affiliate = Affiliate::find($affiliate_id);

        if (is_null($maybe_affiliate)) {
            return 0;
        } else {
            Affiliate::updateInstance($maybe_affiliate, ['mailchimp_user_id' => $mailchimp_user_id]);
            return $affiliate_id;
        }
    }

    /**
     * @param int $affiliate_id
     * @return string|null
     */
    public static function get_mailchimp_user_id_for_affiliate($affiliate_id)
    {
        $maybe_affiliate = Affiliate::find($affiliate_id);
        if (is_null($maybe_affiliate)) {
            return null;
        } else {
            $maybe_mailchimp_user_id = $maybe_affiliate->mailchimp_user_id;
            if (empty($maybe_mailchimp_user_id)) {
                return null;
            } else {
                return $maybe_mailchimp_user_id;
            }
        }
    }

    /**
     * @param MailChimp $MailChimp
     * @return string
     */
    public static function get_list_id($MailChimp)
    {
        $maybe_mailchimp_affiliate_sync_list_id = (string)Settings::get(Settings::KEY_INTEGRATIONS_MAILCHIMP_AFFILIATE_SYNC_LIST_ID);
        if (!empty($maybe_mailchimp_affiliate_sync_list_id)) {
            return $maybe_mailchimp_affiliate_sync_list_id;
        }

        try {
            $response = $MailChimp->get('lists');
            if (isset($response['lists']) && isset($response['lists'][0]) && isset($response['lists'][0]['id'])) {
                $list_id = (string)$response['lists'][0]['id'];
                return $list_id;
            } else {
                return '';
            }
        } catch (Exception $e) {
            return __("There was an error accessing the MailChimp Lists via API.", 'solid-affiliate');
        }
    }
}
