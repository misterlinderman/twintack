<?php
/**
 * Template Name: Marketing Target Page
 *
 * Campaign landing page template with video hero, product grid,
 * and video feature blocks for the TT Pro Flex launch and future campaigns.
 *
 * @package twintack2025
 */

if (!defined('ABSPATH')) exit;

get_header();

$page_id = get_the_ID();
$meta    = array();

if (class_exists('TwinTack_Marketing_Target_Page')) {
    $meta = TwinTack_Marketing_Target_Page::get_target_page_meta($page_id);
}

// Provide safe defaults when the plugin class isn't available
$hero_type     = isset($meta['hero_type'])          ? $meta['hero_type']          : 'image';
$video_desktop = isset($meta['hero_video_desktop']) ? $meta['hero_video_desktop'] : '';
$video_mobile  = isset($meta['hero_video_mobile'])  ? $meta['hero_video_mobile']  : '';
$image_desktop = isset($meta['hero_image_desktop']) ? $meta['hero_image_desktop'] : '';
$image_mobile  = isset($meta['hero_image_mobile'])  ? $meta['hero_image_mobile']  : '';
$hero_title    = isset($meta['hero_title'])         ? $meta['hero_title']         : '';
$hero_subtitle = isset($meta['hero_subtitle'])      ? $meta['hero_subtitle']      : '';
$cta_text      = isset($meta['hero_cta_text'])      ? $meta['hero_cta_text']      : '';
$cta_url       = isset($meta['hero_cta_url'])       ? $meta['hero_cta_url']       : '';

$product_cat     = isset($meta['product_category']) ? $meta['product_category'] : '';
$product_heading = isset($meta['product_heading'])  ? $meta['product_heading']  : '';
$product_columns = isset($meta['product_columns'])  ? intval($meta['product_columns']) : 4;
$product_limit   = isset($meta['product_limit'])    ? intval($meta['product_limit'])   : 8;

$image_row         = isset($meta['image_row']) && is_array($meta['image_row']) ? $meta['image_row'] : array();
$image_row_heading = isset($meta['image_row_heading']) ? $meta['image_row_heading'] : '';

$video_blocks = isset($meta['video_blocks']) && is_array($meta['video_blocks']) ? $meta['video_blocks'] : array();

$has_hero = $video_desktop || $image_desktop || $image_mobile || $hero_title;
?>

