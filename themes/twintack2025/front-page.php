<?php
get_header();

// Get other homepage sections
get_template_part('template-parts/content', 'flexible');

// Add blog roll section before footer
get_template_part('template-parts/content', 'blog-roll');

get_footer(); 