<?php
/**
 * Template Name: Custom Grip Introduction
 * 
 * Custom template for the custom grip introduction page that explains
 * the customer journey and process for ordering custom grips.
 *
 * @package twintack2025
 */

get_header();
?>

<div class="custom-grip-intro-wrapper">
    <main class="main">
        <?php
        while ( have_posts() ) :
            the_post();
            ?>
            
            <!-- Hero Section -->
            <section class="hero-section">
                <div class="container">
                    <h1 class="hero-title">Custom Grip Process</h1>
                    <p class="hero-subtitle">
                        Create professional custom grips for your team with our streamlined design process. 
                        From concept to completion in just a few simple steps.
                    </p>
                </div>
            </section>

            <!-- Process Steps -->
            <section class="process-section">
                <div class="container">
                    <h2 class="section-title">How It Works</h2>
                    
                    <div class="process-steps">
                        
                        <!-- Step 1: Create Account -->
                        <div class="process-step">
                            <div class="step-visual">
                                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="50" cy="30" r="12" fill="none" stroke="currentColor" stroke-width="3"/>
                                    <path d="M25 75c0-13.8 11.2-25 25-25s25 11.2 25 25" fill="none" stroke="currentColor" stroke-width="3"/>
                                    <circle cx="75" cy="25" r="15" fill="currentColor"/>
                                    <line x1="69" y1="25" x2="81" y2="25" stroke="#000" stroke-width="2"/>
                                    <line x1="75" y1="19" x2="75" y2="31" stroke="#000" stroke-width="2"/>
                                </svg>
                            </div>
                            <div class="step-info">
                                <div class="step-header">
                                    <div class="step-number">1</div>
                                    <h3 class="step-title">Create Account</h3>
                                </div>
                                <p class="step-description">
                                    Start by creating your account or logging in to access our custom grip design system.
                                </p>
                                <div class="step-action">
                                    <?php if ( !is_user_logged_in() ) : ?>
                                        <a href="<?php echo esc_url( wp_registration_url() ); ?>" class="btn">Create Account</a>
                                    <?php else : ?>
                                        <div class="step-status">✓ Account Ready</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Design Your Grip -->
                        <div class="process-step">
                            <div class="step-visual">
                                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="20" y="30" width="60" height="40" rx="5" fill="none" stroke="currentColor" stroke-width="3"/>
                                    <circle cx="35" cy="45" r="8" fill="none" stroke="currentColor" stroke-width="2"/>
                                    <rect x="50" y="40" width="25" height="3" fill="currentColor"/>
                                    <rect x="50" y="47" width="20" height="3" fill="currentColor"/>
                                    <rect x="50" y="54" width="15" height="3" fill="currentColor"/>
                                    <line x1="15" y1="85" x2="35" y2="65" stroke="currentColor" stroke-width="3"/>
                                    <polygon points="15,85 20,80 25,85 20,90" fill="currentColor"/>
                                </svg>
                            </div>
                            <div class="step-info">
                                <div class="step-header">
                                    <div class="step-number">2</div>
                                    <h3 class="step-title">Design Your Grip</h3>
                                </div>
                                <p class="step-description">
                                    Select your pattern, choose colors, and upload your team logo or artwork. Our intuitive design tool makes it easy to create the perfect grip.
                                </p>
                                <div class="step-action">
                                    <a href="/twintack-custom-grips/" class="btn btn-secondary">Start Designing</a>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Pay Design Fee -->
                        <div class="process-step">
                            <div class="step-visual">
                                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="15" y="35" width="70" height="45" rx="8" fill="none" stroke="currentColor" stroke-width="3"/>
                                    <rect x="15" y="45" width="70" height="8" fill="currentColor"/>
                                    <rect x="22" y="60" width="15" height="4" fill="currentColor"/>
                                    <rect x="22" y="67" width="25" height="4" fill="currentColor"/>
                                    <circle cx="65" cy="25" r="15" fill="currentColor"/>
                                    <text x="65" y="31" text-anchor="middle" fill="#000" font-size="16" font-weight="bold">$</text>
                                </svg>
                            </div>
                            <div class="step-info">
                                <div class="step-header">
                                    <div class="step-number">3</div>
                                    <h3 class="step-title">Pay Design Fee</h3>
                                </div>
                                <p class="step-description">
                                    Complete your design with a one-time setup fee. This covers our design team's time to create professional mockups of your custom grip.
                                </p>
                                <div class="step-action">
                                    <div class="step-highlight">$50 Design Fee</div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 4: Review Mockups -->
                        <div class="process-step">
                            <div class="step-visual">
                                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                                    <ellipse cx="50" cy="50" rx="35" ry="20" fill="none" stroke="currentColor" stroke-width="3"/>
                                    <circle cx="50" cy="50" r="12" fill="none" stroke="currentColor" stroke-width="3"/>
                                    <circle cx="50" cy="50" r="6" fill="currentColor"/>
                                    <circle cx="75" cy="25" r="15" fill="currentColor"/>
                                    <polyline points="69,25 73,29 81,21" stroke="#000" stroke-width="3" fill="none"/>
                                </svg>
                            </div>
                            <div class="step-info">
                                <div class="step-header">
                                    <div class="step-number">4</div>
                                    <h3 class="step-title">Review Mockups</h3>
                                </div>
                                <p class="step-description">
                                    Our internal art team will create professional mockups of your design. Review and approve before we move to production.
                                </p>
                                <div class="step-action">
                                    <div class="step-timeline">2-3 business days</div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 5: Purchase Your Grips -->
                        <div class="process-step">
                            <div class="step-visual">
                                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M20 25h10l8 32h32l6-20H35" fill="none" stroke="currentColor" stroke-width="3"/>
                                    <circle cx="45" cy="70" r="5" fill="currentColor"/>
                                    <circle cx="65" cy="70" r="5" fill="currentColor"/>
                                    <rect x="35" y="35" width="8" height="25" rx="4" fill="none" stroke="currentColor" stroke-width="2"/>
                                    <line x1="37" y1="40" x2="41" y2="40" stroke="currentColor" stroke-width="1"/>
                                    <line x1="37" y1="45" x2="41" y2="45" stroke="currentColor" stroke-width="1"/>
                                    <line x1="37" y1="50" x2="41" y2="50" stroke="currentColor" stroke-width="1"/>
                                    <line x1="37" y1="55" x2="41" y2="55" stroke="currentColor" stroke-width="1"/>
                                </svg>
                            </div>
                            <div class="step-info">
                                <div class="step-header">
                                    <div class="step-number">5</div>
                                    <h3 class="step-title">Purchase Your Grips</h3>
                                </div>
                                <p class="step-description">
                                    Once approved, purchase your grips in quantity breaks of 25. Production begins immediately with a 2-week lead time.
                                </p>
                                <div class="step-action">
                                    <div class="step-timeline">2-week lead time</div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </section>

            <!-- Info Cards -->
            <section class="info-section">
                <div class="container">
                    <h2 class="section-title">Important Details</h2>
                    
                    <div class="info-cards">
                        <div class="info-card">
                            <div class="info-icon">💰</div>
                            <h3>Design Fee</h3>
                            <p>One-time $50 setup fee covers professional mockup creation and design review by our art team.</p>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-icon">📦</div>
                            <h3>Quantity Breaks</h3>
                            <p>Grips are sold in quantity breaks of 25. Perfect for teams, leagues, or bulk orders.</p>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-icon">⏰</div>
                            <h3>Lead Time</h3>
                            <p>2-week production lead time from approval to shipping. Rush orders available upon request.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Call to Action -->
            <section class="cta-section">
                <div class="container">
                    <h2 class="cta-title">Ready to Get Started?</h2>
                    <p class="cta-subtitle">Join thousands of teams who trust TwinTack for their custom grip needs.</p>
                    <?php if ( !is_user_logged_in() ) : ?>
                        <a href="<?php echo esc_url( wp_registration_url() ); ?>" class="cta-button">Create Account & Start Designing</a>
                    <?php else : ?>
                        <a href="/twintack-custom-grips/" class="cta-button">Start Designing Your Grips</a>
                    <?php endif; ?>
                </div>
            </section>

        <?php
        endwhile;
        ?>
    </main>
</div>

<?php
get_footer();
