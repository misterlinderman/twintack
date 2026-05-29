<?php
/**
 * TTCG_Status — Artwork status management with permission matrix.
 *
 * Controls which roles can set which statuses and creates
 * audit-trail system messages on every change.
 *
 * @package TwinTack_Custom_Grips
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TTCG_Status {

    /** @var TTCG_Status|null */
    private static $instance = null;

    /**
     * When true, {@see TTCG_Notifications::on_status_changed} skips the customer email
     * (used when a mockup upload sends the dedicated mockup-ready message instead).
     *
     * @var bool
     */
    private static $suppress_customer_status_email = false;

    /**
     * Statuses the Art Team role can set.
     *
     * @var array
     */
    private static $art_team_allowed = array(
        'pending_review',
    );

    /**
     * Statuses the Production Team role can set (all statuses).
     *
     * @var array
     */
    private static $production_team_allowed = array(
        'artwork_pending',
        'pending_review',
        'customer_requested_changes',
        'customer_approved',
        'in_production',
        'shipped',
    );

    /**
     * Get the singleton instance.
     *
     * @return TTCG_Status
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        // Status change hooks handled via AJAX class
    }

    // ------------------------------------------------------------------
    // Permission Checks
    // ------------------------------------------------------------------

    /**
     * Get the statuses the current user is allowed to set.
     *
     * Administrators get full access (same as production team).
     *
     * @param  int|null $user_id
     * @return array    Allowed status slugs.
     */
    public static function get_allowed_statuses( $user_id = null ) {
        if ( TTCG_Roles::user_is_admin( $user_id ) ) {
            return self::$production_team_allowed;
        }
        if ( TTCG_Roles::user_is_production_team( $user_id ) ) {
            return self::$production_team_allowed;
        }
        if ( TTCG_Roles::user_is_art_team( $user_id ) ) {
            return self::$art_team_allowed;
        }
        return array();
    }

    /**
     * Can the current user change a design to the given status?
     *
     * @param  string   $status
     * @param  int|null $user_id
     * @return bool
     */
    public static function can_set_status( $status, $user_id = null ) {
        return in_array( $status, self::get_allowed_statuses( $user_id ), true );
    }

    // ------------------------------------------------------------------
    // Status Update
    // ------------------------------------------------------------------

    /**
     * Update the artwork status of a grip design.
     *
     * Validates permissions, updates meta, logs a system message,
     * and fires the notification hook.
     *
     * @param  int    $design_id Post ID of the grip design.
     * @param  string $new_status New artwork status slug.
     * @param  int|null $user_id  User performing the change. Defaults to current.
     * @param  array  $options {
     *     Optional flags.
     *
     *     @type bool $customer_self_service When true, the owner customer may set only
     *                                      customer_approved or customer_requested_changes (caller must verify ownership).
     *     @type bool $suppress_customer_status_email Skip customer "status update" email for this transition
     *                                               (e.g. mockup flow sends mockup-ready instead).
     * }
     * @return bool|WP_Error
     */
    public static function update_status( $design_id, $new_status, $user_id = null, $options = array() ) {
        if ( null === $user_id ) {
            $user_id = get_current_user_id();
        }

        $options = wp_parse_args(
            $options,
            array(
                'customer_self_service'          => false,
                'suppress_customer_status_email' => false,
            )
        );

        $customer_self = ! empty( $options['customer_self_service'] );

        if ( $customer_self ) {
            if ( ! in_array( $new_status, array( 'customer_approved', 'customer_requested_changes' ), true ) ) {
                return new WP_Error( 'ttcg_forbidden', __( 'You do not have permission to set this status.', 'twintack-custom-grips' ) );
            }
        } elseif ( ! self::can_set_status( $new_status, $user_id ) ) {
            return new WP_Error( 'ttcg_forbidden', __( 'You do not have permission to set this status.', 'twintack-custom-grips' ) );
        }

        $old_status = get_post_meta( $design_id, '_grip_artwork_status', true );

        if ( $old_status === $new_status ) {
            return true; // No change
        }

        update_post_meta( $design_id, '_grip_artwork_status', $new_status );

        // Create a system message for the audit trail
        $old_label = TTCG_Dashboard::get_status_label( $old_status );
        $new_label = TTCG_Dashboard::get_status_label( $new_status );
        $user      = get_userdata( $user_id );
        $username  = $user ? $user->display_name : __( 'System', 'twintack-custom-grips' );

        TTCG_Messaging::create_system_message(
            $design_id,
            sprintf(
                /* translators: 1: user name, 2: old status, 3: new status */
                __( '%1$s changed status from "%2$s" to "%3$s".', 'twintack-custom-grips' ),
                $username,
                $old_label,
                $new_label
            ),
            'status_change'
        );

        if ( ! empty( $options['suppress_customer_status_email'] ) ) {
            self::$suppress_customer_status_email = true;
        }

        /**
         * Fires when a grip design's artwork status changes via the dashboard.
         *
         * @param int    $design_id  Post ID.
         * @param string $new_status New artwork status slug.
         * @param string $old_status Previous artwork status slug.
         * @param int    $user_id    User who made the change.
         */
        do_action( 'ttcg_status_changed', $design_id, $new_status, $old_status, $user_id );

        self::$suppress_customer_status_email = false;

        if ( WP_DEBUG ) {
            error_log( sprintf(
                'TTCG Status: Design #%d changed from "%s" to "%s" by user #%d.',
                $design_id,
                $old_status,
                $new_status,
                $user_id
            ) );
        }

        return true;
    }

    /**
     * Whether the current status-change notification should skip emailing the customer.
     *
     * @return bool
     */
    public static function should_suppress_customer_status_email() {
        return self::$suppress_customer_status_email;
    }

    /**
     * Set artwork status to shipped from WooCommerce fulfillment (no capability check).
     *
     * Updates meta, adds a system message, and fires {@see 'ttcg_status_changed'} so
     * customer notifications and dashboards stay in sync with the order lifecycle.
     *
     * @param int $design_id Grip design post ID.
     * @param int $order_id  WooCommerce order ID (optional, for messaging / meta).
     * @return bool True if updated or already shipped.
     */
    public static function sync_shipped_from_wc_order( $design_id, $order_id = 0 ) {
        $design_id = absint( $design_id );
        if ( ! $design_id || 'grip_design' !== get_post_type( $design_id ) ) {
            return false;
        }

        $old_status = get_post_meta( $design_id, '_grip_artwork_status', true );
        if ( 'shipped' === $old_status ) {
            return true;
        }

        update_post_meta( $design_id, '_grip_artwork_status', 'shipped' );
        update_post_meta( $design_id, '_grip_shipped_at', current_time( 'mysql' ) );
        if ( $order_id ) {
            update_post_meta( $design_id, '_grip_shipped_order_id', absint( $order_id ) );
        }

        if ( class_exists( 'TTCG_Messaging' ) ) {
            $msg = $order_id
                ? sprintf(
                    /* translators: %d: WooCommerce order ID */
                    __( 'WooCommerce order #%d was marked fulfilled — your grips are on the way.', 'twintack-custom-grips' ),
                    absint( $order_id )
                )
                : __( 'Your grip order has been marked as shipped.', 'twintack-custom-grips' );
            TTCG_Messaging::create_system_message( $design_id, $msg, 'system' );
        }

        /**
         * Fires after artwork status is set to shipped from an order (mirrors staff-driven changes).
         *
         * @param int    $design_id  Post ID.
         * @param string $new_status Always "shipped".
         * @param string $old_status Previous artwork status.
         * @param int    $user_id    0 (system).
         */
        do_action( 'ttcg_status_changed', $design_id, 'shipped', $old_status, 0 );

        return true;
    }

    /**
     * Set artwork status to in production when the customer completes purchase (no capability check).
     *
     * @param int $design_id Grip design post ID.
     * @param int $order_id  WooCommerce order ID.
     * @return bool
     */
    public static function sync_in_production_from_wc_order( $design_id, $order_id = 0 ) {
        $design_id = absint( $design_id );
        if ( ! $design_id || 'grip_design' !== get_post_type( $design_id ) ) {
            return false;
        }

        $old_status = get_post_meta( $design_id, '_grip_artwork_status', true );
        $old_status = TTCG_Dashboard::normalize_status( $old_status );

        if ( in_array( $old_status, array( 'in_production', 'shipped' ), true ) ) {
            if ( $order_id ) {
                update_post_meta( $design_id, '_grip_final_order_id', absint( $order_id ) );
            }
            return true;
        }

        update_post_meta( $design_id, '_grip_artwork_status', 'in_production' );
        update_post_meta( $design_id, '_grip_production_started', current_time( 'mysql' ) );
        if ( $order_id ) {
            update_post_meta( $design_id, '_grip_final_order_id', absint( $order_id ) );
        }

        if ( class_exists( 'TTCG_Messaging' ) ) {
            $msg = $order_id
                ? sprintf(
                    /* translators: %d: WooCommerce order ID */
                    __( 'Order #%d received — your custom grips are now in production.', 'twintack-custom-grips' ),
                    absint( $order_id )
                )
                : __( 'Your custom grips are now in production.', 'twintack-custom-grips' );
            TTCG_Messaging::create_system_message( $design_id, $msg, 'system' );
        }

        do_action( 'ttcg_status_changed', $design_id, 'in_production', $old_status, 0 );

        return true;
    }
}
