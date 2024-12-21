<?php
/**
 * Template part for displaying flexible content layouts
 *
 * @package twintack2025
 */

if (have_rows('content_configurations')) :
    while (have_rows('content_configurations')) : the_row();
        
        if (get_row_layout() == 'full_width_callout') :
            $title = get_sub_field('callout_title');
            $content = get_sub_field('callout_content');
            $image = get_sub_field('callout_image');
            $cta = get_sub_field('callout_cta');
            $layout = get_sub_field('callout_layout');
            $id = get_sub_field('callout_id');
            ?>
            
            <section class="full-width-callout layout-<?php echo esc_attr($layout); ?>" 
                <?php echo $id ? 'id="' . esc_attr($id) . '"' : ''; ?>
                <?php echo ($layout === 'full' && $image) ? 'style="background-image: url(' . esc_url($image) . ');"' : ''; ?>>
                <div class="container">
                    <div class="callout-wrapper">
                        <?php if ($image && $layout === 'left') : ?>
                            <div class="callout-media">
                                <img src="<?php echo esc_url($image); ?>" alt="" class="callout-image">
                            </div>
                        <?php endif; ?>

                        <div class="callout-content">
                            <?php if ($title) : ?>
                                <h2 class="callout-title"><?php echo esc_html($title); ?></h2>
                            <?php endif; ?>

                            <?php if ($content) : ?>
                                <div class="callout-text">
                                    <?php echo wp_kses_post($content); ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($cta) : ?>
                                <a href="<?php echo esc_url($cta); ?>" class="button">Learn More</a>
                            <?php endif; ?>
                        </div>

                        <?php if ($image && $layout === 'right') : ?>
                            <div class="callout-media">
                                <img src="<?php echo esc_url($image); ?>" alt="" class="callout-image">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

        <?php elseif (get_row_layout() == 'partner_display') :
            $partners = get_sub_field('partners');
            if ($partners) : ?>
                <section class="partners-display">
                    <div class="container">
                        <div class="partners-grid">
                            <?php foreach ($partners as $partner) :
                                $logo = get_field('partner_logo', $partner->ID);
                                $name = get_field('partner_name', $partner->ID);
                                $cta = get_field('partner_cta', $partner->ID);
                                ?>
                                <div class="partner-card">
                                    <?php if ($logo) : ?>
                                        <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($name); ?>" class="partner-logo">
                                    <?php endif; ?>
                                    
                                    <?php if ($name) : ?>
                                        <h3 class="partner-name"><?php echo esc_html($name); ?></h3>
                                    <?php endif; ?>

                                    <?php if ($cta) : ?>
                                        <a href="<?php echo esc_url($cta); ?>" class="partner-link">Learn More</a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            <?php endif;
        endif;

    endwhile;
endif; 