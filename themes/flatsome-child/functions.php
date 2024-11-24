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


// Add this to your child theme's functions.php or a separate file like inc/gravity-woo-integration.php

class Custom_Product_Form_Handler {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Add form ID field to product data
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_form_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_form_field'));

        // Display form on product page
        add_action('woocommerce_before_add_to_cart_button', array($this, 'display_product_form'));

        // Handle form submission
        add_action('gform_after_submission', array($this, 'handle_form_submission'), 10, 2);

        // Add form data to cart item
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_form_data_to_cart'), 10, 3);

        // Display form data in cart and checkout
        add_filter('woocommerce_get_item_data', array($this, 'display_form_data_in_cart'), 10, 2);

        // Save form data to order
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_form_data_to_order'), 10, 4);

        // Add form data to order emails
        add_action('woocommerce_order_item_meta_end', array($this, 'display_form_data_in_emails'), 10, 3);

        // Add form data to admin order page
        add_action('woocommerce_admin_order_item_values', array($this, 'display_form_data_in_admin'), 10, 3);
    }

    public function add_form_field() {
        global $woocommerce, $post;
        
        echo '<div class="options_group">';
        
        woocommerce_wp_select(array(
            'id' => '_gravity_form_id',
            'label' => 'Custom Order Form',
            'options' => $this->get_gravity_forms_list(),
            'desc_tip' => true,
            'description' => 'Select a form to attach to this product'
        ));

        echo '</div>';
    }

    public function save_form_field($post_id) {
        if (isset($_POST['_gravity_form_id'])) {
            update_post_meta($post_id, '_gravity_form_id', esc_attr($_POST['_gravity_form_id']));
        }
    }

    public function display_product_form() {
        global $product;
        
        $form_id = get_post_meta($product->get_id(), '_gravity_form_id', true);
        
        if ($form_id && class_exists('GFAPI')) {
            echo '<div class="custom-product-form">';
            gravity_form(
                $form_id, 
                false, // show title
                false, // show description
                false, // don't show inactive
                array('product_id' => $product->get_id()), // form args
                true, // ajax
                1, // tabindex
                false // echo
            );
            echo '</div>';
        }
    }

    public function handle_form_submission($entry, $form) {
        // Store the entry ID in a session for cart integration
        WC()->session->set('last_gravity_form_entry', array(
            'entry_id' => $entry['id'],
            'form_id' => $form['id']
        ));
    }

    public function add_form_data_to_cart($cart_item_data, $product_id, $variation_id) {
        $form_data = WC()->session->get('last_gravity_form_entry');
        
        if ($form_data) {
            $cart_item_data['gravity_form_data'] = $form_data;
            WC()->session->__unset('last_gravity_form_entry');
        }
        
        return $cart_item_data;
    }

    public function display_form_data_in_cart($item_data, $cart_item) {
        if (isset($cart_item['gravity_form_data'])) {
            $entry = GFAPI::get_entry($cart_item['gravity_form_data']['entry_id']);
            $form = GFAPI::get_form($cart_item['gravity_form_data']['form_id']);
            
            foreach ($form['fields'] as $field) {
                if (!empty($entry[$field->id])) {
                    $item_data[] = array(
                        'key' => $field->label,
                        'value' => $entry[$field->id]
                    );
                }
            }
        }
        return $item_data;
    }

    public function save_form_data_to_order($item, $cart_item_key, $values, $order) {
        if (isset($values['gravity_form_data'])) {
            $entry = GFAPI::get_entry($values['gravity_form_data']['entry_id']);
            $form = GFAPI::get_form($values['gravity_form_data']['form_id']);
            
            $item->add_meta_data('_gravity_form_entry_id', $entry['id']);
            $item->add_meta_data('_gravity_form_data', $this->format_form_data($entry, $form));
        }
    }

    private function format_form_data($entry, $form) {
        $formatted_data = array();
        foreach ($form['fields'] as $field) {
            if (!empty($entry[$field->id])) {
                $formatted_data[$field->label] = $entry[$field->id];
            }
        }
        return $formatted_data;
    }

    private function get_gravity_forms_list() {
        $forms = GFAPI::get_forms();
        $options = array('' => 'Select a form');
        foreach ($forms as $form) {
            $options[$form['id']] = $form['title'];
        }
        return $options;
    }
}

