<?php
/**
 * Carousel Post Type for TwinTack
 * 
 * This file registers the custom post type for carousel items
 * and its associated taxonomies and meta boxes.
 * 
 * @package TwinTack2025
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the carousel item post type
 */
function twintack_register_carousel_post_type() {
    $labels = array(
        'name'               => __('Carousel Items', 'twintack'),
        'singular_name'      => __('Carousel Item', 'twintack'),
        'menu_name'          => __('Carousel Items', 'twintack'),
        'add_new'            => __('Add New', 'twintack'),
        'add_new_item'       => __('Add New Carousel Item', 'twintack'),
        'edit_item'          => __('Edit Carousel Item', 'twintack'),
        'new_item'           => __('New Carousel Item', 'twintack'),
        'view_item'          => __('View Carousel Item', 'twintack'),
        'search_items'       => __('Search Carousel Items', 'twintack'),
        'not_found'          => __('No carousel items found', 'twintack'),
        'not_found_in_trash' => __('No carousel items found in Trash', 'twintack'),
    );
    
    $args = array(
        'labels'              => $labels,
        'public'              => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_nav_menus'   => false,
        'show_in_admin_bar'   => false,
        'menu_position'       => 20,
        'menu_icon'           => 'dashicons-slides',
        'can_export'          => true,
        'has_archive'         => false,
        'exclude_from_search' => true,
        'publicly_queryable'  => false,
        'capability_type'     => 'post',
        'show_in_rest'        => true,
        'supports'            => array('title', 'thumbnail', 'page-attributes'),
        'rewrite'             => false,
    );
    
    register_post_type('carousel_item', $args);
    
    // Register sport category taxonomy
    $tax_labels = array(
        'name'              => __('Sport Categories', 'twintack'),
        'singular_name'     => __('Sport Category', 'twintack'),
        'search_items'      => __('Search Sport Categories', 'twintack'),
        'all_items'         => __('All Sport Categories', 'twintack'),
        'parent_item'       => __('Parent Sport Category', 'twintack'),
        'parent_item_colon' => __('Parent Sport Category:', 'twintack'),
        'edit_item'         => __('Edit Sport Category', 'twintack'),
        'update_item'       => __('Update Sport Category', 'twintack'),
        'add_new_item'      => __('Add New Sport Category', 'twintack'),
        'new_item_name'     => __('New Sport Category Name', 'twintack'),
        'menu_name'         => __('Sport Categories', 'twintack'),
    );
    
    $tax_args = array(
        'hierarchical'      => true,
        'labels'            => $tax_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'sport-category'),
        'show_in_rest'      => true,
    );
    
    register_taxonomy('sport_category', array('carousel_item'), $tax_args);
}
add_action('init', 'twintack_register_carousel_post_type');

/**
 * Ensure basic sport categories exist
 */
function twintack_create_default_sport_categories() {
    $default_categories = array(
        'baseball' => __('Baseball', 'twintack'),
        'fishing'  => __('Fishing', 'twintack'),
    );
    
    foreach ($default_categories as $slug => $name) {
        if (!term_exists($slug, 'sport_category')) {
            wp_insert_term($name, 'sport_category', array('slug' => $slug));
        }
    }
}
add_action('init', 'twintack_create_default_sport_categories', 20);

/**
 * Add meta boxes for carousel items
 */
