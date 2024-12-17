<?php
/**
 * Template for Fishing category
 */

get_header();

// Get header configuration and render
get_template_part('template-parts/header/header', 'base');
?>

<main id="primary" class="site-main">
    <div class="container">
        <?php if (have_posts()) : ?>
            <header class="page-header">
                <?php
                the_archive_title('<h1 class="page-title">', '</h1>');
                the_archive_description('<div class="archive-description">', '</div>');
                ?>
            </header>

            <div class="products-grid">
                <?php
                while (have_posts()) :
                    the_post();
                    wc_get_template_part('content', 'product');
                endwhile;
                ?>
            </div>

            <?php
            the_posts_navigation();
        else :
            get_template_part('template-parts/content', 'none');
        endif;
        ?>
    </div>
</main>

<?php get_footer(); ?> 