<?php
/**
 * My Account Dashboard
 *
 * Shows the first intro screen on the account dashboard.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/dashboard.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 4.4.0
 */

defined( 'ABSPATH' ) || exit;
?>

<style>
.dashboard-welcome {
    background: var(--color-accent);
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    box-shadow: none;
}

.dashboard-welcome h1 {
    margin: 0;
    font-size: 1.8rem;
    color: var(--color-text);
}

.dashboard-welcome p {
    margin: 0.5rem 0 0;
    color: var(--color-text);
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
    margin-bottom: 3rem;
}

.dashboard-card {
    background: var(--color-accent);
    border: 1px solid var(--color-accent);
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.3s ease;
    box-shadow: none;
    display: flex;
    flex-direction: column;
    text-decoration: none !important;
}

.dashboard-card:hover {
    transform: translateY(-5px);
    border-color: #ddd;
}

.dashboard-card-icon {
    width: 48px;
    height: 48px;
    background: var(--color-text);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
}

.dashboard-card-icon svg {
    width: 24px;
    height: 24px;
    fill: var(--color-accent);
}

.dashboard-card h2 {
    margin: 0 0 0.5rem;
    font-size: 1.5rem;
    color: var(--color-text);
}

.dashboard-card p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
    flex-grow: 1;
}

.dashboard-card-footer {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #eee;
    font-size: 0.9rem;
    color: var(--color-text);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .dashboard-welcome {
        padding: 1.5rem;
    }
}
</style>
</style>

<div class="dashboard-welcome">
    <h1><?php printf( esc_html__( 'Hello %1$s', 'woocommerce' ), esc_html( $current_user->display_name ) ); ?></h1>
    <p><?php _e('Welcome to your account dashboard! Here you can manage all your account activities.', 'twintack2025'); ?></p>
</div>

<div class="dashboard-grid">
    <?php if (current_user_can('wholesale_customer')) : ?>
    <!-- Wholesale Orders Card -->
    <a href="<?php echo esc_url(wc_get_account_endpoint_url('wholesale-orderforms')); ?>" class="dashboard-card">
        <div class="dashboard-card-icon">
            <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.11 0-2 .89-2 2v14c0 1.11.89 2 2 2h14c1.11 0 2-.89 2-2V5c0-1.11-.89-2-2-2zm0 16H5V5h14v14zm-7-2h2v-4h4v-2h-4V7h-2v4H8v2h4z"/></svg>
        </div>
        <h2><?php _e('Wholesale Orders', 'twintack2025'); ?></h2>
        <p><?php _e('Access your wholesale order forms and place bulk orders.', 'twintack2025'); ?></p>
        <div class="dashboard-card-footer"><?php _e('View Order Forms →', 'twintack2025'); ?></div>
    </a>
    <?php endif; ?>

    <!-- Orders Card -->
    <a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>" class="dashboard-card">
        <div class="dashboard-card-icon">
            <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.11 0-2 .89-2 2v14c0 1.11.89 2 2 2h14c1.11 0 2-.89 2-2V5c0-1.11-.89-2-2-2zm0 16H5V5h14v14zM7 10h2v7H7zm4-3h2v10h-2zm4 6h2v4h-2z"/></svg>
        </div>
        <h2><?php _e('Orders', 'twintack2025'); ?></h2>
        <p><?php _e('View and track your order history and check order statuses.', 'twintack2025'); ?></p>
        <div class="dashboard-card-footer"><?php _e('View Orders →', 'twintack2025'); ?></div>
    </a>

    <!-- Custom Grips Card (TwinTack Custom Grips — my-custom-grips endpoint) -->
    <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'my-custom-grips' ) ); ?>" class="dashboard-card">
        <div class="dashboard-card-icon">
            <svg viewBox="0 0 24 24"><path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9 9-4.03 9-9-4.03-9-9-9zm0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7 7 3.14 7 7-3.14 7-7 7zm1-11h-2v3H8v2h3v3h2v-3h3v-2h-3z"/></svg>
        </div>
        <h2><?php _e( 'My Custom Grips', 'twintack2025' ); ?></h2>
        <p><?php _e( 'View and manage your custom grip designs, mockups, and approvals.', 'twintack2025' ); ?></p>
        <div class="dashboard-card-footer"><?php _e( 'View Custom Grips →', 'twintack2025' ); ?></div>
    </a>

    <!-- Account Details Card -->
    <a href="<?php echo esc_url(wc_get_account_endpoint_url('edit-account')); ?>" class="dashboard-card">
        <div class="dashboard-card-icon">
            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
        </div>
        <h2><?php _e('Account Details', 'twintack2025'); ?></h2>
        <p><?php _e('Update your account information and password.', 'twintack2025'); ?></p>
        <div class="dashboard-card-footer"><?php _e('Edit Details →', 'twintack2025'); ?></div>
    </a>

    <!-- Addresses Card -->
    <a href="<?php echo esc_url(wc_get_account_endpoint_url('edit-address')); ?>" class="dashboard-card">
        <div class="dashboard-card-icon">
            <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
        </div>
        <h2><?php _e('Addresses', 'twintack2025'); ?></h2>
        <p><?php _e('Manage your shipping and billing addresses.', 'twintack2025'); ?></p>
        <div class="dashboard-card-footer"><?php _e('Manage Addresses →', 'twintack2025'); ?></div>
    </a>
</div>

<?php
	/**
	 * My Account dashboard.
	 *
	 * @since 2.6.0
	 */
	do_action( 'woocommerce_account_dashboard' );

	/**
	 * Deprecated woocommerce_before_my_account action.
	 *
	 * @deprecated 2.6.0
	 */
	do_action( 'woocommerce_before_my_account' );

	/**
	 * Deprecated woocommerce_after_my_account action.
	 *
	 * @deprecated 2.6.0
	 */
	do_action( 'woocommerce_after_my_account' );
?>
