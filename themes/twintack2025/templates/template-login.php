<?php
/**
 * Template Name: Unified Login Page
 *
 * A custom template for the unified login experience.
 *
 * @package twintack2025
 */

get_header();

// Redirect logic has been moved to page-login.php
// This prevents duplicate redirects that could cause loops
?>

<div class="twintack-login-page">
    <div class="container">
        <div class="row">
            <div class="col-md-6 offset-md-3">
                <div class="twintack-login-container">
                    <div class="twintack-login-tabs">
                        <ul class="nav nav-tabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login-tab-pane" type="button" role="tab" aria-controls="login-tab-pane" aria-selected="true">
                                    <?php esc_html_e( 'Login', 'twintack2025' ); ?>
                                </button>
                            </li>
                            <?php if ( 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) ) : ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register-tab-pane" type="button" role="tab" aria-controls="register-tab-pane" aria-selected="false">
                                        <?php esc_html_e( 'Register', 'twintack2025' ); ?>
                                    </button>
                                </li>
                            <?php endif; ?>
                        </ul>
                        
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="login-tab-pane" role="tabpanel" aria-labelledby="login-tab" tabindex="0">
                                <?php get_template_part( 'template-parts/account/login-form' ); ?>
                            </div>
                            
                            <?php if ( 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) ) : ?>
                                <div class="tab-pane fade" id="register-tab-pane" role="tabpanel" aria-labelledby="register-tab" tabindex="0">
                                    <?php get_template_part( 'template-parts/account/register-form' ); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
get_footer(); 