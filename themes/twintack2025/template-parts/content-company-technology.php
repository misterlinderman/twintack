<?php
/**
 * Template part for displaying technology content on the company page
 *
 * @package twintack2025
 */

// Check if ACF is active before trying to use its functions
if (!function_exists('have_rows')) {
    return;
}

// Default title if none is provided
$tech_title = "Our Technology";
$tech_content = "";
$tech_layout = "full";
$tech_image = "";

// Check if technology content exists in the flexible content
if (have_rows('content_configurations')) : 
    $tech_section_found = false;
    
    // First loop to find a dedicated technology section
    while (have_rows('content_configurations')) : the_row();
        if (get_row_layout() == 'full_width_content' && get_sub_field('content_id') == 'technology') {
            $tech_section_found = true;
            $tech_title = get_sub_field('content_title') ? get_sub_field('content_title') : $tech_title;
            $tech_content = get_sub_field('custom_content');
            $tech_layout = get_sub_field('content_layout');
            $tech_image = get_sub_field('support_image');
            break;
        }
    endwhile;
    
    // If no dedicated technology section found, use the first full_width_content
    if (!$tech_section_found) {
        reset_rows();
        while (have_rows('content_configurations')) : the_row();
            if (get_row_layout() == 'full_width_content') {
                $tech_title = get_sub_field('content_title') ? get_sub_field('content_title') : $tech_title;
                $tech_content = get_sub_field('custom_content');
                $tech_layout = get_sub_field('content_layout');
                $tech_image = get_sub_field('support_image');
                break;
            }
        endwhile;
    }
endif;
?>

<section id="technology" class="technology-section full-width-content layout-<?php echo esc_attr($tech_layout); ?>">
    <h2 class="section-title"><?php echo esc_html($tech_title); ?></h2>
    
    <div class="content-wrapper">
        <?php if ($tech_image && $tech_layout === 'left') : ?>
            <div class="technology-media">
                <img src="<?php echo esc_url($tech_image); ?>" alt="<?php echo esc_attr($tech_title); ?>" class="technology-image">
            </div>
        <?php endif; ?>
        
        <div class="technology-content">
            <?php if ($tech_content) : ?>
                <div class="technology-text">
                    <?php echo wp_kses_post($tech_content); ?>
                </div>
            <?php else : ?>
                <div class="technology-text">
                    <p>TwinTack utilizes cutting-edge technology to create versatile, high-performance solutions for various sports. Our proprietary manufacturing process ensures consistency, durability, and optimal performance in every product we make.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($tech_image && $tech_layout === 'right') : ?>
            <div class="technology-media">
                <img src="<?php echo esc_url($tech_image); ?>" alt="<?php echo esc_attr($tech_title); ?>" class="technology-image">
            </div>
        <?php endif; ?>
    </div>
</section> 