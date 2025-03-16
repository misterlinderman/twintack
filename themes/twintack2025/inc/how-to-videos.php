<?php
/**
 * How-To Videos functionality for TwinTack
 *
 * @package TwinTack2025
 */

// Register How-To Videos custom post type and taxonomy
function twintack_register_how_to_videos() {
    $labels = array(
        'name'               => 'How-To Videos',
        'singular_name'      => 'How-To Video',
        'menu_name'          => 'How-To Videos',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Video',
        'edit_item'          => 'Edit Video',
        'new_item'           => 'New Video',
        'view_item'          => 'View Video',
        'search_items'       => 'Search Videos',
        'not_found'          => 'No videos found',
        'not_found_in_trash' => 'No videos found in Trash'
    );
    
    $args = array(
        'labels'              => $labels,
        'public'              => true,
        'exclude_from_search' => false,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_nav_menus'   => false,
        'supports'            => array('title', 'thumbnail', 'excerpt'),
        'menu_icon'           => 'dashicons-video-alt3',
        'has_archive'         => false,
        'rewrite'             => array('slug' => 'how-to-videos'),
    );
    
    register_post_type('how_to_video', $args);
    
    // Register taxonomy for sport categories
    register_taxonomy(
        'video_sport',
        'how_to_video',
        array(
            'label' => 'Sport',
            'hierarchical' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'video-sport'),
        )
    );
}
add_action('init', 'twintack_register_how_to_videos');

// Add meta box for Vimeo URL and duration
function twintack_add_video_meta_boxes() {
    add_meta_box(
        'twintack_video_details',
        'Video Details',
        'twintack_video_details_callback',
        'how_to_video',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'twintack_add_video_meta_boxes');

// Meta box callback function
function twintack_video_details_callback($post) {
    // Add nonce for security
    wp_nonce_field('twintack_save_video_data', 'twintack_video_meta_nonce');
    
    // Get current values
    $vimeo_url = get_post_meta($post->ID, '_vimeo_url', true);
    $duration = get_post_meta($post->ID, '_video_duration', true);
    
    echo '<p>';
    echo '<label for="vimeo_url">Vimeo URL:</label> ';
    echo '<input type="url" id="vimeo_url" name="vimeo_url" value="' . esc_attr($vimeo_url) . '" size="40" />';
    echo '</p>';
    
    echo '<p>';
    echo '<label for="video_duration">Duration (e.g. "2:30"):</label> ';
    echo '<input type="text" id="video_duration" name="video_duration" value="' . esc_attr($duration) . '" size="10" />';
    echo '</p>';
}

// Save meta box data
function twintack_save_video_data($post_id) {
    // Check nonce
    if (!isset($_POST['twintack_video_meta_nonce']) || 
        !wp_verify_nonce($_POST['twintack_video_meta_nonce'], 'twintack_save_video_data')) {
        return;
    }
    
    // Don't save on autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Save Vimeo URL
    if (isset($_POST['vimeo_url'])) {
        update_post_meta(
            $post_id,
            '_vimeo_url',
            sanitize_url($_POST['vimeo_url'])
        );
    }
    
    // Save video duration
    if (isset($_POST['video_duration'])) {
        update_post_meta(
            $post_id,
            '_video_duration',
            sanitize_text_field($_POST['video_duration'])
        );
    }
}
add_action('save_post_how_to_video', 'twintack_save_video_data');

// Add submenu page for assigning videos to sports
function twintack_add_video_assignment_submenu() {
    add_submenu_page(
        'edit.php?post_type=how_to_video',
        'Assign to Sports',
        'Assign to Sports',
        'manage_options',
        'video-sport-assignment',
        'twintack_video_assignment_page'
    );
}
add_action('admin_menu', 'twintack_add_video_assignment_submenu');

// Video assignment page callback
function twintack_video_assignment_page() {
    // Get all sports
    $sports = get_terms(array(
        'taxonomy' => 'video_sport',
        'hide_empty' => false,
    ));
    
    // Get all videos
    $videos = get_posts(array(
        'post_type' => 'how_to_video',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ));
    
    // Save assignments if form submitted
    if (isset($_POST['save_assignments']) && isset($_POST['video_assignments']) && is_array($_POST['video_assignments'])) {
        foreach ($_POST['video_assignments'] as $video_id => $sport_slugs) {
            // Delete existing terms
            wp_set_object_terms($video_id, array(), 'video_sport');
            
            // Add new terms
            if (!empty($sport_slugs)) {
                wp_set_object_terms($video_id, $sport_slugs, 'video_sport');
            }
        }
        
        echo '<div class="notice notice-success"><p>Video assignments saved successfully!</p></div>';
    }
    
    // Display the assignment form
    ?>
    <div class="wrap">
        <h1>Assign Videos to Sports</h1>
        <form method="post" action="">
            <table class="widefat fixed" cellspacing="0">
                <thead>
                    <tr>
                        <th>Video Title</th>
                        <?php foreach ($sports as $sport) : ?>
                            <th><?php echo esc_html($sport->name); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($videos as $video) : ?>
                        <?php $video_sports = wp_get_object_terms($video->ID, 'video_sport', array('fields' => 'slugs')); ?>
                        <tr>
                            <td><?php echo esc_html($video->post_title); ?></td>
                            <?php foreach ($sports as $sport) : ?>
                                <td>
                                    <input type="checkbox" 
                                           name="video_assignments[<?php echo esc_attr($video->ID); ?>][]" 
                                           value="<?php echo esc_attr($sport->slug); ?>"
                                           <?php checked(in_array($sport->slug, $video_sports)); ?>>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="submit">
                <input type="submit" name="save_assignments" class="button button-primary" value="Save Assignments">
            </p>
        </form>
    </div>
    <?php
}

// Function to get product sport
function twintack_get_product_sport($product_id) {
    $terms = get_the_terms($product_id, 'product_cat');
    $sport_slugs = array('baseball', 'fishing'); // Add all your sport slugs
    
    if ($terms && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            if (in_array($term->slug, $sport_slugs)) {
                return $term->slug;
            }
        }
    }
    
    // Default sport if no match found
    return 'baseball';
}

