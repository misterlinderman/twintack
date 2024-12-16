<?php
class TwinTack_Custom_Products {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_form_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_form_field'));
        add_action('woocommerce_before_add_to_cart_button', array($this, 'display_custom_form'));
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_form_data_to_cart'), 10, 3);
        add_filter('woocommerce_get_item_data', array($this, 'display_form_data_in_cart'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_form_data_to_order'), 10, 4);
        add_action('woocommerce_after_order_details', array($this, 'display_form_data_in_order'));
    }

    public function add_form_field() {
        global $woocommerce, $post;
        
        echo '<div class="options_group">';
        woocommerce_wp_select(array(
            'id' => '_custom_form_id',
            'label' => 'Custom Order Form',
            'options' => $this->get_gravity_forms_list(),
            'desc_tip' => true,
            'description' => 'Select a form for custom product options'
        ));
        echo '</div>';
    }

    public function save_form_field($post_id) {
        if (isset($_POST['_custom_form_id'])) {
            update_post_meta($post_id, '_custom_form_id', esc_attr($_POST['_custom_form_id']));
        }
    }

    public function display_custom_form() {
        global $product;
        
        $form_id = get_post_meta($product->get_id(), '_custom_form_id', true);
        
        if ($form_id && function_exists('gravity_form')) {
            echo '<div class="product-custom-form">';
            gravity_form(
                $form_id,
                false, // show title
                false, // show description
                false, // don't show inactive
                array('product_id' => $product->get_id()),
                true, // ajax
                1, // tabindex
                true // echo
            );
            echo '</div>';
        }
    }

    public function add_form_data_to_cart($cart_item_data, $product_id, $variation_id) {
        if (isset($_POST['gform_submit'])) {
            $form_id = get_post_meta($product_id, '_custom_form_id', true);
            $entry = GFAPI::add_entry(array(
                'form_id' => $form_id,
                'status' => 'active',
                'field_values' => $_POST
            ));
            
            if (!is_wp_error($entry)) {
                $cart_item_data['custom_form_entry'] = $entry;
            }
        }
        return $cart_item_data;
    }

    public function display_form_data_in_cart($item_data, $cart_item) {
        if (isset($cart_item['custom_form_entry'])) {
            $entry = GFAPI::get_entry($cart_item['custom_form_entry']);
            $form = GFAPI::get_form($entry['form_id']);
            
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
        if (isset($values['custom_form_entry'])) {
            $entry = GFAPI::get_entry($values['custom_form_entry']);
            $form = GFAPI::get_form($entry['form_id']);
            
            foreach ($form['fields'] as $field) {
                if (!empty($entry[$field->id])) {
                    $item->add_meta_data(
                        $field->label,
                        $entry[$field->id],
                        true
                    );
                }
            }
        }
    }

    private function get_gravity_forms_list() {
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
}

// Initialize the custom products handler
add_action('init', array('TwinTack_Custom_Products', 'get_instance'));
