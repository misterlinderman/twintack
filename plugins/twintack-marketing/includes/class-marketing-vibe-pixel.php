<?php
/**
 * Vibe.co conversion pixel — sitewide page views + WooCommerce purchase events.
 *
 * @package TwinTack_Marketing
 */

if (!defined('ABSPATH')) {
    exit;
}

class TwinTack_Marketing_Vibe_Pixel {
    const PIXEL_ID       = 'QRngFc';
    const TRACKED_META   = '_twintack_vibe_purchase_tracked';
    const SCRIPT_URL     = 'https://s.vibe.co/vbpx.js';

    /** @var self|null */
    private static $instance = null;

    /**
     * @return self
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action('wp_head', array($this, 'render_head'), 0);
    }

    /**
     * Output Vibe pixel loader, page_view, and optional purchase event.
     */
    public function render_head() {
        if ($this->should_skip()) {
            return;
        }

        $pixel_id = apply_filters('twintack_vibe_pixel_id', self::PIXEL_ID);
        if ('' === $pixel_id) {
            return;
        }

        $purchase_payload = $this->get_purchase_payload();
        $purchase_event   = null !== $purchase_payload ? array(
            'price_usd' => $purchase_payload['price_usd'],
        ) : null;

        ?>
        <script>
        !function(v,i,b,e,c,o){if(!v[c]){var s=v[c]=function(){s.process?s.process.apply(s,arguments):s.queue.push(arguments)};s.queue=[],s.b=1*new Date;var t=i.createElement(b);t.async=!0,t.src=e;var n=i.getElementsByTagName(b)[0];n.parentNode.insertBefore(t,n)}}(window,document,"script","<?php echo esc_url(self::SCRIPT_URL); ?>","vbpx");
        vbpx('init','<?php echo esc_js($pixel_id); ?>');
        vbpx('event', 'page_view');
        <?php if (null !== $purchase_event) : ?>
        vbpx('event', 'purchase', <?php echo wp_json_encode($purchase_event); ?>);
        <?php endif; ?>
        </script>
        <?php

        if (null !== $purchase_payload) {
            $this->mark_order_tracked((int) $purchase_payload['order_id']);
        }
    }

    /**
     * Build purchase payload for validated thank-you orders (USD, tax + shipping total).
     *
     * @return array<string,mixed>|null
     */
    private function get_purchase_payload() {
        $order = $this->get_valid_thankyou_order();
        if (!$order) {
            return null;
        }

        if ($order->get_meta(self::TRACKED_META)) {
            return null;
        }

        if ('USD' !== strtoupper($order->get_currency())) {
            return null;
        }

        $price = wc_format_decimal($order->get_total(), 2);

        return array(
            'price_usd' => $price,
            'order_id'  => $order->get_id(),
        );
    }

    /**
     * Validate order on the WooCommerce order-received endpoint.
     *
     * @return WC_Order|false
     */
    private function get_valid_thankyou_order() {
        if (!function_exists('is_order_received_page') || !is_order_received_page()) {
            return false;
        }

        global $wp;

        $order_id = isset($wp->query_vars['order-received']) ? absint($wp->query_vars['order-received']) : 0;
        if (!$order_id) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $order_key = isset($_GET['key']) ? wc_clean(wp_unslash($_GET['key'])) : '';
        if ('' === $order_key) {
            return false;
        }

        $order = wc_get_order($order_id);
        if (!$order || !hash_equals($order->get_order_key(), $order_key)) {
            return false;
        }

        return $order;
    }

    /**
     * Prevent duplicate purchase events on thank-you page refresh.
     *
     * @param int $order_id Order ID.
     */
    private function mark_order_tracked($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $order->update_meta_data(self::TRACKED_META, '1');
        $order->save();
    }

    /**
     * Skip tracking on pages that intentionally strip marketing scripts.
     *
     * @return bool
     */
    private function should_skip() {
        if (is_admin()) {
            return true;
        }

        if (is_page_template('templates/template-gripform-clean.php')) {
            return true;
        }

        return (bool) apply_filters('twintack_vibe_pixel_disabled', false);
    }
}
