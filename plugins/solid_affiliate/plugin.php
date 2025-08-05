<?php
/*
* Plugin Name: Solid Affiliate
* Version: 3.1.0
* Description: Adds an Affiliate Platform to your WordPress store.
* Author: Solid Plugins
* Author URI: https://www.solidaffiliate.com
* Text Domain: solid-affiliate
* Domain Path: /assets/lang
* Requires at least: 5.2
* Requires PHP:      7.3.8
*/

use SolidAffiliate\Controllers\AffiliatePortalController;
use SolidAffiliate\Controllers\CreativesController;
use SolidAffiliate\Lib\AjaxHandler;
use SolidAffiliate\Lib\Roles;
use SolidAffiliate\Lib\SetupWizard;
use SolidAffiliate\Lib\ShortCodes;
use SolidAffiliate\Lib\Translation;

/**
 * @return void
 */
function solid_affiliate_deactivate_plugin()
{
	deactivate_plugins(plugin_basename(__FILE__));
	if (!empty($_GET['activate'])) {
		unset($_GET['activate']);
	}
}

// Check for minimum supported WP version
if (version_compare(get_bloginfo('version'), '5.2', '<')) {
	add_action('admin_notices', function () {
		echo '<div class="error"><p>Solid Affiliate requires WordPress 5.2 or higher. Please upgrade your WordPress installation.</p></div>';
	});
	// deactivate the plugin
	add_action('admin_init', 'solid_affiliate_deactivate_plugin');
	return;
}

// Check for minimum supported PHP version
if (version_compare(phpversion(), '7.3.8', '<')) {
	add_action('admin_notices', function () {
		echo '<div class="error"><p>Solid Affiliate requires PHP 7.4 or higher. Please upgrade your PHP installation.</p></div>';
	});
	// deactivate the plugin
	add_action('admin_init', 'solid_affiliate_deactivate_plugin');
	return;
}

// Define this plugin's directory and filepath as a constant.
define('SOLID_AFFILIATE_DIR', __DIR__);
define('SOLID_AFFILIATE_FILE', __FILE__);

// Check if we've already autoloaded all our code, if not, load it.
if (!class_exists(\SolidAffiliate\Main::class)) {
	/** @psalm-suppress UnresolvableInclude */
	require_once(SOLID_AFFILIATE_DIR . '/vendor/autoload.php');
}


class SolidAffiliate
{
	/**
	 * As WordPress reads this file for the first time, this function is called directly.
	 * 
	 * - stuff we're pretty sure we need to run at the start (License?; Custom Tables; Action Scheduler)
	 * - register_activation_hook
	 * - things that add action on 'init'
	 *
	 * @return void
	 */
	public static function pre_init()
	{
		\SolidAffiliate\Lib\Roles::register_affiliate_manager_role();
		\SolidAffiliate\Lib\SolidLogger::add_hooks();
		\SolidAffiliate\Lib\License::init(SOLID_AFFILIATE_FILE);
		\SolidAffiliate\Lib\CustomTables::register_hooks();

		\SolidAffiliate\Lib\Action_Scheduler::load();

		register_activation_hook(SOLID_AFFILIATE_FILE, [\SolidAffiliate\Lib\Activation::class, 'activation_hook']);

		add_action('plugins_loaded', [SetupWizard::class, 'initial_redirect_to_setup_upon_activation'], 999);
		add_action('init', [SolidAffiliate::class, 'on_init'], 0);
	}

	/**
	 * Undocumented function
	 * 
	 * @see https://developer.wordpress.org/reference/hooks/init/ init
	 *
	 * @return void
	 */
	public static function on_init()
	{
		\SolidAffiliate\Lib\DBMigration::run();

		\SolidAffiliate\Addons\Core::register_hooks_for_all_addons(); // IMPORTANT leave this addons one at the top
		\SolidAffiliate\Lib\Email_Notifications::register_hooks();
		\SolidAffiliate\Controllers\SetupWizardController::register_hooks();

		// Register the primary Solid Affiliate hooks.
		\SolidAffiliate\Main::register_hooks();
		\SolidAffiliate\Assets::register_hooks();

		\SolidAffiliate\Lib\FF::register_hooks();
		///////////////////////////////////////////////////////////

		// Register hooks of other Solid Affiliate modules which
		// are decoupled enough to have their own register_hooks() function.
		\SolidAffiliate\Lib\Roles::register_hooks();
		\SolidAffiliate\Lib\Tags::register_hooks();
		\SolidAffiliate\Lib\Notices::register_hooks();
		\SolidAffiliate\Lib\Extensions\WPUserEditExtension::register_hooks();
		\SolidAffiliate\Lib\DevHelpers::register_hooks();
		\SolidAffiliate\Models\AffiliatePortal::register_hooks();
		\SolidAffiliate\Lib\CustomAffiliateSlugs\AffiliateCustomSlugBase::register_hooks();
		\SolidAffiliate\Lib\LifetimeCommissions::register_hooks();
		///////////////////////////////////////////////////////////

		// Core Integrations
		\SolidAffiliate\Lib\Integrations\WooCommerceIntegration::register_hooks();
		\SolidAffiliate\Lib\Integrations\WooCommerceSubscriptionsIntegration::register_hooks();
		\SolidAffiliate\Lib\Integrations\MailChimpIntegration::register_hooks();

		//////////////////////////////////////////////////////////
		ShortCodes::register_shortcodes();
		AffiliatePortalController::register_shortcodes();
		CreativesController::register_shortcodes();
		Roles::register_affiliate_related_custom_roles();
		AjaxHandler::register_all_ajax_hooks();
		AjaxHandler::register_all_ajax_nopriv_hooks();
		Translation::solid_affiliate_load_plugin_textdomain();
	}
}

SolidAffiliate::pre_init();
