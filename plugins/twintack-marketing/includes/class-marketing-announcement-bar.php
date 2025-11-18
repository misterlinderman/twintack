<?php
/**
 * Website-wide Announcement Bar
 * Displays an optional announcement bar at the top of all pages
 */

if (!defined('ABSPATH')) exit;

class TwinTack_Marketing_Announcement_Bar {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Display in body, not head - use wp_body_open
        add_action('wp_body_open', array($this, 'display_announcement_bar'), 5);
        
        // Add menu after admin menu is created (higher priority)
        add_action('admin_menu', array($this, 'add_settings_page'), 20);
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function add_settings_page() {
        // Menu is now added by the admin class to ensure parent exists
        // This method is kept for backward compatibility but won't be called
        // if admin class handles it
    }
    
    public function register_settings() {
        register_setting('twintack_announcement_bar', 'twintack_announcement_enabled');
        register_setting('twintack_announcement_bar', 'twintack_announcement_text');
        register_setting('twintack_announcement_bar', 'twintack_announcement_link');
        register_setting('twintack_announcement_bar', 'twintack_announcement_link_text');
        register_setting('twintack_announcement_bar', 'twintack_announcement_bg_color');
        register_setting('twintack_announcement_bar', 'twintack_announcement_text_color');
        register_setting('twintack_announcement_bar', 'twintack_announcement_dismissible');
    }
    
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Announcement Bar Settings', 'twintack-marketing'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('twintack_announcement_bar'); ?>
                <?php do_settings_sections('twintack_announcement_bar'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Announcement Bar', 'twintack-marketing'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="twintack_announcement_enabled" value="1" 
                                       <?php checked(get_option('twintack_announcement_enabled'), '1'); ?> />
                                <?php _e('Show announcement bar on all pages', 'twintack-marketing'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="twintack_announcement_text"><?php _e('Announcement Text', 'twintack-marketing'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="twintack_announcement_text" id="twintack_announcement_text" 
                                   value="<?php echo esc_attr(get_option('twintack_announcement_text')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="twintack_announcement_link"><?php _e('Link URL', 'twintack-marketing'); ?></label>
                        </th>
                        <td>
                            <input type="url" name="twintack_announcement_link" id="twintack_announcement_link" 
                                   value="<?php echo esc_attr(get_option('twintack_announcement_link')); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php _e('Optional: URL to link the announcement to', 'twintack-marketing'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="twintack_announcement_link_text"><?php _e('Link Text', 'twintack-marketing'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="twintack_announcement_link_text" id="twintack_announcement_link_text" 
                                   value="<?php echo esc_attr(get_option('twintack_announcement_link_text')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="twintack_announcement_bg_color"><?php _e('Background Color', 'twintack-marketing'); ?></label>
                        </th>
                        <td>
                            <input type="color" name="twintack_announcement_bg_color" id="twintack_announcement_bg_color" 
                                   value="<?php echo esc_attr(get_option('twintack_announcement_bg_color', '#000000')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="twintack_announcement_text_color"><?php _e('Text Color', 'twintack-marketing'); ?></label>
                        </th>
                        <td>
                            <input type="color" name="twintack_announcement_text_color" id="twintack_announcement_text_color" 
                                   value="<?php echo esc_attr(get_option('twintack_announcement_text_color', '#ffffff')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Dismissible', 'twintack-marketing'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="twintack_announcement_dismissible" value="1" 
                                       <?php checked(get_option('twintack_announcement_dismissible'), '1'); ?> />
                                <?php _e('Allow users to dismiss the announcement', 'twintack-marketing'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    private static $displayed = false;
    
    public function display_announcement_bar() {
        // Prevent duplicate display
        if (self::$displayed) {
            return;
        }
        
        if (!get_option('twintack_announcement_enabled')) {
            return;
        }
        
        $text = get_option('twintack_announcement_text');
        if (empty($text)) {
            return;
        }
        
        $link = get_option('twintack_announcement_link');
        $link_text = get_option('twintack_announcement_link_text', 'Learn More');
        $bg_color = get_option('twintack_announcement_bg_color', '#000000');
        $text_color = get_option('twintack_announcement_text_color', '#ffffff');
        $dismissible = get_option('twintack_announcement_dismissible');
        
        // Mark as displayed
        self::$displayed = true;
        
        ?>
        <div id="twintack-announcement-bar" 
             class="twintack-announcement-bar<?php echo $dismissible ? ' dismissible' : ''; ?>"
             style="background-color: <?php echo esc_attr($bg_color); ?>; color: <?php echo esc_attr($text_color); ?>;"
             data-dismissible="<?php echo $dismissible ? '1' : '0'; ?>">
            <div class="twintack-announcement-content">
                <span class="twintack-announcement-text">
                    <?php echo esc_html($text); ?>
                    <?php if (!empty($link)) : ?>
                        <a href="<?php echo esc_url($link); ?>" 
                           style="color: <?php echo esc_attr($text_color); ?>; text-decoration: underline; margin-left: 10px;">
                            <?php echo esc_html($link_text); ?>
                        </a>
                    <?php endif; ?>
                </span>
                <?php if ($dismissible) : ?>
                    <button class="twintack-announcement-close" aria-label="<?php esc_attr_e('Close', 'twintack-marketing'); ?>">×</button>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
}

