<?php
/**
 * Test Production Status Trigger
 * Upload this to test the Monday.com production status trigger
 */

// Load WordPress
require_once('wp-config.php');
require_once('wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

echo "<h1>Monday.com Production Status Trigger Test</h1>";

// Get a grip design for testing
$grip_designs = get_posts(array(
    'post_type' => 'grip_design',
    'posts_per_page' => 5,
    'post_status' => 'any'
));

if (empty($grip_designs)) {
    echo "<p style='color: red;'><strong>No grip designs found!</strong> Create a grip design first.</p>";
    exit;
}

$test_grip = $grip_designs[0];
echo "<h2>Testing with Grip Design: {$test_grip->post_title} (ID: {$test_grip->ID})</h2>";

// Show current status
$current_status = get_post_meta($test_grip->ID, '_grip_artwork_status', true);
echo "<p><strong>Current Status:</strong> " . ($current_status ?: 'Not set') . "</p>";

// Test the production status trigger
echo "<h3>Simulating Monday.com Production Status Update</h3>";

// Trigger the status change that would come from Monday.com
$old_status = $current_status ?: 'customer_approved';
$new_status = 'production_started';

echo "<p>Simulating status change: <strong>{$old_status}</strong> → <strong>{$new_status}</strong></p>";

// Update the status
update_post_meta($test_grip->ID, '_grip_artwork_status', $new_status);

// Trigger the email notification action
do_action('grip_design_artwork_status_changed', $test_grip->ID, $old_status, $new_status);

echo "<div style='background: #d4edda; color: #155724; padding: 15px; margin: 20px 0; border: 1px solid #c3e6cb; border-radius: 5px;'>";
echo "<strong>✓ Production status trigger executed!</strong><br>";
echo "Check your WordPress error logs for email notification details.";
echo "</div>";

// Show customer details for verification
$customer_email = get_post_meta($test_grip->ID, '_grip_customer_email', true);
$customer_name = get_post_meta($test_grip->ID, '_grip_customer_name', true);
$team_name = get_post_meta($test_grip->ID, '_grip_team_name', true);

echo "<h3>Customer Details (for verification)</h3>";
echo "<p><strong>Customer Email:</strong> " . ($customer_email ?: 'Not set') . "</p>";
echo "<p><strong>Customer Name:</strong> " . ($customer_name ?: 'Not set') . "</p>";
echo "<p><strong>Team Name:</strong> " . ($team_name ?: 'Not set') . "</p>";

echo "<h3>For Make.com Integration</h3>";
echo "<p>Create a new Make.com scenario that:</p>";
echo "<ol>";
echo "<li><strong>Watches Monday.com</strong> for status changes in your Custom Grip Orders board</li>";
echo "<li><strong>Triggers when</strong> an item moves to 'Production Ready' or 'In Production' status</li>";
echo "<li><strong>Calls WordPress API</strong>: <code>POST /wp-json/twintack/v1/grip-design/{id}/monday</code></li>";
echo "<li><strong>Sends payload</strong>: <code>{\"artwork_status\": \"production_started\"}</code></li>";
echo "</ol>";

echo "<p><strong>API URL:</strong> <code>" . get_site_url() . "/wp-json/twintack/v1/grip-design/{grip_id}/monday</code></p>";
echo "<p><strong>API Key:</strong> <code>2ta1rn5l53v5twumr9jyt8w3xklayygd</code></p>";

echo "<p style='margin-top: 30px;'><em>Delete this file after testing.</em></p>";
?>
