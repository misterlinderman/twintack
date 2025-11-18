<?php
if (!get_field('enable_svg_header')) {
    return;
}

$background = get_field('header_background');
$layers = get_field('svg_layers');

if (!$layers) {
    return;
}
?>

<div class="svg-header">
    <?php
    // Background
    if ($background['bg_type'] === 'color') {
        printf(
            '<div class="svg-header__background" style="background-color: %s;"></div>',
            esc_attr($background['bg_color'])
        );
    }

    // SVG Layers
    foreach ($layers as $index => $layer) {
        $svg_url = $layer['svg_file']['url'];
        if (!$svg_url) continue;

        $layer_class = 'svg-header__layer';
        if ($layer['animation_type'] !== 'none') {
            $layer_class .= ' animation-' . $layer['animation_type'];
        }

        $style = sprintf(
            'opacity: %s; color: %s;',
            $layer['layer_opacity'] / 100,
            $layer['layer_color']
        );

        printf(
            '<div class="%s" style="%s" data-layer="%d">%s</div>',
            esc_attr($layer_class),
            esc_attr($style),
            $index,
            file_get_contents($svg_url)
        );
    }
    ?>

    <div class="svg-header__content">
        <?php if (is_singular()) : ?>
            <h1><?php the_title(); ?></h1>
        <?php endif; ?>
    </div>
</div> 