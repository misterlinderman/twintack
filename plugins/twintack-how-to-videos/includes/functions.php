<?php
/**
 * Core Functions for How-To Videos
 *
 * @package TwinTackHowToVideos
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get product sport category
 * Determines the sport category for a given product
 *
 * @param int $product_id Product ID
 * @return string Sport slug
 */
function twintack_htv_get_product_sport($product_id) {
    $terms = get_the_terms($product_id, 'product_cat');
    $sport_slugs = apply_filters('twintack_htv_sport_slugs', array('baseball', 'fishing'));
    
    if ($terms && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            if (in_array($term->slug, $sport_slugs)) {
                return $term->slug;
            }
        }
    }
    
    // Default sport if no match found
    return apply_filters('twintack_htv_default_sport', 'baseball');
}

/**
 * Sort videos by priority then date
 * Helper function to properly sort videos by priority (highest first), then by date (newest first)
 *
 * @param array $videos Array of video data to sort
 * @return array Sorted array of video data
 */
function twintack_htv_sort_videos_by_priority($videos) {
    if (empty($videos)) {
        return $videos;
    }
    
    usort($videos, function($a, $b) {
        // Get priority values, default to 0 if not set
        $priority_a = !empty($a['priority']) ? intval($a['priority']) : 0;
        $priority_b = !empty($b['priority']) ? intval($b['priority']) : 0;
        
        // First sort by priority (higher first)
        if ($priority_a !== $priority_b) {
            return $priority_b - $priority_a;
        }
        
        // If priorities are equal, sort by date (newer first)
        $date_a = get_post_field('post_date', $a['id']);
        $date_b = get_post_field('post_date', $b['id']);
        
        return strcmp($date_b, $date_a);
    });
    
    return $videos;
}

/**
 * Get how-to videos by sport
 * Retrieves videos assigned to a specific sport
 *
 * @param string $sport_slug Sport taxonomy slug
 * @param int    $limit      Number of videos to retrieve (default: 5)
 * @return array Array of video data
 */
function twintack_htv_get_videos_by_sport($sport_slug, $limit = 5) {
    $args = array(
        'post_type' => 'how_to_video',
        'posts_per_page' => -1, // Get all first, then limit after sorting
        'post_status' => 'publish',
        'tax_query' => array(
            array(
                'taxonomy' => 'video_sport',
                'field' => 'slug',
                'terms' => $sport_slug,
            ),
        ),
    );
    
    $args = apply_filters('twintack_htv_query_args', $args, $sport_slug, $limit);
    
    $videos = array();
    $video_query = new WP_Query($args);
    
    if ($video_query->have_posts()) {
        while ($video_query->have_posts()) {
            $video_query->the_post();
            $video_id = get_the_ID();
            
            $video_data = array(
                'id' => $video_id,
                'title' => get_the_title(),
                'thumbnail' => get_the_post_thumbnail_url($video_id, 'medium'),
                'vimeo_url' => get_post_meta($video_id, '_htv_vimeo_url', true),
                'duration' => get_post_meta($video_id, '_htv_video_duration', true),
                'priority' => get_post_meta($video_id, '_htv_video_priority', true),
                'excerpt' => get_the_excerpt(),
                'permalink' => get_permalink($video_id),
            );
            
            $videos[] = apply_filters('twintack_htv_video_data', $video_data, $video_id);
        }
        wp_reset_postdata();
    }
    
    // Sort videos by priority, then date
    $videos = twintack_htv_sort_videos_by_priority($videos);
    
    // Limit results after sorting
    if ($limit > 0 && count($videos) > $limit) {
        $videos = array_slice($videos, 0, $limit);
    }
    
    return apply_filters('twintack_htv_videos_by_sport', $videos, $sport_slug, $limit);
}

/**
 * Get videos by category
 * Retrieves videos assigned to a specific category
 *
 * @param string $category_slug Category taxonomy slug
 * @param int    $limit         Number of videos to retrieve (default: 5)
 * @return array Array of video data
 */
