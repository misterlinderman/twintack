<?php

namespace SolidAffiliate\Controllers;

use SolidAffiliate\Lib\Capabilities;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\ControllerFunctions;
use SolidAffiliate\Lib\FF;
use SolidAffiliate\Lib\Roles;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\Either;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Models\AffiliateGroup;
use SolidAffiliate\Views\Admin\AffiliateGroups\EditView;
use SolidAffiliate\Views\Admin\AffiliateGroups\NewView;
use SolidAffiliate\Views\Shared\AdminHeader;
use SolidAffiliate\Views\Shared\DeleteResourceView;
use SolidAffiliate\Views\Shared\WPListTableView;


/**
 * AffiliateGroupsController
 * 
 * @psalm-suppress PropertyNotSetInConstructor
 */
class AffiliateGroupsController
{
    const POST_PARAM_SUBMIT_AFFILIATE_GROUP = 'submit_affiliate_group';
    const NONCE_SUBMIT_AFFILIATE = 'solid-affiliate-affiliate-groups-new';

    const POST_PARAM_DELETE = 'delete_affiliate_group';
    const NONCE_DELETE = 'solid-affiliate-affiliate-groups-delete';
    /**
     * This is the function that gets called when somone clicks on the
     * "Manage affiliates" link in the admin side nav. We can use use URL
     * paramaters to decide which page to show (index, show, new, etc.)
     *
     * @return void
     */
    public static function admin_root()
    {
        $action = isset($_GET["action"]) ? (string)$_GET["action"] : "index";
        switch ($action) {
            case "index":
                $active_tab = (isset($_GET['tab']) && (string)$_GET['tab'] == 'tags') ? 'tags' : 'groups';
                echo self::tabs_for_affiliate_groups_and_tags($active_tab);
                AffiliateGroupsController::GET_admin_index();
                break;
            case "new":
                AffiliateGroupsController::GET_admin_new();
                break;
            case "edit":
                $ids = ControllerFunctions::extract_ids_from_get($_GET);
                AffiliateGroupsController::GET_admin_edit($ids[0]);
                break;
            case "delete":
                $ids = ControllerFunctions::extract_ids_from_get($_GET);
                AffiliateGroupsController::GET_admin_delete($ids);
                break;
            case "set_default":
                $id = ControllerFunctions::extract_ids_from_get($_GET)[0];
                AffiliateGroup::set_default_group_id($id);
                ControllerFunctions::handle_redirecting_and_exit(URLs::admin_path(AffiliateGroup::ADMIN_PAGE_KEY, true), [], [__('Group set to default.', 'solid-affiliate')], 'admin');
                break;
            default:
                AffiliateGroupsController::GET_admin_index();
                break;
        }
    }

    /**
     * Returns a simple html string with tabs for affiliate groups and tags.
     * The active tab is selected.
     * Both tabs are links to the respective pages.
     *
     * @param 'groups' | 'tags' $active
     * @return string
     */
    public static function tabs_for_affiliate_groups_and_tags($active = 'groups')
    {
        $groups_active = $active === 'groups' ? 'active' : '';
        $tags_active = $active === 'tags' ? 'active' : '';

        $groups_url = URLs::admin_path(AffiliateGroup::ADMIN_PAGE_KEY, true);
        $tags_url = URLs::admin_path(AffiliateGroup::ADMIN_PAGE_KEY, true, ['tab' => 'tags']);

        $ff_pill = FF::maybe_pill('affiliate_tags');

        $html = <<<HTML
        <style>
            .affilates-tab {
                background:rgba(255,255,255,.4);
                border-bottom:1px solid var(--sld-border);
                margin-inline: -20px;
                padding-inline: 20px;
                max-width: calc(100% - 20px);
            }
            .affilates-tab svg {
                stroke:#000;
            }
        </style>
        <div class="affilates-tab">
    <div class="nav-tab-wrapper">
        <a href="{$groups_url}" class="nav-tab nav-tab-{$groups_active}">  
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M8 21V20C8 19.4696 8.21071 18.9609 8.58579 18.5858C8.96086 18.2107 9.46957 18 10 18H14C14.5304 18 15.0391 18.2107 15.4142 18.5858C15.7893 18.9609 16 19.4696 16 20V21M17 10H19C19.5304 10 20.0391 10.2107 20.4142 10.5858C20.7893 10.9609 21 11.4696 21 12V13M3 13V12C3 11.4696 3.21071 10.9609 3.58579 10.5858C3.96086 10.2107 4.46957 10 5 10H7M10 13C10 13.5304 10.2107 14.0391 10.5858 14.4142C10.9609 14.7893 11.4696 15 12 15C12.5304 15 13.0391 14.7893 13.4142 14.4142C13.7893 14.0391 14 13.5304 14 13C14 12.4696 13.7893 11.9609 13.4142 11.5858C13.0391 11.2107 12.5304 11 12 11C11.4696 11 10.9609 11.2107 10.5858 11.5858C10.2107 11.9609 10 12.4696 10 13ZM15 5C15 5.53043 15.2107 6.03914 15.5858 6.41421C15.9609 6.78929 16.4696 7 17 7C17.5304 7 18.0391 6.78929 18.4142 6.41421C18.7893 6.03914 19 5.53043 19 5C19 4.46957 18.7893 3.96086 18.4142 3.58579C18.0391 3.21071 17.5304 3 17 3C16.4696 3 15.9609 3.21071 15.5858 3.58579C15.2107 3.96086 15 4.46957 15 5ZM5 5C5 5.53043 5.21071 6.03914 5.58579 6.41421C5.96086 6.78929 6.46957 7 7 7C7.53043 7 8.03914 6.78929 8.41421 6.41421C8.78929 6.03914 9 5.53043 9 5C9 4.46957 8.78929 3.96086 8.41421 3.58579C8.03914 3.21071 7.53043 3 7 3C6.46957 3 5.96086 3.21071 5.58579 3.58579C5.21071 3.96086 5 4.46957 5 5Z" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Affiliate Groups
        </a>
        <a href="{$tags_url}" class="nav-tab {$tags_active}">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M6.5 7.5C6.5 7.76522 6.60536 8.01957 6.79289 8.20711C6.98043 8.39464 7.23478 8.5 7.5 8.5C7.76522 8.5 8.01957 8.39464 8.20711 8.20711C8.39464 8.01957 8.5 7.76522 8.5 7.5C8.5 7.23478 8.39464 6.98043 8.20711 6.79289C8.01957 6.60536 7.76522 6.5 7.5 6.5C7.23478 6.5 6.98043 6.60536 6.79289 6.79289C6.60536 6.98043 6.5 7.23478 6.5 7.5Z" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M3 6V11.172C3.00011 11.7024 3.2109 12.211 3.586 12.586L11.296 20.296C11.748 20.7479 12.3609 21.0017 13 21.0017C13.6391 21.0017 14.252 20.7479 14.704 20.296L20.296 14.704C20.7479 14.252 21.0017 13.6391 21.0017 13C21.0017 12.3609 20.7479 11.748 20.296 11.296L12.586 3.586C12.211 3.2109 11.7024 3.00011 11.172 3H6C5.20435 3 4.44129 3.31607 3.87868 3.87868C3.31607 4.44129 3 5.20435 3 6Z" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Affiliate tags {$ff_pill}
        </a>
    </div>
        </div>
    HTML;

        return $html;
    }


