<?php
/**
 * Admin Interface Management
 *
 * @package TwinTackHowToVideos
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TwinTack_HTV_Admin Class
 */
class TwinTack_HTV_Admin {
    
    /**
     * Instance of this class
     * @var TwinTack_HTV_Admin
     */
    private static $instance = null;
    
    /**
     * Get the single instance of this class
     * @return TwinTack_HTV_Admin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_form_submission'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=how_to_video',
            __('Assign to Sports', 'twintack-how-to-videos'),
            __('Assign to Sports', 'twintack-how-to-videos'),
            'manage_options',
            'video-sport-assignment',
            array($this, 'video_sport_assignment_page')
        );
        
        add_submenu_page(
            'edit.php?post_type=how_to_video',
            __('Assign to Categories', 'twintack-how-to-videos'),
            __('Assign to Categories', 'twintack-how-to-videos'),
            'manage_options',
            'video-category-assignment',
            array($this, 'video_category_assignment_page')
        );
    }
    
    /**
     * Handle form submission for video assignments
     */
    public function handle_form_submission() {
        // Handle sport assignments
        if (isset($_POST['twintack_htv_save_sport_assignments']) && 
            wp_verify_nonce($_POST['twintack_htv_sport_assignment_nonce'], 'save_video_sport_assignments')) {
            
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.', 'twintack-how-to-videos'));
            }
            
            if (isset($_POST['video_sport_assignments']) && is_array($_POST['video_sport_assignments'])) {
                foreach ($_POST['video_sport_assignments'] as $video_id => $sport_slugs) {
                    $video_id = intval($video_id);
                    
                    // Delete existing terms
                    wp_set_object_terms($video_id, array(), 'video_sport');
                    
                    // Add new terms
                    if (!empty($sport_slugs) && is_array($sport_slugs)) {
                        $sanitized_slugs = array_map('sanitize_text_field', $sport_slugs);
                        wp_set_object_terms($video_id, $sanitized_slugs, 'video_sport');
                    }
                }
                
                add_action('admin_notices', array($this, 'show_success_notice'));
            }
        }
        
        // Handle category assignments
        if (isset($_POST['twintack_htv_save_category_assignments']) && 
            wp_verify_nonce($_POST['twintack_htv_category_assignment_nonce'], 'save_video_category_assignments')) {
            
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.', 'twintack-how-to-videos'));
            }
            
