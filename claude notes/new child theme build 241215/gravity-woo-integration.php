<?php
// Add this to your child theme's functions.php or a separate file like inc/gravity-woo-integration.php

class Custom_Product_Form_Handler {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Add form ID field to product data
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_form_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_form_field'));

        // Display form on product page
        add_action('woocommerce_before_add_to_cart_button', array($this, 'display_product_form'));

        // Handle form submission
        add_action('gform_after_submission', array($this, 'handle_form_submission'), 10, 2);

        // Add form data to cart item
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_form_data_to_cart'), 10, 3);

        // Display form data in cart and checkout
        add_filter('woocommerce_get_item_data', array($this, 'display_form_data_in_cart'), 10, 2);

        // Save form data to order
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_form_data_to_order'), 10, 4);

        // Add form data to order emails
        add_action('woocommerce_order_item_meta_end', array($this, 'display_form_data_in_emails'), 10, 3);

        // Add form data to admin order page
        add_action('woocommerce_admin_order_item_values', array($this, 'display_form_data_in_admin'), 10, 3);
    }

    public function add_form_field() {
        global $woocommerce, $post;
        
        echo '<div class="options_group">';
        
        woocommerce_wp_select(array(
            'id' => '_gravity_form_id',
            'label' => 'Custom Order Form',
            'options' => $this->get_gravity_forms_list(),
            'desc_tip' => true,
            'description' => 'Select a form to attach to this product'
        ));

        echo '</div>';
    }

    public function save_form_field($post_id) {
        if (isset($_POST['_gravity_form_id'])) {
            update_post_meta($post_id, '_gravity_form_id', esc_attr($_POST['_gravity_form_id']));
        }
    }

    public function display_product_form() {
        global $product;
        
        $form_id = get_post_meta($product->get_id(), '_gravity_form_id', true);
        
        if ($form_id && class_exists('GFAPI')) {
            echo '<div class="custom-product-form">';
            gravity_form(
                $form_id, 
                false, // show title
                false, // show description
                false, // don't show inactive
                array('product_id' => $product->get_id()), // form args
                true, // ajax
                1, // tabindex
                false // echo
            );
            echo '</div>';
        }
    }

    public function handle_form_submission($entry, $form) {
        // Store the entry ID in a session for cart integration
        WC()->session->set('last_gravity_form_entry', array(
            'entry_id' => $entry['id'],
            'form_id' => $form['id']
        ));
    }

    public function add_form_data_to_cart($cart_item_data, $product_id, $variation_id) {
        $form_data = WC()->session->get('last_gravity_form_entry');
        
        if ($form_data) {
            $cart_item_data['gravity_form_data'] = $form_data;
            WC()->session->__unset('last_gravity_form_entry');
        }
        
        return $cart_item_data;
    }

    public function display_form_data_in_cart($item_data, $cart_item) {
        if (isset($cart_item['gravity_form_data'])) {
            $entry = GFAPI::get_entry($cart_item['gravity_form_data']['entry_id']);
            $form = GFAPI::get_form($cart_item['gravity_form_data']['form_id']);
            
            foreach ($form['fields'] as $field) {
                if (!empty($entry[$field->id])) {
                    $item_data[] = array(
                        'key' => $field->label,
                        'value' => $entry[$field->id]
                    );
                }
            }
        }
        return $item_data;
    }

    public function save_form_data_to_order($item, $cart_item_key, $values, $order) {
        if (isset($values['gravity_form_data'])) {
            $entry = GFAPI::get_entry($values['gravity_form_data']['entry_id']);
            $form = GFAPI::get_form($values['gravity_form_data']['form_id']);
            
            $item->add_meta_data('_gravity_form_entry_id', $entry['id']);
            $item->add_meta_data('_gravity_form_data', $this->format_form_data($entry, $form));
        }
    }

    private function format_form_data($entry, $form) {
        $formatted_data = array();
        foreach ($form['fields'] as $field) {
            if (!empty($entry[$field->id])) {
                $formatted_data[$field->label] = $entry[$field->id];
            }
        }
        return $formatted_data;
    }

    private function get_gravity_forms_list() {
        $forms = GFAPI::get_forms();
        $options = array('' => 'Select a form');
        foreach ($forms as $form) {
            $options[$form['id']] = $form['title'];
        }
        return $options;
    }
}

// Initialize the handler
add_action('init', array('Custom_Product_Form_Handler', 'get_instance'));

// Add custom styles
add_action('wp_enqueue_scripts', 'custom_product_form_styles');
function custom_product_form_styles() {
    wp_add_inline_style('flatsome-style', '
        .custom-product-form {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .custom-product-form .gform_wrapper {
            margin: 0;
        }
        
        .custom-product-form .gfield {
            margin-bottom: 15px;
        }
    ');
}
