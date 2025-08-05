<?php

namespace SolidAffiliate;

use SolidAffiliate\Controllers\AdminDashboardController;
use SolidAffiliate\Controllers\AdminMenuController;
use SolidAffiliate\Controllers\POSTRequestController;
use SolidAffiliate\Controllers\SetupWizardController;
use SolidAffiliate\Controllers\SolidController;
use SolidAffiliate\Lib\CustomAffiliateSlugs\AffiliateCustomSlugBase;
use SolidAffiliate\Lib\ListTables\SharedListTableFunctions;
use SolidAffiliate\Lib\Logger;
use SolidAffiliate\Lib\Misc;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\SolidLogger;
use SolidAffiliate\Lib\Tutorials;
use SolidAffiliate\Models\AffiliateCustomerLink;
use SolidAffiliate\Models\AffiliateGroup;
use SolidAffiliate\Models\AffiliateMeta;

/**
 * Main class.
 * Bridge between WordPress and the Plugin
 * @psalm-suppress PropertyNotSetInConstructor
 */
class Main
{
    /**
     * Registers hooks with WordPress.
     *
     * @return void
     */
    public static function register_hooks()
    {
        // add_action('wp_loaded', [\SolidAffiliate\Addons\Core::class, 'register_hooks_for_all_addons'], 9);
        add_action('wp_loaded', [POSTRequestController::class, 'route_post_request_to_callback_function']);
        add_action('solid_affiliate/log', [Logger::class, 'log'], 10, 4);
        add_action('deleted_user', [Misc::class, 'handle_deleted_user'], 10, 3);
        add_action('plugin_action_links_' . plugin_basename(SOLID_AFFILIATE_FILE), [Misc::class, 'solid_affiliate_plugin_action_links'], 10, 3);

        if (!is_admin()) {
            add_action('wp', [SolidController::class, 'visit_tracking']);
        }

        if (is_admin()) {
            add_action('admin_menu', [AdminMenuController::class, 'init']);
            add_action('admin_init', [Settings::class, 'register_hooks']);
            add_action('admin_init', [AffiliateGroup::class, 'maybe_create_default_affiliate_group']);
            add_action('admin_init', [AdminMenuController::class, 'admin_wp_list_table_search_and_filter_handler']);
            add_action('admin_init', [AffiliateCustomSlugBase::class, 'register_admin_hooks']);
            add_action('admin_init', [AffiliateMeta::class, 'register_affiliate_edit_hooks']);
            add_action('wp_dashboard_setup', [AdminDashboardController::class, 'solid_affiliate_add_dashboard_widgets']);
            add_action('add_meta_boxes', [AdminDashboardController::class, 'solid_affiliate_add_admin_dashboard_meta_boxes']);
            add_action('add_meta_boxes', [Tutorials::class, 'add_meta_boxes']);
            add_filter('default_hidden_columns', [SharedListTableFunctions::class, 'handle_default_hidden_columns'], 10, 2);
            add_filter('admin_footer_text', [AdminMenuController::class, 'admin_footer_hook_callback']);
            


            // Auto generated crud tables TODO - where do initiate things from? The goal is to be declarative.
            AffiliateCustomerLink::register_admin_crud_resource();
        }
    }
}
