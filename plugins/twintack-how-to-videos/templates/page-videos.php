<?php
/**
 * Template Name: Videos Page
 * 
 * Comprehensive video page template that displays videos organized by categories
 * Copy this file to your theme to use as a page template
 * 
 * @package TwinTackHowToVideos
 */

get_header(); ?>

<div class="twintack-videos-page">
    <div class="container">
        <?php while (have_posts()) : the_post(); ?>
            
            <!-- Page Header -->
            <header class="page-header">
                <h1 class="page-title"><?php the_title(); ?></h1>
                <?php if (get_the_content()) : ?>
                    <div class="page-description">
                        <?php the_content(); ?>
                    </div>
                <?php endif; ?>
            </header>
            
            <!-- Video Categories Section -->
            <div class="video-categories-section">
                <?php
                // Get all video categories
                $categories = get_terms(array(
                    'taxonomy' => 'video_category',
                    'hide_empty' => true,
                    'orderby' => 'name',
                    'order' => 'ASC',
                ));
                
                if (!empty($categories) && !is_wp_error($categories)) :
                    foreach ($categories as $category) :
                        // Get videos for this category
                        $videos = twintack_htv_get_videos_by_category($category->slug, 6);
                        
                        if (!empty($videos)) :
                ?>
                    
                    <section class="video-category-section" id="category-<?php echo esc_attr($category->slug); ?>">
                        <div class="category-header">
                            <h2 class="category-title"><?php echo esc_html($category->name); ?></h2>
                            <?php if (!empty($category->description)) : ?>
                                <p class="category-description"><?php echo esc_html($category->description); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <?php
                        // Display videos using grid layout for categories with many videos, simple for few
                        $layout = count($videos) > 3 ? 'grid' : 'simple';
                        twintack_htv_display_videos(array(
                            'category' => $category->slug,
                            'limit' => 6,
                            'layout' => $layout,
                            'show_title' => false,
                        ));
                        ?>
                        
                        <?php
                        // Show "View All" link if there are more videos
                        $total_videos = wp_count_posts('how_to_video');
                        $category_count = get_term($category->term_id)->count;
                        
                        if ($category_count > 6) :
                        ?>
                            <div class="category-view-all">
                                <a href="<?php echo get_term_link($category); ?>" class="view-all-link">
                                    <?php printf(__('View All %s Videos (%d)', 'twintack-how-to-videos'), $category->name, $category_count); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </section>
                    
                <?php 
                        endif;
                    endforeach;
                else :
                ?>
                    
                    <div class="no-videos-message">
                        <h3><?php _e('No Video Categories Found', 'twintack-how-to-videos'); ?></h3>
                        <p><?php _e('No video categories have been created yet. Please create some categories and assign videos to them.', 'twintack-how-to-videos'); ?></p>
                        
                        <?php if (current_user_can('manage_options')) : ?>
                            <p>
                                <a href="<?php echo admin_url('edit-tags.php?taxonomy=video_category&post_type=how_to_video'); ?>" class="button">
                                    <?php _e('Create Video Categories', 'twintack-how-to-videos'); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                <?php endif; ?>
            </div>
            
            <!-- All Videos Section (if no categories or as fallback) -->
            <?php
            if (empty($categories) || is_wp_error($categories)) {
                // Get all videos without category filtering
                $all_videos = get_posts(array(
                    'post_type' => 'how_to_video',
                    'posts_per_page' => 12,
                    'post_status' => 'publish',
                    'orderby' => 'date',
                    'order' => 'DESC',
                ));
                
                if (!empty($all_videos)) :
            ?>
                <section class="all-videos-section">
                    <h2><?php _e('All Videos', 'twintack-how-to-videos'); ?></h2>
                    
                    <?php
                    // Convert posts to video data format
                    $formatted_videos = array();
                    foreach ($all_videos as $video_post) {
                        $formatted_videos[] = array(
                            'id' => $video_post->ID,
                            'title' => $video_post->post_title,
                            'thumbnail' => get_the_post_thumbnail_url($video_post->ID, 'medium'),
                            'vimeo_url' => get_post_meta($video_post->ID, '_htv_vimeo_url', true),
                            'duration' => get_post_meta($video_post->ID, '_htv_video_duration', true),
                            'excerpt' => $video_post->post_excerpt,
                            'permalink' => get_permalink($video_post->ID),
                        );
                    }
                    
                    // Display using grid layout
                    $videos = $formatted_videos;
                    $args = array(
                        'layout' => 'grid',
                        'show_title' => false,
                    );
                    include TWINTACK_HTV_PLUGIN_DIR . 'templates/video-grid.php';
                    ?>
                </section>
            <?php 
                endif;
            }
            ?>
            
        <?php endwhile; ?>
    </div>
</div>

<style>
/* Videos Page Specific Styles */
.twintack-videos-page {
    padding: 2rem 0;
}

.page-header {
    text-align: center;
    margin-bottom: 3rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid #e5e5e5;
}

.page-title {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    color: #333;
}

.page-description {
    font-size: 1.1rem;
    color: #666;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.6;
}

.video-category-section {
    margin-bottom: 4rem;
}

.category-header {
    margin-bottom: 2rem;
}

.category-title {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    color: #333;
    border-left: 4px solid #007cba;
    padding-left: 1rem;
}

.category-description {
    font-size: 1rem;
    color: #666;
    margin-left: 1.25rem;
    line-height: 1.5;
}

.category-view-all {
    text-align: center;
    margin-top: 2rem;
}

.view-all-link {
    display: inline-block;
    padding: 0.75rem 1.5rem;
    background-color: #007cba;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    font-weight: 500;
    transition: background-color 0.3s ease;
}

.view-all-link:hover {
    background-color: #005a87;
    color: white;
}

.no-videos-message {
    text-align: center;
    padding: 3rem 2rem;
    background-color: #f8f9fa;
    border-radius: 8px;
    margin: 2rem 0;
}

.no-videos-message h3 {
    color: #333;
    margin-bottom: 1rem;
}

.no-videos-message p {
    color: #666;
    margin-bottom: 1rem;
}

.all-videos-section {
    margin-top: 3rem;
    padding-top: 3rem;
    border-top: 1px solid #e5e5e5;
}

.all-videos-section h2 {
    font-size: 2rem;
    margin-bottom: 2rem;
    text-align: center;
    color: #333;
}

/* Override plugin styles for page layout */
.twintack-videos-page .twintack-video-grid-section,
.twintack-videos-page .twintack-video-simple-section {
    padding: 1rem 0;
}

.twintack-videos-page .twintack-section-title {
    display: none; /* Hide individual section titles since we have category titles */
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .category-title {
        font-size: 1.5rem;
    }
    
    .page-description {
        font-size: 1rem;
    }
}
</style>

<?php get_footer(); ?> 