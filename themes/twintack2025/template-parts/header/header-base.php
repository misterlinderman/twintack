<?php
/**
 * Base header template part
 *
 * @package twintack2025
 */
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="page" class="site">
    <a class="skip-link screen-reader-text" href="#primary">
        <?php esc_html_e('Skip to content', 'twintack2025'); ?>
    </a>

    <header id="masthead" class="site-header" data-nav-state="closed">
        <nav class="corporate-nav">
            <div></div>
            <?php
            wp_nav_menu(array(
                'theme_location' => 'primary',
                'menu_class' => 'corporate-menu',
                'container_class' => 'primary-menu-container'
            ));
            ?>
        </nav>

        <div class="logo-container">
            <?php the_custom_logo(); ?>
            <button class="nav-toggle" aria-label="Toggle Navigation">
                <svg class="nav-arrow" viewBox="0 0 24 24" width="24" height="24">
                    <path d="M7 10l5 5 5-5H7z" fill="currentColor"/>
                </svg>
            </button>
        </div>

        <div class="category-nav">
            <div class="product-selector">
                <button class="select-products">
                    SELECT PRODUCTS
                    <svg viewBox="0 0 24 24" width="16" height="16">
                        <path d="M7 10l5 5 5-5H7z" fill="currentColor"/>
                    </svg>
                </button>
                <div class="product-menu">
                    <a href="/product-category/baseball" class="product-link">BASEBALL</a>
                    <a href="/product-category/fishing" class="product-link">FISHING</a>
                </div>
            </div>

            <?php get_template_part('template-parts/header/navigation', 'category'); ?>
        </div>
    </header>

    <?php get_template_part('template-parts/marquee/marquee', 'content'); ?>

    <div id="content" class="site-content">