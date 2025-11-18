/**
 * Carousel JavaScript for TwinTack
 * 
 * This file handles the initialization and functionality
 * of the product carousel used in sport category pages.
 */

(function($) {
    'use strict';
    
    // Initialize carousel when document is ready
    $(document).ready(function() {
        initializeCarousel();
    });
    
    /**
     * Initialize the Swiper carousel
     */
    function initializeCarousel() {
        // Find the carousel container
        const swiperContainer = document.querySelector('.twintack-carousel .swiper');
        if (!swiperContainer) {
            console.error('Swiper container not found');
            return;
        }

        // Find the wrapper and slides
        const swiperWrapper = swiperContainer.querySelector('.swiper-wrapper');
        if (!swiperWrapper) {
            console.error('Swiper wrapper not found');
            return;
        }

        const slides = swiperWrapper.querySelectorAll('.swiper-slide');
        if (!slides.length) {
            console.error('No slides found');
            handleEmptyState();
            return;
        }

        // Calculate optimal slidesPerView based on number of slides
        const totalSlides = slides.length;
        const maxSlidesPerView = Math.min(totalSlides, 3);
        
        // Create Swiper instance
        const swiper = new Swiper('.twintack-carousel .swiper', {
            slidesPerView: 1,
            spaceBetween: 20,
            loop: false, // Disable loop mode since we don't have enough slides
            autoplay: false, // Disable autoplay with few slides
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            breakpoints: {
                640: {
                    slidesPerView: Math.min(2, maxSlidesPerView),
                },
                1024: {
                    slidesPerView: maxSlidesPerView,
                },
            },
            on: {
                init: function() {
                    console.log('Swiper initialized with', totalSlides, 'slides');
                },
                error: function(error) {
                    console.error('Swiper error:', error);
                }
            }
        });
        
        // Initialize additional functionality
        initializeLightbox();
        initializeAddToCart();
    }
    
    /**
     * Initialize lightbox functionality
     */
    function initializeLightbox() {
        // Create lightbox container if it doesn't exist
        if (!document.querySelector('.twintack-lightbox')) {
            const lightbox = document.createElement('div');
            lightbox.className = 'twintack-lightbox';
            lightbox.innerHTML = `
                <div class="twintack-lightbox-content">
                    <span class="twintack-lightbox-close">&times;</span>
                    <img class="twintack-lightbox-image" src="" alt="">
                </div>
            `;
            document.body.appendChild(lightbox);
            
            // Add close button handler
            const closeButton = lightbox.querySelector('.twintack-lightbox-close');
            closeButton.addEventListener('click', closeLightbox);
            
            // Close lightbox when clicking outside
            lightbox.addEventListener('click', (e) => {
                if (e.target === lightbox) {
                    closeLightbox();
                }
            });
            
            // Close lightbox with Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    closeLightbox();
                }
            });
        }
        
        // Add click handlers to lightbox triggers
        document.querySelectorAll('.product-lightbox').forEach(trigger => {
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                openLightbox(e.currentTarget.href, e.currentTarget.querySelector('img').alt);
            });
        });
    }
    
    /**
     * Open lightbox with image
     */
    function openLightbox(imageUrl, imageAlt) {
        const lightbox = document.querySelector('.twintack-lightbox');
        const image = lightbox.querySelector('.twintack-lightbox-image');
        
        image.src = imageUrl;
        image.alt = imageAlt;
        lightbox.classList.add('active');
        
        // Prevent body scrolling
        document.body.style.overflow = 'hidden';
    }
    
    /**
     * Close lightbox
     */
    function closeLightbox() {
        const lightbox = document.querySelector('.twintack-lightbox');
        lightbox.classList.remove('active');
        
        // Restore body scrolling
        document.body.style.overflow = '';
    }
    
    /**
     * Initialize Add to Cart functionality
     */
    function initializeAddToCart() {
        if (typeof twintackCarousel === 'undefined' || !twintackCarousel.ajaxurl || !twintackCarousel.nonce) {
            console.error('Required AJAX data not found');
            return;
        }

        // Add click handlers to all Add to Cart buttons
        document.querySelectorAll('.button.add-to-cart').forEach(button => {
            button.addEventListener('click', handleAddToCart);
        });
    }
    
    /**
     * Handle Add to Cart button click
     */
    function handleAddToCart(e) {
        e.preventDefault();
        
        const button = e.currentTarget;
        const productId = button.dataset.productId;
        const variationId = button.dataset.variationId;
        
        if (!productId) {
            console.error('No product ID found');
            return;
        }
        
        // Add loading state
        button.disabled = true;
        button.textContent = 'Adding...';
        
        // Prepare data for AJAX request
        const data = {
            action: 'twintack_add_to_cart',
            product_id: productId,
            nonce: twintackCarousel.nonce,
        };
        
        // Add variation ID if available
        if (variationId) {
            data.variation_id = variationId;
        }
        
        // Send AJAX request
        $.ajax({
            url: twintackCarousel.ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    // Update cart count in header if element exists
                    const cartCount = document.querySelector('.cart-contents-count');
                    if (cartCount) {
                        cartCount.textContent = response.data.cart_count;
                    }
                    
                    // Show success message
                    showNotification('Product added to cart successfully!', 'success');
                    
                    // Trigger cart update event
                    $(document.body).trigger('wc_fragment_refresh');
                } else {
                    showNotification(response.data.message || 'Failed to add product to cart.', 'error');
                }
            },
            error: function() {
                showNotification('An error occurred. Please try again.', 'error');
            },
            complete: function() {
                // Reset button state
                button.disabled = false;
                button.textContent = 'Add to Cart';
            },
        });
    }
    
    /**
     * Show notification message
     */
    function showNotification(message, type = 'success') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `twintack-notification ${type}`;
        notification.textContent = message;
        
        // Add to page
        document.body.appendChild(notification);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    /**
     * Handle empty state
     */
    function handleEmptyState() {
        const carousel = document.querySelector('.twintack-carousel');
        if (carousel) {
            carousel.classList.add('empty');
            carousel.innerHTML = '<p class="no-items">No products available.</p>';
        }
    }
    
})(jQuery); 