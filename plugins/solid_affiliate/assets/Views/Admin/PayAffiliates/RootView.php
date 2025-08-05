<?php

namespace SolidAffiliate\Views\Admin\PayAffiliates;

use SolidAffiliate\Models\BulkPayout;
use SolidAffiliate\Views\Shared\WPListTableView;

class RootView
{
  /**
   * @return string
   */
  public static function render()
  {
    //Get the active tab from the $_GET param
    $default_tab = null;
    $bulk_payouts_tab = 'bulk_payouts';
    $tab = isset($_GET['tab']) ? (string)$_GET['tab'] : $default_tab;
    $action = isset($_GET['action']) ? (string)$_GET['action'] : '';

    ob_start();
?>
    <!-- Our admin page content should all be inside .wrap -->
    <div class="wrap sld-admin-container">
      <h1></h1>
      <div class="screen-hero">
        <div>
          <h2>
            <?php _e("Pay your Affiliates", 'solid-affiliate'); ?>
          </h2>
          <p>
            <?php _e('Pay affiliates the commissions they are owed. For more information, see our ', 'solid-affiliate') ?><a href="https://docs.solidaffiliate.com/pay-affiliates/" target="_blank"><?php _e("documentation article", 'solid-affiliate'); ?></a>.
          </p>
        </div>
        <!-- Here are our tabs -->
        <nav class="nav-tab-wrapper">
          <a href=<?php echo ("?page=" . BulkPayout::ADMIN_PAGE_KEY) ?> class="nav-tab <?php if ($tab === null) : ?>nav-tab-active<?php endif; ?>">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd" clip-rule="evenodd" d="M8.57199 16.2857C12.376 16.2857 15.4291 13.2632 15.4291 9.45917C15.4291 5.65516 12.376 2.57141 8.57199 2.57141C4.76798 2.57141 1.71484 5.65516 1.71484 9.45917C1.71484 13.2632 4.76798 16.2857 8.57199 16.2857Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
              <path d="M5.14258 9.42859H11.9997" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
              <path fill-rule="evenodd" clip-rule="evenodd" d="M8.57227 12.9054V6V12.9054Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <?php _e('New payout', 'solid-affiliate') ?>
          </a>
          <a href="?page=solid-affiliate-pay-affiliates&tab=<?php echo ($bulk_payouts_tab) ?>" class="nav-tab <?php if ($tab === $bulk_payouts_tab) : ?>nav-tab-active<?php endif; ?>">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M3.06713 5.56009C4.25439 3.51685 6.46687 2.14307 9.00021 2.14307C12.7873 2.14307 15.8574 5.21311 15.8574 9.00021C15.8574 12.7873 12.7873 15.8574 9.00021 15.8574C5.21311 15.8574 2.14307 12.7873 2.14307 9.00021" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
              <path d="M6.42871 5.57164H3.00014V2.14307" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>

            <?php _e('Payouts history', 'solid-affiliate') ?>
          </a>
        </nav>
      </div>
      <div class="tab-content">
        <?php switch ($tab):
          case $bulk_payouts_tab:
            $o = WPListTableView::render(BulkPayout::ADMIN_PAGE_KEY, BulkPayout::admin_list_table(), __('Bulk Payouts', 'solid-affiliate'), false);
            echo ($o);
            break;
          default:
            if ($action === 'bulk_payout_preview') {
              $o = NewBulkPayoutView::render();
            } else {
              $o = "";
              $o .= NewBulkPayoutPreviewView::render();
            }
            echo ($o);
            break;
        endswitch; ?>
      </div>
    </div>

<?php
    $res = ob_get_clean();
    if ($res) {
      return $res;
    } else {
      return __("Error rendering Pay Affiliates screen.", 'solid-affiliate');
    }
  }
}
