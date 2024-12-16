<?php
get_header();

// Check if ACF is active and we have header fields
if (function_exists('get_field') && have_rows('header_configuration')) {
    get_template_part('template-parts/header/header', 'flexible');
} else {
    // Fallback header if needed
    get_template_part('template-parts/header/header', 'default');
}

// Get other homepage sections
get_template_part('template-parts/home/featured-categories');
get_template_part('template-parts/home/latest-products');
get_template_part('template-parts/home/technology-showcase');

get_footer(); 