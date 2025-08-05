<?php

namespace SolidAffiliate\Lib\WooSoftwareLicense;

use SolidAffiliate\Lib\License;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\Validators;

class WOO_SLT_Options_Interface
{

    /** @var WOO_SLT_Licence */
    var $licence;

    /**
     * @param WOO_SLT_Licence|null $woo_slt_licence_instance
     */
    function __construct($woo_slt_licence_instance = null)
    {
        if ($woo_slt_licence_instance === null) {
            $woo_slt_licence_instance = new WOO_SLT_Licence();
        }

        $this->licence = $woo_slt_licence_instance;

        if (isset($_GET['page']) && ($_GET['page'] == 'solid-affiliate-license-key'  ||  $_GET['page'] == 'solid-affiliate-license-key-options')) {
            add_action('init', array($this, 'options_update'), 1);
        }

        if (!License::is_solid_affiliate_activated() && !License::is_on_keyless_free_trial()) {
            add_action('admin_notices', array($this, 'admin_no_key_notices'));
            add_action('network_admin_notices', array($this, 'admin_no_key_notices'));
        }
    }

    function __destruct() {}

    /**
     * @return void
     */
    function network_admin_menu()
    {
        if (!WOO_SLT_Licence::licence_key_verify())
            $hookID   = add_submenu_page('settings.php', 'Solid Affiliate - License Key', 'Solid Affiliate - License Key', 'manage_options', 'solid-affiliate-license-key', array($this, 'licence_form'));
        else
            $hookID   = add_submenu_page('settings.php', 'Solid Affiliate - License Key', 'Solid Affiliate - License Key', 'manage_options', 'solid-affiliate-license-key', array($this, 'licence_deactivate_form'));

        if ($hookID) {
            add_action('load-' . $hookID, array($this, 'load_dependencies'));
            add_action('load-' . $hookID, array($this, 'admin_notices'));

            add_action('admin_print_styles-' . $hookID, array($this, 'admin_print_styles'));
            add_action('admin_print_scripts-' . $hookID, array($this, 'admin_print_scripts'));
        }
    }


    /**
     * @return void
     */
    function admin_menu()
    {
        if (!WOO_SLT_Licence::licence_key_verify())
            $hookID   = add_options_page('Solid Affiliate - License Key', 'Solid Affiliate - License Key', 'manage_options', 'solid-affiliate-license-key-options', array($this, 'licence_form'));
        else
            $hookID   = add_options_page('Solid Affiliate - License Key', 'Solid Affiliate - License Key', 'manage_options', 'solid-affiliate-license-key-options', array($this, 'licence_deactivate_form'));

        if ($hookID) {
            add_action('load-' . $hookID, array($this, 'load_dependencies'));
            add_action('load-' . $hookID, array($this, 'admin_notices'));

            add_action('admin_print_styles-' . $hookID, array($this, 'admin_print_styles'));
            add_action('admin_print_scripts-' . $hookID, array($this, 'admin_print_scripts'));
        }
    }


    /**
     * @return void
     */
    function options_interface()
    {
        if (!WOO_SLT_Licence::licence_key_verify() && !is_multisite()) {
            $this->licence_form();
            return;
        }

        if (!WOO_SLT_Licence::licence_key_verify() && is_multisite()) {
            $this->licence_multisite_require_nottice();
            return;
        }
    }

    /**
     * @return void
     */
    function options_update()
    {

        if (isset($_POST['slt_licence_form_submit'])) {
            $this->licence_form_submit();
            return;
        }
    }

    /**
     * @return void
     */
    function load_dependencies() {}

    /**
     * @return void
     */
    function admin_notices()
    {
        global $slt_form_submit_messages;

        if ($slt_form_submit_messages == '')
            return;

        $messages = Validators::array_of_string($slt_form_submit_messages);

        if (count($messages) > 0) {
            echo "<div id='notice' class='updated fade'><p>" . implode("</p><p>", $messages)  . "</p></div>";
        }
    }

    /**
     * @return void
     */
    public static function admin_notices_static()
    {
        global $slt_form_submit_messages;

        if ($slt_form_submit_messages == '')
            return;

        $messages = Validators::array_of_string($slt_form_submit_messages);


        if (count($messages) > 0) {
            echo "<div id='notice' class='updated fade'><p>" . implode("</p><p>", $messages)  . "</p></div>";
        }
    }

