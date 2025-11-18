<?php
/**
 * Admin interface enhancements for the TwinTack Carousel
 * 
 * This file adds admin columns, dashboard widgets, and other improvements
 * to make managing carousel items easier.
 * 
 * @package TwinTack2025
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add custom columns to the Carousel Items admin list
 */
function twintack_carousel_item_columns($columns) {
    $new_columns = array();
    
    // Add thumbnail after checkbox but before title
    foreach ($columns as $key => $value) {
        if ($key === 'cb') {
            $new_columns[$key] = $value;
            $new_columns['thumbnail'] = __('Image', 'twintack');
        } elseif ($key === 'title') {
            $new_columns[$key] = $value;
            $new_columns['linked_product'] = __('Linked Product', 'twintack');
            $new_columns['sport_category'] = __('Sport', 'twintack');
            $new_columns['lightbox'] = __('Lightbox', 'twintack');
            $new_columns['add_to_cart'] = __('Add to Cart', 'twintack');
        } else {
            $new_columns[$key] = $value;
        }
    }
    
    return $new_columns;
}
add_filter('manage_carousel_item_posts_columns', 'twintack_carousel_item_columns');

/**
 * Display content for custom columns
 */
function twintack_carousel_item_custom_column($column, $post_id) {
    switch ($column) {
        case 'thumbnail':
            if (has_post_thumbnail($post_id)) {
                echo '<a href="' . esc_url(get_edit_post_link($post_id)) . '">';
                echo get_the_post_thumbnail($post_id, array(50, 50));
                echo '</a>';
            } else {
                echo '<div style="width:50px;height:50px;background:#f8f9fa;border:1px solid #ddd;"></div>';
            }
            break;
            
        case 'linked_product':
            $linked_product_id = get_post_meta($post_id, '_linked_product_id', true);
            if ($linked_product_id) {
                $product = wc_get_product($linked_product_id);
                if ($product) {
                    echo '<a href="' . esc_url(get_edit_post_link($linked_product_id)) . '">' . esc_html($product->get_title()) . '</a>';
                } else {
                    echo '—';
                }
            } else {
                echo '—';
            }
            break;
            
        case 'sport_category':
            $terms = get_the_terms($post_id, 'sport_category');
            if (!empty($terms) && !is_wp_error($terms)) {
                $sport_links = array();
                foreach ($terms as $term) {
                    $sport_links[] = '<a href="' . esc_url(admin_url('edit.php?post_type=carousel_item&sport_category=' . $term->slug)) . '">' . esc_html($term->name) . '</a>';
                }
                echo implode(', ', $sport_links);
            } else {
                echo '—';
            }
            break;
            
        case 'lightbox':
            $enable_lightbox = get_post_meta($post_id, '_enable_lightbox', true);
            echo $enable_lightbox === '1' ? '<span style="color:green;">✓</span>' : '<span style="color:red;">✗</span>';
            break;
            
        case 'add_to_cart':
            $direct_add_to_cart = get_post_meta($post_id, '_direct_add_to_cart', true);
            echo $direct_add_to_cart === '1' ? '<span style="color:green;">✓</span>' : '<span style="color:red;">✗</span>';
            break;
    }
}
add_action('manage_carousel_item_posts_custom_column', 'twintack_carousel_item_custom_column', 10, 2);

/**
 * Make custom columns sortable
 */
function twintack_carousel_item_sortable_columns($columns) {
    $columns['sport_category'] = 'sport_category';
    $columns['linked_product'] = 'linked_product';
    return $columns;
}
add_filter('manage_edit-carousel_item_sortable_columns', 'twintack_carousel_item_sortable_columns');

/**
 * Add filter dropdowns to the admin list
 */
