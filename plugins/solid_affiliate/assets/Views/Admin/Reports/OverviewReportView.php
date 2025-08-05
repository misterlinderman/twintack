<?php

namespace SolidAffiliate\Views\Admin\Reports;

use SolidAffiliate\Lib\AdminReportsHelper;
use SolidAffiliate\Lib\ChartData;
use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Views\Admin\Reports\AdminReportsFiltersView;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;
use SolidAffiliate\Views\Shared\Charts\SolidChartView;
use SolidAffiliate\Views\Shared\Charts\SolidChartViewFive;
use SolidAffiliate\Views\Shared\Charts\SolidChartViewFour;
use SolidAffiliate\Views\Shared\Charts\SolidChartViewSix;
use SolidAffiliate\Views\Shared\Charts\SolidChartViewThree;
use SolidAffiliate\Views\Shared\Charts\SolidChartViewTwo;
use SolidAffiliate\Views\Shared\SimpleTableView;

class OverviewReportView
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

      $data = AdminReportsHelper::overview_data($preset_date_range_params->computed_start_date(), $preset_date_range_params->computed_end_date(), $affiliate_id);

      $custom_range_label = Formatters::humanize($preset_date_range_params->preset_date_range);
      $data_table_header_rows = ['', __('All Time', 'solid-affiliate'), __('This Year', 'solid-affiliate'), __('Last Year', 'solid-affiliate'), "{$custom_range_label} <span class='badge'>" . __('Custom', 'solid-affiliate') . "</span>"];


      $data_table_body_rows = AdminReportsHelper::transform_overview_data_for_table($data);
?>
      <style>
         .solid-affiliate-reports_overview td:nth-child(1) {
            font-weight : 400;
         }

         .solid-affiliate-reports_overview span.note {
            margin-top: 5px;
            display: block;
            font-size: 12px;
         }

         .solid-affiliate-reports_overview th:last-child,
         .solid-affiliate-reports_overview td:last-child {
            border-left: 1px solid #d4d5d7;
            font-weight : 400;
         }

         .solid-affiliate-reports_overview span.badge {
            background: #debbff;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 50px;
         }

         .sld-affiliate_flex {
            display: flex;
            align-items: stretch;
            justify-items: center;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: left;
            width: calc(100% + 12px);
         }

         .sld-affiliate_flex-item {
            width: calc(50.1% - 20px);
            background: #fff;
            border: 1px solid var(--sld-border);
            border-radius:var(--sld-radius-sm);
         }

         @media screen and (min-width: 1560px) {
            .sld-affiliate_flex-item {
               width: calc(33.34% - 20px);
            }
         }

         #sld-affiliate_reports-charts {
            margin: 40px 0;
         }

         #sld-affiliate_reports-charts h3 {
            font-size: 14px;
         }
      </style>
      <!-- Stats -->
      <?php echo AdminReportsFiltersView::render($preset_date_range_params, false, __('This will filter the last column in the data table, and any charts on this page.', 'solid-affiliate')) ?>
      <div class="solid-affiliate-reports_overview">
         <?php echo SimpleTableView::render($data_table_header_rows, $data_table_body_rows) ?>
      </div>
      <div id="sld-affiliate_reports-charts">
         <div class="sld-affiliate_flex">
            <div class="sld-affiliate_flex-item">
               <?php echo (SolidChartView::render($preset_date_range_params)) ?>
            </div>
            <div class="sld-affiliate_flex-item">
               <?php echo (SolidChartViewTwo::render($preset_date_range_params)) ?>
            </div>
            <div class="sld-affiliate_flex-item">
               <?php echo (SolidChartViewThree::render($preset_date_range_params)) ?>
            </div>
            <div class="sld-affiliate_flex-item">
               <?php echo (SolidChartViewFour::render($preset_date_range_params)) ?>
            </div>
            <div class="sld-affiliate_flex-item">
               <?php echo (SolidChartViewFive::render($preset_date_range_params)) ?>
            </div>
            <div class="sld-affiliate_flex-item">
               <?php echo (SolidChartViewSix::render($preset_date_range_params)) ?>
            </div>
         </div>
      </div>
<?php
      $res = ob_get_clean();
      if ($res) {
         return $res;
      } else {
         return __("Error rendering reports.", 'solid-affiliate');
      }
   }
}
