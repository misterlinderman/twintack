<?php
/**
 * SVG Header Controls Class
 */
class TwinTack_SVG_Header_Controls {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('acf/init', array($this, 'register_svg_header_fields'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_svg_header_assets'));
    }

    public function register_svg_header_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key' => 'group_svg_header',
            'title' => 'SVG Header Configuration',
            'fields' => [
                [
                    'key' => 'field_enable_svg_header',
                    'label' => 'Enable SVG Header',
                    'name' => 'enable_svg_header',
                    'type' => 'true_false',
                    'ui' => 1
                ],
                [
                    'key' => 'field_header_background',
                    'label' => 'Header Background',
                    'name' => 'header_background',
                    'type' => 'group',
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_enable_svg_header',
                                'operator' => '==',
                                'value' => '1'
                            ]
                        ]
                    ],
                    'sub_fields' => [
                        [
                            'key' => 'field_bg_type',
                            'label' => 'Background Type',
                            'name' => 'bg_type',
                            'type' => 'select',
                            'choices' => [
                                'color' => 'Solid Color',
                                'gradient' => 'Gradient',
                                'image' => 'Image'
                            ]
                        ],
                        [
                            'key' => 'field_bg_color',
                            'label' => 'Background Color',
                            'name' => 'bg_color',
                            'type' => 'color_picker',
                            'conditional_logic' => [
                                [
                                    [
                                        'field' => 'field_bg_type',
                                        'operator' => '==',
                                        'value' => 'color'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'key' => 'field_svg_layers',
                    'label' => 'SVG Layers',
                    'name' => 'svg_layers',
                    'type' => 'repeater',
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_enable_svg_header',
                                'operator' => '==',
                                'value' => '1'
                            ]
                        ]
                    ],
                    'sub_fields' => [
                        [
                            'key' => 'field_svg_file',
                            'label' => 'SVG File',
                            'name' => 'svg_file',
                            'type' => 'file',
                            'mime_types' => 'svg'
                        ],
                        [
                            'key' => 'field_layer_color',
                            'label' => 'Layer Color',
                            'name' => 'layer_color',
                            'type' => 'color_picker'
                        ],
                        [
                            'key' => 'field_layer_opacity',
                            'label' => 'Opacity',
                            'name' => 'layer_opacity',
                            'type' => 'range',
                            'min' => 0,
                            'max' => 100,
                            'step' => 1
                        ],
                        [
                            'key' => 'field_animation_type',
                            'label' => 'Animation',
                            'name' => 'animation_type',
                            'type' => 'select',
                            'choices' => [
                                'none' => 'None',
                                'parallax' => 'Parallax',
                                'float' => 'Float'
                            ]
                        ]
                    ]
                ]
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'page'
                    ]
                ]
            ]
        ]);
    }

    public function enqueue_svg_header_assets() {
        wp_enqueue_style(
            'twintack-svg-header',
            get_template_directory_uri() . '/css/components/svg-header/style.css',
            array(),
            wp_get_theme()->get('Version')
        );

        wp_enqueue_script(
            'twintack-svg-header',
            get_template_directory_uri() . '/assets/js/svg-header/animations.js',
            array('jquery'),
            wp_get_theme()->get('Version'),
            true
        );
    }
}

// Initialize the class
add_action('init', array('TwinTack_SVG_Header_Controls', 'get_instance'));