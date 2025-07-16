<?php
/**
 * Plugin Name: TwinTack How-To Videos
 * Plugin URI: https://twintack.com
 * Description: Comprehensive how-to video management system with sport categorization, carousel display, and video modal functionality.
 * Version: 1.1.0
 * Author: TwinTack
 * License: GPL v2 or later
 * Requires at least: 5.8
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Text Domain: twintack-how-to-videos
 * Domain Path: /languages
 *
 * @package TwinTackHowToVideos
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TWINTACK_HTV_VERSION', '1.1.0');
define('TWINTACK_HTV_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TWINTACK_HTV_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TWINTACK_HTV_PLUGIN_FILE', __FILE__);

/**
 * Main TwinTack How-To Videos Plugin Class
 */
class TwinTack_How_To_Videos {
    
    /**
     * Instance of this class
     * @var TwinTack_How_To_Videos
     */
    private static $instance = null;
    
    /**
     * Get the single instance of this class
     * @return TwinTack_How_To_Videos
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('twintack-how-to-videos', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Include required files
        $this->includes();
        
        // Initialize plugin components
        $this->init_hooks();
    }
    
    /**
     * Include required files
     */
    private function includes() {
        require_once TWINTACK_HTV_PLUGIN_DIR . 'includes/class-post-type.php';
        require_once TWINTACK_HTV_PLUGIN_DIR . 'includes/class-admin.php';
        require_once TWINTACK_HTV_PLUGIN_DIR . 'includes/class-frontend.php';
        require_once TWINTACK_HTV_PLUGIN_DIR . 'includes/class-assets.php';
        require_once TWINTACK_HTV_PLUGIN_DIR . 'includes/functions.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Initialize components
        TwinTack_HTV_Post_Type::get_instance();
        TwinTack_HTV_Admin::get_instance();
        TwinTack_HTV_Frontend::get_instance();
        TwinTack_HTV_Assets::get_instance();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Flush rewrite rules to ensure custom post types work
        flush_rewrite_rules();
        
        // Set default options if needed
        if (!get_option('twintack_htv_version')) {
            add_option('twintack_htv_version', TWINTACK_HTV_VERSION);
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

/**
 * Initialize the plugin
 */
function twintack_how_to_videos() {
    return TwinTack_How_To_Videos::get_instance();
}

// Start the plugin
twintack_how_to_videos(); 