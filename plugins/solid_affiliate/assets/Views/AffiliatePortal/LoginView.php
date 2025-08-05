<?php

namespace SolidAffiliate\Views\AffiliatePortal;

/**
 * shortcodes.affiliate_portal_login view.
 * WordPress MVC view.
 *
 * @author Mike Holubowski <https://www.github.com/mholubowski>
 * @package solid-affiliate
 * @version 1.0.0
 */

use SolidAffiliate\Controllers\AffiliatePortalController;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\Validators;

# TODO: Decide how we want to do form name scoping if we want to persist the email on a failed submit.
#       Validators::LoginCredentials forces the keys of the params to be user_pass and user_email.
class LoginView
{

    /**
     * @return string
     */
    public static function render()
    {
        $form_id = 'solid-affiliate-affiliate-portal_login-form';
        $nonce = AffiliatePortalController::NONCE_SUBMIT_AFFILIATE_LOGIN;
        $submit_action = AffiliatePortalController::POST_PARAM_SUBMIT_AFFILIATE_LOGIN;

        $affiliate_word = Validators::str(Settings::get(Settings::KEY_AFFILIATE_WORD_ON_PORTAL_FORMS));
        $login_cta = __('Login as', 'solid-affiliate') . ' ' . $affiliate_word;

        ob_start();
?>
        <?php echo (self::render_css()) ?>
        <script>
            // make an ajax request to generate a new nonce and then replace the value of the nonce field in the form
            jQuery(document).ready(function($) {
                var data = {
                    'action': 'sld_affiliate_generate_login_nonce',
                };

                $.post(window.SolidAffiliate.ajaxurl, data, function(response) {
                    if (response.success) {
                        $('#<?php echo $form_id ?> #_wpnonce').val(response.data);
                    }
                });
            });
        </script>

        <div class="sld-col-2 sld-ap-form_box sld-ap-login-component">
            <?php if ($submit_action == Validators::str_from_array($_GET, 'submit_action')) { ?>
                <?php echo (AffiliatePortalController::affiliate_portal_notices()) ?>
            <?php } ?>

            <h2 style="margin: 0 var(--sld-ap-spacing-md) var(--sld-ap-spacing-lg) 0;"><?php echo ($login_cta) ?></h2>
            <form action="" method="post" class="sld-ap-form" id="<?php echo $form_id ?>">
                <div class="sld-ap-form_group">
                    <label class="sld_field-title" for="user_email-1"><?php _e('Account email', 'solid-affiliate') ?></label>
                    <input type="email" id="sld-ap-login-email" name="user_email" required>
                </div>
                <div class="sld-ap-form_group">
                    <label class="sld_field-title" for="user_pass-1"><?php _e('Password', 'solid-affiliate') ?></label>
                    <input type="password" id="sld-ap-login-pass" name="user_pass" required>
                    <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" class="forgot-pass"><?php _e('Forgot your password?', 'solid-affiliate') ?></a>
                </div>
                <input type="hidden" name="field_id" value="0">

                <?php wp_nonce_field($nonce); ?>
                <!-- TODO: Was there a reason that 'id' was set to the string that $submit_action is, but does not use the $submit_action variable? -->
                <input type="submit" name="<?php echo ($submit_action) ?>" id="<?php echo ($submit_action) ?>" class="sld-ap-form_login" value="<?php echo ($login_cta) ?>">
            </form>
        </div>

    <?php
        return ob_get_clean();
    }




