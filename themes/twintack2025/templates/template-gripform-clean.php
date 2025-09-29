<?php
/*
Template Name: Clean Grip Form (No Marketing Scripts)
Description: Custom template for grip configuration form that excludes all marketing scripts and tracking
*/

// AGGRESSIVE script isolation for clean grip form template
add_action('init', function() {
    // Remove WP Analytify tracking
    remove_action('wp_head', 'analytify_tracking_code');
    remove_action('wp_footer', 'analytify_tracking_code');
    remove_action('wp_head', 'analytify_gtag_script');
    remove_action('wp_footer', 'analytify_gtag_script');
    
    // Remove Facebook for WooCommerce tracking (but keep plugin active)
    remove_action('wp_head', 'wc_facebook_pixel_init');
    remove_action('wp_footer', 'wc_facebook_pixel_init');
    remove_action('wp_head', 'wc_facebook_pixel_event');
    remove_action('wp_footer', 'wc_facebook_pixel_event');
    
    // Remove Facebook Pixel class initialization
    if (class_exists('WC_Facebookcommerce_Pixel')) {
        remove_action('wp_head', array('WC_Facebookcommerce_Pixel', 'pixel_init_code'));
        remove_action('wp_footer', array('WC_Facebookcommerce_Pixel', 'pixel_init_code'));
    }
    
    // Remove Facebook Pixel script enqueuing
    remove_action('wp_enqueue_scripts', 'wc_facebook_pixel_scripts');
    remove_action('wp_enqueue_scripts', 'wc_facebook_pixel_events');
    
    // Remove Facebook Pixel from WooCommerce hooks
    remove_action('woocommerce_after_single_product_summary', 'wc_facebook_pixel_view_content');
    remove_action('woocommerce_after_shop_loop', 'wc_facebook_pixel_view_category');
    remove_action('woocommerce_after_cart', 'wc_facebook_pixel_add_to_cart');
    remove_action('woocommerce_after_checkout_form', 'wc_facebook_pixel_initiate_checkout');
    remove_action('woocommerce_thankyou', 'wc_facebook_pixel_purchase');
    
    // Disable Facebook for WooCommerce completely on this template
    add_filter('wc_facebook_pixel_enabled', '__return_false');
    add_filter('woocommerce_facebook_pixel_enabled', '__return_false');
    add_filter('facebook_for_woocommerce_integration_pixel_enabled', '__return_false');
    add_filter('wc_facebook_pixel_events_enabled', '__return_false');
    
    // Remove Facebook for WooCommerce class methods
    if (class_exists('WC_Facebookcommerce_Pixel')) {
        remove_all_actions('wp_head');
        remove_all_actions('wp_footer');
        // Re-add only non-Facebook actions
        add_action('wp_head', 'wp_enqueue_scripts');
        add_action('wp_footer', 'wp_print_footer_scripts');
    }
    
    // Remove any GTM scripts
    remove_action('wp_head', 'gtm4wp_wp_header_top');
    remove_action('wp_footer', 'gtm4wp_wp_footer');
    
    // Block only Facebook Pixel script (not Gravity Forms)
    add_filter('script_loader_tag', function($tag, $handle, $src) {
        // Only block Facebook scripts, allow everything else including Gravity Forms
        if (strpos($src, 'connect.facebook.net') !== false || strpos($src, 'fbevents.js') !== false) {
            return '';
        }
        return $tag;
    }, 10, 3);
    
    // Clean approach - no output buffering needed since Facebook plugin is deactivated
    
    // Final Gravity Forms fix - runs after ALL scripts load
    add_action('wp_footer', function() {
        ?>
        <script>
        // Wait for ALL scripts to load, then fix gform
        window.addEventListener('load', function() {
            setTimeout(function() {
                console.log("=== Template Gravity Forms Fix ===");
                console.log("gform object:", typeof window.gform);
                console.log("gform.addAction:", typeof window.gform?.addAction);
                
                // Fix gform.addAction if missing
                if (typeof window.gform !== "undefined" && typeof window.gform.addAction === "undefined") {
                    console.log("Template: Fixing gform.addAction after all scripts loaded...");
                    window.gform.addAction = function(hook, callback, priority) {
                        if (typeof window.jQuery !== "undefined") {
                            window.jQuery(document).on("gform_post_render", callback);
                        }
                    };
                    console.log("✓ Template: gform.addAction fixed after all scripts");
                }
                
                // Re-initialize form
                var form = document.querySelector("form[id*=\"gform_\"]");
                if (form) {
                    if (typeof window.jQuery !== "undefined") {
                        window.jQuery(form).trigger("gform_post_render");
                    }
                    console.log("✓ Template: Form re-initialized after all scripts:", form.id);
                }
                
                console.log("=== End Template Fix ===");
            }, 1000); // Wait 1 second after page load
        });
        </script>
        <?php
    }, 999);
}, 1);

