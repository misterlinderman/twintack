<?php
/**
 * Spam Protection Admin View
 *
 * @package TwinTack_Security
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap twintack-security-spam">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-shield"></span>
        <?php _e('Spam Protection', 'twintack-security'); ?>
    </h1>

    <div class="twintack-security-grid">
        <!-- Spam Statistics -->
        <div class="twintack-card">
            <div class="twintack-card-header">
                <h3><?php _e('Protection Statistics', 'twintack-security'); ?></h3>
            </div>
            <div class="twintack-card-body">
                <div class="twintack-stats-grid">
                    <div class="twintack-stat">
                        <div class="twintack-stat-number"><?php echo esc_html($spam_stats['blocked_today']); ?></div>
                        <div class="twintack-stat-label"><?php _e('Blocked Today', 'twintack-security'); ?></div>
                    </div>
                    <div class="twintack-stat">
                        <div class="twintack-stat-number"><?php echo esc_html($spam_stats['blocked_week']); ?></div>
                        <div class="twintack-stat-label"><?php _e('This Week', 'twintack-security'); ?></div>
                    </div>
                    <div class="twintack-stat">
                        <div class="twintack-stat-number"><?php echo esc_html($spam_stats['blocked_month']); ?></div>
                        <div class="twintack-stat-label"><?php _e('This Month', 'twintack-security'); ?></div>
                    </div>
                    <div class="twintack-stat">
                        <div class="twintack-stat-number"><?php echo esc_html($spam_stats['total_domains']); ?></div>
                        <div class="twintack-stat-label"><?php _e('Blocked Domains', 'twintack-security'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email Validation Test -->
        <div class="twintack-card">
            <div class="twintack-card-header">
                <h3><?php _e('Test Email Validation', 'twintack-security'); ?></h3>
            </div>
            <div class="twintack-card-body">
                <form id="twintack-test-email-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Email Address', 'twintack-security'); ?></th>
                            <td>
                                <input type="email" id="test-email" class="regular-text" placeholder="user@example.com" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Username', 'twintack-security'); ?></th>
                            <td>
                                <input type="text" id="test-username" class="regular-text" placeholder="username" />
                                <p class="description"><?php _e('Optional - used for correlation testing', 'twintack-security'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php _e('Test Email', 'twintack-security'); ?></button>
                    </p>
                </form>
                <div id="test-email-result" class="twintack-test-result" style="display: none;"></div>
            </div>
        </div>
    </div>

    <!-- Blocked Domains Management -->
    <div class="twintack-card twintack-full-width">
        <div class="twintack-card-header">
            <h3><?php _e('Blocked Domains Management', 'twintack-security'); ?></h3>
            <div class="twintack-card-actions">
                <button type="button" class="button" id="import-common-domains">
                    <?php _e('Import Common SMS Domains', 'twintack-security'); ?>
                </button>
            </div>
        </div>
        <div class="twintack-card-body">
            <!-- Add Domain Form -->
            <div class="twintack-add-domain-form">
                <h4><?php _e('Add Blocked Domain', 'twintack-security'); ?></h4>
                <form id="add-domain-form" class="twintack-inline-form">
                    <input type="text" id="new-domain" placeholder="example.com" class="regular-text" />
                    <button type="submit" class="button button-primary"><?php _e('Add Domain', 'twintack-security'); ?></button>
                </form>
            </div>

            <!-- Domains List -->
            <div class="twintack-domains-list">
                <h4><?php _e('Currently Blocked Domains', 'twintack-security'); ?> (<?php echo count($blocked_domains); ?>)</h4>
                <?php if (!empty($blocked_domains)): ?>
                    <div class="twintack-domains-grid">
                        <?php foreach ($blocked_domains as $domain): ?>
                            <div class="twintack-domain-item" data-domain="<?php echo esc_attr($domain); ?>">
                                <span class="twintack-domain-name"><?php echo esc_html($domain); ?></span>
                                <button type="button" class="button-link twintack-remove-domain" 
                                        data-domain="<?php echo esc_attr($domain); ?>">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="twintack-no-domains"><?php _e('No domains are currently blocked.', 'twintack-security'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top Blocked Domains -->
    <?php if (!empty($spam_stats['top_blocked_domains'])): ?>
    <div class="twintack-card twintack-full-width">
        <div class="twintack-card-header">
            <h3><?php _e('Most Blocked Domains (30 Days)', 'twintack-security'); ?></h3>
        </div>
        <div class="twintack-card-body">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Domain', 'twintack-security'); ?></th>
                        <th><?php _e('Blocked Attempts', 'twintack-security'); ?></th>
                        <th><?php _e('Actions', 'twintack-security'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($spam_stats['top_blocked_domains'] as $domain_stat): ?>
                    <tr>
                        <td><code><?php echo esc_html($domain_stat['domain']); ?></code></td>
                        <td><?php echo esc_html($domain_stat['count']); ?></td>
                        <td>
                            <?php if (!in_array($domain_stat['domain'], $blocked_domains)): ?>
                                <button type="button" class="button button-small twintack-add-domain-from-stats" 
                                        data-domain="<?php echo esc_attr($domain_stat['domain']); ?>">
                                    <?php _e('Block Domain', 'twintack-security'); ?>
                                </button>
                            <?php else: ?>
                                <span class="twintack-already-blocked"><?php _e('Already Blocked', 'twintack-security'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Blocked Attempts -->
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
                    <?php foreach (array_slice($spam_stats['recent_blocks'], 0, 20) as $block): ?>
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

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Test email validation
    $('#twintack-test-email-form').on('submit', function(e) {
        e.preventDefault();
        
        var email = $('#test-email').val();
        var username = $('#test-username').val();
        
        if (!email) {
            alert('<?php _e('Please enter an email address to test.', 'twintack-security'); ?>');
            return;
        }
        
        $.post(ajaxurl, {
            action: 'twintack_security_test_email',
            nonce: twintackSecurity.nonce,
            email: email,
            username: username
        }, function(response) {
            var resultDiv = $('#test-email-result');
            if (response.success) {
                resultDiv.removeClass('twintack-error').addClass('twintack-success')
                       .html('<strong><?php _e('Result:', 'twintack-security'); ?></strong> ' + response.data.message)
                       .show();
            } else {
                resultDiv.removeClass('twintack-success').addClass('twintack-error')
                       .html('<strong><?php _e('Blocked:', 'twintack-security'); ?></strong> ' + 
                             (response.data.user_message || response.data.message))
                       .show();
            }
        });
    });
    
    // Add domain
    $('#add-domain-form').on('submit', function(e) {
        e.preventDefault();
        
        var domain = $('#new-domain').val().trim();
        if (!domain) {
            alert('<?php _e('Please enter a domain to block.', 'twintack-security'); ?>');
            return;
        }
        
        $.post(ajaxurl, {
            action: 'twintack_security_add_domain',
            nonce: '<?php echo wp_create_nonce('twintack_security_domain_management'); ?>',
            domain: domain
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data || twintackSecurity.strings.error);
            }
        });
    });
    
    // Remove domain
    $('.twintack-remove-domain').on('click', function() {
        var domain = $(this).data('domain');
        
        if (!confirm('<?php _e('Are you sure you want to remove this domain from the blocked list?', 'twintack-security'); ?>')) {
            return;
        }
        
        $.post(ajaxurl, {
            action: 'twintack_security_remove_domain',
            nonce: '<?php echo wp_create_nonce('twintack_security_domain_management'); ?>',
            domain: domain
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data || twintackSecurity.strings.error);
            }
        });
    });
    
    // Add domain from stats
    $('.twintack-add-domain-from-stats').on('click', function() {
        var domain = $(this).data('domain');
        
        $.post(ajaxurl, {
            action: 'twintack_security_add_domain',
            nonce: '<?php echo wp_create_nonce('twintack_security_domain_management'); ?>',
            domain: domain
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data || twintackSecurity.strings.error);
            }
        });
    });
    
    // Import common domains
    $('#import-common-domains').on('click', function() {
        if (!confirm('<?php _e('This will import common SMS gateway domains to the blocked list. Continue?', 'twintack-security'); ?>')) {
            return;
        }
        
        var button = $(this);
        button.prop('disabled', true).text('<?php _e('Importing...', 'twintack-security'); ?>');
        
        // This would be handled by a separate AJAX action
        setTimeout(function() {
            location.reload();
        }, 2000);
    });
});
</script>
