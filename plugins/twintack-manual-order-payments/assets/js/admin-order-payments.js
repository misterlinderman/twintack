/**
 * Admin Order Payments JavaScript
 * 
 * Handles payment processing for manually created orders
 * 
 * @package TwinTack_Manual_Order_Payments
 */

jQuery(document).ready(function($) {
    
    // Handle mark as paid button
    $('#mark-order-paid').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm(twintackAdminPayments.messages.confirm_mark_paid)) {
            return;
        }
        
        var paymentMethod = $('#payment_method_select').val();
        var orderId = $(this).data('order-id');
        
        processPayment(orderId, 'mark_paid', paymentMethod);
    });
    
    // Handle process payment button
    $('#process-payment').on('click', function(e) {
        e.preventDefault();
        
        var paymentMethod = $('#payment_method_select').val();
        var orderId = $(this).data('order-id');
        
        if (!paymentMethod) {
            alert(twintackAdminPayments.messages.select_payment_method);
            return;
        }
        
        processPayment(orderId, 'process_payment', paymentMethod);
    });
    
    /**
     * Process payment via AJAX
     */
    function processPayment(orderId, actionType, paymentMethod) {
        // Show processing message
        showMessage(twintackAdminPayments.messages.processing, 'info');
        
        // Disable buttons
        $('#mark-order-paid, #process-payment').prop('disabled', true);
        
        $.ajax({
            url: twintackAdminPayments.ajax_url,
            type: 'POST',
            data: {
                action: 'process_manual_payment',
                order_id: orderId,
                action_type: actionType,
                payment_method: paymentMethod,
                nonce: twintackAdminPayments.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    
                    // Reload page after successful payment to reflect changes
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showMessage(twintackAdminPayments.messages.error + ' ' + response.data.message, 'error');
                    // Re-enable buttons on error
                    $('#mark-order-paid, #process-payment').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                showMessage(twintackAdminPayments.messages.error + ' AJAX request failed.', 'error');
                console.error('AJAX Error:', status, error);
                
                // Re-enable buttons on error
                $('#mark-order-paid, #process-payment').prop('disabled', false);
            }
        });
    }
    
    /**
     * Show message to user
     */
    function showMessage(message, type) {
        var messageClass = 'notice notice-' + (type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info'));
        var messageHtml = '<div class="' + messageClass + ' is-dismissible"><p>' + message + '</p></div>';
        
        // Remove existing messages
        $('#payment-processing-messages .notice').remove();
        
        // Add new message
        $('#payment-processing-messages').html(messageHtml);
        
        // Auto-dismiss after 5 seconds for success/info messages
        if (type !== 'error') {
            setTimeout(function() {
                $('#payment-processing-messages .notice').fadeOut();
            }, 5000);
        }
    }
    
    // Update payment method when selection changes
    $('#payment_method_select').on('change', function() {
        var selectedMethod = $(this).val();
        var selectedText = $(this).find('option:selected').text();
        
        if (selectedMethod) {
            showMessage('Payment method selected: ' + selectedText, 'info');
        }
    });
    
    // Add some styling for better visual feedback
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .twintack-payment-processing {
                border-radius: 4px;
            }
            
            .twintack-payment-processing h4 {
                margin-top: 0;
                color: #23282d;
            }
            
            .twintack-payment-processing button:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }
            
            #payment-processing-messages {
                margin-top: 15px;
            }
            
            #payment-processing-messages .notice {
                margin: 0;
                padding: 10px;
            }
            
            #payment_method_select {
                padding: 5px;
                border: 1px solid #ddd;
                border-radius: 3px;
            }
        `)
        .appendTo('head');
        
    // Initialize: check if there's already a payment method set
    var currentMethod = $('#payment_method_select').val();
    if (currentMethod) {
        var methodText = $('#payment_method_select option:selected').text();
        showMessage('Current payment method: ' + methodText, 'info');
    }
}); 