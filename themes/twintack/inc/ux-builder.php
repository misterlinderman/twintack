<?php
// flatsome-child/inc/ux-builder.php

function custom_ux_builder_element() {
    if (function_exists('add_ux_builder_shortcode')) {
        add_ux_builder_shortcode('custom_product_grid', array(
            'name' => 'Custom Product Grid',
            'category' => 'Shop',
            'priority' => 1,
            'options' => array(
                'title' => array(
                    'type' => 'textfield',
                    'heading' => 'Title',
                    'default' => '',
                ),
                'category' => array(
                    'type' => 'select',
                    'heading' => 'Category',
                    'default' => '',
                    'options' => get_product_categories_for_select()
                ),
                'style' => array(
                    'type' => 'select',
                    'heading' => 'Style',
                    'default' => 'normal',
                    'options' => array(
                        'normal' => 'Normal',
                        'overlay' => 'Overlay',
                        'shade' => 'Shade'
                    )
                ),
                'columns' => array(
                    'type' => 'slider',
                    'heading' => 'Columns',
                    'default' => '4',
                    'max' => '6',
                    'min' => '1'
                ),
            ),
            'template' => '[products category="{{category}}" columns="{{columns}}" style="{{style}}"]'
        ));
    }
}
add_action('ux_builder_setup', 'custom_ux_builder_element');

// Helper function for categories
function get_product_categories_for_select() {
    $categories = get_terms('product_cat', array('hide_empty' => false));
    $options = array();
    foreach ($categories as $cat) {
        $options[$cat->slug] = $cat->name;
    }
    return $options;
}