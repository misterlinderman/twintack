<?php
/**
 * Design detail partial — full single-design view.
 *
 * Included from templates/dashboard.php when ttcg_page = design-detail.
 *
 * @package TwinTack_Custom_Grips
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$data        = TTCG_Dashboard::get_design_data( $design_id );
$status      = $data['artwork_status'];
$status_label = TTCG_Dashboard::get_status_label( $status );
$status_color = TTCG_Dashboard::get_status_color( $status );

$can_edit_details = current_user_can( 'edit_grip_details' );
$can_edit_designs = current_user_can( 'edit_grip_designs' );
$can_upload       = current_user_can( 'manage_grip_mockups' );
$can_message      = current_user_can( 'send_grip_messages' );

$allowed_statuses = TTCG_Status::get_allowed_statuses();
$all_statuses     = TTCG_Dashboard::get_statuses();

// Mockup history
$mockup_history = TTCG_Mockups::get_history( $design_id );

// Color helper
$color_map = array(
    'Red' => '#c0392b', 'Blue' => '#2980b9', 'Green' => '#27ae60',
    'Yellow' => '#f1c40f', 'Orange' => '#e67e22', 'Purple' => '#8e44ad',
    'Pink' => '#e91e63', 'Black' => '#2c3e50', 'White' => '#ecf0f1',
    'Gray' => '#95a5a6', 'Navy' => '#2c3e50', 'Maroon' => '#800000',
    'Teal' => '#008080', 'Gold' => '#d4a017', 'Silver' => '#c0c0c0',
);
?>

<div class="ttcg-detail" data-design-id="<?php echo esc_attr( $design_id ); ?>">

    <!-- Back Link -->
    <div class="ttcg-detail__back">
        <a href="<?php echo esc_url( TTCG_Router::get_dashboard_url() ); ?>" class="ttcg-detail__back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
            <?php esc_html_e( 'Back to All Designs', 'twintack-custom-grips' ); ?>
        </a>
    </div>

    <!-- Title + Status -->
    <div class="ttcg-detail__header">
        <div class="ttcg-detail__title-area">
            <h2 class="ttcg-detail__title"><?php echo esc_html( $data['title'] ); ?></h2>
            <span class="ttcg-detail__id">#<?php echo esc_html( $design_id ); ?></span>
            <span class="ttcg-detail__date"><?php echo esc_html( $data['date'] ); ?></span>
        </div>
        <div class="ttcg-detail__status-area">
            <span class="ttcg-status-badge ttcg-status-badge--large" id="ttcg-status-badge"
                  style="background-color: <?php echo esc_attr( $status_color ); ?>;">
                <?php echo esc_html( $status_label ); ?>
            </span>
            <?php if ( current_user_can( 'delete_grip_designs' ) ) : ?>
                <button type="button" class="ttcg-btn ttcg-btn--small ttcg-btn--danger" id="ttcg-delete-design"
                        data-design-id="<?php echo esc_attr( $design_id ); ?>"
                        data-design-title="<?php echo esc_attr( $data['title'] ); ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                    <?php esc_html_e( 'Delete', 'twintack-custom-grips' ); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="ttcg-detail__grid">

        <!-- Left Column: Visual + Upload -->
        <div class="ttcg-detail__col ttcg-detail__col--left">

            <!-- Mockup Section -->
            <div class="ttcg-detail__section">
                <h3 class="ttcg-detail__section-title"><?php esc_html_e( 'Mockup', 'twintack-custom-grips' ); ?></h3>
                <div class="ttcg-detail__mockup" id="ttcg-mockup-display">
                    <?php if ( ! empty( $data['mockup_display_url'] ) ) : ?>
                        <img src="<?php echo esc_url( $data['mockup_display_url'] ); ?>"
                             alt="<?php echo esc_attr( $data['title'] ); ?> mockup"
                             class="ttcg-detail__mockup-img" id="ttcg-mockup-img">
                    <?php else : ?>
                        <div class="ttcg-detail__mockup-empty" id="ttcg-mockup-empty">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                            <p><?php esc_html_e( 'No mockup uploaded yet', 'twintack-custom-grips' ); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ( $can_upload ) : ?>
                    <?php include TTCG_PLUGIN_DIR . 'templates/partials/mockup-upload.php'; ?>
                <?php endif; ?>

                <?php if ( ! empty( $mockup_history ) ) : ?>
                    <div class="ttcg-detail__mockup-history">
                        <h4 class="ttcg-detail__subsection-title"><?php esc_html_e( 'Previous Versions', 'twintack-custom-grips' ); ?></h4>
                        <div class="ttcg-detail__history-grid">
                            <?php foreach ( array_reverse( $mockup_history ) as $i => $version ) : ?>
                                <div class="ttcg-detail__history-item">
                                    <img src="<?php echo esc_url( $version['url'] ); ?>"
                                         alt="<?php echo esc_attr( sprintf( 'Version %d', count( $mockup_history ) - $i ) ); ?>"
                                         class="ttcg-detail__history-thumb" loading="lazy">
                                    <span class="ttcg-detail__history-date">
                                        <?php echo esc_html( date_i18n( 'M j, g:ia', strtotime( $version['replaced_at'] ) ) ); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Original Artwork -->
            <?php if ( ! empty( $data['artwork_url'] ) ) : ?>
                <div class="ttcg-detail__section">
                    <h3 class="ttcg-detail__section-title"><?php esc_html_e( 'Original Artwork', 'twintack-custom-grips' ); ?></h3>
                    <div class="ttcg-detail__artwork">
                        <img src="<?php echo esc_url( $data['artwork_url'] ); ?>"
                             alt="<?php esc_attr_e( 'Original artwork', 'twintack-custom-grips' ); ?>"
                             class="ttcg-detail__artwork-img" loading="lazy">
                        <a href="<?php echo esc_url( $data['artwork_url'] ); ?>"
                           download class="ttcg-btn ttcg-btn--small ttcg-btn--secondary">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            <?php esc_html_e( 'Download', 'twintack-custom-grips' ); ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Info + Status + Messages -->
        <div class="ttcg-detail__col ttcg-detail__col--right">

            <!-- Status Controls -->
            <?php if ( ! empty( $allowed_statuses ) ) : ?>
                <?php include TTCG_PLUGIN_DIR . 'templates/partials/status-controls.php'; ?>
            <?php endif; ?>

            <!-- Customer & Design Info -->
            <div class="ttcg-detail__section">
                <div class="ttcg-detail__section-header">
                    <h3 class="ttcg-detail__section-title"><?php esc_html_e( 'Design Details', 'twintack-custom-grips' ); ?></h3>
                    <?php if ( $can_edit_details || $can_edit_designs ) : ?>
                        <button type="button" class="ttcg-btn ttcg-btn--small ttcg-btn--ghost" id="ttcg-toggle-edit">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            <?php esc_html_e( 'Edit', 'twintack-custom-grips' ); ?>
                        </button>
                    <?php endif; ?>
                </div>

                <?php include TTCG_PLUGIN_DIR . 'templates/partials/design-edit-form.php'; ?>
            </div>

            <!-- Order Info -->
            <?php if ( ! empty( $data['order_id'] ) || ! empty( $data['final_order_id'] ) ) : ?>
                <div class="ttcg-detail__section">
                    <h3 class="ttcg-detail__section-title"><?php esc_html_e( 'Order Information', 'twintack-custom-grips' ); ?></h3>
                    <div class="ttcg-detail__info-grid">
                        <?php if ( ! empty( $data['order_id'] ) ) : ?>
                            <div class="ttcg-detail__info-row">
                                <span class="ttcg-detail__label"><?php esc_html_e( 'Deposit Order', 'twintack-custom-grips' ); ?></span>
                                <span class="ttcg-detail__value">
                                    <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $data['order_id'] . '&action=edit' ) ); ?>" target="_blank">
                                        #<?php echo esc_html( $data['order_id'] ); ?>
                                    </a>
                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if ( ! empty( $data['final_order_id'] ) ) : ?>
                            <div class="ttcg-detail__info-row">
                                <span class="ttcg-detail__label"><?php esc_html_e( 'Production Order', 'twintack-custom-grips' ); ?></span>
                                <span class="ttcg-detail__value">
                                    <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $data['final_order_id'] . '&action=edit' ) ); ?>" target="_blank">
                                        #<?php echo esc_html( $data['final_order_id'] ); ?>
                                    </a>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Message Thread -->
            <div class="ttcg-detail__section ttcg-detail__section--messages">
                <h3 class="ttcg-detail__section-title"><?php esc_html_e( 'Messages', 'twintack-custom-grips' ); ?></h3>
                <?php include TTCG_PLUGIN_DIR . 'templates/partials/message-thread.php'; ?>
            </div>
        </div>
    </div>
</div>
