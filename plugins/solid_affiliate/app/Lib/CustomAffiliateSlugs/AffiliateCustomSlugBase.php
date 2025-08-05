<?php

namespace SolidAffiliate\Lib\CustomAffiliateSlugs;

use SolidAffiliate\Lib\DevHelpers;
use SolidAffiliate\Lib\FF;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\Utils;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliateMeta;
use SolidAffiliate\Views\Admin\Affiliates\EditView;

# TODO: How do we want to handle if a dup custom slug is created if we don't use a DB level constraint
/**
 * @psalm-import-type EnumOptionsReturnType from \SolidAffiliate\Lib\VO\SchemaEntry
 */
class AffiliateCustomSlugBase
{
    const POST_PARAM_NEW_CUSTOM_SLUG_FOR_AFFILIATE = 'submit_new_custom_slug_for_affiliate';
    const NONCE_NEW_CUSTOM_SLUG_FOR_AFFILIATE = 'solid-affiliate-new-custom-slug-for-affiliate';

    const POST_PARAM_DELETE_AFFILIATE_CUSTOM_SLUG = 'delete_affiliate_custom_slug';
    const NONCE_DELETE_AFFILIATE_CUSTOM_SLUG  = 'solid-affiliate-delete-affiliate-custom-slug';
    const DELETE_CUSTOM_SLUG_FIELD_PARAM_KEY = 'sld_affiliate_custom_slug_delete_param_key';

    const POST_PARAM_AFFILIATE_CAN_EDIT_CUSTOM_SLUGS = 'can_affiliate_edit_custom_slugs';
    const NONCE_AFFILIATE_CAN_EDIT_SLUGS = 'solid-affiliate-affiliate-can-edit-custom-slugs';
    const CAN_EDIT_CUSTOM_SLUGS_FORM_ID = 'sld_affiliate_can_edit_custom_slug_form';

    const DISPLAY_FORMAT_VALUE_ID = 'id_format';
    const DISPLAY_FORMAT_VALUE_SLUG = 'slug_format';
    const DEFAULT_DISPLAY_FORMAT_VALUE = self::DISPLAY_FORMAT_VALUE_SLUG;
    const ALL_DISPLAY_FORMAT_VALUES = [self::DISPLAY_FORMAT_VALUE_ID, self::DISPLAY_FORMAT_VALUE_SLUG];

    const ONLY_ENGLISH_ALNUM = '/[^A-Za-z0-9]/';

    const DOCS_URL = 'https://docs.solidaffiliate.com/custom-affiliate-slugs/';

    ///////////
    // HOOKS //
    ///////////

    /**
     * Called on @hook admin_init from Main. Adds the Custom Slugs panel to the Affiliate Edit page.
     *
     * @return void
     */
    public static function register_admin_hooks()
    {
        add_filter(EditView::AFFILIATE_EDIT_AFTER_FORM_FILTER, [CustomSlugViewFunctions::class, 'render_custom_slugs_for_affiliate_edit'], 10, 2);
    }

    /**
     * Called on @hook init from plugin.php. Adds the action to auto create the default username custom slug for a new and approved Affiliate if the setting is enabled.
     *
     * @return void
     */
    public static function register_hooks()
    {
        if (self::get_should_auto_create_default_username_custom_slug()) {
            add_action(DevHelpers::AFFILIATE_APPROVED, [self::class, 'maybe_create_default_custom_slug']);
        }
    }

    ///////////////////////////////
    // Settings Helper Functions //
    ///////////////////////////////

    /**
     * Gets the KEY_SHOULD_AUTO_CREATE_AFFILIATE_USERNAME_SLUG setting
     *
     * @return boolean
     */
    public static function get_should_auto_create_default_username_custom_slug()
    {
        return (bool)Settings::get(Settings::KEY_SHOULD_AUTO_CREATE_AFFILIATE_USERNAME_SLUG);
    }

    /**
     * Gets the KEY_DEFAULT_DISPLAY_AFFILIATE_SLUG_FORMAT setting
     *
     * @return value-of<AffiliateCustomSlugBase::ALL_DISPLAY_FORMAT_VALUES>
     */
    public static function get_default_display_format()
    {
        return Validators::one_of(AffiliateCustomSlugBase::ALL_DISPLAY_FORMAT_VALUES, AffiliateCustomSlugBase::ALL_DISPLAY_FORMAT_VALUES[0], Settings::get(Settings::KEY_DEFAULT_DISPLAY_AFFILIATE_SLUG_FORMAT));
    }

    /**
     * Gets the KEY_PER_AFFILIATE_CUSTOM_SLUG_LIMIT setting
     *
     * @return int
     */
    public static function get_per_affiliate_slug_limit()
    {
        return (int)Settings::get(Settings::KEY_PER_AFFILIATE_CUSTOM_SLUG_LIMIT);
    }

