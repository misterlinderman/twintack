<?php
/**
 * The Template for displaying product archives, including the main shop page which is a post type archive
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/archive-product.php.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.6.0
 */

defined( 'ABSPATH' ) || exit;

get_header(); ?>

<div class="page-wrapper">
    <main class="main">
        <?php
        /**
         * Hook: woocommerce_before_main_content.
         *
         * @hooked woocommerce_output_content_wrapper - 10 (outputs opening divs for the content)
         * @hooked woocommerce_breadcrumb - 20
         * @hooked WC_Structured_Data::generate_website_data() - 30
         */
        do_action( 'woocommerce_before_main_content' );

        /**
         * Hook: woocommerce_shop_loop_header.
         *
         * @since 8.6.0
         * @hooked woocommerce_product_taxonomy_archive_header - 10
         */
        do_action( 'woocommerce_shop_loop_header' );
        ?>

        <?php if ( apply_filters( 'woocommerce_show_page_title', true ) ) : ?>
            <div class="category-hero <?php echo esc_attr( wc_get_loop_class() ); ?>">
                <div class="container">
                    <h1 class="woocommerce-products-header__title page-title">
                        <?php woocommerce_page_title(); ?>
                    </h1>
                    <?php do_action( 'woocommerce_archive_description' ); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Filter Button -->
        <div class="container">
            <button class="filter-button" id="filter-button">
                <span>FILTERS</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
            </button>
        </div>

        <!-- Filter Modal -->
        <div id="filter-modal">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div id="filter-modal-backdrop"></div>
                
                <!-- Modal container -->
                <div class="filter-modal-container">
                    <!-- Modal header -->
                    <div class="flex items-center justify-between p-4 border-b">
                        <h2 class="text-2xl font-bold">FILTERS</h2>
                        <button id="filter-modal-close" class="p-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>
                    </div>
                    
                    <!-- Modal content -->
                    <form method="get" class="twintack-filter-form">
                        <div class="p-6 max-h-[70vh] overflow-y-auto">
                            <?php 
                            // Display all attribute filters
                            $attributes = wc_get_attribute_taxonomies();
                            foreach ($attributes as $attribute) :
                                $attribute_name = 'pa_' . $attribute->attribute_name;
                                $terms = get_terms(array(
                                    'taxonomy' => $attribute_name,
                                    'hide_empty' => true,
                                ));
                                
                                if (empty($terms)) {
                                    continue;
                                }

                                // Get current filter values
                                $current_filters = isset($_GET['filter_' . $attribute_name]) ? (array)$_GET['filter_' . $attribute_name] : array();
                            ?>
                            <div class="mb-8">
                                <h3 class="font-bold mb-4"><?php echo esc_html($attribute->attribute_label); ?></h3>
                                <div class="grid grid-cols-2 gap-2">
                                    <?php foreach ($terms as $term) : ?>
                                        <label class="flex items-center">
                                            <input 
                                                type="checkbox" 
                                                name="filter_<?php echo esc_attr($attribute_name); ?>[]" 
                                                value="<?php echo esc_attr($term->slug); ?>" 
                                                <?php checked(in_array($term->slug, $current_filters)); ?>
                                                class="mr-2 filter-checkbox"
                                            >
                                            <span class="text-sm"><?php echo esc_html($term->name); ?> (<?php echo esc_html($term->count); ?>)</span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Modal footer -->
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 flex justify-between">
                            <a href="<?php echo esc_url(remove_query_arg(array_map(function($attr) { 
                                return 'filter_pa_' . $attr->attribute_name; 
                            }, $attributes))); ?>" class="py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium">
                                Clear All
                            </a>
                            <button
                                type="submit"
                                class="py-2 px-4 bg-black text-white rounded-md shadow-sm text-sm font-medium"
                            >
                                Apply Filters
                            </button>
                        </div>
                        
                        <?php
                        // Preserve sort parameters if present
                        if (isset($_GET['orderby'])) {
                            echo '<input type="hidden" name="orderby" value="' . esc_attr($_GET['orderby']) . '">';
                        }
                        
                        // Preserve category parameter if present
                        if (isset($_GET['product_cat'])) {
                            echo '<input type="hidden" name="product_cat" value="' . esc_attr($_GET['product_cat']) . '">';
                        }
                        ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="container">
            <?php
            if ( woocommerce_product_loop() ) {
                /**
                 * Hook: woocommerce_before_shop_loop.
                 *
                 * @hooked woocommerce_output_all_notices - 10
                 * @hooked woocommerce_result_count - 20
                 * @hooked woocommerce_catalog_ordering - 30
                 */
                echo '<div class="product-sorting">';
                do_action( 'woocommerce_before_shop_loop' );
                echo '</div>';

                // Remove the default pagination
                remove_action('woocommerce_after_shop_loop', 'woocommerce_pagination', 10);
                
                // Set posts per page to -1 to show all products
                global $wp_query;
                $original_query = $wp_query;
                
                // Create a new query to get all products
                $args = array_merge(
                    $original_query->query,
                    array(
                        'posts_per_page' => -1,
                        'post_type' => 'product',
                        'orderby' => 'menu_order title',
                        'order' => 'ASC'
                    )
                );
                
                // Run the new query
                query_posts($args);
                
                // Create a new query for grip products (using custom field)
                $grip_args = array(
                    'post_type' => 'product',
                    'posts_per_page' => -1,
                    'meta_query' => array(
                        array(
                            'key' => 'display_type',
                            'value' => 'grip_carousel',
                            'compare' => '='
                        )
                    )
                );
                $grip_query = new WP_Query($grip_args);
                
                // Create a new query for non-grip products (using custom field)
                $other_args = array(
                    'post_type' => 'product',
                    'posts_per_page' => -1,
                    'meta_query' => array(
                        array(
                            'key' => 'display_type',
                            'value' => 'grip_carousel',
                            'compare' => '!=',
                            'type' => 'CHAR'
                        )
                    )
                );
                // For products without the custom field set
                $other_args_no_meta = array(
                    'post_type' => 'product',
                    'posts_per_page' => -1,
                    'meta_query' => array(
                        array(
                            'key' => 'display_type',
                            'compare' => 'NOT EXISTS'
                        )
                    )
                );
                $other_query1 = new WP_Query($other_args);
                $other_query2 = new WP_Query($other_args_no_meta);
                
                // Display grip products in horizontal scroll layout if there are any
                if ($grip_query->have_posts()) :
                ?>
                <div class="grip-products-section">
                    <h2 class="section-title">Grip Products</h2>
                    <div class="products-container">
                        <!-- Scroll buttons -->
                        <button class="scroll-button scroll-button-left">
                            <div class="scroll-button-inner">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="15 18 9 12 15 6"></polyline>
                                </svg>
                            </div>
                        </button>
                        <div class="products-row">
                            <?php
                            while ($grip_query->have_posts()) {
                                $grip_query->the_post();
                                // Use custom template for grip products
                                wc_get_template_part('content', 'product-grip');
                            }
                            wp_reset_postdata();
                            ?>
                        </div>
                        <button class="scroll-button scroll-button-right">
                            <div class="scroll-button-inner">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="9 18 15 12 9 6"></polyline>
                                </svg>
                            </div>
                        </button>
                    </div>
                </div>
                <?php
                endif;
                
                // Display other products in traditional grid layout if there are any
                if ($other_query1->have_posts() || $other_query2->have_posts()) :
                    // Reset the post data
                    rewind_posts();
                ?>
                <div class="other-products-section">
                    <h2 class="section-title">Other Products</h2>
                    <ul class="products">
                        <?php
                        // Display products with display_type != 'grip_carousel'
                        while ($other_query1->have_posts()) {
                            $other_query1->the_post();
                            // Use standard product template
                            wc_get_template_part('content', 'product-standard');
                        }
                        wp_reset_postdata();
                        
                        // Display products without the custom field
                        while ($other_query2->have_posts()) {
                            $other_query2->the_post();
                            // Use standard product template
                            wc_get_template_part('content', 'product-standard');
                        }
                        wp_reset_postdata();
                        ?>
                    </ul>
                </div>
                <?php
                endif;
                
                /**
                 * Hook: woocommerce_after_shop_loop.
                 *
                 * @hooked woocommerce_pagination - 10
                 */
                do_action('woocommerce_after_shop_loop');
                
                // Restore original query
                $wp_query = $original_query;
            } else {
                /**
                 * Hook: woocommerce_no_products_found.
                 *
                 * @hooked wc_no_products_found - 10
                 */
                do_action( 'woocommerce_no_products_found' );
            }

            /**
             * Hook: woocommerce_after_main_content.
             *
             * @hooked woocommerce_output_content_wrapper_end - 10 (outputs closing divs for the content)
             */
            do_action( 'woocommerce_after_main_content' );
            ?>
        </div>
    </main>
</div>

<?php get_footer(); ?>
