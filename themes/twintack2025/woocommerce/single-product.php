<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Check for ACF header first
if (have_rows('header_configuration')) {
    get_template_part('template-parts/header/header', 'flexible');
} else {
    // Fallback to default product header
    get_template_part('template-parts/header/header', 'product');
}

while (have_posts()) :
    the_post();
    wc_get_template_part('content', 'single-product');
endwhile;

get_footer(); 