<?php
/**
 * Plugin Name: TwinTack Amazon Tracking Bridge
 * Description: Bridges Shippo tracking data from order notes to WP-Lister Amazon fulfillment feeds.
 * Version: 1.1.0
 * Author: TwinTack
 * Requires Plugins: wp-lister-amazon
 *
 * Problem: Shippo label creation writes tracking numbers ONLY to WooCommerce order notes,
 * not to any meta key. WP-Lister Amazon reads meta keys exclusively.
 *
 * Solution: Two-pronged approach:
 *   1. Primary: Intercept order notes in real-time via woocommerce_order_note_added,
 *      parse the Shippo tracking pattern, and write _wpla_tracking_number immediately
 *      (before WP-Lister's completion handler fires ~1 second later).
 *   2. Fallback: Filter hooks (wpla_custom_tracking_number, wpla_set_tracking_number_for_order)
 *      parse order notes at feed-build time as a safety net.
 */

if (!defined('ABSPATH')) {
    exit;
}

class TwinTack_Amazon_Tracking_Bridge {

    /**
     * Regex for Shippo label creation notes.
     * Matches: "usps Ground Advantage label with tracking number 9200190396055707139969 has been created on Shippo"
     */
    const SHIPPO_NOTE_PATTERN = '/(\w+)\s+.*?label\s+with\s+tracking\s+number\s+([A-Za-z0-9]{10,30})\s+has\s+been\s+created\s+on\s+Shippo/i';

    private static $instance = null;
    private static $processing_note = false;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // PRIMARY: Intercept order notes in real-time to capture Shippo tracking
        add_action('woocommerce_order_note_added', array($this, 'intercept_order_note'), 10, 2);

