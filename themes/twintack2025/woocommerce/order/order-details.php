<?php get_header(); ?>

<div class="page-wrapper">
    <main class="main">
        <div class="container">
            <?php
                while ( have_posts() ) :
                    the_post();
                    do_action( 'woocommerce_before_main_content' );
                    the_content();
                    do_action( 'woocommerce_after_main_content' );
                endwhile;
            ?>
        </div>
    </main>
</div>

<?php get_footer(); ?>
