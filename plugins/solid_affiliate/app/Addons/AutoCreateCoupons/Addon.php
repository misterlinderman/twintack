<?php

namespace SolidAffiliate\Addons\AutoCreateCoupons;

use SolidAffiliate\Addons\AddonInterface;
use SolidAffiliate\Addons\Core;
use SolidAffiliate\Lib\ControllerFunctions;
use SolidAffiliate\Lib\DevHelpers;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\RandomData;
use SolidAffiliate\Lib\VO\AddonDescription;
use SolidAffiliate\Lib\VO\RouteDescription;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Models\Affiliate;

class Addon implements AddonInterface
{
    const ADDON_SLUG = 'auto-create-coupons';
    const DOCS_URL = 'https://docs.solidaffiliate.com/automatic-affiliate-coupons/';
    const KEY_COUPON_TEMPLATE_ID = "coupon_template";

    /**
     * This is the function which gets called when the Addon is loaded.
     * This is the entry point for the Addon.
     *
     * Register your Addon by using the "solid_affiliate/addons/addon_descriptions" filter.
     *
     * Then check if your Addon is enabled, and if so do your stuff.
     *
     * @return void
     */
    public static function register_hooks()
    {
        add_filter("solid_affiliate/addons/addon_descriptions", [self::class, "register_addon_description"]);
        add_action("solid_affiliate/addons/after_settings_form/" . self::ADDON_SLUG, [self::class, "after_settings_form"]);
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
        if (Core::is_addon_enabled(self::ADDON_SLUG)) {
            add_filter("solid_affiliate/PostRequestController/routes", [self::class, "register_routes"]);

            if (self::is_a_valid_coupon_template_selected()) {
                add_action(DevHelpers::AFFILIATE_APPROVED, [self::class, "maybe_create_coupon_for_affiliate"]);
                add_action('edit_form_top', [self::class, "render_coupon_template_notice"]);
                add_action('trashed_post', [self::class, "maybe_unset_trashed_coupon_template"]);
                add_action('deleted_post', [self::class, "maybe_unset_deleted_coupon_template"], 10, 2);
            } else {
                add_action('edit_form_top', [self::class, "render_coupon_template_needed_notice"]);
            }
        }
    }

    /**
     * The returned AddonDescription is used by \SolidAffiliate\Addons\Core
     * to display the addon in the admin panel, handle the settings, etc.
     *
     * @param AddonDescription[] $addon_descriptions
     * @return AddonDescription[]
     */
    public static function register_addon_description($addon_descriptions)
    {
        $settings_schema = new Schema(["entries" => [
            self::KEY_COUPON_TEMPLATE_ID => new SchemaEntry(array(
                'type'                         => 'bigint',
                'length'                       => 20,
                'form_input_description'       => __('The WooCommerce Coupon that will be used as a template for auto-generated coupons. Each affiliate will get their own coupon, with a unique code, using the settings from the coupon template.', 'solid-affiliate'),
                'form_input_type_override'     => 'woocommerce_coupon_select',
                'required'                     => true,
                'display_name'                 => __('Coupon Template', 'solid-affiliate'),
                'custom_form_input_attributes' => ['min' => '1'],
                'show_on_edit_form'            => true
            ))
        ]]);

        $description = new AddonDescription([
            'slug' => self::ADDON_SLUG,
            'name' => __('Auto Create a Coupon for all New and Approved Affiliates', 'solid-affiliate'),
            'description' => __('Automatically creates a coupon whenever a new affiliate is created and has the status of', 'solid-affiliate') . ' <code>' . Affiliate::APPROVED_STATUS . '</code>.',
            'author' => 'Solid Affiliate',
            'graphic_src' => '',
            'settings_schema' => $settings_schema,
            'documentation_url' => self::DOCS_URL,
        ]);

        $addon_descriptions[] = $description;
        return $addon_descriptions;
    }

