<?php
/**
 * The Template for displaying products in a product category.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Rest of category content
get_template_part('template-parts/woocommerce/category', 'content');

get_footer();