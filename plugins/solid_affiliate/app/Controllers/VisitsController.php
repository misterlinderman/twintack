<?php

namespace SolidAffiliate\Controllers;

use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\ControllerFunctions;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\Either;
use SolidAffiliate\Models\Visit;
use SolidAffiliate\Views\Admin\Visits\EditView;
use SolidAffiliate\Views\Admin\Visits\NewView;
use SolidAffiliate\Views\Shared\AdminHeader;
use SolidAffiliate\Views\Shared\DeleteResourceView;
use SolidAffiliate\Views\Shared\WPListTableView;

/**
 * VisitsController
 * 
 * @psalm-suppress PropertyNotSetInConstructor
 */
class VisitsController
{
    const POST_PARAM_SUBMIT_VISIT = 'submit_visit';
    const NONCE_SUBMIT_VISIT = 'solid-affiliate-visits-new';

    const POST_PARAM_DELETE_VISIT = 'delete_visit';
    const NONCE_DELETE_VISIT = 'solid-affiliate-visits-delete';

    const POST_PARAM_DELETE_UNCONVERTED_VISITS = 'delete_unconverted_visits';
    const NONCE_DELETE_UNCONVERTED_VISITS = 'solid-affiliate-visits-delete-unconverted';

    const DELETE_UNCONVERTED_VISITS_INPUT_ID = 'visits-delete-unconverted-days';

    /**
     * @since 1.0.0
     *
     * @return void
     */
    public static function admin_root()
    {
        $action = isset($_GET["action"]) ? (string)$_GET["action"] : "index";

        switch ($action) {
            case "index":
                VisitsController::GET_admin_index();
                break;
            case "new":
                VisitsController::GET_admin_new();
                break;
            case "edit":
                $ids = ControllerFunctions::extract_ids_from_get($_GET);
                VisitsController::GET_admin_edit($ids[0]);
                break;
            case "delete":
                $ids = ControllerFunctions::extract_ids_from_get($_GET);
                VisitsController::GET_admin_delete($ids);
                break;
            default:
                VisitsController::GET_admin_index();
                break;
        }
    }

    /**
     * @return void
     */
    public static function GET_admin_index()
    {
        $o = WPListTableView::render(Visit::ADMIN_PAGE_KEY, Visit::admin_list_table(), __('Visits', 'solid-affiliate'), false);
        echo ($o);
    }

    /**
     * @return void
     */
    public static function GET_admin_new()
    {
        echo NewView::render();
    }

    /**
     * @param int $id
     *
     * @return void
     */
    public static function GET_admin_edit($id)
    {
        $maybe_visit = Visit::find($id);
        if (is_null($maybe_visit)) {
            _e('Not found', 'solid-affiliate');
        } else {
            $item = (object)$maybe_visit->attributes;
            echo EditView::render($item);
        }
    }


    /**
     * @param array<int> $ids
     *
     * @return void
     */
    public static function GET_admin_delete($ids)
    {
        $delete_view_configs = [
            'singular_name' => __('Visit', 'solid-affiliate'),
            'plural_name' => __('Visits', 'solid-affiliate'),
            'form_id' => 'visits-new',
            'nonce' => VisitsController::NONCE_DELETE_VISIT,
            'submit_action' => VisitsController::POST_PARAM_DELETE_VISIT,
            'children_classes' => ['Referrals']
        ];

        $o = DeleteResourceView::render($delete_view_configs, $ids);
        echo ($o);
    }

    /**
     * Creates the Resource
     *
     * @return void
     */
    public static function POST_admin_create_and_update_handler()
    {
        $args   = [
            'page' => Visit::ADMIN_PAGE_KEY,
            'class_string' => Visit::class,
            'success_msg_create' => __('Successfully created Visit', 'solid-affiliate'),
            'success_msg_update' => __('Successfully updated Visit', 'solid-affiliate'),
            'error_msg' => 'There was an error updating Visit.',
            'schema' => Visit::schema(),
            'upsert_function' =>
            /** 
             * @param array<string, mixed> $args
             * @return Either<int> */
            function ($args) {
                return Visit::upsert($args);
            },
            'capability' => 'read'
        ];

        ControllerFunctions::generic_upsert_handler($_POST, $args);
    }

    /**
     * @since 1.0.0
     *
     * @hook admin_init
     *
     * @return void
     */
    public static function POST_admin_delete_handler()
    {
        ////////////////////////////////////////////////////////////////////////
        // variables
        $args = [
            'page' => Visit::ADMIN_PAGE_KEY,
            'success_msg' => __('Successfully deleted Visit', 'solid-affiliate'),
            'error_msg' => __('There was an error.', 'solid-affiliate'),
            'delete_by_id_function' =>
            /**
             * @param int $id
             * 
             * @return Either<int>
             */
            function ($id) {
                return Visit::delete($id, true);
            },
        ];

        ControllerFunctions::generic_delete_handler($_POST, $args);
        ////////////////////////////////////////////////////////////////////////
    }

    /**
     * The POST action for the delete unconverted visits form on the visits list table.
     * It takes one param, the number of days old a Visit must be to be deleted if it is unconverted (no associated referral ID on the Visit record).
     *
     * @return void
     */
    public static function POST_admin_delete_unconverted_handler()
    {
        $day_count = Validators::str_from_array($_POST, self::DELETE_UNCONVERTED_VISITS_INPUT_ID);

        if (is_numeric($day_count)) {
            $day_int = intval($day_count);

            if ($day_int > 0) {
                $day_range = '-' . $day_count . ' days';

                $unconverted_ids_outside_day_range = \SolidAffiliate\Models\Visit::select_ids([
                    'referral_id' => 0,
                    'created_at' => [
                        'operator' => '<',
                        'value' => \SolidAffiliate\Lib\Utils::date_picker_time($day_range)
                    ]
                ]);

                $all_deleted = [];
                foreach ($unconverted_ids_outside_day_range as $id) {
                    $eitherID = \SolidAffiliate\Models\Visit::delete($id, false);
                    # NOTE: Just let the loop continue even if a delete fails. Better to delete all the eligible Visits and give them a success message with that count, than stoping the deletion because one fails.
                    if ($eitherID->isRight) {
                        $all_deleted[] = $eitherID->right;
                    }
                }
                $visit_count = count($all_deleted);

                ControllerFunctions::handle_redirecting_and_exit(URLs::admin_path(Visit::ADMIN_PAGE_KEY, true), [], ["{$visit_count} " . __('Visits Deleted', 'solid-affiliate')], 'admin');
            }
        }

        ControllerFunctions::handle_redirecting_and_exit(
            'REDIRECT_BACK',
            [__('A valid number of days that is greater than zero must be submitted.', 'soid-affiliate')],
            [],
            'admin'
        );
    }
}