function twintack_htv_get_videos_by_category($category_slug, $limit = 5) {
    $args = array(
        'post_type' => 'how_to_video',
        'posts_per_page' => -1, // Get all first, then limit after sorting
        'post_status' => 'publish',
        'tax_query' => array(
            array(
                'taxonomy' => 'video_category',
                'field' => 'slug',
                'terms' => $category_slug,
            ),
        ),
    );
    
    $args = apply_filters('twintack_htv_category_query_args', $args, $category_slug, $limit);
    
    $videos = array();
    $video_query = new WP_Query($args);
    
    if ($video_query->have_posts()) {
        while ($video_query->have_posts()) {
            $video_query->the_post();
            $video_id = get_the_ID();
            
            $video_data = array(
                'id' => $video_id,
                'title' => get_the_title(),
                'thumbnail' => get_the_post_thumbnail_url($video_id, 'medium'),
                'vimeo_url' => get_post_meta($video_id, '_htv_vimeo_url', true),
                'duration' => get_post_meta($video_id, '_htv_video_duration', true),
                'priority' => get_post_meta($video_id, '_htv_video_priority', true),
                'excerpt' => get_the_excerpt(),
                'permalink' => get_permalink($video_id),
            );
            
            $videos[] = apply_filters('twintack_htv_video_data', $video_data, $video_id);
        }
        wp_reset_postdata();
    }
    
    // Sort videos by priority, then date
    $videos = twintack_htv_sort_videos_by_priority($videos);
    
    // Limit results after sorting
    if ($limit > 0 && count($videos) > $limit) {
        $videos = array_slice($videos, 0, $limit);
    }
    
    return apply_filters('twintack_htv_videos_by_category', $videos, $category_slug, $limit);
}

/**
 * Get videos by multiple criteria
 * Retrieves videos that match sport and/or category filters
 *
 * @param array $args Query arguments
 * @return array Array of video data
 */
function twintack_htv_get_videos($args = array()) {
    $defaults = array(
        'sport' => '',
        'category' => '',
        'limit' => 5,
        'orderby' => 'priority',
        'order' => 'DESC',
    );
    
    $args = wp_parse_args($args, $defaults);
    
    $query_args = array(
        'post_type' => 'how_to_video',
        'posts_per_page' => -1, // Get all first, then limit after sorting if using priority
        'post_status' => 'publish',
    );
    
    // Handle ordering
    $use_priority_sorting = ($args['orderby'] === 'priority');
    
    if (!$use_priority_sorting) {
        $query_args['orderby'] = $args['orderby'];
        $query_args['order'] = $args['order'];
        $query_args['posts_per_page'] = $args['limit']; // Use limit directly for non-priority sorting
    }
    
    // Build tax query
    $tax_query = array();
    
    if (!empty($args['sport'])) {
        $tax_query[] = array(
            'taxonomy' => 'video_sport',
            'field' => 'slug',
            'terms' => $args['sport'],
        );
    }
    
    if (!empty($args['category'])) {
        $tax_query[] = array(
            'taxonomy' => 'video_category',
            'field' => 'slug',
            'terms' => $args['category'],
        );
    }
    
    if (!empty($tax_query)) {
        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }
        $query_args['tax_query'] = $tax_query;
    }
    
    $query_args = apply_filters('twintack_htv_multi_query_args', $query_args, $args);
    
    $videos = array();
    $video_query = new WP_Query($query_args);
    
    if ($video_query->have_posts()) {
        while ($video_query->have_posts()) {
            $video_query->the_post();
            $video_id = get_the_ID();
            
            $video_data = array(
                'id' => $video_id,
                'title' => get_the_title(),
                'thumbnail' => get_the_post_thumbnail_url($video_id, 'medium'),
                'vimeo_url' => get_post_meta($video_id, '_htv_vimeo_url', true),
                'duration' => get_post_meta($video_id, '_htv_video_duration', true),
                'priority' => get_post_meta($video_id, '_htv_video_priority', true),
                'excerpt' => get_the_excerpt(),
                'permalink' => get_permalink($video_id),
            );
            
            $videos[] = apply_filters('twintack_htv_video_data', $video_data, $video_id);
        }
        wp_reset_postdata();
    }
    
    // Apply priority sorting if requested
    if ($use_priority_sorting) {
        $videos = twintack_htv_sort_videos_by_priority($videos);
        
        // Limit results after sorting
        if ($args['limit'] > 0 && count($videos) > $args['limit']) {
            $videos = array_slice($videos, 0, $args['limit']);
        }
    }
    
    return apply_filters('twintack_htv_videos_multi', $videos, $args);
}

/**
 * Get all video sports
 * Retrieves all available video sport categories
 *
 * @return array Array of sport terms
 */
function twintack_htv_get_all_sports() {
    $sports = get_terms(array(
        'taxonomy' => 'video_sport',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
    ));
    
    if (is_wp_error($sports)) {
        return array();
    }
    
    return apply_filters('twintack_htv_all_sports', $sports);
}

/**
 * Check if how-to videos are available for a sport
 *
 * @param string $sport_slug Sport taxonomy slug
 * @return bool True if videos exist, false otherwise
 */
