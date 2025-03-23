<?php
/**
 * Template part for displaying how-to videos on sport pages
 *
 * @package TwinTack2025
 */
?>

<div class="sport-how-to-videos">
    <?php
    // Get the current page title to determine the sport
    $page_title = strtolower(get_the_title());
    $sport = sanitize_title($page_title); // Default to page title as sport slug
    
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
                                       onclick="console.log('Play button clicked: <?php echo esc_js($video['vimeo_url']); ?>', this.getAttribute('data-video-url'));"
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

.carousel-prev:hover, .carousel-next:hover {
    transform: translateY(-50%) scale(1.1);
    background-color: #f0f0f0;
}

.carousel-prev {
    left: 0;
}

.carousel-next {
    right: 0;
}

/* Responsive styles */
@media (max-width: 768px) {
    .video-slide {
        flex: 0 0 calc(50% - 20px);
    }
}

@media (max-width: 576px) {
    .video-slide {
        flex: 0 0 calc(100% - 20px);
    }
    
    .video-carousel-container {
        padding: 0 30px;
    }
}
</style>

<!-- The video modal is now created dynamically via JavaScript --> 