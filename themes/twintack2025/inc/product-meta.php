<?php
/**
 * Product Meta Box for Template Selection
 *
 * Adds a meta box to product edit pages to select between
 * standard and redesigned product templates.
 *
 * @package TwinTack2025
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add the meta box to the product editor
 */
function twintack_add_product_template_meta_box() {
    add_meta_box(
        'twintack_product_template_meta',
        __('Product Template Options', 'twintack2025'),
        'twintack_product_template_meta_box_callback',
        'product',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'twintack_add_product_template_meta_box');

/**
 * Meta box display callback
 */
function twintack_product_template_meta_box_callback($post) {
    // Add a nonce field for security
    wp_nonce_field('twintack_product_template_meta_box', 'twintack_product_template_meta_box_nonce');

    // Get the current value
    $use_redesign = get_post_meta($post->ID, '_use_product_redesign', true);
    
    ?>
    <p>
        <label for="use_product_redesign">
            <input type="checkbox" id="use_product_redesign" name="use_product_redesign" value="yes" <?php checked($use_redesign, 'yes'); ?> />
            <?php _e('Use redesigned product template', 'twintack2025'); ?>
        </label>
    </p>
    <p class="description">
        <?php _e('Check this box to use the new product page layout with tall grip image display.', 'twintack2025'); ?>
    </p>
    <?php
}

/**
 * Save the meta box data
 */
function twintack_save_product_template_meta_box($post_id) {
    // Verify nonce
    if (!isset($_POST['twintack_product_template_meta_box_nonce']) || 
        !wp_verify_nonce($_POST['twintack_product_template_meta_box_nonce'], 'twintack_product_template_meta_box')) {
        return;
    }

    // Don't save on autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save the checkbox value
    if (isset($_POST['use_product_redesign'])) {
        update_post_meta($post_id, '_use_product_redesign', 'yes');
    } else {
        update_post_meta($post_id, '_use_product_redesign', 'no');
    }
}
add_action('save_post_product', 'twintack_save_product_template_meta_box'); 