// Prevent marketing scripts from loading on this template
add_action('wp_enqueue_scripts', function() {
    // Remove Klaviyo scripts
    wp_dequeue_script('twintack-klaviyo-newsletter');
    wp_deregister_script('twintack-klaviyo-newsletter');
    wp_dequeue_style('twintack-klaviyo-styles');
    wp_deregister_style('twintack-klaviyo-styles');
    
    // Remove WP Analytify GTM scripts
    wp_dequeue_script('analytify-google-analytics');
    wp_deregister_script('analytify-google-analytics');
    wp_dequeue_script('analytify-google-analytics-gtag');
    wp_deregister_script('analytify-google-analytics-gtag');
    
    // Remove Facebook scripts
    wp_dequeue_script('wc-facebook-pixel');
    wp_deregister_script('wc-facebook-pixel');
    
    // Remove any other marketing scripts that might interfere
    wp_dequeue_script('klaviyo-newsletter');
    wp_dequeue_script('facebook-pixel');
    wp_dequeue_script('google-tag-manager');
    wp_dequeue_script('gtm4wp');
    wp_deregister_script('gtm4wp');
    
    // Ensure Gravity Forms scripts are loaded for form ID 9
    if (class_exists('GFCommon')) {
        // Also manually enqueue the core scripts
        wp_enqueue_script('gform_gravityforms');
        wp_enqueue_script('gform_conditional_logic');
        wp_enqueue_script('gform_placeholder');
        wp_enqueue_script('gform_json');
        wp_enqueue_script('gform_utils');
        
        // Enqueue Gravity Forms styles
        wp_enqueue_style('gform_basic');
        wp_enqueue_style('gform_theme_reset');
        wp_enqueue_style('gform_theme_foundation');
        wp_enqueue_style('gform_theme_framework');
    }
}, 999);

// Prevent marketing scripts from being output in footer
add_action('wp_footer', function() {
    // Remove any inline marketing scripts
    remove_action('wp_footer', 'klaviyo_newsletter_script');
    remove_action('wp_footer', 'analytify_gtag_script');
    remove_action('wp_footer', 'wc_facebook_pixel_init');
    remove_action('wp_footer', 'gtm4wp_wp_footer');
    
    // Override any remaining script outputs and add debug info
    echo '<script>
    console.log("Clean template: Marketing scripts blocked");
    console.log("Scripts loaded on this page:");
    var scripts = document.getElementsByTagName("script");
    for (var i = 0; i < scripts.length; i++) {
        if (scripts[i].src) {
            var src = scripts[i].src;
            if (src.indexOf("fbevents") !== -1 || src.indexOf("googletagmanager") !== -1 || src.indexOf("gtag") !== -1) {
                console.log("⚠ Marketing script still loaded:", src);
            }
        }
    }
    </script>';
}, 1);

// Add output buffering to catch and remove any remaining marketing scripts
ob_start(function($content) {
    // Remove Facebook Pixel scripts
    $content = preg_replace('/<script[^>]*fbevents\.js[^>]*><\/script>/i', '', $content);
    $content = preg_replace('/<script[^>]*facebook\.com\/tr[^>]*><\/script>/i', '', $content);
    
    // Remove GTM scripts
    $content = preg_replace('/<script[^>]*googletagmanager\.com[^>]*><\/script>/i', '', $content);
    $content = preg_replace('/<script[^>]*gtag\/js[^>]*><\/script>/i', '', $content);
    
    // Remove any noscript GTM
    $content = preg_replace('/<noscript[^>]*googletagmanager\.com[^>]*>.*?<\/noscript>/is', '', $content);
    
    return $content;
});

get_header();
?>