    /**
     * Undocumented function
     *
     * @return string
     */
    public static function render_css()
    {

        ob_start();
    ?>

        <style>
            :root {

                --sld-ap-font-family: -apple-system, BlinkMacSystemFont, " var", "Segoe UI", Helvetica, "Apple Color Emoji", Arial, sans-serif, "Segoe UI Emoji", "Segoe UI Symbol";

                /* Light Theme Variables */
                --sld-ap-background: #fff;
                --sld-ap-shading: #fafafa;
                --sld-ap-primary-color: #000;
                --sld-ap-secondary-color: #727272;
                --sld-ap-accent-color: #000;
                --sld-ap-border-color: #DADCE0;
                --sld-ap-error-color: #D8000C;
                --sld-ap-success-color: #4CAF50;
                --sld-ap-info-color: #4a51c1;

                /* Spacing Variables */
                --sld-ap-spacing-xs: 5px;
                --sld-ap-spacing-sm: 10px;
                --sld-ap-spacing-md: 20px;
                --sld-ap-spacing-lg: 30px;

                /* Border Radius */
                --sld-ap-border-radius-sm: 5px;
                --sld-ap-border-radius-md: 8px;
                --sld-ap-border-radius-lg: 12px;

                /* Font Sizes */
                --sld-ap-font-size-xs: 12px;
                --sld-ap-font-size-sm: 14px;
                --sld-ap-font-size-md: 16px;
                --sld-ap-font-size-lg: 20px;
                --sld-ap-font-size-xl: 22px;

                /* Font Weights */
                --sld-ap-font-weight-normal: 400;
                --sld-ap-font-weight-medium: 500;

                /* Shadow */
                --sld-ap-shadow-sm: 0 2px 2px 0 rgb(0 0 0 / 0.08);
                --sld-ap-shadow-md: 0 2px 4px 0 rgb(0 0 0 / 0.08);


                /* Dark Theme Variables */
                --sld-ap-dark-background: #121212;
                --sld-ap-dark-shading: #1E1E1E;
                --sld-ap-dark-primary-color: #E0E0E0;
                --sld-ap-dark-secondary-color: #A0A0A0;
                --sld-ap-dark-accent-color: #6AB0FF;
                --sld-ap-dark-border-color: #333333;
                --sld-ap-dark-shadow-sm: 0 2px 2px 0 rgb(255 255 255 / 0.08);
                
            }

            /* Dark Mode Selector */
            .dark-mode {
                --sld-ap-background: var(--sld-ap-dark-background);
                --sld-ap-shading: var(--sld-ap-dark-shading);
                --sld-ap-primary-color: var(--sld-ap-dark-primary-color);
                --sld-ap-secondary-color: var(--sld-ap-dark-secondary-color);
                --sld-ap-accent-color: var(--sld-ap-dark-accent-color);
                --sld-ap-border-color: var(--sld-ap-dark-border-color);
                --sld-ap-shadow-sm: var(--sld-ap-dark-shadow-sm);
            }

            .sld-ap {
                display: flex;
                font-size: var(--sld-ap-font-size-md);
                font-family: var(--sld-ap-font-family);
                gap: var(--sld-ap-spacing-lg);
                color: var(--sld-ap-primary-color);
            }

            /* Hide the default checkbox */
            .sld-ap input[type="checkbox"] {
                appearance: none;
                -webkit-appearance: none;
                -moz-appearance: none;
                width: 20px;
                height: 20px;
                border: 2px solid var(--sld-ap-border-color);
                border-radius: 4px;
                background-color: #f0f0f0;
                position: relative;
                cursor: pointer;
                transition: background-color 0.3s, border-color 0.3s;
            }

            /* Checked state */
            .sld-ap input[type="checkbox"]:checked {
                background-color: var(--sld-ap-accent-color);
                border-color: var(--sld-ap-accent-color);
            }

            .sld-ap input[type="checkbox"]:checked::after {
                content: "";
                position: absolute;
                left: 5px;
                top: 1px;
                width: 3px;
                height: 10px;
                border: solid white;
                border-width: 0 2px 2px 0;
                transform: rotate(45deg);
            }

            /* Hover state */
            .sld-ap input[type="checkbox"]:hover {
                border-color: var(--sld-ap-border-color);
            }

            /* Active state */
            .sld-ap input[type="checkbox"]:active {
                background-color: var(--sld-ap-border-color);
            }


            /* Hide the default radio button */
            .sld-ap input[type="radio"] {
                appearance: none;
                -webkit-appearance: none;
                -moz-appearance: none;
                width: 20px;
                height: 20px;
                border: 2px solid var(--sld-ap-border-color);
                border-radius: 50%;
                background-color: #f0f0f0;
                position: relative;
                vertical-align: middle;
                cursor: pointer;
                transition: background-color 0.3s, border-color 0.3s;
            }

            /* Checked state for radio button */
            .sld-ap input[type="radio"]:checked {
                background-color: var(--sld-ap-accent-color);
                ;
                border-color: var(--sld-ap-accent-color);
                ;
            }

            /* Inner circle when checked */
            .sld-ap input[type="radio"]:checked::after {
                content: "";
                position: absolute;
                top: 4px;
                left: 4px;
                width: 8px;
                height: 8px;
                background-color: white;
                border-radius: 10px;
            }

            /* Hover state */
            .sld-ap input[type="radio"]:hover {
                border-color: var(--sld-ap-border-color);
            }

            /* Active state */
            .sld-ap input[type="radio"]:active {
                background-color: var(--sld-ap-border-color);
            }



            .sld-ap textarea {
                font-family: var(--family-sans-serif);
            }

            .sld-ap fieldset {
                all: unset;
                border: none;
                padding: 0;
                margin: 0;
                background: transparent;
            }

            .sld-ap fieldset.fieldset-radio label,
            .sld-ap fieldset.fieldset-checkbox label {
                font-size: var(--sld-ap-font-size-sm);
                color: var(--sld-ap-primary-color);
            }

            .sld-ap h2 {
                font-family: var(--sld-ap-font-family);
                font-size: var(--sld-ap-font-size-xl);
                margin: var(--sld-ap-spacing-xs) 0;
                font-weight: var(--sld-ap-font-weight-normal);
                color: var(--sld-ap-primary-color);
            }

            .sld-ap p.sld-ap-form-lead {
                margin: 0 var(--sld-ap-spacing-md) var(--sld-ap-spacing-lg) 0;
                font-size: var(--sld-ap-font-size-sm);
                color: var(--sld-ap-secondary-color);

            }

            .sld-col-1 {
                flex-shrink: 0;
                flex-basis: 60%;
            }

            .sld-col-1:last-child {
                flex-shrink: 0;
                flex-basis: fit-content;
            }

            @media only screen and (max-width: 800px) {
                .sld-ap {
                    flex-direction: column;
                }
            }

            .sld-ap-form {
                display: flex;
                flex-direction: column;
            }

            .sld-ap-form_box {
                padding: var(--sld-ap-spacing-lg);
                width: 100%;
                border: 1px solid var(--sld-ap-border-color);
                border-radius: var(--sld-ap-border-radius-lg);
                background-color: var(--sld-ap-background);
                box-shadow:var(--sld-ap-shadow-md);
                box-sizing: border-box;
            }

            .sld_field-wrapper {
                display: flex;
                flex-direction: column;
                gap: var(--sld-ap-spacing-sm);
                justify-content: space-between;
            }

            .sld_field-left {
                display: flex;
                flex-direction: column;
            }

            .sld_field-input {
                min-width: 40%;
                justify-content: flex-end;
                display: flex;
            }

            .sld_field-input fieldset {
                display: flex;
                flex-direction: row;
                flex-wrap: wrap;
                gap: var(--sld-ap-spacing-sm);
            }

            .sld_field-wrapper.multi-checkbox {
                flex-direction: column;
                align-items: stretch;
                gap: var(--sld-ap-spacing-md);
            }

            .sld_field-wrapper.multi-checkbox .sld_field-label {
                display: flex;
                gap: var(--sld-ap-spacing-md);
                flex-wrap: wrap;
            }

            .sld_field-wrapper.multi-checkbox .sld_field-input .sld-ap-form_checkbox {
                display: flex;
                flex-direction: row;
                gap: var(--sld-ap-spacing-sm);
            }

            .sld_field-wrapper.multi-checkbox .sld_field-input {
                justify-content: flex-start;
            }

            .sld_field-wrapper.multi-checkbox .multi-checkbox-option {
                display: flex;
                flex-direction: row;
                gap: var(--sld-ap-spacing-xs);
                align-items: center;
                font-size: var(--sld-ap-font-size-xs);
            }

            label.sld_field-label {
                width: 100%;
            }

            .sld_field-title {
                font-size: var(--sld-ap-font-size-sm);
                font-weight: var(--sld-ap-font-weight-medium);
                color: var(--sld-ap-primary-color);
            }

            .sld_field-description {
                font-size: var(--sld-ap-font-size-xs);
                margin: 0;
                color: var(--sld-ap-secondary-color);
            }

            .sld-ap-form_group {
                width: 100%;
            }

            .sld-ap-form_group>label {
                margin: var(--sld-ap-spacing-xs) 0;
                font-size: var(--sld-ap-font-size-sm);
                display: flex;
                flex-direction: column;
            }

            .sld-ap-form_group>label input,
            .sld-ap-form_group>label textarea,
            .sld-ap-form_group>label select {
                width: 100%;
            }

            .sld-ap-form_group input:not([type='checkbox']):not([type='radio']),
            .sld-ap-form_group textarea,
            .sld-ap-form_group select {
                border: 1px solid var(--sld-ap-border-color);
                padding: var(--sld-ap-spacing-sm);
                border-radius: var(--sld-ap-border-radius-md);
                background: var(--sld-ap-shading);
                color: var(--sld-ap-primary-color);
                box-shadow: var(--sld-ap-shadow-sm);
                width: 100%;
                box-sizing: border-box;
            }

            label.sld_field-label input[type='radio'] {
                width: 25px;
                border-radius: 50%;
                display: inline-block;
            }

            .sld-ap-form_checkbox a {
                color: var(--sld-ap-accent-color);
            }

            .sld-ap-form_submit {
                background: var(--sld-ap-accent-color);
                font-size: var(--sld-ap-font-size-sm);
                color: #fff;
                padding: var(--sld-ap-spacing-md) var(--sld-ap-spacing-lg);
                margin-top: var(--sld-ap-spacing-md);
                border: none !important;
                border-radius: var(--sld-ap-border-radius-lg);
            }

            input#submit_solid_affiliate_login {
                background: var(--sld-ap-accent-color);
                font-size: var(--sld-ap-font-size-sm);
                color: #fff;
                padding: var(--sld-ap-spacing-md) var(--sld-ap-spacing-lg);
                border: none !important;
                border-radius: var(--sld-ap-border-radius-lg);
            }

