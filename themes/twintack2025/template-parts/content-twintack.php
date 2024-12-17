<?php
/**
 * Template part for displaying content on the Twintack homepage
 *
 * @package twintack2025
 */

?>

<div class="twintack-homepage-content">
	<?php if (have_rows('homepage_sections')): ?>
		<?php while (have_rows('homepage_sections')): the_row(); ?>
			
			<?php if (get_row_layout() == 'hero_section'): ?>
				<section class="hero-section">
					<div class="container">
						<?php if ($title = get_sub_field('title')): ?>
							<h1><?php echo esc_html($title); ?></h1>
						<?php endif; ?>
						
						<?php if ($content = get_sub_field('content')): ?>
							<div class="hero-content">
								<?php echo wp_kses_post($content); ?>
							</div>
						<?php endif; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if (get_row_layout() == 'content_section'): ?>
				<section class="content-section">
					<div class="container">
						<?php if ($heading = get_sub_field('heading')): ?>
							<h2><?php echo esc_html($heading); ?></h2>
						<?php endif; ?>
						
						<?php if ($content = get_sub_field('content')): ?>
							<div class="section-content">
								<?php echo wp_kses_post($content); ?>
							</div>
						<?php endif; ?>
					</div>
				</section>
			<?php endif; ?>

		<?php endwhile; ?>
	<?php endif; ?>
</div>
