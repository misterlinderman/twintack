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

        // Resolve full-size URLs while server-rendered srcset is still on the page.
        const resolvedSources = [];
        $wrapper.find('.woocommerce-product-gallery__image').each(function() {
            resolvedSources.push(resolveLightboxImageSource($(this)));
        });
        
        // Check if we have multiple images
        const $images = $wrapper.find('.woocommerce-product-gallery__image');
        
        if ($images.length > 1) {
            // Create carousel structure
            createCarouselStructure($gallery, $wrapper, $images, resolvedSources);
            
            // Initialize Slick carousel
            initSlickCarousel($gallery);
            
            // Initialize lightbox
            initLightbox($gallery);
            
            // Handle variation changes
            handleVariationChanges($gallery);
        } else {
            // Single image - just initialize lightbox
            applyResolvedSource($gallery.find('.woocommerce-product-gallery__image').first(), resolvedSources[0]);
            initLightbox($gallery);
        }

        neutralizeWooCommerceLightbox($gallery);
        window.setTimeout(function() {
            neutralizeWooCommerceLightbox($gallery);
        }, 300);
    }

    /**
     * Prevent WooCommerce core PhotoSwipe from opening with 600px data-large_image URLs.
     *
     * @param {jQuery} $gallery Product gallery root element.
     */
    function neutralizeWooCommerceLightbox($gallery) {
        const wcGallery = $gallery.data('product_gallery');

        if (wcGallery) {
            wcGallery.photoswipe_enabled = false;
        }

        $gallery.off('click', '.woocommerce-product-gallery__trigger');
        $gallery.off('click', '.woocommerce-product-gallery__image a');
    }

    /**
     * Apply a resolved image source to a gallery slide/image element.
     *
     * @param {jQuery} $imageEl Gallery image wrapper.
     * @param {{url:string,width:number,height:number}} resolved Resolved source.
     */
    function applyResolvedSource($imageEl, resolved) {
        if (!$imageEl || !$imageEl.length || !resolved || !resolved.url) {
            return;
        }

        const $img = $imageEl.find('img').first();
        if (!$img.length) {
            return;
        }

        const srcset = $img.attr('srcset');
        if (srcset) {
            $img.attr('data-twintack-srcset', srcset);
        }

        $img.attr('src', resolved.url);
        $img.removeAttr('srcset sizes');

        if (resolved.width) {
            $img.attr('width', resolved.width);
            $img.attr('data-large_image_width', resolved.width);
            $img.attr('data-twintack-full-width', resolved.width);
        }

        if (resolved.height) {
            $img.attr('height', resolved.height);
            $img.attr('data-large_image_height', resolved.height);
            $img.attr('data-twintack-full-height', resolved.height);
        }

        $img.attr('data-large_image', resolved.url);
        $img.attr('data-twintack-full-src', resolved.url);
        $imageEl.find('a').first().attr('href', resolved.url);
    }

    /**
     * Use WooCommerce full-size source for display (not 100px gallery thumbs).
     *
     * @param {jQuery} $scope Gallery element or slide container.
     */
    function useFullSizeGalleryImages($scope) {
        $scope.find('.woocommerce-product-gallery__image').each(function() {
            applyResolvedSource($(this), resolveLightboxImageSource($(this)));
        });
    }
    
    function createCarouselStructure($gallery, $wrapper, $images, resolvedSources) {
        // Create main carousel container
        const $carousel = $('<div class="twintack-product-carousel"></div>');
        
        // Create slides container
        const $slides = $('<div class="twintack-carousel-slides"></div>');
        
        // Move images to slides
        $images.each(function(index) {
            const $image = $(this);
            const $slide = $('<div class="twintack-carousel-slide"></div>');
            $slide.append($image.clone());
            applyResolvedSource($slide.find('.woocommerce-product-gallery__image').first(), resolvedSources[index]);
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

        const galleryNode = $gallery.get(0);
        if (galleryNode) {
            galleryNode.addEventListener(
                'click',
                function(event) {
                    const $target = $(event.target);
                    const isTrigger = $target.closest('.woocommerce-product-gallery__trigger').length > 0;
                    const $link = $target.closest('.woocommerce-product-gallery__image a');

                    if (!isTrigger && !$link.length) {
                        return;
                    }

                    event.preventDefault();
                    event.stopImmediatePropagation();

                    let index = 0;
                    if ($link.length) {
                        const $mainImages = getMainGalleryImages($gallery);
                        const $imageEl = $link.closest('.woocommerce-product-gallery__image');
                        index = $mainImages.index($imageEl);
                    } else {
                        index = getCurrentSlideIndex($gallery);
                    }

                    openTwinTackLightbox($gallery, index >= 0 ? index : 0);
                },
                true
            );
        }

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

    function unwrapPhotonUrl(url) {
        try {
            const parsed = new URL(url, window.location.origin);
            const photonHosts = ['i0.wp.com', 'i1.wp.com', 'i2.wp.com', 'i3.wp.com'];

            if (photonHosts.indexOf(parsed.hostname) === -1) {
                return url;
            }

            const path = decodeURIComponent(parsed.pathname);
            const match = path.match(/^\/([^/\s]+)((?:\/wp-content\/).*)$/i);

            if (match) {
                return parsed.protocol + '//' + match[1] + match[2];
            }
        } catch (error) {
            return url;
        }

        return url;
    }

    function stripImageSizeQueryParams(url) {
        try {
            const parsed = new URL(url, window.location.origin);
            ['w', 'h', 'fit', 'resize', 'zoom'].forEach(function(param) {
                parsed.searchParams.delete(param);
            });

            if (!parsed.search || parsed.search === '?') {
                return parsed.origin + parsed.pathname;
            }

            return parsed.toString();
        } catch (error) {
            return url;
        }
    }

    function getUrlDimensionHint(url) {
        const match = String(url).match(/-(\d+)x(\d+)\.(jpe?g|png|gif|webp|avif)(\?|#|$)/i);

        if (!match) {
            return { width: 0, height: 0 };
        }

        return {
            width: parseInt(match[1], 10) || 0,
            height: parseInt(match[2], 10) || 0,
        };
    }

    function getLargestSrcsetEntry($img) {
        const srcset = $img.attr('srcset');

        if (!srcset) {
            return { url: '', width: 0, height: 0 };
        }

        let best = { url: '', width: 0, height: 0 };

        srcset.split(',').forEach(function(part) {
            const bits = part.trim().split(/\s+/);
            const url = bits[0];
            const width = parseInt(bits[1], 10) || 0;
            const dimensions = getUrlDimensionHint(url);
            const resolvedWidth = width || dimensions.width;

            if (resolvedWidth >= best.width) {
                best = {
                    url: url,
                    width: resolvedWidth,
                    height: dimensions.height || best.height,
                };
            }
        });

        return best;
    }

    function getSrcsetCandidateUrls($img) {
        const srcset = $img.attr('srcset') || $img.attr('data-twintack-srcset');
        const candidates = [];
        const seen = {};

        if (!srcset) {
            return candidates;
        }

        srcset.split(',').forEach(function(part) {
            const bits = part.trim().split(/\s+/);
            const url = stripImageSizeQueryParams(unwrapPhotonUrl(bits[0]));

            if (!url || seen[url]) {
                return;
            }

            seen[url] = true;
            candidates.push({
                url: url,
                width: parseInt(bits[1], 10) || getUrlDimensionHint(url).width || 0,
            });
        });

        candidates.sort(function(a, b) {
            return b.width - a.width;
        });

        return candidates.map(function(entry) {
            return entry.url;
        });
    }

    function resolveLightboxImageSource($imageEl) {
        const $img = $imageEl.find('img').first();
        const $link = $imageEl.find('a').first();
        const srcsetBest = getLargestSrcsetEntry($img);
        let best = { url: '', width: 0, height: 0 };

        function consider(url, widthHint, heightHint) {
            if (!url || typeof url !== 'string') {
                return;
            }

            const cleaned = stripImageSizeQueryParams(unwrapPhotonUrl(url));
            const dimensions = getUrlDimensionHint(cleaned);
            const width = widthHint || dimensions.width || 0;
            const height = heightHint || dimensions.height || 0;

            if (width > best.width || (!best.width && cleaned)) {
                best = {
                    url: cleaned,
                    width: width,
                    height: height || width,
                };
            }
        }

        consider(
            srcsetBest.url,
            srcsetBest.width,
            srcsetBest.height
        );
        consider(
            $img.attr('data-twintack-full-src'),
            parseInt($img.attr('data-twintack-full-width'), 10),
            parseInt($img.attr('data-twintack-full-height'), 10)
        );
        consider(
            $link.attr('href'),
            parseInt($img.attr('data-large_image_width'), 10),
            parseInt($img.attr('data-large_image_height'), 10)
        );
        consider(
            $img.attr('data-large_image'),
            parseInt($img.attr('data-large_image_width'), 10),
            parseInt($img.attr('data-large_image_height'), 10)
        );
        consider($img.attr('data-src'), 0, 0);
        consider($img.attr('src'), 0, 0);

        return best;
    }

    function resolveLightboxImageUrl($imageEl) {
        return resolveLightboxImageSource($imageEl).url;
    }

    function buildLightboxItems($images) {
        const items = [];

        $images.each(function() {
            const $imageEl = $(this);
            const $img = $imageEl.find('img').first();
            if (!$img.length) {
                return;
            }

            const resolved = resolveLightboxImageSource($imageEl);
            if (!resolved.url) {
                return;
            }

            const candidateUrls = getSrcsetCandidateUrls($img);
            if (candidateUrls.indexOf(resolved.url) === -1) {
                candidateUrls.unshift(resolved.url);
            }

            items.push({
                src: resolved.url,
                candidateUrls: candidateUrls,
                w: resolved.width || 0,
                h: resolved.height || 0,
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

        photoswipe.listen('gettingData', function(index, item) {
            if (item.__twintackDimensionsLoaded || !item.src) {
                return;
            }

            const candidates = item.candidateUrls && item.candidateUrls.length ? item.candidateUrls : [item.src];
            let attempt = 0;

            function loadNextCandidate() {
                if (attempt >= candidates.length) {
                    item.__twintackDimensionsLoaded = true;
                    return;
                }

                const preload = new Image();
                preload.onload = function() {
                    item.src = candidates[attempt];
                    item.w = this.naturalWidth;
                    item.h = this.naturalHeight;
                    item.__twintackDimensionsLoaded = true;
                    photoswipe.updateSize(true);
                };
                preload.onerror = function() {
                    attempt += 1;
                    loadNextCandidate();
                };
                preload.src = candidates[attempt];
            }

            loadNextCandidate();
        });

        photoswipe.listen('afterInit', function() {
            $('body').addClass('twintack-pswp-active');
        });

        photoswipe.listen('destroy', function() {
            $('body').removeClass('twintack-pswp-active');
        });

        photoswipe.init();
    }
    
    function resetGalleryToFirstSlide($gallery) {
        const $carousel = $gallery.find('.twintack-carousel-slides');

        if (!$carousel.length || !$carousel.hasClass('slick-initialized')) {
            return;
        }

        $carousel.slick('slickGoTo', 0);

        const $thumbnails = $gallery.find('.twintack-carousel-thumbnails');
        if ($thumbnails.length && $thumbnails.hasClass('slick-initialized')) {
            $thumbnails.slick('slickGoTo', 0);
        }

        $gallery.find('.twintack-carousel-thumb').removeClass('active slick-current');
        $gallery.find('.twintack-carousel-thumb').first().addClass('active');
    }

    function handleVariationChanges($gallery) {
        // WooCommerce triggers this when the variation image changes; flexslider is disabled here.
        $gallery.on('woocommerce_gallery_reset_slide_position.twintackGallery', function() {
            resetGalleryToFirstSlide($gallery);
        });

        $(document.body).on('found_variation.twintackGallery', 'form.variations_form', function(event, variation) {
            if (!variation) {
                return;
            }

            // Run after WooCommerce wc_variations_image_update (20ms timeout).
            window.setTimeout(function() {
                resetGalleryToFirstSlide($gallery);
                useFullSizeGalleryImages($gallery);
                $gallery.trigger('woocommerce_gallery_init_zoom');
            }, 30);
        });

        $(document.body).on('reset_data.twintackGallery', 'form.variations_form', function() {
            window.setTimeout(function() {
                resetGalleryToFirstSlide($gallery);
                useFullSizeGalleryImages($gallery);
                $gallery.trigger('woocommerce_gallery_init_zoom');
            }, 30);
        });
    }
});