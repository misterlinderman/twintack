<?php
class TwinTack_Grip_Post_Type {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_statuses'));
        add_action('rest_api_init', array($this, 'register_meta_fields'));
        add_action('rest_api_init', array($this, 'register_monday_api_endpoint'));
        add_action('init', array($this, 'fix_existing_grip_designs'));
        add_action('init', array($this, 'initialize_artwork_status'));
        add_filter('single_template', array($this, 'load_grip_design_template'));
        
        // Add admin meta boxes and functionality
        add_action('add_meta_boxes', array($this, 'add_admin_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_filter('display_post_states', array($this, 'add_artwork_status_to_post_states'), 10, 2);
        add_action('admin_notices', array($this, 'display_status_change_notices'));
        
        // Add WooCommerce order status change hook
        add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_changed'), 10, 3);
        
        // Add webhook URL filters
        add_filter('grip_customer_feedback_webhook_url', array($this, 'get_customer_feedback_webhook_url'));
        add_filter('grip_production_approval_webhook_url', array($this, 'get_production_approval_webhook_url'));
        
        // Removed problematic REST API hooks that were causing critical errors
    }
    
    public function register_post_type() {
        $labels = array(
            'name' => 'Grip Designs',
            'singular_name' => 'Grip Design',
            'add_new' => 'Add New Design',
            'add_new_item' => 'Add New Grip Design',
            'edit_item' => 'Edit Grip Design',
            'new_item' => 'New Grip Design',
            'view_item' => 'View Grip Design',
            'search_items' => 'Search Grip Designs',
            'not_found' => 'No grip designs found',
            'not_found_in_trash' => 'No grip designs found in trash',
            'menu_name' => 'Grip Designs'
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 56,
            'menu_icon' => 'dashicons-art',
            'supports' => array('title', 'editor', 'custom-fields', 'author'),
            'hierarchical' => false,
            'rewrite' => array('slug' => 'grip-designs'),
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'has_archive' => true,
            'show_in_nav_menus' => true,
            'publicly_queryable' => true,
            'show_in_rest' => true,
            'rest_base' => 'grip_design',
            'rest_controller_class' => 'WP_REST_Posts_Controller'
        );

        register_post_type('grip_design', $args);
        
        // Force flush rewrite rules once after registering
        if (get_option('grip_design_plugin_rewrite_flushed') !== '1') {
            flush_rewrite_rules();
            update_option('grip_design_plugin_rewrite_flushed', '1');
        }
    }

    public function register_statuses() {
        // No longer registering custom statuses
        // We're using core WordPress statuses with custom display labels
        // See twintack_get_grip_status_label() function in theme for mapping
    }

    public function register_meta_fields() {
        $meta_fields = array(
            '_grip_customer_name',
            '_grip_customer_email', 
            '_grip_team_name',
            '_grip_design_type',
            '_grip_design_layout',
            '_grip_primary_color',
            '_grip_secondary_color', 
            '_grip_tertiary_color',
            '_grip_quantity',
            '_grip_form_entry_id',
            '_grip_artwork_url',
            '_grip_artwork_filename',
            '_grip_feedback',
            '_grip_monday_feedback',
            '_grip_monday_item_id',
            '_grip_customer_feedback',
            '_grip_latest_customer_feedback',
            '_grip_latest_customer_action',
            '_grip_final_order_id',
            '_grip_production_started',
            '_grip_mockup_url',
            '_grip_mockup_filename',
            '_grip_artwork_status',
            '_grip_mockup_asset_id',
            '_grip_mockup_asset_url'
        );

        foreach ($meta_fields as $meta_key) {
            try {
                register_rest_field('grip_design', $meta_key, array(
                    'get_callback' => function($post) use ($meta_key) {
                        return get_post_meta($post['id'], $meta_key, true);
                    },
                    'update_callback' => function($value, $post) use ($meta_key) {
                        return update_post_meta($post->ID, $meta_key, $value);
                    },
                    'schema' => array(
                        'description' => 'Grip design meta field: ' . $meta_key,
                        'type' => 'string',
                        'context' => array('view', 'edit'),
                        'single' => true,
                        'show_in_rest' => true,
                    )
                ));
            } catch (Exception $e) {
                if (WP_DEBUG) {
                    error_log('Error registering REST field ' . $meta_key . ': ' . $e->getMessage());
                }
            }
        }
        
        // Add artwork status fields for REST API
        try {
            register_rest_field('grip_design', 'artwork_status', array(
                'get_callback' => function($post) {
                    $artwork_status = get_post_meta($post['id'], '_grip_artwork_status', true);
                    return $artwork_status ?: 'artwork_pending'; // Default status
                },
                'update_callback' => function($value, $post) {
                    return update_post_meta($post->ID, '_grip_artwork_status', $value);
                },
                'schema' => array(
                    'description' => 'Artwork status for grip design',
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'enum' => array(
                        'artwork_pending',
                        'pending_review',
                        'customer_requested_changes',
                        'customer_approved',
                        'artwork_approved',
                        'approved_for_production',
                        'internal_review',
                        'in_production',
                        'shipped'
                    ),
                    'single' => true,
                    'show_in_rest' => true,
                )
        ));
        
            register_rest_field('grip_design', 'artwork_status_label', array(
                'get_callback' => function($post) {
                    $artwork_status = get_post_meta($post['id'], '_grip_artwork_status', true);
                    return $this->get_artwork_status_label($artwork_status ?: 'artwork_pending');
                },
                'schema' => array(
                    'description' => 'Human readable artwork status label',
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                )
            ));

            // Monday.com Item ID field for easier Make.com access
            register_rest_field('grip_design', 'monday_item_id', array(
                'get_callback' => function($post) {
                    return get_post_meta($post['id'], '_grip_monday_item_id', true);
                },
                'update_callback' => function($value, $post) {
                    return update_post_meta($post->ID, '_grip_monday_item_id', $value);
                },
                'schema' => array(
                    'description' => 'Monday.com item ID for this grip design',
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'single' => true,
                    'show_in_rest' => true,
                )
            ));

            // Customer feedback fields for easier Make.com access
            register_rest_field('grip_design', 'customer_feedback_history', array(
                'get_callback' => function($post) {
                    $history = get_post_meta($post['id'], '_grip_customer_feedback', true);
                    // Ensure we return a string that Make.com can parse
                    if (is_array($history)) {
                        return json_encode($history);
                    }
                    return $history ?: '';
                },
                'schema' => array(
                    'description' => 'Complete customer feedback history as JSON string',
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                )
            ));
            
            // Add revision count field to help Make.com detect unique updates
            register_rest_field('grip_design', 'feedback_revision_count', array(
                'get_callback' => function($post) {
                    $count = get_post_meta($post['id'], '_grip_feedback_revision_count', true);
                    return (int) $count ?: 0;
                },
                'schema' => array(
                    'description' => 'Number of customer feedback revisions (helps Make.com detect updates)',
                    'type' => 'integer',
                    'context' => array('view', 'edit'),
                )
            ));

            register_rest_field('grip_design', 'latest_customer_feedback', array(
                'get_callback' => function($post) {
                    return get_post_meta($post['id'], '_grip_latest_customer_feedback', true);
                },
                'schema' => array(
                    'description' => 'Most recent customer feedback text',
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                )
            ));

            register_rest_field('grip_design', 'latest_customer_action', array(
                'get_callback' => function($post) {
                    return get_post_meta($post['id'], '_grip_latest_customer_action', true);
                },
                'schema' => array(
                    'description' => 'Most recent customer action (approve or request_changes)',
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'enum' => array('approve', 'request_changes'),
                )
            ));

            register_rest_field('grip_design', 'final_order_id', array(
                'get_callback' => function($post) {
                    return get_post_meta($post['id'], '_grip_final_order_id', true);
                },
                'schema' => array(
                    'description' => 'WooCommerce order ID after final purchase',
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                )
            ));

            register_rest_field('grip_design', 'production_started', array(
                'get_callback' => function($post) {
                    return get_post_meta($post['id'], '_grip_production_started', true);
                },
                'schema' => array(
                    'description' => 'Production start timestamp',
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                )
            ));
        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error registering artwork status fields: ' . $e->getMessage());
            }
        }
    }

