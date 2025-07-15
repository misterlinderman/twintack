/**
 * TwinTack Enhanced Shop Filters JavaScript
 * Enhanced functionality with variation swatch integration
 */
(function($) {
    'use strict';
    
    const EnhancedFilters = {
        init: function() {
            this.bindEvents();
            this.initializeColorSwatches();
            this.initializeModal();
        },
        
        bindEvents: function() {
            // Handle filter form submission
            $(document).on('submit', '.filter-form', this.handleFilterSubmit.bind(this));
            
            // Handle clear filters button
            $(document).on('click', '.clear-filters-btn', this.clearFilters.bind(this));
            
            // Handle clear all filters button
            $(document).on('click', '.clear-all-filters', this.clearAllFilters.bind(this));
            
            // Handle individual filter removal
            $(document).on('click', '.remove-filter', this.removeFilter.bind(this));
            
            // Handle color swatch clicks
            $(document).on('click', '.color-swatch', this.handleColorSwatchClick.bind(this));
            
            // Handle modal close
            $(document).on('click', '#filter-modal-close', this.closeModal.bind(this));
            
            // Handle filter modal backdrop click
            $(document).on('click', '.filter-modal', function(e) {
                if (e.target === this) {
                    EnhancedFilters.closeModal();
                }
            });
            
            // Handle escape key
            $(document).on('keydown', this.handleKeyDown.bind(this));
            
            // Handle filter changes
            $(document).on('change', '.filter-select', this.handleFilterChange.bind(this));
            
            // Handle price input changes
            $(document).on('input', '.price-input', this.handlePriceChange.bind(this));
        },
        
        initializeModal: function() {
            // Ensure modal is hidden on page load
            $('#filter-modal').hide();
            
            // Handle filter button click
            $(document).on('click', '.filter-button', function() {
                $('#filter-modal').show();
                EnhancedFilters.focusFirstInput();
            });
        },
        
        initializeColorSwatches: function() {
            // Sync color swatches with select dropdown
            const colorSelect = $('#filter_pa_color');
            const colorSwatches = $('.color-swatch');
            
            // Set initial active state based on URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const activeColor = urlParams.get('filter_pa_color');
            
            if (activeColor) {
                colorSelect.val(activeColor);
                colorSwatches.removeClass('active');
                colorSwatches.filter(`[data-color="${activeColor}"]`).addClass('active');
            }
            
            // Update swatches when select changes
            colorSelect.on('change', function() {
                const selectedColor = $(this).val();
                colorSwatches.removeClass('active');
                
                if (selectedColor) {
                    colorSwatches.filter(`[data-color="${selectedColor}"]`).addClass('active');
                }
            });
        },
        
        handleColorSwatchClick: function(e) {
            e.preventDefault();
            
            const $swatch = $(e.currentTarget);
            const colorValue = $swatch.data('color');
            const colorSelect = $('#filter_pa_color');
            
            // Toggle swatch selection
            if ($swatch.hasClass('active')) {
                // Deselect
                $swatch.removeClass('active');
                colorSelect.val('').trigger('change');
            } else {
                // Select
                $('.color-swatch').removeClass('active');
                $swatch.addClass('active');
                colorSelect.val(colorValue).trigger('change');
            }
        },
        
        handleFilterSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(e.currentTarget);
            const formData = new FormData($form[0]);
            const params = new URLSearchParams();
            
            // Add all form data to params
            for (let [key, value] of formData.entries()) {
                if (value && value.trim() !== '') {
                    params.append(key, value);
                }
            }
            
            // Add current sort parameter if it exists
            const urlParams = new URLSearchParams(window.location.search);
            const orderby = urlParams.get('orderby');
            if (orderby) {
                params.append('orderby', orderby);
            }
            
            // Build new URL
            const shopUrl = twintack_filters.shop_url;
            const newUrl = params.toString() ? `${shopUrl}?${params.toString()}` : shopUrl;
            
            // Show loading state
            this.showLoading($form);
            
            // Redirect to filtered results
            window.location.href = newUrl;
        },
        
        clearFilters: function(e) {
            e.preventDefault();
            
            // Clear all form inputs
            $('.filter-form')[0].reset();
            
            // Clear color swatches
            $('.color-swatch').removeClass('active');
            
            // Redirect to shop without filters
            window.location.href = twintack_filters.shop_url;
        },
        
        clearAllFilters: function(e) {
            e.preventDefault();
            
            // Redirect to shop without any filters
            window.location.href = twintack_filters.shop_url;
        },
        
        removeFilter: function(e) {
            e.preventDefault();
            
            const $link = $(e.currentTarget);
            const removeUrl = $link.attr('href');
            
            if (removeUrl) {
                window.location.href = removeUrl;
            }
        },
        
        handleFilterChange: function(e) {
            // Optional: Add real-time filtering if AJAX is enabled
            if (twintack_filters.ajax_enabled) {
                this.debounce(this.updateFilters.bind(this), 300)();
            }
        },
        
        handlePriceChange: function(e) {
            // Optional: Add real-time price filtering if AJAX is enabled
            if (twintack_filters.ajax_enabled) {
                this.debounce(this.updateFilters.bind(this), 500)();
            }
        },
        
        handleKeyDown: function(e) {
            // Close modal on Escape key
            if (e.keyCode === 27) {
                this.closeModal();
            }
        },
        
        closeModal: function() {
            $('#filter-modal').hide();
        },
        
        focusFirstInput: function() {
            // Focus first input in modal for accessibility
            setTimeout(function() {
                $('.filter-modal .filter-select:first, .filter-modal .price-input:first').focus();
            }, 100);
        },
        
        showLoading: function($form) {
            $form.addClass('loading');
            $('.apply-filters-btn').prop('disabled', true).text('Applying...');
        },
        
        hideLoading: function($form) {
            $form.removeClass('loading');
            $('.apply-filters-btn').prop('disabled', false).text('Apply Filters');
        },
        
        updateFilters: function() {
            // AJAX filtering functionality (if enabled)
            if (!twintack_filters.ajax_enabled) {
                return;
            }
            
            const $form = $('.filter-form');
            const formData = new FormData($form[0]);
            const filters = {};
            
            // Convert form data to object
            for (let [key, value] of formData.entries()) {
                if (value && value.trim() !== '') {
                    filters[key] = value;
                }
            }
            
            // Make AJAX request
            $.ajax({
                url: twintack_filters.ajax_url,
                type: 'POST',
                data: {
                    action: 'twintack_filter_products',
                    nonce: twintack_filters.nonce,
                    filters: filters
                },
                beforeSend: function() {
                    $('.woocommerce-notices-wrapper').empty();
                    // Show loading indicator
                },
                success: function(response) {
                    if (response.success) {
                        // Update product grid
                        // This would require additional backend implementation
                        console.log('Filter results:', response.data);
                    }
                },
                error: function() {
                    console.error('Filter request failed');
                }
            });
        },
        
        debounce: function(func, delay) {
            let timeoutId;
            return function() {
                const context = this;
                const args = arguments;
                clearTimeout(timeoutId);
                timeoutId = setTimeout(function() {
                    func.apply(context, args);
                }, delay);
            };
        },
        
        // Utility functions for variation swatch integration
        syncVariationSwatches: function() {
            if (!twintack_filters.variation_swatches_active) {
                return;
            }
            
            // Enhanced functionality for variation swatches
            $('.variation-swatch').each(function() {
                const $swatch = $(this);
                const colorValue = $swatch.data('color');
                
                // Add enhanced tooltips
                if (!$swatch.attr('title')) {
                    $swatch.attr('title', colorValue.replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase()));
                }
                
                // Add click animation
                $swatch.on('click', function() {
                    $(this).addClass('clicked');
                    setTimeout(function() {
                        $swatch.removeClass('clicked');
                    }, 200);
                });
            });
        },
        
        // Enhanced accessibility features
        enhanceAccessibility: function() {
            // Add ARIA labels
            $('.color-swatch').each(function() {
                const $swatch = $(this);
                const colorName = $swatch.data('color') || $swatch.attr('title');
                $swatch.attr('aria-label', `Filter by ${colorName}`);
                $swatch.attr('role', 'button');
                $swatch.attr('tabindex', '0');
            });
            
            // Add keyboard navigation for color swatches
            $('.color-swatch').on('keydown', function(e) {
                if (e.keyCode === 13 || e.keyCode === 32) { // Enter or Space
                    e.preventDefault();
                    $(this).click();
                }
            });
            
            // Add focus management
            $('.filter-modal').on('shown', function() {
                $('.filter-modal .filter-select:first').focus();
            });
        },
        
        // Handle responsive behavior
        handleResponsive: function() {
            const $modal = $('#filter-modal');
            const $content = $('.filter-modal-content');
            
            // Adjust modal size on window resize
            $(window).on('resize', function() {
                if ($modal.is(':visible')) {
                    // Adjust modal position if needed
                    const windowHeight = $(window).height();
                    const contentHeight = $content.outerHeight();
                    
                    if (contentHeight > windowHeight * 0.9) {
                        $content.css('max-height', windowHeight * 0.9 + 'px');
                    }
                }
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        EnhancedFilters.init();
        EnhancedFilters.syncVariationSwatches();
        EnhancedFilters.enhanceAccessibility();
        EnhancedFilters.handleResponsive();
    });
    
    // Expose to global scope if needed
    window.TwinTackEnhancedFilters = EnhancedFilters;
    
})(jQuery); 