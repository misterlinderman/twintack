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
            useFullSizeGalleryImages($gallery);
            initLightbox($gallery);
        }
    }

    /**
     * Use WooCommerce full-size source for display (not 100px gallery thumbs).
     *
     * @param {jQuery} $scope Gallery element or slide container.
     */
    function useFullSizeGalleryImages($scope) {
        $scope.find('.woocommerce-product-gallery__image img').each(function() {
            const $img = $(this);
            const fullSrc = $img.attr('data-large_image') || $img.attr('data-src');

            if (!fullSrc) {
                return;
            }

            $img.attr('src', fullSrc);
            $img.removeAttr('srcset sizes');

            if ($img.attr('data-large_image_width')) {
                $img.attr('width', $img.attr('data-large_image_width'));
            }

            if ($img.attr('data-large_image_height')) {
                $img.attr('height', $img.attr('data-large_image_height'));
            }
        });
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
            useFullSizeGalleryImages($slide);
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
        ensureGalleryTrigger($gallery);

        $gallery.off('.twintackLightbox');

        $gallery.on('click.twintackLightbox', '.woocommerce-product-gallery__trigger', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            openTwinTackLightbox($gallery, getCurrentSlideIndex($gallery));
        });

        $gallery.on(
            'click.twintackLightbox',
            '.twintack-carousel-slides .woocommerce-product-gallery__image a, .woocommerce-product-gallery__wrapper > .woocommerce-product-gallery__image a',
            function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                const $mainImages = getMainGalleryImages($gallery);
                const $imageEl = $(this).closest('.woocommerce-product-gallery__image');
                const index = $mainImages.index($imageEl);
                openTwinTackLightbox($gallery, index >= 0 ? index : 0);
            }
        );
    }

    function ensureGalleryTrigger($gallery) {
        if ($gallery.find('.woocommerce-product-gallery__trigger').length) {
            return;
        }

        const label =
            typeof wc_single_product_params !== 'undefined' && wc_single_product_params.i18n_product_gallery_trigger_text
                ? wc_single_product_params.i18n_product_gallery_trigger_text
                : 'View full-screen image gallery';

        $gallery.prepend(
            '<a href="#" role="button" class="woocommerce-product-gallery__trigger" aria-haspopup="dialog" ' +
                'aria-controls="photoswipe-fullscreen-dialog" aria-label="' +
                label +
                '">' +
                '<span aria-hidden="true"></span></a>'
        );
    }

    function getMainGalleryImages($gallery) {
        const $carouselImages = $gallery.find('.twintack-carousel-slides .woocommerce-product-gallery__image');
        if ($carouselImages.length) {
            return $carouselImages;
        }

        return $gallery.find('.woocommerce-product-gallery__wrapper > .woocommerce-product-gallery__image');
    }

    function getCurrentSlideIndex($gallery) {
        const $slides = $gallery.find('.twintack-carousel-slides');
        if ($slides.length && $slides.hasClass('slick-initialized')) {
            return $slides.slick('slickCurrentSlide');
        }

        return 0;
    }

    function buildLightboxItems($images) {
        const items = [];

        $images.each(function() {
            const $img = $(this).find('img').first();
            if (!$img.length) {
                return;
            }

            const src = $img.attr('data-large_image') || $img.attr('src') || $img.attr('data-src');
            if (!src) {
                return;
            }

            items.push({
                src: src,
                w: parseInt($img.attr('data-large_image_width'), 10) || 800,
                h: parseInt($img.attr('data-large_image_height'), 10) || 600,
                title: $img.attr('alt') || '',
            });
        });

        return items;
    }

    function openTwinTackLightbox($gallery, index) {
        if (typeof PhotoSwipe === 'undefined' || typeof PhotoSwipeUI_Default === 'undefined') {
            return;
        }

        const pswpElement = document.getElementById('photoswipe-fullscreen-dialog') || document.querySelector('.pswp');
        if (!pswpElement) {
            return;
        }

        const items = buildLightboxItems(getMainGalleryImages($gallery));
        if (!items.length) {
            return;
        }

        index = Math.max(0, Math.min(index, items.length - 1));

        const options = {
            index: index,
            bgOpacity: 0.92,
            showHideOpacity: true,
            timeToIdle: 0,
            addCaptionHTMLFn: function(item, captionEl) {
                if (!item.title) {
                    captionEl.children[0].textContent = '';
                    return false;
                }
                captionEl.children[0].textContent = item.title;
                return true;
            },
        };

        const photoswipe = new PhotoSwipe(pswpElement, PhotoSwipeUI_Default, items, options);

        photoswipe.listen('afterInit', function() {
            $('body').addClass('twintack-pswp-active');
        });

        photoswipe.listen('destroy', function() {
            $('body').removeClass('twintack-pswp-active');
        });

        photoswipe.init();
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