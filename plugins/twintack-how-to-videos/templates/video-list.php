<?php
/**
 * Template for displaying videos in a list layout
 *
 * This template can be overridden by copying it to yourtheme/twintack-how-to-videos/video-list.php
 * Displays videos in a vertical list with thumbnail and content side by side
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
$wrapper_class = 'twintack-video-list-section';
if (!empty($args['class'])) {
    $wrapper_class .= ' ' . esc_attr($args['class']);
}
?>

<div class="<?php echo esc_attr($wrapper_class); ?>">
    <div class="container">
        <?php if ($args['show_title']) : ?>
            <h2 class="twintack-section-title"><?php echo esc_html($section_title); ?></h2>
        <?php endif; ?>
        
        <div class="twintack-video-list">
            <?php foreach ($videos as $index => $video) : ?>
                <div class="twintack-video-list-item" data-index="<?php echo esc_attr($index); ?>">
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
                    
                    <!-- Video Content -->
                    <div class="twintack-video-content">
                        <h3 class="twintack-video-title"><?php echo esc_html($video['title']); ?></h3>
                        <?php if (!empty($video['excerpt'])) : ?>
                            <p class="twintack-video-excerpt"><?php echo esc_html($video['excerpt']); ?></p>
                        <?php endif; ?>
                        
                        <div class="twintack-video-meta">
                            <?php if (!empty($video['duration'])) : ?>
                                <span class="twintack-video-duration-meta">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/>
                                    </svg>
                                    <?php echo esc_html($video['duration']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div> 