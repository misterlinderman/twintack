<?php

namespace SolidAffiliate\Views\Admin\Reports;

use SolidAffiliate\Controllers\AdminReportsController;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;

class AdminReportsFiltersView
{
   /**
    * Undocumented function
    * 
    * @param PresetDateRangeParams $preset_date_range_params
    * @param string $start_date
    * @param string $end_date
    * @param int|false $affiliate_id
    * @param string $copy
    * @param bool $hide_date_range_filter
    *
    * @return string
    */
   public static function render($preset_date_range_params, $affiliate_id, $copy = "Use these filters to narrow down the data on this page.", $hide_date_range_filter = false)
   {
      $filter_form_schema = AdminReportsController::reports_filters_schema();

      $form_id = 'solid-affiliate-reports-filter-form';
      $nonce = AdminReportsController::NONCE_SUBMIT_ADMIN_REPORTS_FILTERS;


      $item = (object)[
         'date_range_preset' => $preset_date_range_params->preset_date_range,
         'start_date' => $preset_date_range_params->computed_start_date(),
         'end_date' => $preset_date_range_params->computed_end_date(),
         'affiliate_id' => $affiliate_id
      ];

      ob_start();
?>
      <?php if ($affiliate_id === false) { ?>
         <style>
            .row-affiliate_id {
               display: none !important;
            }
         </style>
      <?php } ?>
      <?php if ($hide_date_range_filter) { ?>
         <style>
            .row-date_range_preset {
               display: none !important;
            }
         </style>
      <?php } ?>
      <style>
         .sld-reports_filter {
            margin: 15px 0;
         }

         .sld-reports_filter form, .sld-reports_filter form > div {
            display:flex;
            flex-direction:row;
            gap:5px;
         }

         .row-date_range_preset .description {
            display: none !important;
         }

         .sld-reports_filter input,
         .sld-reports_filter select {
            height: 100%;
         }

         input#submit_admin_reports_filters {
            height: 40px;
            padding: 0 8px;
         }

         .sld-reports_filter p.submit {
            margin-top: 0 !important;
            padding-top: 0 !important;
         }

         .sld-reports_filter td:first-child {
            margin-right: 0;
         }

         .sld-reports_filter td {
            padding: 0 !important;
            margin-right: 10px;
            height: 40px;
            vertical-align: top !important
         }
      </style>

      <div class="sld-reports_filter" style="padding-bottom: 0px;">
         <form action="" method="post" id="<?php echo $form_id ?>">
                  <?php echo FormBuilder::build_form($filter_form_schema, 'new', $item, true); ?>
                  <?php wp_nonce_field($nonce); ?>
                  <?php submit_button(__("Filter", 'solid-affiliate'), 'primary', AdminReportsController::PARAM_KEY_SUBMIT_ADMIN_REPORTS_FILTERS); ?>
         </form>
      </div>

<?php
      $res = ob_get_clean();
      if ($res) {
         return $res;
      } else {
         return __("Error rendering Reports screen.", 'solid-affiliate');
      }
   }
}
