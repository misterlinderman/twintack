<?php
/**
 * Template part for displaying technology content
 *
 * @package twintack2025
 */

// Get ACF fields
$title = get_sub_field('title');
$description = get_sub_field('description');
$features = get_sub_field('features');
$image = get_sub_field('image');
$cta = get_sub_field('cta');
?>

<div class="technology-card">
    <div class="technology-card__inner">
        <?php if($image): ?>
            <div class="technology-card__media">
                <img src="<?php echo esc_url($image['url']); ?>" 
                     alt="<?php echo esc_attr($image['alt']); ?>"
                     class="technology-card__image">
            </div>
        <?php endif; ?>

        <div class="technology-card__content">
            <?php if($title): ?>
                <h3 class="technology-card__title"><?php echo esc_html($title); ?></h3>
            <?php endif; ?>

            <?php if($description): ?>
                <div class="technology-card__description">
                    <?php echo wp_kses_post($description); ?>
                </div>
            <?php endif; ?>

            <?php if($features): ?>
                <ul class="technology-card__features">
                    <?php foreach($features as $feature): ?>
                        <li class="technology-card__feature">
                            <?php echo esc_html($feature['feature_text']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if($cta): ?>
                <a href="<?php echo esc_url($cta['url']); ?>" 
                   class="technology-card__cta button"
                   target="<?php echo esc_attr($cta['target']); ?>">
                    <?php echo esc_html($cta['title']); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
