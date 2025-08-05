<?php

namespace SolidAffiliate\Addons\AutoCreateAffiliatesForNewUsers;

use SolidAffiliate\Addons\AddonInterface;
use SolidAffiliate\Lib\DevHelpers;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\AddonDescription;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;


/**
 * @psalm-import-type EnumOptionsReturnType from \SolidAffiliate\Lib\VO\SchemaEntry
 */
class Addon implements AddonInterface
{
    /** @var string */
    const ADDON_SLUG = 'auto-create-affiliates-for-new-users';

    /** @var AddonDescription|null */
    private static $description_cache = null;


    /**
     * This is the function which gets called when the Addon is loaded.
     * This is the entry point for the Addon.
     * 
     * Register your Addon by using the
     * "solid_affiliate/addons/addon_descriptions" filter.
     * 
     * Then check if your Addon is enabled, and if so do your stuff.
     *
     * @return void
     */
    public static function register_hooks()
    {
        add_filter("solid_affiliate/addons/addon_descriptions", [self::class, "register_addon_description"]);
    }

    /**
     * This is the function which includes a call to Core::is_addon_enabled() to check if the addon is enabled.
     * 
     * Do not put anything in the register_hooks above besides add_filter and add_action calls. 
     *
     * @return void
     */
    public static function register_if_enabled_hooks()
    {
        if (\SolidAffiliate\Addons\Core::is_addon_enabled(self::ADDON_SLUG)) {
            add_action("user_register", [self::class, "create_affiliate_on_user_register"]);
        }
    }

    /**
     * This function is required.
     * 
     * Return a declatrative description of the addon.
     * 
     * The returned AddonDescription is used by \SolidAffiliate\Addons\Core 
     * to display the addon in the admin panel,
     * handle the settings and so on.
     * 
     * @param AddonDescription[] $addon_descriptions
     * @return AddonDescription[]
     */
    public static function register_addon_description($addon_descriptions)
    {
        $addon_descriptions[] = self::get_addon_description();
        return $addon_descriptions;
    }

