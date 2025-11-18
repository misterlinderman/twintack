<?php
/**
 * TwinTack Shippo Tracking Display
 *
 * Adds tracking information to the customer's My Account order view and to
 * WooCommerce order emails when a Shippo tracking number is available.
 *
 * @package TwinTack_Manual_Order_Payments
 */

if (!defined('ABSPATH')) {
    exit;
}

class TwinTack_Shippo_Tracking_Display {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Frontend: after the order details table on the My Account → View order page
        add_action('woocommerce_order_details_after_order_table', array($this, 'render_tracking_block'));

        // Emails: after the order table in customer/admin emails
        add_action('woocommerce_email_after_order_table', array($this, 'render_tracking_block_in_email'), 10, 4);
    }

    /**
     * Render tracking information on the order view screen.
     */
    public function render_tracking_block($order) {
        if (!$order instanceof WC_Order) {
            return;
        }

        $tracking = $this->extract_tracking($order);
        if (empty($tracking['number'])) {
            return;
        }

        $tracking_number = $tracking['number'];
        $tracking_status = $tracking['status'];
        $tracking_url    = $tracking['url'];
        $carrier_label   = $tracking['carrier_label'];

        echo '<section class="twintack-tracking">';
        echo '<h2>' . esc_html__('Shipment tracking', 'twintack-manual-payments') . '</h2>';
        echo '<p>';
        echo '<strong>' . esc_html__('Tracking number:', 'twintack-manual-payments') . '</strong> ' . esc_html($tracking_number) . ' ';
        echo '<a target="_blank" rel="noopener" href="' . esc_url($tracking_url) . '">';
        echo esc_html($carrier_label ? sprintf(__('Track with %s', 'twintack-manual-payments'), $carrier_label) : __('Track package', 'twintack-manual-payments'));
        echo '</a>';
        if (!empty($tracking_status)) {
            echo '<br/><small>' . esc_html__('Latest status:', 'twintack-manual-payments') . ' ' . esc_html(is_array($tracking_status) ? wp_json_encode($tracking_status) : $tracking_status) . '</small>';
        }
        echo '</p>';
        echo '</section>';
    }

    /**
     * Render tracking information in order emails.
     *
     * @param WC_Order $order
     * @param bool     $sent_to_admin
     * @param bool     $plain_text
     * @param WC_Email $email
     */
    public function render_tracking_block_in_email($order, $sent_to_admin, $plain_text, $email) {
        if (!$order instanceof WC_Order) {
            return;
        }

        $tracking = $this->extract_tracking($order);
        if (empty($tracking['number'])) {
            return;
        }

        $tracking_number = $tracking['number'];
        $tracking_url    = $tracking['url'];
        $carrier_label   = $tracking['carrier_label'];

        if ($plain_text) {
            echo "\n" . __('Shipment tracking', 'twintack-manual-payments') . ":\n";
            echo __('Tracking number', 'twintack-manual-payments') . ': ' . $tracking_number . "\n";
            echo ($carrier_label ? sprintf(__('Track with %s', 'twintack-manual-payments'), $carrier_label) : __('Track package', 'twintack-manual-payments')) . ': ' . $tracking_url . "\n";
            return;
        }

        echo '<div class="twintack-tracking" style="margin-top:16px">';
        echo '<p><strong>' . esc_html__('Shipment tracking', 'twintack-manual-payments') . '</strong><br/>';
        echo esc_html__('Tracking number', 'twintack-manual-payments') . ': ' . esc_html($tracking_number) . ' — ';
        echo '<a target="_blank" rel="noopener" href="' . esc_url($tracking_url) . '">';
        echo esc_html($carrier_label ? sprintf(__('Track with %s', 'twintack-manual-payments'), $carrier_label) : __('Track package', 'twintack-manual-payments'));
        echo '</a>';
        echo '</p></div>';
    }

    /**
     * Extract tracking number, status and url from multiple possible sources.
     *
     * This supports:
     * - _shippo_tracking_number (our integration)
     * - _wc_shipment_tracking_items (popular Shipment Tracking plugins)
     * - Parsing order notes that contain a phrase like "tracking number 123..."
     */
    private function extract_tracking(WC_Order $order) {
        $number = $order->get_meta('_shippo_tracking_number');
        $status = $order->get_meta('_shippo_tracking_status');
        $carrier = $order->get_meta('_shippo_tracking_carrier');

        if (empty($number)) {
            // Try WooCommerce Shipment Tracking items structure
            $items = $order->get_meta('_wc_shipment_tracking_items');
            if (!empty($items) && is_array($items)) {
                $first = reset($items);
                if (!empty($first['tracking_number'])) {
                    $number = $first['tracking_number'];
                }
                if (!empty($first['status'])) {
                    $status = $first['status'];
                }
                if (!empty($first['tracking_url'])) {
                    $url = $first['tracking_url'];
                }
                if (!empty($first['tracking_provider'])) {
                    $carrier = $first['tracking_provider'];
                }
            }
        }

        // Fallback: parse order notes (also try to infer carrier)
        if (empty($number)) {
            $notes = wc_get_order_notes(array('order_id' => $order->get_id()));
            foreach ($notes as $note) {
                $content = is_object($note) && isset($note->content) ? $note->content : (string) $note;
                if (preg_match('/tracking\s*number\s*[:#-]?\s*([A-Za-z0-9]+)/i', $content, $m)) {
                    $number = $m[1];
                    break;
                }
                if (empty($carrier)) {
                    if (stripos($content, 'usps') !== false) { $carrier = 'usps'; }
                    elseif (stripos($content, 'ups') !== false) { $carrier = 'ups'; }
                    elseif (stripos($content, 'fedex') !== false) { $carrier = 'fedex'; }
                    elseif (stripos($content, 'dhl') !== false) { $carrier = 'dhl'; }
                }
            }
        }

        // Guess carrier by pattern if still unknown
        if (empty($carrier) && !empty($number)) {
            $carrier = $this->guess_carrier_from_number($number);
        }

        // Compute a carrier URL if we know the carrier (fallback to universal tracker)
        $url = isset($url) && !empty($url) ? $url : $this->build_carrier_url($carrier, $number);

        $carrier_label = $this->normalize_carrier_label($carrier);

        return array(
            'number' => $number,
            'status' => $status,
            'url'    => $url,
            'carrier_label' => $carrier_label,
        );
    }

    private function build_carrier_url($carrier, $number) {
        if (empty($number)) {
            return '';
        }
        $c = strtolower(trim((string) $carrier));
        switch ($c) {
            case 'usps':
            case 'us postal service':
                return 'https://tools.usps.com/go/TrackConfirmAction?tLabels=' . rawurlencode($number);
            case 'ups':
                return 'https://www.ups.com/track?loc=en_US&tracknum=' . rawurlencode($number);
            case 'fedex':
                return 'https://www.fedex.com/fedextrack/?trknbr=' . rawurlencode($number);
            case 'dhl':
            case 'dhl express':
                return 'https://www.dhl.com/us-en/home/tracking.html?tracking-id=' . rawurlencode($number);
            default:
                // Fallback to a universal public tracker (no login required)
                return 'https://t.17track.net/en#nums=' . rawurlencode($number);
        }
    }

    private function normalize_carrier_label($carrier) {
        if (empty($carrier)) {
            return '';
        }
        $c = strtolower(trim((string) $carrier));
        if ($c === 'usps' || $c === 'us postal service') return 'USPS';
        if ($c === 'ups') return 'UPS';
        if ($c === 'fedex') return 'FedEx';
        if ($c === 'dhl' || $c === 'dhl express') return 'DHL';
        return ucwords($c);
    }

    private function guess_carrier_from_number($number) {
        $n = strtoupper(trim($number));
        if (preg_match('/^1Z[0-9A-Z]{16}$/', $n)) {
            return 'ups';
        }
        if (preg_match('/^[0-9]{22}$/', $n) && preg_match('/^(92|93|94|95)/', $n)) {
            return 'usps';
        }
        if (preg_match('/^[0-9]{10}$/', $n)) {
            return 'dhl';
        }
        if (preg_match('/^[0-9]{12,14}$/', $n)) {
            return 'fedex';
        }
        return '';
    }
}


