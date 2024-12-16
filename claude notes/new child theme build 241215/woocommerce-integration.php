<?php
// flatsome-child/inc/woocommerce.php

// Modify product page layout
function custom_product_layout() {
    // Remove default Flatsome product hooks
    remove_action('woocommerce_before_single_product', 'flatsome_before_product_page', 1);
    
    // Add custom layout
    add_action('woocommerce_before_single_product', 'custom_before_product_page', 1);
}
add_action('init', 'custom_product_layout');

function custom_before_product_page() {
    // Your custom product page layout
    echo '<div class="custom-product-container">';
    // Use Flatsome's built-in functions
    echo do_shortcode('[ux_banner height="300px" bg_overlay="rgba(0,0,0,.3)"]');
}

// Modify category layout
function custom_category_layout() {
    // Use Flatsome's product grid options
    add_filter('flatsome_product_box_tools', 'custom_product_box_tools');
}
add_action('init', 'custom_category_layout');

function custom_product_box_tools($tools) {
    // Modify Flatsome's product box
    $tools['custom_button'] = array(
        'title' => 'Custom Button',
        'callback' => 'custom_button_html'
    );
    return $tools;
}
