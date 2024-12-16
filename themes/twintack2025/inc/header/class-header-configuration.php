<?php
/**
 * Header Configuration Class
 */
class Header_Configuration {
    /**
     * Get header configuration for current page
     */
    public static function get_header_config() {
        $config = array();

        if (is_page()) {
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

    /**
     * Parse manual header configuration
     */
    private static function parse_manual_config($manual_config) {
        $config = array();

        if (!empty($manual_config)) {
            $layout = $manual_config[0];
            $config['type'] = $layout['acf_fc_layout'];
            $config['title'] = $layout['header_title'];
            $config['content'] = $layout['header_content'];

            if ($config['type'] === 'image_single') {
                $config['background'] = $layout['background_image'];
            } elseif ($config['type'] === 'video_single') {
                $config['video'] = $layout['background_video_upload'];
                $config['embed'] = $layout['embed_responsively_shortcode'];
            }
        }

        return $config;
    }

    /**
     * Parse selected header configuration
     */
    private static function parse_selected_config($selected_config) {
        $config = array();

        if ($selected_config) {
            $config['type'] = get_field('image_or_video', $selected_config->ID);
            $config['background'] = get_field('background_image', $selected_config->ID);
            $config['embed'] = get_field('embed_responsively_shortcode', $selected_config->ID);
            
            $show_callout = get_field('header_callout_content', $selected_config->ID);
            if ($show_callout === 'on') {
                $config['callout_title'] = get_field('callout_content_title', $selected_config->ID);
                $config['callout_content'] = get_field('callout_content', $selected_config->ID);
            }

            $show_links = get_field('header_callout_links', $selected_config->ID);
            if ($show_links === 'on') {
                $config['links'] = get_field('callout_links', $selected_config->ID);
            }
        }

        return $config;
    }
}