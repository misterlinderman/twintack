// assets/js/product-gallery.js
jQuery(document).ready(function($) {
    $('.woocommerce-product-gallery').each(function() {
        $(this).wc_product_gallery();
    });
});