<?php
/**
 * Admin Dashboard View
 *
 * @package TwinTack_Security
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$security_status = TwinTack_Security_Admin::instance()->get_security_status();
?>

<div class="wrap twintack-security-dashboard">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-shield-alt"></span>
        <?php _e('TwinTack Security Suite', 'twintack-security'); ?>
    </h1>
    
    <div class="twintack-version">
        <?php printf(__('Version %s', 'twintack-security'), TWINTACK_SECURITY_VERSION); ?>
    </div>

    <div class="twintack-security-grid">
        <!-- Security Status Card -->
        <div class="twintack-card twintack-status-card">
            <div class="twintack-card-header">
                <h3><?php _e('Security Status', 'twintack-security'); ?></h3>
                <span class="twintack-status-indicator twintack-status-<?php echo esc_attr($security_status['overall']); ?>">
                    <?php echo ucfirst($security_status['overall']); ?>
                </span>
            </div>
            <div class="twintack-card-body">
                <?php if (!empty($security_status['issues'])): ?>
                    <div class="twintack-status-issues">
                        <h4><?php _e('Issues Detected:', 'twintack-security'); ?></h4>
                        <ul>
                            <?php foreach ($security_status['issues'] as $issue): ?>
                                <li class="twintack-issue"><?php echo esc_html($issue); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($security_status['recommendations'])): ?>
                    <div class="twintack-status-recommendations">
                        <h4><?php _e('Recommendations:', 'twintack-security'); ?></h4>
                        <ul>
                            <?php foreach ($security_status['recommendations'] as $recommendation): ?>
                                <li class="twintack-recommendation"><?php echo esc_html($recommendation); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (empty($security_status['issues']) && empty($security_status['recommendations'])): ?>
                    <p class="twintack-status-good">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php _e('Your security configuration is optimal.', 'twintack-security'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Security Statistics -->
        <div class="twintack-card">
            <div class="twintack-card-header">
                <h3><?php _e('Security Events', 'twintack-security'); ?></h3>
            </div>
            <div class="twintack-card-body">
                <div class="twintack-stats-grid">
                    <div class="twintack-stat">
                        <div class="twintack-stat-number"><?php echo esc_html($stats['events_today']); ?></div>
                        <div class="twintack-stat-label"><?php _e('Today', 'twintack-security'); ?></div>
                    </div>
                    <div class="twintack-stat">
                        <div class="twintack-stat-number"><?php echo esc_html($stats['events_week']); ?></div>
                        <div class="twintack-stat-label"><?php _e('This Week', 'twintack-security'); ?></div>
                    </div>
                    <div class="twintack-stat">
                        <div class="twintack-stat-number"><?php echo esc_html($stats['events_month']); ?></div>
                        <div class="twintack-stat-label"><?php _e('This Month', 'twintack-security'); ?></div>
                    </div>
                    <div class="twintack-stat twintack-stat-highlight">
                        <div class="twintack-stat-number"><?php echo esc_html($stats['high_priority_events']); ?></div>
                        <div class="twintack-stat-label"><?php _e('High Priority', 'twintack-security'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Spam Protection Stats -->
        <div class="twintack-card">
            <div class="twintack-card-header">
                <h3><?php _e('Spam Protection', 'twintack-security'); ?></h3>
            </div>
            <div class="twintack-card-body">
                <div class="twintack-stats-grid">
                    <div class="twintack-stat">
                        <div class="twintack-stat-number"><?php echo esc_html($spam_stats['blocked_today']); ?></div>
                        <div class="twintack-stat-label"><?php _e('Blocked Today', 'twintack-security'); ?></div>
                    </div>
                    <div class="twintack-stat">
                        <div class="twintack-stat-number"><?php echo esc_html($spam_stats['blocked_week']); ?></div>
                        <div class="twintack-stat-label"><?php _e('Blocked This Week', 'twintack-security'); ?></div>
                    </div>
                    <div class="twintack-stat">
                        <div class="twintack-stat-number"><?php echo esc_html($spam_stats['blocked_month']); ?></div>
                        <div class="twintack-stat-label"><?php _e('Blocked This Month', 'twintack-security'); ?></div>
                    </div>
                    <div class="twintack-stat">
                        <div class="twintack-stat-number"><?php echo esc_html($spam_stats['total_domains']); ?></div>
                        <div class="twintack-stat-label"><?php _e('Blocked Domains', 'twintack-security'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Event Types -->
        <?php if (!empty($stats['top_event_types'])): ?>
        <div class="twintack-card">
            <div class="twintack-card-header">
                <h3><?php _e('Top Event Types (30 Days)', 'twintack-security'); ?></h3>
            </div>
            <div class="twintack-card-body">
                <div class="twintack-top-list">
                    <?php foreach (array_slice($stats['top_event_types'], 0, 5) as $event): ?>
                        <div class="twintack-top-item">
                            <span class="twintack-top-label"><?php echo esc_html(str_replace('_', ' ', $event['type'])); ?></span>
                            <span class="twintack-top-count"><?php echo esc_html($event['count']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Top Blocked IPs -->
        <?php if (!empty($stats['top_blocked_ips'])): ?>
        <div class="twintack-card">
            <div class="twintack-card-header">
                <h3><?php _e('Top Blocked IPs (30 Days)', 'twintack-security'); ?></h3>
            </div>
            <div class="twintack-card-body">
                <div class="twintack-top-list">
                    <?php foreach (array_slice($stats['top_blocked_ips'], 0, 5) as $ip): ?>
                        <div class="twintack-top-item">
                            <span class="twintack-top-label"><?php echo esc_html($ip['ip']); ?></span>
                            <span class="twintack-top-count"><?php echo esc_html($ip['count']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="twintack-card">
            <div class="twintack-card-header">
                <h3><?php _e('Quick Actions', 'twintack-security'); ?></h3>
            </div>
            <div class="twintack-card-body">
                <div class="twintack-quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=twintack-security-spam'); ?>" class="button button-primary">
                        <span class="dashicons dashicons-shield"></span>
                        <?php _e('Manage Spam Protection', 'twintack-security'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=twintack-security-logs'); ?>" class="button">
                        <span class="dashicons dashicons-list-view"></span>
                        <?php _e('View Security Logs', 'twintack-security'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=twintack-security-cleanup'); ?>" class="button">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Clean Up Spam Accounts', 'twintack-security'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=twintack-security-settings'); ?>" class="button">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php _e('Security Settings', 'twintack-security'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <?php if (!empty($spam_stats['recent_blocks'])): ?>
    <div class="twintack-card twintack-full-width">
        <div class="twintack-card-header">
            <h3><?php _e('Recent Blocked Attempts', 'twintack-security'); ?></h3>
        </div>
        <div class="twintack-card-body">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Time', 'twintack-security'); ?></th>
                        <th><?php _e('Email', 'twintack-security'); ?></th>
                        <th><?php _e('Username', 'twintack-security'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($spam_stats['recent_blocks'], 0, 10) as $block): ?>
                    <tr>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($block['timestamp']))); ?></td>
                        <td><code><?php echo esc_html($block['email']); ?></code></td>
                        <td><?php echo esc_html($block['username']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
