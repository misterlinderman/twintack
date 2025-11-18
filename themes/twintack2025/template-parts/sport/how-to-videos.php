<?php
/**
 * Template part for displaying how-to videos on sport pages and product pages
 *
 * @package TwinTack2025
 */
?>

<div class="sport-how-to-videos">
    <?php
    // Determine the sport based on context
    $sport = '';
    if (is_product()) {
        $product_id = get_the_ID();
        $sport = twintack_get_product_sport($product_id);
    } else {
        $page_title = strtolower(get_the_title());
        $sport = sanitize_title($page_title);
    }
    
    // Get videos for this sport
    $videos = twintack_get_how_to_videos_by_sport($sport);
    
    if (empty($videos)) {
        return; // Don't show anything if no videos
    }
    ?>
    
    <div class="video-carousel-section py-12">
        <div class="container">
            <h2 class="section-title">How-To Videos</h2>
            
            <div class="video-carousel-container relative">
                <!-- Video Carousel Track -->
                <div class="video-carousel-track overflow-hidden">
                    <div class="video-carousel-slides flex transition-transform duration-500 ease-out" 
                         data-active-slide="0">
                        <?php foreach ($videos as $index => $video) : ?>
                            <div class="video-slide" data-index="<?php echo esc_attr($index); ?>">
                                <!-- Video Thumbnail -->
                                <div class="video-thumbnail">
                                    <?php if (!empty($video['thumbnail'])) : ?>
                                        <img src="<?php echo esc_url($video['thumbnail']); ?>" alt="<?php echo esc_attr($video['title']); ?>" class="thumbnail-image">
                                    <?php else: ?>
                                        <div class="thumbnail-placeholder">
                                            <span class="placeholder-text">No thumbnail</span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($video['duration'])) : ?>
                                        <span class="video-duration"><?php echo esc_html($video['duration']); ?></span>
                                    <?php endif; ?>
                                    
                                    <!-- Play Button Overlay -->
                                    <a href="#" 
                                       class="play-button" 
                                       data-video-url="<?php echo esc_url($video['vimeo_url']); ?>"
                                       data-video-title="<?php echo esc_attr($video['title']); ?>"
                                       aria-label="Play video">
                                        <svg class="play-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <circle cx="12" cy="12" r="10" fill="currentColor" fill-opacity="0.7"/>
                                            <path d="M15.5 12L10 15.5V8.5L15.5 12Z" fill="white"/>
                                        </svg>
                                    </a>
                                </div>
                                
                                <!-- Video Info -->
                                <div class="video-info">
                                    <h3 class="video-title"><?php echo esc_html($video['title']); ?></h3>
                                    <?php if (!empty($video['excerpt'])) : ?>
                                        <p class="video-excerpt"><?php echo esc_html($video['excerpt']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Navigation Buttons -->
                <button type="button" class="carousel-prev" aria-label="Previous video">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
                
                <button type="button" class="carousel-next" aria-label="Next video">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Video Modal -->
<div id="video-modal" class="video-modal fixed inset-0" style="z-index: 99999;">
    <div class="video-modal-overlay absolute inset-0 bg-black bg-opacity-85 backdrop-blur"></div>
    <div class="video-modal-container relative z-10 w-11/12 max-w-4xl mx-auto transform transition-all">
        <div class="video-modal-content bg-black rounded-xl overflow-hidden shadow-2xl">
            <button id="close-video-modal" class="video-modal-close absolute -top-10 right-0" aria-label="Close video">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            <div class="video-modal-header p-4 bg-gray-900">
                <h3 id="video-modal-title" class="text-white text-lg font-medium"></h3>
            </div>
            <div id="video-container" class="video-modal-player relative pt-[56.25%]"></div>
        </div>
    </div>
</div>

<style>
/* Sport How-To Videos Styles */
.sport-how-to-videos {
    padding: 60px 0;
    background-color: #f0f4f8;
}

.video-carousel-section {
    max-width: 1200px;
    margin: 0 auto;
}

.section-title {
    font-size: 3rem;
    margin-bottom: 30px;
    text-align: left;
    font-style: italic;
    letter-spacing: -0.05em;
}

.video-carousel-container {
    position: relative;
    padding: 0 40px;
}

.video-carousel-track {
    overflow: hidden;
}

.video-carousel-slides {
    display: flex;
    gap: 20px;
    transition: transform 0.5s ease;
    padding: 10px 0;
    align-items: flex-start;
}

.video-slide {
    flex: 0 0 calc(33.333% - 20px);
    min-width: 280px;
}

.video-thumbnail {
    position: relative;
    aspect-ratio: 16/9;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    background-color: #000;
}

.video-thumbnail:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
}

.thumbnail-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 0.85;
    transition: opacity 0.3s ease;
}

.video-thumbnail:hover .thumbnail-image {
    opacity: 0.7;
}

.thumbnail-placeholder {
    width: 100%;
    height: 100%;
    background-color: #343a40;
    display: flex;
    align-items: center;
    justify-content: center;
}

.placeholder-text {
    color: #adb5bd;
    font-size: 14px;
}

.play-button {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.2);
    transition: background 0.3s ease;
    z-index: 5;
}

.play-button:hover {
    background: rgba(0, 0, 0, 0.4);
}

.play-icon {
    width: 64px;
    height: 64px;
    color: white;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
    opacity: 0.9;
    transition: transform 0.3s ease, opacity 0.3s ease;
}

