<?php
/**
 * Banner Blocks Management
 * Handles banner blocks for homepage and landing pages
 */

if (!defined('ABSPATH')) exit;

class TwinTack_Marketing_Banner_Blocks {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Shortcode for displaying banner blocks
        add_shortcode('twintack_banner_block', array($this, 'render_banner_block'));
        
        // AJAX handlers for admin
        add_action('wp_ajax_twintack_get_banner_blocks', array($this, 'ajax_get_banner_blocks'));
        add_action('wp_ajax_twintack_save_banner_block', array($this, 'ajax_save_banner_block'));
        add_action('wp_ajax_twintack_delete_banner_block', array($this, 'ajax_delete_banner_block'));
    }
    
    /**
     * Get banner blocks for a specific context
     */
    public function get_banner_blocks($context = 'homepage') {
        $blocks = get_option('twintack_banner_blocks_' . $context, array());
        
        if (empty($blocks) || !is_array($blocks)) {
            return array();
        }
        
        // Sort by order
        usort($blocks, function($a, $b) {
            return ($a['order'] ?? 0) - ($b['order'] ?? 0);
        });
        
        return $blocks;
    }
    
    /**
     * Save banner blocks for a specific context
     */
    public function save_banner_blocks($context, $blocks) {
        update_option('twintack_banner_blocks_' . $context, $blocks);
        return true;
    }
    
    /**
     * Render banner block shortcode
     */
    public function render_banner_block($atts) {
        $atts = shortcode_atts(array(
            'context' => 'homepage',
            'id' => ''
        ), $atts, 'twintack_banner_block');
        
        $blocks = $this->get_banner_blocks($atts['context']);
        
        if (empty($blocks)) {
            return '';
        }
        
        ob_start();
        
        foreach ($blocks as $block) {
            // If ID is specified, only show that block
            if (!empty($atts['id']) && $block['id'] !== $atts['id']) {
                continue;
            }
            
            $this->render_single_banner($block);
        }
        
        return ob_get_clean();
    }
    
    /**
     * Render a single banner block
     */
    private function render_single_banner($block) {
        $layout = isset($block['layout']) ? $block['layout'] : 'fullwidth';
        $image_desktop = isset($block['image_desktop']) ? $block['image_desktop'] : '';
        $image_mobile = isset($block['image_mobile']) ? $block['image_mobile'] : '';
        $title = isset($block['title']) ? $block['title'] : '';
        $text = isset($block['text']) ? $block['text'] : '';
        $cta_text = isset($block['cta_text']) ? $block['cta_text'] : '';
        $cta_link = isset($block['cta_link']) ? $block['cta_link'] : '';
        $link = isset($block['link']) ? $block['link'] : '';
        
        $classes = array('twintack-banner-block', 'twintack-banner-' . $layout);
        
        ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
            <?php if ($layout === 'fullwidth') : ?>
                <?php if (!empty($link)) : ?>
                    <a href="<?php echo esc_url($link); ?>" class="twintack-banner-link">
                <?php endif; ?>
                
                <?php if (!empty($image_desktop)) : ?>
                    <picture>
                        <?php if (!empty($image_mobile)) : ?>
                            <source media="(max-width: 768px)" srcset="<?php echo esc_url($image_mobile); ?>">
                        <?php endif; ?>
                        <img src="<?php echo esc_url($image_desktop); ?>" 
                             alt="<?php echo esc_attr($title); ?>" 
                             class="twintack-banner-image" />
                    </picture>
                <?php endif; ?>
                
                <?php if (!empty($link)) : ?>
                    </a>
                <?php endif; ?>
                
            <?php else : // 50/50 layout ?>
                <div class="twintack-banner-content-wrapper">
                    <div class="twintack-banner-image-side">
                        <?php if (!empty($image_desktop)) : ?>
                            <picture>
                                <?php if (!empty($image_mobile)) : ?>
                                    <source media="(max-width: 768px)" srcset="<?php echo esc_url($image_mobile); ?>">
                                <?php endif; ?>
                                <img src="<?php echo esc_url($image_desktop); ?>" 
                                     alt="<?php echo esc_attr($title); ?>" 
                                     class="twintack-banner-image" />
                            </picture>
                        <?php endif; ?>
                    </div>
                    <div class="twintack-banner-text-side">
                        <?php if (!empty($title)) : ?>
                            <h2 class="twintack-banner-title"><?php echo esc_html($title); ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($text)) : ?>
                            <div class="twintack-banner-text"><?php echo wp_kses_post(wpautop($text)); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($cta_text) && !empty($cta_link)) : ?>
                            <a href="<?php echo esc_url($cta_link); ?>" class="twintack-banner-cta button">
                                <?php echo esc_html($cta_text); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * AJAX: Get banner blocks
     */
    public function ajax_get_banner_blocks() {
        check_ajax_referer('twintack_marketing_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'homepage';
        $blocks = $this->get_banner_blocks($context);
        
        wp_send_json_success(array('blocks' => $blocks));
    }
    
    /**
     * AJAX: Save banner block
     */
    public function ajax_save_banner_block() {
        check_ajax_referer('twintack_marketing_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'homepage';
        $block = isset($_POST['block']) ? $_POST['block'] : array();
        
        // Sanitize block data
        $sanitized_block = array(
            'id' => isset($block['id']) ? sanitize_text_field($block['id']) : uniqid('banner_'),
            'layout' => isset($block['layout']) ? sanitize_text_field($block['layout']) : 'fullwidth',
            'image_desktop' => isset($block['image_desktop']) ? esc_url_raw($block['image_desktop']) : '',
            'image_mobile' => isset($block['image_mobile']) ? esc_url_raw($block['image_mobile']) : '',
            'title' => isset($block['title']) ? sanitize_text_field($block['title']) : '',
            'text' => isset($block['text']) ? wp_kses_post($block['text']) : '',
            'cta_text' => isset($block['cta_text']) ? sanitize_text_field($block['cta_text']) : '',
            'cta_link' => isset($block['cta_link']) ? esc_url_raw($block['cta_link']) : '',
            'link' => isset($block['link']) ? esc_url_raw($block['link']) : '',
            'order' => isset($block['order']) ? intval($block['order']) : 0
        );
        
        $blocks = $this->get_banner_blocks($context);
        
        // Update or add block
        $found = false;
        foreach ($blocks as $key => $existing_block) {
            if ($existing_block['id'] === $sanitized_block['id']) {
                $blocks[$key] = $sanitized_block;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $blocks[] = $sanitized_block;
        }
        
        $this->save_banner_blocks($context, $blocks);
        
        wp_send_json_success(array('block' => $sanitized_block, 'message' => 'Banner block saved'));
    }
    
    /**
     * AJAX: Delete banner block
     */
    public function ajax_delete_banner_block() {
        check_ajax_referer('twintack_marketing_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'homepage';
        $block_id = isset($_POST['block_id']) ? sanitize_text_field($_POST['block_id']) : '';
        
        $blocks = $this->get_banner_blocks($context);
        $blocks = array_filter($blocks, function($block) use ($block_id) {
            return $block['id'] !== $block_id;
        });
        
        $this->save_banner_blocks($context, array_values($blocks));
        
        wp_send_json_success(array('message' => 'Banner block deleted'));
    }
}

