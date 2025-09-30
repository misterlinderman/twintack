/**
 * TwinTack Bulk Operations JavaScript
 * 
 * Handles bulk operations for invoice management and CSV exports
 * 
 * @package TwinTack_Manual_Order_Payments
 * @since 4.2.0
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Ensure we have the global object
    if (typeof twintackBulk === 'undefined') {
        console.error('TwinTack Bulk: Configuration object not found');
        return;
    }
    
    console.log('TwinTack Bulk Operations loaded');
    
    /**
     * Bulk mark invoiced orders as paid
     */
    $('#bulk-mark-invoiced-paid').on('click', function() {
        if (!confirm(twintackBulk.messages.confirm_mark_paid)) {
            return;
        }
        
        var button = $(this);
        var originalText = button.text();
        
        button.prop('disabled', true).text(twintackBulk.messages.processing);
        
        // Get all invoiced order IDs via AJAX first
        $.post(twintackBulk.ajax_url, {
            action: 'twintack_get_invoiced_orders',
            nonce: twintackBulk.nonce
        })
        .done(function(response) {
            if (response.success && response.data.order_ids.length > 0) {
                // Now bulk mark them as paid
                $.post(twintackBulk.ajax_url, {
                    action: 'twintack_bulk_mark_paid',
                    order_ids: response.data.order_ids,
                    nonce: twintackBulk.nonce
                })
                .done(function(response) {
                    if (response.success) {
                        showMessage(response.data.message, 'success');
                        // Refresh stats after delay
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        showMessage(response.data.message || twintackBulk.messages.error, 'error');
                    }
                })
                .fail(function() {
                    showMessage(twintackBulk.messages.error, 'error');
                })
                .always(function() {
                    button.prop('disabled', false).text(originalText);
                });
            } else {
                showMessage('No invoiced orders found to process.', 'warning');
                button.prop('disabled', false).text(originalText);
            }
        })
        .fail(function() {
            showMessage(twintackBulk.messages.error, 'error');
            button.prop('disabled', false).text(originalText);
        });
    });
    
    /**
     * Export invoiced orders
     */
    $('#export-invoiced-orders').on('click', function() {
        if (!confirm(twintackBulk.messages.confirm_export)) {
            return;
        }
        
        var button = $(this);
        var originalText = button.text();
        
        button.prop('disabled', true).text(twintackBulk.messages.processing);
        
        // Generate download URL and trigger download
        var exportUrl = twintackBulk.ajax_url.replace('admin-ajax.php', 'admin.php') + 
                       '?twintack_export=invoiced&nonce=' + twintackBulk.export_nonce;
        
        // Create hidden download link and click it
        var downloadLink = $('<a>').attr({
            href: exportUrl,
            download: 'invoiced_orders_' + new Date().toISOString().slice(0, 10) + '.csv'
        }).appendTo('body');
        
        downloadLink[0].click();
        downloadLink.remove();
        
        showMessage('CSV export started. Download should begin shortly.', 'success');
        
        setTimeout(function() {
            button.prop('disabled', false).text(originalText);
        }, 1000);
    });
    
    /**
     * Export all orders
     */
    $('#export-all-orders').on('click', function() {
        if (!confirm(twintackBulk.messages.confirm_export)) {
            return;
        }
        
        var button = $(this);
        var originalText = button.text();
        
        button.prop('disabled', true).text(twintackBulk.messages.processing);
        
        // Generate download URL and trigger download
        var exportUrl = twintackBulk.ajax_url.replace('admin-ajax.php', 'admin.php') + 
                       '?twintack_export=all&nonce=' + twintackBulk.export_nonce;
        
        // Create hidden download link and click it
        var downloadLink = $('<a>').attr({
            href: exportUrl,
            download: 'all_orders_' + new Date().toISOString().slice(0, 10) + '.csv'
        }).appendTo('body');
        
        downloadLink[0].click();
        downloadLink.remove();
        
        showMessage('CSV export started. Download should begin shortly.', 'success');
        
        setTimeout(function() {
            button.prop('disabled', false).text(originalText);
        }, 1000);
    });
    
    /**
     * Enhanced export buttons on orders list page
     */
    $('#twintack-export-invoices').on('click', function() {
        triggerExport('invoiced', $(this));
    });
    
    $('#twintack-export-all-orders').on('click', function() {
        triggerExport('all', $(this));
    });
    
    /**
     * Helper function to trigger exports
     */
    function triggerExport(type, button) {
        var originalText = button.text();
        button.prop('disabled', true).text('Exporting...');
        
        var exportUrl = twintackBulk.ajax_url.replace('admin-ajax.php', 'admin.php') + 
                       '?twintack_export=' + type + '&nonce=' + twintackBulk.export_nonce;
        
        // Open in new window to trigger download
        window.open(exportUrl, '_blank');
        
        setTimeout(function() {
            button.prop('disabled', false).text(originalText);
        }, 1000);
    }
    
    /**
     * Show message to user
     */
    function showMessage(message, type) {
        var messageClass = 'notice-info';
        var icon = 'ℹ️';
        
        switch (type) {
            case 'success':
                messageClass = 'notice-success';
                icon = '✅';
                break;
            case 'error':
                messageClass = 'notice-error';
                icon = '❌';
                break;
            case 'warning':
                messageClass = 'notice-warning';
                icon = '⚠️';
                break;
        }
        
        var messageHtml = '<div class="notice ' + messageClass + ' is-dismissible" style="padding: 10px; margin: 10px 0; border-radius: 3px;">' +
                         '<p>' + icon + ' ' + message + '</p>' +
                         '</div>';
        
        $('#twintack-bulk-messages').html(messageHtml);
        
        // Auto-dismiss success messages after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                $('#twintack-bulk-messages .notice').fadeOut();
            }, 5000);
        }
    }
    
    /**
     * Make bulk action dropdowns more prominent
     */
    function enhanceBulkActions() {
        // Find bulk action selects and enhance them
        $('select[name="action"], select[name="action2"]').each(function() {
            var select = $(this);
            var twintackOptions = select.find('option[value^="twintack_"]');
            
            if (twintackOptions.length > 0) {
                // Add separator before TwinTack options
                twintackOptions.first().before('<option disabled>────────────</option>');
                
                // Style TwinTack options
                twintackOptions.css({
                    'background-color': '#e3f2fd',
                    'font-weight': 'bold'
                });
            }
        });
        
        // Add helpful text near bulk actions
        $('.tablenav-pages').before(
            '<div class="twintack-bulk-help" style="background: #f0f8ff; padding: 10px; margin: 10px 0; border: 1px solid #0073aa; border-radius: 3px; font-size: 12px;">' +
            '<strong>💡 TwinTack Tip:</strong> Select multiple orders and use "Mark as Paid (TwinTack)" or "Export Selected (CSV)" from the bulk actions dropdown.' +
            '</div>'
        );
    }
    
    /**
     * Initialize enhancements
     */
    function init() {
        // Check if we're on orders page using more specific selectors
        var isOrdersPage = false;
        
        // Check for WooCommerce orders page (HPOS)
        if ($('.woocommerce-orders-list').length) {
            isOrdersPage = true;
        }
        
        // Check for traditional orders page (posts-filter with shop_order post type)
        if ($('#posts-filter').length && $('input[name="post_type"]').val() === 'shop_order') {
            isOrdersPage = true;
        }
        
        // Check for bulk actions specific to orders
        if ($('select[name="action"] option[value*="twintack_"]').length) {
            isOrdersPage = true;
        }
        
        if (isOrdersPage) {
            enhanceBulkActions();
        }
        
        console.log('TwinTack Bulk Operations initialized', { isOrdersPage: isOrdersPage });
    }
    
    // Initialize when page loads
    init();
    
    // Also initialize when WooCommerce loads content dynamically
    $(document).on('wc_backbone_modal_loaded', init);
});
