<?php
/**
 * My Account navigation
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/navigation.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get the my account page URL for consistent base
$account_url = wc_get_page_permalink('myaccount');
// Make sure it ends with a slash
$account_url = trailingslashit($account_url);

do_action( 'woocommerce_before_account_navigation' );
?>

<nav class="woocommerce-MyAccount-navigation" aria-label="<?php esc_html_e( 'Account pages', 'woocommerce' ); ?>">
	<ul>
		<?php 
		// Get the menu items
		$menu_items = wc_get_account_menu_items();
		
		// Loop through each menu item
		foreach ( $menu_items as $endpoint => $label ) : 
			// Generate the correct URL
			if ($endpoint === 'dashboard') {
				$url = $account_url;
			} else {
				$url = $account_url . $endpoint;
			}
			
			// Add trailing slash for consistency
			$url = trailingslashit($url);
			
			// Get the classes
			$classes = wc_get_account_menu_item_classes( $endpoint );
		?>
			<li class="<?php echo esc_attr($classes); ?>">
				<a href="<?php echo esc_url($url); ?>" <?php echo wc_is_current_account_menu_item( $endpoint ) ? 'aria-current="page"' : ''; ?>>
					<?php echo esc_html( $label ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>

<?php do_action( 'woocommerce_after_account_navigation' ); ?>
