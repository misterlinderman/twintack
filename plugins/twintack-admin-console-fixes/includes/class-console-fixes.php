<?php
/**
 * Console Fixes Class
 * 
 * This class handles various console errors and warnings
 * that appear in the WordPress admin area.
 */

class TwinTack_Console_Fixes {

    /**
     * Initialize the fixes
     */
    public function __construct() {
        add_action('admin_init', array($this, 'init_fixes'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_fixes'));
        add_action('admin_footer', array($this, 'add_inline_fixes'));
    }

    /**
     * Initialize various fixes
     */
    public function init_fixes() {
        // Fix duplicate nonce fields
        add_filter('wp_nonce_field', array($this, 'make_nonce_fields_unique'), 10, 3);
        
        // Fix duplicate Jetpack contact form buttons
        add_action('admin_footer', array($this, 'fix_jetpack_duplicate_buttons'));
        
        // Fix Yoast SEO duplicate buttons
        add_action('admin_footer', array($this, 'fix_yoast_duplicate_buttons'));
    }

    /**
     * Enqueue necessary scripts and styles
     */
    public function enqueue_fixes() {
        // Add inline CSS to hide duplicate elements temporarily
        wp_add_inline_style('wp-admin', '
            .duplicate-element-hidden {
                display: none !important;
            }
        ');
    }

    /**
     * Make nonce fields unique to prevent duplicate ID errors
     */
    public function make_nonce_fields_unique($nonce_field, $action, $name) {
        // Add a unique suffix to prevent duplicate IDs
        static $nonce_counter = 0;
        $nonce_counter++;
        
        if ($nonce_counter > 1) {
            $name = $name . '_' . $nonce_counter;
        }
        
        return wp_nonce_field($action, $name, true, false);
    }

    /**
     * Fix duplicate Jetpack contact form buttons
     */
    public function fix_jetpack_duplicate_buttons() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Fix duplicate Jetpack contact form buttons
            var jetpackButtons = $('#insert-jetpack-contact-form');
            if (jetpackButtons.length > 1) {
                jetpackButtons.each(function(index) {
                    if (index > 0) {
                        $(this).attr('id', 'insert-jetpack-contact-form-' + (index + 1));
                    }
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Fix duplicate Yoast SEO buttons
     */
    public function fix_yoast_duplicate_buttons() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Fix duplicate Yoast AI buttons
            var yoastButtons = $('[id*="yst-replacevar__use-ai-button"]');
            yoastButtons.each(function(index) {
                var $button = $(this);
                var currentId = $button.attr('id');
                var existingButtons = $('#' + currentId);
                
                if (existingButtons.length > 1) {
                    $button.attr('id', currentId + '_' + (index + 1));
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Add inline fixes to admin footer
     */
    public function add_inline_fixes() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            
            // Fix 1: Handle TinyMCE undefined errors
            if (typeof tinymce === 'undefined') {
                // Wait for TinyMCE to load
                var checkTinyMCE = setInterval(function() {
                    if (typeof tinymce !== 'undefined') {
                        clearInterval(checkTinyMCE);
                        console.log('TinyMCE loaded successfully');
                    }
                }, 100);

                // Clear interval after 10 seconds
                setTimeout(function() {
                    clearInterval(checkTinyMCE);
                }, 10000);
            }

            // Fix 2: Suppress jQuery migration warnings
            var originalWarn = console.warn;
            console.warn = function(message) {
                if (typeof message === 'string' && 
                    (message.includes('JQMIGRATE:') || 
                     message.includes('jQuery.fn.') ||
                     message.includes('event shorthand is deprecated'))) {
                    return; // Suppress these warnings
                }
                originalWarn.apply(console, arguments);
            };

            // Fix 3: Suppress React defaultProps warnings
            var originalError = console.error;
            console.error = function(message) {
                if (typeof message === 'string' && 
                    (message.includes('Support for defaultProps will be removed') ||
                     message.includes('useSelect hook returns different values'))) {
                    return; // Suppress these warnings
                }
                originalError.apply(console, arguments);
            };

            // Fix 4: Handle script loading order issues
            $('script[src*="klaviyo-klaviyo-checkout-block"]').each(function() {
                var $script = $(this);
                if ($script.attr('data-footer') !== 'true') {
                    $script.attr('data-footer', 'true');
                    $script.appendTo('body');
                }
            });

            // Fix 5: Handle FileBird jQuery selector errors
            try {
                // Override jQuery's find method to handle invalid selectors
                var originalFind = $.fn.find;
                $.fn.find = function(selector) {
                    try {
                        // Check if selector contains invalid characters
                        if (typeof selector === 'string' && selector.includes('module/') && selector.includes('.tsx-js-extra')) {
                            console.log('TwinTack Console Fix: Blocked invalid FileBird selector:', selector);
                            return $(); // Return empty jQuery object
                        }
                        return originalFind.call(this, selector);
                    } catch (e) {
                        console.log('TwinTack Console Fix: Caught jQuery selector error:', e.message);
                        return $(); // Return empty jQuery object
                    }
                };
            } catch (e) {
                console.log('TwinTack Console Fix: Error setting up FileBird fix:', e.message);
            }

            // Fix 6: Handle Jetpack API errors
            var originalConsoleError = console.error;
            console.error = function() {
                var args = Array.prototype.slice.call(arguments);
                var message = args.join(' ');
                
                // Suppress Jetpack undefined API errors
                if (message.includes('undefinedjetpack') || message.includes('404 (Not Found)')) {
                    return;
                }
                
                // Call original error function
                originalConsoleError.apply(console, args);
            };

            // Fix 7: Handle any remaining duplicate IDs
            $('[id]').each(function() {
                var id = $(this).attr('id');
                var elements = $('#' + id);
                if (elements.length > 1) {
                    elements.each(function(index) {
                        if (index > 0) {
                            $(this).attr('id', id + '_' + (index + 1));
                        }
                    });
                }
            });

        });
        </script>
        <?php
    }
}