// Function to get how-to videos by sport
function twintack_get_how_to_videos_by_sport($sport_slug, $limit = 5) {
    $args = array(
        'post_type' => 'how_to_video',
        'posts_per_page' => $limit,
        'orderby' => 'date',
        'order' => 'DESC',
        'tax_query' => array(
            array(
                'taxonomy' => 'video_sport',
                'field' => 'slug',
                'terms' => $sport_slug,
            ),
        ),
    );
    
    $videos = array();
    $video_query = new WP_Query($args);
    
    if ($video_query->have_posts()) {
        while ($video_query->have_posts()) {
            $video_query->the_post();
            $video_id = get_the_ID();
            
            $videos[] = array(
                'id' => $video_id,
                'title' => get_the_title(),
                'thumbnail' => get_the_post_thumbnail_url($video_id, 'medium'),
                'vimeo_url' => get_post_meta($video_id, '_vimeo_url', true),
                'duration' => get_post_meta($video_id, '_video_duration', true),
                'excerpt' => get_the_excerpt(),
            );
        }
        wp_reset_postdata();
    }
    
    return $videos;
}

// Enqueue scripts and styles for product video carousel
function twintack_enqueue_product_video_carousel() {
    if (is_product()) {
        wp_enqueue_script(
            'twintack-product-video-carousel',
            get_template_directory_uri() . '/js/product-video-carousel.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        wp_add_inline_style('twintack-woocommerce-styles', '
    /**
     * Enhanced Video Carousel Styles
     * 
     * @package TwinTack2025
     */
    
    /* Video Carousel Section */
    .video-carousel-section {
        background-color: #f5f5f7;
        padding: 4rem 0;
        border-top: 1px solid #e5e5e5;
        border-bottom: 1px solid #e5e5e5;
        overflow: hidden;
    }
    
    /* Carousel Container */
    .video-carousel-container {
        position: relative;
        padding: 0 2rem;
        margin-bottom: 2rem;
    }
    
    /* Carousel Track */
    .video-carousel-track {
        overflow: hidden;
        width: 100%;
        border-radius: 8px;
    }
    
    /* Carousel Slides */
    .video-carousel-slides {
        display: flex;
        transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        width: 100%;
    }
    
    /* Individual Slide */
    .video-slide {
        flex: 0 0 100%;
        padding: 0 0.5rem;
    }
    
    /* Video Thumbnail */
    .video-thumbnail {
        position: relative;
        background-color: #000;
        aspect-ratio: 16/9;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .video-thumbnail:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    }
    
    .video-thumbnail img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        opacity: 0.85;
        transition: opacity 0.3s ease;
    }
    
    .video-thumbnail:hover img {
        opacity: 0.7;
    }
    
    /* Play Button */
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
        cursor: pointer;
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
    
    /* Video Duration Tag */
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
    
    /* Video Info */
    .video-info {
        margin-top: 12px;
        text-align: center;
    }
    
    .video-info h3 {
        font-size: 18px;
        font-weight: 600;
        line-height: 1.3;
        color: #333;
        margin-bottom: 5px;
    }
    
    .video-info p {
        font-size: 14px;
        color: #666;
    }
    
    /* Navigation Buttons */
    .carousel-prev,
    .carousel-next {
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
    
    .carousel-prev:hover,
    .carousel-next:hover {
        transform: translateY(-50%) scale(1.1);
        background-color: #f0f0f0;
    }
    
    .carousel-prev:focus,
    .carousel-next:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
    }
    
    .carousel-prev:disabled,
    .carousel-next:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .carousel-prev {
        left: 0.5rem;
    }
    
    .carousel-next {
        right: 0.5rem;
    }
    
    /* Carousel Indicators */
    .carousel-indicators {
        display: flex;
        justify-content: center;
        margin-top: 1.5rem;
    }
    
    .carousel-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin: 0 4px;
        background-color: #d1d5db;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        padding: 0;
    }
    
    .carousel-indicator.active {
        background-color: #3b82f6;
        transform: scale(1.2);
    }
    
    /* Video Modal Styles */
    .video-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease;
    }
    
    .video-modal.active {
        opacity: 1;
        visibility: visible;
    }
    
    .video-modal-container {
        position: relative;
        z-index: 1;
        width: 90%;
        max-width: 1000px;
        transform: translateY(30px) scale(0.95);
        opacity: 0;
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease;
    }
    
    .video-modal.animate-in .video-modal-container {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
    
    .video-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.85);
        backdrop-filter: blur(5px);
    }
    
    .video-modal-content {
        background-color: #000;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
    }
    
    .video-modal-header {
        padding: 15px 20px;
        background-color: #111;
        color: white;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .video-modal-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 500;
    }
    
    .video-modal-close {
        position: absolute;
        top: -40px;
        right: 0;
        background-color: rgba(0, 0, 0, 0.5);
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        border: none;
        cursor: pointer;
        transition: background-color 0.2s;
        z-index: 2;
    }
    
    .video-modal-close:hover {
        background-color: rgba(0, 0, 0, 0.7);
    }
    
    .video-modal-close:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
    }
    
    .video-modal-close svg {
        width: 24px;
        height: 24px;
    }
    
    .video-modal-player {
        position: relative;
        padding-top: 56.25%; /* 16:9 Aspect Ratio */
        background-color: #000;
    }
    
    .video-modal-player iframe {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        border: 0;
    }
    
    /* Hide scrollbar when modal is open */
    body.modal-open {
        overflow: hidden;
    }
    
    /* Responsive Adjustments */
    @media (min-width: 768px) {
        .video-slide {
            flex: 0 0 100%;
        }
    }
    
    @media (min-width: 1024px) {
        .video-slide {
            flex: 0 0 100%;
        }
        
        .carousel-prev {
            left: -20px;
        }
        
        .carousel-next {
            right: -20px;
        }
    }
    
    /* For smaller screens */
    @media (max-width: 640px) {
        .video-carousel-section {
            padding: 2rem 0;
        }
        
        .play-icon {
            width: 48px;
            height: 48px;
        }
        
        .video-modal-container {
            width: 95%;
        }
        
        .video-modal-close {
            top: -35px;
            right: 0;
            width: 30px;
            height: 30px;
        }
    }
        ');
    }
}
add_action('wp_enqueue_scripts', 'twintack_enqueue_product_video_carousel');