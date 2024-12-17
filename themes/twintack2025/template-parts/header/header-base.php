<?php
/**
 * Base header template part
 *
 * @package twintack2025
 */
?>

<header id="masthead" class="site-header">
    <div class="header-main">
        <div class="container">
            <div class="site-branding">
                <?php the_custom_logo(); ?>
            </div>

            <!-- Primary Navigation -->
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

    <!-- Secondary Category Navigation -->
    <?php get_template_part('template-parts/header/navigation', 'category'); ?>
</header>