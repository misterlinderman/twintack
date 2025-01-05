jQuery(document).ready(function($) {
    // Initialize main carousel
    const $mainCarousel = $('.main-carousel').slick({
        slidesToShow: 1,
        slidesToScroll: 1,
        arrows: true,
        fade: true,
        adaptiveHeight: true,
        prevArrow: '<button type="button" class="slick-prev">Previous</button>',
        nextArrow: '<button type="button" class="slick-next">Next</button>'
    });

    // Handle variation thumbnail clicks
    $('.variation-thumb').on('click', function() {
        const $this = $(this);
        const imageId = $this.data('image-id');
        
        // Find the slide index with matching image ID
        const slideIndex = $('.main-carousel .carousel-slide').find(`[data-image-id="${imageId}"]`).closest('.carousel-slide').index();
        
        // Go to that slide
        if (slideIndex !== -1) {
            $mainCarousel.slick('slickGoTo', slideIndex);
        }
        
        // Update active state of thumbnails
        $('.variation-thumb').removeClass('active');
        $this.addClass('active');

        // Update the select dropdown if it exists
        const colorSlug = $this.data('color-slug');
        if (colorSlug) {
            $('select[data-attribute_name="attribute_pa_color"]').val(colorSlug).trigger('change');
        }
    });

    // Handle variation selection from dropdown
    $('form.variations_form').on('show_variation', function(event, variation) {
        if (variation && variation.image_id) {
            // Find the slide with matching variation image
            const slideIndex = $('.main-carousel .carousel-slide').find(`[data-image-id="${variation.image_id}"]`).closest('.carousel-slide').index();
            
            if (slideIndex !== -1) {
                $mainCarousel.slick('slickGoTo', slideIndex);
            }

            // Update active thumbnail
            $('.variation-thumb').removeClass('active');
            $(`.variation-thumb[data-image-id="${variation.image_id}"]`).addClass('active');
        }
    });

    // Set initial active state
    const initialImageId = $('.main-carousel .carousel-slide:first-child img').data('image-id');
    if (initialImageId) {
        $(`.variation-thumb[data-image-id="${initialImageId}"]`).addClass('active');
    }
}); 