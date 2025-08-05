<?php

namespace SolidAffiliate\Views\Admin\Affiliates;

use SolidAffiliate\Controllers\AffiliatesController;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Models\Affiliate;

class NewView
{

    /**
     * @return string
     */
    public static function render()
    {
        $singular = __('Affiliate', 'solid-affiliate');
        $form_id = 'affiliates-new';
        $schema = Affiliate::schema_with_custom_registration_data();
        $nonce = AffiliatesController::NONCE_SUBMIT_AFFILIATE;
        $submit_action = AffiliatesController::POST_PARAM_SUBMIT_AFFILIATE;

        // if there's a user_id and it's proper in the query params, pass in a partial item to the form
        $item = (object)['user_id' => (isset($_REQUEST['user_id']) && ((int)$_REQUEST['user_id'] != 0)) ? (int)$_REQUEST['user_id'] : null];
        $form = FormBuilder::render_crud_form_new($schema, $submit_action, $nonce, $form_id, $singular, $item);
        ob_start();
?>
        <div class="wrap has-form">
            <div class="sld_form-container">
                <div class="sld_form-wrapper">
                    <div class="sld_form-head">
                        <h1><?php echo sprintf(__('Add New %1$s', 'solid-affiliate'), $singular); ?></h1>
                        <p><?php _e('Register an existing user as an affiliate using the form below.', 'solid-affiliate'); ?></p>
                        <div class="notice notice-info inline">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 8V12M12 16H12.01M12 3C19.2 3 21 4.8 21 12C21 19.2 19.2 21 12 21C4.8 21 3 19.2 3 12C3 4.8 4.8 3 12 3Z" stroke="#682914" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <p><b>Note : </b><?php _e('You can register existing users as affiliates directly from their WordPress user profile.', 'solid-affiliate'); ?></p>
                        </div>
                    </div>
                    <?php echo $form ?>
                </div>
            </div>

    <?php
        return ob_get_clean();
    }
}
