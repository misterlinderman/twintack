<?php
/**
 * Template part for displaying header content
 */

$header_config = Header_Configuration::get_instance()->get_header_config();
if (!$header_config) {
    return;
}
?>

<div class="site-header__hero">
    <?php if ($header_config['media_type'] === 'image:Image' && $header_config['background_image']) : ?>
        <div class="site-header__media site-header__media--image" style="background-image: url('<?php echo esc_url($header_config['background_image']); ?>')"></div>
    <?php elseif ($header_config['media_type'] === 'video:Video' && $header_config['video_embed']) : ?>
        <div class="site-header__media site-header__media--video">
            <?php echo do_shortcode($header_config['video_embed']); ?>
        </div>
    <?php endif; ?>

    <?php if ($header_config['callout_enabled']) : ?>
        <div class="site-header__callout">
            <?php if ($header_config['callout_title']) : ?>
                <h2 class="site-header__callout-title"><?php echo esc_html($header_config['callout_title']); ?></h2>
            <?php endif; ?>

            <?php if ($header_config['callout_content']) : ?>
                <div class="site-header__callout-content">
                    <?php echo wp_kses_post($header_config['callout_content']); ?>
                </div>
            <?php endif; ?>

            <?php if ($header_config['links_enabled'] && $header_config['links']) : ?>
                <div class="site-header__callout-links">
                    <?php foreach ($header_config['links'] as $link) : ?>
                        <a href="<?php echo esc_url($link['callout_link_url']); ?>" class="site-header__callout-link">
                            <?php echo esc_html($link['callout_link_text']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>