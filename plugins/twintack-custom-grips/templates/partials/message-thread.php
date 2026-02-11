<?php
/**
 * Message thread partial — chat-style conversation.
 *
 * Included from design-detail.php.
 *
 * @package TwinTack_Custom_Grips
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$messages = TTCG_Messaging::get_messages( $design_id );

// Mark as read for current user
TTCG_Messaging::mark_all_read( $design_id );
?>

<div class="ttcg-messages" id="ttcg-messages" data-design-id="<?php echo esc_attr( $design_id ); ?>">

    <!-- Message List -->
    <div class="ttcg-messages__thread" id="ttcg-message-thread">
        <?php if ( empty( $messages ) ) : ?>
            <div class="ttcg-messages__empty" id="ttcg-messages-empty">
                <p><?php esc_html_e( 'No messages yet. Start the conversation below.', 'twintack-custom-grips' ); ?></p>
            </div>
        <?php else : ?>
            <?php foreach ( $messages as $msg ) :
                $is_system = ( 'system' === $msg['sender_role'] || 0 === $msg['sender_id'] );
                $is_own    = ( (int) $msg['sender_id'] === get_current_user_id() );
                $role_class = 'ttcg-msg--' . esc_attr( $msg['sender_role'] );
                if ( $is_system ) $role_class = 'ttcg-msg--system';
                if ( $is_own ) $role_class .= ' ttcg-msg--own';
            ?>
                <div class="ttcg-msg <?php echo esc_attr( $role_class ); ?>" data-message-id="<?php echo esc_attr( $msg['id'] ); ?>">
                    <?php if ( $is_system ) : ?>
                        <div class="ttcg-msg__system">
                            <span class="ttcg-msg__system-icon">
                                <?php if ( 'status_change' === $msg['type'] ) : ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
                                <?php elseif ( 'mockup_upload' === $msg['type'] ) : ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                <?php else : ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                <?php endif; ?>
                            </span>
                            <span class="ttcg-msg__system-text"><?php echo wp_kses_post( $msg['content'] ); ?></span>
                            <span class="ttcg-msg__time"><?php echo esc_html( $msg['date_human'] ); ?></span>
                        </div>
                    <?php else : ?>
                        <div class="ttcg-msg__bubble">
                            <div class="ttcg-msg__header">
                                <?php if ( ! empty( $msg['sender_avatar'] ) ) : ?>
                                    <img src="<?php echo esc_url( $msg['sender_avatar'] ); ?>"
                                         alt="" class="ttcg-msg__avatar">
                                <?php endif; ?>
                                <span class="ttcg-msg__sender"><?php echo esc_html( $msg['sender_name'] ); ?></span>
                                <span class="ttcg-msg__role-badge ttcg-role--<?php echo esc_attr( $msg['sender_role'] ); ?>">
                                    <?php echo esc_html( ucwords( str_replace( '_', ' ', $msg['sender_role'] ) ) ); ?>
                                </span>
                                <span class="ttcg-msg__time"><?php echo esc_html( $msg['date_human'] ); ?></span>
                            </div>
                            <div class="ttcg-msg__content">
                                <?php echo wp_kses_post( $msg['content'] ); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Compose Area -->
    <?php if ( $can_message ) : ?>
        <div class="ttcg-messages__compose">
            <form id="ttcg-message-form" class="ttcg-messages__form">
                <textarea id="ttcg-message-input" class="ttcg-messages__textarea"
                          placeholder="<?php esc_attr_e( 'Type a message…', 'twintack-custom-grips' ); ?>"
                          rows="3"></textarea>
                <div class="ttcg-messages__actions">
                    <button type="submit" class="ttcg-btn ttcg-btn--primary" id="ttcg-send-message">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        <?php esc_html_e( 'Send', 'twintack-custom-grips' ); ?>
                    </button>
                </div>
            </form>
            <div class="ttcg-messages__feedback" id="ttcg-message-feedback"></div>
        </div>
    <?php endif; ?>
</div>
