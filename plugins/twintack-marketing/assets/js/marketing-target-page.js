/**
 * TwinTack Marketing — Target Page Frontend
 *
 * Handles intersection-observer-based video autoplay for feature blocks
 * and basic responsive video behaviour.
 *
 * @package TwinTack_Marketing
 */

(function () {
    'use strict';

    /* =====================================================================
     * Autoplay feature videos when they scroll into view
     * ================================================================== */

    function initScrollAutoplay() {
        var videos = document.querySelectorAll('video[data-autoplay-on-scroll]');
        if (!videos.length) {
            return;
        }

        if (!('IntersectionObserver' in window)) {
            // Fallback: just autoplay everything
            videos.forEach(function (v) { v.play(); });
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                var video = entry.target;
                if (entry.isIntersecting) {
                    if (video.paused) {
                        video.play().catch(function () {
                            // Autoplay blocked — silently fail
                        });
                    }
                } else {
                    if (!video.paused) {
                        video.pause();
                    }
                }
            });
        }, { threshold: 0.25 });

        videos.forEach(function (v) {
            observer.observe(v);
        });
    }

    /* =====================================================================
     * Responsive embed aspect ratio (16:9)
     * ================================================================== */

    function initResponsiveEmbeds() {
        var embeds = document.querySelectorAll('.target-video-embed iframe');
        embeds.forEach(function (iframe) {
            if (!iframe.parentElement.classList.contains('target-video-embed')) {
                return;
            }
            iframe.setAttribute('width', '100%');
            iframe.setAttribute('height', '100%');
        });
    }

    /* =====================================================================
     * Init
     * ================================================================== */

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initScrollAutoplay();
            initResponsiveEmbeds();
        });
    } else {
        initScrollAutoplay();
        initResponsiveEmbeds();
    }

})();

/**
 * Image Row — mobile Slick carousel
 * Uses jQuery + Slick (both already enqueued by the marketing plugin).
 */
(function ($) {
    'use strict';

    if (!$ || !$.fn.slick) {
        return;
    }

    $(function () {
        var $grid = $('.target-image-row-grid');
        if (!$grid.length || $grid.children().length < 2) {
            return;
        }

        var mobileBreakpoint = 768;

        function handleCarousel() {
            if (window.innerWidth <= mobileBreakpoint) {
                if (!$grid.hasClass('slick-initialized')) {
                    $grid.slick({
                        slidesToShow: 1,
                        slidesToScroll: 1,
                        arrows: true,
                        dots: true,
                        infinite: true,
                        adaptiveHeight: true
                    });
                }
            } else {
                if ($grid.hasClass('slick-initialized')) {
                    $grid.slick('unslick');
                }
            }
        }

        handleCarousel();

        var resizeTimer;
        $(window).on('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(handleCarousel, 150);
        });
    });

})(window.jQuery);
