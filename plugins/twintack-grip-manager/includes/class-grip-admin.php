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
                <?php if ($artwork_url): ?>
                    <div class="artwork-preview">
                        <img src="<?php echo esc_url($artwork_url); ?>" alt="Original Artwork" style="max-width: 100%; height: auto;" />
                    </div>
                    <p class="artwork-url">
                        File: <a href="<?php echo esc_url($artwork_url); ?>" target="_blank"><?php echo esc_html($filename); ?></a>
                    </p>
                <?php else: ?>
                    <p>No artwork uploaded yet.</p>
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
            '_grip_feedback'
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
}
