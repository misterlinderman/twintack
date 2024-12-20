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

    <header id="masthead" class="site-header">
        <div class="header-main">
            <div class="container">
                <div class="site-branding">
                    <?php the_custom_logo(); ?>
                </div>

                <nav id="site-navigation" class="main-navigation">
                    <?php
                    wp_nav_menu(array(
                        'theme_location' => 'primary',
                        'menu_id' => 'primary-menu',
                        'container_class' => 'primary-menu-container'
                    ));
                    ?>
                </nav>
            </div>
        </div>
        <?php get_template_part('template-parts/header/navigation', 'category'); ?>
    </header>

    <?php get_template_part('template-parts/marquee/marquee', 'content'); ?>

    <div id="content" class="site-content">