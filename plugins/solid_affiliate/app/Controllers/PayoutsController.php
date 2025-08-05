<?php

namespace SolidAffiliate\Controllers;

use SolidAffiliate\Lib\ControllerFunctions;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\Either;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Views\Admin\Payouts\EditView;
use SolidAffiliate\Views\Admin\Payouts\NewView;
use SolidAffiliate\Views\Shared\AdminHeader;
use SolidAffiliate\Views\Shared\DeleteResourceView;
use SolidAffiliate\Views\Shared\WPListTableView;

/**
 * PayoutsController
 * 
 * @psalm-suppress PropertyNotSetInConstructor
 */
class PayoutsController
{
    const POST_PARAM_SUBMIT_PAYOUT = 'submit_payout';
    const NONCE_SUBMIT_PAYOUT = Payout::ADMIN_PAGE_KEY . "-new";

    const POST_PARAM_DELETE_PAYOUT = 'delete_payout';
    const NONCE_DELETE_PAYOUT = Payout::ADMIN_PAGE_KEY . "-delete";

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
                PayoutsController::GET_admin_index();
                break;
            case "new":
                PayoutsController::GET_admin_new();
                break;
            case "edit":
                $ids = ControllerFunctions::extract_ids_from_get($_GET);
                PayoutsController::GET_admin_edit($ids[0]);
                break;
            case "delete":
                $ids = ControllerFunctions::extract_ids_from_get($_GET);
                PayoutsController::GET_admin_delete($ids);
                break;
            default:
                PayoutsController::GET_admin_index();
                break;
        }
    }

    /**
     * @return void
     */
    public static function GET_admin_index()
    {
        $o = WPListTableView::render(Payout::ADMIN_PAGE_KEY, Payout::admin_list_table(), __('Payouts', 'solid-affiliate'), false, true);
        echo ($o);
    }

    /**
     * @return void
     */
    public static function GET_admin_new()
    {
        $o = NewView::render();
        echo ($o);
    }

    /**
     * @param int $id
     *
     * @return void
     */
    public static function GET_admin_edit($id)
    {
        $maybe_payout = Payout::find($id);
        if (is_null($maybe_payout)) {
            _e('Not found', 'solid-affiliate');
        } else {
            $item = (object)$maybe_payout->attributes;
            $o = EditView::render($item);
            echo ($o);
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
            'singular_name' => __('Payout', 'solid-affiliate'),
            'plural_name' => __('Payouts', 'solid-affiliate'),
            'form_id' => 'payouts-new',
            'nonce' => PayoutsController::NONCE_DELETE_PAYOUT,
            'submit_action' => PayoutsController::POST_PARAM_DELETE_PAYOUT,
            'children_classes' => []
        ];

        $o = DeleteResourceView::render($delete_view_configs, $ids);
        echo ($o);
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public static function POST_admin_table_bulk_actions_handler()
    {
        $schema = new Schema(['entries' =>
        [
            'id' => new SchemaEntry([
                'type' => 'varchar',
                'is_enum' => true,
                'display_name' => 'id'
            ]),
            'action' => new SchemaEntry([
                'type' => 'varchar',
                'display_name' => 'action'
            ]),
        ]]);


        $eitherPostParams = ControllerFunctions::extract_and_validate_POST_params(
            $_POST,
            ['id', 'action'],
            $schema
        );

        if ($eitherPostParams->isLeft) {
            ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', $eitherPostParams->left);
        } else {
            $bulk_action_string = (string)$eitherPostParams->right['action'];
            // TODO the idea is that we can encode the data type requirement in a Schema entry,
            // and ControllerFunctions::extract_and_validate_POST_params should take care of ensureing the type.
            // So we wouldn't need to do this below Validator.
            $ids = Validators::array_of_int($eitherPostParams->right['id']);

            switch ($bulk_action_string) {
                case 'Delete':
                    $delete_url = URLs::delete(Payout::class, true);
                    ControllerFunctions::handle_redirecting_and_exit($delete_url, [], [], 'admin', ['id' => $ids]);
                    break;
                default:
                    ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', ["Invalid bulk action: {$bulk_action_string}"], [], 'admin');
                    break;
            }

            ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', [], [__('Update Successful', 'solid-affiliate')]);
        };
    }


    /**
     * Creates the Resource
     *
     * @return void
     */
    public static function POST_admin_create_and_update_handler()
    {
        $args   = [
            'page' => Payout::ADMIN_PAGE_KEY,
            'class_string' => Payout::class,
            'success_msg_create' => __('Successfully created Payout', 'solid-affiliate'),
            'success_msg_update' => __('Successfully updated Payout', 'solid-affiliate'),
            'error_msg' => __('There was an error updating the Payout.', 'solid-affiliate'),
            'schema' => Payout::schema(),
            'upsert_function' =>
            /** 
             * @param array<string, mixed> $args
             * @return Either<int> */
            function ($args) {
                return Payout::upsert($args);
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
            'page' => Payout::ADMIN_PAGE_KEY,
            'success_msg' => __('Successfully deleted Payout', 'solid-affiliate'),
            'error_msg' => __('There was an error.', 'solid-affiliate'),
            'delete_by_id_function' =>
            /**
             * @param int $id
             * 
             * @return Either<int>
             */
            function ($id) {
                return Payout::delete($id, true);
            },
        ];

        ControllerFunctions::generic_delete_handler($_POST, $args);
        ////////////////////////////////////////////////////////////////////////
    }
}
