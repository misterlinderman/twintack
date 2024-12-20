<?php
// Get the selected header configuration from a page
$header_config = get_field('select_header_configuration');

if ($header_config) {
    $image_or_video = get_field('image_or_video', $header_config->ID);
    $background_image = get_field('background_image', $header_config->ID);
    $embed_shortcode = get_field('embed_responsively_shortcode', $header_config->ID);
    
    ?>
    <div class="site-marquee">
        <?php if ($header_callout === 'on:On') : ?>
            <div class="marquee-callout">
                <?php if ($callout_title) : ?>
                    <h2><?php echo esc_html($callout_title); ?></h2>
                <?php endif; ?>
                
                <?php if ($callout_content) : ?>
                    <div class="callout-content">
                        <?php echo wp_kses_post($callout_content); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (get_field('header_callout_links', $header_config->ID) === 'on:On') : ?>
            <?php if (have_rows('callout_links', $header_config->ID)) : ?>
                <div class="marquee-links">
                    <?php while (have_rows('callout_links', $header_config->ID)) : the_row(); ?>
                        <a href="<?php echo esc_url(get_sub_field('callout_link_url')); ?>" class="marquee-link">
                            <?php echo esc_html(get_sub_field('callout_link_text')); ?>
                        </a>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}
?> 