<?php
// Add custom user roles
add_action('init', 'add_custom_user_roles');
function add_custom_user_roles() {
    add_role('brand_partner', 'Brand Partner', array('read' => true));
    add_role('sales_rep', 'Sales Representative', array('read' => true));
    add_role('event_customer', 'Event Customer', array('read' => true));
}

// Add custom price fields to products
add_action('woocommerce_product_options_pricing', 'add_custom_price_fields');
function add_custom_price_fields() {
    global $post;
    
    echo '<div class="options_group pricing show_if_simple show_if_variable">';
    
    // Brand Partner Price
    woocommerce_wp_text_input(array(
        'id' => '_brand_partner_price',
        'label' => 'Brand Partner Price',
        'data_type' => 'price',
        'desc_tip' => true,
        'description' => 'Enter the price for brand partners'
    ));
    
    // Sales Rep Price
    woocommerce_wp_text_input(array(
        'id' => '_sales_rep_price',
        'label' => 'Sales Rep Price',
        'data_type' => 'price',
        'desc_tip' => true,
        'description' => 'Enter the price for sales representatives'
    ));
    
    // Event Price
    woocommerce_wp_text_input(array(
        'id' => '_event_price',
        'label' => 'Event Price',
        'data_type' => 'price',
        'desc_tip' => true,
        'description' => 'Enter the price for event customers'
    ));
    
    echo '</div>';
}

// Add custom price fields to variations
add_action('woocommerce_variation_options_pricing', 'add_custom_variation_price_fields', 10, 3);
function add_custom_variation_price_fields($loop, $variation_data, $variation) {
    // Brand Partner Price
    woocommerce_wp_text_input(array(
        'id' => '_brand_partner_price_' . $variation->ID,
        'label' => 'Brand Partner Price',
        'data_type' => 'price',
        'wrapper_class' => 'form-row form-row-first',
        'value' => get_post_meta($variation->ID, '_brand_partner_price', true)
    ));
    
    // Sales Rep Price
    woocommerce_wp_text_input(array(
        'id' => '_sales_rep_price_' . $variation->ID,
        'label' => 'Sales Rep Price',
        'data_type' => 'price',
        'wrapper_class' => 'form-row form-row-last',
        'value' => get_post_meta($variation->ID, '_sales_rep_price', true)
    ));
    
    // Event Price
    woocommerce_wp_text_input(array(
        'id' => '_event_price_' . $variation->ID,
        'label' => 'Event Price',
        'data_type' => 'price',
        'wrapper_class' => 'form-row form-row-first',
        'value' => get_post_meta($variation->ID, '_event_price', true)
    ));
}

// Save custom prices for simple products
add_action('woocommerce_process_product_meta', 'save_custom_price_fields');
function save_custom_price_fields($post_id) {
    $custom_prices = array(
        '_brand_partner_price',
        '_sales_rep_price',
        '_event_price'
    );
    
    foreach ($custom_prices as $price_field) {
        $price = isset($_POST[$price_field]) ? wc_clean($_POST[$price_field]) : '';
        update_post_meta($post_id, $price_field, $price);
    }
}

// Save custom prices for variations
add_action('woocommerce_save_product_variation', 'save_custom_variation_price_fields', 10, 2);
function save_custom_variation_price_fields($variation_id, $i) {
    $custom_prices = array(
        '_brand_partner_price',
        '_sales_rep_price',
        '_event_price'
    );
    
    foreach ($custom_prices as $price_field) {
        $price = isset($_POST[$price_field . '_' . $variation_id]) ? 
            wc_clean($_POST[$price_field . '_' . $variation_id]) : '';
        update_post_meta($variation_id, $price_field, $price);
    }
}

// Modify displayed price based on user role
add_filter('woocommerce_product_get_price', 'custom_price_by_role', 10, 2);
add_filter('woocommerce_product_variation_get_price', 'custom_price_by_role', 10, 2);
function custom_price_by_role($price, $product) {
    if (!is_user_logged_in()) {
        return $price;
    }
    
    $user = wp_get_current_user();
    $role_price_map = array(
        'brand_partner' => '_brand_partner_price',
        'sales_rep' => '_sales_rep_price',
        'event_customer' => '_event_price'
    );
    
    foreach ($role_price_map as $role => $meta_key) {
        if (in_array($role, $user->roles)) {
            $role_price = get_post_meta($product->get_id(), $meta_key, true);
            if ($role_price !== '' && $role_price > 0) {
                return $role_price;
            }
        }
    }
    
    return $price;
}

// Add role price display in admin order items
add_action('woocommerce_admin_order_item_values', 'show_role_price_in_admin_order', 10, 3);
function show_role_price_in_admin_order($product, $item, $item_id) {
    if ($item->get_meta('_role_price_type')) {
        echo '<div class="role-price-note" style="color: #777;">';
        echo '(' . esc_html($item->get_meta('_role_price_type')) . ' pricing applied)';
        echo '</div>';
    }
}

// Store the price type with order items
add_action('woocommerce_checkout_create_order_line_item', 'store_role_price_type', 10, 4);
function store_role_price_type($item, $cart_item_key, $values, $order) {
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        $role_labels = array(
            'brand_partner' => 'Brand Partner',
            'sales_rep' => 'Sales Representative',
            'event_customer' => 'Event Customer'
        );
        
        foreach ($role_labels as $role => $label) {
            if (in_array($role, $user->roles)) {
                $item->add_meta_data('_role_price_type', $label, true);
                break;
            }
        }
    }
}

// Optional: Add role price display in cart tooltips
add_filter('woocommerce_cart_item_price', 'add_role_price_tooltip', 10, 3);
function add_role_price_tooltip($price_html, $cart_item, $cart_item_key) {
    if (!is_user_logged_in()) {
        return $price_html;
    }
    
    $user = wp_get_current_user();
    $role_labels = array(
        'brand_partner' => 'Brand Partner',
        'sales_rep' => 'Sales Representative',
        'event_customer' => 'Event Customer'
    );
    
    foreach ($role_labels as $role => $label) {
        if (in_array($role, $user->roles)) {
            return '<span class="role-price" title="' . $label . ' Price">' . $price_html . '</span>';
        }
    }
    
    return $price_html;
}
