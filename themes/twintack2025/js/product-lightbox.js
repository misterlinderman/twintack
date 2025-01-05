jQuery(document).ready(function($) {
    $('.main-carousel').on('click', '.woocommerce-product-gallery__image a', function(e) {
        e.preventDefault();
        
        var pswpElement = $('.pswp')[0];
        var $carousel = $(this).closest('.main-carousel');
        var items = [];
        
        // Build items array
        $carousel.find('.woocommerce-product-gallery__image a').each(function() {
            var $link = $(this);
            items.push({
                src: $link.attr('href'),
                w: parseInt($link.data('pswp-width'), 10),
                h: parseInt($link.data('pswp-height'), 10)
            });
        });
        
        // Get current slide index
        var options = {
            index: $(this).closest('.carousel-slide').index(),
            bgOpacity: 0.85,
            showHideOpacity: true
        };
        
        // Initialize PhotoSwipe
        var gallery = new PhotoSwipe(pswpElement, PhotoSwipeUI_Default, items, options);
        gallery.init();
    });
}); 