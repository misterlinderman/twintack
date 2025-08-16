<?php
/**
 * Bulk Invoice Manager
 * 
 * Handles bulk operations for invoiced orders including bulk mark as paid 
 * and CSV export functionality
 * 
 * @package TwinTack_Manual_Order_Payments
 * @since 4.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TwinTack_Bulk_Invoice_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Only initialize in admin
        if (!is_admin()) {
            return;
        }
        
        // Add bulk actions to orders list
        add_filter('bulk_actions-edit-shop_order', array($this, 'add_bulk_actions'));
        add_filter('bulk_actions-woocommerce_page_wc-orders', array($this, 'add_bulk_actions'));
        
        // Handle bulk actions
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'handle_bulk_actions'), 10, 3);
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', array($this, 'handle_bulk_actions'), 10, 3);
        
        // Add admin notices for bulk actions
        add_action('admin_notices', array($this, 'show_bulk_action_notices'));
        
        // Add export actions to orders page
        add_action('manage_posts_extra_tablenav', array($this, 'add_export_button'));
        add_action('manage_woocommerce_page_wc-orders_extra_tablenav', array($this, 'add_export_button'));
        
        // Handle export requests
        add_action('admin_init', array($this, 'handle_export_request'));
        
        // Add AJAX handlers for advanced operations
        add_action('wp_ajax_twintack_bulk_mark_paid', array($this, 'ajax_bulk_mark_paid'));
        add_action('wp_ajax_twintack_export_invoices', array($this, 'ajax_export_invoices'));
        add_action('wp_ajax_twintack_get_invoiced_orders', array($this, 'ajax_get_invoiced_orders'));
        
        // Add admin page for advanced bulk operations
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        twintack_manual_payments_log('Bulk Invoice Manager: Initialized');
    }
    
    /**
     * Add bulk actions to orders list
     */
    public function add_bulk_actions($actions) {
        $actions['twintack_mark_paid'] = __('Mark as Paid (TwinTack)', 'twintack-manual-payments');
        $actions['twintack_set_invoiced'] = __('Set to Invoiced (TwinTack)', 'twintack-manual-payments');
        $actions['twintack_export_selected'] = __('Export Selected (CSV)', 'twintack-manual-payments');
        
        return $actions;
    }
    
    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_to, $action, $post_ids) {
        if (!in_array($action, array('twintack_mark_paid', 'twintack_set_invoiced', 'twintack_export_selected'))) {
            return $redirect_to;
        }
        
        if (empty($post_ids)) {
            return $redirect_to;
        }
        
        $processed = 0;
        $errors = 0;
        
        switch ($action) {
            case 'twintack_mark_paid':
                foreach ($post_ids as $order_id) {
                    if ($this->mark_order_as_paid($order_id)) {
                        $processed++;
                    } else {
                        $errors++;
                    }
                }
                
                $redirect_to = add_query_arg(array(
                    'twintack_bulk_action' => 'mark_paid',
                    'processed' => $processed,
                    'errors' => $errors
                ), $redirect_to);
                break;
                
            case 'twintack_set_invoiced':
                foreach ($post_ids as $order_id) {
                    if ($this->set_order_invoiced($order_id)) {
                        $processed++;
                    } else {
                        $errors++;
                    }
                }
                
                $redirect_to = add_query_arg(array(
                    'twintack_bulk_action' => 'set_invoiced',
                    'processed' => $processed,
                    'errors' => $errors
                ), $redirect_to);
                break;
                
            case 'twintack_export_selected':
                // For CSV export, redirect to export handler
                $order_ids = implode(',', $post_ids);
                $redirect_to = add_query_arg(array(
                    'twintack_export' => 'selected',
                    'order_ids' => $order_ids,
                    'nonce' => wp_create_nonce('twintack_export_orders')
                ), admin_url('admin.php'));
                break;
        }
        
        return $redirect_to;
    }
    
    /**
     * Mark individual order as paid
     */
    private function mark_order_as_paid($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            twintack_manual_payments_log("Bulk action: Order {$order_id} not found", 'error');
            return false;
        }
        
        $current_status = $order->get_status();
        twintack_manual_payments_log("Bulk action: Processing order {$order_id} with current status: {$current_status}");
        
        // Skip orders that are already paid/completed
        if (in_array($current_status, array('processing', 'completed', 'shipped'))) {
            twintack_manual_payments_log("Bulk action: Order {$order_id} already in paid status ({$current_status}), skipping");
            return true; // Return true because it's not an error - just already processed
        }
        
        try {
            // Set payment method to manual if not set
            if (empty($order->get_payment_method())) {
                $order->set_payment_method('twintack_manual');
                $order->set_payment_method_title('Manual Payment (Admin)');
                twintack_manual_payments_log("Bulk action: Set payment method for order {$order_id}");
            }
            
            // Check if order can accept payment
            if ($order->get_total() <= 0) {
                twintack_manual_payments_log("Bulk action: Order {$order_id} has zero total, setting to processing directly");
                $order->update_status('processing', 'Order marked as paid via TwinTack bulk action (zero total).');
            } else {
                // Mark as paid using payment_complete which triggers proper WooCommerce hooks
                twintack_manual_payments_log("Bulk action: Calling payment_complete() for order {$order_id}");
                $order->payment_complete();
            }
            
            $order->add_order_note('Payment marked as received via TwinTack bulk action by admin.');
            
            // Update Shippo meta for fulfillment
            $order->update_meta_data('_shippo_fulfillment_status', 'Paid');
            $order->update_meta_data('_shippo_ready_for_fulfillment', 'yes');
            $order->update_meta_data('_twintack_manual_order', 'yes');
            $order->update_meta_data('_twintack_bulk_processed', current_time('timestamp'));
            $order->save();
            
            twintack_manual_payments_log("Bulk action: Order {$order_id} payment processing completed, new status: " . $order->get_status());
            
            // Trigger grip creation if applicable
            if (class_exists('TwinTack_Manual_Order_Payments') && 
                TwinTack_Manual_Order_Payments::get_option('auto_create_grip_posts', 'yes') === 'yes') {
                
                if (function_exists('twintack_trigger_grip_creation_from_order')) {
                    twintack_trigger_grip_creation_from_order($order->get_id());
                    twintack_manual_payments_log("Bulk action: Triggered grip creation for order {$order_id}");
                }
            }
            
            // Trigger Shippo sync if available
            $this->trigger_shippo_sync($order);
            
            twintack_manual_payments_log("Bulk action: Order {$order_id} marked as paid successfully, final status: " . $order->get_status());
            return true;
            
        } catch (Exception $e) {
            twintack_manual_payments_log("Bulk action: Error marking order {$order_id} as paid: " . $e->getMessage(), 'error');
            twintack_manual_payments_log("Bulk action: Exception trace: " . $e->getTraceAsString(), 'error');
            return false;
        }
    }
    
    /**
     * Set order to invoiced status
     */
    private function set_order_invoiced($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        try {
            // Set payment method to invoice if not set
            if (empty($order->get_payment_method())) {
                $order->set_payment_method('twintack_invoice');
                $order->set_payment_method_title('Invoice Payment (Pay Later)');
            }
            
            // Set to invoiced status
            $order->update_status('invoiced', 'Order set to invoiced via TwinTack bulk action.');
            
            // Update meta
            $order->update_meta_data('_twintack_manual_order', 'yes');
            $order->update_meta_data('_twintack_bulk_processed', current_time('timestamp'));
            $order->update_meta_data('_shippo_fulfillment_status', 'Payment Pending');
            $order->update_meta_data('_shippo_ready_for_fulfillment', 'yes');
            $order->save();
            
            // Trigger Shippo sync if available
            $this->trigger_shippo_sync($order);
            
            twintack_manual_payments_log("Bulk action: Order {$order_id} set to invoiced successfully");
            return true;
            
        } catch (Exception $e) {
            twintack_manual_payments_log("Bulk action: Error setting order {$order_id} to invoiced: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Trigger Shippo sync for an order
     */
    private function trigger_shippo_sync($order) {
        try {
            // Try multiple Shippo integration methods
            $order_id = $order->get_id();
            $wc_status = $order->get_status();
            
            // 1. Custom action
            do_action('twintack_shippo_status_updated', $order_id, 'Bulk Action', $wc_status);
            
            // 2. Direct integration call
            if (class_exists('TwinTack_Shippo_Integration')) {
                $shippo_integration = TwinTack_Shippo_Integration::get_instance();
                if (method_exists($shippo_integration, 'sync_order_with_shippo')) {
                    $shippo_integration->sync_order_with_shippo($order_id, '', $wc_status, $order);
                }
            }
            
            // 3. API client direct call
            if (class_exists('TwinTack_Shippo_API_Client')) {
                $api_client = TwinTack_Shippo_API_Client::get_instance();
                $shippo_order_data = array(
                    'order_id' => $order_id,
                    'status' => $wc_status,
                    'fulfillment_status' => $order->get_meta('_shippo_fulfillment_status') ?: 'Payment Pending'
                );
                $api_client->send_order_to_shippo($shippo_order_data, $order);
            }
            
        } catch (Exception $e) {
            twintack_manual_payments_log("Bulk action: Shippo sync error for order {$order->get_id()}: " . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Show bulk action notices
     */
    public function show_bulk_action_notices() {
        if (!isset($_GET['twintack_bulk_action'])) {
            return;
        }
        
        $action = sanitize_text_field($_GET['twintack_bulk_action']);
        $processed = isset($_GET['processed']) ? intval($_GET['processed']) : 0;
        $errors = isset($_GET['errors']) ? intval($_GET['errors']) : 0;
        
        $messages = array();
        
        switch ($action) {
            case 'mark_paid':
                if ($processed > 0) {
                    $messages[] = sprintf(__('%d orders marked as paid successfully.', 'twintack-manual-payments'), $processed);
                }
                if ($errors > 0) {
                    $messages[] = sprintf(__('%d orders failed to process.', 'twintack-manual-payments'), $errors);
                }
                break;
                
            case 'set_invoiced':
                if ($processed > 0) {
                    $messages[] = sprintf(__('%d orders set to invoiced successfully.', 'twintack-manual-payments'), $processed);
                }
                if ($errors > 0) {
                    $messages[] = sprintf(__('%d orders failed to process.', 'twintack-manual-payments'), $errors);
                }
                break;
        }
        
        foreach ($messages as $message) {
            $class = ($errors > 0) ? 'notice-warning' : 'notice-success';
            echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }
    
    /**
     * Add export button to orders page
     */
    public function add_export_button($which) {
        if ('top' !== $which) {
            return;
        }
        
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array('edit-shop_order', 'woocommerce_page_wc-orders'))) {
            return;
        }
        
        echo '<div class="alignleft actions">';
        echo '<button type="button" id="twintack-export-invoices" class="button button-secondary">';
        echo __('Export Invoiced Orders (CSV)', 'twintack-manual-payments');
        echo '</button>';
        echo '<button type="button" id="twintack-export-all-orders" class="button button-secondary" style="margin-left: 5px;">';
        echo __('Export All Orders (CSV)', 'twintack-manual-payments');
        echo '</button>';
        echo '</div>';
    }
    
    /**
     * Handle export requests
     */
    public function handle_export_request() {
        if (!isset($_GET['twintack_export']) || !isset($_GET['nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_GET['nonce'], 'twintack_export_orders')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('edit_shop_orders')) {
            wp_die('Insufficient permissions');
        }
        
        $export_type = sanitize_text_field($_GET['twintack_export']);
        
        switch ($export_type) {
            case 'selected':
                $order_ids = isset($_GET['order_ids']) ? explode(',', sanitize_text_field($_GET['order_ids'])) : array();
                $this->export_orders_csv(array_map('intval', $order_ids), 'selected_orders');
                break;
                
            case 'invoiced':
                $this->export_invoiced_orders_csv();
                break;
                
            case 'all':
                $this->export_all_orders_csv();
                break;
        }
        
        exit;
    }
    
    /**
     * Export invoiced orders to CSV
     */
    private function export_invoiced_orders_csv() {
        $orders = wc_get_orders(array(
            'status' => 'invoiced',
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        $this->export_orders_csv($orders, 'invoiced_orders');
    }
    
    /**
     * Export all orders to CSV
     */
    private function export_all_orders_csv() {
        $orders = wc_get_orders(array(
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        $this->export_orders_csv($orders, 'all_orders');
    }
    
    /**
     * Export orders to CSV
     */
    private function export_orders_csv($orders, $filename_prefix = 'orders') {
        // If we have order IDs instead of order objects, convert them
        if (!empty($orders) && is_numeric($orders[0])) {
            $order_ids = $orders;
            $orders = array();
            foreach ($order_ids as $order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $orders[] = $order;
                }
            }
        }
        
        if (empty($orders)) {
            wp_die('No orders found to export');
        }
        
        // Set headers for CSV download
        $filename = $filename_prefix . '_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Create file pointer connected to the output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM to fix UTF-8 in Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV column headers
        $headers = array(
            'Order ID',
            'Order Number',
            'Date Created',
            'Status',
            'Customer Name',
            'Customer Email',
            'Billing Address',
            'Shipping Address',
            'Payment Method',
            'Order Total',
            'Items',
            'Shippo Status',
            'Tracking Number',
            'Notes'
        );
        
        fputcsv($output, $headers);
        
        // Add order data
        foreach ($orders as $order) {
            $row = $this->prepare_order_row($order);
            fputcsv($output, $row);
        }
        
        fclose($output);
        
        twintack_manual_payments_log("CSV export completed: {$filename} with " . count($orders) . " orders");
        exit;
    }
    
    /**
     * Prepare order data for CSV row
     */
    private function prepare_order_row($order) {
        // Get customer name
        $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        if (empty($customer_name)) {
            $customer_name = $order->get_billing_company();
        }
        
        // Get billing address
        $billing_address = implode(', ', array_filter(array(
            $order->get_billing_address_1(),
            $order->get_billing_address_2(),
            $order->get_billing_city(),
            $order->get_billing_state(),
            $order->get_billing_postcode(),
            $order->get_billing_country()
        )));
        
        // Get shipping address
        $shipping_address = implode(', ', array_filter(array(
            $order->get_shipping_address_1(),
            $order->get_shipping_address_2(),
            $order->get_shipping_city(),
            $order->get_shipping_state(),
            $order->get_shipping_postcode(),
            $order->get_shipping_country()
        )));
        
        // Get items
        $items = array();
        foreach ($order->get_items() as $item) {
            $product_name = $item->get_name();
            $quantity = $item->get_quantity();
            $items[] = $quantity > 1 ? "{$product_name} (×{$quantity})" : $product_name;
        }
        $items_string = implode('; ', $items);
        
        // Get Shippo data
        $shippo_status = $order->get_meta('_shippo_fulfillment_status');
        $tracking_number = $order->get_meta('_shippo_tracking_number');
        if (empty($tracking_number)) {
            $tracking_number = $order->get_meta('_wc_shipment_tracking_items');
            if (is_array($tracking_number) && !empty($tracking_number)) {
                $tracking_number = $tracking_number[0]['tracking_number'] ?? '';
            }
        }
        
        // Get order notes (latest public note)
        $notes = '';
        $order_notes = wc_get_order_notes(array(
            'order_id' => $order->get_id(),
            'limit' => 1,
            'type' => 'customer'
        ));
        if (!empty($order_notes)) {
            $notes = wp_strip_all_tags($order_notes[0]->content);
        }
        
        return array(
            $order->get_id(),
            $order->get_order_number(),
            $order->get_date_created()->format('Y-m-d H:i:s'),
            ucfirst($order->get_status()),
            $customer_name,
            $order->get_billing_email(),
            $billing_address,
            $shipping_address,
            $order->get_payment_method_title(),
            $order->get_total(),
            $items_string,
            $shippo_status,
            $tracking_number,
            $notes
        );
    }
    
    /**
     * AJAX handler for bulk mark as paid
     */
    public function ajax_bulk_mark_paid() {
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_bulk_operations')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $order_ids = isset($_POST['order_ids']) ? array_map('intval', $_POST['order_ids']) : array();
        
        if (empty($order_ids)) {
            wp_send_json_error(array('message' => 'No orders selected'));
        }
        
        $processed = 0;
        $errors = 0;
        
        foreach ($order_ids as $order_id) {
            if ($this->mark_order_as_paid($order_id)) {
                $processed++;
            } else {
                $errors++;
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('%d orders marked as paid, %d errors', 'twintack-manual-payments'), $processed, $errors),
            'processed' => $processed,
            'errors' => $errors
        ));
    }
    
    /**
     * AJAX handler for export
     */
    public function ajax_export_invoices() {
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_bulk_operations')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $export_type = sanitize_text_field($_POST['export_type']);
        
        // Generate export URL
        $export_url = add_query_arg(array(
            'twintack_export' => $export_type,
            'nonce' => wp_create_nonce('twintack_export_orders')
        ), admin_url('admin.php'));
        
        wp_send_json_success(array(
            'message' => 'Export ready',
            'export_url' => $export_url
        ));
    }
    
    /**
     * Add admin menu for advanced bulk operations
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('TwinTack Bulk Operations', 'twintack-manual-payments'),
            __('Bulk Invoice Manager', 'twintack-manual-payments'),
            'edit_shop_orders',
            'twintack-bulk-operations',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Get statistics
        $invoiced_count = wc_orders_count('invoiced');
        $total_orders = wc_orders_count('any');
        $processing_count = wc_orders_count('processing');
        $completed_count = wc_orders_count('completed');
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(__('TwinTack Bulk Invoice Manager', 'twintack-manual-payments')); ?></h1>
            
            <div class="twintack-bulk-stats" style="display: flex; gap: 20px; margin: 20px 0;">
                <div style="background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 5px; min-width: 150px;">
                    <h3 style="margin: 0 0 10px 0;">📄 Invoiced Orders</h3>
                    <div style="font-size: 24px; font-weight: bold; color: #ff9800;"><?php echo $invoiced_count; ?></div>
                </div>
                <div style="background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 5px; min-width: 150px;">
                    <h3 style="margin: 0 0 10px 0;">⏳ Processing Orders</h3>
                    <div style="font-size: 24px; font-weight: bold; color: #28a745;"><?php echo $processing_count; ?></div>
                </div>
                <div style="background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 5px; min-width: 150px;">
                    <h3 style="margin: 0 0 10px 0;">✅ Completed Orders</h3>
                    <div style="font-size: 24px; font-weight: bold; color: #17a2b8;"><?php echo $completed_count; ?></div>
                </div>
                <div style="background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 5px; min-width: 150px;">
                    <h3 style="margin: 0 0 10px 0;">📊 Total Orders</h3>
                    <div style="font-size: 24px; font-weight: bold; color: #0073aa;"><?php echo $total_orders; ?></div>
                </div>
            </div>
            
            <div class="twintack-bulk-operations" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin: 20px 0;">
                <h2><?php echo esc_html(__('Bulk Operations', 'twintack-manual-payments')); ?></h2>
                
                <div style="margin-bottom: 20px;">
                    <h3><?php echo esc_html(__('Mark Invoiced Orders as Paid', 'twintack-manual-payments')); ?></h3>
                    <p><?php echo esc_html(__('Bulk process all invoiced orders to mark them as paid and ready for fulfillment.', 'twintack-manual-payments')); ?></p>
                    <button type="button" id="bulk-mark-invoiced-paid" class="button button-primary">
                        <?php echo esc_html(__('Mark All Invoiced Orders as Paid', 'twintack-manual-payments')); ?>
                    </button>
                </div>
                
                <div style="margin-bottom: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                    <h3><?php echo esc_html(__('Export Options', 'twintack-manual-payments')); ?></h3>
                    <p><?php echo esc_html(__('Export order data to CSV for reporting and analysis.', 'twintack-manual-payments')); ?></p>
                    <button type="button" id="export-invoiced-orders" class="button button-secondary" style="margin-right: 10px;">
                        <?php echo esc_html(__('Export Invoiced Orders', 'twintack-manual-payments')); ?>
                    </button>
                    <button type="button" id="export-all-orders" class="button button-secondary">
                        <?php echo esc_html(__('Export All Orders', 'twintack-manual-payments')); ?>
                    </button>
                </div>
                
                <div id="twintack-bulk-messages" style="margin-top: 20px;"></div>
            </div>
            
            <div class="twintack-bulk-help" style="background: #f0f8ff; padding: 15px; border: 1px solid #0073aa; border-radius: 5px; margin: 20px 0;">
                <h3><?php echo esc_html(__('How to Use', 'twintack-manual-payments')); ?></h3>
                <ul>
                    <li><strong><?php echo esc_html(__('Bulk Actions on Orders List:', 'twintack-manual-payments')); ?></strong> <?php echo esc_html(__('Go to WooCommerce → Orders, select multiple orders, and use the "Mark as Paid (TwinTack)" bulk action.', 'twintack-manual-payments')); ?></li>
                    <li><strong><?php echo esc_html(__('Individual Order Actions:', 'twintack-manual-payments')); ?></strong> <?php echo esc_html(__('Edit any order to see the TwinTack payment processing section with quick action buttons.', 'twintack-manual-payments')); ?></li>
                    <li><strong><?php echo esc_html(__('CSV Export:', 'twintack-manual-payments')); ?></strong> <?php echo esc_html(__('Use the export buttons to download order data for reporting and analysis.', 'twintack-manual-payments')); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler to get invoiced order IDs
     */
    public function ajax_get_invoiced_orders() {
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_bulk_operations')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $orders = wc_get_orders(array(
            'status' => 'invoiced',
            'limit' => -1,
            'return' => 'ids'
        ));
        
        wp_send_json_success(array(
            'order_ids' => $orders,
            'count' => count($orders)
        ));
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Load on our admin page and orders pages
        if (!in_array($hook, array('woocommerce_page_twintack-bulk-operations', 'edit.php', 'woocommerce_page_wc-orders'))) {
            return;
        }
        
        wp_enqueue_script(
            'twintack-bulk-operations',
            TWINTACK_MANUAL_PAYMENTS_PLUGIN_URL . 'assets/js/bulk-operations.js',
            array('jquery'),
            TWINTACK_MANUAL_PAYMENTS_VERSION,
            true
        );
        
        wp_localize_script('twintack-bulk-operations', 'twintackBulk', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('twintack_bulk_operations'),
            'export_nonce' => wp_create_nonce('twintack_export_orders'),
            'messages' => array(
                'confirm_mark_paid' => __('Are you sure you want to mark all invoiced orders as paid? This action cannot be undone.', 'twintack-manual-payments'),
                'confirm_export' => __('Generate CSV export?', 'twintack-manual-payments'),
                'processing' => __('Processing...', 'twintack-manual-payments'),
                'success' => __('Operation completed successfully!', 'twintack-manual-payments'),
                'error' => __('An error occurred. Please try again.', 'twintack-manual-payments')
            )
        ));
    }
}
