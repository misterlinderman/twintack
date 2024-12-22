<?php
// Create this as woocommerce/content-product-variation.php in your theme

defined('ABSPATH') || exit;

global $product;
$variation_id = $post->ID;
$parent_product = wc_get_product($post->post_parent);
if (!$parent_product) return;

$variation = new WC_Product_Variation($variation_id);
$variation_data = $variation->get_data();
?>

<li <?php wc_product_class('product-variation', $variation); ?>>
    <a href="<?php echo esc_url(add_query_arg('variation_id', $variation_id, get_permalink($parent_product->get_id()))); ?>">
        <?php
        echo $variation->get_image('woocommerce_thumbnail');
        echo '<h2 class="woocommerce-loop-product__title">' . $parent_product->get_title();
        
        if ($variation_data['attributes']) {
            echo ' - ' . implode(', ', $variation_data['attributes']);
        }
        echo '</h2>';
        
        echo $variation->get_price_html();
        ?>
    </a>
</li>
<?php
