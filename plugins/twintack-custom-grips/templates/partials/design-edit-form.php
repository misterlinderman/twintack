<?php
/**
 * Design edit form partial — inline editable fields.
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

<form id="ttcg-edit-form" class="ttcg-edit-form" data-design-id="<?php echo esc_attr( $design_id ); ?>">

    <!-- Read-only view (default) -->
    <div class="ttcg-detail__info-grid" id="ttcg-info-view">
        <div class="ttcg-detail__info-row">
            <span class="ttcg-detail__label"><?php esc_html_e( 'Customer Name', 'twintack-custom-grips' ); ?></span>
            <span class="ttcg-detail__value" id="ttcg-display-customer_name"><?php echo esc_html( $data['customer_name'] ); ?></span>
        </div>
        <div class="ttcg-detail__info-row">
            <span class="ttcg-detail__label"><?php esc_html_e( 'Email', 'twintack-custom-grips' ); ?></span>
            <span class="ttcg-detail__value" id="ttcg-display-customer_email">
                <a href="mailto:<?php echo esc_attr( $data['customer_email'] ); ?>"><?php echo esc_html( $data['customer_email'] ); ?></a>
            </span>
        </div>
        <div class="ttcg-detail__info-row">
            <span class="ttcg-detail__label"><?php esc_html_e( 'Team / School', 'twintack-custom-grips' ); ?></span>
            <span class="ttcg-detail__value" id="ttcg-display-team_name"><?php echo esc_html( $data['team_name'] ); ?></span>
        </div>
        <div class="ttcg-detail__info-row">
            <span class="ttcg-detail__label"><?php esc_html_e( 'Design Type', 'twintack-custom-grips' ); ?></span>
            <span class="ttcg-detail__value" id="ttcg-display-design_type"><?php echo esc_html( $data['design_type'] ); ?></span>
        </div>
        <?php if ( ! empty( $data['design_layout'] ) ) : ?>
            <div class="ttcg-detail__info-row">
                <span class="ttcg-detail__label"><?php esc_html_e( 'Pattern', 'twintack-custom-grips' ); ?></span>
                <span class="ttcg-detail__value" id="ttcg-display-design_layout"><?php echo esc_html( $data['design_layout'] ); ?></span>
            </div>
        <?php endif; ?>
        <div class="ttcg-detail__info-row">
            <span class="ttcg-detail__label"><?php esc_html_e( 'Quantity', 'twintack-custom-grips' ); ?></span>
            <span class="ttcg-detail__value" id="ttcg-display-quantity"><?php echo esc_html( $data['quantity'] ); ?></span>
        </div>

        <!-- Colors -->
        <?php if ( ! empty( $data['primary_color'] ) ) : ?>
            <div class="ttcg-detail__info-row">
                <span class="ttcg-detail__label"><?php esc_html_e( 'Colors', 'twintack-custom-grips' ); ?></span>
                <span class="ttcg-detail__value ttcg-detail__colors">
                    <?php
                    $colors = array_filter( array(
                        $data['primary_color'],
                        $data['secondary_color'],
                        $data['tertiary_color'],
                    ) );
                    foreach ( $colors as $color_name ) :
                        $hex = isset( $color_map[ $color_name ] ) ? $color_map[ $color_name ] : '#ccc';
                    ?>
                        <span class="ttcg-color-swatch" title="<?php echo esc_attr( $color_name ); ?>">
                            <span class="ttcg-color-swatch__dot" style="background-color: <?php echo esc_attr( $hex ); ?>;"></span>
                            <?php echo esc_html( $color_name ); ?>
                        </span>
                    <?php endforeach; ?>
                </span>
            </div>
        <?php endif; ?>

        <!-- Design instructions -->
        <?php if ( ! empty( $data['feedback'] ) ) : ?>
            <div class="ttcg-detail__info-row ttcg-detail__info-row--full">
                <span class="ttcg-detail__label"><?php esc_html_e( 'Design Instructions', 'twintack-custom-grips' ); ?></span>
                <span class="ttcg-detail__value" id="ttcg-display-feedback"><?php echo nl2br( esc_html( $data['feedback'] ) ); ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Editable view (hidden by default) -->
    <div class="ttcg-edit-form__fields" id="ttcg-edit-view" style="display: none;">
        <?php if ( $can_edit_details ) : ?>
            <div class="ttcg-form-row">
                <label for="ttcg-edit-customer_name"><?php esc_html_e( 'Customer Name', 'twintack-custom-grips' ); ?></label>
                <input type="text" id="ttcg-edit-customer_name" name="customer_name"
                       value="<?php echo esc_attr( $data['customer_name'] ); ?>" class="ttcg-input">
            </div>
            <div class="ttcg-form-row">
                <label for="ttcg-edit-customer_email"><?php esc_html_e( 'Email', 'twintack-custom-grips' ); ?></label>
                <input type="email" id="ttcg-edit-customer_email" name="customer_email"
                       value="<?php echo esc_attr( $data['customer_email'] ); ?>" class="ttcg-input">
            </div>
            <div class="ttcg-form-row">
                <label for="ttcg-edit-team_name"><?php esc_html_e( 'Team / School', 'twintack-custom-grips' ); ?></label>
                <input type="text" id="ttcg-edit-team_name" name="team_name"
                       value="<?php echo esc_attr( $data['team_name'] ); ?>" class="ttcg-input">
            </div>
            <div class="ttcg-form-row">
                <label for="ttcg-edit-quantity"><?php esc_html_e( 'Quantity', 'twintack-custom-grips' ); ?></label>
                <input type="number" id="ttcg-edit-quantity" name="quantity" min="1"
                       value="<?php echo esc_attr( $data['quantity'] ); ?>" class="ttcg-input">
            </div>
        <?php endif; ?>

        <?php if ( $can_edit_designs ) : ?>
            <div class="ttcg-form-row">
                <label for="ttcg-edit-design_type"><?php esc_html_e( 'Design Type', 'twintack-custom-grips' ); ?></label>
                <input type="text" id="ttcg-edit-design_type" name="design_type"
                       value="<?php echo esc_attr( $data['design_type'] ); ?>" class="ttcg-input">
            </div>
            <div class="ttcg-form-row">
                <label for="ttcg-edit-design_layout"><?php esc_html_e( 'Pattern / Layout', 'twintack-custom-grips' ); ?></label>
                <input type="text" id="ttcg-edit-design_layout" name="design_layout"
                       value="<?php echo esc_attr( $data['design_layout'] ); ?>" class="ttcg-input">
            </div>
            <div class="ttcg-form-row">
                <label for="ttcg-edit-primary_color"><?php esc_html_e( 'Primary Color', 'twintack-custom-grips' ); ?></label>
                <input type="text" id="ttcg-edit-primary_color" name="primary_color"
                       value="<?php echo esc_attr( $data['primary_color'] ); ?>" class="ttcg-input">
            </div>
            <div class="ttcg-form-row">
                <label for="ttcg-edit-secondary_color"><?php esc_html_e( 'Secondary Color', 'twintack-custom-grips' ); ?></label>
                <input type="text" id="ttcg-edit-secondary_color" name="secondary_color"
                       value="<?php echo esc_attr( $data['secondary_color'] ); ?>" class="ttcg-input">
            </div>
            <div class="ttcg-form-row">
                <label for="ttcg-edit-tertiary_color"><?php esc_html_e( 'Tertiary Color', 'twintack-custom-grips' ); ?></label>
                <input type="text" id="ttcg-edit-tertiary_color" name="tertiary_color"
                       value="<?php echo esc_attr( $data['tertiary_color'] ); ?>" class="ttcg-input">
            </div>
            <div class="ttcg-form-row ttcg-form-row--full">
                <label for="ttcg-edit-feedback"><?php esc_html_e( 'Design Instructions', 'twintack-custom-grips' ); ?></label>
                <textarea id="ttcg-edit-feedback" name="feedback" rows="4" class="ttcg-input ttcg-textarea"><?php echo esc_textarea( $data['feedback'] ); ?></textarea>
            </div>
        <?php endif; ?>

        <div class="ttcg-edit-form__actions">
            <button type="submit" class="ttcg-btn ttcg-btn--primary" id="ttcg-save-details">
                <?php esc_html_e( 'Save Changes', 'twintack-custom-grips' ); ?>
            </button>
            <button type="button" class="ttcg-btn ttcg-btn--ghost" id="ttcg-cancel-edit">
                <?php esc_html_e( 'Cancel', 'twintack-custom-grips' ); ?>
            </button>
        </div>
        <div class="ttcg-edit-form__feedback" id="ttcg-edit-feedback"></div>
    </div>
</form>
