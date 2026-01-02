<?php
/**
 * Cron Health Monitor
 * 
 * Monitors WP-Cron health and displays status in WordPress admin
 * 
 * @package TwinTack_Manual_Order_Payments
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class TwinTack_Cron_Health_Monitor {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add dashboard widget
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        
        // Add admin notice if cron is failing
        add_action('admin_notices', array($this, 'show_cron_health_notice'));
        
        // Store last cron run time when wp-cron.php is called
        add_action('init', array($this, 'record_cron_execution'), 1);
    }
    
    /**
     * Record when wp-cron.php executes
     */
    public function record_cron_execution() {
        // Only record if this is actually a cron request
        if (defined('DOING_CRON') && DOING_CRON) {
            update_option('twintack_last_cron_run', time());
            update_option('twintack_cron_run_count', get_option('twintack_cron_run_count', 0) + 1);
        }
    }
    
    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'twintack_cron_health_widget',
            '🔄 WP-Cron Health Monitor',
            array($this, 'render_dashboard_widget')
        );
    }
    
    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        $health = $this->get_cron_health();
        
        // Status indicator
        $status_color = $health['is_healthy'] ? '#28a745' : '#dc3545';
        $status_text = $health['is_healthy'] ? '✅ HEALTHY' : '❌ UNHEALTHY';
        
        ?>
        <div style="padding: 10px;">
            <div style="margin-bottom: 15px;">
                <strong style="font-size: 16px; color: <?php echo $status_color; ?>;">
                    <?php echo $status_text; ?>
                </strong>
            </div>
            
            <table style="width: 100%; border-collapse: collapse;">
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 8px 0; font-weight: bold;">Last Cron Run:</td>
                    <td style="padding: 8px 0;">
                        <?php if ($health['last_run_timestamp']): ?>
                            <?php echo $health['last_run_human']; ?>
                            <br><small style="color: #666;"><?php echo $health['last_run_date']; ?></small>
                        <?php else: ?>
                            <span style="color: #dc3545;">Never recorded</span>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 8px 0; font-weight: bold;">Real Cron Job:</td>
                    <td style="padding: 8px 0;">
                        <?php if ($health['has_real_cron']): ?>
                            <span style="color: #28a745;">✅ Active</span>
                        <?php else: ?>
                            <span style="color: #ffc107;">⚠️ Using WP-Cron only</span>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 8px 0; font-weight: bold;">Overdue Events:</td>
                    <td style="padding: 8px 0;">
                        <?php if ($health['overdue_count'] > 0): ?>
                            <span style="color: #dc3545; font-weight: bold;">
                                ⚠️ <?php echo $health['overdue_count']; ?> event(s)
                            </span>
                        <?php else: ?>
                            <span style="color: #28a745;">None</span>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 8px 0; font-weight: bold;">Total Executions:</td>
                    <td style="padding: 8px 0;">
                        <?php echo number_format(get_option('twintack_cron_run_count', 0)); ?>
                    </td>
                </tr>
                
                <?php if (class_exists('TwinTack_Shippo_Webhook_Handler')): ?>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 8px 0; font-weight: bold;">Shippo Sync:</td>
                    <td style="padding: 8px 0;">
                        <?php
                        $webhook_handler = TwinTack_Shippo_Webhook_Handler::get_instance();
                        $automation_status = $webhook_handler->get_automation_status();
                        if ($automation_status['enabled']) {
                            echo '<span style="color: #28a745;">✅ Auto-sync enabled</span>';
                            echo '<br><small style="color: #666;">Next run: ' . $automation_status['next_run'] . '</small>';
                        } else {
                            echo '<span style="color: #ffc107;">⚠️ Manual sync only</span>';
                        }
                        ?>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            
            <?php if (!$health['is_healthy']): ?>
            <div style="background: #fff3cd; padding: 10px; margin-top: 15px; border-left: 4px solid #ffc107; border-radius: 3px;">
                <strong>⚠️ Action Required:</strong><br>
                <?php echo $health['warning_message']; ?>
            </div>
            <?php endif; ?>
            
            <div style="margin-top: 15px; text-align: center;">
                <a href="<?php echo admin_url('admin.php?page=twintack-shippo-sync'); ?>" class="button button-primary">
                    View Shippo Sync
                </a>
                <a href="/wp-content/plugins/twintack-manual-order-payments/test-wp-cron-status.php" class="button" target="_blank">
                    Full Diagnostics
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Show admin notice if cron is unhealthy
     */
    public function show_cron_health_notice() {
        // Only show on main admin pages, not everywhere
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array('dashboard', 'edit-shop_order', 'shop_order'))) {
            return;
        }
        
        $health = $this->get_cron_health();
        
        // Only show warning if cron hasn't run in over 30 minutes
        if (!$health['is_healthy'] && $health['minutes_since_last_run'] > 30) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong>⚠️ WP-Cron Warning:</strong> 
                    <?php echo $health['warning_message']; ?>
                    <a href="<?php echo admin_url('admin.php?page=twintack-shippo-sync'); ?>">View Shippo Sync Status</a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Get comprehensive cron health status
     */
    public function get_cron_health() {
        $last_run = get_option('twintack_last_cron_run', 0);
        $current_time = time();
        $minutes_since = $last_run ? round(($current_time - $last_run) / 60) : null;
        
        // Check if WP-Cron is disabled
        $wp_cron_disabled = (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON);
        
        // Count overdue events
        $overdue_count = $this->count_overdue_events();
        
        // Determine if we have a real cron job
        // If DISABLE_WP_CRON is true, assume real cron is set up
        // OR if cron ran in last 20 minutes consistently
        $has_real_cron = $wp_cron_disabled || ($last_run && $minutes_since <= 20);
        
        // Determine overall health
        $is_healthy = true;
        $warning_message = '';
        
        if (!$last_run) {
            $is_healthy = false;
            $warning_message = 'WP-Cron has never been recorded. Set up a real cron job in your hosting control panel.';
        } elseif ($minutes_since > 60) {
            $is_healthy = false;
            $warning_message = "WP-Cron hasn't run in {$minutes_since} minutes. Check your cron job configuration.";
        } elseif ($overdue_count > 0) {
            $is_healthy = false;
            $warning_message = "{$overdue_count} scheduled event(s) are overdue. Cron may not be running frequently enough.";
        }
        
        return array(
            'is_healthy' => $is_healthy,
            'has_real_cron' => $has_real_cron,
            'last_run_timestamp' => $last_run,
            'last_run_date' => $last_run ? date('Y-m-d H:i:s', $last_run) : null,
            'last_run_human' => $last_run ? human_time_diff($last_run) . ' ago' : null,
            'minutes_since_last_run' => $minutes_since,
            'overdue_count' => $overdue_count,
            'warning_message' => $warning_message,
            'wp_cron_disabled' => $wp_cron_disabled
        );
    }
    
    /**
     * Count overdue cron events
     */
    private function count_overdue_events() {
        $cron_events = _get_cron_array();
        $overdue_count = 0;
        $current_time = time();
        
        if (!empty($cron_events)) {
            foreach ($cron_events as $timestamp => $cron) {
                if ($timestamp < $current_time) {
                    $overdue_count += count($cron);
                }
            }
        }
        
        return $overdue_count;
    }
    
    /**
     * Get cron health for API/AJAX use
     */
    public function get_health_data() {
        return $this->get_cron_health();
    }
}

