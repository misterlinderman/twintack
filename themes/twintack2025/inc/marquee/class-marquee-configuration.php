<?php
/**
 * Marquee Configuration Class
 *
 * @package twintack2025
 */

class TwinTack_Marquee_Configuration {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_marquee_post_type'));
        add_action('acf/init', array($this, 'register_marquee_fields'));
    }

    public function register_marquee_post_type() {
        $args = array(
            'public' => true,
            'label'  => 'Marquee Configurations',
            'supports' => array('title', 'custom-fields'),
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-admin-post',
            'rewrite' => array(
                'slug' => 'marquee-config',
                'with_front' => true
            )
        );
        register_post_type('marquee-config', $args);
    }

    public function register_marquee_fields() {
        if (function_exists('acf_add_local_field_group')) {
            acf_add_local_field_group(array(
                'key' => 'group_marquee_tint_overlay',
                'title' => 'Marquee Tint Overlay Settings',
                'fields' => array(
                    array(
                        'key' => 'field_marquee_tint_enabled',
                        'label' => 'Enable Tint Overlay',
                        'name' => 'tint_enabled',
                        'type' => 'true_false',
                        'instructions' => 'Enable a colored tint overlay on top of the background image',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'message' => '',
                        'default_value' => 0,
                        'ui' => 1,
                        'ui_on_text' => 'Yes',
                        'ui_off_text' => 'No',
                    ),
                    array(
                        'key' => 'field_marquee_tint_color',
                        'label' => 'Tint Color',
                        'name' => 'tint_color',
                        'type' => 'color_picker',
                        'instructions' => 'Choose the color for the tint overlay',
                        'required' => 0,
                        'conditional_logic' => array(
                            array(
                                array(
                                    'field' => 'field_marquee_tint_enabled',
                                    'operator' => '==',
                                    'value' => '1',
                                ),
                            ),
                        ),
                        'wrapper' => array(
                            'width' => '50',
                            'class' => '',
                            'id' => '',
                        ),
                        'default_value' => '#000000',
                        'enable_opacity' => 0,
                        'return_format' => 'string',
                    ),
                    array(
                        'key' => 'field_marquee_tint_opacity',
                        'label' => 'Tint Opacity',
                        'name' => 'tint_opacity',
                        'type' => 'range',
                        'instructions' => 'Set the opacity/transparency of the tint overlay (0 = transparent, 1 = opaque)',
                        'required' => 0,
                        'conditional_logic' => array(
                            array(
                                array(
                                    'field' => 'field_marquee_tint_enabled',
                                    'operator' => '==',
                                    'value' => '1',
                                ),
                            ),
                        ),
                        'wrapper' => array(
                            'width' => '50',
                            'class' => '',
                            'id' => '',
                        ),
                        'default_value' => 0.3,
                        'min' => 0,
                        'max' => 1,
                        'step' => 0.1,
                        'prepend' => '',
                        'append' => '',
                    ),
                ),
                'location' => array(
                    array(
                        array(
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'marquee-config',
                        ),
                    ),
                ),
                'menu_order' => 1,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'top',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
            ));
        }
    }

    public static function get_marquee_config($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        $marquee_config = get_field('select_marquee_configuration', $post_id);
        if (!$marquee_config) {
            return false;
        }

        return array(
            'background_image' => get_field('background_image', $marquee_config->ID),
            'embed_shortcode' => get_field('embed_responsively_shortcode', $marquee_config->ID),
            'product_image' => get_field('product_image', $marquee_config->ID),
            'callout_title' => get_field('callout_content_title', $marquee_config->ID),
            'callout_content' => get_field('callout_content', $marquee_config->ID),
            'callout_links' => get_field('callout_links', $marquee_config->ID),
            'tint_enabled' => get_field('tint_enabled', $marquee_config->ID),
            'tint_color' => get_field('tint_color', $marquee_config->ID),
            'tint_opacity' => get_field('tint_opacity', $marquee_config->ID)
        );
    }
} 