<?php

namespace SolidAffiliate\Views\Admin\PayAffiliates;

use SolidAffiliate\Addons\Core;
use SolidAffiliate\Addons\StoreCredit\Addon as StoreCreditAddon;
use SolidAffiliate\Controllers\PayAffiliatesController;
use SolidAffiliate\Lib\FF;
use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\PayAffiliatesFunctions;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;
use SolidAffiliate\Lib\RandomData;
use SolidAffiliate\Lib\Tags;

class NewBulkPayoutPreviewView
{

   /**
    * Undocumented function
    * 
    * @return string
    */
   public static function render()
   {

      $nonce = PayAffiliatesController::NONCE_SUBMIT_BULK_PAYOUT_PREVIEW;
      $submit_action = PayAffiliatesController::POST_PARAM_SUBMIT_BULK_PAYOUT_PREVIEW;

      /////////////////////////////////////////////
      // Grace Period Calculations
      $r = PayAffiliatesFunctions::data_for_grace_period_referrals();
      $modal_id = 'sld-modal-' . RandomData::string(4);

      $grace_period_days = $r['grace_period_days'];
      $affiliates_owed_count = $r['affiliates_owed_count'];
      $total_payout_formatted = $r['amount_owed_formatted'];
      $disable_grace_period_option = empty($affiliates_owed_count);
      $grace_period_settings_link = URLs::settings(Settings::TAB_FRAUD_PREVENTION, false, Settings::KEY_REFERRAL_GRACE_PERIOD_NUMBER_OF_DAYS);
      $amount_preview_before_grace_period = $disable_grace_period_option ?
         __("There are no unpaid Referrals in this period", 'solid-affiliate') : sprintf(__('A total of %1$s due to %2$s affiliates', 'solid-affiliate'), $total_payout_formatted, $affiliates_owed_count);

      /////////////////////////////////////////////


      $all_time_range = new PresetDateRangeParams(['preset_date_range' => 'all_time']);
      $start_date = $all_time_range->computed_start_date();
      $end_date = $all_time_range->computed_end_date();
      $total_payout = PayAffiliatesFunctions::total_amount_owed_for_date_range($start_date, $end_date);
      $total_payout_formatted = Formatters::money($total_payout);
      $affiliates_owed = PayAffiliatesFunctions::find_affiliates_owed_for_date_range($start_date, $end_date);
      $affiliates_owed_count = count($affiliates_owed);
      $disable_all_time_option = empty($affiliates_owed_count);
      $amount_preview_all_time = $disable_all_time_option ? __("There are no unpaid Referrals in this period", 'solid-affiliate') : sprintf(__('A total of %1$s due to %2$s affiliates', 'solid-affiliate'), $total_payout_formatted, $affiliates_owed_count);


      // PayPal option
      $is_paypal_enabled = Settings::is_paypal_integration_configured_and_enabled();
      $is_paypal_currency_valid = WooCommerceIntegration::is_current_currency_valid_for_paypal_integration();
      $paypal_disabled_option = ($is_paypal_enabled && $is_paypal_currency_valid) ? '' : 'disabled';

      $paypal_integration_settings_link = URLs::settings(Settings::TAB_INTEGRATIONS);
      $paypal_label_enabled = __('Send mass payments via PayPal', 'solid-affiliate');
      $paypal_label_disabled =  '<div class="disabled-pill">' . __('Currently disabled', 'solid-affiliate') . '</div>' . __('To use, enable ', 'solid-affiliate') . "<a href='{$paypal_integration_settings_link}'>" . __('the PayPal Integration', 'solid-affiliate') . "</a>.";
      $paypal_label = $is_paypal_enabled ? $paypal_label_enabled : $paypal_label_disabled;

      // Store Credit option
      $is_store_credit_enabled = Core::is_addon_enabled(StoreCreditAddon::ADDON_SLUG);
      $store_credit_disabled_option = $is_store_credit_enabled ? '' : 'disabled';
      $addons_link = URLs::admin_path(Core::ADDONS_PAGE_SLUG);
      $store_credit_label_enabled = __('Pay commissions in store credits', 'solid-affiliate');
      $store_credit_label_disabled = '<div class="disabled-pill">' . __('Currently disabled', 'solid-affiliate') . '</div>' . __('To use, enable ', 'solid-affiliate') . "<a href='{$addons_link}'>" . __('the store credit addon', 'solid-affiliate') . "</a>.";
      $store_credit_label = $is_store_credit_enabled ? $store_credit_label_enabled : $store_credit_label_disabled;

      // Currency
      $payout_currency = WooCommerceIntegration::get_current_currency();

      ob_start();
?>
      <style>
         .sld-pay-affiliates_card {
            background: #fff;
            border: 1px solid #D6D6D6;
            border-radius: var(--sld-radius-sm);
            padding: 30px;
            position:relative;
            box-sizing: border-box;
            overflow:hidden;
         }

         .sld-pay-affiliates_card p {
            margin: 0 !important;
         }

         .sld-pay-affiliates_card h2 {
            font-size: 16px;
            margin-bottom: 5px;
            margin-top: 0;
            line-height: 16px;
         }

         .sld-pay-affiliates_card p {
            font-size: 12px;
         }

         .sld-pay-affiliates_card .step-bullet {
            background: #FAAC50;
            font-size: 14px;
            width: 24px;
            line-height: 24px;
            text-align: center;
            height: 24px;
            display: inline-block;
            border-radius: 24px;
            margin-right: 10px;
         }

         .sld-pay-affiliates_card .pill {
            background: #ffe3fe;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 5px;
         }

         .sld-pay-affiliates_card-head {
            display: flex;
         }

         .sld-pay-affiliates_card-head h2 {
            margin-top: 5px !important;
         }

         .sld-pay-affiliates_card-item:last-child {
            margin-bottom: 0;
         }

         #new-bulk-payout-preview label.label-card {
            display: block;
            background: #fdfdfd;
            border: 1px solid #D6D6D6;
            border-radius: 5px;
            padding: 20px 20px 20px 40px;
            width: calc(100%-30px);
            -webkit-transition: 0.2s;
            -moz-transition: 0.2s;
            transition: 0.2s;
            position: relative;
         }

