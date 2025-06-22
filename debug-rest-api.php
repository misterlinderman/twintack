<?php
/**
 * Debug script for REST API issues
 * Place this in the WordPress root directory and access via browser
 */

// WordPress environment
require_once('wp-config.php');
require_once('wp-blog-header.php');

header('Content-Type: text/html; charset=utf-8');

echo '<h1>REST API Debug Information</h1>';

// Check if grip_design post type is registered
echo '<h2>1. Post Type Registration</h2>';
$post_types = get_post_types(array(), 'objects');
if (isset($post_types['grip_design'])) {
    echo '<p style="color: green;">✓ grip_design post type is registered</p>';
    $grip_design = $post_types['grip_design'];
    echo '<ul>';
    echo '<li><strong>Public:</strong> ' . ($grip_design->public ? 'Yes' : 'No') . '</li>';
    echo '<li><strong>Show in REST:</strong> ' . ($grip_design->show_in_rest ? 'Yes' : 'No') . '</li>';
    echo '<li><strong>REST Base:</strong> ' . ($grip_design->rest_base ?: 'Default') . '</li>';
    echo '<li><strong>REST Controller:</strong> ' . ($grip_design->rest_controller_class ?: 'Default') . '</li>';
    echo '</ul>';
} else {
    echo '<p style="color: red;">✗ grip_design post type is NOT registered</p>';
}

// Check REST API routes
echo '<h2>2. REST API Routes</h2>';
$rest_server = rest_get_server();
$routes = $rest_server->get_routes();

$grip_routes = array();
foreach ($routes as $route => $handlers) {
    if (strpos($route, 'grip') !== false) {
        $grip_routes[$route] = $handlers;
    }
}

if (!empty($grip_routes)) {
    echo '<p style="color: green;">✓ Found grip-related REST routes:</p>';
    echo '<ul>';
    foreach ($grip_routes as $route => $handlers) {
        echo '<li><strong>' . esc_html($route) . '</strong></li>';
        foreach ($handlers as $handler) {
            if (isset($handler['methods'])) {
                echo '<ul><li>Methods: ' . implode(', ', array_keys($handler['methods'])) . '</li></ul>';
            }
        }
    }
    echo '</ul>';
} else {
    echo '<p style="color: red;">✗ No grip-related REST routes found</p>';
}

// Check for grip design posts
echo '<h2>3. Grip Design Posts</h2>';
$grip_posts = get_posts(array(
    'post_type' => 'grip_design',
    'posts_per_page' => 10,
    'post_status' => array('publish', 'draft', 'pending', 'private')
));

if (!empty($grip_posts)) {
    echo '<p style="color: green;">✓ Found ' . count($grip_posts) . ' grip design posts</p>';
    echo '<ul>';
    foreach ($grip_posts as $post) {
        echo '<li>ID: ' . $post->ID . ' - ' . esc_html($post->post_title) . ' (Status: ' . $post->post_status . ')</li>';
    }
    echo '</ul>';
} else {
    echo '<p style="color: red;">✗ No grip design posts found</p>';
}

// Test REST API directly
echo '<h2>4. Direct REST API Test</h2>';
$rest_url = home_url('/wp-json/wp/v2/grip-designs');
echo '<p><strong>REST URL:</strong> <a href="' . esc_url($rest_url) . '" target="_blank">' . esc_html($rest_url) . '</a></p>';

// Try to make a request
$response = wp_remote_get($rest_url);
if (is_wp_error($response)) {
    echo '<p style="color: red;">✗ REST API request failed: ' . $response->get_error_message() . '</p>';
} else {
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    if ($code === 200) {
        $data = json_decode($body, true);
        if ($data && is_array($data)) {
            echo '<p style="color: green;">✓ REST API working - Found ' . count($data) . ' items</p>';
        } else {
            echo '<p style="color: orange;">⚠ REST API returned 200 but no valid JSON data</p>';
        }
    } else {
        echo '<p style="color: red;">✗ REST API returned status code: ' . $code . '</p>';
        echo '<pre>' . esc_html($body) . '</pre>';
    }
}

// Check rewrite rules
echo '<h2>5. Rewrite Rules</h2>';
$rewrite_rules = get_option('rewrite_rules');
$grip_rules = array();
foreach ($rewrite_rules as $rule => $rewrite) {
    if (strpos($rule, 'grip') !== false || strpos($rewrite, 'grip') !== false) {
        $grip_rules[$rule] = $rewrite;
    }
}

if (!empty($grip_rules)) {
    echo '<p style="color: green;">✓ Found grip-related rewrite rules:</p>';
    echo '<ul>';
    foreach ($grip_rules as $rule => $rewrite) {
        echo '<li><strong>' . esc_html($rule) . '</strong> → ' . esc_html($rewrite) . '</li>';
    }
    echo '</ul>';
} else {
    echo '<p style="color: red;">✗ No grip-related rewrite rules found</p>';
}

// Check plugin status
echo '<h2>6. Plugin Status</h2>';
$active_plugins = get_option('active_plugins');
$grip_plugin_active = false;
foreach ($active_plugins as $plugin) {
    if (strpos($plugin, 'grip') !== false || strpos($plugin, 'twintack') !== false) {
        echo '<p style="color: green;">✓ Found plugin: ' . esc_html($plugin) . '</p>';
        $grip_plugin_active = true;
    }
}

if (!$grip_plugin_active) {
    echo '<p style="color: red;">✗ No grip/twintack plugins found in active plugins</p>';
}

echo '<h2>7. Debugging Actions</h2>';
echo '<p><a href="?flush=1" style="background: #0073aa; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px;">Flush Rewrite Rules</a></p>';

if (isset($_GET['flush'])) {
    flush_rewrite_rules();
    echo '<p style="color: green;">✓ Rewrite rules flushed. <a href="' . remove_query_arg('flush') . '">Refresh page</a></p>';
}
?> 