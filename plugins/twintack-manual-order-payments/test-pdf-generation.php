<?php
/**
 * PDF Generation Test Script
 * 
 * Quick diagnostic tool to test PDF invoice generation
 * Access: /wp-content/plugins/twintack-manual-order-payments/test-pdf-generation.php
 */

// Load WordPress
$wp_load_paths = array(
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../wp-load.php'
);

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('❌ Could not load WordPress. Please access this file through your WordPress admin.');
}

// Security check
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    die('❌ Access denied. Please log in as an administrator.');
}

echo '<h1>🔍 TwinTack PDF Generation Diagnostic</h1>';
echo '<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .warning{color:orange;} .error{color:red;}</style>';

// Test 1: Check if plugin is loaded
echo '<h2>Plugin Status</h2>';
if (class_exists('TwinTack_Manual_Order_Payments')) {
    echo '<p class="success">✅ TwinTack Manual Order Payments plugin is loaded</p>';
} else {
    echo '<p class="error">❌ TwinTack Manual Order Payments plugin is not loaded</p>';
    exit;
}

// Test 2: Check PDF generator class
echo '<h2>PDF Generator Status</h2>';
if (!class_exists('TwinTack_PDF_Invoice_Generator')) {
    require_once __DIR__ . '/includes/class-pdf-invoice-generator.php';
}

if (class_exists('TwinTack_PDF_Invoice_Generator')) {
    echo '<p class="success">✅ PDF Generator class loaded</p>';
    
    $pdf_generator = TwinTack_PDF_Invoice_Generator::get_instance();
    $pdf_available = $pdf_generator->is_pdf_available();
    
    if ($pdf_available) {
        echo '<p class="success">✅ TCPDF library is available - PDF generation enabled</p>';
    } else {
        echo '<p class="warning">⚠️ TCPDF library not found - HTML fallback will be used</p>';
    }
    
    echo '<p><strong>Status Message:</strong> ' . $pdf_generator->get_status_message() . '</p>';
} else {
    echo '<p class="error">❌ PDF Generator class not found</p>';
}

// Test 3: Check TCPDF paths
echo '<h2>TCPDF Library Check</h2>';
$tcpdf_paths = array(
    ABSPATH . 'wp-content/plugins/woocommerce-pdf-invoices-packing-slips/lib/tcpdf/tcpdf.php',
    __DIR__ . '/vendor/tcpdf/tcpdf.php',
    ABSPATH . 'wp-content/plugins/tcpdf/tcpdf.php'
);

foreach ($tcpdf_paths as $path) {
    if (file_exists($path)) {
        echo '<p class="success">✅ Found TCPDF at: ' . $path . '</p>';
    } else {
        echo '<p class="error">❌ Not found: ' . $path . '</p>';
    }
}

if (class_exists('TCPDF')) {
    echo '<p class="success">✅ TCPDF class is loaded</p>';
    
    try {
        $test_pdf = new TCPDF();
        echo '<p class="success">✅ TCPDF instance created successfully</p>';
    } catch (Exception $e) {
        echo '<p class="error">❌ Error creating TCPDF instance: ' . $e->getMessage() . '</p>';
    }
} else {
    echo '<p class="warning">⚠️ TCPDF class not loaded</p>';
}

// Test 4: Find a test order
echo '<h2>Test Order</h2>';
$orders = wc_get_orders(array(
    'limit' => 1,
    'status' => array('completed', 'processing', 'invoiced')
));

if (!empty($orders)) {
    $test_order = $orders[0];
    echo '<p class="success">✅ Found test order #' . $test_order->get_order_number() . '</p>';
    echo '<p><a href="?test_invoice=' . $test_order->get_id() . '" target="_blank">🔗 Generate Test Invoice</a></p>';
} else {
    echo '<p class="warning">⚠️ No suitable test orders found</p>';
}

// Test 5: Generate test invoice if requested
if (isset($_GET['test_invoice']) && !empty($orders)) {
    $order_id = intval($_GET['test_invoice']);
    $order = wc_get_order($order_id);
    
    if ($order) {
        echo '<h2>Generating Test Invoice</h2>';
        echo '<p>Generating invoice for Order #' . $order->get_order_number() . '...</p>';
        
        try {
            $result = $pdf_generator->generate_invoice($order_id, true);
        } catch (Exception $e) {
            echo '<p class="error">❌ Error generating invoice: ' . $e->getMessage() . '</p>';
        }
    }
}

echo '<hr>';
echo '<p><strong>Next Steps:</strong></p>';
echo '<ul>';
echo '<li>If TCPDF is not available, invoices will be generated as printable HTML</li>';
echo '<li>To get PDF functionality, install a plugin that includes TCPDF (like WooCommerce PDF Invoices & Packing Slips)</li>';
echo '<li>HTML invoices work perfectly for printing and saving - they just open in the browser instead of downloading as PDF</li>';
echo '</ul>';

echo '<p><a href="' . admin_url() . '">← Back to WordPress Admin</a></p>';
?>
