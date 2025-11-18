<?php
/*
Template Name: Story
*/

get_header();

if (have_rows('header_configuration')) {
    get_template_part('template-parts/header/header', 'flexible');
}
?>

<div class="story-content">
    <?php while (have_posts()) : the_post(); ?>
        <div class="container">
            <?php the_content(); ?>
        </div>
        <?php get_template_part('template-parts/content', 'flexible'); ?>
    <?php endwhile; ?>
</div>

<?php get_footer(); ?> 