    /**
     * @return AddonDescription
     */
    public static function get_addon_description()
    {
        if (!is_null(self::$description_cache)) {
            return self::$description_cache;
        }

        $roles = self::get_all_available_roles();

        $settings_schema = new Schema(["entries" => [
            "send-email-notification-to-admin" => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Send email notification to admin', 'solid-affiliate'),
                'user_default' => true,
                'form_input_description' => __('When enabled, an email notification will be sent to your affiliate managers whenever an affiliate is created by this add on.', 'solid-affiliate'),
                'required' => false,
                'show_on_edit_form' => true,
            )),
            "send-email-notification-to-affiliate" => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Send email notification to affiliate', 'solid-affiliate'),
                'user_default' => true,
                'form_input_description' => __('When enabled, an email notification will be sent to the Affiliate which was just created by this add on.', 'solid-affiliate'),
                'required' => false,
                'show_on_edit_form' => true,
            )),
            "registration-notes" => new SchemaEntry(array(
                'type' => 'text',
                'form_input_type_override' => 'textarea',
                'display_name' => __('Registration Notes', 'solid-affiliate'),
                'user_default' => __('This affiliate was auto-created for a new user by Solid Affiliate via the "Auto-register new users as Affiliates" addon.', 'solid-affiliate'),
                'form_input_description' => __('Enter the default registration notes that will be added to the affiliate registration.', 'solid-affiliate'),
                'required' => false,
                'show_on_edit_form' => true,
            )),
            "user-roles-to-ignore" => new SchemaEntry(array(
                'type' => 'multi_checkbox',
                'is_enum' => true,
                'enum_options' => $roles,
                'display_name' => __('User roles to ignore', 'solid-affiliate'),
                'user_default' => [],
                'form_input_description' => __('You can select any roles which should not result in an affiliate being created.', 'solid-affiliate'),
                'required' => false,
                'show_on_edit_form' => true,
            )),
        ]]);

        self::$description_cache = new AddonDescription([
            'slug' => (string)self::ADDON_SLUG,
            'name' => __('Auto-register new users as Affiliates', 'solid-affiliate'),
            'description' => __('Automatically create an affiliate account for all new users who register a user account.', 'solid-affiliate'),
            'author' => 'Solid Affiliate',
            'graphic_src' => 'https://app-cdn.clickup.com/dashboards.0b111a6875dfa3f40e0f.png',
            'settings_schema' => $settings_schema,
            'documentation_url' => 'https://docs.solidaffiliate.com/auto-register-new-users-as-affiliates/',
        ]);

        return self::$description_cache;
    }

    /**
     * @param int $user_id
     * @return void
     */
    public static function create_affiliate_on_user_register($user_id)
    {
        ////////////////////////////////////////////////////////////////////////////////
        // If the user is being created by Solid Affiliate's core affiliate registration flow,
        // then we don't want to create an affiliate for them. 
        if (did_action('solid_affiliate/AffiliatePortal/before_create_new_user')) {
            return;
        }
        ////////////////////////////////////////////////////////////////////////////////

        ////////////////////////////////////////////////////////////////////////////////
        // Return false if the user has roles that should be ignored.
        $user_roles_to_ignore = Validators::array_of_string(
            \SolidAffiliate\Addons\Core::get_addon_setting((string)self::ADDON_SLUG, 'user-roles-to-ignore')
        );

        $user = get_user_by('id', $user_id);
        if ($user instanceof \WP_User) {
            $user_roles = $user->roles;
            if (count(array_intersect($user_roles_to_ignore, $user_roles)) > 0) {
                return;
            }
        } else {
            return;
        }
        ////////////////////////////////////////////////////////////////////////////////


        ////////////////////////////////////////////////////////////////////////////////
        // Stop Solid Affiliate sending emails depending on the addon settings.
        $should_send_email_to_admin = (bool)\SolidAffiliate\Addons\Core::get_addon_setting(self::ADDON_SLUG, 'send-email-notification-to-admin');

        if (!$should_send_email_to_admin) {
            remove_action('solid_affiliate/Affiliate/new_registration/success', [\SolidAffiliate\Lib\Email_Notifications::class, 'on_new_affiliate_registration_success'], 10);
        }

        $should_send_email_to_affiliate = (bool)\SolidAffiliate\Addons\Core::get_addon_setting(self::ADDON_SLUG, 'send-email-notification-to-affiliate');
        if (!$should_send_email_to_affiliate) {
            remove_action(DevHelpers::AFFILIATE_APPROVED, [\SolidAffiliate\Lib\Email_Notifications::class, 'on_affiliate_approved'], 10);
        }
        ////////////////////////////////////////////////////////////////////////////////


        ////////////////////////////////////////////////////////////////////////////////
        // Create the affiliate.
        $registration_notes = (string)\SolidAffiliate\Addons\Core::get_addon_setting(self::ADDON_SLUG, 'registration-notes');
        $user = get_user_by('id', $user_id);
        if ($user instanceof \WP_User) {
            $affiliate_status = \SolidAffiliate\Lib\Settings::get(
                \SolidAffiliate\Lib\Settings::KEY_IS_REQUIRE_AFFILIATE_REGISTRATION_APPROVAL
            ) ? 'pending' : 'approved';
            $affiliate_fields = [
                'payment_email' => '',
                'status' => $affiliate_status,
                'registration_notes' => $registration_notes,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name
            ];

            \SolidAffiliate\Models\AffiliatePortal::create_affiliate_for_existing_user($user->ID, $affiliate_fields, false);
            return;
        }
        ////////////////////////////////////////////////////////////////////////////////
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////
    // Helper Functions
    /////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Gets all the available roles and returns them as an array of tuples.
     * 
     * Example: 
     * [
     *  ['administrator', 'Administrator'],
     *  ['editor', 'Editor'],
     *  ['author', 'Author'],
     * ]
     *
     * @psalm-suppress UnusedVariable
     * 
     * @return EnumOptionsReturnType
     */
    private static function get_all_available_roles()
    {
        global $wp_roles;
        $a = wp_roles()->get_names();
        // zip the above associative array into a [[key_name, role_name], [key_name, role_name]] array
        $a = array_map(function ($key, $value) {
            return [Validators::str($key), $value];
        }, array_keys($a), $a);

        return $a;
    }
}
