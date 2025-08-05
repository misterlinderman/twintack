<?php

namespace SolidAffiliate\Views\AffiliatePortal;

use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Lib\License;
use SolidAffiliate\Lib\Links;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\AffiliatePortal\AffiliatePortalRegistrationViewInterface;
use SolidAffiliate\Views\Shared\AdminHeader;
use SolidAffiliate\Lib\FF;


class RegistrationView
{

    const FORM_ID = 'solid-affiliate-affiliate-portal_new_affiliate';

    /**
     * @param AffiliatePortalRegistrationViewInterface $Iregistration
     *
     * @return string
     */
    public static function render($Iregistration)
    {
        // get the current user's email address if they are logged in
        $maybe_email = (string)$Iregistration->form_values['user_email'];
        if (!empty($maybe_email) && get_user_by('email', $maybe_email)) {
            $already_logged_in_notice = '<div class="sld-ap-form_notice info">
                <div class="sld-ap-form_notice_icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6.16797 18.849C6.41548 18.0252 6.92194 17.3032 7.61222 16.79C8.30249 16.2768 9.13982 15.9997 9.99997 16H14C14.8612 15.9997 15.6996 16.2774 16.3904 16.7918C17.0811 17.3062 17.5874 18.0298 17.834 18.855M3 12C3 13.1819 3.23279 14.3522 3.68508 15.4442C4.13738 16.5361 4.80031 17.5282 5.63604 18.364C6.47177 19.1997 7.46392 19.8626 8.55585 20.3149C9.64778 20.7672 10.8181 21 12 21C13.1819 21 14.3522 20.7672 15.4442 20.3149C16.5361 19.8626 17.5282 19.1997 18.364 18.364C19.1997 17.5282 19.8626 16.5361 20.3149 15.4442C20.7672 14.3522 21 13.1819 21 12C21 10.8181 20.7672 9.64778 20.3149 8.55585C19.8626 7.46392 19.1997 6.47177 18.364 5.63604C17.5282 4.80031 16.5361 4.13738 15.4442 3.68508C14.3522 3.23279 13.1819 3 12 3C10.8181 3 9.64778 3.23279 8.55585 3.68508C7.46392 4.13738 6.47177 4.80031 5.63604 5.63604C4.80031 6.47177 4.13738 7.46392 3.68508 8.55585C3.23279 9.64778 3 10.8181 3 12ZM9 10C9 10.7956 9.31607 11.5587 9.87868 12.1213C10.4413 12.6839 11.2044 13 12 13C12.7956 13 13.5587 12.6839 14.1213 12.1213C14.6839 11.5587 15 10.7956 15 10C15 9.20435 14.6839 8.44129 14.1213 7.87868C13.5587 7.31607 12.7956 7 12 7C11.2044 7 10.4413 7.31607 9.87868 7.87868C9.31607 8.44129 9 9.20435 9 10Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                </div>
                
            ' . sprintf(__('You are currently logged in as %1$s.', 'solid-affiliate'), $maybe_email) . '</div>';
        } else {
            $already_logged_in_notice = '';
        }


        $maybe_deactivated_component = AdminHeader::render_solid_affiliate_is_deactivated_component();
        if (!empty($maybe_deactivated_component) && !License::is_on_keyless_free_trial()) {
            return $maybe_deactivated_component;
        }

        $affiliate_word = Validators::str(Settings::get(Settings::KEY_AFFILIATE_WORD_ON_PORTAL_FORMS));
        $register_cta = __('Register as', 'solid-affiliate') . ' ' . $affiliate_word;

        // get wordpress login url/link
        $login_link = Links::render(wp_login_url(), __('Login', 'solid-affiliate'));
        // but translated. the interior of the div should be a translated string using __() and sprintf()
        $login_error_html = "<div id='sld-user_login-error' class='sld-ap-form_error'>" . sprintf(__('An account with this username aready exists. Please %1$s first if it is your account.', 'solid-affiliate'), $login_link) . "</div>";
        $email_error_html = "<div id='sld-user_email-error' class='sld-ap-form_error'>" . sprintf(__('An account with this email aready exists. Please %1$s first if it is your account.', 'solid-affiliate'), $login_link) . "</div>";


        ob_start();
?>
        <?php echo (LoginView::render_css()) ?>
        <script>
            // make an ajax request to generate a new nonce and then replace the value of the nonce field in the form
            jQuery(document).ready(function($) {
                var data = {
                    'action': 'sld_affiliate_generate_registration_nonce',
                };

                jQuery.post(window.SolidAffiliate.ajaxurl, data, function(response) {
                    if (response.success) {
                        jQuery('#<?php echo self::FORM_ID ?> #_wpnonce').val(response.data);
                    }
                });

                /////////////////////////////////////////////////
                // Function to check if a field is already in use
                function checkIfFieldIsAlreadyInUse(field, value) {
                    return jQuery.ajax({
                        url: window.SolidAffiliate.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sld_affiliate_check_if_field_is_already_in_use',
                            field: field,
                            value: value,
                        }
                    });
                }

                // Add an event handler to do something whenever the name="user_email" or "user_login" field is changed.
                jQuery('#<?php echo self::FORM_ID ?> input[name="user_email"], #<?php echo self::FORM_ID ?> input[name="user_login"]').on('change', function() {
                    let $this = jQuery(this);
                    let newValue = $this.val();
                    let field = $this.attr('name') === 'user_email' ? 'email' : 'login';
                    let errorId = 'sld-user_' + field + '-error';

                    // check if the field is already in use
                    checkIfFieldIsAlreadyInUse(field, newValue).done(function(response) {
                        if (response.success) {
                            jQuery('#<?php echo self::FORM_ID ?> #' + errorId).remove();
                        } else {
                            if (jQuery('#<?php echo self::FORM_ID ?> #' + errorId).length) {
                                return;
                            }
                            $this.after(field === 'email' ? <?php echo json_encode($email_error_html) ?> : <?php echo json_encode($login_error_html) ?>);
                        }
                    }).fail(function(error) {
                        jQuery('#<?php echo self::FORM_ID ?> #' + errorId).remove();
                    });
                });

            });
        </script>