function twintack_carousel_item_filters() {
    global $typenow;
    
    if ($typenow === 'carousel_item') {
        // Sport Category Filter - already handled by WordPress for taxonomies
        
        // Linked Product Filter
        $linked_product = isset($_GET['linked_product']) ? $_GET['linked_product'] : '';
        
        echo '<select name="linked_product">';
        echo '<option value="">' . __('All Products', 'twintack') . '</option>';
        
        // Get all products used in carousel items
        $args = array(
            'post_type' => 'carousel_item',
            'posts_per_page' => -1,
            'fields' => 'ids',
        );
        $carousel_items = get_posts($args);
        
        $product_ids = array();
        foreach ($carousel_items as $item_id) {
            $product_id = get_post_meta($item_id, '_linked_product_id', true);
            if ($product_id && !in_array($product_id, $product_ids)) {
                $product_ids[] = $product_id;
            }
        }
        
        // Get product names and create dropdown
        foreach ($product_ids as $id) {
            $product = wc_get_product($id);
            if ($product) {
                echo '<option value="' . esc_attr($id) . '" ' . selected($id, $linked_product, false) . '>' . esc_html($product->get_title()) . '</option>';
            }
        }
        
        echo '</select>';
        
        // Lightbox Filter
        $lightbox = isset($_GET['lightbox']) ? $_GET['lightbox'] : '';
        echo '<select name="lightbox">';
        echo '<option value="">' . __('Any Lightbox Status', 'twintack') . '</option>';
        echo '<option value="1" ' . selected('1', $lightbox, false) . '>' . __('Lightbox Enabled', 'twintack') . '</option>';
        echo '<option value="0" ' . selected('0', $lightbox, false) . '>' . __('Lightbox Disabled', 'twintack') . '</option>';
        echo '</select>';
        
        // Add to Cart Filter
        $add_to_cart = isset($_GET['add_to_cart']) ? $_GET['add_to_cart'] : '';
        echo '<select name="add_to_cart">';
        echo '<option value="">' . __('Any Add to Cart Status', 'twintack') . '</option>';
        echo '<option value="1" ' . selected('1', $add_to_cart, false) . '>' . __('Add to Cart Enabled', 'twintack') . '</option>';
        echo '<option value="0" ' . selected('0', $add_to_cart, false) . '>' . __('Add to Cart Disabled', 'twintack') . '</option>';
        echo '</select>';
    }
}
add_action('restrict_manage_posts', 'twintack_carousel_item_filters');

/**
 * Modify the main query to handle our custom filters
 */
function twintack_carousel_item_filter_query($query) {
    global $pagenow, $typenow;
    
    if ($pagenow === 'edit.php' && $typenow === 'carousel_item' && is_admin()) {
        // Handle Linked Product filter
        if (isset($_GET['linked_product']) && $_GET['linked_product'] !== '') {
            $query->query_vars['meta_key'] = '_linked_product_id';
            $query->query_vars['meta_value'] = $_GET['linked_product'];
            $query->query_vars['meta_compare'] = '=';
        }
        
        // Handle Lightbox filter
        if (isset($_GET['lightbox']) && $_GET['lightbox'] !== '') {
            $meta_query = array(
                'key' => '_enable_lightbox',
                'value' => $_GET['lightbox'],
                'compare' => '='
            );
            
            if (isset($query->query_vars['meta_query'])) {
                $query->query_vars['meta_query'][] = $meta_query;
            } else {
                $query->query_vars['meta_query'] = array($meta_query);
            }
        }
        
        // Handle Add to Cart filter
        if (isset($_GET['add_to_cart']) && $_GET['add_to_cart'] !== '') {
            $meta_query = array(
                'key' => '_direct_add_to_cart',
                'value' => $_GET['add_to_cart'],
                'compare' => '='
            );
            
            if (isset($query->query_vars['meta_query'])) {
                $query->query_vars['meta_query'][] = $meta_query;
            } else {
                $query->query_vars['meta_query'] = array($meta_query);
            }
        }
    }
    
    return $query;
}
add_action('pre_get_posts', 'twintack_carousel_item_filter_query');

/**
 * Add a dashboard widget showing carousel items by sport
 */
function twintack_add_carousel_dashboard_widget() {
    wp_add_dashboard_widget(
        'twintack_carousel_dashboard_widget',
        __('Carousel Items by Sport', 'twintack'),
        'twintack_carousel_dashboard_widget_callback'
    );
}
add_action('wp_dashboard_setup', 'twintack_add_carousel_dashboard_widget');

/**
 * Dashboard widget callback
 */
