<?php
/**
 * Plugin Name: TwinTack Custom Grips
 * Description: Frontend team dashboard for managing custom grip design submissions. Provides art and production team workflows, threaded messaging, mockup uploads, and customer communication — all from the frontend.
 * Version: 1.2.4
 * Author: TwinTack Team
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: twintack-custom-grips
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'TTCG_VERSION', '1.2.4' );
define( 'TTCG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TTCG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TTCG_PLUGIN_FILE', __FILE__ );

/**
 * Main plugin class — singleton pattern.
 *
 * Bootstraps all components and manages activation / deactivation.
 *
 * @since 1.0.0
 */
class TwinTack_Custom_Grips {

    /** @var TwinTack_Custom_Grips|null */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * @return TwinTack_Custom_Grips
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor — registers lifecycle hooks.
     */
    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ), 15 ); // After Grip Manager (priority 10)

        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }

    /**
     * Initialize all plugin components.
     */
    public function init() {
        if ( WP_DEBUG ) {
            error_log( 'TwinTack Custom Grips: Initializing…' );
        }

        // Dependency: WooCommerce
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'notice_woocommerce_missing' ) );
            return;
        }

        // Dependency: Grip Manager (for the grip_design CPT)
        if ( ! post_type_exists( 'grip_design' ) && ! class_exists( 'TwinTack_Grip_Manager' ) ) {
            add_action( 'admin_notices', array( $this, 'notice_grip_manager_missing' ) );
            return;
        }

        // Load includes
        $this->load_includes();

        // Initialize components
        TTCG_Roles::get_instance();
        TTCG_Router::get_instance();
        TTCG_Dashboard::get_instance();
        TTCG_Status::get_instance();
        TTCG_Mockups::get_instance();
        TTCG_Messaging::get_instance();
        TTCG_Ajax::get_instance();
        TTCG_Notifications::get_instance();
        TTCG_Admin::get_instance();
        TTCG_Customer::get_instance();

        // Enqueue customer-facing messaging scripts on My Account grip designs page
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_customer_scripts' ) );

        // Add Team Dashboard card to My Account dashboard for team members
        add_action( 'woocommerce_account_dashboard', array( $this, 'render_dashboard_card' ) );

        // Flush rewrite rules once after version change
        $this->maybe_flush_rules();

        if ( WP_DEBUG ) {
            error_log( 'TwinTack Custom Grips: All components loaded.' );
        }
    }

    /**
     * Require all include files.
     */
    private function load_includes() {
        $includes = array(
            'class-custom-grips-roles',
            'class-custom-grips-router',
            'class-custom-grips-dashboard',
            'class-custom-grips-status',
            'class-custom-grips-mockups',
            'class-custom-grips-messaging',
            'class-custom-grips-ajax',
            'class-custom-grips-notifications',
            'class-custom-grips-admin',
            'class-custom-grips-customer',
        );

        foreach ( $includes as $file ) {
            require_once TTCG_PLUGIN_DIR . 'includes/' . $file . '.php';
        }
    }

    /**
     * Flush rewrite rules when the plugin version changes.
     */
    private function maybe_flush_rules() {
        $option  = 'ttcg_plugin_version';
        $current = get_option( $option );

        if ( $current !== TTCG_VERSION ) {
            $this->migrate_legacy_artwork_statuses();
            flush_rewrite_rules();
            update_option( $option, TTCG_VERSION );

            if ( WP_DEBUG ) {
                error_log( 'TwinTack Custom Grips: Flushed rewrite rules for v' . TTCG_VERSION );
            }
        }
    }

    /**
     * One-time migration: map deprecated approved_for_production → in_production.
     */
    private function migrate_legacy_artwork_statuses() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $updated = $wpdb->query(
            "UPDATE {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             SET pm.meta_value = 'in_production'
             WHERE pm.meta_key = '_grip_artwork_status'
               AND pm.meta_value = 'approved_for_production'
               AND p.post_type = 'grip_design'"
        );

        if ( WP_DEBUG && false !== $updated ) {
            error_log( 'TwinTack Custom Grips: Migrated ' . (int) $updated . ' grip design(s) from approved_for_production to in_production.' );
        }
    }

    // ------------------------------------------------------------------
    // Activation / Deactivation
    // ------------------------------------------------------------------

    /**
     * Plugin activation callback.
     */
    public function activate() {
        // Load roles class and register roles
        require_once TTCG_PLUGIN_DIR . 'includes/class-custom-grips-roles.php';
        TTCG_Roles::get_instance()->register_roles();

        // Load messaging class and register the message CPT
        require_once TTCG_PLUGIN_DIR . 'includes/class-custom-grips-messaging.php';
        TTCG_Messaging::get_instance()->register_post_type();

        // Load router and register rewrite rules
        require_once TTCG_PLUGIN_DIR . 'includes/class-custom-grips-router.php';
        TTCG_Router::get_instance()->add_rewrite_rules();

        // Load customer class and register endpoint
        require_once TTCG_PLUGIN_DIR . 'includes/class-custom-grips-customer.php';
        TTCG_Customer::get_instance()->register_endpoint();

        flush_rewrite_rules();

        if ( WP_DEBUG ) {
            error_log( 'TwinTack Custom Grips: Activated.' );
        }
    }

    /**
     * Plugin deactivation callback.
     */
    public function deactivate() {
        flush_rewrite_rules();

        if ( WP_DEBUG ) {
            error_log( 'TwinTack Custom Grips: Deactivated.' );
        }
    }

    // ------------------------------------------------------------------
    // Customer-Facing Scripts
    // ------------------------------------------------------------------

    /**
     * Enqueue the customer messaging script on the My Account grip designs page.
     */
    public function enqueue_customer_scripts() {
        if ( ! is_account_page() ) {
            return;
        }

        global $wp_query;
        $qv = isset( $wp_query->query_vars ) ? $wp_query->query_vars : array();
        // Legacy endpoint + canonical customer dashboard
        if ( empty( $qv['grip-designs'] ) && empty( $qv['my-custom-grips'] ) ) {
            return;
        }

        wp_enqueue_script(
            'ttcg-customer-messages',
            TTCG_PLUGIN_URL . 'assets/js/customer-messages.js',
            array( 'jquery' ),
            TTCG_VERSION,
            true
        );

        wp_localize_script( 'ttcg-customer-messages', 'ttcg_customer', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ttcg_customer_nonce' ),
            'user_id'  => get_current_user_id(),
        ) );

        // Add minimal inline styles for the thread container
        wp_add_inline_style( 'wp-block-library', '
            .ttcg-customer-thread {
                max-height: 400px;
                overflow-y: auto;
                padding: 8px 0;
            }
        ' );
    }

    // ------------------------------------------------------------------
    // My Account — Team Dashboard Card
    // ------------------------------------------------------------------

    /**
     * Render a "Team Dashboard" card on the My Account dashboard
     * for users who have access to the grip design dashboard.
     *
     * Hooks into woocommerce_account_dashboard.
     */
    public function render_dashboard_card() {
        if ( ! TTCG_Roles::user_can_access_dashboard() ) {
            return;
        }

        $role_label = TTCG_Roles::get_user_role_label();
        $role_slug  = TTCG_Roles::get_user_role_slug();
        $url        = TTCG_Router::get_dashboard_url();
        ?>
        <div class="dashboard-grid" style="margin-top: 0;">
            <a href="<?php echo esc_url( $url ); ?>" class="dashboard-card" style="border: 2px solid var(--color-text); background: var(--color-text); color: var(--color-accent);">
                <div class="dashboard-card-icon" style="background: var(--color-accent);">
                    <svg viewBox="0 0 24 24" style="fill: var(--color-text);"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
                </div>
                <h2 style="color: var(--color-accent);"><?php esc_html_e( 'Team Dashboard', 'twintack-custom-grips' ); ?></h2>
                <p style="color: rgba(255,255,255,0.8);">
                    <?php
                    printf(
                        /* translators: %s: user role label */
                        esc_html__( 'Manage grip design submissions, upload mockups, and communicate with customers. Logged in as %s.', 'twintack-custom-grips' ),
                        esc_html( $role_label )
                    );
                    ?>
                </p>
                <div class="dashboard-card-footer" style="border-top-color: rgba(255,255,255,0.2); color: var(--color-accent);">
                    <?php esc_html_e( 'Open Dashboard →', 'twintack-custom-grips' ); ?>
                </div>
            </a>
        </div>
        <?php
    }

    // ------------------------------------------------------------------
    // Admin Notices
    // ------------------------------------------------------------------

    /**
     * WooCommerce not found notice.
     */
    public function notice_woocommerce_missing() {
        echo '<div class="error"><p>';
        esc_html_e( 'TwinTack Custom Grips requires WooCommerce to be installed and activated.', 'twintack-custom-grips' );
        echo '</p></div>';
    }

    /**
     * Grip Manager not found notice.
     */
    public function notice_grip_manager_missing() {
        echo '<div class="error"><p>';
        esc_html_e( 'TwinTack Custom Grips requires the TwinTack Grip Manager plugin for grip design data.', 'twintack-custom-grips' );
        echo '</p></div>';
    }
}

// Boot the plugin
TwinTack_Custom_Grips::get_instance();
