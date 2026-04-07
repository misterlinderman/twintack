<?php
/**
 * TTCG_Ajax — All AJAX endpoint handlers for the dashboard.
 *
 * Every public-facing AJAX action is registered here with nonce
 * verification and capability checks.
 *
 * @package TwinTack_Custom_Grips
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TTCG_Ajax {

    /** @var TTCG_Ajax|null */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * @return TTCG_Ajax
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor — registers AJAX actions.
     */
    private function __construct() {
        // Status update
        add_action( 'wp_ajax_ttcg_update_status', array( $this, 'handle_update_status' ) );

        // Design detail editing
        add_action( 'wp_ajax_ttcg_update_design_details', array( $this, 'handle_update_design_details' ) );

        // Mockup upload
        add_action( 'wp_ajax_ttcg_upload_mockup', array( $this, 'handle_upload_mockup' ) );

        // Messaging
        add_action( 'wp_ajax_ttcg_send_message', array( $this, 'handle_send_message' ) );
        add_action( 'wp_ajax_ttcg_get_messages', array( $this, 'handle_get_messages' ) );
        add_action( 'wp_ajax_ttcg_mark_read', array( $this, 'handle_mark_read' ) );

        // Create / Delete grip designs
        add_action( 'wp_ajax_ttcg_create_design', array( $this, 'handle_create_design' ) );
        add_action( 'wp_ajax_ttcg_delete_design', array( $this, 'handle_delete_design' ) );

        // Customer-facing messaging (also logged-in)
        add_action( 'wp_ajax_ttcg_customer_send_message', array( $this, 'handle_customer_send_message' ) );
        add_action( 'wp_ajax_ttcg_customer_get_messages', array( $this, 'handle_customer_get_messages' ) );

        // Customer review action (approve / request changes) from new My Custom Grips page
        add_action( 'wp_ajax_ttcg_customer_review_design', array( $this, 'handle_customer_review_design' ) );
    }

    // ------------------------------------------------------------------
    // Verification Helpers
    // ------------------------------------------------------------------

    /**
     * Verify the AJAX nonce and that the user can access the dashboard.
     *
     * @return bool True on success, dies on failure.
     */
    private function verify_team_request() {
        if ( ! check_ajax_referer( 'ttcg_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'twintack-custom-grips' ) ), 403 );
        }

        if ( ! TTCG_Roles::user_can_access_dashboard() ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'twintack-custom-grips' ) ), 403 );
        }

        return true;
    }

    /**
     * Verify a customer AJAX request (logged-in, owns the design).
     *
     * Allows access if:
     * - User email matches _grip_customer_email, OR
     * - User is the post author, OR
     * - User is an administrator
     *
     * @param  int $design_id
     * @return bool
     */
    private function verify_customer_request( $design_id ) {
        if ( ! check_ajax_referer( 'ttcg_customer_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'twintack-custom-grips' ) ), 403 );
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'twintack-custom-grips' ) ), 403 );
        }

        // Admins can access any design
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        // Verify customer owns this design (by email or authorship)
        $customer_email = get_post_meta( $design_id, '_grip_customer_email', true );
        $current_user   = wp_get_current_user();
        $post           = get_post( $design_id );

        $email_match  = $current_user->user_email === $customer_email;
        $author_match = $post && (int) $post->post_author === $current_user->ID;

        if ( ! $email_match && ! $author_match ) {
            wp_send_json_error( array( 'message' => __( 'You do not have access to this design.', 'twintack-custom-grips' ) ), 403 );
        }

        return true;
    }

    // ------------------------------------------------------------------
    // Status Update
    // ------------------------------------------------------------------

    /**
     * AJAX: Update artwork status on a grip design.
     */
    public function handle_update_status() {
        $this->verify_team_request();

        $design_id  = isset( $_POST['design_id'] ) ? absint( $_POST['design_id'] ) : 0;
        $new_status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

        if ( ! $design_id || ! $new_status ) {
            wp_send_json_error( array( 'message' => __( 'Missing required fields.', 'twintack-custom-grips' ) ) );
        }

        $result = TTCG_Status::update_status( $design_id, $new_status );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message'      => __( 'Status updated successfully.', 'twintack-custom-grips' ),
            'status'       => $new_status,
            'status_label' => TTCG_Dashboard::get_status_label( $new_status ),
            'status_color' => TTCG_Dashboard::get_status_color( $new_status ),
        ) );
    }

    // ------------------------------------------------------------------
    // Design Detail Editing
    // ------------------------------------------------------------------

    /**
     * AJAX: Update design details (customer info, specs, quantity).
     */
    public function handle_update_design_details() {
        $this->verify_team_request();

        $design_id = isset( $_POST['design_id'] ) ? absint( $_POST['design_id'] ) : 0;

        if ( ! $design_id ) {
            wp_send_json_error( array( 'message' => __( 'Missing design ID.', 'twintack-custom-grips' ) ) );
        }

        // Determine permission level
        $can_edit_details = current_user_can( 'edit_grip_details' );

        // Editable fields and which capability they require
        $fields = array(
            'customer_name'   => array( 'meta' => '_grip_customer_name',   'requires' => 'edit_grip_details' ),
            'customer_email'  => array( 'meta' => '_grip_customer_email',  'requires' => 'edit_grip_details' ),
            'team_name'       => array( 'meta' => '_grip_team_name',       'requires' => 'edit_grip_details' ),
            'quantity'        => array( 'meta' => '_grip_quantity',         'requires' => 'edit_grip_details' ),
            'design_type'     => array( 'meta' => '_grip_design_type',     'requires' => 'edit_grip_designs' ),
            'design_layout'   => array( 'meta' => '_grip_design_layout',   'requires' => 'edit_grip_designs' ),
            'primary_color'   => array( 'meta' => '_grip_primary_color',   'requires' => 'edit_grip_designs' ),
            'secondary_color' => array( 'meta' => '_grip_secondary_color', 'requires' => 'edit_grip_designs' ),
            'tertiary_color'  => array( 'meta' => '_grip_tertiary_color',  'requires' => 'edit_grip_designs' ),
            'feedback'        => array( 'meta' => '_grip_feedback',        'requires' => 'edit_grip_designs' ),
        );

        $updated = array();
        $changes = array();

        foreach ( $fields as $field => $config ) {
            if ( ! isset( $_POST[ $field ] ) ) {
                continue;
            }

            if ( ! current_user_can( $config['requires'] ) ) {
                continue;
            }

            $new_value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
            $old_value = get_post_meta( $design_id, $config['meta'], true );

            if ( $new_value !== $old_value ) {
                update_post_meta( $design_id, $config['meta'], $new_value );
                $updated[] = $field;
                $changes[] = sprintf( '%s: "%s" → "%s"', $field, $old_value, $new_value );
            }
        }

        if ( ! empty( $changes ) ) {
            $user     = wp_get_current_user();
            $username = $user->display_name;

            TTCG_Messaging::create_system_message(
                $design_id,
                sprintf(
                    /* translators: 1: user name, 2: list of changes */
                    __( '%1$s updated design details: %2$s', 'twintack-custom-grips' ),
                    $username,
                    implode( '; ', $changes )
                ),
                'detail_edit'
            );
        }

        wp_send_json_success( array(
            'message' => empty( $updated )
                ? __( 'No changes to save.', 'twintack-custom-grips' )
                : __( 'Details updated successfully.', 'twintack-custom-grips' ),
            'updated' => $updated,
        ) );
    }

    // ------------------------------------------------------------------
    // Mockup Upload
    // ------------------------------------------------------------------

    /**
     * AJAX: Upload a mockup file.
     */
    public function handle_upload_mockup() {
        $this->verify_team_request();

        if ( ! current_user_can( 'manage_grip_mockups' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to upload mockups.', 'twintack-custom-grips' ) ), 403 );
        }

        $design_id = isset( $_POST['design_id'] ) ? absint( $_POST['design_id'] ) : 0;

        if ( ! $design_id ) {
            wp_send_json_error( array( 'message' => __( 'Missing design ID.', 'twintack-custom-grips' ) ) );
        }

        if ( empty( $_FILES['mockup_file'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No file was provided.', 'twintack-custom-grips' ) ) );
        }

        $result = TTCG_Mockups::process_upload( $design_id, $_FILES['mockup_file'] );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message'       => __( 'Mockup uploaded successfully!', 'twintack-custom-grips' ),
            'attachment_id' => $result['attachment_id'],
            'url'           => $result['url'],
            'filename'      => $result['filename'],
        ) );
    }

    // ------------------------------------------------------------------
    // Create Grip Design
    // ------------------------------------------------------------------

    /**
     * AJAX: Create a new grip design post manually.
     *
     * Requires the create_grip_designs capability
     * (Administrators and Production Team).
     */
    public function handle_create_design() {
        $this->verify_team_request();

        if ( ! current_user_can( 'create_grip_designs' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to create grip designs.', 'twintack-custom-grips' ) ), 403 );
        }

        // Required fields
        $customer_name  = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
        $customer_email = isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '';
        $quantity       = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;

        if ( empty( $customer_name ) || empty( $customer_email ) ) {
            wp_send_json_error( array( 'message' => __( 'Customer name and email are required.', 'twintack-custom-grips' ) ) );
        }

        if ( ! is_email( $customer_email ) ) {
            wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'twintack-custom-grips' ) ) );
        }

        // Optional fields
        $team_name       = isset( $_POST['team_name'] ) ? sanitize_text_field( wp_unslash( $_POST['team_name'] ) ) : '';
        $design_type     = isset( $_POST['design_type'] ) ? sanitize_text_field( wp_unslash( $_POST['design_type'] ) ) : '';
        $design_layout   = isset( $_POST['design_layout'] ) ? sanitize_text_field( wp_unslash( $_POST['design_layout'] ) ) : '';
        $primary_color   = isset( $_POST['primary_color'] ) ? sanitize_text_field( wp_unslash( $_POST['primary_color'] ) ) : '';
        $secondary_color = isset( $_POST['secondary_color'] ) ? sanitize_text_field( wp_unslash( $_POST['secondary_color'] ) ) : '';
        $tertiary_color  = isset( $_POST['tertiary_color'] ) ? sanitize_text_field( wp_unslash( $_POST['tertiary_color'] ) ) : '';
        $feedback        = isset( $_POST['feedback'] ) ? sanitize_textarea_field( wp_unslash( $_POST['feedback'] ) ) : '';
        $artwork_status  = isset( $_POST['artwork_status'] ) ? sanitize_text_field( wp_unslash( $_POST['artwork_status'] ) ) : 'artwork_pending';

        // Build the post title (matches existing Grip Manager pattern)
        $post_title = sprintf( 'Grip Design — %s', $customer_name );
        if ( ! empty( $team_name ) ) {
            $post_title = sprintf( 'Grip Design — %s (%s)', $customer_name, $team_name );
        }

        // Try to find a WordPress user by email to set as author
        $user    = get_user_by( 'email', $customer_email );
        $user_id = $user ? $user->ID : get_current_user_id();

        // Build meta input
        $meta_input = array(
            '_grip_customer_name'   => $customer_name,
            '_grip_customer_email'  => $customer_email,
            '_grip_team_name'       => $team_name,
            '_grip_design_type'     => $design_type,
            '_grip_design_layout'   => $design_layout,
            '_grip_primary_color'   => $primary_color,
            '_grip_secondary_color' => $secondary_color,
            '_grip_tertiary_color'  => $tertiary_color,
            '_grip_quantity'        => $quantity,
            '_grip_feedback'        => $feedback,
            '_grip_artwork_status'  => $artwork_status,
            '_grip_form_type'       => 'manual',
        );

        // Create the grip design post
        $post_id = wp_insert_post( array(
            'post_type'    => 'grip_design',
            'post_title'   => $post_title,
            'post_content' => $feedback,
            'post_status'  => 'publish',
            'post_author'  => $user_id,
            'meta_input'   => $meta_input,
        ), true );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
        }

        // Handle artwork file upload if provided
        if ( ! empty( $_FILES['artwork_file'] ) && ! empty( $_FILES['artwork_file']['tmp_name'] ) ) {
            $this->process_artwork_upload( $post_id, $_FILES['artwork_file'] );
        }

        // Create a system message for the audit trail
        $creator = wp_get_current_user();
        TTCG_Messaging::create_system_message(
            $post_id,
            sprintf(
                /* translators: 1: user name, 2: role label */
                __( 'Grip design created manually by %1$s (%2$s).', 'twintack-custom-grips' ),
                $creator->display_name,
                TTCG_Roles::get_user_role_label()
            ),
            'system'
        );

        if ( WP_DEBUG ) {
            error_log( sprintf(
                'TTCG: Grip design #%d created manually by user #%d (%s).',
                $post_id,
                get_current_user_id(),
                $creator->display_name
            ) );
        }

        wp_send_json_success( array(
            'message'   => __( 'Grip design created successfully!', 'twintack-custom-grips' ),
            'design_id' => $post_id,
            'url'       => TTCG_Router::get_design_url( $post_id ),
        ) );
    }

    /**
     * Process an artwork file upload during manual design creation.
     *
     * @param int   $post_id Design post ID.
     * @param array $file    $_FILES element.
     */
    private function process_artwork_upload( $post_id, $file ) {
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $overrides = array( 'test_form' => false );
        $upload    = wp_handle_upload( $file, $overrides );

        if ( isset( $upload['error'] ) ) {
            if ( WP_DEBUG ) {
                error_log( 'TTCG: Artwork upload error during design creation: ' . $upload['error'] );
            }
            return;
        }

        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name( $file['name'] ),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_author'    => get_current_user_id(),
        );

        $attach_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );

        if ( ! is_wp_error( $attach_id ) ) {
            $attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
            wp_update_attachment_metadata( $attach_id, $attach_data );

            update_post_meta( $post_id, '_grip_artwork_url', $upload['url'] );
            update_post_meta( $post_id, '_grip_artwork_filename', $file['name'] );
            update_post_meta( $post_id, '_grip_artwork_attachment_id', $attach_id );
        }
    }

    // ------------------------------------------------------------------
    // Delete Grip Design
    // ------------------------------------------------------------------

    /**
     * AJAX: Delete a grip design post.
     *
     * Requires the delete_grip_designs capability (Administrators only).
     */
    public function handle_delete_design() {
        $this->verify_team_request();

        if ( ! current_user_can( 'delete_grip_designs' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to delete grip designs.', 'twintack-custom-grips' ) ), 403 );
        }

        $design_id = isset( $_POST['design_id'] ) ? absint( $_POST['design_id'] ) : 0;

        if ( ! $design_id ) {
            wp_send_json_error( array( 'message' => __( 'Missing design ID.', 'twintack-custom-grips' ) ) );
        }

        // Verify the post exists and is a grip_design
        $post = get_post( $design_id );
        if ( ! $post || 'grip_design' !== $post->post_type ) {
            wp_send_json_error( array( 'message' => __( 'Grip design not found.', 'twintack-custom-grips' ) ) );
        }

        $title = $post->post_title;

        // Move to trash rather than permanent delete for safety
        $result = wp_trash_post( $design_id );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'Failed to delete the grip design. Please try again.', 'twintack-custom-grips' ) ) );
        }

        if ( WP_DEBUG ) {
            error_log( sprintf(
                'TTCG: Grip design #%d ("%s") trashed by user #%d.',
                $design_id,
                $title,
                get_current_user_id()
            ) );
        }

        wp_send_json_success( array(
            'message'      => sprintf(
                /* translators: %s: design title */
                __( '"%s" has been moved to trash.', 'twintack-custom-grips' ),
                $title
            ),
            'redirect_url' => TTCG_Router::get_dashboard_url(),
        ) );
    }

    // ------------------------------------------------------------------
    // Team Messaging
    // ------------------------------------------------------------------

    /**
     * AJAX: Send a message from a team member.
     */
    public function handle_send_message() {
        $this->verify_team_request();

        if ( ! current_user_can( 'send_grip_messages' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to send messages.', 'twintack-custom-grips' ) ), 403 );
        }

        $design_id = isset( $_POST['design_id'] ) ? absint( $_POST['design_id'] ) : 0;
        $content   = isset( $_POST['message'] ) ? wp_kses_post( wp_unslash( $_POST['message'] ) ) : '';

        if ( ! $design_id || empty( trim( $content ) ) ) {
            wp_send_json_error( array( 'message' => __( 'Design ID and message content are required.', 'twintack-custom-grips' ) ) );
        }

        $msg_id = TTCG_Messaging::create_message( $design_id, $content, get_current_user_id(), 'text' );

        if ( is_wp_error( $msg_id ) ) {
            wp_send_json_error( array( 'message' => $msg_id->get_error_message() ) );
        }

        // Return the full messages list so the UI can refresh
        $messages = TTCG_Messaging::get_messages( $design_id );

        wp_send_json_success( array(
            'message'    => __( 'Message sent!', 'twintack-custom-grips' ),
            'message_id' => $msg_id,
            'messages'   => $messages,
        ) );
    }

    /**
     * AJAX: Get all messages for a design (team view).
     */
    public function handle_get_messages() {
        $this->verify_team_request();

        $design_id = isset( $_GET['design_id'] ) ? absint( $_GET['design_id'] ) : 0;

        if ( ! $design_id ) {
            wp_send_json_error( array( 'message' => __( 'Missing design ID.', 'twintack-custom-grips' ) ) );
        }

        // Mark as read for current user
        TTCG_Messaging::mark_all_read( $design_id );

        $messages = TTCG_Messaging::get_messages( $design_id );

        wp_send_json_success( array( 'messages' => $messages ) );
    }

    /**
     * AJAX: Mark messages as read.
     */
    public function handle_mark_read() {
        $this->verify_team_request();

        $design_id = isset( $_POST['design_id'] ) ? absint( $_POST['design_id'] ) : 0;

        if ( ! $design_id ) {
            wp_send_json_error( array( 'message' => __( 'Missing design ID.', 'twintack-custom-grips' ) ) );
        }

        TTCG_Messaging::mark_all_read( $design_id );

        wp_send_json_success( array( 'message' => __( 'Marked as read.', 'twintack-custom-grips' ) ) );
    }

    // ------------------------------------------------------------------
    // Customer-Facing Messaging
    // ------------------------------------------------------------------

    /**
     * AJAX: Send a message from the customer.
     */
    public function handle_customer_send_message() {
        $design_id = isset( $_POST['design_id'] ) ? absint( $_POST['design_id'] ) : 0;

        if ( ! $design_id ) {
            wp_send_json_error( array( 'message' => __( 'Missing design ID.', 'twintack-custom-grips' ) ) );
        }

        $this->verify_customer_request( $design_id );

        // Accept both 'content' and 'message' parameter names for backwards compatibility
        $content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
        if ( empty( $content ) ) {
            $content = isset( $_POST['message'] ) ? wp_kses_post( wp_unslash( $_POST['message'] ) ) : '';
        }

        if ( empty( trim( $content ) ) ) {
            wp_send_json_error( array( 'message' => __( 'Message content is required.', 'twintack-custom-grips' ) ) );
        }

        $msg_id = TTCG_Messaging::create_message( $design_id, $content, get_current_user_id(), 'text' );

        if ( is_wp_error( $msg_id ) ) {
            wp_send_json_error( array( 'message' => $msg_id->get_error_message() ) );
        }

        $messages = TTCG_Messaging::get_messages( $design_id, true ); // customer view

        wp_send_json_success( array(
            'message'    => __( 'Message sent!', 'twintack-custom-grips' ),
            'message_id' => $msg_id,
            'messages'   => $messages,
        ) );
    }

    /**
     * AJAX: Get messages for a design (customer view — filtered).
     */
    public function handle_customer_get_messages() {
        $design_id = isset( $_GET['design_id'] ) ? absint( $_GET['design_id'] ) : 0;

        if ( ! $design_id ) {
            wp_send_json_error( array( 'message' => __( 'Missing design ID.', 'twintack-custom-grips' ) ) );
        }

        $this->verify_customer_request( $design_id );

        TTCG_Messaging::mark_all_read( $design_id );

        $messages = TTCG_Messaging::get_messages( $design_id, true );

        wp_send_json_success( array( 'messages' => $messages ) );
    }

    // ------------------------------------------------------------------
    // Customer Design Review (Approve / Request Changes)
    // ------------------------------------------------------------------

    /**
     * AJAX: Handle customer design approval or change request.
     *
     * Called from the new My Custom Grips page. Updates artwork status,
     * creates a system message, and triggers the status changed hook
     * for notifications.
     */
    public function handle_customer_review_design() {
        $design_id = isset( $_POST['design_id'] ) ? absint( $_POST['design_id'] ) : 0;

        if ( ! $design_id ) {
            wp_send_json_error( __( 'Missing design ID.', 'twintack-custom-grips' ) );
        }

        $this->verify_customer_request( $design_id );

        $action   = isset( $_POST['review_action'] ) ? sanitize_text_field( wp_unslash( $_POST['review_action'] ) ) : '';
        $feedback = isset( $_POST['feedback'] ) ? sanitize_textarea_field( wp_unslash( $_POST['feedback'] ) ) : '';

        if ( ! in_array( $action, array( 'approve', 'request_changes' ), true ) ) {
            wp_send_json_error( __( 'Invalid review action.', 'twintack-custom-grips' ) );
        }

        // Verify design is in a reviewable status
        $current_status = get_post_meta( $design_id, '_grip_artwork_status', true );
        if ( 'pending_review' !== $current_status ) {
            wp_send_json_error( __( 'This design is not currently awaiting review.', 'twintack-custom-grips' ) );
        }

        // Determine new status
        $new_status = ( 'approve' === $action ) ? 'customer_approved' : 'customer_requested_changes';

        // Update status via the Status class (which fires the ttcg_status_changed hook).
        // Customers are not in the staff role matrix; use customer self-service path after ownership check above.
        $result = TTCG_Status::update_status(
            $design_id,
            $new_status,
            get_current_user_id(),
            array( 'customer_self_service' => true )
        );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        // Store customer feedback
        if ( ! empty( $feedback ) ) {
            $timestamp = current_time( 'mysql' );
            $action_label = ( 'approve' === $action ) ? 'Approved' : 'Requested Changes';
            $feedback_entry = "[{$timestamp}] Customer {$action_label}: {$feedback}";

            $existing = get_post_meta( $design_id, '_grip_customer_feedback', true );
            if ( ! empty( $existing ) ) {
                $feedback_entry = $existing . "\n\n" . $feedback_entry;
            }

            update_post_meta( $design_id, '_grip_customer_feedback', $feedback_entry );
            update_post_meta( $design_id, '_grip_latest_customer_feedback', $feedback );
            update_post_meta( $design_id, '_grip_latest_customer_action', $action );
        }

        // Create a customer message in the thread for visibility
        $current_user = wp_get_current_user();
        $system_text = ( 'approve' === $action )
            ? sprintf( __( '%s approved the design.', 'twintack-custom-grips' ), $current_user->display_name )
            : sprintf( __( '%s requested changes.', 'twintack-custom-grips' ), $current_user->display_name );

        if ( ! empty( $feedback ) ) {
            $system_text .= ' ' . __( 'Feedback:', 'twintack-custom-grips' ) . ' ' . $feedback;
        }

        TTCG_Messaging::create_message( $design_id, $system_text, get_current_user_id(), 'text' );

        // Prepare response message
        $message = ( 'approve' === $action )
            ? __( 'Design approved! You can now proceed to purchase your custom grips.', 'twintack-custom-grips' )
            : __( 'Your feedback has been submitted. Our design team will work on the revisions.', 'twintack-custom-grips' );

        wp_send_json_success( array(
            'message'    => $message,
            'new_status' => $new_status,
            'action'     => $action,
        ) );
    }
}