            if (isset($_POST['video_category_assignments']) && is_array($_POST['video_category_assignments'])) {
                foreach ($_POST['video_category_assignments'] as $video_id => $category_slugs) {
                    $video_id = intval($video_id);
                    
                    // Delete existing terms
                    wp_set_object_terms($video_id, array(), 'video_category');
                    
                    // Add new terms
                    if (!empty($category_slugs) && is_array($category_slugs)) {
                        $sanitized_slugs = array_map('sanitize_text_field', $category_slugs);
                        wp_set_object_terms($video_id, $sanitized_slugs, 'video_category');
                    }
                }
                
                add_action('admin_notices', array($this, 'show_success_notice'));
            }
        }
    }
    
    /**
     * Show success notice after saving assignments
     */
    public function show_success_notice() {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Video assignments saved successfully!', 'twintack-how-to-videos'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Video sport assignment page callback
     */
    public function video_sport_assignment_page() {
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
            'post_status' => 'publish',
        ));
        ?>
        <div class="wrap">
            <h1><?php _e('Assign Videos to Sports', 'twintack-how-to-videos'); ?></h1>
            
            <?php if (empty($sports)) : ?>
                <div class="notice notice-warning">
                    <p>
                        <?php 
                        printf(
                            __('No sports found. Please <a href="%s">create some video sports</a> first.', 'twintack-how-to-videos'),
                            admin_url('edit-tags.php?taxonomy=video_sport&post_type=how_to_video')
                        ); 
                        ?>
                    </p>
                </div>
            <?php elseif (empty($videos)) : ?>
                <div class="notice notice-warning">
                    <p>
                        <?php 
                        printf(
                            __('No videos found. Please <a href="%s">create some videos</a> first.', 'twintack-how-to-videos'),
                            admin_url('post-new.php?post_type=how_to_video')
                        ); 
                        ?>
                    </p>
                </div>
            <?php else : ?>
                <form method="post" action="">
                    <?php wp_nonce_field('save_video_sport_assignments', 'twintack_htv_sport_assignment_nonce'); ?>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th class="manage-column" style="width: 30%;">
                                    <?php _e('Video Title', 'twintack-how-to-videos'); ?>
                                </th>
                                <th class="manage-column" style="width: 20%;">
                                    <?php _e('Thumbnail', 'twintack-how-to-videos'); ?>
                                </th>
                                <?php foreach ($sports as $sport) : ?>
                                    <th class="manage-column column-cb check-column" style="text-align: center;">
                                        <?php echo esc_html($sport->name); ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($videos as $video) : ?>
                                <?php 
                                $video_sports = wp_get_object_terms($video->ID, 'video_sport', array('fields' => 'slugs'));
                                $thumbnail = get_the_post_thumbnail($video->ID, 'thumbnail');
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($video->post_title); ?></strong>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="<?php echo get_edit_post_link($video->ID); ?>">
                                                    <?php _e('Edit', 'twintack-how-to-videos'); ?>
                                                </a>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($thumbnail) : ?>
                                            <?php echo $thumbnail; ?>
                                        <?php else : ?>
                                            <span class="dashicons dashicons-format-video" style="font-size: 50px; color: #ccc;"></span>
                                        <?php endif; ?>
                                    </td>
                                    <?php foreach ($sports as $sport) : ?>
                                        <td style="text-align: center;">
                                            <input type="checkbox" 
                                                   name="video_sport_assignments[<?php echo esc_attr($video->ID); ?>][]" 
                                                   value="<?php echo esc_attr($sport->slug); ?>"
                                                   <?php checked(in_array($sport->slug, $video_sports)); ?>>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" 
                               name="twintack_htv_save_sport_assignments" 
                               class="button button-primary" 
                               value="<?php _e('Save Sport Assignments', 'twintack-how-to-videos'); ?>">
                    </p>
                </form>
                
                <div class="postbox" style="margin-top: 20px;">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php _e('Instructions', 'twintack-how-to-videos'); ?></h2>
                    </div>
                    <div class="inside">
                        <p><?php _e('Use this page to assign videos to specific sports. Videos assigned to a sport will appear on:', 'twintack-how-to-videos'); ?></p>
                        <ul>
                            <li><?php _e('Product pages for products in that sport category', 'twintack-how-to-videos'); ?></li>
                            <li><?php _e('Sport-specific pages that use the video display functionality', 'twintack-how-to-videos'); ?></li>
                        </ul>
                        <p><?php _e('You can assign a video to multiple sports by checking multiple boxes.', 'twintack-how-to-videos'); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .wp-list-table th.check-column {
            width: 10%;
        }
        .wp-list-table td img {
            max-width: 50px;
            height: auto;
        }
        .notice {
            margin: 20px 0;
        }
        </style>
        <?php
    }
    
    /**
     * Video category assignment page callback
     */
    public function video_category_assignment_page() {
        // Get all categories
        $categories = get_terms(array(
            'taxonomy' => 'video_category',
            'hide_empty' => false,
        ));
        
        // Get all videos
        $videos = get_posts(array(
            'post_type' => 'how_to_video',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish',
        ));
        ?>
        <div class="wrap">
            <h1><?php _e('Assign Videos to Categories', 'twintack-how-to-videos'); ?></h1>
            
            <?php if (empty($categories)) : ?>
                <div class="notice notice-warning">
                    <p>
                        <?php 
                        printf(
                            __('No categories found. Please <a href="%s">create some video categories</a> first.', 'twintack-how-to-videos'),
                            admin_url('edit-tags.php?taxonomy=video_category&post_type=how_to_video')
                        ); 
                        ?>
                    </p>
                </div>
            <?php elseif (empty($videos)) : ?>
                <div class="notice notice-warning">
                    <p>
                        <?php 
                        printf(
                            __('No videos found. Please <a href="%s">create some videos</a> first.', 'twintack-how-to-videos'),
                            admin_url('post-new.php?post_type=how_to_video')
                        ); 
                        ?>
                    </p>
                </div>
            <?php else : ?>
                <form method="post" action="">
                    <?php wp_nonce_field('save_video_category_assignments', 'twintack_htv_category_assignment_nonce'); ?>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th class="manage-column" style="width: 30%;">
                                    <?php _e('Video Title', 'twintack-how-to-videos'); ?>
                                </th>
                                <th class="manage-column" style="width: 20%;">
                                    <?php _e('Thumbnail', 'twintack-how-to-videos'); ?>
                                </th>
                                <?php foreach ($categories as $category) : ?>
                                    <th class="manage-column column-cb check-column" style="text-align: center;">
                                        <?php echo esc_html($category->name); ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($videos as $video) : ?>
                                <?php 
                                $video_categories = wp_get_object_terms($video->ID, 'video_category', array('fields' => 'slugs'));
                                $thumbnail = get_the_post_thumbnail($video->ID, 'thumbnail');
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($video->post_title); ?></strong>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="<?php echo get_edit_post_link($video->ID); ?>">
                                                    <?php _e('Edit', 'twintack-how-to-videos'); ?>
                                                </a>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($thumbnail) : ?>
                                            <?php echo $thumbnail; ?>
                                        <?php else : ?>
                                            <span class="dashicons dashicons-format-video" style="font-size: 50px; color: #ccc;"></span>
                                        <?php endif; ?>
                                    </td>
                                    <?php foreach ($categories as $category) : ?>
                                        <td style="text-align: center;">
                                            <input type="checkbox" 
                                                   name="video_category_assignments[<?php echo esc_attr($video->ID); ?>][]" 
                                                   value="<?php echo esc_attr($category->slug); ?>"
                                                   <?php checked(in_array($category->slug, $video_categories)); ?>>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" 
                               name="twintack_htv_save_category_assignments" 
                               class="button button-primary" 
                               value="<?php _e('Save Category Assignments', 'twintack-how-to-videos'); ?>">
                    </p>
                </form>
                
                <div class="postbox" style="margin-top: 20px;">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php _e('Instructions', 'twintack-how-to-videos'); ?></h2>
                    </div>
                    <div class="inside">
                        <p><?php _e('Use this page to assign videos to general categories. Videos assigned to a category will appear on:', 'twintack-how-to-videos'); ?></p>
                        <ul>
                            <li><?php _e('Category-specific pages that use the video display functionality', 'twintack-how-to-videos'); ?></li>
                            <li><?php _e('Custom video pages with category filtering', 'twintack-how-to-videos'); ?></li>
                            <li><?php _e('Shortcodes that specify category filtering', 'twintack-how-to-videos'); ?></li>
                        </ul>
                        <p><?php _e('You can assign a video to multiple categories by checking multiple boxes.', 'twintack-how-to-videos'); ?></p>
                        <p><strong><?php _e('Common categories:', 'twintack-how-to-videos'); ?></strong> Blog, Testimonial, TwinTack, How-To, Marketing, Education</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .wp-list-table th.check-column {
            width: 10%;
        }
        .wp-list-table td img {
            max-width: 50px;
            height: auto;
        }
        .notice {
            margin: 20px 0;
        }
        </style>
        <?php
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only enqueue on our admin pages
        if ('how_to_video_page_video-sport-assignment' === $hook) {
            wp_enqueue_style('twintack-htv-admin', TWINTACK_HTV_PLUGIN_URL . 'assets/css/admin.css', array(), TWINTACK_HTV_VERSION);
        }
    }
} 