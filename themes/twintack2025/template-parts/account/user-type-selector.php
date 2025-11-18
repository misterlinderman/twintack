<?php
/**
 * Template part for displaying the user type selector
 *
 * @package twintack2025
 */

// Don't allow direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Default values
$field_name = isset( $args['field_name'] ) ? $args['field_name'] : 'user_type';
$selected = isset( $args['selected'] ) ? $args['selected'] : 'customer';
?>

<div class="twintack-user-type-selection">
    <p><?php esc_html_e( 'I am a:', 'twintack2025' ); ?></p>
    <div class="user-type-options">
        <label>
            <input type="radio" name="<?php echo esc_attr( $field_name ); ?>" value="customer" <?php checked( $selected, 'customer' ); ?> />
            <span><?php esc_html_e( 'Customer', 'twintack2025' ); ?></span>
        </label>
        <label>
            <input type="radio" name="<?php echo esc_attr( $field_name ); ?>" value="wholesale" <?php checked( $selected, 'wholesale' ); ?> />
            <span><?php esc_html_e( 'Wholesale Buyer', 'twintack2025' ); ?></span>
        </label>
        <label>
            <input type="radio" name="<?php echo esc_attr( $field_name ); ?>" value="affiliate" <?php checked( $selected, 'affiliate' ); ?> />
            <span><?php esc_html_e( 'Affiliate Partner', 'twintack2025' ); ?></span>
        </label>
    </div>
</div> 