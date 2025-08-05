<?php

namespace SolidAffiliate\Models;

use SolidAffiliate\Lib\AffiliateRegistrationForm\AffiliateRegistrationFormFunctions;
use SolidAffiliate\Lib\AffiliateRegistrationForm\SchemaSerializer;
use SolidAffiliate\Lib\MikesDataModel;
use SolidAffiliate\Lib\MikesDataModelTrait;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\Utils;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\Either;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Views\Admin\Affiliates\EditView as AffiliateEditView;

/**
 * @psalm-type AffiliateRegistrationFormDataReturnType = string|int|boolean|array<string>
 */
class AffiliatePortal extends MikesDataModel
{
    use MikesDataModelTrait;

    /**
     * The Model name, used to identify it in Filters.
     *
     * @since TBD
     *
     * @var string
     */
    const MODEL_NAME = 'AffiliatePortal';

    const REQUIRED_SCHEMA_ENTRY_KEY_USER_LOGIN = 'user_login';
    const REQUIRED_SCHEMA_ENTRY_KEY_USER_EMAIL = 'user_email';
    const REQUIRED_SCHEMA_ENTRY_KEY_USER_PASS = 'user_pass';
    const REQUIRED_SCHEMA_ENTRY_KEY_ACCEPT_POLICY = 'is_accept_affiliate_policy';

    /** @var array<AffiliatePortal::REQUIRED_SCHEMA_ENTRY_KEY_*> */
    const REQUIRED_AFFILIATE_REGISTRATION_ONLY_SCHEMA_ENTRIES = [
        self::REQUIRED_SCHEMA_ENTRY_KEY_ACCEPT_POLICY,
        self::REQUIRED_SCHEMA_ENTRY_KEY_USER_EMAIL,
        self::REQUIRED_SCHEMA_ENTRY_KEY_USER_LOGIN,
        self::REQUIRED_SCHEMA_ENTRY_KEY_USER_PASS
    ];

    /**
     * Hooks that are registered by plugin.php.
     *
     * @return void
     */
    public static function register_hooks()
    {
        add_filter('solid_affiliate/upsert/' . Affiliate::TABLE . '/schema', [self::class, "schema_for_upsert_validations"]);
        add_filter('solid_affiliate/before_upsert/' . Affiliate::TABLE, [self::class, "prep_custom_registration_data_for_db"]);
    }

    /**
     * @param Affiliate|null $affiliate
     * @return string
     */
    public static function render_affiliate_portal_preview_section_on_affiliate_edit($affiliate)
    {
        if (is_null($affiliate)) {
            return '';
        }

        $preview_link = URLs::admin_portal_preview_path($affiliate->id, true);
        return "<a href='{$preview_link}' target='_blank'><button class='button button-primary action preview-portal-button' style='width:100%'>" . __('Preview as affiliate', 'solid-affiliate') . '</button></a>';
    }


    /**
     * Provides MikesDataModelTrait::upsert with the a Schema that includes custom registration fields to be validated before being saved as unstructured data in the Affiliate->custom_registration_data column.
     *
     * @param Schema $schema
     *
     * @return Schema
     */
    public static function schema_for_upsert_validations($schema)
    {
        $portal_entries = AffiliatePortal::get_affiliate_registration_schema()->entries;
        $all_entries = array_merge($portal_entries, $schema->entries);
        $entries = AffiliateRegistrationFormFunctions::remove_from_array($all_entries, self::REQUIRED_AFFILIATE_REGISTRATION_ONLY_SCHEMA_ENTRIES);
        return new Schema(['entries' => $entries]);
    }

