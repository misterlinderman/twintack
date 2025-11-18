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
        
        // Handle AJAX requests
        add_action('wp_ajax_test_grip_email', array($this, 'handle_test_email_ajax'));
        add_action('wp_ajax_grip_import_gf_entry', array($this, 'handle_gf_import_ajax'));
        
        // Add product meta boxes for volume pricing
        add_action('add_meta_boxes', array($this, 'add_volume_pricing_meta_boxes'));
        add_action('save_post_product', array($this, 'save_volume_pricing_meta'));
        
        // Enqueue media uploader for grip designs
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
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
        // Gravity Forms Importer (top priority for new posts)
        add_meta_box(
            'grip_gf_importer',
            'Import from Gravity Forms',
            array($this, 'render_gf_importer_meta_box'),
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
            'grip_artwork_versions',
            'Artwork Versions',
            array($this, 'render_artwork_meta_box'),
            'grip_design',
            'normal',
            'high'
        );
        
        // Order & System Information
        add_meta_box(
            'grip_system_info',
            'Order & System Information',
            array($this, 'render_system_info_meta_box'),
            'grip_design',
            'side',
            'default'
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
        wp_nonce_field('grip_artwork_meta_box', 'grip_artwork_meta_box_nonce');
        
        $artwork_url = get_post_meta($post->ID, '_grip_artwork_url', true);
        $filename = get_post_meta($post->ID, '_grip_artwork_filename', true);
        $artwork_attachment_id = get_post_meta($post->ID, '_grip_artwork_attachment_id', true);
        ?>
        <div class="grip-artwork-versions">
            <div class="original-artwork">
                <h4>Original Submitted Artwork</h4>
                <?php if (!empty($artwork_url) && filter_var($artwork_url, FILTER_VALIDATE_URL)): ?>
                    <div class="artwork-preview">
                        <img src="<?php echo esc_url($artwork_url); ?>" alt="Original Artwork" style="max-width: 100%; height: auto; border: 1px solid #ddd;" />
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
                
                <hr style="margin: 20px 0;">
                
                <h4>Upload or Link Artwork</h4>
                
                <p>
                    <label for="_grip_artwork_url"><strong>Artwork URL:</strong></label><br>
                    <input type="url" id="_grip_artwork_url" name="_grip_artwork_url" 
                           value="<?php echo esc_attr($artwork_url); ?>" 
                           class="widefat" 
                           placeholder="https://twintack.com/wp-content/uploads/..." />
                    <span class="description">Paste the direct URL to the artwork file (from Gravity Forms uploads or Media Library)</span>
                </p>
                
                <p>
                    <label for="_grip_artwork_filename"><strong>Filename:</strong></label><br>
                    <input type="text" id="_grip_artwork_filename" name="_grip_artwork_filename" 
                           value="<?php echo esc_attr($filename); ?>" 
                           class="widefat" 
                           placeholder="artwork.jpg" />
                    <span class="description">Just the filename (e.g., IMG_8870.jpeg)</span>
                </p>
                
                <p>
                    <strong>Or Upload New Artwork:</strong><br>
                    <button type="button" class="button grip-upload-artwork-btn" data-post-id="<?php echo esc_attr($post->ID); ?>">
                        📁 Upload Artwork File
                    </button>
                    <?php if ($artwork_attachment_id): ?>
                        <br><small>Current attachment ID: <?php echo esc_html($artwork_attachment_id); ?></small>
                    <?php endif; ?>
                </p>
                
                <script>
                jQuery(document).ready(function($) {
                    $('.grip-upload-artwork-btn').on('click', function(e) {
                        e.preventDefault();
                        var button = $(this);
                        var postId = button.data('post-id');
                        
                        var mediaUploader = wp.media({
                            title: 'Select Artwork',
                            button: {
                                text: 'Use this artwork'
                            },
                            multiple: false
                        });
                        
                        mediaUploader.on('select', function() {
                            var attachment = mediaUploader.state().get('selection').first().toJSON();
                            $('#_grip_artwork_url').val(attachment.url);
                            $('#_grip_artwork_filename').val(attachment.filename);
                            $('input[name="_grip_artwork_attachment_id"]').remove();
                            button.after('<input type="hidden" name="_grip_artwork_attachment_id" value="' + attachment.id + '">');
                            
                            // Update preview
                            $('.artwork-preview').html('<img src="' + attachment.url + '" alt="Artwork" style="max-width: 100%; height: auto; border: 1px solid #ddd;" />');
                            $('.artwork-url').html('File: <a href="' + attachment.url + '" target="_blank">' + attachment.filename + '</a>');
                        });
                        
                        mediaUploader.open();
                    });
                });
                </script>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Gravity Forms Importer meta box
     */
    public function render_gf_importer_meta_box($post) {
        $current_entry_id = get_post_meta($post->ID, '_grip_form_entry_id', true);
        ?>
        <div class="grip-gf-importer">
            <p>
                <label for="gf_entry_id"><strong>Gravity Forms Entry ID:</strong></label><br>
                <input type="number" id="gf_entry_id" name="gf_entry_lookup" 
                       value="<?php echo esc_attr($current_entry_id); ?>" 
                       class="widefat" 
                       placeholder="849" 
                       min="1" />
            </p>
            
            <p>
                <button type="button" class="button button-primary grip-import-gf-btn" style="width: 100%;">
                    📥 Import Data from Entry
                </button>
            </p>
            
            <?php if ($current_entry_id): ?>
                <p class="description">
                    Currently linked to entry #<?php echo esc_html($current_entry_id); ?>
                    <?php if (class_exists('GFAPI')): ?>
                        <br><a href="<?php echo admin_url('admin.php?page=gf_entries&view=entry&id=9&lid=' . $current_entry_id); ?>" target="_blank">View Entry →</a>
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <p class="description">
                    Enter the Gravity Forms entry ID to import customer and design data.
                </p>
            <?php endif; ?>
            
            <div class="grip-import-result" style="margin-top: 15px; padding: 10px; display: none; border-left: 4px solid #00a0d2; background: #f0f8ff;"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.grip-import-gf-btn').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var entryId = $('#gf_entry_id').val();
                var postId = <?php echo (int) $post->ID; ?>;
                var resultDiv = $('.grip-import-result');
                
                if (!entryId) {
                    alert('Please enter a Gravity Forms entry ID');
                    return;
                }
                
                button.prop('disabled', true).text('⏳ Importing...');
                resultDiv.hide();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'grip_import_gf_entry',
                        entry_id: entryId,
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce('grip_import_gf_' . $post->ID); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Use warning color if there are missing fields, success color otherwise
                            var borderColor = response.data.has_warnings ? '#f0b849' : '#46b450';
                            var bgColor = response.data.has_warnings ? '#fff8e5' : '#f0f8ff';
                            
                            resultDiv.css({
                                'border-left-color': borderColor,
                                'background': bgColor
                            })
                            .html(response.data.message)
                            .show();
                            
                            // Populate fields with imported data
                            if (response.data.fields) {
                                var fields = response.data.fields;
                                for (var key in fields) {
                                    var input = $('[name="' + key + '"]');
                                    if (input.length && fields[key]) {
                                        input.val(fields[key]);
                                        // Remove any previous highlighting
                                        input.css('background', '');
                                    }
                                }
                            }
                            
                            // Highlight empty required fields
                            if (response.data.missing_fields && response.data.missing_fields.length > 0) {
                                // Highlight Team/School Name if missing (most critical)
                                if ($('[name="_grip_team_name"]').val() === '') {
                                    $('[name="_grip_team_name"]').css('background', '#fff3cd');
                                }
                                // Highlight Customer Name if missing
                                if ($('[name="_grip_customer_name"]').val() === '') {
                                    $('[name="_grip_customer_name"]').css('background', '#fff3cd');
                                }
                                
                                // Show reminder to fill missing fields
                                resultDiv.append('<br><br><strong>→ Please fill the highlighted fields above and save.</strong>');
                                
                                // Don't auto-save if fields are missing
                            } else {
                                // All fields complete - suggest saving
                                setTimeout(function() {
                                    if (confirm('All data imported! Would you like to save now?')) {
                                        $('#publish').click();
                                    }
                                }, 1000);
                            }
                        } else {
                            resultDiv.css('border-left-color', '#dc3232')
                                    .html('<strong>✗ Error:</strong><br>' + response.data.message)
                                    .show();
                        }
                    },
                    error: function() {
                        resultDiv.css('border-left-color', '#dc3232')
                                .html('<strong>✗ Error:</strong><br>Failed to connect to server')
                                .show();
                    },
                    complete: function() {
                        button.prop('disabled', false).text('📥 Import Data from Entry');
                    }
                });
            });
        });
        </script>
        
        <style>
        .grip-gf-importer .button-primary {
            background: #0073aa;
            border-color: #0073aa;
        }
        .grip-gf-importer .button-primary:hover {
            background: #005177;
            border-color: #005177;
        }
        .grip-import-result strong {
            display: block;
            margin-bottom: 8px;
        }
        .grip-import-result ul {
            margin: 8px 0;
            padding-left: 20px;
        }
        input[name="_grip_team_name"]:required,
        input[name="_grip_customer_name"]:required {
            border-left: 3px solid #f0b849;
        }
        </style>
        <?php
    }
    
    /**
     * Render System Info meta box
     */
    public function render_system_info_meta_box($post) {
        wp_nonce_field('grip_system_info_meta_box', 'grip_system_info_meta_box_nonce');
        
        $order_id = get_post_meta($post->ID, '_grip_order_id', true);
        $order_item_id = get_post_meta($post->ID, '_grip_order_item_id', true);
        $form_entry_id = get_post_meta($post->ID, '_grip_form_entry_id', true);
        $form_type = get_post_meta($post->ID, '_grip_form_type', true);
        $timestamp = get_post_meta($post->ID, '_grip_timestamp', true);
        ?>
        <div class="grip-system-info">
            <p>
                <label for="_grip_form_entry_id"><strong>Form Entry ID:</strong></label><br>
                <input type="number" id="_grip_form_entry_id" name="_grip_form_entry_id" 
                       value="<?php echo esc_attr($form_entry_id); ?>" 
                       class="widefat" 
                       min="1" />
                <?php if ($form_entry_id && class_exists('GFAPI')): ?>
                    <small><a href="<?php echo admin_url('admin.php?page=gf_entries&view=entry&id=9&lid=' . $form_entry_id); ?>" target="_blank">View Entry →</a></small>
                <?php endif; ?>
            </p>
            
            <p>
                <label for="_grip_form_type"><strong>Form Type:</strong></label><br>
                <select id="_grip_form_type" name="_grip_form_type" class="widefat">
                    <option value="">-- Select --</option>
                    <option value="new" <?php selected($form_type, 'new'); ?>>New Form (ID 9)</option>
                    <option value="original" <?php selected($form_type, 'original'); ?>>Original Form (ID 8)</option>
                </select>
            </p>
            
            <hr>
            
            <p>
                <label for="_grip_order_id"><strong>Order ID:</strong></label><br>
                <input type="number" id="_grip_order_id" name="_grip_order_id" 
                       value="<?php echo esc_attr($order_id); ?>" 
                       class="widefat" 
                       min="1" />
                <?php if ($order_id): ?>
                    <small><a href="<?php echo admin_url('post.php?post=' . $order_id . '&action=edit'); ?>" target="_blank">View Order →</a></small>
                <?php endif; ?>
            </p>
            
            <p>
                <label for="_grip_order_item_id"><strong>Order Item ID:</strong></label><br>
                <input type="number" id="_grip_order_item_id" name="_grip_order_item_id" 
                       value="<?php echo esc_attr($order_item_id); ?>" 
                       class="widefat" 
                       min="1" />
            </p>
            
            <?php if ($timestamp): ?>
                <hr>
                <p>
                    <strong>Form Submission:</strong><br>
                    <small><?php echo esc_html(date('Y-m-d H:i:s', $timestamp)); ?></small>
                </p>
            <?php endif; ?>
            
            <?php
            $created_via = get_post_meta($post->ID, '_grip_created_via_fallback', true);
            if ($created_via):
            ?>
                <hr>
                <p style="background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107;">
                    <strong>⚠️ Manual Creation:</strong><br>
                    <small>Created via fallback tool on <?php echo esc_html($created_via); ?></small>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function save_grip_design($post_id) {
        // Check nonces
        $nonces_to_check = array(
            'grip_design_meta_box_nonce' => 'grip_design_meta_box',
            'grip_artwork_meta_box_nonce' => 'grip_artwork_meta_box',
            'grip_system_info_meta_box_nonce' => 'grip_system_info_meta_box'
        );
        
        $nonce_verified = false;
        foreach ($nonces_to_check as $nonce_name => $nonce_action) {
            if (isset($_POST[$nonce_name]) && wp_verify_nonce($_POST[$nonce_name], $nonce_action)) {
                $nonce_verified = true;
                break;
            }
        }
        
        if (!$nonce_verified) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        
        // All grip design meta fields
        $fields = array(
            // Customer info
            '_grip_customer_name',
            '_grip_customer_email',
            '_grip_team_name',
            
            // Design specs
            '_grip_design_type',
            '_grip_quantity',
            '_grip_feedback',
            
            // Color/pattern fields (new form)
            '_grip_design_layout',
            '_grip_primary_color',
            '_grip_secondary_color',
            '_grip_tertiary_color',
            
            // Artwork fields
            '_grip_artwork_url',
            '_grip_artwork_filename',
            '_grip_artwork_attachment_id',
            
            // System fields
            '_grip_form_entry_id',
            '_grip_form_type',
            '_grip_order_id',
            '_grip_order_item_id'
        );
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $value = $_POST[$field];
                
                // Sanitize based on field type
                if ($field === '_grip_feedback') {
                    $value = sanitize_textarea_field($value);
                } elseif ($field === '_grip_customer_email') {
                    $value = sanitize_email($value);
                } elseif ($field === '_grip_artwork_url') {
                    $value = esc_url_raw($value);
                } elseif (in_array($field, array('_grip_quantity', '_grip_form_entry_id', '_grip_order_id', '_grip_order_item_id', '_grip_artwork_attachment_id'))) {
                    $value = absint($value);
                } else {
                    $value = sanitize_text_field($value);
                }
                
                update_post_meta($post_id, $field, $value);
            }
        }
        
        // If artwork filename wasn't set but URL was, extract filename from URL
        if (isset($_POST['_grip_artwork_url']) && !empty($_POST['_grip_artwork_url']) && empty($_POST['_grip_artwork_filename'])) {
            $url = $_POST['_grip_artwork_url'];
            $filename = basename(parse_url($url, PHP_URL_PATH));
            update_post_meta($post_id, '_grip_artwork_filename', sanitize_file_name($filename));
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
    
    /**
     * Enqueue admin scripts for grip designs
     */
    public function enqueue_admin_scripts($hook) {
        global $post;
        
        // Only load on grip_design post type edit screens
        if ($hook === 'post.php' || $hook === 'post-new.php') {
            if (isset($post) && $post->post_type === 'grip_design') {
                wp_enqueue_media();
            }
        }
    }
    
    /**
     * Handle Gravity Forms import AJAX request
     */
    public function handle_gf_import_ajax() {
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        // Verify nonce
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!wp_verify_nonce($_POST['nonce'], 'grip_import_gf_' . $post_id)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }
        
        // Check if Gravity Forms is available
        if (!class_exists('GFAPI')) {
            wp_send_json_error(array('message' => 'Gravity Forms is not active'));
            return;
        }
        
        $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
        
        if (!$entry_id) {
            wp_send_json_error(array('message' => 'No entry ID provided'));
            return;
        }
        
        // Get the entry
        $entry = GFAPI::get_entry($entry_id);
        
        if (is_wp_error($entry)) {
            wp_send_json_error(array('message' => 'Entry not found: ' . $entry->get_error_message()));
            return;
        }
        
        // Determine which form this is from
        $form_id = $entry['form_id'];
        $form_type = ($form_id == 9) ? 'new' : 'original';
        
        // Extract data from entry
        // Field mappings for both forms:
        // 1.3 = First Name, 1.6 = Last Name
        // 8 = Team/School Name
        // 12 = Design Type/Layout
        // 21 = Quantity
        // 9 = File Upload
        // 14 = Feedback/Design Instructions
        // 41 = Primary Color (new form)
        // 42 = Secondary Color (new form)
        // 43 = Tertiary Color (new form)
        
        $first_name = rgar($entry, '1.3');
        $last_name = rgar($entry, '1.6');
        $customer_name = trim($first_name . ' ' . $last_name);
        $customer_email = rgar($entry, '2'); // Email field
        $team_name = rgar($entry, '8');
        $quantity = rgar($entry, '21');
        $file_upload = rgar($entry, '9');
        $feedback = rgar($entry, '14');
        
        // Get the appropriate design type
        $design_layout = rgar($entry, '12');
        $primary_color = rgar($entry, '41');
        $secondary_color = rgar($entry, '42');
        $tertiary_color = rgar($entry, '43');
        
        // Construct design type
        if ($form_type === 'new') {
            $design_type = $design_layout;
            if ($design_layout != 'Solid Color') {
                $colors = $primary_color;
                if (!empty($secondary_color)) {
                    $colors .= " + " . $secondary_color;
                }
                if (!empty($tertiary_color)) {
                    $colors .= " + " . $tertiary_color;
                }
                $design_type .= " (" . $colors . ")";
            } else {
                $design_type .= " (" . $primary_color . ")";
            }
        } else {
            $design_type = rgar($entry, '12'); // Original form just has the design type directly
        }
        
        // Extract filename from upload
        $filename = !empty($file_upload) ? basename($file_upload) : '';
        
        // Track what's missing for helpful feedback
        $missing_fields = array();
        $warnings = array();
        
        if (empty($customer_name)) {
            $missing_fields[] = 'Customer Name';
            $warnings[] = '⚠️ Customer Name is missing - please add manually';
        }
        
        if (empty($team_name)) {
            $missing_fields[] = 'Team/School Name';
            $warnings[] = '⚠️ Team/School Name is REQUIRED - please add manually';
        }
        
        if (empty($quantity)) {
            $missing_fields[] = 'Quantity';
            $warnings[] = '⚠️ Quantity is missing - please add manually';
        }
        
        if (empty($file_upload)) {
            $missing_fields[] = 'Artwork';
            $warnings[] = '⚠️ No artwork file uploaded in form - add via upload or URL';
        }
        
        // Update the grip design post with whatever data is available
        $meta_updates = array(
            '_grip_customer_name' => $customer_name,
            '_grip_customer_email' => $customer_email,
            '_grip_team_name' => $team_name,
            '_grip_design_type' => $design_type,
            '_grip_quantity' => $quantity,
            '_grip_artwork_url' => $file_upload,
            '_grip_artwork_filename' => $filename,
            '_grip_feedback' => $feedback,
            '_grip_form_entry_id' => $entry_id,
            '_grip_form_type' => $form_type,
            '_grip_timestamp' => strtotime($entry['date_created'])
        );
        
        // Add new form specific fields
        if ($form_type === 'new') {
            $meta_updates['_grip_design_layout'] = $design_layout;
            $meta_updates['_grip_primary_color'] = $primary_color;
            $meta_updates['_grip_secondary_color'] = $secondary_color;
            $meta_updates['_grip_tertiary_color'] = $tertiary_color;
        }
        
        // Update all meta fields
        foreach ($meta_updates as $meta_key => $meta_value) {
            update_post_meta($post_id, $meta_key, $meta_value);
        }
        
        // Update post title if this is a new post
        $post = get_post($post_id);
        if ($post && ($post->post_title === 'Auto Draft' || empty($post->post_title))) {
            $date_suffix = current_time('ymd');
            $new_title = sprintf('Custom Grip - %s %s', $team_name, $date_suffix);
            wp_update_post(array(
                'ID' => $post_id,
                'post_title' => $new_title
            ));
        }
        
        // Prepare response with imported data
        $response_fields = array();
        foreach ($meta_updates as $meta_key => $meta_value) {
            $response_fields[$meta_key] = $meta_value;
        }
        
        // Build success message
        $message_parts = array();
        $imported_count = 0;
        
        // Count non-empty imported fields
        foreach ($meta_updates as $value) {
            if (!empty($value)) {
                $imported_count++;
            }
        }
        
        $message_parts[] = sprintf('✓ Imported %d fields from entry #%d', $imported_count, $entry_id);
        
        if (!empty($missing_fields)) {
            $message_parts[] = sprintf('<br><strong>Missing %d field(s):</strong>', count($missing_fields));
            $message_parts = array_merge($message_parts, $warnings);
        } else {
            $message_parts[] = '<br><strong>✓ All fields complete!</strong>';
        }
        
        wp_send_json_success(array(
            'message' => implode('<br>', $message_parts),
            'fields' => $response_fields,
            'entry_id' => $entry_id,
            'form_type' => $form_type,
            'missing_fields' => $missing_fields,
            'has_warnings' => !empty($missing_fields)
        ));
    }
}
