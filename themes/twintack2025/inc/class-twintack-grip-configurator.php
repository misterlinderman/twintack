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
            
            if (hasGripContent || accountRequiredElements.length > 0) {
                // Find the best place to insert the registration prompt
                var insertTarget = accountRequiredElements[0] || 
                                 document.querySelector('.entry-content') || 
                                 document.querySelector('.content') ||
                                 document.querySelector('main');
                                 
                if (insertTarget && !document.querySelector('.twintack-grip-registration-prompt')) {
                    // Create the registration prompt
                    var registrationHtml = <?php echo json_encode($this->get_registration_html()); ?>;
                    
                    // Insert after the target element
                    if (accountRequiredElements[0]) {
                        accountRequiredElements[0].insertAdjacentHTML('afterend', registrationHtml);
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
                <a href="<?php echo esc_url(site_url('/login/?action=register')); ?>" 
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
                
                <a href="<?php echo esc_url(site_url('/login/')); ?>" 
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
                   onmouseover="this.style.background='rgba(255, 255, 255, 0.1)'; this.style.borderColor='#a9ff00'; this.style.color='#a9ff00'"
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
                        <span style="color: #a9ff00; font-size: 1.2rem; font-weight: bold;">✓</span>
                        <span style="color: rgba(255, 255, 255, 0.9); font-size: 0.95rem;">
                            <strong style="color: #ffffff;">Track Your Designs:</strong> View status updates and artwork progress
                        </span>
                    </div>
                    
                    <div style="display: flex; align-items: start; gap: 10px;">
                        <span style="color: #a9ff00; font-size: 1.2rem; font-weight: bold;">✓</span>
                        <span style="color: rgba(255, 255, 255, 0.9); font-size: 0.95rem;">
                            <strong style="color: #ffffff;">Order History:</strong> Access all your past custom grip orders
                        </span>
                    </div>
                    
                    <div style="display: flex; align-items: start; gap: 10px;">
                        <span style="color: #a9ff00; font-size: 1.2rem; font-weight: bold;">✓</span>
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
                    <a href="/contact/" style="color: #a9ff00; text-decoration: none; font-weight: 600;">Contact our team</a> 
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
        // Don't show if user is logged in
        if (is_user_logged_in()) {
            return '';
        }
        
        $atts = shortcode_atts(array(
            'title' => 'Ready to Design Your Custom Grips?',
            'subtitle' => 'Create your free account to start designing custom grips for your team or personal use.',
            'show_benefits' => 'true'
        ), $atts);
        
        return $this->get_registration_html();
    }
    
    /**
     * Shortcode to render a simplified account required message with registration links
     * Usage: [twintack_account_required]
     */
    public function render_account_required_message($atts = array()) {
        // Don't show if user is logged in
        if (is_user_logged_in()) {
            return '';
        }
        
        $atts = shortcode_atts(array(
            'message' => 'Account required for custom grip orders.',
            'style' => 'compact' // 'compact' or 'full'
        ), $atts);
        
        ob_start();
        ?>
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
                <a href="<?php echo esc_url(site_url('/login/?action=register')); ?>" 
                   style="
                       background: #a9ff00;
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
                
                <a href="<?php echo esc_url(site_url('/login/')); ?>" 
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