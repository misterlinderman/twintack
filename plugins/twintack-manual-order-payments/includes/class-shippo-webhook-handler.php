<?php
/**
 * TwinTack Shippo Webhook Handler
 * 
 * Handles incoming webhooks from Shippo to update order statuses
 * 
 * @package TwinTack_Manual_Order_Payments
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TwinTack_Shippo_Webhook_Handler {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Register webhook endpoint
        add_action('rest_api_init', array($this, 'register_webhook_endpoints'));
        
        // Add webhook endpoint to query vars
        add_action('init', array($this, 'add_webhook_endpoint'));
        add_action('parse_request', array($this, 'handle_webhook_request'));
    }
    
    /**
     * Register REST API webhook endpoints
     */
    public function register_webhook_endpoints() {
        register_rest_route('twintack/v1', '/shippo-webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_shippo_webhook'),
            'permission_callback' => array($this, 'verify_webhook_signature'),
        ));
    }
    
    /**
     * Add webhook endpoint for non-REST requests
     */
    public function add_webhook_endpoint() {
        add_rewrite_rule('^twintack-shippo-webhook/?$', 'index.php?twintack_shippo_webhook=1', 'top');
        flush_rewrite_rules();
    }
    
    /**
     * Handle webhook request via query vars
     */
    public function handle_webhook_request($wp) {
        if (isset($wp->query_vars['twintack_shippo_webhook'])) {
            $this->handle_shippo_webhook_legacy();
            exit;
        }
    }
    
    /**
     * Verify webhook signature from Shippo
     */
    public function verify_webhook_signature($request) {
        // For now, allow all requests - in production you should verify Shippo's signature
        // Shippo webhook verification would go here
        return true;
    }
    
    /**
     * Handle Shippo webhook (REST API version)
     */
    public function handle_shippo_webhook($request) {
        $body = $request->get_body();
        $data = json_decode($body, true);
        
        if (function_exists('twintack_manual_payments_log')) {
            twintack_manual_payments_log("Shippo Webhook received: " . $body);
        }
        
        return $this->process_webhook_data($data);
    }
    
    /**
     * Handle Shippo webhook (legacy query var version)
     */
    public function handle_shippo_webhook_legacy() {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);
        
        if (function_exists('twintack_manual_payments_log')) {
            twintack_manual_payments_log("Shippo Webhook (legacy) received: " . $body);
        }
        
        $result = $this->process_webhook_data($data);
        
        if ($result instanceof WP_REST_Response) {
            wp_send_json($result->get_data(), $result->get_status());
        } else {
            wp_send_json_success($result);
        }
    }
    
    /**
     * Process webhook data from Shippo
     */
    private function process_webhook_data($data) {
        if (!$data || !isset($data['event'])) {
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("Shippo Webhook: Invalid data received", 'error');
            }
            return new WP_REST_Response(array('error' => 'Invalid webhook data'), 400);
        }
        
        $event_type = $data['event'];
        
        if (function_exists('twintack_manual_payments_log')) {
            twintack_manual_payments_log("Shippo Webhook: Processing event type '{$event_type}'");
        }
        
        switch ($event_type) {
            case 'order_updated':
                return $this->handle_order_updated($data);
                
            case 'shipment_updated':
                return $this->handle_shipment_updated($data);
                
            case 'track_updated':
                return $this->handle_tracking_updated($data);
                
            default:
                if (function_exists('twintack_manual_payments_log')) {
                    twintack_manual_payments_log("Shippo Webhook: Unhandled event type '{$event_type}'");
                }
                return new WP_REST_Response(array('message' => 'Event type not handled'), 200);
        }
    }
    
    /**
     * Handle order updated webhook
     */
    private function handle_order_updated($data) {
        if (!isset($data['data']['object'])) {
            return new WP_REST_Response(array('error' => 'Invalid order data'), 400);
        }
        
        $order_data = $data['data']['object'];
        $shippo_order_id = isset($order_data['object_id']) ? $order_data['object_id'] : null;
        $shippo_status = isset($order_data['order_status']) ? $order_data['order_status'] : null;
        
        if (!$shippo_order_id) {
            return new WP_REST_Response(array('error' => 'Missing Shippo order ID'), 400);
        }
        
        // Find WooCommerce order by Shippo order ID
        $wc_order = $this->find_order_by_shippo_id($shippo_order_id);
        
        if (!$wc_order) {
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("Shippo Webhook: Could not find WooCommerce order for Shippo ID '{$shippo_order_id}'");
            }
            return new WP_REST_Response(array('error' => 'Order not found'), 404);
        }
        
        // Map Shippo status to WooCommerce status
        $wc_status = $this->map_shippo_status_to_wc($shippo_status);
        
        if ($wc_status && $wc_status !== $wc_order->get_status()) {
            $old_status = $wc_order->get_status();
            $wc_order->update_status($wc_status, "Status updated via Shippo webhook: {$shippo_status}");
            
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("Shippo Webhook: Updated order {$wc_order->get_id()} from '{$old_status}' to '{$wc_status}' (Shippo: {$shippo_status})");
            }
        }
        
        return new WP_REST_Response(array('message' => 'Order updated successfully'), 200);
    }
    
    /**
     * Handle shipment updated webhook
     */
    private function handle_shipment_updated($data) {
        if (!isset($data['data']['object'])) {
            return new WP_REST_Response(array('error' => 'Invalid shipment data'), 400);
        }
        
        $shipment_data = $data['data']['object'];
        $tracking_number = isset($shipment_data['tracking_number']) ? $shipment_data['tracking_number'] : null;
        $status = isset($shipment_data['status']) ? $shipment_data['status'] : null;
        
        // Look for order references in the shipment data
        $order_reference = null;
        if (isset($shipment_data['metadata']['wc_order_id'])) {
            $order_reference = $shipment_data['metadata']['wc_order_id'];
        } elseif (isset($shipment_data['order'])) {
            // If linked to an order, try to find it
            $order_reference = $shipment_data['order'];
        }
        
        if (!$order_reference) {
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("Shippo Webhook: Shipment updated but no order reference found");
            }
            return new WP_REST_Response(array('message' => 'No order reference found'), 200);
        }
        
        $wc_order = wc_get_order($order_reference);
        if (!$wc_order) {
            return new WP_REST_Response(array('error' => 'Order not found'), 404);
        }
        
        // Update tracking information
        if ($tracking_number) {
            $wc_order->update_meta_data('_shippo_tracking_number', $tracking_number);
            $wc_order->save();

            // If the order is already completed, ensure the completed-order email is sent once tracking is added
            if ('completed' === $wc_order->get_status()) {
                $this->maybe_trigger_completed_email($wc_order->get_id(), $wc_order);
            }
            
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("Shippo Webhook: Added tracking number '{$tracking_number}' to order {$wc_order->get_id()}");
            }
        }
        
        // Update order status based on shipment status
        if ($status === 'SUCCESS' && $wc_order->get_status() !== 'completed') {
            $wc_order->update_status('completed', 'Shipment successful - updated via Shippo webhook');
            // Trigger completed email when moving to completed from webhook
            $this->maybe_trigger_completed_email($wc_order->get_id(), $wc_order);
            
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("Shippo Webhook: Marked order {$wc_order->get_id()} as completed - shipment successful");
            }
        }
        
        return new WP_REST_Response(array('message' => 'Shipment updated successfully'), 200);
    }
    
    /**
     * Handle tracking updated webhook
     */
    private function handle_tracking_updated($data) {
        if (!isset($data['data']['object'])) {
            return new WP_REST_Response(array('error' => 'Invalid tracking data'), 400);
        }
        
        $tracking_data = $data['data']['object'];
        $tracking_number = isset($tracking_data['tracking_number']) ? $tracking_data['tracking_number'] : null;
        $tracking_status = isset($tracking_data['tracking_status']) ? $tracking_data['tracking_status'] : null;
        
        if (!$tracking_number) {
            return new WP_REST_Response(array('error' => 'Missing tracking number'), 400);
        }
        
        // Find order by tracking number
        $orders = wc_get_orders(array(
            'meta_key' => '_shippo_tracking_number',
            'meta_value' => $tracking_number,
            'limit' => 1,
        ));
        
        if (empty($orders)) {
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("Shippo Webhook: No order found for tracking number '{$tracking_number}'");
            }
            return new WP_REST_Response(array('message' => 'No order found for tracking number'), 200);
        }
        
        $wc_order = $orders[0];
        
        // Update tracking status
        $wc_order->update_meta_data('_shippo_tracking_status', $tracking_status);
        $wc_order->save();
        
        // Update order status based on tracking status
        if ($tracking_status === 'DELIVERED' && $wc_order->get_status() !== 'completed') {
            $wc_order->update_status('completed', 'Package delivered - updated via Shippo webhook');
            
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("Shippo Webhook: Marked order {$wc_order->get_id()} as completed - package delivered");
            }
        }
        
        if (function_exists('twintack_manual_payments_log')) {
            twintack_manual_payments_log("Shippo Webhook: Updated tracking status for order {$wc_order->get_id()}: {$tracking_status}");
        }
        
        return new WP_REST_Response(array('message' => 'Tracking updated successfully'), 200);
    }
    
    /**
     * Find WooCommerce order by Shippo order ID
     */
    private function find_order_by_shippo_id($shippo_order_id) {
        $orders = wc_get_orders(array(
            'meta_key' => '_shippo_order_id',
            'meta_value' => $shippo_order_id,
            'limit' => 1,
        ));
        
        return !empty($orders) ? $orders[0] : null;
    }
    
    /**
     * Map Shippo order status to WooCommerce status
     */
    private function map_shippo_status_to_wc($shippo_status) {
        $status_mapping = array(
            'PAID' => 'processing',
            'SHIPPED' => 'completed',
            'DELIVERED' => 'completed',
            'CANCELLED' => 'cancelled',
            'REFUNDED' => 'refunded',
        );
        
        return isset($status_mapping[$shippo_status]) ? $status_mapping[$shippo_status] : null;
    }

    /**
     * Trigger the customer completed-order email safely.
     */
    private function maybe_trigger_completed_email($order_id, $order) {
        try {
            if (!function_exists('WC')) {
                return;
            }
            $mailer = WC()->mailer();
            if (!$mailer) {
                return;
            }
            $emails = $mailer->get_emails();
            if (isset($emails['WC_Email_Customer_Completed_Order'])) {
                $emails['WC_Email_Customer_Completed_Order']->trigger($order_id, $order);
                if (function_exists('twintack_manual_payments_log')) {
                    twintack_manual_payments_log("Shippo Webhook: Triggered customer completed-order email for order {$order_id}");
                }
            }
        } catch (Exception $e) {
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log('Email trigger error: ' . $e->getMessage(), 'error');
            }
        }
    }
}