    /**
     * @return void
     */
    public static function GET_admin_index()
    {
        $o = WPListTableView::render(AffiliateGroup::ADMIN_PAGE_KEY, AffiliateGroup::admin_list_table(), __('Affiliate Groups', 'solid-affiliate'));
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

    // Luca: capabilities?
    // Usually you do checks for 'view/edit/delete/update/manage' this kind of content
    // - I might have a user that can CREATE and affiliate but cannot DELETE an affiliate
    //   - the expected UX would be that user gets redirected to a page that says "no you can't do that sir"
    // - User capabilities in multiple places: nonces, views, db level.
    // - expectation that plugin should be able to have developers add/edit/remove custom roles and respective capabilities.
    // - Talk to Louis about multisite
    /**
     * @param int $id
     *
     * @return void
     */
    public static function GET_admin_edit($id)
    {
        $maybe_item = AffiliateGroup::find($id);

        if (is_null($maybe_item)) {
            _e('Not found', 'solid-affiliate');
        } else {
            $item = (object)$maybe_item->attributes;
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
            'singular_name' => __('Affiliate Group', 'solid-affiliate'),
            'plural_name' => __('Affiliate Groups', 'solid-affiliate'),
            'form_id' => 'affiliate-groups-new',
            'nonce' => AffiliateGroupsController::NONCE_DELETE,
            'submit_action' => AffiliateGroupsController::POST_PARAM_DELETE,
            'children_classes' => []
        ];
        $o = DeleteResourceView::render($delete_view_configs, $ids);
        echo ($o);
    }

    ///////////////////////////////////////////////////////////////////////////
    // POST
    ///////////////////////////////////////////////////////////////////////////

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
                    $delete_url = URLs::delete(AffiliateGroup::class, true);
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
            'page' => AffiliateGroup::ADMIN_PAGE_KEY,
            'class_string' => AffiliateGroup::class,
            'success_msg_create' => __('Successfully created Affiliate Group', 'solid-affiliate'),
            'success_msg_update' => __('Successfully updated Affiliate Group', 'solid-affiliate'),
            'error_msg' => __('There was an error.', 'solid-affiliate'),
            'schema' => AffiliateGroup::schema(),
            'upsert_function' =>
            /** 
             * @param array<string, mixed> $args
             * @return Either<int> */
            function ($args) {
                $eitherId = AffiliateGroup::upsert($args);
                return $eitherId;
            },
            'capability' => Capabilities::EDIT_AFFILIATES
        ];

        ControllerFunctions::generic_upsert_handler($_POST, $args);
    }

    /**
     * @return void
     */
    public static function POST_admin_delete_handler()
    {
        ////////////////////////////////////////////////////////////////////////
        // variables
        $delete_handler_args = [
            'page' => AffiliateGroup::ADMIN_PAGE_KEY,
            'success_msg' => __('Successfully deleted Affiliate Group(s)', 'solid-affiliate'),
            'error_msg' => __('There was an error.', 'solid-affiliate'),
            'delete_by_id_function' =>
            /**
             * @param int $id
             * 
             * @return Either<int>
             */
            function ($id) {
                return AffiliateGroup::delete($id, true);
            },
        ];

        ControllerFunctions::generic_delete_handler($_POST, $delete_handler_args);
        ////////////////////////////////////////////////////////////////////////
    }
}
