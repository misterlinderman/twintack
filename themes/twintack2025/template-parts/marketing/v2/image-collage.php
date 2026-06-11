<?php
/**
 * Image collage + value proposition block.
 *
 * @package twintack2025
 * @var array $args Template args.
 */

if (!defined('ABSPATH')) {
    exit;
}

$meta    = isset($args['meta']) ? $args['meta'] : array();
$images  = isset($meta['collage_images']) ? array_filter((array) $meta['collage_images']) : array();
$bullets = isset($meta['collage_bullets']) ? (array) $meta['collage_bullets'] : array();
$labels  = array('a', 'b', 'c');

$cta_text = !empty($meta['collage_cta_text']) ? $meta['collage_cta_text'] : '';
$cta_url  = !empty($meta['collage_cta_url'])
    ? $meta['collage_cta_url']
    : (class_exists('TwinTack_Marketing_Homepage_V2')
        ? TwinTack_Marketing_Homepage_V2::get_default_shop_url()
        : home_url('/shop/'));
?>
<section class="tt-v2-collage">
    <div class="tt-v2-collage__inner container">
        <div class="tt-v2-collage__media">
            <?php foreach ($labels as $index => $label) :
                $url = isset($images[ $index ]) ? $images[ $index ] : '';
                ?>
                <div class="tt-v2-collage__img tt-v2-collage__img--<?php echo esc_attr($label); ?>">
                    <?php if ($url) : ?>
                        <img src="<?php echo esc_url($url); ?>" alt="" loading="lazy">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="tt-v2-collage__content">
            <?php if (!empty($meta['collage_heading_1']) || !empty($meta['collage_heading_2'])) : ?>
                <h2 class="tt-v2-collage__heading">
                    <?php if (!empty($meta['collage_heading_1'])) : ?>
                        <span class="tt-v2-collage__heading-1"><?php echo esc_html($meta['collage_heading_1']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($meta['collage_heading_2'])) : ?>
                        <span class="tt-v2-collage__heading-2"><?php echo esc_html($meta['collage_heading_2']); ?></span>
                    <?php endif; ?>
                </h2>
            <?php endif; ?>

            <?php if (!empty($meta['collage_paragraph'])) : ?>
                <p class="tt-v2-collage__para"><?php echo esc_html($meta['collage_paragraph']); ?></p>
            <?php endif; ?>

            <?php if (!empty($bullets)) : ?>
                <ul class="tt-v2-collage__bullets">
                    <?php foreach ($bullets as $index => $bullet) : ?>
                        <li class="tt-v2-collage__bullet">
                            <span class="tt-v2-collage__bullet-icon" aria-hidden="true">
                                <?php if (!empty($bullet['icon'])) : ?>
                                    <img src="<?php echo esc_url($bullet['icon']); ?>" alt="" loading="lazy">
                                <?php elseif (class_exists('TwinTack_Marketing_Homepage_V2')) : ?>
                                    <?php
                                    echo wp_kses(
                                        TwinTack_Marketing_Homepage_V2::get_collage_bullet_icon_svg((int) $index),
                                        TwinTack_Marketing_Homepage_V2::get_collage_bullet_icon_allowed_html()
                                    );
                                    ?>
                                <?php endif; ?>
                            </span>
                            <span class="tt-v2-collage__bullet-body">
                                <strong><?php echo esc_html($bullet['title']); ?></strong>
                                <?php if (!empty($bullet['desc'])) : ?>
                                    <span><?php echo esc_html($bullet['desc']); ?></span>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($cta_text && $cta_url) : ?>
                <div class="tt-v2-collage__cta-wrap">
                    <a class="tt-v2-btn tt-v2-btn--shop" href="<?php echo esc_url($cta_url); ?>">
                        <?php echo esc_html($cta_text); ?>
                        <span class="tt-v2-collage__cta-arrow" aria-hidden="true">→</span>
                    </a>
                    <?php if (!empty($meta['collage_subtext'])) : ?>
                        <p class="tt-v2-collage__subtext"><?php echo esc_html($meta['collage_subtext']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