    /**
     * The function that fires on the solid_affiliate/before_upsert/solid_affiliate_affiliates filter.
     * The filter expects a tuple of an empty string or present error messages, and the fields to be passed to insert.
     *
     * Takes the custom registration data that are top level keys in $fields, encodes them, merges them into $fields under Affiliate->custom_registration_data to be saved in the DB.
     * Then it removes the top level custom registration data keys because they are not columns on the Affiliate table.
     *
     * @param array<string, mixed> $fields
     *
     * @return array|string
     */
    public static function prep_custom_registration_data_for_db($fields)
    {
        # Get the full registration schema
        $registration_schema = self::get_affiliate_registration_schema();
        # Get the full registration schema keys
        $registration_keys = SchemaFunctions::keys($registration_schema);
        # Get the full Affiliate schema keys
        $affiliate_schema = Affiliate::schema();
        $affiliate_keys = SchemaFunctions::keys($affiliate_schema);
        # Filter out the affiliate keys, so that only custom keys are left (even though some "custom keys" may be filtered out if they are set to shared schema entries)
        $custom_keys = array_diff($registration_keys, $affiliate_keys);
        # Build a single json blob of the custom key-value pairs, which will filter out any of the legacy schema entries because they should be saved flat on the Affiliate as columns
        $new_data = AffiliateRegistrationFormFunctions::build_custom_data_from_form_submit(
            $registration_schema,
            self::_legacy_affiliate_registration_schema(),
            $fields
        );
        # Add new custom data to old custom data
        $old_data = AffiliateRegistrationFormFunctions::decode_json_string(Validators::str_from_array($fields, Affiliate::RESERVED_SCHEMA_ENTRY_KEY_CUSTOM_REGISTRATION_DATA));
        if (is_null($old_data)) {
            # NOTE: If this is the case, the Affiliate will lose their old data.
            $old_data = [];
        }
        $all_data = array_merge($old_data, $new_data);
        # Encode the custom fields
        $encoded_data = AffiliateRegistrationFormFunctions::encode_to_json_string($all_data);
        $custom_data_key = Affiliate::RESERVED_SCHEMA_ENTRY_KEY_CUSTOM_REGISTRATION_DATA;

        # Validate the new custom data because the custom data schema entry has already been validated by MikesDataModelTrait::upsert with only the old data
        $result = SchemaEntry::validate($affiliate_schema->entries[$custom_data_key], $encoded_data);
        if (!$result[0]) {
            return __('A value for one of the custom affiliate registration fields was invalid and could not be encoded. Please do not use escaped special characters that can corrupt a database.', 'solid-affiliate');
        }

        if (is_null($encoded_data)) {
            # TODO:2: What do we want the UX to be?
            $encoded_data = '';
        }

        # Merge the custom data in its unstructured text column on Affiliate into the Affiliate fields from the upsert hook
        $all_fields = array_merge($fields, [$custom_data_key => sanitize_text_field($encoded_data)]);
        # Remove all the custom keys from the Affiliate fields that were flat as columns during the upsert so that they could be validated jus like normal schema entries
        $fields_to_upsert = AffiliateRegistrationFormFunctions::remove_from_array($all_fields, $custom_keys);
        # Return the Affiliate fields back to upsert with the added json field to be saved
        return $fields_to_upsert;
    }

    /**
     * The SchemEntry's that are required to be on every affiliate registration form because they are needed to create an Affiliate.
     *
     * @return array<AffiliatePortal::REQUIRED_SCHEMA_ENTRY_KEY_*, SchemaEntry>
     */
    public static function required_affiliate_registration_schema_entries()
    {
        // $filter_on = array_merge(self::REQUIRED_AFFILIATE_REGISTRATION_ONLY_SCHEMA_ENTRIES, [Affiliate::RESERVED_SCHEMA_ENTRY_KEY_AFFILIATE_GROUP_ID]);
        $filter_on = self::REQUIRED_AFFILIATE_REGISTRATION_ONLY_SCHEMA_ENTRIES;
        $entries = array_filter(
            self::_legacy_affiliate_registration_schema()->entries,
            function ($key) use ($filter_on) {
                return in_array($key, $filter_on, true);
            },
            ARRAY_FILTER_USE_KEY
        );
        /** @var array<AffiliatePortal::REQUIRED_SCHEMA_ENTRY_KEY_*, SchemaEntry> $entries */
        return $entries;
    }

