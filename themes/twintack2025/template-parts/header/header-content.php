<?php
/**
 * Template part for displaying the header content
 */

$header_config = Header_Configuration::get_header_config();

if (!empty($header_config)) :
?>
<div class="site-header__content">
    <?php if ($header_config['type'] === 'image_single' && !empty($header_config['background'])) : ?>
        <div class="site-header__background" style="background-image: url('<?php echo esc_url($header_config['background']); ?>')"></div>
    <?php elseif ($header_config['type'] === 'video_single') : ?>
        <?php if (!empty($header_config['embed'])) : ?>
            <div class="site-header__video">
                <?php echo do_shortcode($header_config['embed']); ?>
            </div>
        <?php elseif (!empty($header_config['video'])) : ?>
            <video class="site-header__video" autoplay muted loop playsinline>
                <source src="<?php echo esc_url($header_config['video']); ?>" type="video/mp4">
            </video>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($header_config['callout_title']) || !empty($header_config['callout_content'])) : ?>
        <div class="site-header__callout">
            <?php if (!empty($header_config['callout_title'])) : ?>
                <h1 class="site-header__title"><?php echo esc_html($header_config['callout_title']); ?></h1>
            <?php endif; ?>

            <?php if (!empty($header_config['callout_content'])) : ?>
                <div class="site-header__text">
                    <?php echo wp_kses_post($header_config['callout_content']); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($header_config['links'])) : ?>
                <div class="site-header__links">
                    <?php foreach ($header_config['links'] as $link) : ?>
                        <a href="<?php echo esc_url($link['callout_link_url']); ?>" class="button">
                            <?php echo esc_html($link['callout_link_text']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>