    /**
     * Hits the API and attempts to activate the license key.
     * Returns true if the activation was successful.
     * Returns an array of error messages if the activation failed.
     *
     * @param string $license_key
     * 
     * @return true|string[] - true if the activation was successful, an array of error messages if the activation failed.
     */
    public static function handle_activating_license($license_key)
    {
        $license_key = sanitize_key(trim($license_key));
        $error_messages = [];

        if ($license_key == '') {
            /** @psalm-suppress MixedArrayAssignment */
            $error_messages[] = __("Licence Key can't be empty", 'solid-affiliate');
            return $error_messages;
        }

        try {

            //build the request query
            $args = array(
                'woo_sl_action'     => 'activate',
                'licence_key'       => $license_key,
                'product_unique_id' => WOO_SLT_PRODUCT_ID,
                'domain'            => WOO_SLT_INSTANCE,
                'cache_buster'          => time()
            );
            $request_uri    = WOO_SLT_APP_API_URL . '?' . http_build_query($args, '', '&');
            $data           = wp_remote_get($request_uri);


            if (($data instanceof \WP_Error) || $data['response']['code'] != 200) {
                /**
                 * @psalm-suppress MixedOperand
                 * @psalm-suppress MixedArrayAssignment
                 * @psalm-suppress MixedArrayAccess
                 */
                $error_messages[] = __('There was a problem connecting to ', 'solid-affiliate') . WOO_SLT_APP_API_URL;
                return $error_messages;
            }

            /**
             * @psalm-suppress MixedAssignment
             */
            $response_block = json_decode($data['body']);
            //retrieve the last message within the $response_block
            /**
             * @psalm-suppress MixedArrayAccess
             * @psalm-suppress MixedAssignment
             * @psalm-suppress MixedArgument
             */
            $response_block = $response_block[count($response_block) - 1];

            /**
             * @psalm-suppress MixedPropertyFetch
             */
            if (isset($response_block->status)) {
                if ($response_block->status == 'success' && ($response_block->status_code == 's100' || $response_block->status_code == 's101')) {
                    /**
                     * @psalm-suppress MixedArrayAssignment
                     * @psalm-suppress MixedAssignment
                     */
                    $error_messages[] = $response_block->message;

                    $license_data = WOO_SLT_Licence::get_license_data();
                    if ($license_data == false) {
                        $license_data = [];
                    }

                    //save the license
                    $license_data['key']          = $license_key;
                    $license_data['last_check']   = time();

                    if (isset($response_block->feature_level)) {
                        $license_data['feature_level'] = (int)$response_block->feature_level;
                    }

                    WOO_SLT_Licence::update_license_data($license_data);
                } else {
                    $error_messages[] = __('There was a problem activating the licence: ', 'solid-affiliate') . (string)$response_block->message;
                    return $error_messages;
                }
            } else {
                $error_messages[] = __('There was a problem with the data block received from ' . WOO_SLT_APP_API_URL, 'solid-affiliate');
                return $error_messages;
            }
        } catch (\Exception $e) {
            $error_messages[] = __('Please contact team@solidaffiliate.com - An unexpected error occurred: ', 'solid-affiliate') . $e->getMessage();
            return Validators::array_of_string($error_messages);
        }

        return true;
    }


    /**
     * @return void
     */
    function admin_print_styles()
    {
        wp_register_style('wooslt_admin', WOO_SLT_URL . '/css/admin.css');
        wp_enqueue_style('wooslt_admin');
    }

    /**
     * @return void
     */
    function admin_print_scripts() {}


    /**
     * @return void
     */
    function admin_no_key_notices()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $screen = get_current_screen();
        if (is_null($screen)) {
            return;
        }