function twintack_carousel_dashboard_widget_callback() {
    // Get all sport categories
    $sport_terms = get_terms(array(
        'taxonomy' => 'sport_category',
        'hide_empty' => false,
    ));
    
    if (empty($sport_terms) || is_wp_error($sport_terms)) {
        echo '<p>' . __('No sport categories found.', 'twintack') . '</p>';
        return;
    }
    
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>' . __('Sport', 'twintack') . '</th>';
    echo '<th>' . __('Items', 'twintack') . '</th>';
    echo '<th>' . __('Actions', 'twintack') . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';
    
    foreach ($sport_terms as $term) {
        // Count carousel items for this sport
        $args = array(
            'post_type' => 'carousel_item',
            'tax_query' => array(
                array(
                    'taxonomy' => 'sport_category',
                    'field' => 'term_id',
                    'terms' => $term->term_id,
                ),
            ),
            'posts_per_page' => -1,
            'fields' => 'ids',
        );
        $items = get_posts($args);
        $count = count($items);
        
        echo '<tr>';
        echo '<td>' . esc_html($term->name) . '</td>';
        echo '<td>' . esc_html($count) . '</td>';
        echo '<td>';
        echo '<a href="' . esc_url(admin_url('edit.php?post_type=carousel_item&sport_category=' . $term->slug)) . '">' . __('View', 'twintack') . '</a> | ';
        echo '<a href="' . esc_url(admin_url('post-new.php?post_type=carousel_item&sport_category=' . $term->slug)) . '">' . __('Add New', 'twintack') . '</a>';
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    
    echo '<p class="twintack-dashboard-actions">';
    echo '<a href="' . esc_url(admin_url('edit.php?post_type=carousel_item')) . '" class="button button-primary">' . __('Manage All Carousel Items', 'twintack') . '</a> ';
    echo '<a href="' . esc_url(admin_url('post-new.php?post_type=carousel_item')) . '" class="button">' . __('Add New Carousel Item', 'twintack') . '</a>';
    echo '</p>';
}

/**
 * Add Quick Edit fields for carousel items
 */
function twintack_carousel_quick_edit($column_name, $post_type) {
    if ($post_type !== 'carousel_item' || $column_name !== 'lightbox') {
        return;
    }
    ?>
    <fieldset class="inline-edit-col-right">
        <div class="inline-edit-col">
            <label class="inline-edit-group">
                <span class="title"><?php _e('Lightbox', 'twintack'); ?></span>
                <input type="checkbox" name="enable_lightbox" value="1" />
            </label>
            <label class="inline-edit-group">
                <span class="title"><?php _e('Add to Cart', 'twintack'); ?></span>
                <input type="checkbox" name="direct_add_to_cart" value="1" />
            </label>
        </div>
    </fieldset>
    <?php
}
add_action('quick_edit_custom_box', 'twintack_carousel_quick_edit', 10, 2);

/**
 * Add JavaScript for Quick Edit
 */
function twintack_carousel_quick_edit_js() {
    global $current_screen;
    
    if ($current_screen->id !== 'edit-carousel_item') {
        return;
    }
    ?>
    <script type="text/javascript">
    (function($) {
        // We'll create a copy of the WP inline edit post function
        var $wp_inline_edit = inlineEditPost.edit;
        
        // Then we override the function with our own
        inlineEditPost.edit = function(id) {
            // Call the original WP edit function to set up the Quick Edit panel
            $wp_inline_edit.apply(this, arguments);
            
            // Get the post ID
            var $post_id = 0;
            if (typeof(id) == 'object') {
                $post_id = parseInt(this.getId(id));
            }
            
            if ($post_id > 0) {
                // Define the row and edit row
                var $edit_row = $('#edit-' + $post_id);
                var $post_row = $('#post-' + $post_id);
                
                // Get the data
                var enableLightbox = $post_row.find('.column-lightbox span.dashicons-yes').length > 0;
                var enableAddToCart = $post_row.find('.column-add_to_cart span.dashicons-yes').length > 0;
                
                // Populate the form
                $edit_row.find('input[name="enable_lightbox"]').prop('checked', enableLightbox);
                $edit_row.find('input[name="direct_add_to_cart"]').prop('checked', enableAddToCart);
            }
        };
        
        $(document).ready(function() {
            // Add save event to capture our custom data
            $('#inline-edit').on('submit', function() {
                // Get the post ID
                var post_id = $('input[name="post_ID"]').val();
                
                // Get the values
                var enableLightbox = $('input[name="enable_lightbox"]').is(':checked') ? '1' : '0';
                var enableAddToCart = $('input[name="direct_add_to_cart"]').is(':checked') ? '1' : '0';
                
                // Add them as hidden fields
                $(this).append('<input type="hidden" name="_enable_lightbox" value="' + enableLightbox + '">');
                $(this).append('<input type="hidden" name="_direct_add_to_cart" value="' + enableAddToCart + '">');
            });
        });
    })(jQuery);
    </script>
    <?php
}
add_action('admin_footer-edit.php', 'twintack_carousel_quick_edit_js');

/**
 * Save Quick Edit data
 */
function twintack_carousel_save_quick_edit_data($post_id) {
    // Don't save for autosave or revisions
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id)) {
        return;
    }
    
    // Check post type
    if (get_post_type($post_id) !== 'carousel_item') {
        return;
    }
    
    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Save lightbox setting
    if (isset($_POST['_enable_lightbox'])) {
        update_post_meta($post_id, '_enable_lightbox', sanitize_text_field($_POST['_enable_lightbox']));
    }
    
    // Save add to cart setting
    if (isset($_POST['_direct_add_to_cart'])) {
        update_post_meta($post_id, '_direct_add_to_cart', sanitize_text_field($_POST['_direct_add_to_cart']));
    }
}
add_action('save_post', 'twintack_carousel_save_quick_edit_data');

