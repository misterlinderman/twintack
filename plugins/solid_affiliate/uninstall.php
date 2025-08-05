<?php

/**
 * Uninstall Solid Affiliate
 *
 * @package     Solid Affiliate
 * @subpackage  Uninstall
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

use SolidAffiliate\Addons\Core;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\Roles;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\SolidSearch;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliateCustomerLink;
use SolidAffiliate\Models\AffiliateGroup;
use SolidAffiliate\Models\AffiliateMeta;
use SolidAffiliate\Models\AffiliateProductRate;
use SolidAffiliate\Models\BulkPayout;
use SolidAffiliate\Models\Creative;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\StoreCreditTransaction;
use SolidAffiliate\Models\Visit;

// Exit if accessed directly
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

// Load Solid Affiliate
include_once('plugin.php');

if ((bool)Settings::get(Settings::KEY_IS_REMOVE_DATA_ON_UNINSTALL)) {
    // [] remove Custom Tables
    global $wpdb;

    $tables = [
        Affiliate::TABLE,
        AffiliateGroup::TABLE,
        Referral::TABLE,
        Payout::TABLE,
        Visit::TABLE,
        Creative::TABLE,
        BulkPayout::TABLE,
        AffiliateProductRate::TABLE,
        AffiliateMeta::TABLE,
        StoreCreditTransaction::TABLE,
        AffiliateCustomerLink::TABLE
    ];
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
    };

    // [] Custom roles/capabilities
    Roles::remove_affiliate_related_custom_roles();

    // [] Anything in wp_options
    delete_option(Settings::OPTIONS_KEY);
    delete_option(Core::OPTIONS_KEY_ENABLED_ADDONS);
    delete_option('solid_navigator_pages_db');
    delete_option('solid_navigator_pages_db_last_update');
    delete_option(SolidSearch::OPTION_KEY_SEARCH_QUERY_COUNT);
    delete_option('solid_affiliate_past_custom_affiliate_registration_names_and_types');

    // TODO WooCommerce Integration cleanup
    // WooCommerceIntegration::MISC -> delete all these post metas by the keys
    
    // TODO all options that starts with Core::OPTIONS_KEY_PREFIX
}
