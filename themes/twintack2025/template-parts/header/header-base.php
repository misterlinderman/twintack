<?php
/**
 * Template part for displaying the page header
 * 
 * @package TwinTack
 */

function get_header_configuration() {
    // Check if we're using manual configuration or selected header
    if (have_rows('header_configuration_manual')) {
        // Use flexible content
        get_flexible_header_content();
    } else {
        // Check for selected header configuration
        $header_config = get_field('select_header_configuration');
        if ($header_config) {
            // Determine if image or video
            $media_type = get_field('image_or_video', $header_config->ID);
            
            if (strpos($media_type, 'image') !== false) {
                $bg_image = get_field('background_image', $header_config->ID);
                ?>
                <header class="site-header image-header" <?php if ($bg_image) : ?>style="background-image: url('<?php echo esc_url($bg_image); ?>');"<?php endif; ?>>
                    <div class="header-content">
                        <?php get_header_content($header_config->ID); ?>
                    </div>
                </header>
                <?php
            } else {
                $embed = get_field('embed_responsively_shortcode', $header_config->ID);
                ?>
                <header class="site-header video-header">
                    <?php if ($embed) : ?>
                        <div class="video-background">
                            <?php echo do_shortcode($embed); ?>
                        </div>
                    <?php endif; ?>
                    <div class="header-content">
                        <?php get_header_content($header_config->ID); ?>
                    </div>
                </header>
                <?php
            }
        }
    }
}

function get_header_content($config_id) {
    // Get callout content if enabled
    if (get_field('header_callout_content', $config_id) === 'on:On') : ?>
        <div class="header-callout">
            <?php if ($title = get_field('callout_content_title', $config_id)) : ?>
                <h1><?php echo esc_html($title); ?></h1>
            <?php endif; ?>
            
            <?php if ($content = get_field('callout_content', $config_id)) : ?>
                <div class="callout-content">
                    <?php echo wp_kses_post($content); ?>
                </div>
            <?php endif; ?>
            
            <?php if (get_field('header_callout_links', $config_id) === 'on:On') : ?>
                <?php if (have_rows('callout_links', $config_id)) : ?>
                    <div class="callout-links">
                        <?php while (have_rows('callout_links', $config_id)) : the_row(); ?>
                            <a href="<?php echo esc_url(get_sub_field('callout_link_url')); ?>" class="callout-link">
                                <?php echo esc_html(get_sub_field('callout_link_text')); ?>
                            </a>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif;
}