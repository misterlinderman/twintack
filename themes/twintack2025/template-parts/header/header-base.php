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
    <?php do_action('twintack_before_page_content'); ?>
    
    <!-- Fixed Navigation Icons -->
    <div class="fixed-nav-icons">
        <a href="/my-account" class="account-icon">
            <svg viewBox="0 0 24 24" class="icon-svg">
                <path class="icon-path" d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
            </svg>
            <span class="screen-reader-text">My Account</span>
        </a>
        <a href="/cart" class="cart-icon">
            <svg viewBox="0 0 24 24" class="icon-svg">
                <path class="icon-path" d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/>
            </svg>
            <span class="screen-reader-text">Cart</span>
        </a>
    </div>
    
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
                <!-- Custom Grips Navigation -->
                <div class="nav-section">
                    <h2><a class="custom-grips-link" href="/twintack-custom-grips/">CUSTOM GRIPS</a></h2>
                </div>
                <!-- Sport Navigation -->
                <div class="nav-section">
                    <h2>SPORT</h2>
                    <div class="sport-icons">
                        <a href="/baseball/" class="sport-icon baseball">
                            
                            <svg id="baseball" viewBox="0 0 1024 1024">
                                <path id="baseball-art" d="M952,0H72C32.2,0,0,32.2,0,72v880c0,39.8,32.2,72,72,72h880c39.8,0,72-32.2,72-72V72c0-39.8-32.2-72-72-72ZM504.5,140.3c73.9-6.1,126.3,74,90.9,139.6-32.3,60-115.7,65.7-158.1,13.1-47.3-58.7-6.6-146.6,67.2-152.7h0ZM896.5,277.3l-130.5,129.5-192.5,171.2c41.2,44.2,81.5,89.3,123.1,133.1,21.7,22.9,48.6,54.6,71.9,74.4,1.8,1.5,6.7,5.9,8.3,6.7,11.5,6,20.2-12.7,37.9-.4,11.1,7.7,11.5,20.7,3.8,31.2-14.4,19.7-42.2,37.5-58.1,56.9-13.3,8.9-31.8,2.3-35.1-13.7-3.4-16.9,15.9-20.2,9.5-35.8l-222.1-199.2-222.4,199.9c-8.3,10,1.7,13,5.5,20.8,10.4,21.2-13.2,40.3-32.3,28.5-16.2-19.2-42-37.4-57-56.8-13.9-18.1,1.1-40.6,21.7-35.4,7,1.8,13.7,9.7,20.9,5.5,34.1-36.7,69.5-72.2,103.7-108.8,30.1-32.2,62.5-65.7,91.3-98.7,1.1-1.3,7.1-8.5,6.9-9.4l-182.5-160-141.9-140.6c-23.7-30.6-1.9-60.4,22.5-81.2,30.4-26,49-19.8,76.2,4.9,33,30,68.1,67.2,98.7,100,64.4,69.2,123.2,143.6,189.3,211.3,0-1.5.9-2.7,1.8-3.8,25.7-29.2,52.9-57.2,78.8-86.2,67.3-75.3,138-162.5,214.3-228.2,20.6-17.7,38.7-20,61.2-3.6,28.4,20.7,53,54.6,26.9,87.9h.2Z"/>
                            </svg>
                            <span>BASEBALL</span>
                        </a>
                        <a href="/fishing/" class="sport-icon fishing">
                            
                            <svg id="fishing" viewBox="0 0 1024 1024">
                                <path id="fishing-art" d="M375.7,330.3c8.3,6,16.1-6.6,10.4-12.3-7.1-7.1-18.9,6.2-10.4,12.3ZM302.6,552.1c19.3,6,17.4-26.4,1-21.7-6.8,1.9-9.4,19.1-1,21.7ZM952,0H72C32.2,0,0,32.2,0,72v880c0,39.8,32.2,72,72,72h880c39.8,0,72-32.2,72-72V72c0-39.8-32.2-72-72-72ZM788.9,817c-7.7,97.6-138,121.8-182.7,36.5-13.9-26.5-11.2-43.3-11-71.4.2-29.1,0-58.2-.3-87.4-4.9-4.3-10.9-5.8-16.1-10.2-12.3-10.5-18-26.4-16.1-42.4.6-4.8,4.4-11.8,3.6-16.1-.2-1.1-13.1-14.1-15.5-17.3-36.4-47.9-43.5-106.1-26.1-163.3,23.5-77.1,87.9-141.9,96.6-223.4,3.4-31.6-1.2-64.6-11.9-94.4-79.5,25.5-151.9,80.1-203,145.7-2.4,3.1-15.4,19.5-13.7,21.8,36.8,15.2,18.7,67.6-20.4,61-4-.7-11.9-6.4-13.5-5.4-25.3,50-47.9,104.7-55,160.7,48.7,1,37.9,72.2-10,58.7-5.4,27.4-5.8,55.3-6.2,83.2-.1,9.8-2.2,19.2.2,29.7,1.2,5.1,7.3,7.8,8.4,16.6,1.7,14.2,1.3,30.3,2.6,44.9,3.4,35.4,7.7,70.9,14.1,105.9,2.3,12.7,10.9,39.3,9.3,50.1-4.9,32.5-51.8,37.1-64.1,5.9-6.3-16-9.4-53.5-11.7-72-4.7-37.4-9.8-79.9-11.4-117.4-.3-5.8-.8-15.7,0-21.2.7-4.2,4.3-6.8,4.9-10.1,2.8-15,1.2-39.6,2.6-56.2,18.7-223.6,129.9-451.2,354.3-526.9,9.6-3.2,32.8-13.7,35.6,3.2,1.8,10.9-4.9,12.4-5,17.4,0,5.2,7,25,8.4,32.9,19.9,111.7-36.2,164.2-78.6,255.6-28.2,60.8-36.7,122.6,3.7,180.5,2.5,3.5,11.8,16.1,14.6,17.9,4.2,2.8,8.6-3.5,13.1-5.7,18.8-9.5,43.3-6.4,58.4,8.6,24.9,24.9,16.2,69.4-18.1,79.7v117.9c9.1,82.1,125.8,79.9,125-4.9-2.3-3-25.2,1-30.1,1.2-1.6,0-1.8,1.4-1.3-1.3,2.1-11.4,19.4-41.2,24.7-55.3,4.8-12.6,9.6-25.6,11.6-39.1,2.9-.6,2,.4,2.8,1.6,15.7,24.2,29.4,75.4,27.2,104v.2ZM608.6,629.2c-4.5.7-10.4,4.7-12.8,8.5-10.5,17.1,9.8,36.8,26.7,25.5,17.7-11.8,5.9-37.1-14-34h0Z"/>
                            </svg>
                            <span>FISHING</span>
                        </a>
                    </div>
                </div>

                <!-- Product Navigation -->
                <div class="nav-section">
                    <h2>PRODUCT</h2>
                    <nav class="product-nav">
                        <a href="/shop/">SHOP ALL</a>
                        <a href="/shop/?product_cat=baseball">BASEBALL GRIPS</a>
                        <a href="/shop/?product_cat=fishing">FISHING GRIPS</a>
                        <a href="/shop/?product_cat=accessory">ACCESSORIES</a>
                    </nav>
                </div>

                <!-- Main Navigation -->
                <div class="nav-section">
                    <h2>TWINTACK</h2>
                    <nav class="main-nav">
                        <a href="/twintack/#ourstory">OUR STORY</a>
                        <a href="/twintack/#technology">TECHNOLOGY</a>
                        <a href="/contact/">CONTACT</a>
                        <a href="/login/">LOGIN/REGISTER</a>
                    </nav>
                </div>
            </div>

        </div>
    </header>

    <?php get_template_part('template-parts/marquee/marquee', 'content'); ?>

    <div id="content" class="site-content">