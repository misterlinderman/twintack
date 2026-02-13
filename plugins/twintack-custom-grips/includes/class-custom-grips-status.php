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
        'approved_for_production',
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
     * @return bool|WP_Error
     */
    public static function update_status( $design_id, $new_status, $user_id = null ) {
        if ( null === $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! self::can_set_status( $new_status, $user_id ) ) {
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

        /**
         * Fires when a grip design's artwork status changes via the dashboard.
         *
         * @param int    $design_id  Post ID.
         * @param string $new_status New artwork status slug.
         * @param string $old_status Previous artwork status slug.
         * @param int    $user_id    User who made the change.
         */
        do_action( 'ttcg_status_changed', $design_id, $new_status, $old_status, $user_id );

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
}
