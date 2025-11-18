/**
 * Wholesale Plugin jQuery Delegate Patch
 * 
 * This script patches the wholesale plugin's use of deprecated jQuery.delegate()
 * to use the modern jQuery.on() method instead.
 */

(function($) {
    'use strict';

    // Wait for DOM to be ready
    $(document).ready(function() {
        
        // Patch jQuery delegate method before wholesale plugin loads
        patchJQueryDelegate();
        
        // Fix wholesale plugin specific issues
        fixWholesalePluginIssues();
        
    });

    /**
     * Patch jQuery delegate method to use modern on() method
     */
    function patchJQueryDelegate() {
        // Store original delegate method
        var originalDelegate = $.fn.delegate;
        
        // Override delegate to use on() method
        $.fn.delegate = function(selector, eventType, handler) {
            // Check if this is a valid delegate call
            if (typeof selector === 'string' && typeof eventType === 'string' && typeof handler === 'function') {
                // Convert to modern on() method with event delegation
                return this.on(eventType, selector, handler);
            }
            
            // For other cases, use original method
            return originalDelegate.apply(this, arguments);
        };
        
        console.log('jQuery delegate method patched to use modern on() method');
    }

    /**
     * Fix wholesale plugin specific issues
     */
    function fixWholesalePluginIssues() {
        // Wait for wholesale plugin scripts to load
        var checkWholesalePlugin = setInterval(function() {
            if (window.wwpp_single_product_admin_params) {
                clearInterval(checkWholesalePlugin);
                applyWholesalePluginFixes();
            }
        }, 100);

        // Clear interval after 10 seconds
        setTimeout(function() {
            clearInterval(checkWholesalePlugin);
        }, 10000);
    }

    /**
     * Apply specific fixes for wholesale plugin
     */
    function applyWholesalePluginFixes() {
        // Fix price type change handler
        $(document).on('change', '.pqbwp_price_type', function() {
            var $this = $(this);
            var $price_field = $this
                .closest('p')
                .siblings('.pqbwp_wholesale_price_field');

            if ($this.val() == 'fixed-price') {
                $price_field
                    .find('label')
                    .text(window.wwpp_single_product_admin_params.i18n_fixed_price_wholesale_label);
            } else if ($this.val() == 'percent-price') {
                $price_field
                    .find('label')
                    .text(window.wwpp_single_product_admin_params.i18n_percent_price_wholesale_label);
            }
        });

        // Fix enable/disable handler
        $(document).on('click', '.pqbwp-enable', function() {
            var $this = $(this);
            var $parent_fields_container = $this.closest('.product-quantity-based-wholesale-pricing');
            var $processing_indicator = $parent_fields_container.find('.processing-indicator');
            var $pqbwp_controls = $parent_fields_container.find('.pqbwp-controls');
            var post_id = $.trim($this.siblings('.post-id').text());
            var enable = $this.is(':checked') ? 'yes' : 'no';
            var is_parent_variable = $parent_fields_container.hasClass('parent-variable');
            var $variation_qty_based_pricing_tbl = $('body').find('#variable_product_options .product-quantity-based-wholesale-pricing.variable');

            $this.attr('disabled', 'disabled');
            $processing_indicator.show();

            // Make AJAX call to enable/disable
            $.ajax({
                url: window.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wwpp_toggle_qty_based_wholesale_pricing',
                    post_id: post_id,
                    enable: enable,
                    is_parent_variable: is_parent_variable,
                    nonce: window.wwpp_single_product_admin_params.toggle_qty_based_wholesale_pricing_nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (enable === 'yes') {
                            $pqbwp_controls.show();
                            if (is_parent_variable) {
                                $variation_qty_based_pricing_tbl.show();
                            }
                        } else {
                            $pqbwp_controls.hide();
                            if (is_parent_variable) {
                                $variation_qty_based_pricing_tbl.hide();
                            }
                        }
                    } else {
                        // Revert checkbox state
                        $this.prop('checked', !$this.is(':checked'));
                        alert('Error: ' + (response.data || 'Unknown error occurred'));
                    }
                },
                error: function() {
                    // Revert checkbox state
                    $this.prop('checked', !$this.is(':checked'));
                    alert('Error: Failed to update wholesale pricing settings');
                },
                complete: function() {
                    $this.attr('disabled', false);
                    $processing_indicator.hide();
                }
            });
        });

        console.log('Wholesale plugin fixes applied');
    }

    /**
     * Additional fixes for product type consistency
     */
    function additionalProductTypeFixes() {
        // Monitor for product type changes
        $(document).on('change', '#product-type', function() {
            var newType = $(this).val();
            
            // If changing to variable, ensure variations are shown
            if (newType === 'variable') {
                setTimeout(function() {
                    $('.product-quantity-based-wholesale-pricing.variable').show();
                }, 500);
            }
        });

        // Handle WooCommerce product data updates
        $(document).on('woocommerce-product-data-updated', function() {
            // Re-apply wholesale plugin fixes after data updates
            setTimeout(function() {
                applyWholesalePluginFixes();
            }, 500);
        });
    }

    // Run additional fixes
    additionalProductTypeFixes();

})(jQuery);