    /**
     * Gets the KEY_CAN_AFFILIATES_CREATE_CUSTOM_SLUGS setting
     *
     * @return bool
     */
    public static function get_can_affiliates_create_and_delete_slugs()
    {
        return (bool)Settings::get(Settings::KEY_CAN_AFFILIATES_CREATE_CUSTOM_SLUGS);
    }

    /**
     * Enum options for the KEY_DEFAULT_DISPLAY_AFFILIATE_SLUG_FORMAT setting
     *
     * @return EnumOptionsReturnType
     */
    public static function default_display_formats_enum_options()
    {
        return [[self::DISPLAY_FORMAT_VALUE_ID, __('ID Slugs', 'solid-affiliate')], [self::DISPLAY_FORMAT_VALUE_SLUG, __('Custom Slugs', 'solid-affiliate')]];
    }

    //////////////////////
    // Schema Functions //
    //////////////////////

    /**
     * The Schema for the "can edit custom slugs" permissions AffiliateMeta.
     *
     * @return Schema<string>
     */
    public static function affiliate_can_edit_schema()
    {
        $entries = [
            AffiliateMeta::PRIMARY_KEY => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'show_list_table_column' => false,
                'display_name' => __('ID', 'solid-affiliate'),
                'show_on_new_form' => false,
                'show_on_edit_form' => 'hidden',
                'user_default' => null,
                'validate_callback' =>
                /** @param mixed $id */
                static function ($id) {
                    return is_int($id);
                },
                'sanitize_callback' =>
                /** @param mixed $id */
                static function ($id) {
                    return (int) $id;
                }
            ]),
            AffiliateMeta::SCHEMA_KEY_AFFILIATE_ID => new SchemaEntry([
                'type' => 'bigint',
                'show_list_table_column' => true,
                'required' => true,
                'display_name' => __('Affiliate ID', 'solid-affiliate'),
                'form_input_description' => __("The Associated Affiliate", 'solid-affiliate'),
                'show_on_new_form' => 'hidden',
                'show_on_edit_form' => 'hidden',
                'user_default' => null,
                'validate_callback' =>
                /** @param mixed $id */
                static function ($id) {
                    return is_int($id) && (Affiliate::find($id));
                },
                'sanitize_callback' =>
                /** @param mixed $id */
                static function ($id) {
                    return (int) $id;
                }
            ]),
            AffiliateMeta::SCHEMA_KEY_META_KEY => new SchemaEntry([
                'type' => 'varchar',
                'required' => true,
                'display_name' => __('Affiliate Can Edit Custom Slug Key', 'solid-affiliate'),
                'show_on_new_form' => 'hidden',
                'show_on_edit_form' => 'hidden',
                'show_list_table_column' => true,
                'user_default' => AffiliateMeta::META_KEY_CUSTOM_AFFILIATE_SLUG_IS_EDITABLE_BY_AFFILIATE,
                'validate_callback' =>
                /** @param mixed $key */
                static function ($key) {
                    return Validators::str($key) === AffiliateMeta::META_KEY_CUSTOM_AFFILIATE_SLUG_IS_EDITABLE_BY_AFFILIATE;
                },
                'sanitize_callback' =>
                /** @param mixed $_key */
                static function ($_key) {
                    return AffiliateMeta::META_KEY_CUSTOM_AFFILIATE_SLUG_IS_EDITABLE_BY_AFFILIATE;
                }
            ]),
            AffiliateMeta::SCHEMA_KEY_META_VALUE => new SchemaEntry([
                'type' => 'bool',
                'display_name' => __('Custom slugs', 'solid-affiliate'),
                'form_input_description' => __('Allow this affiliate to create and delete custom slugs.', 'solid-affiliate'),
                'user_default' => false,
                'show_on_new_form' => false,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'validate_callback' =>
                /** @param mixed $val */
                static function ($val) {
                    return is_bool($val);
                },
                'sanitize_callback' =>
                /** @param mixed $val */
                static function ($val) {
                    return (bool)$val;
                },
                'custom_form_input_attributes' => ['onchange' => "jQuery('form#" . self::CAN_EDIT_CUSTOM_SLUGS_FORM_ID . "').find('#" . self::POST_PARAM_AFFILIATE_CAN_EDIT_CUSTOM_SLUGS . "').click()"]
            ])
        ];

        $entries = FF::filter_schema_entries_for_feature_flag($entries, 'custom_affiliate_slugs');

        return new Schema(['entries' => $entries]);
    }

    /**
     * The Schema for an Affiliate custom slug AffiliateMeta.
     *
     * @return Schema<AffiliateMeta::SCHEMA_KEY_*>
     */
    public static function slug_schema()
    {
        $entries = [
            AffiliateMeta::SCHEMA_KEY_AFFILIATE_ID => new SchemaEntry([
                'type' => 'bigint',
                'show_list_table_column' => true,
                'required' => true,
                'display_name' => __('Affiliate ID', 'solid-affiliate'),
                'form_input_description' => __("The Associated Affiliate", 'solid-affiliate'),
                'show_on_new_form' => 'hidden',
                'show_on_edit_form' => 'hidden',
                'user_default' => null,
                'validate_callback' =>
                /** @param mixed $id */
                static function ($id) {
                    return is_int($id) && (Affiliate::find($id));
                },
                'sanitize_callback' =>
                /** @param mixed $id */
                static function ($id) {
                    return (int) $id;
                }
            ]),
            AffiliateMeta::SCHEMA_KEY_META_KEY => new SchemaEntry([
                'type' => 'varchar',
                'required' => true,
                'display_name' => __('Affiliate Custom Slug Key', 'solid-affiliate'),
                'show_on_new_form' => 'hidden',
                'show_on_edit_form' => 'hidden',
                'show_list_table_column' => true,
                'user_default' => AffiliateMeta::META_KEY_CUSTOM_AFFILIATE_SLUG,
                'validate_callback' =>
                /** @param mixed $key */
                static function ($key) {
                    return Validators::str($key) === AffiliateMeta::META_KEY_CUSTOM_AFFILIATE_SLUG;
                },
                'sanitize_callback' =>
                /** @param mixed $_key */
                static function ($_key) {
                    return AffiliateMeta::META_KEY_CUSTOM_AFFILIATE_SLUG;
                }
            ]),
            AffiliateMeta::SCHEMA_KEY_META_VALUE => new SchemaEntry([
                'type' => 'text',
                'required' => true,
                'display_name' => __('Slug text', 'solid-affiliate'),
                'form_input_description' => __('The affiliate custom slug to be used in affiliate links. It must be only alphanumeric characters, and can be up to 40 characters long.', 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'validate_callback' =>
                /** @param mixed $val */
                static function ($val) {
                    return is_string($val);
                },
                'sanitize_callback' =>
                /** @param mixed $val */
                static function ($val) {
                    return Validators::str($val);
                },
                'custom_form_input_attributes' => ['minlength' => '1', 'maxlength' => '40'],
                'form_input_wrap_class' => 'sld-ap-form_group'
            ])
        ];

        return new Schema(['entries' => $entries]);
    }

    //////////////////////////////////////////////
    // Auto Create Username Custom Slug Feature //
    //////////////////////////////////////////////

    /**
     * Create the default username based custom slug for an Affiliate if that Affiliate does not already have a custom slug.
     *
     * @param int $affiliate_id
     *
     * @return void
     */
    public static function maybe_create_default_custom_slug($affiliate_id)
    {
        $maybe_affiliate = Affiliate::find($affiliate_id);

        if ($maybe_affiliate instanceof Affiliate) {
            if (self::_does_affiliate_have_no_slug($maybe_affiliate)) {
                $maybe_user = get_user_by('id', $maybe_affiliate->user_id);

                if ($maybe_user instanceof \WP_User) {
                    $username = $maybe_user->user_login;
                    $slug = self::sanitize_slug($username, true);
                    $unique_slug = self::_recursively_create_unique_slug($slug);
                    CustomSlugDbFunctions::create_custom_slug_affiliate_meta($affiliate_id, $unique_slug);
                }
            }
        }
    }

    /**
     * Whether or not an Affiliate already has a custom slug.
     *
     * @param Affiliate $affiliate
     *
     * @return boolean
     */
    private static function _does_affiliate_have_no_slug($affiliate)
    {
        return empty(CustomSlugDbFunctions::slug_metas_for_affiliate($affiliate->id));
    }

    /**
     * Reecursively tries to create a custom slug until one is found that does not already exist.
     *
     * @param string $slug
     *
     * @return string
     */
    private static function _recursively_create_unique_slug($slug)
    {
        if (CustomSlugDbFunctions::does_slug_already_exist($slug)) {
            $new_slug = $slug . rand(1, 9);
            return self::_recursively_create_unique_slug($new_slug);
        } else {
            return $slug;
        }
    }

    //////////////////////////////////
    // Permissions Helper Functions //
    //////////////////////////////////

    /**
     * Checks if an Affiliate has permissions to create and delete custom slugs.
     * If an Affiliate is not given, it will ony check against the global KEY_CAN_AFFILIATES_CREATE_CUSTOM_SLUGS setting.
     *
     * @param Affiliate|null $affiliate
     *
     * @return boolean
     */
    public static function can_edit_custom_slugs($affiliate = null)
    {
        if (self::get_can_affiliates_create_and_delete_slugs()) {
            return true;
        }

        if (is_null($affiliate)) {
            return false;
        }

        return self::_can_affiliate_edit_custom_slugs($affiliate);
    }

    /**
     * Checks the AffiliateMeta value for the "can edit custom slugs" permissions meta, return false if it does not exist yet.
     *
     * @param Affiliate $affiliate
     *
     * @return boolean
     */
    private static function _can_affiliate_edit_custom_slugs($affiliate)
    {
        return (bool)CustomSlugDbFunctions::can_edit_slugs_meta_for_affiliate($affiliate)->meta_value;
    }

    //////////////////////////
    // URL Helper Functions //
    //////////////////////////

    /**
     * The default custom slug for an Affiliate. If it does not exist yet, then default to their ID slug.
     *
     * @param Affiliate $affiliate
     *
     * @return int|string
     */
    public static function default_affiliate_slug($affiliate)
    {
        $format = self::get_default_display_format();

        if ($format === self::DISPLAY_FORMAT_VALUE_SLUG) {
            $maybe_meta = self::maybe_default_slug_meta_for_affiliate($affiliate->id);
            if (is_null($maybe_meta)) {
                return $affiliate->id;
            } else {
                return $maybe_meta->meta_value;
            }
        } else {
            return $affiliate->id;
        }
    }

    /**
     * The query param string using the default affiliate slug.
     *
     * @param Affiliate $affiliate
     *
     * @return string
     */
    public static function default_referral_string($affiliate)
    {
        $slug = self::default_affiliate_slug($affiliate);
        $referral_variable = (string)Settings::get(Settings::KEY_REFERRAL_VARIABLE);
        return URLs::url_referral_query_string($referral_variable, $slug);
    }

    /**
     * Tries to get the default (first) custom slug AffiliateMeta, returning null if one does not exist yet.
     *
     * @param int $affiliate_id
     *
     * @return AffiliateMeta|null
     */
    public static function maybe_default_slug_meta_for_affiliate($affiliate_id)
    {
        $metas = CustomSlugDbFunctions::slug_metas_for_affiliate($affiliate_id);

        if (empty($metas)) {
            return null;
        } else {
            return $metas[0];
        }
    }

    /////////////////////////////////
    // Sanitization and Validation //
    /////////////////////////////////

    /**
     * Sanitizes a custom slug in preparation for validations.
     * If mutate is true, then it will many many santizations so that it will always work in a URL.
     *
     * @param mixed $slug
     * @param boolean $mutate
     *
     * @return string
     */
    public static function sanitize_slug($slug, $mutate)
    {
        $slug = Validators::str($slug);

        if ($mutate) {
            $slug = sanitize_user($slug, true);
            $slug = sanitize_title($slug);
            $slug = remove_accents($slug);
            $slug = preg_replace(self::ONLY_ENGLISH_ALNUM, '', $slug);
        }

        return strtolower(trim($slug));
    }

    /**
     * Validates the custom slug.
     * Checks for string based validations so that it will in URLs, and DB based validations such as uniqunes and coutn limitations.
     *
     * @param string $slug
     * @param int $affiliate_id
     *
     * @return array{0: boolean, 1: string}
     */
    public static function validate_slug($slug, $affiliate_id)
    {
        if (Utils::is_empty($slug)) {
            $error_msg = __('The custom slug is empty. Custom slugs must have at least one character.', 'solid-affiliate');
            return [false, $error_msg];
        }

        if (strlen($slug) > 40) {
            $error_msg = __('The custom slug is too long. Custom slugs cannot exceed 40 characters.', 'solid-affiliate');
            return [false, $error_msg];
        }

        if (is_numeric($slug)) {
            $error_msg = __('The custom slug cannot contain only numbers.', 'solid-affiliate');
            return [false, $error_msg];
        }

        if (preg_match(self::ONLY_ENGLISH_ALNUM, $slug)) {
            $error_msg = __('The custom slug cannot contain any character that is not an English numeric digit or alphabetic letter.', 'solid-affiliate');
            return [false, $error_msg];
        }

        if (CustomSlugDbFunctions::does_slug_already_exist($slug)) {
            $error_msg = __('The custom slug already exists. Custom slugs must be unique across an affiliate program.', 'solid-affiliate');
            return [false, $error_msg];
        }

        if (CustomSlugDbFunctions::has_affiliate_reached_slug_limit($affiliate_id)) {
            $error_msg = __('The Affiliate has reached or exceeded the limit of custom slugs they can own. This limit is set by the site admin.', 'solid-affiliate');
            return [false, $error_msg];
        }

        return [true, ''];
    }
}
