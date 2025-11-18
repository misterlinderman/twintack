<?php
/**
 * PDF Invoice Generator
 * 
 * Generates professional PDF invoices for TwinTack orders with PO numbers
 * 
 * @package TwinTack_Manual_Order_Payments
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Define TCPDF constants if not already defined
 */
if (!defined('PDF_PAGE_ORIENTATION')) {
    define('PDF_PAGE_ORIENTATION', 'P');
}
if (!defined('PDF_UNIT')) {
    define('PDF_UNIT', 'mm');
}
if (!defined('PDF_PAGE_FORMAT')) {
    define('PDF_PAGE_FORMAT', 'A4');
}
if (!defined('PDF_MARGIN_LEFT')) {
    define('PDF_MARGIN_LEFT', 15);
}
if (!defined('PDF_MARGIN_TOP')) {
    define('PDF_MARGIN_TOP', 27);
}
if (!defined('PDF_MARGIN_RIGHT')) {
    define('PDF_MARGIN_RIGHT', 15);
}
if (!defined('PDF_MARGIN_BOTTOM')) {
    define('PDF_MARGIN_BOTTOM', 25);
}
if (!defined('PDF_MARGIN_HEADER')) {
    define('PDF_MARGIN_HEADER', 5);
}
if (!defined('PDF_MARGIN_FOOTER')) {
    define('PDF_MARGIN_FOOTER', 10);
}
if (!defined('PDF_FONT_NAME_MAIN')) {
    define('PDF_FONT_NAME_MAIN', 'helvetica');
}
if (!defined('PDF_FONT_SIZE_MAIN')) {
    define('PDF_FONT_SIZE_MAIN', 10);
}
if (!defined('PDF_FONT_NAME_DATA')) {
    define('PDF_FONT_NAME_DATA', 'helvetica');
}
if (!defined('PDF_FONT_SIZE_DATA')) {
    define('PDF_FONT_SIZE_DATA', 8);
}
if (!defined('PDF_FONT_MONOSPACED')) {
    define('PDF_FONT_MONOSPACED', 'courier');
}
if (!defined('PDF_IMAGE_SCALE_RATIO')) {
    define('PDF_IMAGE_SCALE_RATIO', 1.25);
}

/**
 * Include TCPDF library
 */
if (!class_exists('TCPDF')) {
    // Try to load TCPDF from WordPress or plugin directory
    $tcpdf_paths = array(
        ABSPATH . 'wp-content/plugins/woocommerce-pdf-invoices-packing-slips/lib/tcpdf/tcpdf.php',
        TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR . 'vendor/tcpdf/tcpdf.php',
        ABSPATH . 'wp-content/plugins/tcpdf/tcpdf.php'
    );
    
    $tcpdf_loaded = false;
    foreach ($tcpdf_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $tcpdf_loaded = true;
            break;
        }
    }
    
    // Fallback: Use basic TCPDF if available in system
    if (!$tcpdf_loaded && !class_exists('TCPDF')) {
        // Create a simple HTML-to-PDF fallback
        class TCPDF_Fallback {
            public function __construct($orientation = '', $unit = '', $format = '', $unicode = true, $encoding = 'UTF-8', $diskcache = false, $pdfa = false) {}
            public function SetCreator($creator) {}
            public function SetAuthor($author) {}
            public function SetTitle($title) {}
            public function SetSubject($subject) {}
            public function SetHeaderData($ln = '', $lw = 0, $ht = '', $hs = '') {}
            public function setHeaderFont($font) {}
            public function setFooterFont($font) {}
            public function SetDefaultMonospacedFont($font) {}
            public function SetMargins($left, $top, $right) {}
            public function SetHeaderMargin($hm) {}
            public function SetFooterMargin($fm) {}
            public function SetAutoPageBreak($auto, $margin) {}
            public function setImageScale($scale) {}
            public function AddPage() {}
            public function writeHTML($html, $ln = true, $fill = false, $reseth = false, $cell = false, $align = '') {}
            public function Output($name = 'doc.pdf', $dest = 'I') {
                return false; // Indicate fallback mode
            }
        }
        class_alias('TCPDF_Fallback', 'TCPDF');
    }
}