            .sld-ap-form_group input:focus,
            .sld-ap-form_group input:focus-within,
            .sld-ap-form_group input:focus-visible {
                border: 2px solid var(--sld-ap-accent-color);
                outline: none !important;
                background: var(--sld-ap-background);
            }

            .sld-ap-form_tip {
                font-size: var(--sld-ap-font-size-xs);
                color: var(--sld-ap-primary-color);
            }

            .sld-ap-form_notice {
                display: flex;
                gap: var(--sld-ap-spacing-xs);
                background-color: var(--sld-ap-background);
                margin: var(--sld-ap-spacing-md) 0;
                border: 1px solid var(--sld-ap-border-color);
                color: var(--sld-ap-primary-color);
                line-height: 1.2;
                font-size: var(--sld-ap-font-size-sm);
                border-radius: var(--sld-ap-border-radius-sm);
                padding: var(--sld-ap-spacing-sm);
                align-items: center;
            }
            .sld-ap-form_notice_icon {
                display:flex;
                align-items: center;
            }
            .sld-ap-form_notice_icon svg {
                stroke: var(--sld-ap-secondary-color);
                width: auto;
                height: 24px;
            }

            .sld-ap-form.registration>div {
                display: flex;
                flex-direction: column;
                gap: var(--sld-ap-spacing-lg);
            }

