<?php
class TwinTack_Grip_Account {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'register_endpoints'));
        add_filter('woocommerce_account_menu_items', array($this, 'add_grip_designs_endpoint'));
        add_action('woocommerce_account_grip-designs_endpoint', array($this, 'grip_designs_content'));
    }
    
    public function register_endpoints() {
        add_rewrite_endpoint('grip-designs', EP_ROOT | EP_PAGES);
    }
    
    public function add_grip_designs_endpoint($items) {
        $items['grip-designs'] = 'My Grip Designs';
        return $items;
    }
    
    public function grip_designs_content() {
        $customer_email = wp_get_current_user()->user_email;
        
        $args = array(
            'post_type' => 'grip_design',
            'meta_query' => array(
                array(
                    'key' => '_grip_customer_email',
                    'value' => $customer_email
                )
            )
        );
        
        $designs = new WP_Query($args);
        
        if ($designs->have_posts()) {
            while ($designs->have_posts()) {
                $designs->the_post();
                $status = get_post_status();
                ?>
                <div class="grip-design-item">
                    <h3><?php the_title(); ?></h3>
                    <p>Status: <?php echo get_post_status_object($status)->label; ?></p>
                    <p>Design Type: <?php echo get_post_meta(get_the_ID(), '_grip_design_type', true); ?></p>
                    <p>Quantity: <?php echo get_post_meta(get_the_ID(), '_grip_quantity', true); ?></p>
                </div>
                <?php
            }
        } else {
            echo '<p>No grip designs found.</p>';
        }
        wp_reset_postdata();
    }
}