function twintack_add_carousel_meta_boxes() {
    add_meta_box(
        'twintack_carousel_product_link',
        __('Product Link', 'twintack'),
        'twintack_carousel_product_link_callback',
        'carousel_item',
        'normal',
        'high'
    );
    
    add_meta_box(
        'twintack_carousel_lightbox',
        __('Lightbox Settings', 'twintack'),
        'twintack_carousel_lightbox_callback',
        'carousel_item',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'twintack_add_carousel_meta_boxes');

/**
 * Product link meta box callback
 */
function twintack_carousel_product_link_callback($post) {
    // Add nonce for security
    wp_nonce_field('twintack_carousel_product_link', 'twintack_carousel_product_link_nonce');
    
    // Get current values
    $linked_product_id = get_post_meta($post->ID, '_linked_product_id', true);
    $linked_variation_id = get_post_meta($post->ID, '_linked_variation_id', true);
    $direct_add_to_cart = get_post_meta($post->ID, '_direct_add_to_cart', true);
    
    // Get all WooCommerce products
    $products = wc_get_products(array(
        'status' => 'publish',
        'limit' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'type' => array('simple', 'variable'), // Only get simple and variable products
    ));
    
    ?>
    <p>
        <label for="linked_product_id"><?php _e('Select Product:', 'twintack'); ?></label>
        <select name="linked_product_id" id="linked_product_id" class="widefat">
            <option value=""><?php _e('-- Select a product --', 'twintack'); ?></option>
            <?php foreach ($products as $product) : ?>
                <option value="<?php echo esc_attr($product->get_id()); ?>" <?php selected($linked_product_id, $product->get_id()); ?>>
                    <?php echo esc_html($product->get_name()); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    
    <p class="variation-select" style="display: none;">
        <label for="linked_variation_id"><?php _e('Select Colorway:', 'twintack'); ?></label>
        <select name="linked_variation_id" id="linked_variation_id" class="widefat">
            <option value=""><?php _e('-- Select a colorway --', 'twintack'); ?></option>
        </select>
    </p>
    
    <p>
        <label>
            <input type="checkbox" name="direct_add_to_cart" value="1" <?php checked($direct_add_to_cart, '1'); ?>>
            <?php _e('Show "Add to Cart" button directly on carousel item', 'twintack'); ?>
        </label>
    </p>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var variationSelect = $('.variation-select');
        var variationDropdown = $('#linked_variation_id');
        var selectedVariationId = '<?php echo esc_js($linked_variation_id); ?>';
        
        // Function to load variations
        function loadVariations(productId) {
            if (!productId) {
                variationSelect.hide();
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'twintack_get_product_variations',
                    product_id: productId,
                    nonce: '<?php echo wp_create_nonce("twintack_get_variations"); ?>'
                },
                success: function(response) {
                    if (response.success && response.data.variations) {
                        variationDropdown.empty()
                            .append('<option value=""><?php _e("-- Select a colorway --", "twintack"); ?></option>');
                        
                        $.each(response.data.variations, function(index, variation) {
                            var selected = (variation.id == selectedVariationId) ? 'selected' : '';
                            variationDropdown.append(
                                '<option value="' + variation.id + '" ' + selected + '>' + 
                                variation.name + '</option>'
                            );
                        });
                        
                        if (response.data.variations.length > 0) {
                            variationSelect.show();
                        } else {
                            variationSelect.hide();
                        }
                    }
                }
            });
        }
        
        // Load variations on page load if product is selected
        if ($('#linked_product_id').val()) {
            loadVariations($('#linked_product_id').val());
        }
        
        // Load variations when product selection changes
        $('#linked_product_id').on('change', function() {
            loadVariations($(this).val());
        });
    });
    </script>
    <?php
}

/**
 * Lightbox settings meta box callback
 */
function twintack_carousel_lightbox_callback($post) {
    // Add nonce for security
    wp_nonce_field('twintack_carousel_lightbox', 'twintack_carousel_lightbox_nonce');
    
    // Get current values
    $enable_lightbox = get_post_meta($post->ID, '_enable_lightbox', true);
    $lightbox_image_id = get_post_meta($post->ID, '_lightbox_image_id', true);
    $lightbox_image_url = $lightbox_image_id ? wp_get_attachment_image_url($lightbox_image_id, 'full') : '';
    
    ?>
    <p>
        <label>
            <input type="checkbox" name="enable_lightbox" value="1" <?php checked($enable_lightbox, '1'); ?>>
            <?php _e('Enable lightbox for this carousel item', 'twintack'); ?>
        </label>
    </p>
    <div class="twintack-lightbox-image-wrapper">
        <p>
            <label for="lightbox_image"><?php _e('Custom Lightbox Image:', 'twintack'); ?></label>
            <input type="hidden" name="lightbox_image_id" id="lightbox_image_id" value="<?php echo esc_attr($lightbox_image_id); ?>">
            <input type="text" name="lightbox_image_url" id="lightbox_image_url" value="<?php echo esc_url($lightbox_image_url); ?>" class="widefat">
            <button type="button" class="button" id="upload_lightbox_image_button"><?php _e('Upload Image', 'twintack'); ?></button>
            <button type="button" class="button" id="remove_lightbox_image_button" style="display: none;"><?php _e('Remove Image', 'twintack'); ?></button>
        </p>
        <div id="lightbox_image_preview" style="margin-top: 10px;">
            <?php if ($lightbox_image_url) : ?>
                <img src="<?php echo esc_url($lightbox_image_url); ?>" alt="" style="max-width: 100%; height: auto;">
            <?php endif; ?>
        </div>
    </div>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var mediaUploader;
        
        $('#upload_lightbox_image_button').click(function(e) {
            e.preventDefault();
            
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            
            mediaUploader = wp.media({
                title: '<?php _e('Select Lightbox Image', 'twintack'); ?>',
                button: {
                    text: '<?php _e('Use this image', 'twintack'); ?>'
                },
                multiple: false
            });
            
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#lightbox_image_id').val(attachment.id);
                $('#lightbox_image_url').val(attachment.url);
                $('#lightbox_image_preview').html('<img src="' + attachment.url + '" alt="" style="max-width: 100%; height: auto;">');
                $('#remove_lightbox_image_button').show();
            });
            
            mediaUploader.open();
        });
        
        $('#remove_lightbox_image_button').click(function(e) {
            e.preventDefault();
            $('#lightbox_image_id').val('');
            $('#lightbox_image_url').val('');
            $('#lightbox_image_preview').empty();
            $(this).hide();
        });
        
        if ($('#lightbox_image_url').val()) {
            $('#remove_lightbox_image_button').show();
        }
    });
    </script>
    <?php
}

