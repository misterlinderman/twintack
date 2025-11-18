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
								<a href="<?php echo esc_url($cta); ?>" class="button">
									<?php echo esc_html(get_sub_field('callout_link_text')); ?>
								</a>
							</div>
						<?php endif; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if (get_row_layout() == 'twintack_info_block'): ?>
				<?php
				$bg_color = get_sub_field('background_color');
				$title = get_sub_field('title');
				$hero_background = get_sub_field('hero_background');
				$hero_image = get_sub_field('hero_image');
				$content = get_sub_field('content');
				$link_text = get_sub_field('link_text');
				$link_url = get_sub_field('link_url');
				?>
				
				<section class="twintack-info-block" <?php if ($bg_color) : ?>style="background-color: <?php echo esc_attr($bg_color); ?>"<?php endif; ?>>
					<div class="container">
						<?php if ($title) : ?>
							<h2 class="info-block-title"><?php echo esc_html($title); ?></h2>
						<?php endif; ?>

						<div class="info-block-content">
							<div class="info-block-hero"<?php if ($hero_background) : ?> style="background-image: url('<?php echo esc_url($hero_background); ?>'); background-size: cover; background-position: center; background-repeat: no-repeat;"<?php endif; ?>>
								<?php if ($hero_image) : ?>
									<img src="<?php echo esc_url($hero_image); ?>" alt="<?php echo esc_attr($title); ?>" class="hero-overlay-image">
								<?php endif; ?>
							</div>

							<?php if ($content) : ?>
								<div class="info-block-text">
									<?php echo wp_kses_post($content); ?>
								</div>
							<?php endif; ?>

							<?php if ($link_text && $link_url) : ?>
								<div class="info-block-cta">
									<a href="<?php echo esc_url($link_url); ?>" class="button">
										<?php echo esc_html($link_text); ?>
									</a>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<?php if (get_row_layout() == 'twintack_callouts'): ?>
				<section class="twintack-callouts" <?php if ($bg_color = get_sub_field('background_color')): ?>style="background-color: <?php echo esc_attr($bg_color); ?>"<?php endif; ?>>
					<div class="container">
						<?php if (have_rows('twintack_callouts')): ?>
							<div class="callouts-grid">
								<?php while (have_rows('twintack_callouts')): the_row(); ?>
									<div class="callout-card">
										<?php if ($title = get_sub_field('title')): ?>
											<h3 class="callout-title"><?php echo esc_html($title); ?></h3>
										<?php endif; ?>

										<?php if ($hero_img = get_sub_field('callout_hero_image')): ?>
											<div class="callout-image">
												<img src="<?php echo esc_url($hero_img); ?>" alt="<?php echo esc_attr($title); ?>">
											</div>
										<?php endif; ?>

										<?php if ($description = get_sub_field('description')): ?>
											<div class="callout-description">
												<?php echo wp_kses_post($description); ?>
											</div>
										<?php endif; ?>

										<?php if ($link_text = get_sub_field('link_text') && $link_url = get_sub_field('link_url')): ?>
											<div class="callout-cta">
												<a href="<?php echo esc_url($link_url); ?>" class="button">
													<?php echo esc_html($link_text); ?>
												</a>
											</div>
										<?php endif; ?>
									</div>
								<?php endwhile; ?>
							</div>
						<?php endif; ?>
					</div>
				</section>
			<?php endif; ?>

		<?php endwhile; ?>
	<?php endif; ?>
</div>
