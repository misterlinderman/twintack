<?php
class TwinTack_Grip_Account {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add grip designs endpoint to My Account
        add_action('init', array($this, 'register_endpoints'));
        add_filter('woocommerce_account_menu_items', array($this, 'add_grip_designs_endpoint'));
        add_action('woocommerce_account_grip-designs_endpoint', array($this, 'grip_designs_content'));
        
        // Handle grip design add to cart
        add_filter('woocommerce_add_cart_item_data', array($this, 'handle_grip_add_to_cart'), 10, 3);
        
        // Update cart item quantity after add to cart
        add_action('woocommerce_add_to_cart', array($this, 'update_cart_item_quantity'), 10, 6);
        
        // Display grip design data in cart
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_data'), 10, 2);
        
        // Save cart item data to order
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_cart_item_data_to_order'), 10, 4);
        
        // Handle order status changes
        add_action('woocommerce_order_status_completed', array($this, 'handle_grip_order_complete'));
        add_action('woocommerce_order_status_processing', array($this, 'handle_grip_order_complete'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_grip_scripts'));
        
        // Handle AJAX customer feedback
        add_action('wp_ajax_grip_customer_feedback', array($this, 'handle_ajax_customer_feedback'));
    }
    
    public function register_endpoints() {
        add_rewrite_endpoint('grip-designs', EP_ROOT | EP_PAGES);
    }
    
    public function add_grip_designs_endpoint($items) {
        $items['grip-designs'] = 'My Grip Designs';
        return $items;
    }
    
    public function grip_designs_content() {
        // Prevent any other handlers from running after this
        if (!defined('TWINTACK_GRIP_CONTENT_LOADED')) {
            define('TWINTACK_GRIP_CONTENT_LOADED', true);
        } else {
            return; // Already loaded
        }
        
        $customer_email = wp_get_current_user()->user_email;
        
        // Check if we're viewing a specific grip design
        $grip_id = isset($_GET['grip_id']) ? intval($_GET['grip_id']) : 0;
        
        if ($grip_id > 0) {
            // Show single grip design details
            $this->display_single_grip_design($grip_id);
            return;
        }
        
        // Debug information (only shown with debug parameter)
        if (isset($_GET['tt_debug']) && current_user_can('manage_options')) {
            echo '<div class="grip-debug-info" style="background: #f5f5f5; padding: 15px; margin-bottom: 20px; border-left: 4px solid #0073aa;">';
            echo '<h3>Debug Information</h3>';
            echo '<p><strong>Current User Email:</strong> ' . esc_html($customer_email) . '</p>';
            
            // Show how many grip designs are associated with this email
            global $wpdb;
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                WHERE meta_key = '_grip_customer_email' 
                AND meta_value = %s",
                $customer_email
            ));
            
            echo '<p><strong>Number of Associated Grip Designs:</strong> ' . intval($count) . '</p>';
            echo '</div>';
        }
        
        // Display heading and description
        echo '<h2>My Grip Designs</h2>';
        echo '<p>View and manage all your custom grip designs.</p>';
        
        $args = array(
            'post_type' => 'grip_design',
            'posts_per_page' => -1, // Show all designs
            'meta_query' => array(
                array(
                    'key' => '_grip_customer_email',
                    'value' => $customer_email
                )
            )
        );
        
        $designs = new WP_Query($args);
        
