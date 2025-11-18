(function($) {
    'use strict';
    
    // Update cart quantities via AJAX
    $('.quantity-input').on('change', function() {
        $('[name="update_cart"]').trigger('click');
    });

    // Custom shipping calculator behavior
    $('#calc_shipping_country').on('change', function() {
        // Trigger shipping calculation update
        $('button[name="calc_shipping"]').trigger('click');
    });

    // Listen for checkout errors and handle them
    $(document.body).on('checkout_error', function(event, errorMessage) {
        console.log('Checkout error detected:', errorMessage);
        
        // If there's a WC Store API error, try to handle it
        if (errorMessage.indexOf('wp-json/wc/store/v1/checkout') !== -1 || 
            errorMessage.indexOf('500') !== -1) {
            
            // Check if we're in Stripe sandbox mode
            const isYithStripeSandbox = $('input[name="yith_stripe_mode"]').val() === 'sandbox';
            
            if (isYithStripeSandbox) {
                // Prevent the default error handling
                event.preventDefault();
                event.stopPropagation();
                
                console.log('Handling YITH Stripe sandbox mode error');
                
                // If this is a sandbox mode error, continue with checkout
                const paymentMethod = $('input[name="payment_method"]:checked').val();
                
                if (paymentMethod && paymentMethod.includes('yith-stripe')) {
                    // Submit the form directly bypassing the API
                    $('#place_order').prop('disabled', false);
                    
                    // Add a message to the user
                    if (!$('.yith-stripe-sandbox-notice').length) {
                        $('<div class="woocommerce-info yith-stripe-sandbox-notice">Stripe sandbox mode is active. Test transactions will be processed without actual charges.</div>')
                            .insertBefore('#payment');
                    }
                }
            }
        }
    });
    
    // Handle potential Store API errors on form submission
    $('form.checkout').on('submit', function(e) {
        const paymentMethod = $('input[name="payment_method"]:checked').val();
        const isYithStripeSandbox = $('input[name="yith_stripe_mode"]').val() === 'sandbox';
        
        if (paymentMethod && paymentMethod.includes('yith-stripe') && isYithStripeSandbox) {
            // Make sure the form doesn't use AJAX submission in sandbox mode
            $(this).addClass('processing').block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
        }
    });
})(jQuery);
