<?php
/**
 * Custom Post Type and Taxonomy Management
 *
 * @package TwinTackHowToVideos
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TwinTack_HTV_Post_Type Class
 */
class TwinTack_HTV_Post_Type {
    
    /**
     * Instance of this class
     * @var TwinTack_HTV_Post_Type
     */
    private static $instance = null;
    
    /**
     * Get the single instance of this class
     * @return TwinTack_HTV_Post_Type
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
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_how_to_video', array($this, 'save_video_data'));
    }
    
    /**
     * Register How-To Videos custom post type
     */
    public function register_post_type() {
        $labels = array(
            'name'               => __('How-To Videos', 'twintack-how-to-videos'),
            'singular_name'      => __('How-To Video', 'twintack-how-to-videos'),
            'menu_name'          => __('How-To Videos', 'twintack-how-to-videos'),
            'add_new'            => __('Add New', 'twintack-how-to-videos'),
            'add_new_item'       => __('Add New Video', 'twintack-how-to-videos'),
            'edit_item'          => __('Edit Video', 'twintack-how-to-videos'),
            'new_item'           => __('New Video', 'twintack-how-to-videos'),
            'view_item'          => __('View Video', 'twintack-how-to-videos'),
            'search_items'       => __('Search Videos', 'twintack-how-to-videos'),
            'not_found'          => __('No videos found', 'twintack-how-to-videos'),
            'not_found_in_trash' => __('No videos found in Trash', 'twintack-how-to-videos')
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
            'capability_type'     => 'post',
            'show_in_rest'        => true,
        );
        
        register_post_type('how_to_video', $args);
    }
    
    /**
     * Register taxonomies for video categorization
     */
    public function register_taxonomies() {
        // Register video sports taxonomy (existing)
        $sport_labels = array(
            'name'              => __('Video Sports', 'twintack-how-to-videos'),
            'singular_name'     => __('Video Sport', 'twintack-how-to-videos'),
            'search_items'      => __('Search Sports', 'twintack-how-to-videos'),
            'all_items'         => __('All Sports', 'twintack-how-to-videos'),
            'parent_item'       => __('Parent Sport', 'twintack-how-to-videos'),
            'parent_item_colon' => __('Parent Sport:', 'twintack-how-to-videos'),
            'edit_item'         => __('Edit Sport', 'twintack-how-to-videos'),
            'update_item'       => __('Update Sport', 'twintack-how-to-videos'),
            'add_new_item'      => __('Add New Sport', 'twintack-how-to-videos'),
            'new_item_name'     => __('New Sport Name', 'twintack-how-to-videos'),
            'menu_name'         => __('Video Sports', 'twintack-how-to-videos'),
        );
        
        register_taxonomy(
            'video_sport',
            'how_to_video',
            array(
                'labels'            => $sport_labels,
                'hierarchical'      => true,
                'show_admin_column' => true,
                'query_var'         => true,
                'rewrite'           => array('slug' => 'video-sport'),
                'show_in_rest'      => true,
                'show_ui'           => true,
                'show_in_menu'      => true,
            )
        );
        
        // Register video categories taxonomy (new)
        $category_labels = array(
            'name'              => __('Video Categories', 'twintack-how-to-videos'),
            'singular_name'     => __('Video Category', 'twintack-how-to-videos'),
            'search_items'      => __('Search Categories', 'twintack-how-to-videos'),
            'all_items'         => __('All Categories', 'twintack-how-to-videos'),
            'parent_item'       => __('Parent Category', 'twintack-how-to-videos'),
            'parent_item_colon' => __('Parent Category:', 'twintack-how-to-videos'),
            'edit_item'         => __('Edit Category', 'twintack-how-to-videos'),
            'update_item'       => __('Update Category', 'twintack-how-to-videos'),
            'add_new_item'      => __('Add New Category', 'twintack-how-to-videos'),
            'new_item_name'     => __('New Category Name', 'twintack-how-to-videos'),
            'menu_name'         => __('Video Categories', 'twintack-how-to-videos'),
        );
        
        register_taxonomy(
            'video_category',
            'how_to_video',
            array(
                'labels'            => $category_labels,
                'hierarchical'      => true,
                'show_admin_column' => true,
                'query_var'         => true,
                'rewrite'           => array('slug' => 'video-category'),
                'show_in_rest'      => true,
                'show_ui'           => true,
                'show_in_menu'      => true,
            )
        );
    }
    
    /**
     * Add meta boxes for video details
     */
    public function add_meta_boxes() {
        add_meta_box(
            'twintack_htv_video_details',
            __('Video Details', 'twintack-how-to-videos'),
            array($this, 'video_details_meta_box'),
            'how_to_video',
            'normal',
            'high'
        );
    }
    
    /**
     * Video details meta box callback
     */
    public function video_details_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('twintack_htv_save_video_data', 'twintack_htv_video_meta_nonce');
        
        // Get current values
        $vimeo_url = get_post_meta($post->ID, '_htv_vimeo_url', true);
        $duration = get_post_meta($post->ID, '_htv_video_duration', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="htv_vimeo_url"><?php _e('Vimeo URL', 'twintack-how-to-videos'); ?></label>
                </th>
                <td>
                    <input type="url" 
                           id="htv_vimeo_url" 
                           name="htv_vimeo_url" 
                           value="<?php echo esc_attr($vimeo_url); ?>" 
                           class="regular-text" 
                           placeholder="https://vimeo.com/123456789" />
                    <p class="description"><?php _e('Enter the full Vimeo URL for this video.', 'twintack-how-to-videos'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="htv_video_duration"><?php _e('Duration', 'twintack-how-to-videos'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="htv_video_duration" 
                           name="htv_video_duration" 
                           value="<?php echo esc_attr($duration); ?>" 
                           class="small-text" 
                           placeholder="2:30" />
                    <p class="description"><?php _e('Video duration in MM:SS format (e.g., "2:30").', 'twintack-how-to-videos'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save video meta data
     */
    public function save_video_data($post_id) {
        // Check nonce
        if (!isset($_POST['twintack_htv_video_meta_nonce']) || 
            !wp_verify_nonce($_POST['twintack_htv_video_meta_nonce'], 'twintack_htv_save_video_data')) {
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
        if (isset($_POST['htv_vimeo_url'])) {
            update_post_meta(
                $post_id,
                '_htv_vimeo_url',
                sanitize_url($_POST['htv_vimeo_url'])
            );
        }
        
        // Save video duration
        if (isset($_POST['htv_video_duration'])) {
            update_post_meta(
                $post_id,
                '_htv_video_duration',
                sanitize_text_field($_POST['htv_video_duration'])
            );
        }
    }
} 