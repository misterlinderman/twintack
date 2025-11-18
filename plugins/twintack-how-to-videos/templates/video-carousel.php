<?php
/**
 * Template for displaying how-to videos carousel
 *
 * This template can be overridden by copying it to yourtheme/twintack-how-to-videos/video-carousel.php
 *
 * @package TwinTackHowToVideos
 * @var array $videos Array of video data
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// If videos variable is not set, try to get it from global context
if (!isset($videos)) {
    $sport = '';
    $limit = 5;
    
    // Try to determine sport from context
    if (is_product()) {
        $sport = twintack_htv_get_product_sport(get_the_ID());
    } else {
        $page_title = strtolower(get_the_title());
        $sport = sanitize_title($page_title);
    }
    
    $videos = twintack_htv_get_videos_by_sport($sport, $limit);
}

if (empty($videos)) {
    return;
}
?>

<div class="twintack-video-carousel-section">
    <div class="container">
        <h2 class="twintack-section-title"><?php _e('How-To Videos', 'twintack-how-to-videos'); ?></h2>
        
        <div class="twintack-video-carousel-container">
            <!-- Video Carousel Track -->
            <div class="twintack-video-carousel-track">
                <div class="twintack-video-carousel-slides" data-active-slide="0">
                    <?php foreach ($videos as $index => $video) : ?>
                        <div class="twintack-video-slide" data-index="<?php echo esc_attr($index); ?>">
                            <!-- Video Thumbnail -->
                            <div class="twintack-video-thumbnail">
                                <?php if (!empty($video['thumbnail'])) : ?>
                                    <img src="<?php echo esc_url($video['thumbnail']); ?>" 
                                         alt="<?php echo esc_attr($video['title']); ?>" 
                                         class="twintack-thumbnail-image">
                                <?php else: ?>
                                    <div class="twintack-thumbnail-placeholder">
                                        <span class="twintack-placeholder-text">
                                            <?php _e('No thumbnail', 'twintack-how-to-videos'); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($video['duration'])) : ?>
                                    <span class="twintack-video-duration"><?php echo esc_html($video['duration']); ?></span>
                                <?php endif; ?>
                                
                                <!-- Play Button Overlay -->
                                <a href="#" 
                                   class="twintack-play-button" 
                                   data-video-url="<?php echo esc_url($video['vimeo_url']); ?>"
                                   data-video-title="<?php echo esc_attr($video['title']); ?>"
                                   aria-label="<?php esc_attr_e('Play video', 'twintack-how-to-videos'); ?>">
                                    <svg class="twintack-play-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <circle cx="12" cy="12" r="10" fill="currentColor" fill-opacity="0.7"/>
                                        <path d="M15.5 12L10 15.5V8.5L15.5 12Z" fill="white"/>
                                    </svg>
                                </a>
                            </div>
                            
                            <!-- Video Info -->
                            <div class="twintack-video-info">
                                <h3 class="twintack-video-title"><?php echo esc_html($video['title']); ?></h3>
                                <?php if (!empty($video['excerpt'])) : ?>
                                    <p class="twintack-video-excerpt"><?php echo esc_html($video['excerpt']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Navigation Buttons -->
            <?php if (count($videos) > 1) : ?>
                <button type="button" class="twintack-carousel-prev" aria-label="<?php esc_attr_e('Previous video', 'twintack-how-to-videos'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
                
                <button type="button" class="twintack-carousel-next" aria-label="<?php esc_attr_e('Next video', 'twintack-how-to-videos'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
            <?php endif; ?>
            
            <!-- Indicators -->
            <?php if (count($videos) > 1) : ?>
                <div class="twintack-carousel-indicators">
                    <?php foreach ($videos as $index => $video) : ?>
                        <button type="button" 
                                class="twintack-carousel-indicator <?php echo $index === 0 ? 'active' : ''; ?>" 
                                data-slide="<?php echo esc_attr($index); ?>"
                                aria-label="<?php echo esc_attr(sprintf(__('Go to video %d', 'twintack-how-to-videos'), $index + 1)); ?>">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div> 