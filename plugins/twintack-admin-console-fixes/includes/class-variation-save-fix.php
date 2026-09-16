<?php
/**
 * Variation Save Fix
 *
 * WooCommerce variation AJAX posts every field for every dirty variation.
 * Products with wholesale roles, Amazon fields, and variation galleries
 * easily exceed PHP max_input_vars. WordPress then never sees `action` and
 * returns HTTP 400, while the WooCommerce script leaves the spinner running.
 */

class TwinTack_Variation_Save_Fix {

	/**
	 * Hook script enqueue.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_script' ), 30 );
	}

	/**
	 * Load the patch after WooCommerce variation metabox scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_script( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}

		wp_enqueue_script(
			'twintack-variation-save-fix',
			TWINTACK_CONSOLE_FIXES_PLUGIN_URL . 'assets/js/variation-save-fix.js',
			array( 'jquery', 'wc-admin-variation-meta-boxes' ),
			TWINTACK_CONSOLE_FIXES_VERSION,
			true
		);
	}
}
