<?php
/**
 * Rich text section heading.
 *
 * @package twintack2025
 * @var array $args Template args.
 */

if (!defined('ABSPATH')) {
    exit;
}

$heading    = isset($args['heading']) ? $args['heading'] : '';
$subheading = isset($args['subheading']) ? $args['subheading'] : '';
$variant    = isset($args['variant']) ? $args['variant'] : 'light';
$align      = isset($args['align']) ? $args['align'] : 'center';

if ('' === $heading && '' === $subheading) {
    return;
}

$allowed = array(
    'em'     => array(),
    'strong' => array(),
    'span'   => array('class' => array()),
    'br'     => array(),
);
?>
<section class="tt-v2-section-heading tt-v2-section-heading--<?php echo esc_attr($variant); ?> tt-v2-section-heading--<?php echo esc_attr($align); ?>">
    <div class="container">
        <?php if ($heading) : ?>
            <h2 class="tt-v2-section-heading__title"><?php echo wp_kses($heading, $allowed); ?></h2>
        <?php endif; ?>
        <?php if ($subheading) : ?>
            <p class="tt-v2-section-heading__sub"><?php echo esc_html($subheading); ?></p>
        <?php endif; ?>
    </div>
</section>
