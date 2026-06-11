<?php
/**
 * Shared video lightbox for Marketing V2.
 *
 * @package twintack2025
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="tt-v2-video-modal" data-tt-v2-modal hidden>
    <div class="tt-v2-video-modal__backdrop" data-tt-v2-modal-close></div>
    <div class="tt-v2-video-modal__dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Video player', 'twintack2025'); ?>">
        <button type="button" class="tt-v2-video-modal__close" data-tt-v2-modal-close aria-label="<?php esc_attr_e('Close', 'twintack2025'); ?>">&times;</button>
        <div class="tt-v2-video-modal__player" data-tt-v2-modal-player></div>
    </div>
</div>
