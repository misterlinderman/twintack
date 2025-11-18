/**
 * Admin Order Payments JavaScript
 * 
 * Handles payment processing for manually created orders
 * 
 * @package TwinTack_Manual_Order_Payments
 */

jQuery(document).ready(function($) {
    
    // Handle Pay Now button (Mark as Paid)
    $('#twintack-pay-now').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Mark this order as paid now? This will set the order to Processing status and make it ready for fulfillment.')) {
            return;
        }
        
        var paymentMethod = $('#twintack_payment_method_select').val();
        var orderId = $(this).data('order-id');
        
        processPayment(orderId, 'mark_paid', paymentMethod);
    });
    
    // Handle Pay Later button (Invoice)
    $('#twintack-pay-later').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Set this order to Pay Later (Invoice)? The customer will receive an invoice with a payment link via email.')) {
            return;
        }
        
        var orderId = $(this).data('order-id');
        
        processPayment(orderId, 'set_pay_later', '');
    });
    
    // Handle Mark as Paid button (legacy support)
    $('#twintack-mark-paid').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm(twintackAdminPayments.messages.confirm_mark_paid)) {
            return;
        }
        
        var paymentMethod = $('#twintack_payment_method_select').val();
        var orderId = $(this).data('order-id');
        
        processPayment(orderId, 'mark_paid', paymentMethod);
    });
    
    // Handle Process Payment via Gateway button
    $('#twintack-process-payment').on('click', function(e) {
        e.preventDefault();
        
        var paymentMethod = $('#twintack_payment_method_select').val();
        var orderId = $(this).data('order-id');
        
        if (!paymentMethod) {
            alert(twintackAdminPayments.messages.select_payment_method);
            return;
        }
        
        processPayment(orderId, 'process_payment', paymentMethod);
    });
    
    // Handle Send Payment Link to Customer button
    $('#twintack-send-payment-link').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm(twintackAdminPayments.messages.confirm_send_link)) {
            return;
        }
        
        var paymentMethod = $('#twintack_payment_method_select').val();
        var orderId = $(this).data('order-id');
        
        processPayment(orderId, 'send_payment_link', paymentMethod);
    });
    
    /**
     * Process payment via AJAX
     */
    function processPayment(orderId, actionType, paymentMethod) {
        // Show processing message
        showMessage(twintackAdminPayments.messages.processing, 'info');
        
        // Disable all buttons
        $('#twintack-pay-now, #twintack-pay-later, #twintack-mark-paid, #twintack-process-payment, #twintack-send-payment-link').prop('disabled', true);
        
        // Determine the AJAX action based on type
        var ajaxAction = 'twintack_' + actionType;
        
        $.ajax({
            url: twintackAdminPayments.ajax_url,
            type: 'POST',
            data: {
                action: ajaxAction,
                order_id: orderId,
                payment_method: paymentMethod,
                nonce: twintackAdminPayments.nonce
            },
            success: function(response) {
                if (response.success) {
                    var message = response.data.message;
                    
                    // Add additional info for different action types
                    if (actionType === 'mark_paid') {
                        message += ' Order status: ' + (response.data.order_status || 'Updated');
                    } else if (actionType === 'set_pay_later') {
                        // Show Pay Later specific info
                        if (response.data.order_status) {
                            message += '<br/>📄 Order Status: ' + response.data.order_status;
                        }
                        if (response.data.shippo_status) {
                            message += '<br/>📦 Shippo Status: ' + response.data.shippo_status;
                        }
                        if (response.data.payment_url) {
                            message += '<br/>🔗 Payment Link: <a href="' + response.data.payment_url + '" target="_blank">View Payment Page</a>';
                        }
                        if (response.data.email_sent === true) {
                            message += '<br/>✅ Invoice email sent to customer';
                        } else if (response.data.email_sent === false) {
                            message += '<br/>⚠️ Invoice created but email failed to send';
                        }
                    } else if (actionType === 'send_payment_link') {
                        // Show payment link details
                        if (response.data.payment_url) {
                            message += '<br/>🔗 Payment Link: <a href="' + response.data.payment_url + '" target="_blank">Open Payment Page</a>';
                        }
                        if (response.data.session_id) {
                            message += '<br/>📄 Session ID: ' + response.data.session_id;
                        }
                        if (response.data.email_sent === true) {
                            message += '<br/>✅ Email successfully sent to customer';
                        } else if (response.data.email_sent === false) {
                            message += '<br/>⚠️ Payment link created but email failed to send';
                        }
                    }
                    
                    showMessage(message, 'success');
                    
                    // Reload page after successful payment actions to reflect changes
                    if (actionType === 'mark_paid' || actionType === 'process_payment' || actionType === 'set_pay_later') {
                        setTimeout(function() {
                            location.reload();
                        }, 3000); // Slightly longer delay for Pay Later to show full message
                    } else {
                        // Re-enable buttons for payment link (no page reload needed)
                        setTimeout(function() {
                            $('#twintack-pay-now, #twintack-pay-later, #twintack-mark-paid, #twintack-process-payment, #twintack-send-payment-link').prop('disabled', false);
                        }, 2000);
                    }
                } else {
                    showMessage(twintackAdminPayments.messages.error + ' ' + response.data.message, 'error');
                    // Re-enable buttons on error
                    $('#twintack-pay-now, #twintack-pay-later, #twintack-mark-paid, #twintack-process-payment, #twintack-send-payment-link').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                showMessage(twintackAdminPayments.messages.error + ' AJAX request failed.', 'error');
                console.error('AJAX Error:', status, error);
                
                // Re-enable buttons on error
                $('#twintack-pay-now, #twintack-pay-later, #twintack-mark-paid, #twintack-process-payment, #twintack-send-payment-link').prop('disabled', false);
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
        $('#twintack-payment-messages .notice').remove();
        
        // Add new message (using html() instead of text() to support HTML content)
        $('#twintack-payment-messages').html(messageHtml);
        
        // Auto-dismiss after 8 seconds for success/info messages (longer for payment links)
        if (type !== 'error') {
            var dismissTime = type === 'success' && message.includes('Payment Link') ? 12000 : 6000;
            setTimeout(function() {
                $('#twintack-payment-messages .notice').fadeOut();
            }, dismissTime);
        }
    }
    
    // Update payment method when selection changes
    $('#twintack_payment_method_select').on('change', function() {
        var selectedMethod = $(this).val();
        var selectedText = $(this).find('option:selected').text();
        
        if (selectedMethod) {
            showMessage('Payment method selected: ' + selectedText, 'info');
            
            // Show/hide payment link button based on Stripe selection
            if (selectedMethod === 'stripe') {
                $('#twintack-send-payment-link').show().removeClass('hidden');
                showMessage('💡 Stripe selected - Payment link option available!', 'info');
            }
        } else {
            // Clear any existing messages when no method selected
            $('#twintack-payment-messages').empty();
        }
    });
    
    // Add enhanced styling for the updated interface
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .twintack-payment-processing {
                border-radius: 4px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            
            .twintack-payment-processing h4 {
                margin-top: 0;
                color: #23282d;
                border-bottom: 1px solid #e1e1e1;
                padding-bottom: 8px;
            }
            
            .twintack-payment-processing button {
                min-width: 120px;
                font-weight: 500;
                transition: all 0.2s ease;
            }
            
            .twintack-payment-processing button:hover:not(:disabled) {
                transform: translateY(-1px);
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .twintack-payment-processing button:disabled {
                opacity: 0.6;
                cursor: not-allowed;
                transform: none !important;
            }
            
            #twintack-payment-messages {
                margin-top: 15px;
            }
            
            #twintack-payment-messages .notice {
                margin: 0 0 10px 0;
                padding: 10px;
                border-radius: 3px;
            }
            
            #twintack_payment_method_select {
                padding: 6px;
                border: 1px solid #ddd;
                border-radius: 3px;
                font-size: 13px;
            }
            
            #twintack-send-payment-link {
                background: linear-gradient(135deg, #6c5ce7, #a29bfe) !important;
                border-color: #6c5ce7 !important;
                text-shadow: 0 1px 2px rgba(0,0,0,0.1);
            }
            
            #twintack-send-payment-link:hover:not(:disabled) {
                background: linear-gradient(135deg, #5b4cdb, #8b7eed) !important;
            }
            
            /* Pay Now button styling */
            #twintack-pay-now {
                background: linear-gradient(135deg, #00a86b, #4CAF50) !important;
                border-color: #00a86b !important;
                color: white !important;
                font-weight: bold !important;
                text-shadow: 0 1px 2px rgba(0,0,0,0.1);
                box-shadow: 0 2px 4px rgba(0,168,107,0.2);
            }
            
            #twintack-pay-now:hover:not(:disabled) {
                background: linear-gradient(135deg, #008f5b, #45a049) !important;
                transform: translateY(-2px);
            }
            
            /* Pay Later button styling */
            #twintack-pay-later {
                background: linear-gradient(135deg, #ff9800, #ffa726) !important;
                border-color: #ff9800 !important;
                color: white !important;
                font-weight: bold !important;
                text-shadow: 0 1px 2px rgba(0,0,0,0.1);
                box-shadow: 0 2px 4px rgba(255,152,0,0.2);
            }
            
            #twintack-pay-later:hover:not(:disabled) {
                background: linear-gradient(135deg, #f57c00, #ff9500) !important;
                transform: translateY(-2px);
            }
        `)
        .appendTo('head');
        
    // Initialize: check if there's already a payment method set
    var currentMethod = $('#twintack_payment_method_select').val();
    if (currentMethod) {
        var methodText = $('#twintack_payment_method_select option:selected').text();
        showMessage('Current payment method: ' + methodText, 'info');
        
        // Show payment link button if Stripe is selected
        if (currentMethod === 'stripe') {
            showMessage('💡 Stripe is selected - Payment link option available!', 'info');
        }
    }
}); 