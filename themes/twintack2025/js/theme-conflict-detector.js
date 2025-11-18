/**
 * TwinTack Theme Conflict Detector - Simplified
 * 
 * This script helps identify conflicts in a read-only manner without
 * attempting to modify or fix anything.
 */
(function($) {
    'use strict';

    // Store information about the page
    var pageInfo = {
        url: window.location.href,
        isCheckout: window.location.href.indexOf('checkout') > -1,
        theme: document.body.className
    };

    // Function to collect basic info - completely read-only
    function collectBasicInfo() {
        if (pageInfo.isCheckout) {
            // Check jQuery version
            pageInfo.jQueryVersion = jQuery.fn.jquery;
            
            // Log the information to console
            console.log('Theme Detector Info:', {
                url: pageInfo.url,
                theme: pageInfo.theme,
                jQueryVersion: pageInfo.jQueryVersion
            });
            
            // Check for checkout form
            var hasCheckoutForm = $('form.checkout').length > 0;
            if (!hasCheckoutForm) {
                console.log('Checkout form not found');
            }
        }
    }

    // Only run on checkout page
    if (pageInfo.isCheckout) {
        $(document).ready(function() {
            // Wait for page to fully load
            setTimeout(function() {
                collectBasicInfo();
            }, 1000);
        });
    }

})(jQuery); 