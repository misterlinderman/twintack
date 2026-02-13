<?php
/**
 * My Account Grip Designs template
 */

defined( 'ABSPATH' ) || exit;

// Enqueue lightbox scripts
wp_enqueue_style('magnific-popup', 'https://cdnjs.cloudflare.com/ajax/libs/magnific-popup.js/1.1.0/magnific-popup.min.css');
wp_enqueue_script('magnific-popup', 'https://cdnjs.cloudflare.com/ajax/libs/magnific-popup.js/1.1.0/jquery.magnific-popup.min.js', array('jquery'), '1.1.0', true);

?>
<style>
.grip-designs-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.grip-designs-header h2 {
    margin: 0;
    font-size: 1.8rem;
    color: #333;
}

.grip-designs-header p {
    margin: 0.5rem 0 0;
    color: #666;
}

.grip-designs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 2rem;
    padding: 1rem 0;
}

.grip-design-card {
    position: relative;
    height: 400px;
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    background: #f8f9fa;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.grip-design-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0,0,0,0.15);
}

.grip-design-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(180deg, 
        rgba(0,0,0,0.7) 0%,
        rgba(0,0,0,0.3) 30%,
        rgba(0,0,0,0.1) 50%,
        rgba(0,0,0,0.3) 70%,
        rgba(0,0,0,0.7) 100%
    );
    z-index: 1;
}

