<?php
// Add this to your functions.php temporarily

add_action('init', 'debug_gravity_form_setup');
function debug_gravity_form_setup() {
    error_log('Gravity Forms Debug: Init called');
    
    if (class_exists('GFAPI')) {
        error_log('Gravity Forms is active');
        $forms = GFAPI::get_forms();
        error_log('Available forms: ' . print_r($forms, true));
    } else {
        error_log('Gravity Forms is NOT active');
    }
}

add_action('woocommerce_before_single_product', 'debug_product_form');
function debug_product_form() {
    global $product;
    
    if ($product) {
        $form_id = get_post_meta($product->get_id(), '_gravity_form_id', true);
        error_log('Product ID: ' . $product->get_id());
        error_log('Form ID found: ' . $form_id);
    } else {
        error_log('No product object found');
    }
}
