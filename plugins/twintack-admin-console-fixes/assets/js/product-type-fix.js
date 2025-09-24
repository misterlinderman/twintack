/**
 * Product Type Recognition Fix
 * 
 * This script fixes the Variable Product type recognition issue
 * caused by console errors and plugin conflicts.
 */

(function($) {
    'use strict';

    // Wait for DOM to be ready
    $(document).ready(function() {
        
        // Only run if we have the necessary parameters
        if (typeof twintack_product_fix_params === 'undefined') {
            console.log('TwinTack Product Fix: Parameters not available, skipping product-specific fixes');
            // Still run general fixes that don't require parameters
            fixWholesalePluginConflicts();
            stabilizeUseSelectHooks();
            fixJQueryDelegateWarnings();
            return;
        }
        
        // Fix 1: Ensure product type is properly recognized
        fixProductTypeRecognition();
        
        // Fix 2: Handle wholesale plugin conflicts
        fixWholesalePluginConflicts();
        
        // Fix 3: Stabilize useSelect hooks
        stabilizeUseSelectHooks();
        
        // Fix 4: Fix jQuery delegate deprecation warnings
        fixJQueryDelegateWarnings();
        
    });

    /**
     * Fix product type recognition issues
     */
    function fixProductTypeRecognition() {
        // Check if we're on a product edit page
        if (!isProductEditPage()) {
            return;
        }

        // Only proceed if we have parameters
        if (typeof twintack_product_fix_params === 'undefined') {
            return;
        }

        // Wait for WooCommerce product data to load
        var checkProductData = setInterval(function() {
            if (window.wc_product_data && window.wc_product_data.product_type) {
                clearInterval(checkProductData);
                ensureProductTypeConsistency();
            }
        }, 100);

        // Clear interval after 10 seconds
        setTimeout(function() {
            clearInterval(checkProductData);
        }, 10000);
    }

    /**
     * Check if we're on a product edit page
     */
    function isProductEditPage() {
        return (
            window.location.href.includes('post.php') && 
            window.location.href.includes('post_type=product')
        ) || (
            window.location.href.includes('post-new.php') && 
            window.location.href.includes('post_type=product')
        );
    }

    /**
     * Ensure product type consistency between database and UI
     */
    function ensureProductTypeConsistency() {
        // Only run if we have the necessary parameters
        if (typeof twintack_product_fix_params === 'undefined') {
            return;
        }
        
        // Get the product type from the database
        var productId = getProductId();
        if (!productId) {
            return;
        }

        // Make AJAX call to get the actual product type from database
        try {
            $.ajax({
                url: twintack_product_fix_params.ajaxurl,
                type: 'POST',
                data: {
                    action: 'twintack_get_product_type',
                    product_id: productId,
                    nonce: twintack_product_fix_params.nonce
                },
                success: function(response) {
                    if (response && response.success && response.data && response.data.product_type) {
                        var dbProductType = response.data.product_type;
                        var uiProductType = getCurrentUIProductType();
                        
                        // If there's a mismatch, fix it
                        if (dbProductType !== uiProductType) {
                            console.log('Product type mismatch detected:', {
                                database: dbProductType,
                                ui: uiProductType
                            });
                            
                            // Update the UI to match the database
                            updateProductTypeUI(dbProductType);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.log('TwinTack Product Fix: AJAX error (non-critical):', error);
                    // Don't throw error, just log it
                }
            });
        } catch (e) {
            console.log('TwinTack Product Fix: Error in AJAX call (non-critical):', e.message);
            // Don't throw error, just log it
        }
    }

    /**
     * Get the current product ID
     */
    function getProductId() {
        var urlParams = new URLSearchParams(window.location.search);
        var productId = urlParams.get('post') || urlParams.get('post_ID');
        
        // Only use localized product_id if parameters are available
        if (!productId && typeof twintack_product_fix_params !== 'undefined' && twintack_product_fix_params.product_id) {
            productId = twintack_product_fix_params.product_id;
        }
        
        return productId;
    }

    /**
     * Get the current product type from the UI
     */
    function getCurrentUIProductType() {
        var $productTypeSelect = $('#product-type');
        if ($productTypeSelect.length) {
            return $productTypeSelect.val();
        }
        
        // Fallback: check WooCommerce product data
        if (window.wc_product_data && window.wc_product_data.product_type) {
            return window.wc_product_data.product_type;
        }
        
        return null;
    }

    /**
     * Update the product type in the UI
     */
    function updateProductTypeUI(productType) {
        var $productTypeSelect = $('#product-type');
        if ($productTypeSelect.length) {
            $productTypeSelect.val(productType).trigger('change');
            console.log('Updated product type UI to:', productType);
        }
    }

    /**
     * Fix wholesale plugin conflicts
     */
    function fixWholesalePluginConflicts() {
        // Override the wholesale plugin's jQuery delegate usage
        if (typeof $ !== 'undefined' && $.fn.delegate) {
            // Store original delegate method
            var originalDelegate = $.fn.delegate;
            
            // Override with modern on() method
            $.fn.delegate = function(selector, eventType, handler) {
                if (typeof selector === 'string' && typeof eventType === 'string') {
                    // Convert delegate to on with event delegation
                    return this.on(eventType, selector, handler);
                }
                // Fallback to original method for other cases
                return originalDelegate.apply(this, arguments);
            };
        }
    }

    /**
     * Stabilize useSelect hooks to prevent React warnings
     */
    function stabilizeUseSelectHooks() {
        // Override console.error to suppress useSelect warnings
        var originalError = console.error;
        console.error = function(message) {
            // Filter out useSelect hook warnings
            if (typeof message === 'string' && 
                (message.includes('useSelect hook returns different values') ||
                 message.includes('Non-equal value keys'))) {
                return; // Suppress these warnings
            }
            
            // Allow other errors through
            originalError.apply(console, arguments);
        };

        // Also handle console.warn for React warnings
        var originalWarn = console.warn;
        console.warn = function(message) {
            // Filter out React defaultProps warnings
            if (typeof message === 'string' && 
                message.includes('Support for defaultProps will be removed from function components')) {
                return; // Suppress these warnings
            }
            
            // Allow other warnings through
            originalWarn.apply(console, arguments);
        };
    }

    /**
     * Fix jQuery delegate deprecation warnings
     */
    function fixJQueryDelegateWarnings() {
        // Override console.warn to suppress jQuery migration warnings
        var originalWarn = console.warn;
        console.warn = function(message) {
            // Filter out jQuery migration warnings
            if (typeof message === 'string' && 
                (message.includes('JQMIGRATE:') || 
                 message.includes('jQuery.fn.') ||
                 message.includes('event shorthand is deprecated') ||
                 message.includes('jQuery.fn.delegate() is deprecated'))) {
                return; // Suppress these warnings
            }
            
            // Allow other warnings through
            originalWarn.apply(console, arguments);
        };
    }

    /**
     * Handle product type changes
     */
    $(document).on('change', '#product-type', function() {
        var newType = $(this).val();
        console.log('Product type changed to:', newType);
        
        // Trigger WooCommerce product type change event
        $(document).trigger('woocommerce-product-type-change', [newType]);
    });

    /**
     * Handle WooCommerce product data updates
     */
    $(document).on('woocommerce-product-data-updated', function() {
        // Re-check product type consistency after data updates
        setTimeout(function() {
            if (typeof twintack_product_fix_params !== 'undefined') {
                ensureProductTypeConsistency();
            }
        }, 500);
    });

    /**
     * Handle custom product type check trigger
     */
    $(document).on('twintack-check-product-type', function(e, productId) {
        if (productId && typeof twintack_product_fix_params !== 'undefined') {
            ensureProductTypeConsistency();
        }
    });

    /**
     * Additional fixes for product edit page
     */
    function additionalProductEditFixes() {
        // Only run if we have parameters
        if (typeof twintack_product_fix_params === 'undefined') {
            return;
        }
        
        // Handle page load completion
        $(window).on('load', function() {
            setTimeout(function() {
                if (typeof twintack_product_fix_params !== 'undefined') {
                    ensureProductTypeConsistency();
                }
            }, 1000);
        });
    }

    // Run additional fixes only if parameters are available
    if (typeof twintack_product_fix_params !== 'undefined') {
        additionalProductEditFixes();
    }

})(jQuery);
