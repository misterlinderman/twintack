<?php
/**
 * Template Name: Custom Grip Inquiry
 *
 * Simplified custom grip intake. The introduction is edited on the page.
 * Gravity Forms notifications deliver the request to sales.
 *
 * The Custom Grip Introduction template remains available for the previous flow.
 *
 * @package twintack2025
 */

get_header();

$intro_message = '';
$form_id       = 0;

if ( function_exists( 'get_field' ) ) {
	$intro_message = get_field( 'inquiry_intro_message' );
	$form_id       = absint( get_field( 'inquiry_gravity_form' ) );
}

if ( ! is_string( $intro_message ) || '' === trim( wp_strip_all_tags( $intro_message ) ) ) {
	$intro_message = TwinTack_Custom_Grip_Inquiry_Fields::default_intro_message();
}
?>

<div class="custom-grip-inquiry-wrapper">
	<main id="primary" class="main custom-grip-inquiry">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<section class="custom-grip-inquiry__panel">
				<h1 class="custom-grip-inquiry__title"><?php the_title(); ?></h1>

				<div class="custom-grip-inquiry__intro">
					<?php echo wp_kses_post( $intro_message ); ?>
				</div>

				<div class="custom-grip-inquiry__form">
					<?php echo TwinTack_Custom_Grip_Inquiry_Fields::render_form( $form_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Gravity Forms escapes its markup. ?>
				</div>
			</section>
			<?php
		endwhile;
		?>
	</main>
</div>

<?php
get_footer();
