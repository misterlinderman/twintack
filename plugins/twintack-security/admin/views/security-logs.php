<?php
/**
 * Security Logs Admin View
 *
 * @package TwinTack_Security
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap twintack-security-logs">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-list-view"></span>
        <?php _e('Security Logs', 'twintack-security'); ?>
    </h1>
    
    <div id="logs-page-status" style="display: none;"></div>

    <!-- Log Filters -->
    <div class="twintack-card twintack-full-width">
        <div class="twintack-card-header">
            <h3><?php _e('Filter Logs', 'twintack-security'); ?></h3>
            <div class="twintack-card-actions">
                <button type="button" class="button" id="export-logs">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Export Filtered Logs', 'twintack-security'); ?>
                </button>
                <button type="button" class="button" id="clear-old-logs">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e('Clear Old Logs', 'twintack-security'); ?>
                </button>
            </div>
        </div>
        <div class="twintack-card-body">
            <form id="log-filters" class="twintack-log-filters">
                <div class="twintack-filter-grid">
                    <div class="twintack-filter-group">
                        <label for="filter-event-type"><?php _e('Event Type:', 'twintack-security'); ?></label>
                        <select id="filter-event-type" name="event_type">
                            <option value=""><?php _e('All Events', 'twintack-security'); ?></option>
                            <option value="registration_blocked"><?php _e('Registration Blocked', 'twintack-security'); ?></option>
                            <option value="woo_registration_blocked"><?php _e('WooCommerce Registration Blocked', 'twintack-security'); ?></option>
                            <option value="login_failed"><?php _e('Login Failed', 'twintack-security'); ?></option>
                            <option value="comment_blocked"><?php _e('Comment Blocked', 'twintack-security'); ?></option>
                            <option value="domain_blocked"><?php _e('Domain Blocked', 'twintack-security'); ?></option>
                            <option value="spam_account_deleted"><?php _e('Spam Account Deleted', 'twintack-security'); ?></option>
                        </select>
                    </div>

                    <div class="twintack-filter-group">
                        <label for="filter-severity"><?php _e('Severity:', 'twintack-security'); ?></label>
                        <select id="filter-severity" name="severity">
                            <option value=""><?php _e('All Severities', 'twintack-security'); ?></option>
                            <option value="low"><?php _e('Low', 'twintack-security'); ?></option>
                            <option value="medium"><?php _e('Medium', 'twintack-security'); ?></option>
                            <option value="high"><?php _e('High', 'twintack-security'); ?></option>
                            <option value="critical"><?php _e('Critical', 'twintack-security'); ?></option>
                        </select>
                    </div>

                    <div class="twintack-filter-group">
                        <label for="filter-date-from"><?php _e('From Date:', 'twintack-security'); ?></label>
                        <input type="date" id="filter-date-from" name="date_from" />
                    </div>

                    <div class="twintack-filter-group">
                        <label for="filter-date-to"><?php _e('To Date:', 'twintack-security'); ?></label>
                        <input type="date" id="filter-date-to" name="date_to" />
                    </div>

                    <div class="twintack-filter-group twintack-filter-search">
                        <label for="filter-search"><?php _e('Search:', 'twintack-security'); ?></label>
                        <input type="text" id="filter-search" name="search" 
                               placeholder="<?php _e('Email, IP address, or details...', 'twintack-security'); ?>" />
                    </div>

                    <div class="twintack-filter-group twintack-filter-actions">
                        <button type="submit" class="button button-primary">
                            <?php _e('Filter Logs', 'twintack-security'); ?>
                        </button>
                        <button type="button" class="button" id="clear-filters">
                            <?php _e('Clear Filters', 'twintack-security'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs Display -->
    <div class="twintack-card twintack-full-width">
        <div class="twintack-card-header">
            <h3><?php _e('Security Events', 'twintack-security'); ?></h3>
            <div class="twintack-log-stats">
                <span id="logs-count-display"><?php _e('Loading...', 'twintack-security'); ?></span>
            </div>
        </div>
        <div class="twintack-card-body">
            <div id="logs-status-message" style="display: none;"></div>
            
            <div id="logs-loading" class="twintack-loading-indicator" style="display: none;">
                <span class="dashicons dashicons-update-alt spin"></span>
                <?php _e('Loading logs...', 'twintack-security'); ?>
            </div>
            
            <div id="logs-container">
                <!-- Logs will be loaded here via AJAX -->
            </div>
            
            <div id="logs-pagination" class="twintack-pagination">
                <!-- Pagination will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Log Statistics -->
    <div class="twintack-security-grid">
        <div class="twintack-card">
            <div class="twintack-card-header">
                <h3><?php _e('Event Statistics', 'twintack-security'); ?></h3>
            </div>
            <div class="twintack-card-body">
                <div class="twintack-stats-list" id="event-stats">
                    <!-- Statistics will be loaded here -->
                </div>
            </div>
        </div>

        <div class="twintack-card">
            <div class="twintack-card-header">
                <h3><?php _e('Severity Distribution', 'twintack-security'); ?></h3>
            </div>
            <div class="twintack-card-body">
                <div class="twintack-severity-chart" id="severity-chart">
                    <!-- Severity chart will be loaded here -->
                </div>
            </div>
        </div>

        <div class="twintack-card">
            <div class="twintack-card-header">
                <h3><?php _e('Top Blocked IPs', 'twintack-security'); ?></h3>
            </div>
            <div class="twintack-card-body">
                <div class="twintack-top-ips" id="top-ips">
                    <!-- Top IPs will be loaded here -->
                </div>
            </div>
        </div>

        <div class="twintack-card">
            <div class="twintack-card-header">
                <h3><?php _e('Recent Activity', 'twintack-security'); ?></h3>
            </div>
            <div class="twintack-card-body">
                <div class="twintack-recent-activity" id="recent-activity">
                    <!-- Recent activity will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Clear Old Logs Modal -->
<div id="clear-logs-modal" class="twintack-modal" style="display: none;">
    <div class="twintack-modal-content">
        <div class="twintack-modal-header">
            <h3><?php _e('Clear Old Logs', 'twintack-security'); ?></h3>
            <button type="button" class="twintack-modal-close">&times;</button>
        </div>
        <div class="twintack-modal-body">
            <p><?php _e('Select how old logs should be before deletion:', 'twintack-security'); ?></p>
            <form id="clear-logs-form">
                <div class="twintack-modal-options">
                    <label>
                        <input type="radio" name="clear_option" value="7" />
                        <?php _e('Older than 7 days', 'twintack-security'); ?>
                    </label>
                    <label>
                        <input type="radio" name="clear_option" value="30" checked />
                        <?php _e('Older than 30 days', 'twintack-security'); ?>
                    </label>
                    <label>
                        <input type="radio" name="clear_option" value="90" />
                        <?php _e('Older than 90 days', 'twintack-security'); ?>
                    </label>
                    <label>
                        <input type="radio" name="clear_option" value="0" />
                        <?php _e('All logs (WARNING: Cannot be undone)', 'twintack-security'); ?>
                    </label>
                </div>
            </form>
        </div>
        <div class="twintack-modal-footer">
            <button type="button" class="button button-primary" id="confirm-clear-logs">
                <?php _e('Clear Logs', 'twintack-security'); ?>
            </button>
            <button type="button" class="button twintack-modal-close">
                <?php _e('Cancel', 'twintack-security'); ?>
            </button>
        </div>
    </div>
</div>

<style>
/* Log viewer specific styles */
.twintack-log-filters {
    margin: 0;
}

