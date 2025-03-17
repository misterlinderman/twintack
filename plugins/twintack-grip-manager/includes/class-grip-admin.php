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
        // Get stored metadata
        $customer_name = get_post_meta($post->ID, '_grip_customer_name', true);
        $customer_email = get_post_meta($post->ID, '_grip_customer_email', true);
        $team_name = get_post_meta($post->ID, '_grip_team_name', true);
        $design_type = get_post_meta($post->ID, '_grip_design_type', true);
        $quantity = get_post_meta($post->ID, '_grip_quantity', true);
        
        // Output the fields
        ?>
        <div class="grip-design-details">
            <p>
                <label><strong>Customer Name:</strong></label><br>
                <input type="text" name="grip_customer_name" value="<?php echo esc_attr($customer_name); ?>" class="widefat">
            </p>
            <p>
                <label><strong>Customer Email:</strong></label><br>
                <input type="email" name="grip_customer_email" value="<?php echo esc_attr($customer_email); ?>" class="widefat">
            </p>
            <p>
                <label><strong>Team/School Name:</strong></label><br>
                <input type="text" name="grip_team_name" value="<?php echo esc_attr($team_name); ?>" class="widefat">
            </p>
            <p>
                <label><strong>Design Type:</strong></label><br>
                <input type="text" name="grip_design_type" value="<?php echo esc_attr($design_type); ?>" class="widefat">
            </p>
            <p>
                <label><strong>Quantity:</strong></label><br>
                <input type="number" name="grip_quantity" value="<?php echo esc_attr($quantity); ?>" class="widefat">
            </p>
        </div>
        <?php
    }

    public function render_artwork_meta_box($post) {
        // Get artwork versions
        global $wpdb;
        $versions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}grip_artwork_versions WHERE grip_id = %d ORDER BY version DESC",
            $post->ID
        ));
        
        ?>
        <div class="grip-artwork-versions">
            <div class="artwork-list">
                <?php if ($versions): ?>
                    <?php foreach ($versions as $version): ?>
                        <div class="artwork-version">
                            <h4>Version <?php echo esc_html($version->version); ?></h4>
                            <p>Status: <?php echo esc_html($version->status); ?></p>
                            <p>Created: <?php echo esc_html($version->created_at); ?></p>
                            <p><a href="<?php echo esc_url($version->file_url); ?>" target="_blank">View Artwork</a></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No artwork versions uploaded yet.</p>
                <?php endif; ?>
            </div>
            
            <div class="upload-new-version">
                <h4>Upload New Version</h4>
                <input type="file" name="new_artwork" accept="image/*,.pdf">
                <button type="button" class="button upload-artwork">Upload</button>
            </div>
        </div>
        <?php
    }

    public function save_grip_design($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        
        $fields = array(
            'grip_customer_name' => '_grip_customer_name',
            'grip_customer_email' => '_grip_customer_email',
            'grip_team_name' => '_grip_team_name',
            'grip_design_type' => '_grip_design_type',
            'grip_quantity' => '_grip_quantity'
        );
        
        foreach ($fields as $field => $meta_key) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$field]));
            }
        }
    }

    public function add_custom_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['customer'] = 'Customer';
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
}
