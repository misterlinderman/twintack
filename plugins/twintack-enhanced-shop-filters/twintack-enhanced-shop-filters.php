<?php
/**
 * Plugin Name: TwinTack Enhanced Shop Filters
 * Description: Enhanced filtering and sorting for TwinTack WooCommerce shop with color swatches, variation support, and admin controls
 * Version: 1.0.2
 * Author: TwinTack Development Team
 * Requires at least: 5.8
 * Tested up to: 6.7
 * WC requires at least: 7.5
 * WC tested up to: 9.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
if (!defined('TWINTACK_ESF_VERSION')) {
    define('TWINTACK_ESF_VERSION', '1.0.2');
}
define('TWINTACK_ESF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TWINTACK_ESF_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', 'twintack_esf_woocommerce_missing_notice');
    return;
}

/**
 * Display notice if WooCommerce is not active
 */
function twintack_esf_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php _e('TwinTack Enhanced Shop Filters requires WooCommerce to be installed and activated.', 'twintack-enhanced-shop-filters'); ?></p>
    </div>
    <?php
}

// Declare WooCommerce HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Main Plugin Class
 */
class TwinTack_Enhanced_Shop_Filters {
    private static $instance = null;
    private $variation_swatches_active = false;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        if (WP_DEBUG) {
            error_log('TwinTack Enhanced Shop Filters: Plugin initializing...');
        }
        
        $this->check_dependencies();
        $this->ensure_default_options();
        $this->init_hooks();
        