<style>
/* Hide header and footer for clean form experience */
header#masthead {
    display: none;
}

footer#colophon {
    display: none;
}

/* Clean form container */
.clean-grip-form-content {
    padding: 25px 0 0;
    background: #fff;
    min-height: 100vh;
}

.container, .container-fluid, .container-lg, .container-md, .container-sm, .container-xl, .container-xxl {
    --bs-gutter-x: 0;
    --bs-gutter-y: 0;
    width: 100%;
    padding-right: calc(var(--bs-gutter-x)* .5);
    padding-left: calc(var(--bs-gutter-x)* .5);
    margin-right: auto;
    margin-left: auto;
}

/* Gravity Forms styling */
.gform-theme--api, .gform-theme--foundation {
    --gf-form-gap-y: 20px;
    --gf-field-gap-y: 12px;
}

.gform-theme--framework .gfield--type-image_choice .gfield_checkbox, 
.gform-theme--framework .gfield--type-image_choice .gfield_radio {
    gap: 3px;
}

/* Ensure form elements stay within bounds */
.gform-theme--framework {
    overflow-x: hidden;
}

.gform-theme--api, .gform-theme--framework {
    --gf-field-img-choice-size-md: 49%;
}

.gform-theme--framework .gfield--type-image_choice .gfield-choice-image {
    inline-size: 170px;
    max-block-size: 170px;
    max-inline-size: 170px;
    rotate: -90deg;
}

.gform-theme--framework .gfield--type-image_choice .gfield-image-choice-wrapper-outer {
    display: block;
    min-block-size: 100%;
    padding-top: 0 !important;
}

.gfield-choice-image-wrapper {
    width: 100%;
    height: 130px;
}

/* Basic navigation for clean template */
.clean-nav {
    background: #1a1a1a;
    padding: 15px 0;
    border-bottom: 3px solid #ff4800;
}

.clean-nav .container {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.clean-nav .logo {
    color: #ff4800;
    font-size: 24px;
    font-weight: bold;
    text-decoration: none;
}

.clean-nav .nav-links {
    display: flex;
    gap: 20px;
}

.clean-nav .nav-links a {
    color: #fff;
    text-decoration: none;
    font-weight: 500;
}

.clean-nav .nav-links a:hover {
    color: #ff4800;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .clean-grip-form-content {
        padding: 15px 0 0;
    }
    
    .gform-theme--api, .gform-theme--foundation {
        --gf-form-gap-y: 15px;
        --gf-field-gap-y: 10px;
    }
    
    .clean-nav .container {
        flex-direction: column;
        gap: 10px;
    }
    
    .clean-nav .nav-links {
        gap: 15px;
    }
}

/* Ensure images are responsive */
img {
    max-width: 100%;
    height: auto;
}
</style>

<!-- Clean Navigation -->
<nav class="clean-nav">
    <div class="container">
        <a href="<?php echo home_url(); ?>" class="logo">TwinTack</a>
        <div class="nav-links">
            <a href="<?php echo home_url('/shop/'); ?>">Shop</a>
            <a href="<?php echo home_url('/twintack/'); ?>">About</a>
            <a href="<?php echo home_url('/contact/'); ?>">Contact</a>
            <?php if (is_user_logged_in()) : ?>
                <a href="<?php echo home_url('/my-account/'); ?>">My Account</a>
            <?php else : ?>
                <a href="<?php echo home_url('/login/'); ?>">Login</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Clean Form Content -->
<div class="clean-grip-form-content">
    <?php while (have_posts()) : the_post(); ?>
        <div class="container">
            <h1 style="text-align: center; margin-bottom: 30px;">Custom Grip Configuration</h1>
            <?php the_content(); ?>
        </div>
        <?php get_template_part('template-parts/content', 'flexible'); ?>
    <?php endwhile; ?>
</div>

<!-- Clean Footer (minimal) -->
<footer style="background: #f5f5f5; padding: 20px 0; text-align: center; border-top: 1px solid #ddd;">
    <div class="container">
        <p style="margin: 0; color: #666; font-size: 14px;">
            &copy; <?php echo date('Y'); ?> TwinTack. All rights reserved.
        </p>
    </div>
</footer>

<?php
// Clean footer without marketing scripts
wp_footer();
?>
</body>
</html>
