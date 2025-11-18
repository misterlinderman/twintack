<?php
// Template part for handling the flexible content header
function get_flexible_header_content() {
    if (have_rows('header_configuration_manual')) :
        while (have_rows('header_configuration_manual')) : the_row();
            
            if (get_row_layout() == 'image_single') :
                $title = get_sub_field('header_title');
                $content = get_sub_field('header_content');
                $bg_image = get_sub_field('background_image');
                ?>
                <div class="header-single-image" <?php if ($bg_image) : ?>style="background-image: url('<?php echo esc_url($bg_image); ?>');"<?php endif; ?>>
                    <div class="header-content">
                        <?php if ($title) : ?>
                            <h1><?php echo esc_html($title); ?></h1>
                        <?php endif; ?>
                        
                        <?php if ($content) : ?>
                            <div class="content-area">
                                <?php echo wp_kses_post($content); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                
            elseif (get_row_layout() == 'video_single') :
                $title = get_sub_field('header_title');
                $content = get_sub_field('header_content');
                $video = get_sub_field('background_video_upload');
                $embed = get_sub_field('embed_responsively_shortcode');
                ?>
                <div class="header-single-video">
                    <?php if ($video) : ?>
                        <video autoplay muted loop playsinline class="background-video">
                            <source src="<?php echo esc_url($video); ?>" type="video/mp4">
                        </video>
                    <?php elseif ($embed) : ?>
                        <div class="video-embed">
                            <?php echo do_shortcode($embed); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="header-content">
                        <?php if ($title) : ?>
                            <h1><?php echo esc_html($title); ?></h1>
                        <?php endif; ?>
                        
                        <?php if ($content) : ?>
                            <div class="content-area">
                                <?php echo wp_kses_post($content); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            endif;
            
        endwhile;
    endif;
}