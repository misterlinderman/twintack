<?php
/**
 * The template for displaying the footer
 *
 * @package twintack2025
 */
?>

	<footer id="colophon" class="site-footer">
		<div class="footer-container">
			<!-- Logo and About Column -->
			<div class="footer-column footer-brand">
				<?php if (function_exists('custom_logo_svg')) : ?>
					<div class="footer-logo"><?php echo custom_logo_svg(); ?></div>
				<?php endif; ?>
				<div class="footer-about">
					<p>TwinTack is revolutionizing grip technology for baseball and fishing, providing innovative solutions for athletes and outdoor enthusiasts.</p>
				</div>
				<div class="social-links">
					<a href="https://www.facebook.com/profile.php?id=100095162121521" target="_blank" aria-label="Facebook"><svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M18.77,7.46H14.5v-1.9c0-.9.6-1.1,1-1.1h3V.5L14.17.5C10.09.5,9.14,3.25,9.14,6.06V7.46H6v4H9.14V21.5h5.36V11.46h3.55l.52-4Z"/></svg></a>
					<a href="https://www.instagram.com/twintackgrips/" target="_blank" aria-label="Instagram"><svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M12,2.16c3.2,0,3.58,0,4.85.07,3.25.15,4.77,1.7,4.92,4.92.06,1.27.07,1.65.07,4.85s0,3.58-.07,4.85c-.15,3.23-1.66,4.77-4.92,4.92-1.27.06-1.65.07-4.85.07s-3.58,0-4.85-.07c-3.26-.15-4.77-1.7-4.92-4.92-.06-1.27-.07-1.65-.07-4.85s0-3.58.07-4.85C2.38,3.92,3.9,2.38,7.15,2.23,8.42,2.18,8.8,2.16,12,2.16ZM12,0C8.74,0,8.33,0,7.05.07c-4.35.2-6.78,2.62-6.98,6.98C0,8.33,0,8.74,0,12S0,15.67.07,17c.2,4.36,2.63,6.78,6.98,6.98C8.33,24,8.74,24,12,24s3.67,0,4.95-.07c4.35-.2,6.78-2.62,7-6.98C24,15.67,24,15.26,24,12s0-3.67-.07-4.95c-.2-4.35-2.65-6.78-7-6.98C15.67,0,15.26,0,12,0Zm0,5.84A6.16,6.16,0,1,0,18.16,12,6.16,6.16,0,0,0,12,5.84ZM12,16a4,4,0,1,1,4-4A4,4,0,0,1,12,16ZM18.41,4.15a1.44,1.44,0,1,0,1.43,1.44A1.44,1.44,0,0,0,18.41,4.15Z"/></svg></a>
					<a href="https://x.com/TwinTackGrips" target="_blank" aria-label="Twitter"><svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M22,5.8a8.6,8.6,0,0,1-2.36.65,4.07,4.07,0,0,0,1.8-2.27,8.1,8.1,0,0,1-2.6,1A4.1,4.1,0,0,0,11.75,8a4.73,4.73,0,0,0,.1.93A11.6,11.6,0,0,1,3.39,4.62,4.2,4.2,0,0,0,2.83,6.7a4.09,4.09,0,0,0,1.82,3.4A4,4,0,0,1,2.8,9.6v.05a4.11,4.11,0,0,0,3.29,4A4.68,4.68,0,0,1,5,13.81a4.09,4.09,0,0,0,3.83,2.84A8.22,8.22,0,0,1,3,18.34a11.57,11.57,0,0,0,6.29,1.85A11.59,11.59,0,0,0,21,8.45c0-.17,0-.35,0-.53A8.43,8.43,0,0,0,22,5.8Z"/></svg></a>
				</div>
			</div>

			<!-- Products Column -->
			<div class="footer-column">
				<h3>Products</h3>
				<nav class="footer-nav">
					<a href="/twintack-custom-grips/">Custom Grips</a>
					<a href="/shop/">Shop All</a>
					<a href="/shop/?product_cat=baseball">Bat Grips</a>
					<a href="/shop/?product_cat=fishing">Fishing Grips</a>
					<a href="/shop/?product_cat=accessory">Accessories</a>
				</nav>
			</div>

			<!-- Company Column -->
			<div class="footer-column">
				<h3>Company</h3>
				<nav class="footer-nav">
					<a href="/twintack/">TwinTack</a>
					<a href="/twintack/#technology">Technology</a>
					<a href="/twintack/#ourstory">Our Story</a>
					<a href="/contact/">Contact</a>
					<a href="/affiliates">Affiliate Program</a>
				</nav>
			</div>

			<!-- My Account Column -->
			<div class="footer-column footer-myaccount">
				<h3>My Account</h3>
				<nav class="footer-nav">
					<?php if ( is_user_logged_in() ) : ?>
						<a href="/my-account">Dashboard</a>
						<a href="/my-account/my-custom-grips/">My Custom Grips</a>
						<a href="/my-account/orders/">Orders</a>
						<a href="/my-account/edit-address/">Addresses</a>
						<a href="/my-account/edit-account/">Account Details</a>
						                                              <a href="<?php echo esc_url( twintack_get_logout_url() ); ?>" rel="nofollow" data-no-prefetch data-no-instant>Log out</a>
					<?php else : ?>
						<a href="/login">Login/Register</a>
					<?php endif; ?>
				</nav>
			</div>

			<!-- Newsletter Column -->
			<div class="footer-column">
				<h3>Newsletter</h3>
				<div class="newsletter-form">
					<p>Stay updated with our latest products, news and special offers.</p>
					<!-- Klaviyo Embedded Form -->
					<div class="klaviyo-form-wrapper" id="klaviyo-form-wrapper">
						<form id="email_signup" class="form-group klaviyo-newsletter-form" action="https://manage.kmail-lists.com/subscriptions/subscribe" data-ajax-submit="https://manage.kmail-lists.com/ajax/subscriptions/subscribe" method="GET" target="_blank" novalidate="novalidate">
							<input type="hidden" name="g" value="<?php 
							// Fallback in case function doesn't exist
							if (function_exists('twintack_get_klaviyo_data')) {
								echo esc_attr(twintack_get_klaviyo_data()['listId']);
							} else {
								echo 'UXnNZg'; // Default list ID
							}
							?>">
							<input type="email" name="email" id="k_id_email" placeholder="Your email address" required aria-label="Email">
							<button type="submit" name="klaviyo_submit" id="klaviyo_submit">Subscribe</button>
						</form>
						<div class="klaviyo-form-message"></div>
					</div>
				</div>
			</div>
		</div>

		<div class="site-info footer-bottom">
			<p class="copyright">&copy; <?php echo date('Y'); ?> TwinTack. All rights reserved.</p>
			<nav class="footer-policies">
				<a href="/terms-of-service">Terms of Service</a>
				<a href="/privacy-policy">Privacy Policy</a>
				<a href="/refund_returns">Refund & Returns</a>
			</nav>
		</div>
	</footer><!-- #colophon -->
</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
