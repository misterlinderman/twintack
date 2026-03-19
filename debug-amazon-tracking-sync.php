<?php
/**
 * Debug Amazon Tracking Sync Issue
 * 
 * Upload to WordPress root and access via browser while logged in as admin.
 * Inspects order meta keys to identify where Shippo tracking data is stored
 * and why WP-Lister can't find it.
 * 
 * DELETE THIS FILE AFTER DEBUGGING.
 */

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 1);

$dir = dirname(__FILE__);
for ($i = 0; $i < 5; $i++) {
    if (file_exists($dir . '/wp-load.php')) {
        require_once($dir . '/wp-load.php');
        break;
    }
    $dir = dirname($dir);
}

if (!defined('ABSPATH')) {
    die('WordPress not found');
}

if (!is_user_logged_in() || !current_user_can('manage_options')) {
    die('Access denied. Log in as admin first.');
}

$affected_amazon_ids = array(
    '114-9316152-9471452','113-9319113-4333812','114-4756301-7112211',
    '111-0346069-2697023','111-2463636-4036228','113-4862037-7747460',
    '111-9229339-6832233','111-4166268-2649820','111-2995150-5043469',
    '111-5713251-7229053','113-6541501-8141020','114-0117305-1876227',
    '114-0216526-1636258','114-8280810-9194621','112-1715826-5872260',
    '114-3947390-4471449','114-8963022-6045833','114-8304215-8799464',
    '112-2097529-8924229','113-2465237-7169061','113-1120854-0386608',
    '112-9441248-5334617','111-6890457-3249805','111-4913465-8704227',
    '114-3048379-2042643','111-6188752-1724260','114-3759731-7180240',
    '114-6669772-3548266','113-1268513-6273057',
);

