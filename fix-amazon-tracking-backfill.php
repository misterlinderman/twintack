<?php
/**
 * Backfill Amazon Tracking for Affected Orders
 * 
 * Parses order notes for Shippo tracking numbers and writes them to WP-Lister
 * meta keys, then re-submits the fulfillment feed to Amazon.
 * 
 * Upload to WordPress root and access via browser while logged in as admin.
 * DELETE THIS FILE AFTER USE.
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

/**
 * Regex matching Shippo label creation notes.
 * Example: "usps Ground Advantage label with tracking number 9200190396055707139969 has been created on Shippo"
 */
define('SHIPPO_NOTE_PATTERN', '/(\w+)\s+.*?label\s+with\s+tracking\s+number\s+([A-Za-z0-9]{10,30})\s+has\s+been\s+created\s+on\s+Shippo/i');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Backfill Amazon Tracking</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; font-size: 14px; }
        .success { color: #155724; background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 5px 0; border-radius: 4px; }
        .error { color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin: 5px 0; border-radius: 4px; }
        .warning { color: #856404; background: #fff3cd; border: 1px solid #ffeeba; padding: 10px; margin: 5px 0; border-radius: 4px; }
        .info { color: #004085; background: #cce5ff; border: 1px solid #b8daff; padding: 10px; margin: 5px 0; border-radius: 4px; }
        table { border-collapse: collapse; width: 100%; margin: 15px 0; }
        td, th { border: 1px solid #ddd; padding: 6px 10px; text-align: left; font-size: 13px; }
        th { background: #f5f5f5; }
        .btn { display: inline-block; padding: 12px 25px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #005a87; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
    </style>
</head>
<body>
<h1>Backfill Amazon Tracking for Affected Orders</h1>

<?php
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';

if ($action === 'scan') {
    scan_orders($affected_amazon_ids);
} elseif ($action === 'fix') {
    fix_orders($affected_amazon_ids);
} else {
    ?>
    <div class="info">
        <strong>What this does:</strong>
        <ol>
            <li><strong>Scan</strong> - Finds each affected order, parses order notes for Shippo tracking numbers, and shows what can be fixed.</li>
            <li><strong>Fix</strong> - Writes tracking data into WP-Lister's meta keys and re-submits the fulfillment feed to Amazon.</li>
        </ol>
        <p><strong>Prerequisites:</strong> The <code>twintack-amazon-tracking-bridge</code> plugin should be activated first so future orders are handled automatically.</p>
    </div>
    
    <p>
        <a href="?action=scan" class="btn">Step 1: Scan Orders</a>
        &nbsp;&nbsp;
        <a href="?action=fix" class="btn btn-danger" onclick="return confirm('This will re-submit fulfillment feeds to Amazon. Continue?')">Step 2: Fix &amp; Re-submit</a>
    </p>
    <?php
}

function find_wc_order_by_amazon_id($amazon_order_id) {
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
        return $orders[0];
    }

    // HPOS fallback
    global $wpdb;
    $wc_order_id = $wpdb->get_var($wpdb->prepare(
        "SELECT DISTINCT order_id FROM {$wpdb->prefix}wc_orders_meta
         WHERE meta_key = '_wpla_amazon_order_id' AND meta_value = %s LIMIT 1",
        $amazon_order_id
    ));

    return $wc_order_id ? wc_get_order($wc_order_id) : null;
}

/**
 * Extract tracking number and carrier from order notes.
 * This is the sole source of tracking data -- confirmed by debug script.
 */
function extract_tracking_from_order_notes($order) {
    $notes = wc_get_order_notes(array('order_id' => $order->get_id(), 'limit' => 30));
    foreach ($notes as $note) {
        $content = is_object($note) && isset($note->content) ? $note->content : (string) $note;

        // Primary: Shippo label creation note
        if (preg_match(SHIPPO_NOTE_PATTERN, $content, $m)) {
            return array('number' => $m[2], 'carrier' => strtoupper($m[1]));
        }

        // Fallback: generic "tracking number XXXXX" pattern
        if (preg_match('/tracking\s*(?:number|#|:)\s*[:#-]?\s*([A-Za-z0-9]{10,30})/i', $content, $m)) {
            $carrier = '';
            if (stripos($content, 'usps') !== false) $carrier = 'USPS';
            elseif (stripos($content, 'fedex') !== false) $carrier = 'FedEx';
            elseif (stripos($content, 'dhl') !== false) $carrier = 'DHL';
            elseif (stripos($content, 'ups') !== false && stripos($content, 'usps') === false) $carrier = 'UPS';
            return array('number' => $m[1], 'carrier' => $carrier);
        }
    }
    return array('number' => '', 'carrier' => '');
}

/**
 * Map carrier string to WP-Lister carrier code.
 */
function map_carrier_code($carrier) {
    $code_map = array(
        'USPS' => 'USPS', 'UPS' => 'UPS', 'FEDEX' => 'FedEx',
        'DHL' => 'DHL', 'DHL EXPRESS' => 'DHL',
    );
    $upper = strtoupper(trim($carrier));
    return isset($code_map[$upper]) ? $code_map[$upper] : 'Other';
}

function scan_orders($amazon_ids) {
    echo '<h2>Scan Results</h2>';
    echo '<table>';
    echo '<tr><th>Amazon Order ID</th><th>WC Order</th><th>Status</th><th>WPLA Tracking</th><th>Note Tracking</th><th>Note Carrier</th></tr>';

    $fixable = 0;
    $already_fixed = 0;
    $not_found = 0;
    $no_tracking = 0;

    foreach ($amazon_ids as $amz_id) {
        $order = find_wc_order_by_amazon_id($amz_id);

        if (!$order) {
            echo "<tr><td>" . esc_html($amz_id) . "</td><td colspan='5' style='color:red'>Not found in WooCommerce</td></tr>";
            $not_found++;
            continue;
        }

        $wc_id = $order->get_id();
        $status = $order->get_status();
        $wpla_tracking = $order->get_meta('_wpla_tracking_number', true);
        $note_data = extract_tracking_from_order_notes($order);

        $tracking_display = !empty($wpla_tracking)
            ? "<span style='color:green'>" . esc_html($wpla_tracking) . "</span>"
            : "<span style='color:red'>MISSING</span>";

        $note_display = !empty($note_data['number'])
            ? "<span style='color:green'>" . esc_html($note_data['number']) . "</span>"
            : "<span style='color:red'>NOT FOUND</span>";

        $carrier_display = !empty($note_data['carrier'])
            ? esc_html($note_data['carrier'])
            : '<em>N/A</em>';

        $can_fix = empty($wpla_tracking) && !empty($note_data['number']);
        if ($can_fix) $fixable++;
        if (!empty($wpla_tracking)) $already_fixed++;
        if (empty($note_data['number']) && empty($wpla_tracking)) $no_tracking++;

        echo "<tr>";
        echo "<td>" . esc_html($amz_id) . "</td>";
        echo "<td>#{$wc_id}</td>";
        echo "<td>{$status}</td>";
        echo "<td>{$tracking_display}</td>";
        echo "<td>{$note_display}</td>";
        echo "<td>{$carrier_display}</td>";
        echo "</tr>";
    }

    echo '</table>';

    echo "<div class='info'><strong>Summary:</strong> ";
    echo "{$fixable} fixable, {$already_fixed} already have tracking, {$no_tracking} have no tracking anywhere, {$not_found} not found in WooCommerce.";
    echo "</div>";

    if ($fixable > 0) {
        echo '<p><a href="?action=fix" class="btn btn-danger" onclick="return confirm(\'This will re-submit fulfillment feeds to Amazon for ' . $fixable . ' orders. Continue?\')">Fix ' . $fixable . ' Orders</a></p>';
    }
}

function fix_orders($amazon_ids) {
    echo '<h2>Fixing Orders</h2>';

    $fixed = 0;
    $skipped = 0;
    $errors = 0;

    foreach ($amazon_ids as $amz_id) {
        $order = find_wc_order_by_amazon_id($amz_id);

        if (!$order) {
            echo "<div class='warning'>Amazon " . esc_html($amz_id) . ": WC order not found - skipped</div>";
            $skipped++;
            continue;
        }

        $wc_id = $order->get_id();
        $existing_tracking = $order->get_meta('_wpla_tracking_number', true);

        if (!empty($existing_tracking)) {
            echo "<div class='info'>#{$wc_id} (" . esc_html($amz_id) . "): Already has tracking " . esc_html($existing_tracking) . " - re-submitting feed</div>";
        } else {
            $note_data = extract_tracking_from_order_notes($order);

            if (empty($note_data['number'])) {
                echo "<div class='error'>#{$wc_id} (" . esc_html($amz_id) . "): No tracking found in order notes - skipped</div>";
                $errors++;
                continue;
            }

            $tracking_number = $note_data['number'];
            $carrier = $note_data['carrier'] ?: 'USPS';
            $carrier_code = map_carrier_code($carrier);

            $order->update_meta_data('_wpla_tracking_number', $tracking_number);
            $order->update_meta_data('_wpla_tracking_provider', $carrier_code);
            if ($carrier_code === 'Other') {
                $order->update_meta_data('_wpla_tracking_service_name', $carrier);
            }
            $order->save();

            echo "<div class='success'>#{$wc_id} (" . esc_html($amz_id) . "): Set tracking to " . esc_html($tracking_number) . " (" . esc_html($carrier_code) . ") from order notes</div>";
        }

        // Re-submit fulfillment feed
        if (class_exists('WPLA_AmazonFeed')) {
            try {
                $feed = new WPLA_AmazonFeed();
                $feed->updateShipmentFeed($wc_id);
                echo "<div class='success'>#{$wc_id}: Re-submitted fulfillment feed to Amazon</div>";

                $order->add_order_note(sprintf(
                    'Amazon tracking backfill: Re-submitted fulfillment feed with tracking %s (%s).',
                    $order->get_meta('_wpla_tracking_number', true),
                    $order->get_meta('_wpla_tracking_provider', true)
                ));

                $fixed++;
            } catch (Exception $e) {
                echo "<div class='error'>#{$wc_id}: Feed submission failed - " . esc_html($e->getMessage()) . "</div>";
                $errors++;
            }
        } else {
            echo "<div class='warning'>#{$wc_id}: WPLA_AmazonFeed class not found - is WP-Lister active?</div>";
            $errors++;
        }
    }

    echo "<hr>";
    echo "<div class='info'><strong>Results:</strong> {$fixed} fixed, {$skipped} skipped, {$errors} errors</div>";
    echo '<p>WP-Lister will process the fulfillment feeds on its next background task run (every 5 min per your settings).</p>';
}
?>

<hr>
<p><strong>DELETE THIS FILE AFTER USE.</strong></p>
</body>
</html>
