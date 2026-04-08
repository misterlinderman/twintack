<?php
/**
 * Customer grip designs list template.
 *
 * Available variables:
 * - $designs : array of design data arrays from TTCG_Dashboard::get_design_data()
 * - $current_user : WP_User object
 *
 * @package TwinTack_Custom_Grips
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="ttcg-my-grips">
    <div class="ttcg-my-grips__header">
        <h2 class="ttcg-my-grips__title"><?php esc_html_e( 'My Custom Grips', 'twintack-custom-grips' ); ?></h2>
        <p class="ttcg-my-grips__subtitle"><?php esc_html_e( 'View your custom grip designs, communicate with our design team, and manage approvals.', 'twintack-custom-grips' ); ?></p>
    </div>

    <?php if ( empty( $designs ) ) : ?>

        <div class="ttcg-my-grips__empty">
            <div class="ttcg-my-grips__empty-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                    <circle cx="8.5" cy="8.5" r="1.5"/>
                    <path d="m21 15-5-5L5 21"/>
                </svg>
            </div>
            <h3><?php esc_html_e( 'No Custom Grips Yet', 'twintack-custom-grips' ); ?></h3>
            <p><?php esc_html_e( 'Once you submit a custom grip design order, it will appear here.', 'twintack-custom-grips' ); ?></p>
        </div>

    <?php else : ?>

        <div class="ttcg-my-grips__grid">
            <?php foreach ( $designs as $design ) :
                $status       = $design['artwork_status'];
                $status_label = TTCG_Dashboard::get_status_label( $status );
                $status_color = TTCG_Dashboard::get_status_color( $status );
                $detail_url   = TTCG_Customer::get_detail_url( $design['id'] );
                $has_image    = ! empty( $design['mockup_display_url'] );

                // Get unread message count for this design
                $unread = class_exists( 'TTCG_Messaging' ) ? TTCG_Messaging::get_unread_count( $design['id'] ) : 0;

                // Determine action hint based on status
                $action_hint = '';
                switch ( $status ) {
                    case 'pending_review':
                        $action_hint = __( 'Review Required', 'twintack-custom-grips' );
                        break;
                    case 'customer_approved':
                        $action_hint = __( 'Ready to Purchase', 'twintack-custom-grips' );
                        break;
                    case 'customer_requested_changes':
                        $action_hint = __( 'Changes Submitted', 'twintack-custom-grips' );
                        break;
                    case 'shipped':
                        $action_hint = __( 'Reorder available', 'twintack-custom-grips' );
                        break;
                }
            ?>
                <a href="<?php echo esc_url( $detail_url ); ?>" class="ttcg-my-grips__card">
                    <div class="ttcg-my-grips__card-image">
                        <?php if ( $has_image ) : ?>
                            <img src="<?php echo esc_url( $design['mockup_display_url'] ); ?>"
                                 alt="<?php echo esc_attr( $design['title'] ); ?>"
                                 loading="lazy">
                        <?php else : ?>
                            <div class="ttcg-my-grips__card-placeholder">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <path d="m21 15-5-5L5 21"/>
                                </svg>
                                <span><?php esc_html_e( 'Design in Progress', 'twintack-custom-grips' ); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ( $unread > 0 ) : ?>
                            <span class="ttcg-my-grips__unread-badge"><?php echo esc_html( $unread ); ?></span>
                        <?php endif; ?>

                        <?php if ( ! empty( $action_hint ) ) : ?>
                            <span class="ttcg-my-grips__action-hint"><?php echo esc_html( $action_hint ); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="ttcg-my-grips__card-body">
                        <h3 class="ttcg-my-grips__card-title"><?php echo esc_html( $design['title'] ); ?></h3>
                        <div class="ttcg-my-grips__card-meta">
                            <span class="ttcg-my-grips__status-badge" style="background-color: <?php echo esc_attr( $status_color ); ?>;">
                                <?php echo esc_html( $status_label ); ?>
                            </span>
                            <?php if ( ! empty( $design['team_name'] ) ) : ?>
                                <span class="ttcg-my-grips__team"><?php echo esc_html( $design['team_name'] ); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="ttcg-my-grips__card-details">
                            <?php if ( ! empty( $design['quantity'] ) ) : ?>
                                <span><?php printf( esc_html__( 'Qty: %s', 'twintack-custom-grips' ), esc_html( $design['quantity'] ) ); ?></span>
                            <?php endif; ?>
                            <span><?php echo esc_html( $design['date'] ); ?></span>
                        </div>
                        <div class="ttcg-my-grips__card-cta">
                            <?php esc_html_e( 'View Details →', 'twintack-custom-grips' ); ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</div>
