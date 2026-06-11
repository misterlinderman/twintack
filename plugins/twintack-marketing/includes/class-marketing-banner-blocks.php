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
        
        $use_overlay = ('homepage-v2' === $atts['context']);

        foreach ($blocks as $block) {
            // If ID is specified, only show that block
            if (!empty($atts['id']) && $block['id'] !== $atts['id']) {
                continue;
            }
            
            $this->render_single_banner($block, $use_overlay);
        }
        
        return ob_get_clean();
    }
    
    /**
     * Render a single banner block
     *
     * @param array $block        Banner data.
     * @param bool  $force_overlay Use image-background overlay layout (Homepage V2).
     */
    private function render_single_banner($block, $force_overlay = false) {
        if ($force_overlay) {
            $this->render_overlay_banner($block);
            return;
        }

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
     * Normalize tag vs hover body for overlay cards.
     *
     * @param string $text        Tag line field.
     * @param string $description Hover body field.
     * @return array{tag:string,body:string}
     */
    private function normalize_overlay_copy($text, $description) {
        $text        = trim(wp_strip_all_tags($text));
        $description = trim(wp_strip_all_tags($description));

        if ('' !== $description) {
            return array(
                'tag'  => $text,
                'body' => $description,
            );
        }

        if (strlen($text) > 48) {
            return array(
                'tag'  => '',
                'body' => $text,
            );
        }

        return array(
            'tag'  => $text,
            'body' => '',
        );
    }

    /**
     * Render Homepage V2 overlay card (background image + centered content).
     *
     * @param array $block Banner data.
     */
    private function render_overlay_banner($block) {
        $image_desktop = isset($block['image_desktop']) ? $block['image_desktop'] : '';
        $image_mobile  = isset($block['image_mobile']) ? $block['image_mobile'] : '';
        $title         = isset($block['title']) ? $block['title'] : '';
        $text          = isset($block['text']) ? $block['text'] : '';
        $description   = isset($block['description']) ? $block['description'] : '';
        $cta_text      = isset($block['cta_text']) ? $block['cta_text'] : '';
        $cta_link      = isset($block['cta_link']) ? $block['cta_link'] : '';

        $copy = $this->normalize_overlay_copy($text, $description);

        $bg_image = $image_desktop;
        if (empty($bg_image) && !empty($image_mobile)) {
            $bg_image = $image_mobile;
        }

        $bg_style = '';
        if ($bg_image) {
            $bg_style = sprintf(' style="background-image: url(%s);"', esc_url($bg_image));
        }
        ?>
        <article
            class="twintack-banner-block twintack-banner-overlay"
            tabindex="0"
            <?php if (!empty($block['id'])) : ?>
                data-banner-id="<?php echo esc_attr($block['id']); ?>"
            <?php endif; ?>
        >
            <div class="twintack-banner-overlay__bg"<?php echo $bg_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-hidden="true"></div>
            <div class="twintack-banner-overlay__scrim" aria-hidden="true"></div>
            <div class="twintack-banner-overlay__content">
                <?php if ($copy['tag']) : ?>
                    <p class="twintack-banner-tag"><?php echo esc_html($copy['tag']); ?></p>
                <?php endif; ?>
                <?php if ($title) : ?>
                    <h3 class="twintack-banner-title"><?php echo esc_html($title); ?></h3>
                <?php endif; ?>
                <?php if ($copy['body']) : ?>
                    <p class="twintack-banner-body"><?php echo esc_html($copy['body']); ?></p>
                <?php endif; ?>
                <?php if ($cta_text && $cta_link) : ?>
                    <a href="<?php echo esc_url($cta_link); ?>" class="twintack-banner-cta button">
                        <?php echo esc_html($cta_text); ?>
                    </a>
                <?php endif; ?>
            </div>
        </article>
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
            'description' => isset($block['description']) ? sanitize_textarea_field($block['description']) : '',
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

