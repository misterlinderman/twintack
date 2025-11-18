<?php
/**
 * Landing Page Hero Management
 * Adds hero image fields to landing page template
 */

if (!defined('ABSPATH')) exit;

class TwinTack_Marketing_Landing_Page {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add meta box to pages using landing page template
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_meta_box'), 10, 2);
    }
    
    public function add_meta_box() {
        // Add to all pages (user can select template)
        add_meta_box(
            'twintack_landing_hero',
            __('Landing Page Hero', 'twintack-marketing'),
            array($this, 'render_meta_box'),
            'page',
            'normal',
            'high'
        );
    }
    
    public function render_meta_box($post) {
        wp_nonce_field('twintack_landing_hero_nonce', 'twintack_landing_hero_nonce');
        
        $hero_desktop = get_post_meta($post->ID, '_twintack_landing_hero_desktop', true);
        $hero_mobile = get_post_meta($post->ID, '_twintack_landing_hero_mobile', true);
        $hero_title = get_post_meta($post->ID, '_twintack_landing_hero_title', true);
        $hero_subtitle = get_post_meta($post->ID, '_twintack_landing_hero_subtitle', true);
        
        ?>
        <p class="description">
            <?php _e('These fields are used when the "Marketing Landing Page" template is selected for this page.', 'twintack-marketing'); ?>
        </p>
        
        <table class="form-table">
            <tr>
                <th>
                    <label for="twintack_landing_hero_title"><?php _e('Hero Title', 'twintack-marketing'); ?></label>
                </th>
                <td>
                    <input type="text" name="twintack_landing_hero_title" id="twintack_landing_hero_title" 
                           value="<?php echo esc_attr($hero_title); ?>" class="regular-text" />
                    <p class="description"><?php _e('Optional: Override page title in hero section', 'twintack-marketing'); ?></p>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="twintack_landing_hero_subtitle"><?php _e('Hero Subtitle', 'twintack-marketing'); ?></label>
                </th>
                <td>
                    <input type="text" name="twintack_landing_hero_subtitle" id="twintack_landing_hero_subtitle" 
                           value="<?php echo esc_attr($hero_subtitle); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th>
                    <label for="twintack_landing_hero_desktop"><?php _e('Desktop Hero Image', 'twintack-marketing'); ?></label>
                </th>
                <td>
                    <div class="image-upload-wrapper">
                        <input type="hidden" name="twintack_landing_hero_desktop" id="twintack_landing_hero_desktop" 
                               value="<?php echo esc_attr($hero_desktop); ?>" />
                        <div class="image-preview" style="width: 300px; height: 150px; border: 1px solid #ddd; margin-bottom: 10px; display: <?php echo $hero_desktop ? 'flex' : 'none'; ?>; align-items: center; justify-content: center; background: #f9f9f9;">
                            <?php if ($hero_desktop) : ?>
                                <img src="<?php echo esc_url($hero_desktop); ?>" alt="" style="max-width: 100%; max-height: 100%;" />
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button upload-hero-image" data-target="desktop">
                            <?php _e('Upload Desktop Image', 'twintack-marketing'); ?>
                        </button>
                        <button type="button" class="button remove-hero-image" data-target="desktop" style="<?php echo $hero_desktop ? '' : 'display:none;'; ?>">
                            <?php _e('Remove', 'twintack-marketing'); ?>
                        </button>
                    </div>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="twintack_landing_hero_mobile"><?php _e('Mobile Hero Image (Optional)', 'twintack-marketing'); ?></label>
                </th>
                <td>
                    <div class="image-upload-wrapper">
                        <input type="hidden" name="twintack_landing_hero_mobile" id="twintack_landing_hero_mobile" 
                               value="<?php echo esc_attr($hero_mobile); ?>" />
                        <div class="image-preview" style="width: 300px; height: 150px; border: 1px solid #ddd; margin-bottom: 10px; display: <?php echo $hero_mobile ? 'flex' : 'none'; ?>; align-items: center; justify-content: center; background: #f9f9f9;">
                            <?php if ($hero_mobile) : ?>
                                <img src="<?php echo esc_url($hero_mobile); ?>" alt="" style="max-width: 100%; max-height: 100%;" />
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button upload-hero-image" data-target="mobile">
                            <?php _e('Upload Mobile Image', 'twintack-marketing'); ?>
                        </button>
                        <button type="button" class="button remove-hero-image" data-target="mobile" style="<?php echo $hero_mobile ? '' : 'display:none;'; ?>">
                            <?php _e('Remove', 'twintack-marketing'); ?>
                        </button>
                    </div>
                </td>
            </tr>
        </table>
        
        <script>
        jQuery(document).ready(function($) {
            $('.upload-hero-image').on('click', function() {
                var target = $(this).data('target');
                var inputId = 'twintack_landing_hero_' + target;
                var $input = $('#' + inputId);
                var $preview = $input.siblings('.image-preview');
                var $removeBtn = $input.siblings('.remove-hero-image[data-target="' + target + '"]');
                
                var frame = wp.media({
                    title: 'Select Hero Image',
                    button: {
                        text: 'Use Image'
                    },
                    multiple: false
                });
                
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $input.val(attachment.url);
                    $preview.html('<img src="' + attachment.url + '" alt="" style="max-width: 100%; max-height: 100%;" />');
                    $preview.show();
                    $removeBtn.show();
                });
                
                frame.open();
            });
            
            $('.remove-hero-image').on('click', function() {
                var target = $(this).data('target');
                var inputId = 'twintack_landing_hero_' + target;
                var $input = $('#' + inputId);
                var $preview = $input.siblings('.image-preview');
                
                $input.val('');
                $preview.html('').hide();
                $(this).hide();
            });
        });
        </script>
        <?php
    }
    
    public function save_meta_box($post_id, $post) {
        // Check nonce
        if (!isset($_POST['twintack_landing_hero_nonce']) || 
            !wp_verify_nonce($_POST['twintack_landing_hero_nonce'], 'twintack_landing_hero_nonce')) {
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Check if autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Save hero data
        if (isset($_POST['twintack_landing_hero_title'])) {
            update_post_meta($post_id, '_twintack_landing_hero_title', sanitize_text_field($_POST['twintack_landing_hero_title']));
        }
        
        if (isset($_POST['twintack_landing_hero_subtitle'])) {
            update_post_meta($post_id, '_twintack_landing_hero_subtitle', sanitize_text_field($_POST['twintack_landing_hero_subtitle']));
        }
        
        if (isset($_POST['twintack_landing_hero_desktop'])) {
            update_post_meta($post_id, '_twintack_landing_hero_desktop', esc_url_raw($_POST['twintack_landing_hero_desktop']));
        }
        
        if (isset($_POST['twintack_landing_hero_mobile'])) {
            update_post_meta($post_id, '_twintack_landing_hero_mobile', esc_url_raw($_POST['twintack_landing_hero_mobile']));
        }
    }
}

