<?php
/**
 * Cart Page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/cart/cart.php.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.9.0
 */

defined( 'ABSPATH' ) || exit;

get_header(); ?>

<div class="page-wrapper">
    <main class="main">
        <div class="container">
            <?php
            do_action( 'woocommerce_before_cart' );
            ?>

            <form class="woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
                <?php do_action( 'woocommerce_before_cart_table' ); ?>

                <table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents" cellspacing="0">
                    <thead>
                        <tr>
                            <th class="product-remove"><span class="screen-reader-text"><?php esc_html_e( 'Remove item', 'woocommerce' ); ?></span></th>
                            <th class="product-thumbnail"><span class="screen-reader-text"><?php esc_html_e( 'Thumbnail image', 'woocommerce' ); ?></span></th>
                            <th class="product-name"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
                            <th class="product-price"><?php esc_html_e( 'Price', 'woocommerce' ); ?></th>
                            <th class="product-quantity"><?php esc_html_e( 'Quantity', 'woocommerce' ); ?></th>
                            <th class="product-subtotal"><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php do_action( 'woocommerce_before_cart_contents' ); ?>

                        <?php
                        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                            $_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
                            $product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
                            $product_name = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );

                            if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
                                $product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
                                ?>
                                <tr class="woocommerce-cart-form__cart-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
                                    // ... existing code ...
                                </tr>
                                <?php
                            }
                        }
                        ?>

                        <?php do_action( 'woocommerce_cart_contents' ); ?>

                        <tr>
                            <td colspan="6" class="actions">
                                <?php if ( wc_coupons_enabled() ) { ?>
                                    <div class="coupon">
                                        <label for="coupon_code" class="screen-reader-text"><?php esc_html_e( 'Coupon:', 'woocommerce' ); ?></label>
                                        <input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="<?php esc_attr_e( 'Coupon code', 'woocommerce' ); ?>" />
                                        <button type="submit" class="button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'woocommerce' ); ?>"><?php esc_html_e( 'Apply coupon', 'woocommerce' ); ?></button>
                                        <?php do_action( 'woocommerce_cart_coupon' ); ?>
                                    </div>
                                <?php } ?>

                                <button type="submit" class="button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="update_cart" value="<?php esc_attr_e( 'Update cart', 'woocommerce' ); ?>"><?php esc_html_e( 'Update cart', 'woocommerce' ); ?></button>

                                <?php do_action( 'woocommerce_cart_actions' ); ?>

                                <?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
                            </td>
                        </tr>

                        <?php do_action( 'woocommerce_after_cart_contents' ); ?>
                    </tbody>
                </table>
                <?php do_action( 'woocommerce_after_cart_table' ); ?>
            </form>

            <?php do_action( 'woocommerce_before_cart_collaterals' ); ?>

            <div class="cart-collaterals">
                <?php
                    do_action( 'woocommerce_cart_collaterals' );
                ?>
            </div>

            <?php do_action( 'woocommerce_after_cart' ); ?>
        </div>
    </main>
</div>

<?php get_footer(); ?>