    /**
     * @return void
     */
    public static function after_settings_form()
    {
        // echo out a button    
        echo ("<div style='padding:20px'><h2>Actions</h2>");
        echo ("<h3>" . __('Create a coupon for each of your existing affiliates', 'solid-affiliate') . "</h3>");
        echo ("<p> " . __('If you want to bulk create and assign coupons to each of your <strong><em>existing</em></strong> affiliates, you can do so here.', 'solid-affiliate') . "</p>");
        echo ("<p> " . __('Step 1) Ensure that you have selected a coupon template above, and you have saved the setting and refreshed the page.', 'solid-affiliate') . "</p>");
        echo ("<p> " . __('Step 2) Click the button below. This action is not reversable.', 'solid-affiliate') . "</p>");

        // echo a POST button
        $param_key = 'sld_affiliate-create_coupon_for_each_existing_affiliate';
        $form = '<form action="" method="post" id="' . $param_key . '">' .
            wp_nonce_field($param_key) .
            '<input type="submit" name="' . $param_key . '" id="createCouponButton" class="button" value="Create coupons for each of your affiliates" onclick="var e=this;setTimeout(function(){e.disabled=true; e.value=\'Processing...\'},0);return true;">' .
            '</form></div<';

        echo ($form);
    }

    /**
     * Register a POST route for each resource download.
     *
     * @param RouteDescription[] $routes
     *
     * @return RouteDescription[]
     */
    public static function register_routes($routes)
    {
        $param_key = 'sld_affiliate-create_coupon_for_each_existing_affiliate';
        $download_routes = array(
            new RouteDescription([
                'post_param_key' => $param_key,
                'nonce' => $param_key,
                'callback' => function () {
                    self::POST_handle_create_coupon_for_each_existing_affiliate();
                }
            ]),
        );

        return array_merge($routes, $download_routes);
    }

    /**
     * @return void
     */
    public static function POST_handle_create_coupon_for_each_existing_affiliate()
    {
        $error_or_count_of_coupons_created = self::create_coupon_for_every_affiliate();

        if (is_int($error_or_count_of_coupons_created)) {
            ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', [], [sprintf(__('Successfully created %1$s coupons.', 'solid-affiliate'), $error_or_count_of_coupons_created)]);
        } else {
            $error_message = $error_or_count_of_coupons_created['error'];
            ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', ['There was an error creating the coupons. ' . $error_message]);
        }

        ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', ['test error']);
    }

