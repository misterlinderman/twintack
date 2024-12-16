<?php
get_header('baseball');

// Get category-specific marquee slides
$category_slides = get_term_meta(get_queried_object_id(), 'category_marquee_slides', true);
?>

<div class="category-hero baseball">
    <div class="hero-marquee">
        <?php if (!empty($category_slides)) : 
            foreach ($category_slides as $slide) : ?>
                <div class="marquee-slide" style="background-image: url(<?php echo esc_url($slide['image']); ?>)">
                    <div class="container">
                        <h1><?php echo esc_html($slide['title']); ?></h1>
                        <p><?php echo esc_html($slide['description']); ?></p>
                        <?php if (!empty($slide['link'])) : ?>
                            <a href="<?php echo esc_url($slide['link']); ?>" class="hero-cta">Shop Now</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach;
        endif; ?>
    </div>
</div>

<div class="category-navigation">
    <div class="container">
        <?php
        wp_nav_menu(array(
            'theme_location' => 'baseball',
            'container_class' => 'category-menu',
            'menu_class' => 'category-menu-items'
        ));
        ?>
    </div>
</div>

<div class="category-content">
    <?php do_action('twintack_before_category_content'); ?>
    
    <div class="container">
        <?php if (have_posts()) : ?>
            <div class="product-grid">
                <?php while (have_posts()) : the_post();
                    wc_get_template_part('content', 'product');
                endwhile; ?>
            </div>
            <?php do_action('twintack_after_shop_loop'); ?>
        <?php else :
            do_action('twintack_no_products_found');
        endif; ?>
    </div>
</div>

<?php get_footer('baseball'); ?> 