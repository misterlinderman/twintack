/**
 * TwinTack Product Gallery with Slick Carousel
 * Enhanced mobile touch support and lightbox integration
 */
jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize product gallery if it exists
    if ($('.woocommerce-product-gallery').length) {
        initProductGallery();
    }
    
    function initProductGallery() {
        const $gallery = $('.woocommerce-product-gallery');
        const $wrapper = $gallery.find('.woocommerce-product-gallery__wrapper');
        
        // Check if we have multiple images
        const $images = $wrapper.find('.woocommerce-product-gallery__image');
        
        if ($images.length > 1) {
            // Create carousel structure
            createCarouselStructure($gallery, $wrapper, $images);
            
            // Initialize Slick carousel
            initSlickCarousel($gallery);
            
            // Initialize lightbox
            initLightbox($gallery);
            
            // Handle variation changes
            handleVariationChanges($gallery);
        } else {
            // Single image - just initialize lightbox
            initLightbox($gallery);
        }
    }
    
    function createCarouselStructure($gallery, $wrapper, $images) {
        // Create main carousel container
        const $carousel = $('<div class="twintack-product-carousel"></div>');
        
        // Create slides container
        const $slides = $('<div class="twintack-carousel-slides"></div>');
        
        // Move images to slides
        $images.each(function() {
            const $image = $(this);
            const $slide = $('<div class="twintack-carousel-slide"></div>');
            $slide.append($image.clone());
            $slides.append($slide);
        });
        
        $carousel.append($slides);
        
        // Create thumbnails if we have more than 3 images
        if ($images.length > 3) {
            const $thumbnails = $('<div class="twintack-carousel-thumbnails"></div>');
            
            $images.each(function(index) {
                const $image = $(this);
                const $thumb = $('<div class="twintack-carousel-thumb"></div>');
                const $img = $image.find('img').clone();
                $img.attr('data-slide-index', index);
                $thumb.append($img);
                $thumbnails.append($thumb);
            });
            
            $carousel.append($thumbnails);
        }
        
        // Replace wrapper content
        $wrapper.html($carousel);
    }
    
    function initSlickCarousel($gallery) {
        const $carousel = $gallery.find('.twintack-product-carousel');
        const $slides = $carousel.find('.twintack-carousel-slides');
        const $thumbnails = $carousel.find('.twintack-carousel-thumbnails');
        
        // Main carousel settings
        const carouselSettings = {
            slidesToShow: 1,
            slidesToScroll: 1,
            arrows: true,
            fade: true,
            adaptiveHeight: true,
            infinite: true,
            autoplay: false,
            autoplaySpeed: 5000,
            pauseOnHover: true,
            pauseOnFocus: true,
            swipe: true,
            touchMove: true,
            touchThreshold: 5,
            swipeToSlide: true,
            prevArrow: '<button type="button" class="slick-prev twintack-carousel-arrow" aria-label="Previous image"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15,18 9,12 15,6"></polyline></svg></button>',
            nextArrow: '<button type="button" class="slick-next twintack-carousel-arrow" aria-label="Next image"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,18 15,12 9,6"></polyline></svg></button>',
            responsive: [
                {
                    breakpoint: 768,
                    settings: {
                        arrows: true,
                        swipe: true,
                        touchMove: true,
                        swipeToSlide: true,
                        touchThreshold: 3
                    }
                },
                {
                    breakpoint: 480,
                    settings: {
                        arrows: true,
                        swipe: true,
                        touchMove: true,
                        swipeToSlide: true,
                        touchThreshold: 2
                    }
                }
            ]
        };
        
        // Initialize main carousel
        $slides.slick(carouselSettings);
        
        // Initialize thumbnail carousel if it exists
        if ($thumbnails.length) {
            const thumbnailSettings = {
                slidesToShow: Math.min(4, $thumbnails.find('.twintack-carousel-thumb').length),
                slidesToScroll: 1,
                arrows: true,
                infinite: false,
                centerMode: false,
                focusOnSelect: true,
                swipe: true,
                touchMove: true,
                prevArrow: '<button type="button" class="slick-prev twintack-thumb-arrow" aria-label="Previous thumbnails"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15,18 9,12 15,6"></polyline></svg></button>',
                nextArrow: '<button type="button" class="slick-next twintack-thumb-arrow" aria-label="Next thumbnails"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,18 15,12 9,6"></polyline></svg></button>',
                responsive: [
                    {
                        breakpoint: 768,
                        settings: {
                            slidesToShow: Math.min(3, $thumbnails.find('.twintack-carousel-thumb').length),
                            swipe: true,
                            touchMove: true
                        }
                    },
                    {
                        breakpoint: 480,
                        settings: {
                            slidesToShow: Math.min(2, $thumbnails.find('.twintack-carousel-thumb').length),
                            swipe: true,
                            touchMove: true
                        }
                    }
                ]
            };
            
            $thumbnails.slick(thumbnailSettings);
            
            // Sync main carousel with thumbnails
            $slides.on('afterChange', function(event, slick, currentSlide) {
                $thumbnails.slick('slickGoTo', currentSlide);
            });
            
            // Handle thumbnail clicks
            $thumbnails.on('click', '.twintack-carousel-thumb', function() {
                const slideIndex = $(this).find('img').data('slide-index');
                $slides.slick('slickGoTo', slideIndex);
            });
        }
    }
    
    function initLightbox($gallery) {
        // Add PhotoSwipe markup if it doesn't exist
        if (!$('.pswp').length) {
            $('body').append(`
                <div class="pswp" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="pswp__bg"></div>
                    <div class="pswp__scroll-wrap">
                        <div class="pswp__container">
                            <div class="pswp__item"></div>
                            <div class="pswp__item"></div>
                            <div class="pswp__item"></div>
                        </div>
                        <div class="pswp__ui pswp__ui--hidden">
                            <div class="pswp__top-bar">
                                <div class="pswp__counter"></div>
                                <button class="pswp__button pswp__button--close" title="Close (Esc)"></button>
                                <button class="pswp__button pswp__button--share" title="Share"></button>
                                <button class="pswp__button pswp__button--fs" title="Toggle fullscreen"></button>
                                <button class="pswp__button pswp__button--zoom" title="Zoom in/out"></button>
                                <div class="pswp__preloader">
                                    <div class="pswp__preloader__icn">
                                        <div class="pswp__preloader__cut">
                                            <div class="pswp__preloader__donut"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="pswp__share-modal pswp__share-modal--hidden pswp__single-tap">
                                <div class="pswp__share-tooltip"></div>
                            </div>
                            <button class="pswp__button pswp__button--arrow--left" title="Previous (arrow left)"></button>
                            <button class="pswp__button pswp__button--arrow--right" title="Next (arrow right)"></button>
                            <div class="pswp__caption">
                                <div class="pswp__caption__center"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `);
        }
        
        // Handle image clicks for lightbox
        $gallery.on('click', 'img', function(e) {
            e.preventDefault();
            
            const $clickedImg = $(this);
            const $galleryImages = $gallery.find('img');
            const items = [];
            let startIndex = 0;
            
            // Build items array
            $galleryImages.each(function(index) {
                const $img = $(this);
                const src = $img.attr('src') || $img.attr('data-src');
                const alt = $img.attr('alt') || '';
                
                if (src) {
                    items.push({
                        src: src,
                        w: $img.attr('data-width') || $img.width() || 800,
                        h: $img.attr('data-height') || $img.height() || 600,
                        title: alt
                    });
                    
                    if ($img[0] === $clickedImg[0]) {
                        startIndex = index;
                    }
                }
            });
            
            if (items.length > 0) {
                // Initialize PhotoSwipe
                const pswpElement = document.querySelectorAll('.pswp')[0];
                const options = {
                    index: startIndex,
                    bgOpacity: 0.85,
                    showHideOpacity: true,
                    shareButtons: [
                        {id:'facebook', label:'Share on Facebook', url:'https://www.facebook.com/sharer/sharer.php?u={{url}}'},
                        {id:'twitter', label:'Tweet', url:'https://twitter.com/intent/tweet?text={{text}}&url={{url}}'},
                        {id:'pinterest', label:'Pin it', url:'http://www.pinterest.com/pin/create/button/?url={{url}}&media={{image_url}}&description={{text}}'}
                    ]
                };
                
                const gallery = new PhotoSwipe(pswpElement, PhotoSwipeUI_Default, items, options);
                gallery.init();
            }
        });
    }
    
    function handleVariationChanges($gallery) {
        // Handle WooCommerce variation changes
        $(document.body).on('found_variation', 'form.variations_form', function(event, variation) {
            if (variation && variation.image_id) {
                const $carousel = $gallery.find('.twintack-carousel-slides');
                if ($carousel.length && $carousel.hasClass('slick-initialized')) {
                    // Find the slide with the matching image
                    const $targetSlide = $carousel.find(`[data-image-id="${variation.image_id}"]`).closest('.twintack-carousel-slide');
                    if ($targetSlide.length) {
                        const slideIndex = $targetSlide.index();
                        $carousel.slick('slickGoTo', slideIndex);
                    }
                }
            }
        });
    }
});