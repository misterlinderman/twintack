<?php

namespace SolidAffiliate\Lib;

class Activation
{
    /**
     * @hook register_activation_hook
     * @param bool $network_wide
     * 
     * @return void
     */
    public static function activation_hook($network_wide)
    {
        ///////////////////////////////////////
        // Handle Multisite Activation
        if (function_exists('is_multisite') && is_multisite()) {
            if ($network_wide) {
                if (false == is_super_admin()) {
                    return;
                }
                $blogs = \SolidAffiliate\Lib\Validators::arr_of_wp_site(get_sites());
                foreach ($blogs as $blog) {
                    switch_to_blog((int)$blog->blog_id);
                    self::_activate_solid_affiliate_for_current_site();
                    restore_current_blog();
                }
            } else {
                if (false == current_user_can('activate_plugins')) {
                    return;
                }
                self::_activate_solid_affiliate_for_current_site();
            }
        } else {
            ///////////////////////////////////////
            // Handle Single Site Intallation
            self::_activate_solid_affiliate_for_current_site();
        }
    }

    /**
     * @return void
     */
    public static function _activate_solid_affiliate_for_current_site()
    {
        License::begin_free_trial();
        add_option('solid_affiliate_do_activation_redirect', true);
        ///////////////////////////////////////////////////////////////
        // Start the custom tables framework.
        do_action("solid_affiliate/ct_init");
        // Fire the activation hook;
        do_action("solid_affiliate/activation");
        ///////////////////////////////////////////////////////////////
    }
}
