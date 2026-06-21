<?php
/**
 * Marketing V2 single product content template.
 *
 * @package WooCommerce\Templates
 */

defined('ABSPATH') || exit;

global $product;

do_action('woocommerce_before_single_product');

if (post_password_required()) {
    echo get_the_password_form();
    return;
}

$product_id = $product->get_id();
$meta       = class_exists('TwinTack_Marketing_Product_Layout')
    ? TwinTack_Marketing_Product_Layout::get_product_v2_meta($product_id)
    : array();

if ($product && $product->is_type('variable') && isset($_GET['variation_id'])) {
    $variation_id = absint($_GET['variation_id']);
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $(document).on('woocommerce_variation_has_changed wc_variation_form', function() {
            if (typeof window.variationPreselected === 'undefined') {
                window.variationPreselected = true;
                var $form = $('form.variations_form');
                var variationData = $form.data('product_variations');
                var variationId = <?php echo $variation_id; ?>;
                for (var i = 0; i < variationData.length; i++) {
                    if (variationData[i].variation_id === variationId) {
                        $.each(variationData[i].attributes, function(attr_name, attr_value) {
                            if (attr_value === '') return;
                            $form.find('select[name="' + attr_name + '"]').val(attr_value).trigger('change');
                        });
                        break;
                    }
                }
                $form.find('select').prop('disabled', false);
            }
        });
    });
    </script>
    <?php
}

remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);
remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10);
remove_action('woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15);
remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20);
?>

<div id="product-<?php the_ID(); ?>" <?php wc_product_class('tt-v2-product-shell', $product); ?>>

    <section class="tt-v2-product-hero container">
        <div class="tt-v2-product-hero__gallery">
            <?php do_action('woocommerce_before_single_product_summary'); ?>
        </div>

        <div class="tt-v2-product-hero__summary summary entry-summary">
            <?php
            $rating_display = class_exists('TwinTack_Marketing_Product_Layout')
                ? TwinTack_Marketing_Product_Layout::get_summary_rating_display($product_id)
                : null;
            if ($rating_display) :
                ?>
                <div class="tt-v2-product-rating">
                    <?php echo wc_get_rating_html($rating_display['rating']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <p class="tt-v2-product-rating-line"><?php echo esc_html($rating_display['line']); ?></p>
                </div>
            <?php endif; ?>

            <?php woocommerce_template_single_title(); ?>
            <?php woocommerce_template_single_price(); ?>

            <?php
            $short_pitch = class_exists('TwinTack_Marketing_Product_Layout')
                ? TwinTack_Marketing_Product_Layout::get_short_pitch_display($product_id)
                : $product->get_short_description();
            if (!empty(trim(wp_strip_all_tags($short_pitch)))) :
                ?>
                <div class="tt-v2-product-pitch woocommerce-product-details__short-description">
                    <?php echo wp_kses_post($short_pitch); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($meta['summary_bullets'])) : ?>
                <ul class="tt-v2-product-bullets">
                    <?php foreach ((array) $meta['summary_bullets'] as $bullet) : ?>
                        <li>
                            <strong><?php echo esc_html($bullet['title']); ?></strong>
                            <?php if (!empty($bullet['desc'])) : ?>
                                <span><?php echo esc_html($bullet['desc']); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php woocommerce_template_single_add_to_cart(); ?>

            <?php if (!empty($meta['trust_icons'])) : ?>
                <ul class="tt-v2-product-trust">
                    <?php foreach ((array) $meta['trust_icons'] as $index => $icon_label) : ?>
                        <?php
                        $trust_item = class_exists('TwinTack_Marketing_Product_Layout')
                            ? TwinTack_Marketing_Product_Layout::get_trust_icon_item($icon_label, (int) $index)
                            : array(
                                'icon'  => 'fa-circle-check',
                                'line1' => $icon_label,
                                'line2' => '',
                            );
                        ?>
                        <li class="tt-v2-product-trust__item">
                            <i class="fa-solid <?php echo esc_attr($trust_item['icon']); ?>" aria-hidden="true"></i>
                            <span class="tt-v2-product-trust__text">
                                <span class="tt-v2-product-trust__line"><?php echo esc_html($trust_item['line1']); ?></span>
                                <?php if (!empty($trust_item['line2'])) : ?>
                                    <span class="tt-v2-product-trust__line"><?php echo esc_html($trust_item['line2']); ?></span>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($meta['howto_video'])) : ?>
                <div class="tt-v2-product-howto">
                    <button type="button" class="tt-v2-product-howto__btn" data-tt-v2-video="<?php echo esc_url($meta['howto_video']); ?>">
                        <span class="tt-v2-product-howto__icon" aria-hidden="true"></span>
                        <span class="tt-v2-product-howto__copy">
                            <span class="tt-v2-product-howto__label"><?php esc_html_e('How to apply your grip', 'twintack2025'); ?></span>
                            <span class="tt-v2-product-howto__hint"><?php esc_html_e('Watch the install video before you wrap', 'twintack2025'); ?></span>
                        </span>
                    </button>
                </div>
            <?php endif; ?>

            <div class="tt-v2-product-accordions">
                <?php wc_get_template('single-product/summary-tabs.php'); ?>
            </div>
        </div>
    </section>

    <?php
    if (!empty($meta['ugc_videos'])) {
        get_template_part(
            'template-parts/marketing/v2/ugc-carousel',
            null,
            array(
                'meta' => array(
                    'ugc_headline'    => !empty($meta['ugc_headline']) ? $meta['ugc_headline'] : __('See it in action', 'twintack2025'),
                    'ugc_subheadline' => isset($meta['ugc_subheadline']) ? $meta['ugc_subheadline'] : '',
                    'ugc_videos'      => $meta['ugc_videos'],
                ),
            )
        );
    }
    ?>

    <?php get_template_part('template-parts/marketing/v2/product-comparison', null, array('meta' => $meta)); ?>

    <?php
    $video_tabs_meta = class_exists('TwinTack_Marketing_Video_Tabs')
        ? TwinTack_Marketing_Video_Tabs::get_meta()
        : array();

    get_template_part(
        'template-parts/marketing/v2/video-tabs',
        null,
        array('meta' => $video_tabs_meta)
    );
    ?>

    <section class="tt-v2-product-reviews">
        <div class="container">
            <?php wc_get_template('single-product/tabs/review-content.php'); ?>
        </div>
    </section>

    <?php get_template_part('template-parts/marketing/v2/video-modal'); ?>
</div>

<?php do_action('woocommerce_after_single_product'); ?>
