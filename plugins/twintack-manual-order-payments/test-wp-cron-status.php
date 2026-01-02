<?php
/**
 * WP-Cron Diagnostic Tool
 * 
 * Tests WP-Cron functionality and Shippo sync status
 * 
 * Access via: yoursite.com/wp-content/plugins/twintack-manual-order-payments/test-wp-cron-status.php
 */

// Load WordPress - try multiple paths
$wp_load_paths = array(
    __DIR__ . '/../../../../wp-load.php',  // Standard structure
    __DIR__ . '/../../../wp-load.php',      // Alternative structure
    dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php',
    $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php',
);

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('Error: Could not locate wp-load.php. Please access diagnostics through WordPress admin: WooCommerce → Shippo Sync → Full Diagnostics');
}

// Security check
if (!current_user_can('manage_options')) {
    die('Access denied. Admin only.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>WP-Cron Diagnostic Tool</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; background: #f0f0f1; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.13); }
        h1 { color: #1d2327; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
        h2 { color: #2c3338; margin-top: 30px; }
        .status-box { padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid; }
        .status-ok { background: #d4f6d4; border-color: #28a745; }
        .status-warning { background: #fff3cd; border-color: #ffc107; }
        .status-error { background: #f8d7da; border-color: #dc3545; }
        .info-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .info-table td { padding: 10px; border-bottom: 1px solid #ddd; }
        .info-table td:first-child { font-weight: bold; width: 250px; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        .button { display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px; cursor: pointer; border: none; font-size: 14px; }
        .button:hover { background: #005a87; }
        .button-secondary { background: #6c757d; }
        .button-secondary:hover { background: #5a6268; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .action-section { background: #e7f3ff; padding: 20px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 WP-Cron & Shippo Sync Diagnostic Tool</h1>
    
    <?php
    // Action handlers
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'force_shippo_sync':
                echo '<div class="status-box status-warning"><strong>🔄 Running manual Shippo sync...</strong></div>';
                if (class_exists('TwinTack_Shippo_Webhook_Handler')) {
                    $webhook_handler = TwinTack_Shippo_Webhook_Handler::get_instance();
                    $webhook_handler->run_automated_sync();
                    echo '<div class="status-box status-ok"><strong>✅ Sync completed!</strong> Check the results below.</div>';
                    echo '<meta http-equiv="refresh" content="2;url=' . strtok($_SERVER["REQUEST_URI"], '?') . '">';
                } else {
                    echo '<div class="status-box status-error"><strong>❌ Error:</strong> Shippo Webhook Handler class not found.</div>';
                }
                break;
                
            case 'enable_cron':
                echo '<div class="status-box status-warning"><strong>🚀 Enabling WP-Cron...</strong></div>';
                if (class_exists('TwinTack_Shippo_Webhook_Handler')) {
                    $webhook_handler = TwinTack_Shippo_Webhook_Handler::get_instance();
                    $webhook_handler->set_automation_enabled(true);
                    echo '<div class="status-box status-ok"><strong>✅ WP-Cron enabled!</strong> Next sync will run in ~4 hours (if WP-Cron is working).</div>';
                    echo '<meta http-equiv="refresh" content="2;url=' . strtok($_SERVER["REQUEST_URI"], '?') . '">';
                }
                break;
                
            case 'spawn_cron':
                echo '<div class="status-box status-warning"><strong>⚡ Spawning WP-Cron manually...</strong></div>';
                spawn_cron();
                echo '<div class="status-box status-ok"><strong>✅ WP-Cron spawn triggered!</strong> Check if scheduled tasks are running.</div>';
                echo '<meta http-equiv="refresh" content="2;url=' . strtok($_SERVER["REQUEST_URI"], '?') . '">';
                break;
        }
    }
    
    // Test 1: WP-Cron Configuration
    echo '<h2>⚙️ WordPress Cron Configuration</h2>';
    
    $wp_cron_disabled = (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON);
    $alt_cron = (defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON);
    
    if ($wp_cron_disabled) {
        echo '<div class="status-box status-error">';
        echo '<strong>❌ CRITICAL:</strong> WP-Cron is DISABLED in wp-config.php<br>';
        echo 'The constant <code>DISABLE_WP_CRON</code> is set to TRUE, which prevents all scheduled tasks from running.';
        echo '</div>';
    } elseif ($alt_cron) {
        echo '<div class="status-box status-warning">';
        echo '<strong>⚠️ WARNING:</strong> Alternate WP-Cron is enabled<br>';
        echo 'WP-Cron will only run when someone visits your site. This can cause delays.';
        echo '</div>';
    } else {
        echo '<div class="status-box status-ok">';
        echo '<strong>✅ GOOD:</strong> WP-Cron is enabled and configured normally.';
        echo '</div>';
    }
    
    // Test 2: Shippo Sync Status
    echo '<h2>📦 Shippo Sync Status</h2>';
    
    if (class_exists('TwinTack_Shippo_Webhook_Handler')) {
        $webhook_handler = TwinTack_Shippo_Webhook_Handler::get_instance();
        $automation_status = $webhook_handler->get_automation_status();
        
        echo '<table class="info-table">';
        echo '<tr><td>Automation Status</td><td>';
        if ($automation_status['enabled']) {
            echo '<span style="color: green; font-weight: bold;">✅ ENABLED</span>';
        } else {
            echo '<span style="color: red; font-weight: bold;">❌ DISABLED</span>';
        }
        echo '</td></tr>';
        
        echo '<tr><td>Next Scheduled Run</td><td>' . ($automation_status['next_run'] ? $automation_status['next_run'] : '<span style="color: red;">Not scheduled</span>') . '</td></tr>';
        echo '<tr><td>Sync Interval</td><td>' . $automation_status['interval'] . '</td></tr>';
        echo '<tr><td>Last Run</td><td>' . $automation_status['last_run'] . '</td></tr>';
        echo '</table>';
        
        // Check for stuck orders
        $processing_orders = wc_get_orders(array(
            'status' => 'processing',
            'limit' => 100,
            'return' => 'ids'
        ));
        
        $invoiced_with_shippo = wc_get_orders(array(
            'status' => 'invoiced',
            'limit' => 100,
            'meta_query' => array(
                array(
                    'key' => '_shippo_order_id',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        echo '<table class="info-table">';
        echo '<tr><td>Processing Orders</td><td>' . count($processing_orders) . '</td></tr>';
        echo '<tr><td>Invoiced Orders with Shippo ID</td><td>' . count($invoiced_with_shippo) . '</td></tr>';
        echo '</table>';
        
        if (count($invoiced_with_shippo) > 0) {
            echo '<div class="status-box status-warning">';
            echo '<strong>⚠️ ATTENTION:</strong> You have ' . count($invoiced_with_shippo) . ' invoiced orders that may need syncing to Shippo.';
            echo '</div>';
        }
        
    } else {
        echo '<div class="status-box status-error">';
        echo '<strong>❌ ERROR:</strong> Shippo Webhook Handler class not loaded.';
        echo '</div>';
    }
    
    // Test 3: All Scheduled Cron Events
    echo '<h2>📅 All Scheduled Cron Events</h2>';
    
    $cron_events = _get_cron_array();
    $shippo_found = false;
    
    if (!empty($cron_events)) {
        echo '<table class="info-table">';
        echo '<tr><td style="width: 200px;"><strong>Next Run</strong></td><td><strong>Hook Name</strong></td></tr>';
        
        foreach ($cron_events as $timestamp => $cron) {
            foreach ($cron as $hook => $dings) {
                if (strpos($hook, 'shippo') !== false || strpos($hook, 'twintack') !== false) {
                    $shippo_found = true;
                    echo '<tr style="background: #e7f3ff;">';
                } else {
                    echo '<tr>';
                }
                
                $next_run = date('Y-m-d H:i:s', $timestamp);
                $time_diff = human_time_diff($timestamp);
                $is_past = $timestamp < time();
                
                echo '<td>';
                echo $next_run;
                if ($is_past) {
                    echo '<br><span style="color: red; font-weight: bold;">⚠️ OVERDUE by ' . $time_diff . '</span>';
                } else {
                    echo '<br><span style="color: green;">in ' . $time_diff . '</span>';
                }
                echo '</td>';
                echo '<td><code>' . $hook . '</code></td>';
                echo '</tr>';
            }
        }
        echo '</table>';
        
        if (!$shippo_found) {
            echo '<div class="status-box status-error">';
            echo '<strong>❌ CRITICAL:</strong> No Shippo sync cron job found! The automated sync is not scheduled.';
            echo '</div>';
        }
    } else {
        echo '<div class="status-box status-error">';
        echo '<strong>❌ ERROR:</strong> No cron events scheduled at all. WP-Cron may be broken.';
        echo '</div>';
    }
    
    // Test 4: Check if orders exist in Shippo (via GoShippo plugin)
    echo '<h2>🚢 Shippo Integration Check</h2>';
    
    if (function_exists('shippo_create_order') || class_exists('Shippo')) {
        echo '<div class="status-box status-ok">';
        echo '<strong>✅ GOOD:</strong> Shippo integration plugin is active.';
        echo '</div>';
    } else {
        echo '<div class="status-box status-warning">';
        echo '<strong>⚠️ WARNING:</strong> No Shippo integration plugin detected. Make sure GoShippo or similar is installed.';
        echo '</div>';
    }
    
    // Immediate Actions
    echo '<div class="action-section">';
    echo '<h2>🔧 Immediate Actions</h2>';
    
    echo '<h3>Option 1: Force Manual Sync (RECOMMENDED)</h3>';
    echo '<p>This will immediately sync all eligible orders to Shippo without waiting for WP-Cron.</p>';
    echo '<a href="?action=force_shippo_sync" class="button">🔄 Force Shippo Sync Now</a>';
    
    echo '<h3 style="margin-top: 30px;">Option 2: Trigger WP-Cron Manually</h3>';
    echo '<p>This will attempt to spawn WP-Cron and run all overdue tasks.</p>';
    echo '<a href="?action=spawn_cron" class="button button-secondary">⚡ Spawn WP-Cron</a>';
    
    if (class_exists('TwinTack_Shippo_Webhook_Handler')) {
        $automation_status = TwinTack_Shippo_Webhook_Handler::get_instance()->get_automation_status();
        if (!$automation_status['enabled']) {
            echo '<h3 style="margin-top: 30px;">Option 3: Enable Automated Sync</h3>';
            echo '<p>This will re-enable the automated 4-hour sync (requires WP-Cron to be working).</p>';
            echo '<a href="?action=enable_cron" class="button">🚀 Enable Automated Sync</a>';
        }
    }
    
    echo '</div>';
    
    // Long-term Solutions
    echo '<h2>🛠️ Long-term Solutions</h2>';
    
    echo '<div class="status-box status-warning">';
    echo '<h3>Why WP-Cron Fails:</h3>';
    echo '<ul>';
    echo '<li><strong>Low Traffic:</strong> WP-Cron relies on site visits. No visits = no cron runs.</li>';
    echo '<li><strong>Server Configuration:</strong> Some hosting providers disable WP-Cron by default.</li>';
    echo '<li><strong>Performance Optimization:</strong> Caching plugins can prevent WP-Cron from triggering.</li>';
    echo '</ul>';
    
    echo '<h3>Recommended Fix: Real Cron Job</h3>';
    echo '<p>Set up a real server cron job instead of relying on WP-Cron. Add this to your server crontab:</p>';
    echo '<pre>*/15 * * * * curl ' . site_url('/wp-cron.php') . ' > /dev/null 2>&1</pre>';
    echo '<p><em>This runs wp-cron.php every 15 minutes regardless of site traffic.</em></p>';
    
    echo '<h3>Alternative: Use Admin Page Manual Sync</h3>';
    echo '<p>You can also manually trigger syncs from the WordPress admin:</p>';
    echo '<p><strong>WooCommerce → Shippo Sync</strong> - Click "Simulate Shippo Webhooks"</p>';
    echo '<a href="' . admin_url('admin.php?page=twintack-shippo-sync') . '" class="button" style="margin-top: 10px;">Go to Shippo Sync Page</a>';
    echo '</div>';
    
    // FTP Environment Note
    echo '<h2>📝 Important Note: FTP-Only Environment</h2>';
    echo '<div class="status-box status-warning">';
    echo '<p><strong>Since you\'re working in an FTP-only environment</strong>, setting up a real cron job requires:</p>';
    echo '<ol>';
    echo '<li>Contact your hosting provider (or server admin)</li>';
    echo '<li>Ask them to add a crontab entry to run <code>wp-cron.php</code> every 15 minutes</li>';
    echo '<li>Alternatively, ask them to check if WP-Cron is blocked by server configuration</li>';
    echo '</ol>';
    echo '<p><strong>Temporary workaround:</strong> Manually run the sync from the admin panel 2-3 times per day.</p>';
    echo '</div>';
    
    ?>
    
    <hr style="margin: 40px 0;">
    <p style="text-align: center; color: #666;">
        <a href="<?php echo admin_url('admin.php?page=twintack-shippo-sync'); ?>">← Back to WordPress Admin</a>
    </p>
</div>
</body>
</html>

