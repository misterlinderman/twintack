<?php

namespace SolidAffiliate\Views\Shared;

use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Lib\ListTables\VisitsListTable;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Models\BulkPayout;
use SolidAffiliate\Models\Visit;

class WPListTableView
{

  /**
   * Undocumented function
   *
   * @param string $page_key
   * @param \SolidAffiliate\Lib\ListTables\SolidWPListTable $list_table
   * @param string $plural_resource_name
   * @param bool $show_add_new_link
   * @param bool $show_pay_affiliates_button
   * @param bool $delete_unconverted_visits
   * 
   * @return string
   */
  public static function render($page_key, $list_table, $plural_resource_name, $show_add_new_link = true, $show_pay_affiliates_button = false, $delete_unconverted_visits = false)
  {
    ob_start();
    // $show_pay_affiliates_button = ($plural_resource_name === 'Payouts' || $plural_resource_name === 'Affiliates');
    $url = URLs::admin_path($page_key, false, ['action' => 'new']);
    $add_new_link = "<a href='{$url}' class='add-new-h2'>" . __('Add new', 'solid-affiliate') . "</a>";
    $pay_affiliates_btn = "<a class='add-new-h2 pay' href='" . URLs::admin_path(BulkPayout::ADMIN_PAGE_KEY) . "'>" . __('Pay affiliates', 'solid-affiliate') . "</a>";
    $delete_unconverted_visits = "<a href='#' data-micromodal-trigger='modal-unconverted-visits' class='add-new-h2'>" . __('Delete unconverted visits', 'solid-affiliate') . "</a>";
?>

    <div class="wrap">
      <h2>
        <?php
        _e("Manage", 'solid-affiliate');
        echo " ";
        echo ($plural_resource_name);
        if ($show_add_new_link) {
          echo ($add_new_link);
        }

        if ($show_pay_affiliates_button) {
          echo $pay_affiliates_btn;
        }

        if ($page_key == Visit::ADMIN_PAGE_KEY) {
          echo ("$delete_unconverted_visits");
        }
        ?>
      </h2>

      <?php
      if ($page_key === Visit::ADMIN_PAGE_KEY) {
        echo VisitsListTable::delete_unconverted_visits_form();
      }
      $list_table->prepare_items();
      echo ($list_table->custom_css());
      ?>
      <form method="post">
        <input type="hidden" name="page" value="<?php echo $page_key ?>">
        <?php
        /** @psalm-suppress MixedPropertyFetch
         *  @psalm-suppress InvalidPropertyFetch */
        if (is_null($list_table->configs()->search_button_text_override)) {
          $search_key = Formatters::humanize((string)$list_table->configs()->search_by_key);
          $search_button_text = __("Search by", 'solid-affiliate') . ' ' . $search_key;
        } else {
          $search_button_text = (string)$list_table->configs()->search_button_text_override;
        }
        $list_table->search_box($search_button_text, 'search_id');
        $list_table->views();
        $list_table->display();
        ?>
      </form>
    </div>
<?php
    $res = ob_get_clean();
    if ($res) {
      return $res;
    } else {
      return __("Error rendering.", 'solid-affiliate');
    }
  }
}
