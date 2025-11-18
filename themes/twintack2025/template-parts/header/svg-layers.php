<?php
$layers = get_field('svg_layers');
$bg_color = get_field('background_color');

if (!$layers) return;
?>

<div class="twintack-svg-header" style="background-color: <?php echo esc_attr($bg_color); ?>">
    <?php foreach ($layers as $index => $layer) : 
        $layer_class = sprintf(
            'svg-layer layer-%d animation-%s',
            $index,
            esc_attr($layer['animation_type'])
        );
        
        $layer_style = sprintf(
            'opacity: %f; transform: translate(%d%%, %d%%); z-index: %d;',
            $layer['opacity'] / 100,
            $layer['x_position'],
            $layer['y_position'],
            $layer['z_index']
        );
    ?>
        <div class="<?php echo esc_attr($layer_class); ?>" style="<?php echo esc_attr($layer_style); ?>">
            <?php if ($layer['layer_type'] === 'svg' && $layer['svg_file']) : ?>
                <div class="svg-content" style="color: <?php echo esc_attr($layer['layer_color']); ?>">
                    <?php 
                    $svg_path = get_attached_file($layer['svg_file']['ID']);
                    if ($svg_path && file_exists($svg_path)) {
                        $svg_content = TwinTack_SVG_Support::sanitize_svg($svg_path);
                        echo wp_kses(
                            $svg_content,
                            array(
                                'svg' => array(
                                    'class' => true,
                                    'aria-hidden' => true,
                                    'aria-labelledby' => true,
                                    'role' => true,
                                    'xmlns' => true,
                                    'width' => true,
                                    'height' => true,
                                    'viewbox' => true,
                                    'preserveaspectratio' => true,
                                ),
                                'g' => array('fill' => true),
                                'title' => array('title' => true),
                                'path' => array(
                                    'd' => true,
                                    'fill' => true
                                )
                            )
                        );
                    }
                    ?>
                </div>
            <?php elseif ($layer['layer_type'] === 'color') : ?>
                <div class="color-layer" style="background-color: <?php echo esc_attr($layer['layer_color']); ?>"></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div> 