.play-button:hover .play-icon {
    transform: scale(1.1);
    opacity: 1;
}

.video-duration {
    position: absolute;
    bottom: 10px;
    right: 10px;
    background-color: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    z-index: 4;
}

.video-info {
    margin-top: 12px;
    text-align: center;
}

.video-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 8px;
}

.video-excerpt {
    font-size: 14px;
    color: #6c757d;
}

.carousel-prev, .carousel-next {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background-color: white;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    z-index: 10;
    transition: transform 0.2s, background-color 0.2s;
    cursor: pointer;
    border: none;
}

.carousel-prev {
    left: 0;
}

.carousel-next {
    right: 0;
}

.carousel-prev:hover, .carousel-next:hover {
    background-color: #f8f9fa;
    transform: translateY(-50%) scale(1.1);
}

/* Video Modal Styles */
.video-modal {
    display: none;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 99999;
}

.video-modal.active {
    display: flex;
}

.video-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(4px);
    transition: opacity 0.3s ease;
}

.video-modal-container {
    position: relative;
    z-index: 10;
    width: 91.666667%;
    max-width: 56rem;
    margin: 0 auto;
    transform: scale(0.95);
    opacity: 0;
    transition: transform 0.3s ease, opacity 0.3s ease;
}

.video-modal.active .video-modal-container {
    transform: scale(1);
    opacity: 1;
}

.video-modal-content {
    background-color: #000;
    border-radius: 0.75rem;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.video-modal-close {
    position: absolute;
    top: -2.5rem;
    right: 0;
    color: white;
    padding: 0.5rem;
    border-radius: 9999px;
    background: rgba(0, 0, 0, 0.5);
    transition: background-color 0.2s;
}

.video-modal-close:hover {
    background: rgba(0, 0, 0, 0.7);
}

.video-modal-header {
    padding: 1rem;
    background-color: #111827;
}

.video-modal-title {
    color: white;
    font-size: 1.125rem;
    font-weight: 500;
    margin: 0;
}

.video-modal-player {
    position: relative;
    padding-top: 56.25%;
    background: #000;
}

.video-modal-player iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border: none;
}

/* Responsive styles */
@media (max-width: 768px) {
    .video-slide {
        flex: 0 0 calc(50% - 20px);
    }
    
    .video-modal-container {
        width: 95%;
    }
}

@media (max-width: 576px) {
    .video-slide {
        flex: 0 0 calc(100% - 20px);
    }
    
    .video-carousel-container {
        padding: 0 30px;
    }
    
    .video-modal-container {
        width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const carousel = document.querySelector('.video-carousel-slides');
    const slides = document.querySelectorAll('.video-slide');
    const prevBtn = document.querySelector('.carousel-prev');
    const nextBtn = document.querySelector('.carousel-next');
    const modal = document.getElementById('video-modal');
    const closeBtn = document.getElementById('close-video-modal');
    const videoContainer = document.getElementById('video-container');
    const modalTitle = document.getElementById('video-modal-title');
    
    let currentSlide = 0;
    const slideWidth = slides[0].offsetWidth + 20; // Include gap
    const totalSlides = slides.length;
    const slidesToShow = Math.min(3, totalSlides);
    
    // Hide navigation buttons if we have 3 or fewer slides
    if (totalSlides <= 3) {
        prevBtn.style.display = 'none';
        nextBtn.style.display = 'none';
    }
    
    function updateCarousel() {
        carousel.style.transform = `translateX(-${currentSlide * slideWidth}px)`;
        carousel.setAttribute('data-active-slide', currentSlide);
        
        // Update button states
        prevBtn.disabled = currentSlide === 0;
        nextBtn.disabled = currentSlide >= totalSlides - slidesToShow;
    }
    
    function showVideo(videoUrl, title) {
        modalTitle.textContent = title;
        
        // Extract Vimeo video ID from URL
        const videoId = videoUrl.match(/vimeo\.com\/(\d+)/)?.[1];
        if (!videoId) return;
        
        // Create iframe with Vimeo embed
        const iframe = document.createElement('iframe');
        iframe.src = `https://player.vimeo.com/video/${videoId}?autoplay=1`;
        iframe.allow = 'autoplay; fullscreen';
        
        // Clear and add new iframe
        videoContainer.innerHTML = '';
        videoContainer.appendChild(iframe);
        
        // Show modal
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    // Event Listeners
    prevBtn.addEventListener('click', () => {
        if (currentSlide > 0) {
            currentSlide--;
            updateCarousel();
        }
    });
    
    nextBtn.addEventListener('click', () => {
        if (currentSlide < totalSlides - slidesToShow) {
            currentSlide++;
            updateCarousel();
        }
    });
    
    // Video play button click
    document.querySelectorAll('.play-button').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const videoUrl = button.getAttribute('data-video-url');
            const videoTitle = button.getAttribute('data-video-title');
            showVideo(videoUrl, videoTitle);
        });
    });
    
    // Close modal
    closeBtn.addEventListener('click', () => {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        videoContainer.innerHTML = '';
    });
    
    // Close on overlay click
    modal.querySelector('.video-modal-overlay').addEventListener('click', () => {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        videoContainer.innerHTML = '';
    });
    
    // Close on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
            videoContainer.innerHTML = '';
        }
    });
    
    // Initialize carousel
    updateCarousel();
});
</script> 