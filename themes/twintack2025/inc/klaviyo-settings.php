<?php
/**
 * Klaviyo Integration Settings
 * 
 * @package twintack2025
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add Klaviyo settings page to the admin menu
 */
function twintack_add_klaviyo_settings_page() {
    add_submenu_page(
        'options-general.php',
        'Klaviyo Integration',
        'Klaviyo Integration',
        'manage_options',
        'twintack-klaviyo-settings',
        'twintack_render_klaviyo_settings_page'
    );
}
add_action('admin_menu', 'twintack_add_klaviyo_settings_page');

/**
 * Register Klaviyo settings
 */
function twintack_register_klaviyo_settings() {
    register_setting('twintack_klaviyo_settings_group', 'twintack_klaviyo_list_id');
}
add_action('admin_init', 'twintack_register_klaviyo_settings');

// Klaviyo script loading order fix moved to plugin: twintack-admin-console-fixes

/**
 * Render the Klaviyo settings page
 */
function twintack_render_klaviyo_settings_page() {
    ?>
    <div class="wrap">
        <h1>Klaviyo Newsletter Integration</h1>
        
        <form method="post" action="options.php">
            <?php settings_fields('twintack_klaviyo_settings_group'); ?>
            <?php do_settings_sections('twintack_klaviyo_settings_group'); ?>
            
            <table class="form-table">                
                <tr valign="top">
                    <th scope="row">Klaviyo List ID</th>
                    <td>
                        <input type="text" name="twintack_klaviyo_list_id" 
                               value="<?php echo esc_attr(get_option('twintack_klaviyo_list_id', 'UXnNZg')); ?>" 
                               class="regular-text" />
                        <p class="description">
                            Enter your Klaviyo List ID for the newsletter subscription. You can find this in 
                            <a href="https://www.klaviyo.com/lists" target="_blank">Klaviyo Lists & Segments</a>.
                            Current implementation uses Klaviyo's embedded form to avoid API issues.
                        </p>
                    </td>
                </tr>
            </table>
            
            <h2>How to Use This Integration</h2>
            <p>This integration uses Klaviyo's official embedded form approach for better reliability. The newsletter form in your website footer will:</p>
            <ol>
                <li>Submit directly to Klaviyo's servers</li>
                <li>Handle success and error responses</li>
                <li>Provide user feedback without page refreshes</li>
            </ol>
            
            <p>To customize newsletter signup behavior further, you can use 
            <a href="https://help.klaviyo.com/hc/en-us/articles/115005249588-Embedded-Sign-Up-Forms" target="_blank">Klaviyo's embedded form documentation</a>.</p>
            
            <h2>Testing Your Integration</h2>
            <p>After saving your settings, you can test the newsletter subscription form on your website to ensure it's working correctly.</p>
            
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Update the Klaviyo data getter function to use options or constants
 */
function twintack_get_klaviyo_data() {
    // First check if constants are defined (highest priority)
    if (defined('KLAVIYO_LIST_ID')) {
        return array(
            'listId' => KLAVIYO_LIST_ID
        );
    }
    
    // Otherwise use options values
    return array(
        'listId' => get_option('twintack_klaviyo_list_id', 'UXnNZg')
    );
} 