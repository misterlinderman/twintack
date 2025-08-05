<?php

namespace SolidAffiliate\Addons;

use SolidAffiliate\Lib\Capabilities;
use SolidAffiliate\Lib\ControllerFunctions;
use SolidAffiliate\Lib\FF;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\Utils;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\AddonDescription;
use SolidAffiliate\Lib\VO\RouteDescription;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Views\Shared\AdminHeader;

/**
 */
class Core
{
    const OPTIONS_KEY_PREFIX = "solid_affiliate_addons";
    const OPTIONS_KEY_ENABLED_ADDONS = self::OPTIONS_KEY_PREFIX . "-enabled-addons";
    const POST_ACTION_TOGGLE_ADDON = "solid_affiliate-toggle-addon";
    const POST_ACTION_UPDATE_ADDON_SETTINGS = "solid_affiliate-update-addon-settings";
    const ADDON_SLUG_KEY = "solid_affiliate-addon-slug";
    const ADDONS_PAGE_SLUG = 'solid-affiliate-addons';

    /**
     * Loads all the addons.
     *
     * @return void
     */
    public static function register_hooks_for_all_addons()
    {
        // return;
        // First it renders the Addons page.
        add_action("solid_affiliate/admin/submenu_pages/after", [self::class, "add_addons_submenu_page_to_wordpress_admin"]);
        add_filter("solid_affiliate/PostRequestController/routes", [self::class, "register_post_routes_for_addons"]);

        // Then it loads all the addons by finding the Main.php file in each addon folder.
        // TODO figure out how to auto-load the add-ons in the folder...eventually.
        \SolidAffiliate\Addons\AutoCreateAffiliatesForNewUsers\Addon::register_hooks();
        // \SolidAffiliate\Addons\DisableGateways\Addon::register_hooks();
        \SolidAffiliate\Addons\AutoCreateCoupons\Addon::register_hooks();
        \SolidAffiliate\Addons\DataExport\Addon::register_hooks();
        \SolidAffiliate\Addons\AffiliateLandingPages\Addon::register_hooks();
        \SolidAffiliate\Addons\StoreCredit\Addon::register_hooks();

        \SolidAffiliate\Addons\AutoCreateAffiliatesForNewUsers\Addon::register_if_enabled_hooks();
        // \SolidAffiliate\Addons\DisableGateways\Addon::register_if_enabled_hooks();
        \SolidAffiliate\Addons\AutoCreateCoupons\Addon::register_if_enabled_hooks();
        \SolidAffiliate\Addons\DataExport\Addon::register_if_enabled_hooks();
        \SolidAffiliate\Addons\AffiliateLandingPages\Addon::register_if_enabled_hooks();
        \SolidAffiliate\Addons\StoreCredit\Addon::register_if_enabled_hooks();
    }

    /**
     * Registers a submenu page for the Solid Affiliate plugin.
     *
     * @param string $menu_slug - the Slug of the parent menu.
     * @return void
     */
    public static function add_addons_submenu_page_to_wordpress_admin($menu_slug)
    {
        add_submenu_page(
            $menu_slug,
            'Solid Affiliate - Addons',
            __('Addons', 'solid-affiliate'),
            Capabilities::MANAGE_AFFILIATE_PROGRAM,
            self::ADDONS_PAGE_SLUG,
            function () {
                echo AdminHeader::render_from_get_request($_GET);
                // if the request includes a settings_page parameter, then we render the settings page for that addon.
                if (isset($_GET['settings_page']) && !empty($_GET['settings_page'])) {
                    $slug = (string)$_GET['settings_page'];
                    echo self::render_addon_settings_page_for_slug($slug);
                } else {
                    echo self::render_addons_dashboard();
                }
            }
        );
    }

