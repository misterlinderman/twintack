<?php
/**
 * Customer grip design detail template.
 *
 * Available variables:
 * - $grip_id : int — Grip design post ID
 * - $data    : array — Design data from TTCG_Dashboard::get_design_data()
 * - $messages : array — Customer-visible messages
 *
 * @package TwinTack_Custom_Grips
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$status       = $data['artwork_status'];
$status_label = TTCG_Dashboard::get_status_label( $status );
$status_color = TTCG_Dashboard::get_status_color( $status );
$back_url     = TTCG_Customer::get_list_url();
?>

<div class="ttcg-design-detail">

    <!-- Back Navigation -->
    <a href="<?php echo esc_url( $back_url ); ?>" class="ttcg-design-detail__back">
        &larr; <?php esc_html_e( 'Back to My Custom Grips', 'twintack-custom-grips' ); ?>
    </a>

    <!-- Header -->
    <div class="ttcg-design-detail__header">
        <div class="ttcg-design-detail__header-info">
            <h2 class="ttcg-design-detail__title"><?php echo esc_html( $data['title'] ); ?></h2>
            <span class="ttcg-design-detail__status" style="background-color: <?php echo esc_attr( $status_color ); ?>;">
                <?php echo esc_html( $status_label ); ?>
            </span>
        </div>
        <div class="ttcg-design-detail__header-meta">
            <?php if ( ! empty( $data['team_name'] ) ) : ?>
                <span><?php printf( esc_html__( 'Team: %s', 'twintack-custom-grips' ), esc_html( $data['team_name'] ) ); ?></span>
            <?php endif; ?>
            <span><?php printf( esc_html__( 'Submitted: %s', 'twintack-custom-grips' ), esc_html( $data['date'] ) ); ?></span>
            <?php if ( ! empty( $data['quantity'] ) ) : ?>
                <span><?php printf( esc_html__( 'Quantity: %s', 'twintack-custom-grips' ), esc_html( $data['quantity'] ) ); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status Progress Bar -->
    <div class="ttcg-design-detail__progress">
        <?php
        $progress_statuses = array(
            'artwork_pending'            => __( 'Submitted', 'twintack-custom-grips' ),
            'pending_review'             => __( 'Design Ready', 'twintack-custom-grips' ),
            'customer_approved'          => __( 'Approved', 'twintack-custom-grips' ),
            'in_production'              => __( 'In Production', 'twintack-custom-grips' ),
            'shipped'                    => __( 'Shipped', 'twintack-custom-grips' ),
        );

        $current_found = false;
        $status_index  = 0;
        $status_keys   = array_keys( $progress_statuses );

        // Map current status to nearest progress step
        $mapped_index = 0;
        switch ( $status ) {
            case 'artwork_pending':
                $mapped_index = 0;
                break;
            case 'pending_review':
            case 'customer_requested_changes':
            case 'internal_review':       // Legacy — kept for backward compatibility
                $mapped_index = 1;
                break;
            case 'customer_approved':
            case 'artwork_approved':      // Legacy — kept for backward compatibility
                $mapped_index = 2;
                break;
            case 'in_production':
            case 'approved_for_production': // Legacy — maps to in production
                $mapped_index = 3;
                break;
            case 'shipped':
                $mapped_index = 4;
                break;
        }

        $step_index = 0;
        foreach ( $progress_statuses as $slug => $label ) :
            $is_completed = $step_index < $mapped_index;
            $is_current   = $step_index === $mapped_index;
            $step_class   = $is_completed ? 'completed' : ( $is_current ? 'current' : 'upcoming' );
        ?>
            <div class="ttcg-design-detail__progress-step ttcg-design-detail__progress-step--<?php echo esc_attr( $step_class ); ?>">
                <div class="ttcg-design-detail__progress-dot">
                    <?php if ( $is_completed ) : ?>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                    <?php endif; ?>
                </div>
                <span class="ttcg-design-detail__progress-label"><?php echo esc_html( $label ); ?></span>
            </div>
        <?php
            $step_index++;
        endforeach;
        ?>
    </div>

    <!-- Main Content Grid -->
    <div class="ttcg-design-detail__content">

        <!-- Left Column: Mockup & Design Details -->
        <div class="ttcg-design-detail__main">

            <!-- Mockup Display -->
            <div class="ttcg-design-detail__mockup-section">
                <h3><?php esc_html_e( 'Design Mockup', 'twintack-custom-grips' ); ?></h3>

                <?php
                // Use full-resolution image for the customer detail view
                $mockup_src  = ! empty( $data['mockup_full_url'] ) ? $data['mockup_full_url'] : $data['mockup_display_url'];
                $mockup_link = ! empty( $data['mockup_full_url'] ) ? $data['mockup_full_url'] : $data['mockup_display_url'];
                ?>
                <?php if ( ! empty( $mockup_src ) ) : ?>
                    <div class="ttcg-design-detail__mockup">
                        <img src="<?php echo esc_url( $mockup_src ); ?>"
                             alt="<?php echo esc_attr( $data['title'] ); ?>">
                        <a href="<?php echo esc_url( $mockup_link ); ?>"
                           target="_blank"
                           class="ttcg-design-detail__mockup-fullsize">
                            <?php esc_html_e( 'View Full Size', 'twintack-custom-grips' ); ?> ↗
                        </a>
                    </div>
                <?php else : ?>
                    <div class="ttcg-design-detail__mockup-pending">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <path d="m21 15-5-5L5 21"/>
                        </svg>
                        <p><?php esc_html_e( 'Our design team is working on your mockup. You\'ll receive an email when it\'s ready for review.', 'twintack-custom-grips' ); ?></p>
                    </div>
                <?php endif; ?>

                <?php
                // Show original artwork if available and different from mockup
                if ( ! empty( $data['artwork_url'] ) && $data['artwork_url'] !== ( $mockup_src ?? '' ) ) :
                ?>
                    <div class="ttcg-design-detail__original-artwork">
                        <h4><?php esc_html_e( 'Your Original Artwork', 'twintack-custom-grips' ); ?></h4>
                        <div class="ttcg-design-detail__artwork-thumb">
                            <img src="<?php echo esc_url( $data['artwork_url'] ); ?>" alt="<?php esc_attr_e( 'Original Artwork', 'twintack-custom-grips' ); ?>">
                            <?php if ( ! empty( $data['artwork_filename'] ) ) : ?>
                                <span class="ttcg-design-detail__artwork-filename"><?php echo esc_html( $data['artwork_filename'] ); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Design Details -->
            <div class="ttcg-design-detail__info-section">
                <h3><?php esc_html_e( 'Design Specifications', 'twintack-custom-grips' ); ?></h3>
                <div class="ttcg-design-detail__specs">
                    <?php if ( ! empty( $data['design_type'] ) ) : ?>
                        <div class="ttcg-design-detail__spec">
                            <span class="ttcg-design-detail__spec-label"><?php esc_html_e( 'Type', 'twintack-custom-grips' ); ?></span>
                            <span class="ttcg-design-detail__spec-value"><?php echo esc_html( $data['design_type'] ); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ( ! empty( $data['design_layout'] ) ) : ?>
                        <div class="ttcg-design-detail__spec">
                            <span class="ttcg-design-detail__spec-label"><?php esc_html_e( 'Pattern', 'twintack-custom-grips' ); ?></span>
                            <span class="ttcg-design-detail__spec-value"><?php echo esc_html( $data['design_layout'] ); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ( ! empty( $data['primary_color'] ) ) : ?>
                        <div class="ttcg-design-detail__spec">
                            <span class="ttcg-design-detail__spec-label"><?php esc_html_e( 'Colors', 'twintack-custom-grips' ); ?></span>
                            <span class="ttcg-design-detail__spec-value">
                                <?php
                                $colors = array_filter( array( $data['primary_color'], $data['secondary_color'], $data['tertiary_color'] ) );
                                echo esc_html( implode( ', ', $colors ) );
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <?php if ( ! empty( $data['quantity'] ) ) : ?>
                        <div class="ttcg-design-detail__spec">
                            <span class="ttcg-design-detail__spec-label"><?php esc_html_e( 'Quantity', 'twintack-custom-grips' ); ?></span>
                            <span class="ttcg-design-detail__spec-value"><?php echo esc_html( $data['quantity'] ); ?> <?php esc_html_e( 'grips', 'twintack-custom-grips' ); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Section: Approval / Purchase -->
            <?php if ( 'pending_review' === $status ) : ?>
                <div class="ttcg-design-detail__action-section ttcg-design-detail__action-section--review">
                    <h3><?php esc_html_e( 'Review Your Design', 'twintack-custom-grips' ); ?></h3>
                    <p><?php esc_html_e( 'Please review the mockup above and let us know if you approve it or need changes.', 'twintack-custom-grips' ); ?></p>

                    <div class="ttcg-design-detail__review-form" data-grip-id="<?php echo esc_attr( $grip_id ); ?>">
                        <div class="ttcg-design-detail__review-textarea">
                            <label for="ttcg-review-feedback"><?php esc_html_e( 'Comments (optional)', 'twintack-custom-grips' ); ?></label>
                            <textarea id="ttcg-review-feedback" rows="3" placeholder="<?php esc_attr_e( 'Any specific feedback or changes you\'d like…', 'twintack-custom-grips' ); ?>"></textarea>
                        </div>
                        <div class="ttcg-design-detail__review-buttons">
                            <button type="button" class="ttcg-btn ttcg-btn--approve" data-action="approve">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                <?php esc_html_e( 'Approve Design', 'twintack-custom-grips' ); ?>
                            </button>
                            <button type="button" class="ttcg-btn ttcg-btn--changes" data-action="request_changes">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                <?php esc_html_e( 'Request Changes', 'twintack-custom-grips' ); ?>
                            </button>
                        </div>
                        <div class="ttcg-design-detail__review-response" style="display:none;"></div>
                    </div>
                </div>
            <?php elseif ( 'customer_approved' === $status ) : ?>
                <div class="ttcg-design-detail__action-section ttcg-design-detail__action-section--purchase">
                    <h3><?php esc_html_e( 'Design Approved!', 'twintack-custom-grips' ); ?></h3>
                    <p><?php esc_html_e( 'Your design has been approved. You can now purchase your custom grips.', 'twintack-custom-grips' ); ?></p>
                    <div class="ttcg-design-detail__purchase-info">
                        <span><strong><?php esc_html_e( 'Quantity:', 'twintack-custom-grips' ); ?></strong> <?php echo esc_html( $data['quantity'] ); ?> <?php esc_html_e( 'grips', 'twintack-custom-grips' ); ?></span>
                    </div>
                    <?php
                    $product_id   = (int) apply_filters( 'twintack_custom_grip_product_id', 1196 );
                    $purchase_url = add_query_arg(
                        array(
                            'add-to-cart'    => $product_id,
                            'quantity'       => max( 1, (int) $data['quantity'] ),
                            'grip_design_id' => $grip_id,
                        ),
                        wc_get_cart_url()
                    );
                    ?>
                    <a href="<?php echo esc_url( $purchase_url ); ?>" class="ttcg-btn ttcg-btn--purchase">
                        <?php esc_html_e( 'Purchase Custom Grips', 'twintack-custom-grips' ); ?>
                    </a>
                </div>
            <?php elseif ( 'in_production' === $status ) : ?>
                <div class="ttcg-design-detail__action-section ttcg-design-detail__action-section--production">
                    <h3><?php esc_html_e( 'In Production', 'twintack-custom-grips' ); ?></h3>
                    <p><?php esc_html_e( 'Your custom grips are being produced! We\'ll update you when they ship.', 'twintack-custom-grips' ); ?></p>
                </div>
            <?php elseif ( 'shipped' === $status ) : ?>
                <div class="ttcg-design-detail__action-section ttcg-design-detail__action-section--shipped">
                    <h3><?php esc_html_e( 'Shipped!', 'twintack-custom-grips' ); ?></h3>
                    <p><?php esc_html_e( 'Your custom grips have been shipped. You should receive them soon!', 'twintack-custom-grips' ); ?></p>
                    <?php
                    $reorder_product_id = (int) apply_filters( 'twintack_custom_grip_product_id', 1196 );
                    if ( $reorder_product_id > 0 && function_exists( 'wc_get_cart_url' ) ) :
                        $reorder_url = add_query_arg(
                            array(
                                'add-to-cart'    => $reorder_product_id,
                                'quantity'       => max( 1, (int) $data['quantity'] ),
                                'grip_design_id' => $grip_id,
                            ),
                            wc_get_cart_url()
                        );
                        ?>
                        <p class="ttcg-design-detail__reorder-hint"><?php esc_html_e( 'Need another run of this design? Add it to your cart with one click.', 'twintack-custom-grips' ); ?></p>
                        <a href="<?php echo esc_url( $reorder_url ); ?>" class="ttcg-btn ttcg-btn--purchase">
                            <?php esc_html_e( 'Order This Design Again', 'twintack-custom-grips' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php elseif ( 'customer_requested_changes' === $status ) : ?>
                <div class="ttcg-design-detail__action-section ttcg-design-detail__action-section--changes">
                    <h3><?php esc_html_e( 'Customer Changes', 'twintack-custom-grips' ); ?></h3>
                    <p><?php esc_html_e( 'Our design team is reviewing your feedback and working on revisions. You\'ll be notified when the updated mockup is ready.', 'twintack-custom-grips' ); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Messages -->
        <div class="ttcg-design-detail__sidebar">
            <div class="ttcg-design-detail__messages-section">
                <h3><?php esc_html_e( 'Messages', 'twintack-custom-grips' ); ?></h3>

                <div class="ttcg-design-detail__messages" id="ttcg-customer-messages" data-grip-id="<?php echo esc_attr( $grip_id ); ?>">
                    <?php if ( empty( $messages ) ) : ?>
                        <div class="ttcg-design-detail__messages-empty">
                            <p><?php esc_html_e( 'No messages yet. Send a message to our design team below.', 'twintack-custom-grips' ); ?></p>
                        </div>
                    <?php else : ?>
                        <?php foreach ( $messages as $msg ) :
                            $bubble_class = $msg['is_customer'] ? 'customer' : ( $msg['is_system'] ? 'system' : 'team' );
                        ?>
                            <div class="ttcg-message ttcg-message--<?php echo esc_attr( $bubble_class ); ?>">
                                <?php if ( $msg['is_system'] ) : ?>
                                    <div class="ttcg-message__system">
                                        <?php echo wp_kses_post( $msg['content'] ); ?>
                                        <span class="ttcg-message__time"><?php echo esc_html( $msg['date'] ); ?></span>
                                    </div>
                                <?php else : ?>
                                    <div class="ttcg-message__header">
                                        <span class="ttcg-message__sender">
                                            <?php echo $msg['is_customer']
                                                ? esc_html__( 'You', 'twintack-custom-grips' )
                                                : esc_html( $msg['sender_name'] ) . ' <small>(' . esc_html__( 'TwinTack Team', 'twintack-custom-grips' ) . ')</small>'; ?>
                                        </span>
                                        <span class="ttcg-message__time"><?php echo esc_html( $msg['date'] ); ?></span>
                                    </div>
                                    <div class="ttcg-message__body">
                                        <?php echo wp_kses_post( wpautop( $msg['content'] ) ); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Message Input -->
                <div class="ttcg-design-detail__message-form">
                    <textarea id="ttcg-customer-message-input"
                              rows="3"
                              placeholder="<?php esc_attr_e( 'Type a message to our design team…', 'twintack-custom-grips' ); ?>"
                              data-grip-id="<?php echo esc_attr( $grip_id ); ?>"></textarea>
                    <button type="button" id="ttcg-customer-send-message" class="ttcg-btn ttcg-btn--send" data-grip-id="<?php echo esc_attr( $grip_id ); ?>">
                        <?php esc_html_e( 'Send Message', 'twintack-custom-grips' ); ?>
                    </button>
                </div>
            </div>
        </div>

    </div>

</div>
