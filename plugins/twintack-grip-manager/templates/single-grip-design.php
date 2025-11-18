<?php
/**
 * Template for displaying single grip designs
 */

get_header(); ?>

<div class="grip-design-container">
    <a href="<?php echo esc_url(wc_get_account_endpoint_url('grip-designs')); ?>" class="back-to-designs">&laquo; Back to My Grip Designs</a>
    
    <div class="grip-design-header">
        <h1><?php the_title(); ?></h1>
        <div class="grip-design-status">
            <?php 
            $status = get_post_status();
            // Use our custom grip design status mapping
            $grip_status_label = function_exists('twintack_get_grip_status_label') 
                ? twintack_get_grip_status_label($status) 
                : get_post_status_object($status)->label;
            echo '<span class="status-label status-' . esc_attr($status) . '">';
            echo esc_html($grip_status_label);
            echo '</span>';
            ?>
        </div>
    </div>

    <div class="grip-design-content">
        <!-- Artwork Section -->
        <div class="grip-design-artwork">
            <h2>Design Preview</h2>
            <?php 
            // Display Design Mockup from Monday.com first if available
            $mockup_url = get_post_meta(get_the_ID(), '_grip_mockup_url', true);
            $mockup_filename = get_post_meta(get_the_ID(), '_grip_mockup_filename', true);
            if (!empty($mockup_url) && filter_var($mockup_url, FILTER_VALIDATE_URL)): ?>
                <div class="artwork-preview mockup-preview">
                    <img src="<?php echo esc_url($mockup_url); ?>" alt="Design Mockup">
                    <div class="artwork-actions">
                        <strong>Mockup File:</strong> <?php echo esc_html($mockup_filename); ?><br>
                        <a href="<?php echo esc_url($mockup_url); ?>" class="button" target="_blank">View Full Size</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php 
            // Display submitted artwork
            $artwork_url = get_post_meta(get_the_ID(), '_grip_artwork_url', true);
            $filename = get_post_meta(get_the_ID(), '_grip_artwork_filename', true);
            if (!empty($artwork_url) && filter_var($artwork_url, FILTER_VALIDATE_URL)): ?>
                <div class="artwork-preview">
                    <h3>Submitted Artwork</h3>
                    <img src="<?php echo esc_url($artwork_url); ?>" alt="Submitted Artwork">
                    <div class="artwork-actions">
                        <strong>File:</strong> <?php echo esc_html($filename); ?><br>
                        <a href="<?php echo esc_url($artwork_url); ?>" class="button" target="_blank">View Full Size</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="artwork-preview no-artwork">
                    <div class="no-artwork-placeholder">No Artwork Available</div>
                    <?php if (!empty($filename)): ?>
                        <p><strong>Filename:</strong> <?php echo esc_html($filename); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Design Details Section -->
        <div class="grip-design-info">
            <h2>Design Details</h2>
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
                // Display detailed color information
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

            <?php if ($monday_feedback = get_post_meta(get_the_ID(), '_grip_monday_feedback', true)): ?>
                <div class="monday-feedback">
                    <h3>Design Team Feedback</h3>
                    <div class="feedback-content">
                        <?php echo wpautop(esc_html($monday_feedback)); ?>
                    </div>
                </div>
            <?php endif; ?>
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

/* Artwork Pending - Draft Status */
.status-draft {
    background: #fff6d9;
    color: #856404;
    border: 1px solid #ffeaa7;
}

/* Pending Review - Pending Status */
.status-pending {
    background: #e1f5fe;
    color: #0277bd;
    border: 1px solid #81d4fa;
}

/* Artwork Approved - Publish Status */
.status-publish {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

/* Internal Review - Private Status */
.status-private {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f1aeb5;
}

/* Scheduled - Future Status */
.status-future {
    background: #e2e3e5;
    color: #383d41;
    border: 1px solid #ced4da;
}

/* Legacy support for old custom statuses */
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

/* Monday.com specific styling */
.monday-feedback {
    background: #e8f4fd;
    border-color: #0078d4;
    border-left: 4px solid #0078d4;
}

.monday-feedback h3 {
    color: #0078d4;
}

.grip-design-mockup {
    margin-top: 2rem;
    padding: 1rem;
    background: #f0f9ff;
    border: 1px solid #0078d4;
    border-radius: 4px;
}

.grip-design-mockup h2 {
    color: #0078d4;
    margin-bottom: 1rem;
}

.mockup-preview {
    background: #ffffff;
    border: 2px dashed #0078d4;
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