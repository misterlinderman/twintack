<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Add this code to handle the variation selection
add_action('wp_footer', function() {
    if (isset($_GET['variation_id'])) {
        $variation_id = absint($_GET['variation_id']);
        $variation = new WC_Product_Variation($variation_id);
        if ($variation) {
            $attributes = $variation->get_variation_attributes();
            ?>
            <script>
            jQuery(document).ready(function($) {
                // Wait for variations to be initialized
                setTimeout(function() {
                    // Set each attribute in the form
                    <?php foreach ($attributes as $attribute_name => $attribute_value) : ?>
                    $('select[name="<?php echo esc_js($attribute_name); ?>"]').val('<?php echo esc_js($attribute_value); ?>').trigger('change');
                    <?php endforeach; ?>
                }, 100);
            });
            </script>
            <?php
        }
    }
});

// Check for ACF header first
if (have_rows('header_configuration')) {
    get_template_part('template-parts/header/header', 'flexible');
} else {
    // Fallback to default product header
    get_template_part('template-parts/header/header', 'product');
}

?>
<div class="page-wrapper">
    <main class="main">
        <div class="container">
            <?php while (have_posts()) :
                the_post();
                    wc_get_template_part('content', 'single-product');
                endwhile;
            ?>
        </div>
    </main>
</div>
<?php
get_footer(); 