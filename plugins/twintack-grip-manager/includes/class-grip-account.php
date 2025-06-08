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
        add_action('init', array($this, 'register_endpoints'));
        add_filter('woocommerce_account_menu_items', array($this, 'add_grip_designs_endpoint'));
        add_action('woocommerce_account_grip-designs_endpoint', array($this, 'grip_designs_content'), 5); // Run before theme
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
                
                // Check for Monday.com mockup (priority over artwork)
                $mockup_asset_url = get_post_meta(get_the_ID(), '_grip_mockup_asset_url', true);
                $display_url = !empty($mockup_asset_url) ? $mockup_asset_url : $artwork_url;
                $has_image = !empty($display_url) && filter_var($display_url, FILTER_VALIDATE_URL);
                $card_class = $has_image ? 'has-artwork' : 'no-artwork';
                $background_style = $has_image ? 'style="background-image: url(' . esc_url($display_url) . ')"' : '';
                
                // Get artwork status label
                $status_labels = array(
                    'artwork_pending'   => 'Artwork Pending',
                    'pending_review'    => 'Pending Review',
                    'artwork_approved'  => 'Artwork Approved',
                    'internal_review'   => 'Internal Review',
                    'in_production'     => 'In Production',
                    'shipped'           => 'Shipped'
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
        <div class="grip-design-container">
            <div class="grip-design-header">
                <h1><?php echo esc_html($post->post_title); ?></h1>
                <?php 
                $artwork_status = get_post_meta($grip_id, '_grip_artwork_status', true) ?: 'artwork_pending';
                // Get artwork status label
                $status_labels = array(
                    'artwork_pending'   => 'Artwork Pending',
                    'pending_review'    => 'Pending Review',
                    'artwork_approved'  => 'Artwork Approved',
                    'internal_review'   => 'Internal Review',
                    'in_production'     => 'In Production',
                    'shipped'           => 'Shipped'
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
                    if (!empty($monday_feedback)): ?>
                        <div class="grip-monday-feedback">
                            <h3>Message from Design Team</h3>
                            <div class="monday-feedback-content">
                                <?php echo wpautop(esc_html($monday_feedback)); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="grip-design-artwork">
                    <?php 
                    $mockup_asset_url = get_post_meta($grip_id, '_grip_mockup_asset_url', true);
                    $artwork_url = get_post_meta($grip_id, '_grip_artwork_url', true);
                    $filename = get_post_meta($grip_id, '_grip_artwork_filename', true);
                    
                    // Show Monday.com mockup if available
                    if (!empty($mockup_asset_url) && filter_var($mockup_asset_url, FILTER_VALIDATE_URL)): ?>
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
}