<?php

namespace SolidAffiliate\Lib\CustomAffiliateSlugs;

use SolidAffiliate\Lib\ControllerFunctions;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliateMeta;

class CustomSlugControllerFunctions
{
    /**
     * POST action to upsert the "can edit custom slugs" permissions AffiliateMeta.
     *
     * @return void
     */
    public static function POST_edit_affiliate_can_edit_slugs()
    {
        ControllerFunctions::enforce_current_user_capabilities(['read']);

        $edit_schema = AffiliateCustomSlugBase::affiliate_can_edit_schema();
        $eitherFields = ControllerFunctions::extract_and_validate_POST_params(
            $_POST,
            SchemaFunctions::keys_on_edit_form_from_schema($edit_schema),
            $edit_schema
        );

        if ($eitherFields->isLeft) {
            ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', $eitherFields->left, [], 'admin');
        } else {
            $attrs = $eitherFields->right;
            $affiliate_id = Validators::int_from_array($attrs, AffiliateMeta::SCHEMA_KEY_AFFILIATE_ID);
            $can_edit = Validators::bool_from_array($attrs, AffiliateMeta::SCHEMA_KEY_META_VALUE);
            $id = Validators::int_from_array($attrs, AffiliateMeta::PRIMARY_KEY);
            $eitherAffiliateMetaID = CustomSlugDbFunctions::upsert_can_edit_custom_slugs_meta($affiliate_id, $can_edit, $id);

            if ($eitherAffiliateMetaID->isLeft) {
                ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', $eitherAffiliateMetaID->left, [], 'admin');
            } else {
                ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', [], [self::_POST_edit_affiliate_can_edit_slugs_success_msg($can_edit)], 'admin');
            }
        }
    }

    /**
     * Success message when a "can edit custom slugs" permissions AffiliateMeta is upserted that notifies of if the affiliate now has, or does not have, permission.
     *
     * @param boolean $can_edit
     *
     * @return string
     */
    private static function _POST_edit_affiliate_can_edit_slugs_success_msg($can_edit)
    {
        return __("Successfully updated Affiliate permission to create and delete custom slugs.", 'solid-affiliate') . ' ' .
            __('It is now set to') . ': <strong>' . json_encode($can_edit) . '</strong>';
    }

    /**
     * POST action to create a new affiliate custom slug.
     *
     * @return void
     */
    public static function POST_create_custom_slug_for_affiliate()
    {
        ControllerFunctions::enforce_current_user_capabilities(['read'], self::_current_user_has_capability_func());

        $slug_schema = AffiliateCustomSlugBase::slug_schema();
        $eitherFields = ControllerFunctions::extract_and_validate_POST_params(
            $_POST,
            SchemaFunctions::keys_on_new_form_from_schema($slug_schema),
            $slug_schema
        );

        if ($eitherFields->isLeft) {
            ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', $eitherFields->left);
        } else {
            $attrs = $eitherFields->right;
            $affiliate_id = Validators::int_from_array($attrs, AffiliateMeta::SCHEMA_KEY_AFFILIATE_ID);
            $slug = Validators::str_from_array($attrs, AffiliateMeta::SCHEMA_KEY_META_VALUE);
            $eitherAffiliateMetaID = CustomSlugDbFunctions::create_custom_slug_affiliate_meta($affiliate_id, $slug);

            if ($eitherAffiliateMetaID->isLeft) {
                ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', $eitherAffiliateMetaID->left);
            } else {
                $meta = AffiliateMeta::find($eitherAffiliateMetaID->right);
                $validated_slug = '';
                if ($meta instanceof AffiliateMeta) {
                    $validated_slug = $meta->meta_value;
                }
                ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', [], [self::_POST_create_custom_slug_for_affiliate_success_msg($validated_slug)]);
            }
        }
    }

    /**
     * Success message when a new custom slug is created that displays the custom slug text.
     *
     * @param string $slug
     *
     * @return string
     */
    private static function _POST_create_custom_slug_for_affiliate_success_msg($slug)
    {
        return __('Successfully created the custom slug', 'solid-affiliate') . ': <strong>' . $slug . '</strong>';
    }

    /**
     * POST action to delete an affiliate custom slug.
     *
     * @return void
     */
    public static function POST_delete_custom_slug_for_affiliate()
    {
        ControllerFunctions::enforce_current_user_capabilities(['read'], self::_current_user_has_capability_func());

        $meta_id = Validators::int_from_array($_POST, AffiliateCustomSlugBase::DELETE_CUSTOM_SLUG_FIELD_PARAM_KEY);
        $meta = AffiliateMeta::find($meta_id);
        $eitherID = AffiliateMeta::delete($meta_id, false);

        if ($eitherID->isLeft) {
            ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', $eitherID->left);
        } else {
            $slug = '';
            if ($meta instanceof AffiliateMeta) {
                $slug = $meta->meta_value;
            }
            ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', [], [self::_POST_delete_custom_slug_for_affiliate_success_msg($slug)]);
        }
    }

    /**
     * Success message when a custom slug is deleted that shows the old slug text.
     *
     * @param string $slug
     *
     * @return string
     */
    private static function _POST_delete_custom_slug_for_affiliate_success_msg($slug)
    {
        return __('Successfully deleted the custom slug', 'solid-affiliate') . ': <strong>' . $slug . '</strong>';
    }

    /**
     * The callback to be passed to ControllerFunctions::enforce_current_user_capabilities()
     *
     * @return callable():bool
     */
    private static function _current_user_has_capability_func()
    {
        $current_user = wp_get_current_user();
        $maybe_affiliate = Affiliate::find_where(['user_id' => $current_user->ID]);
        return static function () use ($maybe_affiliate) {
            return AffiliateCustomSlugBase::can_edit_custom_slugs($maybe_affiliate);
        };
    }
}