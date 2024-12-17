<?php
/**
 * Template part for displaying content on the Twintack homepage
 *
 * @package twintack2025
 */

?>

<div class="twintack-homepage-content">
	<?php if (have_rows('content_configurations')): ?>
		<?php while (have_rows('content_configurations')): the_row(); ?>
			
			<?php if (get_row_layout() == 'full_width_callout'): ?>
				<section class="full-width-callout">
					<div class="container">
						<?php if ($title = get_sub_field('callout_title')): ?>
							<h2><?php echo esc_html($title); ?></h2>
						<?php endif; ?>
						
						<?php if ($content = get_sub_field('callout_content')): ?>
							<div class="callout-content">
								<?php echo wp_kses_post($content); ?>
							</div>
						<?php endif; ?>

						<?php if ($cta = get_sub_field('callout_cta')): ?>
							<div class="callout-cta">
								<a href="<?php echo esc_url($cta['url']); ?>" 
								   class="button"
								   <?php echo $cta['target'] ? 'target="' . esc_attr($cta['target']) . '"' : ''; ?>>
									<?php echo esc_html($cta['title']); ?>
								</a>
							</div>
						<?php endif; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php 
			// Add more layout conditions here as needed
			// Example:
			// if (get_row_layout() == 'another_layout'): 
			?>

		<?php endwhile; ?>
	<?php endif; ?>
</div>
