<?php get_header(); ?>

<div class="page-wrapper">
    <main class="main">
        <div class="container">
            <?php
                do_action( 'woocommerce_account_navigation' );
                do_action( 'woocommerce_account_content' );
            ?>
        </div>
    </main>
</div>

<?php get_footer(); ?>
