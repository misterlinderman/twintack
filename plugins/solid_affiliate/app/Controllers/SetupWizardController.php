<?php

namespace SolidAffiliate\Controllers;

use SolidAffiliate\Lib\Action_Scheduler;
use SolidAffiliate\Lib\SetupWizard;
use SolidAffiliate\Views\Admin\SetupWizard\V2View;

/**
 * SetupWizardController
 * 
 * @psalm-suppress PropertyNotSetInConstructor
 */
class SetupWizardController
{
    /**
     * @return void
     */
    public static function register_hooks()
    {
        add_action('solid_affiliate/create_affiliates_for_existing_users', [SetupWizard::class, 'handle_invite_existing_users'], 10, 2);
        add_action('admin_notices', [self::class, 'admin_notices']);
    }

    /**
     * @return void
     */
    public static function admin_notices()
    {
        if (self::is_there_a_background_job_creating_affiliates()) {
            echo "<div class='notice notice-warning'><p><div class='sld-spinner'></div>" . __("Affiliate accounts are currently being created in the background. This may take a few minutes.", "solid-affiliate") . "</p></div>"; 
        }
    }

    /**
     * @return bool
     */
    public static function is_there_a_background_job_creating_affiliates()
    {
        $actions = Action_Scheduler::get_pending_scheduled_actions([
            'hook' => 'solid_affiliate/create_affiliates_for_existing_users'
        ]);

        return !empty($actions);
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
                SetupWizardController::GET_index();
                break;
            default:
                SetupWizardController::GET_index();
                break;
        }
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public static function GET_index()
    {
        echo V2View::render();
    }
}
