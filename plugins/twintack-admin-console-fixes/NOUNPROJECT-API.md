# Noun Project API Integration

## Overview
The TwinTack Admin Console Fixes plugin now includes integration with The Noun Project API, providing icon search and download capabilities that are **shared across all TwinTack plugins**.

This centralized approach ensures that:
- All TwinTack plugins can access the Noun Project API
- API credentials are configured in one place
- Icon functionality is consistent across plugins
- Future consolidation into a single plugin is easier

## Setup Instructions

### 1. Configure API Credentials

1. Navigate to **Noun Project** in your WordPress admin menu (standalone menu item)
2. Enter your API credentials:
   - **API Key**: `de619475672e4f8587b163372ad9b03d`
   - **API Secret**: `64db3370100c4a3184cbb69afbf6c9d8`
3. Click **Save API Credentials**

### 2. Test Connection

1. After saving, scroll down to the **Test Connection** section
2. Click **Test API Connection** to verify your credentials are working
3. You should see a success message if everything is configured correctly

## Features

### For Plugin Developers

The API is available to all TwinTack plugins through the singleton pattern:

```php
// Check if API is available
if (class_exists('TwinTack_NounProject_API')) {
    $api = TwinTack_NounProject_API::get_instance();
    
    // Check if configured
    if ($api->is_configured()) {
        // Use API methods
    }
}
```

### Available Methods

#### 1. Check Configuration Status
```php
$api = TwinTack_NounProject_API::get_instance();
$is_configured = $api->is_configured(); // Returns boolean
```

#### 2. Search Icons
```php
$api = TwinTack_NounProject_API::get_instance();
$icons = $api->search_icons('baseball', 50, 1);

if (!is_wp_error($icons)) {
    foreach ($icons as $icon) {
        echo $icon['id'];
        echo $icon['term'];
        echo $icon['icon_url'];
        // ... more properties
    }
}
```

#### 3. Get Specific Icon
```php
$api = TwinTack_NounProject_API::get_instance();
$icon = $api->get_icon(123456);

if (!is_wp_error($icon)) {
    echo $icon['term'];
    echo $icon['icon_url'];
}
```

#### 4. Download Icon SVG
```php
$api = TwinTack_NounProject_API::get_instance();
$svg_content = $api->download_icon_svg(123456);

if (!is_wp_error($svg_content)) {
    // Use SVG content
    echo $svg_content;
}
```

#### 5. Save Icon to Media Library
```php
$api = TwinTack_NounProject_API::get_instance();
$attachment_id = $api->save_icon_to_media(123456, 'custom-filename');

if (!is_wp_error($attachment_id)) {
    // Use attachment ID
    $image_url = wp_get_attachment_url($attachment_id);
}
```

#### 6. Test Connection
```php
$api = TwinTack_NounProject_API::get_instance();
$test_result = $api->test_connection();

if ($test_result === true) {
    // Connection successful
} else {
    // Connection failed
    echo $test_result->get_error_message();
}
```

## API Details

### Authentication
- Uses OAuth 2.0 Bearer token authentication
- API Key is used as the Bearer token
- All requests include proper authorization headers

### Endpoint Base
```
https://api.thenounproject.com/v2/
```

### Rate Limits
- Follows Noun Project API rate limits
- Implement caching in your plugins to reduce API calls
- Handle `429 Too Many Requests` errors gracefully

### Error Handling

All API methods return `WP_Error` objects on failure:

```php
$result = $api->search_icons('test');

if (is_wp_error($result)) {
    $error_code = $result->get_error_code();
    $error_message = $result->get_error_message();
    
    // Handle specific errors
    switch ($error_code) {
        case 'not_configured':
            // API credentials not set
            break;
        case 'api_error':
            // API request failed
            break;
        default:
            // Other errors
            break;
    }
}
```

## Icon Metadata

When icons are saved to the media library, metadata is stored:

