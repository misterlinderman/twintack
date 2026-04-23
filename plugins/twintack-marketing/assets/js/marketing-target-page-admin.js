/**
 * TwinTack Marketing — Target Page Admin
 *
 * Handles media uploads, hero type toggling, and video block repeater
 * for the Marketing Target Page meta boxes.
 *
 * @package TwinTack_Marketing
 */

(function ($) {
    'use strict';

    /* =====================================================================
     * Hero type field toggling
     * ================================================================== */

    function toggleHeroFields() {
        var type = $('.target-hero-type-select').val();

        // Video fields visible for video_background and video_full
        if (type === 'video_background' || type === 'video_full') {
            $('.target-hero-video-field').show();
        } else {
            $('.target-hero-video-field').hide();
        }

        // Overlay fields visible for video_background and image (not video_full)
        if (type === 'video_full') {
            $('.target-hero-overlay-field').hide();
        } else {
            $('.target-hero-overlay-field').show();
        }
    }

    /* =====================================================================
     * Generic media upload (images & video)
     * ================================================================== */

    function initMediaUpload($context) {
        $context = $context || $(document);

        $context.find('.upload-media-btn').off('click.ttTarget').on('click.ttTarget', function () {
            var $wrapper = $(this).closest('.twintack-media-upload');
            var mediaType = $wrapper.data('type') || 'image';
            var $input = $wrapper.find('.media-url');
            var $preview = $wrapper.find('.media-preview');
            var $removeBtn = $wrapper.find('.remove-media-btn');

            var frameOpts = {
                title: mediaType === 'video' ? 'Select Video' : 'Select Image',
                button: { text: 'Use ' + (mediaType === 'video' ? 'Video' : 'Image') },
                multiple: false
            };

            if (mediaType === 'video') {
                frameOpts.library = { type: 'video' };
            } else {
                frameOpts.library = { type: 'image' };
            }

            var frame = wp.media(frameOpts);

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                $input.val(attachment.url);

                if (mediaType === 'video') {
                    $preview.html(
                        '<video src="' + attachment.url + '" style="max-width:300px;height:auto;" muted></video>'
                    );
                } else {
                    $preview.html(
                        '<img src="' + attachment.url + '" alt="" style="max-width:300px;height:auto;" />'
                    );
                }
                $preview.show();
                $removeBtn.show();
            });

            frame.open();
        });

        $context.find('.remove-media-btn').off('click.ttTarget').on('click.ttTarget', function () {
            var $wrapper = $(this).closest('.twintack-media-upload');
            $wrapper.find('.media-url').val('');
            $wrapper.find('.media-preview').html('').hide();
            $(this).hide();
        });
    }

    /* =====================================================================
     * Video block repeater
     * ================================================================== */

    var videoBlockIndex = 0;

    function initVideoBlockRepeater() {
        var $container = $('#twintack-video-blocks-container');
        if (!$container.length) {
            return;
        }

        // Determine starting index from existing blocks
        videoBlockIndex = $container.find('.twintack-video-block-row').length;

        // Make blocks sortable
        $container.sortable({
            handle: '.video-block-header',
            placeholder: 'sortable-placeholder',
            update: function () {
                renumberVideoBlocks();
            }
        });

        // Add block
        $('#twintack-add-video-block').on('click', function () {
            var tmpl = $('#tmpl-twintack-video-block').html();
            var html = tmpl.replace(/__INDEX__/g, videoBlockIndex);
            var $newBlock = $(html);

            $container.append($newBlock);
            initMediaUpload($newBlock);
            initVideoTypeToggle($newBlock);
            renumberVideoBlocks();
            videoBlockIndex++;
        });

        // Remove block (delegated)
        $container.on('click', '.twintack-remove-video-block', function () {
            $(this).closest('.twintack-video-block-row').slideUp(200, function () {
                $(this).remove();
                renumberVideoBlocks();
            });
        });
    }

    function renumberVideoBlocks() {
        $('#twintack-video-blocks-container .twintack-video-block-row').each(function (i) {
            $(this).find('.block-number').text('#' + (i + 1));

            // Reindex name attributes so the repeater array stays sequential
            $(this).find('[name]').each(function () {
                var name = $(this).attr('name');
                name = name.replace(/twintack_target_video_blocks\[\d+\]/, 'twintack_target_video_blocks[' + i + ']');
                name = name.replace(/twintack_target_video_blocks\[__INDEX__\]/, 'twintack_target_video_blocks[' + i + ']');
                $(this).attr('name', name);
            });
        });
    }

    /* =====================================================================
     * Video type toggle (mp4 upload vs embed URL per block)
     * ================================================================== */

    function initVideoTypeToggle($context) {
        $context = $context || $(document);

        $context.find('.video-type-select').off('change.ttTarget').on('change.ttTarget', function () {
            var $row = $(this).closest('.twintack-video-block-row');
            var val = $(this).val();

            if (val === 'mp4') {
                $row.find('.video-block-mp4-field').show();
                $row.find('.video-block-image-field').hide();
                $row.find('.video-block-embed-field').hide();
            } else if (val === 'image') {
                $row.find('.video-block-mp4-field').hide();
                $row.find('.video-block-image-field').show();
                $row.find('.video-block-embed-field').hide();
            } else {
                $row.find('.video-block-mp4-field').hide();
                $row.find('.video-block-image-field').hide();
                $row.find('.video-block-embed-field').show();
            }
        }).trigger('change.ttTarget');
    }

    /* =====================================================================
     * Image row repeater (multi-select gallery)
     * ================================================================== */

    function initImageRowRepeater() {
        var $container = $('#twintack-image-row-container');
        if (!$container.length) {
            return;
        }

        // Add images via media library (multi-select)
        $('#twintack-add-image-row').on('click', function () {
            var frame = wp.media({
                title: 'Select Images',
                button: { text: 'Add to Row' },
                library: { type: 'image' },
                multiple: true
            });

            frame.on('select', function () {
                var attachments = frame.state().get('selection').toJSON();
                $.each(attachments, function (i, att) {
                    var html = '<div class="twintack-image-row-item" style="position:relative;width:200px;">';
                    html += '<img src="' + att.url + '" alt="" style="width:100%;height:auto;border:1px solid #ccc;border-radius:4px;" />';
                    html += '<input type="hidden" name="twintack_target_image_row[]" value="' + att.url + '" />';
                    html += '<button type="button" class="button twintack-remove-image-row-item" style="position:absolute;top:4px;right:4px;padding:0 6px;min-height:24px;line-height:22px;">&times;</button>';
                    html += '</div>';
                    $container.append(html);
                });
            });

            frame.open();
        });

        // Remove single image (delegated)
        $container.on('click', '.twintack-remove-image-row-item', function () {
            $(this).closest('.twintack-image-row-item').fadeOut(200, function () {
                $(this).remove();
            });
        });

        // Make sortable
        $container.sortable({
            items: '.twintack-image-row-item',
            tolerance: 'pointer'
        });
    }

    /* =====================================================================
     * Init on DOM ready
     * ================================================================== */

    $(function () {
        toggleHeroFields();
        $('.target-hero-type-select').on('change', toggleHeroFields);

        initMediaUpload($(document));
        initVideoBlockRepeater();
        initVideoTypeToggle($(document));
        initImageRowRepeater();

        // WordPress color picker for accent color
        if ($.fn.wpColorPicker) {
            $('.twintack-color-picker').wpColorPicker();
        }
    });

})(jQuery);
