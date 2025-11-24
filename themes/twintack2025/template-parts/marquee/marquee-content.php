<?php
// Don't display ACF marquee on Marketing Homepage template (uses Marketing Plugin hero instead)
// Need to check multiple ways because get_page_template_slug() doesn't always work on frontpage
$page_template = get_page_template_slug();
$post_id = get_the_ID();

// Check if current page uses Marketing Homepage template
if ($page_template === 'templates/template-homepage-marketing.php') {
    return;
}

// Additional check for frontpage case
if ($post_id && get_page_template_slug($post_id) === 'templates/template-homepage-marketing.php') {
    return;
}

// Check if this is the frontpage and it's using the Marketing Homepage template
if (is_front_page() && $post_id) {
    $template = get_post_meta($post_id, '_wp_page_template', true);
    if ($template === 'templates/template-homepage-marketing.php') {
        return;
    }
}

// Get the selected header configurations from a page
$header_configs = get_field('select_marquee_configuration');

if ($header_configs) {
    // Ensure we're working with an array even if only one item is selected
    if (!is_array($header_configs)) {
        $header_configs = array($header_configs);
    }
    ?>
    <div class="site-marquee">
        <div class="marquee-slides">
            <?php foreach ($header_configs as $index => $config) : 
                $background_image = get_field('background_image', $config->ID);
                $embed_shortcode = get_field('embed_responsively_shortcode', $config->ID);
                $callout_title = get_field('callout_content_title', $config->ID);
                $product_image = get_field('product_image', $config->ID);
                $callout_content = get_field('callout_content', $config->ID);
                $tint_enabled = get_field('tint_enabled', $config->ID);
                $tint_color = get_field('tint_color', $config->ID);
                $tint_opacity = get_field('tint_opacity', $config->ID);
                
                // Set default tint values if not set
                $tint_color = $tint_color ? $tint_color : '#000000';
                $tint_opacity = $tint_opacity !== '' ? $tint_opacity : 0.3;
            ?>
                <div class="marquee-slide <?php echo ($index === 0) ? 'active' : ''; ?>" 
                     <?php if ($background_image) : ?>
                         style="background-image: url('<?php echo esc_url($background_image); ?>');"
                     <?php endif; ?>>
                    
                    <?php if ($tint_enabled && $background_image) : ?>
                        <div class="marquee-tint-overlay" 
                             style="background-color: <?php echo esc_attr($tint_color); ?>; opacity: <?php echo esc_attr($tint_opacity); ?>;"></div>
                    <?php endif; ?>
                    
                    <?php if ($embed_shortcode) : ?>
                        <div class="marquee-embed">
                            <?php echo do_shortcode($embed_shortcode); ?>
                        </div>
                    <?php endif; ?>

                    <div class="marquee-callout">
                        <?php if ($callout_title) : ?>
                            <h2><?php echo esc_html($callout_title); ?></h2>
                        <?php endif; ?>

                        <?php if ($product_image) : ?>
                            <div class="marquee-product-image">
                                <img src="<?php echo esc_url($product_image); ?>" alt="Featured Product">
                            </div>
                        <?php endif; ?>

                        <?php if ($callout_content) : ?>
                            <div class="callout-content">
                                <?php echo wp_kses_post($callout_content); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (have_rows('callout_links', $config->ID)) : ?>
                        <div class="marquee-links">
                            <?php while (have_rows('callout_links', $config->ID)) : the_row(); ?>
                                <a href="<?php echo esc_url(get_sub_field('callout_link_url')); ?>" class="marquee-link">
                                    <?php echo esc_html(get_sub_field('callout_link_text')); ?>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (count($header_configs) > 1) : ?>
            <div class="marquee-navigation">
                <?php foreach ($header_configs as $index => $config) : ?>
                    <button class="marquee-nav-dot <?php echo ($index === 0) ? 'active' : ''; ?>" 
                            data-slide="<?php echo esc_attr($index); ?>">
                        <span class="screen-reader-text">Slide <?php echo esc_html($index + 1); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
?>