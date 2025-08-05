<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Controllers\AffiliateGroupsController;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Lib\ListTables\AffiliateGroupsListTable;
use SolidAffiliate\Lib\ListTables\SolidWPListTable;
use SolidAffiliate\Lib\VO\Either;
use SolidAffiliate\Lib\VO\RouteDescription;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Models\AffiliateGroup;
use SolidAffiliate\Views\Shared\AdminHeader;
use SolidAffiliate\Views\Shared\DeleteResourceView;
use SolidAffiliate\Views\Shared\WPListTableView;

/**
 * @psalm-type ResourceDescription = array{
 *   model: class-string<MikesDataModel>,
 *   table_suffix: string,
 *   schema: Schema,
 *   page_key: string,
 *   menu_title_override: string,
 *   post_param_create_resource: string,
 *   post_param_delete_resource: string,
 *   search_by_key: string,
 *   model_singular: string,
 *   model_plural: string,
 *   column_name_overrides?: array<string, string>,
 *   submenu_position?: int,
 *   ff_disabled?: bool
 * }
 * 
 */
class SolidAdminCRUD
{

    /**
     * Handles everything for you. Just give it a description.
     *
     * @param ResourceDescription $description
     * 
     * @return void
     */
    public static function register($description)
    {
        self::register_admin_menu($description);
        self::register_post_routes($description);
    }


    ////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////
    // Supporting Functions
    ////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * @param ResourceDescription $description
     * 
     * @return void
     */
    public static function register_admin_menu($description)
    {
        // add a hook to register the menu
        add_action(
            "solid_affiliate/admin/submenu_pages/after",
            /** @param string $menu_slug */
            function ($menu_slug) use ($description) {
                add_submenu_page(
                    $menu_slug,
                    $description['menu_title_override'],
                    $description['menu_title_override'],
                    Capabilities::MANAGE_AFFILIATE_PROGRAM,
                    $description['page_key'],
                    /** @param ResourceDescription $description */
                    function () use ($description) {
                        ////////////////////////////////////////////////////////////////////////////////
                        // Admin Header
                        $additional_admin_header_page_map = [
                            $description['page_key'] => $description['menu_title_override'],
                        ];
                        echo AdminHeader::render_from_get_request($_GET, $additional_admin_header_page_map);
                        ////////////////////////////////////////////////////////////////////////////////

                        self::admin_root($description);
                    },
                    (isset($description['submenu_position'])) ? $description['submenu_position'] : null
                );
            }
        );
    }

    /**
     * @param ResourceDescription $description
     * 
     * @return void
     */
    public static function register_post_routes($description)
    {
        $new_routes = [
            new RouteDescription([
                'post_param_key' => $description['post_param_delete_resource'],
                'nonce' => $description['post_param_delete_resource'],
                'callback' => function () use ($description) {
                    $delete_handler_args = [
                        'page' => $description['page_key'],
                        'success_msg' => __('Successfully deleted this item.', 'solid-affiliate'),
                        'error_msg' => __('There was an error.', 'solid-affiliate'),
                        'delete_by_id_function' =>
                        /**
                         * @param int $id
                         * 
                         * @return Either<int>
                         */
                        function ($id) use ($description) {
                            return $description['model']::delete($id, true);
                        },
                    ];

                    ControllerFunctions::generic_delete_handler($_POST, $delete_handler_args);
                }
            ]),
            new RouteDescription([
                'post_param_key' => $description['post_param_create_resource'],
                'nonce' => $description['post_param_create_resource'],
                'callback' => function () use ($description) {
                    $args   = [
                        'page' => $description['page_key'],
                        'class_string' => $description['model'],
                        'success_msg_create' => __('Successfully created ' . $description['model_singular'], 'solid-affiliate'),
                        'success_msg_update' => __('Successfully updated ' . $description['model_singular'], 'solid-affiliate'),
                        'error_msg' => __('There was an error.', 'solid-affiliate'),
                        'schema' => $description['schema'],
                        'upsert_function' =>
                        /** 
                         * @param array<string, mixed> $args
                         * @return Either<int> */
                        function ($args) use ($description) {
                            $eitherId = $description['model']::upsert($args);
                            return $eitherId;
                        },
                        'capability' => Capabilities::EDIT_AFFILIATES
                    ];

                    ControllerFunctions::generic_upsert_handler($_POST, $args);
                }
            ]),
            new RouteDescription([
                'post_param_key' => 'page',
                'post_param_val' => $description['page_key'],
                'post_param_key_b' => 'action',
                'post_param_val_b' => ['Delete'],
                'nonce' => 'bulk-' . sanitize_key($description['model_plural']), // nonce is "bulk-{plural_from_table_config}"
                'callback' => function () use ($description) {
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
                        $ids = Validators::array_of_int($eitherPostParams->right['id']);

                        switch ($bulk_action_string) {
                            case 'Delete':
                                $delete_url = URLs::delete($description['model'], true);
                                ControllerFunctions::handle_redirecting_and_exit($delete_url, [], [], 'admin', ['id' => $ids]);
                                break;
                            default:
                                ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', ["Invalid bulk action: {$bulk_action_string}"], [], 'admin');
                                break;
                        }

                        ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', [], [__('Update Successful', 'solid-affiliate')]);
                    };
                }
            ])
        ];

