<?php

namespace SolidAffiliate\Controllers;

use SolidAffiliate\Lib\ControllerFunctions;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\Either;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Views\Admin\Referrals\EditView;
use SolidAffiliate\Views\Admin\Referrals\NewView;
use SolidAffiliate\Views\Shared\AdminHeader;
use SolidAffiliate\Views\Shared\DeleteResourceView;
use SolidAffiliate\Views\Shared\WPListTableView;


/**
 * ReferralsController
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
class ReferralsController
{
    const POST_PARAM_SUBMIT_REFERRAL = 'submit_referral';
    const NONCE_SUBMIT_REFERRAL = 'solid-affiliate-referrals-new';

    const POST_PARAM_DELETE_REFERRAL = 'delete_referral';
    const NONCE_DELETE_REFERRAL = 'solid-affiliate-referrals-delete';

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
                ReferralsController::GET_admin_index();
                break;
            case "new":
                ReferralsController::GET_admin_new();
                break;
            case "edit":
                $ids = ControllerFunctions::extract_ids_from_get($_GET);
                ReferralsController::GET_admin_edit($ids[0]);
                break;
            case "delete":
                $ids = ControllerFunctions::extract_ids_from_get($_GET);
                ReferralsController::GET_admin_delete($ids);
                break;
            default:
                ReferralsController::GET_admin_index();
                break;
        }
    }

    /**
     * @return void
     */
    public static function GET_admin_index()
    {
        $o = WPListTableView::render(Referral::ADMIN_PAGE_KEY, Referral::admin_list_table(), __('Referrals', 'solid-affiliate'));
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
        $maybe_referral = Referral::find($id);
        if (is_null($maybe_referral)) {
            _e('Not found', 'solid-affiliate');
        } else {
            $item = (object)$maybe_referral->attributes;
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
            'singular_name' => __('Referral', 'solid-affiliate'),
            'plural_name' => __('Referrals', 'solid-affiliate'),
            'form_id' => 'referrals-new',
            'nonce' => ReferralsController::NONCE_DELETE_REFERRAL,
            'submit_action' => ReferralsController::POST_PARAM_DELETE_REFERRAL,
            'children_classes' => []
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
            'page' => Referral::ADMIN_PAGE_KEY,
            'class_string' => Referral::class,
            'success_msg_create' => __('Successfully created Referral', 'solid-affiliate'),
            'success_msg_update' => __('Successfully updated Referral', 'solid-affiliate'),
            'error_msg' => __('There was an error updating the Referral.', 'solid-affiliate'),
            'schema' => Referral::schema(),
            'upsert_function' =>
            /** 
             * @param array<string, mixed> $args
             * @return Either<int> */
            function ($args) {
                $args["manually_created_by_admin_user_id"] = get_current_user_id();
                return Referral::upsert($args);
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
            'page' => Referral::ADMIN_PAGE_KEY,
            'success_msg' => __('Successfully deleted Referral', 'solid-affiliate'),
            'error_msg' => __('There was an error.', 'solid-affiliate'),
            'delete_by_id_function' =>
            /**
             * @param int $id
             * 
             * @return Either<int>
             */
            function ($id) {
                return Referral::delete($id, true);
            },
        ];

        ControllerFunctions::generic_delete_handler($_POST, $args);
        ////////////////////////////////////////////////////////////////////////
    }

    /**
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
        $eitherFields = ControllerFunctions::extract_and_validate_POST_params(
            $_POST,
            ['action', 'id'],
            $schema
        );

        if ($eitherFields->isLeft) {
            ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', $eitherFields->left);
        } else {

            $bulk_action_string = (string) $eitherFields->right['action'];
            $ids = Validators::array_of_int($eitherFields->right['id']);

            switch ($bulk_action_string) {
                case 'Reject':
                    $referrals = Referral::find_many($ids);
                    Referral::updateInstances($referrals, ['status' => Referral::STATUS_REJECTED]);
                    break;
                case 'Mark as Paid':
                    $referrals = Referral::find_many($ids);
                    Referral::updateInstances($referrals, ['status' => Referral::STATUS_PAID]);
                    break;
                case 'Mark as Unpaid':
                    $referrals = Referral::find_many($ids);
                    Referral::updateInstances($referrals, ['status' => Referral::STATUS_UNPAID]);
                    break;
                case 'Delete':
                    $delete_url = URLs::delete(Referral::class, true);
                    ControllerFunctions::handle_redirecting_and_exit($delete_url, [], [], 'admin', ['id' => $ids]);
                    break;
                default:
                    ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', ["Invalid bulk action: {$bulk_action_string}"], [], 'admin');
                    break;
            }

            ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', [], [__('Update Successful', 'solid-affiliate')]);
        };
    }
}