    /**
     * The SchemEntry's that can be on the registration form but are also on the Affiliate model, but have different configrations when being used for registration.
     *
     * @return array<Affiliate::KEY_SHARED_WITH_PORTAL_*, SchemaEntry>
     */
    public static function registration_schema_entries_that_are_also_on_affiliate()
    {
        return self::_prebuilt_schema_entries();
    }

    /**
     * @var Schema<string>|null
     */
    private static $schema_cache_affiliate_registration = null;

    /**
     * Returns the Schema representing the Affiliate Registration Form.
     * Will return the static Schema if a custom one has not been set yet.
     *
     * @return Schema<string>
     */
    public static function get_affiliate_registration_schema()
    {
        if (!is_null(self::$schema_cache_affiliate_registration)) {
            return self::$schema_cache_affiliate_registration;
        }

        $schema_str = Validators::str(Settings::get(Settings::KEY_CUSTOM_REGISTRATION_FORM_SCHEMA));

        if (Utils::is_empty($schema_str)) {
            self::$schema_cache_affiliate_registration = self::_legacy_affiliate_registration_schema();
        } else {
            $entries = SchemaSerializer::json_string_to_schema($schema_str)->entries;
            $all_entries = AffiliateRegistrationFormFunctions::merge_required_and_shared_schema_entries(
                $entries,
                self::registration_schema_entries_that_are_also_on_affiliate(),
                self::required_affiliate_registration_schema_entries()
            );
            self::$schema_cache_affiliate_registration = new Schema(['entries' => $all_entries]);
        }

        return self::$schema_cache_affiliate_registration;
    }

    /**
     * The Schema for the affiliate portal forms which include the affiliate group ID SchemaEntry.
     *
     * @param array{for_logged_in_user?: bool} $args 
     * 
     * @return Schema<string|Affiliate::RESERVED_SCHEMA_ENTRY_KEY_AFFILIATE_GROUP_ID>
     */
    public static function get_affiliate_registration_schema_for_portal_forms($args = [])
    {
        $schema = AffiliateRegistrationFormFunctions::schema_with_affiliate_group_id(
            self::get_affiliate_registration_schema(),
            self::_legacy_affiliate_registration_schema()
        );

        ////////////////////////////////////////////////////////////
        // remove the password entry if this is for a logged in user
        if (isset($args['for_logged_in_user']) && $args['for_logged_in_user']) {
            $schema->entries = array_filter(
                $schema->entries,
                function ($key) {
                    return $key !== AffiliatePortal::REQUIRED_SCHEMA_ENTRY_KEY_USER_PASS;
                },
                ARRAY_FILTER_USE_KEY
            );
        }
        ////////////////////////////////////////////////////////////

        return $schema;
    }