    /**
     * Creates a WooCommerce coupon from specific options.
     *
     * @param float $rate
     * @param 'percentage'|'flat' $rate_type
     * @param bool $is_individual_use
     * @param bool $is_exclude_sales_items
     * 
     * @return int|false - The ID of the newly created coupon.
     */
    public static function create_new_woocommerce_coupon($rate, $rate_type, $is_individual_use, $is_exclude_sales_items)
    {
        try {
            // Create the woocommerce coupon from the input params. make a random slug
            $coupon = new \WC_Coupon();

            $coupon->set_code('SolidAffiliate' . '-' . RandomData::string(4));
            // convert to either 'percent' or 'fixed_cart'
            $coupon->set_discount_type($rate_type === 'percentage' ? 'percent' : 'fixed_cart');
            $coupon->set_amount($rate);
            // set a description for the coupon - that this was auto created by solid affiliate during setup. don't delete!
            $coupon->set_description('This special coupon was auto created by Solid Affiliate during affiliate setup. Do not delete!');
            $coupon->set_individual_use($is_individual_use);
            $coupon->set_exclude_sale_items($is_exclude_sales_items);
            $coupon->save();
            // return the ID
            return $coupon->get_id();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Creates an coupon in WooCommerce for the new Affiliate if the Affiliate is approved.
     *
     * @param int $affiliate_id
     *
     * @return int|false - The ID of the newly created coupon.
     */
    public static function maybe_create_coupon_for_affiliate($affiliate_id)
    {
        $maybe_affiliate = Affiliate::find($affiliate_id);

        if (is_null($maybe_affiliate)) {
            return false;
        } else {
            $maybe_coupon_template_id = (int)Core::get_addon_setting(self::ADDON_SLUG, self::KEY_COUPON_TEMPLATE_ID);

            if (WooCommerceIntegration::is_a_valid_coupon_id($maybe_coupon_template_id)) {
                $new_coupon_id = self::create_new_coupon_from_template($maybe_coupon_template_id, $maybe_affiliate);
            } else {
                $new_coupon_id = WooCommerceIntegration::UNPERSISTED_COUPON_ID;
            }

            if ($new_coupon_id != WooCommerceIntegration::UNPERSISTED_COUPON_ID) {
                $maybe_meta_id = WooCommerceIntegration::associate_affiliate_with_coupon($new_coupon_id, $affiliate_id);

                // If the update_post_meta fails, then check if it failed because the post meta already existed.
                // This should not happen because the coupon was just created, but err on the side of caution to the detriment of performance for now.
                if (!is_int($maybe_meta_id)) {
                    self::delete_orphaned_coupon_if_not_associated_to_affiliate($new_coupon_id, $affiliate_id);
                    return false;
                } else {
                    return $new_coupon_id;
                }
            } else {
                // TODO
                return false;
            }
        }
    }

    /**
     * This function will attempt to create a coupon (from the configured template) for every Affiliate.
     *
     * @return int|array{error: string} - the number of coupons created.
     */
    public static function create_coupon_for_every_affiliate()
    {
        // First, ensure that the coupon template is set. If not, return an error.
        if (!self::is_a_valid_coupon_template_selected()) {
            return ['error' => __('Please select a coupon template and save the settings before running this action.', 'solid-affiliate')];
        }

        // Get all affiliates
        $affiliates = Affiliate::all();

        // For each affiliate, create a coupon from the template. using maybe_create_coupon_for_affiliate
        $created_coupons = 0;
        foreach ($affiliates as $affiliate) {
            $maybe_coupon_id = self::maybe_create_coupon_for_affiliate($affiliate->id);
            if (is_int($maybe_coupon_id)) {
                $created_coupons++;
            }
        }

        // Return the number of coupons created.
        return $created_coupons;
    }

    /**
     * Checks if an Affiliate is being approved from a different prior status.
     *
     * @param int $coupon_template_id
     * @param Affiliate $affiliate
     *
     * @return int
     */
    private static function create_new_coupon_from_template($coupon_template_id, $affiliate)
    {
        $template_coupon = new \WC_Coupon($coupon_template_id);
        $new_code = self::generate_coupon_code($template_coupon, $affiliate);
        $new_coupon = WooCommerceIntegration::build_coupon_from_coupon($template_coupon, $new_code);
        return $new_coupon->save();
    }

    /**
     * Generates a coupon code for auto-generated coupons.
     *
     * @param \WC_Coupon $coupon
     * @param Affiliate $affiliate
     *
     * @return string
     */
    public static function generate_coupon_code($coupon, $affiliate)
    {
        $user = $affiliate->user();

        if ($user instanceof \WP_User) {
            $username = $user->user_nicename;
            $code = sprintf('%1$s%2$s', $username, $coupon->get_amount());
        } else {
            $code = sprintf('%1$s%2$s', RandomData::string(4), $coupon->get_amount());
        }

        $code = strtoupper($code);

        /**
         * Filters the generated coupon code for auto-generated coupons.
         *
         * @param string $code The generated coupon code.
         * @param Affiliate $affiliate The affiliate object.
         *
         * @return string The filtered coupon code.
         */
        $code = (string)apply_filters("solid_affiliate/generate_coupon_code", $code, $affiliate);

        // check if the code already exists for a coupon
        // if it does, add a random string to the end
        $maybe_already_coupon = new \WC_Coupon($code);
        if ($maybe_already_coupon->get_id() > 0) {
            $code = sprintf('%1$s%2$s', $code, RandomData::string(4));
        }

        return $code;
    }


    /**
     * Renders a notice to inform the user that a coupon template needs to be set.
     *
     * @param \WP_Post $post
     *
     * @return void
     */
    public static function render_coupon_template_needed_notice($post)
    {
        if (self::is_a_coupon_post($post)) {
            echo (self::coupon_template_notice(
                'info',
                __('The Auto-Create Coupons Addon is currently enabled, but there is no coupon template configured.', 'solid-affiliate'),
                __('Please select a coupon to be the template at:', 'solid-affiliate'),
                Core::url_for_addon_settings(self::ADDON_SLUG)
            ));
        }
    }

    /**
     * Renders a notice to warn the user that a coupon is set as the template.
     *
     * @param \WP_Post $post
     *
     * @return void
     */
    public static function render_coupon_template_notice($post)
    {
        if (self::is_a_coupon_post($post)) {
            $template_id = (int)Core::get_addon_setting(self::ADDON_SLUG, self::KEY_COUPON_TEMPLATE_ID);

            # TODO: Why is $post->ID not an int when running tests,
            #   BUT is an int when using the UI?
            if ((int)$post->ID === $template_id) {
                echo (self::coupon_template_notice(
                    'warning',
                    __('This coupon has been selected as the template when coupons are auto-created for new and approved Affiliates.', 'solid-affiliate'),
                    __('If this is not intentional, please set the proper coupon template at:', 'solid-affiliate'),
                    Core::url_for_addon_settings(self::ADDON_SLUG)
                ));
            }
        }
    }

    /**
     * Returns the HTML for coupon template notices.
     *
     * @param string $level
     * @param string $notice_msg
     * @param string $settings_msg
     * @param string $settings_url
     *
     * @return string
     */
    private static function coupon_template_notice($level, $notice_msg, $settings_msg, $settings_url)
    {
        ob_start();
?>
        <div class="notice notice-<?php echo ($level) ?>">
            <p><strong>
                    Solid Affiliate
                </strong></p>
            <p>
                <?php _e($notice_msg, 'solid-affiliate') ?>
            </p>
            <p>
                <?php _e($settings_msg, 'solid-affiliate') ?>
                <?php echo sprintf(__('<a href="%1$s" target="_blank">Auto-Create Coupons Addon Settings</a>.', 'solid-affiliate'), $settings_url) ?>
            </p>
            <p>
                <?php _e('You can read more about how the addon works at:', 'solid-affliate') ?>
                <?php echo sprintf(__('<a href="%1$s" target="_blank">Auto-Create Coupons Addon Documentation</a>.', 'solid-affiliate'), self::DOCS_URL) ?>
            </p>
        </div>
<?php
        return ob_get_clean();
    }

    /**
     * Whether or not a coupon has been set as the template.
     *
     * @return boolean
     */
    private static function is_a_valid_coupon_template_selected()
    {
        $maybe_coupon_id = (int)Core::get_addon_setting(self::ADDON_SLUG, self::KEY_COUPON_TEMPLATE_ID);

        if (WooCommerceIntegration::is_a_valid_coupon_id($maybe_coupon_id)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Whether or not a the post is a coupon.
     *
     * @param \WP_Post $post
     *
     * @return boolean
     */
    private static function is_a_coupon_post($post)
    {
        if ($post->post_type === WooCommerceIntegration::COUPON_POST_TYPE) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * If a coupon is deleted in WooCommerce and it is set as the coupon template,
     * then remove it as the coupon template.
     *
     * @param  int $coupon_id
     * @param  \WP_Post $post
     *
     * @return void
     */
    public static function maybe_unset_deleted_coupon_template($coupon_id, $post)
    {
        if ($post->post_type === WooCommerceIntegration::COUPON_POST_TYPE) {
            $template_id = (int)Core::get_addon_setting(self::ADDON_SLUG, self::KEY_COUPON_TEMPLATE_ID);

            if ($coupon_id === $template_id) {
                self::set_null_coupon_template();
            }
        }
    }

    /**
     * @param int $woocommerce_coupon_id
     * @return bool
     */
    public static function set_coupon_template_id($woocommerce_coupon_id)
    {
        if (WooCommerceIntegration::is_a_valid_coupon_id($woocommerce_coupon_id)) {
            return Core::set_settings_for_slug(self::ADDON_SLUG, [self::KEY_COUPON_TEMPLATE_ID => $woocommerce_coupon_id]);
        } else {
            return false;
        }
    }

    /**
     * If a coupon is trashed in WooCommerce and it is set as the coupon template,
     * then remove it as the coupon template.
     *
     * @param  int $coupon_id
     *
     * @return void
     */
    public static function maybe_unset_trashed_coupon_template($coupon_id)
    {
        if (get_post_type($coupon_id) === WooCommerceIntegration::COUPON_POST_TYPE) {
            $template_id = (int)Core::get_addon_setting(self::ADDON_SLUG, self::KEY_COUPON_TEMPLATE_ID);

            if ($coupon_id === $template_id) {
                self::set_null_coupon_template();
            }
        }
    }

    /**
     * Sets the coupon template ID to 0, "removing" the setting.
     *
     * @return void
     */
    private static function set_null_coupon_template()
    {
        Core::set_settings_for_slug(
            self::ADDON_SLUG,
            [self::KEY_COUPON_TEMPLATE_ID => WooCommerceIntegration::UNPERSISTED_COUPON_ID]
        );
    }

    /**
     * Force delete the coupon post if the coupon is not associated to the Affiliate.
     *
     * @param int $new_coupon_id
     * @param int $affiliate_id
     *
     * @return void
     */
    private static function delete_orphaned_coupon_if_not_associated_to_affiliate($new_coupon_id, $affiliate_id)
    {
        if (is_null(WooCommerceIntegration::find_coupon_for_affiliate($new_coupon_id, $affiliate_id))) {
            wp_delete_post($new_coupon_id, true);
        }
    }
}
