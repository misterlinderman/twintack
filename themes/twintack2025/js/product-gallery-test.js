/**
 * Product Gallery Test Script
 * Run this in browser console to test carousel functionality
 */
(function() {
    'use strict';
    
    console.log('🔍 TwinTack Product Gallery Test');
    console.log('================================');
    
    // Check if jQuery is loaded
    if (typeof jQuery === 'undefined') {
        console.error('❌ jQuery is not loaded');
        return;
    }
    console.log('✅ jQuery is loaded');
    
    // Check if Slick is loaded
    if (typeof jQuery.fn.slick === 'undefined') {
        console.error('❌ Slick Carousel is not loaded');
        return;
    }
    console.log('✅ Slick Carousel is loaded');
    
    // Check if PhotoSwipe is loaded
    if (typeof PhotoSwipe === 'undefined') {
        console.error('❌ PhotoSwipe is not loaded');
        return;
    }
    console.log('✅ PhotoSwipe is loaded');
    
    // Check for product gallery elements
    const $gallery = jQuery('.woocommerce-product-gallery');
    if ($gallery.length === 0) {
        console.warn('⚠️ No product gallery found on this page');
        return;
    }
    console.log('✅ Product gallery found');
    
    // Check for carousel structure
    const $carousel = $gallery.find('.twintack-product-carousel');
    if ($carousel.length === 0) {
        console.warn('⚠️ New carousel structure not found - may be using legacy gallery');
        return;
    }
    console.log('✅ New carousel structure found');
    
    // Check for slides
    const $slides = $carousel.find('.twintack-carousel-slides');
    if ($slides.length === 0) {
        console.error('❌ No carousel slides found');
        return;
    }
    console.log('✅ Carousel slides found');
    
    // Check if Slick is initialized
    if (!$slides.hasClass('slick-initialized')) {
        console.error('❌ Slick carousel is not initialized');
        return;
    }
    console.log('✅ Slick carousel is initialized');
    
    // Check for thumbnails
    const $thumbnails = $carousel.find('.twintack-carousel-thumbnails');
    if ($thumbnails.length > 0) {
        console.log('✅ Thumbnails found');
        if ($thumbnails.hasClass('slick-initialized')) {
            console.log('✅ Thumbnail carousel is initialized');
        } else {
            console.warn('⚠️ Thumbnail carousel is not initialized');
        }
    } else {
        console.log('ℹ️ No thumbnails (may have ≤3 images)');
    }
    
    // Test touch/swipe functionality
    console.log('📱 Testing touch support...');
    const touchSupport = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    console.log(touchSupport ? '✅ Touch support detected' : 'ℹ️ No touch support detected');
    
    // Test responsive breakpoints
    const width = window.innerWidth;
    console.log(`📏 Current viewport width: ${width}px`);
    
    if (width <= 480) {
        console.log('📱 Mobile viewport detected');
    } else if (width <= 768) {
        console.log('📱 Tablet viewport detected');
    } else {
        console.log('🖥️ Desktop viewport detected');
    }
    
    // Test carousel navigation
    console.log('🧪 Testing carousel navigation...');
    const $prevArrow = $carousel.find('.slick-prev');
    const $nextArrow = $carousel.find('.slick-next');
    
    if ($prevArrow.length > 0 && $nextArrow.length > 0) {
        console.log('✅ Navigation arrows found');
        
        // Test arrow clicks
        console.log('🔄 Testing arrow functionality...');
        const currentSlide = $slides.slick('slickCurrentSlide');
        console.log(`📍 Current slide: ${currentSlide}`);
        
        // Click next arrow
        $nextArrow.trigger('click');
        setTimeout(() => {
            const newSlide = $slides.slick('slickCurrentSlide');
            console.log(`📍 After next click: ${newSlide}`);
            
            // Click prev arrow
            $prevArrow.trigger('click');
            setTimeout(() => {
                const finalSlide = $slides.slick('slickCurrentSlide');
                console.log(`📍 After prev click: ${finalSlide}`);
                
                if (finalSlide === currentSlide) {
                    console.log('✅ Navigation arrows working correctly');
                } else {
                    console.error('❌ Navigation arrows not working correctly');
                }
            }, 100);
        }, 100);
    } else {
        console.error('❌ Navigation arrows not found');
    }
    
    // Test lightbox functionality
    console.log('🔍 Testing lightbox functionality...');
    const $images = $carousel.find('img');
    if ($images.length > 0) {
        console.log(`✅ ${$images.length} images found for lightbox`);
        
        // Check if images have click handlers
        const hasClickHandler = $images.first().data('events') && $images.first().data('events').click;
        if (hasClickHandler) {
            console.log('✅ Image click handlers detected');
        } else {
            console.log('ℹ️ No click handlers detected (may be attached via event delegation)');
        }
    } else {
        console.error('❌ No images found');
    }
    
    console.log('================================');
    console.log('🎉 Test completed! Check results above.');
    
    // Return test results for programmatic access
    return {
        jquery: typeof jQuery !== 'undefined',
        slick: typeof jQuery.fn.slick !== 'undefined',
        photoswipe: typeof PhotoSwipe !== 'undefined',
        gallery: $gallery.length > 0,
        carousel: $carousel.length > 0,
        slides: $slides.length > 0,
        slickInitialized: $slides.hasClass('slick-initialized'),
        thumbnails: $thumbnails.length > 0,
        touchSupport: touchSupport,
        viewportWidth: width
    };
})();
