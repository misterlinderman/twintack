/**
 * TwinTack Marketing Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Featured Products Management
        initFeaturedProducts();
        
        // Banner Blocks Management
        initBannerBlocks();
    });
    
    function initFeaturedProducts() {
        // Context selector change
        $('#featured-context').on('change', function() {
            var context = $(this).val();
            window.location.href = '?page=twintack-marketing-featured&context=' + context;
        });
        
        // Add product button - open product search modal
        $('.add-featured-product').on('click', function() {
            openProductSearchModal();
        });
        
        // Product search functionality
        function openProductSearchModal() {
            var $modal = $('#twintack-product-search-modal');
            if ($modal.length === 0) {
                createProductSearchModal();
                $modal = $('#twintack-product-search-modal');
            }
            $modal.show();
            $('#product-search-input').focus();
        }
        
        function createProductSearchModal() {
            var modal = $('<div id="twintack-product-search-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); z-index:100000; overflow-y:auto;">' +
                '<div style="background:#fff; margin:50px auto; max-width:800px; padding:20px; border-radius:5px;">' +
                '<h2>Search Products</h2>' +
                '<input type="text" id="product-search-input" placeholder="Type to search products..." style="width:100%; padding:10px; margin-bottom:15px; font-size:14px;" />' +
                '<div id="product-search-results" style="max-height:400px; overflow-y:auto;"></div>' +
                '<div style="margin-top:15px; text-align:right;">' +
                '<button type="button" class="button close-product-search">Cancel</button>' +
                '</div>' +
                '</div>' +
                '</div>');
            $('body').append(modal);
            
            // Search products
            var searchTimeout;
            $('#product-search-input').on('input', function() {
                clearTimeout(searchTimeout);
                var query = $(this).val();
                if (query.length < 2) {
                    $('#product-search-results').html('');
                    return;
                }
                
                searchTimeout = setTimeout(function() {
                    searchProducts(query);
                }, 300);
            });
            
            // Close modal
            $('.close-product-search, #twintack-product-search-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#twintack-product-search-modal').hide();
                }
            });
        }
        
        function searchProducts(query) {
            $.ajax({
                url: twintackMarketing.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'twintack_search_products',
                    nonce: twintackMarketing.nonce,
                    query: query
                },
                success: function(response) {
                    if (response.success && response.data.products) {
                        displayProductResults(response.data.products);
                    } else {
                        $('#product-search-results').html('<p>No products found.</p>');
                    }
                }
            });
        }
        
        function displayProductResults(products) {
            var html = '<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:15px;">';
            products.forEach(function(product) {
                html += '<div class="product-search-item" data-product-id="' + product.id + '" style="border:1px solid #ddd; padding:10px; cursor:pointer; border-radius:4px; transition:background 0.2s;">' +
                    '<img src="' + (product.image || '') + '" style="width:100%; height:150px; object-fit:cover; margin-bottom:10px;" />' +
                    '<strong style="display:block; margin-bottom:5px;">' + product.name + '</strong>' +
                    '<span style="color:#666; font-size:12px;">' + product.price + '</span>' +
                    '</div>';
            });
            html += '</div>';
            $('#product-search-results').html(html);
            
            // Add click handler
            $('.product-search-item').on('click', function() {
                var productId = $(this).data('product-id');
                addProductToList(productId);
                $('#twintack-product-search-modal').hide();
            });
        }
        
        function addProductToList(productId) {
            // Check if already added
            if ($('#featured-products-list .twintack-product-item[data-product-id="' + productId + '"]').length > 0) {
                alert('This product is already in the list.');
                return;
            }
            
            // Fetch product details
            $.ajax({
                url: twintackMarketing.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'twintack_get_product_details',
                    nonce: twintackMarketing.nonce,
                    product_id: productId
                },
                success: function(response) {
                    if (response.success && response.data.product) {
                        var product = response.data.product;
                        var item = $('<div class="twintack-product-item" data-product-id="' + product.id + '">' +
                            '<img src="' + product.image + '" style="width:60px; height:60px; object-fit:cover;" />' +
                            '<div class="product-info">' +
                            '<strong>' + product.name + '</strong>' +
                            '<span class="product-price">' + product.price + '</span>' +
                            '</div>' +
                            '<button class="remove-product" aria-label="Remove">×</button>' +
                            '</div>');
                        $('#featured-products-list').append(item);
                    }
                }
            });
        }
        
        // Remove product
        $(document).on('click', '.remove-product', function() {
            $(this).closest('.twintack-product-item').fadeOut(300, function() {
                $(this).remove();
            });
        });
        
        // Save featured products
        $('.save-featured-products').on('click', function() {
            var context = $('#featured-context').val();
            var productIds = [];
            
            $('#featured-products-list .twintack-product-item').each(function() {
                productIds.push($(this).data('product-id'));
            });
            
            $.ajax({
                url: twintackMarketing.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'twintack_save_featured_products',
                    nonce: twintackMarketing.nonce,
                    context: context,
                    product_ids: productIds
                },
                success: function(response) {
                    if (response.success) {
                        alert('Featured products saved successfully!');
                    } else {
                        alert('Error saving featured products.');
                    }
                }
            });
        });
        
        // Make products sortable
        $('#featured-products-list').sortable({
            handle: '.twintack-product-item',
            placeholder: 'ui-state-highlight',
            axis: 'y'
        });
    }
    
    function initBannerBlocks() {
        // Context selector change
        $('#banner-context').on('change', function() {
            var context = $(this).val();
            window.location.href = '?page=twintack-marketing-banners&context=' + context;
        });
        
        // Layout change - show/hide fields
        $(document).on('change', '.banner-layout', function() {
            var layout = $(this).val();
            var $block = $(this).closest('.twintack-banner-block-editor');
            var context = $block.data('context') || $('#banner-context').val();

            if (context === 'homepage-v2') {
                $block.find('.v2-overlay-fields').show();
                $block.find('.text-fields').hide();
                $block.find('.link-field').hide();
                return;
            }
            
            if (layout === '50-50') {
                $block.find('.text-fields').show();
                $block.find('.link-field').hide();
            } else {
                $block.find('.text-fields').hide();
                $block.find('.link-field').show();
            }
        });
        
        // Image upload
        $(document).on('click', '.upload-image', function() {
            var $wrapper = $(this).closest('.image-upload-wrapper');
            var $input = $wrapper.find('.image-url');
            var $preview = $wrapper.find('.image-preview');
            var $removeBtn = $wrapper.find('.remove-image');
            
            var frame = wp.media({
                title: 'Select Image',
                button: {
                    text: 'Use Image'
                },
                multiple: false
            });
            
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $input.val(attachment.url);
                $preview.html('<img src="' + attachment.url + '" alt="" />');
                $removeBtn.show();
            });
            
            frame.open();
        });
        
        // Remove image
        $(document).on('click', '.remove-image', function() {
            var $wrapper = $(this).closest('.image-upload-wrapper');
            $wrapper.find('.image-url').val('');
            $wrapper.find('.image-preview').html('');
            $(this).hide();
        });
        
        // Add banner block
        $('.add-banner-block').on('click', function() {
            var $template = $('#banner-block-template').clone();
            $template.removeAttr('id').removeAttr('style');
            $template.find('input, select, textarea').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    $(this).attr('name', name);
                }
            });
            
            $('#banner-blocks-list').append($template);
            
            // Initialize layout fields visibility
            var layout = $template.find('.banner-layout').val();
            if (layout === '50-50') {
                $template.find('.text-fields').show();
                $template.find('.link-field').hide();
            } else {
                $template.find('.text-fields').hide();
                $template.find('.link-field').show();
            }
        });
        
        // Remove banner block
        $(document).on('click', '.remove-banner-block', function() {
            var $block = $(this).closest('.twintack-banner-block-editor');
            var blockId = $block.data('block-id');
            
            if (blockId && confirm('Are you sure you want to remove this banner block?')) {
                var context = $('#banner-context').val();
                
                $.ajax({
                    url: twintackMarketing.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'twintack_delete_banner_block',
                        nonce: twintackMarketing.nonce,
                        context: context,
                        block_id: blockId
                    },
                    success: function(response) {
                        if (response.success) {
                            $block.fadeOut(300, function() {
                                $(this).remove();
                            });
                        }
                    }
                });
            } else if (!blockId) {
                $block.fadeOut(300, function() {
                    $(this).remove();
                });
            }
        });
        
        // Save banner block
        $(document).on('click', '.save-banner-block', function() {
            var $block = $(this).closest('.twintack-banner-block-editor');
            var context = $('#banner-context').val();
            
            var blockData = {
                id: $block.data('block-id') || 'banner_' + Date.now(),
                layout: $block.find('.banner-layout').val(),
                image_desktop: $block.find('input[name="image_desktop"]').val(),
                image_mobile: $block.find('input[name="image_mobile"]').val(),
                title: $block.find('input[name="title"]').val(),
                text: $block.find('input[name="text"]').val() || $block.find('textarea[name="text"]').val(),
                description: $block.find('textarea[name="description"]').val(),
                cta_text: $block.find('input[name="cta_text"]').val(),
                cta_link: $block.find('input[name="cta_link"]').val(),
                link: $block.find('input[name="link"]').val(),
                order: $block.index()
            };
            
            $.ajax({
                url: twintackMarketing.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'twintack_save_banner_block',
                    nonce: twintackMarketing.nonce,
                    context: context,
                    block: blockData
                },
                success: function(response) {
                    if (response.success) {
                        $block.data('block-id', response.data.block.id);
                        $block.find('.block-id').text(response.data.block.id);
                        alert('Banner block saved successfully!');
                    } else {
                        alert('Error saving banner block.');
                    }
                }
            });
        });
        
        // Make banner blocks sortable
        $('#banner-blocks-list').sortable({
            handle: '.banner-block-header',
            placeholder: 'ui-state-highlight',
            axis: 'y',
            update: function() {
                $(this).find('.twintack-banner-block-editor').each(function(index) {
                    $(this).find('.block-order').val(index);
                });
            }
        });
    }
    
    // Hero Carousel Management
    function initHeroCarousel() {
        // Add hero slide
        $('.add-hero-slide').on('click', function() {
            var $template = $('#hero-slide-template').clone();
            $template.removeAttr('id').removeAttr('style');
            $template.find('input, select, textarea').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    $(this).attr('name', name);
                }
            });
            
            $('#hero-slides-list').append($template);
        });
        
        // Video upload for hero slides (MP4)
        $(document).on('click', '.twintack-hero-slide-editor .upload-video, .twintack-video-tab-editor .upload-video', function() {
            var $wrapper = $(this).closest('.video-upload-wrapper');
            var $input = $wrapper.find('.video-url');
            var $preview = $wrapper.find('.video-preview');
            var $removeBtn = $wrapper.find('.remove-video');
            
            var frame = wp.media({
                title: 'Select Video (MP4)',
                button: {
                    text: 'Use Video'
                },
                library: {
                    type: 'video/mp4'
                },
                multiple: false
            });
            
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                if (attachment.mime === 'video/mp4' || attachment.url.toLowerCase().endsWith('.mp4')) {
                    $input.val(attachment.url);
                    $preview.html('<video src="' + attachment.url + '" style="max-width: 300px; height: auto;" muted></video>');
                    $removeBtn.show();
                } else {
                    alert('Please select an MP4 video file.');
                }
            });
            
            frame.open();
        });
        
        // Remove video
        $(document).on('click', '.twintack-hero-slide-editor .remove-video', function() {
            var $wrapper = $(this).closest('.video-upload-wrapper');
            $wrapper.find('.video-url').val('');
            $wrapper.find('.video-preview').html('');
            $(this).hide();
        });
        
        // Image upload for hero slides
        $(document).on('click', '.twintack-hero-slide-editor .upload-image', function() {
            var $wrapper = $(this).closest('.image-upload-wrapper');
            var $input = $wrapper.find('.image-url');
            var $preview = $wrapper.find('.image-preview');
            var $removeBtn = $wrapper.find('.remove-image');
            
            var frame = wp.media({
                title: 'Select Image',
                button: {
                    text: 'Use Image'
                },
                multiple: false
            });
            
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $input.val(attachment.url);
                $preview.html('<img src="' + attachment.url + '" alt="" style="max-width: 300px; height: auto;" />');
                $removeBtn.show();
            });
            
            frame.open();
        });
        
        // Remove image
        $(document).on('click', '.twintack-hero-slide-editor .remove-image', function() {
            var $wrapper = $(this).closest('.image-upload-wrapper');
            $wrapper.find('.image-url').val('');
            $wrapper.find('.image-preview').html('');
            $(this).hide();
        });
        
        // Remove hero slide
        $(document).on('click', '.remove-hero-slide', function() {
            var $slide = $(this).closest('.twintack-hero-slide-editor');
            var slideId = $slide.data('slide-id');
            
            if (slideId && confirm('Are you sure you want to remove this hero slide?')) {
                $.ajax({
                    url: twintackMarketing.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'twintack_delete_hero_slide',
                        nonce: twintackMarketing.nonce,
                        slide_id: slideId
                    },
                    success: function(response) {
                        if (response.success) {
                            $slide.fadeOut(300, function() {
                                $(this).remove();
                            });
                        }
                    }
                });
            } else if (!slideId) {
                $slide.fadeOut(300, function() {
                    $(this).remove();
                });
            }
        });
        
        // Save hero slide
        $(document).on('click', '.save-hero-slide', function() {
            var $slide = $(this).closest('.twintack-hero-slide-editor');
            
            var slideData = {
                id: $slide.data('slide-id') || 'hero_' + Date.now(),
                video_desktop: $slide.find('input[name="video_desktop"]').val(),
                image_desktop: $slide.find('input[name="image_desktop"]').val(),
                image_mobile: $slide.find('input[name="image_mobile"]').val(),
                destination_url: $slide.find('input[name="destination_url"]').val(),
                alt_text: $slide.find('input[name="alt_text"]').val(),
                active: $slide.find('.slide-active').is(':checked') ? '1' : '0',
                order: $slide.index()
            };
            
            $.ajax({
                url: twintackMarketing.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'twintack_save_hero_slide',
                    nonce: twintackMarketing.nonce,
                    slide: slideData
                },
                success: function(response) {
                    if (response.success) {
                        $slide.data('slide-id', response.data.slide.id);
                        $slide.find('.slide-id').text(response.data.slide.id);
                        alert('Hero slide saved successfully!');
                    } else {
                        alert('Error saving hero slide.');
                    }
                }
            });
        });
        
        // Make hero slides sortable
        $('#hero-slides-list').sortable({
            handle: '.hero-slide-header',
            placeholder: 'ui-state-highlight',
            axis: 'y',
            update: function() {
                $(this).find('.twintack-hero-slide-editor').each(function(index) {
                    $(this).find('.slide-order').val(index);
                });
            }
        });
    }
    
    // Initialize hero carousel if on that page
    if ($('.twintack-hero-carousel-manager').length > 0) {
        initHeroCarousel();
    }
})(jQuery);

