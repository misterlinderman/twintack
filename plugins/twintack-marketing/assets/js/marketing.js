/**
 * TwinTack Marketing JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Announcement Bar Dismissible
        $('.twintack-announcement-bar.dismissible .twintack-announcement-close').on('click', function() {
            var $bar = $(this).closest('.twintack-announcement-bar');
            var dismissed = localStorage.getItem('twintack_announcement_dismissed');
            
            if (!dismissed) {
                localStorage.setItem('twintack_announcement_dismissed', '1');
                $bar.fadeOut(300, function() {
                    $bar.remove();
                });
            } else {
                $bar.fadeOut(300, function() {
                    $bar.remove();
                });
            }
        });
        
        // Check if announcement was previously dismissed
        var dismissed = localStorage.getItem('twintack_announcement_dismissed');
        if (dismissed && $('.twintack-announcement-bar.dismissible').length) {
            $('.twintack-announcement-bar.dismissible').hide();
        }
        
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
})(jQuery);