            .sld-ap-form_notice.error svg {
                stroke: var(--sld-ap-error-color);
            }

            .sld-ap-form_notice.error {
                color: var(--sld-ap-error-color);
                background: rgba(var(--sld-ap-error-color), 0.1);
            }

            .sld-ap-form_notice.success svg {
                stroke: var(--sld-ap-success-color);
            }

            .sld-ap-form_notice.success {
                color: var(--sld-ap-success-color);
                background: rgba(var(--sld-ap-success-color), 0.1);
            }

            .sld-ap-form_notice.info svg {
                stroke: var(--sld-ap-info-color);
            }

            .sld-ap-form_notice.info {
                color: var(--sld-ap-info-color);
                background: rgba(var(--sld-ap-info-color), 0.1);
            }

            .sld-ap-form_group a.forgot-pass {
                font-size: var(--sld-ap-font-size-xs);
                color: var(--sld-ap-accent-color);
            }

            .sld-tooltip>svg {
                max-width: 15px;
            }

            label[for="is_accept_affiliate_policy"] p.sld_field-description {
                display: inline-block;
            }

            .sld_field-wrapper.is_accept_affiliate_policy {
                flex-direction: row-reverse;
                align-items: center;
                gap: var(--sld-ap-spacing-xs);
            }

            .sld_field-wrapper.is_accept_affiliate_policy .sld_field-left {
                flex-grow: 1;
            }

            .sld_field-wrapper.is_accept_affiliate_policy .sld_field-left a {
                font-size: var(--sld-ap-font-size-sm);
            }

            .sld_field-wrapper.is_accept_affiliate_policy .sld_field-input {
                flex-shrink: 1;
                min-width: auto;

            }

            #is_accept_affiliate_policy+p::before {
                content: '* ';
                color: var(--sld-ap-error-color);
            }

            .sld-ap-login-component {
                height: fit-content;
            }

            .sld-ap-form_error {
                color: var(--sld-ap-error-color);
                font-size: var(--sld-ap-font-size-xs);
                margin: var(--sld-ap-spacing-xs) 0;
            }

            .powered-by {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  border: 1px solid #ccc; /* Customize border color */
  padding: 10px; /* Adjust padding as needed */
  gap: 8px; /* Space between text and image */
  font-family: Arial, sans-serif; /* Optional: Choose your font */
  font-size: 14px; /* Optional: Adjust font size */
}

.powered-by img {
  height: 20px; /* Adjust logo size as needed */}

  #solid-affiliate-affiliate-portal_login-form {
display:flex;
flex-direction:column;
gap:var(--sld-ap-spacing-md);
  }

        </style>

<?php
        return ob_get_clean();
    }
}
