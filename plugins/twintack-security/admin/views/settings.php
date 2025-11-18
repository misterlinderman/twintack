<?php
/**
 * Settings Admin View
 *
 * @package TwinTack_Security
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap twintack-security-settings">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-admin-settings"></span>
        <?php _e('TwinTack Security Settings', 'twintack-security'); ?>
    </h1>

    <form method="post" action="">
        <?php wp_nonce_field('twintack_security_settings'); ?>
        
        <div class="twintack-settings-grid">
            <!-- Main Security Settings -->
            <div class="twintack-card">
                <div class="twintack-card-header">
                    <h3><?php _e('Main Security Settings', 'twintack-security'); ?></h3>
                </div>
                <div class="twintack-card-body">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Enable Security Suite', 'twintack-security'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="twintack_security_enabled" value="1" 
                                           <?php checked(get_option('twintack_security_enabled'), 'yes'); ?> />
                                    <?php _e('Enable TwinTack Security protection', 'twintack-security'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Master switch for all security features. Disable this to turn off all protection.', 'twintack-security'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('SMS Gateway Blocking', 'twintack-security'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="twintack_security_sms_blocking_enabled" value="1" 
                                           <?php checked(get_option('twintack_security_sms_blocking_enabled'), 'yes'); ?> />
                                    <?php _e('Block SMS gateway email addresses', 'twintack-security'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Prevents registration from SMS-to-email gateway addresses like @vtext.com, @tmomail.net, etc.', 'twintack-security'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Enhanced Email Validation', 'twintack-security'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="twintack_security_email_validation_enabled" value="1" 
                                           <?php checked(get_option('twintack_security_email_validation_enabled'), 'yes'); ?> />
                                    <?php _e('Enable advanced email validation', 'twintack-security'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Blocks numeric-only emails, suspicious patterns, and disposable email providers.', 'twintack-security'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Rate Limiting Settings -->
            <div class="twintack-card">
                <div class="twintack-card-header">
                    <h3><?php _e('Rate Limiting', 'twintack-security'); ?></h3>
                </div>
                <div class="twintack-card-body">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Enable Rate Limiting', 'twintack-security'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="twintack_security_rate_limiting_enabled" value="1" 
                                           <?php checked(get_option('twintack_security_rate_limiting_enabled'), 'yes'); ?> />
                                    <?php _e('Limit registration attempts per IP address', 'twintack-security'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Helps prevent brute force attacks and spam floods.', 'twintack-security'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Maximum Attempts', 'twintack-security'); ?></th>
                            <td>
                                <input type="number" name="twintack_security_rate_limit_attempts" 
                                       value="<?php echo esc_attr(get_option('twintack_security_rate_limit_attempts', 5)); ?>" 
                                       min="1" max="50" class="small-text" />
                                <p class="description">
                                    <?php _e('Maximum failed registration attempts allowed within the time window.', 'twintack-security'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Time Window (seconds)', 'twintack-security'); ?></th>
                            <td>
                                <input type="number" name="twintack_security_rate_limit_window" 
                                       value="<?php echo esc_attr(get_option('twintack_security_rate_limit_window', 300)); ?>" 
                                       min="60" max="3600" class="small-text" />
                                <p class="description">
                                    <?php _e('Time window for rate limiting (300 = 5 minutes, 3600 = 1 hour).', 'twintack-security'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Logging Settings -->
            <div class="twintack-card">
                <div class="twintack-card-header">
                    <h3><?php _e('Security Logging', 'twintack-security'); ?></h3>
                </div>
                <div class="twintack-card-body">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Log Retention Period', 'twintack-security'); ?></th>
                            <td>
                                <input type="number" name="twintack_security_log_retention_days" 
                                       value="<?php echo esc_attr(get_option('twintack_security_log_retention_days', 30)); ?>" 
                                       min="1" max="365" class="small-text" />
                                <?php _e('days', 'twintack-security'); ?>
                                <p class="description">
                                    <?php _e('How long to keep security logs before automatic deletion.', 'twintack-security'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Admin Email Notifications', 'twintack-security'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="twintack_security_admin_email_notifications" value="1" 
                                           <?php checked(get_option('twintack_security_admin_email_notifications'), 'yes'); ?> />
                                    <?php _e('Send email alerts for high-priority security events', 'twintack-security'); ?>
                                </label>
                                <p class="description">
                                    <?php printf(__('Notifications will be sent to: %s', 'twintack-security'), get_option('admin_email')); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Integration Status -->
            <div class="twintack-card">
                <div class="twintack-card-header">
                    <h3><?php _e('Integration Status', 'twintack-security'); ?></h3>
                </div>
                <div class="twintack-card-body">
                    <div class="twintack-integration-status">
                        <div class="twintack-integration-item">
                            <span class="twintack-integration-label"><?php _e('WordPress Core', 'twintack-security'); ?></span>
                            <span class="twintack-status-indicator twintack-status-good">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php _e('Active', 'twintack-security'); ?>
                            </span>
                        </div>
                        
                        <?php if (TwinTack_Security()->is_woocommerce_active()): ?>
                        <div class="twintack-integration-item">
                            <span class="twintack-integration-label"><?php _e('WooCommerce', 'twintack-security'); ?></span>
                            <span class="twintack-status-indicator twintack-status-good">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php _e('Active', 'twintack-security'); ?>
                            </span>
                        </div>
                        <?php else: ?>
                        <div class="twintack-integration-item">
                            <span class="twintack-integration-label"><?php _e('WooCommerce', 'twintack-security'); ?></span>
                            <span class="twintack-status-indicator twintack-status-warning">
                                <span class="dashicons dashicons-warning"></span>
                                <?php _e('Not Detected', 'twintack-security'); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="twintack-integration-item">
                            <span class="twintack-integration-label"><?php _e('Database Table', 'twintack-security'); ?></span>
                            <?php
                            global $wpdb;
                            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}twintack_security_log'");
                            ?>
                            <span class="twintack-status-indicator <?php echo $table_exists ? 'twintack-status-good' : 'twintack-status-critical'; ?>">
                                <span class="dashicons <?php echo $table_exists ? 'dashicons-yes-alt' : 'dashicons-no-alt'; ?>"></span>
                                <?php echo $table_exists ? __('Exists', 'twintack-security') : __('Missing', 'twintack-security'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Information -->
            <div class="twintack-card">
                <div class="twintack-card-header">
                    <h3><?php _e('System Information', 'twintack-security'); ?></h3>
                </div>
                <div class="twintack-card-body">
                    <div class="twintack-system-info">
                        <div class="twintack-info-item">
                            <span class="twintack-info-label"><?php _e('Plugin Version:', 'twintack-security'); ?></span>
                            <span class="twintack-info-value"><?php echo TWINTACK_SECURITY_VERSION; ?></span>
                        </div>
                        <div class="twintack-info-item">
                            <span class="twintack-info-label"><?php _e('WordPress Version:', 'twintack-security'); ?></span>
                            <span class="twintack-info-value"><?php echo get_bloginfo('version'); ?></span>
                        </div>
                        <div class="twintack-info-item">
                            <span class="twintack-info-label"><?php _e('PHP Version:', 'twintack-security'); ?></span>
                            <span class="twintack-info-value"><?php echo PHP_VERSION; ?></span>
                        </div>
                        <?php if (TwinTack_Security()->is_woocommerce_active()): ?>
                        <div class="twintack-info-item">
                            <span class="twintack-info-label"><?php _e('WooCommerce Version:', 'twintack-security'); ?></span>
                            <span class="twintack-info-value"><?php echo WC()->version; ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="twintack-info-item">
                            <span class="twintack-info-label"><?php _e('Server Time:', 'twintack-security'); ?></span>
                            <span class="twintack-info-value"><?php echo current_time('Y-m-d H:i:s T'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php submit_button(__('Save Settings', 'twintack-security'), 'primary large'); ?>
    </form>

    <!-- Danger Zone -->
    <div class="twintack-card twintack-danger-zone">
        <div class="twintack-card-header">
            <h3><?php _e('Danger Zone', 'twintack-security'); ?></h3>
        </div>
        <div class="twintack-card-body">
            <div class="twintack-danger-actions">
                <div class="twintack-danger-action">
                    <h4><?php _e('Clear All Security Logs', 'twintack-security'); ?></h4>
                    <p><?php _e('This will permanently delete all security event logs. This action cannot be undone.', 'twintack-security'); ?></p>
                    <button type="button" class="button button-secondary" id="clear-all-logs">
                        <?php _e('Clear All Logs', 'twintack-security'); ?>
                    </button>
                </div>
                
                <div class="twintack-danger-action">
                    <h4><?php _e('Reset to Default Settings', 'twintack-security'); ?></h4>
                    <p><?php _e('This will reset all plugin settings to their default values. Your blocked domains list will be preserved.', 'twintack-security'); ?></p>
                    <button type="button" class="button button-secondary" id="reset-settings">
                        <?php _e('Reset Settings', 'twintack-security'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Clear all logs
    $('#clear-all-logs').on('click', function() {
        if (!confirm('<?php _e('Are you sure you want to delete ALL security logs? This action cannot be undone.', 'twintack-security'); ?>')) {
            return;
        }
        
        var button = $(this);
        button.prop('disabled', true).text('<?php _e('Clearing...', 'twintack-security'); ?>');
        
        $.post(ajaxurl, {
            action: 'twintack_security_clear_logs',
            nonce: '<?php echo wp_create_nonce('twintack_security_clear_logs'); ?>',
            older_than_days: 0
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                button.prop('disabled', false).text('<?php _e('Clear All Logs', 'twintack-security'); ?>');
            } else {
                alert(response.data || '<?php _e('Error clearing logs.', 'twintack-security'); ?>');
                button.prop('disabled', false).text('<?php _e('Clear All Logs', 'twintack-security'); ?>');
            }
        });
    });
    
    // Reset settings
    $('#reset-settings').on('click', function() {
        if (!confirm('<?php _e('Are you sure you want to reset all settings to defaults? Your blocked domains will be preserved.', 'twintack-security'); ?>')) {
            return;
        }
        
        // This would need to be implemented as a separate AJAX action
        alert('<?php _e('Settings reset functionality would be implemented here.', 'twintack-security'); ?>');
    });
});
</script>
