<?php
/**
 * Header Render Class
 */
class Header_Render {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function render_header($config) {
        if (!$config) return;

        $output = '<div class="site-header">';
        
        // Render background
        if (!empty($config['background_image'])) {
            $output .= sprintf(
                '<div class="site-header__media site-header__media--image" style="background-image: url(%s)"></div>',
                esc_url($config['background_image'])
            );
        } elseif (!empty($config['embed_shortcode'])) {
            $output .= sprintf(
                '<div class="site-header__media site-header__media--video">%s</div>',
                do_shortcode($config['embed_shortcode'])
            );
        }

        // Render content
        $output .= '<div class="site-header__content">';
        if (!empty($config['callout_title'])) {
            $output .= sprintf('<h1 class="site-header__title">%s</h1>', esc_html($config['callout_title']));
        }
        if (!empty($config['callout_content'])) {
            $output .= sprintf('<div class="site-header__text">%s</div>', wp_kses_post($config['callout_content']));
        }

        // Render links
        if (!empty($config['callout_links'])) {
            $output .= '<div class="site-header__links">';
            foreach ($config['callout_links'] as $link) {
                $output .= sprintf(
                    '<a href="%s" class="button">%s</a>',
                    esc_url($link['callout_link_url']),
                    esc_html($link['callout_link_text'])
                );
            }
            $output .= '</div>';
        }

        $output .= '</div></div>';

        return $output;
    }
}