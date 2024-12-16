<?php
/**
 * The Template for displaying products in a product category.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get the current category
$category = get_queried_object();
$category_slug = $category->slug;

// Get category-specific header
get_template_part('template-parts/headers/header', $category_slug);

get_header(); ?>

<div class="product-category-hero <?php echo esc_attr($category_slug); ?>">
    <div class="hero-marquee">
        <?php
        // Get category-specific marquee slides
        $marquee_slides = get_term_meta($category->term_id, 'category_marquee_slides', true);
        
        if (!empty($marquee_slides)) : 
            foreach ($marquee_slides as $slide) : ?>
                <div class="marquee-slide" style="background-image: url(<?php echo esc_url($slide['image']); ?>)">
                    <div class="container">
                        <div class="slide-content">
                            <h1><?php echo esc_html($slide['title']); ?></h1>
                            <?php if (!empty($slide['description'])) : ?>
                                <p><?php echo esc_html($slide['description']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($slide['link'])) : ?>
                                <a href="<?php echo esc_url($slide['link']); ?>" class="hero-cta">
                                    <?php echo esc_html($slide['button_text'] ?? 'Shop Now'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach;
        else : ?>
            <div class="category-header default">
                <div class="container">
                    <h1><?php woocommerce_page_title(); ?></h1>
                    <?php do_action('woocommerce_archive_description'); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Category specific navigation
if (has_nav_menu($category_slug)) : ?>
    <div class="category-navigation">
        <div class="container">
            <?php
            wp_nav_menu(array(
                'theme_location' => $category_slug,
                'container_class' => 'category-menu',
                'menu_class' => 'category-menu-items'
            ));
            ?>
        </div>
    </div>
<?php endif; ?>

<div class="category-content">
    <div class="container">
        <?php
        /**
         * Hook: woocommerce_before_main_content
         */
        do_action('woocommerce_before_main_content');

        if (woocommerce_product_loop()) {
            /**
             * Hook: woocommerce_before_shop_loop
             * @hooked woocommerce_output_all_notices - 10
             * @hooked woocommerce_result_count - 20
             * @hooked woocommerce_catalog_ordering - 30
             */
            do_action('woocommerce_before_shop_loop');

            // Custom category layout class based on category
            $grid_class = 'product-grid ' . $category_slug . '-grid';
            ?>
            
            <div class="<?php echo esc_attr($grid_class); ?>">
                <?php
                if (wc_get_loop_prop('total')) {
                    while (have_posts()) {
                        the_post();
                        /**
                         * Hook: woocommerce_shop_loop
                         */
                        do_action('woocommerce_shop_loop');

                        wc_get_template_part('content', 'product');
                    }
                }
                ?>
            </div>

            <?php
            /**
             * Hook: woocommerce_after_shop_loop
             * @hooked woocommerce_pagination - 10
             */
            do_action('woocommerce_after_shop_loop');
        } else {
            /**
             * Hook: woocommerce_no_products_found
             * @hooked wc_no_products_found - 10
             */
            do_action('woocommerce_no_products_found');
        }

        /**
         * Hook: woocommerce_after_main_content
         */
        do_action('woocommerce_after_main_content');
        ?>
    </div>
</div>

<?php get_footer(); ?>