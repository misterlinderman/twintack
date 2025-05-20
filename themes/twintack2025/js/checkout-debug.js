/**
 * TwinTack Checkout Debug Script - Minimal Version
 * 
 * This script provides basic monitoring of the checkout process
 * without modifying any standard WooCommerce behavior.
 */
(function($) {
    'use strict';

    // Debug log function - only logs to console, doesn't modify anything
    function debugLog(message) {
        if (typeof console !== 'undefined' && console && console.log) {
            console.log('[Checkout Debug] ' + message);
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        debugLog('Checkout debug initialized');
        
        // Log if we're on checkout page
        if (window.location.href.indexOf('checkout') > -1) {
            debugLog('On checkout page: ' + window.location.href);
        }
    });

})(jQuery); 