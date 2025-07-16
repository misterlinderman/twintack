/**
 * TwinTack How-To Videos Frontend JavaScript
 * 
 * @package TwinTackHowToVideos
 */

(function() {
    'use strict';
    
    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        console.log('TwinTack HTV: DOM loaded, initializing...');
        initVideoCarousel();
        initVideoModal();
        
        // Set up mutation observer to watch for dynamically added video content
        const observer = new MutationObserver(function(mutations) {
            let videoContentAdded = false;
            
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        if (node.classList && (node.classList.contains('twintack-video-list') || 
                            node.classList.contains('twintack-video-grid') ||
                            node.classList.contains('twintack-video-carousel-container'))) {
                            videoContentAdded = true;
                        }
                        
                        // Also check if any descendant has video classes
                        if (node.querySelector && (node.querySelector('.twintack-play-button') || 
                            node.querySelector('.twintack-video-list') || 
                            node.querySelector('.twintack-video-grid'))) {
                            videoContentAdded = true;
                        }
                    }
                });
            });
            
            if (videoContentAdded) {
                console.log('TwinTack HTV: Video content added dynamically, re-initializing...');
                initVideoModal();
            }
        });
        
        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
    
    // Also initialize after a short delay to catch dynamically loaded content
    setTimeout(function() {
        console.log('TwinTack HTV: Delayed initialization...');
        if (!document.getElementById('video-modal')) {
            initVideoModal();
        }
    }, 1000);

    function initVideoCarousel() {
        const carousels = document.querySelectorAll('.twintack-video-carousel-container');
        
        if (carousels.length > 0) {
            console.log('TwinTack HTV: Found ' + carousels.length + ' carousels');
        }
        
        carousels.forEach(carousel => {
            const track = carousel.querySelector('.twintack-video-carousel-slides');
            const slides = carousel.querySelectorAll('.twintack-video-slide');
            const prevBtn = carousel.querySelector('.twintack-carousel-prev');
            const nextBtn = carousel.querySelector('.twintack-carousel-next');
            const indicators = carousel.querySelectorAll('.twintack-carousel-indicator');
            
            const slideCount = slides.length;
            if (slideCount === 0) return;
            
            // Calculate videos per page based on viewport
            let videosPerPage = getVideosPerPage();
            let currentPage = 0;
            let totalPages = Math.ceil(slideCount / videosPerPage);
            
            // If we only have one page worth of videos, hide navigation
            if (totalPages <= 1) {
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
            
            // Update indicators to reflect pages, not individual videos
            updateIndicators();
            
            // Set initial position
            updateCarouselPosition();
            
            // Previous button
            if (prevBtn) {
                prevBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (currentPage > 0) {
                        currentPage--;
                        updateCarouselPosition();
                        updateIndicators();
                    }
                });
            }
            
            // Next button
            if (nextBtn) {
                nextBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (currentPage < totalPages - 1) {
                        currentPage++;
                        updateCarouselPosition();
                        updateIndicators();
                    }
                });
            }
            
            // Indicator clicks
            indicators.forEach((indicator, index) => {
                // Only show indicators for the number of pages we have
                if (index < totalPages) {
                    indicator.style.display = 'block';
                    indicator.addEventListener('click', function(e) {
                        e.preventDefault();
                        currentPage = index;
                        updateCarouselPosition();
                        updateIndicators();
                    });
                } else {
                    indicator.style.display = 'none';
                }
            });
            
            // Window resize handler
            let resizeTimeout;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(function() {
                    const newVideosPerPage = getVideosPerPage();
                    if (newVideosPerPage !== videosPerPage) {
                        videosPerPage = newVideosPerPage;
                        totalPages = Math.ceil(slideCount / videosPerPage);
                        
                        // Adjust current page if necessary
                        if (currentPage >= totalPages) {
                            currentPage = totalPages - 1;
                        }
                        
                        updateCarouselPosition();
                        updateIndicators();
                    }
                }, 250);
            });
            
            function updateCarouselPosition() {
                const videosToSkip = currentPage * videosPerPage;
                const translatePercentage = (videosToSkip / slideCount) * 100;
                
                track.style.transform = `translateX(-${translatePercentage}%)`;
                
                // Update button states
                if (prevBtn) {
                    prevBtn.disabled = currentPage === 0;
                    prevBtn.style.opacity = currentPage === 0 ? '0.5' : '1';
                }
                if (nextBtn) {
                    nextBtn.disabled = currentPage === totalPages - 1;
                    nextBtn.style.opacity = currentPage === totalPages - 1 ? '0.5' : '1';
                }
            }
            
            function updateIndicators() {
                indicators.forEach((indicator, index) => {
                    if (index < totalPages) {
                        indicator.style.display = 'block';
                        if (index === currentPage) {
                            indicator.classList.add('active');
                        } else {
                            indicator.classList.remove('active');
                        }
                    } else {
                        indicator.style.display = 'none';
                    }
                });
            }
        });
    }

    function getVideosPerPage() {
        const width = window.innerWidth;
        if (width < 768) {
            return 1; // Mobile: 1 video per page
        } else if (width < 1024) {
            return 2; // Tablet: 2 videos per page
        } else {
            return 3; // Desktop: 3 videos per page
        }
    }

    function initVideoModal() {
        console.log('TwinTack HTV: Initializing video modal...');
        
        // Debug: Count play buttons found
        const playButtons = document.querySelectorAll('.twintack-play-button, .play-button');
        console.log('TwinTack HTV: Found ' + playButtons.length + ' play buttons on page');
        
        // Debug: Log button details
        playButtons.forEach((btn, index) => {
            const url = btn.getAttribute('data-video-url');
            const title = btn.getAttribute('data-video-title');
            console.log('TwinTack HTV: Button ' + index + ' - URL:', url, 'Title:', title);
        });
        
        // Check if modal already exists
        let modal = document.getElementById('video-modal');
        let videoContainer = document.getElementById('video-container');
        let closeBtn = document.getElementById('close-video-modal');

        // If modal doesn't exist, create it
        if (!modal) {
            console.log('TwinTack HTV: Creating modal with legacy structure');
            const modalHTML = `
                <div id="video-modal" style="position:fixed;top:0;left:0;right:0;bottom:0;z-index:99999;display:none;align-items:center;justify-content:center;background-color:rgba(0,0,0,0.85);">
                    <div class="video-modal-content" style="position:relative;width:90%;max-width:1000px;background-color:#000;border-radius:8px;overflow:hidden;">
                        <button id="close-video-modal" style="position:absolute;top:1rem;right:1rem;z-index:10;background:rgba(0,0,0,0.5);border:none;color:white;cursor:pointer;padding:0.5rem;border-radius:50%;" aria-label="Close video">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                        <div id="video-container" style="position:relative;width:100%;aspect-ratio:16/9;background-color:#000;"></div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            // Re-get elements after creation
            modal = document.getElementById('video-modal');
            videoContainer = document.getElementById('video-container');
            closeBtn = document.getElementById('close-video-modal');
            
            console.log('TwinTack HTV: Modal created successfully');
        } else {
            console.log('TwinTack HTV: Modal already exists');
        }

        if (!modal || !videoContainer) {
            console.error('TwinTack HTV: Video modal elements not found after creation attempt');
            return;
        }

        // Remove any existing event listeners to avoid duplicates
        const existingHandler = modal._twintackClickHandler;
        if (existingHandler) {
            document.removeEventListener('click', existingHandler);
        }

        // Handle play button clicks with event delegation
        const clickHandler = function(e) {
            console.log('TwinTack HTV: Click detected on:', e.target);
            
            const playButton = e.target.closest('.twintack-play-button') || e.target.closest('.play-button');
            
            console.log('TwinTack HTV: Play button found:', playButton);
            
            if (!playButton) {
                console.log('TwinTack HTV: No play button found for click');
                return;
            }

            console.log('TwinTack HTV: Play button clicked');
            e.preventDefault();
            e.stopPropagation();
            
            const videoUrl = playButton.getAttribute('data-video-url');
            const videoTitle = playButton.getAttribute('data-video-title');
            
            console.log('TwinTack HTV: Video URL:', videoUrl);
            console.log('TwinTack HTV: Video Title:', videoTitle);
            
            if (!videoUrl) {
                console.error('TwinTack HTV: No video URL provided');
                return;
            }

            console.log('TwinTack HTV: Loading video:', videoUrl);

            // Extract Vimeo ID and create embed URL
            const vimeoId = extractVimeoId(videoUrl);
            if (!vimeoId) {
                console.error('TwinTack HTV: Invalid Vimeo URL format');
                return;
            }

            const embedUrl = `https://player.vimeo.com/video/${vimeoId}?autoplay=1&title=0&byline=0&portrait=0`;
            
            // Create and add the iframe
            const iframe = document.createElement('iframe');
            iframe.src = embedUrl;
            iframe.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;border:none;';
            iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture');
            iframe.setAttribute('allowfullscreen', 'true');
            
            // Clear previous content and add new iframe
            videoContainer.innerHTML = '';
            videoContainer.appendChild(iframe);
            
            // Show modal
            console.log('TwinTack HTV: Attempting to show modal...');
            console.log('TwinTack HTV: Modal element:', modal);
            console.log('TwinTack HTV: Modal current display style:', modal.style.display);
            console.log('TwinTack HTV: Modal computed styles before:', window.getComputedStyle(modal).display);
            
            // Force display with multiple methods to override any conflicts
            modal.style.cssText = 'position:fixed!important;top:0!important;left:0!important;right:0!important;bottom:0!important;z-index:999999!important;display:flex!important;align-items:center!important;justify-content:center!important;background-color:rgba(0,0,0,0.85)!important;';
            modal.style.setProperty('display', 'flex', 'important');
            modal.style.setProperty('visibility', 'visible', 'important');
            modal.style.setProperty('opacity', '1', 'important');
            
            // Also ensure it's not hidden by other means
            modal.removeAttribute('hidden');
            modal.classList.remove('hidden');
            
            document.body.classList.add('twintack-modal-open');
            document.body.style.overflow = 'hidden';
            
            console.log('TwinTack HTV: Modal display style after setting:', modal.style.display);
            console.log('TwinTack HTV: Modal computed styles after:', window.getComputedStyle(modal).display);
            
            // Double check after a brief delay
            setTimeout(function() {
                console.log('TwinTack HTV: Modal styles after delay - display:', window.getComputedStyle(modal).display, 'visibility:', window.getComputedStyle(modal).visibility);
                if (window.getComputedStyle(modal).display === 'none') {
                    console.error('TwinTack HTV: Modal is still hidden! Something is overriding our styles.');
                    // Force it again
                    modal.style.cssText = 'position:fixed!important;top:0!important;left:0!important;right:0!important;bottom:0!important;z-index:999999!important;display:flex!important;align-items:center!important;justify-content:center!important;background-color:rgba(0,0,0,0.85)!important;';
                }
            }, 100);
            
            console.log('TwinTack HTV: Modal displayed');
        };

        // Store reference for cleanup
        modal._twintackClickHandler = clickHandler;
        document.addEventListener('click', clickHandler);

        console.log('TwinTack HTV: Click handler attached to document');

        // Close modal function
        function closeVideoModal() {
            console.log('TwinTack HTV: Closing modal');
            modal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;z-index:99999;display:none;align-items:center;justify-content:center;background-color:rgba(0,0,0,0.85);';
            videoContainer.innerHTML = '';
            document.body.classList.remove('twintack-modal-open');
            document.body.style.overflow = '';
        }

        // Close modal on close button click
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                closeVideoModal();
            });
        }

        // Close modal on background overlay click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeVideoModal();
            }
        });

        // ESC key to close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display === 'flex') {
                closeVideoModal();
            }
        });

        console.log('TwinTack HTV: Modal initialization complete');
    }

    function extractVimeoId(url) {
        const regex = /(?:vimeo\.com\/(?:.*\/)?(?:video\/)?|player\.vimeo\.com\/video\/)(\d+)/;
        const match = url.match(regex);
        return match ? match[1] : null;
    }
})(); 