/**
 * TwinTack Marketing JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Announcement Bar Dismissible
        $('.twintack-announcement-bar.dismissible .twintack-announcement-close').on('click', function() {
            var $bar = $(this).closest('.twintack-announcement-bar');
            var $bundleCounter = $('#twintack-bundle-counter');
            var dismissed = localStorage.getItem('twintack_announcement_dismissed');
            
            if (!dismissed) {
                localStorage.setItem('twintack_announcement_dismissed', '1');
                $bar.fadeOut(300, function() {
                    $bar.remove();
                    // Adjust bundle counter position if present
                    adjustBarsPosition();
                });
            } else {
                $bar.fadeOut(300, function() {
                    $bar.remove();
                    adjustBarsPosition();
                });
            }
        });
        
        // Check if announcement was previously dismissed
        var dismissed = localStorage.getItem('twintack_announcement_dismissed');
        if (dismissed && $('.twintack-announcement-bar.dismissible').length) {
            $('.twintack-announcement-bar.dismissible').hide();
            adjustBarsPosition();
        }
        
        // Initialize Bundle Counter
        initBundleCounter();
        
        // Initialize Featured Products Carousel
        initFeaturedProductsCarousel();
    });
    
    function initFeaturedProductsCarousel() {
        var $carousel = $('.twintack-featured-products-slider');
        
        if ($carousel.length === 0 || typeof $.fn.slick === 'undefined') {
            return;
        }
        
        // Check if carousel is already initialized
        if ($carousel.hasClass('slick-initialized')) {
            return;
        }
        
        var $container = $carousel.closest('.twintack-featured-products');
        var $prevBtn = $container.find('.twintack-featured-products-prev');
        var $nextBtn = $container.find('.twintack-featured-products-next');
        var $nav = $container.find('.twintack-featured-products-nav');
        var productCount = $carousel.children('li').length;
        
        // Always initialize carousel if there's more than 1 product
        if (productCount > 1) {
            // Determine initial slides to show based on screen width
            var isMobile = $(window).width() <= 768;
            var initialSlidesToShow = isMobile ? 1 : (productCount > 3 ? 3 : productCount);
            
            $carousel.slick({
                slidesToShow: initialSlidesToShow,
                slidesToScroll: 1,
                infinite: false,
                arrows: false,
                dots: false,
                autoplay: false,
                draggable: true,
                swipe: true,
                touchMove: true,
                variableWidth: false,
                centerMode: false,
                responsive: [
                    {
                        breakpoint: 768,
                        settings: {
                            slidesToShow: 1,
                            slidesToScroll: 1,
                            infinite: productCount > 1
                        }
                    }
                ]
            });
            
            // Custom navigation buttons
            $prevBtn.on('click', function() {
                $carousel.slick('slickPrev');
            });
            
            $nextBtn.on('click', function() {
                $carousel.slick('slickNext');
            });
            
            // Update button states
            $carousel.on('afterChange', function(event, slick, currentSlide) {
                updateCarouselButtons(slick, $prevBtn, $nextBtn);
            });
            
            // Initial button state
            setTimeout(function() {
                var slick = $carousel.slick('getSlick');
                updateCarouselButtons(slick, $prevBtn, $nextBtn);
            }, 100);
            
            // Hide navigation on desktop if 3 or fewer products
            if (!isMobile && productCount <= 3) {
                $nav.hide();
            } else {
                $nav.show();
            }
        } else {
            // Hide navigation if only 1 product
            $nav.hide();
        }
    }
    
    function updateCarouselButtons(slick, $prevBtn, $nextBtn) {
        if (!$prevBtn.length || !$nextBtn.length) {
            return;
        }
        
        var currentSlide = slick.currentSlide;
        var slideCount = slick.slideCount;
        var isMobile = $(window).width() <= 768;
        var slidesToShow = isMobile ? 1 : slick.options.slidesToShow;
        
        // Disable prev button at start
        if (currentSlide === 0) {
            $prevBtn.prop('disabled', true).css('opacity', '0.3');
        } else {
            $prevBtn.prop('disabled', false).css('opacity', '1');
        }
        
        // Disable next button at end
        if (isMobile) {
            // On mobile, disable next at last slide
            if (currentSlide >= slideCount - 1) {
                $nextBtn.prop('disabled', true).css('opacity', '0.3');
            } else {
                $nextBtn.prop('disabled', false).css('opacity', '1');
            }
        } else {
            // On desktop, disable next when showing last set of slides
            if (currentSlide >= slideCount - slidesToShow) {
                $nextBtn.prop('disabled', true).css('opacity', '0.3');
            } else {
                $nextBtn.prop('disabled', false).css('opacity', '1');
            }
        }
    }
    
    // Reinitialize on window resize
    var resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            var $carousel = $('.twintack-featured-products-slider');
            if ($carousel.length > 0 && $carousel.hasClass('slick-initialized')) {
                var $container = $carousel.closest('.twintack-featured-products');
                var $nav = $container.find('.twintack-featured-products-nav');
                var productCount = $carousel.children('li').length;
                var isMobile = $(window).width() <= 768;
                
                $carousel.slick('setPosition');
                
                // Show/hide navigation based on screen size and product count
                if (productCount > 1) {
                    if (!isMobile && productCount <= 3) {
                        $nav.hide();
                    } else {
                        $nav.show();
                    }
                } else {
                    $nav.hide();
                }
            }
        }, 250);
    });
    
    /**
     * Adjust positioning of bars and header when announcement bar is dismissed
     */
    function adjustBarsPosition() {
        var $bundleCounter = $('#twintack-bundle-counter');
        var $siteHeader = $('.site-header');
        var $fixedNavIcons = $('.fixed-nav-icons');
        var $page = $('#page');
        var $body = $('body');
        var isAdminBar = $body.hasClass('admin-bar');
        var isMobile = $(window).width() <= 782;
        
        if ($bundleCounter.length) {
            var adminBarHeight = 0;
            if (isAdminBar) {
                adminBarHeight = isMobile ? 46 : 32;
            }
            
            // Move bundle counter to top (after admin bar if present)
            $bundleCounter.css('top', adminBarHeight + 'px');
            
            // Adjust site header (increased bundle height to 75px)
            var bundleHeight = 75;
            var newHeaderTop = adminBarHeight + bundleHeight;
            $siteHeader.css('top', newHeaderTop + 'px');
            
            // Adjust fixed nav icons (account/cart)
            var fixedNavTop = adminBarHeight + bundleHeight + 20; // 20px padding
            $fixedNavIcons.css('top', fixedNavTop + 'px');
            
            // Adjust main content padding
            $page.css('padding-top', newHeaderTop + 'px');
        } else {
            // No bundle counter, just reset everything if announcement was dismissed
            var adminBarHeight = 0;
            if (isAdminBar) {
                adminBarHeight = isMobile ? 46 : 32;
            }
            
            $siteHeader.css('top', adminBarHeight + 'px');
            
            // Reset fixed nav icons to original position with admin bar offset
            var fixedNavTop = adminBarHeight + 20; // 20px original padding
            $fixedNavIcons.css('top', fixedNavTop + 'px');
            
            // Reset main content padding
            $page.css('padding-top', adminBarHeight + 'px');
        }
    }
    
    /**
     * Bundle Counter Functions
     */
    function initBundleCounter() {
        var $counter = $('#twintack-bundle-counter');
        
        if ($counter.length === 0) {
            return;
        }
        
        // Update bundle counter when cart changes
        $(document.body).on('added_to_cart removed_from_cart updated_cart_totals', function() {
            updateBundleCounter();
        });
        
        // Update on WooCommerce fragments refresh
        $(document.body).on('wc_fragments_refreshed', function() {
            updateBundleCounter();
        });
    }
    
    function updateBundleCounter() {
        var $counter = $('#twintack-bundle-counter');
        
        if ($counter.length === 0 || typeof wc_add_to_cart_params === 'undefined') {
            return;
        }
        
        // Call AJAX to get updated bundle count
        $.ajax({
            url: wc_add_to_cart_params.ajax_url,
            type: 'POST',
            data: {
                action: 'get_bundle_count'
            },
            success: function(response) {
                if (response.success && response.data) {
                    var data = response.data;
                    var bundleSize = parseInt($counter.data('bundle-size'));
                    var discountText = $counter.data('discount-text');
                    var count = data.count;
                    var progressPercent = data.progress;
                    var isQualified = data.qualified;
                    
                    // Update text
                    var $bundleText = $counter.find('.bundle-text');
                    var newText = '';
                    
                    if (isQualified) {
                        newText = '<span class="bundle-qualified">🎉 Congrats! You qualify for ' + discountText + ' on your bundle!</span>';
                        $counter.addClass('qualified');
                    } else if (count > 0) {
                        var remaining = bundleSize - count;
                        var gripText = remaining === 1 ? 'grip' : 'grips';
                        newText = '<span class="bundle-progress-text">Add ' + remaining + ' more ' + gripText + ' to get ' + discountText + '!</span>';
                        $counter.removeClass('qualified');
                    } else {
                        newText = '<span class="bundle-empty-text">Buy ' + bundleSize + ' grips and save ' + discountText + '!</span>';
                        $counter.removeClass('qualified');
                    }
                    
                    $bundleText.html(newText);
                    
                    // Update or hide progress bar
                    var $progressBar = $counter.find('.bundle-progress-bar');
                    
                    if (count > 0 && !isQualified) {
                        // Show and update progress bar
                        if ($progressBar.length === 0) {
                            // Create progress bar if it doesn't exist
                            var progressHtml = '<div class="bundle-progress-bar">' +
                                '<div class="progress-track">' +
                                '<div class="progress-fill" style="width: ' + progressPercent + '%; background-color: ' + $counter.css('color') + ';"></div>' +
                                '</div>' +
                                '<div class="progress-count">' +
                                '<span class="current-count">' + count + '</span> / ' +
                                '<span class="target-count">' + bundleSize + '</span>' +
                                '</div>' +
                                '</div>';
                            $counter.find('.twintack-bundle-content').append(progressHtml);
                        } else {
                            // Update existing progress bar
                            $progressBar.find('.progress-fill').css('width', progressPercent + '%');
                            $progressBar.find('.current-count').text(count);
                            $progressBar.show();
                        }
                    } else {
                        // Hide progress bar
                        $progressBar.hide();
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Bundle counter update failed:', error);
            }
        });
    }
})(jQuery);

