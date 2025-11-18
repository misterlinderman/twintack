<?php
/**
 * Team Member Class
 *
 * @package twintack2025
 */

class TwinTack_Team_Member {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_team_member_post_type'));
        add_action('acf/init', array($this, 'register_team_member_fields'));
    }

    public function register_team_member_post_type() {
        $args = array(
            'public' => true,
            'label'  => 'Team Members',
            'supports' => array('custom-fields'),
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-admin-users',
        );
        register_post_type('team-member', $args);
    }

    public function register_team_member_fields() {
        if (function_exists('acf_add_local_field_group')) {
            // ACF fields will be registered automatically from JSON
        }
    }
} 