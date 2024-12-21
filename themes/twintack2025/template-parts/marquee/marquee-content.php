<?php
// Get the selected header configurations from a page
$header_configs = get_field('select_header_configuration');

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
                $callout_title = get_field('callout_content_title', $config->ID);
                $callout_content = get_field('callout_content', $config->ID);
            ?>
                <div class="marquee-slide <?php echo ($index === 0) ? 'active' : ''; ?>" 
                     <?php if ($background_image) : ?>
                         style="background-image: url('<?php echo esc_url($background_image); ?>');"
                     <?php endif; ?>>
                    
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