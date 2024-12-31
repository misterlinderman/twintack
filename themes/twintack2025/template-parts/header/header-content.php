<?php
// Get the selected header configuration from a page
$header_config = get_field('select_marquee_configuration');

if ($header_config) {
    // Get fields from the header configuration post
    $image_or_video = get_field('image_or_video', $header_config->ID);
    $background_image = get_field('background_image', $header_config->ID);
    $embed_shortcode = get_field('embed_responsively_shortcode', $header_config->ID);
    
    // Check if header callout is enabled
    $header_callout = get_field('header_callout_content', $header_config->ID);
    
    if ($header_callout === 'on:On') {
        $callout_title = get_field('callout_content_title', $header_config->ID);
        $callout_content = get_field('callout_content', $header_config->ID);
        
        // Output the callout
        ?>
        <div class="header-callout">
            <?php if ($callout_title) : ?>
                <h2><?php echo esc_html($callout_title); ?></h2>
            <?php endif; ?>
            
            <?php if ($callout_content) : ?>
                <div class="callout-content">
                    <?php echo wp_kses_post($callout_content); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    // Check for and output callout links
    if (get_field('header_callout_links', $header_config->ID) === 'on:On') {
        if (have_rows('callout_links', $header_config->ID)) : ?>
            <div class="callout-links">
                <?php while (have_rows('callout_links', $header_config->ID)) : the_row(); ?>
                    <a href="<?php echo esc_url(get_sub_field('callout_link_url')); ?>" class="callout-link">
                        <?php echo esc_html(get_sub_field('callout_link_text')); ?>
                    </a>
                <?php endwhile; ?>
            </div>
        <?php endif;
    }
}