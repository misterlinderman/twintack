<?php

/**
 * Amazon for WooCommerce
 *
 * @link              https://woocommerce.com/vendor/cedcommerce/
 * @since             1.0.0
 * @package           Amazon_For_Woocommerce
 *
 * @wordpress-plugin
 * Plugin Name:       Amazon for WooCommerce
 * Plugin URI:        https://woocommerce.com/document/amazon-for-woocommerce/
 * Description:       Configure and sell your WooCommerce products on Amazon.

 * Version:           1.2.6
 * Author:            CedCommerce
 * Author URI:        https://woocommerce.com/vendor/cedcommerce/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       amazon-for-woocommerce
 * Domain Path:       /languages
 *
 * Woo: 18734002472433:11f0c73d890ed3c1b76389bb9928891d
 * WC requires at least: 4.0
 * WC tested up to: 8.0.2
 * Requires PHP: 7.4 
 * Requires at least: 5.6 
 * Tested up to: 7.4
 */


if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'AMAZON_INTEGRATION_FOR_WOOCOMMERCE_VERSION', '1.1.8' );

if ( ! defined( 'CED_LIVE_VALIDATOR' ) ) {
	define( 'CED_LIVE_VALIDATOR', 'https://api.cedcommerce.com/cedcommerce-validator/' );
}

