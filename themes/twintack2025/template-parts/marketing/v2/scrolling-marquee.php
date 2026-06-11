<?php
/**
 * Scrolling trust marquee.
 *
 * @package twintack2025
 * @var array $args Template args.
 */

if (!defined('ABSPATH')) {
    exit;
}

$meta  = isset($args['meta']) ? $args['meta'] : array();
$variant = isset($args['variant']) ? $args['variant'] : 'dark';
$items = isset($meta['marquee_items']) ? (array) $meta['marquee_items'] : array();
$items = array_values(array_filter(array_map('trim', $items)));

if (empty($items)) {
    return;
}

/**
 * Repeat base items so each scroll half is wider than typical viewports.
 * Two identical halves + -50% animation = seamless infinite loop.
 */
$set_repeats = (int) apply_filters('twintack_v2_marquee_set_repeats', 4);
$set_items   = array();
for ($repeat = 0; $repeat < max(2, $set_repeats); $repeat++) {
    $set_items = array_merge($set_items, $items);
}

$render_marquee_set = static function ($set_items, $is_duplicate = false) {
    ?>
    <div class="tt-v2-marquee__set"<?php echo $is_duplicate ? ' aria-hidden="true"' : ''; ?>>
        <?php foreach ($set_items as $index => $item) : ?>
            <?php if ($index > 0) : ?>
                <span class="tt-v2-marquee__sep" aria-hidden="true">|</span>
            <?php endif; ?>
            <span class="tt-v2-marquee__item"><?php echo esc_html($item); ?></span>
        <?php endforeach; ?>
    </div>
    <?php
};
?>
<section class="tt-v2-marquee tt-v2-marquee--<?php echo esc_attr($variant); ?>" aria-label="<?php esc_attr_e('Promotional messages', 'twintack2025'); ?>">
    <div class="tt-v2-marquee__fade tt-v2-marquee__fade--left"></div>
    <div class="tt-v2-marquee__track">
        <?php $render_marquee_set($set_items); ?>
        <?php $render_marquee_set($set_items, true); ?>
    </div>
    <div class="tt-v2-marquee__fade tt-v2-marquee__fade--right"></div>
</section>
