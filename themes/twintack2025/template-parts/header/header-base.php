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
        <div class="header-container">
            <!-- Logo and Toggle Container -->
            <div class="header-controls">
                <?php echo custom_logo_svg(); ?>
                <button class="nav-toggle" aria-label="Toggle Navigation">
                    <span class="toggle-line"></span>
                    <span class="toggle-line"></span>
                    <span class="toggle-line"></span>
                </button>
            </div>

            <!-- Navigation Panels -->
            <div class="nav-panels">
                <!-- Sport Navigation -->
                <div class="nav-section">
                    <h2>SPORT</h2>
                    <div class="sport-icons">
                        <a href="/baseball" class="sport-icon baseball">
                            <svg viewBox="0 0 100 100">
                                <!-- Baseball crossed bats icon -->
                                <path d="M20,50 L80,50 M50,20 L50,80" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            <span>BASEBALL</span>
                        </a>
                        <a href="/fishing" class="sport-icon fishing">
                            <svg viewBox="0 0 100 100">
                                <!-- Fishing rod icon -->
                                <path d="M30,70 Q50,20 70,70" stroke="currentColor" stroke-width="2" fill="none"/>
                            </svg>
                            <span>FISHING</span>
                        </a>
                    </div>
                </div>

                <!-- Product Navigation -->
                <div class="nav-section">
                    <h2>PRODUCT</h2>
                    <nav class="product-nav">
                        <a href="/shop">SHOP ALL</a>
                        <a href="/baseball-grips">BASEBALL GRIPS</a>
                        <a href="/fishing-grips">FISHING GRIPS</a>
                    </nav>
                </div>

                <!-- Main Navigation -->
                <div class="nav-section">
                    <nav class="main-nav">
                        <a href="/technology">TECHNOLOGY</a>
                        <a href="/partners">PARTNERS</a>
                        <a href="/contact">CONTACT</a>
                        <a href="/my-account">MY ACCOUNT</a>
                        <a href="/cart">CART</a>
                    </nav>
                </div>
            </div>
        </div>
    </header>

    <?php get_template_part('template-parts/marquee/marquee', 'content'); ?>

    <div id="content" class="site-content">