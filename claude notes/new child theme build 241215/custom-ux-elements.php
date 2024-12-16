<?php
// Add this to your child theme's functions.php or a separate file like inc/ux-builder-elements.php

add_action('ux_builder_setup', 'register_custom_ux_elements');

function register_custom_ux_elements() {
    // Basic Content Element
    add_ux_builder_shortcode('custom_content_block', array(
        'name' => 'Custom Content Block',
        'category' => 'Content', // Appears in UX Builder sidebar under "Content"
        'priority' => 1,
        'options' => array(
            'title' => array(
                'type' => 'textfield',
                'heading' => 'Title',
                'default' => '',
            ),
            'content' => array(
                'type' => 'textarea',
                'heading' => 'Content',
                'default' => '',
            ),
            'style' => array(
                'type' => 'select',
                'heading' => 'Style',
                'default' => 'style1',
                'options' => array(
                    'style1' => 'Style 1',
                    'style2' => 'Style 2',
                    'style3' => 'Style 3',
                )
            ),
            'bg_color' => array(
                'type' => 'colorpicker',
                'heading' => 'Background Color',
                'default' => '#ffffff',
            ),
            'padding' => array(
                'type' => 'slider',
                'heading' => 'Padding',
                'default' => 30,
                'min' => 0,
                'max' => 100,
                'step' => 5,
                'unit' => 'px',
            ),
        ),
    ));

    // Product Display Element
    add_ux_builder_shortcode('custom_product_showcase', array(
        'name' => 'Custom Product Showcase',
        'category' => 'Shop',
        'priority' => 1,
        'options' => array(
            'category' => array(
                'type' => 'select',
                'heading' => 'Category',
                'default' => '',
                'options' => get_product_categories_array()
            ),
            'layout' => array(
                'type' => 'select',
                'heading' => 'Layout',
                'default' => 'grid',
                'options' => array(
                    'grid' => 'Grid',
                    'slider' => 'Slider',
                    'masonry' => 'Masonry'
                )
            ),
            'columns' => array(
                'type' => 'slider',
                'heading' => 'Columns',
                'default' => 4,
                'min' => 1,
                'max' => 6,
            ),
            'products' => array(
                'type' => 'slider',
                'heading' => 'Number of Products',
                'default' => 8,
                'min' => 1,
                'max' => 24,
            )
        ),
    ));

    // Custom Form Element
    add_ux_builder_shortcode('custom_form_block', array(
        'name' => 'Custom Form Block',
        'category' => 'Content',
        'priority' => 1,
        'options' => array(
            'form_id' => array(
                'type' => 'select',
                'heading' => 'Select Form',
                'default' => '',
                'options' => get_gravity_forms_array()
            ),
            'title' => array(
                'type' => 'textfield',
                'heading' => 'Form Title',
                'default' => '',
            ),
            'description' => array(
                'type' => 'textarea',
                'heading' => 'Form Description',
                'default' => '',
            ),
            'layout' => array(
                'type' => 'select',
                'heading' => 'Layout',
                'default' => 'standard',
                'options' => array(
                    'standard' => 'Standard',
                    'floating' => 'Floating Labels',
                    'compact' => 'Compact'
                )
            )
        ),
    ));
}

// Helper function to get product categories
function get_product_categories_array() {
    $categories = get_terms(array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    ));
    
    $options = array('' => 'Select Category');
    foreach ($categories as $cat) {
        $options[$cat->term_id] = $cat->name;
    }
    return $options;
}

// Helper function to get Gravity Forms
function get_gravity_forms_array() {
    if (class_exists('GFAPI')) {
        $forms = GFAPI::get_forms();
        $options = array('' => 'Select Form');
        foreach ($forms as $form) {
            $options[$form['id']] = $form['title'];
        }
        return $options;
    }
    return array('' => 'No Forms Found');
}

// Render functions for the shortcodes
function custom_content_block_shortcode($atts) {
    $atts = shortcode_atts(array(
        'title' => '',
        'content' => '',
        'style' => 'style1',
        'bg_color' => '#ffffff',
        'padding' => 30,
    ), $atts);
    
    $style_class = 'custom-block-' . $atts['style'];
    $style_attr = sprintf(
        'background-color: %s; padding: %spx;',
        esc_attr($atts['bg_color']),
        esc_attr($atts['padding'])
    );
    
    ob_start();
    ?>
    <div class="custom-content-block <?php echo $style_class; ?>" style="<?php echo $style_attr; ?>">
        <?php if ($atts['title']) : ?>
            <h3 class="custom-block-title"><?php echo esc_html($atts['title']); ?></h3>
        <?php endif; ?>
        <div class="custom-block-content">
            <?php echo wp_kses_post($atts['content']); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('custom_content_block', 'custom_content_block_shortcode');

function custom_product_showcase_shortcode($atts) {
    $atts = shortcode_atts(array(
        'category' => '',
        'layout' => 'grid',
        'columns' => 4,
        'products' => 8
    ), $atts);
    
    $shortcode = sprintf(
        '[products category="%s" columns="%d" limit="%d" style="%s"]',
        esc_attr($atts['category']),
        esc_attr($atts['columns']),
        esc_attr($atts['products']),
        esc_attr($atts['layout'])
    );
    
    return do_shortcode($shortcode);
}
add_shortcode('custom_product_showcase', 'custom_product_showcase_shortcode');

function custom_form_block_shortcode($atts) {
    $atts = shortcode_atts(array(
        'form_id' => '',
        'title' => '',
        'description' => '',
        'layout' => 'standard'
    ), $atts);
    
    ob_start();
    ?>
    <div class="custom-form-block layout-<?php echo esc_attr($atts['layout']); ?>">
        <?php if ($atts['title']) : ?>
            <h3 class="form-title"><?php echo esc_html($atts['title']); ?></h3>
        <?php endif; ?>
        
        <?php if ($atts['description']) : ?>
            <div class="form-description">
                <?php echo wp_kses_post($atts['description']); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($atts['form_id'] && function_exists('gravity_form')) : ?>
            <?php gravity_form($atts['form_id'], false, false, false, '', true); ?>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('custom_form_block', 'custom_form_block_shortcode');
