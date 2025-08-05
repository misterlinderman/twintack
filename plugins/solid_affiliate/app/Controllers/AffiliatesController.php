<?php

namespace SolidAffiliate\Controllers;

use SolidAffiliate\Lib\AffiliateRegistrationForm\AffiliateRegistrationFormFunctions;
use SolidAffiliate\Lib\Capabilities;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\ControllerFunctions;
use SolidAffiliate\Lib\FF;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Lib\MikesDataModel;
use SolidAffiliate\Lib\Roles;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\Either;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliateGroup;
use SolidAffiliate\Models\AffiliatePortal;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Views\Admin\Affiliates\EditView;
use SolidAffiliate\Views\Admin\Affiliates\NewView;
use SolidAffiliate\Views\Shared\AdminHeader;
use SolidAffiliate\Views\Shared\DeleteResourceView;
use SolidAffiliate\Views\Shared\WPListTableView;


/**
 * AffiliatesController
 * 
 * @psalm-suppress PropertyNotSetInConstructor
 */
class AffiliatesController
{
    const POST_PARAM_SUBMIT_AFFILIATE = 'submit_affiliate';
    const NONCE_SUBMIT_AFFILIATE = 'solid-affiliate-affiliates-new';

    const POST_PARAM_DELETE_AFFILIATE = 'delete_affiliate';
    const NONCE_DELETE_AFFILIATE = 'solid-affiliate-affiliates-delete';

