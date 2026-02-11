<?php
/**
 * Mockup upload partial — drag-and-drop upload zone.
 *
 * Included from design-detail.php.
 *
 * @package TwinTack_Custom_Grips
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="ttcg-upload" id="ttcg-upload-zone" data-design-id="<?php echo esc_attr( $design_id ); ?>">
    <div class="ttcg-upload__dropzone" id="ttcg-dropzone">
        <div class="ttcg-upload__icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="17 8 12 3 7 8"/>
                <line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
        </div>
        <p class="ttcg-upload__text"><?php esc_html_e( 'Drag & drop a mockup file here', 'twintack-custom-grips' ); ?></p>
        <p class="ttcg-upload__subtext"><?php esc_html_e( 'or', 'twintack-custom-grips' ); ?></p>
        <label for="ttcg-file-input" class="ttcg-btn ttcg-btn--small ttcg-btn--secondary">
            <?php esc_html_e( 'Choose File', 'twintack-custom-grips' ); ?>
        </label>
        <input type="file" id="ttcg-file-input" class="ttcg-upload__input"
               accept=".jpg,.jpeg,.png,.gif,.webp,.pdf">
        <p class="ttcg-upload__hint"><?php esc_html_e( 'JPG, PNG, GIF, WebP, or PDF — max 50 MB', 'twintack-custom-grips' ); ?></p>
    </div>

    <!-- Upload Progress -->
    <div class="ttcg-upload__progress" id="ttcg-upload-progress" style="display: none;">
        <div class="ttcg-upload__progress-bar">
            <div class="ttcg-upload__progress-fill" id="ttcg-upload-progress-fill"></div>
        </div>
        <span class="ttcg-upload__progress-text" id="ttcg-upload-progress-text"><?php esc_html_e( 'Uploading…', 'twintack-custom-grips' ); ?></span>
    </div>

    <!-- Upload Result -->
    <div class="ttcg-upload__result" id="ttcg-upload-result" style="display: none;"></div>
</div>