        <div class="sld-col-1  sld-ap-registration-component">
            <?php echo ($already_logged_in_notice) ?>
            <?php echo ($Iregistration->notices) ?>
            <div class="sld-ap-form_box">


                <h2><?php echo ($register_cta) ?></h2>

                <?php if ($Iregistration->affiliate_approval_required) { ?>
                    <p class="sld-ap-form-lead">
                        <?php _e("Your account must be approved before earning referrals. You'll be notified by email once approved.", 'solid-affiliate') ?>
                    </p>
                <?php } else { ?>
                    <p class="sld-ap-form-lead">
                        <?php _e('Your account will be automatically approved, allowing you to start earning referrals immediately.', 'solid-affiliate') ?>
                    </p>
                <?php }; ?>

                <form action="" method="post" class="sld-ap-form registration" id="<?php echo self::FORM_ID ?>">
                    <?php echo (FormBuilder::build_form(
                        $Iregistration->schema,
                        'non_admin_new',
                        (object)$Iregistration->form_values,
                        false,
                        false,
                        false
                    )); ?>
                    <?php if ((bool)Settings::get(Settings::KEY_IS_RECAPTCHA_ENABLED_FOR_AFFILIATE_REGISTRATION)) { ?>
                        <?php echo FormBuilder::render_solid_g_recaptcha_v2(); ?>
                    <?php } ?>
                    <?php wp_nonce_field($Iregistration->form_nonce) ?>
                    <input type="submit" name="<?php echo ($Iregistration->submit_action) ?>" id="<?php echo ($Iregistration->submit_action) ?>" class="sld-ap-form_submit" value="<?php echo ($register_cta) ?>">
                </form>

            </div>

            <?php
            if (FF::is_off('white_labeled_affiliate_portal')) {
                echo '<div style="color:var(--sld-ap-secondary-color); display:flex!important;align-items:center!important;justify-content:center!important;padding:20px!important;gap:8px!important;font-size:12px!important;pointer-events:none;">
                        Powered by <img height="18" src="https://solidaffiliate.com/brand/logo.svg" alt="Solid Affiliate Logo">
                      </div>';
            } else {
                echo '';
            }
            ?>

        </div>


<?php
        return ob_get_clean();
    }
}
