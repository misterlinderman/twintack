<?php
/**
 * Theme Hooks Class
 * Manages theme-specific WordPress hooks
 */
class Theme_Hooks {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('body_class', array($this, 'add_body_classes'));
        add_filter('excerpt_more', array($this, 'custom_excerpt_more'));
        add_filter('excerpt_length', array($this, 'custom_excerpt_length'));
    }

    public function add_body_classes($classes) {
        if (!is_singular()) {
            $classes[] = 'hfeed';
        }

        if (is_active_sidebar('sidebar-1')) {
            $classes[] = 'has-sidebar';
        }

        return $classes;
    }

    public function custom_excerpt_more($more) {
        return '...';
    }

    public function custom_excerpt_length($length) {
        return 20;
    }
}