<?php

namespace SolidAffiliate\Views\Admin\Reports;

use SolidAffiliate\Lib\AdminReportsHelper;
use SolidAffiliate\Lib\Translation;
use SolidAffiliate\Views\Admin\Reports\AdminReportsFiltersView;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;
use SolidAffiliate\Views\Shared\SimpleTableView;

class CouponsReportView
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

      $data = AdminReportsHelper::coupons_data($preset_date_range_params->computed_start_date(), $preset_date_range_params->computed_end_date(), $affiliate_id);
      if (empty($data)) {
         $data_table_header_rows = [];
      } else {

         $data_table_header_rows = Translation::translate_array(array_keys($data[0]));
      }
      // $data_table_body_rows = [array_values($data)];
      $data_table_body_rows = $data;
?>

      <?php echo AdminReportsFiltersView::render($preset_date_range_params, $affiliate_id, "Use these filters to narrow down the data on this page.", true) ?>
      <!-- Stats -->
      <?php echo SimpleTableView::render($data_table_header_rows, $data_table_body_rows) ?>
      <div class="m-4"></div>
<?php
      $res = ob_get_clean();
      if ($res) {
         return $res;
      } else {
         return __("Error rendering report.", 'solid-affiliate');
      }
   }
}