```php
// Get Noun Project icon ID
$icon_id = get_post_meta($attachment_id, '_nounproject_icon_id', true);

// Get full icon data
$icon_data = get_post_meta($attachment_id, '_nounproject_icon_data', true);
```

## Usage in TwinTack Plugins

### Example: Marketing Plugin

```php
// In your marketing plugin
class TwinTack_Marketing_Icon_Manager {
    public function search_marketing_icons($query) {
        // Check if Noun Project API is available
        if (!class_exists('TwinTack_NounProject_API')) {
            return new WP_Error('no_api', 'Noun Project API not available');
        }
        
        $api = TwinTack_NounProject_API::get_instance();
        
        if (!$api->is_configured()) {
            return new WP_Error('not_configured', 'Please configure Noun Project API');
        }
        
        return $api->search_icons($query);
    }
}
```

### Example: Grip Manager Plugin

```php
// In your grip manager plugin
class TwinTack_Grip_Icon_Selector {
    public function add_icon_to_design($icon_id, $design_post_id) {
        if (!class_exists('TwinTack_NounProject_API')) {
            return false;
        }
        
        $api = TwinTack_NounProject_API::get_instance();
        $attachment_id = $api->save_icon_to_media($icon_id);
        
        if (!is_wp_error($attachment_id)) {
            update_post_meta($design_post_id, '_design_icon', $attachment_id);
            return true;
        }
        
        return false;
    }
}
```

## Security

- **API credentials** are stored securely in WordPress options table
- **API Secret** is displayed as a password field (not visible)
- **Nonce verification** on all admin actions
- **Capability checks** (`manage_options`) for settings access
- **Input sanitization** on all user inputs
- **Output escaping** on all displayed data

## Settings Page

The standalone **Noun Project** menu item provides:

1. **API Credentials Form**
   - API Key input (monospace font for easier entry)
   - API Secret input (password field)
   - Save button with validation

2. **Connection Test**
   - One-click test button
   - Clear success/error feedback
   - Error message display

3. **Documentation**
   - About the Noun Project API
   - How to get credentials
   - Usage examples for developers
   - Code snippets

## Future Enhancements

Potential additions for future development:

1. **Icon Browser Interface**
   - Search and preview icons in admin
   - Click to download
   - Visual selection UI

2. **Icon Collections**
   - Save frequently used icons
   - Create collections by category
   - Quick access to favorites

3. **Usage Analytics**
   - Track API usage
   - Monitor rate limits
   - Display statistics

4. **Caching Layer**
   - Cache search results
   - Reduce API calls
   - Improve performance

5. **Custom Fields Integration**
   - Icon picker for ACF
   - Meta box for posts
   - Custom field support

## Troubleshooting

### Common Issues

**"Noun Project API credentials not configured"**
- Solution: Navigate to Noun Project menu and enter credentials

**API request fails (401 Unauthorized)**
- Verify credentials are correct
- Check that your API app is active on Noun Project
- Ensure no typos in key/secret

**API request fails (429 Too Many Requests)**
- You've exceeded rate limits
- Wait before making more requests
- Implement caching in your plugin

**Connection test fails**
- Verify server can make outbound HTTPS requests
- Check firewall settings
- Ensure thenounproject.com is not blocked

## Resources

- [Noun Project Developer Dashboard](https://thenounproject.com/developers/apps)
- [Noun Project API Documentation](https://thenounproject.com/developers/api-docs)
- [WordPress HTTP API](https://developer.wordpress.org/plugins/http-api/)

## Version History

### 1.0.0 (December 2, 2025)
- Initial Noun Project API integration in Admin Console Fixes plugin
- Shared API access across all TwinTack plugins
- API credentials configuration
- Connection testing
- Core API methods (search, get, download, save)
- Standalone admin menu
- Comprehensive documentation

## Credits

- The Noun Project API by Noun Project, Inc.
- Integration developed for TwinTack plugin ecosystem
- Part of TwinTack Admin Console Fixes plugin

