<?php
class TwinTack_Product_Forms {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add form selection to product admin
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_form_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_form_field'));
        
        // Display form on product page
        add_action('woocommerce_before_add_to_cart_button', array($this, 'display_product_form'));
        
        // Save form data with order
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_form_data_to_cart'), 10, 3);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_form_data_to_order'), 10, 4);
    }
    
    public function add_form_field() {
        global $woocommerce, $post;
        
        echo '<div class="options_group">';
        
        woocommerce_wp_select(array(
            'id' => '_gravity_form_id',
            'label' => __('Custom Order Form', 'twintack2025'),
            'options' => $this->get_gravity_forms_list()
        ));
        
        echo '</div>';
    }
    
    private function get_gravity_forms_list() {
        $forms = GFAPI::get_forms();
        $options = array('' => __('Select a form', 'twintack2025'));
        
        foreach ($forms as $form) {
            $options[$form['id']] = $form['title'];
        }
        
        return $options;
    }
    
    public function save_form_field($post_id) {
        $form_id = isset($_POST['_gravity_form_id']) ? $_POST['_gravity_form_id'] : '';
        update_post_meta($post_id, '_gravity_form_id', sanitize_text_field($form_id));
    }
    
    public function display_product_form() {
        global $product;
        
        $form_id = get_post_meta($product->get_id(), '_gravity_form_id', true);
        
        if (!empty($form_id) && function_exists('gravity_form')) {
            echo '<div class="product-custom-form">';
            gravity_form($form_id, false, false, false, null, true);
            echo '</div>';
        }
    }
    
    public function add_form_data_to_cart($cart_item_data, $product_id, $variation_id) {
        if (isset($_POST['gform_submit'])) {
            $form_id = get_post_meta($product_id, '_gravity_form_id', true);
            $cart_item_data['gravity_form_data'] = array(
                'form_id' => $form_id,
                'entry' => $_POST
            );
        }
        return $cart_item_data;
    }
    
    public function save_form_data_to_order($item, $cart_item_key, $values, $order) {
        if (isset($values['gravity_form_data'])) {
            $item->add_meta_data(
                '_gravity_form_data',
                $values['gravity_form_data'],
                true
            );
        }
    }
}

// Initialize the class
add_action('init', array('TwinTack_Product_Forms', 'get_instance')); 