<?php
/**
 * Header Configuration Class
 */
class Header_Configuration {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function get_header_config() {
        $config = array();

        if (is_page() && function_exists('get_field')) {
            $manual_config = get_field('header_configuration_manual');
            $selected_config = get_field('select_header_configuration');

            if ($manual_config) {
                $config = self::parse_manual_config($manual_config);
            } elseif ($selected_config) {
                $config = self::parse_selected_config($selected_config);
            }
        }

        return $config;
    }

    private static function parse_manual_config($manual_config) {
        $config = array();

        if (!empty($manual_config)) {
            foreach ($manual_config as $layout) {
                $config['type'] = $layout['acf_fc_layout'];
                $config['title'] = isset($layout['header_title']) ? $layout['header_title'] : '';
                $config['content'] = isset($layout['header_content']) ? $layout['header_content'] : '';

                if ($config['type'] === 'image_single') {
                    $config['background'] = isset($layout['background_image']) ? $layout['background_image'] : '';
                } elseif ($config['type'] === 'video_single') {
                    $config['video'] = isset($layout['background_video_upload']) ? $layout['background_video_upload'] : '';
                    $config['embed'] = isset($layout['embed_responsively_shortcode']) ? $layout['embed_responsively_shortcode'] : '';
                }
            }
        }

        return $config;
    }

    private static function parse_selected_config($selected_config) {
        $config = array();
        
        if ($selected_config && function_exists('get_field')) {
            $config['type'] = get_field('image_or_video', $selected_config->ID);
            $config['title'] = get_field('header_title', $selected_config->ID);
            $config['content'] = get_field('header_content', $selected_config->ID);
            
            if (strpos($config['type'], 'image') !== false) {
                $config['background'] = get_field('background_image', $selected_config->ID);
            } else {
                $config['video'] = get_field('background_video_upload', $selected_config->ID);
                $config['embed'] = get_field('embed_responsively_shortcode', $selected_config->ID);
            }
        }

        return $config;
    }
}