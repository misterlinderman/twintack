<?php
/**
 * Product feature block (below fold).
 *
 * @package twintack2025
 * @var array $args Template args.
 */

if (!defined('ABSPATH')) {
    exit;
}

$meta = isset($args['meta']) ? $args['meta'] : array();

if (empty($meta['feature_heading']) && empty($meta['feature_body']) && empty($meta['feature_image'])) {
    return;
}
?>
<section class="tt-v2-product-feature">
    <div class="tt-v2-product-feature__inner container">
        <?php if (!empty($meta['feature_image'])) : ?>
            <div class="tt-v2-product-feature__media">
                <img src="<?php echo esc_url($meta['feature_image']); ?>" alt="" loading="lazy">
            </div>
        <?php endif; ?>
        <div class="tt-v2-product-feature__content">
            <?php if (!empty($meta['feature_heading'])) : ?>
                <h2 class="tt-v2-product-feature__heading"><?php echo esc_html($meta['feature_heading']); ?></h2>
            <?php endif; ?>
            <?php if (!empty($meta['feature_body'])) : ?>
                <div class="tt-v2-product-feature__body"><?php echo wp_kses_post(wpautop($meta['feature_body'])); ?></div>
            <?php endif; ?>
        </div>
    </div>
</section>
