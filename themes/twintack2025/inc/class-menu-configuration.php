<?php
/**
 * Menu Configuration Class
 */
class Menu_Configuration {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('wp_nav_menu_items', array($this, 'add_category_links'), 10, 2);
    }

    public function add_category_links($items, $args) {
        // Only modify primary menu
        if ($args->theme_location !== 'primary') {
            return $items;
        }

        // Get Baseball categories
        $baseball_cats = get_terms(array(
            'taxonomy' => 'product_cat',
            'name__like' => 'baseball',
            'hide_empty' => false
        ));

        // Get Fishing categories
        $fishing_cats = get_terms(array(
            'taxonomy' => 'product_cat',
            'name__like' => 'fishing',
            'hide_empty' => false
        ));

        // Add Baseball link
        if (!empty($baseball_cats)) {
            $items .= '<li class="menu-item baseball-menu">';
            $items .= '<a href="' . get_term_link($baseball_cats[0]) . '">Bat Grips</a>';
            $items .= '</li>';
        }

        // Add Fishing link 
        if (!empty($fishing_cats)) {
            $items .= '<li class="menu-item fishing-menu">';
            $items .= '<a href="' . get_term_link($fishing_cats[0]) . '">Fishing</a>';
            $items .= '</li>';
        }

        return $items;
    }
} 