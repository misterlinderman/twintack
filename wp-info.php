<?php
/**
 * WordPress Configuration Info
 */

// Load WordPress
require_once 'wp-config.php';

if (!isset($_GET['info'])) {
    die('Add ?info=1 to the URL to run this script');
}

echo '<h1>WordPress Configuration Info</h1>';

echo '<h2>PHP Configuration</h2>';
echo '<p><strong>PHP Version:</strong> ' . phpversion() . '</p>';
echo '<p><strong>Memory Limit:</strong> ' . ini_get('memory_limit') . '</p>';
echo '<p><strong>Max Execution Time:</strong> ' . ini_get('max_execution_time') . ' seconds</p>';
echo '<p><strong>Upload Max Filesize:</strong> ' . ini_get('upload_max_filesize') . '</p>';
echo '<p><strong>Post Max Size:</strong> ' . ini_get('post_max_size') . '</p>';

echo '<h2>WordPress Configuration</h2>';
echo '<p><strong>WordPress Version:</strong> ' . get_bloginfo('version') . '</p>';
echo '<p><strong>Site URL:</strong> ' . site_url() . '</p>';
echo '<p><strong>WP Debug:</strong> ' . (WP_DEBUG ? 'Enabled' : 'Disabled') . '</p>';
echo '<p><strong>WP Debug Log:</strong> ' . (WP_DEBUG_LOG ? 'Enabled' : 'Disabled') . '</p>';

echo '<h2>Server Info</h2>';
echo '<p><strong>Server Software:</strong> ' . $_SERVER['SERVER_SOFTWARE'] . '</p>';
echo '<p><strong>Request Method:</strong> ' . $_SERVER['REQUEST_METHOD'] . '</p>';

echo '<h2>WordPress Constants</h2>';
$constants = array(
    'WP_DEBUG',
    'WP_DEBUG_LOG', 
    'WP_DEBUG_DISPLAY',
    'SCRIPT_DEBUG',
    'WP_MEMORY_LIMIT',
    'WP_MAX_MEMORY_LIMIT'
);

foreach ($constants as $constant) {
    if (defined($constant)) {
        $value = constant($constant);
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }
        echo '<p><strong>' . $constant . ':</strong> ' . $value . '</p>';
    } else {
        echo '<p><strong>' . $constant . ':</strong> Not defined</p>';
    }
}

echo '<h2>Error Log Test</h2>';
$log_path = WP_CONTENT_DIR . '/debug.log';
if (file_exists($log_path)) {
    echo '<p>✅ Debug log exists at: ' . $log_path . '</p>';
    $log_size = filesize($log_path);
    echo '<p>Log size: ' . round($log_size / 1024, 2) . ' KB</p>';
    
    // Show last few lines of the log
    $lines = file($log_path);
    $last_lines = array_slice($lines, -10);
    echo '<h3>Last 10 log entries:</h3>';
    echo '<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; font-size: 12px;">';
    foreach ($last_lines as $line) {
        echo htmlspecialchars($line);
    }
    echo '</pre>';
} else {
    echo '<p>❌ Debug log not found at: ' . $log_path . '</p>';
}

?> 