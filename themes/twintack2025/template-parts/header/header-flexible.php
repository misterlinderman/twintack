<?php
if (have_rows('header_configuration')):
    while (have_rows('header_configuration')) : the_row();
        
        if (get_row_layout() == 'image_header'):
            $background_image = get_sub_field('background_image');
            $title = get_sub_field('title');
            $content = get_sub_field('content');
            ?>
            
            <div class="category-hero flexible-header image-header" <?php if($background_image): ?>style="background-image: url('<?php echo esc_url($background_image['url']); ?>');"<?php endif; ?>>
                <div class="container">
                    <?php if($title): ?>
                        <h1><?php echo esc_html($title); ?></h1>
                    <?php endif; ?>
                    
                    <?php if($content): ?>
                        <div class="header-content">
                            <?php echo wp_kses_post($content); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif (get_row_layout() == 'video_header'):
            $video_embed = get_sub_field('video_embed');
            $title = get_sub_field('title');
            $content = get_sub_field('content');
            ?>

            <div class="category-hero flexible-header video-header">
                <div class="video-container">
                    <?php echo do_shortcode($video_embed); ?>
                </div>
                <div class="container">
                    <?php if($title): ?>
                        <h1><?php echo esc_html($title); ?></h1>
                    <?php endif; ?>
                    
                    <?php if($content): ?>
                        <div class="header-content">
                            <?php echo wp_kses_post($content); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif;
    endwhile;
endif; 