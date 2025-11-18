<?php
require_once('wp-load.php');

echo "<h1>Artwork Status System Test</h1>";

// Test 1: Check existing grip designs and their artwork status
echo "<h2>1. Current Grip Designs & Artwork Status</h2>";
$posts = get_posts(array(
    'post_type' => 'grip_design',
    'post_status' => 'any',
    'posts_per_page' => -1
));

echo "<p><strong>Found " . count($posts) . " grip designs:</strong></p>";
foreach($posts as $post) {
    $artwork_status = get_post_meta($post->ID, '_grip_artwork_status', true);
    
    echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 8px;'>";
    echo "<strong>ID:</strong> {$post->ID}<br>";
    echo "<strong>Title:</strong> {$post->post_title}<br>";
    echo "<strong>Post Status:</strong> {$post->post_status}<br>";
    echo "<strong>Artwork Status:</strong> " . ($artwork_status ?: 'NOT SET') . "<br>";
    
    // Show what the customer would see
    $artwork_status_labels = array(
        'artwork_pending'   => 'Artwork Pending',
        'pending_review'    => 'Pending Review', 
        'artwork_approved'  => 'Artwork Approved',
        'internal_review'   => 'Internal Review'
    );
    
    $display_status = isset($artwork_status_labels[$artwork_status]) 
        ? $artwork_status_labels[$artwork_status] 
        : 'Artwork Pending';
    
    echo "<strong>Customer Sees:</strong> <span style='background: #e3f2fd; padding: 4px 8px; border-radius: 4px;'>{$display_status}</span><br>";
    echo "</div>";
}

// Test 2: REST API Check
echo "<h2>2. REST API Artwork Status</h2>";
$request = new WP_REST_Request('GET', '/wp/v2/grip-designs');
$server = rest_get_server();
$response = $server->dispatch($request);

if (is_wp_error($response)) {
    echo "<p style='color: red;'>❌ REST API Error: " . $response->get_error_message() . "</p>";
} else {
    $data = $response->get_data();
    echo "<p><strong>REST API returns " . count($data) . " grip designs with artwork status:</strong></p>";
    
    foreach($data as $design) {
        echo "<div style='background: #f8f9fa; padding: 10px; margin: 5px 0; border-radius: 4px;'>";
        echo "<strong>ID:</strong> {$design['id']}<br>";
        echo "<strong>Title:</strong> {$design['title']['rendered']}<br>";
        echo "<strong>Post Status:</strong> {$design['status']}<br>";
        
        if (isset($design['artwork_status'])) {
            echo "<strong>Artwork Status:</strong> {$design['artwork_status']}<br>";
        } else {
            echo "<strong>Artwork Status:</strong> <span style='color: red;'>NOT EXPOSED</span><br>";
        }
        
        if (isset($design['artwork_status_label'])) {
            echo "<strong>Status Label:</strong> {$design['artwork_status_label']}<br>";
        } else {
            echo "<strong>Status Label:</strong> <span style='color: red;'>NOT EXPOSED</span><br>";
        }
        echo "</div>";
    }
}

// Test 3: Update Test (simulate Make.com updating status)
echo "<h2>3. Status Update Test</h2>";
if (!empty($posts)) {
    $test_post = $posts[0];
    $original_status = get_post_meta($test_post->ID, '_grip_artwork_status', true);
    
    echo "<p>Testing status update on post ID {$test_post->ID}...</p>";
    echo "<p><strong>Original Status:</strong> {$original_status}</p>";
    
    // Update to pending_review
    $update_result = update_post_meta($test_post->ID, '_grip_artwork_status', 'pending_review');
    $new_status = get_post_meta($test_post->ID, '_grip_artwork_status', true);
    
    echo "<p><strong>Updated Status:</strong> {$new_status}</p>";
    
    if ($update_result && $new_status == 'pending_review') {
        echo "<p style='color: green;'>✅ Status update successful!</p>";
        
        // Test REST API after update
        $request = new WP_REST_Request('GET', '/wp/v2/grip-designs/' . $test_post->ID);
        $response = $server->dispatch($request);
        
        if (!is_wp_error($response)) {
            $data = $response->get_data();
            echo "<p><strong>REST API shows:</strong> {$data['artwork_status']} ({$data['artwork_status_label']})</p>";
        }
        
        // Restore original status
        update_post_meta($test_post->ID, '_grip_artwork_status', $original_status);
        echo "<p><em>Restored original status: {$original_status}</em></p>";
    } else {
        echo "<p style='color: red;'>❌ Status update failed!</p>";
    }
}

// Test 4: Make.com Integration URLs
echo "<h2>4. Make.com Integration Info</h2>";
echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 8px;'>";
echo "<h3>📡 REST API Endpoints for Make.com</h3>";

$base_url = home_url('/wp-json/wp/v2/grip-designs');
echo "<p><strong>Get All Grip Designs:</strong><br>";
echo "<code>{$base_url}</code></p>";

echo "<p><strong>Update Artwork Status (POST/PUT):</strong><br>";
echo "<code>{$base_url}/[ID]</code><br>";
echo "Body: <code>{\"artwork_status\": \"artwork_approved\"}</code></p>";

echo "<h3>🎯 Artwork Status Values</h3>";
echo "<ul>";
echo "<li><code>artwork_pending</code> → 'Artwork Pending'</li>";
echo "<li><code>pending_review</code> → 'Pending Review'</li>";
echo "<li><code>artwork_approved</code> → 'Artwork Approved'</li>";
echo "<li><code>internal_review</code> → 'Internal Review'</li>";
echo "</ul>";

echo "<h3>📋 Available Fields in REST API</h3>";
echo "<ul>";
echo "<li><code>artwork_status</code> - Machine readable status</li>";
echo "<li><code>artwork_status_label</code> - Human readable label</li>";
echo "<li><code>_grip_customer_name</code> - Customer name</li>";
echo "<li><code>_grip_team_name</code> - Team/school name</li>";
echo "<li><code>_grip_monday_feedback</code> - Feedback from Monday.com</li>";
echo "<li><code>_grip_mockup_url</code> - Mockup file URL</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><em>Test completed at " . date('Y-m-d H:i:s') . "</em></p>";
?> 