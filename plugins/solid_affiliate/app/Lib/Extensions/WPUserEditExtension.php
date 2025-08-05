<?php

namespace SolidAffiliate\Lib\Extensions;

use SolidAffiliate\Lib\Notices;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Models\Affiliate;

class WPUserEditExtension
{

    /**
     * Registers WordPress Hooks
     *
     * @return void
     */
    public static function register_hooks()
    {
        add_action('show_user_profile', [self::class, 'sld_extension_wp_user_edit']);
        add_action('edit_user_profile', [self::class, 'sld_extension_wp_user_edit']);
        // TODO this isn't actually on the User Edit page. It's the users Index page.
        add_filter('user_row_actions', [self::class, 'filter_user_row_actions'], 10, 2);
    }

    /**
     * @param string[] $actions
     * @param \WP_User $user
     * 
     * @return string[]
     */
    public static function filter_user_row_actions($actions, $user)
    {
        // if there is no affiliate already associated with this user, then add a link to the affiliate edit page
        if (is_null(Affiliate::find_where(['user_id' => $user->ID]))) {
            $actions['register_as_affiliate'] = "<a class='register_as_affiliate' href='" . self::prefilled_new_affiliate_url($user) . "'>" . esc_html__('Register as Affiliate', 'solid-affiliate') . "</a>";
        }
        return $actions;
    }



    /**
     * Extends WP User Edit screen.
     *
     * @param \WP_User $wp_user
     * @return void
     */
    public static function sld_extension_wp_user_edit($wp_user)
    {
        echo ('<h2>Solid Affiliate</h2>');
        $maybe_affiliate = Affiliate::for_user_id($wp_user->ID);

        if ($maybe_affiliate) {
            _e('Affiliate Registration Status: ', 'solid-affiliate');
            _e($maybe_affiliate->status, 'solid-affiliate');
            echo self::render_affiliate_actions($maybe_affiliate);
        } else {
            _e('Affiliate Registration Status: ', 'solid-affiliate');
            _e('Not Registered', 'solid-affiliate');
            echo self::render_not_affiliate_actions($wp_user);
        }
    }

    /**
     * Renders Affiliate Actions for User Edit extension.
     *
     * @param \SolidAffiliate\Models\Affiliate $affiliate
     * 
     * @return string
     */
    public static function render_affiliate_actions($affiliate)
    {
        ob_start();

        $affiliate_edit_url = URLs::edit(Affiliate::class, $affiliate->id);
        // a link to take me to affiliate edit screen
        // TODO one day, a link to Reports from here.
?>
        <br />
        <br />
        <a href="<?php echo ($affiliate_edit_url) ?>">Edit Affiliate</a>


    <?php
        $res = ob_get_clean();
        return $res;
    }

    /**
     * Renders Not Affiliate Actions for User Edit extension.
     *
     * @param \WP_User $wp_user
     * 
     * @return string
     */
    public static function render_not_affiliate_actions($wp_user)
    {
        ob_start();
        $affiliate_edit_url = self::prefilled_new_affiliate_url($wp_user);
    ?>
        <br />
        <br />
        <a href="<?php echo ($affiliate_edit_url) ?>"><?php _e('Register this User as an Affiliate', 'solid-affiliate') ?></a><small> (<?php _e('This will take you to the Affiliate creation screen with some data pre-filled for this User.', 'solid-affiliate') ?>)</small>


<?php
        $res = ob_get_clean();
        return $res;
    }

    /**
     * @param \WP_User $wp_user
     * @return string
     */
    public static function prefilled_new_affiliate_url($wp_user)
    {
        $msg = sprintf(__('User ID has been pre-filled with the ID %1$d for the User %2$s.', 'solid-affiliate'), $wp_user->ID, $wp_user->user_email);
        return URLs::admin_path(Affiliate::ADMIN_PAGE_KEY, false, ['action' => 'new', 'user_id' => $wp_user->ID, Notices::URL_PARAM_MESSAGE => $msg]);
    }
}
