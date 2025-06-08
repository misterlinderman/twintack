<?php
require_once('wp-load.php');

echo "<h2>Grip Design REST API Test</h2>";

// Test 1: Direct database query
echo "<h3>1. Database Query</h3>";
$posts = get_posts(array(
    'post_type' => 'grip_design',
    'post_status' => array('publish', 'draft', 'pending', 'private', 'trash'),
    'posts_per_page' => -1,
    'meta_query' => array()
));

echo "Found " . count($posts) . " grip designs in database:<br>";
foreach($posts as $post) {
    echo "- ID: {$post->ID}, Status: {$post->post_status}, Title: {$post->post_title}, Author: {$post->post_author}<br>";
}

echo "<br>";

// Test 2: REST API route check
echo "<h3>2. REST API Route Check</h3>";
$server = rest_get_server();
$routes = $server->get_routes();

if (isset($routes['/wp/v2/grip-designs'])) {
    echo "✅ /wp/v2/grip-designs route exists<br>";
} else {
    echo "❌ /wp/v2/grip-designs route missing<br>";
}

// Test 3: REST API query with various statuses
echo "<h3>3. REST API Query Test</h3>";

$statuses = array('publish', 'draft', 'pending', 'private');
foreach($statuses as $status) {
    $request = new WP_REST_Request('GET', '/wp/v2/grip-designs');
    $request->set_param('status', $status);
    
    $response = $server->dispatch($request);
    
    if (is_wp_error($response)) {
        echo "❌ Error for status '{$status}': " . $response->get_error_message() . "<br>";
    } else {
        $data = $response->get_data();
        echo "Status '{$status}': " . count($data) . " posts<br>";
    }
}

// Test 4: Full REST API call
echo "<h3>4. Full REST API Call</h3>";
$request = new WP_REST_Request('GET', '/wp/v2/grip-designs');
$response = $server->dispatch($request);

if (is_wp_error($response)) {
    echo "❌ Error: " . $response->get_error_message() . "<br>";
} else {
    $data = $response->get_data();
    echo "✅ Found " . count($data) . " grip designs via REST API<br>";
    
    foreach($data as $design) {
        echo "- ID: {$design['id']}, Status: {$design['status']}, Title: {$design['title']['rendered']}<br>";
        
        // Show some meta fields
        if(isset($design['_grip_customer_name'])) {
            echo "  Customer: {$design['_grip_customer_name']}<br>";
        }
        if(isset($design['_grip_team_name'])) {
            echo "  Team: {$design['_grip_team_name']}<br>";
        }
    }
}

// Test 5: Direct URL test
echo "<h3>5. Direct URL Test</h3>";
$rest_url = home_url('/wp-json/wp/v2/grip-designs');
echo "Direct URL: <a href='{$rest_url}' target='_blank'>{$rest_url}</a><br>";

?> 