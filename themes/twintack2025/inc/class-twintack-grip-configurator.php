<?php
/**
 * TwinTack Grip Configurator Enhancements
 *
 * Adds registration options and improved messaging for the grip configurator page
 *
 * @package twintack2025
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class TwinTack_Grip_Configurator {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_footer', array($this, 'enhance_grip_configurator_page'));
        add_shortcode('twintack_grip_login_prompt', array($this, 'render_grip_login_prompt'));
        add_shortcode('twintack_account_required', array($this, 'render_account_required_message'));
        
        // Add admin notice for shortcode usage
        add_action('admin_notices', array($this, 'show_shortcode_usage_notice'));
    }
    
    /**
     * Enhance the grip configurator page with registration options
     * Only runs for non-logged-in users
     */
    public function enhance_grip_configurator_page() {
        // Only enhance for non-logged-in users
        if (is_user_logged_in()) {
            return;
        }
        
        // Only run on pages that might be the grip configurator
        if (!$this->is_grip_configurator_page()) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            // Comprehensive login status check via JavaScript
            var isLoggedIn = 
                // WordPress standard body class
                document.body.classList.contains('logged-in') || 
                // Admin bar indicators
                document.querySelector('.admin-bar') !== null ||
                document.querySelector('#wpadminbar') !== null ||
                // Account page indicators
                document.querySelector('.my-account') !== null ||
                document.querySelector('[href*="/my-account"]') !== null ||
                document.querySelector('[href*="wp-admin"]') !== null ||
                // Logout link presence (only appears for logged-in users)
                document.querySelector('[href*="wp-login.php?action=logout"]') !== null ||
                document.querySelector('[href*="logout"]') !== null ||
                // WooCommerce specific indicators
                document.querySelector('.woocommerce-account') !== null ||
                document.querySelector('.woocommerce-MyAccount') !== null ||
                // Check for user account menu items
                document.querySelector('[class*="account-menu"]') !== null ||
                document.querySelector('[class*="user-menu"]') !== null;
            
            // If user appears to be logged in, don't show the enhancement
            if (isLoggedIn) {
                return;
            }
            
            // Find elements that suggest this is a grip configurator page
            var targetElements = [
                document.querySelector('[class*="grip-configurator"], [id*="grip-configurator"]'),
                document.querySelector('h1, h2, h3')
            ].filter(Boolean);
            
            // Check if any element contains grip configurator text
            var hasGripContent = targetElements.some(function(el) {
                return el && el.textContent.toLowerCase().includes('grip configurator');
            });
            
            // Also check for "Account required" text
            var accountRequiredElements = Array.from(document.querySelectorAll('*')).filter(function(el) {
                return el.textContent && el.textContent.includes('Account required');
            });
            
            // Check for any form-related content that might indicate this is the grip configurator
            var formElements = document.querySelectorAll('form, .gform_wrapper, .gform_body');
            
            // Check for any element containing "custom grip" text
            var customGripElements = Array.from(document.querySelectorAll('*')).filter(function(el) {
                return el.textContent && el.textContent.toLowerCase().includes('custom grip');
            });
            
            if (hasGripContent || accountRequiredElements.length > 0 || (formElements.length > 0 && customGripElements.length > 0)) {
                // Find the best place to insert the registration prompt
                var insertTarget = accountRequiredElements[0] || 
                                 document.querySelector('.entry-content') || 
                                 document.querySelector('.content') ||
                                 document.querySelector('main') ||
                                 document.querySelector('.gform_wrapper') ||
                                 document.querySelector('form');
                                 
                if (insertTarget && !document.querySelector('.twintack-grip-registration-prompt') && 
                   !document.querySelector('.twintack-grip-login-prompt-wrapper') && 
                   !document.querySelector('.twintack-account-required-wrapper')) {
                    // Create the registration prompt
                    var registrationHtml = <?php echo wp_json_encode($this->get_registration_html(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
                    
                    // Insert after the target element
                    if (accountRequiredElements[0]) {
                        accountRequiredElements[0].insertAdjacentHTML('afterend', registrationHtml);
                    } else if (insertTarget.tagName === 'FORM' || insertTarget.className.includes('gform')) {
                        insertTarget.insertAdjacentHTML('beforebegin', registrationHtml);
                    } else {
                        insertTarget.insertAdjacentHTML('beforeend', registrationHtml);
                    }
                }
            }
        });
        </script>
        <?php
    }
    
    /**
     * Check if we're on the grip configurator page
     */
    private function is_grip_configurator_page() {
        global $post;
        
        // Check if we're on a page with grip configurator in the URL
        if (strpos($_SERVER['REQUEST_URI'], '/twintack-custom-grips') !== false || 
            strpos($_SERVER['REQUEST_URI'], '/grip-configurator') !== false) {
            return true;
        }
        
        // Check if the current page contains grip configurator content
        if ($post && (strpos($post->post_content, 'GRIP CONFIGURATOR') !== false ||
            strpos($post->post_content, 'Account required for custom grip orders') !== false)) {
            return true;
        }
        
        // Additional checks for common form page patterns
        if ($post && (
            strpos($post->post_content, '[gravityform') !== false ||
            strpos($post->post_content, 'custom grip') !== false ||
            strpos(strtolower($post->post_title), 'grip') !== false
        )) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get the HTML for the registration prompt
     */
    private function get_registration_html() {
        ob_start();
        ?>
        <div class="twintack-grip-registration-prompt" style="
            background: rgba(26, 26, 26, 0.95);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 30px;
            margin: 25px 0;
            text-align: center;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            font-family: 'Saira Condensed', 'Archivo', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #ffffff;
        ">
            <div style="margin-bottom: 20px;">
                <h3 style="
                    color: #ffffff;
                    margin: 0 0 15px 0;
                    font-size: 1.8rem;
                    font-weight: 700;
                    font-family: 'Saira Condensed', 'Archivo', sans-serif;
                ">Ready to Design Your Custom Grips?</h3>
                <p style="
                    color: rgba(255, 255, 255, 0.8);
                    margin: 0 0 25px 0;
                    font-size: 1.1rem;
                    line-height: 1.5;
                ">Create your free account to start designing custom grips for your team or personal use.</p>
            </div>
            
            <div class="registration-options" style="
                display: flex;
                gap: 15px;
                justify-content: center;
                flex-wrap: wrap;
                margin-bottom: 20px;
            ">
                <?php 
                // Get current page URL for redirect after login
                $current_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                $register_url = add_query_arg(array(
                    'action' => 'register',
                    'redirect_to' => urlencode($current_url)
                ), site_url('/login/'));
                $login_url = add_query_arg(array(
                    'redirect_to' => urlencode($current_url)
                ), site_url('/login/'));
                ?>
                <a href="<?php echo esc_url($register_url); ?>" 
                   class="btn btn-primary" 
                   style="
                       background: var(--color-highlight);
                       color: #000000;
                       padding: 12px 25px;
                       text-decoration: none;
                       border-radius: 4px;
                       font-weight: 600;
                       display: inline-block;
                       transition: all 0.3s ease;
                       border: none;
                       font-size: 1rem;
                       font-family: 'Saira Condensed', 'Archivo', sans-serif;
                       text-transform: uppercase;
                       letter-spacing: 0.5px;
                   "
                   onmouseover="this.style.background='var(--color-text)'; this.style.color='var(--color-primary)'"
                   onmouseout="this.style.background='var(--color-highlight)'; this.style.color='var(--color-primary)'">
                    Create Account
                </a>
                
                <a href="<?php echo esc_url($login_url); ?>" 
                   class="btn btn-outline" 
                   style="
                       background: transparent;
                       color: #ffffff;
                       padding: 12px 25px;
                       text-decoration: none;
                       border-radius: 4px;
                       font-weight: 600;
                       display: inline-block;
                       transition: all 0.3s ease;
                       border: 2px solid rgba(255, 255, 255, 0.3);
                       font-size: 1rem;
                       font-family: 'Saira Condensed', 'Archivo', sans-serif;
                       text-transform: uppercase;
                       letter-spacing: 0.5px;
                   "
                   onmouseover="this.style.background='rgba(255, 255, 255, 0.1)'; this.style.borderColor='#ff0b00'; this.style.color='#ff0b00'"
                   onmouseout="this.style.background='transparent'; this.style.borderColor='rgba(255, 255, 255, 0.3)'; this.style.color='#ffffff'">
                    Sign In
                </a>
            </div>
            
            <div class="account-benefits" style="
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 8px;
                padding: 20px;
                margin-top: 20px;
                text-align: left;
            ">
                <h4 style="
                    color: #ffffff;
                    margin: 0 0 15px 0;
                    font-size: 1.3rem;
                    font-weight: 700;
                    text-align: center;
                    font-family: 'Saira Condensed', 'Archivo', sans-serif;
                ">Why Create an Account?</h4>
                
                <div style="
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                    gap: 15px;
                    margin-top: 15px;
                ">
                    <div style="display: flex; align-items: start; gap: 10px;">
                        <span style="color: #ff0b00; font-size: 1.2rem; font-weight: bold;">✓</span>
                        <span style="color: rgba(255, 255, 255, 0.9); font-size: 0.95rem;">
                            <strong style="color: #ffffff;">Track Your Designs:</strong> View status updates and artwork progress
                        </span>
                    </div>
                    
                    <div style="display: flex; align-items: start; gap: 10px;">
                        <span style="color: #ff0b00; font-size: 1.2rem; font-weight: bold;">✓</span>
                        <span style="color: rgba(255, 255, 255, 0.9); font-size: 0.95rem;">
                            <strong style="color: #ffffff;">Order History:</strong> Access all your past custom grip orders
                        </span>
                    </div>
                    
                    <div style="display: flex; align-items: start; gap: 10px;">
                        <span style="color: #ff0b00; font-size: 1.2rem; font-weight: bold;">✓</span>
                        <span style="color: rgba(255, 255, 255, 0.9); font-size: 0.95rem;">
                            <strong style="color: #ffffff;">Secure Checkout:</strong> Save payment and shipping information
                        </span>
                    </div>
                    
                </div>
            </div>
            
            <div style="
                margin-top: 20px;
                padding-top: 15px;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
                color: rgba(255, 255, 255, 0.7);
                font-size: 0.9rem;
            ">
                <p style="margin: 0;">
                    Questions about custom grips? 
                    <a href="/contact/" style="color: #ff0b00; text-decoration: none; font-weight: 600;">Contact our team</a> 
                    for assistance.
                </p>
            </div>
        </div>
        
        <style>
        .twintack-grip-registration-prompt .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }
        
        @media (max-width: 768px) {
            .twintack-grip-registration-prompt {
                padding: 20px !important;
                margin: 15px 0 !important;
            }
            
            .registration-options {
                flex-direction: column !important;
                align-items: center !important;
            }
            
            .registration-options .btn {
                width: 100% !important;
                max-width: 280px !important;
            }
            
            .account-benefits > div {
                grid-template-columns: 1fr !important;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode to render the grip login prompt
     * Usage: [twintack_grip_login_prompt]
     */
    public function render_grip_login_prompt($atts = array()) {
        $atts = shortcode_atts(array(
            'title' => 'Ready to Design Your Custom Grips?',
            'subtitle' => 'Create your free account to start designing custom grips for your team or personal use.',
            'show_benefits' => 'true'
        ), $atts);
        
        // Always render the content but add client-side login detection
        // This prevents caching issues where server-side login check is cached
        ob_start();
        ?>
        <div class="twintack-grip-login-prompt-wrapper" style="<?php echo is_user_logged_in() ? 'display: none;' : 'display: block;'; ?>">
            <?php echo $this->get_registration_html(); ?>
        </div>
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            var promptWrapper = document.querySelector('.twintack-grip-login-prompt-wrapper');
            if (!promptWrapper) {
                console.log('TwinTack: Login prompt wrapper not found');
                return;
            }
            
            // Server-side check (most reliable when not cached)
            var serverSideLoggedIn = <?php echo is_user_logged_in() ? 'true' : 'false'; ?>;
            
            // Client-side login indicators (for cached pages)
            var hasLoggedInBodyClass = document.body.classList.contains('logged-in');
            var hasAdminBar = document.querySelector('#wpadminbar') !== null;
            var hasMyAccountLinks = document.querySelector('a[href*="/my-account"]') !== null;
            var hasLogoutLinks = document.querySelector('a[href*="logout"]') !== null;
            
            // Determine if user is logged in
            var isLoggedIn = serverSideLoggedIn || hasLoggedInBodyClass || hasAdminBar || (hasMyAccountLinks && hasLogoutLinks);
            
            console.log('TwinTack Login Detection:', {
                serverSide: serverSideLoggedIn,
                bodyClass: hasLoggedInBodyClass,
                adminBar: hasAdminBar,
                myAccountLinks: hasMyAccountLinks,
                logoutLinks: hasLogoutLinks,
                finalDecision: isLoggedIn
            });
            
            // Check for significant cache mismatch (logged in indicators present but server says logged out)
            var cacheConflict = !serverSideLoggedIn && (hasLoggedInBodyClass || hasAdminBar);
            
            // Hide the prompt if user is confirmed logged in (for cached pages)
            if (isLoggedIn && !serverSideLoggedIn) {
                promptWrapper.style.display = 'none';
                console.log('TwinTack: User detected as logged in via client-side, hiding login prompt');
                
                // If there's a significant cache conflict, refresh the page to get fresh content
                if (cacheConflict) {
                    console.log('TwinTack: Cache conflict detected, refreshing page for fresh content');
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                }
            } else if (!isLoggedIn) {
                promptWrapper.style.display = 'block';
                console.log('TwinTack: User not logged in, ensuring login prompt is visible');
            } else {
                console.log('TwinTack: Server-side detection handled display');
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode to render a simplified account required message with registration links
     * Usage: [twintack_account_required]
     */
    public function render_account_required_message($atts = array()) {
        $atts = shortcode_atts(array(
            'message' => 'Account required for custom grip orders.',
            'style' => 'compact' // 'compact' or 'full'
        ), $atts);
        
        // Always render the content but add client-side login detection
        // This prevents caching issues where server-side login check is cached
        ob_start();
        ?>
        <div class="twintack-account-required-wrapper" style="<?php echo is_user_logged_in() ? 'display: none;' : 'display: block;'; ?>">
            <div class="twintack-account-required" style="
                background: rgba(26, 26, 26, 0.9);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
                text-align: center;
                font-family: 'Saira Condensed', 'Archivo', -apple-system, BlinkMacSystemFont, sans-serif;
                color: #ffffff;
            ">
                <p style="margin: 0 0 15px 0; color: rgba(255, 255, 255, 0.9); font-size: 1.1rem; font-weight: 500;">
                    <?php echo esc_html($atts['message']); ?>
                </p>
                
                <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                    <?php 
                    // Get current page URL for redirect after login
                    $current_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                    $register_url = add_query_arg(array(
                        'action' => 'register',
                        'redirect_to' => urlencode($current_url)
                    ), site_url('/login/'));
                    $login_url = add_query_arg(array(
                        'redirect_to' => urlencode($current_url)
                    ), site_url('/login/'));
                    ?>
                    <a href="<?php echo esc_url($register_url); ?>" 
                       style="
                           background: #ff0b00;
                           color: #000000;
                           padding: 10px 20px;
                           text-decoration: none;
                           border-radius: 4px;
                           font-weight: 600;
                           display: inline-block;
                           transition: all 0.3s ease;
                           font-family: 'Saira Condensed', 'Archivo', sans-serif;
                           text-transform: uppercase;
                           letter-spacing: 0.5px;
                       ">Create Account</a>
                    
                    <a href="<?php echo esc_url($login_url); ?>" 
                       style="
                           background: transparent;
                           color: #ffffff;
                           padding: 10px 20px;
                           text-decoration: none;
                           border-radius: 4px;
                           font-weight: 600;
                           display: inline-block;
                           border: 1px solid rgba(255, 255, 255, 0.3);
                           transition: all 0.3s ease;
                           font-family: 'Saira Condensed', 'Archivo', sans-serif;
                           text-transform: uppercase;
                           letter-spacing: 0.5px;
                       ">Sign In</a>
                </div>
            </div>
        </div>
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            var accountWrapper = document.querySelector('.twintack-account-required-wrapper');
            if (!accountWrapper) {
                console.log('TwinTack: Account required wrapper not found');
                return;
            }
            
            // Server-side check (most reliable when not cached)
            var serverSideLoggedIn = <?php echo is_user_logged_in() ? 'true' : 'false'; ?>;
            
            // Client-side login indicators (for cached pages)
            var hasLoggedInBodyClass = document.body.classList.contains('logged-in');
            var hasAdminBar = document.querySelector('#wpadminbar') !== null;
            var hasMyAccountLinks = document.querySelector('a[href*="/my-account"]') !== null;
            var hasLogoutLinks = document.querySelector('a[href*="logout"]') !== null;
            
            // Determine if user is logged in
            var isLoggedIn = serverSideLoggedIn || hasLoggedInBodyClass || hasAdminBar || (hasMyAccountLinks && hasLogoutLinks);
            
            console.log('TwinTack Account Required Detection:', {
                serverSide: serverSideLoggedIn,
                bodyClass: hasLoggedInBodyClass,
                adminBar: hasAdminBar,
                myAccountLinks: hasMyAccountLinks,
                logoutLinks: hasLogoutLinks,
                finalDecision: isLoggedIn
            });
            
            // Check for significant cache mismatch (logged in indicators present but server says logged out)
            var cacheConflict = !serverSideLoggedIn && (hasLoggedInBodyClass || hasAdminBar);
            
            // Hide the account required message if user is confirmed logged in (for cached pages)
            if (isLoggedIn && !serverSideLoggedIn) {
                accountWrapper.style.display = 'none';
                console.log('TwinTack: User detected as logged in via client-side, hiding account required message');
                
                // If there's a significant cache conflict, refresh the page to get fresh content
                if (cacheConflict) {
                    console.log('TwinTack: Cache conflict detected, refreshing page for fresh content');
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                }
            } else if (!isLoggedIn) {
                accountWrapper.style.display = 'block';
                console.log('TwinTack: User not logged in, ensuring account required message is visible');
            } else {
                console.log('TwinTack: Server-side detection handled display for account required');
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Show admin notice with shortcode usage instructions
     */
    public function show_shortcode_usage_notice() {
        $screen = get_current_screen();
        
        // Only show on pages/posts edit screen
        if (!$screen || !in_array($screen->base, array('post', 'page'))) {
            return;
        }
        
        // Only show if editing the custom grips related page
        global $post;
        if (!$post || strpos($post->post_content, 'GRIP CONFIGURATOR') === false) {
            return;
        }
        
        ?>
        <div class="notice notice-info">
            <p><strong>TwinTack Grip Configurator Enhancement:</strong> You can add registration prompts for non-logged-in users using these shortcodes:</p>
            <ul>
                <li><code>[twintack_grip_login_prompt]</code> - Full registration prompt with benefits</li>
                <li><code>[twintack_account_required]</code> - Simple account required message with login links</li>
            </ul>
        </div>
        <?php
    }
}

// Initialize the class
TwinTack_Grip_Configurator::get_instance(); 