/**
 * Add help text to the Carousel Item edit screen
 */
function twintack_carousel_item_help_tabs() {
    $screen = get_current_screen();
    
    if (!$screen || $screen->post_type !== 'carousel_item') {
        return;
    }
    
    // Main help tab
    $screen->add_help_tab(array(
        'id'      => 'twintack_carousel_help_overview',
        'title'   => __('Overview', 'twintack'),
        'content' => '
            <h2>' . __('Carousel Items Overview', 'twintack') . '</h2>
            <p>' . __('Carousel items are used to display products in the TwinTack sport category carousels.', 'twintack') . '</p>
            <p>' . __('Each carousel item consists of:', 'twintack') . '</p>
            <ul>
                <li>' . __('<strong>Title</strong> - The name displayed on the carousel item', 'twintack') . '</li>
                <li>' . __('<strong>Featured Image</strong> - The image displayed in the carousel (optimized at 600x150 for grip wraps)', 'twintack') . '</li>
                <li>' . __('<strong>Sport Category</strong> - Which sport carousel this item appears in', 'twintack') . '</li>
                <li>' . __('<strong>Product Link</strong> - The WooCommerce product this carousel item links to', 'twintack') . '</li>
                <li>' . __('<strong>Lightbox Settings</strong> - Whether clicking the image opens a larger view', 'twintack') . '</li>
            </ul>
        ',
    ));
    
    // Settings help tab
    $screen->add_help_tab(array(
        'id'      => 'twintack_carousel_help_settings',
        'title'   => __('Settings', 'twintack'),
        'content' => '
            <h2>' . __('Carousel Item Settings', 'twintack') . '</h2>
            <ul>
                <li>' . __('<strong>Product Link</strong> - Connect this carousel item to a WooCommerce product. This determines where "View Details" and "Add to Cart" buttons lead.', 'twintack') . '</li>
                <li>' . __('<strong>Add to Cart Button</strong> - Check this box to display an "Add to Cart" button directly on the carousel item.', 'twintack') . '</li>
                <li>' . __('<strong>Lightbox</strong> - Enable image lightbox functionality. When enabled, clicking the carousel image opens a larger view.', 'twintack') . '</li>
                <li>' . __('<strong>Custom Lightbox Image</strong> - Optional. Upload a different image for the lightbox view. If not set, the featured image will be used.', 'twintack') . '</li>
            </ul>
        ',
    ));
    
    // Image optimization help tab
    $screen->add_help_tab(array(
        'id'      => 'twintack_carousel_help_images',
        'title'   => __('Image Optimization', 'twintack'),
        'content' => '
            <h2>' . __('Carousel Image Guidelines', 'twintack') . '</h2>
            <p>' . __('For optimal display in the product carousel, follow these guidelines:', 'twintack') . '</p>
            <ul>
                <li>' . __('<strong>Dimensions</strong> - Prepare images at 600px width by 150px height (4:1 aspect ratio)', 'twintack') . '</li>
                <li>' . __('<strong>Orientation</strong> - Images will be displayed in their natural orientation', 'twintack') . '</li>
                <li>' . __('<strong>File size</strong> - Keep images under 200KB for optimal performance', 'twintack') . '</li>
                <li>' . __('<strong>Background</strong> - Use consistent backgrounds for a cohesive carousel appearance', 'twintack') . '</li>
            </ul>
        ',
    ));
    
    // Side help content
    $screen->set_help_sidebar(
        '<p><strong>' . __('For more information:', 'twintack') . '</strong></p>' .
        '<p><a href="' . admin_url('edit.php?post_type=carousel_item') . '">' . __('Manage Carousel Items', 'twintack') . '</a></p>' .
        '<p><a href="' . admin_url('edit-tags.php?taxonomy=sport_category&post_type=carousel_item') . '">' . __('Manage Sport Categories', 'twintack') . '</a></p>'
    );
}
add_action('current_screen', 'twintack_carousel_item_help_tabs');

