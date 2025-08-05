<?php

namespace SolidAffiliate\Views\Admin\Reports;

use SolidAffiliate\Lib\AdminReportsHelper;
use SolidAffiliate\Lib\Translation;
use SolidAffiliate\Views\Admin\Reports\AdminReportsFiltersView;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;
use SolidAffiliate\Views\Admin\AdminDashboard\MostValuableAffiliatesView;
use SolidAffiliate\Views\Shared\SimpleTableView;

class AffiliatesReportView
{
   /**
    * Undocumented function
    * 
    * @param PresetDateRangeParams $preset_date_range_params
    * @param int $affiliate_id
    *
    * @return string
    */
   public static function render($preset_date_range_params, $affiliate_id)
   {
      ob_start();

      $table_data = AdminReportsHelper::affiliates_data($preset_date_range_params->computed_start_date(), $preset_date_range_params->computed_end_date());
      $data_table_header_rows = Translation::translate_array(array_keys($table_data));
      $data_table_body_rows = [array_values($table_data)];
?>
      <style>
         .sld-date_range {
            background: #debbff;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 50px;
         }
      </style>
      <?php echo AdminReportsFiltersView::render($preset_date_range_params, false) ?>
      <!-- Stats -->
      <div class="mvp-affiliates">
         <h2><?php _e('Top Affiliates', 'solid-affiliate') ?> <span class="sld-date_range"><?php echo ($preset_date_range_params->formatted_preset_date_range()) ?></span></h2>
         <?php echo MostValuableAffiliatesView::render($preset_date_range_params); ?>
      </div>
      <div class="m-4"></div>
      <h2><?php _e('Affiliate Registrations', 'solid-affiliate') ?> <span class="sld-date_range"><?php echo ($preset_date_range_params->formatted_preset_date_range()) ?></span></h2>
      <?php echo SimpleTableView::render($data_table_header_rows, $data_table_body_rows, null, __('None of your affiliates earned a referral during this date range. Please update your filter.', 'solid-affiliate')) ?>
      <div class="m-4"></div>
<?php
      $res = ob_get_clean();
      if ($res) {
         return $res;
      } else {
         return __("Error rendering reports.", 'solid-affiliate');
      }
   }
}
