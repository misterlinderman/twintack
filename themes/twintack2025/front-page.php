<?php
/**
 * Front Page Template
 * 
 * This file takes precedence when a static page is set as the homepage.
 * We check if the frontpage is using the Marketing Homepage template,
 * and if so, we load that template instead.
 */

// Check if the frontpage is using the Marketing Homepage template
$page_id = get_option('page_on_front');
if ($page_id) {
    $page_template = get_post_meta($page_id, '_wp_page_template', true);
    
    if ($page_template === 'templates/template-homepage-marketing.php') {
        include(locate_template('templates/template-homepage-marketing.php'));
        return;
    }
    if ($page_template === 'templates/template-homepage-v2.php') {
        include(locate_template('templates/template-homepage-v2.php'));
        return;
    }
}

// Otherwise, load the default frontpage content
get_header();

// Get other homepage sections
get_template_part('template-parts/content', 'flexible');

// Add blog roll section before footer
get_template_part('template-parts/content', 'blog-roll');

get_footer(); 