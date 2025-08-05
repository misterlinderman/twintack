<?php

namespace SolidAffiliate\Controllers;

use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\ControllerFunctions;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\Either;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Models\Creative;
use SolidAffiliate\Views\Admin\Creatives\EditView;
use SolidAffiliate\Views\Admin\Creatives\NewView;
use SolidAffiliate\Views\Shared\AdminHeader;
use SolidAffiliate\Views\Shared\DeleteResourceView;
use SolidAffiliate\Views\Shared\WPListTableView;

/**
 * CreativesController
 * 
 * @psalm-suppress PropertyNotSetInConstructor
 */
class CreativesController
{
    const POST_PARAM_SUBMIT_CREATIVE = 'submit_creative';
    const NONCE_SUBMIT_CREATIVE = 'solid-affiliate-creatives-new';

    const POST_PARAM_DELETE_CREATIVE = 'delete_creative';
    const NONCE_DELETE_CREATIVE = 'solid-affiliate-creatives-delete';

    /**
     * @hook solid_affiliate_creative
     *
     * @return void
     */
    public static function register_shortcodes()
    {
        add_shortcode('solid_affiliate_creative', [CreativesController::class, 'solid_affiliate_creative']);
    }

    /**
     * @param array $args
     * @return string
     */
    public static function solid_affiliate_creative($args = array())
    {
        $creative_id = isset($args['id']) ? (int) $args['id'] : 0;
        $creative = Creative::find($creative_id);

        if (is_null($creative)) {
            return sprintf(__('Creative with ID = %1$s not found.', 'solid-affiliate'), $creative_id);
        } else {
            return Creative::generate_html_for_creative($creative, 0);
        }
    }

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
                CreativesController::GET_admin_index();
                break;
            case "new":
                CreativesController::GET_admin_new();
                break;
            case "edit":
                $ids = ControllerFunctions::extract_ids_from_get($_GET);
                CreativesController::GET_admin_edit($ids[0]);
                break;
            case "delete":
                $ids = ControllerFunctions::extract_ids_from_get($_GET);
                CreativesController::GET_admin_delete($ids);
                break;
            default:
                CreativesController::GET_admin_index();
                break;
        }
    }

    /**
     * @return void
     */
    public static function GET_admin_index()
    {
        $o = WPListTableView::render(Creative::ADMIN_PAGE_KEY, Creative::admin_list_table(), __('Creatives', 'solid-affiliate'));
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
        $maybe_visit = Creative::find($id);
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
            'singular_name' => __('Creative', 'solid-affiliate'),
            'plural_name' => __('Creatives', 'solid-affiliate'),
            'form_id' => 'creatives-new',
            'nonce' => CreativesController::NONCE_DELETE_CREATIVE,
            'submit_action' => CreativesController::POST_PARAM_DELETE_CREATIVE,
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
            'page' => Creative::ADMIN_PAGE_KEY,
            'class_string' => Creative::class,
            'success_msg_create' => __('Successfully created Creative', 'solid-affiliate'),
            'success_msg_update' => __('Successfully updated Creative', 'solid-affiliate'),
            'error_msg' => __('There was an error updating Creative', 'solid-affiliate'),
            'schema' => Creative::schema(),
            'upsert_function' =>
            /** 
             * @param array<string, mixed> $args
             * @return Either<int> */
            function ($args) {
                return Creative::upsert($args);
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
            'page' => Creative::ADMIN_PAGE_KEY,
            'success_msg' => __('Successfully deleted Creative', 'solid-affiliate'),
            'error_msg' => __('There was an error.', 'solid-affiliate'),
            'delete_by_id_function' =>
            /**
             * @param int $id
             * 
             * @return Either<int>
             */
            function ($id) {
                return Creative::delete($id, true);
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
                case 'Activate':
                    $creatives = Creative::find_many($ids);
                    Creative::updateInstances($creatives, ['status' => 'active']);
                    break;
                case 'Deactivate':
                    $creatives = Creative::find_many($ids);
                    Creative::updateInstances($creatives, ['status' => 'inactive']);
                    break;
                case 'Delete':
                    $delete_url = URLs::delete(Creative::class, true);
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
