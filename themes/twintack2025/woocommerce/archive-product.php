<?php get_header(); ?>

<div class="page-wrapper">
    <main class="main">
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

        <div class="container">
            <?php
            if ( woocommerce_product_loop() ) {
                do_action( 'woocommerce_before_shop_loop' );
                ?>
                <div class="products-grid">
                    <?php
                    while ( have_posts() ) {
                        the_post();
                        wc_get_template_part( 'content', 'product' );
                    }
                    ?>
                </div>
                <?php
                do_action( 'woocommerce_after_shop_loop' );
            } else {
                do_action( 'woocommerce_no_products_found' );
            }
            ?>
        </div>
    </main>
</div>

<?php get_footer(); ?>