.twintack-filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    align-items: end;
}

.twintack-filter-search {
    grid-column: span 2;
}

.twintack-filter-actions {
    display: flex;
    gap: 10px;
}

.twintack-filter-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.twintack-filter-group input,
.twintack-filter-group select {
    width: 100%;
}

.twintack-loading-indicator {
    text-align: center;
    padding: 20px;
    color: #666;
}

.twintack-loading-indicator .dashicons {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.twintack-severity-low { color: #28a745; }
.twintack-severity-medium { color: #ffc107; }
.twintack-severity-high { color: #fd7e14; }
.twintack-severity-critical { color: #dc3545; }

.twintack-log-details {
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.twintack-log-details details {
    cursor: pointer;
}

.twintack-log-details pre {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
    font-size: 11px;
    max-height: 200px;
    overflow-y: auto;
}

.twintack-pagination {
    text-align: center;
    margin-top: 20px;
}

.twintack-pagination a,
.twintack-pagination span {
    display: inline-block;
    padding: 8px 12px;
    margin: 0 2px;
    border: 1px solid #ddd;
    text-decoration: none;
}

.twintack-pagination .current {
    background: #007cba;
    color: white;
    border-color: #007cba;
}

/* Modal styles */
.twintack-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.twintack-modal-content {
    background: white;
    max-width: 500px;
    width: 90%;
    border-radius: 4px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.twintack-modal-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.twintack-modal-header h3 {
    margin: 0;
}

.twintack-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.twintack-modal-body {
    padding: 20px;
}

.twintack-modal-options {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.twintack-modal-options label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.twintack-modal-footer {
    padding: 20px;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var currentPage = 1;
    var currentFilters = {};

    // Clear any existing error messages
    $('.notice-error').hide();
    
    // Initialize log viewer
    loadLogs();
    loadLogStats();

    // Filter form submission
    $('#log-filters').on('submit', function(e) {
        e.preventDefault();
        currentPage = 1;
        loadLogs();
    });

    // Clear filters
    $('#clear-filters').on('click', function() {
        $('#log-filters')[0].reset();
        currentPage = 1;
        loadLogs();
    });

    // Export logs
    $('#export-logs').on('click', function() {
        var filters = getFilters();
        filters.action = 'twintack_security_export_logs';
        filters.nonce = '<?php echo wp_create_nonce('twintack_security_export'); ?>';

        var button = $(this);
        button.prop('disabled', true);

        $.post(ajaxurl, filters, function(response) {
            if (response.success) {
                downloadCSV(response.data.csv_content, response.data.filename);
            } else {
                alert('<?php _e('Error exporting logs.', 'twintack-security'); ?>');
            }
        }).always(function() {
            button.prop('disabled', false);
        });
    });

    // Clear old logs
    $('#clear-old-logs').on('click', function() {
        $('#clear-logs-modal').show();
    });

    // Modal close
    $('.twintack-modal-close').on('click', function() {
        $('#clear-logs-modal').hide();
    });

    // Confirm clear logs
    $('#confirm-clear-logs').on('click', function() {
        var days = $('input[name="clear_option"]:checked').val();
        
        if (days === '0' && !confirm('<?php _e('Are you sure you want to delete ALL logs? This cannot be undone.', 'twintack-security'); ?>')) {
            return;
        }

        var button = $(this);
        button.prop('disabled', true).text('<?php _e('Clearing...', 'twintack-security'); ?>');

        $.post(ajaxurl, {
            action: 'twintack_security_clear_logs',
            nonce: '<?php echo wp_create_nonce('twintack_security_clear_logs'); ?>',
            older_than_days: days
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                $('#clear-logs-modal').hide();
                loadLogs();
                loadLogStats();
            } else {
                alert(response.data || '<?php _e('Error clearing logs.', 'twintack-security'); ?>');
            }
        }).always(function() {
            button.prop('disabled', false).text('<?php _e('Clear Logs', 'twintack-security'); ?>');
        });
    });

    // Pagination
    $(document).on('click', '.twintack-pagination a', function(e) {
        e.preventDefault();
        currentPage = parseInt($(this).data('page'));
        loadLogs();
    });

    function getFilters() {
        return {
            event_type: $('#filter-event-type').val(),
            severity: $('#filter-severity').val(),
            date_from: $('#filter-date-from').val(),
            date_to: $('#filter-date-to').val(),
            search: $('#filter-search').val()
        };
    }

    function loadLogs() {
        $('#logs-loading').show();
        $('#logs-container').html('');
        $('#logs-status-message').hide();

        var filters = getFilters();
        filters.action = 'twintack_security_get_logs';
        filters.nonce = '<?php echo wp_create_nonce('twintack_security_logs'); ?>';
        filters.page = currentPage;
        filters.limit = 50;

        currentFilters = filters;

        $.post(ajaxurl, filters, function(response) {
            if (response.success) {
                // Clear any existing error messages on the page
                $('.notice-error').hide();
                $('#logs-status-message').hide();
                displayLogs(response.data);
            } else {
                console.log('AJAX Error:', response);
                showStatusMessage('error', response.data || '<?php _e('Error loading logs.', 'twintack-security'); ?>');
            }
        }).fail(function(xhr, status, error) {
            console.log('AJAX Failed:', xhr, status, error);
            showStatusMessage('error', 'Failed to load logs. Please check browser console for details.');
        }).always(function() {
            $('#logs-loading').hide();
        });
    }

    function displayLogs(data) {
        var html = '';
        
        if (data.logs.length === 0) {
            html = '<p class="twintack-no-logs"><?php _e('No logs found matching your criteria.', 'twintack-security'); ?></p>';
        } else {
            html += '<table class="wp-list-table widefat fixed striped">';
            html += '<thead><tr>';
            html += '<th><?php _e('Time', 'twintack-security'); ?></th>';
            html += '<th><?php _e('Event', 'twintack-security'); ?></th>';
            html += '<th><?php _e('IP Address', 'twintack-security'); ?></th>';
            html += '<th><?php _e('Email', 'twintack-security'); ?></th>';
            html += '<th><?php _e('Severity', 'twintack-security'); ?></th>';
            html += '<th><?php _e('Details', 'twintack-security'); ?></th>';
            html += '</tr></thead><tbody>';

            data.logs.forEach(function(log) {
                html += '<tr>';
                html += '<td>' + log.formatted_timestamp + '</td>';
                html += '<td>' + log.event_type.replace(/_/g, ' ') + '</td>';
                html += '<td><code>' + log.ip_address + '</code></td>';
                html += '<td>' + (log.user_email || '-') + '</td>';
                html += '<td><span class="twintack-severity-' + log.severity + '">' + log.severity + '</span></td>';
                html += '<td class="twintack-log-details">';
                if (log.details) {
                    html += '<details><summary><?php _e('View', 'twintack-security'); ?></summary>';
                    html += '<pre>' + JSON.stringify(log.details, null, 2) + '</pre></details>';
                } else {
                    html += '-';
                }
                html += '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
        }

        $('#logs-container').html(html);
        $('#logs-count-display').text(data.total_count + ' <?php _e('total events', 'twintack-security'); ?>');

        // Update pagination
        updatePagination(data.current_page, data.total_pages);
    }

    function updatePagination(currentPage, totalPages) {
        var html = '';
        
        if (totalPages > 1) {
            for (var i = 1; i <= totalPages; i++) {
                if (i === currentPage) {
                    html += '<span class="current">' + i + '</span>';
                } else {
                    html += '<a href="#" data-page="' + i + '">' + i + '</a>';
                }
            }
        }
        
        $('#logs-pagination').html(html);
    }

    function loadLogStats() {
        $.post(ajaxurl, {
            action: 'twintack_security_get_log_stats',
            nonce: '<?php echo wp_create_nonce('twintack_security_stats'); ?>'
        }, function(response) {
            if (response.success) {
                displayLogStats(response.data);
            }
        });
    }

    function displayLogStats(stats) {
        // Update event statistics
        var eventStatsHtml = '';
        if (stats.top_event_types) {
            stats.top_event_types.forEach(function(event) {
                eventStatsHtml += '<div class="twintack-stat-item">';
                eventStatsHtml += '<span class="twintack-stat-label">' + event.type.replace(/_/g, ' ') + '</span>';
                eventStatsHtml += '<span class="twintack-stat-count">' + event.count + '</span>';
                eventStatsHtml += '</div>';
            });
        }
        $('#event-stats').html(eventStatsHtml);

        // Update severity chart (simplified)
        var severityHtml = '';
        if (stats.severity_breakdown) {
            for (var severity in stats.severity_breakdown) {
                severityHtml += '<div class="twintack-severity-item">';
                severityHtml += '<span class="twintack-severity-' + severity + '">' + severity + '</span>';
                severityHtml += '<span class="twintack-severity-count">' + stats.severity_breakdown[severity] + '</span>';
                severityHtml += '</div>';
            }
        }
        $('#severity-chart').html(severityHtml);

        // Update top IPs
        var topIpsHtml = '';
        if (stats.top_blocked_ips) {
            stats.top_blocked_ips.forEach(function(ip) {
                topIpsHtml += '<div class="twintack-ip-item">';
                topIpsHtml += '<code>' + ip.ip + '</code>';
                topIpsHtml += '<span class="twintack-ip-count">' + ip.count + '</span>';
                topIpsHtml += '</div>';
            });
        }
        $('#top-ips').html(topIpsHtml);
    }

    function downloadCSV(content, filename) {
        var blob = new Blob([content], { type: 'text/csv' });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    function showStatusMessage(type, message) {
        var $statusDiv = $('#logs-status-message');
        $statusDiv.removeClass('notice-error notice-success notice-info')
                  .addClass('notice notice-' + type)
                  .html('<p>' + message + '</p>')
                  .show();
    }
});
</script>