        if ($designs->have_posts()) {
            echo '<div class="grip-designs-list">';
            while ($designs->have_posts()) {
                $designs->the_post();
                $artwork_status = get_post_meta(get_the_ID(), '_grip_artwork_status', true) ?: 'artwork_pending';
                $artwork_url = get_post_meta(get_the_ID(), '_grip_artwork_url', true);
                
                // Check for Monday.com mockup (priority: featured image > mockup asset URL > artwork)
                $featured_image_url = get_the_post_thumbnail_url(get_the_ID(), 'large');
                $mockup_asset_url = get_post_meta(get_the_ID(), '_grip_mockup_asset_url', true);
                
                if ($featured_image_url) {
                    $display_url = $featured_image_url;
                } elseif (!empty($mockup_asset_url) && filter_var($mockup_asset_url, FILTER_VALIDATE_URL)) {
                    $display_url = $mockup_asset_url;
                } else {
                    $display_url = $artwork_url;
                }
                
                $has_image = !empty($display_url) && filter_var($display_url, FILTER_VALIDATE_URL);
                $card_class = $has_image ? 'has-artwork' : 'no-artwork';
                $background_style = $has_image ? 'style="background-image: url(' . esc_url($display_url) . ')"' : '';
                
                // Get artwork status label
                $status_labels = array(
                    'artwork_pending'           => 'Artwork Pending',
                    'pending_review'            => 'Pending Review',
                    'artwork_approved'          => 'Artwork Approved',
                    'customer_requested_changes' => 'Requested Changes',
                    'customer_approved'         => 'Customer Approved',
                    'approved_for_production'   => 'Approved for Production',
                    'internal_review'           => 'Internal Review',
                    'in_production'             => 'In Production',
                    'shipped'                   => 'Shipped'
                );
                $status_label = isset($status_labels[$artwork_status]) ? $status_labels[$artwork_status] : 'Artwork Pending';
                ?>
                <div class="grip-design-item <?php echo esc_attr($card_class); ?>">
                    <div class="grip-design-preview" <?php echo $background_style; ?>>
                        <div class="grip-design-overlay">
                            <h3><?php the_title(); ?></h3>
                            <p class="grip-design-status">Status: <?php echo esc_html($status_label); ?></p>
                            <?php if (!$has_image): ?>
                                <div class="no-artwork-placeholder">Awaiting Mockup</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="grip-design-info">
                        <p>Design Type: <?php echo get_post_meta(get_the_ID(), '_grip_design_type', true); ?></p>
                        
                        <?php
                        // Display color information if available from new form
                        $design_layout = get_post_meta(get_the_ID(), '_grip_design_layout', true);
                        if (!empty($design_layout)) {
                            echo '<p>Pattern: ' . esc_html($design_layout) . '</p>';
                            
                            echo '<div class="design-pattern-colors">';
                            
                            $primary_color = get_post_meta(get_the_ID(), '_grip_primary_color', true);
                            if (!empty($primary_color)) {
                                echo '<div class="design-color">';
                                echo '<span class="color-swatch" style="background-color: ' . $this->get_color_hex($primary_color) . ';"></span>';
                                echo '<span class="color-name">Primary: ' . esc_html($primary_color) . '</span>';
                                echo '</div>';
                            }
                            
                            $secondary_color = get_post_meta(get_the_ID(), '_grip_secondary_color', true);
                            if (!empty($secondary_color)) {
                                echo '<div class="design-color">';
                                echo '<span class="color-swatch" style="background-color: ' . $this->get_color_hex($secondary_color) . ';"></span>';
                                echo '<span class="color-name">Secondary: ' . esc_html($secondary_color) . '</span>';
                                echo '</div>';
                            }
                            
                            $tertiary_color = get_post_meta(get_the_ID(), '_grip_tertiary_color', true);
                            if (!empty($tertiary_color)) {
                                echo '<div class="design-color">';
                                echo '<span class="color-swatch" style="background-color: ' . $this->get_color_hex($tertiary_color) . ';"></span>';
                                echo '<span class="color-name">Tertiary: ' . esc_html($tertiary_color) . '</span>';
                                echo '</div>';
                            }
                            
                            echo '</div>';
                        }
                        ?>
                        <p>Quantity: <?php echo get_post_meta(get_the_ID(), '_grip_quantity', true); ?></p>
                        <a href="<?php echo esc_url(add_query_arg('grip_id', get_the_ID(), wc_get_account_endpoint_url('grip-designs'))); ?>" class="button view-grip-design">View Details</a>
                    </div>
                </div>
                <?php
            }
            echo '</div>';
        } else {
            echo '<p>No grip designs found.</p>';
        }
        wp_reset_postdata();
    }
    
    /**
     * Display a single grip design in the account area
     */
    private function display_single_grip_design($grip_id) {
        // Verify this grip design belongs to the current user
        $post = get_post($grip_id);
        if (!$post || $post->post_type !== 'grip_design') {
            echo '<p>Grip design not found.</p>';
            echo '<p><a href="' . esc_url(wc_get_account_endpoint_url('grip-designs')) . '">&laquo; Back to My Grip Designs</a></p>';
            return;
        }
        
        $customer_email = wp_get_current_user()->user_email;
        $design_email = get_post_meta($grip_id, '_grip_customer_email', true);
        
        if ($design_email !== $customer_email) {
            echo '<p>You do not have permission to view this design.</p>';
            echo '<p><a href="' . esc_url(wc_get_account_endpoint_url('grip-designs')) . '">&laquo; Back to My Grip Designs</a></p>';
            return;
        }
        
        // Display back link
        echo '<p><a href="' . esc_url(wc_get_account_endpoint_url('grip-designs')) . '" class="button">&laquo; Back to My Grip Designs</a></p>';
        
        // Display grip design details
        ?>
        <div class="grip-design-container" data-grip-id="<?php echo esc_attr($grip_id); ?>">
            <div class="grip-design-header">
                <h1><?php echo esc_html($post->post_title); ?></h1>
                <?php 
                $artwork_status = get_post_meta($grip_id, '_grip_artwork_status', true) ?: 'artwork_pending';
                // Get artwork status label
                $status_labels = array(
                    'artwork_pending'           => 'Artwork Pending',
                    'pending_review'            => 'Pending Review',
                    'artwork_approved'          => 'Artwork Approved',
                    'customer_requested_changes' => 'Requested Changes',
                    'customer_approved'         => 'Customer Approved',
                    'approved_for_production'   => 'Approved for Production',
                    'internal_review'           => 'Internal Review',
                    'in_production'             => 'In Production',
                    'shipped'                   => 'Shipped'
                );
                $status_label = isset($status_labels[$artwork_status]) ? $status_labels[$artwork_status] : 'Artwork Pending';
                echo '<span class="status-label status-' . esc_attr($artwork_status) . '">';
                echo esc_html($status_label);
                echo '</span>';
                ?>
            </div>

            <div class="grip-design-content">
                <div class="grip-design-info">
                    <h2>Order Details</h2>
                    <table class="grip-details-table">
                        <tr>
                            <th>Team/School:</th>
                            <td><?php echo esc_html(get_post_meta($grip_id, '_grip_team_name', true)); ?></td>
                        </tr>
                        <tr>
                            <th>Design Type:</th>
                            <td><?php echo esc_html(get_post_meta($grip_id, '_grip_design_type', true)); ?></td>
                        </tr>
                        <?php
                        // Display detailed color information from the new form if available
                        $design_layout = get_post_meta($grip_id, '_grip_design_layout', true);
                        if (!empty($design_layout)): ?>
                            <tr>
                                <th>Pattern:</th>
                                <td><?php echo esc_html($design_layout); ?></td>
                            </tr>
                            <tr>
                                <th>Colors:</th>
                                <td>
                                    <div class="design-pattern-colors">
                                        <?php 
                                        $primary_color = get_post_meta($grip_id, '_grip_primary_color', true);
                                        if (!empty($primary_color)): ?>
                                            <div class="design-color">
                                                <span class="color-swatch" style="background-color: <?php echo $this->get_color_hex($primary_color); ?>;"></span>
                                                <span class="color-name">Primary: <?php echo esc_html($primary_color); ?></span>
                                            </div>
                                        <?php endif;
                                        
                                        $secondary_color = get_post_meta($grip_id, '_grip_secondary_color', true);
                                        if (!empty($secondary_color)): ?>
                                            <div class="design-color">
                                                <span class="color-swatch" style="background-color: <?php echo $this->get_color_hex($secondary_color); ?>;"></span>
                                                <span class="color-name">Secondary: <?php echo esc_html($secondary_color); ?></span>
                                            </div>
                                        <?php endif;
                                        
                                        $tertiary_color = get_post_meta($grip_id, '_grip_tertiary_color', true);
                                        if (!empty($tertiary_color)): ?>
                                            <div class="design-color">
                                                <span class="color-swatch" style="background-color: <?php echo $this->get_color_hex($tertiary_color); ?>;"></span>
                                                <span class="color-name">Tertiary: <?php echo esc_html($tertiary_color); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <th>Quantity:</th>
                            <td><?php echo esc_html(get_post_meta($grip_id, '_grip_quantity', true)); ?></td>
                        </tr>
                    </table>

                    <?php if ($feedback = get_post_meta($grip_id, '_grip_feedback', true)): ?>
                        <div class="grip-feedback">
                            <h3>Your Design Instructions</h3>
                            <div class="feedback-content">
                                <?php echo wpautop(esc_html($feedback)); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php 
                    $monday_feedback = get_post_meta($grip_id, '_grip_monday_feedback', true);
                    $monday_feedback_history = get_post_meta($grip_id, '_grip_monday_feedback_history', true);
                    
                    if (WP_DEBUG) {
                        error_log('TwinTack Display: Grip ID: ' . $grip_id);
                        error_log('TwinTack Display: Current feedback: ' . print_r($monday_feedback, true));
                        error_log('TwinTack Display: Feedback history: ' . print_r($monday_feedback_history, true));
                    }
                    
                    if (!empty($monday_feedback_history) && is_array($monday_feedback_history)): ?>
                        <div class="grip-monday-feedback">
                            <h3>Messages from Design Team</h3>
                            <div class="monday-feedback-content">
                                <?php 
                                // Display feedback history in reverse chronological order
                                $feedback_entries = array_reverse($monday_feedback_history);
                                
                                if (WP_DEBUG) {
                                    error_log('TwinTack Display: Processing ' . count($feedback_entries) . ' feedback entries');
                                }
                                
                                foreach ($feedback_entries as $index => $entry): 
                                    // Ensure we're accessing the message correctly whether it's a string or array
                                    $message = is_array($entry) ? $entry['message'] : $entry;
                                    $timestamp = is_array($entry) ? $entry['timestamp'] : current_time('c');
                                    
                                    if (WP_DEBUG) {
                                        error_log('TwinTack Display: Processing entry ' . $index);
                                        error_log('TwinTack Display: Entry data - ' . print_r($entry, true));
                                        error_log('TwinTack Display: Extracted message - ' . print_r($message, true));
                                        error_log('TwinTack Display: Extracted timestamp - ' . $timestamp);
                                    }
                                ?>
                                    <div class="feedback-entry">
                                        <div class="feedback-timestamp">
                                            <?php echo esc_html(date('F j, Y g:i a', strtotime($timestamp))); ?>
                                        </div>
                                        <div class="feedback-message">
                                            <?php echo wpautop(esc_html($message)); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php elseif (!empty($monday_feedback)): ?>
                        <div class="grip-monday-feedback">
                            <h3>Message from Design Team</h3>
                            <div class="monday-feedback-content">
                                <?php echo wpautop(esc_html($monday_feedback)); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php 
                    // Show customer feedback form if artwork is pending review
                    if ($artwork_status === 'pending_review'): ?>
                        <div class="customer-feedback-section">
                            <h3>Review Your Design</h3>
                            <p>Please review the design mockup above and let us know if you approve it or need changes.</p>
                            
                            <form id="customer-feedback-form" class="feedback-form">
                                <div class="feedback-textarea">
                                    <label for="customer-feedback">Additional Comments (Optional):</label>
                                    <textarea id="customer-feedback" name="feedback" rows="4" placeholder="Any specific changes or feedback for our design team..."></textarea>
                                </div>
                                
                                <div class="feedback-buttons">
                                    <button type="button" id="approve-design" class="button button-primary feedback-btn approve-btn">
                                        <span class="btn-text">Approve Design</span>
                                        <span class="btn-loading" style="display: none;">Processing...</span>
                                    </button>
                                    <button type="button" id="request-changes" class="button feedback-btn changes-btn">
                                        <span class="btn-text">Request Changes</span>
                                        <span class="btn-loading" style="display: none;">Processing...</span>
                                    </button>
                                </div>
                            </form>
                            
                            <div id="feedback-response" class="feedback-response" style="display: none;"></div>
                        </div>
                        
                        <script type="text/javascript">
                        jQuery(document).ready(function($) {
                            console.log('Inline grip feedback script loaded');
                            
                            // Debug info
                            console.log('Grip ID from data attribute:', $('.grip-design-container').data('grip-id'));
                            console.log('Feedback form exists:', $('#customer-feedback-form').length > 0);
                            console.log('Buttons found:', $('.feedback-btn').length);
                            
                            // Main feedback click handler
                            $('#approve-design, #request-changes').on('click', function(e) {
                                e.preventDefault();
                                console.log('Button clicked:', $(this).attr('id'));
                                
                                var button = $(this);
                                var action = button.attr('id') === 'approve-design' ? 'approve' : 'request_changes';
                                var feedback = $('#customer-feedback').val() || '';
                                var gripId = $('.grip-design-container').data('grip-id');
                                
                                console.log('Action:', action);
                                console.log('Feedback:', feedback);
                                console.log('Grip ID:', gripId);
                                
                                if (!gripId) {
                                    alert('Error: Grip design ID not found');
                                    return;
                                }
                                
                                // Show loading state
                                button.prop('disabled', true);
                                button.find('.btn-text').hide();
                                button.find('.btn-loading').show();
                                
                                // Make AJAX request
                                $.ajax({
                                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                                    type: 'POST',
                                    dataType: 'json',
                                    data: {
                                        action: 'grip_customer_feedback',
                                        grip_id: gripId,
                                        feedback_action: action,
                                        feedback: feedback,
                                        security: '<?php echo wp_create_nonce('grip_customer_feedback'); ?>'
                                    },
                                    success: function(response) {
                                        console.log('AJAX Success:', response);
                                        
                                        if (response.success) {
                                            $('#feedback-response').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>').show();
                                            
                                            // Reload page after delay
                                            setTimeout(function() {
                                                window.location.reload();
                                            }, 2000);
                                        } else {
                                            var errorMessage = response.data || 'Unknown error occurred';
                                            $('#feedback-response').html('<div class="notice notice-error"><p>Error: ' + errorMessage + '</p></div>').show();
                                            
                                            // Reset button
                                            button.prop('disabled', false);
                                            button.find('.btn-text').show();
                                            button.find('.btn-loading').hide();
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                        console.log('AJAX Error:', {xhr: xhr, status: status, error: error});
                                        
                                        $('#feedback-response').html('<div class="notice notice-error"><p>Connection error. Please try again.</p></div>').show();
                                        
                                        // Reset button
                                        button.prop('disabled', false);
                                        button.find('.btn-text').show();
                                        button.find('.btn-loading').hide();
                                    }
                                });
                            });
                        });
                        </script>
                    <?php endif; ?>

                    <?php 
                    // Show customer feedback history if any exists
                    $customer_feedback = get_post_meta($grip_id, '_grip_customer_feedback', true);
                    if (!empty($customer_feedback)): ?>
                        <div class="customer-feedback-history">
                            <h3>Your Feedback History</h3>
                            <div class="feedback-history-content">
                                <?php echo wpautop(esc_html($customer_feedback)); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php 
                    // Show purchase option if customer approved
                    if ($artwork_status === 'customer_approved'): ?>
                        <div class="purchase-section">
                            <h3>🎉 Design Approved!</h3>
                            <p>Great! Your design has been approved. You can now purchase your custom grips.</p>
                            
                            <div class="purchase-details">
                                <p><strong>Quantity:</strong> <?php echo esc_html(get_post_meta($grip_id, '_grip_quantity', true)); ?> grips</p>
                                <p><strong>Price:</strong> $19.99 per grip</p>
                                <?php 
                                $quantity = intval(get_post_meta($grip_id, '_grip_quantity', true));
                                $total = $quantity * 19.99;
                                ?>
                                <p><strong>Total:</strong> $<?php echo number_format($total, 2); ?></p>
                            </div>
                            
                            <a href="<?php echo esc_url($this->get_purchase_url($grip_id)); ?>" class="button button-primary purchase-btn">
                                Purchase Custom Grips
                            </a>
                        </div>
                    <?php elseif ($artwork_status === 'approved_for_production'): ?>
                        <div class="production-status">
                            <h3>🚀 In Production</h3>
                            <p>Your order has been sent to production! We'll update you when your grips are ready to ship.</p>
                        </div>
                    <?php elseif ($artwork_status === 'shipped'): ?>
                        <div class="shipped-status">
                            <h3>📦 Shipped</h3>
                            <p>Your custom grips have been shipped! You should receive them soon.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="grip-design-artwork">
                    <?php 
                    $featured_image_url = get_the_post_thumbnail_url($grip_id, 'large');
                    $mockup_asset_url = get_post_meta($grip_id, '_grip_mockup_asset_url', true);
                    $artwork_url = get_post_meta($grip_id, '_grip_artwork_url', true);
                    $filename = get_post_meta($grip_id, '_grip_artwork_filename', true);
                    
                    // Show mockup (priority: featured image > asset URL)
                    if ($featured_image_url): ?>
                        <h2>Design Mockup</h2>
                        <div class="artwork-preview">
                            <img src="<?php echo esc_url($featured_image_url); ?>" alt="Design Mockup">
                        </div>
                        <p class="artwork-actions">
                            <strong>Status:</strong> Mockup from design team<br>
                            <a href="<?php echo esc_url($featured_image_url); ?>" class="button" target="_blank">View Full Size</a>
                        </p>
                        
                        <?php if (!empty($artwork_url) && filter_var($artwork_url, FILTER_VALIDATE_URL)): ?>
                            <div style="margin-top: 30px;">
                                <h3>Your Original Artwork</h3>
                                <div class="artwork-preview secondary">
                                    <img src="<?php echo esc_url($artwork_url); ?>" alt="Original Submitted Artwork">
                                </div>
                                <p class="artwork-actions">
                                    <strong>File:</strong> <?php echo esc_html($filename); ?><br>
                                    <a href="<?php echo esc_url($artwork_url); ?>" class="button secondary" target="_blank">View Original</a>
                                </p>
                            </div>
                        <?php endif; ?>
                        
                    <?php elseif (!empty($mockup_asset_url) && filter_var($mockup_asset_url, FILTER_VALIDATE_URL)): ?>
                        <h2>Design Mockup</h2>
                        <div class="artwork-preview">
                            <img src="<?php echo esc_url($mockup_asset_url); ?>" alt="Design Mockup">
                        </div>
                        <p class="artwork-actions">
                            <strong>Status:</strong> Mockup from design team<br>
                            <a href="<?php echo esc_url($mockup_asset_url); ?>" class="button" target="_blank">View Full Size</a>
                        </p>
                        
                        <?php if (!empty($artwork_url) && filter_var($artwork_url, FILTER_VALIDATE_URL)): ?>
                            <div style="margin-top: 30px;">
                                <h3>Your Original Artwork</h3>
                                <div class="artwork-preview secondary">
                                    <img src="<?php echo esc_url($artwork_url); ?>" alt="Original Submitted Artwork">
                                </div>
                                <p class="artwork-actions">
                                    <strong>File:</strong> <?php echo esc_html($filename); ?><br>
                                    <a href="<?php echo esc_url($artwork_url); ?>" class="button secondary" target="_blank">View Original</a>
                                </p>
                            </div>
                        <?php endif; ?>
                        
                    <?php elseif (!empty($artwork_url) && filter_var($artwork_url, FILTER_VALIDATE_URL)): ?>
                        <h2>Submitted Artwork</h2>
                        <div class="artwork-preview">
                            <img src="<?php echo esc_url($artwork_url); ?>" alt="Submitted Artwork">
                        </div>
                        <p class="artwork-actions">
                            <strong>File:</strong> <?php echo esc_html($filename); ?><br>
                            <a href="<?php echo esc_url($artwork_url); ?>" class="button" target="_blank">View Full Size</a>
                        </p>
                        
                    <?php else: ?>
                        <h2>Artwork Status</h2>
                        <div class="artwork-preview no-artwork">
                            <div class="no-artwork-placeholder">Awaiting Design Mockup</div>
                        </div>
                        <?php if (!empty($filename)): ?>
                            <p><strong>Submitted Filename:</strong> <?php echo esc_html($filename); ?></p>
                        <?php else: ?>
                            <p>Design team is working on your mockup</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get approximate hex color codes for named colors
     */
    private function get_color_hex($color_name) {
        $color_map = array(
            'white' => '#ffffff',
            'grey' => '#888888',
            'black' => '#000000',
            'red' => '#ff0000',
            'yellow' => '#ffff00',
            'royal' => '#4169e1',
            'orange' => '#ffa500',
            'green' => '#008000',
            'purple' => '#800080',
            'neon green' => '#39ff14',
            'neon pink' => '#ff6ec7',
            'teal' => '#008080',
            'violet' => '#8a2be2',
            'safety yellow' => '#eed202'
        );
        
        // Normalize color name for lookup
        $color_key = strtolower($color_name);
        
        // Check for exact match
        if (isset($color_map[$color_key])) {
            return $color_map[$color_key];
        }
        
        // Check for partial matches
        foreach ($color_map as $key => $hex) {
            if (strpos($color_key, $key) !== false) {
                return $hex;
            }
        }
        
        // Default fallback color
        return '#cccccc';
    }
    
    /**
     * Generate purchase URL for approved grip design
     */
    private function get_purchase_url($grip_id) {
        // Get the Custom Grip Product (ID: 1196)
        $product_id = 1196;
        
        // Get the quantity from the grip design
        $grip_quantity = intval(get_post_meta($grip_id, '_grip_quantity', true));
        
        // Ensure we have a valid quantity
        if ($grip_quantity <= 0) {
            $grip_quantity = 25; // Default fallback to minimum quantity
        }
        
        // Create add to cart URL with grip design meta data AND quantity
        $add_to_cart_url = wc_get_cart_url() . '?add-to-cart=' . $product_id . '&quantity=' . $grip_quantity . '&grip_design_id=' . $grip_id;
        
        return $add_to_cart_url;
    }

    /**
     * Enqueue scripts and styles for grip designs
     */
    public function enqueue_grip_scripts() {
        // Only load on individual grip design pages (with grip_id parameter)
        if (is_account_page() && is_wc_endpoint_url('grip-designs') && isset($_GET['grip_id'])) {
            wp_enqueue_script('jquery');
            
            // Create a separate JS file for better management
            $js_url = plugins_url('assets/js/customer-feedback.js', dirname(__FILE__));
            wp_enqueue_script(
                'grip-customer-feedback',
                $js_url,
                array('jquery'),
                '1.5.1',
                true
            );
            
            // Debug: Log the JS URL
            if (WP_DEBUG) {
                error_log('Grip Feedback JS URL: ' . $js_url);
            }
            
            // Localize script with necessary data
            wp_localize_script('grip-customer-feedback', 'gripFeedback', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('grip_customer_feedback'),
                'messages' => array(
                    'noGripId' => 'Error: Grip design ID not found',
                    'connectionError' => 'Connection error. Please try again.',
                    'processing' => 'Processing...'
                )
            ));
            

        }
    }

    /**
     * Handle AJAX customer feedback
     */
    public function handle_ajax_customer_feedback() {
        // Check if required POST data exists
        if (!isset($_POST['security']) || !isset($_POST['grip_id']) || !isset($_POST['feedback_action'])) {
            wp_send_json_error('Missing required data');
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['security'], 'grip_customer_feedback')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $grip_id = intval($_POST['grip_id']);
        $action = sanitize_text_field($_POST['feedback_action']);
        $feedback = sanitize_textarea_field($_POST['feedback'] ?? '');
        
        // Verify user can provide feedback for this design
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to provide feedback');
            return;
        }
        
        $current_user = wp_get_current_user();
        $customer_email = get_post_meta($grip_id, '_grip_customer_email', true);
        
        if ($current_user->user_email !== $customer_email) {
            wp_send_json_error('You can only provide feedback on your own designs');
            return;
        }
        
        // Check current status
        $current_status = get_post_meta($grip_id, '_grip_artwork_status', true);
        if ($current_status !== 'pending_review') {
            wp_send_json_error('Design is not available for review. Current status: ' . $current_status);
            return;
        }
        
        // Update status based on action
        $new_status = ($action === 'approve') ? 'customer_approved' : 'customer_requested_changes';
        update_post_meta($grip_id, '_grip_artwork_status', $new_status);
        
        // Store customer feedback with timestamp
        $timestamp = current_time('mysql');
        $feedback_entry = "[$timestamp] Customer " . ucfirst(str_replace('_', ' ', $action)) . ": " . $feedback;
        
        // Get existing feedback history
        $existing_feedback = get_post_meta($grip_id, '_grip_customer_feedback', true);
        if (!empty($existing_feedback)) {
            $feedback_entry = $existing_feedback . "\n\n" . $feedback_entry;
        }
        
        update_post_meta($grip_id, '_grip_customer_feedback', $feedback_entry);
        
        // Store the latest feedback separately for easy access
        update_post_meta($grip_id, '_grip_latest_customer_feedback', $feedback);
        update_post_meta($grip_id, '_grip_latest_customer_action', $action);
        
        // Trigger webhook for Make.com integration if needed
        $this->trigger_customer_feedback_webhook($grip_id, $action, $feedback, $new_status);
        
        $message = ($action === 'approve') ? 
            'Design approved! You can now purchase your custom grips.' : 
            'Feedback submitted. Our design team will review your changes.';
        
        wp_send_json_success(array(
            'action' => $action,
            'new_status' => $new_status,
            'message' => $message
        ));
    }

    /**
     * Trigger webhook for Make.com when customer provides feedback
     */
    private function trigger_customer_feedback_webhook($grip_id, $action, $feedback, $new_status) {
        // Get grip design details for webhook
        $post = get_post($grip_id);
        $customer_name = get_post_meta($grip_id, '_grip_customer_name', true);
        $customer_email = get_post_meta($grip_id, '_grip_customer_email', true);
        $team_name = get_post_meta($grip_id, '_grip_team_name', true);
        $quantity = get_post_meta($grip_id, '_grip_quantity', true);
        
        // Prepare webhook data
        $webhook_data = array(
            'grip_design_id' => $grip_id,
            'grip_design_title' => $post->post_title,
            'customer_action' => $action,
            'customer_feedback' => $feedback,
            'artwork_status' => $new_status,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'team_name' => $team_name,
            'quantity' => $quantity,
            'timestamp' => current_time('c'), // ISO 8601 format
            'webhook_type' => 'customer_feedback'
        );
        
        // Allow filtering of webhook data
        $webhook_data = apply_filters('grip_customer_feedback_webhook_data', $webhook_data, $grip_id);
        
        // Allow custom webhook URLs
        $webhook_url = apply_filters('grip_customer_feedback_webhook_url', '');
        
        if (!empty($webhook_url)) {
            // Send webhook
            wp_remote_post($webhook_url, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'TwinTack-Grip-Manager/1.5.1'
                ),
                'body' => wp_json_encode($webhook_data),
                'timeout' => 15,
                'blocking' => false // Don't wait for response
            ));
        }
        
        // WordPress action for custom integrations
        do_action('grip_customer_feedback_submitted', $grip_id, $action, $feedback, $new_status, $webhook_data);
    }

    /**
     * Handle WooCommerce add to cart for grip designs
     */
    public function handle_grip_add_to_cart($cart_item_data, $product_id, $variation_id) {
        if (WP_DEBUG) {
            error_log('TwinTack: Adding to cart - Product ID: ' . $product_id);
            error_log('TwinTack: Grip Design ID from URL: ' . (isset($_GET['grip_design_id']) ? $_GET['grip_design_id'] : 'not set'));
        }
        
        // Check if this is the custom grip product with grip design meta
        if ($product_id == 1196 && isset($_GET['grip_design_id'])) {
            $grip_id = intval($_GET['grip_design_id']);
            
            // Verify the grip design is customer approved
            $artwork_status = get_post_meta($grip_id, '_grip_artwork_status', true);
            if ($artwork_status !== 'customer_approved') {
                wc_add_notice('This design must be approved before purchasing.', 'error');
                return $cart_item_data;
            }
            
            if (WP_DEBUG) {
                error_log('TwinTack: Adding grip design ' . $grip_id . ' to cart');
            }
            
            // Add grip design meta to cart item
            $cart_item_data['grip_design_id'] = $grip_id;
            $cart_item_data['grip_team_name'] = get_post_meta($grip_id, '_grip_team_name', true);
            $cart_item_data['grip_customer_name'] = get_post_meta($grip_id, '_grip_customer_name', true);
            $cart_item_data['grip_design_type'] = get_post_meta($grip_id, '_grip_design_type', true);
            
            // Store quantity in cart item data for later use
            $grip_quantity = intval(get_post_meta($grip_id, '_grip_quantity', true));
            if ($grip_quantity > 0) {
                $cart_item_data['grip_quantity'] = $grip_quantity;
            }
            
            // Ensure unique cart item
            $cart_item_data['unique_key'] = md5($grip_id . time());
            
            if (WP_DEBUG) {
                error_log('TwinTack: Cart item data: ' . print_r($cart_item_data, true));
                error_log('TwinTack: Stored quantity: ' . $grip_quantity);
            }
        }
        
        return $cart_item_data;
    }

    /**
     * Update cart item quantity after add to cart
     */
    public function update_cart_item_quantity($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        if (WP_DEBUG) {
            error_log('TwinTack: update_cart_item_quantity called');
            error_log('TwinTack: Product ID: ' . $product_id);
            error_log('TwinTack: Current quantity: ' . $quantity);
            error_log('TwinTack: Cart item data: ' . print_r($cart_item_data, true));
        }
        
        if (isset($cart_item_data['grip_quantity'])) {
            $grip_quantity = intval($cart_item_data['grip_quantity']);
            if (WP_DEBUG) {
                error_log('TwinTack: Found grip_quantity in cart data: ' . $grip_quantity);
            }
            
            if ($grip_quantity > 0) {
                if (WP_DEBUG) {
                    error_log('TwinTack: Updating cart item quantity from ' . $quantity . ' to ' . $grip_quantity);
                }
                WC()->cart->set_quantity($cart_item_key, $grip_quantity);
                
                if (WP_DEBUG) {
                    error_log('TwinTack: Cart quantity update completed');
                }
            } else {
                if (WP_DEBUG) {
                    error_log('TwinTack: Grip quantity is 0 or negative, not updating');
                }
            }
        } else {
            if (WP_DEBUG) {
                error_log('TwinTack: No grip_quantity found in cart item data');
            }
        }
    }

    /**
     * Handle order completion for grip designs
     */
    public function handle_grip_order_complete($order_id) {
        if (WP_DEBUG) {
            error_log('TwinTack: Processing order #' . $order_id . ' for grip designs');
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            if (WP_DEBUG) {
                error_log('TwinTack: Could not find order #' . $order_id);
            }
            return;
        }

        // Check if any order items are grip designs
        foreach ($order->get_items() as $item_id => $item) {
            if (WP_DEBUG) {
                error_log('TwinTack: Checking order item #' . $item_id);
                error_log('TwinTack: Item data: ' . print_r($item->get_data(), true));
            }

            // Try to get grip design ID from item meta
            $grip_design_id = $item->get_meta('grip_design_id');
            
            // If not found in meta, check cart item data
            if (!$grip_design_id) {
                if (WP_DEBUG) {
                    error_log('TwinTack: No grip_design_id in meta, checking cart item data');
                }
                
                // Get all meta data for debugging
                $all_meta = $item->get_meta_data();
                if (WP_DEBUG) {
                    error_log('TwinTack: All meta data: ' . print_r($all_meta, true));
                }

                // Check cart item data
                $cart_item_data = $item->get_meta('_cart_item_data');
                if (WP_DEBUG) {
                    error_log('TwinTack: Cart item data: ' . print_r($cart_item_data, true));
                }

                if (is_array($cart_item_data) && isset($cart_item_data['grip_design_id'])) {
                    $grip_design_id = $cart_item_data['grip_design_id'];
                }
            }
            
            if ($grip_design_id) {
                if (WP_DEBUG) {
                    error_log('TwinTack: Found grip design #' . $grip_design_id);
                    error_log('TwinTack: Current status: ' . get_post_meta($grip_design_id, '_grip_artwork_status', true));
                }

                // Update grip design status - Make.com will detect this via Watch Posts
                update_post_meta($grip_design_id, '_grip_artwork_status', 'approved_for_production');
                update_post_meta($grip_design_id, '_grip_final_order_id', $order_id);
                update_post_meta($grip_design_id, '_grip_production_started', current_time('mysql'));

                if (WP_DEBUG) {
                    error_log('TwinTack: Updated grip design #' . $grip_design_id . ' status to approved_for_production');
                    error_log('TwinTack: New status: ' . get_post_meta($grip_design_id, '_grip_artwork_status', true));
                }
            } else {
                if (WP_DEBUG) {
                    error_log('TwinTack: No grip design ID found in order item');
                }
            }
        }
    }

    /**
     * Display cart item data
     */
    public function display_cart_item_data($item_data, $cart_item) {
        if (isset($cart_item['grip_design_id'])) {
            $grip_id = $cart_item['grip_design_id'];
            
            $item_data[] = array(
                'key' => 'Team Name',
                'value' => get_post_meta($grip_id, '_grip_team_name', true)
            );
            
            $item_data[] = array(
                'key' => 'Design Type',
                'value' => get_post_meta($grip_id, '_grip_design_type', true)
            );
        }
        
        return $item_data;
    }

    /**
     * Save cart item data to order line item
     */
    public function save_cart_item_data_to_order($item, $cart_item_key, $values, $order) {
        if (isset($values['grip_design_id'])) {
            if (WP_DEBUG) {
                error_log('TwinTack: Saving grip design #' . $values['grip_design_id'] . ' to order item');
            }
            
            // Save grip design ID directly to order item meta
            $item->add_meta_data('grip_design_id', $values['grip_design_id']);
            
            // Save other grip design data
            $item->add_meta_data('grip_team_name', $values['grip_team_name']);
            $item->add_meta_data('grip_customer_name', $values['grip_customer_name']);
            $item->add_meta_data('grip_design_type', $values['grip_design_type']);
            
            if (WP_DEBUG) {
                error_log('TwinTack: Saved grip design data to order item');
            }
        }
    }

}