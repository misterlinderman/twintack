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
            'label' => _x('Artwork Pending', 'post status', 'twintack-grip-manager'),
            'public' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Artwork Pending <span class="count">(%s)</span>',
                                    'Artwork Pending <span class="count">(%s)</span>')
        ));
    }
}