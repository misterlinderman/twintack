<?php
/**
 * Template part for displaying team members on the company page
 *
 * @package twintack2025
 */

// Check if ACF is active before trying to use its functions
if (!function_exists('get_field')) {
    return;
}

// Query team members
$args = array(
    'post_type' => 'team-member',
    'posts_per_page' => -1,
    'orderby' => 'menu_order',
    'order' => 'ASC'
);

$team_query = new WP_Query($args);

if ($team_query->have_posts()) : ?>
    <section id="our-team" class="team-section">
        <h2 class="section-title">Our Team</h2>
        <div class="team-members-grid">
            <?php 
            $modal_counter = 0;
            while ($team_query->have_posts()) : $team_query->the_post();
                $member_name = get_field('member_name');
                $member_role = get_field('member_role');
                $member_photo = get_field('member_photo');
                $member_description = get_field('member_description');
                $modal_id = 'team-modal-' . $modal_counter;
                $modal_counter++;
                ?>
                
                <div class="team-member-card" data-modal-target="<?php echo esc_attr($modal_id); ?>">
                    <?php if ($member_photo) : ?>
                        <div class="member-photo">
                            <img src="<?php echo esc_url($member_photo); ?>" alt="<?php echo esc_attr($member_name); ?>">
                        </div>
                    <?php endif; ?>
                    
                    <div class="member-info">
                        <?php if ($member_name) : ?>
                            <h3 class="member-name"><?php echo esc_html($member_name); ?></h3>
                        <?php endif; ?>
                        
                        <?php if ($member_role) : ?>
                            <p class="member-role"><?php echo esc_html($member_role); ?></p>
                        <?php endif; ?>
                        
                        <div class="member-description-preview">
                            <?php 
                            // Display a short preview of description
                            if ($member_description) {
                                $preview = wp_strip_all_tags($member_description);
                                $preview = substr($preview, 0, 100);
                                echo esc_html($preview) . (strlen($preview) >= 100 ? '...' : '');
                            }
                            ?>
                        </div>
                        
                        <button class="view-profile-btn">View Profile</button>
                    </div>
                </div>
                
                <!-- Team Member Modal -->
                <div class="team-modal" id="<?php echo esc_attr($modal_id); ?>">
                    <div class="team-modal-content">
                        <span class="close-modal">&times;</span>
                        <div class="team-modal-inner">
                            <?php if ($member_photo) : ?>
                                <div class="modal-member-photo">
                                    <img src="<?php echo esc_url($member_photo); ?>" alt="<?php echo esc_attr($member_name); ?>">
                                </div>
                            <?php endif; ?>
                            
                            <div class="modal-member-info">
                                <?php if ($member_name) : ?>
                                    <h3 class="modal-member-name"><?php echo esc_html($member_name); ?></h3>
                                <?php endif; ?>
                                
                                <?php if ($member_role) : ?>
                                    <p class="modal-member-role"><?php echo esc_html($member_role); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($member_description) : ?>
                                    <div class="modal-member-description">
                                        <?php echo wp_kses_post($member_description); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php endwhile; ?>
        </div>
    </section>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get all team member cards
        const teamCards = document.querySelectorAll('.team-member-card');
        const teamModals = document.querySelectorAll('.team-modal');
        const closeButtons = document.querySelectorAll('.close-modal');
        
        // Add click event to each team card
        teamCards.forEach(card => {
            card.addEventListener('click', function() {
                const modalId = this.getAttribute('data-modal-target');
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.style.display = 'block';
                    document.body.classList.add('modal-open');
                }
            });
        });
        
        // Add click event to close buttons
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const modal = this.closest('.team-modal');
                if (modal) {
                    modal.style.display = 'none';
                    document.body.classList.remove('modal-open');
                }
            });
        });
        
        // Close modal when clicking outside the content
        window.addEventListener('click', function(event) {
            teamModals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                    document.body.classList.remove('modal-open');
                }
            });
        });
    });
    </script>
    
    <?php wp_reset_postdata();
else : ?>
    <section id="our-team" class="team-section">
        <h2 class="section-title">Our Team</h2>
        <p class="no-team-members">No team members found.</p>
    </section>
<?php endif; ?> 