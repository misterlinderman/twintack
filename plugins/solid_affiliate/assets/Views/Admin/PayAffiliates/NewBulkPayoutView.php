<?php

namespace SolidAffiliate\Views\Admin\PayAffiliates;

use SolidAffiliate\Controllers\PayAffiliatesController;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Lib\PayAffiliatesFunctions;
use SolidAffiliate\Lib\ControllerFunctions;
use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Lib\ListTables\SharedListTableFunctions;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;
use SolidAffiliate\Models\BulkPayout;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Views\Shared\SimpleTableView;
use SolidAffiliate\Views\Shared\SolidTooltipView;

/**
 * @psalm-import-type BulkPayoutLogicRuleType from \SolidAffiliate\Lib\VO\BulkPayoutOrchestrationParams
 */
class NewBulkPayoutView
{

   /**
    * Undocumented function
    * 
    * @return string
    */
   public static function render()
   {
      /////////////////////////////////
      // TODO WIP
      $bulkPayoutOrchestrationParams = PayAffiliatesController::bulk_payout_orchestration_params_from_post_request($_GET);
      /////////////////////////////////

      $nonce = PayAffiliatesController::NONCE_SUBMIT_BULK_PAYOUT;
      $submit_action = PayAffiliatesController::POST_PARAM_SUBMIT_BULK_PAYOUT;
      $schema = \SolidAffiliate\Controllers\PayAffiliatesController::pay_affiliates_tool_schema();
      $minimum_payout_settings_link = URLs::settings(Settings::TAB_GENERAL, false, Settings::KEY_BULK_PAYOUTS_MINIMUM_PAYOUT_AMOUNT);
      $minimum_payout_amount = esc_html(Formatters::money($bulkPayoutOrchestrationParams->filters['minimum_payout_amount']));

      // TODO this should probs not be happening in the view
      $eitherFields = ControllerFunctions::extract_and_validate_POST_params($_GET, ['date_range_preset', 'start_date', 'end_date', 'bulk_payout_method', 'payout_currency', 'filter_choice'], $schema);

      /** @psalm-suppress ArgumentTypeCoercion */
      $preset_date_range_params = new PresetDateRangeParams([
         'preset_date_range' => (string)$eitherFields->right['date_range_preset'],
         'start_date' => (string)$eitherFields->right['start_date'],
         'end_date' => (string)$eitherFields->right['end_date'],
      ]);
      $item = (object)$eitherFields->right;
      $bulk_payout_method = (string)$item->bulk_payout_method;
      $item->start_date = $preset_date_range_params->computed_start_date('Y-m-d H:i:s', false);
      $item->end_date = $preset_date_range_params->computed_end_date('Y-m-d H:i:s', false);

      ////////////////////////////////////////////////////////////////
      $referrals_owed = PayAffiliatesFunctions::find_referrals_owed_for_bulk_payout_filters($bulkPayoutOrchestrationParams->filters);

      $total_payout = Referral::sum_commission_amount($referrals_owed);
      $mapping = PayAffiliatesFunctions::create_affiliate_id_to_referrals_owed_mapping_from_referrals($referrals_owed);
      $total_referrals_owed = count($referrals_owed);
      $total_attributable_sales = Referral::sum_order_amount($referrals_owed);
      $total_affiliates_owed = count($mapping);

      // change minimum payout amount to 0 so we can get all affiliates, in order to calculate how many affiliates were excluded due to minimum payout amount
      $modified_filters = $bulkPayoutOrchestrationParams->filters;
      $modified_filters['minimum_payout_amount'] = 0;
      $referrals_owed_if_no_minimum_payout_amount = PayAffiliatesFunctions::find_referrals_owed_for_bulk_payout_filters($modified_filters);
      $mapping_if_no_minimum_payout_amount = PayAffiliatesFunctions::create_affiliate_id_to_referrals_owed_mapping_from_referrals($referrals_owed_if_no_minimum_payout_amount);

      $total_affiliates_owed_if_no_minimum_payout_amount = count($mapping_if_no_minimum_payout_amount);
      $count_affiliates_excluded_due_to_minimum_payout_amount = $total_affiliates_owed_if_no_minimum_payout_amount - $total_affiliates_owed;
      /////////////////////////////////

      $affiliate_table_rows = self::affiliate_table_rows_from_mapping($mapping);
      $date_range_preview_string = $preset_date_range_params->human_readable_range();

      ob_start();
?>
      <style>
         .sld-pay-affiliates_card {
            margin-top: 40px;
            background: #fff;
            border: 1px solid var(--sld-border);
            border-radius: var(--sld-radius-sm);
            padding: 0 20px 20px 20px;
         }

         .sld-pay-affiliates_card .badge {
            background: #debbff;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 50px;
         }

         #bulk-payout-submit-buttons {
            margin-top: 0px;
            padding-top: 20px;
            border-top: 1px solid var(--sld-border);
         }

