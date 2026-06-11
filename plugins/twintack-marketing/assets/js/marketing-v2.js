(function ($) {
    'use strict';

    function scrollCarousel($track, direction) {
        if (!$track.length) {
            return;
        }
        var slide = $track.find('[data-tt-v2-slide]').first();
        var amount = slide.outerWidth(true) || 280;
        $track[0].scrollBy({ left: direction * amount, behavior: 'smooth' });
    }

    function openVideoModal(url) {
        var $modal = $('[data-tt-v2-modal]').first();
        if (!$modal.length || !url) {
            return;
        }

        var $player = $modal.find('[data-tt-v2-modal-player]');
        $player.empty();

        if (/\.(mp4|webm|ogg)(\?|$)/i.test(url)) {
            $player.append(
                $('<video>', {
                    src: url,
                    controls: true,
                    autoplay: true,
                    playsinline: true
                })
            );
        } else {
            $player.append(
                $('<iframe>', {
                    src: url,
                    allow: 'autoplay; fullscreen',
                    allowfullscreen: true,
                    frameborder: 0
                })
            );
        }

        $modal.removeAttr('hidden');
        $('body').addClass('tt-v2-modal-open');
    }

    function closeVideoModal() {
        var $modal = $('[data-tt-v2-modal]').first();
        $modal.attr('hidden', 'hidden');
        $modal.find('[data-tt-v2-modal-player]').empty();
        $('body').removeClass('tt-v2-modal-open');
    }

    function activateTab($widget, index) {
        $widget.find('[data-tt-v2-tab]').each(function () {
            var isActive = String($(this).data('tt-v2-tab')) === String(index);
            $(this).toggleClass('is-active', isActive).attr('aria-selected', isActive ? 'true' : 'false');
        });

        $widget.find('[data-tt-v2-panel]').each(function () {
            var isActive = String($(this).data('tt-v2-panel')) === String(index);
            $(this).toggleClass('is-active', isActive).prop('hidden', !isActive);

            if (isActive) {
                var $video = $(this).find('video.tt-v2-video-tabs__video');
                var lazySrc = $video.attr('data-tt-v2-lazy-src');
                if (lazySrc && !$video.find('source').length) {
                    $video.append($('<source>', { src: lazySrc, type: 'video/mp4' }));
                    $video.removeAttr('data-tt-v2-lazy-src');
                }
            } else {
                $(this).find('video').each(function () {
                    this.pause();
                });
            }
        });
    }

    $(document).on('click', '[data-tt-v2-prev]', function () {
        scrollCarousel($(this).closest('[data-tt-v2-carousel]').find('[data-tt-v2-track]'), -1);
    });

    $(document).on('click', '[data-tt-v2-next]', function () {
        scrollCarousel($(this).closest('[data-tt-v2-carousel]').find('[data-tt-v2-track]'), 1);
    });

    $(document).on('click', '[data-tt-v2-video]', function () {
        openVideoModal($(this).data('tt-v2-video'));
    });

    $(document).on('click', '[data-tt-v2-modal-close]', closeVideoModal);

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape') {
            closeVideoModal();
        }
    });

    $(document).on('click', '[data-tt-v2-tab]', function () {
        var $widget = $(this).closest('[data-tt-v2-tabs]');
        activateTab($widget, $(this).data('tt-v2-tab'));
    });
})(jQuery);
