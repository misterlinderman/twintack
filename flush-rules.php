<?php
// A temporary file to flush rewrite rules
require_once('./wp-load.php');

// Register the endpoints
function twintack_register_woocommerce_endpoints_temp() {
    // Register standard WooCommerce endpoints
    add_rewrite_endpoint('orders', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('view-order', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('downloads', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('edit-account', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('edit-address', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('payment-methods', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('customer-logout', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('add-payment-method', EP_ROOT | EP_PAGES);
}
twintack_register_woocommerce_endpoints_temp();

// Flush the rules
flush_rewrite_rules();
echo 'Rewrite rules have been flushed!'; 