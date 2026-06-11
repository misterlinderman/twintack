/**
 * TwinTack How-To Videos Frontend JavaScript
 *
 * @package TwinTackHowToVideos
 */

(function() {
    'use strict';

    let clickHandlerAttached = false;

    document.addEventListener('DOMContentLoaded', function() {
        initVideoCarousel();
        initVideoModal();

        const observer = new MutationObserver(function(mutations) {
            let videoContentAdded = false;

            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType !== 1) {
                        return;
                    }

                    if (node.classList && (node.classList.contains('twintack-video-list') ||
                        node.classList.contains('twintack-video-grid') ||
                        node.classList.contains('twintack-video-carousel-container'))) {
                        videoContentAdded = true;
                    }

                    if (node.querySelector && (node.querySelector('.twintack-play-button') ||
                        node.querySelector('.twintack-video-list') ||
                        node.querySelector('.twintack-video-grid'))) {
                        videoContentAdded = true;
                    }
                });
            });

            if (videoContentAdded) {
                ensureVideoModal();
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });

    function initVideoCarousel() {
        const carousels = document.querySelectorAll('.twintack-video-carousel-container');

        carousels.forEach(function(carousel) {
            const track = carousel.querySelector('.twintack-video-carousel-slides');
            const slides = carousel.querySelectorAll('.twintack-video-slide');
            const prevBtn = carousel.querySelector('.twintack-carousel-prev');
            const nextBtn = carousel.querySelector('.twintack-carousel-next');
            const indicators = carousel.querySelectorAll('.twintack-carousel-indicator');

            const slideCount = slides.length;
            if (slideCount === 0) {
                return;
            }

            let videosPerPage = getVideosPerPage();
            let currentPage = 0;
            let totalPages = Math.ceil(slideCount / videosPerPage);

            if (totalPages <= 1) {
                if (prevBtn) {
                    prevBtn.style.display = 'none';
                }
                if (nextBtn) {
                    nextBtn.style.display = 'none';
                }
                if (indicators.length) {
                    const indicatorsContainer = indicators[0].parentElement;
                    if (indicatorsContainer) {
                        indicatorsContainer.style.display = 'none';
                    }
                }
                return;
            }

            updateIndicators();

            updateCarouselPosition();

            if (prevBtn) {
                prevBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (currentPage > 0) {
                        currentPage -= 1;
                        updateCarouselPosition();
                        updateIndicators();
                    }
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (currentPage < totalPages - 1) {
                        currentPage += 1;
                        updateCarouselPosition();
                        updateIndicators();
                    }
                });
            }

            indicators.forEach(function(indicator, index) {
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

            let resizeTimeout;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(function() {
                    const newVideosPerPage = getVideosPerPage();
                    if (newVideosPerPage !== videosPerPage) {
                        videosPerPage = newVideosPerPage;
                        totalPages = Math.ceil(slideCount / videosPerPage);

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

                track.style.transform = 'translateX(-' + translatePercentage + '%)';

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
                indicators.forEach(function(indicator, index) {
                    if (index < totalPages) {
                        indicator.style.display = 'block';
                        indicator.classList.toggle('active', index === currentPage);
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
            return 1;
        }
        if (width < 1024) {
            return 2;
        }
        return 3;
    }

    function ensureVideoModal() {
        let modal = document.getElementById('video-modal');
        let videoContainer = document.getElementById('video-container');

        if (modal && videoContainer) {
            return { modal: modal, videoContainer: videoContainer };
        }

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

        modal = document.getElementById('video-modal');
        videoContainer = document.getElementById('video-container');

        if (!modal || !videoContainer) {
            return null;
        }

        return { modal: modal, videoContainer: videoContainer };
    }

    function initVideoModal() {
        const elements = ensureVideoModal();
        if (!elements) {
            return;
        }

        const modal = elements.modal;
        const videoContainer = elements.videoContainer;
        const closeBtn = document.getElementById('close-video-modal');

        function closeVideoModal() {
            modal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;z-index:99999;display:none;align-items:center;justify-content:center;background-color:rgba(0,0,0,0.85);';
            videoContainer.innerHTML = '';
            document.body.classList.remove('twintack-modal-open');
            document.body.style.overflow = '';
        }

        if (!clickHandlerAttached) {
            document.addEventListener('click', function(e) {
                const playButton = e.target.closest('.twintack-play-button, .play-button');
                if (!playButton) {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();

                const videoUrl = playButton.getAttribute('data-video-url');
                if (!videoUrl) {
                    return;
                }

                const vimeoId = extractVimeoId(videoUrl);
                if (!vimeoId) {
                    return;
                }

                const embedUrl = 'https://player.vimeo.com/video/' + vimeoId + '?autoplay=1&title=0&byline=0&portrait=0';
                const iframe = document.createElement('iframe');
                iframe.src = embedUrl;
                iframe.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;border:none;';
                iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture');
                iframe.setAttribute('allowfullscreen', 'true');

                videoContainer.innerHTML = '';
                videoContainer.appendChild(iframe);

                modal.style.cssText = 'position:fixed!important;top:0!important;left:0!important;right:0!important;bottom:0!important;z-index:999999!important;display:flex!important;align-items:center!important;justify-content:center!important;background-color:rgba(0,0,0,0.85)!important;';
                modal.removeAttribute('hidden');
                modal.classList.remove('hidden');
                document.body.classList.add('twintack-modal-open');
                document.body.style.overflow = 'hidden';
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.style.display === 'flex') {
                    closeVideoModal();
                }
            });

            clickHandlerAttached = true;
        }

        if (closeBtn && !closeBtn.dataset.twintackHtvBound) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                closeVideoModal();
            });
            closeBtn.dataset.twintackHtvBound = '1';
        }

        if (!modal.dataset.twintackHtvBound) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeVideoModal();
                }
            });
            modal.dataset.twintackHtvBound = '1';
        }
    }

    function extractVimeoId(url) {
        const regex = /(?:vimeo\.com\/(?:.*\/)?(?:video\/)?|player\.vimeo\.com\/video\/)(\d+)/;
        const match = url.match(regex);
        return match ? match[1] : null;
    }
})();
