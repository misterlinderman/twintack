<?php
$current_category = '';
$show_secondary_nav = false;

// Check if we're on a product category page or its child
if (is_product_category()) {
    $term = get_queried_object();
    if (strpos(strtolower($term->name), 'baseball') !== false) {
        $current_category = 'baseball';
        $show_secondary_nav = true;
    } elseif (strpos(strtolower($term->name), 'fishing') !== false) {
        $current_category = 'fishing';
        $show_secondary_nav = true;
    }
}

// Check if we're on a single product page
if (is_product()) {
    $product_cats = get_the_terms(get_the_ID(), 'product_cat');
    if ($product_cats) {
        foreach ($product_cats as $cat) {
            if (strpos(strtolower($cat->name), 'baseball') !== false) {
                $current_category = 'baseball';
                $show_secondary_nav = true;
                break;
            } elseif (strpos(strtolower($cat->name), 'fishing') !== false) {
                $current_category = 'fishing';
                $show_secondary_nav = true;
                break;
            }
        }
    }
}

// Add check for parent page IDs
$body_classes = get_body_class();
if (in_array('parent-pageid-92', $body_classes)) {
    $current_category = 'baseball';
    $show_secondary_nav = true;
} elseif (in_array('parent-pageid-95', $body_classes)) {
    $current_category = 'fishing';
    $show_secondary_nav = true;
}

if ($show_secondary_nav) : ?>
    <nav class="category-menu">
        <?php
        wp_nav_menu(array(
            'theme_location' => $current_category,
            'menu_class' => 'category-menu',
            'container_class' => 'category-menu-container'
        ));
        ?>
    </nav>
<?php endif; ?> 