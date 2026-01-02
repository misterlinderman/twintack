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
        register_setting('twintack_announcement_bar', 'twintack_announcement_show_all_pages');
        register_setting('twintack_announcement_bar', 'twintack_announcement_specific_pages');
    }
    
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Announcement Bar Settings', 'twintack-marketing'); ?></h1>
            <p class="description"><?php _e('Display a site-wide announcement bar with custom text, colors, and optional link.', 'twintack-marketing'); ?></p>
            
            <div class="twintack-announcement-settings">
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
                                <?php _e('Enable announcement bar', 'twintack-marketing'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Display Options', 'twintack-marketing'); ?></th>
                        <td>
                            <label style="display: block; margin-bottom: 10px;">
                                <input type="radio" name="twintack_announcement_show_all_pages" value="1" 
                                       <?php checked(get_option('twintack_announcement_show_all_pages', '1'), '1'); ?> />
                                <?php _e('Show on all pages', 'twintack-marketing'); ?>
                            </label>
                            <label style="display: block;">
                                <input type="radio" name="twintack_announcement_show_all_pages" value="0" 
                                       <?php checked(get_option('twintack_announcement_show_all_pages', '1'), '0'); ?> />
                                <?php _e('Show on specific pages only', 'twintack-marketing'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr class="announcement-specific-pages" style="<?php echo get_option('twintack_announcement_show_all_pages', '1') === '0' ? '' : 'display:none;'; ?>">
                        <th scope="row">
                            <label for="twintack_announcement_specific_pages"><?php _e('Select Pages', 'twintack-marketing'); ?></label>
                        </th>
                        <td>
                            <?php
                            $pages = get_pages();
                            $selected_pages = get_option('twintack_announcement_specific_pages', array());
                            if (!is_array($selected_pages)) {
                                $selected_pages = array();
                            }
                            ?>
                            <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
                                <?php foreach ($pages as $page) : ?>
                                    <label style="display: block; margin: 5px 0;">
                                        <input type="checkbox" 
                                               name="twintack_announcement_specific_pages[]" 
                                               value="<?php echo esc_attr($page->ID); ?>"
                                               <?php checked(in_array($page->ID, $selected_pages)); ?> />
                                        <?php echo esc_html($page->post_title); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description"><?php _e('Select which pages should display the announcement bar', 'twintack-marketing'); ?></p>
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
                    
                    <?php submit_button(__('Save Settings', 'twintack-marketing'), 'primary', 'submit', true, array('style' => 'margin-top: 20px;')); ?>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Toggle specific pages visibility
            $('input[name="twintack_announcement_show_all_pages"]').on('change', function() {
                if ($(this).val() === '0' && $(this).is(':checked')) {
                    $('.announcement-specific-pages').slideDown();
                } else if ($(this).val() === '1' && $(this).is(':checked')) {
                    $('.announcement-specific-pages').slideUp();
                }
            });
        });
        </script>
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
        
        // Check page-specific display settings
        $show_all_pages = get_option('twintack_announcement_show_all_pages', '1');
        if ($show_all_pages === '0') {
            $specific_pages = get_option('twintack_announcement_specific_pages', array());
            if (!is_array($specific_pages)) {
                $specific_pages = array();
            }
            
            // Check if current page is in the list
            $current_page_id = get_queried_object_id();
            if (!in_array($current_page_id, $specific_pages)) {
                return;
            }
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
             data-dismissible="<?php echo $dismissible ? '1' : '0'; ?>"
             data-bar-height="48">
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
        <style>
            /* Push site header down when announcement bar is present */
            body:not(.admin-bar) .site-header {
                top: 48px !important;
            }
            body.admin-bar .site-header {
                top: 80px !important; /* 32px admin bar + 48px announcement */
            }
            
            /* Push fixed nav icons (account/cart) down */
            body:not(.admin-bar) .fixed-nav-icons {
                top: 68px !important; /* 48px announcement + 20px original padding */
            }
            body.admin-bar .fixed-nav-icons {
                top: 100px !important; /* 32px admin bar + 48px announcement + 20px padding */
            }
            
            /* Push main content down to prevent overlap */
            #page {
                padding-top: 48px !important;
            }
            body.admin-bar #page {
                padding-top: 80px !important;
            }
            
            @media screen and (max-width: 782px) {
                body.admin-bar .site-header {
                    top: 94px !important; /* 46px admin bar + 48px announcement */
                }
                body.admin-bar .fixed-nav-icons {
                    top: 114px !important; /* 46px admin bar + 48px announcement + 20px padding */
                }
                body.admin-bar #page {
                    padding-top: 94px !important;
                }
            }
        </style>
        <?php
    }
    
}

