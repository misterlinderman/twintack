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
})(jQuery);