/**
 * Add "Add New Carousel Item" to the "+New" admin bar menu
 */
function twintack_admin_bar_carousel_item($admin_bar) {
    if (!current_user_can('edit_posts')) {
        return;
    }
    
    $admin_bar->add_menu(array(
        'parent' => 'new-content',
        'id'     => 'new-carousel-item',
        'title'  => __('Carousel Item', 'twintack'),
        'href'   => admin_url('post-new.php?post_type=carousel_item')
    ));
}
add_action('admin_bar_menu', 'twintack_admin_bar_carousel_item', 99);

/**
 * Add a "Duplicate" action for carousel items
 */
function twintack_duplicate_carousel_item_link($actions, $post) {
    if ($post->post_type === 'carousel_item' && current_user_can('edit_posts')) {
        $actions['duplicate'] = '<a href="' . wp_nonce_url(admin_url('admin.php?action=duplicate_carousel_item&post=' . $post->ID), 'duplicate-carousel-item_' . $post->ID) . '" aria-label="' . esc_attr__('Duplicate this item', 'twintack') . '">' . __('Duplicate', 'twintack') . '</a>';
    }
    return $actions;
}
add_filter('post_row_actions', 'twintack_duplicate_carousel_item_link', 10, 2);

/**
 * Handle the duplicate carousel item action
 */
function twintack_duplicate_carousel_item() {
    // Check if post ID is provided
    if (empty($_GET['post'])) {
        wp_die(__('No carousel item to duplicate has been provided!', 'twintack'));
    }
    
    // Check permissions and nonce
    $post_id = intval($_GET['post']);
    if (!current_user_can('edit_post', $post_id)) {
        wp_die(__('You do not have permission to duplicate this carousel item.', 'twintack'));
    }
    
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'duplicate-carousel-item_' . $post_id)) {
        wp_die(__('Security check failed. Please try again.', 'twintack'));
    }
    
    // Get the original post
    $post = get_post($post_id);
    if (!$post) {
        wp_die(__('Carousel item creation failed, could not find original item.', 'twintack'));
    }
    
    // Create the duplicate
    $new_post_args = array(
        'post_title'     => $post->post_title . ' ' . __('(Copy)', 'twintack'),
        'post_status'    => 'draft',
        'post_type'      => $post->post_type,
        'comment_status' => $post->comment_status,
        'ping_status'    => $post->ping_status,
        'post_author'    => get_current_user_id(),
    );
    
    // Insert the new post
    $new_post_id = wp_insert_post($new_post_args);
    
    if (!is_wp_error($new_post_id)) {
        // Copy post meta
        $meta_keys = get_post_custom_keys($post_id);
        if ($meta_keys) {
            foreach ($meta_keys as $meta_key) {
                $meta_values = get_post_custom_values($meta_key, $post_id);
                foreach ($meta_values as $meta_value) {
                    $meta_value = maybe_unserialize($meta_value);
                    add_post_meta($new_post_id, $meta_key, $meta_value);
                }
            }
        }
        
        // Copy featured image
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            set_post_thumbnail($new_post_id, $thumbnail_id);
        }
        
        // Copy taxonomy terms
        $taxonomies = get_object_taxonomies($post->post_type);
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
            wp_set_object_terms($new_post_id, $terms, $taxonomy, false);
        }
        
        // Redirect to the edit screen for the new item
        wp_redirect(admin_url('post.php?action=edit&post=' . $new_post_id));
        exit;
    } else {
        wp_die(__('Carousel item duplication failed.', 'twintack') . ': ' . $new_post_id->get_error_message());
    }
}
add_action('admin_action_duplicate_carousel_item', 'twintack_duplicate_carousel_item'); 