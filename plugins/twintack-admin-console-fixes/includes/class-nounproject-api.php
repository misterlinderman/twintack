<?php
/**
 * Noun Project API Integration
 * Handles communication with the Noun Project API for icon search and retrieval
 * Shared across all TwinTack plugins
 */

if (!defined('ABSPATH')) exit;

class TwinTack_NounProject_API {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_menu_page'), 100); // Load late to appear after other TwinTack menus
    }
    
    /**
     * Add admin menu page
     */
    public function add_menu_page() {
        add_menu_page(
            __('Noun Project', 'twintack-admin-console-fixes'),
            __('Noun Project', 'twintack-admin-console-fixes'),
            'manage_options',
            'twintack-nounproject',
            array($this, 'render_settings_page'),
            'dashicons-format-gallery',
            85 // Position after TwinTack plugins
        );
    }
    
    /**
     * Register settings for API credentials
     */
    public function register_settings() {
        register_setting('twintack_nounproject_settings', 'twintack_nounproject_api_key');
        register_setting('twintack_nounproject_settings', 'twintack_nounproject_api_secret');
    }
    
    /**
     * Get API credentials
     */
    private function get_credentials() {
        return array(
            'key' => get_option('twintack_nounproject_api_key', ''),
            'secret' => get_option('twintack_nounproject_api_secret', '')
        );
    }
    
    /**
     * Check if API credentials are configured
     */
    public function is_configured() {
        $credentials = $this->get_credentials();
        return !empty($credentials['key']) && !empty($credentials['secret']);
    }
    
    /**
     * Generate OAuth 1.0a signature for Noun Project API
     * 
     * @param string $method HTTP method
     * @param string $url Full URL
     * @param array $params OAuth parameters
     * @return string Signature
     */
    private function generate_oauth_signature($method, $url, $params) {
        $credentials = $this->get_credentials();
        
        // Sort parameters alphabetically
        ksort($params);
        
        // Build parameter string
        $param_string = array();
        foreach ($params as $key => $value) {
            $param_string[] = rawurlencode($key) . '=' . rawurlencode($value);
        }
        $param_string = implode('&', $param_string);
        
        // Build signature base string
        $base_string = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($param_string);
        
        // Build signing key
        $signing_key = rawurlencode($credentials['secret']) . '&';
        
        // Generate signature
        $signature = base64_encode(hash_hmac('sha1', $base_string, $signing_key, true));
        
        return $signature;
    }
    
    /**
     * Make authenticated request to Noun Project API
     * 
     * @param string $endpoint API endpoint (e.g., 'icon/1', 'collection/1/icons')
     * @param array $params Query parameters
     * @return array|WP_Error Response data or error
     */
    private function make_request($endpoint, $params = array()) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Noun Project API credentials not configured.', 'twintack-admin-console-fixes'));
        }
        
        $credentials = $this->get_credentials();
        $base_url = 'https://api.thenounproject.com/v2/';
        $url = $base_url . ltrim($endpoint, '/');
        
        // OAuth 1.0a parameters
        $oauth_params = array(
            'oauth_consumer_key' => $credentials['key'],
            'oauth_timestamp' => time(),
            'oauth_nonce' => md5(microtime() . mt_rand()),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_version' => '1.0'
        );
        
        // Merge OAuth params with query params for signature
        $all_params = array_merge($oauth_params, $params);
        
        // Generate signature
        $oauth_params['oauth_signature'] = $this->generate_oauth_signature('GET', $url, $all_params);
        
        // Build Authorization header
        $auth_header_parts = array();
        foreach ($oauth_params as $key => $value) {
            $auth_header_parts[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
        }
        $auth_header = 'OAuth ' . implode(', ', $auth_header_parts);
        
        // Build final URL with query parameters
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        // Make request with OAuth 1.0a
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => $auth_header,
                'Accept' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            error_log('TwinTack Noun Project API Error: ' . $response->get_error_message());
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $error_message = isset($data['message']) ? $data['message'] : __('API request failed', 'twintack-admin-console-fixes');
            error_log('TwinTack Noun Project API Error: HTTP ' . $status_code . ' - ' . $error_message);
            error_log('Response body: ' . $body);
            return new WP_Error('api_error', $error_message . ' (HTTP ' . $status_code . ')', array('status' => $status_code, 'body' => $body));
        }
        
        return $data;
    }
    
    /**
     * Search for icons
     * 
     * @param string $query Search term
     * @param int $limit Number of results (default 50, max 50)
     * @param int $page Page number (default 1)
     * @return array|WP_Error Array of icon objects or error
     */
    public function search_icons($query, $limit = 50, $page = 1) {
        $params = array(
            'query' => sanitize_text_field($query),
            'limit_to_public_domain' => '0',
            'limit' => min(intval($limit), 50),
            'page' => max(intval($page), 1)
        );
        
        // Use the collection endpoint for search (v2 API)
        $response = $this->make_request('collection/1/icons', $params);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return isset($response['icons']) ? $response['icons'] : array();
    }
    
    /**
     * Get icon by ID
     * 
     * @param int $icon_id Icon ID
     * @return array|WP_Error Icon object or error
     */
    public function get_icon($icon_id) {
        $response = $this->make_request('icon/' . intval($icon_id));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return isset($response['icon']) ? $response['icon'] : null;
    }
    
    /**
     * Download icon SVG or PNG
     * 
     * @param int $icon_id Icon ID
     * @param string $format Format to download ('svg' or 'png'), default 'svg'
     * @return string|WP_Error Icon content or error
     */
    public function download_icon_svg($icon_id, $format = 'svg') {
        $icon = $this->get_icon($icon_id);
        
        if (is_wp_error($icon)) {
            return $icon;
        }
        
        // Debug: Log the icon structure
        error_log('Noun Project Icon Data for ID ' . $icon_id . ': ' . print_r($icon, true));
        
        $icon_url = null;
        
        // For SVG format - Note: SVG access may require paid API key for non-public domain icons
        if ($format === 'svg') {
            // Try different possible SVG URL fields
            $svg_fields = array('icon_url', 'svg_url', 'svg');
            foreach ($svg_fields as $field) {
                if (isset($icon[$field]) && !empty($icon[$field])) {
                    $icon_url = $icon[$field];
                    error_log("Found SVG in field '{$field}': {$icon_url}");
                    break;
                }
            }
        }
        
        // Fallback to PNG if SVG not available
        if (!$icon_url) {
            error_log('SVG not available for icon ' . $icon_id . ', trying PNG preview');
            // Try PNG preview URLs (available on free plans)
            $png_fields = array('thumbnail_url', 'preview_url_84', 'preview_url', 'preview');
            
            foreach ($png_fields as $field) {
                if (isset($icon[$field]) && !empty($icon[$field])) {
                    $png_url = $icon[$field];
                    error_log("Found PNG in field '{$field}': {$png_url}");
                    
                    // Download PNG
                    $png_data = wp_remote_get($png_url, array('timeout' => 15));
                    if (is_wp_error($png_data)) {
                        error_log("Failed to download PNG from {$png_url}: " . $png_data->get_error_message());
                        continue; // Try next field
                    }
                    
                    $png_content = wp_remote_retrieve_body($png_data);
                    if (empty($png_content)) {
                        error_log("Empty PNG content from {$png_url}");
                        continue; // Try next field
                    }
                    
                    error_log("Successfully downloaded PNG (" . strlen($png_content) . " bytes), converting to SVG");
                    
                    // Return as data URI embedded in SVG for consistency
                    $base64 = base64_encode($png_content);
                    return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 100 100"><image xlink:href="data:image/png;base64,' . $base64 . '" x="0" y="0" width="100" height="100"/></svg>';
                }
            }
            
            // If we get here, no PNG was found or downloaded successfully
            error_log('No PNG URL found or download failed. Available fields: ' . implode(', ', array_keys($icon)));
            return new WP_Error('no_icon_url', __('Icon download failed. No SVG or PNG URL available.', 'twintack-admin-console-fixes'));
        }
        
        // Download the icon
        $response = wp_remote_get($icon_url, array(
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            error_log('Failed to download icon from ' . $icon_url . ': ' . $response->get_error_message());
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        error_log('Successfully downloaded icon ' . $icon_id . ' (' . strlen($body) . ' bytes)');
        
        return $body;
    }
    
    /**
     * Save icon to WordPress media library
     * 
     * @param int $icon_id Noun Project icon ID
     * @param string $filename Optional filename
     * @return int|WP_Error Attachment ID or error
     */
    public function save_icon_to_media($icon_id, $filename = '') {
        $svg_content = $this->download_icon_svg($icon_id);
        
        if (is_wp_error($svg_content)) {
            return $svg_content;
        }
        
        // Get icon details for metadata
        $icon = $this->get_icon($icon_id);
        
        if (empty($filename)) {
            $filename = isset($icon['term']) ? sanitize_file_name($icon['term']) : 'icon-' . $icon_id;
        }
        $filename = $filename . '.svg';
        
        // Upload to WordPress
        $upload = wp_upload_bits($filename, null, $svg_content);
        
        if ($upload['error']) {
            return new WP_Error('upload_failed', $upload['error']);
        }
        
        // Create attachment
        $attachment = array(
            'post_mime_type' => 'image/svg+xml',
            'post_title' => isset($icon['term']) ? $icon['term'] : 'Icon ' . $icon_id,
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attach_id = wp_insert_attachment($attachment, $upload['file']);
        
        if (is_wp_error($attach_id)) {
            return $attach_id;
        }
        
        // Add metadata
        if (!empty($icon)) {
            update_post_meta($attach_id, '_nounproject_icon_id', $icon_id);
            update_post_meta($attach_id, '_nounproject_icon_data', $icon);
        }
        
        return $attach_id;
    }
    
    /**
     * Test API connection
     * 
     * @return bool|WP_Error True if connection successful, error otherwise
     */
    public function test_connection() {
        // Test by fetching a known icon (icon ID 1 should always exist)
        $response = $this->get_icon(1);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return true;
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $is_configured = $this->is_configured();
        $test_result = null;
        
        // Handle test connection
        if (isset($_POST['test_connection']) && check_admin_referer('twintack_nounproject_test')) {
            $test_result = $this->test_connection();
        }
        
        ?>
        <div class="wrap twintack-nounproject-wrap">
            <h1><?php _e('Noun Project API Settings', 'twintack-admin-console-fixes'); ?></h1>
            <p class="description"><?php _e('Configure your Noun Project API credentials to enable icon search and integration across all TwinTack plugins.', 'twintack-admin-console-fixes'); ?></p>
            
            <div class="twintack-nounproject-settings">
                <form method="post" action="options.php">
                    <?php settings_fields('twintack_nounproject_settings'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="twintack_nounproject_api_key"><?php _e('API Key', 'twintack-admin-console-fixes'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       name="twintack_nounproject_api_key" 
                                       id="twintack_nounproject_api_key" 
                                       value="<?php echo esc_attr(get_option('twintack_nounproject_api_key')); ?>" 
                                       class="regular-text code" 
                                       placeholder="de619475672e4f8587b163372ad9b03d" />
                                <p class="description">
                                    <?php _e('Your Noun Project API Key from', 'twintack-admin-console-fixes'); ?> 
                                    <a href="https://thenounproject.com/developers/apps" target="_blank"><?php _e('The Noun Project Developer Dashboard', 'twintack-admin-console-fixes'); ?></a>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="twintack_nounproject_api_secret"><?php _e('API Secret', 'twintack-admin-console-fixes'); ?></label>
                            </th>
                            <td>
                                <input type="password" 
                                       name="twintack_nounproject_api_secret" 
                                       id="twintack_nounproject_api_secret" 
                                       value="<?php echo esc_attr(get_option('twintack_nounproject_api_secret')); ?>" 
                                       class="regular-text code" 
                                       placeholder="64db3370100c4a3184cbb69afbf6c9d8" />
                                <p class="description"><?php _e('Your Noun Project API Secret (kept secure in the database)', 'twintack-admin-console-fixes'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Save API Credentials', 'twintack-admin-console-fixes')); ?>
                </form>
                
                <?php if ($is_configured) : ?>
                    <hr style="margin: 30px 0;">
                    
                    <h2><?php _e('Test Connection', 'twintack-admin-console-fixes'); ?></h2>
                    <form method="post">
                        <?php wp_nonce_field('twintack_nounproject_test'); ?>
                        <p><?php _e('Test your API credentials to ensure they are working correctly.', 'twintack-admin-console-fixes'); ?></p>
                        <button type="submit" name="test_connection" class="button"><?php _e('Test API Connection', 'twintack-admin-console-fixes'); ?></button>
                    </form>
                    
                    <?php if ($test_result !== null) : ?>
                        <?php if ($test_result === true) : ?>
                            <div class="notice notice-success" style="margin-top: 20px;">
                                <p><strong><?php _e('Success!', 'twintack-admin-console-fixes'); ?></strong> <?php _e('Your Noun Project API credentials are working correctly.', 'twintack-admin-console-fixes'); ?></p>
                            </div>
                        <?php else : ?>
                            <div class="notice notice-error" style="margin-top: 20px;">
                                <p><strong><?php _e('Error:', 'twintack-admin-console-fixes'); ?></strong> <?php echo esc_html($test_result->get_error_message()); ?></p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else : ?>
                    <div class="notice notice-warning inline" style="margin-top: 20px;">
                        <p><?php _e('Please enter your API credentials and save to enable Noun Project integration.', 'twintack-admin-console-fixes'); ?></p>
                    </div>
                <?php endif; ?>
                
                <hr style="margin: 30px 0;">
                
                <div class="twintack-api-info">
                    <h2><?php _e('About Noun Project API', 'twintack-admin-console-fixes'); ?></h2>
                    <p><?php _e('The Noun Project provides access to millions of icons through their API. With your API credentials configured, TwinTack plugins can:', 'twintack-admin-console-fixes'); ?></p>
                    <ul style="list-style: disc; margin-left: 30px;">
                        <li><?php _e('Search for icons directly from the WordPress admin', 'twintack-admin-console-fixes'); ?></li>
                        <li><?php _e('Download icons in SVG format', 'twintack-admin-console-fixes'); ?></li>
                        <li><?php _e('Use icons in marketing materials and templates', 'twintack-admin-console-fixes'); ?></li>
                        <li><?php _e('Save icons to your WordPress media library', 'twintack-admin-console-fixes'); ?></li>
                    </ul>
                    
                    <h3><?php _e('Getting Your API Credentials', 'twintack-admin-console-fixes'); ?></h3>
                    <ol style="list-style: decimal; margin-left: 30px;">
                        <li><?php _e('Visit', 'twintack-admin-console-fixes'); ?> <a href="https://thenounproject.com/developers/apps" target="_blank">The Noun Project Developer Dashboard</a></li>
                        <li><?php _e('Create a new app or select an existing one', 'twintack-admin-console-fixes'); ?></li>
                        <li><?php _e('Copy the API Key and Secret from your app settings', 'twintack-admin-console-fixes'); ?></li>
                        <li><?php _e('Paste them into the fields above and save', 'twintack-admin-console-fixes'); ?></li>
                    </ol>
                    
                    <h3><?php _e('Usage in TwinTack Plugins', 'twintack-admin-console-fixes'); ?></h3>
                    <p><?php _e('Once configured, the Noun Project API is available to all TwinTack plugins through the shared API class:', 'twintack-admin-console-fixes'); ?></p>
                    <pre style="background: #f8f9fa; padding: 15px; border: 1px solid #e0e0e0; border-radius: 4px; overflow-x: auto;"><code>// Get API instance
$api = TwinTack_NounProject_API::get_instance();

// Search for icons
$icons = $api->search_icons('baseball', 50, 1);

// Get specific icon
$icon = $api->get_icon(123456);

// Download icon SVG
$svg = $api->download_icon_svg(123456);

// Save to media library
$attachment_id = $api->save_icon_to_media(123456);</code></pre>
                </div>
            </div>
        </div>
        <?php
    }
}

