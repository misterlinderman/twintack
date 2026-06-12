<?php
/**
 * Bundle Counter - Cart Progress Tracker
 * Displays a progress bar showing customer's progress toward bundle discounts
 */

if (!defined('ABSPATH')) exit;

class TwinTack_Marketing_Bundle_Counter {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Display bundle counter at top of page
        add_action('wp_body_open', array($this, 'display_bundle_counter'), 6);
        
        // Admin settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX endpoint for real-time cart updates
        add_action('wp_ajax_get_bundle_count', array($this, 'ajax_get_bundle_count'));
        add_action('wp_ajax_nopriv_get_bundle_count', array($this, 'ajax_get_bundle_count'));
    }
    
    public function register_settings() {
        register_setting('twintack_bundle_counter', 'twintack_bundle_counter_enabled');
        register_setting('twintack_bundle_counter', 'twintack_bundle_counter_category');
        register_setting('twintack_bundle_counter', 'twintack_bundle_counter_bundle_size');
        register_setting('twintack_bundle_counter', 'twintack_bundle_counter_discount_text');
        register_setting('twintack_bundle_counter', 'twintack_bundle_counter_bg_color');
        register_setting('twintack_bundle_counter', 'twintack_bundle_counter_text_color');
        register_setting('twintack_bundle_counter', 'twintack_bundle_counter_progress_color');
    }
    
    public function render_settings_page() {
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Bundle Counter Settings', 'twintack-marketing'); ?></h1>
            <p class="description"><?php _e('Display a cart progress bar showing customers how close they are to qualifying for bundle discounts.', 'twintack-marketing'); ?></p>
            
            <div class="twintack-bundle-counter-settings">
                <form method="post" action="options.php">
                    <?php settings_fields('twintack_bundle_counter'); ?>
                    <?php do_settings_sections('twintack_bundle_counter'); ?>
                    
                    <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Bundle Counter', 'twintack-marketing'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="twintack_bundle_counter_enabled" value="1" 
                                       <?php checked(get_option('twintack_bundle_counter_enabled'), '1'); ?> />
                                <?php _e('Show bundle counter on all pages', 'twintack-marketing'); ?>
                            </label>
                            <p class="description"><?php _e('This works independently from the standard announcement bar', 'twintack-marketing'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="twintack_bundle_counter_category"><?php _e('Product Category', 'twintack-marketing'); ?></label>
                        </th>
                        <td>
                            <select name="twintack_bundle_counter_category" id="twintack_bundle_counter_category" class="regular-text">
                                <option value=""><?php _e('Select a category...', 'twintack-marketing'); ?></option>
                                <?php 
                                $selected_category = get_option('twintack_bundle_counter_category', '');
                                foreach ($categories as $category) : 
                                ?>
                                    <option value="<?php echo esc_attr($category->term_id); ?>" 
                                            <?php selected($selected_category, $category->term_id); ?>>
                                        <?php echo esc_html($category->name); ?> (ID: <?php echo $category->term_id; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Select the product category to track for bundle counting (e.g., Bat Grips)', 'twintack-marketing'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="twintack_bundle_counter_bundle_size"><?php _e('Bundle Size', 'twintack-marketing'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="twintack_bundle_counter_bundle_size" 
                                   id="twintack_bundle_counter_bundle_size" 
                                   value="<?php echo esc_attr(get_option('twintack_bundle_counter_bundle_size', '3')); ?>" 
                                   min="1" max="100" class="small-text" />
                            <p class="description"><?php _e('Number of items needed for bundle discount (e.g., 3 for "Buy 3 Get 15% Off")', 'twintack-marketing'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="twintack_bundle_counter_discount_text"><?php _e('Discount Text', 'twintack-marketing'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="twintack_bundle_counter_discount_text" 
                                   id="twintack_bundle_counter_discount_text" 
                                   value="<?php echo esc_attr(get_option('twintack_bundle_counter_discount_text', '15% off')); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php _e('Text describing the discount (e.g., "15% off", "$10 off", "Free Shipping")', 'twintack-marketing'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="twintack_bundle_counter_bg_color"><?php _e('Background Color', 'twintack-marketing'); ?></label>
                        </th>
                        <td>
                            <input type="color" name="twintack_bundle_counter_bg_color" 
                                   id="twintack_bundle_counter_bg_color" 
                                   value="<?php echo esc_attr(get_option('twintack_bundle_counter_bg_color', '#1a1a1a')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="twintack_bundle_counter_text_color"><?php _e('Text Color', 'twintack-marketing'); ?></label>
                        </th>
                        <td>
                            <input type="color" name="twintack_bundle_counter_text_color" 
                                   id="twintack_bundle_counter_text_color" 
                                   value="<?php echo esc_attr(get_option('twintack_bundle_counter_text_color', '#ffffff')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="twintack_bundle_counter_progress_color"><?php _e('Progress Bar Color', 'twintack-marketing'); ?></label>
                        </th>
                        <td>
                            <input type="color" name="twintack_bundle_counter_progress_color" 
                                   id="twintack_bundle_counter_progress_color" 
                                   value="<?php echo esc_attr(get_option('twintack_bundle_counter_progress_color', '#ff0b00')); ?>" />
                        </td>
                    </tr>
                    </table>
                    
                    <?php submit_button(__('Save Settings', 'twintack-marketing'), 'primary', 'submit', true, array('style' => 'margin-top: 20px;')); ?>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Count eligible products in cart
     */
    private function count_bundle_items() {
        if (!function_exists('WC') || !WC()->cart) {
            return 0;
        }
        
        $category_id = get_option('twintack_bundle_counter_category', '');
        if (empty($category_id)) {
            return 0;
        }
        
        $count = 0;
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            
            // Check if product is in the selected category
            if (has_term($category_id, 'product_cat', $product_id)) {
                $count += $cart_item['quantity'];
            }
        }
        
        return $count;
    }
    
    /**
     * AJAX handler for getting current bundle count
     */
    public function ajax_get_bundle_count() {
        $count = $this->count_bundle_items();
        $bundle_size = intval(get_option('twintack_bundle_counter_bundle_size', '3'));
        
        wp_send_json_success(array(
            'count' => $count,
            'bundle_size' => $bundle_size,
            'progress' => min(100, ($count / $bundle_size) * 100),
            'qualified' => $count >= $bundle_size
        ));
    }
    
    private static $displayed = false;
    
    public function display_bundle_counter() {
        // Prevent duplicate display
        if (self::$displayed) {
            return;
        }
        
        if (!get_option('twintack_bundle_counter_enabled')) {
            return;
        }

        // Allow per-page suppression (used by Marketing Target Page template)
        $current_page_id = get_queried_object_id();
        if ($current_page_id && get_post_meta($current_page_id, '_twintack_target_hide_bundle_counter', true) === '1') {
            return;
        }

        // Don't show if WooCommerce is not active
        if (!function_exists('WC') || !WC()->cart) {
            return;
        }
        
        $category_id = get_option('twintack_bundle_counter_category', '');
        if (empty($category_id)) {
            return;
        }
        
        $bundle_size = intval(get_option('twintack_bundle_counter_bundle_size', '3'));
        $discount_text = get_option('twintack_bundle_counter_discount_text', '15% off');
        $bg_color = get_option('twintack_bundle_counter_bg_color', '#1a1a1a');
        $text_color = get_option('twintack_bundle_counter_text_color', '#ffffff');
        $progress_color = get_option('twintack_bundle_counter_progress_color', '#ff0b00');
        
        $current_count = $this->count_bundle_items();
        $progress_percent = min(100, ($current_count / $bundle_size) * 100);
        $is_qualified = $current_count >= $bundle_size;
        
        // Mark as displayed
        self::$displayed = true;
        
        // Check if announcement bar is active to calculate offset
        $announcement_active = get_option('twintack_announcement_enabled') && !empty(get_option('twintack_announcement_text'));
        $top_offset = 0;
        $header_offset = 0;
        $bundle_height = 75; // Bundle counter height (increased to accommodate progress bar)
        
        if ($announcement_active) {
            $top_offset = 48; // Height of announcement bar
            $header_offset = 48 + $bundle_height; // announcement + bundle counter
        } else {
            $header_offset = $bundle_height; // Just bundle counter
        }
        
        ?>
        <div id="twintack-bundle-counter" 
             class="twintack-bundle-counter<?php echo $is_qualified ? ' qualified' : ''; ?><?php echo $announcement_active ? ' has-announcement' : ''; ?>"
             style="background-color: <?php echo esc_attr($bg_color); ?>; color: <?php echo esc_attr($text_color); ?>; top: <?php echo $top_offset; ?>px;"
             data-bundle-size="<?php echo esc_attr($bundle_size); ?>"
             data-discount-text="<?php echo esc_attr($discount_text); ?>"
             data-bar-height="60">
            <div class="twintack-bundle-content">
                <div class="bundle-text">
                    <?php if ($is_qualified) : ?>
                        <span class="bundle-qualified">
                            🎉 <?php printf(__('Congrats! You qualify for %s on your bundle!', 'twintack-marketing'), esc_html($discount_text)); ?>
                        </span>
                    <?php elseif ($current_count > 0) : ?>
                        <span class="bundle-progress-text">
                            <?php 
                            $remaining = $bundle_size - $current_count;
                            printf(
                                _n(
                                    'Add %d more grip to get %s!',
                                    'Add %d more grips to get %s!',
                                    $remaining,
                                    'twintack-marketing'
                                ),
                                $remaining,
                                esc_html($discount_text)
                            );
                            ?>
                        </span>
                    <?php else : ?>
                        <span class="bundle-empty-text">
                            <?php printf(__('Buy %d grips and save %s!', 'twintack-marketing'), $bundle_size, esc_html($discount_text)); ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <?php if ($current_count > 0 && !$is_qualified) : ?>
                <div class="bundle-progress-bar">
                    <div class="progress-track">
                        <div class="progress-fill" 
                             style="width: <?php echo esc_attr($progress_percent); ?>%; background-color: <?php echo esc_attr($progress_color); ?>;">
                        </div>
                    </div>
                    <div class="progress-count">
                        <span class="current-count"><?php echo $current_count; ?></span> / 
                        <span class="target-count"><?php echo $bundle_size; ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <style>
            /* Push site header and other elements when bundle counter is present */
            <?php if ($announcement_active) : ?>
                /* Both announcement bar and bundle counter */
                body:not(.admin-bar) .site-header {
                    top: 123px !important; /* 48px announcement + 75px bundle counter */
                }
                body.admin-bar .site-header {
                    top: 155px !important; /* 32px admin bar + 48px announcement + 75px bundle */
                }
                
                /* Push fixed nav icons (account/cart) */
                body:not(.admin-bar) .fixed-nav-icons {
                    top: 143px !important; /* 48px announcement + 75px bundle + 20px padding */
                }
                body.admin-bar .fixed-nav-icons {
                    top: 175px !important; /* 32px admin bar + 48px announcement + 75px bundle + 20px padding */
                }
                
                /* Push main content */
                #page {
                    padding-top: 123px !important;
                }
                body.admin-bar #page {
                    padding-top: 155px !important;
                }
                
                body.admin-bar .twintack-bundle-counter {
                    top: 80px !important; /* 32px admin bar + 48px announcement */
                }
                
                @media screen and (max-width: 782px) {
                    body.admin-bar .site-header {
                        top: 169px !important; /* 46px admin bar + 48px announcement + 75px bundle */
                    }
                    body.admin-bar .fixed-nav-icons {
                        top: 189px !important; /* 46px admin bar + 48px announcement + 75px bundle + 20px padding */
                    }
                    body.admin-bar .twintack-bundle-counter {
                        top: 94px !important; /* 46px admin bar + 48px announcement */
                    }
                    body.admin-bar #page {
                        padding-top: 169px !important;
                    }
                }
            <?php else : ?>
                /* Only bundle counter, no announcement bar */
                body:not(.admin-bar) .site-header {
                    top: 75px !important;
                }
                body.admin-bar .site-header {
                    top: 107px !important; /* 32px admin bar + 75px bundle */
                }
                
                /* Push fixed nav icons */
                body:not(.admin-bar) .fixed-nav-icons {
                    top: 95px !important; /* 75px bundle + 20px padding */
                }
                body.admin-bar .fixed-nav-icons {
                    top: 127px !important; /* 32px admin bar + 75px bundle + 20px padding */
                }
                
                /* Push main content */
                #page {
                    padding-top: 75px !important;
                }
                body.admin-bar #page {
                    padding-top: 107px !important;
                }
                
                @media screen and (max-width: 782px) {
                    body.admin-bar .site-header {
                        top: 121px !important; /* 46px admin bar + 75px bundle */
                    }
                    body.admin-bar .fixed-nav-icons {
                        top: 141px !important; /* 46px admin bar + 75px bundle + 20px padding */
                    }
                    body.admin-bar #page {
                        padding-top: 121px !important;
                    }
                }
            <?php endif; ?>
        </style>
        <?php
    }
}