        if (is_multisite()) {
            if ($screen->id == 'settings_page_solid-affiliate-license-key-network') {
                return;
            }
?>
            <div class="updated fade">
                <p><?php _e("Solid Affiliate is inactive. There may be critical security updates available. Please enter your", 'solid-affiliate') ?> <a href="<?php echo URLs::admin_path('solid-affiliate-license-key-options'); ?>"><?php _e("License Key", 'solid-affiliate') ?></a></p>
            </div>
        <?php
        } else {
            if ($screen->id == 'settings_page_solid-affiliate-license-key-options') {
                return;
            }
        ?>
            <div class="updated fade">
                <p><?php _e("Solid Affiliate is inactive. There may be critical security updates available. Please enter your", 'solid-affiliate') ?> <a href="<?php echo URLs::admin_path('solid-affiliate-license-key-options'); ?>"><?php _e("License Key", 'solid-affiliate') ?></a></p>
            </div>
        <?php
        }
    }

    /**
     * @return void
     */
    function licence_form_submit()
    {
        global $slt_form_submit_messages;

        //check for refresh request
        if (isset($_POST['slt_licence_form_submit']) && isset($_POST['slt_licence_refresh']) && wp_verify_nonce((string)$_POST['solid_affiliate_slt_license_nonce'], 'solid_affiliate_slt_license')) {
            // $slt_form_submit_messages = $this->refresh_license();
            WOO_SLT_Licence::run_status_check(true);
            /**
             * @psalm-suppress PossiblyUndefinedArrayOffset
             */
            $current_url    =   'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

            wp_redirect($current_url);
            die();
        }

        //check for de-activation request
        if (isset($_POST['slt_licence_form_submit']) && isset($_POST['slt_licence_deactivate']) && wp_verify_nonce((string)$_POST['solid_affiliate_slt_license_nonce'], 'solid_affiliate_slt_license')) {

            $license_data = WOO_SLT_Licence::get_license_data();
            if ($license_data == false) {
                $license_key = '';
            } else {
                $license_key = $license_data['key'];
            }

            //build the request query
            $args = array(
                'woo_sl_action'         => 'deactivate',
                'licence_key'           => $license_key,
                'product_unique_id'     => WOO_SLT_PRODUCT_ID,
                'domain'                => WOO_SLT_INSTANCE,
                'cache_buster'          => time()
            );
            $request_uri    = WOO_SLT_APP_API_URL . '?' . http_build_query($args, '', '&');
            $data           = wp_remote_get($request_uri);

            if (($data instanceof \WP_Error) || !isset($data['response']) || $data['response']['code'] != 200) {
                /**
                 * @psalm-suppress MixedOperand
                 * @psalm-suppress MixedArrayAssignment
                 * @psalm-suppress MixedArrayAccess
                 */
                $slt_form_submit_messages[] .= __('There was a problem connecting to ', 'solid-affiliate') . WOO_SLT_APP_API_URL;
                return;
            }

            /**
             * @psalm-suppress MixedAssignment
             */
            $response_block = json_decode($data['body']);

            //retrieve the last message within the $response_block
            /**
             * @psalm-suppress MixedArrayAccess
             * @psalm-suppress MixedAssignment
             * @psalm-suppress MixedArgument
             */
            $response_block = $response_block[count($response_block) - 1];

            /**
             * @psalm-suppress MixedPropertyFetch
             */
            if (isset($response_block->status)) {
                if ($response_block->status == 'success' && $response_block->status_code == 's201') {
                    //the license is active and the software is active
                    /**
                     * @psalm-suppress MixedAssignment
                     * @psalm-suppress MixedArrayAssignment
                     */
                    $slt_form_submit_messages[] = $response_block->message;

                    $license_data = WOO_SLT_Licence::get_license_data();
                    if ($license_data == false) {
                        $license_data = [];
                    }

                    //save the license
                    $license_data['key']          = '';
                    $license_data['last_check']   = time();

                    WOO_SLT_Licence::update_license_data($license_data);
                } else //if message code is e104  force de-activation
                    if ($response_block->status_code == 'e002' || $response_block->status_code == 'e104') {
                        $license_data = WOO_SLT_Licence::get_license_data();
                        if ($license_data == false) {
                            $license_data = [];
                        }

                        //save the license
                        $license_data['key']          = '';
                        $license_data['last_check']   = time();

                        WOO_SLT_Licence::update_license_data($license_data);
                    } else {
                        /** @psalm-suppress MixedArrayAssignment */
                        $slt_form_submit_messages[] = __('There was a problem deactivating the licence: ', 'solid-affiliate') . (string)$response_block->message;

                        return;
                    }
            } else {
                /** @psalm-suppress MixedArrayAssignment */
                $slt_form_submit_messages[] = __('There was a problem with the data block received from ' . WOO_SLT_APP_API_URL, 'solid-affiliate');
                return;
            }

            //redirect
            /**
             * @psalm-suppress PossiblyUndefinedArrayOffset
             */
            $current_url    =   'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

            wp_redirect($current_url);
            die();
        }



        // Handle the submit/activate license key
        if (isset($_POST['slt_licence_form_submit']) && wp_verify_nonce((string)$_POST['solid_affiliate_slt_license_nonce'], 'solid_affiliate_slt_license')) {

            $license_key = isset($_POST['license_key']) ? sanitize_key(trim((string)$_POST['license_key'])) : '';
            ///////////////////////////////////// /////////////////////////////////////
            // START License Activation Logic - Extract
            ///////////////////////////////////// /////////////////////////////////////
            $true_or_error_messages = self::handle_activating_license($license_key);
            if (is_array($true_or_error_messages)) {
                $slt_form_submit_messages = $true_or_error_messages;
                return;
            }
            ///////////////////////////////////// /////////////////////////////////////
            // END License Activation Logic - Extract
            ///////////////////////////////////// /////////////////////////////////////

            //redirect
            /**
             * @psalm-suppress PossiblyUndefinedArrayOffset
             */
            $current_url = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            wp_redirect($current_url);
            die();
        }
    }

    /**
     * @return void
     */
    function licence_form()
    {
        ?>

        <div class="wrap">
            <div id="icon-settings" class="icon32"></div>
            <h1><?php _e("Solid Affiliate - License Key", 'solid-affiliate') ?><br />&nbsp;</h1>


            <form id="form_data" name="form" method="post">
                <div class="postbox">

                    <?php wp_nonce_field('solid_affiliate_slt_license', 'solid_affiliate_slt_license_nonce'); ?>
                    <input type="hidden" name="slt_licence_form_submit" value="true" />



                    <div class="section section-text ">
                        <h4 class="heading"><?php _e("License Key", 'solid-affiliate') ?></h4>
                        <div class="option">
                            <div class="controls">
                                <input type="text" value="" name="license_key" class="text-input">
                            </div>
                            <div class="explain"><?php _e("Enter the License Key you got when bought this product. If you lost the key, you can always retrieve it from", 'solid-affiliate') ?> <a href="https://solidaffiliate.com/my-account/orders/" target="_blank"><?php _e("My Account", 'solid-affiliate') ?></a><br />
                                <?php _e("More keys can be generate from", 'solid-affiliate') ?> <a href="https://solidaffiliate.com/my-account/orders/" target="_blank"><?php _e("My Account", 'solid-affiliate') ?></a>
                            </div>
                        </div>
                    </div>


                </div>

                <p class="submit">
                    <input type="submit" name="Submit" class="button-primary" value="<?php _e('Save', 'solid-affiliate') ?>">
                </p>
            </form>
        </div>
    <?php

    }

    /**
     * @return void
     */
    function licence_deactivate_form()
    {
        $license_data = WOO_SLT_Licence::get_license_data();
        if (!$license_data) {
            return;
        }

    ?>
        <div class="wrap">
            <div id="form_data">
                <h1><?php _e("Solid Affiliate - License Key", 'solid-affiliate') ?><br />&nbsp;</h1>
                <div class="postbox">
                    <form id="form_data" name="form" method="post">
                        <?php wp_nonce_field('solid_affiliate_slt_license', 'solid_affiliate_slt_license_nonce'); ?>
                        <input type="hidden" name="slt_licence_form_submit" value="true" />
                        <input type="hidden" name="slt_licence_deactivate" value="true" />
                        <input type="hidden" name="slt_licence_refresh" value="true" />

                        <div class="section section-text ">
                            <h4 class="heading"><?php _e("License Key", 'solid-affiliate') ?> <?php if (License::is_solid_affiliate_activated_but_expired()) {
                                                                                                    echo ("<div class='sld_badge alert'>" . __("Expired", 'solid-affiliate') . "</div>");
                                                                                                } ?></h4>
                            <div class="option">
                                <div class="controls">
                                    <?php
                                    /** @psalm-suppress DocblockTypeContradiction */
                                    if ($this->licence::is_test_instance()) {
                                    ?>
                                        <p>Local instance, no key applied.</p>
                                    <?php
                                    } else {
                                    ?>
                                        <p>
                                            <b><?php echo substr($license_data['key'], 0, 12) ?>-xxxxxxxx-xxxxxxxx</b> &nbsp;&nbsp;&nbsp;

                                            <a class="button-secondary" title="Deactivate" href="javascript: void(0)" onclick="jQuery(this).closest('form').find('input[name=&quot;slt_licence_refresh&quot;]').remove(); jQuery(this).closest('form').submit();"><?php _e('Deactivate', 'solid-affiliate') ?></a>
                                            <a class="button-secondary" title="Refresh" href="javascript: void(0)" onclick="jQuery(this).closest('form').find('input[name=&quot;slt_licence_deactivate&quot;]').remove(); jQuery(this).closest('form').submit();"><?php _e('Refresh', 'solid-affiliate') ?></a>
                                        </p>
                                    <?php } ?>
                                </div>
                                <div class="explain"><?php _e("You can generate more keys from", 'solid-affiliate') ?> <a href="https://solidaffiliate.com/my-account/orders/" target="_blank"><?php _e('My Account', 'solid-affiliate') ?></a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <?php
    }

    /**
     * @return void
     */
    function licence_multisite_require_nottice()
    {
    ?>
        <div class="wrap">
            <h1><?php _e("Solid Affiliate - License Key", 'solid-affiliate') ?><br />&nbsp;</h1>
            <div id="form_data">
                <div class="postbox">
                    <div class="section section-text ">
                        <h4 class="heading"><?php _e("License Key Required", 'solid-affiliate') ?>!</h4>
                        <div class="option">
                            <div class="explain"><?php _e("Enter the License Key you got when bought this product. If you lost the key, you can always retrieve it from", 'solid-affiliate') ?> <a href="https://solidaffiliate.com/my-account/orders/" target="_blank"><?php _e("My Account", 'solid-affiliate') ?></a><br />
                                <?php _e("More keys can be generated from", 'solid-affiliate') ?> <a href="https://solidaffiliate.com/my-account/orders/" target="_blank"><?php _e("My Account", 'solid-affiliate') ?></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php

    }
}



?>