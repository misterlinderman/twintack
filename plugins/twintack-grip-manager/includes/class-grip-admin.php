<?php
class TwinTack_Grip_Admin {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add custom columns
        add_filter('manage_grip_design_posts_columns', array($this, 'add_custom_columns'));
        add_action('manage_grip_design_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
        
        // Add meta boxes
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_grip_design', array($this, 'save_grip_design'));
        
        // Add admin menu for volume pricing settings
        add_action('admin_menu', array($this, 'add_volume_pricing_menu'));
        add_action('admin_init', array($this, 'register_volume_pricing_settings'));
        
        // Handle email notification test AJAX
        add_action('wp_ajax_test_grip_email', array($this, 'handle_test_email_ajax'));
        
        // Add product meta boxes for volume pricing
        add_action('add_meta_boxes', array($this, 'add_volume_pricing_meta_boxes'));
        add_action('save_post_product', array($this, 'save_volume_pricing_meta'));
    }
    
    /**
     * Add volume pricing admin menu
     */
    public function add_volume_pricing_menu() {
        add_submenu_page(
            'edit.php?post_type=grip_design',
            'Volume Pricing Settings',
            'Volume Pricing',
            'manage_options',
            'grip-volume-pricing',
            array($this, 'volume_pricing_settings_page')
        );
    }
    
    /**
     * Register volume pricing settings
     */
    public function register_volume_pricing_settings() {
        register_setting('grip_volume_pricing_settings', 'grip_volume_pricing_product_id');
        register_setting('grip_volume_pricing_settings', 'grip_volume_pricing_enabled');
        register_setting('grip_volume_pricing_settings', 'grip_volume_pricing_settings');
    }
    