         input[type="radio"]:checked::before {
            background-color: #FF4B0D !important;
         }


         span.label-head {
            display: block;
            font-size: 14px;
            font-weight: 400;
            margin-bottom: 5px;
            color: var(--sld-primary);
         }

         .label-body {
            font-size: 12px;
            color: #373E4D;
         }

         #new-bulk-payout-preview input[type="radio"] {
            position: relative;
            top: 40px;
            left: 16px;
            border: 1px solid #D6D6D6;
            z-index: 999;
         }

         #new-bulk-payout-preview input[type="radio"]:checked+label {
            border-color: #FF4B0D;
            background: #FFF2EE;
            box-shadow: 0px 0px 0px 3px rgba(227, 87, 39, 0.1);
            -webkit-transition: 0.2s;
            -moz-transition: 0.2s;
            transition: 0.2s;
         }

         #new-bulk-payout-preview input[type="radio"]:checked+label>.label-card_check {
            display: block;
         }

         #new-bulk-payout-preview input[type="radio"]:disabled+label {
            background: #D6D6D6;
            opacity: 0.8;
         }

         #new-bulk-payout-preview input[type="radio"]:disabled+label:hover {
            cursor: not-allowed !important;
         }

         .sld-pay-affiliates-wizard {
            display: flex;
            justify-content: space-around;
            flex-direction: column;
         }

         @media only screen and (max-width: 1175px) {
            .sld-pay-affiliates-wizard {
               display: flex;
               justify-content: space-around;
               flex-direction: row;
            }
         }

         .sld-pay-affiliates_card-wrapper-div {
            display: flex;
            gap: 20px;
            margin-top: -20px;
            ;
         }

         .sld-pay-affiliates_card-wrapper {
            margin-top: 20px;
         }

         .sld-pay-affiliates_card-item {
            width: 33.33%;
         }

         .mb-2 {
            margin-bottom: 20px;
         }

         .optional-filters {
            display: flex;
            width: 100%;
            justify-content: stretch;
            gap: 20px;
         }

         .optional-filters>div>select,
         .optional-filters>div>button,
         .optional-filters>div>input {
            width: 100% !important;
         }

         .optional-filters>div {
            flex: 1;
         }

         .sld-pay-affiliates_card-seperator {
            height: 40px;
            width: 60px;
            border-right: 2px solid #D6D6D6;
         }

         .sld-pay-affiliates_card-wrapper .values {
            flex-direction: row;
         }

         .values {
            display: flex;
            gap: 10px;
            margin: 0;
            border-radius: 10px;
            flex-wrap: wrap;
            transition: margin 200ms ease-in-out
         }

         .values:has(.value-row) {
            margin: 20px 0;
         }

         .value-row {
            background: #C5ECDB;
            display: flex;
            gap: 5px;
            align-items: center;
            padding: 10px 20px;
            line-height: 14px;
            font-weight: 400;
            border-radius: var(--sld-radius-sm);
            text-decoration: underline;
         }

         .remove-value {
            background: transparent;
            border: none;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border-radius: var(--sld-radius-sm);
            cursor: pointer;
         }

         .button.filter,
         .button.filter:hover,
         .button.filter:focus {
            background: var(--sld-primary);
            color: #fff;
            text-transform: capitalize;
         }

         .disabled-pill {
            background: #fff;
            position: absolute;
            right: 10px;
            top: 0;
            margin-top: -10px;
            padding: 5px 10px;
            border-radius: 5px;
            line-height: 12px;
            font-size: 12px;
            color: #444444;
            border: 2px solid #dedede;
         }
      </style>

      <form action="" method="post" id="new-bulk-payout-preview">
         <div class="sld-pay-affiliates_card">
            <div class="sld-pay-affiliates_card-head">
               <div class="step-bullet">1</div>
               <div>
                  <h2><?php _e('Select referrals', 'solid-affiliate') ?></h2>
                  <p><?php _e('Filter the unpaid referrals you want to include in this bulk payout.', 'solid-affiliate') ?></p>
               </div>
            </div>
            <div class="sld-pay-affiliates_card-wrapper">
               <div class="sld-pay-affiliates_card-wrapper-div">
                  <div class="sld-pay-affiliates_card-item">
                     <input type="radio" name="filter_choice" id="before_refund_grace_period" class="regular-text" value="before_refund_grace_period" required="" <?php echo ($disable_grace_period_option ? 'disabled' : '') ?>>
                     <label class="label-card" for="before_refund_grace_period">
                        <span class="label-head"><?php echo sprintf(__('Referrals older than %1$d days', 'solid-affiliate'), $grace_period_days) ?>
                           <svg width="13" height="14" viewBox="0 0 13 14" fill="none" xmlns="http://www.w3.org/2000/svg" class="sld-tooltip" data-html="true" data-sld-tooltip-content="
                                 <div style='max-width:300px; font-weight : 400; padding:10px; font-size:12px'><strong>Recommended.</strong> Pay any unpaid Referrals which have passed the refund grace period. You can configure the grace period under <a href='<?php echo ($grace_period_settings_link) ?>'>Settings > Fraud Prevention</a></div>
                                 " aria-expanded="false">
                              <path fill-rule="evenodd" clip-rule="evenodd" d="M6.15839 0.598308C6.23499 0.534773 6.33138 0.5 6.4309 0.5C6.53042 0.5 6.62681 0.534773 6.70341 0.598308C8.30922 1.93405 10.3067 2.70986 12.3932 2.80813C12.4872 2.81102 12.5772 2.84679 12.6475 2.90922C12.7179 2.97164 12.7641 3.05677 12.7782 3.14977C12.8337 3.56778 12.8618 3.99382 12.8618 4.42791C12.8618 8.57745 10.2412 12.1153 6.56434 13.4762C6.47822 13.5079 6.38359 13.5079 6.29746 13.4762C2.62059 12.1153 0 8.57745 0 4.42711C0 3.99463 0.0281353 3.56778 0.0836018 3.14977C0.0976919 3.05664 0.144044 2.97141 0.214566 2.90897C0.285089 2.84653 0.375302 2.81084 0.469456 2.80813C2.5557 2.70946 4.55291 1.93418 6.15839 0.598308ZM9.5314 5.38451C9.62542 5.2551 9.66418 5.09364 9.63916 4.93565C9.61413 4.77766 9.52737 4.63608 9.39796 4.54206C9.26855 4.44804 9.10709 4.40928 8.9491 4.4343C8.79111 4.45933 8.64953 4.54609 8.55551 4.6755L5.75566 8.526L4.2444 7.01474C4.18875 6.95719 4.12221 6.91129 4.04864 6.87973C3.97506 6.84816 3.89594 6.83157 3.81589 6.83091C3.73584 6.83026 3.65646 6.84555 3.58238 6.8759C3.5083 6.90625 3.44101 6.95104 3.38443 7.00768C3.32785 7.06431 3.28311 7.13165 3.25283 7.20575C3.22255 7.27986 3.20733 7.35926 3.20807 7.43931C3.2088 7.51936 3.22547 7.59847 3.2571 7.67201C3.28873 7.74555 3.33469 7.81205 3.3923 7.86764L5.40196 9.8773C5.46356 9.93893 5.53778 9.98648 5.61952 10.0167C5.70126 10.0469 5.78857 10.059 5.87544 10.0522C5.96231 10.0454 6.04668 10.0198 6.12273 9.97728C6.19877 9.93474 6.26469 9.87622 6.31595 9.80575L9.5314 5.38451Z" fill="#2E9557" />
                           </svg>
                        </span>
                        <div class="label-body"><?php echo ($amount_preview_before_grace_period) ?></div>
                     </label>
                  </div>
                  <script>
                     jQuery(document).ready(function() {
                        jQuery('.sld-pay-affiliates_card-item label[for="custom_range"]').find('#start_date', '#end_date').on('change', function() {
                           var el = jQuery('#custom_range');
                           if (el.prop('checked') !== true) {
                              el.prop('checked', true);
                           };
                        });
                     });
                  </script>
                  <div class="sld-pay-affiliates_card-item">
                     <input type="radio" name="filter_choice" id="custom_range" class="regular-text" value="custom_range" required="" data-micromodal-trigger="<?php echo ($modal_id); ?>">
                     <label class="label-card" for="custom_range">
                        <span class="label-head"><?php _e('Referrals in custom Range', 'solid-affiliate') ?></span>
                        <div class="label-body"><?php _e('Filter Referrals by date range', 'solid-affiliate') ?></div>
                     </label>
                     <div class="modal micromodal-slide" id="<?php echo ($modal_id); ?>" aria-hidden="true">
                        <div class="modal__overlay" tabindex="-1" data-micromodal-close>
                           <div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="<?php echo ($modal_id); ?>-title">
                              <header class="modal__header">
                                 <h2 class="modal__title" id="<?php echo ($modal_id); ?>-title">
                                    <?php _e('Custom date range', 'solid-affiliate') ?>
                                 </h2>
                                 <a class="modal__close" aria-label="Close modal" data-micromodal-close></a>
                              </header>
                              <main class="modal__content" id="<?php echo ($modal_id); ?>-content">
                                 <div class="modal__content_text"><?php _e('Choose a predefined date range or specify your own.', 'solid-affiliate') ?></div>
                                 <?php echo FormBuilder::render_solid_date_picker() ?>
                              </main>
                              <footer class="modal__footer">
                                 <a class="modal__btn" data-micromodal-close aria-label="Close this dialog window">
                                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                                       <path fill-rule="evenodd" clip-rule="evenodd" d="M3.85686 2.14282H14.1656C15.1124 2.14282 15.8799 2.91033 15.8799 3.85711V14.1349C15.8799 15.0386 15.1806 15.779 14.2936 15.8445L14.1574 15.8492L3.84864 15.7997C2.90508 15.7951 2.14258 15.029 2.14258 14.0854V3.85711C2.14258 2.91033 2.91009 2.14282 3.85686 2.14282V2.14282Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                       <path d="M2.14258 5.57129H15.88" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                       <path d="M12.4275 13.2856C12.9008 13.2856 13.2846 12.9018 13.2846 12.4284C13.2846 11.955 12.9008 11.5713 12.4275 11.5713C11.9541 11.5713 11.5703 11.955 11.5703 12.4284C11.5703 12.9018 11.9541 13.2856 12.4275 13.2856Z" fill="white" />
                                    </svg>
                                    <?php _e('Confirm date range', 'solid-affiliate') ?>
                                 </a>
                              </footer>
                           </div>
                        </div>
                     </div>
                  </div>
                  <div class="sld-pay-affiliates_card-item">
                     <input type="radio" name="filter_choice" id="all_referrals" class="regular-text" value="all_referrals" required="" <?php echo ($disable_all_time_option ? 'disabled' : '') ?>>
                     <label class="label-card" for="all_referrals">
                        <span class="label-head"><?php _e('All unpaid referrals', 'solid-affiliate') ?>
                           <svg width="13" height="14" viewBox="0 0 13 14" fill="none" xmlns="http://www.w3.org/2000/svg" class="sld-tooltip" data-html="true" data-sld-tooltip-content="
                                 <div style='max-width:300px; font-weight : 400; padding:10px; font-size:12px'><strong>Not recommended.</strong> Pay all unpaid Referrals, including the ones that are still in the grace period.</div>
                                 " aria-expanded="false">
                              <path fill-rule="evenodd" clip-rule="evenodd" d="M6.70354 0.59807C6.62687 0.53468 6.5305 0.5 6.43102 0.5C6.33154 0.5 6.23518 0.53468 6.15851 0.59807C4.55278 1.93413 2.55522 2.71023 0.468661 2.80873C0.374645 2.81163 0.284622 2.8474 0.214263 2.90983C0.143905 2.97225 0.0976685 3.05738 0.083604 3.15038C0.0277769 3.57361 -0.000151224 4.00005 6.15795e-07 4.42694C6.15796e-07 8.57736 2.62064 12.1152 6.29758 13.4762C6.38371 13.5079 6.47834 13.5079 6.56447 13.4762C10.2414 12.1152 12.862 8.57736 12.862 4.42774C12.862 3.99365 12.8339 3.56759 12.7784 3.14958C12.7644 3.05644 12.718 2.97121 12.6475 2.90877C12.577 2.84633 12.4867 2.81064 12.3926 2.80793C10.3063 2.70926 8.30905 1.93316 6.70354 0.597266V0.59807ZM6.43102 3.62306C6.59092 3.62306 6.74428 3.68658 6.85734 3.79965C6.97041 3.91272 7.03393 4.06607 7.03393 4.22597V7.03954C7.03393 7.19944 6.97041 7.3528 6.85734 7.46586C6.74428 7.57893 6.59092 7.64245 6.43102 7.64245C6.27112 7.64245 6.11777 7.57893 6.0047 7.46586C5.89163 7.3528 5.82811 7.19944 5.82811 7.03954V4.22597C5.82811 4.06607 5.89163 3.91272 6.0047 3.79965C6.11777 3.68658 6.27112 3.62306 6.43102 3.62306ZM6.43102 10.858C6.64422 10.858 6.84869 10.7733 6.99945 10.6225C7.15021 10.4718 7.2349 10.2673 7.2349 10.0541C7.2349 9.84088 7.15021 9.63641 6.99945 9.48566C6.84869 9.3349 6.64422 9.25021 6.43102 9.25021C6.21782 9.25021 6.01335 9.3349 5.8626 9.48566C5.71184 9.63641 5.62714 9.84088 5.62714 10.0541C5.62714 10.2673 5.71184 10.4718 5.8626 10.6225C6.01335 10.7733 6.21782 10.858 6.43102 10.858Z" fill="#B66C6C" />
                           </svg>
                        </span>
                        <div class="label-body"><?php echo ($amount_preview_all_time) ?></div>
                     </label>
                  </div>
               </div>
            </div>
            <div>
            </div>
         </div>
         <div class="sld-pay-affiliates_card-seperator"></div>
         <?php echo (self::render_bulk_payout_filters_component()) ?>

         <div class="sld-pay-affiliates_card-seperator"></div>
         <div class="sld-pay-affiliates_card">
            <div class="sld-pay-affiliates_card-head">
               <div class="step-bullet">3</div>
               <div>
                  <h2><?php _e('Payment method', 'solid-affiliate') ?></h2>
                  <p><?php _e('Select the payment method you want to use for this payout.', 'solid-affiliate') ?></p>
               </div>
            </div>
            <div class="sld-pay-affiliates_card-wrapper">
               <div class="sld-pay-affiliates_card-wrapper-div">


                  <!-- Manual Payout Method -->
                  <div class="sld-pay-affiliates_card-item">
                     <input type="radio" name="bulk_payout_method" id="manual" class="regular-text" value="manual" required="">
                     <label class="label-card" for="manual">
                        <span class="label-head"><?php _e('Manual', 'solid-affiliate') ?></span>
                        <div class="label-body"><?php _e('Export a CSV file of the Payout data.', 'solid-affiliate') ?></div>
                     </label>
                  </div>

                  <!-- PayPal Payout Method -->
                  <div class="sld-pay-affiliates_card-item">
                     <input type="radio" name="bulk_payout_method" id="paypal" class="regular-text" value="paypal" required="" <?php echo $paypal_disabled_option ?>>
                     <label class="label-card" for="paypal">
                        <span class="label-head"><?php _e('PayPal Bulk Payout', 'solid-affiliate') ?></span>
                        <div class="label-body"><?php echo ($paypal_label) ?></div>
                     </label>
                  </div>

                  <!-- Store Credit Payout Method -->
                  <div class="sld-pay-affiliates_card-item">
                     <input type="radio" name="bulk_payout_method" id="store_credit" class="regular-text" value="store_credit" required="" <?php echo $store_credit_disabled_option ?>>
                     <label class="label-card" for="store_credit">
                        <span class="label-head"><?php _e('Store Credit Payout', 'solid-affiliate') ?></span>
                        <div class="label-body"><?php echo ($store_credit_label) ?></div>
                     </label>
                  </div>

               </div>

            </div>
         </div>


         <input type="hidden" id="" name="payout_currency" value="<?php echo $payout_currency ?>">
         <?php wp_nonce_field($nonce); ?>
         <?php submit_button(__("Preview  payout", 'solid-affiliate'), 'primary', $submit_action); ?>
         <small>
            <?php _e('Clicking this button will <strong>not</strong> initiate the payout. On the next page you will see a full preview of the payout data, including which affiliates will be paid and how much. If everything looks good, you can confirm and initiate the payout on the next page.', 'solid-affiliate') ?>
         </small>
      </form>
      <?php
      $res = ob_get_clean();
      if ($res) {
         return $res;
      } else {
         return __("Error rendering Pay Affiliates screen.", 'solid-affiliate');
      }
   }


   /**
    * @return string
    */
   public static function render_bulk_payout_filters_component()
   {
      ob_start();
      ?>
      <!-- Alpine Plugins -->
      <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
      <script>
         // Used to filter an array when we need only unique values
         window.onlyUnique = function(value, index, self) {
            return self.indexOf(value) === index;
         }

         window.humanize_field = function(field, remove_id_suffix = true) {
            var humanized = field.replace(/_/g, ' ').replace(/\w\S*/g, function(txt) {
               return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
            });
            // remove the word "id" from the end of the string if it exists
            if (remove_id_suffix && humanized.endsWith('Id')) {
               humanized = humanized.slice(0, -2);
            }
            return humanized;
         }
      </script>


      <div class="sld-pay-affiliates_card" style="margin-left:30px;">
         <div class="sld-pay-affiliates_card-head">
            <div class="step-bullet">2</div>
            <div>
               <h2><?php _e('Include/Exclude filtering rules', 'solid-affiliate') ?><span class="pill"><?php _e('Optional', 'solid-affiliate') ?></span></h2>
               <p><?php _e('Set custom rules to filter the unpaid referrals you want to include/exclude.', 'solid-affiliate') ?></p>
            </div>
         </div>
         <div class="sld-pay-affiliates_card-wrapper">
            <div x-data="{tempSelectedValue: '', rule: { operator: 'include', field: 'affiliate_id', value: [] } }" x-init="
                  $watch('rule.operator', value => { rule.value = []; tempSelectedValue = '' } ); 
                  $watch('rule.field', value => { rule.value = []; tempSelectedValue = '' });
                  $nextTick(() => { 
                     jQuery($refs.bulkPayoutFiltersAffiliateIdSelect).on('select2:select', (event) => {
                        tempSelectedValue = parseInt(event.target.value);
                     }); 
                  })
                  ">

               <div class="optional-filters">
                  <div>
                     <!-- Operator select -->
                     <select x-model="rule.operator">
                        <option value="include"><?php _e('Include only', 'solid-affiliate') ?></option>
                        <option value="exclude"><?php _e('Exclude', 'solid-affiliate') ?></option>
                     </select>
                  </div>

                  <div>
                     <!-- Field select -->
                     <select x-model="rule.field">
                        <option value="affiliate_id"><?php _e('Affiliate(s)', 'solid-affiliate') ?></option>
                        <option value="affiliate_group_id"><?php _e('Affiliate Group(s)', 'solid-affiliate') ?></option>
                        <option value="referral_id"><?php _e('Referral(s)', 'solid-affiliate') ?></option>
                        <option value="tag"><?php _e('Tag(s)', 'solid-affiliate') ?></option>
                     </select>
                  </div>

                  <!-- Value selects - Conditionally show the correct input depending on which field is selected -->
                  <div x-show="rule.field === 'affiliate_id'">
                     <!-- This is the select2 component. It gets instantiated by our 'global' js in admin.js which looks for the class name. -->
                     <select class="solid-affiliate-affiliate-search-select" x-ref="bulkPayoutFiltersAffiliateIdSelect"> </select>
                  </div>
                  <div x-show="rule.field !== 'affiliate_id'">
                     <input x-bind:placeholder="'Enter ' + window.humanize_field(rule.field, false)" type="text" x-model="tempSelectedValue" />
                  </div>
                  <!-- Display a select with Enum values from Tags::tags_enum_options() -->
                  <div x-show="rule.field === 'tag'">
                     <select x-model="tempSelectedValue">
                        <option value="" disabled selected><?php _e('Select a tag', 'solid-affiliate') ?></option>
                        <?php foreach (Tags::tags_enum_options() as $tag) : ?>
                           <option value="<?php echo $tag[0] ?>"><?php echo $tag[1] ?></option>
                        <?php endforeach; ?>
                     </select>
                  </div>

                  <!-- end -->

                  <div>

                     <?php if (FF::is_on('advanced_payout_filters')) { ?>
                        <button type="button" class="button filter" x-on:click="rule.value = rule.value.concat(rule.field === 'tag' ? tempSelectedValue : parseInt(tempSelectedValue)).filter(window.onlyUnique); tempSelectedValue=''; jQuery($refs.bulkPayoutFiltersAffiliateIdSelect).val(null).trigger('change'); " x-text="rule.operator + ' ' + window.humanize_field(rule.field)">
                        <?php } else { ?>
                           <div class="inline-upgrade-card">
                              <div class="content">
                                 <h2>This a pro feature!</h2>
                                 <p>Access to this feature is restricted exclusively to users with a "Pro Plan" subscription. Please consider upgrading your license to use this feature.</p>
                              </div>
                              <a class="sld-button primary large" href="https://solidaffiliate.com/pricing" target="_blank">Upgrade to Pro</a>
                           </div>
                        <?php } ?>

                        
                  </div>

               </div>
               <div class="values">
                  <template x-for="value in rule.value" :key="value">
                     <div class="value-row">
                        <div x-text="`${rule.field} #${value}`" :class="rule.operator"></div>
                        <button type="button" class="remove-value" x-on:click="rule.value = rule.value.filter(v => v !== value)">
                           <svg width="10" height="10" viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                              <path d="M1 9L9 1M1 1L9 9" stroke="#111127" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                           </svg>
                        </button>
                     </div>
                  </template>
               </div>
               <!-- hidden inputs to pass the values with the form submission -->
               <input type="hidden" x-model="rule.operator" id="logic_rules[0][operator]" name="logic_rules[0][operator]" />
               <input type="hidden" x-model="rule.field" id="logic_rules[0][field]" name="logic_rules[0][field]" />
               <template x-for="(value,index) in rule.value" :key="value">
                  <input type="hidden" x-model="value" x-bind:id="`logic_rules[0][value][${index}]`" x-bind:name="`logic_rules[0][value][${index}]`">
               </template>


            </div>
         </div>
      </div>


<?php
      return ob_get_clean();
   }
}
