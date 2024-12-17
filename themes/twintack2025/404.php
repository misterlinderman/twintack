<?php
/**
 * The template for displaying 404 pages
 *
 * @package twintack2025
 */

get_header();

// Get header configuration and render
get_template_part('template-parts/header/header', 'base');
?>

<main id="primary" class="site-main">
	<section class="error-404 not-found">
		<header class="page-header">
			<h1 class="page-title"><?php esc_html_e('Oops! That page can&rsquo;t be found.', 'twintack2025'); ?></h1>
		</header>

		<div class="page-content">
			<p><?php esc_html_e('It looks like nothing was found at this location. Maybe try one of the links below or a search?', 'twintack2025'); ?></p>

			<?php
			get_search_form();
			the_widget('WP_Widget_Recent_Posts');
			?>

			<div class="widget widget_categories">
				<h2 class="widget-title"><?php esc_html_e('Most Used Categories', 'twintack2025'); ?></h2>
				<ul>
					<?php
					wp_list_categories(array(
						'orderby'    => 'count',
						'order'      => 'DESC',
						'show_count' => 1,
						'title_li'   => '',
						'number'     => 10,
					));
					?>
				</ul>
			</div>
		</div>
	</section>
</main>

<?php get_footer(); ?>
