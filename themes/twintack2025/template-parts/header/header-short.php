<?php
/**
 * Short Header Template
 * A shorter header for pages with less dynamic content
 *
 * @package twintack2025
 */

// Get custom header data
$header_title = get_field('header_title') ?: get_the_title();
$header_description = get_field('header_description') ?: '';
$header_background = get_field('header_background_image');
?>

<header class="short-header">
    <?php if ($header_background) : ?>
        <?php 
        // Clean the URL to remove problematic parameters
        $clean_url = $header_background['url'];
        // Remove any query parameters that might cause QUIC issues
        $clean_url = strtok($clean_url, '?');
        ?>
        <div class="header-background" style="background-image: url('<?php echo esc_url($clean_url); ?>');"></div>
    <?php endif; ?>
    
    <div class="header-content">
        <div class="container">
            <h1 class="header-title"><?php echo esc_html($header_title); ?></h1>
            <?php if ($header_description) : ?>
                <p class="header-description"><?php echo esc_html($header_description); ?></p>
            <?php endif; ?>
        </div>
    </div>
</header>
