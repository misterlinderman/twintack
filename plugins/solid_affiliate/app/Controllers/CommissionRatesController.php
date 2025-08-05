<?php

namespace SolidAffiliate\Controllers;


use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\ControllerFunctions;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Views\Admin\CommissionRates\RootView as CommissionRatesRootView;
use SolidAffiliate\Views\Admin\Settings\RootView;
use SolidAffiliate\Views\Shared\AdminHeader;
use SolidAffiliate\Views\Shared\SimpleTableView;

/**
 * CommissionRatesController
 * 
 * @psalm-suppress PropertyNotSetInConstructor
 */
class CommissionRatesController
{
    const ADMIN_PAGE_KEY = 'solid-affiliate-commission-rates';
    const POST_PARAM_SUBMIT_SETTINGS = 'submit_admin_settings';
    const NONCE_SUBMIT_SETTINGS = 'solid-affiliate-admin-settings-update';

    const SUCCESS_MSG_UPDATE_SETTINGS = 'Successfully updated Settings.';
    /**
     * @since 1.0.0
     *
     *
     * @return void
     */
    public static function admin_root()
    {
        $action = isset($_GET["action"]) ? (string) $_GET["action"] : "index";

        switch ($action) {
            case "index":
                CommissionRatesController::index();
                break;
            default:
                CommissionRatesController::index();
                break;
        }
    }

    /**
     * Renders our entire Admin Settings page.
     *
     * @return void
     */
    public static function index()
    {
        echo (CommissionRatesRootView::render());
    }
}
