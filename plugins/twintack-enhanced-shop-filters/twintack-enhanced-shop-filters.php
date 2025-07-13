<?php
/**
 * Plugin Name: TwinTack Enhanced Shop Filters
 * Description: Advanced filtering and sorting options for WooCommerce shop pages with admin controls
 * Version: 1.0.0
 * Author: TwinTack
 * Requires at least: 5.8
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

/**
 * Main Plugin Class
 */
class TwinTack_Enhanced_Shop_Filters {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
        $this->includes();
    }
    
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Shop filtering and sorting hooks
        add_filter('woocommerce_catalog_orderby_options', array($this, 'modify_sorting_options'));
        add_filter('woocommerce_default_catalog_orderby', array($this, 'set_default_sorting'));
        add_action('woocommerce_before_shop_loop', array($this, 'display_enhanced_filters'), 15);
        add_action('wp_ajax_filter_products', array($this, 'ajax_filter_products'));
        add_action('wp_ajax_nopriv_filter_products', array($this, 'ajax_filter_products'));
    }
    
    private function includes() {
        // Include additional files as needed
    }
    
    public function init() {
        // Plugin initialization
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Enhanced Shop Filters',
            'Shop Filters',
            'manage_woocommerce',
            'twintack-shop-filters',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('twintack_shop_filters', 'twintack_sorting_options');
        register_setting('twintack_shop_filters', 'twintack_default_sorting');
        register_setting('twintack_shop_filters', 'twintack_filter_display');
        register_setting('twintack_shop_filters', 'twintack_enabled_filters');
        register_setting('twintack_shop_filters', 'twintack_filter_style');
        register_setting('twintack_shop_filters', 'twintack_ajax_enabled');
        
        // Add settings sections
        add_settings_section(
            'twintack_sorting_section',
            'Sorting Options',
            array($this, 'sorting_section_callback'),
            'twintack_shop_filters'
        );
        
        add_settings_section(
            'twintack_filtering_section',
            'Filtering Options',
            array($this, 'filtering_section_callback'),
            'twintack_shop_filters'
        );
    }
    
    /**
     * Admin page content
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Enhanced Shop Filters</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('twintack_shop_filters');
                do_settings_sections('twintack_shop_filters');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Custom Sorting Options</th>
                        <td>
                            <?php
                            $sorting_options = get_option('twintack_sorting_options', array());
                            $default_options = array(
                                'menu_order' => 'Default sorting',
                                'popularity' => 'Sort by popularity',
                                'rating' => 'Sort by average rating',
                                'date' => 'Sort by latest',
                                'price' => 'Sort by price: low to high',
                                'price-desc' => 'Sort by price: high to low',
                                'color' => 'Sort by color',
                                'pattern' => 'Sort by pattern'
                            );
                            ?>
                            <fieldset>
                                <legend>Select which sorting options to display:</legend>
                                <?php foreach ($default_options as $key => $label): ?>
                                    <label>
                                        <input type="checkbox" name="twintack_sorting_options[<?php echo esc_attr($key); ?>]" 
                                               value="<?php echo esc_attr($label); ?>" 
                                               <?php checked(isset($sorting_options[$key])); ?>>
                                        <?php echo esc_html($label); ?>
                                    </label><br>
                                <?php endforeach; ?>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Default Sorting</th>
                        <td>
                            <select name="twintack_default_sorting">
                                <?php
                                $default_sorting = get_option('twintack_default_sorting', 'menu_order');
                                foreach ($default_options as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($default_sorting, $key); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Filter Display Style</th>
                        <td>
                            <?php $filter_style = get_option('twintack_filter_style', 'horizontal'); ?>
                            <fieldset>
                                <label>
                                    <input type="radio" name="twintack_filter_style" value="horizontal" <?php checked($filter_style, 'horizontal'); ?>>
                                    Horizontal Filter Bar
                                </label><br>
                                <label>
                                    <input type="radio" name="twintack_filter_style" value="sidebar" <?php checked($filter_style, 'sidebar'); ?>>
                                    Sidebar Filters
                                </label><br>
                                <label>
                                    <input type="radio" name="twintack_filter_style" value="modal" <?php checked($filter_style, 'modal'); ?>>
                                    Modal Filters (Current)
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Enabled Filters</th>
                        <td>
                            <?php
                            $enabled_filters = get_option('twintack_enabled_filters', array());
                            $available_filters = array(
                                'color' => 'Filter by Color',
                                'pattern' => 'Filter by Pattern',
                                'price' => 'Filter by Price Range',
                                'category' => 'Filter by Category',
                                'sport' => 'Filter by Sport',
                                'brand' => 'Filter by Brand'
                            );
                            ?>
                            <fieldset>
                                <legend>Select which filters to display:</legend>
                                <?php foreach ($available_filters as $key => $label): ?>
                                    <label>
                                        <input type="checkbox" name="twintack_enabled_filters[<?php echo esc_attr($key); ?>]" 
                                               value="1" 
                                               <?php checked(isset($enabled_filters[$key])); ?>>
                                        <?php echo esc_html($label); ?>
                                    </label><br>
                                <?php endforeach; ?>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">AJAX Filtering</th>
                        <td>
                            <label>
                                <input type="checkbox" name="twintack_ajax_enabled" value="1" 
                                       <?php checked(get_option('twintack_ajax_enabled', 1)); ?>>
                                Enable AJAX filtering (filter without page reload)
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <div class="postbox">
                <h3 class="hndle">Instructions</h3>
                <div class="inside">
                    <h4>Setting Up Product Attributes for Filtering:</h4>
                    <ol>
                        <li>Go to <strong>WooCommerce > Products > Attributes</strong></li>
                        <li>Create attributes like "Color", "Pattern", "Sport", etc.</li>
                        <li>Set <strong>"Enable archives?"</strong> to <strong>Yes</strong> for filterable attributes</li>
                        <li>Add terms to your attributes (e.g., Red, Blue, Green for Color)</li>
                        <li>Assign attributes to your products</li>
                    </ol>
                    
                    <h4>Custom Sorting Options:</h4>
                    <p>The plugin supports custom sorting by product attributes. Make sure your products have the corresponding attributes assigned.</p>
                    
                    <h4>Filter URLs:</h4>
                    <p>Filters work with URL parameters:</p>
                    <ul>
                        <li><code>?filter_pa_color=red</code> - Filter by color</li>
                        <li><code>?filter_pa_pattern=gradient</code> - Filter by pattern</li>
                        <li><code>?filter_pa_color=red&filter_pa_pattern=gradient</code> - Multiple filters</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Section callbacks
     */
    public function sorting_section_callback() {
        echo '<p>Configure the sorting options available to customers on the shop page.</p>';
    }
    
    public function filtering_section_callback() {
        echo '<p>Configure which filters to display and how they should appear.</p>';
    }
    
    /**
     * Modify WooCommerce sorting options
     */
    public function modify_sorting_options($options) {
        $custom_options = get_option('twintack_sorting_options', array());
        
        if (!empty($custom_options)) {
            // Start with empty array if we have custom options
            $new_options = array();
            
            // Add enabled custom options
            foreach ($custom_options as $key => $label) {
                $new_options[$key] = $label;
            }
            
            return $new_options;
        }
        
        return $options;
    }
    
    /**
     * Set default sorting option
     */
    public function set_default_sorting($default) {
        $custom_default = get_option('twintack_default_sorting', 'menu_order');
        return $custom_default ? $custom_default : $default;
    }
    
    /**
     * Display enhanced filters
     */
    public function display_enhanced_filters() {
        if (!is_shop() && !is_product_category()) {
            return;
        }
        
        $filter_style = get_option('twintack_filter_style', 'modal');
        $enabled_filters = get_option('twintack_enabled_filters', array());
        
        if (empty($enabled_filters)) {
            return;
        }
        
        // Get current filters from URL
        $current_filters = array();
        foreach ($_GET as $key => $value) {
            if (strpos($key, 'filter_') === 0) {
                $current_filters[$key] = sanitize_text_field($value);
            }
        }
        
        echo '<div class="twintack-enhanced-filters" data-style="' . esc_attr($filter_style) . '">';
        
        if ($filter_style === 'horizontal') {
            $this->display_horizontal_filters($enabled_filters, $current_filters);
        } elseif ($filter_style === 'sidebar') {
            $this->display_sidebar_filters($enabled_filters, $current_filters);
        } else {
            $this->display_modal_filters($enabled_filters, $current_filters);
        }
        
        echo '</div>';
    }
    
    /**
     * Display horizontal filters
     */
    private function display_horizontal_filters($enabled_filters, $current_filters) {
        echo '<div class="horizontal-filters">';
        
        foreach ($enabled_filters as $filter_key => $enabled) {
            if (!$enabled) continue;
            
            $this->render_filter_control($filter_key, $current_filters);
        }
        
        echo '</div>';
    }
    
    /**
     * Display sidebar filters
     */
    private function display_sidebar_filters($enabled_filters, $current_filters) {
        echo '<div class="sidebar-filters">';
        echo '<h3>Filter Products</h3>';
        
        foreach ($enabled_filters as $filter_key => $enabled) {
            if (!$enabled) continue;
            
            echo '<div class="filter-group">';
            $this->render_filter_control($filter_key, $current_filters);
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Display modal filters (current system)
     */
    private function display_modal_filters($enabled_filters, $current_filters) {
        // This integrates with the existing modal system
        echo '<button id="filter-button" class="filter-button">';
        echo 'Filter Products';
        echo '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">';
        echo '<path d="M3 4.5A.5.5 0 0 1 3.5 4h9a.5.5 0 0 1 0 1h-9A.5.5 0 0 1 3 4.5zM5 7.5A.5.5 0 0 1 5.5 7h5a.5.5 0 0 1 0 1h-5A.5.5 0 0 1 5 7.5zM7 10.5A.5.5 0 0 1 7.5 10h1a.5.5 0 0 1 0 1h-1A.5.5 0 0 1 7 10.5z"/>';
        echo '</svg>';
        echo '</button>';
        
        echo '<div id="filter-modal" class="filter-modal">';
        echo '<div id="filter-modal-backdrop"></div>';
        echo '<div class="filter-modal-container">';
        echo '<div class="filter-modal-header">';
        echo '<h3>Filter Products</h3>';
        echo '<button id="filter-modal-close">&times;</button>';
        echo '</div>';
        echo '<div class="filter-modal-content">';
        
        foreach ($enabled_filters as $filter_key => $enabled) {
            if (!$enabled) continue;
            
            echo '<div class="filter-group">';
            $this->render_filter_control($filter_key, $current_filters);
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render individual filter control
     */
    private function render_filter_control($filter_key, $current_filters) {
        switch ($filter_key) {
            case 'color':
                $this->render_attribute_filter('pa_color', 'Color', $current_filters);
                break;
            case 'pattern':
                $this->render_attribute_filter('pa_pattern', 'Pattern', $current_filters);
                break;
            case 'sport':
                $this->render_attribute_filter('pa_sport', 'Sport', $current_filters);
                break;
            case 'price':
                $this->render_price_filter($current_filters);
                break;
            case 'category':
                $this->render_category_filter($current_filters);
                break;
            case 'brand':
                $this->render_attribute_filter('pa_brand', 'Brand', $current_filters);
                break;
        }
    }
    
    /**
     * Render attribute filter
     */
    private function render_attribute_filter($taxonomy, $label, $current_filters) {
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
        ));
        
        if (empty($terms) || is_wp_error($terms)) {
            return;
        }
        
        $filter_key = 'filter_' . $taxonomy;
        $current_value = isset($current_filters[$filter_key]) ? $current_filters[$filter_key] : '';
        
        echo '<div class="filter-control">';
        echo '<label for="' . esc_attr($filter_key) . '">' . esc_html($label) . '</label>';
        echo '<select name="' . esc_attr($filter_key) . '" id="' . esc_attr($filter_key) . '" class="filter-select">';
        echo '<option value="">All ' . esc_html($label) . '</option>';
        
        foreach ($terms as $term) {
            $selected = ($current_value === $term->slug) ? 'selected' : '';
            echo '<option value="' . esc_attr($term->slug) . '" ' . $selected . '>';
            echo esc_html($term->name);
            echo '</option>';
        }
        
        echo '</select>';
        echo '</div>';
    }
    
    /**
     * Render price filter
     */
    private function render_price_filter($current_filters) {
        $min_price = isset($current_filters['min_price']) ? $current_filters['min_price'] : '';
        $max_price = isset($current_filters['max_price']) ? $current_filters['max_price'] : '';
        
        echo '<div class="filter-control price-filter">';
        echo '<label>Price Range</label>';
        echo '<div class="price-inputs">';
        echo '<input type="number" name="min_price" placeholder="Min" value="' . esc_attr($min_price) . '" class="price-input">';
        echo '<span>-</span>';
        echo '<input type="number" name="max_price" placeholder="Max" value="' . esc_attr($max_price) . '" class="price-input">';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render category filter
     */
    private function render_category_filter($current_filters) {
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
        ));
        
        if (empty($categories) || is_wp_error($categories)) {
            return;
        }
        
        $current_value = isset($current_filters['product_cat']) ? $current_filters['product_cat'] : '';
        
        echo '<div class="filter-control">';
        echo '<label for="product_cat">Category</label>';
        echo '<select name="product_cat" id="product_cat" class="filter-select">';
        echo '<option value="">All Categories</option>';
        
        foreach ($categories as $category) {
            $selected = ($current_value === $category->slug) ? 'selected' : '';
            echo '<option value="' . esc_attr($category->slug) . '" ' . $selected . '>';
            echo esc_html($category->name);
            echo '</option>';
        }
        
        echo '</select>';
        echo '</div>';
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        if (!is_shop() && !is_product_category()) {
            return;
        }
        
        wp_enqueue_script(
            'twintack-enhanced-filters',
            plugin_dir_url(__FILE__) . 'assets/js/enhanced-filters.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        wp_localize_script('twintack-enhanced-filters', 'twintackFilters', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('twintack_filter_nonce'),
            'ajax_enabled' => get_option('twintack_ajax_enabled', 1)
        ));
        
        wp_enqueue_style(
            'twintack-enhanced-filters',
            plugin_dir_url(__FILE__) . 'assets/css/enhanced-filters.css',
            array(),
            '1.0.0'
        );
    }
    
    /**
     * AJAX filter products
     */
    public function ajax_filter_products() {
        check_ajax_referer('twintack_filter_nonce', 'nonce');
        
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
        
        // Build WooCommerce query args
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_visibility',
                    'value' => array('catalog', 'visible'),
                    'compare' => 'IN'
                )
            )
        );
        
        // Add tax query for filters
        $tax_query = array();
        
        foreach ($filters as $key => $value) {
            if (empty($value)) continue;
            
            if (strpos($key, 'filter_pa_') === 0) {
                $taxonomy = str_replace('filter_', '', $key);
                $tax_query[] = array(
                    'taxonomy' => $taxonomy,
                    'field' => 'slug',
                    'terms' => $value
                );
            } elseif ($key === 'product_cat') {
                $tax_query[] = array(
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => $value
                );
            }
        }
        
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }
        
        // Add price query
        if (isset($filters['min_price']) || isset($filters['max_price'])) {
            $meta_query = array();
            
            if (isset($filters['min_price']) && !empty($filters['min_price'])) {
                $meta_query[] = array(
                    'key' => '_price',
                    'value' => floatval($filters['min_price']),
                    'compare' => '>='
                );
            }
            
            if (isset($filters['max_price']) && !empty($filters['max_price'])) {
                $meta_query[] = array(
                    'key' => '_price',
                    'value' => floatval($filters['max_price']),
                    'compare' => '<='
                );
            }
            
            if (!empty($meta_query)) {
                $args['meta_query'] = array_merge($args['meta_query'], $meta_query);
            }
        }
        
        $query = new WP_Query($args);
        
        ob_start();
        
        if ($query->have_posts()) {
            woocommerce_product_loop_start();
            
            while ($query->have_posts()) {
                $query->the_post();
                wc_get_template_part('content', 'product');
            }
            
            woocommerce_product_loop_end();
        } else {
            echo '<p>No products found matching your criteria.</p>';
        }
        
        wp_reset_postdata();
        
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html,
            'count' => $query->found_posts
        ));
    }
}

// Initialize the plugin
TwinTack_Enhanced_Shop_Filters::get_instance(); 