<main id="primary" class="site-main twintack-target-page">

    <?php
    /* =======================================================================
     * HERO SECTION
     * ==================================================================== */
    if ($has_hero) :
        $hero_classes = 'twintack-target-hero hero-type-' . esc_attr($hero_type);
    ?>
    <section class="<?php echo esc_attr($hero_classes); ?>">

        <?php if ($hero_type === 'video_background' && $video_desktop) : ?>
            <!-- Desktop background video -->
            <div class="target-hero-video-wrap desktop-only">
                <video class="target-hero-video"
                       autoplay muted loop playsinline
                       <?php if ($image_desktop) : ?>poster="<?php echo esc_url($image_desktop); ?>"<?php endif; ?>>
                    <source src="<?php echo esc_url($video_desktop); ?>" type="video/mp4">
                    <?php if ($image_desktop) : ?>
                        <img src="<?php echo esc_url($image_desktop); ?>"
                             alt="<?php echo esc_attr($hero_title ?: get_the_title()); ?>" />
                    <?php endif; ?>
                </video>
            </div>

            <!-- Mobile: video if provided, otherwise static image -->
            <?php if ($video_mobile) : ?>
                <div class="target-hero-video-wrap mobile-only">
                    <video class="target-hero-video"
                           autoplay muted loop playsinline
                           <?php if ($image_mobile) : ?>poster="<?php echo esc_url($image_mobile); ?>"<?php endif; ?>>
                        <source src="<?php echo esc_url($video_mobile); ?>" type="video/mp4">
                    </video>
                </div>
            <?php elseif ($image_mobile) : ?>
                <picture class="target-hero-image mobile-only">
                    <img src="<?php echo esc_url($image_mobile); ?>"
                         alt="<?php echo esc_attr($hero_title ?: get_the_title()); ?>" />
                </picture>
            <?php endif; ?>

            <!-- Text overlay -->
            <?php if ($hero_title || $hero_subtitle || $cta_text) : ?>
                <div class="target-hero-overlay">
                    <div class="container">
                        <?php if ($hero_title) : ?>
                            <h1 class="target-hero-title"><?php echo esc_html($hero_title); ?></h1>
                        <?php endif; ?>
                        <?php if ($hero_subtitle) : ?>
                            <p class="target-hero-subtitle"><?php echo esc_html($hero_subtitle); ?></p>
                        <?php endif; ?>
                        <?php if ($cta_text && $cta_url) : ?>
                            <a href="<?php echo esc_url($cta_url); ?>" class="target-hero-cta"><?php echo esc_html($cta_text); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($hero_type === 'video_full' && $video_desktop) : ?>
            <!-- Full-width video (no overlay) -->
            <div class="target-hero-video-wrap target-hero-video-full desktop-only">
                <video class="target-hero-video"
                       autoplay muted loop playsinline
                       <?php if ($image_desktop) : ?>poster="<?php echo esc_url($image_desktop); ?>"<?php endif; ?>>
                    <source src="<?php echo esc_url($video_desktop); ?>" type="video/mp4">
                </video>
            </div>

            <?php if ($video_mobile) : ?>
                <div class="target-hero-video-wrap target-hero-video-full mobile-only">
                    <video class="target-hero-video"
                           autoplay muted loop playsinline
                           <?php if ($image_mobile) : ?>poster="<?php echo esc_url($image_mobile); ?>"<?php endif; ?>>
                        <source src="<?php echo esc_url($video_mobile); ?>" type="video/mp4">
                    </video>
                </div>
            <?php elseif ($image_mobile) : ?>
                <picture class="target-hero-image mobile-only">
                    <img src="<?php echo esc_url($image_mobile); ?>"
                         alt="<?php echo esc_attr($hero_title ?: get_the_title()); ?>" />
                </picture>
            <?php endif; ?>

        <?php else : ?>
            <!-- Static image hero (default) -->
            <picture class="target-hero-image">
                <?php if ($image_mobile) : ?>
                    <source media="(max-width: 768px)" srcset="<?php echo esc_url($image_mobile); ?>">
                <?php endif; ?>
                <?php if ($image_desktop) : ?>
                    <img src="<?php echo esc_url($image_desktop); ?>"
                         alt="<?php echo esc_attr($hero_title ?: get_the_title()); ?>" />
                <?php endif; ?>
            </picture>

            <?php if ($hero_title || $hero_subtitle || $cta_text) : ?>
                <div class="target-hero-overlay">
                    <div class="container">
                        <?php if ($hero_title) : ?>
                            <h1 class="target-hero-title"><?php echo esc_html($hero_title); ?></h1>
                        <?php endif; ?>
                        <?php if ($hero_subtitle) : ?>
                            <p class="target-hero-subtitle"><?php echo esc_html($hero_subtitle); ?></p>
                        <?php endif; ?>
                        <?php if ($cta_text && $cta_url) : ?>
                            <a href="<?php echo esc_url($cta_url); ?>" class="target-hero-cta"><?php echo esc_html($cta_text); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </section>
    <?php endif; // has_hero ?>

    <?php
    /* =======================================================================
     * PAGE CONTENT (WordPress editor)
     * ==================================================================== */
    while (have_posts()) :
        the_post();
        if (get_the_content()) :
    ?>
    <section class="twintack-target-content">
        <div class="container">
            <div class="entry-content">
                <?php the_content(); ?>
            </div>
        </div>
    </section>
    <?php
        endif;
    endwhile;
    ?>

    <?php
    /* =======================================================================
     * IMAGE ROW / CAROUSEL
     * ==================================================================== */
    if (!empty($image_row)) :
    ?>
    <section id="image-row" class="twintack-target-image-row">
        <div class="container">
            <?php if ($image_row_heading) : ?>
                <h2 class="target-image-row-heading"><?php echo esc_html($image_row_heading); ?></h2>
            <?php endif; ?>

            <div class="target-image-row-grid">
                <?php foreach ($image_row as $ir_img) : ?>
                    <div class="target-image-row-item">
                        <img src="<?php echo esc_url($ir_img); ?>" alt="" loading="lazy" />
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php
    /* =======================================================================
     * VIDEO FEATURE BLOCKS
     * ==================================================================== */
    if (!empty($video_blocks)) :
    ?>
    <section id="video-features" class="twintack-target-video-blocks">
        <?php foreach ($video_blocks as $vb_index => $vblock) :
            $vb_url     = isset($vblock['video_url'])   ? $vblock['video_url']   : '';
            $vb_type    = isset($vblock['video_type'])  ? $vblock['video_type']  : 'mp4';
            $vb_heading  = isset($vblock['heading'])     ? $vblock['heading']     : '';
            $vb_desc     = isset($vblock['description']) ? $vblock['description'] : '';
            $vb_cta_text = isset($vblock['cta_text'])    ? $vblock['cta_text']    : '';
            $vb_cta_url  = isset($vblock['cta_url'])     ? $vblock['cta_url']     : '';
            $vb_layout   = isset($vblock['layout'])      ? $vblock['layout']      : 'video_left';

            if (empty($vb_url) && empty($vb_heading)) {
                continue;
            }

            $block_classes = 'target-video-block layout-' . esc_attr($vb_layout);
        ?>
        <div class="<?php echo esc_attr($block_classes); ?>">
            <div class="container">
                <div class="target-video-block-inner">
                    <?php if ($vb_url) : ?>
                        <div class="target-video-block-media">
                            <?php if ($vb_type === 'mp4') : ?>
                                <video class="target-feature-video"
                                       muted loop playsinline preload="metadata"
                                       data-autoplay-on-scroll="true">
                                    <source src="<?php echo esc_url($vb_url); ?>" type="video/mp4">
                                </video>
                            <?php elseif ($vb_type === 'image') : ?>
                                <img class="target-feature-block-image"
                                     src="<?php echo esc_url($vb_url); ?>"
                                     alt="<?php echo esc_attr($vb_heading ?: ''); ?>"
                                     loading="lazy" />
                            <?php elseif ($vb_type === 'youtube') :
                                $yt_id = twintack_extract_youtube_id($vb_url);
                                if ($yt_id) :
                            ?>
                                <div class="target-video-embed">
                                    <iframe src="https://www.youtube.com/embed/<?php echo esc_attr($yt_id); ?>?rel=0"
                                            frameborder="0" allowfullscreen
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                            loading="lazy"></iframe>
                                </div>
                            <?php endif; ?>
                            <?php elseif ($vb_type === 'vimeo') :
                                $vimeo_id = twintack_extract_vimeo_id($vb_url);
                                if ($vimeo_id) :
                            ?>
                                <div class="target-video-embed">
                                    <iframe src="https://player.vimeo.com/video/<?php echo esc_attr($vimeo_id); ?>?title=0&byline=0&portrait=0"
                                            frameborder="0" allowfullscreen
                                            loading="lazy"></iframe>
                                </div>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($vb_heading || $vb_desc) : ?>
                        <div class="target-video-block-text">
                            <?php if ($vb_heading) : ?>
                                <h3 class="target-video-block-heading"><?php echo esc_html($vb_heading); ?></h3>
                            <?php endif; ?>
                            <?php if ($vb_desc) : ?>
                                <div class="target-video-block-desc"><?php echo wp_kses_post(wpautop($vb_desc)); ?></div>
                            <?php endif; ?>
                            <?php if ($vb_cta_text && $vb_cta_url) : ?>
                                <a href="<?php echo esc_url($vb_cta_url); ?>" class="target-video-block-cta"><?php echo esc_html($vb_cta_text); ?></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <?php
    /* =======================================================================
     * PRODUCT GRID
     * ==================================================================== */
    if (!empty($product_cat) && class_exists('WooCommerce')) :
        $product_args = array(
            'post_type'      => 'product',
            'posts_per_page' => $product_limit,
            'post_status'    => 'publish',
            'tax_query'      => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => $product_cat,
                ),
            ),
        );
        $products_query = new WP_Query($product_args);

        if ($products_query->have_posts()) :
    ?>
    <section id="product-grid" class="twintack-target-product-grid">
        <div class="container">
            <?php if ($product_heading) : ?>
                <h2 class="target-product-heading"><?php echo esc_html($product_heading); ?></h2>
            <?php endif; ?>

            <ul class="products columns-<?php echo esc_attr($product_columns); ?>">
                <?php
                while ($products_query->have_posts()) :
                    $products_query->the_post();
                    global $product;

                    if ($product && $product->is_type('variable')) :
                        $variations = $product->get_available_variations();
                        $filtered   = twintack_filter_variations_by_active_filters($variations);

                        if (!empty($filtered)) :
                            foreach ($filtered as $variation) :
                                twintack_display_single_variation($variation, $product);
                            endforeach;
                        endif;
                    else :
                        wc_get_template_part('content', 'product');
                    endif;
                endwhile;
                ?>
            </ul>
        </div>
    </section>
    <?php
        endif;
        wp_reset_postdata();
    endif;
    ?>

    <?php
    /* =======================================================================
     * BANNER BLOCKS (existing marketing plugin)
     * ==================================================================== */
    if (class_exists('TwinTack_Marketing_Banner_Blocks')) {
        echo do_shortcode('[twintack_banner_block context="landing"]');
    }
    ?>

    <?php
    /* =======================================================================
     * FEATURED PRODUCTS (existing marketing plugin)
     * ==================================================================== */
    if (class_exists('TwinTack_Marketing_Featured_Products')) {
        echo do_shortcode('[twintack_featured_products context="landing" columns="4" limit="8" title="Featured Products"]');
    }
    ?>

</main>

<?php
get_footer();

/* ==========================================================================
 * Helper functions (only defined here to keep template self-contained;
 * these are safe to call multiple times thanks to function_exists checks).
 * ======================================================================= */

if (!function_exists('twintack_extract_youtube_id')) :
    /**
     * Extract YouTube video ID from various URL formats.
     *
     * @param string $url YouTube URL.
     * @return string|false Video ID or false.
     */
    function twintack_extract_youtube_id($url) {
        $pattern = '/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return false;
    }
endif;

if (!function_exists('twintack_extract_vimeo_id')) :
    /**
     * Extract Vimeo video ID from URL.
     *
     * @param string $url Vimeo URL.
     * @return string|false Video ID or false.
     */
    function twintack_extract_vimeo_id($url) {
        $pattern = '/vimeo\.com\/(?:video\/)?(\d+)/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return false;
    }
endif;
