<?php
// Reference implementation from:
// claude notes/new theme custom 241215/role-pricing-system.php
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

    // Implementation continues as per role-pricing-system.php
}

// Initialize the pricing system
add_action('init', array('TwinTack_Role_Pricing', 'get_instance')); 