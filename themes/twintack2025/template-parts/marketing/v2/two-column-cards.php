<?php
/**
 * Peak performance intro + banner block grid (homepage-v2 context).
 *
 * @package twintack2025
 * @var array $args Template args.
 */

if (!defined('ABSPATH')) {
    exit;
}

$meta     = isset($args['meta']) ? $args['meta'] : array();
$heading  = isset($meta['peak_heading']) ? $meta['peak_heading'] : '';
$subheading = isset($meta['peak_subheading']) ? $meta['peak_subheading'] : '';

$html = '';
if (shortcode_exists('twintack_banner_block')) {
    $html = do_shortcode('[twintack_banner_block context="homepage-v2"]');
}

if ('' === trim(wp_strip_all_tags($html)) && '' === $heading && '' === $subheading) {
    return;
}

$allowed = array(
    'em'     => array(),
    'strong' => array(),
    'span'   => array('class' => array()),
    'br'     => array(),
);
?>
<section class="tt-v2-two-col tt-v2-peak-banners">
    <?php if ($heading || $subheading) : ?>
        <div class="tt-v2-peak-banners__intro container">
            <?php if ($heading) : ?>
                <h2 class="tt-v2-peak-banners__title"><?php echo wp_kses($heading, $allowed); ?></h2>
            <?php endif; ?>
            <?php if ($subheading) : ?>
                <p class="tt-v2-peak-banners__sub"><?php echo esc_html($subheading); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ('' !== trim(wp_strip_all_tags($html))) : ?>
        <div class="container tt-v2-two-col__grid">
            <?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode HTML. ?>
        </div>
    <?php endif; ?>
</section>
