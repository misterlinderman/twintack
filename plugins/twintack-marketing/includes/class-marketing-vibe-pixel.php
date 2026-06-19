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

    /** @var array<string,mixed>|null */
    private $purchase_payload = null;

    /** @var bool */
    private $purchase_rendered = false;

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
        add_action('template_redirect', array($this, 'capture_purchase_context'), 20);
        add_action('woocommerce_thankyou', array($this, 'capture_purchase_from_order'), 5);
        add_action('wp_head', array($this, 'render_head'), 0);
        add_action('wp_footer', array($this, 'maybe_render_purchase_event'), 20);
    }

    /**
     * Capture purchase context from the order-received endpoint after query setup.
     */
    public function capture_purchase_context() {
        if ($this->should_skip() || null !== $this->purchase_payload) {
            return;
        }

        $order = $this->get_valid_thankyou_order();
        if (!$order) {
            return;
        }

        $this->purchase_payload = $this->build_purchase_payload_from_order($order);
    }

    /**
     * Capture purchase context from the checkout thank-you template.
     *
     * @param int $order_id Order ID.
     */
    public function capture_purchase_from_order($order_id) {
        if ($this->should_skip() || null !== $this->purchase_payload) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $this->purchase_payload = $this->build_purchase_payload_from_order($order);
    }

    /**
     * Output Vibe pixel loader and page_view event.
     */
    public function render_head() {
        if ($this->should_skip()) {
            return;
        }

        $pixel_id = apply_filters('twintack_vibe_pixel_id', self::PIXEL_ID);
        if ('' === $pixel_id) {
            return;
        }

        ?>
        <script>
        !function(v,i,b,e,c,o){if(!v[c]){var s=v[c]=function(){s.process?s.process.apply(s,arguments):s.queue.push(arguments)};s.queue=[],s.b=1*new Date;var t=i.createElement(b);t.async=!0,t.src=e;var n=i.getElementsByTagName(b)[0];n.parentNode.insertBefore(t,n)}}(window,document,"script","<?php echo esc_url(self::SCRIPT_URL); ?>","vbpx");
        vbpx('init','<?php echo esc_js($pixel_id); ?>');
        vbpx('event', 'page_view');
        </script>
        <?php

        $this->maybe_render_purchase_event();
    }

    /**
     * Output purchase conversion event once per validated order.
     */
    public function maybe_render_purchase_event() {
        if ($this->purchase_rendered || null === $this->purchase_payload) {
            return;
        }

        $purchase_event = array(
            'price_usd' => $this->purchase_payload['price_usd'],
        );

        ?>
        <script>
        if (typeof vbpx === 'function') {
            vbpx('event', 'purchase', <?php echo wp_json_encode($purchase_event); ?>);
        }
        </script>
        <?php

        $this->purchase_rendered = true;
        $this->mark_order_tracked((int) $this->purchase_payload['order_id']);
    }

    /**
     * Build purchase payload for validated thank-you orders (USD order total).
     *
     * @param WC_Order $order Order object.
     * @return array<string,mixed>|null
     */
    private function build_purchase_payload_from_order($order) {
        if ($order->get_meta(self::TRACKED_META)) {
            return null;
        }

        if ('USD' !== strtoupper($order->get_currency())) {
            return null;
        }

        return array(
            'price_usd' => wc_format_decimal($order->get_total(), 2),
            'order_id'  => $order->get_id(),
        );
    }

    /**
     * Validate order on any WooCommerce order-received endpoint.
     *
     * TwinTack routes post-checkout URLs to /my-account/order-received/, so we cannot
     * rely on is_order_received_page() which only matches the checkout page.
     *
     * @return WC_Order|false
     */
    private function get_valid_thankyou_order() {
        if (!function_exists('is_wc_endpoint_url') || !is_wc_endpoint_url('order-received')) {
            return false;
        }

        global $wp;

        $order_id = isset($wp->query_vars['order-received']) ? absint($wp->query_vars['order-received']) : 0;
        if (!$order_id) {
            return false;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $order_key = isset($_GET['key']) ? wc_clean(wp_unslash($_GET['key'])) : '';
        if ('' !== $order_key && hash_equals($order->get_order_key(), $order_key)) {
            return $order;
        }

        if (is_user_logged_in() && (int) $order->get_customer_id() === get_current_user_id()) {
            return $order;
        }

        return false;
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
