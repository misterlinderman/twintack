<?php
// Custom shortcodes
function custom_category_products($atts) {
    $atts = shortcode_atts(array(
        'category' => '',
        'limit' => '12',
        'columns' => '4'
    ), $atts);
    
    return do_shortcode('[products category="' . $atts['category'] . '" limit="' . $atts['limit'] . '" columns="' . $atts['columns'] . '"]');
}
add_shortcode('custom_category_products', 'custom_category_products');