    /**
     * @param RouteDescription[] $route_descriptions
     * @return RouteDescription[]
     */
    public static function register_post_routes_for_addons($route_descriptions)
    {
        $route_descriptions[] = new RouteDescription([
            'post_param_key' => self::POST_ACTION_TOGGLE_ADDON,
            'nonce' => self::POST_ACTION_TOGGLE_ADDON,
            'callback' => function () {
                if (isset($_POST[self::ADDON_SLUG_KEY])) {
                    $addon_slug = (string)$_POST[self::ADDON_SLUG_KEY];
                    self::toggle_addon($addon_slug);
                    return ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK');
                } else {
                    return ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK');
                }
            },
        ]);

        $route_descriptions[] = new RouteDescription([
            'post_param_key' => self::POST_ACTION_UPDATE_ADDON_SETTINGS,
            'nonce' => self::POST_ACTION_UPDATE_ADDON_SETTINGS,
            'callback' => function () {
                if (isset($_POST[self::ADDON_SLUG_KEY])) {
                    $slug = (string)$_POST[self::ADDON_SLUG_KEY];
                    $description = self::get_addon_description_for_slug($slug);
                    if ($description instanceof AddonDescription) {
                        $schema = $description->settings_schema;
                        self::update_addon_settings_from_post_data($_POST, $schema, $slug);
                    }
                    ///// Handle extracting the settings
                    return ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK');
                } else {
                    return ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK');
                }
            },
        ]);

        return $route_descriptions;
    }

