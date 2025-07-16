<?php
/**
 * Template for displaying videos in a simple layout
 *
 * This template can be overridden by copying it to yourtheme/twintack-how-to-videos/video-simple.php
 * Best for 1-3 videos in a clean, minimal layout
 *
 * @package TwinTackHowToVideos
 * @var array $videos Array of video data
 * @var array $args Display arguments
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// If videos variable is not set, return
if (!isset($videos) || empty($videos)) {
    return;
}

// Set default title if not provided
$section_title = !empty($args['title']) ? $args['title'] : __('Videos', 'twintack-how-to-videos');
$wrapper_class = 'twintack-video-simple-section';
if (!empty($args['class'])) {
    $wrapper_class .= ' ' . esc_attr($args['class']);
}
?>

<div class="<?php echo esc_attr($wrapper_class); ?>">
    <div class="container">
        <?php if ($args['show_title']) : ?>
            <h2 class="twintack-section-title"><?php echo esc_html($section_title); ?></h2>
        <?php endif; ?>
        
        <div class="twintack-video-simple-wrapper">
            <?php foreach ($videos as $index => $video) : ?>
                <div class="twintack-video-simple-item" data-index="<?php echo esc_attr($index); ?>">
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
</div>

<style>
/* Simple Layout Specific Styles */
.twintack-video-simple-section {
    padding: 2rem 0;
}

.twintack-video-simple-wrapper {
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
    justify-content: center;
    margin-top: 1.5rem;
}

.twintack-video-simple-item {
    flex: 0 1 auto;
    max-width: 320px;
    min-width: 280px;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.twintack-video-simple-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
}

.twintack-video-simple-wrapper .twintack-video-thumbnail {
    aspect-ratio: 16/9;
    position: relative;
    overflow: hidden;
}

.twintack-video-simple-wrapper .twintack-video-info {
    padding: 1.25rem;
    text-align: center;
}

.twintack-video-simple-wrapper .twintack-video-title {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    line-height: 1.4;
    color: #333;
}

.twintack-video-simple-wrapper .twintack-video-excerpt {
    font-size: 0.9rem;
    color: #666;
    line-height: 1.5;
    margin-bottom: 0;
}

/* Single video layout */
.twintack-video-simple-wrapper:has(.twintack-video-simple-item:only-child) {
    justify-content: center;
}

.twintack-video-simple-wrapper .twintack-video-simple-item:only-child {
    max-width: 400px;
}

/* Two video layout */
.twintack-video-simple-wrapper:has(.twintack-video-simple-item:nth-child(2):last-child) {
    justify-content: center;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .twintack-video-simple-wrapper {
        gap: 1.5rem;
    }
    
    .twintack-video-simple-item {
        min-width: 250px;
        max-width: 100%;
    }
    
    .twintack-video-simple-wrapper .twintack-video-info {
        padding: 1rem;
    }
}

@media (max-width: 480px) {
    .twintack-video-simple-wrapper {
        flex-direction: column;
        align-items: center;
        gap: 1rem;
    }
    
    .twintack-video-simple-item {
        width: 100%;
        max-width: 100%;
        min-width: auto;
    }
}
</style> 