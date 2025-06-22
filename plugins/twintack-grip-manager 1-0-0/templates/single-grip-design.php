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
                if ($artwork_url) {
                    echo '<div class="artwork-preview">';
                    echo '<img src="' . esc_url($artwork_url) . '" alt="Submitted Artwork">';
                    echo '</div>';
                    echo '<p class="artwork-actions">';
                    echo '<strong>File:</strong> ' . esc_html($filename) . '<br>';
                    echo '<a href="' . esc_url($artwork_url) . '" class="button" target="_blank">View Full Size</a>';
                    echo '</p>';
                } else {
                    echo '<p>No artwork submitted</p>';
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
</style>

<?php get_footer(); ?> 