    /**
     * Renders the addons page within the Admin menu.
     *
     * @return string
     */
    public static function render_addons_dashboard()
    {
        $addon_descriptions = self::get_all_addon_descriptions();
        ob_start();
?>

        <div class="wrap">
            <h1></h1>
            <div class="all-addons">
                <?php foreach ($addon_descriptions as $addon_description) { ?>
                    <div class='addon-card <?php if (self::is_addon_enabled($addon_description->slug)) {
                                                echo "active";
                                            } ?>' data-addon-slug="<?php echo ($addon_description->slug) ?>">
                        <div class="addon-card_wrapper">
                            <h2>
                                <?php echo $addon_description->name; ?>
                                <?php echo $addon_description->ff_disabled ? FF::pill() : ''; ?>
                            </h2>

                            <div class="addon-card_author">
                                By <a href="https://solidaffiliate.com"><?php echo $addon_description->author ?></a>
                            </div>
                            <p><?php echo $addon_description->description ?></p>
                        </div>
                        <div class="addon-card_cta">
                            <div class="addon-card_cta-wrapper">
                                <form action="" method="post">
                                    <?php wp_nonce_field(self::POST_ACTION_TOGGLE_ADDON); ?>
                                    <input type="hidden" name="<?php echo self::ADDON_SLUG_KEY ?>" value="<?php echo $addon_description->slug ?>">
                                    <?php if (self::is_addon_enabled($addon_description->slug)) { ?>
                                        <div class="addon-card_cta-flex">
                                            <input type="checkbox" class="<?php if (self::is_addon_enabled($addon_description->slug)) {
                                                                                echo "checked";
                                                                            } ?>" onChange="submit()" name="<?php echo (self::POST_ACTION_TOGGLE_ADDON) ?>">
                                            <label class="active"><?php _e('Enabled', 'solid-affiliate') ?></label>
                                            <a class="button" style="margin-left:auto" href="<?php echo ($addon_description->documentation_url) ?>" target="_blank">
                                                <?php _e('Details', 'solid-affiliate') ?>
                                            </a>
                                            <a class="button setting" href="<?php echo (self::url_for_addon_settings($addon_description->slug)) ?>">
                                                <?php _e('Settings', 'solid-affiliate') ?>
                                            </a>
                                        </div>
                                    <?php
                                    } else { ?>
                                        <div class="addon-card_cta-flex">
                                            <?php if ($addon_description->ff_disabled) { ?>
                                                <input disabled type="checkbox" onChange="" name="<?php echo (self::POST_ACTION_TOGGLE_ADDON) ?>">
                                            <?php } else { ?>
                                                <input type="checkbox" onChange="submit()" name="<?php echo (self::POST_ACTION_TOGGLE_ADDON) ?>">
                                            <?php } ?>
                                            <label><?php _e('Disabled', 'solid-affiliate') ?></label>
                                            <a style="margin-left:auto" class="button" href="<?php echo ($addon_description->documentation_url) ?>" target="_blank">
                                                <?php _e('Details', 'solid-affiliate') ?>
                                            </a>
                                            <a class="button setting" href="<?php echo (self::url_for_addon_settings($addon_description->slug)) ?>">
                                                <?php _e('Settings', 'solid-affiliate') ?>
                                            </a>
                                        </div>
                                    <?php
                                    } ?>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php
                } ?>
            </div>
        <?php
        return ob_get_clean();
    }

    /**
     * @param string $slug
     * 
     * @return string
     */
    public static function render_addon_settings_page_for_slug($slug)
    {
        ob_start();
        ?>
            <div class="wrap">
                <h1></h1>
                <a class="button goback" href='admin.php?page=<?php echo (self::ADDONS_PAGE_SLUG) ?>'>
                    <svg width="6" height="10" viewBox="0 0 6 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5.5575 1.5575L4.5 0.5L0 5L4.5 9.5L5.5575 8.4425L2.1225 5L5.5575 1.5575Z" fill="#000" />
                    </svg>
                    <?php _e('Back to all addons', 'solid-affiliate') ?>
                </a>
                <?php
                if (!self::is_addon_enabled($slug)) {
                    echo "<div class='addons-note'><strong>Important</strong> : This addon is disabled. If you want to use it, please make sure to enable it.</div>";
                }
                ?>
                <div class="sld-card medium setting">
                    <?php do_action("solid_affiliate/addons/before_settings_form/$slug") ?>

                    <?php
                    $settings = (object)\SolidAffiliate\Addons\Core::get_settings_for_slug($slug);
                    $addon_description = self::get_addon_description_for_slug($slug);
                    if ($addon_description instanceof AddonDescription) {
                        if (self::do_settings_exist($addon_description)) {
                            echo FormBuilder::render_crud_form_edit($addon_description->settings_schema, self::POST_ACTION_UPDATE_ADDON_SETTINGS, self::POST_ACTION_UPDATE_ADDON_SETTINGS, 'id-addon-settings-form', 'Settings', $settings, [self::ADDON_SLUG_KEY, $slug]);
                        }
                    } else {
                        echo "No addon description found for {$slug}";
                    }
                    ?>

                    <?php do_action("solid_affiliate/addons/after_settings_form/$slug") ?>
                </div>
        <?php
        return ob_get_clean();
    }

    /**
     * @return AddonDescription[]
     */
    public static function get_all_addon_descriptions()
    {
        $v = Utils::solid_transient(function () {
            return \SolidAffiliate\Lib\Validators::arr_of_addon_description(apply_filters("solid_affiliate/addons/addon_descriptions", []));
        }, 'solid_affiliate_addon_descriptions', 10); // TODO-ff 60 * 10

        return $v;
    }

    /**
     * @param string $slug
     *
     * @return AddonDescription|false
     */
    public static function get_addon_description_for_slug($slug)
    {
        $descriptions = self::get_all_addon_descriptions();
        // find the first item in the array which matches the slug, if not found, return null.
        foreach ($descriptions as $description) {
            if ($description->slug === $slug) {
                return $description;
            }
        }
        return false;
    }

    /**
     * Check if an addon is enabled.
     *
     * @param string $addon_slug
     * @return boolean
     */
    public static function is_addon_enabled($addon_slug)
    {
        return in_array($addon_slug, self::get_enabled_addons());
    }

    /**
     * @return string[]
     */
    public static function get_enabled_addons()
    {
        $enabled_addons = Validators::array_of_string(
            get_option(self::OPTIONS_KEY_ENABLED_ADDONS, self::addon_slugs_enabled_by_default())
        );

        return FF::filter_enabled_addons($enabled_addons);
    }

    /**
     * Returns the addon slug for each addon that is enabled by default.
     *
     * @return string[]
     */
    public static function addon_slugs_enabled_by_default()
    {
        return array_filter(array_map(function ($desc) {
            if ($desc->enabled_by_default) {
                return $desc->slug;
            }
        }, self::get_all_addon_descriptions()));
    }

    /**
     * @param string $addon_slug
     * @return void
     */
    public static function toggle_addon($addon_slug)
    {
        // toggle the string in the array.
        if (in_array($addon_slug, self::get_enabled_addons())) {
            $enabled_addons = self::get_enabled_addons();
            $enabled_addons = array_diff($enabled_addons, [$addon_slug]);
            update_option(self::OPTIONS_KEY_ENABLED_ADDONS, $enabled_addons);
        } else {
            $enabled_addons = self::get_enabled_addons();
            $enabled_addons[] = $addon_slug;
            update_option(self::OPTIONS_KEY_ENABLED_ADDONS, $enabled_addons);
        }
    }

    /**
     * @param string $addon_slug
     * @return array
     */
    public static function get_settings_for_slug($addon_slug)
    {
        $options_key_for_this_addon = self::OPTIONS_KEY_PREFIX . $addon_slug;
        $settings = Validators::arr(get_option($options_key_for_this_addon, []));
        $defaults = self::get_default_settings_for_slug($addon_slug);

        $settings = array_merge($defaults, $settings);

        return $settings;
    }

    /**
     * 
     * @param string $addon_slug
     * @return array
     */
    public static function get_default_settings_for_slug($addon_slug)
    {
        $addon_description = self::get_addon_description_for_slug($addon_slug);
        if ($addon_description instanceof AddonDescription) {
            return SchemaFunctions::defaults_from_schema($addon_description->settings_schema);
        } else {
            return [];
        }
    }

    /**
     * @param string $addon_slug
     * @param string $key
     *
     * @return mixed
     */
    public static function get_addon_setting($addon_slug, $key)
    {
        $settings = self::get_settings_for_slug($addon_slug);

        if (isset($settings[$key])) {
            return $settings[$key];
        } else {
            return null;
        }
    }


    /**
     * @param string $addon_slug
     * @param array $new_settings
     *
     * @return bool Whether the settings were updated.
     */
    public static function set_settings_for_slug($addon_slug, $new_settings)
    {
        $options_key_for_this_addon = self::OPTIONS_KEY_PREFIX . $addon_slug;
        $settings = array_merge(self::get_settings_for_slug($addon_slug), $new_settings);
        return update_option($options_key_for_this_addon, $settings);
    }


    /**
     * Given the $_POST data, a Schema, and a slug, update the settings for the addon.
     *
     * @param array $post
     * @param Schema $schema
     * @param string $addon_slug
     *
     * @return bool - If the settings were updated successfully.
     */
    public static function update_addon_settings_from_post_data($post, $schema, $addon_slug)
    {
        // extract the settings values from the $_POST data.
        $eitherFields = ControllerFunctions::extract_and_validate_POST_params($post, SchemaFunctions::keys($schema), $schema);
        if ($eitherFields->isLeft) {
            return false;
        } else {
            return self::set_settings_for_slug($addon_slug, $eitherFields->right);
        }
    }

    /**
     * The admin url for the Addon's settings page.
     *
     * @param string $addon_slug
     *
     * @return string
     */
    public static function url_for_addon_settings($addon_slug)
    {
        return admin_url(sprintf('admin.php?page=%1$s&settings_page=%2$s', self::ADDONS_PAGE_SLUG, $addon_slug));
    }

    /**
     * Whether or not the Addon has any settings.
     *
     * @param AddonDescription $addon_description
     *
     * @return boolean
     */
    private static function do_settings_exist($addon_description)
    {
        return !empty($addon_description->settings_schema->entries);
    }
}
