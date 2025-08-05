<?php

namespace SolidAffiliate\Views\Admin\Settings;

use SolidAffiliate\Controllers\CommissionRatesController;
use SolidAffiliate\Controllers\SettingsController;
use SolidAffiliate\Lib\AffiliateRegistrationForm\AffiliateRegistrationFormFunctions;
use SolidAffiliate\Lib\AffiliateRegistrationForm\FormBuilderControlSerializer;
use SolidAffiliate\Lib\FF;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\SetupWizard;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliatePortal;
use SolidAffiliate\Views\Shared\AdminHeader;

class RootView
{
  const BEFORE_CUSTOMIZE_AFFILIATE_REGISTRATION_FORM_ACTION = 'solid_affiliate/settings/tab/customize_affiliate_registration_form/before';

  /**
   * Renders the entire Admin Settings page.
   *
   * @param Schema $settings_schema
   * @param Object $current_settings
   * 
   * @return string
   */
  public static function render($settings_schema, $current_settings)
  {
    // Configs
    $form_id = 'settings-edit';
    $nonce = SettingsController::NONCE_SUBMIT_SETTINGS;
    $submit_action = SettingsController::POST_PARAM_SUBMIT_SETTINGS;

    // Get the active tab from the $_GET param
    $default_tab = null;
    $current_tab_key = isset($_GET['tab']) ? (string)$_GET['tab'] : $default_tab;

    ob_start();
?>
    <!-- Our admin page content should all be inside .wrap -->
    <div class="wrap">
      <h1></h1>
      <!-- Print the page title -->

      <!-- Here are our tabs -->
      <div class="settings-tabs">

        <?php echo (self::render_settings_nav_tabs($current_tab_key)); ?>
        <div class="tab-content">
          <form action="" method="post" id="<?php echo $form_id ?>">
            <?php switch ($current_tab_key):
              case 'affiliate_portal':
                $tab = Settings::TAB_AFFILIATE_PORTAL_AND_REGISTRATION;
                $tab_schema = Settings::schema_for_tab($tab);
                echo SettingsTabView::render($tab_schema, $current_settings);
                break;
              case 'fraud_prevention':
                $tab = Settings::TAB_FRAUD_PREVENTION;
                $tab_schema = Settings::schema_for_tab($tab);
                echo SettingsTabView::render($tab_schema, $current_settings);
                break;
              case 'integrations':
                $tab = Settings::TAB_INTEGRATIONS;
                $tab_schema = Settings::schema_for_tab($tab);
                echo SettingsTabView::render($tab_schema, $current_settings);
                break;
              case 'emails':
                $tab = Settings::TAB_EMAILS;
                $tab_schema = Settings::schema_for_tab($tab);
                if (FF::is_off('customizable_email_notifications')) {
                  $need_to_upgrade_div = '
                  <div class="upgrade-card">
                  <div class="content">
                  <img src="https://solidaffiliate.com/marketing-screens/customize-email-template.svg" alt="Customize email templates">
                      <h2>' . __('Customize the email templates the way you want', 'solid-affiliate') . '</h2>
                      <p>' . __('Customizable email notifications are only available in the Pro version of Solid Affiliate. You can still use all the settings below, but if you want to edit the built-in email notifications, whitelabel them, etc., you need to upgrade.', 'solid-affiliate') . '</p>
                      <a class="sld-button primary large" href="https://solidaffiliate.com/pricing" target="_blank">' . __('Upgrade to Pro', 'solid-affiliate') . '</a>
                  </div>
                      </div>';
                } else {
                  $need_to_upgrade_div = '';
                };
                echo $need_to_upgrade_div;
                echo SettingsTabView::render($tab_schema, $current_settings);
                break;
              case 'misc':
                $tab = Settings::TAB_MISC;
                $tab_schema = Settings::schema_for_tab($tab);
                echo SettingsTabView::render($tab_schema, $current_settings);
                break;
              case 'recurring_referrals':
                $tab = Settings::TAB_RECURRING_REFERRALS;
                $tab_schema = Settings::schema_for_tab($tab);
                echo "<p>" . __('Subscription Renewal Referral tracking will only work if you have WooCommerce Subscriptions installed and activated.', 'solid-affiliate') . "</p>";
                $wc_sub_is_active_badge = SetupWizard::wc_sub_is_active_badge();
                echo "<div class='sld-admin-card'>" . __('WooCommerce Subscriptions plugin status: ', 'solid-affiliate') . $wc_sub_is_active_badge . "</div>";
                $commission_settings_url = URLs::admin_path(CommissionRatesController::ADMIN_PAGE_KEY, true);
                echo SettingsTabView::render($tab_schema, $current_settings);
                echo "<p>" . __('Subscription Renewal Referral rates will not be used during the initial purchase of a subscription, only on renewels. The initial purchase of a subscription is handled as a regular order, and will follow the standard rules for calculating commission', 'solid-affiliate') . ' ' .  __('(which you can edit in ', 'solid-affiliate') .
                  "<a href={$commission_settings_url}>" . __("Commission Rates", 'solid-affiliate') . "</a>). " .
                  __("Any future renewels of a subscription (for example, once per month for a monthly subscription) will take the below Subscription Renewal Referral settings into account.", 'solid-affiliate') . "</p>";
                break;
              case 'customize_registration_form':
                do_action(self::BEFORE_CUSTOMIZE_AFFILIATE_REGISTRATION_FORM_ACTION);
                $tab = Settings::TAB_CUSTOMIZE_REGISTRATION_FORM;
                $tab_schema = Settings::schema_for_tab($tab);
                $form_schema = AffiliatePortal::get_affiliate_registration_schema();
                $restore_defaults_form_schema = AffiliatePortal::get_restore_default_affiliate_registration_schema();

                echo (FF::maybe_overlay('customizable_affiliate_registration'));

                ////////////////////////////////////////////////////////////////////////////////
                // serialize the form schema to pass to the view
                $controls = FormBuilderControlSerializer::from_schema_to_controls($form_schema);
                $controls_json = AffiliateRegistrationFormFunctions::encode_to_json_string($controls);
                if (is_null($controls_json)) {
                  $controls_json_for_html = '';
                } else {
                  $controls_json_for_html = esc_html(htmlspecialchars($controls_json));
                }
                ////////////////////////////////////////////////////////////////////////////////
                // serialize the default form schema to pass to the view, so we can have a "reset form" button
                $restore_default_controls = FormBuilderControlSerializer::from_schema_to_controls($restore_defaults_form_schema);
                $restore_default_controls_json = AffiliateRegistrationFormFunctions::encode_to_json_string($restore_default_controls);
                if (is_null($restore_default_controls_json)) {
                  $restore_default_controls_json_for_html = '';
                } else {
                  $restore_default_controls_json_for_html = esc_html(htmlspecialchars($restore_default_controls_json));
                }
                $locked_required_fields_json = AffiliateRegistrationFormFunctions::encode_to_json_string(AffiliatePortal::REQUIRED_AFFILIATE_REGISTRATION_ONLY_SCHEMA_ENTRIES);
                $locked_optional_fields_json = AffiliateRegistrationFormFunctions::encode_to_json_string(Affiliate::SCHEMA_ENTRIES_THAT_CAN_ALSO_BE_ON_THE_REGISTRATION_FORM);
                $error_msg_header = __('An error occured when trying to save your custom form', 'solid-affiliate') . ':';
                echo "<div id='form-builder-data' 
                 data-controls_json='{$controls_json_for_html}' 
                 data-restore_default_controls_json='{$restore_default_controls_json_for_html}'
                 data-locked_required_fields='{$locked_required_fields_json}'
                 data-locked_optional_fields='{$locked_optional_fields_json}'
                 data-error_msg_header='{$error_msg_header}'
                 >
                </div>";
                echo SettingsTabView::render($tab_schema, $current_settings);
                echo "<div class='fb-editor-loading'><div class='lds-ellipsis'><div></div><div></div><div></div><div></div></div></div>";
                if (FF::is_on('customizable_affiliate_registration')) {
                  echo "<div id='fb-editor'></div>";
                } else {
                  echo "";
                  return;
                }
                break;
              default:
                $tab = Settings::TAB_GENERAL;
                $tab_schema = Settings::schema_for_tab($tab);
                echo SettingsTabView::render($tab_schema, $current_settings);
                break;
            endswitch; ?>

            <!-- TODO:3: There are two hidden inputs with the id '' ?? -->
            <?php echo FormBuilder::build_hidden_field('settings_tab', $tab) ?>
            <?php wp_nonce_field($nonce); ?>
            <?php submit_button(__("Update Settings", 'solid-affiliate'), 'primary', $submit_action); ?>
          </form>

        </div>

      </div>

    </div>

    <?php
    $res = ob_get_clean();
    if ($res) {
      return $res;
    } else {
      return __("Error rendering settings.", 'solid-affiliate');
    }
  }