         table.sld-padded-table {
            border: 1px solid var(--sld-border);
            background: #fff;
            margin-top: 20px;
            width: 100%;
         }

         table.sld-padded-table th {
            padding: 10px;
            border: 1px 0 1px 0 solid var(--sld-border);
            text-align: left;
            font-weight: 400;
            font-size: 13px;
            background: rgba(255,255,255,.35);
         }

         table.sld-padded-table td,
         table.sld-padded-table th {
            border-width: 0 0 1px 1px;
            border-color: var(--sld-border);
            border-style: solid;
         }

         table.sld-padded-table th {
            border-left: 0
         }

         table.sld-padded-table tr:last-child td {
            font-weight: 400;
            font-size: 16px;
         }

         .no-border_bottom {
            border-bottom: 0 !important;
         }

         #bulk-payout-submit-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-start
         }

         #bulk-payout-submit-buttons a {
            margin-right: 20px;
            height: 40px;
         }

         #bulk-payout-submit-buttons a.cancel {
            margin-left: auto;
            margin-right: 0;
            padding: 0 14px;
            line-height: 2.71428571;
            font-size: 14px;
            vertical-align: middle;
            min-height: 40px;
            margin-bottom: 4px;
            color: #d04242;
            border-color: #e2bbbb;
            background: #fffcfc;
         }

         .sld-table-button {
            font-weight: 400;
            color: #1329EE;
            font-size: 12px;
            text-decoration: underline !important;
         }

         .sld-pay-affiliates-confirmation-wrapper>div {
            display: flex;
            justify-content: space-between;
            text-align: left;
            align-items: center;
            font-weight: 400;
         }

         .sld-pay-affiliates-minimum {
            display: flex;
            background: #f1dbff;
            justify-content: flex-start;
            gap: 10px;
            padding: 5px 10px;
            border-radius: var(--sld-radius-sm);
         }

         .sld-pay-affiliates-minimum div {
            display: block;
            font-size: 13px;
         }

         .sld-pay-affiliates-minimum div.mono {
            font-weight: 400;
            font-size: 12px;
         }
      </style>
      <div class="sld-admin-container">
         <!-- ***************
      Bulk payout form
      *******************-->
         <div class="sld-pay-affiliates_card">
            <form action="" method="post" id="new-bulk-payout">
               <?php echo FormBuilder::build_hidden_fields_for_associative_array('logic_rules', $bulkPayoutOrchestrationParams->filters['logic_rules']); ?>
               <?php echo FormBuilder::build_form($schema, 'preview', $item) ?>
               <div class="sld-pay-affiliates-confirmation-wrapper">
                  <div class="">
                     <h2>
                        <?php _e('Confirm this bulk payout', 'solid-affiliate') ?>
                     </h2>



                     <div class="sld-pay-affiliates-minimum">
                        <svg width="21" height="21" viewBox="0 0 21 21" fill="none" xmlns="http://www.w3.org/2000/svg" class="sld-tooltip" data-html="true" data-sld-tooltip-content="<div style='max-width:300px; font-weight : 400; padding:10px; font-size:12px'>Only affiliates that are owed more than <?php echo ($minimum_payout_amount) ?> for the date range and filters you've selected are included in this payout. You can edit this setting in <a href='<?php echo ($minimum_payout_settings_link) ?>'>Solid Affiliate > Settings > General</a></div>" aria-expanded="false">
                           <path d="M10.5 18.5C14.9183 18.5 18.5 14.9183 18.5 10.5C18.5 6.08172 14.9183 2.5 10.5 2.5C6.08172 2.5 2.5 6.08172 2.5 10.5C2.5 14.9183 6.08172 18.5 10.5 18.5Z" stroke="#111127" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                           <path d="M10.5 11.5V10.5L11.9142 9.0858C12.2893 8.71073 12.5 8.20202 12.5 7.67159V7.50002C12.5 6.88715 12.1537 6.32688 11.6056 6.0528L11.3944 5.94723C10.8314 5.6657 10.1686 5.6657 9.60557 5.94723L9.5 6.00002C8.88713 6.30645 8.5 6.93284 8.5 7.61805V8.50002" stroke="#111127" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                           <path d="M10.5 15.5C11.0523 15.5 11.5 15.0523 11.5 14.5C11.5 13.9477 11.0523 13.5 10.5 13.5C9.94772 13.5 9.5 13.9477 9.5 14.5C9.5 15.0523 9.94772 15.5 10.5 15.5Z" fill="#111127" />

                        </svg>
                        <div class="mono"><?php _e('Minimum payout amount', 'solid-affiliate') ?>
                           <?php if ($count_affiliates_excluded_due_to_minimum_payout_amount > 0) { ?>
                              <div style="font-size: 9px;">(<?php echo ($count_affiliates_excluded_due_to_minimum_payout_amount);
                                                            _e(" affiliates were excluded", "solid-affiliate") ?>)</div>
                           <?php } ?>

                        </div>

                        <?php echo Formatters::money($bulkPayoutOrchestrationParams->filters['minimum_payout_amount']) ?>

                     </div>

                  </div>


                  <table class="sld-padded-table wp-list-table widefat fixed">
                     <tbody>
                        <tr>
                           <th><?php _e('Payout method', 'solid-affiliate') ?></th>
                           <td><?php echo ($bulk_payout_method) ?></td>
                        </tr>
                        <tr>
                           <th><?php _e('Include/exclude filtering rules', 'solid-affiliate') ?></th>
                           <td><?php echo (self::renderLogicRules($bulkPayoutOrchestrationParams->filters['logic_rules'])) ?></td>
                        </tr>
                        <tr>
                           <th><?php _e('Date range', 'solid-affiliate') ?></th>
                           <td><?php echo $date_range_preview_string ?></td>
                        </tr>
                        <tr>
                           <th><?php _e('Payout currency', 'solid-affiliate') ?> <?php echo SolidTooltipView::render_pretty(
                                                                                    __("Payout Currency", 'solid-affiliate'),
                                                                                    __("Currency of affiliate payouts.", 'solid-affiliate'),
                                                                                    __("This will be the currency code sent to the payments provider or exported in your CSV file if you are running a manual payout.", 'solid-affiliate'),
                                                                                    __("Edit in <strong>WooCommerce > Settings > Currency</strong>.", 'solid-affiliate')
                                                                                 ) ?> </th>
                           <td><?php echo ((string)$item->payout_currency) ?></td>
                        </tr>
                        <tr>
                           <th><?php _e('Affiliates', 'solid-affiliate') ?></th>
                           <td><?php echo ($total_affiliates_owed) ?></td>
                        </tr>
                        <tr>
                           <th><?php _e('Referrals', 'solid-affiliate') ?></th>
                           <td><?php echo ($total_referrals_owed) ?></td>
                        </tr>
                        <tr>
                           <th><?php _e('Total sales by affiliates', 'solid-affiliate') ?></th>
                           <td><?php echo Formatters::money($total_attributable_sales) ?></td>
                        </tr>
                        <tr>
                           <th class="no-border_bottom"><?php _e('Total amount to pay affiliates', 'solid-affiliate') ?></th>
                           <td class="no-border_bottom"><?php echo Formatters::money($total_payout) ?></td>
                        </tr>
                     </tbody>
                  </table>
                  <br>
                  <?php wp_nonce_field($nonce); ?>
                  <?php
                  if ($bulk_payout_method == PayAffiliatesController::BULK_PAYOUT_METHOD_CSV) {
                     echo (self::render_csv_after_submit());
                  }
                  if ($bulk_payout_method == PayAffiliatesController::BULK_PAYOUT_METHOD_PAYPAL) {
                     echo (self::render_paypal_after_submit());
                  }
                  if ($bulk_payout_method == PayAffiliatesController::BULK_PAYOUT_METHOD_STORE_CREDIT) {
                     echo (self::render_store_credit_after_submit());
                  }
                  ?>
                  <?php
                  if ($bulk_payout_method == PayAffiliatesController::BULK_PAYOUT_METHOD_CSV) {
                     echo (self::render_csv_method_buttons($submit_action));
                  }
                  if ($bulk_payout_method == PayAffiliatesController::BULK_PAYOUT_METHOD_PAYPAL) {
                     echo (self::render_paypal_method_buttons($submit_action));
                  }
                  if ($bulk_payout_method == PayAffiliatesController::BULK_PAYOUT_METHOD_STORE_CREDIT) {
                     echo (self::render_store_credit_method_buttons($submit_action));
                  }
                  ?>
               </div>
            </form>
         </div>
         <!-- ***************
      Preview of the bulk payout 
      *******************-->
         <div class="m-4">
         </div>
         <h2><?php _e('Preview of the Affiliates in this bulk payout', 'solid-affiliate') ?></h2>
         <div>
            <style>
               highlight {
                  background: lemonchiffon;
                  padding: 4px 10px;
               }
            </style>
            <div class="sld-pay-affiliates-confirmation-wrapper">
               <?php echo SimpleTableView::render([
                  __('Affiliate', 'solid-affiliate'),
                  __('Payment Email', 'solid-affiliate'),
                  __('Included referrals', 'solid-affiliate'),
                  __('Sales by Affiliate', 'solid-affiliate'),
                  __('Commission', 'soid-affiliate')
               ], $affiliate_table_rows) ?>
            </div>
         </div>
         <div class="m-4">
         </div>
         <?php
         $res = ob_get_clean();
         if ($res) {
            return $res;
         } else {
            return __("Error rendering Pay Affiliates screen.", 'solid-affiliate');
         }
      }

      /**
       * @param string $submit_action
       * 
       * @return string
       */
      public static function render_csv_method_buttons($submit_action)
      {
         $onclick_1_text = __('Are you sure? This will mark all Referrals in your range as paid, which cannot be undone. This is a manual Payout, no money will be sent to anyone automatically.', 'solid-affiliate');
         $onclick_2_text = __('This will export a CSV but NOT mark any Referrals as paid.', 'solid-affiliate');
         ob_start();
         ?>
         <div id="bulk-payout-submit-buttons">
            <input type="hidden" name="onclick_1_text" value="<?php echo $onclick_1_text ?>">
            <input type="hidden" name="onclick_2_text" value="<?php echo $onclick_2_text ?>">
            <?php submit_button(PayAffiliatesController::POST_PARAM_SUBMIT_BULK_PAYOUT_VALUE, 'primary', $submit_action, false, ['data-sld-pay-affiliates' => 'onclick_1']); ?>
            <?php submit_button(__("Only Export CSV without marking Referrals as paid", 'solid-affiliate'), 'secondary', $submit_action, false, ['data-sld-pay-affiliates' => 'onclick_2']); ?>
            <!-- TODO path is hardcoded in cancel button -->
            <a class='button cancel' href=<?php echo (URLs::admin_path(BulkPayout::ADMIN_PAGE_KEY)) ?>><?php _e('Cancel', 'solid-affiliate') ?></a>
         </div>
      <?php
         $res = ob_get_clean();
         return $res;
      }

      /**
       * @return string
       */
      public static function render_csv_after_submit()
      {
         $past_bulk_payouts_url = URLs::index(BulkPayout::class);
         ob_start();
      ?>
         <div style="display: none;" class="notice notice-success" id="bulk-payout-after-submit">
            <p>
               <?php echo sprintf(__('Referrals marked as paid and CSV exporting now. You can view this Bulk Payout and re-download the CSV later in <a href="%1$s">Past Bulk Payouts.</a>', 'solid-affiliate'), $past_bulk_payouts_url) ?>
            </p>
         </div>
      <?php
         $res = ob_get_clean();
         return $res;
      }

      /**
       * @param string $submit_action
       * 
       * @return string
       */
      public static function render_paypal_method_buttons($submit_action)
      {
         $paypal_integration_settings_link = URLs::settings(Settings::TAB_INTEGRATIONS);
         $is_paypal_sandbox_mode = !boolval(Settings::get(Settings::KEY_INTEGRATIONS_PAYPAL_USE_LIVE));
         $onclick_1_text = __('Are you sure? This will mark all Referrals in your range as paid, which cannot be undone. The PayPal Mass payment will be initiated, which cannot be undone.', 'solid-affiliate');
         ob_start();
      ?>
         <div id="bulk-payout-submit-buttons">
            <?php $onclick_1 = "if (!confirm({$onclick_1_text})) { return false; } else { jQuery('#bulk-payout-submit-buttons').hide('slow'); jQuery('#bulk-payout-after-submit').show('slow'); return true;}" ?>
            <?php if ($is_paypal_sandbox_mode) { ?>
               <p><?php echo sprintf(__('Note: You are currently in <strong>Sandbox</strong> mode. This Bulk Payout will be sent to your PayPal Sandbox account. You can configure this in your <a href="%1$s">PayPal Integration Settings</a>'), $paypal_integration_settings_link) ?></p>
            <?php }; ?>
            <?php submit_button(__('Initiate PayPal Bulk Payout', 'solid-affiliate'), 'primary', $submit_action, false, ['onclick' => $onclick_1, 'style' => 'background: rgba(var(--sld-accent)); color:#fff; border-color: var(--sld-border);']); ?>
            <p>
               <strong><?php _e('Important', 'solid-affiliate') ?></strong> <?php _e('You must ensure that you have sufficient funds available in your PayPal account to cover the amount of any payments you initiate.', 'solid-affiliate') ?>
            </p>
            <a class='button button-primary cancel' href=<?php echo (URLs::admin_path(BulkPayout::ADMIN_PAGE_KEY)) ?>><?php _e('cancel', 'solid-affiliate') ?></a>
         </div>
      <?php
         $res = ob_get_clean();
         return $res;
      }

      /**
       * @return string
       */
      public static function render_paypal_after_submit()
      {
         ob_start();
      ?>
         <div style="display: none;" class="notice notice-success" id="bulk-payout-after-submit">
            <p>
               <span style="float: none; margin: 0;" class="spinner is-active"></span>
               <?php _e('Sending Bulk Payout data to PayPal. Page will redirect once completed.', 'solid-affiliate') ?>
            </p>
         </div>
      <?php
         $res = ob_get_clean();
         return $res;
      }

      /**
       * @return string
       */
      public static function render_store_credit_after_submit()
      {
         $past_bulk_payouts_url = URLs::index(BulkPayout::class);
         ob_start();
      ?>
         <div style="display: none;" class="notice notice-success" id="bulk-payout-after-submit">
            <p>
               <?php echo sprintf(__('Referrals marked as paid and store credit has been distributed to the appropriate affiliates. You can view this Bulk Payout logged in <a href="%1$s">Past Bulk Payouts.</a>', 'solid-affiliate'), $past_bulk_payouts_url) ?>
            </p>
         </div>
      <?php
         $res = ob_get_clean();
         return $res;
      }

      /**
       * @param string $submit_action
       * 
       * @return string
       */
      public static function render_store_credit_method_buttons($submit_action)
      {
         $onclick_1_text = __('Are you sure? This will mark all Referrals in your range as paid, and distribute store credit appropriately. This cannot be undone.', 'solid-affiliate');
         ob_start();
      ?>
         <div id="bulk-payout-submit-buttons">
            <input type="hidden" name="onclick_1_text" value="<?php echo $onclick_1_text ?>">
            <?php submit_button(PayAffiliatesController::POST_PARAM_SUBMIT_BULK_PAYOUT_VALUE_STORE_CREDIT, 'primary', $submit_action, false, ['data-sld-pay-affiliates' => 'onclick_1']); ?>
            <a class='button cancel' href=<?php echo (URLs::admin_path(BulkPayout::ADMIN_PAGE_KEY)) ?>><?php _e('Cancel', 'solid-affiliate') ?></a>
         </div>
   <?php
         $res = ob_get_clean();
         return $res;
      }

      /**
       * Generates the rows for the table which previews which affiliates are to be paid.
       *
       * @param array<int, array{commission_amount: float, order_amount: float, payment_email: string, referral_ids: array<array-key, int>}> $mapping
       * 
       * @return list<array{string, non-empty-string, string, string, non-empty-string}>
       */
      private static function affiliate_table_rows_from_mapping($mapping)
      {
         $affiliate_table_rows = array_map(
            function ($id, $aff) {
               $commission_amount = Formatters::money($aff['commission_amount']);
               $commission_amount_str = "<strong>{$commission_amount}</strong>";
               $order_amount = Formatters::money($aff['order_amount']);
               $unpaid_referrals_count = count($aff['referral_ids']);
               // make the unpaid referrals a link to the referrals page with the referral ids set as a query param
               $unpaid_referrals = "<a class='sld-table-button' href='" . URLs::admin_path(Referral::ADMIN_PAGE_KEY, false, ['id' => $aff['referral_ids']]) . "'>{$unpaid_referrals_count} </a>";

               $affiliate_column = SharedListTableFunctions::affiliate_column($id, false);
               $payment_email = $aff['payment_email'];
               // if the payment_email is empty, then we need to show a warning. a red warning icon
               if (empty($payment_email)) {
                  $payment_email = '<span class="dashicons dashicons-warning" style="color: red;"></span> ' . '<span style="color: #b0b0b0">' . __('No payment email', 'solid-affiliate') . '</span>';
               }
               return [$affiliate_column, "<small>{$payment_email}</small>", $unpaid_referrals, $order_amount, $commission_amount_str];
            },
            array_keys($mapping),
            $mapping
         );

         return $affiliate_table_rows;
      }

      /**
       * @param BulkPayoutLogicRuleType[] $logic_rules
       * @return string
       */
      private static function renderLogicRules($logic_rules)
      {
         ////////////////////////////////
         // if there are no logic rules, return a helpful message
         if (empty($logic_rules)) {
            return '<p>' . __('No additional filters were applied to this bulk payout', 'solid-affiliate') . '</p>';
         }
         ////////////////////////////////

         // Start building the HTML output
         $html = '';

         // loop through the logic rules
         foreach ($logic_rules as $data) {
            $html .= "<div class='logic-rule'>";

            // Add the operator to the output
            $html .= "<div class='operator'><span>Function</span>" . $data['operator'] . "</div>";

            // Add the field to the output
            $html .= "<div class='field'><span>Filter by</span>" . $data['field'] . "</div>";

            // Add the values to the output
            $html .= "<div class='values'><span>Selected values</span><ul>";
            foreach ($data['value'] as $value) {
               $html .= "<li>" . $value . "</li>";
            }
            $html .= "</ul>";
            $html .= "</div>";
            // Finish the HTML output
            $html .= "</div>";
         }

         // Return the HTML
         return $html;
      }
   }
