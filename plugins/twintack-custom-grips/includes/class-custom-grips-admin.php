<?php
/**
 * TTCG_Admin — WordPress admin pages for the Custom Grips plugin.
 *
 * Provides:
 * - Notification management/transparency dashboard
 * - Test grip design creation tool
 * - Email log viewer
 *
 * @package TwinTack_Custom_Grips
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TTCG_Admin {

    /** @var TTCG_Admin|null */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * @return TTCG_Admin
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
        add_action( 'admin_menu', array( $this, 'register_admin_pages' ) );
        add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // AJAX handler for toggling notification preferences
        add_action( 'wp_ajax_ttcg_toggle_notification', array( $this, 'ajax_toggle_notification' ) );

        // AJAX handler for plugin settings toggles
        add_action( 'wp_ajax_ttcg_toggle_setting', array( $this, 'ajax_toggle_setting' ) );

        // Hide legacy "My Grip Designs" menu item if setting is enabled
        if ( get_option( 'ttcg_hide_legacy_grip_designs', '0' ) === '1' ) {
            add_filter( 'woocommerce_account_menu_items', array( $this, 'remove_legacy_grip_designs_menu' ), 99 );
        }
    }

    // ------------------------------------------------------------------
    // Admin Menu Registration
    // ------------------------------------------------------------------

    /**
     * Register the admin menu pages.
     */
    public function register_admin_pages() {
        // Main menu
        add_menu_page(
            __( 'Custom Grips', 'twintack-custom-grips' ),
            __( 'Custom Grips', 'twintack-custom-grips' ),
            'manage_options',
            'ttcg-dashboard',
            array( $this, 'render_notifications_page' ),
            'dashicons-art',
            58
        );

        // Notifications sub-page
        add_submenu_page(
            'ttcg-dashboard',
            __( 'Notification Manager', 'twintack-custom-grips' ),
            __( 'Notifications', 'twintack-custom-grips' ),
            'manage_options',
            'ttcg-dashboard',
            array( $this, 'render_notifications_page' )
        );

        // Test Tools sub-page
        add_submenu_page(
            'ttcg-dashboard',
            __( 'Test Tools', 'twintack-custom-grips' ),
            __( 'Test Tools', 'twintack-custom-grips' ),
            'manage_options',
            'ttcg-test-tools',
            array( $this, 'render_test_tools_page' )
        );

        // Email Log sub-page
        add_submenu_page(
            'ttcg-dashboard',
            __( 'Email Log', 'twintack-custom-grips' ),
            __( 'Email Log', 'twintack-custom-grips' ),
            'manage_options',
            'ttcg-email-log',
            array( $this, 'render_email_log_page' )
        );
    }

    /**
     * Enqueue admin styles and scripts for our pages.
     *
     * @param string $hook The current admin page.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( false === strpos( $hook, 'ttcg-' ) ) {
            return;
        }

        wp_add_inline_style( 'wp-admin', $this->get_admin_css() );
        wp_add_inline_script( 'jquery', $this->get_admin_js() );

        // Localize data for the toggle AJAX
        wp_localize_script( 'jquery', 'ttcg_admin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ttcg_toggle_notification' ),
        ) );
    }

    // ------------------------------------------------------------------
    // Notification Manager Page
    // ------------------------------------------------------------------

    /**
     * Render the Notification Manager admin page.
     */
    public function render_notifications_page() {
        ?>
        <div class="wrap ttcg-admin">
            <h1><?php esc_html_e( 'Notification Manager', 'twintack-custom-grips' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Complete overview of all email notifications, webhooks, and communication triggers across the TwinTack grip design system.', 'twintack-custom-grips' ); ?></p>

            <!-- Custom Grips Plugin Notifications -->
            <div class="ttcg-admin-section">
                <h2>
                    <span class="dashicons dashicons-email-alt"></span>
                    <?php esc_html_e( 'TwinTack Custom Grips — Email Notifications', 'twintack-custom-grips' ); ?>
                </h2>
                <p class="description"><?php esc_html_e( 'Notifications managed by the twintack-custom-grips plugin (team dashboard & messaging system).', 'twintack-custom-grips' ); ?></p>

                <table class="widefat ttcg-notification-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Trigger', 'twintack-custom-grips' ); ?></th>
                            <th><?php esc_html_e( 'Event Hook', 'twintack-custom-grips' ); ?></th>
                            <th><?php esc_html_e( 'Recipient(s)', 'twintack-custom-grips' ); ?></th>
                            <th><?php esc_html_e( 'Email Template', 'twintack-custom-grips' ); ?></th>
                            <th><?php esc_html_e( 'Subject', 'twintack-custom-grips' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'twintack-custom-grips' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e( 'Customer sends a message', 'twintack-custom-grips' ); ?></strong></td>
                            <td><code>ttcg_message_created</code></td>
                            <td>
                                <span class="ttcg-badge ttcg-badge--team"><?php esc_html_e( 'Art Team', 'twintack-custom-grips' ); ?></span>
                                <span class="ttcg-badge ttcg-badge--team"><?php esc_html_e( 'Production Team', 'twintack-custom-grips' ); ?></span>
                                <span class="ttcg-badge ttcg-badge--admin"><?php esc_html_e( 'Admins', 'twintack-custom-grips' ); ?></span>
                            </td>
                            <td><code>message-notification</code></td>
                            <td><?php esc_html_e( 'New Customer Message', 'twintack-custom-grips' ); ?></td>
                            <td><span class="ttcg-status-active"><?php esc_html_e( 'Active', 'twintack-custom-grips' ); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Team sends a message', 'twintack-custom-grips' ); ?></strong></td>
                            <td><code>ttcg_message_created</code></td>
                            <td><span class="ttcg-badge ttcg-badge--customer"><?php esc_html_e( 'Customer', 'twintack-custom-grips' ); ?></span></td>
                            <td><code>message-notification</code></td>
                            <td><?php esc_html_e( 'New Message About Your Grip Design', 'twintack-custom-grips' ); ?></td>
                            <td><span class="ttcg-status-active"><?php esc_html_e( 'Active', 'twintack-custom-grips' ); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Status changed (any)', 'twintack-custom-grips' ); ?></strong></td>
                            <td><code>ttcg_status_changed</code></td>
                            <td><span class="ttcg-badge ttcg-badge--customer"><?php esc_html_e( 'Customer', 'twintack-custom-grips' ); ?></span></td>
                            <td><code>status-update</code></td>
                            <td><?php esc_html_e( 'Your Grip Design Status Has Been Updated', 'twintack-custom-grips' ); ?></td>
                            <td><span class="ttcg-status-active"><?php esc_html_e( 'Active', 'twintack-custom-grips' ); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Status → Customer Approved', 'twintack-custom-grips' ); ?></strong></td>
                            <td><code>ttcg_status_changed</code></td>
                            <td>
                                <span class="ttcg-badge ttcg-badge--customer"><?php esc_html_e( 'Customer', 'twintack-custom-grips' ); ?></span>
                                <span class="ttcg-badge ttcg-badge--team"><?php esc_html_e( 'Team', 'twintack-custom-grips' ); ?></span>
                            </td>
                            <td><code>status-update</code></td>
                            <td><?php esc_html_e( 'Customer Approved Grip Design (team) + Status Updated (customer)', 'twintack-custom-grips' ); ?></td>
                            <td><span class="ttcg-status-active"><?php esc_html_e( 'Active', 'twintack-custom-grips' ); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Mockup uploaded by team', 'twintack-custom-grips' ); ?></strong></td>
                            <td><code>ttcg_mockup_uploaded</code></td>
                            <td><span class="ttcg-badge ttcg-badge--customer"><?php esc_html_e( 'Customer', 'twintack-custom-grips' ); ?></span></td>
                            <td><code>mockup-ready</code></td>
                            <td><?php esc_html_e( 'Your Grip Mockup Is Ready for Review!', 'twintack-custom-grips' ); ?></td>
                            <td><span class="ttcg-status-active"><?php esc_html_e( 'Active', 'twintack-custom-grips' ); ?></span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Grip Manager Plugin Notifications -->
            <div class="ttcg-admin-section">
                <h2>
                    <span class="dashicons dashicons-email"></span>
                    <?php esc_html_e( 'TwinTack Grip Manager — Email Notifications', 'twintack-custom-grips' ); ?>
                </h2>
                <p class="description"><?php esc_html_e( 'Notifications managed by the twintack-grip-manager plugin (original order workflow).', 'twintack-custom-grips' ); ?></p>

                <table class="widefat ttcg-notification-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Trigger', 'twintack-custom-grips' ); ?></th>
                            <th><?php esc_html_e( 'Event Hook', 'twintack-custom-grips' ); ?></th>
                            <th><?php esc_html_e( 'Recipient(s)', 'twintack-custom-grips' ); ?></th>
                            <th><?php esc_html_e( 'Email Template', 'twintack-custom-grips' ); ?></th>
                            <th><?php esc_html_e( 'Subject', 'twintack-custom-grips' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'twintack-custom-grips' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e( 'Artwork status → Customer Review', 'twintack-custom-grips' ); ?></strong></td>
                            <td><code>grip_design_artwork_status_changed</code></td>
                            <td><span class="ttcg-badge ttcg-badge--customer"><?php esc_html_e( 'Customer', 'twintack-custom-grips' ); ?></span></td>
                            <td><code>artwork_ready</code></td>
                            <td><?php esc_html_e( 'Your {Team} Grip Design is Ready for Review!', 'twintack-custom-grips' ); ?></td>
                            <td><span class="ttcg-status-active"><?php esc_html_e( 'Active', 'twintack-custom-grips' ); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Artwork status → In Production', 'twintack-custom-grips' ); ?></strong></td>
                            <td><code>grip_design_artwork_status_changed</code></td>
                            <td><span class="ttcg-badge ttcg-badge--customer"><?php esc_html_e( 'Customer', 'twintack-custom-grips' ); ?></span></td>
                            <td><code>production_started</code></td>
                            <td><?php esc_html_e( 'Your {Team} Grips are Now in Production!', 'twintack-custom-grips' ); ?></td>
                            <td><span class="ttcg-status-active"><?php esc_html_e( 'Active', 'twintack-custom-grips' ); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Production approval (purchase)', 'twintack-custom-grips' ); ?></strong></td>
                            <td><code>grip_production_approval</code></td>
                            <td><span class="ttcg-badge ttcg-badge--customer"><?php esc_html_e( 'Customer', 'twintack-custom-grips' ); ?></span></td>
                            <td><code>production_started</code></td>
                            <td><?php esc_html_e( 'Your {Team} Grips are Now in Production!', 'twintack-custom-grips' ); ?></td>
                            <td><span class="ttcg-status-active"><?php esc_html_e( 'Active', 'twintack-custom-grips' ); ?></span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Native Workflow Summary -->
            <div class="ttcg-admin-section">
                <h2>
                    <span class="dashicons dashicons-rest-api"></span>
                    <?php esc_html_e( 'Native Workflow (WooCommerce Sync)', 'twintack-custom-grips' ); ?>
                </h2>
                <p class="description"><?php esc_html_e( 'Artwork status transitions driven by WooCommerce order status. No external webhooks required.', 'twintack-custom-grips' ); ?></p>

                <table class="widefat ttcg-notification-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Trigger', 'twintack-custom-grips' ); ?></th>
                            <th><?php esc_html_e( 'Handler', 'twintack-custom-grips' ); ?></th>
                            <th><?php esc_html_e( 'Artwork Status Set', 'twintack-custom-grips' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'twintack-custom-grips' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e( 'WooCommerce order → Processing', 'twintack-custom-grips' ); ?></strong></td>
                            <td><code>TTCG_Status::sync_in_production_from_wc_order()</code></td>
                            <td><code>in_production</code></td>
                            <td><span class="ttcg-status-active"><?php esc_html_e( 'Active', 'twintack-custom-grips' ); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'WooCommerce order → Completed / shipped-unpaid', 'twintack-custom-grips' ); ?></strong></td>
                            <td><code>TTCG_Status::sync_shipped_from_wc_order()</code></td>
                            <td><code>shipped</code></td>
                            <td><span class="ttcg-status-active"><?php esc_html_e( 'Active', 'twintack-custom-grips' ); ?></span></td>
                        </tr>
                    </tbody>
                </table>
                <p class="description" style="margin-top: 12px;">
                    <?php
                    printf(
                        /* translators: %s: path to WORKFLOW.md */
                        esc_html__( 'Full workflow reference: %s', 'twintack-custom-grips' ),
                        '<code>plugins/twintack-custom-grips/WORKFLOW.md</code>'
                    );
                    ?>
                </p>
            </div>

            <!-- Legacy Integrations (Deprecated) -->
            <div class="ttcg-admin-section">
                <h2>
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e( 'Legacy Integrations (Deprecated)', 'twintack-custom-grips' ); ?>
                </h2>
                <p class="description"><?php esc_html_e( 'Monday.com and Make.com are no longer used for the grip production workflow. Code remains for backward compatibility only.', 'twintack-custom-grips' ); ?></p>

                <table class="widefat ttcg-notification-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Integration', 'twintack-custom-grips' ); ?></th>
                            <th><?php esc_html_e( 'Previous Use', 'twintack-custom-grips' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'twintack-custom-grips' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e( 'Make.com webhooks', 'twintack-custom-grips' ); ?></strong></td>
                            <td><?php esc_html_e( 'Customer feedback and order status sync via legacy Grip Manager path', 'twintack-custom-grips' ); ?></td>
                            <td><span class="ttcg-status-inactive"><?php esc_html_e( 'Retired', 'twintack-custom-grips' ); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Monday.com API', 'twintack-custom-grips' ); ?></strong></td>
                            <td><?php esc_html_e( 'External production board sync and mockup assets', 'twintack-custom-grips' ); ?></td>
                            <td><span class="ttcg-status-inactive"><?php esc_html_e( 'Retired', 'twintack-custom-grips' ); ?></span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Notification Flow Diagram -->
            <div class="ttcg-admin-section">
                <h2>
                    <span class="dashicons dashicons-networking"></span>
                    <?php esc_html_e( 'Notification Flow Summary', 'twintack-custom-grips' ); ?>
                </h2>
                <div class="ttcg-flow-diagram">
                    <div class="ttcg-flow-column">
                        <h3><?php esc_html_e( 'Customer Actions', 'twintack-custom-grips' ); ?></h3>
                        <div class="ttcg-flow-item ttcg-flow-item--customer">
                            <?php esc_html_e( 'Sends message → Team gets email', 'twintack-custom-grips' ); ?>
                        </div>
                        <div class="ttcg-flow-item ttcg-flow-item--customer">
                            <?php esc_html_e( 'Approves design → Team + customer email', 'twintack-custom-grips' ); ?>
                        </div>
                        <div class="ttcg-flow-item ttcg-flow-item--customer">
                            <?php esc_html_e( 'Requests changes → Team notified via status + messaging', 'twintack-custom-grips' ); ?>
                        </div>
                        <div class="ttcg-flow-item ttcg-flow-item--customer">
                            <?php esc_html_e( 'Purchases grips → WC Processing → In Production', 'twintack-custom-grips' ); ?>
                        </div>
                    </div>
                    <div class="ttcg-flow-column">
                        <h3><?php esc_html_e( 'Team Actions', 'twintack-custom-grips' ); ?></h3>
                        <div class="ttcg-flow-item ttcg-flow-item--team">
                            <?php esc_html_e( 'Sends message → Customer gets email', 'twintack-custom-grips' ); ?>
                        </div>
                        <div class="ttcg-flow-item ttcg-flow-item--team">
                            <?php esc_html_e( 'Changes status → Customer gets email', 'twintack-custom-grips' ); ?>
                        </div>
                        <div class="ttcg-flow-item ttcg-flow-item--team">
                            <?php esc_html_e( 'Uploads mockup → Customer gets email', 'twintack-custom-grips' ); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Opt-out Status -->
            <div class="ttcg-admin-section">
                <h2>
                    <span class="dashicons dashicons-admin-users"></span>
                    <?php esc_html_e( 'User Notification Preferences', 'twintack-custom-grips' ); ?>
                </h2>
                <?php $this->render_user_notification_prefs(); ?>
            </div>

            <!-- Plugin Settings -->
            <div class="ttcg-admin-section">
                <h2>
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php esc_html_e( 'Plugin Settings', 'twintack-custom-grips' ); ?>
                </h2>
                <p class="description"><?php esc_html_e( 'Control feature visibility and legacy plugin integration.', 'twintack-custom-grips' ); ?></p>
                <?php $this->render_plugin_settings(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render user notification preferences table with interactive toggles.
     */
    private function render_user_notification_prefs() {
        $team_users = get_users( array(
            'role__in' => array( 'grip_art_team', 'grip_production_team', 'administrator' ),
            'orderby'  => 'display_name',
        ) );

        if ( empty( $team_users ) ) {
            echo '<p>' . esc_html__( 'No team users found.', 'twintack-custom-grips' ) . '</p>';
            return;
        }

        $notification_types = array(
            'message-notification' => __( 'Message Notifications', 'twintack-custom-grips' ),
            'status-update'       => __( 'Status Updates', 'twintack-custom-grips' ),
            'mockup-ready'        => __( 'Mockup Ready', 'twintack-custom-grips' ),
        );
        ?>
        <p class="description" style="margin-bottom: 12px;">
            <?php esc_html_e( 'Toggle notifications on or off for each team member. Changes are saved instantly.', 'twintack-custom-grips' ); ?>
        </p>
        <table class="widefat ttcg-prefs-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'User', 'twintack-custom-grips' ); ?></th>
                    <th><?php esc_html_e( 'Role', 'twintack-custom-grips' ); ?></th>
                    <th><?php esc_html_e( 'Email', 'twintack-custom-grips' ); ?></th>
                    <?php foreach ( $notification_types as $label ) : ?>
                        <th class="ttcg-prefs-table__toggle-col"><?php echo esc_html( $label ); ?></th>
                    <?php endforeach; ?>
                    <th class="ttcg-prefs-table__toggle-col"><?php esc_html_e( 'All', 'twintack-custom-grips' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $team_users as $user ) :
                    $prefs = get_user_meta( $user->ID, '_ttcg_notification_prefs', true );
                    if ( ! is_array( $prefs ) ) {
                        $prefs = array();
                    }
                    $roles = array_intersect( $user->roles, array( 'grip_art_team', 'grip_production_team', 'administrator' ) );

                    $all_on  = true;
                    $all_off = true;
                    foreach ( array_keys( $notification_types ) as $type ) {
                        $is_off = ! empty( $prefs[ $type ] ) && 'off' === $prefs[ $type ];
                        if ( $is_off ) {
                            $all_on = false;
                        } else {
                            $all_off = false;
                        }
                    }
                ?>
                    <tr data-user-id="<?php echo esc_attr( $user->ID ); ?>">
                        <td>
                            <strong><?php echo esc_html( $user->display_name ); ?></strong>
                        </td>
                        <td>
                            <?php foreach ( $roles as $role ) : ?>
                                <span class="ttcg-badge ttcg-badge--<?php echo esc_attr( 'administrator' === $role ? 'admin' : 'team' ); ?>">
                                    <?php echo esc_html( $role ); ?>
                                </span>
                            <?php endforeach; ?>
                        </td>
                        <td><code><?php echo esc_html( $user->user_email ); ?></code></td>
                        <?php foreach ( array_keys( $notification_types ) as $type ) :
                            $is_on = empty( $prefs[ $type ] ) || 'off' !== $prefs[ $type ];
                        ?>
                            <td class="ttcg-prefs-table__toggle-col">
                                <label class="ttcg-toggle">
                                    <input type="checkbox"
                                           class="ttcg-toggle__input"
                                           data-user-id="<?php echo esc_attr( $user->ID ); ?>"
                                           data-type="<?php echo esc_attr( $type ); ?>"
                                           <?php checked( $is_on ); ?>>
                                    <span class="ttcg-toggle__slider"></span>
                                    <span class="ttcg-toggle__label ttcg-toggle__label--on"><?php esc_html_e( 'On', 'twintack-custom-grips' ); ?></span>
                                    <span class="ttcg-toggle__label ttcg-toggle__label--off"><?php esc_html_e( 'Off', 'twintack-custom-grips' ); ?></span>
                                </label>
                            </td>
                        <?php endforeach; ?>
                        <td class="ttcg-prefs-table__toggle-col">
                            <button type="button"
                                    class="button button-small ttcg-toggle-all-btn"
                                    data-user-id="<?php echo esc_attr( $user->ID ); ?>"
                                    data-state="<?php echo $all_on ? 'on' : 'off'; ?>">
                                <?php echo $all_on
                                    ? esc_html__( 'Disable All', 'twintack-custom-grips' )
                                    : esc_html__( 'Enable All', 'twintack-custom-grips' ); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div id="ttcg-prefs-save-notice" class="ttcg-prefs-notice" style="display:none;"></div>
        <?php
    }

    // ------------------------------------------------------------------
    // Test Tools Page
    // ------------------------------------------------------------------

    /**
     * Render the Test Tools admin page.
     */
    public function render_test_tools_page() {
        ?>
        <div class="wrap ttcg-admin">
            <h1><?php esc_html_e( 'Test Tools', 'twintack-custom-grips' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Tools for testing the grip design communication workflow.', 'twintack-custom-grips' ); ?></p>

            <?php
            // Show success/error notices
            if ( isset( $_GET['ttcg_created'] ) ) {
                $id = absint( $_GET['ttcg_created'] );
                echo '<div class="notice notice-success is-dismissible"><p>';
                printf(
                    /* translators: %d: grip design post ID */
                    esc_html__( 'Test grip design #%d created successfully! You can now use this design to test the customer communication workflow.', 'twintack-custom-grips' ),
                    $id
                );
                echo ' <a href="' . esc_url( TTCG_Router::get_design_url( $id ) ) . '">' . esc_html__( 'View in Team Dashboard →', 'twintack-custom-grips' ) . '</a>';
                echo '</p></div>';
            }

            if ( isset( $_GET['ttcg_error'] ) ) {
                echo '<div class="notice notice-error is-dismissible"><p>';
                echo esc_html( sanitize_text_field( wp_unslash( $_GET['ttcg_error'] ) ) );
                echo '</p></div>';
            }
            ?>

            <!-- Create Test Grip Design -->
            <div class="ttcg-admin-section">
                <h2>
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e( 'Create Test Grip Design', 'twintack-custom-grips' ); ?>
                </h2>
                <p class="description"><?php esc_html_e( 'Create a grip design associated with a specific user account for testing the customer-team communication workflow.', 'twintack-custom-grips' ); ?></p>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'ttcg_create_test_design', 'ttcg_nonce' ); ?>
                    <input type="hidden" name="action" value="ttcg_create_test_design">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="ttcg_user_id"><?php esc_html_e( 'Associate with User', 'twintack-custom-grips' ); ?></label>
                            </th>
                            <td>
                                <select name="ttcg_user_id" id="ttcg_user_id" class="regular-text">
                                    <?php
                                    $users = get_users( array( 'orderby' => 'display_name', 'fields' => array( 'ID', 'display_name', 'user_email' ) ) );
                                    foreach ( $users as $user ) {
                                        printf(
                                            '<option value="%d" %s>%s (%s)</option>',
                                            esc_attr( $user->ID ),
                                            selected( $user->ID, get_current_user_id(), false ),
                                            esc_html( $user->display_name ),
                                            esc_html( $user->user_email )
                                        );
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'The grip design will be linked to this user\'s email so it appears in their My Account area.', 'twintack-custom-grips' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="ttcg_team_name"><?php esc_html_e( 'Team / School Name', 'twintack-custom-grips' ); ?></label>
                            </th>
                            <td>
                                <input type="text" name="ttcg_team_name" id="ttcg_team_name" class="regular-text" value="Test Team" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="ttcg_design_type"><?php esc_html_e( 'Design Type', 'twintack-custom-grips' ); ?></label>
                            </th>
                            <td>
                                <select name="ttcg_design_type" id="ttcg_design_type">
                                    <option value="bat-grip"><?php esc_html_e( 'Bat Grip', 'twintack-custom-grips' ); ?></option>
                                    <option value="fishing-grip"><?php esc_html_e( 'Fishing Grip', 'twintack-custom-grips' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="ttcg_quantity"><?php esc_html_e( 'Quantity', 'twintack-custom-grips' ); ?></label>
                            </th>
                            <td>
                                <input type="number" name="ttcg_quantity" id="ttcg_quantity" class="small-text" value="25" min="1">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="ttcg_artwork_status"><?php esc_html_e( 'Starting Status', 'twintack-custom-grips' ); ?></label>
                            </th>
                            <td>
                                <select name="ttcg_artwork_status" id="ttcg_artwork_status">
                                    <?php
                                    $statuses = TTCG_Dashboard::get_statuses();
                                    foreach ( $statuses as $slug => $status_data ) {
                                        printf(
                                            '<option value="%s">%s</option>',
                                            esc_attr( $slug ),
                                            esc_html( $status_data['label'] )
                                        );
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Set to "Customer Review" to test the customer approval workflow, or "Mockup Required" to test the full flow.', 'twintack-custom-grips' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="ttcg_design_layout"><?php esc_html_e( 'Pattern', 'twintack-custom-grips' ); ?></label>
                            </th>
                            <td>
                                <input type="text" name="ttcg_design_layout" id="ttcg_design_layout" class="regular-text" value="Striped" placeholder="e.g., Striped, Solid, Custom">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Colors', 'twintack-custom-grips' ); ?></th>
                            <td>
                                <input type="text" name="ttcg_primary_color" placeholder="<?php esc_attr_e( 'Primary (e.g., Red)', 'twintack-custom-grips' ); ?>" class="regular-text" value="Red" style="margin-bottom:4px;"><br>
                                <input type="text" name="ttcg_secondary_color" placeholder="<?php esc_attr_e( 'Secondary (e.g., White)', 'twintack-custom-grips' ); ?>" class="regular-text" value="White" style="margin-bottom:4px;"><br>
                                <input type="text" name="ttcg_tertiary_color" placeholder="<?php esc_attr_e( 'Tertiary (e.g., Black)', 'twintack-custom-grips' ); ?>" class="regular-text" value="Black">
                            </td>
                        </tr>
                    </table>

                    <?php submit_button( __( 'Create Test Grip Design', 'twintack-custom-grips' ), 'primary', 'submit', true ); ?>
                </form>
            </div>

            <!-- Existing Grip Designs Quick Reference -->
            <div class="ttcg-admin-section">
                <h2>
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e( 'Existing Grip Designs', 'twintack-custom-grips' ); ?>
                </h2>
                <p class="description"><?php esc_html_e( 'Quick reference of all grip designs and their associated accounts.', 'twintack-custom-grips' ); ?></p>

                <?php $this->render_design_reference_table(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render a quick reference table of all existing grip designs.
     */
    private function render_design_reference_table() {
        $designs = get_posts( array(
            'post_type'      => 'grip_design',
            'post_status'    => 'any',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        if ( empty( $designs ) ) {
            echo '<p>' . esc_html__( 'No grip designs found.', 'twintack-custom-grips' ) . '</p>';
            return;
        }
        ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'ID', 'twintack-custom-grips' ); ?></th>
                    <th><?php esc_html_e( 'Title', 'twintack-custom-grips' ); ?></th>
                    <th><?php esc_html_e( 'Customer Email', 'twintack-custom-grips' ); ?></th>
                    <th><?php esc_html_e( 'WP User Match', 'twintack-custom-grips' ); ?></th>
                    <th><?php esc_html_e( 'Post Status', 'twintack-custom-grips' ); ?></th>
                    <th><?php esc_html_e( 'Artwork Status', 'twintack-custom-grips' ); ?></th>
                    <th><?php esc_html_e( 'Team Dashboard', 'twintack-custom-grips' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $designs as $design ) :
                    $email = get_post_meta( $design->ID, '_grip_customer_email', true );
                    $artwork_status = get_post_meta( $design->ID, '_grip_artwork_status', true ) ?: 'artwork_pending';
                    $matched_user = $email ? get_user_by( 'email', $email ) : false;
                ?>
                    <tr>
                        <td><strong>#<?php echo esc_html( $design->ID ); ?></strong></td>
                        <td><?php echo esc_html( $design->post_title ); ?></td>
                        <td><code><?php echo esc_html( $email ?: '—' ); ?></code></td>
                        <td>
                            <?php if ( $matched_user ) : ?>
                                <span class="ttcg-status-active"><?php echo esc_html( $matched_user->display_name ); ?></span>
                            <?php else : ?>
                                <span class="ttcg-status-inactive"><?php esc_html_e( 'No match', 'twintack-custom-grips' ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $design->post_status ); ?></td>
                        <td>
                            <span class="ttcg-status-badge" style="background-color: <?php echo esc_attr( TTCG_Dashboard::get_status_color( $artwork_status ) ); ?>; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 12px;">
                                <?php echo esc_html( TTCG_Dashboard::get_status_label( $artwork_status ) ); ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo esc_url( TTCG_Router::get_design_url( $design->ID ) ); ?>" class="button button-small"><?php esc_html_e( 'View', 'twintack-custom-grips' ); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    // ------------------------------------------------------------------
    // Email Log Page
    // ------------------------------------------------------------------

    /**
     * Render the Email Log admin page.
     */
    public function render_email_log_page() {
        ?>
        <div class="wrap ttcg-admin">
            <h1><?php esc_html_e( 'Email Log', 'twintack-custom-grips' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Recent email notifications sent by both grip plugins.', 'twintack-custom-grips' ); ?></p>

            <?php $this->render_email_log_table(); ?>
        </div>
        <?php
    }

    /**
     * Render the aggregated email log table.
     */
    private function render_email_log_table() {
        global $wpdb;

        // Get all grip designs that have email logs
        $designs_with_logs = $wpdb->get_col(
            "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
             WHERE meta_key IN ('_ttcg_email_log', '_grip_email_log')
             ORDER BY post_id DESC
             LIMIT 100"
        );

        if ( empty( $designs_with_logs ) ) {
            echo '<p>' . esc_html__( 'No email logs found yet. Emails will be logged as they are sent.', 'twintack-custom-grips' ) . '</p>';
            return;
        }

        $all_entries = array();

        foreach ( $designs_with_logs as $post_id ) {
            $title = get_the_title( $post_id );

            // TTCG logs
            $ttcg_log = get_post_meta( $post_id, '_ttcg_email_log', true );
            if ( is_array( $ttcg_log ) ) {
                foreach ( $ttcg_log as $entry ) {
                    $entry['design_id']    = $post_id;
                    $entry['design_title'] = $title;
                    $entry['source']       = 'Custom Grips';
                    $all_entries[]         = $entry;
                }
            }

            // Grip Manager logs
            $grip_log = get_post_meta( $post_id, '_grip_email_log', true );
            if ( is_array( $grip_log ) ) {
                foreach ( $grip_log as $entry ) {
                    $entry['design_id']    = $post_id;
                    $entry['design_title'] = $title;
                    $entry['source']       = 'Grip Manager';
                    $all_entries[]         = $entry;
                }
            }
        }

        // Sort by date descending
        usort( $all_entries, function ( $a, $b ) {
            $time_a = isset( $a['sent_at'] ) ? $a['sent_at'] : ( isset( $a['timestamp'] ) ? $a['timestamp'] : '' );
            $time_b = isset( $b['sent_at'] ) ? $b['sent_at'] : ( isset( $b['timestamp'] ) ? $b['timestamp'] : '' );
            return strcmp( $time_b, $time_a );
        } );

        // Only show the most recent 100
        $all_entries = array_slice( $all_entries, 0, 100 );
        ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Date', 'twintack-custom-grips' ); ?></th>
                    <th><?php esc_html_e( 'Source Plugin', 'twintack-custom-grips' ); ?></th>
                    <th><?php esc_html_e( 'Design', 'twintack-custom-grips' ); ?></th>
                    <th><?php esc_html_e( 'Recipient', 'twintack-custom-grips' ); ?></th>
                    <th><?php esc_html_e( 'Template / Type', 'twintack-custom-grips' ); ?></th>
                    <th><?php esc_html_e( 'Subject', 'twintack-custom-grips' ); ?></th>
                    <th><?php esc_html_e( 'Result', 'twintack-custom-grips' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $all_entries ) ) : ?>
                    <tr><td colspan="7"><?php esc_html_e( 'No email entries found.', 'twintack-custom-grips' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $all_entries as $entry ) :
                        $date = isset( $entry['sent_at'] ) ? $entry['sent_at'] : ( isset( $entry['timestamp'] ) ? $entry['timestamp'] : '—' );
                    ?>
                        <tr>
                            <td><?php echo esc_html( $date ); ?></td>
                            <td><?php echo esc_html( $entry['source'] ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( TTCG_Router::get_design_url( $entry['design_id'] ) ); ?>">
                                    #<?php echo esc_html( $entry['design_id'] ); ?> — <?php echo esc_html( $entry['design_title'] ); ?>
                                </a>
                            </td>
                            <td><code><?php echo esc_html( isset( $entry['to'] ) ? $entry['to'] : ( isset( $entry['recipient'] ) ? $entry['recipient'] : '—' ) ); ?></code></td>
                            <td><code><?php echo esc_html( isset( $entry['template'] ) ? $entry['template'] : ( isset( $entry['email_type'] ) ? $entry['email_type'] : '—' ) ); ?></code></td>
                            <td><?php echo esc_html( isset( $entry['subject'] ) ? $entry['subject'] : '—' ); ?></td>
                            <td>
                                <?php if ( isset( $entry['success'] ) ) : ?>
                                    <?php if ( $entry['success'] ) : ?>
                                        <span class="ttcg-status-active"><?php esc_html_e( 'Sent', 'twintack-custom-grips' ); ?></span>
                                    <?php else : ?>
                                        <span class="ttcg-status-inactive"><?php esc_html_e( 'Failed', 'twintack-custom-grips' ); ?></span>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="ttcg-status-active"><?php esc_html_e( 'Sent', 'twintack-custom-grips' ); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    // ------------------------------------------------------------------
    // Action Handlers
    // ------------------------------------------------------------------

    /**
     * Handle admin POST actions (create test design, etc.).
     */
    public function handle_admin_actions() {
        add_action( 'admin_post_ttcg_create_test_design', array( $this, 'handle_create_test_design' ) );
    }

    /**
     * Handle the create test design form submission.
     */
    public function handle_create_test_design() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'twintack-custom-grips' ) );
        }

        if ( ! wp_verify_nonce( $_POST['ttcg_nonce'] ?? '', 'ttcg_create_test_design' ) ) {
            wp_die( esc_html__( 'Security check failed', 'twintack-custom-grips' ) );
        }

        $user_id   = absint( $_POST['ttcg_user_id'] ?? 0 );
        $user      = get_user_by( 'ID', $user_id );

        if ( ! $user ) {
            wp_safe_redirect( add_query_arg( 'ttcg_error', 'Invalid user selected.', admin_url( 'admin.php?page=ttcg-test-tools' ) ) );
            exit;
        }

        $team_name       = sanitize_text_field( $_POST['ttcg_team_name'] ?? 'Test Team' );
        $design_type     = sanitize_text_field( $_POST['ttcg_design_type'] ?? 'bat-grip' );
        $quantity        = absint( $_POST['ttcg_quantity'] ?? 25 );
        $artwork_status  = sanitize_text_field( $_POST['ttcg_artwork_status'] ?? 'artwork_pending' );
        $design_layout   = sanitize_text_field( $_POST['ttcg_design_layout'] ?? '' );
        $primary_color   = sanitize_text_field( $_POST['ttcg_primary_color'] ?? '' );
        $secondary_color = sanitize_text_field( $_POST['ttcg_secondary_color'] ?? '' );
        $tertiary_color  = sanitize_text_field( $_POST['ttcg_tertiary_color'] ?? '' );

        // Create the grip design post
        $post_id = wp_insert_post( array(
            'post_type'   => 'grip_design',
            'post_status' => 'publish',
            'post_title'  => sprintf( '%s — %s (Test)', $team_name, $user->display_name ),
            'post_author' => $user_id,
        ) );

        if ( is_wp_error( $post_id ) ) {
            wp_safe_redirect( add_query_arg( 'ttcg_error', $post_id->get_error_message(), admin_url( 'admin.php?page=ttcg-test-tools' ) ) );
            exit;
        }

        // Set all meta fields
        update_post_meta( $post_id, '_grip_customer_name', $user->display_name );
        update_post_meta( $post_id, '_grip_customer_email', $user->user_email );
        update_post_meta( $post_id, '_grip_team_name', $team_name );
        update_post_meta( $post_id, '_grip_design_type', $design_type );
        update_post_meta( $post_id, '_grip_quantity', $quantity );
        update_post_meta( $post_id, '_grip_artwork_status', $artwork_status );
        update_post_meta( $post_id, '_grip_design_layout', $design_layout );
        update_post_meta( $post_id, '_grip_primary_color', $primary_color );
        update_post_meta( $post_id, '_grip_secondary_color', $secondary_color );
        update_post_meta( $post_id, '_grip_tertiary_color', $tertiary_color );
        update_post_meta( $post_id, '_grip_form_type', 'test' );

        if ( WP_DEBUG ) {
            error_log( sprintf( 'TTCG Admin: Created test grip design #%d for user %s (%s)', $post_id, $user->display_name, $user->user_email ) );
        }

        wp_safe_redirect( add_query_arg( 'ttcg_created', $post_id, admin_url( 'admin.php?page=ttcg-test-tools' ) ) );
        exit;
    }

    // ------------------------------------------------------------------
    // AJAX: Toggle Notification Preference
    // ------------------------------------------------------------------

    /**
     * AJAX handler to toggle a user's notification preference on/off.
     *
     * Expects POST data:
     * - user_id  : int    — The user whose preference to change.
     * - type     : string — The notification type slug (e.g. 'message-notification').
     * - enabled  : string — '1' for on, '0' for off.
     */
    public function ajax_toggle_notification() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'twintack-custom-grips' ) ), 403 );
        }

        if ( ! check_ajax_referer( 'ttcg_toggle_notification', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'twintack-custom-grips' ) ), 403 );
        }

        $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
        $type    = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
        $enabled = isset( $_POST['enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['enabled'] ) ) : '1';

        if ( ! $user_id || empty( $type ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required fields.', 'twintack-custom-grips' ) ) );
        }

        $user = get_user_by( 'ID', $user_id );
        if ( ! $user ) {
            wp_send_json_error( array( 'message' => __( 'User not found.', 'twintack-custom-grips' ) ) );
        }

        // Valid notification types
        $valid_types = array( 'message-notification', 'status-update', 'mockup-ready' );
        if ( ! in_array( $type, $valid_types, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid notification type.', 'twintack-custom-grips' ) ) );
        }

        // Get current prefs
        $prefs = get_user_meta( $user_id, '_ttcg_notification_prefs', true );
        if ( ! is_array( $prefs ) ) {
            $prefs = array();
        }

        // Set the preference
        if ( '1' === $enabled ) {
            // Turning on — remove the opt-out entry
            unset( $prefs[ $type ] );
        } else {
            // Turning off — mark as opted out
            $prefs[ $type ] = 'off';
        }

        update_user_meta( $user_id, '_ttcg_notification_prefs', $prefs );

        if ( WP_DEBUG ) {
            error_log( sprintf(
                'TTCG Admin: Notification "%s" %s for user #%d (%s) by admin #%d.',
                $type,
                '1' === $enabled ? 'enabled' : 'disabled',
                $user_id,
                $user->display_name,
                get_current_user_id()
            ) );
        }

        wp_send_json_success( array(
            'message'  => sprintf(
                /* translators: 1: notification type, 2: on/off, 3: user name */
                __( '%1$s %2$s for %3$s.', 'twintack-custom-grips' ),
                ucwords( str_replace( '-', ' ', $type ) ),
                '1' === $enabled ? __( 'enabled', 'twintack-custom-grips' ) : __( 'disabled', 'twintack-custom-grips' ),
                $user->display_name
            ),
            'user_id'  => $user_id,
            'type'     => $type,
            'enabled'  => $enabled,
        ) );
    }

    // ------------------------------------------------------------------
    // Plugin Settings
    // ------------------------------------------------------------------

    /**
     * Render the Plugin Settings section with toggle switches.
     */
    private function render_plugin_settings() {
        $hide_legacy = get_option( 'ttcg_hide_legacy_grip_designs', '0' );
        ?>
        <table class="widefat ttcg-notification-table ttcg-settings-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Setting', 'twintack-custom-grips' ); ?></th>
                    <th><?php esc_html_e( 'Description', 'twintack-custom-grips' ); ?></th>
                    <th style="text-align: center;"><?php esc_html_e( 'Status', 'twintack-custom-grips' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong><?php esc_html_e( 'Hide Legacy "My Grip Designs"', 'twintack-custom-grips' ); ?></strong></td>
                    <td>
                        <?php esc_html_e( 'Remove the "My Grip Designs" menu item from WooCommerce My Account (registered by twintack-grip-manager). The new "My Custom Grips" experience replaces it. Grip Manager remains active for form intake, post creation, and WooCommerce order sync.', 'twintack-custom-grips' ); ?>
                    </td>
                    <td style="text-align: center;">
                        <label class="ttcg-toggle">
                            <input type="checkbox"
                                   class="ttcg-setting-toggle"
                                   data-setting="ttcg_hide_legacy_grip_designs"
                                   <?php checked( $hide_legacy, '1' ); ?>>
                            <span class="ttcg-toggle__slider"></span>
                        </label>
                        <br>
                        <span class="ttcg-setting-label"><?php echo '1' === $hide_legacy ? esc_html__( 'Hidden', 'twintack-custom-grips' ) : esc_html__( 'Visible', 'twintack-custom-grips' ); ?></span>
                    </td>
                </tr>
            </tbody>
        </table>
        <p class="description" style="margin-top: 10px;">
            <span class="dashicons dashicons-info" style="color: #0073aa;"></span>
            <?php esc_html_e( 'Toggling these settings takes effect immediately. The twintack-grip-manager plugin remains fully active — only the My Account menu item visibility is affected.', 'twintack-custom-grips' ); ?>
        </p>
        <?php
    }

    /**
     * AJAX handler for toggling plugin settings.
     *
     * Expected POST:
     * - nonce   : string — security nonce.
     * - setting : string — the option key.
     * - enabled : string — '1' for on, '0' for off.
     */
    public function ajax_toggle_setting() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'twintack-custom-grips' ) ), 403 );
        }

        if ( ! check_ajax_referer( 'ttcg_toggle_notification', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'twintack-custom-grips' ) ), 403 );
        }

        $setting = isset( $_POST['setting'] ) ? sanitize_key( wp_unslash( $_POST['setting'] ) ) : '';
        $enabled = isset( $_POST['enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['enabled'] ) ) : '0';

        // Whitelist of allowed settings
        $allowed_settings = array( 'ttcg_hide_legacy_grip_designs' );
        if ( ! in_array( $setting, $allowed_settings, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid setting.', 'twintack-custom-grips' ) ) );
        }

        update_option( $setting, $enabled );

        $labels = array(
            'ttcg_hide_legacy_grip_designs' => __( 'Legacy "My Grip Designs" menu', 'twintack-custom-grips' ),
        );

        $label = isset( $labels[ $setting ] ) ? $labels[ $setting ] : $setting;

        if ( WP_DEBUG ) {
            error_log( sprintf(
                'TTCG Admin: Setting "%s" changed to "%s" by admin #%d.',
                $setting,
                $enabled,
                get_current_user_id()
            ) );
        }

        wp_send_json_success( array(
            'message' => sprintf(
                /* translators: 1: setting label, 2: status */
                __( '%1$s is now %2$s.', 'twintack-custom-grips' ),
                $label,
                '1' === $enabled ? __( 'hidden', 'twintack-custom-grips' ) : __( 'visible', 'twintack-custom-grips' )
            ),
            'setting' => $setting,
            'enabled' => $enabled,
        ) );
    }

    /**
     * Remove the legacy "My Grip Designs" (grip-designs) menu item from WooCommerce My Account.
     *
     * Runs at priority 99 so it fires after the grip-manager registers its menu item.
     *
     * @param  array $items Menu items.
     * @return array
     */
    public function remove_legacy_grip_designs_menu( $items ) {
        unset( $items['grip-designs'] );
        return $items;
    }

    // ------------------------------------------------------------------
    // Admin CSS
    // ------------------------------------------------------------------

    /**
     * Get inline CSS for admin pages.
     *
     * @return string
     */
    private function get_admin_css() {
        return '
            .ttcg-admin { max-width: 1200px; }

            .ttcg-admin-section {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 20px;
                margin: 20px 0;
            }

            .ttcg-admin-section h2 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .ttcg-notification-table th,
            .ttcg-notification-table td {
                padding: 12px 10px;
                vertical-align: top;
            }

            .ttcg-notification-table code {
                background: #f0f0f1;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 12px;
            }

            .ttcg-badge {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 600;
                margin: 1px 2px;
            }

            .ttcg-badge--customer { background: #d1ecf1; color: #0c5460; }
            .ttcg-badge--team { background: #d4edda; color: #155724; }
            .ttcg-badge--admin { background: #e2d5f1; color: #4a1a8a; }
            .ttcg-badge--webhook { background: #fff3cd; color: #856404; }

            .ttcg-status-active {
                display: inline-block;
                padding: 2px 8px;
                background: #d4edda;
                color: #155724;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 600;
            }

            .ttcg-status-inactive {
                display: inline-block;
                padding: 2px 8px;
                background: #f8d7da;
                color: #721c24;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 600;
            }

            .ttcg-flow-diagram {
                display: flex;
                gap: 30px;
                flex-wrap: wrap;
            }

            .ttcg-flow-column {
                flex: 1;
                min-width: 300px;
            }

            .ttcg-flow-column h3 {
                margin-top: 0;
                padding-bottom: 8px;
                border-bottom: 2px solid #eee;
            }

            .ttcg-flow-item {
                padding: 10px 15px;
                margin: 8px 0;
                border-radius: 4px;
                font-size: 13px;
                border-left: 4px solid;
            }

            .ttcg-flow-item--customer {
                background: #f0f8ff;
                border-left-color: #0073aa;
            }

            .ttcg-flow-item--team {
                background: #f0fff0;
                border-left-color: #46b450;
            }

            /* Toggle switches */
            .ttcg-prefs-table__toggle-col {
                text-align: center;
                width: 140px;
            }

            .ttcg-toggle {
                position: relative;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                cursor: pointer;
                user-select: none;
            }

            .ttcg-toggle__input,
            .ttcg-setting-toggle {
                position: absolute;
                opacity: 0;
                width: 0;
                height: 0;
            }

            .ttcg-toggle__slider {
                position: relative;
                display: inline-block;
                width: 40px;
                height: 22px;
                background: #ccc;
                border-radius: 22px;
                transition: background 0.25s ease;
            }

            .ttcg-toggle__slider::before {
                content: "";
                position: absolute;
                top: 3px;
                left: 3px;
                width: 16px;
                height: 16px;
                background: #fff;
                border-radius: 50%;
                transition: transform 0.25s ease;
                box-shadow: 0 1px 3px rgba(0,0,0,0.2);
            }

            .ttcg-toggle__input:checked + .ttcg-toggle__slider,
            .ttcg-setting-toggle:checked + .ttcg-toggle__slider {
                background: #46b450;
            }

            .ttcg-toggle__input:checked + .ttcg-toggle__slider::before,
            .ttcg-setting-toggle:checked + .ttcg-toggle__slider::before {
                transform: translateX(18px);
            }

            .ttcg-toggle__input:disabled + .ttcg-toggle__slider,
            .ttcg-setting-toggle:disabled + .ttcg-toggle__slider {
                opacity: 0.5;
                cursor: wait;
            }

            .ttcg-setting-label {
                display: inline-block;
                margin-top: 4px;
                font-size: 11px;
                font-weight: 600;
                color: #666;
            }

            .ttcg-toggle__label {
                font-size: 11px;
                font-weight: 600;
                min-width: 20px;
            }

            .ttcg-toggle__label--on {
                display: none;
                color: #155724;
            }

            .ttcg-toggle__label--off {
                display: inline;
                color: #721c24;
            }

            .ttcg-toggle__input:checked ~ .ttcg-toggle__label--on {
                display: inline;
            }

            .ttcg-toggle__input:checked ~ .ttcg-toggle__label--off {
                display: none;
            }

            .ttcg-toggle-all-btn {
                font-size: 11px !important;
                min-height: 26px !important;
                line-height: 24px !important;
                padding: 0 8px !important;
            }

            .ttcg-prefs-notice {
                margin-top: 12px;
                padding: 8px 14px;
                border-radius: 4px;
                font-size: 13px;
                transition: opacity 0.3s ease;
            }

            .ttcg-prefs-notice--success {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }

            .ttcg-prefs-notice--error {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }

            .ttcg-prefs-table tbody tr {
                transition: background 0.15s ease;
            }

            .ttcg-prefs-table tbody tr.ttcg-row-saving {
                background: #fffbe6;
            }

            .ttcg-prefs-table tbody tr.ttcg-row-saved {
                background: #edfaef;
            }
        ';
    }

    // ------------------------------------------------------------------
    // Admin JS
    // ------------------------------------------------------------------

    /**
     * Get inline JavaScript for admin toggle interactions.
     *
     * @return string
     */
    private function get_admin_js() {
        return "
            jQuery(document).ready(function($) {
                // Single toggle handler
                $('.ttcg-toggle__input').on('change', function() {
                    var \$checkbox = $(this);
                    var userId    = \$checkbox.data('user-id');
                    var type      = \$checkbox.data('type');
                    var enabled   = \$checkbox.is(':checked') ? '1' : '0';
                    var \$row      = \$checkbox.closest('tr');

                    // Disable while saving
                    \$checkbox.prop('disabled', true);
                    \$row.addClass('ttcg-row-saving');

                    $.ajax({
                        url: ttcg_admin.ajax_url,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action:  'ttcg_toggle_notification',
                            nonce:   ttcg_admin.nonce,
                            user_id: userId,
                            type:    type,
                            enabled: enabled
                        },
                        success: function(response) {
                            \$row.removeClass('ttcg-row-saving');
                            if (response.success) {
                                showNotice(response.data.message, 'success');
                                \$row.addClass('ttcg-row-saved');
                                setTimeout(function() { \$row.removeClass('ttcg-row-saved'); }, 1500);
                                updateToggleAllButton(\$row);
                            } else {
                                // Revert
                                \$checkbox.prop('checked', !  \$checkbox.is(':checked'));
                                showNotice(response.data.message || 'Error saving preference.', 'error');
                            }
                        },
                        error: function() {
                            \$row.removeClass('ttcg-row-saving');
                            \$checkbox.prop('checked', ! \$checkbox.is(':checked'));
                            showNotice('Connection error. Please try again.', 'error');
                        },
                        complete: function() {
                            \$checkbox.prop('disabled', false);
                        }
                    });
                });

                // Toggle All button
                $('.ttcg-toggle-all-btn').on('click', function() {
                    var \$btn    = $(this);
                    var userId  = \$btn.data('user-id');
                    var state   = \$btn.data('state'); // current majority state
                    var newState = (state === 'on') ? false : true; // flip it
                    var \$row    = \$btn.closest('tr');
                    var \$checks = \$row.find('.ttcg-toggle__input');
                    var pending = \$checks.length;

                    \$btn.prop('disabled', true).text('Saving...');

                    \$checks.each(function() {
                        var \$cb = $(this);
                        // Only toggle if it doesn't already match target
                        if (\$cb.is(':checked') !== newState) {
                            \$cb.prop('checked', newState);

                            $.ajax({
                                url: ttcg_admin.ajax_url,
                                type: 'POST',
                                dataType: 'json',
                                data: {
                                    action:  'ttcg_toggle_notification',
                                    nonce:   ttcg_admin.nonce,
                                    user_id: userId,
                                    type:    \$cb.data('type'),
                                    enabled: newState ? '1' : '0'
                                },
                                success: function() {
                                    pending--;
                                    if (pending <= 0) {
                                        finishToggleAll(\$btn, \$row, newState);
                                    }
                                },
                                error: function() {
                                    pending--;
                                    if (pending <= 0) {
                                        finishToggleAll(\$btn, \$row, newState);
                                    }
                                }
                            });
                        } else {
                            pending--;
                            if (pending <= 0) {
                                finishToggleAll(\$btn, \$row, newState);
                            }
                        }
                    });
                });

                function finishToggleAll(\$btn, \$row, newState) {
                    \$btn.prop('disabled', false);
                    if (newState) {
                        \$btn.data('state', 'on').text('Disable All');
                        showNotice('All notifications enabled for ' + \$row.find('td:first strong').text() + '.', 'success');
                    } else {
                        \$btn.data('state', 'off').text('Enable All');
                        showNotice('All notifications disabled for ' + \$row.find('td:first strong').text() + '.', 'success');
                    }
                    \$row.addClass('ttcg-row-saved');
                    setTimeout(function() { \$row.removeClass('ttcg-row-saved'); }, 1500);
                }

                function updateToggleAllButton(\$row) {
                    var \$checks = \$row.find('.ttcg-toggle__input');
                    var \$btn    = \$row.find('.ttcg-toggle-all-btn');
                    var allOn   = true;
                    \$checks.each(function() {
                        if (! $(this).is(':checked')) allOn = false;
                    });
                    if (allOn) {
                        \$btn.data('state', 'on').text('Disable All');
                    } else {
                        \$btn.data('state', 'off').text('Enable All');
                    }
                }

                function showNotice(msg, type) {
                    var \$notice = $('#ttcg-prefs-save-notice');
                    \$notice
                        .removeClass('ttcg-prefs-notice--success ttcg-prefs-notice--error')
                        .addClass('ttcg-prefs-notice--' + type)
                        .text(msg)
                        .stop(true)
                        .fadeIn(200);

                    clearTimeout(\$notice.data('timer'));
                    \$notice.data('timer', setTimeout(function() {
                        \$notice.fadeOut(300);
                    }, 3000));
                }

                // Plugin Settings toggle handler
                $('.ttcg-setting-toggle').on('change', function() {
                    var \$checkbox = $(this);
                    var setting   = \$checkbox.data('setting');
                    var enabled   = \$checkbox.is(':checked') ? '1' : '0';
                    var \$cell     = \$checkbox.closest('td');
                    var \$label    = \$cell.find('.ttcg-setting-label');

                    \$checkbox.prop('disabled', true);

                    $.ajax({
                        url: ttcg_admin.ajax_url,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action:  'ttcg_toggle_setting',
                            nonce:   ttcg_admin.nonce,
                            setting: setting,
                            enabled: enabled
                        },
                        success: function(response) {
                            if (response.success) {
                                showNotice(response.data.message, 'success');
                                \$label.text(enabled === '1' ? 'Hidden' : 'Visible');
                            } else {
                                \$checkbox.prop('checked', ! \$checkbox.is(':checked'));
                                showNotice(response.data.message || 'Error saving setting.', 'error');
                            }
                        },
                        error: function() {
                            \$checkbox.prop('checked', ! \$checkbox.is(':checked'));
                            showNotice('Connection error. Please try again.', 'error');
                        },
                        complete: function() {
                            \$checkbox.prop('disabled', false);
                        }
                    });
                });
            });
        ";
    }
}