    /**
     * Returns the Schema representing the Affiliate Registration Form.
     * Will return the static Schema if a custom one has not been set yet.
     *
     * @return Schema<string>
     */
    public static function get_restore_default_affiliate_registration_schema()
    {
        $all_entries = array_merge(
            self::required_affiliate_registration_schema_entries(),
            // self::registration_schema_entries_that_are_also_on_affiliate()
            self::_prebuilt_schema_entries()
        );

        // below we reorder the entries by key to a sensible order.
        $all_entries = [
            AffiliatePortal::REQUIRED_SCHEMA_ENTRY_KEY_USER_EMAIL => $all_entries[AffiliatePortal::REQUIRED_SCHEMA_ENTRY_KEY_USER_EMAIL],
            AffiliatePortal::REQUIRED_SCHEMA_ENTRY_KEY_USER_LOGIN => $all_entries[AffiliatePortal::REQUIRED_SCHEMA_ENTRY_KEY_USER_LOGIN],
            AffiliatePortal::REQUIRED_SCHEMA_ENTRY_KEY_USER_PASS => $all_entries[AffiliatePortal::REQUIRED_SCHEMA_ENTRY_KEY_USER_PASS],
            Affiliate::KEY_SHARED_WITH_PORTAL_PAYMENT_EMAIL => $all_entries[Affiliate::KEY_SHARED_WITH_PORTAL_PAYMENT_EMAIL],

            Affiliate::KEY_SHARED_WITH_PORTAL_FIRST_NAME => $all_entries[Affiliate::KEY_SHARED_WITH_PORTAL_FIRST_NAME],
            Affiliate::KEY_SHARED_WITH_PORTAL_LAST_NAME => $all_entries[Affiliate::KEY_SHARED_WITH_PORTAL_LAST_NAME],
            Affiliate::KEY_SHARED_WITH_PORTAL_REGISTRATION_NOTES => $all_entries[Affiliate::KEY_SHARED_WITH_PORTAL_REGISTRATION_NOTES],

            AffiliatePortal::REQUIRED_SCHEMA_ENTRY_KEY_ACCEPT_POLICY => $all_entries[AffiliatePortal::REQUIRED_SCHEMA_ENTRY_KEY_ACCEPT_POLICY],
        ];

        return new Schema(['entries' => $all_entries]);
    }

    /**
     * The SchemaEntry that are shared with Affiliate but not configured by the legacy portal settings, so that users can configure these prebuilt fields.
     *
     * @return array<Affiliate::KEY_SHARED_WITH_PORTAL_*, SchemaEntry>
     */
    public static function _prebuilt_schema_entries()
    {
        return array(
            Affiliate::KEY_SHARED_WITH_PORTAL_PAYMENT_EMAIL => new SchemaEntry([
                'type' => 'varchar',
                'required' => false,
                'display_name' => __('Payment Email (PayPal)', 'solid-affiliate'),
                'show_on_non_admin_new_form' => true,
                'show_on_non_admin_edit_form' => true,
                'form_input_description' => __('Your PayPal payment email, to which we will send commission payments. Can be the same as your account email.', 'solid-affiliate'),
                'form_input_wrap_class' => 'sld-ap-form_group',
                'form_input_type_override' => 'email',
                'shows_placeholder' => true
            ]),
            Affiliate::KEY_SHARED_WITH_PORTAL_FIRST_NAME => new SchemaEntry([
                'type' => 'varchar',
                'required' => false,
                'display_name' => __('First Name', 'solid-affiliate'),
                'show_on_non_admin_new_form' => true,
                'show_on_non_admin_edit_form' => true,
                'form_input_description' => __('Your first name.', 'solid-affiliate'),
                'form_input_wrap_class' => 'sld-ap-form_group',
                'shows_placeholder' => true
            ]),
            Affiliate::KEY_SHARED_WITH_PORTAL_LAST_NAME => new SchemaEntry([
                'type' => 'varchar',
                'required' => false,
                'display_name' => __('Last Name', 'solid-affiliate'),
                'show_on_non_admin_new_form' => true,
                'show_on_non_admin_edit_form' => true,
                'form_input_description' => __('Your last name.', 'solid-affiliate'),
                'form_input_wrap_class' => 'sld-ap-form_group',
                'shows_placeholder' => true
            ]),
            Affiliate::KEY_SHARED_WITH_PORTAL_REGISTRATION_NOTES => new SchemaEntry([
                'type' => 'text',
                'form_input_type_override' => 'textarea',
                'required' => false,
                'display_name' => __('How will you promote us?', 'solid-affiliate'),
                'show_on_non_admin_new_form' => true,
                'show_on_non_admin_edit_form' => 'hidden_and_disabled',
                'form_input_description' => __('Let us know a bit about yourself and how you plan on promoting our products.', 'solid-affiliate'),
                'form_input_wrap_class' => 'sld-ap-form_group',
                'shows_placeholder' => true,
                'custom_form_input_attributes' => ['rows' => '5'],
                'persist_value_on_form_submit' => false
            ])
        );
    }

