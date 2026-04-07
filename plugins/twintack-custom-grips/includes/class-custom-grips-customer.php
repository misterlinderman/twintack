<?php
/**
 * TTCG_Customer — Customer-facing grip design experience.
 *
 * Registers a new WooCommerce My Account endpoint "my-custom-grips"
 * that provides the enhanced customer experience for viewing grip designs,
 * communicating with the art/production team, and managing approvals.
 *
 * This runs alongside the existing "My Grip Designs" from the grip-manager
 * plugin, serving as a testing ground during development before replacing it.
 *
 * @package TwinTack_Custom_Grips
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TTCG_Customer {

    /** @var TTCG_Customer|null */
    private static $instance = null;

    /** @var string  Endpoint slug. */
    const ENDPOINT = 'my-custom-grips';

    /**
     * Get the singleton instance.
     *
     * @return TTCG_Customer
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor — registers hooks.
     */
    private function __construct() {
        // Register endpoint
        add_action( 'init', array( $this, 'register_endpoint' ) );

        // Add menu item to My Account
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ), 25 );

        // Hide legacy "grip-designs" endpoint from the menu (URLs redirect to this endpoint).
        add_filter( 'woocommerce_account_menu_items', array( $this, 'remove_legacy_grip_designs_menu_item' ), 100 );

        // Send old /my-account/grip-designs/ traffic to the canonical customer dashboard.
        add_action( 'template_redirect', array( $this, 'redirect_legacy_grip_designs_endpoint' ), 1 );

        // Render endpoint content
        add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render_endpoint' ) );

        // Enqueue customer assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    // ------------------------------------------------------------------
    // Endpoint Registration
    // ------------------------------------------------------------------

    /**
     * Register the WooCommerce account endpoint.
     */
    public function register_endpoint() {
        add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
    }

    /**
     * Add "My Custom Grips" to the WooCommerce account menu.
     *
     * @param  array $items Menu items.
     * @return array
     */
    public function add_menu_item( $items ) {
        $new_items = array();

        foreach ( $items as $key => $value ) {
            $new_items[ $key ] = $value;

            // Insert after "My Grip Designs" or after "Dashboard"
            if ( 'grip-designs' === $key || ( ! isset( $items['grip-designs'] ) && 'dashboard' === $key ) ) {
                $new_items[ self::ENDPOINT ] = __( 'My Custom Grips', 'twintack-custom-grips' );
            }
        }

        return $new_items;
    }

    /**
     * Remove the legacy WooCommerce menu item added by the theme / grip-manager plugin.
     *
     * @param array $items Account menu items.
     * @return array
     */
    public function remove_legacy_grip_designs_menu_item( $items ) {
        unset( $items['grip-designs'] );
        return $items;
    }

    /**
     * 301 redirect legacy grip-designs endpoint to my-custom-grips (preserve grip_id query arg).
     */
    public function redirect_legacy_grip_designs_endpoint() {
        if ( ! is_user_logged_in() || ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
            return;
        }
        if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'grip-designs' ) ) {
            return;
        }

        $target = self::get_list_url();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- query arg for public redirect only
        if ( isset( $_GET['grip_id'] ) ) {
            $target = add_query_arg( 'grip_id', absint( wp_unslash( $_GET['grip_id'] ) ), $target );
        }

        wp_safe_redirect( $target, 301 );
        exit;
    }

    /**
     * Enqueue assets on the My Account custom grips page.
     */
    public function enqueue_assets() {
        if ( ! is_account_page() ) {
            return;
        }

        global $wp_query;
        if ( ! isset( $wp_query->query_vars[ self::ENDPOINT ] ) ) {
            return;
        }

        wp_enqueue_style(
            'ttcg-customer-grips',
            TTCG_PLUGIN_URL . 'assets/css/customer-grips.css',
            array(),
            TTCG_VERSION
        );

        wp_enqueue_script(
            'ttcg-customer-grips',
            TTCG_PLUGIN_URL . 'assets/js/customer-grips.js',
            array( 'jquery' ),
            TTCG_VERSION,
            true
        );

        wp_localize_script( 'ttcg-customer-grips', 'ttcg_customer', array(
            'ajax_url'  => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'ttcg_customer_nonce' ),
            'user_id'   => get_current_user_id(),
            'endpoint'  => self::ENDPOINT,
            'strings'   => array(
                'sending'    => __( 'Sending…', 'twintack-custom-grips' ),
                'sent'       => __( 'Message sent!', 'twintack-custom-grips' ),
                'error'      => __( 'An error occurred. Please try again.', 'twintack-custom-grips' ),
                'loading'    => __( 'Loading…', 'twintack-custom-grips' ),
                'approve_confirm' => __( 'Are you sure you want to approve this design?', 'twintack-custom-grips' ),
            ),
        ) );
    }

    // ------------------------------------------------------------------
    // Endpoint Rendering
    // ------------------------------------------------------------------

    /**
     * Render the endpoint content.
     */
    public function render_endpoint() {
        $grip_id = isset( $_GET['grip_id'] ) ? absint( $_GET['grip_id'] ) : 0;

        if ( $grip_id > 0 ) {
            $this->render_single_design( $grip_id );
        } else {
            $this->render_design_list();
        }
    }

    /**
     * Render the list of customer grip designs.
     */
    private function render_design_list() {
        $current_user = wp_get_current_user();
        $designs      = $this->get_customer_designs( $current_user );

        $template = TTCG_PLUGIN_DIR . 'templates/customer/design-list.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
    }

    /**
     * Render a single grip design detail view.
     *
     * @param int $grip_id Grip design post ID.
     */
    private function render_single_design( $grip_id ) {
        $post = get_post( $grip_id );

        if ( ! $post || 'grip_design' !== $post->post_type ) {
            echo '<div class="ttcg-customer-notice ttcg-customer-notice--error">';
            echo '<p>' . esc_html__( 'Grip design not found.', 'twintack-custom-grips' ) . '</p>';
            echo '</div>';
            echo '<p><a href="' . esc_url( wc_get_account_endpoint_url( self::ENDPOINT ) ) . '" class="ttcg-btn ttcg-btn--secondary">&larr; ' . esc_html__( 'Back to My Custom Grips', 'twintack-custom-grips' ) . '</a></p>';
            return;
        }

        // Verify ownership — email must match for ALL users (including admins).
        // Admins can view all designs from the Grip Design Dashboard instead.
        $customer_email = get_post_meta( $grip_id, '_grip_customer_email', true );
        $current_user   = wp_get_current_user();

        if ( $customer_email !== $current_user->user_email ) {
            echo '<div class="ttcg-customer-notice ttcg-customer-notice--error">';
            echo '<p>' . esc_html__( 'You do not have permission to view this design.', 'twintack-custom-grips' ) . '</p>';
            echo '</div>';
            echo '<p><a href="' . esc_url( wc_get_account_endpoint_url( self::ENDPOINT ) ) . '" class="ttcg-btn ttcg-btn--secondary">&larr; ' . esc_html__( 'Back to My Custom Grips', 'twintack-custom-grips' ) . '</a></p>';
            return;
        }

        $data     = TTCG_Dashboard::get_design_data( $grip_id );
        $messages = $this->get_customer_messages( $grip_id );

        $template = TTCG_PLUGIN_DIR . 'templates/customer/design-detail.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
    }

    // ------------------------------------------------------------------
    // Data Helpers
    // ------------------------------------------------------------------

    /**
     * Get grip designs belonging to the current customer.
     *
     * Uses ONLY email matching — the customer's email must match the
     * _grip_customer_email meta value on the grip design.  This applies
     * to all users, including administrators.  Admins/authors who create
     * designs on behalf of customers will NOT see those designs here;
     * they should use the Grip Design Dashboard for that.
     *
     * @param  WP_User $user Current user object.
     * @return array   Array of design data arrays.
     */
    private function get_customer_designs( $user ) {
        $query = new WP_Query( array(
            'post_type'      => 'grip_design',
            'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'   => '_grip_customer_email',
                    'value' => $user->user_email,
                ),
            ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        return $this->posts_to_design_data( $query->posts );
    }

    /**
     * Convert an array of WP_Post objects to design data arrays.
     *
     * @param  array $posts
     * @return array
     */
    private function posts_to_design_data( $posts ) {
        $designs = array();
        foreach ( $posts as $post ) {
            $designs[] = TTCG_Dashboard::get_design_data( $post->ID );
        }
        return $designs;
    }

    /**
     * Get messages for a grip design filtered for customer view.
     *
     * Uses the existing TTCG_Messaging::get_messages() which queries
     * by the correct post type (grip_message) and meta key
     * (_ttcg_grip_design_id), then reformats for the customer template.
     *
     * @param  int $grip_id Grip design post ID.
     * @return array Array of message data.
     */
    private function get_customer_messages( $grip_id ) {
        if ( ! class_exists( 'TTCG_Messaging' ) ) {
            return array();
        }

        // Use the canonical message query (customer_view = true to filter internals)
        $raw_messages = TTCG_Messaging::get_messages( $grip_id, true );
        $current_user = wp_get_current_user();
        $formatted    = array();

        foreach ( $raw_messages as $msg ) {
            // Determine if this message was sent by the current customer
            $is_customer = ( (int) $msg['sender_id'] === $current_user->ID );

            // Also treat it as customer's message if the sender role is "customer"
            if ( ! $is_customer && 'customer' === $msg['sender_role'] ) {
                $is_customer = true;
            }

            $formatted[] = array(
                'id'           => $msg['id'],
                'content'      => $msg['content'],
                'date'         => $msg['date_human'],
                'timestamp'    => $msg['date'],
                'sender_name'  => $msg['sender_name'],
                'sender_role'  => $msg['sender_role'],
                'is_customer'  => $is_customer,
                'is_system'    => 'system' === $msg['type'],
                'type'         => $msg['type'],
            );
        }

        return $formatted;
    }

    // ------------------------------------------------------------------
    // URL Helpers
    // ------------------------------------------------------------------

    /**
     * Get the URL for the customer grips list page.
     *
     * @return string
     */
    public static function get_list_url() {
        return wc_get_account_endpoint_url( self::ENDPOINT );
    }

    /**
     * Get the URL for a single design detail page.
     *
     * @param  int $grip_id Grip design post ID.
     * @return string
     */
    public static function get_detail_url( $grip_id ) {
        return add_query_arg( 'grip_id', $grip_id, wc_get_account_endpoint_url( self::ENDPOINT ) );
    }
}
