<?php
/**
 * TwinTack Grip Volume Pricing Handler
 * 
 * Handles dynamic volume pricing for custom grip products with
 * admin-configurable settings and wholesale integration.
 */
class TwinTack_Grip_Volume_Pricing {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Only initialize if volume pricing is enabled
        if (get_option('grip_volume_pricing_enabled', false)) {
            $this->init_hooks();
        }
    }
    
    private function init_hooks() {
        // Quantity restrictions
        add_filter('woocommerce_quantity_input_args', array($this, 'customize_quantity_input'), 10, 2);
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_quantity'), 10, 3);
        add_filter('woocommerce_update_cart_validation', array($this, 'validate_cart_quantity'), 10, 4);
        
        // Volume pricing
        add_action('woocommerce_before_calculate_totals', array($this, 'apply_volume_pricing'));
        add_filter('woocommerce_product_get_price', array($this, 'get_dynamic_price'), 10, 2);
        add_filter('woocommerce_product_get_sale_price', array($this, 'get_dynamic_price'), 10, 2);
        
        // Frontend display
        add_action('woocommerce_single_product_summary', array($this, 'display_volume_pricing_info'), 25);
        add_action('wp_footer', array($this, 'add_frontend_scripts'));
        
        // Cart display enhancements
        add_filter('woocommerce_cart_item_price', array($this, 'display_cart_item_price'), 10, 3);
    }
    
    /**
     * Get the configured volume pricing product ID
     */
    private function get_volume_product_id() {
        return get_option('grip_volume_pricing_product_id', '');
    }
    
    /**
     * Check if a product uses volume pricing
     */
    private function is_volume_product($product_id) {
        return ($product_id == $this->get_volume_product_id());
    }
    
    /**
     * Get pricing configuration for a product
     */
    private function get_pricing_config($product_id) {
        if (!$this->is_volume_product($product_id)) {
            return false;
        }
        
        // Check if product uses global settings or has custom settings
        $use_global = get_post_meta($product_id, '_grip_use_global_pricing', true);
        
        if ($use_global) {
            // Use global settings
            $config = get_option('grip_volume_pricing_settings', array());
        } else {
            // Use product-specific settings
            $config = array(
                'min_qty' => get_post_meta($product_id, '_grip_min_qty', true),
                'qty_step' => get_post_meta($product_id, '_grip_qty_step', true),
                'volume_break_qty' => get_post_meta($product_id, '_grip_volume_break_qty', true),
                'discount_type' => get_post_meta($product_id, '_grip_discount_type', true),
                'discount_amount' => get_post_meta($product_id, '_grip_discount_amount', true)
            );
            
            // Fill in defaults if empty
            $global_config = get_option('grip_volume_pricing_settings', array());
            foreach ($config as $key => $value) {
                if (empty($value)) {
                    $config[$key] = $global_config[$key] ?? null;
                }
            }
        }
        
        // Apply defaults
        $defaults = array(
            'min_qty' => 25,
            'qty_step' => 25,
            'volume_break_qty' => 75,
            'discount_type' => 'fixed',
            'discount_amount' => 2.00
        );
        
        return wp_parse_args($config, $defaults);
    }
    
    /**
     * Customize quantity input for volume products
     */
    public function customize_quantity_input($args, $product) {
        if (!$this->is_volume_product($product->get_id())) {
            return $args;
        }
        
        $config = $this->get_pricing_config($product->get_id());
        if (!$config) {
            return $args;
        }
        
        $args['min_value'] = intval($config['min_qty']);
        $args['max_value'] = 1000; // Reasonable maximum
        $args['step'] = intval($config['qty_step']);
        $args['input_value'] = intval($config['min_qty']);
        
        return $args;
    }
    
    /**
     * Validate quantity on add to cart
     */
    public function validate_quantity($passed, $product_id, $quantity) {
        if (!$this->is_volume_product($product_id)) {
            return $passed;
        }
        
        $config = $this->get_pricing_config($product_id);
        if (!$config) {
            return $passed;
        }
        
        $min_qty = intval($config['min_qty']);
        $qty_step = intval($config['qty_step']);
        
        if ($quantity < $min_qty) {
            wc_add_notice(
                sprintf(__('This product must be ordered in minimum quantities of %d.', 'twintack-grip-manager'), $min_qty),
                'error'
            );
            return false;
        }
        
        if ($quantity % $qty_step !== 0) {
            wc_add_notice(
                sprintf(__('This product must be ordered in multiples of %d.', 'twintack-grip-manager'), $qty_step),
                'error'
            );
            return false;
        }
        
        return $passed;
    }
    
    /**
     * Validate quantity changes in cart
     */
    public function validate_cart_quantity($passed, $cart_item_key, $values, $quantity) {
        if (!$this->is_volume_product($values['product_id'])) {
            return $passed;
        }
        
        return $this->validate_quantity($passed, $values['product_id'], $quantity);
    }
    
    /**
     * Apply volume pricing in cart
     */
    public function apply_volume_pricing($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (!$this->is_volume_product($cart_item['product_id'])) {
                continue;
            }
            
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];
            $new_price = $this->calculate_volume_price($product, $quantity);
            
            if ($new_price !== false) {
                $product->set_price($new_price);
            }
        }
    }
    
    /**
     * Get dynamic price for product page display
     */
    public function get_dynamic_price($price, $product) {
        if (!$this->is_volume_product($product->get_id())) {
            return $price;
        }
        
        // For product page, show base price
        // Volume discount will be shown separately
        return $price;
    }
    
    /**
     * Calculate volume price based on quantity
     */
    public function calculate_volume_price($product, $quantity) {
        $config = $this->get_pricing_config($product->get_id());
        if (!$config) {
            return false;
        }
        
        $base_price = floatval($product->get_regular_price());
        $volume_break_qty = intval($config['volume_break_qty']);
        
        // Check if quantity qualifies for volume pricing
        if ($quantity < $volume_break_qty) {
            return $base_price;
        }
        
        // Apply volume discount
        $discount_type = $config['discount_type'];
        $discount_amount = floatval($config['discount_amount']);
        
        if ($discount_type === 'fixed') {
            $volume_price = $base_price - $discount_amount;
        } else {
            $volume_price = $base_price * (1 - ($discount_amount / 100));
        }
        
        // Ensure price doesn't go negative
        return max($volume_price, 0);
    }
    
    /**
     * Display volume pricing information on product page
     */
    public function display_volume_pricing_info() {
        global $product;
        
        if (!$product || !$this->is_volume_product($product->get_id())) {
            return;
        }
        
        $config = $this->get_pricing_config($product->get_id());
        if (!$config) {
            return;
        }
        
        $base_price = $product->get_regular_price();
        $volume_price = $this->calculate_volume_price($product, intval($config['volume_break_qty']));
        
        ?>
        <div class="custom-grip-volume-pricing">
            <h4><?php _e('Volume Pricing', 'twintack-grip-manager'); ?></h4>
            <div class="pricing-tiers">
                <div class="pricing-tier">
                    <span class="tier-range">
                        <?php printf(__('%d-%d units:', 'twintack-grip-manager'), 
                            intval($config['min_qty']), 
                            intval($config['volume_break_qty']) - 1); ?>
                    </span>
                    <span class="tier-price"><?php echo wc_price($base_price); ?> <?php _e('each', 'twintack-grip-manager'); ?></span>
                </div>
                <div class="pricing-tier volume-tier">
                    <span class="tier-range">
                        <?php printf(__('%d+ units:', 'twintack-grip-manager'), intval($config['volume_break_qty'])); ?>
                    </span>
                    <span class="tier-price">
                        <?php echo wc_price($volume_price); ?> <?php _e('each', 'twintack-grip-manager'); ?>
                        <small class="savings">
                            (<?php printf(__('Save %s', 'twintack-grip-manager'), wc_price($base_price - $volume_price)); ?>)
                        </small>
                    </span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add frontend JavaScript for enhanced UX
     */
    public function add_frontend_scripts() {
        global $product;
        
        if (!$product || !$this->is_volume_product($product->get_id())) {
            return;
        }
        
        $config = $this->get_pricing_config($product->get_id());
        if (!$config) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var config = <?php echo json_encode($config); ?>;
            var basePrice = <?php echo $product->get_regular_price(); ?>;
            var $quantityInput = $('input[name="quantity"]');
            var $addToCartBtn = $('.single_add_to_cart_button');
            
            // Validate and update pricing display
            function validateAndUpdatePricing() {
                var quantity = parseInt($quantityInput.val()) || 0;
                var isValid = true;
                var message = '';
                
                // Remove existing messages
                $('.quantity-error, .pricing-preview').remove();
                
                // Validate quantity
                if (quantity < config.min_qty) {
                    isValid = false;
                    message = '<?php _e("Minimum quantity is", "twintack-grip-manager"); ?> ' + config.min_qty + ' <?php _e("units", "twintack-grip-manager"); ?>.';
                } else if (quantity % config.qty_step !== 0) {
                    isValid = false;
                    message = '<?php _e("Quantity must be in multiples of", "twintack-grip-manager"); ?> ' + config.qty_step + '.';
                }
                
                if (!isValid) {
                    $quantityInput.addClass('error');
                    $quantityInput.after('<div class="quantity-error" style="color: #e2401c; font-size: 12px; margin-top: 5px;">' + message + '</div>');
                    $addToCartBtn.prop('disabled', true);
                } else {
                    $quantityInput.removeClass('error');
                    $addToCartBtn.prop('disabled', false);
                    
                    // Show pricing preview
                    var pricePerUnit = basePrice;
                    var totalSavings = 0;
                    
                    if (quantity >= config.volume_break_qty) {
                        if (config.discount_type === 'fixed') {
                            pricePerUnit = basePrice - parseFloat(config.discount_amount);
                        } else {
                            pricePerUnit = basePrice * (1 - (parseFloat(config.discount_amount) / 100));
                        }
                        totalSavings = (basePrice - pricePerUnit) * quantity;
                    }
                    
                    var totalPrice = pricePerUnit * quantity;
                    
                    var previewHtml = '<div class="pricing-preview" style="background: #e7f3ff; padding: 10px; margin-top: 10px; border-radius: 4px; border: 1px solid #b3d9ff;">';
                    previewHtml += '<strong><?php _e("Price Preview:", "twintack-grip-manager"); ?></strong><br>';
                    previewHtml += quantity + ' <?php _e("units at", "twintack-grip-manager"); ?> ' + formatPrice(pricePerUnit) + ' <?php _e("each", "twintack-grip-manager"); ?> = ' + formatPrice(totalPrice);
                    
                    if (totalSavings > 0) {
                        previewHtml += '<br><span style="color: #28a745; font-weight: bold;">You save: ' + formatPrice(totalSavings) + '</span>';
                    }
                    
                    previewHtml += '</div>';
                    
                    $quantityInput.closest('.quantity').after(previewHtml);
                }
            }
            
            // Format price helper
            function formatPrice(price) {
                return '$' + parseFloat(price).toFixed(2);
            }
            
            // Bind events
            $quantityInput.on('change input keyup', validateAndUpdatePricing);
            
            // Initial validation
            validateAndUpdatePricing();
        });
        </script>
        
        <style>
        input.error {
            border-color: #e2401c !important;
            box-shadow: 0 0 5px rgba(226, 64, 28, 0.3) !important;
        }
        </style>
        <?php
    }
    
    /**
     * Display enhanced cart item price
     */
    public function display_cart_item_price($price, $cart_item, $cart_item_key) {
        if (!$this->is_volume_product($cart_item['product_id'])) {
            return $price;
        }
        
        $config = $this->get_pricing_config($cart_item['product_id']);
        if (!$config) {
            return $price;
        }
        
        $quantity = $cart_item['quantity'];
        $product = $cart_item['data'];
        $current_price = $product->get_price();
        $regular_price = $product->get_regular_price();
        
        // If volume discount applied, show both prices
        if ($quantity >= intval($config['volume_break_qty']) && $current_price < $regular_price) {
            return '<del>' . wc_price($regular_price) . '</del> <ins>' . wc_price($current_price) . '</ins>';
        }
        
        return $price;
    }
    
    /**
     * Get volume pricing summary for admin or API use
     */
    public function get_pricing_summary($product_id) {
        if (!$this->is_volume_product($product_id)) {
            return false;
        }
        
        $config = $this->get_pricing_config($product_id);
        if (!$config) {
            return false;
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        $base_price = $product->get_regular_price();
        $volume_price = $this->calculate_volume_price($product, intval($config['volume_break_qty']));
        
        return array(
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'min_qty' => intval($config['min_qty']),
            'qty_step' => intval($config['qty_step']),
            'volume_break_qty' => intval($config['volume_break_qty']),
            'base_price' => $base_price,
            'volume_price' => $volume_price,
            'savings_per_unit' => $base_price - $volume_price,
            'discount_type' => $config['discount_type'],
            'discount_amount' => $config['discount_amount']
        );
    }
} 