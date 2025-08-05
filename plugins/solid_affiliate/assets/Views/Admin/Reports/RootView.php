<?php

namespace SolidAffiliate\Views\Admin\Reports;

use SolidAffiliate\Controllers\AdminReportsController;
use SolidAffiliate\Views\Shared\AdminTabsView;
use SolidAffiliate\Views\Admin\Reports\ReferralsReportView;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;

class RootView
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
    //Get the active tab from the $_GET param
    $default_tab = null;
    $tab = isset($_GET['tab']) ? (string)$_GET['tab'] : $default_tab;

    ob_start();
?>
    <!-- Our admin page content should all be inside .wrap -->
    <div class="wrap">
      <h2></h2>
      <!-- Print the page title -->
      <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
      <!-- Here are our tabs -->
      <?php echo AdminTabsView::render(
        AdminReportsController::ADMIN_PAGE_KEY,
        [
          ['overview', __('Overview', 'solid-affiliate')],
          ['affiliates', __('Affiliates', 'solid-affiliate')],
          ['referrals', __('Referrals', 'solid-affiliate')],
          ['payouts', __('Payouts', 'solid-affiliate')],
          ['visits', __('Visits', 'solid-affiliate')],
          ['coupons', __('Coupons', 'solid-affiliate')]
        ],
        $tab
      )
      ?>

      <div class="tab-content">
        <?php switch ($tab):
          case 'overview':
            echo OverviewReportView::render($preset_date_range_params, $affiliate_id);
            break;
          case 'affiliates':
            echo AffiliatesReportView::render($preset_date_range_params, $affiliate_id);
            break;
          case 'referrals':
            echo ReferralsReportView::render($preset_date_range_params, $affiliate_id);
            break;
          case 'payouts':
            echo PayoutsReportView::render($preset_date_range_params, $affiliate_id);
            break;
          case 'visits':
            echo VisitsReportView::render($preset_date_range_params, $affiliate_id);
            break;
          case 'coupons':
            echo CouponsReportView::render($preset_date_range_params, $affiliate_id);
            break;
          default:
            echo OverviewReportView::render($preset_date_range_params, $affiliate_id);
            break;
        endswitch; ?>
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
