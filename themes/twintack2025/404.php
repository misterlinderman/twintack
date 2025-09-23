<?php
/**
 * The template for displaying 404 pages with intelligent redirects
 *
 * @package twintack2025
 */

get_header();

// Get the current URL for intelligent suggestions
$current_url = $_SERVER['REQUEST_URI'];
$url_parts = explode('/', trim($current_url, '/'));
?>

<div class="page-wrapper">
	<main class="main">
	<section class="error-404 not-found">
		<div class="container">
			<div class="error-404-content">
				
				<!-- Main Error Message -->
				<div class="error-header">
					<h1 class="error-title">404</h1>
					<h2 class="error-subtitle">Page Not Found</h2>
					<p class="error-message">
						<?php esc_html_e("We couldn't find the page you're looking for, but we can help you get back on track!", 'twintack2025'); ?>
					</p>
				</div>

				<?php
				// Intelligent redirect suggestions
				$suggestions = twintack_get_404_suggestions($current_url, $url_parts);
				
				if (!empty($suggestions)): ?>
					<div class="suggested-pages">
						<h3><?php esc_html_e('Were you looking for one of these?', 'twintack2025'); ?></h3>
						<div class="suggestions-grid">
							<?php foreach ($suggestions as $suggestion): ?>
								<a href="<?php echo esc_url($suggestion['url']); ?>" class="suggestion-card">
									<div class="suggestion-icon">
										<?php echo $suggestion['icon']; ?>
									</div>
									<h4><?php echo esc_html($suggestion['title']); ?></h4>
									<p><?php echo esc_html($suggestion['description']); ?></p>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<!-- Sport Navigation -->
				<div class="sport-navigation">
					<h3><?php esc_html_e('Explore by Sport', 'twintack2025'); ?></h3>
					<div class="sport-cards">
						<a href="/baseball/" class="sport-card baseball">
							<div class="sport-icon">
								<?php echo file_get_contents(get_template_directory() . '/baseball-icon-3.svg'); ?>
							</div>
							<h4>Bat Grips</h4>
							<p>Grips, accessories, and custom solutions for baseball and softball</p>
						</a>
						<a href="/fishing/" class="sport-card fishing">
							<div class="sport-icon">
								<?php echo file_get_contents(get_template_directory() . '/fishing-icon-3.svg'); ?>
							</div>
							<h4>Fishing</h4>
							<p>Grips, accessories, and custom solutions for fishing</p>
						</a>
					</div>
				</div>

				<!-- Quick Links -->
				<div class="quick-links">
					<h3><?php esc_html_e('Popular Pages', 'twintack2025'); ?></h3>
					<div class="quick-links-grid">
						<a href="/shop/" class="quick-link">
							<span class="link-icon">🛒</span>
							<span>Shop All Products</span>
						</a>
						<a href="/twintack-custom-grips/" class="quick-link">
							<span class="link-icon">🎨</span>
							<span>Custom Grips</span>
						</a>
						<a href="/twintack/" class="quick-link">
							<span class="link-icon">ℹ️</span>
							<span>Our Story</span>
						</a>
						<a href="/contact/" class="quick-link">
							<span class="link-icon">📞</span>
							<span>Contact Us</span>
						</a>
						<?php if (is_user_logged_in()): ?>
							<a href="/my-account/" class="quick-link">
								<span class="link-icon">👤</span>
								<span>My Account</span>
							</a>
						<?php else: ?>
							<a href="/login/" class="quick-link">
								<span class="link-icon">🔐</span>
								<span>Login/Register</span>
							</a>
						<?php endif; ?>
					</div>
				</div>

				<!-- Search Form -->
				<div class="error-search">
					<h3><?php esc_html_e('Search Our Site', 'twintack2025'); ?></h3>
					<?php get_search_form(); ?>
				</div>

			</div>
		</div>
	</section>
	</main>
</div>

<?php get_footer(); ?>
