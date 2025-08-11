<?php
/**
 * Email Notification Test Page
 * Upload this to WordPress root for easy email testing
 */

// Load WordPress
require_once('wp-config.php');
require_once('wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

echo "<h1>TwinTack Email Notification Testing</h1>";

// Handle form submission
if (isset($_POST['send_test_email'])) {
    $email_type = sanitize_text_field($_POST['email_type']);
    $test_email = sanitize_email($_POST['test_email']);
    $grip_id = !empty($_POST['grip_id']) ? intval($_POST['grip_id']) : null;
    
    // Get email notifications instance
    $email_notifications = TwinTack_Grip_Email_Notifications::get_instance();
    
    // Send test email
    $success = $email_notifications->send_test_email($email_type, $test_email, $grip_id);
    
    if ($success) {
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; margin: 20px 0; border: 1px solid #c3e6cb; border-radius: 5px;'>";
        echo "<strong>✓ Success!</strong> Test email sent to " . esc_html($test_email);
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 20px 0; border: 1px solid #f5c6cb; border-radius: 5px;'>";
        echo "<strong>✗ Error!</strong> Failed to send test email.";
        echo "</div>";
    }
}

// Get grip designs for testing
$grip_designs = get_posts(array(
    'post_type' => 'grip_design',
    'posts_per_page' => 10,
    'post_status' => 'any'
));

?>

<div style="max-width: 800px; margin: 20px 0;">
    <h2>Test Email Notifications</h2>
    
    <form method="post" style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
        <table style="width: 100%;">
            <tr>
                <td style="padding: 10px; font-weight: bold;">Email Type:</td>
                <td style="padding: 10px;">
                    <select name="email_type" required style="width: 200px; padding: 5px;">
                        <option value="artwork_ready">Artwork Ready for Review</option>
                        <option value="production_started">Production Started</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; font-weight: bold;">Test Email Address:</td>
                <td style="padding: 10px;">
                    <input type="email" name="test_email" required style="width: 300px; padding: 5px;" 
                           placeholder="your-email@example.com" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>">
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; font-weight: bold;">Use Real Grip Design:</td>
                <td style="padding: 10px;">
                    <select name="grip_id" style="width: 300px; padding: 5px;">
                        <option value="">Use Test Data</option>
                        <?php foreach ($grip_designs as $design): ?>
                            <option value="<?php echo $design->ID; ?>">
                                <?php echo esc_html($design->post_title . ' (ID: ' . $design->ID . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        
        <p style="margin-top: 20px;">
            <input type="submit" name="send_test_email" value="Send Test Email" 
                   style="background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer;">
        </p>
    </form>
    
    <h2>Email Workflow Triggers</h2>
    <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
        <h3>1. Artwork Ready for Review</h3>
        <p><strong>Trigger:</strong> When Monday.com updates grip design with artwork_status = "pending_review"</p>
        <p><strong>API Call:</strong> <code>POST /wp-json/twintack/v1/grip-design/{id}/monday</code></p>
        <p><strong>Payload:</strong> <code>{"artwork_status": "pending_review"}</code></p>
        
        <h3>2. Production Started</h3>
        <p><strong>Trigger:</strong> When customer approves design and final order is completed</p>
        <p><strong>Occurs:</strong> After WooCommerce order status changes to "completed" for custom grip product</p>
    </div>
    
    <h2>Current Email Settings</h2>
    <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
        <p><strong>Site Name:</strong> <?php echo esc_html(get_bloginfo('name')); ?></p>
        <p><strong>Site URL:</strong> <?php echo esc_html(get_site_url()); ?></p>
        <p><strong>From Address:</strong> noreply@<?php echo esc_html(parse_url(get_site_url(), PHP_URL_HOST)); ?></p>
        
        <?php if (empty($grip_designs)): ?>
            <p style="color: #d63384;"><strong>Note:</strong> No grip designs found. Create a test grip design for more realistic testing.</p>
        <?php endif; ?>
    </div>
    
    <p style="margin-top: 30px;"><em>Delete this file after testing email notifications.</em></p>
</div>

<?php
