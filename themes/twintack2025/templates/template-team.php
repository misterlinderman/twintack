<?php
/*
Template Name: Our Team
*/

get_header();

if (have_rows('header_configuration')) {
    get_template_part('template-parts/header/header', 'flexible');
}
?>

<div class="team-content">
    <div class="container">
        <?php while (have_posts()) : the_post(); ?>
            <?php the_content(); ?>
            <div class="team-grid">
                <?php 
                if (have_rows('team_members')) :
                    while (have_rows('team_members')) : the_row();
                        get_template_part('template-parts/content', 'team-member');
                    endwhile;
                endif;
                ?>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<?php get_footer(); ?> 