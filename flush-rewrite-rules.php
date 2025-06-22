<?php
/**
 * Force flush WordPress rewrite rules
 * This can fix REST API 404 errors for custom post types
 */

// This would typically be run in WordPress admin or via WP-CLI
// But we can create it as a reference

echo "WordPress Rewrite Rules Flush Script\n";
echo "===================================\n";
echo "This script should be run in WordPress context to fix REST API issues.\n\n";

echo "To fix the Make.com WordPress connection:\n";
echo "1. Log into your WordPress admin\n";
echo "2. Go to Settings > Permalinks\n";
echo "3. Click 'Save Changes' (this flushes rewrite rules)\n";
echo "4. Or run this in WordPress:\n";
echo "   flush_rewrite_rules();\n\n";

echo "Alternative: Add this to your theme's functions.php temporarily:\n";
echo "add_action('init', function() {\n";
echo "    if (get_option('force_flush_rewrite_rules') !== '1') {\n";
echo "        flush_rewrite_rules();\n";
echo "        update_option('force_flush_rewrite_rules', '1');\n";
echo "    }\n";
echo "});\n\n";

echo "This ensures REST API endpoints are properly registered.\n";
?> 