    const ADMIN_PREVIEW_AFFILIATE_PORTAL_ACTION = "preview-affiliate-portal";
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
                AffiliatesController::GET_admin_index();
                break;
            case "new":
                AffiliatesController::GET_admin_new();
                break;
            case "edit":
                $ids = ControllerFunctions::extract_ids_from_get($_GET);
                AffiliatesController::GET_admin_edit($ids[0]);
                break;
            case "delete":
                $ids = ControllerFunctions::extract_ids_from_get($_GET);
                AffiliatesController::GET_admin_delete($ids);
                break;
            case AffiliatesController::ADMIN_PREVIEW_AFFILIATE_PORTAL_ACTION:
                $ids = ControllerFunctions::extract_ids_from_get($_GET);
                AffiliatesController::GET_admin_preview_affiliate_portal($ids[0]);
                break;
            default:
                AffiliatesController::GET_admin_index();
                break;
        }
    }

    /**
     * @return void
     */
    public static function GET_admin_index()
    {
        $o = WPListTableView::render(Affiliate::ADMIN_PAGE_KEY, Affiliate::admin_list_table(), __('Affiliates', 'solid-affiliate'), true, true);
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
        $maybe_affiliate = Affiliate::find($id);

        if (is_null($maybe_affiliate)) {
            _e('Not found', 'solid-affiliate');
        } else {
            $item = AffiliateRegistrationFormFunctions::prep_affiliate_object_form_form($maybe_affiliate);

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
            'singular_name' => __('Affiliate', 'solid-affiliate'),
            'plural_name' => __('Affiliates', 'solid-affiliate'),
            'form_id' => 'affiliates-new',
            'nonce' => AffiliatesController::NONCE_DELETE_AFFILIATE,
            'submit_action' => AffiliatesController::POST_PARAM_DELETE_AFFILIATE,
            'children_classes' => [__('Payouts', 'solid-affiliate'), __('Referrals', 'solid-affiliate'), __('Visits', 'solid-affiliate'), __('Affiliate Product Rates', 'solid-affiliate'), __('Affiliate Customer Links (Lifetime Customers)', 'solid-affiliate')],
        ];
        $o = DeleteResourceView::render($delete_view_configs, $ids);
        echo ($o);
    }

    /**
     * @param int $affiliate_id
     *
     * @return void
     */
    public static function GET_admin_preview_affiliate_portal($affiliate_id)
    {
        $maybe_affiliate = Affiliate::find($affiliate_id);
        if (FF::is_off('affiliate_portal_preview')) {
            echo FF::maybe_overlay('affiliate_portal_preview');
        } elseif (is_null($maybe_affiliate)) {
            echo '<div class="wrap">';
            echo ('<h2>' . __('Affiliate Portal - Admin Preview', 'solid-affiliate') . '</h2>');
            echo ('<p>' . __('This is a preview of the affiliate portal, as if the affiliate themselves were viewing their own portal. You can select any affiliate to see their portal.', 'solid-affiliate') . '</p>');
            echo ('<p>' . __('Note, this preview does not include the theme or styling of the page where your portals are embeded. The affiliates may see a differently styled portal due to the theme of your site, but the data will be exactly the same.', 'solid-affiliate') . '</p>');
            $schema = new Schema([
                'entries' => [
                    'admin_portal_preview_affiliate_id' => new SchemaEntry([
                        'type' => 'bigint',
                        'length' => 20,
                        'required' => true,
                        'display_name' => __('Affiliate to preview', 'solid-affiliate'),
                        'form_input_description' => __("Select an affiliate to preview their portal", 'solid-affiliate'),
                        'form_input_type_override' => 'affiliate_select',
                        'show_on_new_form' => true,
                        'show_on_edit_form' => true,
                        'show_list_table_column' => true,
                        'key' => true,
                        'is_csv_exportable' => true
                    ]),
                ]
            ]);
            echo FormBuilder::build_form($schema, 'edit', (object)['admin_portal_preview_affiliate_id' => $affiliate_id]);
            echo ('<button type="submit" id="solid-affiliate_change-affiliate-preview-id" class="button action ">' . __('Change Affiliate', 'solid-affiliate') . '</button>');
            echo '<div class="solid-affiliate_admin-affiliate-portal-preview no-affiliate">';
            echo '<div class="solid-affiliate_admin-affiliate-portal-preview-no-affiliate">';
            echo '<h2>' . __('No Affiliate Selected. Select an affiliate above to preview here. If you have no affiliates, please register at least one to use this tool.', 'solid-affiliate') . '</h2>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="wrap">';
            echo ('<h2>' . __('Affiliate Portal - Admin Preview', 'solid-affiliate') . '</h2>');
            echo ('<p>' . __('This is a preview of the affiliate portal, as if the affiliate themselves were viewing their own portal. You can select any affiliate to see their portal.', 'solid-affiliate') . '</p>');
            echo ('<p>' . __('Note, this preview does not include the theme or styling of the page where your portals are embeded. The affiliates may see a differently styled portal due to the theme of your site, but the data will be exactly the same.', 'solid-affiliate') . '</p>');
            $schema = new Schema([
                'entries' => [
                    'admin_portal_preview_affiliate_id' => new SchemaEntry([
                        'type' => 'bigint',
                        'length' => 20,
                        'required' => true,
                        'display_name' => __('Affiliate to preview', 'solid-affiliate'),
                        'form_input_description' => __("Select an affiliate to preview their portal", 'solid-affiliate'),
                        'form_input_type_override' => 'affiliate_select',
                        'show_on_new_form' => true,
                        'show_on_edit_form' => true,
                        'show_list_table_column' => true,
                        'key' => true,
                        'is_csv_exportable' => true
                    ]),
                ]
            ]);
            echo FormBuilder::build_form($schema, 'edit', (object)['admin_portal_preview_affiliate_id' => $affiliate_id]);
            echo ('<button type="submit" id="solid-affiliate_change-affiliate-preview-id" class="button action ">' . __('Change Affiliate', 'solid-affiliate') . '</button>');
            echo '<div class="solid-affiliate_admin-affiliate-portal-preview">';
            echo AffiliatePortalController::render_affiliate_dashboard_with_data($maybe_affiliate, true);
            echo '</div>';
            echo '</div>';
        }
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
                case 'Approve':
                    $affiliates = Affiliate::find_many($ids);
                    Affiliate::updateInstances($affiliates, ['status' => 'approved']);
                    break;
                case 'Reject':
                    $affiliates = Affiliate::find_many($ids);
                    Affiliate::updateInstances($affiliates, ['status' => Referral::STATUS_REJECTED]);
                    break;
                case 'Delete':
                    $delete_url = URLs::delete(Affiliate::class, true);
                    ControllerFunctions::handle_redirecting_and_exit($delete_url, [], [], 'admin', ['id' => $ids]);
                    break;
                default:
                    ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', [__("Invalid bulk action", 'solid-affiliate') . ": " . __($bulk_action_string, 'solid-affiliate')], [], 'admin');
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
            'page' => Affiliate::ADMIN_PAGE_KEY,
            'class_string' => Affiliate::class,
            'success_msg_create' => __('Successfully created Affiliate', 'solid-affiliate'),
            'success_msg_update' => __('Successfully updated Affiliate', 'solid-affiliate'),
            'error_msg' => __('There was an error.', 'solid-affiliate'),
            'schema' => Affiliate::schema_with_custom_registration_data(),
            'upsert_function' =>
            /** 
             * @param array<string, mixed> $args
             * @return Either<int> */
            function ($args) {
                $eitherId = Affiliate::upsert($args);
                if ($eitherId->isRight) {
                    $maybe_affiliate = Affiliate::find($eitherId->right);
                    if ($maybe_affiliate instanceof Affiliate) {
                        $wp_user_id = $maybe_affiliate->user_id;
                        Roles::add_affiliate_role_to_wp_user_ids([$wp_user_id]);
                    }
                }
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
            'page' => Affiliate::ADMIN_PAGE_KEY,
            'success_msg' => __('Successfully deleted Affiliate(s)', 'solid-affiliate'),
            'error_msg' => __('There was an error.', 'solid-affiliate'),
            'delete_by_id_function' =>
            /**
             * @param int $id
             * 
             * @return Either<int>
             */
            function ($id) {
                return Affiliate::delete($id, true);
            },
        ];

        ControllerFunctions::generic_delete_handler($_POST, $delete_handler_args);
        ////////////////////////////////////////////////////////////////////////
    }
}
