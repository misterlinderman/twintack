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
        var productCount = $carousel.children('li').length;
        
        // Hide navigation if not enough products
        if (productCount <= 3) {
            $prevBtn.closest('.twintack-featured-products-nav').hide();
        }
        
        // Only initialize carousel if more than 3 products
        if (productCount > 3) {
            $carousel.slick({
                slidesToShow: 3,
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
        }
    }
    
    function updateCarouselButtons(slick, $prevBtn, $nextBtn) {
        if (!$prevBtn.length || !$nextBtn.length) {
            return;
        }
        
        var currentSlide = slick.currentSlide;
        var slideCount = slick.slideCount;
        var slidesToShow = slick.options.slidesToShow;
        
        // Disable prev button at start
        if (currentSlide === 0) {
            $prevBtn.prop('disabled', true);
        } else {
            $prevBtn.prop('disabled', false);
        }
        
        // Disable next button at end
        if (currentSlide >= slideCount - slidesToShow) {
            $nextBtn.prop('disabled', true);
        } else {
            $nextBtn.prop('disabled', false);
        }
    }
    
    // Reinitialize on window resize
    var resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            var $carousel = $('.twintack-featured-products-slider');
            if ($carousel.length > 0 && $carousel.hasClass('slick-initialized')) {
                $carousel.slick('setPosition');
            }
        }, 250);
    });
})(jQuery);

