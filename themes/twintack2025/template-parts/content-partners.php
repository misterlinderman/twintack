<?php
/**
 * Template part for displaying partner content
 *
 * @package twintack2025
 */

$logo = get_sub_field('logo');
$name = get_sub_field('name');
$description = get_sub_field('description');
$website = get_sub_field('website');
?>

<div class="partner-card">
    <?php if($logo): ?>
        <div class="partner-logo">
            <img src="<?php echo esc_url($logo['url']); ?>" alt="<?php echo esc_attr($logo['alt']); ?>">
        </div>
    <?php endif; ?>

    <?php if($name): ?>
        <h3 class="partner-name"><?php echo esc_html($name); ?></h3>
    <?php endif; ?>

    <?php if($description): ?>
        <div class="partner-description">
            <?php echo wp_kses_post($description); ?>
        </div>
    <?php endif; ?>

    <?php if($website): ?>
        <a href="<?php echo esc_url($website); ?>" class="partner-website" target="_blank" rel="noopener">
            <?php esc_html_e('Visit Website', 'twintack2025'); ?>
        </a>
    <?php endif; ?>
</div>
