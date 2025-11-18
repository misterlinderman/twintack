<?php
/*
Template Name: Flexible
*/

get_header();

if (have_rows('header_configuration')) {
    get_template_part('template-parts/header/header', 'flexible');
}
?>

<div class="technology-content">
    <?php while (have_posts()) : the_post(); ?>
        <?php get_template_part('template-parts/content', 'flexible'); ?>
    <?php endwhile; ?>
</div>

<?php get_footer(); ?> 