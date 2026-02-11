<?php
/**
 * Main dashboard wrapper template.
 *
 * Loads wp_head() / wp_footer() for scripts, then renders the
 * dashboard shell with sidebar navigation and the appropriate
 * content partial (list or detail view).
 *
 * @package TwinTack_Custom_Grips
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Enqueue dashboard assets
TTCG_Dashboard::enqueue_assets();

$current_page = get_query_var( 'ttcg_current_page', 'dashboard' );
$design_id    = absint( get_query_var( 'ttcg_design_id', 0 ) );
$current_user = wp_get_current_user();
$role_label   = TTCG_Roles::get_user_role_label();
$role_slug    = TTCG_Roles::get_user_role_slug();

// Status counts for sidebar filters
$status_counts = TTCG_Dashboard::get_status_counts();
$statuses      = TTCG_Dashboard::get_statuses();

// Current filter state
$active_status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
$search_term   = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html__( 'Team Dashboard', 'twintack-custom-grips' ); ?> — <?php bloginfo( 'name' ); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php wp_head(); ?>
</head>
<body class="ttcg-dashboard-body">

    <!-- Top Header Bar -->
    <header class="ttcg-header">
        <div class="ttcg-header__inner">
            <div class="ttcg-header__brand">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ttcg-header__logo">
                    <?php
                    $custom_logo_id = get_theme_mod( 'custom_logo' );
                    if ( $custom_logo_id ) {
                        echo wp_get_attachment_image( $custom_logo_id, 'medium', false, array( 'class' => 'ttcg-header__logo-img' ) );
                    } else {
                        echo '<span class="ttcg-header__logo-text">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
                    }
                    ?>
                </a>
                <h1 class="ttcg-header__title"><?php esc_html_e( 'Grip Design Dashboard', 'twintack-custom-grips' ); ?></h1>
            </div>
            <div class="ttcg-header__user">
                <span class="ttcg-header__role-badge ttcg-role--<?php echo esc_attr( $role_slug ); ?>">
                    <?php echo esc_html( $role_label ); ?>
                </span>
                <span class="ttcg-header__username"><?php echo esc_html( $current_user->display_name ); ?></span>
                <a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="ttcg-header__link"><?php esc_html_e( 'My Account', 'twintack-custom-grips' ); ?></a>
                <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="ttcg-header__link ttcg-header__link--logout"><?php esc_html_e( 'Log Out', 'twintack-custom-grips' ); ?></a>
            </div>
        </div>
    </header>

    <div class="ttcg-dashboard">
        <!-- Sidebar Navigation -->
        <aside class="ttcg-sidebar" id="ttcg-sidebar">
            <button class="ttcg-sidebar__toggle" id="ttcg-sidebar-toggle" aria-label="<?php esc_attr_e( 'Toggle sidebar', 'twintack-custom-grips' ); ?>">
                <span class="ttcg-sidebar__toggle-icon"></span>
            </button>

            <nav class="ttcg-sidebar__nav">
                <?php if ( current_user_can( 'create_grip_designs' ) ) : ?>
                <div class="ttcg-sidebar__section">
                    <a href="<?php echo esc_url( TTCG_Router::get_new_design_url() ); ?>" class="ttcg-btn ttcg-btn--primary" style="width: 100%; justify-content: center;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <?php esc_html_e( 'New Grip Design', 'twintack-custom-grips' ); ?>
                    </a>
                </div>
                <?php endif; ?>

                <div class="ttcg-sidebar__section">
                    <h3 class="ttcg-sidebar__heading"><?php esc_html_e( 'Designs', 'twintack-custom-grips' ); ?></h3>
                    <ul class="ttcg-sidebar__list">
                        <li>
                            <a href="<?php echo esc_url( TTCG_Router::get_dashboard_url() ); ?>"
                               class="ttcg-sidebar__link <?php echo empty( $active_status ) && 'dashboard' === $current_page ? 'is-active' : ''; ?>">
                                <span class="ttcg-sidebar__link-text"><?php esc_html_e( 'All Designs', 'twintack-custom-grips' ); ?></span>
                                <span class="ttcg-sidebar__count"><?php echo esc_html( $status_counts['all'] ); ?></span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="ttcg-sidebar__section">
                    <h3 class="ttcg-sidebar__heading"><?php esc_html_e( 'By Status', 'twintack-custom-grips' ); ?></h3>
                    <ul class="ttcg-sidebar__list">
                        <?php foreach ( $statuses as $slug => $info ) :
                            $count = isset( $status_counts[ $slug ] ) ? $status_counts[ $slug ] : 0;
                            if ( 0 === $count ) continue;
                        ?>
                            <li>
                                <a href="<?php echo esc_url( TTCG_Router::get_filtered_url( array( 'status' => $slug ) ) ); ?>"
                                   class="ttcg-sidebar__link <?php echo $active_status === $slug ? 'is-active' : ''; ?>">
                                    <span class="ttcg-sidebar__status-dot" style="background-color: <?php echo esc_attr( $info['color'] ); ?>;"></span>
                                    <span class="ttcg-sidebar__link-text"><?php echo esc_html( $info['label'] ); ?></span>
                                    <span class="ttcg-sidebar__count"><?php echo esc_html( $count ); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="ttcg-sidebar__section">
                    <h3 class="ttcg-sidebar__heading"><?php esc_html_e( 'Search', 'twintack-custom-grips' ); ?></h3>
                    <form method="get" action="<?php echo esc_url( TTCG_Router::get_dashboard_url() ); ?>" class="ttcg-sidebar__search">
                        <input type="text"
                               name="search"
                               value="<?php echo esc_attr( $search_term ); ?>"
                               placeholder="<?php esc_attr_e( 'Name, email, team…', 'twintack-custom-grips' ); ?>"
                               class="ttcg-sidebar__search-input">
                        <button type="submit" class="ttcg-sidebar__search-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </button>
                    </form>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="ttcg-main">
            <?php
            if ( 'new-design' === $current_page ) {
                // New grip design form
                include TTCG_PLUGIN_DIR . 'templates/partials/new-design.php';
            } elseif ( 'design-detail' === $current_page && $design_id ) {
                // Single design detail view
                include TTCG_PLUGIN_DIR . 'templates/partials/design-detail.php';
            } else {
                // List view
                include TTCG_PLUGIN_DIR . 'templates/partials/design-list.php';
            }
            ?>
        </main>
    </div>

    <?php wp_footer(); ?>
</body>
</html>