  /**
   * @param string|null $current_tab
   * @return string
   */
  public static function render_settings_nav_tabs($current_tab)
  {
    ob_start();
    ?>

    <div class="nav-tab-wrapper">
      <a href="?page=solid-affiliate-settings" class="nav-tab <?php if ($current_tab === null) : ?>nav-tab-active<?php endif; ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path fill-rule="evenodd" clip-rule="evenodd" d="M12 4C12.4015 4 12.7961 4.02958 13.1818 4.08668L13.7622 5.84566C14.2713 5.99116 14.7544 6.19811 15.2031 6.45798L16.8716 5.65386C17.4621 6.1078 17.9874 6.64229 18.4311 7.2409L17.5976 8.89484C17.8492 9.34747 18.0475 9.8338 18.184 10.3454L19.9324 10.9554C19.977 11.2973 20 11.646 20 12C20 12.4015 19.9704 12.7961 19.9133 13.1818L18.1543 13.7622C18.0088 14.2713 17.8019 14.7544 17.542 15.2031L18.3461 16.8716C17.8922 17.4621 17.3577 17.9874 16.7591 18.4311L15.1052 17.5976C14.6525 17.8492 14.1662 18.0475 13.6546 18.184L13.0446 19.9324C12.7027 19.977 12.354 20 12 20C11.5985 20 11.2039 19.9704 10.8182 19.9133L10.2378 18.1543C9.72875 18.0088 9.2456 17.8019 8.79693 17.542L7.12836 18.3461C6.53791 17.8922 6.01261 17.3577 5.5689 16.7591L6.40242 15.1052C6.15061 14.6522 5.95218 14.1655 5.81565 13.6535L4.06743 13.0434C4.02295 12.7019 4 12.3536 4 12C4 11.5985 4.02958 11.2039 4.08668 10.8182L5.84566 10.2378C5.99116 9.72875 6.19811 9.2456 6.45798 8.79693L5.65386 7.12836C6.1078 6.53791 6.64229 6.01261 7.2409 5.5689L8.89484 6.40242C9.3478 6.15061 9.83452 5.95218 10.3465 5.81565L10.9566 4.06743C11.2981 4.02295 11.6464 4 12 4V4Z" stroke="#111127" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M11.9999 15.4284C13.8934 15.4284 15.4284 13.8934 15.4284 11.9999C15.4284 10.1063 13.8934 8.57129 11.9999 8.57129C10.1063 8.57129 8.57129 10.1063 8.57129 11.9999C8.57129 13.8934 10.1063 15.4284 11.9999 15.4284Z" stroke="#111127" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <?php _e('General', 'solid-affiliate') ?></a>
      <a href="?page=solid-affiliate-settings&tab=affiliate_portal" class="nav-tab <?php if ($current_tab === 'affiliate_portal') : ?>nav-tab-active<?php endif; ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path fill-rule="evenodd" clip-rule="evenodd" d="M11.9999 2.85693C13.8934 2.85693 15.4284 4.39196 15.4284 6.28551V8.57122C15.4284 10.4648 13.8934 11.9998 11.9999 11.9998C10.1063 11.9998 8.57129 10.4648 8.57129 8.57122V6.28551C8.57129 4.39196 10.1063 2.85693 11.9999 2.85693Z" stroke="#111127" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M20 5.14258V9.71401" stroke="#111127" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M22.2858 7.42822H17.7144" stroke="#111127" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
          <path fill-rule="evenodd" clip-rule="evenodd" d="M20 18.8571V18.0249C20 14.3833 15.7871 12 12 12C8.2129 12 4 14.3833 4 18.0249V18.8571C4 19.4883 4.51167 20 5.14286 20H18.8571C19.4883 20 20 19.4883 20 18.8571Z" stroke="#111127" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <?php _e('Portal & Registration', 'solid-affiliate') ?></a>
      <a href="?page=solid-affiliate-settings&tab=customize_registration_form" class="nav-tab <?php if ($current_tab === 'customize_registration_form') : ?>nav-tab-active<?php endif; ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M20 1.71436V6.28578M22.2857 4.00007H17.7143M16 10.8572L8 6.28578M4.57143 9.14293L10.9166 12.5601C11.2496 12.7393 11.6218 12.8332 12 12.8332C12.3782 12.8332 12.7504 12.7393 13.0834 12.5601L19.4286 9.14293M12 13.1429V20.5715M13.1337 4.64807L18.848 7.91321C19.198 8.11314 19.4889 8.40207 19.6912 8.75071C19.8935 9.09934 20.0001 9.49527 20 9.89836V15.2446C20.0001 15.6477 19.8935 16.0437 19.6912 16.3923C19.4889 16.7409 19.198 17.0299 18.848 17.2298L13.1337 20.4949C12.7884 20.6922 12.3977 20.7959 12 20.7959C11.6023 20.7959 11.2116 20.6922 10.8663 20.4949L5.152 17.2298C4.80199 17.0299 4.51108 16.7409 4.30877 16.3923C4.10645 16.0437 3.99993 15.6477 4 15.2446V9.89836C3.99993 9.49527 4.10645 9.09934 4.30877 8.75071C4.51108 8.40207 4.80199 8.11314 5.152 7.91321L10.8663 4.64807C11.2116 4.45083 11.6023 4.34709 12 4.34709C12.3977 4.34709 12.7884 4.45083 13.1337 4.64807Z" stroke="#111127" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <?php (string)_e('Form customizer', 'solid-affiliate') . FF::pill(); ?>
      </a>
      <a href="?page=solid-affiliate-settings&tab=integrations" class="nav-tab <?php if ($current_tab === 'integrations') : ?>nav-tab-active<?php endif; ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path fill-rule="evenodd" clip-rule="evenodd" d="M12.0002 4L18.8574 8.57143V15.4286L12.0002 20L5.14307 15.4286V8.57143L12.0002 4Z" stroke="#111127" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M12.0002 15.4286C13.8938 15.4286 15.4288 13.8935 15.4288 12C15.4288 10.1065 13.8938 8.57143 12.0002 8.57143C10.1067 8.57143 8.57164 10.1065 8.57164 12C8.57164 13.8935 10.1067 15.4286 12.0002 15.4286Z" stroke="#111127" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <?php _e('Integrations', 'solid-affiliate') ?></a>
      <a href="?page=solid-affiliate-settings&tab=emails" class="nav-tab <?php if ($current_tab === 'emails') : ?>nav-tab-active<?php endif; ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M6.28571 8.57164L12 12.0002L17.7143 8.57164M4 7.42878V16.5716C4 17.834 5.02335 18.8574 6.28571 18.8574H17.7143C18.9767 18.8574 20 17.834 20 16.5716V7.42878C20 6.16642 18.9767 5.14307 17.7143 5.14307H6.28571C5.02335 5.14307 4 6.16642 4 7.42878Z" stroke="#111127" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <?php _e('Emails', 'solid-affiliate') ?></a>
      <a href="?page=solid-affiliate-settings&tab=fraud_prevention" class="nav-tab <?php if ($current_tab === 'fraud_prevention') : ?>nav-tab-active<?php endif; ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M6 21V19C6 17.9391 6.42143 16.9217 7.17157 16.1716C7.92172 15.4214 8.93913 15 10 15H12M22 16C22 20 19.5 22 18.5 22C17.5 22 15 20 15 16C16 16 17.5 15.5 18.5 14.5C19.5 15.5 21 16 22 16ZM8 7C8 8.06087 8.42143 9.07828 9.17157 9.82843C9.92172 10.5786 10.9391 11 12 11C13.0609 11 14.0783 10.5786 14.8284 9.82843C15.5786 9.07828 16 8.06087 16 7C16 5.93913 15.5786 4.92172 14.8284 4.17157C14.0783 3.42143 13.0609 3 12 3C10.9391 3 9.92172 3.42143 9.17157 4.17157C8.42143 4.92172 8 5.93913 8 7Z" stroke="#111127" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>

        <?php _e('Fraud prevention', 'solid-affiliate') ?></a>
      <a href="?page=solid-affiliate-settings&tab=misc" class="nav-tab <?php if ($current_tab === 'misc') : ?>nav-tab-active<?php endif; ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M10.8571 6.28551H6.28571C5.02335 6.28551 4 7.30885 4 8.57122V16.5712C4 17.8336 5.02335 18.8569 6.28571 18.8569H14.2857C15.5481 18.8569 16.5714 17.8336 16.5714 16.5712V11.9998M16.5714 2.85693V9.71408M20 6.28551H13.1429" stroke="#111127" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <?php _e('Misc', 'solid-affiliate') ?></a>
      <a href="?page=solid-affiliate-settings&tab=recurring_referrals" class="nav-tab <?php if ($current_tab === 'recurring_referrals') : ?>nav-tab-active<?php endif; ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M9.71564 13.1429L6.28592 16.5714M6.28592 16.5714L9.71564 20M6.28592 16.5714H15.4288C17.3223 16.5714 18.8574 15.0364 18.8574 13.1429V10.8571M14.2848 10.8571L17.7145 7.42857M17.7145 7.42857L14.2848 4M17.7145 7.42857L8.57164 7.42857C6.67809 7.42857 5.14307 8.9636 5.14307 10.8571V13.1429" stroke="#111127" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <?php _e('Subscription renewal referrals', 'solid-affiliate') ?></a>
    </div>

<?php
    $res = ob_get_clean();
    return $res;
  }
}