.grip-design-image {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.grip-design-card:hover .grip-design-image {
    transform: scale(1.05);
}

.grip-design-content {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    z-index: 2;
    color: white;
}

.grip-design-title {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 600;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.grip-design-meta {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.grip-design-quantity {
    font-size: 1.2rem;
    font-weight: 500;
}

.grip-design-cta {
    background: rgba(255,255,255,0.9);
    color: #333;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    text-align: center;
    font-weight: 500;
    transition: background 0.3s ease;
    margin-top: 1rem;
}

.grip-design-card:hover .grip-design-cta {
    background: white;
}

.grip-design-status {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.8rem;
    font-weight: 500;
    background: rgba(255,255,255,0.9);
    color: #333;
}

.grip-design-status.draft {
    background: rgba(255, 243, 205, 0.9);
    color: #856404;
}

.grip-design-status.pending {
    background: rgba(255, 193, 7, 0.9);
    color: #533f03;
}

.grip-design-status.publish {
    background: rgba(212, 237, 218, 0.9);
    color: #155724;
}

.grip-design-status.private {
    background: rgba(248, 249, 250, 0.9);
    color: #6c757d;
}

/* Hide any duplicate grip design displays that might come from other sources */
.grip-designs-grid:not(#main-grip-designs-display .grip-designs-grid) {
    display: none !important;
}

/* Ensure our main display is always visible */
#main-grip-designs-display .grip-designs-grid {
    display: grid !important;
}

/* Lightbox customization */
.mfp-content {
    max-width: 1200px;
    margin: 40px auto;
}

.grip-design-detail {
    display: none;
    background: white;
    border-radius: 12px;
    overflow: hidden;
}

.grip-design-detail-content {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    min-height: 600px;
}

.grip-design-detail-image {
    background: #f8f9fa;
    position: relative;
    padding: 2rem;
}

.grip-design-detail-image img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.grip-design-detail-info {
    padding: 3rem;
    background: #f8f9fa;
    overflow-y: auto;
}

.grip-design-detail-info h2 {
    margin: 0 0 1.5rem;
    font-size: 2rem;
    color: #333;
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 1rem;
}

.design-description {
    color: #666;
    line-height: 1.6;
    margin-bottom: 2rem;
}

.design-metadata {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.design-metadata h3 {
    margin: 0 0 1rem;
    font-size: 1.2rem;
    color: #333;
}

.design-metadata p {
    display: flex;
    justify-content: space-between;
    margin: 0.75rem 0;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f1f1f1;
}

.design-metadata p:last-child {
    border-bottom: none;
}

.design-metadata strong {
    color: #333;
}

.grip-design-author {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.8rem;
    font-weight: 500;
    background: rgba(236, 236, 236, 0.9);
    color: #666;
    margin-top: 0.5rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grip-design-detail-content {
        grid-template-columns: 1fr;
    }
    
    .grip-design-detail-image {
        height: 300px;
        padding: 1rem;
    }

    .grip-design-detail-info {
        padding: 1.5rem;
    }
}
</style>

<!-- START: Custom Grip Designs Template -->
<div class="twintack-custom-grip-designs-wrapper" id="main-grip-designs-display">
<div class="grip-designs-header">
    <h2><?php esc_html_e('My Grip Designs', 'twintack2025'); ?></h2>
    <p><?php _e('View and manage all your custom grip designs.', 'twintack2025'); ?></p>
</div>

<div class="grip-designs-grid">
    <?php
    $current_user = wp_get_current_user();
    $customer_email = $current_user->user_email;
    
    // Set up the query arguments - try multiple approaches to find grip designs
    $query_args = array(
        'post_type' => 'grip_design',
        'posts_per_page' => -1,
        'post_status' => array('publish', 'draft', 'pending', 'private'),
        'orderby' => 'date',
        'order' => 'DESC'
    );

    // For administrators, show all designs
    if (current_user_can('administrator')) {
        // Admins can see all designs
    } else {
        // For regular users, try to match by author first, then by email
        $meta_query = array(
            'relation' => 'OR',
            array(
                'key' => '_grip_customer_email',
                'value' => $customer_email,
                'compare' => '='
            )
        );
        
        // Also include posts authored by this user
        $author_posts_query = array(
            'post_type' => 'grip_design',
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'author' => $current_user->ID,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        // Get posts by email first
        $query_args['meta_query'] = $meta_query;
        $email_designs = get_posts($query_args);
        
        // Get posts by author
        $author_designs = get_posts($author_posts_query);
        
        // Merge and deduplicate
        $all_design_ids = array();
        $grip_designs = array();
        
        foreach ($email_designs as $design) {
            if (!in_array($design->ID, $all_design_ids)) {
                $all_design_ids[] = $design->ID;
                $grip_designs[] = $design;
            }
        }
        
        foreach ($author_designs as $design) {
            if (!in_array($design->ID, $all_design_ids)) {
                $all_design_ids[] = $design->ID;
                $grip_designs[] = $design;
            }
        }
    }

    // If admin or if we didn't get designs from the merged approach above
    if (current_user_can('administrator') || !isset($grip_designs)) {
        $grip_designs = get_posts($query_args);
    }

    // Debug information for testing
    if (isset($_GET['debug']) && current_user_can('administrator')) {
        echo '<div style="background: #f0f0f0; padding: 1rem; margin-bottom: 1rem; border-radius: 4px;">';
        echo '<strong>Debug Info:</strong><br>';
        echo 'Current User ID: ' . $current_user->ID . '<br>';
        echo 'Current User Email: ' . $customer_email . '<br>';
        echo 'Found ' . count($grip_designs) . ' grip designs<br>';
        if (!empty($grip_designs)) {
            echo 'Design IDs: ' . implode(', ', array_map(function($design) { return $design->ID; }, $grip_designs)) . '<br>';
        }
        echo '</div>';
    }

    if (empty($grip_designs)) {
        echo '<div class="woocommerce-message woocommerce-message--info">';
        echo __('No grip designs found.', 'twintack2025');
        echo '</div>';
    }

    foreach ($grip_designs as $design) :
        $thumbnail = get_the_post_thumbnail_url($design->ID, 'large');
        if (!$thumbnail) {
            $thumbnail = wc_placeholder_img_src('large');
        }
        $date_created = get_the_date('F j, Y', $design->ID);
        
        // Get artwork status using post status and our mapping function
        $post_status = get_post_status($design->ID);
        
        // Use the mapping function if it exists, otherwise create our own mapping
        if (function_exists('twintack_get_grip_status_label')) {
            $status = twintack_get_grip_status_label($post_status);
        } else {
            // Fallback mapping if function doesn't exist
            $status_map = array(
                'draft'     => 'Mockup Required',
                'pending'   => 'Customer Review', 
                'publish'   => 'Customer Approved',
                'private'   => 'Internal Review',
                'future'    => 'Scheduled'
            );
            $status = isset($status_map[$post_status]) ? $status_map[$post_status] : ucfirst($post_status);
        }
        
        $status_class = sanitize_html_class($post_status);
        

        $quantity = get_post_meta($design->ID, '_grip_quantity', true);
    ?>
        <div class="grip-design-card" data-design-id="<?php echo esc_attr($design->ID); ?>">
            <img src="<?php echo esc_url($thumbnail); ?>" 
                 alt="<?php echo esc_attr($design->post_title); ?>" 
                 class="grip-design-image">
            <div class="grip-design-content">
                <h3 class="grip-design-title"><?php echo esc_html($design->post_title); ?></h3>
                <div class="grip-design-meta">
                    <span class="grip-design-status <?php echo esc_attr($status_class); ?>">
                        <?php echo esc_html($status); ?>
                    </span>
                    <?php if ($quantity) : ?>
                    <span class="grip-design-quantity">
                        <?php printf(__('Quantity: %s', 'twintack2025'), esc_html($quantity)); ?>
                    </span>
                    <?php endif; ?>
                    <?php if (current_user_can('administrator')) : ?>
                    <span class="grip-design-author">
                        <?php 
                        $author = get_user_by('id', $design->post_author);
                        printf(__('Customer: %s', 'twintack2025'), esc_html($author ? $author->display_name : 'Unknown')); 
                        ?>
                    </span>
                    <?php endif; ?>
                    <div class="grip-design-cta">
                        <?php _e('View Details', 'twintack2025'); ?>
                    </div>
                </div>
            </div>
        </div>

        <div id="grip-design-detail-<?php echo esc_attr($design->ID); ?>" class="grip-design-detail">
            <div class="grip-design-detail-content">
                <div class="grip-design-detail-image">
                    <?php echo get_the_post_thumbnail($design->ID, 'large'); ?>
                </div>
                <div class="grip-design-detail-info">
                    <h2><?php echo esc_html($design->post_title); ?></h2>
                    <div class="grip-design-meta">
                        <span class="grip-design-status <?php echo esc_attr($status_class); ?>">
                            <?php echo esc_html($status); ?>
                        </span>
                        <p><?php printf(__('Created on %s', 'twintack2025'), esc_html($date_created)); ?></p>
                    </div>
                    <div class="design-description">
                        <?php echo wp_kses_post($design->post_content); ?>
                    </div>
                    <?php
                    // Add additional design metadata
                    $design_meta = get_post_meta($design->ID);
                    if (!empty($design_meta)) {
                        echo '<div class="design-metadata">';
                        echo '<h3>' . __('Design Details', 'twintack2025') . '</h3>';
                        foreach ($design_meta as $key => $value) {
                            if (!in_array($key, array('_edit_lock', '_edit_last', '_thumbnail_id', 'design_status'))) {
                                echo '<p>';
                                echo '<strong>' . esc_html(ucwords(str_replace('_', ' ', $key))) . '</strong>';
                                echo '<span>' . esc_html($value[0]) . '</span>';
                                echo '</p>';
                            }
                        }
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
jQuery(document).ready(function($) {
    $('.grip-design-card').on('click', function() {
        var designId = $(this).data('design-id');
        $.magnificPopup.open({
            items: {
                src: '#grip-design-detail-' + designId,
                type: 'inline'
            },
            mainClass: 'mfp-fade',
            removalDelay: 300,
            callbacks: {
                beforeOpen: function() {
                    this.st.mainClass = 'mfp-fade';
                }
            },
            closeMarkup: '<button title="%title%" type="button" class="mfp-close">×</button>'
        });
    });
});
</script>
</div>
<!-- END: Custom Grip Designs Template --> 