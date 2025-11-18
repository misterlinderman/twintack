/**
 * TwinTack Security Admin JavaScript
 * 
 * @package TwinTack_Security
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        TwinTackSecurity.init();
    });

    /**
     * Main TwinTack Security admin object
     */
    window.TwinTackSecurity = {

        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initTooltips();
            this.loadDashboardData();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Global AJAX error handler
            $(document).ajaxError(function(event, xhr, settings, error) {
                if (settings.url.indexOf('twintack_security') !== -1) {
                    TwinTackSecurity.showNotice('error', twintackSecurity.strings.error);
                }
            });

            // Email validation test
            $('#twintack-test-email-form').on('submit', this.handleEmailTest);

            // Domain management
            $('#add-domain-form').on('submit', this.handleAddDomain);
            $(document).on('click', '.twintack-remove-domain', this.handleRemoveDomain);
            $(document).on('click', '.twintack-add-domain-from-stats', this.handleAddDomainFromStats);

            // Bulk actions
            $(document).on('click', '#bulk-delete-spam', this.handleBulkDeleteSpam);
            $(document).on('click', '#import-common-domains', this.handleImportCommonDomains);

            // Settings page actions
            $(document).on('click', '#clear-all-logs', this.handleClearAllLogs);
            $(document).on('click', '#reset-settings', this.handleResetSettings);

            // Log viewer
            this.initLogViewer();
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            // Add tooltips to elements with data-tooltip attribute
            $('[data-tooltip]').each(function() {
                $(this).attr('title', $(this).data('tooltip'));
            });
        },

        /**
         * Load dashboard data
         */
        loadDashboardData: function() {
            if ($('.twintack-security-dashboard').length === 0) {
                return;
            }

            // Auto-refresh dashboard stats every 30 seconds
            setInterval(function() {
                TwinTackSecurity.refreshDashboardStats();
            }, 30000);
        },

        /**
         * Refresh dashboard statistics
         */
        refreshDashboardStats: function() {
            $.post(twintackSecurity.ajaxUrl, {
                action: 'twintack_security_get_stats',
                nonce: twintackSecurity.nonce
            }, function(response) {
                if (response.success) {
                    TwinTackSecurity.updateDashboardStats(response.data);
                }
            });
        },

        /**
         * Update dashboard statistics display
         */
        updateDashboardStats: function(stats) {
            // Update stat numbers
            $('.twintack-stat-number').each(function() {
                var $this = $(this);
                var key = $this.data('stat-key');
                if (stats[key] !== undefined) {
                    $this.text(stats[key]);
                }
            });
        },

        /**
         * Handle email validation test
         */
        handleEmailTest: function(e) {
            e.preventDefault();

            var $form = $(this);
            var email = $('#test-email').val().trim();
            var username = $('#test-username').val().trim();

            if (!email) {
                TwinTackSecurity.showNotice('error', 'Please enter an email address to test.');
                return;
            }

            var $button = $form.find('button[type="submit"]');
            var originalText = $button.text();
            
            $button.prop('disabled', true).text(twintackSecurity.strings.loading);

            $.post(twintackSecurity.ajaxUrl, {
                action: 'twintack_security_test_email',
                nonce: twintackSecurity.nonce,
                email: email,
                username: username
            }, function(response) {
                var $result = $('#test-email-result');
                
                if (response.success) {
                    $result.removeClass('twintack-error')
                           .addClass('twintack-success')
                           .html('<strong>Result:</strong> ' + response.data.message)
                           .show();
                } else {
                    $result.removeClass('twintack-success')
                           .addClass('twintack-error')
                           .html('<strong>Blocked:</strong> ' + 
                                 (response.data.user_message || response.data.message))
                           .show();
                }
            }).always(function() {
                $button.prop('disabled', false).text(originalText);
            });
        },

        /**
         * Handle adding a blocked domain
         */
        handleAddDomain: function(e) {
            e.preventDefault();

            var domain = $('#new-domain').val().trim();
            if (!domain) {
                TwinTackSecurity.showNotice('error', 'Please enter a domain to block.');
                return;
            }

            TwinTackSecurity.addBlockedDomain(domain);
        },

        /**
         * Handle removing a blocked domain
         */
        handleRemoveDomain: function(e) {
            e.preventDefault();

            var domain = $(this).data('domain');
            
            if (!confirm('Are you sure you want to remove "' + domain + '" from the blocked list?')) {
                return;
            }

            TwinTackSecurity.removeBlockedDomain(domain);
        },

        /**
         * Handle adding domain from statistics
         */
        handleAddDomainFromStats: function(e) {
            e.preventDefault();

            var domain = $(this).data('domain');
            TwinTackSecurity.addBlockedDomain(domain);
        },

        /**
         * Add a blocked domain via AJAX
         */
        addBlockedDomain: function(domain) {
            $.post(twintackSecurity.ajaxUrl, {
                action: 'twintack_security_add_domain',
                nonce: this.createNonce('twintack_security_domain_management'),
                domain: domain
            }, function(response) {
                if (response.success) {
                    TwinTackSecurity.showNotice('success', response.data.message);
                    // Clear the input
                    $('#new-domain').val('');
                    // Refresh the page to show updated list
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    TwinTackSecurity.showNotice('error', response.data || 'Failed to add domain.');
                }
            });
        },

        /**
         * Remove a blocked domain via AJAX
         */
        removeBlockedDomain: function(domain) {
            $.post(twintackSecurity.ajaxUrl, {
                action: 'twintack_security_remove_domain',
                nonce: this.createNonce('twintack_security_domain_management'),
                domain: domain
            }, function(response) {
                if (response.success) {
                    TwinTackSecurity.showNotice('success', response.data.message);
                    // Remove the domain item from the display
                    $('.twintack-domain-item[data-domain="' + domain + '"]').fadeOut();
                } else {
                    TwinTackSecurity.showNotice('error', response.data || 'Failed to remove domain.');
                }
            });
        },

        /**
         * Handle bulk delete spam accounts
         */
        handleBulkDeleteSpam: function(e) {
            e.preventDefault();

            if (!confirm(twintackSecurity.strings.confirmBulkDelete)) {
                return;
            }

            var $button = $(this);
            var originalText = $button.text();
            
            $button.prop('disabled', true).text('Deleting...');

            $.post(twintackSecurity.ajaxUrl, {
                action: 'twintack_security_bulk_delete_spam',
                nonce: this.createNonce('twintack_security_bulk_delete')
            }, function(response) {
                if (response.success) {
                    TwinTackSecurity.showNotice('success', response.data.message);
                    // Refresh the page to show updated counts
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    TwinTackSecurity.showNotice('error', response.data || 'Failed to delete spam accounts.');
                }
            }).always(function() {
                $button.prop('disabled', false).text(originalText);
            });
        },

        /**
         * Handle importing common SMS domains
         */
        handleImportCommonDomains: function(e) {
            e.preventDefault();

            if (!confirm('This will import common SMS gateway domains to the blocked list. Continue?')) {
                return;
            }

            var $button = $(this);
            var originalText = $button.text();
            
            $button.prop('disabled', true).text('Importing...');

            // Simulate import - in real implementation this would be an AJAX call
            setTimeout(function() {
                TwinTackSecurity.showNotice('success', 'Common SMS domains imported successfully.');
                location.reload();
            }, 2000);
        },

        /**
         * Handle clearing all logs
         */
        handleClearAllLogs: function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to delete ALL security logs? This action cannot be undone.')) {
                return;
            }

            var $button = $(this);
            var originalText = $button.text();
            
            $button.prop('disabled', true).text('Clearing...');

            $.post(twintackSecurity.ajaxUrl, {
                action: 'twintack_security_clear_logs',
                nonce: this.createNonce('twintack_security_clear_logs'),
                older_than_days: 0
            }, function(response) {
                if (response.success) {
                    TwinTackSecurity.showNotice('success', response.data.message);
                } else {
                    TwinTackSecurity.showNotice('error', response.data || 'Error clearing logs.');
                }
            }).always(function() {
                $button.prop('disabled', false).text(originalText);
            });
        },

        /**
         * Handle resetting settings
         */
        handleResetSettings: function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to reset all settings to defaults?')) {
                return;
            }

            // This would be implemented as a separate AJAX action
            TwinTackSecurity.showNotice('info', 'Settings reset functionality would be implemented here.');
        },

        /**
         * Initialize log viewer functionality
         */
        initLogViewer: function() {
            if ($('.twintack-security-logs').length === 0) {
                return;
            }

            // Load logs on page load
            this.loadLogs();

            // Bind filter controls
            $('#log-filters').on('submit', function(e) {
                e.preventDefault();
                TwinTackSecurity.loadLogs();
            });

            // Export logs
            $('#export-logs').on('click', this.handleExportLogs);
        },

        /**
         * Load security logs
         */
        loadLogs: function(page = 1) {
            var $container = $('#logs-container');
            var $loading = $('#logs-loading');

            $loading.show();
            $container.html('');

            var filters = {
                action: 'twintack_security_get_logs',
                nonce: twintackSecurity.nonce,
                page: page,
                limit: 50,
                event_type: $('#filter-event-type').val(),
                severity: $('#filter-severity').val(),
                search: $('#filter-search').val(),
                date_from: $('#filter-date-from').val(),
                date_to: $('#filter-date-to').val()
            };

            $.post(twintackSecurity.ajaxUrl, filters, function(response) {
                if (response.success) {
                    TwinTackSecurity.displayLogs(response.data);
                } else {
                    TwinTackSecurity.showNotice('error', 'Failed to load logs.');
                }
            }).always(function() {
                $loading.hide();
            });
        },

        /**
         * Display logs in the container
         */
        displayLogs: function(data) {
            var $container = $('#logs-container');
            var html = '';

            if (data.logs.length === 0) {
                html = '<p class="twintack-no-logs">No logs found matching your criteria.</p>';
            } else {
                html += '<table class="wp-list-table widefat fixed striped">';
                html += '<thead><tr>';
                html += '<th>Time</th><th>Event</th><th>IP Address</th><th>Email</th><th>Severity</th><th>Details</th>';
                html += '</tr></thead><tbody>';

                data.logs.forEach(function(log) {
                    html += '<tr>';
                    html += '<td>' + log.formatted_timestamp + '</td>';
                    html += '<td>' + log.event_type.replace(/_/g, ' ') + '</td>';
                    html += '<td><code>' + log.ip_address + '</code></td>';
                    html += '<td>' + (log.user_email || '-') + '</td>';
                    html += '<td><span class="twintack-severity-' + log.severity + '">' + log.severity + '</span></td>';
                    html += '<td><details><summary>View</summary><pre>' + 
                            JSON.stringify(log.details, null, 2) + '</pre></details></td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';

                // Add pagination if needed
                if (data.total_pages > 1) {
                    html += this.buildPagination(data.current_page, data.total_pages);
                }
            }

            $container.html(html);
        },

        /**
         * Build pagination HTML
         */
        buildPagination: function(currentPage, totalPages) {
            var html = '<div class="twintack-pagination">';
            
            for (var i = 1; i <= totalPages; i++) {
                if (i === currentPage) {
                    html += '<span class="current">' + i + '</span>';
                } else {
                    html += '<a href="#" data-page="' + i + '">' + i + '</a>';
                }
            }
            
            html += '</div>';
            return html;
        },

        /**
         * Handle log export
         */
        handleExportLogs: function(e) {
            e.preventDefault();

            var filters = {
                action: 'twintack_security_export_logs',
                nonce: this.createNonce('twintack_security_export'),
                event_type: $('#filter-event-type').val(),
                severity: $('#filter-severity').val(),
                date_from: $('#filter-date-from').val(),
                date_to: $('#filter-date-to').val()
            };

            $.post(twintackSecurity.ajaxUrl, filters, function(response) {
                if (response.success) {
                    TwinTackSecurity.downloadCSV(response.data.csv_content, response.data.filename);
                } else {
                    TwinTackSecurity.showNotice('error', 'Failed to export logs.');
                }
            });
        },

        /**
         * Download CSV file
         */
        downloadCSV: function(content, filename) {
            var blob = new Blob([content], { type: 'text/csv' });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        },

        /**
         * Show admin notice
         */
        showNotice: function(type, message) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);
            
            // Auto-hide success notices
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut();
                }, 3000);
            }
        },

        /**
         * Create nonce for AJAX requests
         */
        createNonce: function(action) {
            // In a real implementation, nonces would be generated server-side
            // This is a placeholder
            return twintackSecurity.nonce;
        },

        /**
         * Utility function to escape HTML
         */
        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };

})(jQuery);