$tracking_meta_keys = array(
    '_wpla_tracking_number',
    '_wpla_tracking_provider',
    '_wpla_tracking_service_name',
    '_wpla_tracking_ship_method',
    '_wpla_date_shipped',
    '_shippo_tracking_number',
    '_shippo_tracking_carrier',
    '_shippo_tracking_status',
    '_tracking_number',
    '_tracking_provider',
    '_custom_tracking_provider',
    '_wc_shipment_tracking_items',
    '_wc_connect_labels',
    'tracking_code',
    'ywot_tracking_code',
    'ywot_carrier_id',
    'wf_wc_shipment_source',
    'ups_shipment_ids',
    '_fulfillment_status',
    '_wpla_amazon_order_id',
);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Amazon Tracking Sync</title>
    <style>
        body { font-family: monospace; margin: 20px; font-size: 13px; }
        .order-block { border: 1px solid #ccc; margin: 15px 0; padding: 15px; border-radius: 5px; }
        .has-tracking { border-left: 4px solid #28a745; background: #f0fff0; }
        .no-tracking { border-left: 4px solid #dc3545; background: #fff5f5; }
        .meta-key { font-weight: bold; color: #333; }
        .meta-value { color: #0066cc; }
        .empty { color: #999; font-style: italic; }
        .found { color: #28a745; font-weight: bold; }
        .missing { color: #dc3545; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        td, th { border: 1px solid #ddd; padding: 5px 8px; text-align: left; }
        th { background: #f5f5f5; }
        h1 { color: #333; }
        h3 { margin-top: 0; }
        .note-content { background: #fff8dc; padding: 5px; margin: 3px 0; border-left: 3px solid #ffc107; }
        .summary { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 15px 0; }
    </style>
</head>
<body>
<h1>Amazon Tracking Sync Debug Report</h1>
<p>Generated: <?php echo current_time('Y-m-d H:i:s'); ?></p>

<?php
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'compare';

if ($action === 'compare') {
    echo '<div class="summary">';
    echo '<h2>Step 1: Compare Affected Orders vs Working Orders</h2>';
    echo '<p>This compares meta keys on orders flagged as missing tracking vs recent Amazon orders that have valid tracking.</p>';
    echo '</div>';

    // Find WC orders linked to the affected Amazon order IDs
    echo '<h2>Affected Orders (Missing Tracking on Amazon)</h2>';
    $checked_count = 0;
    $max_check = 5; // Check first 5 for brevity

    foreach (array_slice($affected_amazon_ids, 0, $max_check) as $amazon_order_id) {
        $orders = wc_get_orders(array(
            'limit' => 1,
            'meta_query' => array(
                array(
                    'key' => '_wpla_amazon_order_id',
                    'value' => $amazon_order_id,
                ),
            ),
        ));

        // Also try order notes
        if (empty($orders)) {
            global $wpdb;
            $wc_order_id = $wpdb->get_var($wpdb->prepare(
                "SELECT DISTINCT o.id FROM {$wpdb->prefix}wc_orders o
                 INNER JOIN {$wpdb->prefix}wc_orders_meta om ON o.id = om.order_id
                 WHERE om.meta_key = '_wpla_amazon_order_id' AND om.meta_value = %s
                 LIMIT 1",
                $amazon_order_id
            ));
            if ($wc_order_id) {
                $orders = array(wc_get_order($wc_order_id));
            }
        }

        if (empty($orders)) {
            echo "<div class='order-block no-tracking'><h3>Amazon: {$amazon_order_id}</h3>";
            echo "<p class='missing'>Could not find matching WooCommerce order</p></div>";
            continue;
        }

        $order = $orders[0];
        output_order_debug($order, $amazon_order_id, $tracking_meta_keys);
        $checked_count++;
    }

    // Now find recent Amazon orders that DO have tracking (for comparison)
    echo '<h2>Recent Amazon Orders WITH Tracking (Working Orders)</h2>';
    echo '<p>Looking for Amazon orders where _wpla_tracking_number is populated...</p>';

    $working_orders = wc_get_orders(array(
        'limit' => 5,
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => '_wpla_amazon_order_id',
                'compare' => 'EXISTS',
            ),
            array(
                'key' => '_wpla_tracking_number',
                'value' => '',
                'compare' => '!=',
            ),
        ),
    ));

    if (empty($working_orders)) {
        echo '<p class="missing">No Amazon orders found with _wpla_tracking_number populated.</p>';
        echo '<p>Trying to find Amazon orders with any tracking meta...</p>';

        $working_orders = wc_get_orders(array(
            'limit' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_wpla_amazon_order_id',
                    'compare' => 'EXISTS',
                ),
                array(
                    'key' => '_wpla_date_shipped',
                    'compare' => 'EXISTS',
                ),
            ),
        ));
    }

    if (!empty($working_orders)) {
        foreach ($working_orders as $order) {
            $amz_id = $order->get_meta('_wpla_amazon_order_id');
            output_order_debug($order, $amz_id ?: 'Unknown', $tracking_meta_keys);
        }
    } else {
        echo '<p class="missing">No working Amazon orders found for comparison.</p>';
    }

    // ALL meta dump for one affected order
    echo '<h2>Full Meta Dump: First Affected Order</h2>';
    echo '<p>Every meta key on the first affected order (to find where tracking data actually lives):</p>';

    $first_order = null;
    foreach (array_slice($affected_amazon_ids, 0, 1) as $amazon_order_id) {
        $orders = wc_get_orders(array(
            'limit' => 1,
            'meta_query' => array(
                array(
                    'key' => '_wpla_amazon_order_id',
                    'value' => $amazon_order_id,
                ),
            ),
        ));
        if (!empty($orders)) {
            $first_order = $orders[0];
        }
    }

    if ($first_order) {
        echo "<div class='order-block'>";
        echo "<h3>WC #{$first_order->get_id()} - Full Meta (filtered for tracking-related)</h3>";

        $all_meta = $first_order->get_meta_data();
        echo '<table><tr><th>Meta Key</th><th>Meta Value</th></tr>';

        foreach ($all_meta as $meta) {
            $key = $meta->key;
            $val = $meta->value;

            // Filter to show only potentially relevant keys
            $relevant = (
                stripos($key, 'track') !== false ||
                stripos($key, 'ship') !== false ||
                stripos($key, 'carrier') !== false ||
                stripos($key, 'wpla') !== false ||
                stripos($key, 'shippo') !== false ||
                stripos($key, 'fulfil') !== false ||
                stripos($key, 'label') !== false ||
                stripos($key, 'connect') !== false ||
                stripos($key, 'amazon') !== false
            );

            if ($relevant) {
                $display_val = is_array($val) || is_object($val)
                    ? '<pre>' . esc_html(print_r($val, true)) . '</pre>'
                    : esc_html((string) $val);
                echo "<tr><td class='meta-key'>" . esc_html($key) . "</td><td class='meta-value'>{$display_val}</td></tr>";
            }
        }
        echo '</table>';

        // Also dump order notes for tracking patterns
        echo '<h4>Order Notes (looking for tracking numbers)</h4>';
        $notes = wc_get_order_notes(array('order_id' => $first_order->get_id(), 'limit' => 20));
        foreach ($notes as $note) {
            $content = is_object($note) ? $note->content : (string) $note;
            $has_tracking = preg_match('/tracking|label|shippo|shipped|carrier/i', $content);
            $class = $has_tracking ? 'note-content' : '';
            echo "<div class='{$class}'>" . esc_html($content) . "</div>";
        }

        echo '</div>';
    }
}

function output_order_debug($order, $amazon_order_id, $tracking_meta_keys) {
    $has_wpla_tracking = !empty($order->get_meta('_wpla_tracking_number'));
    $class = $has_wpla_tracking ? 'has-tracking' : 'no-tracking';

    echo "<div class='order-block {$class}'>";
    echo "<h3>WC #{$order->get_id()} | Amazon: {$amazon_order_id} | Status: {$order->get_status()}</h3>";

    echo '<table>';
    echo '<tr><th>Meta Key</th><th>Value</th><th>Status</th></tr>';

    foreach ($tracking_meta_keys as $key) {
        $value = $order->get_meta($key, true);
        $display_val = '';
        $status = '';

        if (is_array($value) || is_object($value)) {
            $display_val = '<pre>' . esc_html(print_r($value, true)) . '</pre>';
            $status = '<span class="found">HAS DATA</span>';
        } elseif (!empty($value)) {
            $display_val = esc_html((string) $value);
            $status = '<span class="found">HAS DATA</span>';
        } else {
            $display_val = '<span class="empty">(empty)</span>';
            $status = '<span class="missing">empty</span>';
        }

        echo "<tr><td class='meta-key'>" . esc_html($key) . "</td><td class='meta-value'>{$display_val}</td><td>{$status}</td></tr>";
    }
    echo '</table>';

    // Show first few order notes
    $notes = wc_get_order_notes(array('order_id' => $order->get_id(), 'limit' => 10));
    $tracking_notes = array();
    foreach ($notes as $note) {
        $content = is_object($note) ? $note->content : (string) $note;
        if (preg_match('/tracking|label|shippo|shipped|carrier|Ground Advantage/i', $content)) {
            $tracking_notes[] = $content;
        }
    }

    if (!empty($tracking_notes)) {
        echo '<h4>Tracking-Related Order Notes:</h4>';
        foreach ($tracking_notes as $note) {
            echo "<div class='note-content'>" . esc_html($note) . "</div>";
        }
    }

    echo '</div>';
}
?>

<hr>
<p><strong>DELETE THIS FILE AFTER DEBUGGING.</strong></p>
</body>
</html>
