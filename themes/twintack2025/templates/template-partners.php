<?php
/*
Template Name: Partners
*/

get_header();

if (have_rows('header_configuration')) {
    get_template_part('template-parts/header/header', 'flexible');
}
?>

<div class="partners-content">
    <div class="container">
        <?php while (have_posts()) : the_post(); ?>
            <div class="partners-grid">
                <?php 
                if (have_rows('partners')) :
                    while (have_rows('partners')) : the_row();
                        get_template_part('template-parts/content', 'partner');
                    endwhile;
                endif;
                ?>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<?php get_footer(); ?> 