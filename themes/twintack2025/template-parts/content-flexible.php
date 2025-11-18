<?php
/**
 * Template part for displaying flexible content layouts
 *
 * @package twintack2025
 */

// Check if ACF is active before trying to use its functions
if (!function_exists('get_field')) {
    return;
}

// Check if the flexible content field exists and has rows
if (have_rows('content_configurations')) :
    while (have_rows('content_configurations')) : the_row();
        
        // Full Width Callout Layout
        if (get_row_layout() == 'full_width_callout') :
            $title = get_sub_field('callout_title');
            $content = get_sub_field('callout_content');
            $image = get_sub_field('callout_image');
            $cta = get_sub_field('callout_cta');
            $layout = get_sub_field('callout_layout');
            $id = get_sub_field('callout_id');
            ?>
            
            <section class="full-width-callout layout-<?php echo esc_attr($layout); ?>" 
                <?php if ($id) : ?>id="<?php echo esc_attr($id); ?>"<?php endif; ?>
                <?php if ($layout === 'full' && $image) : ?>style="background-image: url('<?php echo esc_url($image); ?>');"<?php endif; ?>>
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

        <?php 
        // Partner Display Layout
        elseif (get_row_layout() == 'partner_display') :
            $partners = get_sub_field('partners');
            if ($partners && !empty($partners)) : ?>
                <section class="partners-display">
                    <div class="container">
                        <div class="partners-grid">
                            <?php foreach ($partners as $partner) :
                                if (!$partner) continue;
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

        // Full Width Content Layout
        elseif (get_row_layout() == 'full_width_content') :
            $title = get_sub_field('content_title');
            $content = get_sub_field('custom_content');
            $layout = get_sub_field('content_layout');
            $id = get_sub_field('content_id');
            ?>
            
            <section class="full-width-content layout-<?php echo esc_attr($layout); ?>"
                <?php if ($id) : ?>id="<?php echo esc_attr($id); ?>"<?php endif; ?>>
                <div class="container">
                    <div class="content-wrapper">
                        <?php if ($title) : ?>
                            <h2 class="content-title"><?php echo esc_html($title); ?></h2>
                        <?php endif; ?>

                        <?php if ($content) : ?>
                            <div class="content-text">
                                <?php echo wp_kses_post($content); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

        <?php
        // Team Member Display Layout
        elseif (get_row_layout() == 'team_member_display') :
            $args = array(
                'post_type' => 'team-member',
                'posts_per_page' => -1,
                'post_status' => 'publish'
            );
            
            $team_members = new WP_Query($args);
            
            if ($team_members->have_posts()) : ?>
                <section class="team-member-display">
                    <div class="container">
                        <div class="team-grid">
                            <?php while ($team_members->have_posts()) : $team_members->the_post();
                                $photo = get_field('member_photo');
                                $name = get_field('member_name');
                                $role = get_field('member_role');
                                $description = get_field('member_description');
                                ?>
                                <div class="team-member-card">
                                    <?php if ($photo) : ?>
                                        <img src="<?php echo esc_url($photo); ?>" alt="<?php echo esc_attr($name); ?>" class="team-member-photo">
                                    <?php endif; ?>
                                    
                                    <?php if ($name) : ?>
                                        <h3 class="team-member-name"><?php echo esc_html($name); ?></h3>
                                    <?php endif; ?>

                                    <?php if ($role) : ?>
                                        <p class="team-member-role"><?php echo esc_html($role); ?></p>
                                    <?php endif; ?>

                                    <?php if ($description) : ?>
                                        <div class="team-member-description">
                                            <?php echo wp_kses_post($description); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                            <?php wp_reset_postdata(); ?>
                        </div>
                    </div>
                </section>
            <?php endif;

        // TwinTack Info Block Layout
        elseif (get_row_layout() == 'twintack_info_block') :
            $background_color = get_sub_field('background_color');
            $title = get_sub_field('title');
            $hero_background = get_sub_field('hero_background');
            $hero_image = get_sub_field('hero_image');
            $content = get_sub_field('content');
            $link_text = get_sub_field('link_text');
            $link_url = get_sub_field('link_url');
            $content_id = get_sub_field('content_id');
            ?>
            
            <section class="twintack-info-block" <?php if ($background_color || $content_id) : ?>
                <?php if ($background_color) : ?>style="background-color: <?php echo esc_attr($background_color); ?>"<?php endif; ?>
                <?php if ($content_id) : ?>id="<?php echo esc_attr($content_id); ?>"<?php endif; ?>
            <?php endif; ?>>
                <div class="container">
                    <?php if ($title) : ?>
                        <h2 class="info-block-title"><?php echo esc_html($title); ?></h2>
                    <?php endif; ?>

                    <div class="info-block-content">
                        <div class="info-block-hero"<?php if ($hero_background) : ?> style="background-image: url('<?php echo esc_url($hero_background); ?>'); background-size: cover; background-position: center; background-repeat: no-repeat;"<?php endif; ?>>
                            <?php if ($hero_image) : ?>
                                <img src="<?php echo esc_url($hero_image); ?>" alt="<?php echo esc_attr($title); ?>" class="hero-overlay-image">
                            <?php endif; ?>
                        </div>

                        <?php if ($content) : ?>
                            <div class="info-block-text">
                                <?php echo wp_kses_post($content); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($link_text && $link_url) : ?>
                            <div class="info-block-cta">
                                <a href="<?php echo esc_url($link_url); ?>" class="button">
                                    <?php echo esc_html($link_text); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

        <?php
        // TwinTack Callouts Layout
        elseif (get_row_layout() == 'twintack_callouts') :
            $background_color = get_sub_field('background_color');
            $content_id = get_sub_field('content_id');
            ?>
            
            <section class="twintack-callouts" <?php if ($background_color || $content_id) : ?>
                <?php if ($background_color) : ?>style="background-color: <?php echo esc_attr($background_color); ?>"<?php endif; ?>
                <?php if ($content_id) : ?>id="<?php echo esc_attr($content_id); ?>"<?php endif; ?>
            <?php endif; ?>>
                <div class="container">
                    <?php if (have_rows('twintack_callouts')) : ?>
                        <div class="callouts-grid">
                            <?php while (have_rows('twintack_callouts')) : the_row(); 
                                $title = get_sub_field('title');
                                $hero_image = get_sub_field('callout_hero_image');
                                $description = get_sub_field('description');
                                $link_text = get_sub_field('link_text');
                                $link_url = get_sub_field('link_url');
                                ?>
                                <div class="callout-card">
                                    <a href="<?php echo esc_url($link_url); ?>" class="callout-card-link">
                                        <?php if ($hero_image) : ?>
                                            <div class="callout-image">
                                                <img src="<?php echo esc_url($hero_image); ?>" alt="<?php echo esc_attr($title); ?>">
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($title) : ?>
                                            <h3 class="callout-title"><?php echo esc_html($title); ?></h3>
                                        <?php endif; ?>
                                    </a>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

        <?php
        endif;

    endwhile;
endif;
?>