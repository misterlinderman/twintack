<?php
// Try these different hooks one at a time:

// Option 1: After title
add_action('woocommerce_single_product_summary', 'display_gravity_form', 15);

// Option 2: Before add to cart
add_action('woocommerce_before_add_to_cart_button', 'display_gravity_form', 10);

// Option 3: After summary
add_action('woocommerce_after_single_product_summary', 'display_gravity_form', 5);

// Option 4: Override the add to cart template completely
add_action('woocommerce_single_product_summary', 'custom_add_to_cart_template', 30);
function custom_add_to_cart_template() {
    global $product;
    
    $form_id = get_post_meta($product->get_id(), '_gravity_form_id', true);
    
    if (!empty($form_id) && function_exists('gravity_form')) {
        // Remove default add to cart
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
        
        echo '<div class="product-custom-form">';
        gravity_form($form_id, false, false, false, null, true);
        echo '</div>';
    }
}
