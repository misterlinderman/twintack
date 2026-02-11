<?php
/**
 * New design form partial — manual grip creation.
 *
 * Accessible to Administrators and Production Team.
 * Creates a grip_design post via AJAX with all standard meta fields.
 *
 * @package TwinTack_Custom_Grips
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="ttcg-new-design">

    <!-- Back Link -->
    <div class="ttcg-detail__back">
        <a href="<?php echo esc_url( TTCG_Router::get_dashboard_url() ); ?>" class="ttcg-detail__back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
            <?php esc_html_e( 'Back to All Designs', 'twintack-custom-grips' ); ?>
        </a>
    </div>

    <div class="ttcg-new-design__header">
        <h2 class="ttcg-new-design__title"><?php esc_html_e( 'Create New Grip Design', 'twintack-custom-grips' ); ?></h2>
        <p class="ttcg-new-design__subtitle"><?php esc_html_e( 'Manually create a custom grip design for customer service or trade show orders.', 'twintack-custom-grips' ); ?></p>
    </div>

    <form id="ttcg-new-design-form" class="ttcg-new-design__form">

        <!-- Customer Information -->
        <div class="ttcg-new-design__section">
            <h3 class="ttcg-detail__section-title"><?php esc_html_e( 'Customer Information', 'twintack-custom-grips' ); ?></h3>
            <div class="ttcg-new-design__grid">
                <div class="ttcg-form-row">
                    <label for="ttcg-new-customer_name"><?php esc_html_e( 'Customer Name', 'twintack-custom-grips' ); ?> <span class="ttcg-required">*</span></label>
                    <input type="text" id="ttcg-new-customer_name" name="customer_name" class="ttcg-input" required>
                </div>
                <div class="ttcg-form-row">
                    <label for="ttcg-new-customer_email"><?php esc_html_e( 'Customer Email', 'twintack-custom-grips' ); ?> <span class="ttcg-required">*</span></label>
                    <input type="email" id="ttcg-new-customer_email" name="customer_email" class="ttcg-input" required>
                </div>
                <div class="ttcg-form-row">
                    <label for="ttcg-new-team_name"><?php esc_html_e( 'Team / School Name', 'twintack-custom-grips' ); ?></label>
                    <input type="text" id="ttcg-new-team_name" name="team_name" class="ttcg-input">
                </div>
                <div class="ttcg-form-row">
                    <label for="ttcg-new-quantity"><?php esc_html_e( 'Quantity', 'twintack-custom-grips' ); ?> <span class="ttcg-required">*</span></label>
                    <input type="number" id="ttcg-new-quantity" name="quantity" min="1" value="1" class="ttcg-input" required>
                </div>
            </div>
        </div>

        <!-- Design Specifications -->
        <div class="ttcg-new-design__section">
            <h3 class="ttcg-detail__section-title"><?php esc_html_e( 'Design Specifications', 'twintack-custom-grips' ); ?></h3>
            <div class="ttcg-new-design__grid">
                <div class="ttcg-form-row">
                    <label for="ttcg-new-design_type"><?php esc_html_e( 'Design Type', 'twintack-custom-grips' ); ?></label>
                    <select id="ttcg-new-design_type" name="design_type" class="ttcg-input">
                        <option value=""><?php esc_html_e( '— Select —', 'twintack-custom-grips' ); ?></option>
                        <option value="New Design"><?php esc_html_e( 'New Design', 'twintack-custom-grips' ); ?></option>
                        <option value="Original Design"><?php esc_html_e( 'Original Design', 'twintack-custom-grips' ); ?></option>
                    </select>
                </div>
                <div class="ttcg-form-row">
                    <label for="ttcg-new-design_layout"><?php esc_html_e( 'Pattern / Layout', 'twintack-custom-grips' ); ?></label>
                    <select id="ttcg-new-design_layout" name="design_layout" class="ttcg-input">
                        <option value=""><?php esc_html_e( '— Select —', 'twintack-custom-grips' ); ?></option>
                        <option value="Solid Color"><?php esc_html_e( 'Solid Color', 'twintack-custom-grips' ); ?></option>
                        <option value="Two Tone"><?php esc_html_e( 'Two Tone', 'twintack-custom-grips' ); ?></option>
                        <option value="Three Tone"><?php esc_html_e( 'Three Tone', 'twintack-custom-grips' ); ?></option>
                        <option value="2-Color Fade"><?php esc_html_e( '2-Color Fade', 'twintack-custom-grips' ); ?></option>
                        <option value="3-Color Fade"><?php esc_html_e( '3-Color Fade', 'twintack-custom-grips' ); ?></option>
                    </select>
                </div>
                <div class="ttcg-form-row">
                    <label for="ttcg-new-primary_color"><?php esc_html_e( 'Primary Color', 'twintack-custom-grips' ); ?></label>
                    <input type="text" id="ttcg-new-primary_color" name="primary_color" class="ttcg-input" placeholder="<?php esc_attr_e( 'e.g., Red, Navy, Gold', 'twintack-custom-grips' ); ?>">
                </div>
                <div class="ttcg-form-row">
                    <label for="ttcg-new-secondary_color"><?php esc_html_e( 'Secondary Color', 'twintack-custom-grips' ); ?></label>
                    <input type="text" id="ttcg-new-secondary_color" name="secondary_color" class="ttcg-input">
                </div>
                <div class="ttcg-form-row">
                    <label for="ttcg-new-tertiary_color"><?php esc_html_e( 'Tertiary Color', 'twintack-custom-grips' ); ?></label>
                    <input type="text" id="ttcg-new-tertiary_color" name="tertiary_color" class="ttcg-input">
                </div>
                <div class="ttcg-form-row">
                    <label for="ttcg-new-artwork_status"><?php esc_html_e( 'Initial Status', 'twintack-custom-grips' ); ?></label>
                    <select id="ttcg-new-artwork_status" name="artwork_status" class="ttcg-input">
                        <?php foreach ( TTCG_Dashboard::get_statuses() as $slug => $info ) : ?>
                            <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, 'artwork_pending' ); ?>>
                                <?php echo esc_html( $info['label'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Design Instructions -->
        <div class="ttcg-new-design__section">
            <h3 class="ttcg-detail__section-title"><?php esc_html_e( 'Design Instructions', 'twintack-custom-grips' ); ?></h3>
            <div class="ttcg-form-row ttcg-form-row--full">
                <label for="ttcg-new-feedback"><?php esc_html_e( 'Notes / Instructions', 'twintack-custom-grips' ); ?></label>
                <textarea id="ttcg-new-feedback" name="feedback" rows="5" class="ttcg-input ttcg-textarea"
                          placeholder="<?php esc_attr_e( 'Any design instructions, customer notes, or special requests…', 'twintack-custom-grips' ); ?>"></textarea>
            </div>
        </div>

        <!-- Artwork Upload (optional) -->
        <div class="ttcg-new-design__section">
            <h3 class="ttcg-detail__section-title"><?php esc_html_e( 'Artwork / Reference File', 'twintack-custom-grips' ); ?></h3>
            <p class="ttcg-new-design__hint"><?php esc_html_e( 'Optionally attach a logo, artwork file, or reference image. You can also upload a mockup after creation.', 'twintack-custom-grips' ); ?></p>
            <div class="ttcg-form-row">
                <input type="file" id="ttcg-new-artwork_file" name="artwork_file" class="ttcg-input"
                       accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.ai,.eps,.svg">
            </div>
        </div>

        <!-- Submit -->
        <div class="ttcg-new-design__actions">
            <button type="submit" class="ttcg-btn ttcg-btn--primary ttcg-btn--large" id="ttcg-create-design">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <?php esc_html_e( 'Create Grip Design', 'twintack-custom-grips' ); ?>
            </button>
            <a href="<?php echo esc_url( TTCG_Router::get_dashboard_url() ); ?>" class="ttcg-btn ttcg-btn--ghost">
                <?php esc_html_e( 'Cancel', 'twintack-custom-grips' ); ?>
            </a>
        </div>
        <div class="ttcg-new-design__feedback" id="ttcg-new-design-feedback"></div>
    </form>
</div>
