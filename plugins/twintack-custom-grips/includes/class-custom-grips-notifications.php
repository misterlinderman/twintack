<?php
/**
 * TTCG_Notifications — Email notification system.
 *
 * Sends HTML emails to customers, art team, and production team
 * in response to key events (messages, status changes, mockup uploads).
 *
 * @package TwinTack_Custom_Grips
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TTCG_Notifications {

    /** @var TTCG_Notifications|null */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * @return TTCG_Notifications
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor — hooks into plugin events.
     */
    private function __construct() {
        // Message created
        add_action( 'ttcg_message_created', array( $this, 'on_message_created' ), 10, 4 );

        // Status changed
        add_action( 'ttcg_status_changed', array( $this, 'on_status_changed' ), 10, 4 );

        // Mockup uploaded
        add_action( 'ttcg_mockup_uploaded', array( $this, 'on_mockup_uploaded' ), 10, 3 );
    }

    // ------------------------------------------------------------------
    // Event Handlers
    // ------------------------------------------------------------------

    /**
     * Handle a new message being created.
     *
     * @param int    $message_id  Message post ID.
     * @param int    $design_id   Grip design post ID.
     * @param int    $sender_id   Sender user ID.
     * @param string $type        Message type.
     */
    public function on_message_created( $message_id, $design_id, $sender_id, $type ) {
        // Only notify on text messages (not system messages)
        if ( 'text' !== $type ) {
            return;
        }

        $sender_role = TTCG_Roles::get_user_role_slug( $sender_id );
        $content     = get_post_field( 'post_content', $message_id );

        if ( 'customer' === $sender_role ) {
            // Customer sent a message → notify team
            $this->notify_team(
                $design_id,
                __( 'New Customer Message', 'twintack-custom-grips' ),
                'message-notification',
                array( 'message_content' => $content )
            );
        } else {
            // Team member sent a message → notify customer
            $this->notify_customer(
                $design_id,
                __( 'New Message About Your Grip Design', 'twintack-custom-grips' ),
                'message-notification',
                array( 'message_content' => $content )
            );
        }
    }

    /**
     * Handle a status change.
     *
     * @param int    $design_id  Post ID.
     * @param string $new_status New artwork status slug.
     * @param string $old_status Previous artwork status slug.
     * @param int    $user_id    User who made the change.
     */
    public function on_status_changed( $design_id, $new_status, $old_status, $user_id ) {
        $data = array(
            'old_status'       => TTCG_Dashboard::get_status_label( $old_status ),
            'new_status'       => TTCG_Dashboard::get_status_label( $new_status ),
            'new_status_slug'  => $new_status,
        );

        // Mockup upload sets pending_review and sends "mockup ready" — skip duplicate status email.
        $skip_customer_status_email = ( 'pending_review' === $new_status && TTCG_Status::should_suppress_customer_status_email() );

        if ( ! $skip_customer_status_email ) {
            $subject = __( 'Your Grip Design Status Has Been Updated', 'twintack-custom-grips' );
            if ( 'customer_approved' === $new_status ) {
                $subject = __( 'Thank You — Your Custom Grip Design Is Approved', 'twintack-custom-grips' );
            }
            $this->notify_customer(
                $design_id,
                $subject,
                'status-update',
                $data
            );
        }

        // If customer approved → notify the team (exclude the customer's own inbox if they are also staff/admin).
        if ( 'customer_approved' === $new_status ) {
            $this->notify_team(
                $design_id,
                __( 'Customer Approved Grip Design', 'twintack-custom-grips' ),
                'status-update',
                $data
            );
        }
    }

    /**
     * Handle mockup uploaded.
     *
     * @param int $design_id  Grip design post ID.
     * @param int $attach_id  WordPress attachment ID.
     * @param int $user_id    Uploader user ID.
     */
    public function on_mockup_uploaded( $design_id, $attach_id, $user_id ) {
        $mockup_url = wp_get_attachment_url( $attach_id );

        $this->notify_customer(
            $design_id,
            __( 'Your Grip Mockup Is Ready for Review!', 'twintack-custom-grips' ),
            'mockup-ready',
            array( 'mockup_url' => $mockup_url )
        );
    }

    // ------------------------------------------------------------------
    // Sending Helpers
    // ------------------------------------------------------------------

    /**
     * Send an email notification to the customer associated with a design.
     *
     * @param int    $design_id   Grip design post ID.
     * @param string $subject     Email subject.
     * @param string $template    Template slug (without .php).
     * @param array  $extra_data  Extra data for the template.
     */
    private function notify_customer( $design_id, $subject, $template, $extra_data = array() ) {
        $email = get_post_meta( $design_id, '_grip_customer_email', true );

        if ( empty( $email ) || ! is_email( $email ) ) {
            if ( WP_DEBUG ) {
                error_log( "TTCG Notifications: No valid customer email for design #{$design_id}." );
            }
            return;
        }

        // Check notification preferences
        if ( $this->is_opted_out( $email, $template ) ) {
            return;
        }

        $data = $this->get_base_data( $design_id, 'customer' );
        $data = array_merge( $data, $extra_data );

        $body = $this->render_template( $template, $data );
        $this->send( $email, $subject, $body );

        $this->log_email( $design_id, $email, $subject, $template );
    }

    /**
     * Send a notification to all art team and production team users.
     *
     * @param int    $design_id   Grip design post ID.
     * @param string $subject     Email subject.
     * @param string $template    Template slug.
     * @param array  $extra_data  Extra data for the template.
     */
    private function notify_team( $design_id, $subject, $template, $extra_data = array() ) {
        $team_users = get_users( array(
            'role__in' => array( 'grip_art_team', 'grip_production_team', 'administrator' ),
            'fields'   => array( 'ID', 'user_email' ),
        ) );

        if ( empty( $team_users ) ) {
            return;
        }

        $data = $this->get_base_data( $design_id, 'team' );
        $data = array_merge( $data, $extra_data );
        $body = $this->render_template( $template, $data );

        $customer_email = strtolower( trim( (string) get_post_meta( $design_id, '_grip_customer_email', true ) ) );

        foreach ( $team_users as $user ) {
            if ( $customer_email && strtolower( trim( $user->user_email ) ) === $customer_email ) {
                continue;
            }
            if ( $this->is_opted_out_user( $user->ID, $template ) ) {
                continue;
            }
            $this->send( $user->user_email, $subject, $body );
        }

        $this->log_email( $design_id, 'team', $subject, $template );
    }

    /**
     * Get standard template data for a grip design.
     *
     * @param  int    $design_id  Grip design post ID.
     * @param  string $review_for 'customer' uses My Account detail URL; 'team' uses the team dashboard URL.
     * @return array
     */
    private function get_base_data( $design_id, $review_for = 'team' ) {
        $data = TTCG_Dashboard::get_design_data( $design_id );

        $data['status_label']  = TTCG_Dashboard::get_status_label( $data['artwork_status'] );
        if ( 'customer' === $review_for && class_exists( 'TTCG_Customer' ) ) {
            $data['review_url'] = TTCG_Customer::get_detail_url( $design_id );
        } else {
            $data['review_url'] = TTCG_Router::get_design_url( $design_id );
        }
        $data['site_name']     = get_bloginfo( 'name' );
        $data['site_url']      = home_url();
        $data['current_year']  = gmdate( 'Y' );

        return $data;
    }

    /**
     * Send an HTML email.
     *
     * @param string $to      Recipient email.
     * @param string $subject Subject.
     * @param string $body    HTML body.
     */
    private function send( $to, $subject, $body ) {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: TwinTack <support@twintack.com>',
        );

        $result = wp_mail( $to, $subject, $body, $headers );

        if ( WP_DEBUG ) {
            error_log( sprintf(
                'TTCG Notifications: Email to %s — subject: "%s" — result: %s',
                $to,
                $subject,
                $result ? 'sent' : 'FAILED'
            ) );
        }
    }

    // ------------------------------------------------------------------
    // Template Rendering
    // ------------------------------------------------------------------

    /**
     * Render an email template with the given data.
     *
     * @param  string $template Template slug.
     * @param  array  $data     Template variables.
     * @return string           Rendered HTML.
     */
    private function render_template( $template, $data ) {
        $file = TTCG_PLUGIN_DIR . 'templates/emails/' . $template . '.php';

        if ( ! file_exists( $file ) ) {
            // Fallback to a simple plain-text style
            return $this->render_fallback( $data );
        }

        // Extract data to variables for the template
        extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract

        ob_start();
        include $file;
        return ob_get_clean();
    }

    /**
     * Fallback renderer when a template file is missing.
     *
     * @param  array  $data
     * @return string
     */
    private function render_fallback( $data ) {
        $html = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $html .= '<h2 style="color: #333;">' . esc_html( $data['site_name'] ) . '</h2>';
        $html .= '<p>' . esc_html( sprintf( 'Grip Design: %s', $data['title'] ) ) . '</p>';
        $html .= '<p>' . esc_html( sprintf( 'Customer: %s', $data['customer_name'] ) ) . '</p>';
        $html .= '<p>' . esc_html( sprintf( 'Status: %s', $data['status_label'] ) ) . '</p>';

        if ( ! empty( $data['message_content'] ) ) {
            $html .= '<div style="background: #f5f5f5; padding: 15px; border-radius: 8px; margin: 15px 0;">';
            $html .= wp_kses_post( $data['message_content'] );
            $html .= '</div>';
        }

        $html .= '<p><a href="' . esc_url( $data['review_url'] ) . '" style="display: inline-block; padding: 10px 20px; background: #337ab7; color: #fff; text-decoration: none; border-radius: 5px;">View Design</a></p>';
        $html .= '</div>';

        return $html;
    }

    // ------------------------------------------------------------------
    // Logging
    // ------------------------------------------------------------------

    /**
     * Log an email send in post meta.
     *
     * @param int    $design_id
     * @param string $to
     * @param string $subject
     * @param string $template
     */
    private function log_email( $design_id, $to, $subject, $template ) {
        $log = get_post_meta( $design_id, '_ttcg_email_log', true );
        if ( ! is_array( $log ) ) {
            $log = array();
        }

        $log[] = array(
            'to'       => $to,
            'subject'  => $subject,
            'template' => $template,
            'sent_at'  => current_time( 'mysql' ),
        );

        update_post_meta( $design_id, '_ttcg_email_log', $log );
    }

    // ------------------------------------------------------------------
    // Opt-out Helpers
    // ------------------------------------------------------------------

    /**
     * Check if an email address has opted out of a notification type.
     *
     * @param  string $email
     * @param  string $template
     * @return bool
     */
    private function is_opted_out( $email, $template ) {
        $user = get_user_by( 'email', $email );
        if ( ! $user ) {
            return false;
        }
        return $this->is_opted_out_user( $user->ID, $template );
    }

    /**
     * Check if a user has opted out of a notification type.
     *
     * @param  int    $user_id
     * @param  string $template
     * @return bool
     */
    private function is_opted_out_user( $user_id, $template ) {
        $prefs = get_user_meta( $user_id, '_ttcg_notification_prefs', true );
        if ( ! is_array( $prefs ) ) {
            return false;
        }
        return ! empty( $prefs[ $template ] ) && 'off' === $prefs[ $template ];
    }
}