    /**
     * Volume pricing settings page
     */
    public function volume_pricing_settings_page() {
        $current_product_id = get_option('grip_volume_pricing_product_id', '');
        $pricing_enabled = get_option('grip_volume_pricing_enabled', false);
        $pricing_settings = get_option('grip_volume_pricing_settings', array());
        
        // Get all products for dropdown
        $products = wc_get_products(array(
            'limit' => -1,
            'status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        ?>
        <div class="wrap">
            <h1>Custom Grip Volume Pricing Settings</h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('grip_volume_pricing_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Volume Pricing</th>
                        <td>
                            <label>
                                <input type="checkbox" name="grip_volume_pricing_enabled" value="1" <?php checked($pricing_enabled, 1); ?> />
                                Enable volume pricing system
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Custom Grip Product</th>
                        <td>
                            <select name="grip_volume_pricing_product_id" class="regular-text">
                                <option value="">Select a product...</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo esc_attr($product->get_id()); ?>" 
                                            <?php selected($current_product_id, $product->get_id()); ?>>
                                        <?php echo esc_html($product->get_name() . ' (ID: ' . $product->get_id() . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Select which product should use the custom volume pricing system.</p>
                        </td>
                    </tr>
                </table>
                
                <?php if ($current_product_id): ?>
                    <h2>Global Volume Pricing Rules</h2>
                    <p>These settings apply to the selected product. You can also set individual product-specific settings in the product edit page.</p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Minimum Quantity</th>
                            <td>
                                <input type="number" name="grip_volume_pricing_settings[min_qty]" 
                                       value="<?php echo esc_attr($pricing_settings['min_qty'] ?? 25); ?>" 
                                       min="1" class="small-text" />
                                <p class="description">Minimum quantity that can be ordered.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Quantity Step</th>
                            <td>
                                <input type="number" name="grip_volume_pricing_settings[qty_step]" 
                                       value="<?php echo esc_attr($pricing_settings['qty_step'] ?? 25); ?>" 
                                       min="1" class="small-text" />
                                <p class="description">Quantity must be ordered in multiples of this number.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Volume Break Quantity</th>
                            <td>
                                <input type="number" name="grip_volume_pricing_settings[volume_break_qty]" 
                                       value="<?php echo esc_attr($pricing_settings['volume_break_qty'] ?? 75); ?>" 
                                       min="1" class="small-text" />
                                <p class="description">Quantity threshold for volume pricing to apply.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Volume Discount Type</th>
                            <td>
                                <select name="grip_volume_pricing_settings[discount_type]">
                                    <option value="fixed" <?php selected($pricing_settings['discount_type'] ?? 'fixed', 'fixed'); ?>>Fixed Amount ($)</option>
                                    <option value="percentage" <?php selected($pricing_settings['discount_type'] ?? 'fixed', 'percentage'); ?>>Percentage (%)</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Volume Discount Amount</th>
                            <td>
                                <input type="number" name="grip_volume_pricing_settings[discount_amount]" 
                                       value="<?php echo esc_attr($pricing_settings['discount_amount'] ?? 2.00); ?>" 
                                       step="0.01" min="0" class="small-text" />
                                <span class="description">
                                    <?php echo ($pricing_settings['discount_type'] ?? 'fixed') === 'fixed' ? 'Dollar amount to subtract from regular price' : 'Percentage to discount'; ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>
                
                <?php submit_button(); ?>
            </form>
            
            <?php if ($current_product_id): ?>
                <hr>
                <h2>Current Configuration Summary</h2>
                <?php
                $product = wc_get_product($current_product_id);
                if ($product):
                    $regular_price = $product->get_regular_price();
                    $discount_type = $pricing_settings['discount_type'] ?? 'fixed';
                    $discount_amount = $pricing_settings['discount_amount'] ?? 2.00;
                    
                    if ($discount_type === 'fixed') {
                        $volume_price = $regular_price - $discount_amount;
                    } else {
                        $volume_price = $regular_price * (1 - ($discount_amount / 100));
                    }
                ?>
                    <div class="notice notice-info">
                        <p><strong>Product:</strong> <?php echo esc_html($product->get_name()); ?></p>
                        <p><strong>Regular Price:</strong> <?php echo wc_price($regular_price); ?></p>
                        <p><strong>Volume Price (<?php echo esc_html($pricing_settings['volume_break_qty'] ?? 75); ?>+ units):</strong> <?php echo wc_price($volume_price); ?></p>
                        <p><strong>Minimum Order:</strong> <?php echo esc_html($pricing_settings['min_qty'] ?? 25); ?> units</p>
                        <p><strong>Order Increments:</strong> Multiples of <?php echo esc_html($pricing_settings['qty_step'] ?? 25); ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Update discount description based on type
            $('select[name="grip_volume_pricing_settings[discount_type]"]').on('change', function() {
                var type = $(this).val();
                var description = $(this).closest('tr').next().find('.description');
                if (type === 'fixed') {
                    description.text('Dollar amount to subtract from regular price');
                } else {
                    description.text('Percentage to discount');
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Add volume pricing meta boxes to product edit page
     */
    public function add_volume_pricing_meta_boxes() {
        $current_product_id = get_option('grip_volume_pricing_product_id', '');
        
        if (!empty($current_product_id)) {
            add_meta_box(
                'grip_volume_pricing_meta',
                'Custom Grip Volume Pricing',
                array($this, 'render_volume_pricing_meta_box'),
                'product',
                'normal',
                'high'
            );
        }
    }
    
    /**
     * Render volume pricing meta box
     */
    public function render_volume_pricing_meta_box($post) {
        $current_product_id = get_option('grip_volume_pricing_product_id', '');
        $is_volume_product = ($post->ID == $current_product_id);
        
        wp_nonce_field('grip_volume_pricing_meta', 'grip_volume_pricing_meta_nonce');
        
        if ($is_volume_product) {
            // Get product-specific settings
            $use_global = get_post_meta($post->ID, '_grip_use_global_pricing', true);
            $min_qty = get_post_meta($post->ID, '_grip_min_qty', true);
            $qty_step = get_post_meta($post->ID, '_grip_qty_step', true);
            $volume_break_qty = get_post_meta($post->ID, '_grip_volume_break_qty', true);
            $discount_type = get_post_meta($post->ID, '_grip_discount_type', true);
            $discount_amount = get_post_meta($post->ID, '_grip_discount_amount', true);
            
            // Get global settings for defaults
            $global_settings = get_option('grip_volume_pricing_settings', array());
            ?>
            <div class="grip-volume-pricing-settings">
                <p><strong>This product is configured as the Custom Grip Volume Pricing product.</strong></p>
                
                <p>
                    <label>
                        <input type="checkbox" name="_grip_use_global_pricing" value="1" <?php checked($use_global, 1); ?> />
                        Use global pricing settings
                    </label>
                </p>
                
                <div class="custom-pricing-fields" style="<?php echo $use_global ? 'display:none;' : ''; ?>">
                    <table class="form-table">
                        <tr>
                            <th>Minimum Quantity</th>
                            <td>
                                <input type="number" name="_grip_min_qty" 
                                       value="<?php echo esc_attr($min_qty ?: ($global_settings['min_qty'] ?? 25)); ?>" 
                                       min="1" class="small-text" />
                            </td>
                        </tr>
                        <tr>
                            <th>Quantity Step</th>
                            <td>
                                <input type="number" name="_grip_qty_step" 
                                       value="<?php echo esc_attr($qty_step ?: ($global_settings['qty_step'] ?? 25)); ?>" 
                                       min="1" class="small-text" />
                            </td>
                        </tr>
                        <tr>
                            <th>Volume Break Quantity</th>
                            <td>
                                <input type="number" name="_grip_volume_break_qty" 
                                       value="<?php echo esc_attr($volume_break_qty ?: ($global_settings['volume_break_qty'] ?? 75)); ?>" 
                                       min="1" class="small-text" />
                            </td>
                        </tr>
                        <tr>
                            <th>Discount Type</th>
                            <td>
                                <select name="_grip_discount_type">
                                    <option value="fixed" <?php selected($discount_type ?: ($global_settings['discount_type'] ?? 'fixed'), 'fixed'); ?>>Fixed Amount ($)</option>
                                    <option value="percentage" <?php selected($discount_type ?: ($global_settings['discount_type'] ?? 'fixed'), 'percentage'); ?>>Percentage (%)</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Discount Amount</th>
                            <td>
                                <input type="number" name="_grip_discount_amount" 
                                       value="<?php echo esc_attr($discount_amount ?: ($global_settings['discount_amount'] ?? 2.00)); ?>" 
                                       step="0.01" min="0" class="small-text" />
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                $('input[name="_grip_use_global_pricing"]').on('change', function() {
                    if ($(this).is(':checked')) {
                        $('.custom-pricing-fields').hide();
                    } else {
                        $('.custom-pricing-fields').show();
                    }
                });
            });
            </script>
            <?php
        } else {
            echo '<p>This product is not configured for custom grip volume pricing.</p>';
            echo '<p><a href="' . admin_url('edit.php?post_type=grip_design&page=grip-volume-pricing') . '">Configure Volume Pricing Settings</a></p>';
        }
    }
    
    /**
     * Save volume pricing meta
     */
    public function save_volume_pricing_meta($post_id) {
        if (!isset($_POST['grip_volume_pricing_meta_nonce']) || 
            !wp_verify_nonce($_POST['grip_volume_pricing_meta_nonce'], 'grip_volume_pricing_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        
        $current_product_id = get_option('grip_volume_pricing_product_id', '');
        
        if ($post_id == $current_product_id) {
            $fields = array(
                '_grip_use_global_pricing',
                '_grip_min_qty',
                '_grip_qty_step',
                '_grip_volume_break_qty',
                '_grip_discount_type',
                '_grip_discount_amount'
            );
            
            foreach ($fields as $field) {
                if (isset($_POST[$field])) {
                    if ($field === '_grip_use_global_pricing') {
                        update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
                    } else {
                        update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
                    }
                } else {
                    // Handle checkboxes that aren't checked
                    if ($field === '_grip_use_global_pricing') {
                        delete_post_meta($post_id, $field);
                    }
                }
            }
        }
    }

    public function add_meta_boxes() {
        add_meta_box(
            'grip_design_details',
            'Grip Design Details',
            array($this, 'render_details_meta_box'),
            'grip_design',
            'normal',
            'high'
        );
        
        add_meta_box(
            'grip_artwork_versions',
            'Artwork Versions',
            array($this, 'render_artwork_meta_box'),
            'grip_design',
            'normal',
            'high'
        );
    }

    public function render_details_meta_box($post) {
        wp_nonce_field('grip_design_meta_box', 'grip_design_meta_box_nonce');
        
        $fields = array(
            '_grip_customer_name' => array('label' => 'Customer Name', 'type' => 'text'),
            '_grip_customer_email' => array('label' => 'Customer Email', 'type' => 'email'),
            '_grip_team_name' => array('label' => 'Team/School Name', 'type' => 'text'),
            '_grip_design_type' => array('label' => 'Design Type', 'type' => 'text'),
            '_grip_quantity' => array('label' => 'Quantity', 'type' => 'number')
        );
        
        echo '<div class="grip-design-details">';
        foreach ($fields as $meta_key => $field) {
            $value = get_post_meta($post->ID, $meta_key, true);
            echo '<p>';
            echo '<label for="' . esc_attr($meta_key) . '">' . esc_html($field['label']) . ':</label>';
            echo '<input type="' . esc_attr($field['type']) . '" id="' . esc_attr($meta_key) . '" name="' . esc_attr($meta_key) . '" value="' . esc_attr($value) . '" class="widefat" />';
            echo '</p>';
        }
        
        // Add color fields from new form if they exist
        $design_layout = get_post_meta($post->ID, '_grip_design_layout', true);
        if (!empty($design_layout)) {
            echo '<hr>';
            echo '<h3>Pattern & Color Details</h3>';
            
            echo '<p>';
            echo '<label for="_grip_design_layout">Pattern:</label>';
            echo '<input type="text" id="_grip_design_layout" name="_grip_design_layout" value="' . esc_attr($design_layout) . '" class="widefat" />';
            echo '</p>';
            
            $primary_color = get_post_meta($post->ID, '_grip_primary_color', true);
            echo '<p>';
            echo '<label for="_grip_primary_color">Primary Color:</label>';
            echo '<input type="text" id="_grip_primary_color" name="_grip_primary_color" value="' . esc_attr($primary_color) . '" class="widefat" />';
            echo '</p>';
            
            $secondary_color = get_post_meta($post->ID, '_grip_secondary_color', true);
            if (!empty($secondary_color)) {
                echo '<p>';
                echo '<label for="_grip_secondary_color">Secondary Color:</label>';
                echo '<input type="text" id="_grip_secondary_color" name="_grip_secondary_color" value="' . esc_attr($secondary_color) . '" class="widefat" />';
                echo '</p>';
            }
            
            $tertiary_color = get_post_meta($post->ID, '_grip_tertiary_color', true);
            if (!empty($tertiary_color)) {
                echo '<p>';
                echo '<label for="_grip_tertiary_color">Tertiary Color:</label>';
                echo '<input type="text" id="_grip_tertiary_color" name="_grip_tertiary_color" value="' . esc_attr($tertiary_color) . '" class="widefat" />';
                echo '</p>';
            }
        }
        
        // Add Design Instructions field
        $feedback = get_post_meta($post->ID, '_grip_feedback', true);
        echo '<p>';
        echo '<label for="_grip_feedback">Design Instructions:</label>';
        echo '<textarea id="_grip_feedback" name="_grip_feedback" class="widefat" rows="4">' . esc_textarea($feedback) . '</textarea>';
        echo '</p>';
        
        echo '</div>';
    }

    public function render_artwork_meta_box($post) {
        $artwork_url = get_post_meta($post->ID, '_grip_artwork_url', true);
        $filename = get_post_meta($post->ID, '_grip_artwork_filename', true);
        ?>
        <div class="grip-artwork-versions">
            <div class="original-artwork">
                <h4>Original Submitted Artwork</h4>
                <?php if (!empty($artwork_url) && filter_var($artwork_url, FILTER_VALIDATE_URL)): ?>
                    <div class="artwork-preview">
                        <img src="<?php echo esc_url($artwork_url); ?>" alt="Original Artwork" style="max-width: 100%; height: auto;" />
                    </div>
                    <p class="artwork-url">
                        File: <a href="<?php echo esc_url($artwork_url); ?>" target="_blank"><?php echo esc_html($filename); ?></a>
                    </p>
                <?php else: ?>
                    <div class="artwork-preview no-artwork" style="background: #f5f5f5; padding: 30px; text-align: center; border: 1px dashed #ddd;">
                        <p>No artwork available for preview</p>
                    </div>
                    <?php if (!empty($filename)): ?>
                        <p class="artwork-url">
                            File: <?php echo esc_html($filename); ?> (URL unavailable)
                        </p>
                    <?php else: ?>
                        <p>No artwork uploaded yet.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function save_grip_design($post_id) {
        if (!isset($_POST['grip_design_meta_box_nonce']) || 
            !wp_verify_nonce($_POST['grip_design_meta_box_nonce'], 'grip_design_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        
        $fields = array(
            '_grip_customer_name',
            '_grip_customer_email',
            '_grip_team_name',
            '_grip_design_type',
            '_grip_quantity',
            '_grip_feedback',
            // Add new color fields
            '_grip_design_layout',
            '_grip_primary_color',
            '_grip_secondary_color',
            '_grip_tertiary_color'
        );
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                if ($field === '_grip_feedback') {
                    update_post_meta($post_id, $field, sanitize_textarea_field($_POST[$field]));
                } else {
                    update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
                }
            }
        }
    }

    public function add_custom_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = 'Grip Design';
        $new_columns['customer'] = 'Customer';
        $new_columns['team'] = 'Team/School';
        $new_columns['design_type'] = 'Design Type';
        $new_columns['quantity'] = 'Quantity';
        $new_columns['status'] = 'Status';
        $new_columns['date'] = $columns['date'];
        return $new_columns;
    }

    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'customer':
                echo esc_html(get_post_meta($post_id, '_grip_customer_name', true));
                break;
            case 'team':
                echo esc_html(get_post_meta($post_id, '_grip_team_name', true));
                break;
            case 'design_type':
                echo esc_html(get_post_meta($post_id, '_grip_design_type', true));
                break;
            case 'quantity':
                echo esc_html(get_post_meta($post_id, '_grip_quantity', true));
                break;
            case 'status':
                $status = get_post_status($post_id);
                echo esc_html(get_post_status_object($status)->label);
                break;
        }
    }
    
    /**
     * Handle AJAX test email request
     */
    public function handle_test_email_ajax() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'test_grip_email')) {
            wp_die('Invalid nonce');
        }
        
        $email_type = sanitize_text_field($_POST['email_type']);
        $test_email = sanitize_email($_POST['test_email']);
        $grip_id = !empty($_POST['grip_id']) ? intval($_POST['grip_id']) : null;
        
        // Get email notifications instance
        $email_notifications = TwinTack_Grip_Email_Notifications::get_instance();
        
        // Send test email
        $success = $email_notifications->send_test_email($email_type, $test_email, $grip_id);
        
        if ($success) {
            wp_send_json_success('Test email sent successfully to ' . $test_email);
        } else {
            wp_send_json_error('Failed to send test email');
        }
    }
}
