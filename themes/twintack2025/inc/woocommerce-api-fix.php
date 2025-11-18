<?php
/**
 * TwinTack WooCommerce API Fix
 *
 * This file provides compatibility fixes for the WooCommerce Store API
 * to ensure smooth checkout experience across WooCommerce versions.
 *
 * @package twintack2025
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class TwinTack_WooCommerce_API_Fix
 */
class TwinTack_WooCommerce_API_Fix {
    /**
     * Class instance
     *
     * @var TwinTack_WooCommerce_API_Fix|null
     */
    private static $instance = null;

    /**
     * Get the singleton instance
     *
     * @return TwinTack_WooCommerce_API_Fix
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Only apply fixes if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Check if we should disable all hooks due to errors
        if (get_option('twintack_disable_api_hooks', false)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WooCommerce API fixes are disabled due to previous errors');
            }
            return;
        }

        // Add a custom REST API handler to catch server errors
        add_filter('rest_request_before_callbacks', array($this, 'catch_api_errors'), 999, 3);
        
        // Add compatibility for various WooCommerce APIs
        add_filter('woocommerce_store_api_checkout_update_order_from_request', array($this, 'filter_checkout_order_data'), 10, 2);
        
        // Log WooCommerce API errors for debugging
        add_action('woocommerce_rest_checkout_process_payment_with_context', array($this, 'log_payment_context'), 5, 2);
        
        // Fix checkout blocks parameters and scripts
        add_action('wp_enqueue_scripts', array($this, 'localize_checkout_parameters'), 999);
        
        // Dequeue problematic scripts at checkout
        add_action('wp_enqueue_scripts', array($this, 'dequeue_conflicting_scripts'), 999);
        
        // Disable our REST handlers if we get errors
        add_action('rest_api_init', array($this, 'setup_api_fallbacks'), 999);
    }

    /**
     * Setup API fallbacks to ensure smoother checkout
     */
    public function setup_api_fallbacks() {
        // Register our routes with higher priority to override default handlers
        register_rest_route('wc/store/v1', '/checkout', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_checkout_request'),
            'permission_callback' => '__return_true',
        ), true);
    }
    
    /**
     * Handle checkout request with error catching
     * 
     * @param WP_REST_Request $request The request.
     * @return WP_REST_Response|WP_Error The response.
     */
    public function handle_checkout_request($request) {
        // Try to use the original handler with our error catching
        try {
            // Get the controller
            $controllers = rest_get_server()->get_routes();
            
            if (isset($controllers['wc/store/v1/checkout'])) {
                foreach ($controllers['wc/store/v1/checkout'] as $route) {
                    if ($route['methods']['POST'] === 1) {
                        $controller = $route['callback'][0];
                        if (is_object($controller) && method_exists($controller, 'get_response')) {
                            return $controller->get_response($request);
                        }
                    }
                }
            }
            
            // Fall back to default handler
            return rest_ensure_response(array(
                'success' => false,
                'error' => array(
                    'code' => 'checkout_error',
                    'message' => 'Unable to process checkout. Please try again or use classic checkout.',
                ),
            ));
            
        } catch (Exception $e) {
            // Log the error
            if (function_exists('twintack_error_log')) {
                twintack_error_log('Error processing checkout: ' . $e->getMessage(), 'api_error', array(
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ));
            }
            
            // Return a friendly error to the user
            return new WP_Error(
                'checkout_error',
                'There was an error processing your checkout. Please try again or contact support.',
                array('status' => 400)
            );
        }
    }
    
    /**
     * Catch API errors and temporarily disable features if needed
     * 
     * @param WP_REST_Response $response The response object.
     * @param array $handler The endpoint handler.
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response The response object.
     */
    public function catch_api_errors($response, $handler, $request) {
        // Only handle 500 errors for checkout
        if (strpos($request->get_route(), '/wc/store/v1/checkout') !== false) {
            // Add error handling
            try {
                return $response;
            } catch (Exception $e) {
                // Log the error
                if (function_exists('twintack_error_log')) {
                    twintack_error_log('API Error: ' . $e->getMessage(), 'api_error', array(
                        'route' => $request->get_route(),
                        'params' => $request->get_params(),
                    ));
                }
                
                // Disable our hooks temporarily to prevent further errors
                update_option('twintack_disable_api_hooks', true);
                
                // Return a friendly error
                return new WP_Error(
                    'checkout_error',
                    'There was an error processing your request. Please try again or contact support.',
                    array('status' => 400)
                );
            }
        }
        
        return $response;
    }

    /**
     * Log payment context for debugging
     *
     * @param \Automattic\WooCommerce\StoreApi\Payments\PaymentContext $payment_context Payment context.
     * @param \Automattic\WooCommerce\StoreApi\Payments\PaymentResult  $payment_result Payment result.
     */
    public function log_payment_context($payment_context, $payment_result) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        // Avoid accessing properties as array
        $context_data = array(
            'order_id' => $payment_context->order_id,
            'payment_method' => $payment_context->payment_method,
            'payment_data' => is_array($payment_context->payment_data) ? 
                $payment_context->payment_data : 
                'Object: ' . get_class($payment_context->payment_data),
        );
        
        error_log('Payment Context: ' . print_r($context_data, true));
    }

    /**
     * Filter checkout order data
     *
     * @param array|object   $order_data Order data.
     * @param \WP_REST_Request $request Request.
     * @return array|object
     */
    public function filter_checkout_order_data($order_data, $request) {
        // Check if order_data is an object (WooCommerce order)
        if (is_object($order_data)) {
            // Don't modify WooCommerce Order objects, just return as is
            return $order_data;
        }
        
        // Only continue if we have array data
        if (!is_array($order_data)) {
            return $order_data;
        }
        
        // Ensure consistent formatting across different WooCommerce versions
        
        // Make sure billing data is consistent
        if (isset($order_data['billing'])) {
            // Convert empty strings to null for better database storage
            foreach ($order_data['billing'] as $key => $value) {
                if ($value === '') {
                    $order_data['billing'][$key] = null;
                }
            }
        }
        
        // Make sure shipping data is consistent
        if (isset($order_data['shipping'])) {
            // Convert empty strings to null for better database storage
            foreach ($order_data['shipping'] as $key => $value) {
                if ($value === '') {
                    $order_data['shipping'][$key] = null;
                }
            }
        }
        
        return $order_data;
    }

    /**
     * Localize checkout parameters for blocks and classic checkout
     */
    public function localize_checkout_parameters() {
        if (!function_exists('is_checkout') || !is_checkout()) {
            return;
        }
        
        // Only proceed if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Check if checkout params are missing or incomplete
        if (!wp_script_is('wc-checkout') || !wp_script_is('jquery')) {
            return;
        }
        
        $checkout_params = array();
        
        // Define required checkout parameters
        if (!isset($GLOBALS['wc_checkout_params']) || empty($GLOBALS['wc_checkout_params'])) {
            $woocommerce = WC();
            $checkout_params = array(
                'ajax_url'                  => admin_url('admin-ajax.php'),
                'wc_ajax_url'               => WC_AJAX::get_endpoint('%%endpoint%%'),
                'update_order_review_nonce' => wp_create_nonce('update-order-review'),
                'apply_coupon_nonce'        => wp_create_nonce('apply-coupon'),
                'remove_coupon_nonce'       => wp_create_nonce('remove-coupon'),
                'option_guest_checkout'     => get_option('woocommerce_enable_guest_checkout') === 'yes',
                'checkout_url'              => add_query_arg(array('key' => '{quote_id}'), wc_get_checkout_url()),
                'is_checkout'               => is_checkout() && empty($woocommerce->cart->get_cart()) ? 0 : 1,
                'debug_mode'                => defined('WP_DEBUG') && WP_DEBUG,
                'i18n_checkout_error'       => esc_attr__('Error processing checkout. Please try again.', 'woocommerce'),
            );
            
            // Add these parameters globally
            $GLOBALS['wc_checkout_params'] = $checkout_params;
        } else {
            $checkout_params = $GLOBALS['wc_checkout_params'];
        }
        
        // Re-localize the checkout script with our parameters
        if (wp_script_is('wc-checkout')) {
            wp_localize_script('wc-checkout', 'wc_checkout_params', $checkout_params);
        }
        
        // Log for debugging
        if (function_exists('twintack_error_log')) {
            twintack_error_log('Localized checkout parameters', 'checkout_fix', array(
                'params' => array_keys($checkout_params)
            ));
        }
    }

    /**
     * Dequeue conflicting scripts at checkout
     */
    public function dequeue_conflicting_scripts() {
        // Only run on checkout page
        if (!function_exists('is_checkout') || !is_checkout()) {
            return;
        }
        
        // Known problematic scripts that may conflict with checkout
        $problematic_scripts = apply_filters('twintack_problematic_checkout_scripts', array(
            // Bootstrap scripts that may conflict with WooCommerce
            'bootstrap-js',
            'bootstrap',
            
            // Any custom theme scripts that override standard functionality
            'custom-checkout',
            
            // Add other problematic scripts as you identify them
            // 'script-handle-name',
        ));
        
        // Detect if we're using blocks or classic checkout
        $using_blocks = false;
        
        // Check for blocks scripts being enqueued
        if (wp_script_is('wc-blocks-checkout') || wp_script_is('wc-checkout-block-frontend')) {
            $using_blocks = true;
        }
        
        // Get all enqueued scripts
        global $wp_scripts;
        
        // List of scripts that were dequeued
        $dequeued_scripts = array();
        
        foreach ($problematic_scripts as $script_handle) {
            if (wp_script_is($script_handle, 'enqueued')) {
                wp_dequeue_script($script_handle);
                $dequeued_scripts[] = $script_handle;
            }
        }
        
        // Log the dequeued scripts
        if (!empty($dequeued_scripts) && function_exists('twintack_error_log')) {
            twintack_error_log('Dequeued conflicting scripts at checkout', 'script_fix', array(
                'dequeued' => $dequeued_scripts,
                'using_blocks_checkout' => $using_blocks
            ));
        }
        
        // If we're using blocks checkout, ensure blocks dependencies are loaded
        if ($using_blocks) {
            // Add any special handling for blocks checkout
            // This might include ensuring certain scripts are loaded or properly localized
        }
    }
}

// Initialize the API fix
add_action('init', array('TwinTack_WooCommerce_API_Fix', 'get_instance'), 5); 