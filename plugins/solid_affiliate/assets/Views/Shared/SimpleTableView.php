<?php

namespace SolidAffiliate\Views\Shared;

use SolidAffiliate\Lib\GlobalTypes;
use SolidAffiliate\Lib\Utils;

/**
 * @psalm-import-type PaginationArgs from \SolidAffiliate\Lib\GlobalTypes
 */
class SimpleTableView
{


  /**
   * Renders a Simple WP Styled table.
   * 
   * @param array<mixed> $header_row
   * @param array<array<mixed>> $body_rows
   * @param PaginationArgs|null $pagination_args
   * @param string $empty_state_string
   * @param string $table_class
   * 
   * @return string
   */
  public static function render($header_row, $body_rows, $pagination_args = null, $empty_state_string = "No data to display.", $table_class = "wp-list-table widefat fixed striped payouts")
  {
    $empty_state_string = __($empty_state_string, 'solid-affiliate');
    ob_start();

?>
    <!-- Our admin page content should all be inside .wrap -->

    <table class="<?php echo ($table_class) ?>">
      <?php echo self::render_thead($header_row) ?>
      <?php echo self::render_tbody($body_rows) ?>
    </table>

    <?php
    if (empty($body_rows)) {
      echo "<p style='text-align: center; padding: 40px; font-size:13px; opacity:.8;'>" . __($empty_state_string, 'solid-affiliate') . "</p>";
    } else {
      if (!is_null($pagination_args)) {
        echo self::render_pagination($pagination_args);
      }
    }
    ?>
  <?php
    return ob_get_clean();
  }


  /**
   * Undocumented function
   * 
   * @param array<mixed> $header_row
   *
   * @return string
   */
  private static function render_thead($header_row)
  {
    ob_start();
  ?>
    <thead>
      <tr>
        <?php
        /** @psalm-suppress all */
        foreach ($header_row as $th) {
          echo "<th scope='col' class='manage-column' style='font-weight : 400;'><span>" . $th . "</span></th>";
        }
        ?>
      </tr>
    </thead>

  <?php
    $res = ob_get_clean();
    return $res;
  }

  /**
   * Undocumented function
   * 
   * @param array<array<mixed>> $body_rows
   * 
   * @return string
   */
  private static function render_tbody($body_rows)
  {
    ob_start();
  ?>
    <tbody id="the-list" data-wp-lists="list:payout">
      <?php
      foreach ($body_rows as $tr) {
        echo "<tr>";
        /** @psalm-suppress all */
        foreach ($tr as $td) {
          // echo "<td class='' data-colname='{$td}'>{$td}</td>";
          echo "<td class=''>{$td}</td>";
        }
        echo "</tr>";
      }
      ?>


    </tbody>
  <?php
    $res = ob_get_clean();
    return $res;
  }

  /**
   * Undocumented function
   * 
   * @param PaginationArgs $pagination_args
   *
   * @return string
   */
  private static function render_pagination($pagination_args)
  {
    // If we don't get an explicit current_page, try and get it from the query param
    if (!isset($pagination_args['current_page'])) {
      /**
       * @psalm-suppress RiskyCast
       */
      $pagination_args['current_page'] = isset($_GET[GlobalTypes::PAGINATION_PARAM]) ? (int)$_GET[GlobalTypes::PAGINATION_PARAM] : 1;
    }


    $per_page = GlobalTypes::AFFILIATE_PORTAL_PAGINATION_PER_PAGE;
    $total_count = max($pagination_args['total_count'], 1);
    $total_pages = ceil($total_count / $per_page);
    $current_page = $pagination_args['current_page'];
    $base_query_string = add_query_arg($_GET, '');
    if (isset($pagination_args['params_to_add_to_url'])) {
      $base_query_string = add_query_arg($pagination_args['params_to_add_to_url'], $base_query_string);
    }

    if ($current_page > 1) {
      $prev_page_query_string = add_query_arg(
        [
          GlobalTypes::PAGINATION_PARAM => $current_page - 1
        ],
        $base_query_string
      );
    } else {
      $prev_page_query_string = $base_query_string;
    }

    if ($current_page < $total_pages) {
      $next_page_query_string = add_query_arg(
        [
          GlobalTypes::PAGINATION_PARAM => $current_page + 1
        ],
        $base_query_string
      );
    } else {
      $next_page_query_string = $base_query_string;
    }

    $prev_page_url = $prev_page_query_string . '#' . GlobalTypes::AFFILIATE_PORTAL_HTML_ID;
    $next_page_url = $next_page_query_string . '#' . GlobalTypes::AFFILIATE_PORTAL_HTML_ID;

    ob_start();
  ?>
    <style>
      .sld-pagination {
        display: flex;
        justify-content: space-between;
        margin-top: 20px;
      }

      .sld-pagination a {
        padding: 8px 16px;
        text-decoration: none;
        transition: background-color .3s;
        border: 1px solid #ddd;
      }

      .sld-pagination a.active {
        background-color: #4CAF50;
        color: #fff;
        border: 1px solid #4CAF50;
      }

      .sld-pagination_pages span {
        margin-right: 10px;
      }
    </style>
    <div class="sld-pagination">
      <div class="sld-pagination_count">
        <?php echo $total_count . ' items   '; ?>
      </div>
      <div class="sld-pagination_pages">
        <span><?php echo $current_page . ' of ' . $total_pages; ?></span>
        <a href="<?php echo ($prev_page_url) ?>">
          <svg width="8" height="13" viewBox="0 0 8 13" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M7.41 10.74L2.83 6.15L7.41 1.56L6 0.150002L0 6.15L6 12.15L7.41 10.74Z" fill="#757575" />
          </svg>
        </a>
        <a href="<?php echo ($next_page_url) ?>">
          <svg width="8" height="12" viewBox="0 0 8 12" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 10.59L4.58 6L0 1.41L1.41 0L7.41 6L1.41 12L0 10.59Z" fill="#757575" />
          </svg>
        </a>
      </div>
    </div>


<?php
    $res = ob_get_clean();
    return $res;
  }
}
