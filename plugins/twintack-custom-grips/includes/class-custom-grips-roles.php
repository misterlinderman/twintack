<?php
/**
 * TTCG_Roles — Custom user roles for the art and production teams.
 *
 * Registers two new WordPress roles with granular capabilities
 * and provides helper methods for permission checks.
 *
 * @package TwinTack_Custom_Grips
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TTCG_Roles {

    /** @var TTCG_Roles|null */
    private static $instance = null;

    /**
     * All custom capabilities used by this plugin.
     *
     * @var array
     */
    private static $all_caps = array(
        'read',
        'upload_files',
        'edit_grip_designs',
        'manage_grip_mockups',
        'send_grip_messages',
        'edit_grip_status',
        'edit_grip_details',
        'manage_grip_production',
        'create_grip_designs',
        'delete_grip_designs',
    );

    /**
     * Capabilities for the Art Team role.
     *
     * @var array
     */
    private static $art_team_caps = array(
        'read'                 => true,
        'upload_files'         => true,
        'edit_grip_designs'    => true,
        'manage_grip_mockups'  => true,
        'send_grip_messages'   => true,
    );

    /**
     * Capabilities for the Production Team role (superset of art team).
     *
     * @var array
     */
    private static $production_team_caps = array(
        'read'                   => true,
        'upload_files'           => true,
        'edit_grip_designs'      => true,
        'manage_grip_mockups'    => true,
        'send_grip_messages'     => true,
        'edit_grip_status'       => true,
        'edit_grip_details'      => true,
        'manage_grip_production' => true,
        'create_grip_designs'    => true,
    );

    /**
     * Get the singleton instance.
     *
     * @return TTCG_Roles
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor — ensures administrators always have our caps.
     */
    private function __construct() {
        add_action( 'admin_init', array( $this, 'ensure_admin_caps' ) );
    }

    // ------------------------------------------------------------------
    // Role Registration
    // ------------------------------------------------------------------

    /**
     * Register custom roles. Called on plugin activation.
     */
    public function register_roles() {
        // Remove first in case capabilities changed between versions
        remove_role( 'grip_art_team' );
        remove_role( 'grip_production_team' );

        add_role( 'grip_art_team', __( 'Grip Art Team', 'twintack-custom-grips' ), self::$art_team_caps );
        add_role( 'grip_production_team', __( 'Grip Production Team', 'twintack-custom-grips' ), self::$production_team_caps );

        // Grant all caps to administrators
        $this->ensure_admin_caps();

        if ( WP_DEBUG ) {
            error_log( 'TTCG Roles: Registered grip_art_team and grip_production_team roles.' );
        }
    }

    /**
     * Ensure administrators have every custom capability.
     */
    public function ensure_admin_caps() {
        $admin_role = get_role( 'administrator' );
        if ( ! $admin_role ) {
            return;
        }

        foreach ( self::$all_caps as $cap ) {
            if ( ! $admin_role->has_cap( $cap ) ) {
                $admin_role->add_cap( $cap );
            }
        }
    }

    // ------------------------------------------------------------------
    // Permission Helpers
    // ------------------------------------------------------------------

    /**
     * Can the current (or given) user access the team dashboard?
     *
     * @param  int|null $user_id Optional user ID. Defaults to current user.
     * @return bool
     */
    public static function user_can_access_dashboard( $user_id = null ) {
        if ( null === $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! $user_id ) {
            return false;
        }

        return user_can( $user_id, 'edit_grip_designs' );
    }

    /**
     * Is the user a site administrator?
     *
     * @param  int|null $user_id
     * @return bool
     */
    public static function user_is_admin( $user_id = null ) {
        if ( null === $user_id ) {
            $user_id = get_current_user_id();
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        return in_array( 'administrator', (array) $user->roles, true );
    }

    /**
     * Is the user on the art team?
     *
     * Returns true for the grip_art_team role only (not administrators).
     * Use user_can_access_dashboard() for general access checks.
     *
     * @param  int|null $user_id
     * @return bool
     */
    public static function user_is_art_team( $user_id = null ) {
        if ( null === $user_id ) {
            $user_id = get_current_user_id();
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        return in_array( 'grip_art_team', (array) $user->roles, true );
    }

    /**
     * Is the user on the production team?
     *
     * Returns true for the grip_production_team role only (not administrators).
     * Use user_can_access_dashboard() for general access checks.
     *
     * @param  int|null $user_id
     * @return bool
     */
    public static function user_is_production_team( $user_id = null ) {
        if ( null === $user_id ) {
            $user_id = get_current_user_id();
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        return in_array( 'grip_production_team', (array) $user->roles, true );
    }

    /**
     * Get the display-friendly role label for a user.
     *
     * @param  int|null $user_id
     * @return string
     */
    public static function get_user_role_label( $user_id = null ) {
        if ( self::user_is_admin( $user_id ) ) {
            return __( 'Administrator', 'twintack-custom-grips' );
        }
        if ( self::user_is_production_team( $user_id ) ) {
            return __( 'Production Team', 'twintack-custom-grips' );
        }
        if ( self::user_is_art_team( $user_id ) ) {
            return __( 'Art Team', 'twintack-custom-grips' );
        }
        return __( 'Customer', 'twintack-custom-grips' );
    }

    /**
     * Get the role slug for a user (used in CSS classes, message meta, etc.).
     *
     * @param  int|null $user_id
     * @return string   One of: administrator, production_team, art_team, customer
     */
    public static function get_user_role_slug( $user_id = null ) {
        if ( self::user_is_admin( $user_id ) ) {
            return 'administrator';
        }
        if ( self::user_is_production_team( $user_id ) ) {
            return 'production_team';
        }
        if ( self::user_is_art_team( $user_id ) ) {
            return 'art_team';
        }
        return 'customer';
    }
}
