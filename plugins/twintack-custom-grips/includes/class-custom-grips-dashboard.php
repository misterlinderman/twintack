<?php
/**
 * TTCG_Dashboard — Dashboard controller.
 *
 * Queries grip_design posts, prepares data for templates,
 * computes statistics, and enqueues dashboard assets.
 *
 * @package TwinTack_Custom_Grips
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TTCG_Dashboard {

    /** @var TTCG_Dashboard|null */
    private static $instance = null;

    /** @var int  Designs per page. */
    const PER_PAGE = 20;

    /**
     * All artwork statuses with labels and colors.
     *
     * @var array
     */
    private static $statuses = array(
        'artwork_pending'             => array( 'label' => 'Mockup Required',      'color' => '#f0ad4e' ),
        'pending_review'              => array( 'label' => 'Customer Review',      'color' => '#5bc0de' ),
        'customer_requested_changes'  => array( 'label' => 'Customer Changes',     'color' => '#d9534f' ),
        'customer_approved'           => array( 'label' => 'Customer Approved',    'color' => '#5cb85c' ),
        'in_production'               => array( 'label' => 'In Production',        'color' => '#337ab7' ),
        'shipped'                     => array( 'label' => 'Shipped',              'color' => '#22b24c' ),
    );

    /**
     * Get the singleton instance.
     *
     * @return TTCG_Dashboard
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor — registers asset hooks.
     */
    private function __construct() {
        // Assets are loaded via the template, not wp_enqueue_scripts,
        // because the dashboard uses its own template_redirect flow.
    }

    // ------------------------------------------------------------------
    // Status Helpers
    // ------------------------------------------------------------------

    /**
     * Get all artwork statuses.
     *
     * @return array  Keyed by slug with label / color.
     */
    public static function get_statuses() {
        return self::$statuses;
    }

    /**
     * Normalize legacy status slugs to the current workflow.
     *
     * @param  string $status Raw status from post meta.
     * @return string
     */
    public static function normalize_status( $status ) {
        if ( 'approved_for_production' === $status ) {
            return 'in_production';
        }
        return $status;
    }

    /**
     * Get a human-readable label for a status slug.
     *
     * @param  string $status
     * @return string
     */
    public static function get_status_label( $status ) {
        $status = self::normalize_status( $status );
        return isset( self::$statuses[ $status ] ) ? self::$statuses[ $status ]['label'] : ucwords( str_replace( '_', ' ', $status ) );
    }

    /**
     * Get the color for a status slug.
     *
     * @param  string $status
     * @return string  Hex color.
     */
    public static function get_status_color( $status ) {
        $status = self::normalize_status( $status );
        return isset( self::$statuses[ $status ] ) ? self::$statuses[ $status ]['color'] : '#999';
    }

    // ------------------------------------------------------------------
    // Query Methods
    // ------------------------------------------------------------------

    /**
     * Query grip designs with filters, search and pagination.
     *
     * @param  array $args {
     *     Optional arguments.
     *
     *     @type string $status  Artwork status to filter by.
     *     @type string $search  Search term (customer name, team, email).
     *     @type string $orderby Sort field: date | title | customer_name | status.
     *     @type string $order   ASC or DESC.
     *     @type int    $paged   Page number.
     * }
     * @return WP_Query
     */
    public static function query_designs( $args = array() ) {
        $defaults = array(
            'status'  => '',
            'search'  => '',
            'orderby' => 'date',
            'order'   => 'DESC',
            'paged'   => 1,
        );

        $args = wp_parse_args( $args, $defaults );

        $query_args = array(
            'post_type'      => 'grip_design',
            'post_status'    => 'any',
            'posts_per_page' => self::PER_PAGE,
            'paged'          => absint( $args['paged'] ),
        );

        // Filter by artwork status
        if ( ! empty( $args['status'] ) ) {
            $status = sanitize_text_field( $args['status'] );
            if ( 'in_production' === $status ) {
                $query_args['meta_query'][] = array(
                    'key'     => '_grip_artwork_status',
                    'value'   => array( 'in_production', 'approved_for_production' ),
                    'compare' => 'IN',
                );
            } else {
                $query_args['meta_query'][] = array(
                    'key'   => '_grip_artwork_status',
                    'value' => $status,
                );
            }
        }

        // Search by customer name, team or email
        if ( ! empty( $args['search'] ) ) {
            $search = sanitize_text_field( $args['search'] );
            $query_args['meta_query']['relation'] = 'OR';
            $query_args['meta_query'][] = array(
                'key'     => '_grip_customer_name',
                'value'   => $search,
                'compare' => 'LIKE',
            );
            $query_args['meta_query'][] = array(
                'key'     => '_grip_customer_email',
                'value'   => $search,
                'compare' => 'LIKE',
            );
            $query_args['meta_query'][] = array(
                'key'     => '_grip_team_name',
                'value'   => $search,
                'compare' => 'LIKE',
            );
        }

        // Ordering
        switch ( $args['orderby'] ) {
            case 'customer_name':
                $query_args['meta_key'] = '_grip_customer_name';
                $query_args['orderby']  = 'meta_value';
                break;
            case 'status':
                $query_args['meta_key'] = '_grip_artwork_status';
                $query_args['orderby']  = 'meta_value';
                break;
            case 'title':
                $query_args['orderby'] = 'title';
                break;
            default:
                $query_args['orderby'] = 'date';
                break;
        }

        $query_args['order'] = in_array( strtoupper( $args['order'] ), array( 'ASC', 'DESC' ), true )
            ? strtoupper( $args['order'] )
            : 'DESC';

        return new WP_Query( $query_args );
    }

    /**
     * Get status counts for the filter tabs.
     *
     * @return array  Keyed by status slug with count values plus an 'all' key.
     */
    public static function get_status_counts() {
        global $wpdb;

        $counts = array( 'all' => 0 );

        foreach ( array_keys( self::$statuses ) as $status ) {
            $counts[ $status ] = 0;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $results = $wpdb->get_results(
            "SELECT pm.meta_value AS status, COUNT(*) AS cnt
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_grip_artwork_status'
               AND p.post_type = 'grip_design'
             GROUP BY pm.meta_value",
            OBJECT
        );

        if ( $results ) {
            foreach ( $results as $row ) {
                $slug = self::normalize_status( $row->status );
                if ( ! isset( $counts[ $slug ] ) ) {
                    $counts[ $slug ] = 0;
                }
                $counts[ $slug ] += (int) $row->cnt;
                $counts['all']   += (int) $row->cnt;
            }
        }

        // If 'all' is still 0, count posts without status meta too
        if ( 0 === $counts['all'] ) {
            $total = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'grip_design'"
            );
            $counts['all'] = (int) $total;
        }

        return $counts;
    }

    // ------------------------------------------------------------------
    // Data Helpers for Templates
    // ------------------------------------------------------------------

    /**
     * Get all meta data for a single grip design in a normalized array.
     *
     * @param  int $post_id  Grip design post ID.
     * @return array
     */
    public static function get_design_data( $post_id ) {
        $meta = get_post_meta( $post_id );

        $data = array(
            'id'                => $post_id,
            'title'             => get_the_title( $post_id ),
            'date'              => get_the_date( 'M j, Y', $post_id ),
            'customer_name'     => self::meta_val( $meta, '_grip_customer_name' ),
            'customer_email'    => self::meta_val( $meta, '_grip_customer_email' ),
            'team_name'         => self::meta_val( $meta, '_grip_team_name' ),
            'design_type'       => self::meta_val( $meta, '_grip_design_type' ),
            'design_layout'     => self::meta_val( $meta, '_grip_design_layout' ),
            'primary_color'     => self::meta_val( $meta, '_grip_primary_color' ),
            'secondary_color'   => self::meta_val( $meta, '_grip_secondary_color' ),
            'tertiary_color'    => self::meta_val( $meta, '_grip_tertiary_color' ),
            'quantity'          => self::meta_val( $meta, '_grip_quantity' ),
            'artwork_status'    => self::normalize_status( self::meta_val( $meta, '_grip_artwork_status', 'artwork_pending' ) ),
            'artwork_url'       => self::meta_val( $meta, '_grip_artwork_url' ),
            'artwork_filename'  => self::meta_val( $meta, '_grip_artwork_filename' ),
            'mockup_url'        => self::meta_val( $meta, '_grip_mockup_url' ),
            'mockup_filename'   => self::meta_val( $meta, '_grip_mockup_filename' ),
            'mockup_asset_url'  => self::meta_val( $meta, '_grip_mockup_asset_url' ),
            'order_id'          => self::meta_val( $meta, '_grip_order_id' ),
            'final_order_id'    => self::meta_val( $meta, '_grip_final_order_id' ),
            'monday_item_id'    => self::meta_val( $meta, '_grip_monday_item_id' ),
            'feedback'          => self::meta_val( $meta, '_grip_feedback' ),
            'form_type'         => self::meta_val( $meta, '_grip_form_type' ),
            'production_started'=> self::meta_val( $meta, '_grip_production_started' ),
            'featured_image'      => get_the_post_thumbnail_url( $post_id, 'medium' ),
            'featured_image_full' => get_the_post_thumbnail_url( $post_id, 'full' ),
        );

        // Determine the best mockup image URL to display (medium — for list views / thumbnails)
        $data['mockup_display_url'] = '';
        if ( ! empty( $data['featured_image'] ) ) {
            $data['mockup_display_url'] = $data['featured_image'];
        } elseif ( ! empty( $data['mockup_url'] ) ) {
            $data['mockup_display_url'] = $data['mockup_url'];
        } elseif ( ! empty( $data['mockup_asset_url'] ) ) {
            $data['mockup_display_url'] = $data['mockup_asset_url'];
        }

        // Full-resolution mockup URL (for detail views / customer approval)
        $data['mockup_full_url'] = '';
        if ( ! empty( $data['featured_image_full'] ) ) {
            $data['mockup_full_url'] = $data['featured_image_full'];
        } elseif ( ! empty( $data['mockup_url'] ) ) {
            $data['mockup_full_url'] = $data['mockup_url'];
        } elseif ( ! empty( $data['mockup_asset_url'] ) ) {
            $data['mockup_full_url'] = $data['mockup_asset_url'];
        }

        return $data;
    }

    /**
     * Safely extract a single meta value from a get_post_meta() array.
     *
     * @param  array  $meta    Raw post meta array.
     * @param  string $key     Meta key.
     * @param  mixed  $default Default value.
     * @return mixed
     */
    private static function meta_val( $meta, $key, $default = '' ) {
        return isset( $meta[ $key ][0] ) ? $meta[ $key ][0] : $default;
    }

    // ------------------------------------------------------------------
    // Asset Enqueueing (called from the template)
    // ------------------------------------------------------------------

    /**
     * Enqueue all dashboard assets. Called from templates/dashboard.php.
     */
    public static function enqueue_assets() {
        // CSS
        wp_enqueue_style(
            'ttcg-dashboard',
            TTCG_PLUGIN_URL . 'assets/css/dashboard.css',
            array(),
            TTCG_VERSION
        );

        // Core WordPress media scripts (for mockup uploads)
        wp_enqueue_media();

        // Dashboard JS
        wp_enqueue_script(
            'ttcg-dashboard',
            TTCG_PLUGIN_URL . 'assets/js/dashboard.js',
            array( 'jquery' ),
            TTCG_VERSION,
            true
        );

        // Messaging JS
        wp_enqueue_script(
            'ttcg-messaging',
            TTCG_PLUGIN_URL . 'assets/js/messaging.js',
            array( 'jquery' ),
            TTCG_VERSION,
            true
        );

        // Mockup Upload JS
        wp_enqueue_script(
            'ttcg-mockup-upload',
            TTCG_PLUGIN_URL . 'assets/js/mockup-upload.js',
            array( 'jquery' ),
            TTCG_VERSION,
            true
        );

        // Localize AJAX data for all scripts
        $ajax_data = array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ttcg_nonce' ),
            'user_id'  => get_current_user_id(),
            'strings'  => array(
                'confirm_status'  => __( 'Are you sure you want to change the status?', 'twintack-custom-grips' ),
                'saving'          => __( 'Saving…', 'twintack-custom-grips' ),
                'saved'           => __( 'Saved!', 'twintack-custom-grips' ),
                'error'           => __( 'An error occurred. Please try again.', 'twintack-custom-grips' ),
                'uploading'       => __( 'Uploading…', 'twintack-custom-grips' ),
                'upload_success'  => __( 'Mockup uploaded successfully!', 'twintack-custom-grips' ),
                'sending'         => __( 'Sending…', 'twintack-custom-grips' ),
                'sent'            => __( 'Message sent!', 'twintack-custom-grips' ),
                'drop_files'      => __( 'Drop files here or click to upload', 'twintack-custom-grips' ),
            ),
        );

        wp_localize_script( 'ttcg-dashboard', 'ttcg', $ajax_data );
        wp_localize_script( 'ttcg-messaging', 'ttcg', $ajax_data );
        wp_localize_script( 'ttcg-mockup-upload', 'ttcg', $ajax_data );
    }
}