function twintack_htv_has_videos_for_sport($sport_slug) {
    $videos = twintack_htv_get_videos_by_sport($sport_slug, 1);
    return !empty($videos);
}

/**
 * Get video modal HTML
 * Returns the HTML structure for the video modal
 *
 * @return string Modal HTML
 */
function twintack_htv_get_modal_html() {
    ob_start();
    ?>
    <!-- Video Modal -->
    <div id="twintack-video-modal" class="twintack-video-modal">
        <div class="twintack-video-modal-overlay"></div>
        <div class="twintack-video-modal-container">
            <div class="twintack-video-modal-content">
                <button id="twintack-close-video-modal" class="twintack-video-modal-close" aria-label="<?php esc_attr_e('Close video', 'twintack-how-to-videos'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
                <div class="twintack-video-modal-header">
                    <h3 id="twintack-video-modal-title"></h3>
                </div>
                <div id="twintack-video-container" class="twintack-video-modal-player"></div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Display videos with flexible options
 * Main function to display videos with various layout and filtering options
 *
 * @param array|string $args Display arguments or sport slug for backward compatibility
 * @param int          $limit Number of videos (for backward compatibility)
 * @return void
 */
function twintack_htv_display_videos($args = array(), $limit = 5) {
    // Handle backward compatibility: if first param is string, treat as sport slug
    if (is_string($args)) {
        $args = array(
            'sport' => $args,
            'limit' => $limit,
        );
    }
    
    // Set defaults
    $defaults = array(
        'sport' => '',
        'category' => '',
        'limit' => 5,
        'layout' => 'carousel', // carousel, grid, list, simple
        'show_title' => true,
        'title' => '',
        'class' => '',
    );
    
    $args = wp_parse_args($args, $defaults);
    
    // Auto-detect sport/category if not provided
    if (empty($args['sport']) && empty($args['category'])) {
        if (is_product()) {
            $args['sport'] = twintack_htv_get_product_sport(get_the_ID());
        } else {
            // Try to get from page title or other context
            $page_title = strtolower(get_the_title());
            $args['sport'] = sanitize_title($page_title);
        }
    }
    
    // Get videos based on criteria
    if (!empty($args['category'])) {
        $videos = twintack_htv_get_videos_by_category($args['category'], $args['limit']);
    } elseif (!empty($args['sport'])) {
        $videos = twintack_htv_get_videos_by_sport($args['sport'], $args['limit']);
    } else {
        // Use multi-query for complex filtering
        $videos = twintack_htv_get_videos($args);
    }
    
    if (empty($videos)) {
        return; // Don't show anything if no videos
    }
    
    // Determine template based on layout
    $template_file = 'video-' . $args['layout'] . '.php';
    
    // Allow themes to override the template
    $theme_template = locate_template('twintack-how-to-videos/' . $template_file);
    
    if ($theme_template) {
        include $theme_template;
    } elseif (file_exists(TWINTACK_HTV_PLUGIN_DIR . 'templates/' . $template_file)) {
        include TWINTACK_HTV_PLUGIN_DIR . 'templates/' . $template_file;
    } else {
        // Fallback to carousel template
        include TWINTACK_HTV_PLUGIN_DIR . 'templates/video-carousel.php';
    }
}

/**
 * Display videos by category
 * Convenience function for displaying videos by category
 *
 * @param string $category_slug Category to display videos for
 * @param int    $limit         Number of videos to show
 * @param string $layout        Layout type (carousel, grid, list, simple)
 * @return void
 */
function twintack_htv_display_category_videos($category_slug, $limit = 5, $layout = 'carousel') {
    twintack_htv_display_videos(array(
        'category' => $category_slug,
        'limit' => $limit,
        'layout' => $layout,
    ));
}

/**
 * Enqueue video modal in footer
 * Ensures the video modal is available on pages that need it
 */
function twintack_htv_enqueue_modal() {
    if (!wp_script_is('twintack-htv-frontend', 'enqueued')) {
        return;
    }
    
    echo twintack_htv_get_modal_html();
}
add_action('wp_footer', 'twintack_htv_enqueue_modal');

/**
 * Backward compatibility functions
 * These maintain compatibility with the old theme function names
 */

if (!function_exists('twintack_get_product_sport')) {
    function twintack_get_product_sport($product_id) {
        return twintack_htv_get_product_sport($product_id);
    }
}

if (!function_exists('twintack_get_how_to_videos_by_sport')) {
    function twintack_get_how_to_videos_by_sport($sport_slug, $limit = 5) {
        return twintack_htv_get_videos_by_sport($sport_slug, $limit);
    }
} 