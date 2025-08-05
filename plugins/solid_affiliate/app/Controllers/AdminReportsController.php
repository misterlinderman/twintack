<?php

namespace SolidAffiliate\Controllers;


use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\ControllerFunctions;
use SolidAffiliate\Lib\GlobalTypes;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;

use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Views\Admin\Reports\RootView;

/**
 * AdminReportsController
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
class AdminReportsController
{
    const ADMIN_PAGE_KEY = 'solid-affiliate-reports';
    const PARAM_KEY_SUBMIT_ADMIN_REPORTS_FILTERS = 'submit_admin_reports_filters';
    const NONCE_SUBMIT_ADMIN_REPORTS_FILTERS = 'solid-affiliate-admin-reports-filters';

    /**
     * @return Schema<"affiliate_id"|"date_range_preset"|"end_date"|"start_date">
     */
    public static function reports_filters_schema()
    {
        $entries = [
            'date_range_preset' => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'required' => true,
                'display_name' => __('Date Range Preset', 'solid-affiliate'),
                'is_enum' => true,
                'enum_options' => GlobalTypes::translated_DATE_RANGE_ENUM_OPTIONS(),
                'show_on_new_form' => true,
                'form_input_description' => __('Date Range Preset', 'solid-affiliate')
            )),
            'start_date' => new SchemaEntry(array(
                'type' => 'datetime',
                'display_name' => __('Start Date', 'solid-affiliate'),
                'show_on_new_form' => true,
                'form_input_description' => __('Start date filter range.', 'solid-affiliate')
            )),
            'end_date' => new SchemaEntry(array(
                'type' => 'datetime',
                'display_name' => __('End Date', 'solid-affiliate'),
                'show_on_new_form' => true,
                'form_input_description' => __('End date for filter range.', 'solid-affiliate')
            )),
            'affiliate_id' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'required' => false,
                'display_name' => __('Affiliate ID', 'solid-affiliate'),
                'show_on_new_form' => true,
                'form_input_description' => __('Affiliate ID for filter', 'solid-affiliate'),
                'form_input_type_override' => 'affiliate_select',
                'hide_form_description' => true
            ]),
        ];

        return new Schema(['entries' => $entries]);
    }

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
                AdminReportsController::index();
                break;
            default:
                AdminReportsController::index();
                break;
        }
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public static function index()
    {
        $date_range = isset($_GET['date_range']) ? (string)$_GET['date_range'] : 'this_month';
        $start_date = isset($_GET['start_date']) ? (string)$_GET['start_date'] : '';
        $end_date = isset($_GET['end_date']) ? (string)$_GET['end_date'] : '';

        $preset_date_range_params = new PresetDateRangeParams([
            'preset_date_range' => Validators::one_of_date_range_options($date_range),
            'start_date' => $start_date,
            'end_date' => $end_date
        ]);
        $affiliate_id = isset($_GET['affiliate_id']) ? (int)$_GET['affiliate_id'] : 0;

        $o = RootView::render($preset_date_range_params, $affiliate_id);
        echo $o;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public static function POST_admin_reports_filters()
    {
        $filter_form_schema = AdminReportsController::reports_filters_schema();

        ControllerFunctions::enforce_current_user_capabilities(['read']);

        $eitherFields = ControllerFunctions::extract_and_validate_POST_params(
            $_POST,
            SchemaFunctions::keys_on_new_form_from_schema($filter_form_schema),
            $filter_form_schema
        );

        if ($eitherFields->isLeft) {
            ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', $eitherFields->left, [], 'admin');
        }

        /////////////////// Do stuff
        $args_to_add = [
            'date_range' => (string)$eitherFields->right['date_range_preset'],
            'start_date' => (string)$eitherFields->right['start_date'],
            'end_date' => (string)$eitherFields->right['end_date'],
            'affiliate_id' => (int)$eitherFields->right['affiliate_id'],
        ];
        $admin_bulk_payout_preview_path = 'REDIRECT_BACK'; // LUCA TODO paths? (show how rails handles it)
        ControllerFunctions::handle_redirecting_and_exit($admin_bulk_payout_preview_path, [], [], 'admin', $args_to_add);
    }
}
