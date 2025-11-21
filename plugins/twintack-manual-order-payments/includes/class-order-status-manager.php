<?php
/**
 * TwinTack Order Status Manager
 * 
 * Manages custom order statuses and Shippo fulfillment status mapping
 * 
 * @package TwinTack_Manual_Order_Payments
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TwinTack_Order_Status_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'register_custom_order_statuses'));
        add_filter('wc_order_statuses', array($this, 'add_custom_order_statuses'));
        add_filter('woocommerce_valid_order_statuses_for_payment', array($this, 'add_valid_statuses_for_payment'));
        // REMOVED: add_action('woocommerce_order_status_changed', array($this, 'handle_status_change_for_shippo'), 10, 4);
        // This hook is now handled by TwinTack_Shippo_Integration to prevent double API calls
        
        // TARGETED: Auto-upgrade Amazon orders from Pending to Processing (only when in Pending status)
        // Hook into order creation and status changes to catch Amazon orders early
        add_action('woocommerce_checkout_order_processed', array($this, 'auto_upgrade_amazon_orders_on_creation'), 10, 1);
        add_action('woocommerce_new_order', array($this, 'auto_upgrade_amazon_orders_on_creation'), 10, 1);
        add_action('woocommerce_order_status_changed', array($this, 'auto_upgrade_amazon_orders_on_status_change'), 5, 4);
        
        // CRITICAL: Prevent downgrading completed/shipped orders to processing
        // This must run early (priority 5) to intercept ALL status changes before they're saved
        add_action('woocommerce_before_order_object_save', array($this, 'prevent_status_downgrade'), 5, 1);
        
        // Backup protection: Also hook into status changed to catch any that slip through
        // This runs immediately after status change (priority 1) to revert if needed
        add_action('woocommerce_order_status_changed', array($this, 'revert_status_downgrade'), 1, 4);
        
        // Make sure invoiced orders show in admin lists
        add_filter('woocommerce_reports_order_statuses', array($this, 'add_invoiced_to_reports'));
        add_filter('woocommerce_order_is_paid_statuses', array($this, 'remove_invoiced_from_paid_statuses'));
        add_filter('wc_order_is_editable', array($this, 'make_invoiced_orders_editable'), 10, 2);
        
        // HPOS-compatible admin list inclusion
        add_action('init', array($this, 'setup_admin_order_display'), 20);
        
        // Legacy post-based queries (for non-HPOS stores or mixed environments)
        add_action('pre_get_posts', array($this, 'include_invoiced_orders_in_admin_queries'));
        add_filter('woocommerce_order_table_search_query_meta_keys', array($this, 'add_invoiced_to_search'));
        add_action('parse_query', array($this, 'force_include_invoiced_orders'));
        add_filter('posts_where', array($this, 'modify_posts_where_for_invoiced'));
        
        // Handle mixed storage scenarios (legacy orders in HPOS-enabled environment)
        add_action('admin_init', array($this, 'handle_mixed_storage_scenario'));
        
        // Ensure proper admin display
        add_action('admin_footer', array($this, 'add_admin_order_styles'));
        
        // Add invoiced status to order status filters (single reliable hook)
        add_filter('views_woocommerce_page_wc-orders', array($this, 'add_invoiced_status_filter'));
        
        // Additional hooks for different WooCommerce versions/configurations (with singleton control)
        if (!has_action('current_screen', array($this, 'debug_current_screen_and_hooks'))) {
            add_action('current_screen', array($this, 'debug_current_screen_and_hooks'));
        }
        
        // Note: Removed problematic woocommerce_order_query_args hook that was causing critical errors
        
        // DISABLED: Automatic Shippo sync to prevent conflicts with Simple Order Manager
        // add_action('admin_init', array($this, 'sync_invoiced_orders_with_shippo'), 30);
        // add_action('current_screen', array($this, 'force_shippo_sync_on_orders_page'), 35);
        
        // Add email notification support for custom statuses
        add_filter('woocommerce_email_actions', array($this, 'add_email_actions_for_custom_statuses'));
    }
    
    /**
     * Register custom order statuses
     */
    public function register_custom_order_statuses() {
        register_post_status('wc-invoiced', array(
            'label'                     => _x('Invoiced', 'Order status', 'twintack-manual-payments'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Invoiced <span class="count">(%s)</span>', 'Invoiced <span class="count">(%s)</span>', 'twintack-manual-payments')
        ));
        
        register_post_status('wc-shipped-unpaid', array(
            'label'                     => _x('Shipped (Unpaid)', 'Order status', 'twintack-manual-payments'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Shipped (Unpaid) <span class="count">(%s)</span>', 'Shipped (Unpaid) <span class="count">(%s)</span>', 'twintack-manual-payments')
        ));
    }
    
    /**
     * Add custom order statuses to WooCommerce
     */
    public function add_custom_order_statuses($order_statuses) {
        $new_order_statuses = array();
        
        // Add all existing statuses
        foreach ($order_statuses as $key => $status) {
            $new_order_statuses[$key] = $status;
            
            // Add invoiced status after pending
            if ('wc-pending' === $key) {
                $new_order_statuses['wc-invoiced'] = _x('Invoiced', 'Order status', 'twintack-manual-payments');
            }
            
            // Add shipped (unpaid) status after processing
            if ('wc-processing' === $key) {
                $new_order_statuses['wc-shipped-unpaid'] = _x('Shipped (Unpaid)', 'Order status', 'twintack-manual-payments');
            }
        }
        
        return $new_order_statuses;
    }
    
    /**
     * Add custom statuses to valid statuses for payment
     */
    public function add_valid_statuses_for_payment($statuses) {
        $statuses[] = 'invoiced';
        $statuses[] = 'shipped-unpaid';  // Allow payment for shipped but unpaid orders
        return $statuses;
    }
    
    /**
     * Auto-upgrade Amazon orders from Pending to Processing when order is created
     * This only applies when orders are in Pending status - prevents need for downgrade protection
     * 
     * @param int $order_id The order ID
     */
    public function auto_upgrade_amazon_orders_on_creation($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Only process if this is an Amazon order
        if (!$this->is_amazon_order($order)) {
            return;
        }
        
        // Only upgrade if order is currently in Pending status
        // This prevents the issue where Completed orders get downgraded
        $current_status = $order->get_status();
        if ($current_status === 'pending') {
            $order->update_status('processing', 'Amazon order automatically upgraded from Pending to Processing for Shippo fulfillment.');
            
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("Amazon Order Auto-Upgrade: Order {$order_id} upgraded from Pending to Processing on creation");
            }
        }
    }
    
    /**
     * Auto-upgrade Amazon orders from Pending to Processing on status changes
     * This catches orders that might be created with pending status via other methods
     * 
     * @param int $order_id The order ID
     * @param string $old_status The old status
     * @param string $new_status The new status
     * @param WC_Order $order The order object
     */
    public function auto_upgrade_amazon_orders_on_status_change($order_id, $old_status, $new_status, $order) {
        if (!$order) {
            return;
        }
        
        // Only process if this is an Amazon order
        if (!$this->is_amazon_order($order)) {
            return;
        }
        
        // Only upgrade from Pending to Processing (not from any other status)
        // This prevents the issue where Completed orders get downgraded
        if ($old_status === 'pending' && $new_status === 'pending') {
            // This shouldn't happen (status didn't change), but handle edge case
            return;
        } elseif ($old_status === 'pending' && $new_status !== 'processing' && $new_status !== 'completed' && $new_status !== 'cancelled' && $new_status !== 'refunded') {
            // If Amazon order is moving from Pending to something other than Processing/Completed/Cancelled/Refunded,
            // upgrade it to Processing instead (unless it's already going to a final state)
            $order->update_status('processing', 'Amazon order automatically set to Processing for Shippo fulfillment.');
            
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("Amazon Order Auto-Upgrade: Order {$order_id} redirected from {$new_status} to Processing");
            }
        }
    }
    
    /**
     * Prevent downgrading completed/shipped orders to processing
     * This intercepts ALL status changes before they're saved to the database
     * 
     * @param WC_Order $order The order object being saved
     */
    public function prevent_status_downgrade($order) {
        if (!$order || !$order->get_id()) {
            return;
        }
        
        // Get the new status from the order object (what's being set)
        $changes = $order->get_changes();
        if (!isset($changes['status'])) {
            return; // No status change
        }
        
        $new_status = $changes['status'];
        
        // Get the current status from the database (what it currently is)
        $current_order = wc_get_order($order->get_id());
        if (!$current_order) {
            return;
        }
        
        $current_status = $current_order->get_status();
        
        // Protected statuses that should never be downgraded to processing
        $protected_statuses = array('completed', 'shipped-unpaid');
        
        // Prevent downgrade from protected statuses to processing
        if (in_array($current_status, $protected_statuses) && $new_status === 'processing') {
            // Check if this is an admin manual change (allow admins to override)
            $is_admin_override = $this->is_admin_manual_change();
            
            if ($is_admin_override) {
                // Allow admin override but log it
                $is_amazon_order = $this->is_amazon_order($order);
                $order_type = $is_amazon_order ? 'Amazon' : 'regular';
                $admin_user = wp_get_current_user();
                $admin_name = $admin_user ? $admin_user->display_name : 'Admin';
                
                if (function_exists('twintack_manual_payments_log')) {
                    twintack_manual_payments_log("ADMIN OVERRIDE: Allowed status downgrade for {$order_type} order {$order->get_id()} from '{$current_status}' to '{$new_status}' by admin user: {$admin_name}");
                }
                
                // Add order note about the admin override
                $order->add_order_note(sprintf(
                    'Status changed from %s to %s by admin override (%s).',
                    ucfirst($current_status),
                    ucfirst($new_status),
                    $admin_name
                ));
                
                // Allow the change to proceed
                return;
            }
            
            // Not an admin override - block the change
            // Check if this is an Amazon order
            $is_amazon_order = $this->is_amazon_order($order);
            $order_type = $is_amazon_order ? 'Amazon' : 'regular';
            
            // Log the blocked attempt
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("CRITICAL: Blocked status downgrade for {$order_type} order {$order->get_id()} from '{$current_status}' to '{$new_status}' - order is already completed/shipped");
            }
            
            // Add order note about the blocked change
            $order->add_order_note(sprintf(
                'Status change blocked: Cannot downgrade from %s to %s. Order is already completed/shipped.',
                ucfirst($current_status),
                ucfirst($new_status)
            ));
            
            // Revert the status change - keep the current status
            $order->set_status($current_status);
            
            // Also prevent the change from being saved
            return;
        }
    }
    
    /**
     * Backup protection: Revert status downgrades that slip through
     * This runs immediately after status change to catch any that bypassed prevent_status_downgrade
     * 
     * @param int $order_id The order ID
     * @param string $old_status The old status
     * @param string $new_status The new status
     * @param WC_Order $order The order object
     */
    public function revert_status_downgrade($order_id, $old_status, $new_status, $order) {
        if (!$order) {
            return;
        }
        
        // Protected statuses that should never be downgraded to processing
        $protected_statuses = array('completed', 'shipped-unpaid');
        
        // If we're trying to change from a protected status to processing, revert it
        if (in_array($old_status, $protected_statuses) && $new_status === 'processing') {
            // Check if this is an admin manual change (allow admins to override)
            $is_admin_override = $this->is_admin_manual_change();
            
            if ($is_admin_override) {
                // Allow admin override but log it
                $is_amazon_order = $this->is_amazon_order($order);
                $order_type = $is_amazon_order ? 'Amazon' : 'regular';
                $admin_user = wp_get_current_user();
                $admin_name = $admin_user ? $admin_user->display_name : 'Admin';
                
                if (function_exists('twintack_manual_payments_log')) {
                    twintack_manual_payments_log("ADMIN OVERRIDE: Allowed status downgrade for {$order_type} order {$order_id} from '{$old_status}' to '{$new_status}' by admin user: {$admin_name}");
                }
                
                // Add order note about the admin override (if not already added)
                $order->add_order_note(sprintf(
                    'Status changed from %s to %s by admin override (%s).',
                    ucfirst($old_status),
                    ucfirst($new_status),
                    $admin_name
                ));
                
                // Allow the change to proceed
                return;
            }
            
            // Not an admin override - revert the change
            // Check if this is an Amazon order
            $is_amazon_order = $this->is_amazon_order($order);
            $order_type = $is_amazon_order ? 'Amazon' : 'regular';
            
            // Log the blocked attempt
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("CRITICAL: Reverting status downgrade for {$order_type} order {$order_id} from '{$old_status}' to '{$new_status}' - order is already completed/shipped");
            }
            
            // Add order note about the blocked change
            $order->add_order_note(sprintf(
                'Status change reverted: Cannot downgrade from %s to %s. Order is already completed/shipped.',
                ucfirst($old_status),
                ucfirst($new_status)
            ));
            
            // Revert the status change - restore the old status
            $order->set_status($old_status);
            $order->save();
        }
    }
    
    /**
     * Check if the current status change is a manual admin action
     * 
     * @return bool True if this is an admin manual change
     */
    private function is_admin_manual_change() {
        // Check if current user has admin capabilities
        if (!current_user_can('manage_woocommerce') && !current_user_can('edit_shop_orders')) {
            return false;
        }
        
        // IMPORTANT: Exclude automated Amazon plugin syncs from being treated as "admin overrides"
        // The Amazon plugin runs with admin privileges but is NOT a manual admin action
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
        foreach ($backtrace as $trace) {
            // Check if this is coming from the Amazon plugin's automated sync
            if (isset($trace['file']) && 
                (strpos($trace['file'], 'amazon-for-woocommerce') !== false ||
                 strpos($trace['file'], 'class-order-manager.php') !== false) &&
                isset($trace['function']) && 
                $trace['function'] === 'ced_amazon_manage_order_status') {
                
                if (function_exists('twintack_manual_payments_log')) {
                    twintack_manual_payments_log("DETECTED: Amazon plugin automated sync - NOT treating as admin override");
                }
                return false; // This is automated, not a manual admin action
            }
        }
        
        // Check if this is coming from admin interface (not API/webhook)
        // Admin changes typically come from POST requests in admin area
        if (is_admin() && (isset($_POST['order_status']) || isset($_REQUEST['order_status']))) {
            return true;
        }
        
        // Check if this is from WooCommerce admin order edit page
        if (is_admin() && isset($_GET['post']) && isset($_POST['save'])) {
            return true;
        }
        
        // Check if this is from HPOS admin order edit page
        if (is_admin() && isset($_GET['id']) && isset($_POST['order_status'])) {
            return true;
        }
        
        // Check if this is from AJAX admin action
        if (defined('DOING_AJAX') && DOING_AJAX && is_admin()) {
            // Allow if it's from admin AJAX (not public API)
            return true;
        }
        
        // If we're in admin and user has permissions, but can't determine source,
        // default to allowing it (safer to allow admin than block legitimate changes)
        if (is_admin() && current_user_can('manage_woocommerce')) {
            return true;
        }
        
        return false;
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
     * Handle status changes for Shippo fulfillment mapping
     * 
     * Status Mapping:
     * - Processing = Paid (ready for fulfillment)
     * - Invoiced = Payment Pending (awaiting payment)
     * - Completed = Shipped (order fulfilled and shipped)
     */
    public function handle_status_change_for_shippo($order_id, $old_status, $new_status, $order) {
        if (!$order) {
            return;
        }
        
        // Log status change
        if (function_exists('twintack_manual_payments_log')) {
            twintack_manual_payments_log("Order {$order_id} status changed from {$old_status} to {$new_status}");
        }
        
        // Update Shippo fulfillment status based on WooCommerce status
        $shippo_status = $this->get_shippo_status_from_wc_status($new_status);
        
        if ($shippo_status) {
            $order->update_meta_data('_shippo_fulfillment_status', $shippo_status);
            $order->save();
            
            // Add order note about Shippo status
            $order->add_order_note(sprintf(
                'Shippo fulfillment status updated to: %s (based on WooCommerce status: %s)',
                $shippo_status,
                $new_status
            ));
            
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("Order {$order_id} Shippo status set to: {$shippo_status}");
            }
            
            // Trigger Shippo API update if integration exists
            do_action('twintack_shippo_status_updated', $order_id, $shippo_status, $new_status);
        }
        
        // Handle special cases for invoice workflow
        $this->handle_invoice_workflow_status_change($order, $old_status, $new_status);
    }
    
    /**
     * Get Shippo fulfillment status from WooCommerce status
     */
    private function get_shippo_status_from_wc_status($wc_status) {
        $status_mapping = array(
            'processing' => 'Paid',           // Ready for fulfillment
            'invoiced'   => 'Payment Pending', // Awaiting payment
            'completed'  => 'Shipped',        // Order fulfilled and shipped
            'on-hold'    => 'Payment Pending', // Also payment pending
        );
        
        return isset($status_mapping[$wc_status]) ? $status_mapping[$wc_status] : null;
    }
    
    /**
     * Handle invoice workflow status changes
     */
    private function handle_invoice_workflow_status_change($order, $old_status, $new_status) {
        // When order moves from invoiced to processing, payment was received
        if ($old_status === 'invoiced' && $new_status === 'processing') {
            $order->add_order_note('Payment received for invoiced order. Order is now ready for fulfillment.');
            
            // Clear the payment link flag
            $order->delete_meta_data('_twintack_needs_payment_link');
            $order->save();
            
            // Trigger grip creation if applicable
            if (function_exists('twintack_trigger_grip_creation_from_order')) {
                twintack_trigger_grip_creation_from_order($order->get_id());
            }
        }
        
        // When order is set to invoiced, ensure payment link is flagged
        if ($new_status === 'invoiced') {
            $order->update_meta_data('_twintack_needs_payment_link', 'yes');
            $order->save();
        }
    }
    
    /**
     * Get all TwinTack status options for admin interface
     */
    public function get_payment_action_options() {
        return array(
            'pay_now' => array(
                'label' => 'Pay Now (Mark as Paid)',
                'description' => 'Mark order as paid immediately and set to Processing status',
                'target_status' => 'processing',
                'shippo_status' => 'Paid'
            ),
            'pay_later' => array(
                'label' => 'Pay Later (Invoice)',
                'description' => 'Set to Invoice status and send payment link to customer',
                'target_status' => 'invoiced',
                'shippo_status' => 'Payment Pending'
            ),
            'stripe_checkout' => array(
                'label' => 'Send Stripe Payment Link',
                'description' => 'Create Stripe checkout session and email link to customer',
                'target_status' => 'pending',
                'shippo_status' => 'Payment Pending'
            )
        );
    }
    
    /**
     * Get current Shippo status for an order
     */
    public function get_order_shippo_status($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return null;
        }
        
        return $order->get_meta('_shippo_fulfillment_status');
    }
    
    /**
     * Check if order needs payment link
     */
    public function order_needs_payment_link($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        return $order->get_meta('_twintack_needs_payment_link') === 'yes';
    }
    
    /**
     * Mark order as shipped (complete)
     */
    public function mark_order_shipped($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        $order->update_status('completed', 'Order marked as shipped via TwinTack Manual Payments.');
        
        return true;
    }
    
    /**
     * Add invoiced status to reports
     */
    public function add_invoiced_to_reports($statuses) {
        $statuses[] = 'invoiced';
        return $statuses;
    }
    
    /**
     * Remove invoiced from paid statuses (since they're awaiting payment)
     */
    public function remove_invoiced_from_paid_statuses($statuses) {
        $key = array_search('invoiced', $statuses);
        if ($key !== false) {
            unset($statuses[$key]);
        }
        return $statuses;
    }
    
    /**
     * Make invoiced orders editable
     */
    public function make_invoiced_orders_editable($is_editable, $order) {
        if ($order->get_status() === 'invoiced') {
            return true;
        }
        return $is_editable;
    }
    
    /**
     * Add admin styles for invoiced orders
     */
    public function add_admin_order_styles() {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array('woocommerce_page_wc-orders', 'edit-shop_order'))) {
            return;
        }
        ?>
        <style>
        .order-status.status-invoiced {
            background: #ff9800;
            color: white;
            border-radius: 3px;
            padding: 3px 8px;
            font-weight: bold;
            font-size: 11px;
        }
        .widefat .column-order_status mark.invoiced {
            background: #ff9800;
            color: white;
        }
        .order-status.status-shipped-unpaid {
            background: #17a2b8;
            color: white;
            border-radius: 3px;
            padding: 3px 8px;
            font-weight: bold;
            font-size: 11px;
        }
        .widefat .column-order_status mark.shipped-unpaid {
            background: #17a2b8;
            color: white;
        }
        </style>
        <?php
    }
    
    /**
     * Include invoiced orders in admin queries
     */
    public function include_invoiced_orders_in_admin_queries($query) {
        // Only modify admin queries for orders
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        global $pagenow, $typenow;
        
        // Only handle legacy wp_posts queries (not HPOS)
        if ($pagenow !== 'edit.php' || $typenow !== 'shop_order') {
            return;
        }
        
        $post_status = $query->get('post_status');
        $url_post_status = isset($_GET['post_status']) ? $_GET['post_status'] : '';
        
        twintack_manual_payments_log("Legacy query - URL post_status: '{$url_post_status}', Query post_status: " . (is_array($post_status) ? implode(',', $post_status) : $post_status));
        
        // Handle specific invoiced status filter
        if ($url_post_status === 'wc-invoiced' || $url_post_status === 'invoiced') {
            $query->set('post_status', array('wc-invoiced'));
            $query->set('meta_query', array(
                'relation' => 'OR',
                array(
                    'key' => '_order_status',
                    'value' => 'invoiced',
                    'compare' => '='
                )
            ));
            twintack_manual_payments_log("Legacy query: Set to show ONLY invoiced orders");
            return;
        }
        
        // Always include invoiced orders in the default view
        if (empty($post_status) || $post_status === 'any' || 
            (is_array($post_status) && in_array('any', $post_status)) ||
            empty($url_post_status)) {
            
            // Get all WooCommerce order statuses and add invoiced
            $all_statuses = array_keys(wc_get_order_statuses());
            if (!in_array('wc-invoiced', $all_statuses)) {
                $all_statuses[] = 'wc-invoiced';
            }
            
            $query->set('post_status', $all_statuses);
            twintack_manual_payments_log("Legacy query: Added invoiced status to order list (found " . count($all_statuses) . " total statuses)");
        }
    }
    
    /**
     * Add invoiced status to search queries
     */
    public function add_invoiced_to_search($meta_keys) {
        $meta_keys[] = '_order_status';
        return $meta_keys;
    }
    

    
    /**
     * Force include invoiced orders using parse_query hook
     */
    public function force_include_invoiced_orders($query) {
        global $pagenow, $typenow;
        
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        // Check if we're on the orders page
        if (($pagenow === 'edit.php' && $typenow === 'shop_order') || 
            ($pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === 'wc-orders')) {
            
            $post_status = $query->get('post_status');
            
            // Force include invoiced status
            if (empty($post_status) || $post_status === 'any') {
                $all_statuses = array_keys(wc_get_order_statuses());
                $all_statuses[] = 'wc-invoiced';
                $query->set('post_status', $all_statuses);
                twintack_manual_payments_log("parse_query: Forced invoiced orders inclusion");
            }
        }
    }
    
    /**
     * Modify the WHERE clause to include invoiced orders (legacy wp_posts only)
     */
    public function modify_posts_where_for_invoiced($where) {
        global $wpdb, $pagenow, $typenow;
        
        if (!is_admin()) {
            return $where;
        }
        
        // Only handle legacy wp_posts queries (not HPOS)
        if ($pagenow !== 'edit.php' || $typenow !== 'shop_order') {
            return $where;
        }
        
        $url_post_status = isset($_GET['post_status']) ? $_GET['post_status'] : '';
        
        // Handle specific invoiced filter
        if ($url_post_status === 'wc-invoiced' || $url_post_status === 'invoiced') {
            // Force query to look for invoiced orders in multiple ways
            $invoiced_where = "({$wpdb->posts}.post_status = 'wc-invoiced' OR {$wpdb->posts}.post_status = 'invoiced')";
            
            // Replace any existing status conditions with our invoiced condition
            if (strpos($where, 'post_status') !== false) {
                $where = preg_replace(
                    '/\(\s*' . preg_quote($wpdb->posts, '/') . '\.post_status\s*=\s*\'[^\']+\'\s*\)/',
                    $invoiced_where,
                    $where
                );
            } else {
                $where .= " AND $invoiced_where";
            }
            
            twintack_manual_payments_log("posts_where: Modified WHERE for invoiced filter: $invoiced_where");
        } else {
            // If the query is looking for specific statuses but not including invoiced, add it
            if (strpos($where, 'post_status') !== false && strpos($where, 'wc-invoiced') === false && empty($url_post_status)) {
                // Add invoiced status to any existing status conditions
                $where = str_replace(
                    "post_status = 'wc-",
                    "(post_status = 'wc-invoiced' OR post_status = 'wc-",
                    $where
                );
                
                twintack_manual_payments_log("posts_where: Added invoiced to general order list");
            }
        }
        
        return $where;
    }
    
    /**
     * Setup admin order display for both HPOS and legacy storage
     */
    public function setup_admin_order_display() {
        // Check if HPOS is enabled
        if ($this->is_hpos_enabled()) {
            twintack_manual_payments_log("HPOS detected - setting up HPOS-compatible admin display");
            
            // Multiple HPOS hooks for better coverage
            add_filter('woocommerce_order_list_table_prepare_items_query_args', array($this, 'modify_hpos_admin_query'), 10, 2);
            add_filter('woocommerce_orders_table_query_clauses', array($this, 'modify_hpos_query_clauses'), 10, 2);
            
            // Alternative approach - hook into the actual query
            add_action('pre_get_posts', array($this, 'debug_hpos_queries'));
            
            // Direct approach - force include in admin queries
            add_action('current_screen', array($this, 'force_hpos_status_inclusion'), 20);
            
            // Hook into the order query class
            add_action('woocommerce_before_order_list_table', array($this, 'log_hpos_page_load'));
            
        } else {
            twintack_manual_payments_log("Legacy post storage detected - using post-based admin display");
        }
        
        // Add JavaScript to refresh order display on orders page
        add_action('admin_footer', array($this, 'add_order_display_refresh_script'));
    }
    
    /**
     * Check if HPOS (High Performance Order Storage) is enabled
     */
    private function is_hpos_enabled() {
        // Check if the HPOS feature is available and enabled
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
            return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }
        
        // Fallback: check if OrdersTableDataStore is being used
        if (function_exists('wc_get_container')) {
            try {
                $data_store = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::class);
                return !is_null($data_store);
            } catch (Exception $e) {
                return false;
            }
        }
        
        return false;
    }
    
    /**
     * Modify HPOS admin query to include invoiced orders
     */
    public function modify_hpos_admin_query($query_args, $query = null) {
        global $pagenow;
        
        twintack_manual_payments_log("HPOS admin query hook fired - Current args: " . print_r($query_args, true));
        
        // Check for invoiced filter in URL
        $url_status = isset($_GET['status']) ? $_GET['status'] : '';
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        
        // Handle both string and array status
        $status_string = '';
        if (is_array($url_status)) {
            $status_string = implode(',', $url_status);
            $is_invoiced_filter = in_array('invoiced', $url_status) || (strpos($request_uri, 'status=invoiced') !== false);
        } else {
            $status_string = $url_status;
            $is_invoiced_filter = ($url_status === 'invoiced') || (strpos($request_uri, 'status=invoiced') !== false);
        }
        
        twintack_manual_payments_log("HPOS admin query debug - URL status: '$status_string' (type: " . gettype($url_status) . "), Invoiced filter detected: " . ($is_invoiced_filter ? 'YES' : 'NO'));
        
        if ($is_invoiced_filter) {
            // Force invoiced status only
            $query_args['status'] = ['invoiced'];
            twintack_manual_payments_log("HPOS admin query: FORCED status to invoiced only");
        } elseif (!isset($query_args['status']) || empty($query_args['status'])) {
            // No status filter set - this is the "All" view, include all statuses including invoiced
            $all_statuses = array_keys(wc_get_order_statuses());
            
            // Remove the 'wc-' prefix for HPOS queries
            $hpos_statuses = array_map(function($status) {
                return str_replace('wc-', '', $status);
            }, $all_statuses);
            
            // Ensure 'invoiced' is included for HPOS (stored without wc- prefix)
            if (!in_array('invoiced', $hpos_statuses)) {
                $hpos_statuses[] = 'invoiced';
            }
            
            $query_args['status'] = $hpos_statuses;
            twintack_manual_payments_log("HPOS admin query: Set all statuses for 'All' view including invoiced (" . count($hpos_statuses) . " total statuses)");
        } else {
            // Specific status filter is set - DON'T add invoiced to other status views
            // This prevents invoiced orders from appearing in Processing, Completed, etc.
            twintack_manual_payments_log("HPOS admin query: Specific status filter detected (" . implode(',', $query_args['status']) . ") - NOT adding invoiced");
        }
        
        return $query_args;
    }
    
    /**
     * Alternative HPOS query modification using query clauses
     */
    public function modify_hpos_query_clauses($clauses, $query) {
        global $pagenow;
        
        twintack_manual_payments_log("HPOS query clauses hook fired - WHERE: " . (isset($clauses['where']) ? $clauses['where'] : 'none'));
        
        // Multiple ways to detect invoiced filter
        $url_status = isset($_GET['status']) ? $_GET['status'] : '';
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        
        // Handle both string and array status
        $status_string = '';
        if (is_array($url_status)) {
            $status_string = implode(',', $url_status);
            $is_invoiced_filter = in_array('invoiced', $url_status) || (strpos($request_uri, 'status=invoiced') !== false);
        } else {
            $status_string = $url_status;
            $is_invoiced_filter = ($url_status === 'invoiced') || (strpos($request_uri, 'status=invoiced') !== false);
        }
        
        twintack_manual_payments_log("HPOS query debug - URL status: '$status_string' (type: " . gettype($url_status) . "), Request URI contains invoiced: " . (strpos($request_uri, 'status=invoiced') !== false ? 'YES' : 'NO'));
        
        // Force invoiced detection if we're on the orders page and see invoiced in the query
        if (is_admin() && $pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === 'wc-orders') {
            if ($is_invoiced_filter) {
                twintack_manual_payments_log("HPOS: DETECTED INVOICED FILTER - Forcing query modification");
                
                // Force the query to look specifically for wc-invoiced status
                if (isset($clauses['where']) && strpos($clauses['where'], 'status') !== false) {
                    // Replace any existing status condition with invoiced only
                    $original_where = $clauses['where'];
                                    $clauses['where'] = preg_replace(
                    '/sCO_wc_orders\.status IN \([^)]+\)/',
                    "sCO_wc_orders.status = 'invoiced'",
                    $clauses['where']
                );
                    
                    twintack_manual_payments_log("HPOS: FORCED WHERE MODIFICATION");
                    twintack_manual_payments_log("HPOS: Original WHERE: " . $original_where);
                    twintack_manual_payments_log("HPOS: New WHERE: " . $clauses['where']);
                }
            } else if (empty($url_status) || (is_array($url_status) && empty($url_status))) {
                // This is the "All" view - replace wc-invoiced with invoiced
                if (isset($clauses['where']) && strpos($clauses['where'], 'status') !== false) {
                    $original_where = $clauses['where'];
                    
                    // Replace wc-invoiced with invoiced in the status list
                    $clauses['where'] = str_replace(
                        "'wc-invoiced'",
                        "'invoiced'",
                        $clauses['where']
                    );
                    
                    // Also add invoiced if not present
                    if (strpos($clauses['where'], 'invoiced') === false) {
                        $clauses['where'] = str_replace(
                            "sCO_wc_orders.status IN (",
                            "sCO_wc_orders.status IN ('invoiced',",
                            $clauses['where']
                        );
                    }
                    
                    twintack_manual_payments_log("HPOS query clauses: Fixed 'All' view - replaced wc-invoiced with invoiced");
                    if ($original_where !== $clauses['where']) {
                        twintack_manual_payments_log("HPOS: WHERE CHANGED FROM: " . $original_where);
                        twintack_manual_payments_log("HPOS: WHERE CHANGED TO: " . $clauses['where']);
                    }
                }
            }
        }
        
        return $clauses;
    }
    
    /**
     * Note: Removed translate_status_for_hpos method that was causing critical errors
     * The existing query modification methods handle status translation adequately
     */
    
    /**
     * Debug HPOS queries to see what's happening
     */
    public function debug_hpos_queries($query) {
        global $pagenow;
        
        if (is_admin() && $pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === 'wc-orders') {
            twintack_manual_payments_log("HPOS debug: On orders page - Query type: " . get_class($query));
            
            if (isset($_GET['status'])) {
                twintack_manual_payments_log("HPOS debug: Status filter requested: " . $_GET['status']);
            }
        }
    }
    
    /**
     * Enhanced invoiced status filter for both HPOS and legacy
     */
    public function add_invoiced_status_filter($views) {
        global $wpdb;
        
        twintack_manual_payments_log("Tab Creation: add_invoiced_status_filter called with hook: " . current_filter());
        
        // Count invoiced orders in BOTH storage systems (mixed environment)
        $hpos_count = 0;
        $legacy_count = 0;
        
        // Count HPOS orders (HPOS stores without 'wc-' prefix)
        if ($this->is_hpos_enabled()) {
            $hpos_count = (int) $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders 
                WHERE status = 'invoiced' AND type = 'shop_order'
            ");
        }
        
        // Count legacy orders - check both post_status patterns
        $legacy_count = (int) $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_type = 'shop_order' 
            AND (post_status = 'wc-invoiced' OR post_status = 'invoiced')
        ");
        
        // Also check for orders that might have invoiced status in meta
        $meta_count = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND pm.meta_key = '_order_status'
            AND pm.meta_value = 'invoiced'
        ");
        
        $total_count = $hpos_count + $legacy_count + $meta_count;
        
        twintack_manual_payments_log("Invoiced order count - HPOS: {$hpos_count}, Legacy: {$legacy_count}, Meta: {$meta_count}, Total: {$total_count}");
        
        if ($total_count > 0) {
            $class = '';
            $current_status = isset($_GET['status']) ? $_GET['status'] : (isset($_GET['post_status']) ? $_GET['post_status'] : '');
            if ($current_status === 'invoiced' || $current_status === 'wc-invoiced') {
                $class = 'current';
            }
            
            // Use HPOS URL if enabled, otherwise legacy
            if ($this->is_hpos_enabled()) {
                $url = admin_url('admin.php?page=wc-orders&status=invoiced');
            } else {
                $url = admin_url('edit.php?post_type=shop_order&post_status=wc-invoiced');
            }
            
            $views['invoiced'] = sprintf(
                '<a href="%s" class="%s">Invoiced <span class="count">(%d)</span></a>',
                $url,
                $class,
                $total_count
            );
            
            twintack_manual_payments_log("Added invoiced status filter: {$total_count} orders found (HPOS: {$hpos_count}, Legacy: {$legacy_count}, Meta: {$meta_count})");
        }
        
        return $views;
    }
    
    /**
     * Force HPOS status inclusion using current_screen hook
     */
    public function force_hpos_status_inclusion($screen) {
        if ($screen && $screen->id === 'woocommerce_page_wc-orders') {
            twintack_manual_payments_log("HPOS: On orders admin page - screen detected");
            
            // Log all URL parameters for debugging
            $url_params = $_GET;
            twintack_manual_payments_log("HPOS: URL parameters: " . print_r($url_params, true));
            
            // Check specifically for invoiced status
            if (isset($_GET['status']) && $_GET['status'] === 'invoiced') {
                twintack_manual_payments_log("HPOS: Invoiced status filter detected in URL - this should trigger our query modification");
            } else if (!isset($_GET['status'])) {
                twintack_manual_payments_log("HPOS: No status filter in URL - this is the 'All' view");
            } else {
                twintack_manual_payments_log("HPOS: Other status filter detected: " . $_GET['status']);
            }
        }
    }
    
    /**
     * Log when HPOS page loads
     */
    public function log_hpos_page_load() {
        twintack_manual_payments_log("HPOS: Order list table is about to load");
        
        if (isset($_GET['status'])) {
            twintack_manual_payments_log("HPOS: Status filter in URL: " . $_GET['status']);
        }
        
        // Check what statuses are registered
        $statuses = wc_get_order_statuses();
        $status_count = count($statuses);
        $has_invoiced = isset($statuses['wc-invoiced']);
        
        twintack_manual_payments_log("HPOS: {$status_count} total statuses registered, invoiced status: " . ($has_invoiced ? 'YES' : 'NO'));
    }
    
    /**
     * Handle mixed storage scenarios where HPOS is enabled but orders exist in wp_posts
     */
    public function handle_mixed_storage_scenario() {
        if (!is_admin()) {
            return;
        }
        
        global $pagenow;
        
        // Only run on orders pages
        if (!in_array($pagenow, array('admin.php', 'edit.php'))) {
            return;
        }
        
        // Check if this is an HPOS environment with legacy orders
        if ($this->is_hpos_enabled()) {
            global $wpdb;
            
            // Count orders in both storage systems  
            $hpos_invoiced = (int) $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders 
                WHERE status = 'invoiced' AND type = 'shop_order'
            ");
            
            $legacy_invoiced = (int) $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE post_type = 'shop_order' 
                AND (post_status = 'wc-invoiced' OR post_status = 'invoiced')
            ");
            
            if ($legacy_invoiced > 0 && $hpos_invoiced === 0) {
                twintack_manual_payments_log("Mixed storage detected: {$legacy_invoiced} invoiced orders in wp_posts, {$hpos_invoiced} in HPOS");
                
                // Add admin notice about migration
                add_action('admin_notices', array($this, 'show_migration_notice'));
                
                // Ensure legacy query hooks work even in HPOS environment
                $this->force_legacy_compatibility();
            }
        }
    }
    
    /**
     * Show admin notice about order migration
     */
    public function show_migration_notice() {
        if (current_user_can('manage_woocommerce')) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>TwinTack Manual Payments:</strong> Some invoiced orders are in legacy storage. ';
            echo 'Consider migrating orders to HPOS in WooCommerce > Settings > Advanced > Features for better performance.</p>';
            echo '</div>';
        }
    }
    
    /**
     * Force legacy compatibility in HPOS environment
     */
    private function force_legacy_compatibility() {
        // Ensure legacy hooks work even when HPOS is enabled
        if (!has_action('pre_get_posts', array($this, 'include_invoiced_orders_in_admin_queries'))) {
            add_action('pre_get_posts', array($this, 'include_invoiced_orders_in_admin_queries'), 10);
        }
        
        if (!has_filter('posts_where', array($this, 'modify_posts_where_for_invoiced'))) {
            add_filter('posts_where', array($this, 'modify_posts_where_for_invoiced'), 10);
        }
        
        twintack_manual_payments_log("Forced legacy compatibility for mixed storage environment");
    }
    
    /**
     * Debug current screen and available hooks
     */
    public function debug_current_screen_and_hooks($screen) {
        if (!$screen) {
            return;
        }
        
        twintack_manual_payments_log("Current screen: ID={$screen->id}, base={$screen->base}, post_type=" . ($screen->post_type ?: 'none'));
        
        // Check if we're on the orders page
        if ($screen->id === 'woocommerce_page_wc-orders' || $screen->base === 'wc-orders') {
            twintack_manual_payments_log("On HPOS orders page - will inject tab via JavaScript");
        }
        
        if ($screen->id === 'edit-shop_order') {
            twintack_manual_payments_log("On legacy orders page - standard filters should work");
        }
    }
    

    
    /**
     * Sync existing invoiced orders with Shippo integration
     */
    public function sync_invoiced_orders_with_shippo() {
        twintack_manual_payments_log("Shippo Sync: Method called - checking prerequisites");
        
        // Only run once per hour to avoid performance issues
        if (get_transient('twintack_shippo_sync_complete')) {
            twintack_manual_payments_log("Shippo Sync: Already completed this hour, skipping");
            return;
        }
        
        // Check if Shippo integration class exists
        if (!class_exists('TwinTack_Shippo_Integration')) {
            twintack_manual_payments_log("Shippo Sync: TwinTack_Shippo_Integration class not found");
            return;
        }
        
        twintack_manual_payments_log("Shippo Sync: Prerequisites met, starting sync process");
        
        global $wpdb;
        
        // Get invoiced orders from HPOS
        $invoiced_order_ids = array();
        if ($this->is_hpos_enabled()) {
            $invoiced_order_ids = $wpdb->get_col("
                SELECT id FROM {$wpdb->prefix}wc_orders 
                WHERE status = 'invoiced' AND type = 'shop_order'
                LIMIT 20
            ");
        }
        
        // Also check legacy storage
        $legacy_invoiced_ids = $wpdb->get_col("
            SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'shop_order' 
            AND (post_status = 'wc-invoiced' OR post_status = 'invoiced')
            LIMIT 20
        ");
        
        $all_invoiced_ids = array_merge($invoiced_order_ids, $legacy_invoiced_ids);
        
        if (!empty($all_invoiced_ids)) {
            $shippo = TwinTack_Shippo_Integration::get_instance();
            $synced_count = 0;
            
            foreach ($all_invoiced_ids as $order_id) {
                $order = wc_get_order($order_id);
                if ($order && $order->get_status() === 'invoiced') {
                    // Trigger Shippo sync for this order
                    $shippo->sync_order_with_shippo($order_id, '', 'invoiced', $order);
                    $synced_count++;
                }
            }
            
            if ($synced_count > 0) {
                twintack_manual_payments_log("Shippo Sync: Synced {$synced_count} invoiced orders with Shippo");
            }
        }
        
        // Set transient to prevent running again for 1 hour
        set_transient('twintack_shippo_sync_complete', true, HOUR_IN_SECONDS);
    }
    
    /**
     * Force Shippo sync when on orders page  
     */
    public function force_shippo_sync_on_orders_page() {
        $screen = get_current_screen();
        if ($screen && ($screen->id === 'woocommerce_page_wc-orders' || $screen->base === 'woocommerce_page_wc-orders')) {
            twintack_manual_payments_log("Force Shippo Sync: On orders page ({$screen->id}), triggering aggressive sync");
            
            // Aggressively clear ALL related transients
            delete_transient('twintack_shippo_sync_complete');
            delete_transient('twintack_shippo_last_sync');
            delete_site_transient('twintack_shippo_sync_complete');
            delete_site_transient('twintack_shippo_last_sync');
            
            twintack_manual_payments_log("Force Shippo Sync: Cleared all sync transients, forcing new sync");
            
            // Run the sync immediately
            $this->sync_invoiced_orders_with_shippo();
            
            // Also trigger direct Shippo integration for existing orders
            $this->trigger_direct_shippo_integration();
        }
    }
    
    /**
     * Trigger direct Shippo integration by simulating WooCommerce status change hooks
     */
    public function trigger_direct_shippo_integration() {
        global $wpdb;
        
        twintack_manual_payments_log("Direct Shippo Integration: Starting WooCommerce hook simulation for invoiced orders");
        
        // Get all invoiced orders
        $invoiced_orders = $wpdb->get_results("
            SELECT id FROM {$wpdb->prefix}wc_orders 
            WHERE status = 'invoiced' AND type = 'shop_order'
            ORDER BY id DESC
        ");
        
        if (empty($invoiced_orders)) {
            twintack_manual_payments_log("Direct Shippo Integration: No invoiced orders found");
            return;
        }
        
        twintack_manual_payments_log("Direct Shippo Integration: Found " . count($invoiced_orders) . " invoiced orders to trigger WooCommerce hooks");
        
        foreach ($invoiced_orders as $order_data) {
            $order = wc_get_order($order_data->id);
            if (!$order) {
                continue;
            }
            
            $order_id = $order->get_id();
            $current_status = $order->get_status();
            
            twintack_manual_payments_log("Direct Shippo Integration: Processing order #{$order_id} with status '{$current_status}'");
            
            // 1. Ensure order meta is properly set for immediate fulfillment
            $order->update_meta_data('_shippo_fulfillment_status', 'Payment Pending');
            $order->update_meta_data('_shippo_ready_for_fulfillment', 'yes');
            $order->update_meta_data('_shippo_payment_status', 'Payment Pending');
            $order->update_meta_data('_shippo_fulfillment_note', 'Invoice sent - ship immediately despite pending payment');
            $order->update_meta_data('_shippo_sync_timestamp', current_time('timestamp'));
            
            // Remove hold flags to ensure immediate shipment
            $order->delete_meta_data('_shippo_fulfillment_hold');
            $order->delete_meta_data('_shippo_hold_reason');
            $order->save();
            
            // 2. Trigger ONLY the standard WooCommerce hook - no direct calls to prevent duplicates
            // TwinTack_Shippo_Integration will handle the API call via this hook
            do_action('woocommerce_order_status_changed', $order_id, 'pending', 'invoiced', $order);
            twintack_manual_payments_log("Direct Shippo Integration: Triggered woocommerce_order_status_changed hook for order #{$order_id}");
            
            // 5. Add order note for tracking
            $order->add_order_note('Shippo integration: Order manually synced as invoiced (Payment Pending)');
        }
        
        twintack_manual_payments_log("Direct Shippo Integration: Completed WooCommerce hook simulation for " . count($invoiced_orders) . " orders");
    }
    
    /**
     * Add JavaScript to refresh order display after status changes
     */
    public function add_order_display_refresh_script() {
        $screen = get_current_screen();
        
        // Only add script on orders page
        if (!$screen || $screen->id !== 'woocommerce_page_wc-orders') {
            return;
        }
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Auto-refresh order list when returning to "All" tab from other tabs
            var currentURL = window.location.href;
            var isAllView = currentURL.indexOf('status=all') > -1 || (currentURL.indexOf('status=') === -1 && currentURL.indexOf('wc-orders') > -1);
            
            if (isAllView) {
                // Check if we need to refresh (coming from other status views)
                var lastVisitedTab = sessionStorage.getItem('twintack_last_order_tab');
                var currentTab = 'all';
                
                if (lastVisitedTab && lastVisitedTab !== currentTab && lastVisitedTab !== 'undefined') {
                    console.log('TwinTack: Refreshing order list - returned to All view from ' + lastVisitedTab);
                    // Small delay to ensure page is fully loaded
                    setTimeout(function() {
                        window.location.reload();
                    }, 100);
                }
                
                sessionStorage.setItem('twintack_last_order_tab', currentTab);
            } else if (currentURL.indexOf('status=wc-invoiced') > -1 || currentURL.indexOf('status=invoiced') > -1) {
                sessionStorage.setItem('twintack_last_order_tab', 'invoiced');
            } else {
                // Extract status from URL for other tabs
                var statusMatch = currentURL.match(/status=([^&]+)/);
                if (statusMatch) {
                    sessionStorage.setItem('twintack_last_order_tab', statusMatch[1]);
                }
            }
        });
        </script>
        <?php
    }
    
    /**
     * Add email actions for custom order statuses
     * This enables WooCommerce to send customer emails when orders change to custom statuses
     */
    public function add_email_actions_for_custom_statuses($email_actions) {
        // Add email triggers for transitions TO invoiced status
        $custom_email_actions = array(
            'woocommerce_order_status_pending_to_invoiced_notification',
            'woocommerce_order_status_on-hold_to_invoiced_notification',
            'woocommerce_order_status_processing_to_invoiced_notification',
            'woocommerce_order_status_completed_to_invoiced_notification',
            'woocommerce_order_status_cancelled_to_invoiced_notification',
            'woocommerce_order_status_refunded_to_invoiced_notification',
            'woocommerce_order_status_failed_to_invoiced_notification',
            
            // Also add transitions FROM invoiced to other statuses
            'woocommerce_order_status_invoiced_to_processing_notification',
            'woocommerce_order_status_invoiced_to_completed_notification',
            'woocommerce_order_status_invoiced_to_cancelled_notification',
            'woocommerce_order_status_invoiced_to_refunded_notification',
        );
        
        // Merge with existing email actions
        return array_merge($email_actions, $custom_email_actions);
    }
} 