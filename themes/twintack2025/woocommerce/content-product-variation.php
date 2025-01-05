<?php
// Create this as woocommerce/content-product-variation.php in your theme

defined('ABSPATH') || exit;

global $product;
$variation_id = $post->ID;
$parent_product = wc_get_product($post->post_parent);
if (!$parent_product) return;

$variation = new WC_Product_Variation($variation_id);
$variation_data = $variation->get_data();

// Get the category
$terms = get_the_terms($parent_product->get_id(), 'product_cat');
$category = '';
if ($terms) {
    foreach ($terms as $term) {
        if ($term->slug === 'baseball' || $term->slug === 'fishing') {
            $category = $term->slug;
            break;
        }
    }
}
?>

<li class="product type-product product-variation <?php echo esc_attr($category); ?>">
    <a href="<?php echo esc_url(add_query_arg(['variation_id' => $variation_id], get_permalink($parent_product->get_id()))); ?>" class="woocommerce-LoopProduct-link woocommerce-loop-product__link">
        <?php echo $variation->get_image('woocommerce_thumbnail'); ?>
        <h2 class="woocommerce-loop-product__title">
            <?php 
            echo $parent_product->get_title();
            if ($variation_data['attributes']) {
                echo ' - ' . implode(', ', $variation_data['attributes']);
            }
            ?>
        </h2>
        <span class="price"><?php echo $variation->get_price_html(); ?></span>
    </a>
</li>
<?php
