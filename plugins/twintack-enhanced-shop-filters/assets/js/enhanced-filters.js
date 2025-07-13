/**
 * TwinTack Enhanced Shop Filters JavaScript
 * Handles filtering and AJAX functionality
 */
(function($) {
    'use strict';
    
    const EnhancedFilters = {
        init: function() {
            this.bindEvents();
            this.initializeFilters();
        },
        
        bindEvents: function() {
            // Handle filter changes
            $(document).on('change', '.filter-select, .price-input', this.handleFilterChange.bind(this));
            
            // Handle filter form submission
            $(document).on('submit', '.filter-form', this.handleFilterSubmit.bind(this));
            
            // Handle clear filters
            $(document).on('click', '.clear-filters', this.clearFilters.bind(this));
            
            // Handle horizontal filters
            $(document).on('change', '.horizontal-filters select, .horizontal-filters input', this.handleHorizontalFilter.bind(this));
            
            // Handle sidebar filters
            $(document).on('change', '.sidebar-filters select, .sidebar-filters input', this.handleSidebarFilter.bind(this));
            
            // Handle modal filters - integrate with existing modal system
            this.integrateModalFilters();
        },
        
        initializeFilters: function() {
            // Initialize any special filter components
            this.initializePriceSlider();
            this.initializeColorFilter();
        },
        
        handleFilterChange: function(e) {
            if (twintackFilters.ajax_enabled) {
                this.applyFiltersAjax();
            } else {
                this.applyFiltersReload();
            }
        },
        
        handleFilterSubmit: function(e) {
            e.preventDefault();
            
            if (twintackFilters.ajax_enabled) {
                this.applyFiltersAjax();
            } else {
                this.applyFiltersReload();
            }
        },
        
        handleHorizontalFilter: function(e) {
            // Auto-apply filters for horizontal layout
            if (twintackFilters.ajax_enabled) {
                this.applyFiltersAjax();
            } else {
                this.applyFiltersReload();
            }
        },
        
        handleSidebarFilter: function(e) {
            // Auto-apply filters for sidebar layout
            if (twintackFilters.ajax_enabled) {
                this.applyFiltersAjax();
            } else {
                this.applyFiltersReload();
            }
        },
        
        integrateModalFilters: function() {
            // Integrate with the existing modal system
            const existingModal = document.getElementById('filter-modal');
            if (existingModal) {
                // Add apply button to existing modal
                const modalContent = existingModal.querySelector('.filter-modal-content');
                if (modalContent) {
                    const applyButton = document.createElement('button');
                    applyButton.className = 'apply-filters-btn';
                    applyButton.textContent = 'Apply Filters';
                    applyButton.addEventListener('click', () => {
                        if (twintackFilters.ajax_enabled) {
                            this.applyFiltersAjax();
                        } else {
                            this.applyFiltersReload();
                        }
                        // Close modal
                        existingModal.classList.remove('active');
                        document.body.classList.remove('overflow-hidden');
                    });
                    
                    modalContent.appendChild(applyButton);
                }
            }
        },
        
        applyFiltersAjax: function() {
            const filters = this.collectFilters();
            
            if (Object.keys(filters).length === 0) {
                return;
            }
            
            // Show loading state
            this.showLoading();
            
            $.ajax({
                url: twintackFilters.ajax_url,
                method: 'POST',
                data: {
                    action: 'filter_products',
                    nonce: twintackFilters.nonce,
                    filters: filters
                },
                success: function(response) {
                    if (response.success) {
                        this.updateProductsContainer(response.data.html);
                        this.updateResultCount(response.data.count);
                        this.updateURL(filters);
                    } else {
                        console.error('Filter error:', response.data);
                    }
                }.bind(this),
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                },
                complete: function() {
                    this.hideLoading();
                }.bind(this)
            });
        },
        
        applyFiltersReload: function() {
            const filters = this.collectFilters();
            const url = this.buildFilterURL(filters);
            
            // Navigate to the filtered URL
            window.location.href = url;
        },
        
        collectFilters: function() {
            const filters = {};
            
            // Collect all filter values
            $('.filter-select, .price-input').each(function() {
                const $this = $(this);
                const name = $this.attr('name');
                const value = $this.val();
                
                if (name && value) {
                    filters[name] = value;
                }
            });
            
            return filters;
        },
        
        buildFilterURL: function(filters) {
            const currentURL = new URL(window.location.href);
            
            // Clear existing filter parameters
            const params = new URLSearchParams(currentURL.search);
            const filterParams = [];
            
            // Remove old filter parameters
            for (const [key, value] of params.entries()) {
                if (!key.startsWith('filter_') && key !== 'min_price' && key !== 'max_price' && key !== 'product_cat') {
                    filterParams.push([key, value]);
                }
            }
            
            // Add new filter parameters
            for (const [key, value] of Object.entries(filters)) {
                if (value) {
                    filterParams.push([key, value]);
                }
            }
            
            // Build new URL
            const newURL = new URL(currentURL.origin + currentURL.pathname);
            filterParams.forEach(([key, value]) => {
                newURL.searchParams.append(key, value);
            });
            
            return newURL.toString();
        },
        
        updateProductsContainer: function(html) {
            const $container = $('.products, .products-row');
            if ($container.length) {
                $container.html(html);
            }
        },
        
        updateResultCount: function(count) {
            const $resultCount = $('.woocommerce-result-count');
            if ($resultCount.length) {
                $resultCount.text(`Showing ${count} results`);
            }
        },
        
        updateURL: function(filters) {
            const url = this.buildFilterURL(filters);
            history.pushState({}, '', url);
        },
        
        showLoading: function() {
            const $container = $('.products, .products-row');
            $container.addClass('loading');
            
            // Add loading overlay
            if (!$('.filter-loading').length) {
                $container.append('<div class="filter-loading">Loading...</div>');
            }
        },
        
        hideLoading: function() {
            const $container = $('.products, .products-row');
            $container.removeClass('loading');
            $('.filter-loading').remove();
        },
        
        clearFilters: function() {
            // Clear all filter controls
            $('.filter-select').val('');
            $('.price-input').val('');
            
            // Apply cleared filters
            if (twintackFilters.ajax_enabled) {
                this.applyFiltersAjax();
            } else {
                // Redirect to base shop URL
                window.location.href = window.location.pathname;
            }
        },
        
        initializePriceSlider: function() {
            // Initialize price range slider if available
            const $priceInputs = $('.price-input');
            if ($priceInputs.length) {
                $priceInputs.on('input', function() {
                    // Debounce the price filtering
                    clearTimeout(this.priceTimeout);
                    this.priceTimeout = setTimeout(() => {
                        if (twintackFilters.ajax_enabled) {
                            this.applyFiltersAjax();
                        }
                    }, 500);
                }.bind(this));
            }
        },
        
        initializeColorFilter: function() {
            // Initialize color filter with color swatches
            const $colorFilter = $('.filter-select[name="filter_pa_color"]');
            if ($colorFilter.length) {
                this.createColorSwatches($colorFilter);
            }
        },
        
        createColorSwatches: function($select) {
            const $container = $select.closest('.filter-control');
            const $swatches = $('<div class="color-swatches"></div>');
            
            $select.find('option').each(function() {
                const $option = $(this);
                const value = $option.val();
                const text = $option.text();
                
                if (value) {
                    const $swatch = $('<div class="color-swatch" data-value="' + value + '" title="' + text + '"></div>');
                    $swatch.css('background-color', value);
                    $swatches.append($swatch);
                }
            });
            
            $container.append($swatches);
            
            // Handle swatch clicks
            $swatches.on('click', '.color-swatch', function() {
                const value = $(this).data('value');
                $select.val(value).trigger('change');
                
                // Update swatch selection
                $swatches.find('.color-swatch').removeClass('selected');
                $(this).addClass('selected');
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        EnhancedFilters.init();
    });
    
})(jQuery); 