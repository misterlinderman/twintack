<?php
/**
 * Status controls partial — dropdown for changing artwork status.
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

<div class="ttcg-detail__section ttcg-status-controls" data-design-id="<?php echo esc_attr( $design_id ); ?>">
    <h3 class="ttcg-detail__section-title"><?php esc_html_e( 'Update Status', 'twintack-custom-grips' ); ?></h3>
    <div class="ttcg-status-controls__inner">
        <select id="ttcg-status-select" class="ttcg-status-controls__select">
            <option value=""><?php esc_html_e( '— Select New Status —', 'twintack-custom-grips' ); ?></option>
            <?php foreach ( $allowed_statuses as $status_slug ) :
                if ( ! isset( $all_statuses[ $status_slug ] ) ) continue;
                $s = $all_statuses[ $status_slug ];
            ?>
                <option value="<?php echo esc_attr( $status_slug ); ?>"
                        <?php selected( $status_slug, $status ); ?>
                        data-color="<?php echo esc_attr( $s['color'] ); ?>">
                    <?php echo esc_html( $s['label'] ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="button" id="ttcg-status-save" class="ttcg-btn ttcg-btn--primary" disabled>
            <?php esc_html_e( 'Update Status', 'twintack-custom-grips' ); ?>
        </button>
    </div>
    <div class="ttcg-status-controls__feedback" id="ttcg-status-feedback"></div>
</div>
