<?php
/**
 * TTCG_Router — Frontend page routing for the team dashboard.
 *
 * Registers rewrite rules so that /team-dashboard/ and
 * /team-dashboard/design/{id}/ resolve to plugin templates
 * without requiring a WordPress page to exist.
 *
 * @package TwinTack_Custom_Grips
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TTCG_Router {

    /** @var TTCG_Router|null */
    private static $instance = null;

    /** @var string  Base slug for the dashboard URL. */
    const DASHBOARD_SLUG = 'team-dashboard';

    /**
     * Get the singleton instance.
     *
     * @return TTCG_Router
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
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'handle_template_redirect' ) );
    }

    // ------------------------------------------------------------------
    // Rewrite Rules
    // ------------------------------------------------------------------

    /**
     * Register rewrite rules for the dashboard URLs.
     */
    public function add_rewrite_rules() {
        // New design form: /team-dashboard/new/
        add_rewrite_rule(
            '^' . self::DASHBOARD_SLUG . '/new/?$',
            'index.php?ttcg_page=new-design',
            'top'
        );

        // Single design view: /team-dashboard/design/123/
        add_rewrite_rule(
            '^' . self::DASHBOARD_SLUG . '/design/([0-9]+)/?$',
            'index.php?ttcg_page=design-detail&ttcg_design_id=$matches[1]',
            'top'
        );

        // Paginated list view: /team-dashboard/page/2/
        add_rewrite_rule(
            '^' . self::DASHBOARD_SLUG . '/page/([0-9]+)/?$',
            'index.php?ttcg_page=dashboard&paged=$matches[1]',
            'top'
        );

        // Main list view: /team-dashboard/
        add_rewrite_rule(
            '^' . self::DASHBOARD_SLUG . '/?$',
            'index.php?ttcg_page=dashboard',
            'top'
        );
    }

    /**
     * Whitelist our custom query vars.
     *
     * @param  array $vars Existing query vars.
     * @return array
     */
    public function register_query_vars( $vars ) {
        $vars[] = 'ttcg_page';
        $vars[] = 'ttcg_design_id';
        return $vars;
    }

    // ------------------------------------------------------------------
    // Template Loading & Access Control
    // ------------------------------------------------------------------

    /**
     * Intercept the request when our query vars are present
     * and load the appropriate template.
     */
    public function handle_template_redirect() {
        $page = get_query_var( 'ttcg_page' );

        if ( empty( $page ) ) {
            return;
        }

        // Must be logged in
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url( self::get_dashboard_url() ) );
            exit;
        }

        // Must have dashboard access
        if ( ! TTCG_Roles::user_can_access_dashboard() ) {
            wp_safe_redirect( add_query_arg( 'ttcg_denied', '1', wc_get_page_permalink( 'myaccount' ) ) );
            exit;
        }

        // Determine which template to load
        if ( 'design-detail' === $page ) {
            $design_id = absint( get_query_var( 'ttcg_design_id' ) );
            if ( ! $design_id || 'grip_design' !== get_post_type( $design_id ) ) {
                wp_safe_redirect( self::get_dashboard_url() );
                exit;
            }
        }

        // New design form requires create capability
        if ( 'new-design' === $page && ! current_user_can( 'create_grip_designs' ) ) {
            wp_safe_redirect( self::get_dashboard_url() );
            exit;
        }

        // Load the dashboard template and stop WordPress from loading its own
        $this->load_dashboard_template( $page );
        exit;
    }

    /**
     * Load the dashboard wrapper template.
     *
     * @param string $page  Current page identifier (dashboard | design-detail).
     */
    private function load_dashboard_template( $page ) {
        // Make variables available in the template
        set_query_var( 'ttcg_current_page', $page );

        $template = TTCG_PLUGIN_DIR . 'templates/dashboard.php';

        if ( file_exists( $template ) ) {
            include $template;
        } else {
            wp_die(
                esc_html__( 'Dashboard template not found.', 'twintack-custom-grips' ),
                esc_html__( 'Template Error', 'twintack-custom-grips' ),
                array( 'response' => 500 )
            );
        }
    }

    // ------------------------------------------------------------------
    // URL Helpers
    // ------------------------------------------------------------------

    /**
     * Get the base dashboard URL.
     *
     * @return string
     */
    public static function get_dashboard_url() {
        return home_url( '/' . self::DASHBOARD_SLUG . '/' );
    }

    /**
     * Get the URL for a single design detail page.
     *
     * @param  int $design_id Grip design post ID.
     * @return string
     */
    public static function get_design_url( $design_id ) {
        return home_url( '/' . self::DASHBOARD_SLUG . '/design/' . absint( $design_id ) . '/' );
    }

    /**
     * Get the URL for the new design form.
     *
     * @return string
     */
    public static function get_new_design_url() {
        return home_url( '/' . self::DASHBOARD_SLUG . '/new/' );
    }

    /**
     * Get the URL for the list view with optional filters.
     *
     * @param  array $args Query string args (status, search, paged, etc.).
     * @return string
     */
    public static function get_filtered_url( $args = array() ) {
        return add_query_arg( $args, self::get_dashboard_url() );
    }
}
