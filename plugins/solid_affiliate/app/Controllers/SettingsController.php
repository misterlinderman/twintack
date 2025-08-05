<?php

namespace SolidAffiliate\Controllers;


use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\ControllerFunctions;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Views\Admin\Settings\DocumentationView;
use SolidAffiliate\Views\Admin\Settings\RootView;

/**
 * SettingsController
 * 
 * @psalm-suppress PropertyNotSetInConstructor
 */
class SettingsController
{
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
                SettingsController::index();
                break;
            case "render_documentation":
                SettingsController::render_documentation();
                break;
            default:
                SettingsController::index();
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
        $settings_schema = Settings::schema();
        $current_settings = (object)Settings::get_all();

        echo RootView::render($settings_schema, $current_settings);
    }

    /**
     * Renders our Admin Settings documentation page.
     *
     * @return never
     */
    public static function render_documentation()
    {
        $settings_schema = Settings::schema();
        $current_settings = (object)Settings::get_all();

        ob_clean();

        echo DocumentationView::render($settings_schema, $current_settings);
        die();
    }

    /**
     * Handles form submissions for updating our Admin Settings.
     *
     * @return void
     */
    public static function POST_update_admin_settings()
    {
        ////////////////////////////////////////////////////////////////////////
        // variables
        $args = [
            'page' => 'solid-affiliate-settings',
            'error_msg' => 'There was an error.',
            'schema' => Settings::schema()
        ];
        ////////////////////////////////////////////////////////////////////////
        ControllerFunctions::enforce_current_user_capabilities(['read']);

        /** @var string */
        $settings_tab = $_POST['settings_tab'];
        $expected_form_inputs = SchemaFunctions::_keys_that_have_prop_some_value_from_schema($args['schema'], 'settings_tab', $settings_tab);


        $eitherFields = ControllerFunctions::extract_and_validate_POST_params(
            $_POST,
            $expected_form_inputs,
            $args['schema']
        );

        if ($eitherFields->isLeft) {
            ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', $eitherFields->left, [], 'admin');
        } else {
            $valid_settings_fields_to_update = Settings::validator_settings_array($eitherFields->right);
            $eitherSettingsUpdated = Settings::set_many($valid_settings_fields_to_update);

            if ($eitherSettingsUpdated->isLeft) {
                ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', $eitherSettingsUpdated->left, [], 'admin');
            } else {
                ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', [], [SettingsController::SUCCESS_MSG_UPDATE_SETTINGS], 'admin');
            }
        }
    }
}
