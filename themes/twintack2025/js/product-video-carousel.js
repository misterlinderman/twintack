/**
 * Enhanced Product Video Carousel functionality
 * 
 * @package TwinTack2025
 */

(function() {
    document.addEventListener('DOMContentLoaded', function() {
        initVideoCarousel();
        initVideoModal();
    });

    function initVideoCarousel() {
        const carousels = document.querySelectorAll('.video-carousel-container');
        
        carousels.forEach(carousel => {
            const track = carousel.querySelector('.video-carousel-slides');
            const slides = carousel.querySelectorAll('.video-slide');
            const prevBtn = carousel.querySelector('.carousel-prev');
            const nextBtn = carousel.querySelector('.carousel-next');
            const indicators = carousel.querySelectorAll('.carousel-indicator');
            
            let activeSlide = 0;
            const slideCount = slides.length;
            
            if (slideCount <= 1) {
                // Hide navigation buttons if only one slide
                if (prevBtn) prevBtn.style.display = 'none';
                if (nextBtn) nextBtn.style.display = 'none';
                if (indicators.length) {
                    const indicatorsContainer = indicators[0].parentElement;
                    if (indicatorsContainer) {
                        indicatorsContainer.style.display = 'none';
                    }
                }
                return;
            }
            
            // Set initial position
            updateCarouselPosition();
            
            // Next button click
            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    activeSlide = (activeSlide + 1) % slideCount;
                    updateCarouselPosition();
                });
            }
            
            // Previous button click
            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    activeSlide = (activeSlide - 1 + slideCount) % slideCount;
                    updateCarouselPosition();
                });
            }
            
            // Indicator buttons
            indicators.forEach((indicator, index) => {
                indicator.addEventListener('click', () => {
                    activeSlide = index;
                    updateCarouselPosition();
                });
            });
            
            // Touch swipe support
            let touchStartX = 0;
            let touchEndX = 0;
            
            carousel.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
            }, { passive: true });
            
            carousel.addEventListener('touchend', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
            }, { passive: true });
            
            function handleSwipe() {
                const swipeThreshold = 50;
                const swipeDistance = touchEndX - touchStartX;
                
                if (Math.abs(swipeDistance) > swipeThreshold) {
                    if (swipeDistance > 0) {
                        // Swiped right, go to previous
                        activeSlide = (activeSlide - 1 + slideCount) % slideCount;
                    } else {
                        // Swiped left, go to next
                        activeSlide = (activeSlide + 1) % slideCount;
                    }
                    updateCarouselPosition();
                }
            }
            
            // Keyboard navigation
            document.addEventListener('keydown', function(e) {
                // Only process keyboard events if at least one carousel is in the viewport
                const rect = carousel.getBoundingClientRect();
                const isInViewport = rect.top < window.innerHeight && rect.bottom >= 0;
                
                if (!isInViewport) return;
                
                if (e.key === 'ArrowRight') {
                    activeSlide = (activeSlide + 1) % slideCount;
                    updateCarouselPosition();
                } else if (e.key === 'ArrowLeft') {
                    activeSlide = (activeSlide - 1 + slideCount) % slideCount;
                    updateCarouselPosition();
                }
            });
            
            function updateCarouselPosition() {
                // Update slides transform
                track.style.transform = `translateX(-${activeSlide * 100}%)`;
                
                // Update active slide attribute
                track.setAttribute('data-active-slide', activeSlide);
                
                // Update indicators
                indicators.forEach((indicator, index) => {
                    if (index === activeSlide) {
                        indicator.classList.add('active');
                    } else {
                        indicator.classList.remove('active');
                    }
                });
                
                // Update button states - optional: add visual disabled state
                if (slideCount > 1) {
                    prevBtn.disabled = false;
                    nextBtn.disabled = false;
                }
            }
            
            // Auto-rotate carousel (optional)
            // Uncomment this section if you want auto-rotation
            /*
            let autoRotateInterval;
            
            function startAutoRotate() {
                autoRotateInterval = setInterval(() => {
                    activeSlide = (activeSlide + 1) % slideCount;
                    updateCarouselPosition();
                }, 5000); // Change slide every 5 seconds
            }
            
            function stopAutoRotate() {
                clearInterval(autoRotateInterval);
            }
            
            // Start auto-rotation
            startAutoRotate();
            
            // Pause on hover
            carousel.addEventListener('mouseenter', stopAutoRotate);
            carousel.addEventListener('mouseleave', startAutoRotate);
            */
            
            // Update carousel on window resize for responsive behavior
            window.addEventListener('resize', updateCarouselPosition);
        });
    }

    function initVideoModal() {
        const modal = document.getElementById('video-modal');
        const videoContainer = document.getElementById('video-container');
        const modalTitle = document.getElementById('video-modal-title');
        
        if (!modal || !videoContainer) return;
        
        // Ensure modal is appended to body
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        
        const playButtons = document.querySelectorAll('.play-button');
        const closeButton = document.getElementById('close-video-modal');
        const modalOverlay = modal ? modal.querySelector('.video-modal-overlay') : null;
        
        // Open modal on play button click
        playButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation(); // Stop event propagation
                
                const videoUrl = this.getAttribute('data-video-url');
                const videoTitle = this.getAttribute('data-video-title');
                
                if (!videoUrl) return;
                
                // Set modal title if available
                if (modalTitle && videoTitle) {
                    modalTitle.textContent = videoTitle;
                }
                
                // Parse Vimeo URL to get video ID
                let vimeoId = '';
                const vimeoRegex = /vimeo\.com\/(?:video\/)?(\d+)/;
                const match = videoUrl.match(vimeoRegex);
                
                if (match && match[1]) {
                    vimeoId = match[1];
                } else {
                    console.error('Invalid Vimeo URL format');
                    return;
                }
                
                // Create and add the iframe
                const iframe = document.createElement('iframe');
                iframe.src = `https://player.vimeo.com/video/${vimeoId}?autoplay=1&title=0&byline=0&portrait=0`;
                iframe.setAttribute('style', 'position:absolute;top:0;left:0;width:100%;height:100%;');
                iframe.setAttribute('frameborder', '0');
                iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture');
                iframe.setAttribute('allowfullscreen', '');
                
                // Clear previous content and add new iframe
                videoContainer.innerHTML = '';
                videoContainer.appendChild(iframe);
                
                // Show modal with animation
                modal.classList.add('active');
                document.body.classList.add('modal-open'); // Prevent background scrolling
                
                // Add animation classes
                setTimeout(() => {
                    modal.classList.add('animate-in');
                }, 10); // Small delay to ensure the transition works
            });
        });
        
        // Close modal functions
        function closeVideoModal() {
            modal.classList.remove('animate-in');
            
            // Wait for animation to complete before hiding
            setTimeout(() => {
                modal.classList.remove('active');
                videoContainer.innerHTML = ''; // Remove iframe
                document.body.classList.remove('modal-open'); // Re-enable scrolling
            }, 300); // Match this with the CSS transition duration
        }
        
        // Close modal on close button click
        if (closeButton) {
            closeButton.addEventListener('click', function(e) {
                e.preventDefault();
                closeVideoModal();
            });
        }
        
        // Close modal on background overlay click
        if (modalOverlay) {
            modalOverlay.addEventListener('click', function(e) {
                closeVideoModal();
            });
        }
        
        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeVideoModal();
            }
        });
    }
})(); 