<?php
/**
 * TTCG_Messaging — Threaded message system for grip designs.
 *
 * Registers the grip_message custom post type, provides CRUD
 * helpers, and supports system-generated audit messages.
 *
 * @package TwinTack_Custom_Grips
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TTCG_Messaging {

    /** @var TTCG_Messaging|null */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * @return TTCG_Messaging
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor — registers the CPT on init.
     */
    private function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
    }

    // ------------------------------------------------------------------
    // Post Type Registration
    // ------------------------------------------------------------------

    /**
     * Register the grip_message custom post type.
     */
    public function register_post_type() {
        if ( post_type_exists( 'grip_message' ) ) {
            return;
        }

        register_post_type( 'grip_message', array(
            'labels' => array(
                'name'          => __( 'Grip Messages', 'twintack-custom-grips' ),
                'singular_name' => __( 'Grip Message', 'twintack-custom-grips' ),
            ),
            'public'       => false,
            'show_ui'      => false,
            'show_in_rest' => false,
            'supports'     => array( 'editor' ),
            'rewrite'      => false,
            'query_var'    => false,
        ) );
    }

    // ------------------------------------------------------------------
    // Create Messages
    // ------------------------------------------------------------------

    /**
     * Create a new message on a grip design thread.
     *
     * @param  int    $design_id  Grip design post ID.
     * @param  string $content    Message text.
     * @param  int    $sender_id  User ID of the sender.
     * @param  string $type       Message type: text | status_change | mockup_upload | system.
     * @param  array  $extra_meta Additional meta to store.
     * @return int|WP_Error       Message post ID on success.
     */
    public static function create_message( $design_id, $content, $sender_id, $type = 'text', $extra_meta = array() ) {
        $post_id = wp_insert_post( array(
            'post_type'    => 'grip_message',
            'post_status'  => 'publish',
            'post_content' => wp_kses_post( $content ),
            'post_author'  => $sender_id,
            'post_title'   => sprintf( 'Message on Grip #%d', $design_id ),
        ), true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Core meta
        update_post_meta( $post_id, '_ttcg_grip_design_id', $design_id );
        update_post_meta( $post_id, '_ttcg_sender_id', $sender_id );
        update_post_meta( $post_id, '_ttcg_sender_role', TTCG_Roles::get_user_role_slug( $sender_id ) );
        update_post_meta( $post_id, '_ttcg_message_type', $type );
        update_post_meta( $post_id, '_ttcg_read_by', array( $sender_id ) );

        // Any extra meta
        foreach ( $extra_meta as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }

        /**
         * Fires after a message is created on a grip design thread.
         *
         * @param int    $post_id    Message post ID.
         * @param int    $design_id  Grip design post ID.
         * @param int    $sender_id  Sender user ID.
         * @param string $type       Message type.
         */
        do_action( 'ttcg_message_created', $post_id, $design_id, $sender_id, $type );

        return $post_id;
    }

    /**
     * Create a system-generated message (no specific sender).
     *
     * @param  int    $design_id Grip design post ID.
     * @param  string $content   Message text.
     * @param  string $type      Message type slug.
     * @return int|WP_Error
     */
    public static function create_system_message( $design_id, $content, $type = 'system' ) {
        return self::create_message( $design_id, $content, 0, $type );
    }

    // ------------------------------------------------------------------
    // Query Messages
    // ------------------------------------------------------------------

    /**
     * Get all messages for a grip design, ordered chronologically.
     *
     * @param  int  $design_id  Grip design post ID.
     * @param  bool $customer_view If true, filters out internal-only messages.
     * @return array  Array of message data arrays.
     */
    public static function get_messages( $design_id, $customer_view = false ) {
        $args = array(
            'post_type'      => 'grip_message',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'meta_query'     => array(
                array(
                    'key'   => '_ttcg_grip_design_id',
                    'value' => $design_id,
                ),
            ),
        );

        $query    = new WP_Query( $args );
        $messages = array();

        foreach ( $query->posts as $post ) {
            $sender_id   = (int) get_post_meta( $post->ID, '_ttcg_sender_id', true );
            $sender_role = get_post_meta( $post->ID, '_ttcg_sender_role', true );
            $msg_type    = get_post_meta( $post->ID, '_ttcg_message_type', true );

            // In customer view, hide internal-only messages
            if ( $customer_view && 'internal_review' === $msg_type ) {
                continue;
            }

            $sender = get_userdata( $sender_id );

            $messages[] = array(
                'id'          => $post->ID,
                'content'     => $post->post_content,
                'sender_id'   => $sender_id,
                'sender_name' => $sender ? $sender->display_name : __( 'System', 'twintack-custom-grips' ),
                'sender_role' => $sender_role ?: 'system',
                'sender_avatar' => $sender ? get_avatar_url( $sender_id, array( 'size' => 48 ) ) : '',
                'type'        => $msg_type ?: 'text',
                'date'        => $post->post_date,
                'date_human'  => human_time_diff( strtotime( $post->post_date ), current_time( 'timestamp' ) ) . ' ago',
                'read_by'     => get_post_meta( $post->ID, '_ttcg_read_by', true ) ?: array(),
            );
        }

        return $messages;
    }

    /**
     * Get the unread message count for a grip design for a given user.
     *
     * @param  int $design_id
     * @param  int $user_id
     * @return int
     */
    public static function get_unread_count( $design_id, $user_id = 0 ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $messages = self::get_messages( $design_id );
        $unread   = 0;

        foreach ( $messages as $msg ) {
            if ( ! in_array( $user_id, (array) $msg['read_by'], true ) ) {
                $unread++;
            }
        }

        return $unread;
    }

    /**
     * Mark all messages on a design as read for a user.
     *
     * @param int $design_id
     * @param int $user_id
     */
    public static function mark_all_read( $design_id, $user_id = 0 ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $args = array(
            'post_type'      => 'grip_message',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => '_ttcg_grip_design_id',
                    'value' => $design_id,
                ),
            ),
        );

        $ids = get_posts( $args );

        foreach ( $ids as $msg_id ) {
            $read_by = get_post_meta( $msg_id, '_ttcg_read_by', true );
            if ( ! is_array( $read_by ) ) {
                $read_by = array();
            }
            if ( ! in_array( $user_id, $read_by, true ) ) {
                $read_by[] = $user_id;
                update_post_meta( $msg_id, '_ttcg_read_by', $read_by );
            }
        }
    }
}
