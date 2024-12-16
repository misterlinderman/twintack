<?php
get_header();

if (have_rows('header_configuration')) {
    get_template_part('template-parts/header/header', 'flexible');
}

// Get other homepage sections
get_template_part('template-parts/home/featured-categories');
get_template_part('template-parts/home/latest-products');
get_template_part('template-parts/home/technology-showcase');

get_footer(); 