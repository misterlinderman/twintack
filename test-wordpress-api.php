<?php
/**
 * Test WordPress REST API connectivity for Make.com
 * This will help diagnose why the WordPress "Watch Posts" module is failing
 */

$base_url = 'https://meo.efe.mybluehost.me';
$api_key = 'twintack-monday-2024';

echo "Testing WordPress REST API for Make.com\n";
echo "=====================================\n";
echo "Base URL: " . $base_url . "\n";
echo "API Key: " . $api_key . "\n\n";

// Test 1: Basic WordPress REST API
echo "1. Testing basic WordPress REST API...\n";
$response = wp_remote_get($base_url . '/wp-json/wp/v2/posts?per_page=1');
if (is_wp_error($response)) {
    echo "❌ Basic API Error: " . $response->get_error_message() . "\n";
} else {
    $code = wp_remote_retrieve_response_code($response);
    echo "✅ Basic API Response: " . $code . "\n";
}

// Test 2: Grip Design posts (what Make.com is trying to access)
echo "\n2. Testing Grip Design posts...\n";
$response = wp_remote_get($base_url . '/wp-json/wp/v2/grip_design?per_page=5');
if (is_wp_error($response)) {
    echo "❌ Grip Design API Error: " . $response->get_error_message() . "\n";
} else {
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    echo "Response Code: " . $code . "\n";
    
    if ($code == 200) {
        $data = json_decode($body, true);
        echo "✅ Found " . count($data) . " grip design posts\n";
        if (!empty($data)) {
            echo "Latest post: " . $data[0]['title']['rendered'] . "\n";
        }
    } else {
        echo "❌ Error Response: " . $body . "\n";
    }
}

// Test 3: Check if custom post type is public
echo "\n3. Testing custom post type registration...\n";
$post_types = get_post_types(array('public' => true), 'objects');
if (isset($post_types['grip_design'])) {
    echo "✅ Grip Design post type is registered and public\n";
    echo "REST Base: " . $post_types['grip_design']->rest_base . "\n";
    echo "Show in REST: " . ($post_types['grip_design']->show_in_rest ? 'Yes' : 'No') . "\n";
} else {
    echo "❌ Grip Design post type not found or not public\n";
}

// Test 4: Test with authentication (what Make.com uses)
echo "\n4. Testing with API authentication...\n";
$response = wp_remote_get($base_url . '/wp-json/wp/v2/grip_design?per_page=5', array(
    'headers' => array(
        'X-API-Key' => $api_key
    )
));

if (is_wp_error($response)) {
    echo "❌ Authenticated API Error: " . $response->get_error_message() . "\n";
} else {
    $code = wp_remote_retrieve_response_code($response);
    echo "Authenticated Response Code: " . $code . "\n";
}

// Test 5: Check what Make.com is actually trying to access
echo "\n5. Testing Make.com specific endpoints...\n";

$endpoints_to_test = array(
    '/wp-json/' => 'REST API Root',
    '/wp-json/wp/v2/' => 'WordPress REST API v2',
    '/wp-json/wp/v2/posts' => 'Posts endpoint',
    '/wp-json/wp/v2/grip_design' => 'Grip Design endpoint',
    '/wp-json/twintack/v1/' => 'TwinTack custom API'
);

foreach ($endpoints_to_test as $endpoint => $description) {
    $response = wp_remote_get($base_url . $endpoint);
    $code = wp_remote_retrieve_response_code($response);
    
    if ($code == 200) {
        echo "✅ " . $description . ": " . $code . "\n";
    } else {
        echo "❌ " . $description . ": " . $code . "\n";
    }
}

// Test 6: Check recent grip design posts
echo "\n6. Checking for recent grip design posts...\n";
$recent_grips = get_posts(array(
    'post_type' => 'grip_design',
    'posts_per_page' => 5,
    'post_status' => 'any'
));

if ($recent_grips) {
    echo "✅ Found " . count($recent_grips) . " grip design posts:\n";
    foreach ($recent_grips as $grip) {
        echo "- ID: " . $grip->ID . " | Title: " . $grip->post_title . " | Status: " . $grip->post_status . "\n";
    }
} else {
    echo "❌ No grip design posts found\n";
}

echo "\n=== DIAGNOSIS ===\n";
echo "If you see 404 errors above, the issue is likely:\n";
echo "1. Custom post type not properly registered for REST API\n";
echo "2. Permalink structure needs to be flushed\n";
echo "3. WordPress REST API is disabled\n";
echo "4. Make.com is using wrong endpoint URL\n";
?> 