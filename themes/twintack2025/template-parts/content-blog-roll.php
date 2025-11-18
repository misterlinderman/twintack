<?php
/**
 * Template part for displaying blog roll on homepage
 *
 * @package twintack2025
 */

// Only display on the front page
if (!is_front_page()) {
    return;
}

// Get recent blog posts
$recent_posts = get_posts(array(
    'numberposts' => 5,
    'post_status' => 'publish',
    'orderby' => 'date',
    'order' => 'DESC'
));

// Exit if no posts found
if (empty($recent_posts)) {
    return;
}

?>

<section class="homepage-blog-roll">
    <div class="container">
        <div class="blog-roll-header">
            <h2 class="info-block-title">Latest From TwinTack</h2>
        </div>
        
        <div class="twintack-blog-roll layout-grid">
            <div class="blog-roll-grid">
                <?php foreach ($recent_posts as $post) : ?>
                    <article class="blog-roll-item">
                        <?php
                        $featured_image_url = '';
                        $has_image_class = '';
                        
                        // Get featured image
                        if (has_post_thumbnail($post->ID)) {
                            $featured_image_url = get_the_post_thumbnail_url($post->ID, 'large');
                            if ($featured_image_url) {
                                $has_image_class = ' has-featured-image';
                            }
                        }
                        ?>
                        <div class="blog-roll-image<?php echo esc_attr($has_image_class); ?>" <?php if ($featured_image_url) : ?>style="background-image: url('<?php echo esc_url($featured_image_url); ?>');"<?php endif; ?>>
                            <a href="<?php echo get_permalink($post->ID); ?>" class="image-link"></a>
                        </div>
                        
                        <div class="blog-roll-content">
                            <h3 class="blog-roll-title">
                                <a href="<?php echo get_permalink($post->ID); ?>"><?php echo esc_html($post->post_title); ?></a>
                            </h3>
                            
                            <div class="blog-roll-meta">
                                <span class="blog-roll-date"><?php echo get_the_date('', $post->ID); ?></span>
                                <?php
                                $categories = get_the_category($post->ID);
                                if (!empty($categories)) :
                                ?>
                                    <span class="blog-roll-category">
                                        in <?php echo esc_html($categories[0]->name); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="blog-roll-excerpt">
                                <?php echo esc_html(wp_trim_words($post->post_excerpt ?: $post->post_content, 20, '...')); ?>
                            </div>
                            
                            <a href="<?php echo get_permalink($post->ID); ?>" class="blog-roll-read-more">
                                Read More
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="blog-roll-footer">
            <a href="<?php echo get_permalink(get_option('page_for_posts')); ?>" class="view-all-posts">
                View All Posts
            </a>
        </div>
    </div>
</section> 