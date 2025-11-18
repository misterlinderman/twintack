<?php
/**
 * Wholesale Plugin Patch Class
 * 
 * This class handles patching the wholesale plugin's
 * deprecated jQuery methods and conflicts.
 */

class TwinTack_Wholesale_Plugin_Patch {

    /**
     * Initialize the patch
     */
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_patch_script'));
        add_action('admin_footer', array($this, 'add_inline_patch'));
    }

    /**
     * Enqueue the wholesale plugin patch script
     */
    public function enqueue_patch_script($hook) {
        // Only load on product edit pages
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }

        global $post;
        if (!$post || $post->post_type !== 'product') {
            return;
        }

        wp_enqueue_script(
            'twintack-wholesale-plugin-patch',
            TWINTACK_CONSOLE_FIXES_PLUGIN_URL . 'assets/js/wholesale-plugin-patch.js',
            array('jquery'),
            TWINTACK_CONSOLE_FIXES_VERSION,
            true
        );
    }

    /**
     * Add inline patch to admin footer
     */
    public function add_inline_patch() {
        // Only on product edit pages
        if (!is_admin() || get_current_screen()->id !== 'product') {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Fix wholesale plugin jQuery delegate usage
            if (typeof $.fn.delegate !== 'undefined') {
                var originalDelegate = $.fn.delegate;
                $.fn.delegate = function(selector, eventType, handler) {
                    if (typeof selector === 'string' && typeof eventType === 'string') {
                        // Convert to modern on() method
                        return this.on(eventType, selector, handler);
                    }
                    return originalDelegate.apply(this, arguments);
                };
            }

            // Ensure product type is properly set after wholesale plugin loads
            setTimeout(function() {
                if (window.twintack_product_fix_params) {
                    var productId = window.twintack_product_fix_params.product_id;
                    if (productId) {
                        // Trigger product type consistency check
                        $(document).trigger('twintack-check-product-type', [productId]);
                    }
                }
            }, 2000);
        });
        </script>
        <?php
    }
}
