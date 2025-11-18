<?php
/**
 * Account Cleanup Admin View
 *
 * @package TwinTack_Security
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap twintack-security-cleanup">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-trash"></span>
        <?php _e('Account Cleanup', 'twintack-security'); ?>
    </h1>

    <p class="description">
        <?php _e('This tool helps identify and safely remove spam accounts from your website. Only accounts with no orders or activity will be marked as safe to delete.', 'twintack-security'); ?>
    </p>

    <?php if (!empty($spam_accounts)): ?>
        <div class="twintack-card twintack-full-width">
            <div class="twintack-card-header">
                <h3>
                    <?php printf(__('Detected Spam Accounts (%d)', 'twintack-security'), count($spam_accounts)); ?>
                </h3>
                <div class="twintack-card-actions">
                    <?php
                    $safe_to_delete = array_filter($spam_accounts, function($account) {
                        return $account['is_safe_to_delete'];
                    });
                    ?>
                    <?php if (!empty($safe_to_delete)): ?>
                        <button type="button" class="button button-primary" id="bulk-delete-spam">
                            <?php printf(__('Delete %d Safe Accounts', 'twintack-security'), count($safe_to_delete)); ?>
                        </button>
                    <?php endif; ?>
                    <button type="button" class="button" id="refresh-scan">
                        <?php _e('Refresh Scan', 'twintack-security'); ?>
                    </button>
                </div>
            </div>
            <div class="twintack-card-body">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" id="select-all-spam" />
                            </th>
                            <th><?php _e('Username', 'twintack-security'); ?></th>
                            <th><?php _e('Email', 'twintack-security'); ?></th>
                            <th><?php _e('Registered', 'twintack-security'); ?></th>
                            <th><?php _e('Orders', 'twintack-security'); ?></th>
                            <th><?php _e('Status', 'twintack-security'); ?></th>
                            <th><?php _e('Actions', 'twintack-security'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($spam_accounts as $account): ?>
                        <tr class="<?php echo $account['is_safe_to_delete'] ? 'twintack-safe-delete' : 'twintack-unsafe-delete'; ?>">
                            <th class="check-column">
                                <?php if ($account['is_safe_to_delete']): ?>
                                    <input type="checkbox" class="spam-account-checkbox" 
                                           value="<?php echo esc_attr($account['id']); ?>" />
                                <?php endif; ?>
                            </th>
                            <td>
                                <strong><?php echo esc_html($account['username']); ?></strong>
                                <div class="row-actions">
                                    <span class="view">
                                        <a href="<?php echo admin_url('user-edit.php?user_id=' . $account['id']); ?>">
                                            <?php _e('View User', 'twintack-security'); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <code><?php echo esc_html($account['email']); ?></code>
                            </td>
                            <td>
                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($account['registered']))); ?>
                            </td>
                            <td>
                                <?php if ($account['order_count'] > 0): ?>
                                    <span class="twintack-has-orders"><?php echo esc_html($account['order_count']); ?></span>
                                <?php else: ?>
                                    <span class="twintack-no-orders">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($account['is_safe_to_delete']): ?>
                                    <span class="twintack-status-good">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php _e('Safe to Delete', 'twintack-security'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="twintack-status-warning">
                                        <span class="dashicons dashicons-warning"></span>
                                        <?php _e('Has Activity', 'twintack-security'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($account['is_safe_to_delete']): ?>
                                    <button type="button" class="button button-small button-link-delete delete-single-spam" 
                                            data-user-id="<?php echo esc_attr($account['id']); ?>"
                                            data-username="<?php echo esc_attr($account['username']); ?>">
                                        <?php _e('Delete', 'twintack-security'); ?>
                                    </button>
                                <?php else: ?>
                                    <span class="twintack-text-muted"><?php _e('Cannot Delete', 'twintack-security'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="twintack-cleanup-legend">
                    <h4><?php _e('Legend:', 'twintack-security'); ?></h4>
                    <ul>
                        <li class="twintack-safe-delete">
                            <span class="twintack-status-good">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php _e('Safe to Delete', 'twintack-security'); ?>
                            </span>
                            - <?php _e('Account has no orders, posts, comments, or other activity', 'twintack-security'); ?>
                        </li>
                        <li class="twintack-unsafe-delete">
                            <span class="twintack-status-warning">
                                <span class="dashicons dashicons-warning"></span>
                                <?php _e('Has Activity', 'twintack-security'); ?>
                            </span>
                            - <?php _e('Account has orders, posts, comments, or other activity - review manually', 'twintack-security'); ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="twintack-card">
            <div class="twintack-card-body twintack-text-center">
                <div class="twintack-no-spam-accounts">
                    <span class="dashicons dashicons-smiley" style="font-size: 48px; color: #28a745;"></span>
                    <h3><?php _e('No Spam Accounts Detected', 'twintack-security'); ?></h3>
                    <p><?php _e('Great! No accounts with SMS gateway email addresses were found.', 'twintack-security'); ?></p>
                    <button type="button" class="button button-primary" id="refresh-scan">
                        <?php _e('Run Scan Again', 'twintack-security'); ?>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Export Options -->
    <div class="twintack-card">
        <div class="twintack-card-header">
            <h3><?php _e('Export Options', 'twintack-security'); ?></h3>
        </div>
        <div class="twintack-card-body">
            <p><?php _e('Before deleting accounts, you can export customer data for your records.', 'twintack-security'); ?></p>
            <div class="twintack-export-actions">
                <button type="button" class="button" id="export-spam-accounts">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Export Spam Account List', 'twintack-security'); ?>
                </button>
                <?php if (TwinTack_Security()->is_woocommerce_active()): ?>
                <button type="button" class="button" id="export-customer-data">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Export Customer Data', 'twintack-security'); ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Safety Information -->
    <div class="twintack-card twintack-safety-info">
        <div class="twintack-card-header">
            <h3><?php _e('Safety Information', 'twintack-security'); ?></h3>
        </div>
        <div class="twintack-card-body">
            <div class="twintack-safety-notes">
                <h4><?php _e('What Gets Deleted:', 'twintack-security'); ?></h4>
                <ul>
                    <li><?php _e('User account and all associated metadata', 'twintack-security'); ?></li>
                    <li><?php _e('Any empty shopping carts', 'twintack-security'); ?></li>
                    <li><?php _e('Unused form submissions', 'twintack-security'); ?></li>
                </ul>

                <h4><?php _e('What is Protected:', 'twintack-security'); ?></h4>
                <ul>
                    <li><?php _e('Any account that has completed orders', 'twintack-security'); ?></li>
                    <li><?php _e('Accounts with posts, pages, or comments', 'twintack-security'); ?></li>
                    <li><?php _e('Accounts with any form of user-generated content', 'twintack-security'); ?></li>
                    <li><?php _e('Administrator and editor accounts (regardless of email)', 'twintack-security'); ?></li>
                </ul>

                <h4><?php _e('Backup Recommendation:', 'twintack-security'); ?></h4>
                <p><?php _e('While this tool is designed to be safe, we recommend backing up your database before performing bulk deletions.', 'twintack-security'); ?></p>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Select all checkbox
    $('#select-all-spam').on('change', function() {
        $('.spam-account-checkbox').prop('checked', $(this).prop('checked'));
    });

    // Bulk delete spam accounts
    $('#bulk-delete-spam').on('click', function() {
        var selectedAccounts = $('.spam-account-checkbox:checked');
        
        if (selectedAccounts.length === 0) {
            alert('<?php _e('Please select accounts to delete.', 'twintack-security'); ?>');
            return;
        }

        if (!confirm('<?php _e('Are you sure you want to delete the selected spam accounts? This action cannot be undone.', 'twintack-security'); ?>')) {
            return;
        }

        var button = $(this);
        var originalText = button.text();
        button.prop('disabled', true).text('<?php _e('Deleting...', 'twintack-security'); ?>');

        var userIds = [];
        selectedAccounts.each(function() {
            userIds.push($(this).val());
        });

        $.post(ajaxurl, {
            action: 'twintack_security_bulk_delete_spam',
            nonce: '<?php echo wp_create_nonce('twintack_security_bulk_delete'); ?>',
            user_ids: userIds
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert(response.data || '<?php _e('Error deleting accounts.', 'twintack-security'); ?>');
                button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Delete single spam account
    $('.delete-single-spam').on('click', function() {
        var userId = $(this).data('user-id');
        var username = $(this).data('username');

        if (!confirm('<?php _e('Are you sure you want to delete the account for', 'twintack-security'); ?> "' + username + '"?')) {
            return;
        }

        var button = $(this);
        button.prop('disabled', true).text('<?php _e('Deleting...', 'twintack-security'); ?>');

        $.post(ajaxurl, {
            action: 'twintack_security_delete_single_spam',
            nonce: '<?php echo wp_create_nonce('twintack_security_delete_single'); ?>',
            user_id: userId
        }, function(response) {
            if (response.success) {
                button.closest('tr').fadeOut();
            } else {
                alert(response.data || '<?php _e('Error deleting account.', 'twintack-security'); ?>');
                button.prop('disabled', false).text('<?php _e('Delete', 'twintack-security'); ?>');
            }
        });
    });

    // Refresh scan
    $('#refresh-scan').on('click', function() {
        location.reload();
    });

    // Export spam accounts
    $('#export-spam-accounts').on('click', function() {
        var button = $(this);
        button.prop('disabled', true);

        $.post(ajaxurl, {
            action: 'twintack_security_export_spam_accounts',
            nonce: '<?php echo wp_create_nonce('twintack_security_export'); ?>'
        }, function(response) {
            if (response.success) {
                // Create download
                var blob = new Blob([response.data.csv_content], { type: 'text/csv' });
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = response.data.filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            } else {
                alert('<?php _e('Error exporting data.', 'twintack-security'); ?>');
            }
        }).always(function() {
            button.prop('disabled', false);
        });
    });
});
</script>
