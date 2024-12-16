<?php
// Add this to your child theme's functions.php

// 1. Add Form Selection to Product
add_action('woocommerce_product_options_general_product_data', 'add_gravity_form_field');
function add_gravity_form_field() {
    global $woocommerce, $post;
    
    echo '<div class="options_group show_if_simple show_if_variable">';
    
    woocommerce_wp_select(array(
        'id' => '_gravity_form_id',
        'label' => 'Custom Order Form',
        'description' => 'Select a Gravity Form to display with this product',
        'desc_tip' => true,
        'options' => get_gravity_forms_options()
    ));
    
    echo '</div>';
}

// 2. Get Available Forms
function get_gravity_forms_options() {
    if (!class_exists('GFAPI')) {
        return array('' => 'Gravity Forms not installed');
    }
    
    $forms = GFAPI::get_forms();
    $options = array('' => 'Select a form');
    
    foreach ($forms as $form) {
        $options[$form['id']] = $form['title'];
    }
    
    return $options;
}

// 3. Save Form Selection
add_action('woocommerce_process_product_meta', 'save_gravity_form_field');
function save_gravity_form_field($post_id) {
    $gravity_form_id = isset($_POST['_gravity_form_id']) ? $_POST['_gravity_form_id'] : '';
    update_post_meta($post_id, '_gravity_form_id', sanitize_text_field($gravity_form_id));
}

// 4. Display Form on Product Page
add_action('woocommerce_before_add_to_cart_form', 'display_gravity_form');
function display_gravity_form() {
    global $product;
    
    // Debug output
    error_log('Attempting to display form for product: ' . $product->get_id());
    
    $form_id = get_post_meta($product->get_id(), '_gravity_form_id', true);
    
    // Debug output
    error_log('Form ID found: ' . $form_id);
    
    if (!empty($form_id) && function_exists('gravity_form')) {
        echo '<div class="product-custom-form">';
        gravity_form(
            $form_id,
            false, // show title
            false, // show description
            false, // don't show inactive
            null,  // field values
            true,  // ajax
            0,     // tabindex
            true   // echo
        );
        echo '</div>';
    }
}

// 5. Add styling
add_action('wp_head', 'add_gravity_form_styles');
function add_gravity_form_styles() {
    ?>
    <style>
        .product-custom-form {
            margin: 20px 0;
            padding: 20px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .product-custom-form .gform_wrapper {
            margin: 0;
        }
        
        /* Position the form before add to cart */
        .woocommerce div.product form.cart {
            margin-top: 20px;
        }
    </style>
    <?php
}

// 6. Store form submission data with order
add_filter('woocommerce_add_cart_item_data', 'add_gravity_form_data_to_cart', 10, 3);
function add_gravity_form_data_to_cart($cart_item_data, $product_id, $variation_id) {
    $form_id = get_post_meta($product_id, '_gravity_form_id', true);
    
    if (!empty($form_id) && isset($_POST['gform_submit'])) {
        $cart_item_data['gravity_form_data'] = array(
            'form_id' => $form_id,
            'fields' => $_POST
        );
    }
    
    return $cart_item_data;
}