/**
 * Save carousel meta box data
 */
function twintack_save_carousel_meta_box_data($post_id) {
    // Check if our nonces are set
    if (!isset($_POST['twintack_carousel_product_link_nonce']) || !isset($_POST['twintack_carousel_lightbox_nonce'])) {
        return;
    }
    
    // Verify the nonces
    if (!wp_verify_nonce($_POST['twintack_carousel_product_link_nonce'], 'twintack_carousel_product_link') ||
        !wp_verify_nonce($_POST['twintack_carousel_lightbox_nonce'], 'twintack_carousel_lightbox')) {
        return;
    }
    
    // If this is an autosave, don't do anything
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // Check the user's permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Save product link
    if (isset($_POST['linked_product_id'])) {
        update_post_meta($post_id, '_linked_product_id', sanitize_text_field($_POST['linked_product_id']));
    }
    
    // Save variation link
    if (isset($_POST['linked_variation_id'])) {
        update_post_meta($post_id, '_linked_variation_id', sanitize_text_field($_POST['linked_variation_id']));
    }
    
    // Save direct add to cart setting
    $direct_add_to_cart = isset($_POST['direct_add_to_cart']) ? '1' : '0';
    update_post_meta($post_id, '_direct_add_to_cart', $direct_add_to_cart);
    
    // Save lightbox settings
    $enable_lightbox = isset($_POST['enable_lightbox']) ? '1' : '0';
    update_post_meta($post_id, '_enable_lightbox', $enable_lightbox);
    
    if (isset($_POST['lightbox_image_id'])) {
        update_post_meta($post_id, '_lightbox_image_id', sanitize_text_field($_POST['lightbox_image_id']));
    }
}
add_action('save_post', 'twintack_save_carousel_meta_box_data');

/**
 * AJAX handler for getting product variations
 */
function twintack_get_product_variations() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'twintack_get_variations')) {
        wp_send_json_error('Invalid nonce');
    }
    
    // Get product ID
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    if (!$product_id) {
        wp_send_json_error('No product ID provided');
    }
    
    // Get product
    $product = wc_get_product($product_id);
    if (!$product || !$product->is_type('variable')) {
        wp_send_json_success(array('variations' => array()));
        return;
    }
    
    // Get variations
    $variations = $product->get_available_variations();
    $formatted_variations = array();
    
    foreach ($variations as $variation) {
        $variation_obj = wc_get_product($variation['variation_id']);
        if ($variation_obj && $variation_obj->is_purchasable()) {
            $attributes = array();
            foreach ($variation['attributes'] as $attribute => $value) {
                $taxonomy = str_replace('attribute_', '', $attribute);
                $term = get_term_by('slug', $value, $taxonomy);
                if ($term) {
                    $attributes[] = $term->name;
                } else {
                    $attributes[] = $value;
                }
            }
            
            $formatted_variations[] = array(
                'id' => $variation['variation_id'],
                'name' => implode(' - ', $attributes),
                'price' => $variation_obj->get_price_html()
            );
        }
    }
    
    wp_send_json_success(array('variations' => $formatted_variations));
}
add_action('wp_ajax_twintack_get_product_variations', 'twintack_get_product_variations');

/**
 * Add admin styles for meta boxes
 */
function twintack_carousel_admin_styles() {
    $screen = get_current_screen();
    
    if ($screen && $screen->post_type === 'carousel_item') {
        ?>
        <style type="text/css">
            .twintack-lightbox-image-wrapper {
                margin-top: 15px;
            }
            #lightbox_image_preview {
                margin-top: 10px;
                max-width: 300px;
            }
            #lightbox_image_preview img {
                display: block;
                max-width: 100%;
                height: auto;
                border: 1px solid #ddd;
                padding: 5px;
            }
        </style>
        <?php
    }
}
add_action('admin_head', 'twintack_carousel_admin_styles'); 