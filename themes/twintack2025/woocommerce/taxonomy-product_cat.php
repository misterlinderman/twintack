<?php
/**
 * The Template for displaying products in a product category.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Check for ACF header first
if (have_rows('header_configuration', get_queried_object())) {
    get_template_part('template-parts/header/header', 'flexible');
} else {
    // Fallback to default product line header
    get_product_line_header();
}

// Rest of category content
get_template_part('template-parts/woocommerce/category', 'content');

get_footer();