class TwinTack_PDF_Invoice_Generator {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor
    }
    
    /**
     * Generate PDF invoice for an order
     */
    public function generate_invoice($order_id, $download = true) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('invalid_order', 'Order not found');
        }
        
        try {
            // Check if we have real TCPDF available
            if (!$this->is_pdf_available()) {
                twintack_manual_payments_log("TCPDF not available, using HTML fallback for order {$order_id}");
                return $this->generate_html_invoice($order_id, $download);
            }
            
            // Create new PDF document
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator('TwinTack');
            $pdf->SetAuthor(get_bloginfo('name'));
            $pdf->SetTitle('Invoice #' . $order->get_order_number());
            $pdf->SetSubject('Invoice');
            
            // Set default header data
            $pdf->SetHeaderData('', 0, get_bloginfo('name'), 'Invoice #' . $order->get_order_number());
            
            // Set header and footer fonts
            $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
            $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
            
            // Set default monospaced font
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
            
            // Set margins
            $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
            $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
            $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
            
            // Set auto page breaks
            $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
            
            // Set image scale factor
            $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
            
            // Add a page
            $pdf->AddPage();
            
            // Generate invoice HTML content
            $html = $this->generate_invoice_html($order);
            
            // Print text using writeHTMLCell()
            $pdf->writeHTML($html, true, false, true, false, '');
            
            // Generate filename
            $filename = 'invoice-' . $order->get_order_number() . '.pdf';
            
            if ($download) {
                // Force download
                $pdf->Output($filename, 'D');
                exit;
            } else {
                // Return PDF content
                return $pdf->Output($filename, 'S');
            }
            
        } catch (Exception $e) {
            twintack_manual_payments_log("Error generating PDF invoice: " . $e->getMessage(), 'error');
            return new WP_Error('pdf_generation_error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate HTML invoice (fallback when TCPDF not available)
     */
    public function generate_html_invoice($order_id, $download = true) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('invalid_order', 'Order not found');
        }
        
        $html = $this->generate_invoice_html($order);
        
        // Wrap in full HTML document for printing
        $full_html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice #' . $order->get_order_number() . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .invoice-container { max-width: 800px; margin: 0 auto; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        ' . $html . '
        <div class="no-print" style="margin-top: 30px; text-align: center;">
            <button onclick="window.print()" style="background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer;">Print Invoice</button>
        </div>
    </div>
</body>
</html>';
        
        if ($download) {
            // Set headers for HTML display
            header('Content-Type: text/html; charset=utf-8');
            header('Content-Disposition: inline; filename="invoice-' . $order->get_order_number() . '.html"');
            
            // Output HTML for browser display/printing
            echo $full_html;
            exit;
        } else {
            return $full_html;
        }
    }
    
    /**
     * Generate invoice HTML content
     */
    private function generate_invoice_html($order) {
        $po_number = $order->get_meta('_twintack_po_number');
        $order_date = $order->get_date_created();
        $billing_address = $order->get_formatted_billing_address();
        
        // Start building HTML
        $html = '<div style="font-family: Arial, sans-serif; color: #333;">';
        
        // Header section
        $html .= '<table style="width: 100%; margin-bottom: 30px;">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <h1 style="color: #0073aa; margin: 0; font-size: 28px;">' . get_bloginfo('name') . '</h1>
                    <p style="margin: 5px 0; color: #666;">
                        ' . get_bloginfo('description') . '<br>
                        ' . get_option('admin_email') . '
                    </p>
                </td>
                <td style="width: 50%; text-align: right; vertical-align: top;">
                    <h2 style="color: #333; margin: 0; font-size: 24px;">INVOICE</h2>
                    <p style="margin: 5px 0;">
                        <strong>Invoice #:</strong> ' . $order->get_order_number() . '<br>
                        <strong>Date:</strong> ' . $order_date->format('F j, Y') . '<br>
                        <strong>Order Status:</strong> ' . ucfirst($order->get_status()) . '
                    </p>
                </td>
            </tr>
        </table>';
        
        // PO Number section (if available)
        if ($po_number) {
            $html .= '<div style="background: #f8f9fa; padding: 15px; border: 2px solid #0073aa; border-radius: 5px; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #0073aa;">Purchase Order Information</h3>
                <p style="margin: 10px 0 0 0; font-size: 16px;"><strong>PO Number:</strong> ' . esc_html($po_number) . '</p>
            </div>';
        }
        
        // Bill To section
        $html .= '<table style="width: 100%; margin-bottom: 30px;">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <h3 style="color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 5px;">Bill To:</h3>
                    <div style="margin-top: 15px;">';
        
        if ($billing_address) {
            $html .= $billing_address;
        } else {
            $html .= '<p><strong>' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . '</strong><br>';
            if ($order->get_billing_email()) {
                $html .= 'Email: ' . $order->get_billing_email() . '<br>';
            }
            if ($order->get_billing_phone()) {
                $html .= 'Phone: ' . $order->get_billing_phone();
            }
            $html .= '</p>';
        }
        
        $html .= '</div>
                </td>
                <td style="width: 50%; vertical-align: top;">
                    <h3 style="color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 5px;">Payment Details:</h3>
                    <div style="margin-top: 15px;">
                        <p>
                            <strong>Payment Method:</strong> ' . ($order->get_payment_method_title() ?: 'Not specified') . '<br>
                            <strong>Total Amount:</strong> <span style="color: #d63638; font-size: 18px; font-weight: bold;">' . wc_price($order->get_total()) . '</span>
                        </p>
                    </div>
                </td>
            </tr>
        </table>';
        
        // Order items table
        $html .= '<h3 style="color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 5px;">Order Items:</h3>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">Item</th>
                    <th style="border: 1px solid #ddd; padding: 12px; text-align: center;">Qty</th>
                    <th style="border: 1px solid #ddd; padding: 12px; text-align: right;">Unit Price</th>
                    <th style="border: 1px solid #ddd; padding: 12px; text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>';
        
        // Get wholesale pricing helper
        $bulk_manager = null;
        if (class_exists('TwinTack_Bulk_Invoice_Manager')) {
            $bulk_manager = TwinTack_Bulk_Invoice_Manager::get_instance();
            twintack_manual_payments_log("PDF Invoice: Bulk manager instance created for order {$order->get_id()}");
        } else {
            twintack_manual_payments_log("PDF Invoice: TwinTack_Bulk_Invoice_Manager class not found for order {$order->get_id()}");
        }
        
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $item_name = $item->get_name();
            $quantity = $item->get_quantity();
            
            // Get pricing (wholesale if applicable)
            if ($bulk_manager) {
                $pricing = $bulk_manager->get_wholesale_item_pricing($order, $item);
                $unit_price = $pricing['unit_price'];
                $total_price = $pricing['line_total'];
                twintack_manual_payments_log("PDF Invoice: Using wholesale pricing for {$item_name} - Unit: $" . $unit_price . ", Total: $" . $total_price . ", Is Wholesale: " . ($pricing['is_wholesale'] ? 'Yes' : 'No'));
            } else {
                // Fallback: Try to calculate wholesale pricing directly
                $customer_id = $order->get_customer_id();
                $is_wholesale_customer = false;
                
                if ($customer_id) {
                    $user = get_user_by('id', $customer_id);
                    if ($user) {
                        // Use WooCommerce Wholesale Prices plugin method to detect wholesale roles
                        if (class_exists('WWP_Wholesale_Roles')) {
                            $wholesale_roles = WWP_Wholesale_Roles::getInstance()->getUserWholesaleRole($user);
                            if (!empty($wholesale_roles) && is_array($wholesale_roles)) {
                                $is_wholesale_customer = true;
                                twintack_manual_payments_log("PDF Invoice: Detected wholesale role '{$wholesale_roles[0]}' for customer {$customer_id}");
                            }
                        } else {
                            // Fallback to manual role detection
                            $user_roles = $user->roles;
                            foreach ($user_roles as $role) {
                                if (strpos($role, 'wholesale') !== false || strpos($role, 'drop_ship') !== false) {
                                    $is_wholesale_customer = true;
                                    break;
                                }
                            }
                        }
                    }
                }
                
                if ($is_wholesale_customer) {
                    // Calculate wholesale price based on order total
                    $order_total = $order->get_total();
                    $shipping_total = $order->get_shipping_total();
                    $tax_total = $order->get_total_tax();
                    $actual_subtotal = $order_total - $shipping_total - $tax_total;
                    $calculated_unit_price = $quantity > 0 ? ($actual_subtotal / $quantity) : 0;
                    
                    if ($calculated_unit_price > 0) {
                        $unit_price = $calculated_unit_price;
                        $total_price = $calculated_unit_price * $quantity;
                        twintack_manual_payments_log("PDF Invoice: Using direct wholesale calculation for {$item_name} - Unit: $" . $unit_price . ", Total: $" . $total_price);
                    } else {
                        $unit_price = $order->get_item_subtotal($item, false, true);
                        $total_price = $order->get_line_subtotal($item, false, true);
                        twintack_manual_payments_log("PDF Invoice: Using original pricing for {$item_name} - Unit: $" . $unit_price . ", Total: $" . $total_price);
                    }
                } else {
                    // Regular customer - use original pricing
                    $unit_price = $order->get_item_subtotal($item, false, true);
                    $total_price = $order->get_line_subtotal($item, false, true);
                    twintack_manual_payments_log("PDF Invoice: Using original pricing for {$item_name} - Unit: $" . $unit_price . ", Total: $" . $total_price);
                }
            }
            
            $html .= '<tr>
                <td style="border: 1px solid #ddd; padding: 12px;">
                    <strong>' . $item_name . '</strong>';
            
            // Add wholesale indicator if applicable
            if ($bulk_manager) {
                $pricing = $bulk_manager->get_wholesale_item_pricing($order, $item);
                if ($pricing['is_wholesale']) {
                    $html .= ' <span style="background: #0073aa; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; margin-left: 5px;">WHOLESALE</span>';
                }
            } else {
                // Check if this is a wholesale customer for direct calculation
                $customer_id = $order->get_customer_id();
                if ($customer_id) {
                    $user = get_user_by('id', $customer_id);
                    if ($user) {
                        // Use WooCommerce Wholesale Prices plugin method to detect wholesale roles
                        if (class_exists('WWP_Wholesale_Roles')) {
                            $wholesale_roles = WWP_Wholesale_Roles::getInstance()->getUserWholesaleRole($user);
                            if (!empty($wholesale_roles) && is_array($wholesale_roles)) {
                                $html .= ' <span style="background: #0073aa; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; margin-left: 5px;">WHOLESALE</span>';
                            }
                        } else {
                            // Fallback to manual role detection
                            $user_roles = $user->roles;
                            foreach ($user_roles as $role) {
                                if (strpos($role, 'wholesale') !== false || strpos($role, 'drop_ship') !== false) {
                                    $html .= ' <span style="background: #0073aa; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; margin-left: 5px;">WHOLESALE</span>';
                                    break;
                                }
                            }
                        }
                    }
                }
            }
            
            // Add product meta if available
            $meta_data = $item->get_meta_data();
            if (!empty($meta_data)) {
                $html .= '<br><small style="color: #666;">';
                foreach ($meta_data as $meta) {
                    if (!in_array($meta->key, array('_qty', '_product_id', '_variation_id'))) {
                        $html .= $meta->display_key . ': ' . $meta->display_value . '<br>';
                    }
                }
                $html .= '</small>';
            }
            
            $html .= '</td>
                <td style="border: 1px solid #ddd; padding: 12px; text-align: center;">' . $quantity . '</td>
                <td style="border: 1px solid #ddd; padding: 12px; text-align: right;">' . wc_price($unit_price) . '</td>
                <td style="border: 1px solid #ddd; padding: 12px; text-align: right;"><strong>' . wc_price($total_price) . '</strong></td>
            </tr>';
        }
        
        $html .= '</tbody>
        </table>';
        
        // Calculate wholesale subtotal based on actual line items
        $wholesale_subtotal = 0;
        foreach ($order->get_items() as $item_id => $item) {
            if ($bulk_manager) {
                $pricing = $bulk_manager->get_wholesale_item_pricing($order, $item);
                $wholesale_subtotal += $pricing['line_total'];
            } else {
                // Fallback: calculate based on order total
                $order_total = $order->get_total();
                $shipping_total = $order->get_shipping_total();
                $tax_total = $order->get_total_tax();
                $actual_subtotal = $order_total - $shipping_total - $tax_total;
                $wholesale_subtotal = $actual_subtotal;
                break; // Only need to calculate once for the total
            }
        }
        
        // Order totals
        $html .= '<div style="float: right; width: 300px; margin-bottom: 30px;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="border-bottom: 1px solid #ddd; padding: 8px; text-align: left;"><strong>Subtotal:</strong></td>
                    <td style="border-bottom: 1px solid #ddd; padding: 8px; text-align: right;">' . wc_price($wholesale_subtotal) . '</td>
                </tr>';
        
        // Add tax if present
        if ($order->get_total_tax() > 0) {
            $html .= '<tr>
                <td style="border-bottom: 1px solid #ddd; padding: 8px; text-align: left;"><strong>Tax:</strong></td>
                <td style="border-bottom: 1px solid #ddd; padding: 8px; text-align: right;">' . wc_price($order->get_total_tax()) . '</td>
            </tr>';
        }
        
        // Add shipping if present
        if ($order->get_shipping_total() > 0) {
            $html .= '<tr>
                <td style="border-bottom: 1px solid #ddd; padding: 8px; text-align: left;"><strong>Shipping:</strong></td>
                <td style="border-bottom: 1px solid #ddd; padding: 8px; text-align: right;">' . wc_price($order->get_shipping_total()) . '</td>
            </tr>';
        }
        
        $html .= '<tr style="background: #f8f9fa;">
                    <td style="border: 2px solid #0073aa; padding: 12px; text-align: left; font-size: 16px;"><strong>TOTAL:</strong></td>
                    <td style="border: 2px solid #0073aa; padding: 12px; text-align: right; font-size: 16px; color: #d63638;"><strong>' . wc_price($order->get_total()) . '</strong></td>
                </tr>
            </table>
        </div>
        <div style="clear: both;"></div>';
        
        // Order notes (if any)
        $customer_notes = $order->get_customer_note();
        if ($customer_notes) {
            $html .= '<h3 style="color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 5px;">Order Notes:</h3>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <p style="margin: 0;">' . nl2br(esc_html($customer_notes)) . '</p>
            </div>';
        }
        
        // Footer
        $html .= '<div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666;">
            <p>Thank you for your business!</p>
            <p style="font-size: 12px;">
                This invoice was generated on ' . date('F j, Y \a\t g:i A') . '<br>
                For questions about this invoice, please contact us at ' . get_option('admin_email') . '
            </p>
        </div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Check if PDF generation is available
     */
    public function is_pdf_available() {
        // Check if TCPDF class exists and is not our fallback
        if (!class_exists('TCPDF')) {
            return false;
        }
        
        // Try to create a TCPDF instance to verify it works
        try {
            $test_pdf = new TCPDF();
            // If we can call a method without error, it's real TCPDF
            return method_exists($test_pdf, 'SetCreator') && !($test_pdf instanceof TCPDF_Fallback);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get PDF generation status message
     */
    public function get_status_message() {
        if ($this->is_pdf_available()) {
            return 'PDF generation available';
        } else {
            return 'PDF library not found - HTML invoice will be generated instead';
        }
    }
}
