/**
 * Cart Icon Update Script
 * 
 * Updates the cart icon with the current cart count
 */
(function($) {
    'use strict';

    // Function to update cart counter
    function updateCartCount() {
        // Use WooCommerce AJAX to get cart contents
        $.ajax({
            url: wc_cart_fragments_params.wc_ajax_url.toString().replace('%%endpoint%%', 'get_refreshed_fragments'),
            type: 'POST',
            success: function(data) {
                if (data && data.fragments) {
                    var cartCount = 0;
                    
                    // Try to parse the cart count from the fragments
                    if (data.cart_hash) {
                        // Count items in cart
                        if (data.fragments['.widget_shopping_cart_content']) {
                            var $content = $(data.fragments['.widget_shopping_cart_content']);
                            cartCount = $content.find('.cart_item').length;
                        }
                    }
                    
                    // Update cart icon indicator
                    updateCartIndicator(cartCount);
                }
            }
        });
    }
    
    // Function to update cart indicator
    function updateCartIndicator(count) {
        var $cartIcon = $('.fixed-nav-icons .cart-icon');
        
        // Remove existing indicator
        $cartIcon.find('.cart-count').remove();
        
        // Add indicator if count > 0
        if (count > 0) {
            $cartIcon.append('<span class="cart-count">' + count + '</span>');
        }
    }
    
    // Initialize on document ready
    $(document).ready(function() {
        // Initialize cart count
        if (typeof wc_cart_fragments_params !== 'undefined') {
            updateCartCount();
        }
        
        // Update cart count when cart is updated
        $(document.body).on('added_to_cart removed_from_cart', function() {
            updateCartCount();
        });
    });
    
})(jQuery); 