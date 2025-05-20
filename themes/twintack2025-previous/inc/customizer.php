<?php
/**
 * twintack2025 Theme Customizer
 *
 * @package twintack2025
 */

/**
 * Add postMessage support for site title and description for the Theme Customizer.
 *
 * @param WP_Customize_Manager $wp_customize Theme Customizer object.
 */
function twintack2025_customize_register( $wp_customize ) {
	$wp_customize->get_setting( 'blogname' )->transport         = 'postMessage';
	$wp_customize->get_setting( 'blogdescription' )->transport  = 'postMessage';
	$wp_customize->get_setting( 'header_textcolor' )->transport = 'postMessage';

	if ( isset( $wp_customize->selective_refresh ) ) {
		$wp_customize->selective_refresh->add_partial(
			'blogname',
			array(
				'selector'        => '.site-title a',
				'render_callback' => 'twintack2025_customize_partial_blogname',
			)
		);
		$wp_customize->selective_refresh->add_partial(
			'blogdescription',
			array(
				'selector'        => '.site-description',
				'render_callback' => 'twintack2025_customize_partial_blogdescription',
			)
		);
	}

	// Check if the TwinTack_Marquee_Control class exists before using it
	if (class_exists('TwinTack_Marquee_Control')) {
		$wp_customize->add_section('header_marquee', array(
			'title' => __('Marquee Configuration', 'twintack2025'),
			'priority' => 30,
		));

		$wp_customize->add_setting('header_marquee_slides', array(
			'default' => array(),
			'sanitize_callback' => 'twintack2025_sanitize_marquee_slides'
		));

		$wp_customize->add_control(new TwinTack_Marquee_Control($wp_customize, 'header_marquee_slides', array(
			'section' => 'header_marquee',
			'label' => __('Marquee Slides', 'twintack2025'),
			'description' => __('Add slides for the header marquee rotation', 'twintack2025')
		)));
	}
}
add_action( 'customize_register', 'twintack2025_customize_register' );

/**
 * Render the site title for the selective refresh partial.
 *
 * @return void
 */
function twintack2025_customize_partial_blogname() {
	bloginfo( 'name' );
}

/**
 * Render the site tagline for the selective refresh partial.
 *
 * @return void
 */
function twintack2025_customize_partial_blogdescription() {
	bloginfo( 'description' );
}

/**
 * Binds JS handlers to make Theme Customizer preview reload changes asynchronously.
 */
function twintack2025_customize_preview_js() {
	wp_enqueue_script( 'twintack2025-customizer', get_template_directory_uri() . '/js/customizer.js', array( 'customize-preview' ), _S_VERSION, true );
}
add_action( 'customize_preview_init', 'twintack2025_customize_preview_js' );

/**
 * Sanitize the marquee slides
 */
if (!function_exists('twintack2025_sanitize_marquee_slides')) {
    function twintack2025_sanitize_marquee_slides($input) {
        // Add sanitization logic here if needed
        return $input;
    }
}
