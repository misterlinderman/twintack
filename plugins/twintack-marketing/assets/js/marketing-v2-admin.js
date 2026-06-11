(function ($) {
    'use strict';

    function updateMediaPreview($input) {
        var url = $.trim($input.val());
        var $preview = $input.closest('.tt-v2-media-row').find('.tt-v2-media-preview');

        if (!$preview.length) {
            return;
        }

        if (/\.(jpg|jpeg|png|gif|webp|svg)(\?|$)/i.test(url)) {
            $preview.html($('<img>', { src: url, alt: '' }));
        } else {
            $preview.empty();
        }
    }

    function initMediaPreviews() {
        $('.tt-v2-media-url').each(function () {
            updateMediaPreview($(this));
        });
    }

    function toggleHomepageV2Box() {
        var $template = $('#page_template');
        if (!$template.length) {
            return;
        }

        var isV2 = $template.val() === 'templates/template-homepage-v2.php';
        $('#twintack_v2_content').toggle(isV2);
    }

    function toggleProductV2Box() {
        var $layout = $('#twintack_product_layout');
        if (!$layout.length) {
            return;
        }

        var isV2 = $layout.val() === 'marketing-v2';
        $('#twintack_product_v2_content').toggle(isV2);
    }

    $(document).on('click', '.tt-v2-media-upload', function (event) {
        event.preventDefault();

        var targetId = $(this).data('target');
        var $input = $('#' + targetId);

        if (!$input.length) {
            return;
        }

        var frame = wp.media({
            title: 'Select image',
            button: { text: 'Use image' },
            multiple: false
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            $input.val(attachment.url).trigger('change');
        });

        frame.open();
    });

    $(document).on('input change', '.tt-v2-media-url', function () {
        updateMediaPreview($(this));
    });

    function initMarqueeRepeater() {
        var $list = $('[data-marquee-list]');
        if (!$list.length) {
            return;
        }

        $('#tt-v2-marquee-add').on('click', function (event) {
            event.preventDefault();
            var template = document.getElementById('tt-v2-marquee-item-template');
            if (!template || !template.content) {
                return;
            }
            $list.append($(template.content.cloneNode(true)));
        });

        $list.on('click', '[data-marquee-remove]', function (event) {
            event.preventDefault();
            var $items = $list.find('[data-marquee-item]');
            if ($items.length <= 1) {
                $(this).closest('[data-marquee-item]').find('input').val('');
                return;
            }
            $(this).closest('[data-marquee-item]').remove();
        });
    }

    $(function () {
        initMediaPreviews();
        toggleHomepageV2Box();
        toggleProductV2Box();
        initMarqueeRepeater();

        $('#page_template').on('change', toggleHomepageV2Box);
        $('#twintack_product_layout').on('change', toggleProductV2Box);
    });
})(jQuery);
