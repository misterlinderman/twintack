<?php
require_once('wp-load.php');

echo "<h1>Reset Artwork Status</h1>";

// Reset the initialization flag so it runs again with the corrected logic
delete_option('twintack_artwork_status_initialized');

echo "<p>✅ Reset initialization flag</p>";

// Get all grip designs and manually set them to artwork_pending
$posts = get_posts(array(
    'post_type' => 'grip_design',
    'post_status' => 'any',
    'posts_per_page' => -1
));

echo "<p>Found " . count($posts) . " grip designs to reset...</p>";

foreach($posts as $post) {
    // Set all to artwork_pending regardless of current status
    update_post_meta($post->ID, '_grip_artwork_status', 'artwork_pending');
    echo "<p>✅ Reset ID {$post->ID}: {$post->post_title} → artwork_pending</p>";
}

echo "<h2>Verification</h2>";
echo "<p>Now all grip designs should show 'Artwork Pending' status to customers.</p>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ul>";
echo "<li>Visit customer dashboard to verify status display</li>";
echo "<li>Test REST API to ensure no critical errors</li>";
echo "<li>Use Make.com/Monday.com to update statuses as needed</li>";
echo "</ul>";

echo "<p><a href='/wp-json/wp/v2/grip-designs' target='_blank'>Test REST API</a></p>";
echo "<p><a href='/my-account/grip-designs/' target='_blank'>View Customer Dashboard</a></p>";
?> 