        // FALLBACK: Supply tracking via WP-Lister filter hooks at feed-build time
        add_filter('wpla_custom_tracking_number', array($this, 'filter_tracking_number'), 10, 2);
        add_filter('wpla_custom_tracking_provider', array($this, 'filter_tracking_provider'), 10, 2);
        add_filter('wpla_set_tracking_number_for_order', array($this, 'filter_tracking_number'), 10, 2);
        add_filter('wpla_set_tracking_service_for_order', array($this, 'filter_tracking_service'), 10, 2);
    }

    /**
     * PRIMARY MECHANISM: Intercept order notes as they are created.
     *
     * When Shippo creates a label, it adds a note like:
     * "usps Ground Advantage label with tracking number 9200190396055707139969 has been created on Shippo"
     *
     * This fires BEFORE the order status changes to Completed (~1 second before WP-Lister hooks),
     * so the meta will be available when WP-Lister builds the fulfillment feed.
     *
     * @param int      $comment_id The comment/note ID.
     * @param WC_Order $order      The order object.
     */
    public function intercept_order_note($comment_id, $order) {
        if (self::$processing_note) {
            return;
        }

        $comment = get_comment($comment_id);
        if (!$comment || empty($comment->comment_content)) {
            return;
        }

        $content = $comment->comment_content;

        if (!preg_match(self::SHIPPO_NOTE_PATTERN, $content, $m)) {
            return;
        }

        $carrier_raw = $m[1];
        $tracking_number = $m[2];
        $carrier_code = $this->map_carrier_to_wpla_code($carrier_raw);
        $order_id = $order->get_id();

        $this->log("Note intercepted for order #{$order_id}: tracking {$tracking_number}, carrier {$carrier_raw} -> {$carrier_code}");

        $existing_tracking = $order->get_meta('_wpla_tracking_number', true);
        if (!empty($existing_tracking)) {
            $this->log("Order #{$order_id} already has _wpla_tracking_number={$existing_tracking}, skipping");
            return;
        }

        $order->update_meta_data('_wpla_tracking_number', $tracking_number);
        $order->update_meta_data('_wpla_tracking_provider', $carrier_code);
        $order->save();

        $this->log("Wrote _wpla_tracking_number={$tracking_number} and _wpla_tracking_provider={$carrier_code} for order #{$order_id}");

        // If the order was already submitted to Amazon without tracking, re-submit the feed
        $this->maybe_retrigger_fulfillment_feed($order, $tracking_number, $carrier_code);
    }

    /**
     * FALLBACK: Supply tracking number from order notes when WP-Lister filters request it.
     *
     * Called by wpla_custom_tracking_number and wpla_set_tracking_number_for_order
     * during feed building if _wpla_tracking_number is still empty.
     */
    public function filter_tracking_number($tracking_number, $post_id) {
        if (!empty($tracking_number)) {
            return $tracking_number;
        }

        $order = wc_get_order($post_id);
        if (!$order) {
            return $tracking_number;
        }

        $note_data = $this->extract_tracking_from_notes($order);
        if (!empty($note_data['number'])) {
            $this->log("Filter fallback: Found tracking {$note_data['number']} from notes for order #{$post_id}");
            return $note_data['number'];
        }

        return $tracking_number;
    }

    /**
     * FALLBACK: Supply carrier provider from order notes.
     *
     * Called by wpla_custom_tracking_provider during feed building.
     */
    public function filter_tracking_provider($provider, $post_id) {
        if (!empty($provider)) {
            return $provider;
        }

        $order = wc_get_order($post_id);
        if (!$order) {
            return $provider;
        }

        $note_data = $this->extract_tracking_from_notes($order);
        if (!empty($note_data['carrier'])) {
            return $this->map_carrier_to_wpla_code($note_data['carrier']);
        }

        return $provider;
    }

    /**
     * FALLBACK: Supply carrier service name for "Other" carrier codes.
     *
     * Called by wpla_set_tracking_service_for_order during feed building.
     */
    public function filter_tracking_service($service, $post_id) {
        if (!empty($service)) {
            return $service;
        }

        $order = wc_get_order($post_id);
        if (!$order) {
            return $service;
        }

        $note_data = $this->extract_tracking_from_notes($order);
        if (!empty($note_data['carrier'])) {
            $normalized = $this->normalize_carrier_label($note_data['carrier']);
            if (!empty($normalized)) {
                return $normalized;
            }
        }

        return $service;
    }

    /**
     * Re-submit fulfillment feed if the order was already marked shipped without tracking.
     *
     * This handles the case where: (a) the order was completed before the label was created,
     * or (b) note interception fires after WP-Lister already submitted the feed.
     */
    private function maybe_retrigger_fulfillment_feed($order, $tracking_number, $carrier_code) {
        $order_id = $order->get_id();

        $amazon_order_id = $order->get_meta('_wpla_amazon_order_id', true);
        if (empty($amazon_order_id)) {
            return;
        }

        $date_shipped = $order->get_meta('_wpla_date_shipped', true);
        if (empty($date_shipped)) {
            return;
        }

        $this->log("Re-trigger: Order #{$order_id} already shipped to Amazon on {$date_shipped} without tracking, re-submitting feed");

        if (!class_exists('WPLA_AmazonFeed')) {
            $this->log("Re-trigger: WPLA_AmazonFeed class not available");
            return;
        }

        $feed = new WPLA_AmazonFeed();
        $feed->updateShipmentFeed($order_id);

        self::$processing_note = true;
        $order->add_order_note(
            sprintf(
                'Amazon tracking updated via TwinTack Bridge: %s (%s) - re-submitted fulfillment feed.',
                $tracking_number,
                $carrier_code
            )
        );
        self::$processing_note = false;

        $this->log("Re-trigger: Fulfillment feed re-submitted for order #{$order_id}");
    }

    /**
     * Parse all order notes for a Shippo tracking number.
     *
     * Used by the fallback filter hooks. Results are cached per order ID.
     */
    private function extract_tracking_from_notes($order) {
        static $cache = array();
        $order_id = $order->get_id();

        if (isset($cache[$order_id])) {
            return $cache[$order_id];
        }

        $result = array('number' => '', 'carrier' => '');
        $notes = wc_get_order_notes(array('order_id' => $order_id, 'limit' => 30));

        foreach ($notes as $note) {
            $content = is_object($note) && isset($note->content) ? $note->content : (string) $note;

            // Primary: Shippo label creation note
            if (preg_match(self::SHIPPO_NOTE_PATTERN, $content, $m)) {
                $result['carrier'] = $m[1];
                $result['number'] = $m[2];
                break;
            }

            // Generic fallback: any note containing "tracking number XXXXX"
            if (preg_match('/tracking\s*(?:number|#|:)\s*[:#-]?\s*([A-Za-z0-9]{10,30})/i', $content, $m)) {
                $result['number'] = $m[1];

                if (stripos($content, 'usps') !== false) {
                    $result['carrier'] = 'usps';
                } elseif (stripos($content, 'fedex') !== false) {
                    $result['carrier'] = 'fedex';
                } elseif (stripos($content, 'dhl') !== false) {
                    $result['carrier'] = 'dhl';
                } elseif (stripos($content, 'ups') !== false && stripos($content, 'usps') === false) {
                    $result['carrier'] = 'ups';
                }
                break;
            }
        }

        $cache[$order_id] = $result;
        return $result;
    }

    /**
     * Map a carrier name/abbreviation to WP-Lister's carrier-code value.
     */
    private function map_carrier_to_wpla_code($carrier) {
        if (empty($carrier)) {
            return get_option('wpla_default_shipping_provider', 'USPS');
        }

        $c = strtolower(trim($carrier));

        $code_map = array(
            'usps'          => 'USPS',
            'ups'           => 'UPS',
            'fedex'         => 'FedEx',
            'dhl'           => 'DHL',
            'canada post'   => 'Canada Post',
            'royal mail'    => 'Royal Mail',
        );

        foreach ($code_map as $key => $value) {
            if (strpos($c, $key) !== false) {
                return $value;
            }
        }

        return 'Other';
    }

    /**
     * Normalize a raw carrier string to a human-readable label.
     */
    private function normalize_carrier_label($carrier) {
        $c = strtolower(trim((string) $carrier));

        $map = array(
            'usps'  => 'USPS',
            'ups'   => 'UPS',
            'fedex' => 'FedEx',
            'dhl'   => 'DHL',
        );

        foreach ($map as $key => $value) {
            if (strpos($c, $key) !== false) {
                return $value;
            }
        }

        return ucwords($carrier);
    }

    /**
     * Log messages for debugging.
     */
    private function log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('TwinTack Amazon Bridge: ' . $message);
        }
    }
}

add_action('plugins_loaded', function () {
    if (class_exists('WPLA_WPLister')) {
        TwinTack_Amazon_Tracking_Bridge::get_instance();
    }
}, 20);
