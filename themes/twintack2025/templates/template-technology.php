<?php
/*
Template Name: Technology
*/

get_header();

if (have_rows('header_configuration')) {
    get_template_part('template-parts/header/header', 'flexible');
}
?>

<div class="technology-content">
    <div class="container">
        <?php while (have_posts()) : the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <div class="entry-content">
                    <?php the_content(); ?>
                </div>
            </article>
        <?php endwhile; ?>
    </div>
</div>

<?php get_footer(); ?> 