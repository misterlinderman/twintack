<?php
/**
 * Custom Grip Introduction Page ACF Fields
 * 
 * Handles ACF field registration for the Custom Grip Introduction page template
 *
 * @package twintack2025
 */

class TwinTack_Custom_Grip_Intro_Fields {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('acf/init', array($this, 'register_custom_grip_intro_fields'));
    }

    public function register_custom_grip_intro_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group(array(
            'key' => 'group_custom_grip_intro',
            'title' => 'Custom Grip Introduction Page Settings',
            'fields' => array(
                array(
                    'key' => 'field_header_title',
                    'label' => 'Header Title',
                    'name' => 'header_title',
                    'type' => 'text',
                    'instructions' => 'Custom title for the page header. If left empty, the page title will be used.',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => '',
                    'placeholder' => 'Enter custom header title...',
                    'prepend' => '',
                    'append' => '',
                    'maxlength' => '',
                ),
                array(
                    'key' => 'field_header_description',
                    'label' => 'Header Description',
                    'name' => 'header_description',
                    'type' => 'textarea',
                    'instructions' => 'Optional description text that appears below the header title.',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => '',
                    'placeholder' => 'Enter header description...',
                    'maxlength' => '',
                    'rows' => 3,
                    'new_lines' => 'br',
                ),
                array(
                    'key' => 'field_header_background_image',
                    'label' => 'Header Background Image',
                    'name' => 'header_background_image',
                    'type' => 'image',
                    'instructions' => 'Optional background image for the header section.',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'return_format' => 'array',
                    'preview_size' => 'medium',
                    'library' => 'all',
                    'min_width' => '',
                    'min_height' => '',
                    'min_size' => '',
                    'max_width' => '',
                    'max_height' => '',
                    'max_size' => '',
                    'mime_types' => 'jpg,jpeg,png,webp',
                ),
                array(
                    'key' => 'field_page_intro_text',
                    'label' => 'Page Introduction Text',
                    'name' => 'page_intro_text',
                    'type' => 'wysiwyg',
                    'instructions' => 'Optional introduction text that appears before the process steps.',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => '',
                    'tabs' => 'all',
                    'toolbar' => 'full',
                    'media_upload' => 1,
                    'delay' => 0,
                ),
                array(
                    'key' => 'field_cta_section_title',
                    'label' => 'Call to Action Section Title',
                    'name' => 'cta_section_title',
                    'type' => 'text',
                    'instructions' => 'Custom title for the call-to-action section at the bottom of the page.',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '50',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => 'Ready to Get Started?',
                    'placeholder' => 'Enter CTA title...',
                    'prepend' => '',
                    'append' => '',
                    'maxlength' => '',
                ),
                array(
                    'key' => 'field_cta_section_subtitle',
                    'label' => 'Call to Action Section Subtitle',
                    'name' => 'cta_section_subtitle',
                    'type' => 'textarea',
                    'instructions' => 'Subtitle text for the call-to-action section.',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '50',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => 'Join teams and players nationwide who trust TwinTack for their custom grip needs.',
                    'placeholder' => 'Enter CTA subtitle...',
                    'maxlength' => '',
                    'rows' => 2,
                    'new_lines' => 'br',
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'page_template',
                        'operator' => '==',
                        'value' => 'page-custom-grip-intro.php',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => 'Customize the header and content for the Custom Grip Introduction page.',
        ));
    }
}

// Initialize the class
add_action('init', array('TwinTack_Custom_Grip_Intro_Fields', 'get_instance'));