if ( ! defined( 'CED_SANDBOX_VALIDATOR' ) ) {
	define( 'CED_SANDBOX_VALIDATOR', 'https://api.cedcommerce.com/sandbox/cedcommerce-validator/' );
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-amazon-integration-for-woocommerce-activator.php
 */
function activate_amazon_integration_for_woocommerce() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-amazon-integration-for-woocommerce-activator.php';
	Amazon_Integration_For_Woocommerce_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-amazon-integration-for-woocommerce-deactivator.php
 */
function deactivate_amazon_integration_for_woocommerce() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-amazon-integration-for-woocommerce-deactivator.php';
	Amazon_Integration_For_Woocommerce_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_amazon_integration_for_woocommerce' );
register_deactivation_hook( __FILE__, 'deactivate_amazon_integration_for_woocommerce' );


/* DEFINE CONSTANTS */
define( 'CED_AMAZON_DIRPATH', plugin_dir_path( __FILE__ ) );
define( 'CED_AMAZON_URL', plugin_dir_url( __FILE__ ) );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-amazon-integration-for-woocommerce.php';


/**
* This file includes core functions to be used globally in plugin.
 *
* @link  http://www.cedcommerce.com/
*/
require_once plugin_dir_path( __FILE__ ) . 'includes/ced-amazon-core-functions.php';

/**
 * Check WooCommerce activation.
 */
if ( ced_amazon_check_woocommerce_active( 'woocommerce/woocommerce.php' ) ) {
 
	run_amazon_integration_for_woocommerce();
	/* Register activation hook. */
	register_activation_hook( __FILE__, 'ced_admin_notice_example_activation_hook_ced_amazon' );
	// add_action( 'admin_notices', 'ced_amazon_admin_notice_activation' );

} else {

	add_action( 'admin_init', 'deactivate_ced_amazon_woo_missing' );

}


/**
 * Check WooCommmerce active or not.
 *
 * @name ced_amazon_check_woocommerce_active
 * @since 1.0.0
 * @return bool true|false
 * @link  http://www.cedcommerce.com/
 */
function ced_amazon_check_woocommerce_active( $file = '' ) {

	if ( function_exists( 'is_multisite' ) && is_multisite() ) {

		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		if ( is_plugin_active( $file ) ) {
			return true;
		}
		return false;
	} else {
		/**
		 * Function to get list of active plugins
		 *
		 * @param 'function'
		 * @return 'list'
		 * @since 1.0.0
		 */
		$installedPlugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
		if ( in_array( $file, $installedPlugins ) ) {

			return true;
		}
		return false;
	}
}

/**
 * This code runs when WooCommerce is not activated,
 * deativates the extension and displays the notice to admin.
 *
 * @name deactivate_ced_amazon_woo_missing
 * @since 1.0.0
 * @link  http://www.cedcommerce.com/
 */
function deactivate_ced_amazon_woo_missing() {

	deactivate_plugins( 'amazon-for-woocommerce/amazon-for-woocommerce.php' );
	add_action( 'admin_notices', 'ced_amazon_woo_missing_notice' );

	if ( isset( $_GET['activate'] ) ) {
		unset( $_GET['activate'] );
	}
}

/**
 * Callback function for sending notice if woocommerce is not activated.
 *
 * @name ced_amazon_woo_missing_notice
 * @since 1.0.0
 * @return string
 * @link  http://www.cedcommerce.com/
 */
function ced_amazon_woo_missing_notice() {

	$activate_url  = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=woocommerce/woocommerce.php' ), 'activate-plugin_woocommerce/woocommerce.php' );
	$activate_text = __( 'Activate WooCommerce', 'amazon-for-woocommerce' );

	if ( current_user_can( 'install_plugins' ) ) {
		if ( is_wp_error( validate_plugin( 'woocommerce/woocommerce.php' ) ) ) {
			// WooCommerce is not installed.
			$activate_url  = wp_nonce_url( admin_url( 'update.php?action=install-plugin&plugin=woocommerce' ), 'install-plugin_woocommerce' );
			$activate_text = __( 'Install WooCommerce', 'amazon-for-woocommerce' );
		} else {
			// WooCommerce is installed, so it just needs to be enabled.
			$activate_url  = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=woocommerce/woocommerce.php' ), 'activate-plugin_woocommerce/woocommerce.php' );
			$activate_text = __( 'Activate WooCommerce', 'amazon-for-woocommerce' );
		}

		echo '<div class="error"><p>' . esc_html__( 'Amazon for WooCommerce requires WooCommerce plugin to be installed and active. ', 'amazon-for-woocommerce' ) . '
        <a href="' . esc_attr( $activate_url ) . '" id="activate-woocommerce" > Click here to ' . esc_html__( $activate_text, 'amazon-for-woocommerce' ) . '</p></div>';

	}

	return '';
}

/**
 * Callback function for sending notice while plugin activation error.
 */
function ced_amazon_plugin_activation_error() {
	throw new Exception( esc_html__( 'Amazon for WooCommerce requires WooCommerce plugin to be installed and active.', 'cln' ) );
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_amazon_integration_for_woocommerce() {

	$plugin = new Amazon_Integration_For_Woocommerce();
	$plugin->run();
}

/**
 * Runs only when the plugin is activated.
 *
 * @since 1.0.0
 */
function ced_admin_notice_example_activation_hook_ced_amazon() {

	/* Create transient data */
	set_transient( 'ced-amazon-admin-notice', true, 5 );
}

/**
 * Admin Notice on Activation.
 *
 * @since 0.1.0
 */

function ced_amazon_admin_notice_activation() {

	$next_step_url      = ced_get_navigation_url( 'amazon' );
	$connected_accounts = get_option( 'ced_amazon_sellernext_shop_ids', array() );

	if ( get_transient( 'ced-amazon-admin-notice' ) ) {
		?>

	<div class="updated notice is-dismissible">
		<p>Welcome to Amazon Integration for WooCommerce. Get ready to automate, sync, and easily sell your WooCommerce products on Amazon.</p>
		<?php
		if ( empty( $connected_accounts ) ) {
			?>
				<p> To get started , proceed with <a href="<?php echo esc_url( $next_step_url ); ?>" class ="ced_configuration_plugin_main">connecting</a> your Amazon marketplace account. </p>
			<?php
		} else {
			?>
				<p><a href="<?php echo esc_url( $next_step_url ); ?>" class ="ced_configuration_plugin_main">Manage</a></p>
			<?php
		}
		?>
	</div>
		<?php

		/* Delete transient, only display this notice once. */
		delete_transient( 'ced-amazon-admin-notice' );

	}
}


add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );


?>
