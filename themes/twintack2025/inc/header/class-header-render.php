<?php
/**
 * Header Render Class
 * Handles the rendering of header components
 */
class Header_Render {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function render_header_media($config) {
        if ($config['media_type'] === 'image:Image' && $config['background_image']) {
            return sprintf(
                '<div class="site-header__media site-header__media--image" style="background-image: url(%s)"></div>',
                esc_url($config['background_image'])
            );
        } elseif ($config['media_type'] === 'video:Video' && $config['video_embed']) {
            return sprintf(
                '<div class="site-header__media site-header__media--video">%s</div>',
                do_shortcode($config['video_embed'])
            );
        }
        return '';
    }

    public function render_header_content($config) {
        if (!$config['callout_enabled']) {
            return '';
        }

        ob_start();
        ?>
        <div class="site-header__callout">
            <?php if ($config['callout_title']) : ?>
                <h2 class="site-header__callout-title"><?php echo esc_html($config['callout_title']); ?></h2>
            <?php endif; ?>

            <?php if ($config['callout_content']) : ?>
                <div class="site-header__callout-content"><?php echo wp_kses_post($config['callout_content']); ?></div>
            <?php endif; ?>

            <?php if ($config['links_enabled'] && $config['links']) : ?>
                <div class="site-header__callout-links">
                    <?php foreach ($config['links'] as $link) : ?>
                        <a href="<?php echo esc_url($link['callout_link_url']); ?>" 
                           class="site-header__callout-link">
                            <?php echo esc_html($link['callout_link_text']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}