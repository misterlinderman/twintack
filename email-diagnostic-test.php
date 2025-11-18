<?php
/**
 * Email Diagnostic Test for TwinTack
 * Upload this file to your WordPress root and visit: yoursite.com/email-diagnostic-test.php
 */

// Include WordPress
require_once(__DIR__ . '/wp-config.php');
require_once(__DIR__ . '/wp-load.php');

// Start output
echo "<h2>📧 TwinTack Email Diagnostic Test</h2>\n";

// Test 1: Basic PHP mail function
echo "<h3>Test 1: PHP mail() Function</h3>\n";
$test_email = 'matt@twintack.com'; // Replace with your email
$subject = 'Test Email from TwinTack - Basic PHP mail()';
$message = 'This is a test email sent using basic PHP mail() function.';
$headers = 'From: ' . get_option('admin_email');

if (mail($test_email, $subject, $message, $headers)) {
    echo "✅ PHP mail() function works<br>\n";
} else {
    echo "❌ PHP mail() function failed<br>\n";
}

// Test 2: WordPress wp_mail function
echo "<h3>Test 2: WordPress wp_mail() Function</h3>\n";
$subject2 = 'Test Email from TwinTack - WordPress wp_mail()';
$message2 = 'This is a test email sent using WordPress wp_mail() function.';
$headers2 = array('Content-Type: text/plain; charset=UTF-8');

if (wp_mail($test_email, $subject2, $message2, $headers2)) {
    echo "✅ WordPress wp_mail() function works<br>\n";
} else {
    echo "❌ WordPress wp_mail() function failed<br>\n";
}

// Test 3: Check current email settings
echo "<h3>Test 3: Current Email Configuration</h3>\n";
echo "📧 <strong>Admin Email:</strong> " . get_option('admin_email') . "<br>\n";
echo "🌐 <strong>Site URL:</strong> " . get_site_url() . "<br>\n";
echo "📛 <strong>Site Name:</strong> " . get_bloginfo('name') . "<br>\n";

// Test 4: Check for SMTP plugins
echo "<h3>Test 4: Email Plugin Detection</h3>\n";

$email_plugins = array(
    'WP Mail SMTP' => 'wp-mail-smtp/wp_mail_smtp.php',
    'Easy WP SMTP' => 'easy-wp-smtp/easy-wp-smtp.php',
    'Post SMTP' => 'post-smtp/postman-smtp.php',
    'WP SMTP' => 'wp-smtp/wp-smtp.php'
);

$has_smtp_plugin = false;
foreach ($email_plugins as $plugin_name => $plugin_file) {
    if (is_plugin_active($plugin_file)) {
        echo "✅ <strong>{$plugin_name}</strong> is active<br>\n";
        $has_smtp_plugin = true;
    }
}

if (!$has_smtp_plugin) {
    echo "⚠️ <strong>No SMTP plugin detected</strong> - This is likely the Gmail delivery issue!<br>\n";
}

// Test 5: Gmail-specific test
echo "<h3>Test 5: Gmail Delivery Test</h3>\n";
$gmail_test = 'test.twintack.gmail@gmail.com'; // Replace with a Gmail address you can check
$subject3 = 'Gmail Delivery Test from TwinTack - ' . date('Y-m-d H:i:s');
$message3 = "This is a specific Gmail delivery test.\n\nSent at: " . date('Y-m-d H:i:s') . "\nFrom: " . get_site_url();

if (wp_mail($gmail_test, $subject3, $message3)) {
    echo "✅ Gmail test email sent (check Gmail inbox AND spam folder)<br>\n";
    echo "📋 <strong>Action Required:</strong> Check {$gmail_test} for delivery<br>\n";
} else {
    echo "❌ Gmail test email failed to send<br>\n";
}

echo "<hr>\n";
echo "<h3>🔧 Recommendations:</h3>\n";

if (!$has_smtp_plugin) {
    echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>\n";
    echo "<strong>⚠️ Primary Issue: No SMTP Plugin</strong><br>\n";
    echo "Gmail requires authenticated email sending. Install an SMTP plugin:<br>\n";
    echo "• <strong>WP Mail SMTP</strong> (recommended)<br>\n";
    echo "• Configure with your hosting provider's SMTP settings<br>\n";
    echo "• Or use Gmail SMTP with App Password<br>\n";
    echo "</div>\n";
}

echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin-top: 10px;'>\n";
echo "<strong>✅ Quick Fix Steps:</strong><br>\n";
echo "1. Install <strong>WP Mail SMTP</strong> plugin<br>\n";
echo "2. Configure with your hosting SMTP or Gmail SMTP<br>\n";
echo "3. Test email delivery again<br>\n";
echo "4. Delete this diagnostic file when done<br>\n";
echo "</div>\n";

// Cleanup notice
echo "<hr>\n";
echo "<p><strong>⚠️ Security Note:</strong> Delete this file after testing: <code>rm email-diagnostic-test.php</code></p>\n";
?>