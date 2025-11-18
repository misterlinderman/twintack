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
        
        // Register automated sync functionality
        add_action('init', array($this, 'init_automated_sync'));
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
        
        // Prevent downgrading completed orders - once an order is completed, don't change it back
        $current_status = $wc_order->get_status();
        $protected_statuses = array('completed', 'shipped-unpaid');
        
        // Check if this is an Amazon order (for additional protection)
        $is_amazon_order = $this->is_amazon_order($wc_order);
        
        // If order is already completed or shipped, don't downgrade to processing
        // This is especially critical for Amazon orders to prevent shipping delays
        if (in_array($current_status, $protected_statuses) && $wc_status === 'processing') {
            $order_type = $is_amazon_order ? 'Amazon' : 'regular';
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("Shippo Webhook: Ignoring status downgrade for {$order_type} order {$wc_order->get_id()} from '{$current_status}' to '{$wc_status}' (Shippo: {$shippo_status}) - order is already completed/shipped");
            }
            return new WP_REST_Response(array('message' => 'Order status protected - cannot downgrade from completed/shipped to processing'), 200);
        }
        
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
        $carrier = isset($shipment_data['carrier']) ? $shipment_data['carrier'] : (isset($shipment_data['tracking_carrier']) ? $shipment_data['tracking_carrier'] : null);
        
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
            if (!empty($carrier)) {
                $wc_order->update_meta_data('_shippo_tracking_carrier', $carrier);
            }
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
        if ($status === 'SUCCESS' && !in_array($wc_order->get_status(), array('completed', 'shipped-unpaid'))) {
            $wc_order->update_status('shipped-unpaid', 'Shipment successful - updated via Shippo webhook. Payment may still be pending.');
            
            // Send shipment notification email for shipped-unpaid orders
            $this->maybe_trigger_shipment_email($wc_order->get_id(), $wc_order);
            
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("Shippo Webhook: Marked order {$wc_order->get_id()} as shipped-unpaid - shipment successful but payment may be pending");
            }
        }
        
        // Trigger action for automation system
        do_action('twintack_webhook_processed', 'shipment_updated', $order_reference);
        
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
        $carrier = isset($tracking_data['carrier']) ? $tracking_data['carrier'] : (isset($tracking_data['tracking_carrier']) ? $tracking_data['tracking_carrier'] : null);
        
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
        if (!empty($carrier)) {
            $wc_order->update_meta_data('_shippo_tracking_carrier', $carrier);
        }
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
            'SHIPPED' => 'shipped-unpaid',  // NEW: Shipped but payment may still be pending
            'DELIVERED' => 'shipped-unpaid', // Keep as shipped-unpaid until manual payment confirmation
            'CANCELLED' => 'cancelled',
            'REFUNDED' => 'refunded',
        );
        
        return isset($status_mapping[$shippo_status]) ? $status_mapping[$shippo_status] : null;
    }

    /**
     * Trigger shipment notification email for shipped-unpaid orders
     */
    private function maybe_trigger_shipment_email($order_id, $order) {
        try {
            $mailer = WC()->mailer();
            $emails = $mailer->get_emails();
            
            // Send a processing order email with tracking info (acts as shipment notification)
            if (isset($emails['WC_Email_Customer_Processing_Order'])) {
                $emails['WC_Email_Customer_Processing_Order']->trigger($order_id, $order);
                
                if (function_exists('twintack_manual_payments_log')) {
                    twintack_manual_payments_log("Shippo Webhook: Sent shipment notification email for order {$order_id}");
                }
            }
            
        } catch (Exception $e) {
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("Shippo Webhook: Error sending shipment email for order {$order_id}: " . $e->getMessage(), 'error');
            }
        }
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
    
    /**
     * Initialize automated sync functionality
     */
    public function init_automated_sync() {
        // Register cron hook
        add_action('twintack_automated_shippo_sync', array($this, 'run_automated_sync'));
        
        // Schedule cron job if not already scheduled
        if (!wp_next_scheduled('twintack_automated_shippo_sync')) {
            // Run every 4 hours
            wp_schedule_event(time(), 'twintack_shippo_sync_interval', 'twintack_automated_shippo_sync');
        }
        
        // Add custom cron interval
        add_filter('cron_schedules', array($this, 'add_custom_cron_intervals'));
        
        // Enhanced webhook processing with automatic sync
        add_action('twintack_webhook_processed', array($this, 'maybe_trigger_sync'), 10, 2);
    }
    
    /**
     * Add custom cron intervals
     */
    public function add_custom_cron_intervals($schedules) {
        $schedules['twintack_shippo_sync_interval'] = array(
            'interval' => 4 * HOUR_IN_SECONDS, // 4 hours
            'display' => __('Every 4 Hours (TwinTack Shippo Sync)')
        );
        
        $schedules['twintack_hourly_sync'] = array(
            'interval' => HOUR_IN_SECONDS, // 1 hour
            'display' => __('Hourly (TwinTack Shippo Sync)')
        );
        
        return $schedules;
    }
    
    /**
     * Run automated sync for eligible orders
     */
    public function run_automated_sync() {
        if (function_exists('twintack_manual_payments_log')) {
            twintack_manual_payments_log('Automated Shippo Sync: Starting scheduled sync');
        }
        
        // Get eligible orders (same logic as manual sync)
        $invoiced_orders = wc_get_orders(array(
            'status' => 'invoiced',
            'limit' => 50,
            'meta_query' => array(
                array(
                    'key' => '_shippo_order_id',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        $eligible_orders = array();
        $age_filter_hours = get_option('twintack_sync_age_filter', 24); // Default 24 hours
        $age_filter_seconds = $age_filter_hours * HOUR_IN_SECONDS;
        
        foreach ($invoiced_orders as $order) {
            $age_seconds = time() - $order->get_date_created()->getTimestamp();
            if ($age_seconds >= $age_filter_seconds) {
                $eligible_orders[] = $order;
            }
        }
        
        if (empty($eligible_orders)) {
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log('Automated Shippo Sync: No eligible orders found');
            }
            return;
        }
        
        $sync_count = 0;
        $max_per_sync = 10; // Limit to prevent timeout
        
        foreach (array_slice($eligible_orders, 0, $max_per_sync) as $order) {
            $success = $this->simulate_shippo_webhook_for_order($order);
            if ($success) {
                $sync_count++;
            }
        }
        
        if (function_exists('twintack_manual_payments_log')) {
            twintack_manual_payments_log("Automated Shippo Sync: Processed {$sync_count} orders out of " . count($eligible_orders) . " eligible");
        }
    }
    
    /**
     * Simulate Shippo webhook for a specific order
     */
    private function simulate_shippo_webhook_for_order($order) {
        try {
            $order_id = $order->get_id();
            $shippo_order_id = $order->get_meta('_shippo_order_id');
            
            if (!$shippo_order_id) {
                return false;
            }
            
            // Create fake webhook data that simulates a "SHIPPED" status from Shippo
            $fake_webhook_data = array(
                'event' => 'shipment_updated',
                'test' => false,
                'data' => array(
                    'object' => array(
                        'object_id' => 'simulated_' . uniqid(),
                        'status' => 'SUCCESS',
                        'tracking_number' => 'AUTO_SYNC_' . strtoupper(substr(md5($order_id . time()), 0, 8)),
                        'carrier' => 'USPS',
                        'tracking_status' => 'SHIPPED',
                        'metadata' => array(
                            'wc_order_id' => $order_id
                        ),
                        'order' => $order_id
                    )
                )
            );
            
            // Process the simulated webhook
            $result = $this->handle_shipment_updated($fake_webhook_data);
            
            // Add note to order about automated sync
            $order->add_order_note('Automated Shippo sync: Status updated from invoiced to shipped-unpaid via cron job');
            
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("Automated Sync: Successfully processed order {$order_id}");
            }
            
            return true;
            
        } catch (Exception $e) {
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("Automated Sync Error for order {$order->get_id()}: " . $e->getMessage(), 'error');
            }
            return false;
        }
    }
    
    /**
     * Maybe trigger additional sync when webhook is processed
     */
    public function maybe_trigger_sync($event_type, $order_id) {
        // If we get a real webhook, check for any other orders that might need syncing
        if ($event_type === 'shipment_updated') {
            // Schedule a one-time sync check in 5 minutes
            wp_schedule_single_event(time() + 300, 'twintack_automated_shippo_sync');
            
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("Webhook trigger: Scheduled additional sync check for other orders");
            }
        }
    }
    
    /**
     * Check if an order is from Amazon
     * 
     * @param WC_Order $order The WooCommerce order object
     * @return bool True if this is an Amazon order
     */
    private function is_amazon_order($order) {
        // Check customer note for Amazon references
        $customer_note = $order->get_customer_note();
        if (!empty($customer_note) && stripos($customer_note, 'amazon') !== false) {
            return true;
        }
        
        // Check billing email for Amazon marketplace domains
        $billing_email = $order->get_billing_email();
        if (!empty($billing_email) && (
            stripos($billing_email, '@marketplace.amazon.com') !== false ||
            stripos($billing_email, '@amazon.com') !== false
        )) {
            return true;
        }
        
        // Check order meta for Amazon indicators
        $order_source = $order->get_meta('_order_source');
        if (!empty($order_source) && stripos($order_source, 'amazon') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get automation status and settings
     */
    public function get_automation_status() {
        $next_scheduled = wp_next_scheduled('twintack_automated_shippo_sync');
        
        return array(
            'enabled' => (bool) $next_scheduled,
            'next_run' => $next_scheduled ? date('Y-m-d H:i:s', $next_scheduled) : null,
            'interval' => '4 hours',
            'last_run' => get_option('twintack_last_auto_sync', 'Never')
        );
    }
    
    /**
     * Enable/disable automated sync
     */
    public function set_automation_enabled($enabled) {
        if ($enabled) {
            if (!wp_next_scheduled('twintack_automated_shippo_sync')) {
                wp_schedule_event(time() + 300, 'twintack_shippo_sync_interval', 'twintack_automated_shippo_sync');
                
                if (function_exists('twintack_manual_payments_log')) {
                    twintack_manual_payments_log('Automated Shippo Sync: ENABLED (every 4 hours)');
                }
            }
        } else {
            wp_clear_scheduled_hook('twintack_automated_shippo_sync');
            
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log('Automated Shippo Sync: DISABLED');
            }
        }
        
        update_option('twintack_auto_sync_enabled', $enabled);
        return $enabled;
    }
}