        add_filter(
            "solid_affiliate/PostRequestController/routes",
            /** @param RouteDescription[] $routes */
            function ($routes) use ($new_routes) {
                return array_merge($routes, $new_routes);
            }
        );
    }

    /**
     * @param ResourceDescription $description
     * 
     * @return void - TODO think about making these functions return strings instead of echoing them
     */
    public static function admin_root($description)
    {
        echo FF::maybe_overlay('lifetime_commissions');

        $action = isset($_GET["action"]) ? (string)$_GET["action"] : "index";
        switch ($action) {
            case "index":
                self::GET_admin_index($description);
                break;
            case "new":
                self::GET_admin_new($description);
                break;
            case "edit":
                $ids = ControllerFunctions::extract_ids_from_get($_GET);
                self::GET_admin_edit($description, $ids[0]);
                break;
            case "delete":
                $ids = ControllerFunctions::extract_ids_from_get($_GET);
                self::GET_admin_delete($description, $ids);
                break;
            default:
                self::GET_admin_index($description);
                break;
        }
    }

    /**
     * @param ResourceDescription $description
     * 
     * @return void
     */
    public static function GET_admin_index($description)
    {
        $list_table = self::list_table_for_description($description);

        $o = WPListTableView::render(
            $description['page_key'],
            $list_table,
            $description['menu_title_override']
        );
        echo ($o);
    }

    /**
     * @param ResourceDescription $description
     * 
     * @return void
     */
    public static function GET_admin_new($description)
    {
        $o = SolidAdminCRUD::views_admin_new($description);

        echo ($o);
    }

    /**
     * @param ResourceDescription $description
     * @param int $id
     *
     * @return void
     */
    public static function GET_admin_edit($description, $id)
    {
        $maybe_item = $description['model']::find($id);

        if (is_null($maybe_item)) {
            _e('Not found', 'solid-affiliate');
        } else {
            $item = (object)$maybe_item->attributes;
            echo self::views_admin_edit($description, $item);
        }
    }

    /**
     * @param ResourceDescription $description
     * @param array<int> $ids
     *
     * @return void
     */
    public static function GET_admin_delete($description, $ids)
    {
        $delete_view_configs = [
            'singular_name' => $description['menu_title_override'],
            'plural_name' => $description['model_plural'],
            'form_id' => $description['page_key'] . 'delete',
            'nonce' => $description['post_param_delete_resource'],
            'submit_action' => $description['post_param_delete_resource'],
            'children_classes' => [] // TODO worry about this later
        ];
        $o = DeleteResourceView::render($delete_view_configs, $ids);
        echo ($o);
    }

    ////////////////////////////////////////////////////////
    // Start - Views
    ////////////////////////////////////////////////////////
    /**
     * @param ResourceDescription $description
     * 
     * @return string
     */
    public static function views_admin_new($description)
    {
        $singular = $description['model_singular'];
        $form_id = $description['page_key'] . '-new';
        $schema = $description['schema'];
        // $nonce = AffiliateGroupsController::NONCE_SUBMIT_AFFILIATE;
        // $submit_action = AffiliateGroupsController::POST_PARAM_SUBMIT_AFFILIATE_GROUP;
        $nonce = $description['post_param_create_resource'];
        $submit_action = $description['post_param_create_resource'];
        $ff_disabled = $description['ff_disabled'] ?? false;

        // initialize the $item with default data if applicable
        $form = FormBuilder::render_crud_form_new($schema, $submit_action, $nonce, $form_id, $singular, null, null, $ff_disabled);

        ob_start();
?>
        <div class="wrap has-form">
            <div class="sld_form-container">
                <div class="sld_form-wrapper">
                    <div class="sld_form-head">
                        <h1><?php echo sprintf(__('Add New %1$s', 'solid-affiliate'), $singular); ?></h1>
                    </div>
                    <?php echo $form ?>
                </div>
            </div>
        </div>

    <?php
        return ob_get_clean();
    }

    /**
     * @param ResourceDescription $description
     * @param object $item
     * 
     * @return string
     */
    public static function views_admin_edit($description, $item)
    {
        $singular = $description['model_singular'];
        $form_id = $description['page_key'] . '-edit';
        $schema = $description['schema'];
        $nonce = $description['post_param_create_resource'];
        $submit_action = $description['post_param_create_resource'];
        $ff_disabled = $description['ff_disabled'] ?? false;

        $form = FormBuilder::render_crud_form_edit($schema, $submit_action, $nonce, $form_id, $singular, $item, null, $ff_disabled);
        ob_start();
    ?>
                <div class="wrap has-form">
            <div class="sld_form-container">
                <div class="sld_form-wrapper">
                    <div class="sld_form-head">
                        <h1><?php echo sprintf(__('Add New %1$s', 'solid-affiliate'), $singular); ?></h1>
                    </div>
                    <?php echo $form ?>
                </div>
            </div>
        </div>
<?php
        return ob_get_clean();
    }
    ////////////////////////////////////////////////////////
    // End - Views
    ////////////////////////////////////////////////////////


    ////////////////////////////////////////////////////////
    // Start - List Table
    ////////////////////////////////////////////////////////
    /**
     * @param ResourceDescription $description
     * 
     * @return SolidWPListTable
     */
    public static function list_table_for_description($description)
    {

        // return new AffiliateGroupsListTable;
        // $list_table = $description['model']::admin_list_table();
        // return $list_table;
        return new SolidCRUDListTable($description);
    }
    ////////////////////////////////////////////////////////
    // End - List Table
    ////////////////////////////////////////////////////////


}
