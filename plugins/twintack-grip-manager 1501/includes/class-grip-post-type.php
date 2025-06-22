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
            'rest_base' => 'grip-designs',
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
                    'enum' => array('artwork_pending', 'pending_review', 'artwork_approved', 'internal_review', 'in_production', 'shipped'),
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
        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error registering artwork status fields: ' . $e->getMessage());
            }
        }
    }

    public function register_monday_api_endpoint() {
        register_rest_route('twintack/v1', '/grip-design/(?P<id>\d+)/monday', array(
            'methods' => 'POST',
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
                    'enum' => array('artwork_pending', 'pending_review', 'artwork_approved', 'internal_review', 'in_production', 'shipped'),
                ),
            ),
        ));
    }
    
    public function check_monday_api_permission($request) {
        // Check for API key in header or parameter
        $api_key = $request->get_header('X-API-Key') ?: $request->get_param('api_key');
        
        // You can set this in wp-config.php: define('TWINTACK_MONDAY_API_KEY', 'your-secret-key');
        $valid_key = defined('TWINTACK_MONDAY_API_KEY') ? TWINTACK_MONDAY_API_KEY : 'twintack-monday-2024';
        
        if ($api_key !== $valid_key) {
            return new WP_Error('rest_forbidden', 'Invalid API key', array('status' => 401));
        }
        
        return true;
    }
    
    public function update_monday_data($request) {
        $grip_id = $request->get_param('id');
        
        // Verify the grip design exists
        $post = get_post($grip_id);
        if (!$post || $post->post_type !== 'grip_design') {
            return new WP_Error('not_found', 'Grip design not found', array('status' => 404));
        }
        
        $updated_fields = array();
        
        // Update Monday.com feedback
        if ($request->has_param('monday_feedback')) {
            $feedback = $request->get_param('monday_feedback');
            update_post_meta($grip_id, '_grip_monday_feedback', $feedback);
            $updated_fields['monday_feedback'] = $feedback;
        }
        
        // Update mockup asset ID
        if ($request->has_param('mockup_asset_id')) {
            $asset_id = $request->get_param('mockup_asset_id');
            update_post_meta($grip_id, '_grip_mockup_asset_id', $asset_id);
            $updated_fields['mockup_asset_id'] = $asset_id;
        }
        
        // Update mockup asset URL
        if ($request->has_param('mockup_asset_url')) {
            $asset_url = $request->get_param('mockup_asset_url');
            update_post_meta($grip_id, '_grip_mockup_asset_url', $asset_url);
            $updated_fields['mockup_asset_url'] = $asset_url;
            
            // If a WordPress media ID is provided, set it as featured image
            if ($request->has_param('wordpress_media_id')) {
                $media_id = intval($request->get_param('wordpress_media_id'));
                if ($media_id > 0) {
                    set_post_thumbnail($grip_id, $media_id);
                    $updated_fields['featured_image_set'] = $media_id;
                }
            }
        }
        
        // Update artwork status
        if ($request->has_param('artwork_status')) {
            $artwork_status = $request->get_param('artwork_status');
            update_post_meta($grip_id, '_grip_artwork_status', $artwork_status);
            $updated_fields['artwork_status'] = $artwork_status;
            $updated_fields['artwork_status_label'] = $this->get_artwork_status_label($artwork_status);
        }
        
        // Log the update
        if (WP_DEBUG) {
            error_log('TwinTack Monday API: Updated grip design ' . $grip_id . ' with data: ' . json_encode($updated_fields));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'grip_id' => $grip_id,
            'updated_fields' => $updated_fields,
            'message' => 'Grip design updated successfully'
        ));
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
            'artwork_pending'   => 'Artwork Pending',
            'pending_review'    => 'Pending Review', 
            'artwork_approved'  => 'Artwork Approved',
            'internal_review'   => 'Internal Review',
            'in_production'     => 'In Production',
            'shipped'           => 'Shipped'
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
            'artwork_pending'   => 'Artwork Pending',
            'pending_review'    => 'Pending Review',
            'artwork_approved'  => 'Artwork Approved',
            'internal_review'   => 'Internal Review',
            'in_production'     => 'In Production',
            'shipped'           => 'Shipped'
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
        
        $monday_feedback = get_post_meta($post->ID, '_grip_monday_feedback', true);
        $mockup_url = get_post_meta($post->ID, '_grip_mockup_url', true);
        $mockup_filename = get_post_meta($post->ID, '_grip_mockup_filename', true);
        $mockup_asset_id = get_post_meta($post->ID, '_grip_mockup_asset_id', true);
        $mockup_asset_url = get_post_meta($post->ID, '_grip_mockup_asset_url', true);
        
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th scope="row"><label for="_grip_monday_feedback">Design Team Message:</label></th>';
        echo '<td><textarea name="_grip_monday_feedback" id="_grip_monday_feedback" rows="4" style="width: 100%;" placeholder="Message from the design team via Monday.com">' . esc_textarea($monday_feedback) . '</textarea></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="_grip_mockup_asset_id">Mockup Asset ID:</label></th>';
        echo '<td>';
        echo '<input type="text" name="_grip_mockup_asset_id" id="_grip_mockup_asset_id" value="' . esc_attr($mockup_asset_id) . '" style="width: 100%;" placeholder="Monday.com asset ID from Make.com">';
        echo '<p style="font-size: 12px; color: #666; margin: 5px 0 0 0;">Asset ID retrieved from Monday.com via Make.com automation</p>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="_grip_mockup_asset_url">Mockup Asset URL:</label></th>';
        echo '<td>';
        echo '<input type="url" name="_grip_mockup_asset_url" id="_grip_mockup_asset_url" value="' . esc_attr($mockup_asset_url) . '" style="width: 100%;" placeholder="Direct URL to mockup asset">';
        if ($mockup_asset_url) {
            echo '<br><a href="' . esc_url($mockup_asset_url) . '" target="_blank" style="margin-top: 5px; display: inline-block;">View Asset</a>';
        }
        echo '<p style="font-size: 12px; color: #666; margin: 5px 0 0 0;">Direct asset URL for display purposes</p>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="_grip_mockup_url">Legacy Mockup URL:</label></th>';
        echo '<td>';
        echo '<input type="url" name="_grip_mockup_url" id="_grip_mockup_url" value="' . esc_attr($mockup_url) . '" style="width: 100%;" placeholder="Legacy URL to design mockup">';
        if ($mockup_url) {
            echo '<br><a href="' . esc_url($mockup_url) . '" target="_blank" style="margin-top: 5px; display: inline-block;">View Legacy Mockup</a>';
        }
        echo '<p style="font-size: 12px; color: #666; margin: 5px 0 0 0;">Legacy field - kept for backward compatibility</p>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="_grip_mockup_filename">Mockup Filename:</label></th>';
        echo '<td><input type="text" name="_grip_mockup_filename" id="_grip_mockup_filename" value="' . esc_attr($mockup_filename) . '" style="width: 100%;" placeholder="Design mockup filename"></td>';
        echo '</tr>';
        
        echo '</table>';
        
        echo '<div style="margin-top: 15px; padding: 10px; background: #f0f8ff; border-left: 4px solid #0073aa;">';
        echo '<p><strong>Integration Note:</strong> Monday.com fields are automatically updated via Make.com scenarios. The Asset ID and Asset URL fields are populated by your Make.com automation when mockups are available.</p>';
        echo '</div>';
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
            '_grip_mockup_url',
            '_grip_mockup_filename',
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
}