// Initialize the handler
add_action('init', array('Custom_Product_Form_Handler', 'get_instance'));

// Add custom styles
add_action('wp_enqueue_scripts', 'custom_product_form_styles');
function custom_product_form_styles() {
    wp_add_inline_style('flatsome-style', '
        .custom-product-form {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .custom-product-form .gform_wrapper {
            margin: 0;
        }
        
        .custom-product-form .gfield {
            margin-bottom: 15px;
        }
    ');
}


// Add this to your child theme's functions.php

// 1. Add Form Selection to Product
add_action('woocommerce_product_options_general_product_data', 'add_gravity_form_field');
function add_gravity_form_field() {
    global $woocommerce, $post;
    
    echo '<div class="options_group show_if_simple show_if_variable">';
    
    woocommerce_wp_select(array(
        'id' => '_gravity_form_id',
        'label' => 'Custom Order Form',
        'description' => 'Select a Gravity Form to display with this product',
        'desc_tip' => true,
        'options' => get_gravity_forms_options()
    ));
    
    echo '</div>';
}

// 2. Get Available Forms
function get_gravity_forms_options() {
    if (!class_exists('GFAPI')) {
        return array('' => 'Gravity Forms not installed');
    }
    
    $forms = GFAPI::get_forms();
    $options = array('' => 'Select a form');
    
    foreach ($forms as $form) {
        $options[$form['id']] = $form['title'];
    }
    
    return $options;
}

// 3. Save Form Selection
add_action('woocommerce_process_product_meta', 'save_gravity_form_field');
function save_gravity_form_field($post_id) {
    $gravity_form_id = isset($_POST['_gravity_form_id']) ? $_POST['_gravity_form_id'] : '';
    update_post_meta($post_id, '_gravity_form_id', sanitize_text_field($gravity_form_id));
}

// 4. Display Form on Product Page
add_action('woocommerce_before_add_to_cart_form', 'display_gravity_form');
function display_gravity_form() {
    global $product;
    
    // Debug output
    error_log('Attempting to display form for product: ' . $product->get_id());
    
    $form_id = get_post_meta($product->get_id(), '_gravity_form_id', true);
    
    // Debug output
    error_log('Form ID found: ' . $form_id);
    
    if (!empty($form_id) && function_exists('gravity_form')) {
        echo '<div class="product-custom-form">';
        gravity_form(
            $form_id,
            false, // show title
            false, // show description
            false, // don't show inactive
            null,  // field values
            true,  // ajax
            0,     // tabindex
            true   // echo
        );
        echo '</div>';
    }
}

// 5. Add styling
add_action('wp_head', 'add_gravity_form_styles');
function add_gravity_form_styles() {
    ?>
    <style>
        .product-custom-form {
            margin: 20px 0;
            padding: 20px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .product-custom-form .gform_wrapper {
            margin: 0;
        }
        
        /* Position the form before add to cart */
        .woocommerce div.product form.cart {
            margin-top: 20px;
        }
    </style>
    <?php
}

// 6. Store form submission data with order
add_filter('woocommerce_add_cart_item_data', 'add_gravity_form_data_to_cart', 10, 3);
function add_gravity_form_data_to_cart($cart_item_data, $product_id, $variation_id) {
    $form_id = get_post_meta($product_id, '_gravity_form_id', true);
    
    if (!empty($form_id) && isset($_POST['gform_submit'])) {
        $cart_item_data['gravity_form_data'] = array(
            'form_id' => $form_id,
            'fields' => $_POST
        );
    }
    
    return $cart_item_data;
}

// Add this to your functions.php temporarily

add_action('init', 'debug_gravity_form_setup');
function debug_gravity_form_setup() {
    error_log('Gravity Forms Debug: Init called');
    
    if (class_exists('GFAPI')) {
        error_log('Gravity Forms is active');
        $forms = GFAPI::get_forms();
        error_log('Available forms: ' . print_r($forms, true));
    } else {
        error_log('Gravity Forms is NOT active');
    }
}

add_action('woocommerce_before_single_product', 'debug_product_form');
function debug_product_form() {
    global $product;
    
    if ($product) {
        $form_id = get_post_meta($product->get_id(), '_gravity_form_id', true);
        error_log('Product ID: ' . $product->get_id());
        error_log('Form ID found: ' . $form_id);
    } else {
        error_log('No product object found');
    }
}