    /**
     * Static affiliate registration form Schema, used if a custom Schema has not been set by the admin.
     *
     * @return Schema<Affiliate::KEY_SHARED_WITH_PORTAL_*|AffiliatePortal::REQUIRED_SCHEMA_ENTRY_KEY_*|Affiliate::RESERVED_SCHEMA_ENTRY_KEY_AFFILIATE_GROUP_ID>
     */
    public static function _legacy_affiliate_registration_schema()
    {
        $affiliate_registration_fields_to_display = Validators::array_of_string(Settings::get(Settings::KEY_AFFILIATE_REGISTRATION_FIELDS_TO_DISPLAY));
        $is_payment_email_displayed = in_array('payment_email', $affiliate_registration_fields_to_display);
        $is_first_name_displayed = in_array('first_name', $affiliate_registration_fields_to_display);
        $is_last_name_displayed = in_array('last_name', $affiliate_registration_fields_to_display);
        $is_registration_notes_displayed = in_array('registration_notes', $affiliate_registration_fields_to_display);
        $required_affiliate_registration_fields = Validators::array_of_string(Settings::get(Settings::KEY_REQUIRED_AFFILIATE_REGISTRATION_FIELDS));
        $is_payment_email_required = in_array('payment_email', $required_affiliate_registration_fields);
        $is_first_name_required = in_array('first_name', $required_affiliate_registration_fields);
        $is_last_name_required = in_array('last_name', $required_affiliate_registration_fields);
        $is_registration_notes_required = in_array('registration_notes', $required_affiliate_registration_fields);

        $accept_affiliate_terms_label = (string)Settings::get(Settings::KEY_TERMS_OF_USE_LABEL);
        $terms_link = esc_url((string)get_permalink(((int)Settings::get(Settings::KEY_TERMS_OF_USE_PAGE))));
        $full_terms_link = '<a href="' . $terms_link . '" target="_blank">' . __($accept_affiliate_terms_label, 'solid-affiliate') . '</a>';

        # NOTE: Quick fix to disable the field on the Affiliate Registration form because when a user is logged in,
        #       their current_user sets the user_email field, not what is put into the form.
        $user_email_show_on_new_form = is_user_logged_in() ? 'hidden_and_disabled' : true;
        # NOTE: Quick fix to disable the field on the Affiliate Registration form because when a user is logged in,
        #       their current_user sets the user_login field, not what is put into the form.
        $user_login_show_on_new_form = is_user_logged_in() ? 'hidden_and_disabled' : true;

        $entries =  array(
            self::REQUIRED_SCHEMA_ENTRY_KEY_USER_LOGIN => new SchemaEntry([
                'type' => 'varchar',
                'required' => true,
                'display_name' => __('Username', 'solid-affiliate'),
                'show_on_non_admin_new_form' => $user_login_show_on_new_form,
                'show_on_non_admin_edit_form' => false,
                'form_input_description' => __('Your username.', 'solid-affiliate'),
                'form_input_callback_key' => SchemaEntry::TEXT_TEXT_KEY,
                'form_input_wrap_class' => 'sld-ap-form_group',
                'shows_placeholder' => false
            ]),
            self::REQUIRED_SCHEMA_ENTRY_KEY_USER_EMAIL => new SchemaEntry([
                'type' => 'varchar',
                'required' => true,
                'display_name' => __('Account Email', 'solid-affiliate'),
                'show_on_non_admin_new_form' => $user_email_show_on_new_form,
                'show_on_non_admin_edit_form' => false,
                'form_input_description' => __('Your primary email, used for logging in.', 'solid-affiliate'),
                'form_input_callback_key' => SchemaEntry::TEXT_EMAIL_KEY,
                'form_input_wrap_class' => 'sld-ap-form_group',
                'shows_placeholder' => false,
                'form_input_type_override' => 'email'
            ]),
            Affiliate::KEY_SHARED_WITH_PORTAL_PAYMENT_EMAIL => new SchemaEntry([
                'type' => 'varchar',
                'required' => $is_payment_email_required,
                'display_name' => __('Payment Email (PayPal)', 'solid-affiliate'),
                'show_on_non_admin_new_form' => $is_payment_email_displayed,
                'show_on_non_admin_edit_form' => $is_payment_email_displayed,
                'form_input_description' => __('Your PayPal payment email, to which we will send commission payments. Can be the same as your account email.', 'solid-affiliate'),
                'form_input_wrap_class' => 'sld-ap-form_group',
                'shows_placeholder' => false,
                'form_input_type_override' => 'email'
            ]),
            self::REQUIRED_SCHEMA_ENTRY_KEY_USER_PASS => new SchemaEntry([
                'type' => 'varchar',
                'required' => true,
                'display_name' => __('Password', 'solid-affiliate'),
                'show_on_non_admin_new_form' => true,
                'show_on_non_admin_edit_form' => false,
                'form_input_description' => __('Your primary password, used for logging in.', 'solid-affiliate'),
                'is_password' => true,
                'form_input_callback_key' => SchemaEntry::TEXT_PASSWORD_KEY,
                'persist_value_on_form_submit' => false,
                'form_input_wrap_class' => 'sld-ap-form_group',
                'shows_placeholder' => false
            ]),
            Affiliate::KEY_SHARED_WITH_PORTAL_FIRST_NAME => new SchemaEntry([
                'type' => 'varchar',
                'required' => $is_first_name_required,
                'display_name' => __('First Name', 'solid-affiliate'),
                'show_on_non_admin_new_form' => $is_first_name_displayed,
                'show_on_non_admin_edit_form' => $is_first_name_displayed,
                'form_input_description' => __('Your first name.', 'solid-affiliate'),
                'form_input_wrap_class' => 'sld-ap-form_group',
                'shows_placeholder' => false
            ]),
            Affiliate::KEY_SHARED_WITH_PORTAL_LAST_NAME => new SchemaEntry([
                'type' => 'varchar',
                'required' => $is_last_name_required,
                'display_name' => __('Last Name', 'solid-affiliate'),
                'show_on_non_admin_new_form' => $is_last_name_displayed,
                'show_on_non_admin_edit_form' => $is_last_name_displayed,
                'form_input_description' => __('Your last name.', 'solid-affiliate'),
                'form_input_wrap_class' => 'sld-ap-form_group',
                'shows_placeholder' => false
            ]),
            Affiliate::KEY_SHARED_WITH_PORTAL_REGISTRATION_NOTES => new SchemaEntry([
                'type' => 'text',
                'form_input_type_override' => 'textarea',
                'required' => $is_registration_notes_required,
                'display_name' => __('How will you promote us?', 'solid-affiliate'),
                'show_on_non_admin_new_form' => $is_registration_notes_displayed,
                'show_on_non_admin_edit_form' => ($is_registration_notes_displayed ? 'hidden_and_disabled' : false),
                'form_input_description' => __('Let us know a bit about yourself and how you plan on promoting our products.', 'solid-affiliate'),
                'form_input_wrap_class' => 'sld-ap-form_group',
                'shows_placeholder' => false,
                'custom_form_input_attributes' => ['rows' => '5'],
                'persist_value_on_form_submit' => false
            ]),
            # TODO:1: Allow FormBuilder to still have the required asterisk even if the title is hidden, but making it its own entity
            self::REQUIRED_SCHEMA_ENTRY_KEY_ACCEPT_POLICY => new SchemaEntry([
                'type' => 'bool',
                'required' => true,
                'display_name' => __('Accept Policy', 'solid-affiliate'),
                'show_on_non_admin_new_form' => true,
                'show_on_non_admin_edit_form' => false,
                'form_input_description' => $full_terms_link,
                'form_input_callback_key' => SchemaEntry::CHECKBOX_SINGLE_KEY,
                'persist_value_on_form_submit' => false,
                'form_input_wrap_class' => 'sld-ap-form_group',
                'form_label_class' => 'sld-ap-form_checkbox',
                'hide_form_input_title' => true,
                'label_for_value' => 'accept-policy'
            ]),
            Affiliate::RESERVED_SCHEMA_ENTRY_KEY_AFFILIATE_GROUP_ID => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'required' => false,
                'display_name' => __('Group ID', 'solid-affiliate'),
                'show_on_non_admin_new_form' => 'hidden',
                'show_on_non_admin_edit_form' => 'hidden',
                'form_input_description' => __('Affiliate Group ID', 'solid-affiliate'),
                'persist_value_on_form_submit' => false
            ])
        );

        return new Schema(['entries' => $entries]);
    }

    /**
     * @return Schema<"user_email"|"user_pass">
     */
    public static function login_schema()
    {
        $entries = array(
            'user_email' => new SchemaEntry([
                'type' => 'varchar',
                'required' => true,
                'display_name' => __('Account Email', 'solid-affiliate'),
                'show_on_new_form' => true,
                'form_input_description' => __('Your primary email, used for logging in.', 'solid-affiliate'),
                'persist_value_on_form_submit' => false
            ]),
            'user_pass' => new SchemaEntry([
                'type' => 'varchar',
                'required' => true,
                'display_name' => __('Password', 'solid-affiliate'),
                'show_on_new_form' => true,
                'form_input_description' => __('Your primary password, used for logging in.', 'solid-affiliate'),
                'is_password' => true,
                'persist_value_on_form_submit' => false
            ]),
        );

        return new Schema(['entries' => $entries]);
    }

    /**
     * Takes Affiliate Registration parameters and either creates a user + affiliate or just an affiliate and
     * associates it to an existing User. Also deals with catching errors and returning error messages to the caller.
     *
     * @param string $user_login
     * @param string $user_email
     * @param array<string, mixed> $affiliate_fields
     *
     * @return Either<int> - The Affiliate ID.
     */
    public static function handle_affiliate_registration_submission($user_login, $user_email, $affiliate_fields)
    {
        $maybe_user_password = Validators::str_from_array($affiliate_fields, 'user_pass');
        $affiliate_group_id = Validators::int_from_array($affiliate_fields, 'affiliate_group_id');
        $maybe_user = get_user_by('email', $user_email);
        // TODO this is a big one. Ideally the compiler could check that the setting key is correct,
        // the type of the returned setting value, and also that 'pending' and 'approved' are both valid values for Affiliate -> status.
        $affiliate_status = Settings::get(Settings::KEY_IS_REQUIRE_AFFILIATE_REGISTRATION_APPROVAL) ? 'pending' : 'approved';

        $affiliate_fields = array_merge($affiliate_fields, ['status' => $affiliate_status]);
        $affiliate_fields = AffiliateRegistrationFormFunctions::remove_from_array(
            $affiliate_fields,
            self::REQUIRED_AFFILIATE_REGISTRATION_ONLY_SCHEMA_ENTRIES
        );

        if (AffiliateGroup::find($affiliate_group_id)) {
            $affiliate_fields['affiliate_group_id'] = $affiliate_group_id;
            $should_skip_adding_to_default_group = true;
        } else {
            $should_skip_adding_to_default_group = false;
        }

        // @todo this could use refactoring to reduce complexity
        if ($maybe_user instanceof \WP_User) {
            $eitherUserId = new Either([''], $maybe_user->ID, true);
            $created_new_user = false;
        } else {
            $first_name = Validators::str_from_array($affiliate_fields, 'first_name');
            $last_name = Validators::str_from_array($affiliate_fields, 'last_name');
            $eitherUserId = AffiliatePortal::create_new_user($user_login, $user_email, $maybe_user_password, $first_name, $last_name);
            $created_new_user = true;
        }

        if ($eitherUserId->isRight) {
            $eitherAffiliateId = AffiliatePortal::create_affiliate_for_existing_user($eitherUserId->right, $affiliate_fields, $should_skip_adding_to_default_group);

            # NOTE: Delete the WP User if the Affiliate creation fails, so that the User does not get locked out of using the same User info (email, login, etc.) when trying again.
            if ($eitherAffiliateId->isLeft && $created_new_user) {
                if (!function_exists('wp_delete_user')) {
                    /** @psalm-suppress MissingFile */
                    require_once(ABSPATH . 'wp-admin/includes/user.php');
                }
                \wp_delete_user($eitherUserId->right);
            }

            return $eitherAffiliateId;
        }

        return new Either($eitherUserId->left, 0, false);
    }


    /**
     * Creates a new Affiliate and assocates it with an existing User. Also deals with catching errors and returning error messages to the caller.
     *
     * @param int $user_id
     * @param array<string, mixed> $affiliate_fields
     * @param bool $should_skip_adding_to_default_group
     *
     * @return Either<int> - The Affiliate ID.
     */
    public static function create_affiliate_for_existing_user($user_id, $affiliate_fields, $should_skip_adding_to_default_group)
    {
        $affiliate_fields['user_id'] = $user_id;

        // $maybe_existing_affiliate = Affiliate::for_user_id($user_id);
        // if ($maybe_existing_affiliate instanceof Affiliate) {
        //     return new Either([''], $maybe_existing_affiliate->id, true);
        // }

        $eitherUpsert = Affiliate::upsert($affiliate_fields, true);

        if ($eitherUpsert->isRight) {
            $affiliate_id = $eitherUpsert->right;
            if (!$should_skip_adding_to_default_group) {
                AffiliateGroup::maybe_add_affiliate_to_default_group($affiliate_id);
            }
            /**
             * Fires after a new Affiliate has registered successfully.
             *
             * Affiliate is a status that could be applied to both new and existing users; as such, the creation
             * a new Affiliate does not translate into the creation of a new user.
             *
             * @since TBD
             *
             * @param int $affiliate_id The ID of the Affiliate that just registered, this is NOT the same as the
             *                          User ID.
             * @param int $user_id      The WordPress User ID of the Affiliate that just registered.
             */
            do_action("solid_affiliate/Affiliate/new_registration/success", $affiliate_id, $user_id);
            return new Either([''], $affiliate_id, true);
        } else {
            /**
             * Fires after a new Affiliate has attempted to register and the registration failed.
             *
             * Affiliate is a status that could be applied to both new and existing users; as such, the creation
             * a new Affiliate does not translate into the creation of a new user.
             *
             * @since TBD
             *
             * @param int $user_id The ID of the affiliate that just registered.
             */
            do_action("solid_affiliate/Affiliate/new_registration/fail", $user_id);
            return new Either($eitherUpsert->left, 0, false);
        }
    }


    /**
     * Creates a new User in the DB. Also deals with catching errors and returning error messages to the caller.
     *
     * @param string $user_login
     * @param string $user_email
     * @param string $user_password
     * @param string $first_name
     * @param string $last_name
     *
     * @return Either<int> - The User ID.
     */
    public static function create_new_user($user_login, $user_email, $user_password, $first_name = '', $last_name = '')
    {
        $user_args = array(
            'user_login'    => $user_login,
            'user_email'    => $user_email,
            'user_pass'     => $user_password,
            // TODO
            'first_name'    => $first_name,
            'last_name'     => $last_name,
        );

        do_action("solid_affiliate/AffiliatePortal/before_create_new_user", $user_args);
        $maybe_user_id = wp_insert_user($user_args);

        if ($maybe_user_id instanceof \WP_Error) {
            $error_messages = $maybe_user_id->get_error_messages();

            return new Either($error_messages, 0, false);
        } else {
            return new Either([''], $maybe_user_id, true);
        }
    }
}
