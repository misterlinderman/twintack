<?php
class TwinTack_Role_Pricing {
    private static $instance = null;

    private $roles = array(
        'brand_partner' => 'Brand Partner',
        'sales_rep' => 'Sales Representative',
        'event_customer' => 'Event Customer'
    );

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_roles'));
        add_action('woocommerce_product_options_pricing', array($this, 'add_price_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_price_fields'));
        add_filter('woocommerce_product_get_price', array($this, 'modify_price'), 10, 2);
    }

    public function register_roles() {
        foreach ($this->roles as $role_id => $role_name) {
            add_role($role_id, $role_name, array('read' => true));
        }
    }

    public function add_price_fields() {
        foreach ($this->roles as $role_id => $role_name) {
            woocommerce_wp_text_input(array(
                'id' => "_${role_id}_price",
                'label' => "$role_name Price",
                'data_type' => 'price',
                'desc_tip' => true,
                'description' => "Enter the price for $role_name accounts"
            ));
        }
    }

    public function save_price_fields($post_id) {
        foreach ($this->roles as $role_id => $role_name) {
            $price_field = "_${role_id}_price";
            if (isset($_POST[$price_field])) {
                update_post_meta($post_id, $price_field, wc_clean($_POST[$price_field]));
            }
        }
    }

    public function modify_price($price, $product) {
        if (!is_user_logged_in()) return $price;

        $user = wp_get_current_user();
        foreach ($this->roles as $role_id => $role_name) {
            if (in_array($role_id, $user->roles)) {
                $role_price = get_post_meta($product->get_id(), "_${role_id}_price", true);
                if ($role_price !== '' && $role_price > 0) {
                    return $role_price;
                }
            }
        }
        return $price;
    }
}

// Initialize the pricing system
add_action('init', array('TwinTack_Role_Pricing', 'get_instance'));
