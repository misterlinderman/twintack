<?php
/**
 * Template for displaying single grip designs
 */

get_header(); ?>

<div class="grip-design-container">
    <div class="grip-design-header">
        <h1><?php the_title(); ?></h1>
        <div class="grip-design-status">
            <?php 
            $status = get_post_status();
            $status_object = get_post_status_object($status);
            echo '<span class="status-label status-' . esc_attr($status) . '">';
            echo esc_html($status_object->label);
            echo '</span>';
            ?>
        </div>
    </div>

    <div class="grip-design-content">
        <div class="grip-design-details">
            <div class="grip-design-info">
                <h2>Order Details</h2>
                <table class="grip-details-table">
                    <tr>
                        <th>Customer:</th>
                        <td><?php echo esc_html(get_post_meta(get_the_ID(), '_grip_customer_name', true)); ?></td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td><?php echo esc_html(get_post_meta(get_the_ID(), '_grip_customer_email', true)); ?></td>
                    </tr>
                    <tr>
                        <th>Team/School:</th>
                        <td><?php echo esc_html(get_post_meta(get_the_ID(), '_grip_team_name', true)); ?></td>
                    </tr>
                    <tr>
                        <th>Design Type:</th>
                        <td><?php echo esc_html(get_post_meta(get_the_ID(), '_grip_design_type', true)); ?></td>
                    </tr>
                    <?php
                    // Display detailed color information from the new form if available
                    $design_layout = get_post_meta(get_the_ID(), '_grip_design_layout', true);
                    if (!empty($design_layout)): ?>
                        <tr>
                            <th>Pattern:</th>
                            <td><?php echo esc_html($design_layout); ?></td>
                        </tr>
                        <tr>
                            <th>Colors:</th>
                            <td>
                                <div class="design-pattern-colors">
                                    <?php 
                                    $primary_color = get_post_meta(get_the_ID(), '_grip_primary_color', true);
                                    if (!empty($primary_color)): ?>
                                        <div class="design-color">
                                            <span class="color-swatch" style="background-color: <?php echo get_color_hex($primary_color); ?>;"></span>
                                            <span class="color-name">Primary: <?php echo esc_html($primary_color); ?></span>
                                        </div>
                                    <?php endif;
                                    
                                    $secondary_color = get_post_meta(get_the_ID(), '_grip_secondary_color', true);
                                    if (!empty($secondary_color)): ?>
                                        <div class="design-color">
                                            <span class="color-swatch" style="background-color: <?php echo get_color_hex($secondary_color); ?>;"></span>
                                            <span class="color-name">Secondary: <?php echo esc_html($secondary_color); ?></span>
                                        </div>
                                    <?php endif;
                                    
                                    $tertiary_color = get_post_meta(get_the_ID(), '_grip_tertiary_color', true);
                                    if (!empty($tertiary_color)): ?>
                                        <div class="design-color">
                                            <span class="color-swatch" style="background-color: <?php echo get_color_hex($tertiary_color); ?>;"></span>
                                            <span class="color-name">Tertiary: <?php echo esc_html($tertiary_color); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Quantity:</th>
                        <td><?php echo esc_html(get_post_meta(get_the_ID(), '_grip_quantity', true)); ?></td>
                    </tr>
                </table>

                <?php if ($feedback = get_post_meta(get_the_ID(), '_grip_feedback', true)): ?>
                    <div class="grip-feedback">
                        <h3>Design Instructions</h3>
                        <div class="feedback-content">
                            <?php echo wpautop(esc_html($feedback)); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="grip-design-artwork">
                <h2>Submitted Artwork</h2>
                <?php 
                $artwork_url = get_post_meta(get_the_ID(), '_grip_artwork_url', true);
                $filename = get_post_meta(get_the_ID(), '_grip_artwork_filename', true);
                if (!empty($artwork_url) && filter_var($artwork_url, FILTER_VALIDATE_URL)) {
                    echo '<div class="artwork-preview">';
                    echo '<img src="' . esc_url($artwork_url) . '" alt="Submitted Artwork">';
                    echo '</div>';
                    echo '<p class="artwork-actions">';
                    echo '<strong>File:</strong> ' . esc_html($filename) . '<br>';
                    echo '<a href="' . esc_url($artwork_url) . '" class="button" target="_blank">View Full Size</a>';
                    echo '</p>';
                } else {
                    echo '<div class="artwork-preview no-artwork">';
                    echo '<div class="no-artwork-placeholder">No Artwork Available</div>';
                    echo '</div>';
                    if (!empty($filename)) {
                        echo '<p><strong>Filename:</strong> ' . esc_html($filename) . '</p>';
                    } else {
                        echo '<p>No artwork submitted</p>';
                    }
                }
                ?>
            </div>
        </div>
    </div>
</div>

<style>
.grip-design-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 2rem;
}

.grip-design-header {
    margin-bottom: 2rem;
    border-bottom: 1px solid #ddd;
    padding-bottom: 1rem;
}

.status-label {
    display: inline-block;
    padding: 0.5em 1em;
    border-radius: 3px;
    font-weight: bold;
    background: #f0f0f0;
}

.status-artwork_pending {
    background: #fff6d9;
    color: #856404;
}

.status-artwork_approved {
    background: #d4edda;
    color: #155724;
}

.grip-design-content {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
}

.grip-details-table {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
}

.grip-details-table th,
.grip-details-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #ddd;
    text-align: left;
}

.grip-details-table th {
    width: 30%;
    font-weight: bold;
}

.artwork-preview {
    margin: 1rem 0;
    padding: 1rem;
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.artwork-preview img {
    max-width: 100%;
    height: auto;
    display: block;
    margin: 0 auto;
}

.artwork-actions {
    margin-top: 1rem;
    text-align: center;
}

.notice {
    padding: 1rem;
    margin: 1rem 0;
    border-left: 4px solid #00a0d2;
    background: #fff;
}

.notice-info {
    border-color: #00a0d2;
    background-color: #f0f9ff;
}

.grip-feedback {
    margin-top: 2rem;
    padding: 1rem;
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.feedback-content {
    margin-top: 1rem;
}

.design-pattern-colors {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 5px;
}

.design-color {
    display: flex;
    align-items: center;
    padding: 5px 10px;
    background: #f5f5f5;
    border-radius: 20px;
}

.color-swatch {
    display: inline-block;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    margin-right: 5px;
    border: 1px solid rgba(0,0,0,0.1);
}

.color-name {
    font-size: 0.9em;
}

@media (min-width: 768px) {
    .grip-design-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<?php 
/**
 * Get approximate hex color codes for named colors
 */
function get_color_hex($color_name) {
    $color_map = array(
        'white' => '#ffffff',
        'grey' => '#888888',
        'black' => '#000000',
        'red' => '#ff0000',
        'yellow' => '#ffff00',
        'royal' => '#4169e1',
        'orange' => '#ffa500',
        'green' => '#008000',
        'purple' => '#800080',
        'neon green' => '#39ff14',
        'neon pink' => '#ff6ec7',
        'teal' => '#008080',
        'violet' => '#8a2be2',
        'safety yellow' => '#eed202'
    );
    
    // Normalize color name for lookup
    $color_key = strtolower($color_name);
    
    // Check for exact match
    if (isset($color_map[$color_key])) {
        return $color_map[$color_key];
    }
    
    // Check for partial matches
    foreach ($color_map as $key => $hex) {
        if (strpos($color_key, $key) !== false) {
            return $hex;
        }
    }
    
    // Default fallback color
    return '#cccccc';
}

get_footer(); ?> 