<?php
/**
 * Template part for displaying flexible content layouts on the Company page
 *
 * @package twintack2025
 */

// Check if ACF is active before trying to use its functions
if (!function_exists('get_field')) {
    return;
}

// We only need to output the CURRENT row, not loop through all rows
// since we're calling this template for each row from the main template

// Get the current row's layout
$layout = get_row_layout();
    
// Full Width Callout Layout
if ($layout == 'full_width_callout') :
    $title = get_sub_field('callout_title');
    $content = get_sub_field('callout_content');
    $image = get_sub_field('callout_image');
    $cta = get_sub_field('callout_cta');
    $callout_layout = get_sub_field('callout_layout');
    $id = get_sub_field('callout_id');
    ?>
    
    <section class="full-width-callout layout-<?php echo esc_attr($callout_layout); ?>" 
        <?php if ($id) : ?>id="<?php echo esc_attr($id); ?>"<?php endif; ?>
        <?php if ($callout_layout === 'full' && $image) : ?>style="background-image: url('<?php echo esc_url($image); ?>');"<?php endif; ?>>
        <div class="container">
            <div class="callout-wrapper">
                <?php if ($image && $callout_layout === 'left') : ?>
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

                <?php if ($image && $callout_layout === 'right') : ?>
                    <div class="callout-media">
                        <img src="<?php echo esc_url($image); ?>" alt="" class="callout-image">
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

<?php 
// Partner Display Layout
elseif ($layout == 'partner_display') :
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

// Full Width Content Layout - only process if not a technology section (handled separately)
elseif ($layout == 'full_width_content' && get_sub_field('content_id') != 'technology') :
    $title = get_sub_field('content_title');
    $content = get_sub_field('custom_content');
    $content_layout = get_sub_field('content_layout');
    $id = get_sub_field('content_id');
    $image = get_sub_field('support_image');
    ?>
    
    <section class="full-width-content layout-<?php echo esc_attr($content_layout); ?>"
        <?php if ($id) : ?>id="<?php echo esc_attr($id); ?>"<?php endif; ?>>
        <div class="container">
            <div class="content-wrapper">
                <h2 class="section-title"><?php echo esc_html($title); ?></h2>
                
                <?php if ($image && $content_layout === 'left') : ?>
                    <div class="content-media">
                        <img src="<?php echo esc_url($image); ?>" alt="" class="content-image">
                    </div>
                <?php endif; ?>

                <?php if ($content) : ?>
                    <div class="content-text">
                        <?php echo wp_kses_post($content); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($image && $content_layout === 'right') : ?>
                    <div class="content-media">
                        <img src="<?php echo esc_url($image); ?>" alt="" class="content-image">
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

<?php endif; ?> 