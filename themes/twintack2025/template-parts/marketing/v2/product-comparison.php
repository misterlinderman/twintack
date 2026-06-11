<?php
/**
 * Product comparison chart.
 *
 * @package twintack2025
 * @var array $args Template args.
 */

if (!defined('ABSPATH')) {
    exit;
}

$meta = isset($args['meta']) ? $args['meta'] : array();
$rows = isset($meta['comparison_rows']) ? (array) $meta['comparison_rows'] : array();

if (empty($rows)) {
    return;
}
?>
<section class="tt-v2-comparison">
    <div class="container">
        <?php if (!empty($meta['comparison_title'])) : ?>
            <h2 class="tt-v2-comparison__title"><?php echo esc_html($meta['comparison_title']); ?></h2>
        <?php endif; ?>

        <div class="tt-v2-comparison__table-wrap">
            <table class="tt-v2-comparison__table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Feature', 'twintack2025'); ?></th>
                        <th scope="col"><?php esc_html_e('Benefit', 'twintack2025'); ?></th>
                        <th scope="col"><?php esc_html_e('TwinTack', 'twintack2025'); ?></th>
                        <th scope="col"><?php esc_html_e('Others', 'twintack2025'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html($row['feature']); ?></th>
                            <td><?php echo esc_html($row['benefit']); ?></td>
                            <td><?php echo esc_html($row['us']); ?></td>
                            <td><?php echo esc_html($row['them']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