        if (WP_DEBUG) {
            error_log('TwinTack Enhanced Shop Filters: Plugin initialized successfully');
        }
    }
    
    /**
     * Ensure default options are set
     */
    private function ensure_default_options() {
        // Set default options if they don't exist
        if (!get_option('twintack_enabled_filters')) {
            update_option('twintack_enabled_filters', array('color', 'pattern', 'sport', 'brand', 'category', 'price'));
        }
        
        if (!get_option('twintack_custom_sort_options')) {
            update_option('twintack_custom_sort_options', array('menu_order', 'popularity', 'rating', 'date', 'price', 'price-desc'));
        }
        
        if (!get_option('twintack_default_sort_option')) {
            update_option('twintack_default_sort_option', 'menu_order');
        }
        
        if (!get_option('twintack_filter_display_style')) {
            update_option('twintack_filter_display_style', 'modal');
        }
        
        if (!get_option('twintack_enable_ajax')) {
            update_option('twintack_enable_ajax', false);
        }
        
        if (get_option('twintack_enable_color_swatches') === false) {
            update_option('twintack_enable_color_swatches', true);
        }
        
        if (!get_option('twintack_hidden_categories')) {
            update_option('twintack_hidden_categories', array('fees', 'uncategorized'));
        }
    }
    
    /**
     * Check if required dependencies are active
     */
    private function check_dependencies() {
        // Check if Variation Swatches plugin is active using multiple methods
        $this->variation_swatches_active = $this->is_variation_swatches_active();
    }
    
    /**
     * Comprehensive check for Variation Swatches plugin
     */
    private function is_variation_swatches_active() {
        if (WP_DEBUG) {
            error_log('TwinTack Enhanced Shop Filters: Checking variation swatches plugin status');
        }
        
        // Method 1: Check for plugin constant
        if (defined('WOO_VARIATION_SWATCHES_PLUGIN_VERSION')) {
            if (WP_DEBUG) {
                error_log('TwinTack Enhanced Shop Filters: Variation swatches found via constant');
            }
            return true;
        }
        
        // Method 2: Check for main class
        if (class_exists('Woo_Variation_Swatches')) {
            if (WP_DEBUG) {
                error_log('TwinTack Enhanced Shop Filters: Variation swatches found via main class');
            }
            return true;
        }
        
        // Method 3: Check for global function
        if (function_exists('woo_variation_swatches')) {
            if (WP_DEBUG) {
                error_log('TwinTack Enhanced Shop Filters: Variation swatches found via global function');
            }
            return true;
        }
        
        // Method 4: Check if plugin is in active plugins list
        $active_plugins = get_option('active_plugins', array());
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'woo-variation-swatches') !== false) {
                if (WP_DEBUG) {
                    error_log('TwinTack Enhanced Shop Filters: Variation swatches found in active plugins list');
                }
                return true;
            }
        }
        
        // Method 5: Check for pro version
        if (defined('WOO_VARIATION_SWATCHES_PRO_PLUGIN_VERSION') || function_exists('woo_variation_swatches_pro')) {
            if (WP_DEBUG) {
                error_log('TwinTack Enhanced Shop Filters: Variation swatches pro version found');
            }
            return true;
        }
        
        if (WP_DEBUG) {
            error_log('TwinTack Enhanced Shop Filters: Variation swatches plugin not found');
        }
        
        return false;
    }
    
    private function init_hooks() {
        if (WP_DEBUG) {
            error_log('TwinTack Enhanced Shop Filters: Initializing hooks');
        }
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('woocommerce_before_shop_loop', array($this, 'display_filter_modal'), 15);
        add_action('woocommerce_no_products_found', array($this, 'display_filter_modal'), 5);
        add_action('woocommerce_shop_loop_header', array($this, 'display_active_filters'), 25);
        add_filter('woocommerce_catalog_orderby', array($this, 'custom_catalog_orderby'));
        add_filter('woocommerce_default_catalog_orderby', array($this, 'custom_default_catalog_orderby'));
        
        if (WP_DEBUG) {
            error_log('TwinTack Enhanced Shop Filters: Hooks initialized successfully');
        }
    }
    
    public function enqueue_scripts() {
        if (is_shop() || is_product_category() || is_product_tag()) {
            if (WP_DEBUG) {
                error_log('TwinTack Enhanced Shop Filters: Enqueueing scripts on shop page');
            }
            
            wp_enqueue_style('twintack-enhanced-filters', TWINTACK_ESF_PLUGIN_URL . 'assets/css/enhanced-filters.css', array(), TWINTACK_ESF_VERSION);
            wp_enqueue_script('twintack-enhanced-filters', TWINTACK_ESF_PLUGIN_URL . 'assets/js/enhanced-filters.js', array('jquery'), TWINTACK_ESF_VERSION, true);
            
            wp_localize_script('twintack-enhanced-filters', 'twintack_filters', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('twintack_filters_nonce'),
                'shop_url' => wc_get_page_permalink('shop'),
                'variation_swatches_active' => $this->variation_swatches_active
            ));
            
            if (WP_DEBUG) {
                error_log('TwinTack Enhanced Shop Filters: Scripts enqueued successfully');
            }
        } else {
            if (WP_DEBUG) {
                error_log('TwinTack Enhanced Shop Filters: Not on shop page, skipping script enqueue');
            }
        }
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Shop Filters',
            'Shop Filters',
            'manage_woocommerce',
            'twintack-shop-filters',
            array($this, 'admin_page')
        );
    }
    
    public function admin_init() {
        register_setting('twintack_shop_filters', 'twintack_enabled_filters', array(
            'default' => array('color', 'pattern', 'sport', 'brand', 'category', 'price'),
            'sanitize_callback' => array($this, 'sanitize_enabled_filters')
        ));
        
        register_setting('twintack_shop_filters', 'twintack_custom_sort_options', array(
            'default' => array('menu_order', 'popularity', 'rating', 'date', 'price', 'price-desc'),
            'sanitize_callback' => array($this, 'sanitize_sort_options')
        ));
        
        register_setting('twintack_shop_filters', 'twintack_default_sort_option', array(
            'default' => 'menu_order',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('twintack_shop_filters', 'twintack_filter_display_style', array(
            'default' => 'modal',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('twintack_shop_filters', 'twintack_enable_ajax', array(
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ));
        
        register_setting('twintack_shop_filters', 'twintack_enable_color_swatches', array(
            'default' => true,
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));
        
        register_setting('twintack_shop_filters', 'twintack_hidden_categories', array(
            'default' => array('fees', 'uncategorized'),
            'sanitize_callback' => array($this, 'sanitize_hidden_categories')
        ));
        
        // Settings sections
        add_settings_section('twintack_sorting_section', 'Sorting Options', array($this, 'sorting_section_callback'), 'twintack_shop_filters');
        add_settings_section('twintack_filtering_section', 'Filtering Options', array($this, 'filtering_section_callback'), 'twintack_shop_filters');
        
        // Settings fields
        add_settings_field('twintack_enabled_filters', 'Enabled Filters', array($this, 'enabled_filters_callback'), 'twintack_shop_filters', 'twintack_filtering_section');
        add_settings_field('twintack_filter_display_style', 'Display Style', array($this, 'display_style_callback'), 'twintack_shop_filters', 'twintack_filtering_section');
        add_settings_field('twintack_hidden_categories', 'Hidden Categories', array($this, 'hidden_categories_callback'), 'twintack_shop_filters', 'twintack_filtering_section');
        add_settings_field('twintack_enable_color_swatches', 'Enable Color Swatches', array($this, 'enable_color_swatches_callback'), 'twintack_shop_filters', 'twintack_filtering_section');
        add_settings_field('twintack_enable_ajax', 'Enable AJAX', array($this, 'enable_ajax_callback'), 'twintack_shop_filters', 'twintack_filtering_section');
        add_settings_field('twintack_custom_sort_options', 'Available Sort Options', array($this, 'sort_options_callback'), 'twintack_shop_filters', 'twintack_sorting_section');
        add_settings_field('twintack_default_sort_option', 'Default Sort Option', array($this, 'default_sort_callback'), 'twintack_shop_filters', 'twintack_sorting_section');
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>TwinTack Enhanced Shop Filters</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('twintack_shop_filters');
                do_settings_sections('twintack_shop_filters');
                submit_button();
                ?>
            </form>
            
            <div class="postbox">
                <h3 class="hndle">Plugin Status</h3>
                <div class="inside">
                    <h4>Dependencies:</h4>
                    <ul>
                        <li>✅ WooCommerce: Active</li>
                        <li><?php echo $this->variation_swatches_active ? '✅' : '❌'; ?> Variation Swatches for WooCommerce: <?php echo $this->variation_swatches_active ? 'Active' : 'Inactive'; ?></li>
                    </ul>
                    
                    <h4>Detection Details:</h4>
                    <ul>
                        <li>Plugin constant (WOO_VARIATION_SWATCHES_PLUGIN_VERSION): <?php echo defined('WOO_VARIATION_SWATCHES_PLUGIN_VERSION') ? '✅ Found' : '❌ Not found'; ?></li>
                        <li>Main class (Woo_Variation_Swatches): <?php echo class_exists('Woo_Variation_Swatches') ? '✅ Found' : '❌ Not found'; ?></li>
                        <li>Global function (woo_variation_swatches): <?php echo function_exists('woo_variation_swatches') ? '✅ Found' : '❌ Not found'; ?></li>
                        <li>Pro version: <?php echo (defined('WOO_VARIATION_SWATCHES_PRO_PLUGIN_VERSION') || function_exists('woo_variation_swatches_pro')) ? '✅ Found' : '❌ Not found'; ?></li>
                    </ul>
                    
                    <?php if (!$this->variation_swatches_active): ?>
                    <p><strong>Note:</strong> For enhanced color swatches with images and gradients, install the <a href="https://wordpress.org/plugins/woo-variation-swatches/" target="_blank">Variation Swatches for WooCommerce</a> plugin.</p>
                    <?php else: ?>
                    <p><strong>Great!</strong> Variation swatches integration is active. The plugin will automatically use your configured color swatches, gradients, and images.</p>
                    <?php endif; ?>
                </div>
            </div>
            
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
                        <?php if ($this->variation_swatches_active): ?>
                        <li>Configure color swatches and images in <strong>Products > Attributes > Color</strong> for visual filtering</li>
                        <?php endif; ?>
                    </ol>
                    
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
    
    // Settings section callbacks
    public function sorting_section_callback() {
        echo '<p>Configure the sorting options available to customers.</p>';
    }
    
    public function filtering_section_callback() {
        echo '<p>Configure the filtering options and display style.</p>';
    }
    
    // Settings field callbacks
    public function enabled_filters_callback() {
        $enabled_filters = get_option('twintack_enabled_filters', array('color', 'pattern', 'sport', 'brand', 'category', 'price'));
        $available_filters = array(
            'color' => 'Color Filter',
            'pattern' => 'Pattern Filter',
            'sport' => 'Sport Filter',
            'brand' => 'Brand Filter',
            'category' => 'Category Filter',
            'price' => 'Price Range Filter'
        );
        
        echo '<fieldset>';
        foreach ($available_filters as $key => $label) {
            $checked = in_array($key, $enabled_filters) ? 'checked' : '';
            echo '<label><input type="checkbox" name="twintack_enabled_filters[]" value="' . $key . '" ' . $checked . '> ' . $label . '</label><br>';
        }
        echo '</fieldset>';
    }
    
    public function display_style_callback() {
        $style = get_option('twintack_filter_display_style', 'modal');
        echo '<select name="twintack_filter_display_style">';
        echo '<option value="modal" ' . selected($style, 'modal', false) . '>Modal (Default)</option>';
        echo '<option value="sidebar" ' . selected($style, 'sidebar', false) . '>Sidebar</option>';
        echo '<option value="horizontal" ' . selected($style, 'horizontal', false) . '>Horizontal Bar</option>';
        echo '</select>';
    }
    
    public function enable_color_swatches_callback() {
        $enable_swatches = get_option('twintack_enable_color_swatches', true);
        echo '<input type="checkbox" name="twintack_enable_color_swatches" value="1" ' . checked($enable_swatches, true, false) . '> Enable visual color swatches for color attribute filters';
        echo '<p class="description">When enabled, color filters will display visual color swatches in addition to the dropdown. When disabled, only the dropdown will be shown.</p>';
    }
    
    public function hidden_categories_callback() {
        $hidden_categories = get_option('twintack_hidden_categories', array('fees', 'uncategorized'));
        
        // Get all product categories
        $all_categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ));
        
        if (empty($all_categories) || is_wp_error($all_categories)) {
            echo '<p>No product categories found.</p>';
            return;
        }
        
        echo '<fieldset>';
        echo '<p class="description">Select categories to hide from shop filters. These categories will still be functional for custom grip orders and fees, but won\'t appear in the customer-facing filter dropdown.</p>';
        
        foreach ($all_categories as $category) {
            $checked = in_array($category->slug, $hidden_categories) ? 'checked' : '';
            echo '<label><input type="checkbox" name="twintack_hidden_categories[]" value="' . esc_attr($category->slug) . '" ' . $checked . '> ' . esc_html($category->name) . ' (' . $category->slug . ')</label><br>';
        }
        echo '</fieldset>';
    }
    
    public function enable_ajax_callback() {
        $enable_ajax = get_option('twintack_enable_ajax', false);
        echo '<input type="checkbox" name="twintack_enable_ajax" value="1" ' . checked($enable_ajax, true, false) . '> Enable AJAX filtering (no page reload)';
    }
    
    public function sort_options_callback() {
        $sort_options = get_option('twintack_custom_sort_options', array('menu_order', 'popularity', 'rating', 'date', 'price', 'price-desc'));
        $available_sorts = array(
            'menu_order' => 'Default sorting',
            'popularity' => 'Sort by popularity',
            'rating' => 'Sort by average rating',
            'date' => 'Sort by latest',
            'price' => 'Sort by price: low to high',
            'price-desc' => 'Sort by price: high to low'
        );
        
        echo '<fieldset>';
        foreach ($available_sorts as $key => $label) {
            $checked = in_array($key, $sort_options) ? 'checked' : '';
            echo '<label><input type="checkbox" name="twintack_custom_sort_options[]" value="' . $key . '" ' . $checked . '> ' . $label . '</label><br>';
        }
        echo '</fieldset>';
    }
    
    public function default_sort_callback() {
        $default_sort = get_option('twintack_default_sort_option', 'menu_order');
        $available_sorts = array(
            'menu_order' => 'Default sorting',
            'popularity' => 'Sort by popularity',
            'rating' => 'Sort by average rating',
            'date' => 'Sort by latest',
            'price' => 'Sort by price: low to high',
            'price-desc' => 'Sort by price: high to low'
        );
        
        echo '<select name="twintack_default_sort_option">';
        foreach ($available_sorts as $key => $label) {
            echo '<option value="' . $key . '" ' . selected($default_sort, $key, false) . '>' . $label . '</option>';
        }
        echo '</select>';
    }
    
    // Sanitization callbacks
    public function sanitize_enabled_filters($input) {
        $valid_filters = array('color', 'pattern', 'sport', 'brand', 'category', 'price');
        return is_array($input) ? array_intersect($input, $valid_filters) : array();
    }
    
    public function sanitize_sort_options($input) {
        $valid_sorts = array('menu_order', 'popularity', 'rating', 'date', 'price', 'price-desc');
        return is_array($input) ? array_intersect($input, $valid_sorts) : array();
    }

    public function sanitize_checkbox($input) {
        return (bool) $input;
    }
    
    public function sanitize_hidden_categories($input) {
        if (!is_array($input)) {
            return array();
        }
        
        // Sanitize each category slug
        $sanitized = array();
        foreach ($input as $category_slug) {
            $sanitized[] = sanitize_title($category_slug);
        }
        
        return array_filter($sanitized); // Remove empty values
    }
    
    /**
     * Display filter modal
     */
    public function display_filter_modal() {
        // Prevent duplicate display
        static $filter_displayed = false;
        if ($filter_displayed) {
            if (WP_DEBUG) {
                error_log('TwinTack Enhanced Shop Filters: Duplicate display prevented');
            }
            return;
        }
        $filter_displayed = true;
        
        if (WP_DEBUG) {
            error_log('TwinTack Enhanced Shop Filters: display_filter_modal called');
        }
        
        // Only show on shop pages
        if (!is_shop() && !is_product_category() && !is_product_tag()) {
            if (WP_DEBUG) {
                error_log('TwinTack Enhanced Shop Filters: Not on shop page, returning');
            }
            return;
        }
        
        $enabled_filters = get_option('twintack_enabled_filters', array('color', 'pattern', 'sport', 'brand', 'category', 'price'));
        $display_style = get_option('twintack_filter_display_style', 'modal');
        
        // Force default values if empty
        if (empty($enabled_filters)) {
            $enabled_filters = array('color', 'pattern', 'sport', 'brand', 'category', 'price');
        }
        
        if (WP_DEBUG) {
            error_log('TwinTack Enhanced Shop Filters: Enabled filters: ' . print_r($enabled_filters, true));
            error_log('TwinTack Enhanced Shop Filters: Display style: ' . $display_style);
        }
        
        // Get current filters from URL
        $current_filters = array();
        foreach ($enabled_filters as $filter) {
            $value = isset($_GET["filter_pa_{$filter}"]) ? sanitize_text_field($_GET["filter_pa_{$filter}"]) : '';
            if ($value) {
                $current_filters[$filter] = $value;
            }
        }
        
        // Check for category and price filters
        if (isset($_GET['product_cat'])) {
            $current_filters['category'] = sanitize_text_field($_GET['product_cat']);
        }
        if (isset($_GET['min_price']) || isset($_GET['max_price'])) {
            $current_filters['price'] = array(
                'min' => isset($_GET['min_price']) ? intval($_GET['min_price']) : '',
                'max' => isset($_GET['max_price']) ? intval($_GET['max_price']) : ''
            );
        }
        
        if (WP_DEBUG) {
            error_log('TwinTack Enhanced Shop Filters: Current filters: ' . print_r($current_filters, true));
        }
        
        // Modal display
        if ($display_style === 'modal' || empty($display_style)) {
            if (WP_DEBUG) {
                error_log('TwinTack Enhanced Shop Filters: Displaying modal style filters');
            }
            
            // Modal display: Button is completely separate from any containers
            echo '<button class="filter-button twintack-modal-trigger" onclick="document.getElementById(\'twintack-filter-modal\').style.display=\'block\'">Filter Products</button>';
            
            if (WP_DEBUG) {
                error_log('TwinTack Enhanced Shop Filters: Filter button HTML generated');
            }
            
            // Modal container (completely separate and hidden by default)
            echo '<div id="twintack-filter-modal" class="filter-modal" style="display: none;">';
            echo '<div class="filter-modal-content">';
            echo '<div class="filter-modal-header">';
            echo '<h3>Filter Products</h3>';
            echo '<button id="filter-modal-close" onclick="document.getElementById(\'twintack-filter-modal\').style.display=\'none\'">&times;</button>';
            echo '</div>';
            echo '<form class="filter-form" method="get" action="' . esc_url(wc_get_page_permalink('shop')) . '">';
            
            $this->render_all_filters($enabled_filters, $current_filters);
            
            echo '<div class="filter-buttons">';
            echo '<button type="submit" class="apply-filters-btn">Apply Filters</button>';
            echo '<button type="button" class="clear-filters-btn">Clear Filters</button>';
            echo '</div>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
        } else {
            // Horizontal/Sidebar display: Show filters directly
            if (WP_DEBUG) {
                error_log('TwinTack Enhanced Shop Filters: Displaying ' . $display_style . ' style filters');
            }
            
            echo '<div class="twintack-enhanced-filters filter-' . esc_attr($display_style) . '">';
            echo '<form class="filter-form" method="get" action="' . esc_url(wc_get_page_permalink('shop')) . '">';
            
            $this->render_all_filters($enabled_filters, $current_filters);
            
            echo '<div class="filter-buttons">';
            echo '<button type="submit" class="apply-filters-btn">Apply Filters</button>';
            echo '<button type="button" class="clear-filters-btn">Clear Filters</button>';
            echo '</div>';
            echo '</form>';
            echo '</div>';
        }
        
        if (WP_DEBUG) {
            error_log('TwinTack Enhanced Shop Filters: Filter display completed');
        }
    }
    
    /**
     * Render all enabled filters
     */
    private function render_all_filters($enabled_filters, $current_filters) {
        // Color filter
        if (in_array('color', $enabled_filters)) {
            $this->render_color_filter($current_filters);
        }
        
        // Pattern filter
        if (in_array('pattern', $enabled_filters)) {
            $this->render_attribute_filter('pattern', 'Pattern', $current_filters);
        }
        
        // Sport filter
        if (in_array('sport', $enabled_filters)) {
            $this->render_attribute_filter('sport', 'Sport', $current_filters);
        }
        
        // Brand filter
        if (in_array('brand', $enabled_filters)) {
            $this->render_attribute_filter('brand', 'Brand', $current_filters);
        }
        
        // Category filter
        if (in_array('category', $enabled_filters)) {
            $this->render_category_filter($current_filters);
        }
        
        // Price filter
        if (in_array('price', $enabled_filters)) {
            $this->render_price_filter($current_filters);
        }
    }
    
    /**
     * Display active filters with clear options
     */
    public function display_active_filters() {
        $current_filters = $this->get_current_filters();
        
        if (empty($current_filters)) {
            return;
        }
        
        echo '<div class="twintack-active-filters">';
        echo '<div class="active-filters-header">';
        echo '<span class="active-filters-label">Active Filters:</span>';
        echo '<a href="' . esc_url(wc_get_page_permalink('shop')) . '" class="clear-all-filters">Clear All Filters</a>';
        echo '</div>';
        
        echo '<div class="active-filters-list">';
        
        foreach ($current_filters as $filter_key => $filter_value) {
            if (empty($filter_value)) {
                continue;
            }
            
            // Create URL without this specific filter
            $clear_url = $this->get_clear_filter_url($filter_key);
            
            // Get filter label and value
            $filter_label = $this->get_filter_label($filter_key, $filter_value);
            
            echo '<div class="active-filter-item">';
            echo '<span class="filter-label">' . esc_html($filter_label) . '</span>';
            echo '<a href="' . esc_url($clear_url) . '" class="remove-filter" title="Remove this filter">&times;</a>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Get URL with specific filter removed
     */
    private function get_clear_filter_url($filter_to_remove) {
        $current_url = add_query_arg(array());
        $parsed_url = parse_url($current_url);
        
        if (isset($parsed_url['query'])) {
            parse_str($parsed_url['query'], $query_params);
            unset($query_params[$filter_to_remove]);
            
            $base_url = strtok($current_url, '?');
            if (!empty($query_params)) {
                return $base_url . '?' . http_build_query($query_params);
            } else {
                return $base_url;
            }
        }
        
        return $current_url;
    }
    
    /**
     * Get human-readable filter label
     */
    private function get_filter_label($filter_key, $filter_value) {
        // Handle attribute filters
        if (strpos($filter_key, 'filter_pa_') === 0) {
            $attribute_name = str_replace('filter_pa_', '', $filter_key);
            $attribute_label = wc_attribute_label('pa_' . $attribute_name);
            
            // Get term name
            $term = get_term_by('slug', $filter_value, 'pa_' . $attribute_name);
            $term_name = $term ? $term->name : $filter_value;
            
            return $attribute_label . ': ' . $term_name;
        }
        
        // Handle category filter
        if ($filter_key === 'product_cat') {
            $category = get_term_by('slug', $filter_value, 'product_cat');
            $category_name = $category ? $category->name : $filter_value;
            return 'Category: ' . $category_name;
        }
        
        // Handle price filters
        if ($filter_key === 'min_price') {
            return 'Min Price: $' . $filter_value;
        }
        
        if ($filter_key === 'max_price') {
            return 'Max Price: $' . $filter_value;
        }
        
        return ucfirst(str_replace('_', ' ', $filter_key)) . ': ' . $filter_value;
    }
    
    /**
     * Get current filters from URL
     */
    private function get_current_filters() {
        $filters = array();
        
        // Get all attribute taxonomies
        $attributes = wc_get_attribute_taxonomies();
        
        foreach ($attributes as $attribute) {
            $filter_key = 'filter_pa_' . $attribute->attribute_name;
            if (isset($_GET[$filter_key])) {
                $filters[$filter_key] = $_GET[$filter_key];
            }
        }
        
        // Get other filters
        if (isset($_GET['product_cat'])) {
            $filters['product_cat'] = $_GET['product_cat'];
        }
        
        if (isset($_GET['min_price'])) {
            $filters['min_price'] = $_GET['min_price'];
        }
        
        if (isset($_GET['max_price'])) {
            $filters['max_price'] = $_GET['max_price'];
        }
        
        return $filters;
    }
    
    /**
     * Render color filter with variation swatches integration
     */
    private function render_color_filter($current_filters) {
        $terms = get_terms(array(
            'taxonomy' => 'pa_color',
            'hide_empty' => true,
        ));
        
        if (empty($terms) || is_wp_error($terms)) {
            return;
        }
        
        $current_value = isset($current_filters['filter_pa_color']) ? $current_filters['filter_pa_color'] : '';
        
        // Check if swatches are enabled
        $enable_swatches = get_option('twintack_enable_color_swatches', true);
        $swatch_class = $enable_swatches ? 'has-swatches' : '';
        
        echo '<div class="filter-control color-filter ' . $swatch_class . '">';
        echo '<label for="filter_pa_color">Color</label>';
        echo '<select name="filter_pa_color" id="filter_pa_color" class="filter-select">';
        echo '<option value="">All Colors</option>';
        
        foreach ($terms as $term) {
            $selected = ($current_value === $term->slug) ? 'selected' : '';
            echo '<option value="' . esc_attr($term->slug) . '" ' . $selected . '>';
            echo esc_html($term->name);
            echo '</option>';
        }
        
        echo '</select>';
        
        // Add color swatches (if enabled)
        if ($enable_swatches) {
            echo '<div class="color-swatches">';
            foreach ($terms as $term) {
                $active_class = ($current_value === $term->slug) ? 'active' : '';
                
                if ($this->variation_swatches_active) {
                    // Use variation swatches plugin data
                    $swatch_html = $this->get_variation_swatch_html($term);
                } else {
                    // Fallback to basic color mapping
                    $color_hex = $this->get_color_hex($term->name);
                    $swatch_html = '<span class="color-swatch ' . $active_class . '" style="background-color: ' . $color_hex . ';" data-color="' . esc_attr($term->slug) . '" title="' . esc_attr($term->name) . '"></span>';
                }
                
                echo $swatch_html;
            }
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Get variation swatch HTML from the swatches plugin
     */
    private function get_variation_swatch_html($term) {
        $active_class = (isset($_GET['filter_pa_color']) && $_GET['filter_pa_color'] === $term->slug) ? 'active' : '';
        
        // Get swatch data from variation swatches plugin
        $color = sanitize_hex_color(get_term_meta($term->term_id, 'color', true));
        $is_dual_color = wc_string_to_bool(get_term_meta($term->term_id, 'is_dual_color', true));
        $secondary_color = sanitize_hex_color(get_term_meta($term->term_id, 'secondary_color', true));
        $image_id = get_term_meta($term->term_id, 'image', true);
        
        $swatch_html = '<span class="color-swatch variation-swatch ' . $active_class . '" data-color="' . esc_attr($term->slug) . '" title="' . esc_attr($term->name) . '"';
        
        if ($image_id) {
            // Image swatch
            $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
            if ($image_url) {
                $swatch_html .= ' style="background-image: url(' . esc_url($image_url) . '); background-size: cover;"';
            }
        } elseif ($is_dual_color && $secondary_color && $color) {
            // Dual color swatch
            $angle = '90deg'; // Default angle
            
            // Try to get the angle from the plugin if available
            if (function_exists('woo_variation_swatches')) {
                $instance = woo_variation_swatches();
                if ($instance && method_exists($instance, 'get_frontend')) {
                    $frontend = $instance->get_frontend();
                    if ($frontend && method_exists($frontend, 'get_dual_color_gradient_angle')) {
                        $angle = $frontend->get_dual_color_gradient_angle();
                    }
                }
            }
            
            $swatch_html .= ' style="background: linear-gradient(' . $angle . ', ' . $secondary_color . ' 0%, ' . $secondary_color . ' 50%, ' . $color . ' 50%, ' . $color . ' 100%);"';
        } elseif ($color) {
            // Single color swatch
            $swatch_html .= ' style="background-color: ' . esc_attr($color) . ';"';
        } else {
            // Fallback to basic color mapping
            $fallback_color = $this->get_color_hex($term->name);
            $swatch_html .= ' style="background-color: ' . $fallback_color . ';"';
        }
        
        $swatch_html .= '></span>';
        
        return $swatch_html;
    }
    
    /**
     * Render attribute filter
     */
    private function render_attribute_filter($attribute, $label, $current_filters) {
        $terms = get_terms(array(
            'taxonomy' => 'pa_' . $attribute,
            'hide_empty' => true,
        ));
        
        if (empty($terms) || is_wp_error($terms)) {
            return;
        }
        
        $filter_key = 'filter_pa_' . $attribute;
        $current_value = isset($current_filters[$filter_key]) ? $current_filters[$filter_key] : '';
        
        echo '<div class="filter-control attribute-filter">';
        echo '<label for="' . $filter_key . '">' . $label . '</label>';
        echo '<select name="' . $filter_key . '" id="' . $filter_key . '" class="filter-select">';
        echo '<option value="">All ' . $label . '</option>';
        
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
     * Render category filter.
     * Shows model subcategories (children of "baseball") plus Accessories.
     */
    private function render_category_filter($current_filters) {
        $bat_grips_parent = get_term_by('slug', 'baseball', 'product_cat');
        $parent_id = $bat_grips_parent ? $bat_grips_parent->term_id : 0;

        $model_categories = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'parent'     => $parent_id,
        ));

        $accessories = get_term_by('slug', 'accessory', 'product_cat');

        $current_value = isset($current_filters['product_cat']) ? $current_filters['product_cat'] : '';

        echo '<div class="filter-control category-filter">';
        echo '<label for="product_cat">Category</label>';
        echo '<select name="product_cat" id="product_cat" class="filter-select">';
        echo '<option value="">All Categories</option>';

        if (!empty($model_categories) && !is_wp_error($model_categories)) {
            foreach ($model_categories as $cat) {
                $selected = ($current_value === $cat->slug) ? 'selected' : '';
                echo '<option value="' . esc_attr($cat->slug) . '" ' . $selected . '>' . esc_html($cat->name) . '</option>';
            }
        }

        if ($accessories && !is_wp_error($accessories)) {
            $selected = ($current_value === $accessories->slug) ? 'selected' : '';
            echo '<option value="' . esc_attr($accessories->slug) . '" ' . $selected . '>' . esc_html($accessories->name) . '</option>';
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
        echo '<input type="number" name="min_price" placeholder="Min Price" value="' . esc_attr($min_price) . '" class="price-input">';
        echo '<input type="number" name="max_price" placeholder="Max Price" value="' . esc_attr($max_price) . '" class="price-input">';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Get color hex code (fallback method)
     */
    private function get_color_hex($color_name) {
        $color_map = array(
            'red' => '#FF0000',
            'blue' => '#0000FF',
            'green' => '#008000',
            'yellow' => '#FFFF00',
            'orange' => '#FFA500',
            'purple' => '#800080',
            'pink' => '#FFC0CB',
            'black' => '#000000',
            'white' => '#FFFFFF',
            'gray' => '#808080',
            'grey' => '#808080',
            'brown' => '#A52A2A',
            'navy' => '#000080',
            'teal' => '#008080',
            'lime' => '#00FF00',
            'maroon' => '#800000',
            'olive' => '#808000',
            'silver' => '#C0C0C0',
            'gold' => '#FFD700',
            'cyan' => '#00FFFF',
            'magenta' => '#FF00FF',
            'mint' => '#98FB98',
            'coral' => '#FF7F50',
            'salmon' => '#FA8072',
            'khaki' => '#F0E68C',
            'violet' => '#EE82EE',
            'indigo' => '#4B0082',
            'turquoise' => '#40E0D0',
            'crimson' => '#DC143C',
            'azure' => '#F0FFFF',
            'beige' => '#F5F5DC',
            'tan' => '#D2B48C',
            'plum' => '#DDA0DD',
            'orchid' => '#DA70D6',
            'lavender' => '#E6E6FA',
            'ivory' => '#FFFFF0',
            'snow' => '#FFFAFA',
            'flamethrower' => '#FF4500',
            'ocean' => '#006994',
            'lemonade' => '#FFFF9F',
            'gradient' => 'linear-gradient(45deg, #FF0000, #0000FF)',
            'blue/pink' => 'linear-gradient(45deg, #0000FF, #FFC0CB)',
            'light blue/pink' => 'linear-gradient(45deg, #ADD8E6, #FFC0CB)',
            'mint/black' => 'linear-gradient(45deg, #98FB98, #000000)',
            'pink/black' => 'linear-gradient(45deg, #FFC0CB, #000000)'
        );
        
        $color_name_lower = strtolower(trim($color_name));
        
        // Apply filters to allow theme customization
        $color_map = apply_filters('twintack_color_mapping', $color_map);
        
        return isset($color_map[$color_name_lower]) ? $color_map[$color_name_lower] : '#CCCCCC';
    }
    
    /**
     * Custom catalog ordering
     */
    public function custom_catalog_orderby($orderby) {
        $custom_options = get_option('twintack_custom_sort_options', array('menu_order', 'popularity', 'rating', 'date', 'price', 'price-desc'));
        
        $all_options = array(
            'menu_order' => 'Default sorting',
            'popularity' => 'Sort by popularity',
            'rating' => 'Sort by average rating',
            'date' => 'Sort by latest',
            'price' => 'Sort by price: low to high',
            'price-desc' => 'Sort by price: high to low'
        );
        
        $filtered_options = array();
        foreach ($custom_options as $key) {
            if (isset($all_options[$key])) {
                $filtered_options[$key] = $all_options[$key];
            }
        }
        
        return $filtered_options;
    }
    
    /**
     * Custom default catalog ordering
     */
    public function custom_default_catalog_orderby($orderby) {
        $default_option = get_option('twintack_default_sort_option', 'menu_order');
        return $default_option;
    }
}

// Initialize the plugin
TwinTack_Enhanced_Shop_Filters::get_instance();

// Plugin activation hook
register_activation_hook(__FILE__, 'twintack_esf_activate');

function twintack_esf_activate() {
    // Set default options if they don't exist
    if (!get_option('twintack_enabled_filters')) {
        update_option('twintack_enabled_filters', array('color', 'pattern', 'sport', 'brand', 'category', 'price'));
    }
    
    if (!get_option('twintack_custom_sort_options')) {
        update_option('twintack_custom_sort_options', array('menu_order', 'popularity', 'rating', 'date', 'price', 'price-desc'));
    }
    
    if (!get_option('twintack_default_sort_option')) {
        update_option('twintack_default_sort_option', 'menu_order');
    }
    
    if (!get_option('twintack_filter_display_style')) {
        update_option('twintack_filter_display_style', 'modal');
    }
    
    if (!get_option('twintack_enable_ajax')) {
        update_option('twintack_enable_ajax', false);
    }
} 