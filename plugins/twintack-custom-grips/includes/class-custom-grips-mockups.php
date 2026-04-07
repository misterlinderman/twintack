<?php
/**
 * TTCG_Mockups — Mockup upload and version history management.
 *
 * Handles drag-and-drop mockup uploads from the frontend dashboard,
 * stores them in the WordPress Media Library, maintains version
 * history, and auto-sets the artwork status to pending_review.
 *
 * @package TwinTack_Custom_Grips
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TTCG_Mockups {

    /** @var TTCG_Mockups|null */
    private static $instance = null;

    /**
     * Allowed MIME types for mockup uploads.
     *
     * @var array
     */
    private static $allowed_types = array(
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
    );

    /**
     * Maximum upload size in bytes (50 MB).
     *
     * @var int
     */
    const MAX_UPLOAD_SIZE = 52428800;

    /**
     * Get the singleton instance.
     *
     * @return TTCG_Mockups
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        // Upload handled via the AJAX class
    }

    // ------------------------------------------------------------------
    // Upload Processing
    // ------------------------------------------------------------------

    /**
     * Process a mockup file upload for a grip design.
     *
     * @param  int   $design_id Post ID of the grip design.
     * @param  array $file      $_FILES array element.
     * @param  int   $user_id   Uploading user ID.
     * @return array|WP_Error   Array with attachment data on success.
     */
    public static function process_upload( $design_id, $file, $user_id = 0 ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        // Validate the file
        $validation = self::validate_file( $file );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        // Require WordPress file handling
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        // Upload the file
        $overrides = array( 'test_form' => false );
        $upload    = wp_handle_upload( $file, $overrides );

        if ( isset( $upload['error'] ) ) {
            return new WP_Error( 'ttcg_upload_error', $upload['error'] );
        }

        // Create attachment post
        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name( $file['name'] ),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_author'    => $user_id,
        );

        $attach_id = wp_insert_attachment( $attachment, $upload['file'], $design_id );

        if ( is_wp_error( $attach_id ) ) {
            return $attach_id;
        }

        // Generate attachment metadata
        $attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
        wp_update_attachment_metadata( $attach_id, $attach_data );

        // Save previous mockup to version history before overwriting
        self::save_to_history( $design_id );

        // Update grip design meta
        update_post_meta( $design_id, '_grip_mockup_url', $upload['url'] );
        update_post_meta( $design_id, '_grip_mockup_filename', $file['name'] );
        update_post_meta( $design_id, '_grip_mockup_attachment_id', $attach_id );

        // Set as featured image
        set_post_thumbnail( $design_id, $attach_id );

        // Auto-change status to pending_review
        $old_status = get_post_meta( $design_id, '_grip_artwork_status', true );
        if ( 'pending_review' !== $old_status ) {
            TTCG_Status::update_status(
                $design_id,
                'pending_review',
                $user_id,
                array( 'suppress_customer_status_email' => true )
            );
        }

        // Create system message
        $user     = get_userdata( $user_id );
        $username = $user ? $user->display_name : __( 'Team Member', 'twintack-custom-grips' );

        TTCG_Messaging::create_system_message(
            $design_id,
            sprintf(
                /* translators: 1: user name, 2: file name */
                __( '%1$s uploaded a new mockup: %2$s', 'twintack-custom-grips' ),
                $username,
                $file['name']
            ),
            'mockup_upload'
        );

        /**
         * Fires after a mockup is successfully uploaded.
         *
         * @param int   $design_id   Grip design post ID.
         * @param int   $attach_id   WordPress attachment ID.
         * @param int   $user_id     Uploader user ID.
         */
        do_action( 'ttcg_mockup_uploaded', $design_id, $attach_id, $user_id );

        if ( WP_DEBUG ) {
            error_log( sprintf(
                'TTCG Mockups: New mockup (attachment #%d) uploaded for design #%d by user #%d.',
                $attach_id,
                $design_id,
                $user_id
            ) );
        }

        return array(
            'attachment_id' => $attach_id,
            'url'           => $upload['url'],
            'filename'      => $file['name'],
        );
    }

    // ------------------------------------------------------------------
    // Version History
    // ------------------------------------------------------------------

    /**
     * Save the current mockup to the version history before replacing.
     *
     * @param int $design_id
     */
    private static function save_to_history( $design_id ) {
        $current_url      = get_post_meta( $design_id, '_grip_mockup_url', true );
        $current_filename = get_post_meta( $design_id, '_grip_mockup_filename', true );
        $current_attach   = get_post_meta( $design_id, '_grip_mockup_attachment_id', true );

        if ( empty( $current_url ) ) {
            return;
        }

        $history = get_post_meta( $design_id, '_ttcg_mockup_history', true );
        if ( ! is_array( $history ) ) {
            $history = array();
        }

        $history[] = array(
            'url'           => $current_url,
            'filename'      => $current_filename,
            'attachment_id' => $current_attach,
            'replaced_at'   => current_time( 'mysql' ),
            'replaced_by'   => get_current_user_id(),
        );

        update_post_meta( $design_id, '_ttcg_mockup_history', $history );
    }

    /**
     * Get mockup version history for a design.
     *
     * @param  int $design_id
     * @return array
     */
    public static function get_history( $design_id ) {
        $history = get_post_meta( $design_id, '_ttcg_mockup_history', true );
        return is_array( $history ) ? $history : array();
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    /**
     * Validate the uploaded file.
     *
     * @param  array $file  $_FILES element.
     * @return true|WP_Error
     */
    private static function validate_file( $file ) {
        if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            return new WP_Error( 'ttcg_no_file', __( 'No file was uploaded.', 'twintack-custom-grips' ) );
        }

        if ( $file['size'] > self::MAX_UPLOAD_SIZE ) {
            return new WP_Error( 'ttcg_file_too_large', __( 'File exceeds the maximum upload size of 50 MB.', 'twintack-custom-grips' ) );
        }

        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        $mime  = finfo_file( $finfo, $file['tmp_name'] );
        finfo_close( $finfo );

        if ( ! in_array( $mime, self::$allowed_types, true ) ) {
            return new WP_Error(
                'ttcg_invalid_type',
                __( 'Invalid file type. Allowed: JPG, PNG, GIF, WebP, PDF.', 'twintack-custom-grips' )
            );
        }

        return true;
    }
}
