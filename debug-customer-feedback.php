<?php
/**
 * Debug Customer Feedback Webhook System
 */

// Load WordPress
require_once('wp-load.php');

if (!is_admin()) {
    // Simple auth check - you can modify this
    if (!current_user_can('manage_options')) {
        die('Access denied - admin only');
    }
}

echo "<h1>Customer Feedback Debug</h1>";

// Test 1: Check if the grip account class exists
echo "<h2>1. Class Check</h2>";
if (class_exists('TwinTack_Grip_Account')) {
    echo "✅ TwinTack_Grip_Account class exists<br>";
    $instance = TwinTack_Grip_Account::get_instance();
    if ($instance) {
        echo "✅ Instance created successfully<br>";
    } else {
        echo "❌ Failed to create instance<br>";
    }
} else {
    echo "❌ TwinTack_Grip_Account class NOT found<br>";
}

// Test 2: Check for grip designs with pending_review status
echo "<h2>2. Grip Designs Check</h2>";
$args = array(
    'post_type' => 'grip_design',
    'post_status' => 'publish',
    'meta_query' => array(
        array(
            'key' => '_grip_artwork_status',
            'value' => 'pending_review',
            'compare' => '='
        )
    ),
    'posts_per_page' => 5
);

$grip_designs = new WP_Query($args);

if ($grip_designs->have_posts()) {
    echo "✅ Found " . $grip_designs->found_posts . " grip designs with 'pending_review' status:<br>";
    while ($grip_designs->have_posts()) {
        $grip_designs->the_post();
        $grip_id = get_the_ID();
        $customer_email = get_post_meta($grip_id, '_grip_customer_email', true);
        $monday_item_id = get_post_meta($grip_id, '_grip_monday_item_id', true);
        
        echo "- ID: " . $grip_id . " | Title: " . get_the_title() . " | Customer: " . $customer_email . " | Monday ID: " . $monday_item_id . "<br>";
    }
    wp_reset_postdata();
} else {
    echo "❌ No grip designs found with 'pending_review' status<br>";
    echo "⚠️ Customer feedback only works on designs with 'pending_review' status<br>";
}

// Test 3: Simulate webhook call
echo "<h2>3. Manual Webhook Test</h2>";
if ($grip_designs->found_posts > 0) {
    // Get the first grip design for testing
    $test_args = array(
        'post_type' => 'grip_design',
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => '_grip_artwork_status',
                'value' => 'pending_review',
                'compare' => '='
            )
        ),
        'posts_per_page' => 1
    );
    
    $test_query = new WP_Query($test_args);
    if ($test_query->have_posts()) {
        $test_query->the_post();
        $test_grip_id = get_the_ID();
        wp_reset_postdata();
        
        echo "Testing with Grip ID: " . $test_grip_id . "<br>";
        
        // Manually call the webhook function
        if (class_exists('TwinTack_Grip_Account')) {
            $instance = TwinTack_Grip_Account::get_instance();
            if (method_exists($instance, 'trigger_customer_feedback_webhook')) {
                echo "✅ trigger_customer_feedback_webhook method exists<br>";
                
                echo "📡 Attempting to send webhook...<br>";
                
                // Get the method through reflection since it's private
                $reflection = new ReflectionClass($instance);
                $method = $reflection->getMethod('trigger_customer_feedback_webhook');
                $method->setAccessible(true);
                
                // Call the webhook method
                try {
                    $result = $method->invoke($instance, $test_grip_id, 'request_changes', 'Debug test webhook', 'customer_requested_changes');
                    echo "✅ Webhook method called successfully<br>";
                } catch (Exception $e) {
                    echo "❌ Webhook method error: " . $e->getMessage() . "<br>";
                }
                
            } else {
                echo "❌ trigger_customer_feedback_webhook method NOT found<br>";
            }
        }
    }
} else {
    echo "❌ Cannot test webhook - no pending_review grip designs found<br>";
}

// Test 4: Check WordPress error log for recent webhook attempts
echo "<h2>4. Recent Error Log Entries</h2>";
$log_file = ini_get('error_log');
if ($log_file && file_exists($log_file)) {
    echo "Error log location: " . $log_file . "<br>";
    
    // Read last 50 lines of error log
    $lines = file($log_file);
    if ($lines) {
        $recent_lines = array_slice($lines, -50);
        $webhook_lines = array_filter($recent_lines, function($line) {
            return strpos($line, 'TwinTack Webhook Debug') !== false;
        });
        
        if (!empty($webhook_lines)) {
            echo "✅ Found TwinTack webhook log entries:<br>";
            echo "<pre style='background:#f0f0f0; padding:10px; max-height:200px; overflow:auto;'>";
            foreach ($webhook_lines as $line) {
                echo htmlspecialchars($line);
            }
            echo "</pre>";
        } else {
            echo "❌ No TwinTack webhook debug entries found in recent log<br>";
            echo "💡 This suggests the webhook function is not being called<br>";
        }
    }
} else {
    echo "⚠️ Error log file not found or not accessible<br>";
}

echo "<hr>";
echo "<p><strong>Summary:</strong></p>";
echo "<ul>";
echo "<li>If class exists but no webhook logs → AJAX handler issue</li>";
echo "<li>If no pending_review designs → Create test design or change status</li>";
echo "<li>If webhook method works manually → Customer feedback form issue</li>";
echo "</ul>";

echo "<p><em>Delete this file after debugging.</em></p>";
?>