    public function register_monday_api_endpoint() {
        // Test endpoint for Make.com connectivity
        register_rest_route('twintack/v1', '/test', array(
            'methods' => array('GET', 'POST'),
            'callback' => array($this, 'test_api_endpoint'),
            'permission_callback' => '__return_true', // Public for testing
        ));
        
        // Existing Monday.com API endpoint
        register_rest_route('twintack/v1', '/grip-design/(?P<id>\d+)/monday', array(
            'methods' => array('POST', 'GET'), // Allow both POST and GET for testing
            'callback' => array($this, 'update_monday_data'),
            'permission_callback' => array($this, 'check_monday_api_permission'),
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
                'monday_feedback' => array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
                'monday_item_id' => array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'mockup_asset_id' => array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'mockup_asset_url' => array(
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ),
                'wordpress_media_id' => array(
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'artwork_status' => array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'enum' => array(
                        'artwork_pending',
                        'pending_review',
                        'customer_requested_changes',
                        'customer_approved',
                        'artwork_approved',
                        'approved_for_production',
                        'internal_review',
                        'in_production',
                        'shipped'
                    ),
                ),
            ),
        ));
    }
    
    public function check_monday_api_permission($request) {
        // Check for API key in header or parameter
        $api_key = $request->get_header('X-API-Key') ?: $request->get_param('api_key');
        
        // You can set this in wp-config.php: define('TWINTACK_MONDAY_API_KEY', 'your-secret-key');
        $valid_key = defined('TWINTACK_MONDAY_API_KEY') ? TWINTACK_MONDAY_API_KEY : 'twintack-monday-2024';
        
        if (WP_DEBUG) {
            error_log('TwinTack Monday API: Permission check - Key provided: ' . (!empty($api_key) ? 'YES' : 'NO'));
            error_log('TwinTack Monday API: Using API key: ' . substr($valid_key, 0, 8) . '...');
        }
        
        if ($api_key !== $valid_key) {
            if (WP_DEBUG) {
                error_log('TwinTack Monday API: Permission denied - Invalid API key');
            }
            return new WP_Error('rest_forbidden', 'Invalid API key', array('status' => 401));
        }
        
        return true;
    }
    
    public function test_api_endpoint($request) {
        $method = $request->get_method();
        $params = $request->get_params();
        
        if (WP_DEBUG) {
            error_log('TwinTack API Test: Method=' . $method . ', Params=' . json_encode($params));
        }
        
        $response = array(
            'success' => true,
            'message' => 'TwinTack API is working correctly',
            'method' => $method,
            'timestamp' => current_time('c'),
            'site_url' => get_site_url(),
            'api_version' => '1.6.03',
            'params_received' => count($params),
            'test_string' => 'hello_world',
            'test_number' => 123,
            'test_boolean' => true
        );
        
        return rest_ensure_response($response);
    }
    
    public function update_monday_data($request) {
        // Multiple approaches to increase execution time for file processing
        @ini_set('max_execution_time', 300); // 5 minutes
        @ini_set('max_input_time', 300);     // 5 minutes for input processing
        @set_time_limit(300); // Alternative method
        
        // WordPress-specific time limit increase
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }
        
        $grip_id = $request->get_param('id');
        
        // Verify the grip design exists
        $post = get_post($grip_id);
        
        if (WP_DEBUG) {
            error_log('TwinTack Monday API: Processing update for grip ID: ' . $grip_id);
        }
        
        if (!$post || $post->post_type !== 'grip_design') {
            $error_response = array(
                'error' => 'not_found',
                'message' => 'Grip design not found (ID: ' . $grip_id . ')',
                'post_exists' => !empty($post),
                'post_type' => $post ? $post->post_type : 'null',
                'expected_type' => 'grip_design',
                'timestamp' => current_time('c'),
                'status' => 404
            );
            
            if (WP_DEBUG) {
                error_log('TwinTack Monday API: Returning error response: ' . json_encode($error_response));
            }
            
            return new WP_Error('not_found', $error_response['message'], $error_response);
        }
        
        $updated_fields = array();
        
        // Update Monday.com feedback with history
        if ($request->has_param('monday_feedback')) {
            if (WP_DEBUG) {
                error_log('TwinTack Monday API: Processing feedback update');
                error_log('TwinTack Monday API: Raw feedback data: ' . print_r($request->get_param('monday_feedback'), true));
            }
            
            $feedback = $request->get_param('monday_feedback');
            
            // Handle potential JSON string from Monday.com Notes column
            if (is_string($feedback) && strpos($feedback, '{') === 0) {
                $decoded = json_decode($feedback, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['text'])) {
                    $feedback = $decoded['text'];
                }
            }
            
            if (WP_DEBUG) {
                error_log('TwinTack Monday API: Processed feedback: ' . $feedback);
            }
            
            // Get existing feedback history or initialize new array
            $feedback_history = get_post_meta($grip_id, '_grip_monday_feedback_history', true);
            if (!is_array($feedback_history)) {
                $feedback_history = array();
            }
            
            if (WP_DEBUG) {
                error_log('TwinTack Monday API: Current feedback history: ' . json_encode($feedback_history));
            }
            
            // Add new feedback entry with timestamp
            $feedback_entry = array(
                'message' => $feedback,
                'timestamp' => current_time('c')
            );
            $feedback_history[] = $feedback_entry;
            
            if (WP_DEBUG) {
                error_log('TwinTack Monday API: New feedback entry: ' . json_encode($feedback_entry));
                error_log('TwinTack Monday API: Updated feedback history: ' . json_encode($feedback_history));
            }
            
            // Update both current feedback and history
            $current_result = update_post_meta($grip_id, '_grip_monday_feedback', $feedback);
            $history_result = update_post_meta($grip_id, '_grip_monday_feedback_history', $feedback_history);
            
            if (WP_DEBUG) {
                error_log('TwinTack Monday API: Update results - Current: ' . ($current_result ? 'SUCCESS' : 'FAILED') . ', History: ' . ($history_result ? 'SUCCESS' : 'FAILED'));
            }
            
            $updated_fields['monday_feedback'] = $feedback;
            $updated_fields['monday_feedback_history'] = json_encode($feedback_history);
        }
        
        // Update Monday.com item ID
        if ($request->has_param('monday_item_id')) {
            $item_id = $request->get_param('monday_item_id');
            $result = update_post_meta($grip_id, '_grip_monday_item_id', $item_id);
            $updated_fields['monday_item_id'] = $item_id;
            
            if (WP_DEBUG) {
                error_log('Updated monday_item_id: ' . ($result ? 'SUCCESS' : 'FAILED'));
            }
        }
        
        // Update mockup asset ID
        if ($request->has_param('mockup_asset_id')) {
            $asset_id = $request->get_param('mockup_asset_id');
            $result = update_post_meta($grip_id, '_grip_mockup_asset_id', $asset_id);
            $updated_fields['mockup_asset_id'] = $asset_id;
            
            if (WP_DEBUG) {
                error_log('Updated mockup_asset_id: ' . ($result ? 'SUCCESS' : 'FAILED'));
            }
        }
        
        // Update mockup asset URL
        if ($request->has_param('mockup_asset_url')) {
            $asset_url = $request->get_param('mockup_asset_url');
            $result = update_post_meta($grip_id, '_grip_mockup_asset_url', $asset_url);
            $updated_fields['mockup_asset_url'] = $asset_url;
            
            if (WP_DEBUG) {
                error_log('Updated mockup_asset_url: ' . ($result ? 'SUCCESS' : 'FAILED'));
            }
            
            // If a WordPress media ID is provided, set it as featured image
            if ($request->has_param('wordpress_media_id')) {
                $media_id = intval($request->get_param('wordpress_media_id'));
                if ($media_id > 0) {
                    // Increase execution time before image processing
                    @set_time_limit(300);
                    
                    $result = set_post_thumbnail($grip_id, $media_id);
                    $updated_fields['featured_image_set'] = $media_id;
                    
                    if (WP_DEBUG) {
                        error_log('Set featured image ID ' . $media_id . ': ' . ($result ? 'SUCCESS' : 'FAILED'));
                    }
                }
            }
        }
        
        // Update artwork status
        if ($request->has_param('artwork_status')) {
            $old_status = get_post_meta($grip_id, '_grip_artwork_status', true);
            $artwork_status = $request->get_param('artwork_status');
            $result = update_post_meta($grip_id, '_grip_artwork_status', $artwork_status);
            $updated_fields['artwork_status'] = $artwork_status;
            $updated_fields['artwork_status_label'] = $this->get_artwork_status_label($artwork_status);
            
            if (WP_DEBUG) {
                error_log('Updated artwork_status to ' . $artwork_status . ': ' . ($result ? 'SUCCESS' : 'FAILED'));
            }
            
            // Trigger email notification if status changed
            if ($result && $old_status !== $artwork_status) {
                do_action('grip_design_artwork_status_changed', $grip_id, $old_status, $artwork_status);
            }
        }
        
        // Log the successful update
        if (WP_DEBUG) {
            error_log('TwinTack Monday API: Successfully updated grip design ' . $grip_id . ' with data: ' . json_encode($updated_fields));
            error_log('=== End TwinTack Monday API Request ===');
        }
        
        $response = array(
            'success' => true,
            'grip_id' => $grip_id,
            'message' => 'Grip design updated successfully',
            'timestamp' => current_time('c'),
            'post_title' => $post->post_title,
            'post_status' => $post->post_status,
            'total_updates' => count($updated_fields),
            'updated_fields' => json_encode($updated_fields)
        );
        
        return rest_ensure_response($response);
    }

    public function fix_existing_grip_designs() {
        // Only run once
        if (get_option('twintack_grip_designs_authors_fixed')) {
            return;
        }
        
        // Get all grip designs without authors (post_author = 0)
        $grip_designs = get_posts(array(
            'post_type' => 'grip_design',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'author' => 0 // Only get posts with no author
        ));
        
        foreach ($grip_designs as $design) {
            // Try to get the customer email from meta
            $customer_email = get_post_meta($design->ID, '_grip_customer_email', true);
            
            if ($customer_email) {
                // Find user by email
                $user = get_user_by('email', $customer_email);
                
                if ($user) {
                    // Update the post author
                    wp_update_post(array(
                        'ID' => $design->ID,
                        'post_author' => $user->ID
                    ));
                    
                    error_log("Fixed grip design {$design->ID} author to user {$user->ID} ({$customer_email})");
                }
            }
        }
        
        // Mark as fixed
        update_option('twintack_grip_designs_authors_fixed', true);
    }
    
    public function initialize_artwork_status() {
        // Only run once
        if (get_option('twintack_artwork_status_initialized')) {
            return;
        }
        
        // Get all grip designs without artwork status
        $grip_designs = get_posts(array(
            'post_type' => 'grip_design',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => '_grip_artwork_status',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));
        
        foreach ($grip_designs as $design) {
            // Set default artwork status based on current post status
            $current_status = get_post_status($design->ID);
            $artwork_status = 'artwork_pending'; // Default
            
            // Default all existing designs to "artwork_pending" 
            // regardless of post status - let staff manually approve them
            $artwork_status = 'artwork_pending';
            
            update_post_meta($design->ID, '_grip_artwork_status', $artwork_status);
            error_log("Initialized artwork status for grip design {$design->ID}: {$artwork_status}");
        }
        
        // Mark as initialized
        update_option('twintack_artwork_status_initialized', true);
    }
    
    private function get_artwork_status_label($artwork_status) {
        $status_map = array(
            'artwork_pending'            => 'Artwork Pending',
            'pending_review'             => 'Pending Review', 
            'customer_requested_changes' => 'Customer Requested Changes',
            'customer_approved'          => 'Customer Approved',
            'artwork_approved'           => 'Artwork Approved',
            'approved_for_production'    => 'Approved for Production',
            'internal_review'            => 'Internal Review',
            'in_production'              => 'In Production',
            'shipped'                    => 'Shipped'
        );
        
        return isset($status_map[$artwork_status]) ? $status_map[$artwork_status] : 'Artwork Pending';
    }

    // Removed problematic REST API methods that were causing critical errors

    public function load_grip_design_template($template) {
        global $post;

        if ($post->post_type === 'grip_design') {
            // Skip loading the custom template if we're in the My Account area
            // This prevents double content display in the account area
            if (is_account_page()) {
                return $template;
            }
            
            $custom_template = plugin_dir_path(dirname(__FILE__)) . 'templates/single-grip-design.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        return $template;
    }
    
    /**
     * Add meta boxes for grip design admin interface
     */
    public function add_admin_meta_boxes() {
        add_meta_box(
            'grip_design_status',
            'Artwork Status',
            array($this, 'render_status_meta_box'),
            'grip_design',
            'side',
            'high'
        );
        
        add_meta_box(
            'grip_design_details',
            'Grip Design Details',
            array($this, 'render_details_meta_box'),
            'grip_design',
            'normal',
            'high'
        );
        
        add_meta_box(
            'grip_design_customer_feedback',
            'Customer Feedback',
            array($this, 'render_customer_feedback_meta_box'),
            'grip_design',
            'normal',
            'default'
        );
        
        add_meta_box(
            'grip_design_monday',
            'Monday.com Integration',
            array($this, 'render_monday_meta_box'),
            'grip_design',
            'normal',
            'default'
        );
    }
    
    /**
     * Render the status meta box
     */
    public function render_status_meta_box($post) {
        wp_nonce_field('grip_design_status_nonce', 'grip_design_status_nonce');
        
        $current_post_status = get_post_status($post->ID);
        $current_artwork_status = get_post_meta($post->ID, '_grip_artwork_status', true) ?: 'artwork_pending';
        
        // Post Status Section
        echo '<div style="margin-bottom: 20px; padding: 15px; background: #f0f8ff; border-left: 4px solid #0073aa;">';
        echo '<h4 style="margin: 0 0 10px 0;">Post Status</h4>';
        echo '<p><strong>Current:</strong> <span style="color: #0073aa;">' . esc_html(ucfirst($current_post_status)) . '</span></p>';
        echo '<p style="font-size: 12px; color: #666; margin: 5px 0 0 0;">Post status controls WordPress visibility and functions normally. New posts default to Published.</p>';
        echo '</div>';
        
        // Artwork Status Section
        echo '<div style="margin-bottom: 15px;">';
        echo '<h4 style="margin: 0 0 10px 0;">Artwork Status (Customer Visible)</h4>';
        echo '<p style="margin-bottom: 10px;"><strong>Current:</strong> <span style="color: #d63638;">' . esc_html($this->get_artwork_status_label($current_artwork_status)) . '</span></p>';
        echo '<label for="grip_artwork_status"><strong>Change Artwork Status:</strong></label><br>';
        echo '<select name="grip_artwork_status" id="grip_artwork_status" style="width: 100%; margin-top: 5px;">';
        
        $artwork_statuses = array(
            'artwork_pending'            => 'Artwork Pending',
            'pending_review'             => 'Pending Review',
            'customer_requested_changes' => 'Customer Requested Changes',
            'customer_approved'          => 'Customer Approved',
            'artwork_approved'           => 'Artwork Approved',
            'approved_for_production'    => 'Approved for Production',
            'internal_review'            => 'Internal Review',
            'in_production'              => 'In Production',
            'shipped'                    => 'Shipped'
        );
        
        foreach ($artwork_statuses as $status_value => $status_label) {
            $selected = selected($current_artwork_status, $status_value, false);
            echo '<option value="' . esc_attr($status_value) . '" ' . $selected . '>' . esc_html($status_label) . '</option>';
        }
        
        echo '</select>';
        echo '</div>';
        
        echo '<div style="margin-top: 15px; padding: 10px; background: #f9f9f9; border-left: 4px solid #d63638;">';
        echo '<h4 style="margin: 0 0 10px 0;">Artwork Status Guide:</h4>';
        echo '<ul style="margin: 0; padding-left: 20px; font-size: 12px;">';
        echo '<li><strong>Artwork Pending:</strong> Waiting for customer artwork or initial review</li>';
        echo '<li><strong>Pending Review:</strong> Under review by design team</li>';
        echo '<li><strong>Artwork Approved:</strong> Design approved and ready for production</li>';
        echo '<li><strong>Internal Review:</strong> Internal team review (hidden from customer)</li>';
        echo '<li><strong>In Production:</strong> Currently being manufactured</li>';
        echo '<li><strong>Shipped:</strong> Order has been shipped to customer</li>';
        echo '</ul>';
        echo '</div>';
    }
    
    /**
     * Render the design details meta box
     */
    public function render_details_meta_box($post) {
        wp_nonce_field('grip_design_details_nonce', 'grip_design_details_nonce');
        
        $meta_fields = array(
            '_grip_customer_name' => 'Customer Name',
            '_grip_customer_email' => 'Customer Email',
            '_grip_team_name' => 'Team/School',
            '_grip_design_type' => 'Design Type',
            '_grip_design_layout' => 'Pattern/Layout',
            '_grip_primary_color' => 'Primary Color',
            '_grip_secondary_color' => 'Secondary Color',
            '_grip_tertiary_color' => 'Tertiary Color',
            '_grip_quantity' => 'Quantity',
            '_grip_form_entry_id' => 'Form Entry ID',
            '_grip_artwork_filename' => 'Artwork Filename'
        );
        
        echo '<table class="form-table" style="margin-top: 10px;">';
        
        foreach ($meta_fields as $meta_key => $label) {
            $value = get_post_meta($post->ID, $meta_key, true);
            echo '<tr>';
            echo '<th scope="row" style="width: 150px;"><label for="' . esc_attr($meta_key) . '">' . esc_html($label) . ':</label></th>';
            echo '<td>';
            
            if ($meta_key === '_grip_design_type' || $meta_key === '_grip_design_layout') {
                // Dropdown for certain fields
                $options = ($meta_key === '_grip_design_type') 
                    ? array('Solid Color' => 'Solid Color', 'Pattern' => 'Pattern')
                    : array('Solid Color' => 'Solid Color', 'Two Tone' => 'Two Tone', 'Three Tone' => 'Three Tone');
                    
                echo '<select name="' . esc_attr($meta_key) . '" id="' . esc_attr($meta_key) . '" style="width: 100%;">';
                echo '<option value="">Select...</option>';
                foreach ($options as $opt_value => $opt_label) {
                    $selected = selected($value, $opt_value, false);
                    echo '<option value="' . esc_attr($opt_value) . '" ' . $selected . '>' . esc_html($opt_label) . '</option>';
                }
                echo '</select>';
            } elseif ($meta_key === '_grip_form_entry_id') {
                // Read-only for form entry ID
                echo '<input type="text" name="' . esc_attr($meta_key) . '" id="' . esc_attr($meta_key) . '" value="' . esc_attr($value) . '" style="width: 100%;" readonly>';
            } else {
                // Regular text input
                echo '<input type="text" name="' . esc_attr($meta_key) . '" id="' . esc_attr($meta_key) . '" value="' . esc_attr($value) . '" style="width: 100%;">';
            }
            
            echo '</td>';
            echo '</tr>';
        }
        
        // Feedback field
        $feedback = get_post_meta($post->ID, '_grip_feedback', true);
        echo '<tr>';
        echo '<th scope="row"><label for="_grip_feedback">Design Instructions:</label></th>';
        echo '<td><textarea name="_grip_feedback" id="_grip_feedback" rows="4" style="width: 100%;">' . esc_textarea($feedback) . '</textarea></td>';
        echo '</tr>';
        
        // Artwork URL
        $artwork_url = get_post_meta($post->ID, '_grip_artwork_url', true);
        echo '<tr>';
        echo '<th scope="row"><label for="_grip_artwork_url">Artwork URL:</label></th>';
        echo '<td>';
        echo '<input type="url" name="_grip_artwork_url" id="_grip_artwork_url" value="' . esc_attr($artwork_url) . '" style="width: 100%;">';
        if ($artwork_url) {
            echo '<br><a href="' . esc_url($artwork_url) . '" target="_blank" style="margin-top: 5px; display: inline-block;">View Artwork</a>';
        }
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
    }
    
    /**
     * Render the Monday.com integration meta box
     */
    public function render_monday_meta_box($post) {
        wp_nonce_field('grip_design_monday_nonce', 'grip_design_monday_nonce');
        
        // Get all Monday.com related data
        $monday_feedback = get_post_meta($post->ID, '_grip_monday_feedback', true);
        $monday_feedback_history = get_post_meta($post->ID, '_grip_monday_feedback_history', true);
        $monday_item_id = get_post_meta($post->ID, '_grip_monday_item_id', true);
        $mockup_asset_id = get_post_meta($post->ID, '_grip_mockup_asset_id', true);
        $mockup_asset_url = get_post_meta($post->ID, '_grip_mockup_asset_url', true);
        
        echo '<div class="grip-monday-integration">';
        
        // Monday.com Item Details
        echo '<div class="monday-item-details">';
        echo '<h3 style="margin-top: 0;">Monday.com Item Details</h3>';
        echo '<table class="form-table">';
        
        // Monday.com Item ID
        echo '<tr>';
        echo '<th scope="row"><label for="_grip_monday_item_id">Item ID:</label></th>';
        echo '<td>';
        echo '<input type="text" name="_grip_monday_item_id" id="_grip_monday_item_id" value="' . esc_attr($monday_item_id) . '" style="width: 100%;" placeholder="Monday.com item ID for this grip design">';
        if ($monday_item_id) {
            echo '<p class="description">View in Monday.com: <a href="https://twintack.monday.com/boards/items/' . esc_attr($monday_item_id) . '" target="_blank">Open Item →</a></p>';
        }
        echo '</td>';
        echo '</tr>';
        
        // Mockup Asset Details
        echo '<tr>';
        echo '<th scope="row">Mockup Asset:</th>';
        echo '<td>';
        echo '<div class="mockup-asset-details">';
        echo '<input type="text" name="_grip_mockup_asset_id" id="_grip_mockup_asset_id" value="' . esc_attr($mockup_asset_id) . '" style="width: 100%;" placeholder="Monday.com asset ID">';
        echo '<input type="url" name="_grip_mockup_asset_url" id="_grip_mockup_asset_url" value="' . esc_attr($mockup_asset_url) . '" style="width: 100%; margin-top: 5px;" placeholder="Direct URL to mockup asset">';
        if ($mockup_asset_url) {
            echo '<div class="mockup-preview" style="margin-top: 10px;">';
            echo '<a href="' . esc_url($mockup_asset_url) . '" target="_blank" class="button">View Mockup →</a>';
            echo '</div>';
        }
        echo '</div>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        echo '</div>';
        
        // Design Team Communication
        echo '<div class="monday-communication" style="margin-top: 20px;">';
        echo '<h3>Design Team Communication</h3>';
        
        // Current Message
        echo '<div class="current-message">';
        echo '<label for="_grip_monday_feedback"><strong>Current Message:</strong></label>';
        echo '<textarea name="_grip_monday_feedback" id="_grip_monday_feedback" rows="4" style="width: 100%; margin-top: 5px;" placeholder="Message from the design team via Monday.com">' . esc_textarea($monday_feedback) . '</textarea>';
        echo '</div>';
        
        // Message History
        if (!empty($monday_feedback_history) && is_array($monday_feedback_history)) {
            echo '<div class="message-history" style="margin-top: 15px;">';
            echo '<h4 style="margin-bottom: 10px;">Message History</h4>';
            echo '<div class="history-entries" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">';
            
            $feedback_entries = array_reverse($monday_feedback_history);
            foreach ($feedback_entries as $entry) {
                echo '<div class="history-entry" style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">';
                echo '<div class="entry-timestamp" style="font-size: 12px; color: #666;">';
                echo esc_html(date('F j, Y g:i a', strtotime($entry['timestamp'])));
                echo '</div>';
                echo '<div class="entry-message" style="margin-top: 5px;">';
                echo wpautop(esc_html($entry['message']));
                echo '</div>';
                echo '</div>';
            }
            
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Integration Note
        echo '<div class="integration-note" style="margin-top: 20px; padding: 10px; background: #f0f8ff; border-left: 4px solid #0073aa;">';
        echo '<p style="margin: 0;"><strong>Note:</strong> Monday.com integration fields are automatically updated via Make.com scenarios. Manual changes here will be preserved until the next automation update.</p>';
        echo '</div>';
        
        echo '</div>'; // .grip-monday-integration
    }
    
    /**
     * Render customer feedback meta box
     */
    public function render_customer_feedback_meta_box($post) {
        $customer_feedback = get_post_meta($post->ID, '_grip_customer_feedback', true);
        $latest_feedback = get_post_meta($post->ID, '_grip_latest_customer_feedback', true);
        $latest_action = get_post_meta($post->ID, '_grip_latest_customer_action', true);
        
        echo '<div style="margin-bottom: 20px;">';
        
        // Latest Customer Action
        if ($latest_action) {
            $action_label = ($latest_action === 'approve') ? 'Approved' : 'Requested Changes';
            $action_color = ($latest_action === 'approve') ? '#4CAF50' : '#FF9800';
            
            echo '<div style="padding: 10px; background: #f9f9f9; border-left: 4px solid ' . $action_color . '; margin-bottom: 15px;">';
            echo '<h4 style="margin: 0 0 5px 0; color: ' . $action_color . ';">Latest Customer Action: ' . $action_label . '</h4>';
            if ($latest_feedback) {
                echo '<p style="margin: 0; font-style: italic;">"' . esc_html($latest_feedback) . '"</p>';
            }
            echo '</div>';
        }
        
        // Feedback History
        echo '<h4>Customer Feedback History:</h4>';
        
        if ($customer_feedback && is_array($customer_feedback)) {
            echo '<div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fdfdfd;">';
            
            // Sort feedback by timestamp (newest first)
            $sorted_feedback = $customer_feedback;
            usort($sorted_feedback, function($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });
            
            foreach ($sorted_feedback as $feedback_item) {
                $action_label = ($feedback_item['action'] === 'approve') ? 'Approved' : 'Requested Changes';
                $action_color = ($feedback_item['action'] === 'approve') ? '#4CAF50' : '#FF9800';
                $timestamp = date('M j, Y g:i A', strtotime($feedback_item['timestamp']));
                
                echo '<div style="margin-bottom: 15px; padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px; background: #fff;">';
                echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">';
                echo '<strong style="color: ' . $action_color . ';">' . $action_label . '</strong>';
                echo '<small style="color: #666;">' . $timestamp . '</small>';
                echo '</div>';
                
                if (!empty($feedback_item['feedback'])) {
                    echo '<p style="margin: 5px 0 0 0; padding: 8px; background: #f8f8f8; border-radius: 3px; font-style: italic;">';
                    echo '"' . esc_html($feedback_item['feedback']) . '"';
                    echo '</p>';
                }
                echo '</div>';
            }
            
            echo '</div>';
        } else {
            echo '<p style="color: #666; font-style: italic;">No customer feedback yet.</p>';
        }
        
        echo '</div>';
        
        // Customer Information
        $customer_name = get_post_meta($post->ID, '_grip_customer_name', true);
        $customer_email = get_post_meta($post->ID, '_grip_customer_email', true);
        
        if ($customer_name || $customer_email) {
            echo '<div style="margin-top: 20px; padding: 10px; background: #f0f8ff; border-left: 4px solid #0073aa;">';
            echo '<h4 style="margin: 0 0 10px 0;">Customer Information:</h4>';
            if ($customer_name) {
                echo '<p style="margin: 0;"><strong>Name:</strong> ' . esc_html($customer_name) . '</p>';
            }
            if ($customer_email) {
                echo '<p style="margin: 5px 0 0 0;"><strong>Email:</strong> ' . esc_html($customer_email) . '</p>';
            }
            echo '</div>';
        }
    }
    
    /**
     * Save meta box data
     */
    public function save_meta_boxes($post_id) {
        if (!isset($_POST['post_type']) || $_POST['post_type'] !== 'grip_design') {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Verify nonces
        $nonces = array('grip_design_status_nonce', 'grip_design_details_nonce', 'grip_design_monday_nonce');
        $nonce_verified = false;
        
        foreach ($nonces as $nonce_name) {
            if (isset($_POST[$nonce_name]) && wp_verify_nonce($_POST[$nonce_name], $nonce_name)) {
                $nonce_verified = true;
                break;
            }
        }
        
        if (!$nonce_verified) {
            return;
        }
        
        // Handle artwork status change (separate from post status)
        if (isset($_POST['grip_artwork_status']) && isset($_POST['grip_design_status_nonce'])) {
            $new_artwork_status = sanitize_text_field($_POST['grip_artwork_status']);
            $allowed_artwork_statuses = array(
                'artwork_pending', 'pending_review', 'artwork_approved', 
                'customer_requested_changes', 'customer_approved', 'approved_for_production',
                'internal_review', 'in_production', 'shipped'
            );
            
            if (in_array($new_artwork_status, $allowed_artwork_statuses)) {
                // Update the artwork status meta field
                update_post_meta($post_id, '_grip_artwork_status', $new_artwork_status);
                
                // Set a transient to show success notice
                $status_label = $this->get_artwork_status_label($new_artwork_status);
                set_transient('grip_artwork_status_changed_' . $post_id, $status_label, 30);
            }
        }
        
        // Save all meta fields
        $meta_fields = array(
            '_grip_customer_name',
            '_grip_customer_email',
            '_grip_team_name',
            '_grip_design_type',
            '_grip_design_layout',
            '_grip_primary_color',
            '_grip_secondary_color',
            '_grip_tertiary_color',
            '_grip_quantity',
            '_grip_form_entry_id',
            '_grip_artwork_filename',
            '_grip_feedback',
            '_grip_artwork_url',
            '_grip_monday_feedback',
            '_grip_monday_item_id',
            '_grip_mockup_asset_id',
            '_grip_mockup_asset_url'
        );
        
        foreach ($meta_fields as $meta_key) {
            if (isset($_POST[$meta_key])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$meta_key]));
            }
        }
        
        // Handle textarea fields separately
        if (isset($_POST['_grip_feedback'])) {
            update_post_meta($post_id, '_grip_feedback', sanitize_textarea_field($_POST['_grip_feedback']));
        }
        
        if (isset($_POST['_grip_monday_feedback'])) {
            update_post_meta($post_id, '_grip_monday_feedback', sanitize_textarea_field($_POST['_grip_monday_feedback']));
        }
    }
    
    /**
     * Add artwork status to post states in admin list
     */
    public function add_artwork_status_to_post_states($post_states, $post) {
        if ($post->post_type !== 'grip_design') {
            return $post_states;
        }
        
        $artwork_status = get_post_meta($post->ID, '_grip_artwork_status', true) ?: 'artwork_pending';
        $artwork_status_label = $this->get_artwork_status_label($artwork_status);
        
        $post_states['artwork_status'] = $artwork_status_label;
        
        return $post_states;
    }
    
    /**
     * Display admin notices for status changes
     */
    public function display_status_change_notices() {
        $screen = get_current_screen();
        if ($screen->base !== 'post' || $screen->post_type !== 'grip_design') {
            return;
        }
        
        global $post;
        if (!$post) {
            return;
        }
        
        $artwork_status_changed = get_transient('grip_artwork_status_changed_' . $post->ID);
        if ($artwork_status_changed) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>Artwork Status Updated:</strong> Status changed to "' . esc_html($artwork_status_changed) . '"</p>';
            echo '</div>';
            
            // Delete the transient so it only shows once
            delete_transient('grip_artwork_status_changed_' . $post->ID);
        }
    }

    /**
     * Check permissions for customer feedback API
     */  
    public function check_customer_feedback_permission($request) {
        // For customer feedback, we need to verify the customer owns this grip design
        $grip_id = $request->get_param('id'); 
        
        if (!is_user_logged_in()) {
            return new WP_Error('rest_forbidden', __('You must be logged in to provide feedback'), array('status' => 401));
        }
        
        $post = get_post($grip_id);
        if (!$post || $post->post_type !== 'grip_design') {
            return new WP_Error('grip_not_found', __('Grip design not found'), array('status' => 404));
        }
        
        // Check if current user is the customer (by email)
        $current_user = wp_get_current_user();
        $customer_email = get_post_meta($grip_id, '_grip_customer_email', true);
        
        if ($current_user->user_email !== $customer_email) {
            return new WP_Error('rest_forbidden', __('You can only provide feedback on your own designs'), array('status' => 403));
        }
        
        return true;
    }

    /**
     * Handle customer feedback submission
     */
    public function handle_customer_feedback($request) {
        $grip_id = $request->get_param('id');
        $action = $request->get_param('action');
        $feedback = $request->get_param('feedback');
        
        // Verify the grip design exists
        $post = get_post($grip_id);
        if (!$post || $post->post_type !== 'grip_design') {
            return new WP_Error('not_found', 'Grip design not found', array('status' => 404));
        }
        
        // Validate action
        if (!in_array($action, array('approve', 'request_changes'))) {
            return new WP_Error('invalid_action', 'Invalid action. Must be "approve" or "request_changes"', array('status' => 400));
        }
        
        // Get existing feedback history or initialize new array
        $feedback_history = get_post_meta($grip_id, '_grip_customer_feedback', true);
        if (!is_array($feedback_history)) {
            $feedback_history = array();
        }
        
        // Add new feedback entry with timestamp
        $feedback_entry = array(
            'action' => $action,
            'feedback' => $feedback,
            'timestamp' => current_time('c')
        );
        $feedback_history[] = $feedback_entry;
        
        // Update feedback history
        update_post_meta($grip_id, '_grip_customer_feedback', $feedback_history);
        update_post_meta($grip_id, '_grip_latest_customer_feedback', $feedback);
        update_post_meta($grip_id, '_grip_latest_customer_action', $action);
        
        // Update artwork status based on action
        $new_status = $action === 'approve' ? 'customer_approved' : 'customer_requested_changes';
        update_post_meta($grip_id, '_grip_artwork_status', $new_status);
        
        // Prepare response data
        $response_data = array(
            'success' => true,
            'action' => $action,
            'new_status' => $new_status,
            'message' => $action === 'approve' 
                ? 'Design approved! You can now purchase your custom grips.'
                : 'Your feedback has been sent to our design team.',
            'redirect_url' => get_permalink($grip_id)
        );
        
        return rest_ensure_response($response_data);
    }

    public function handle_purchase_completion($request) {
        $grip_id = $request->get_param('id');
        $order_id = $request->get_param('order_id');
        
        // Verify the grip design exists
        $post = get_post($grip_id);
        if (!$post || $post->post_type !== 'grip_design') {
            return new WP_Error('not_found', 'Grip design not found', array('status' => 404));
        }
        
        // Verify order exists
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('invalid_order', 'Invalid order ID', array('status' => 400));
        }
        
        // Update grip design status and order info
        update_post_meta($grip_id, '_grip_artwork_status', 'approved_for_production');
        update_post_meta($grip_id, '_grip_final_order_id', $order_id);
        update_post_meta($grip_id, '_grip_production_started', current_time('c'));
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Grip design marked for production',
            'grip_id' => $grip_id,
            'order_id' => $order_id
        ));
    }

    /**
     * Handle WooCommerce order status changes
     */
    public function handle_order_status_changed($order_id, $old_status, $new_status) {
        if (WP_DEBUG) {
            error_log('TwinTack: Order status changed - Order ID: ' . $order_id);
            error_log('TwinTack: Status change from ' . $old_status . ' to ' . $new_status);
        }

        if ($new_status !== 'completed') {
            return;
        }

        // Get the order
        $order = wc_get_order($order_id);
        if (!$order) {
            if (WP_DEBUG) {
                error_log('TwinTack: Could not find order with ID: ' . $order_id);
            }
            return;
        }

        // Loop through order items
        foreach ($order->get_items() as $item) {
            if (WP_DEBUG) {
                error_log('TwinTack: Processing order item - Product ID: ' . $item->get_product_id());
                error_log('TwinTack: Item meta data: ' . print_r($item->get_meta_data(), true));
            }

            // Check if this is a custom grip product
            if ($item->get_product_id() == 1196) {
                // Get the grip design ID from the item meta
                $grip_id = $item->get_meta('grip_design_id');
                
                if (WP_DEBUG) {
                    error_log('TwinTack: Found custom grip product - Grip Design ID: ' . ($grip_id ? $grip_id : 'not found'));
                }

                if (!$grip_id) {
                    continue;
                }

                // Update the grip design status
                $current_status = get_post_meta($grip_id, '_grip_artwork_status', true);
                if (WP_DEBUG) {
                    error_log('TwinTack: Current grip status: ' . $current_status);
                }

                update_post_meta($grip_id, '_grip_artwork_status', 'approved_for_production');
                update_post_meta($grip_id, '_grip_order_id', $order_id);
                update_post_meta($grip_id, '_grip_production_started', current_time('mysql'));

                if (WP_DEBUG) {
                    error_log('TwinTack: Updated grip design ' . $grip_id . ' to approved_for_production');
                }

                        // Trigger webhook for Make.com
        $this->trigger_production_approval_webhook($grip_id, $order_id);
        
        // Check if this is a legacy grip reorder and trigger special webhook
        $this->trigger_legacy_reorder_webhook($grip_id, $order_id);
            }
        }
    }

    /**
     * Trigger webhook for Make.com when grip is approved for production
     */
    private function trigger_production_approval_webhook($grip_id, $order_id) {
        if (WP_DEBUG) {
            error_log('TwinTack: Triggering production approval webhook - Grip ID: ' . $grip_id . ', Order ID: ' . $order_id);
        }

        // Get grip design details
        $post = get_post($grip_id);
        $monday_item_id = get_post_meta($grip_id, '_grip_monday_item_id', true);
        
        // Prepare webhook data
        $webhook_data = array(
            'grip_design_id' => $grip_id,
            'grip_design_title' => $post->post_title,
            'order_id' => $order_id,
            'artwork_status' => 'approved_for_production',
            'monday_item_id' => $monday_item_id,
            'timestamp' => current_time('c'),
            'webhook_type' => 'production_approval'
        );
        
        if (WP_DEBUG) {
            error_log('TwinTack: Webhook data: ' . print_r($webhook_data, true));
        }

        // Allow filtering of webhook data
        $webhook_data = apply_filters('grip_production_approval_webhook_data', $webhook_data, $grip_id);
        
        // Allow custom webhook URLs
        $webhook_url = apply_filters('grip_production_approval_webhook_url', '');
        
        if (!empty($webhook_url)) {
            if (WP_DEBUG) {
                error_log('TwinTack: Sending webhook to: ' . $webhook_url);
            }

            // Send webhook
            $response = wp_remote_post($webhook_url, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'TwinTack-Grip-Manager/1.6.03'
                ),
                'body' => wp_json_encode($webhook_data),
                'timeout' => 15,
                'blocking' => true // Changed to true for debugging
            ));

            if (WP_DEBUG) {
                if (is_wp_error($response)) {
                    error_log('TwinTack: Webhook error: ' . $response->get_error_message());
                } else {
                    error_log('TwinTack: Webhook response: ' . print_r($response, true));
                }
            }
        } else {
            if (WP_DEBUG) {
                error_log('TwinTack: No webhook URL configured');
            }
        }
        
        // WordPress action for custom integrations
        do_action('grip_production_approval', $grip_id, $order_id, $webhook_data);
    }

    /**
     * Get the webhook URL for customer feedback
     */
    public function get_customer_feedback_webhook_url() {
        // Try to get from constant first
        if (defined('TWINTACK_CUSTOMER_FEEDBACK_WEBHOOK_URL')) {
            return TWINTACK_CUSTOMER_FEEDBACK_WEBHOOK_URL;
        }
        
        // Fallback to option
        return get_option('twintack_customer_feedback_webhook_url', '');
    }

    /**
     * Get the webhook URL for production approval
     */
    public function get_production_approval_webhook_url() {
        // Try to get from constant first
        if (defined('TWINTACK_PRODUCTION_APPROVAL_WEBHOOK_URL')) {
            return TWINTACK_PRODUCTION_APPROVAL_WEBHOOK_URL;
        }
        
        // Fallback to option
        return get_option('twintack_production_approval_webhook_url', '');
    }

    /**
     * Get the webhook URL for legacy grip reorders
     */
    public function get_legacy_reorder_webhook_url() {
        // Try to get from constant first
        if (defined('TWINTACK_LEGACY_REORDER_WEBHOOK_URL')) {
            return TWINTACK_LEGACY_REORDER_WEBHOOK_URL;
        }
        
        // Fallback to option
        return get_option('twintack_legacy_reorder_webhook_url', '');
    }

    /**
     * Trigger webhook for Make.com when legacy grip is reordered
     */
    private function trigger_legacy_reorder_webhook($grip_id, $order_id) {
        // Check if this is a legacy grip
        $legacy_id = get_post_meta($grip_id, '_grip_legacy_id', true);
        if (empty($legacy_id)) {
            return; // Not a legacy grip, skip
        }

        if (WP_DEBUG) {
            error_log('TwinTack: Triggering legacy reorder webhook - Grip ID: ' . $grip_id . ', Legacy ID: ' . $legacy_id . ', Order ID: ' . $order_id);
        }

        // Get the webhook URL
        $webhook_url = $this->get_legacy_reorder_webhook_url();
        if (empty($webhook_url)) {
            if (WP_DEBUG) {
                error_log('TwinTack: No legacy reorder webhook URL configured');
            }
            return;
        }

        // Get grip design details
        $post = get_post($grip_id);
        $monday_item_id = get_post_meta($grip_id, '_grip_monday_item_id', true);
        
        // Prepare comprehensive webhook data
        $webhook_data = array(
            'grip_design_id' => $grip_id,
            'grip_design_title' => $post->post_title,
            'legacy_id' => $legacy_id,
            'is_legacy_reorder' => true,
            'customer_name' => get_post_meta($grip_id, '_grip_customer_name', true),
            'customer_email' => get_post_meta($grip_id, '_grip_customer_email', true),
            'team_name' => get_post_meta($grip_id, '_grip_team_name', true),
            'design_type' => get_post_meta($grip_id, '_grip_design_type', true),
            'design_layout' => get_post_meta($grip_id, '_grip_design_layout', true),
            'primary_color' => get_post_meta($grip_id, '_grip_primary_color', true),
            'secondary_color' => get_post_meta($grip_id, '_grip_secondary_color', true),
            'tertiary_color' => get_post_meta($grip_id, '_grip_tertiary_color', true),
            'quantity' => get_post_meta($grip_id, '_grip_quantity', true),
            'original_quantity' => get_post_meta($grip_id, '_grip_quantity', true), // For legacy, these are the same
            'order_id' => $order_id,
            'monday_item_id' => $monday_item_id,
            'artwork_url' => get_post_meta($grip_id, '_grip_artwork_url', true),
            'artwork_filename' => get_post_meta($grip_id, '_grip_artwork_filename', true),
            'mockup_asset_url' => get_post_meta($grip_id, '_grip_mockup_asset_url', true),
            'mockup_asset_id' => get_post_meta($grip_id, '_grip_mockup_asset_id', true),
            'artwork_status' => 'approved_for_production',
            'final_order_id' => $order_id,
            'production_started' => current_time('c'),
            'timestamp' => current_time('c'),
            'webhook_type' => 'legacy_grip_reorder',
            'site_url' => get_site_url()
        );
        
        if (WP_DEBUG) {
            error_log('TwinTack: Legacy reorder webhook data: ' . print_r($webhook_data, true));
        }

        // Allow filtering of webhook data
        $webhook_data = apply_filters('grip_legacy_reorder_webhook_data', $webhook_data, $grip_id, $order_id);
        
        if (WP_DEBUG) {
            error_log('TwinTack: Sending legacy reorder webhook to: ' . $webhook_url);
        }

        // Send webhook
        $response = wp_remote_post($webhook_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'TwinTack-Grip-Manager/1.6.08'
            ),
            'body' => wp_json_encode($webhook_data),
            'timeout' => 15,
            'blocking' => false // Don't wait for response
        ));

        if (WP_DEBUG) {
            if (is_wp_error($response)) {
                error_log('TwinTack: Legacy reorder webhook error: ' . $response->get_error_message());
            } else {
                error_log('TwinTack: Legacy reorder webhook sent successfully');
            }
        }
        
        // WordPress action for custom integrations
        do_action('grip_legacy_reorder', $grip_id, $order_id, $webhook_data);
    }
}