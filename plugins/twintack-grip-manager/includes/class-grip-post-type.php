<?php
class TwinTack_Grip_Post_Type {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_statuses'));
        add_filter('single_template', array($this, 'load_grip_design_template'));
    }
    
    public function register_post_type() {
        $labels = array(
            'name' => 'Grip Designs',
            'singular_name' => 'Grip Design',
            'add_new' => 'Add New Design',
            'add_new_item' => 'Add New Grip Design',
            'edit_item' => 'Edit Grip Design',
            'new_item' => 'New Grip Design',
            'view_item' => 'View Grip Design',
            'search_items' => 'Search Grip Designs',
            'not_found' => 'No grip designs found',
            'not_found_in_trash' => 'No grip designs found in trash',
            'menu_name' => 'Grip Designs'
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 56,
            'menu_icon' => 'dashicons-art',
            'supports' => array('title', 'editor', 'custom-fields'),
            'hierarchical' => false,
            'rewrite' => array('slug' => 'grip-designs'),
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'has_archive' => true,
            'show_in_nav_menus' => true,
            'publicly_queryable' => true,
            'show_in_rest' => true
        );

        register_post_type('grip_design', $args);
    }

    public function register_statuses() {
        register_post_status('artwork_pending', array(
            'label' => _x('Artwork Pending', 'grip-design'),
            'public' => true,
            'label_count' => _n_noop('Artwork Pending <span class="count">(%s)</span>',
                                    'Artwork Pending <span class="count">(%s)</span>')
        ));
        
        register_post_status('artwork_approved', array(
            'label' => _x('Artwork Approved', 'grip-design'),
            'public' => true,
            'label_count' => _n_noop('Artwork Approved <span class="count">(%s)</span>',
                                    'Artwork Approved <span class="count">(%s)</span>')
        ));
    }

    public function load_grip_design_template($template) {
        global $post;

        if ($post->post_type === 'grip_design') {
            $custom_template = plugin_dir_path(dirname(__FILE__)) . 'templates/single-grip-design.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        return $template;
    }
}