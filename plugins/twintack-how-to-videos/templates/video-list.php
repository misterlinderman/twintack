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

<style>
/* List Layout Specific Styles */
.twintack-video-list-section {
    padding: 2rem 0;
}

.twintack-video-list {
    margin-top: 2rem;
}

.twintack-video-list-item {
    display: flex;
    gap: 1.5rem;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    background: #fff;
    border-radius: 8px;
    border: 1px solid #e5e5e5;
    transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
}

.twintack-video-list-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    border-color: #d1d5db;
}

.twintack-video-list-item:last-child {
    margin-bottom: 0;
}

.twintack-video-list .twintack-video-thumbnail {
    flex: 0 0 200px;
    aspect-ratio: 16/9;
    position: relative;
    overflow: hidden;
    border-radius: 6px;
}

.twintack-video-list .twintack-video-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-width: 0; /* Allow text to wrap */
}

.twintack-video-list .twintack-video-title {
    font-size: 1.25rem;
    margin-bottom: 0.75rem;
    line-height: 1.4;
    color: #333;
    font-weight: 600;
}

.twintack-video-list .twintack-video-excerpt {
    font-size: 1rem;
    color: #666;
    line-height: 1.6;
    margin-bottom: 1rem;
    flex-grow: 1;
}

.twintack-video-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 0.875rem;
    color: #888;
}

.twintack-video-duration-meta {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.twintack-video-duration-meta svg {
    opacity: 0.7;
}

/* Play button adjustments for list layout */
.twintack-video-list .twintack-play-icon {
    width: 48px;
    height: 48px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .twintack-video-list .twintack-video-thumbnail {
        flex: 0 0 150px;
    }
    
    .twintack-video-list-item {
        gap: 1rem;
        padding: 1rem;
    }
    
    .twintack-video-list .twintack-video-title {
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
    }
    
    .twintack-video-list .twintack-video-excerpt {
        font-size: 0.9rem;
        margin-bottom: 0.75rem;
    }
}

@media (max-width: 480px) {
    .twintack-video-list-item {
        flex-direction: column;
        gap: 1rem;
    }
    
    .twintack-video-list .twintack-video-thumbnail {
        flex: none;
        width: 100%;
        max-width: 100%;
    }
    
    .twintack-video-list .twintack-video-content {
        text-align: center;
    }
}
</style> 