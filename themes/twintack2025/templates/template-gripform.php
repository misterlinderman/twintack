<?php
/*
Template Name: Custom Grip Form for iFrame
*/

get_header();

?>

<style>
header#masthead {
    display: none;
}

.iframe-form-content {
    padding: 25px 0 0;
}

.container, .container-fluid, .container-lg, .container-md, .container-sm, .container-xl, .container-xxl {
    --bs-gutter-x: 0;
    --bs-gutter-y: 0;
    width: 100%;
    padding-right: calc(var(--bs-gutter-x)* .5);
    padding-left: calc(var(--bs-gutter-x)* .5);
    margin-right: auto;
    margin-left: auto;
}

.gform-theme--api, .gform-theme--foundation {
    --gf-form-gap-y: 20px;
    --gf-field-gap-y: 12px;
}

.gform-theme--framework .gfield--type-image_choice .gfield_checkbox, .gform-theme--framework .gfield--type-image_choice .gfield_radio {
    gap: 3px;
}

footer#colophon {
    display: none;
}

img {
    max-width: 100%;
    height: auto;
}

/* Ensure form elements stay within bounds */
.gform-theme--framework {
    overflow-x: hidden;
}

.gform-theme--api, .gform-theme--framework {
    --gf-field-img-choice-size-md: 49%;
}

.gform-theme--framework .gfield--type-image_choice .gfield-choice-image {
    inline-size: 170px;
    max-block-size: 170px;
    max-inline-size: 170px;
    rotate: -90deg;
}

.gform-theme--api, .gform-theme--framework {
    --gf-field-img-choice-size-md: 49%;
}

.gform-theme--framework .gfield--type-image_choice .gfield-image-choice-wrapper-outer {
    display: block;
    min-block-size: 100%;
    padding-top: 0 !important;
}

.gfield-choice-image-wrapper {
    width: 100%;
    height: 130px;
}

/* Improve form spacing on mobile */
@media (max-width: 768px) {
    .iframe-form-content {
        padding: 15px 0 0;
    }
    
    .gform-theme--api, .gform-theme--foundation {
        --gf-form-gap-y: 15px;
        --gf-field-gap-y: 10px;
    }
}

</style>

<div class="iframe-form-content">
    <?php while (have_posts()) : the_post(); ?>
        <div class="container">
            <?php the_content(); ?>
        </div>
        <?php get_template_part('template-parts/content', 'flexible'); ?>
    <?php endwhile; ?>
</div>

<?php get_footer(); ?> 