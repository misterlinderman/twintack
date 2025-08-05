<?php

namespace SolidAffiliate\Views\Admin\AffiliateGroups;

use SolidAffiliate\Controllers\AffiliateGroupsController;
use SolidAffiliate\Controllers\AffiliatesController;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliateGroup;

class NewView
{

    /**
     * @return string
     */
    public static function render()
    {
        $singular = __('Affiliate Group', 'solid-affiliate');
        $form_id = 'affiliate-groups-new';
        $schema = AffiliateGroup::schema();
        $nonce = AffiliateGroupsController::NONCE_SUBMIT_AFFILIATE;
        $submit_action = AffiliateGroupsController::POST_PARAM_SUBMIT_AFFILIATE_GROUP;

        // initialize the $item with default data if applicable
        $form = FormBuilder::render_crud_form_new($schema, $submit_action, $nonce, $form_id, $singular);

        ob_start();
?>
        <div class="wrap has-form">
            <div class="sld_form-container">
                <div class="sld_form-wrapper">
                    <div class="sld_form-head">
                        <h1><?php echo sprintf(__('Add New %1$s', 'solid-affiliate'), $singular); ?></h1>
                        <div class="notice notice-info inline">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 8V12M12 16H12.01M12 3C19.2 3 21 4.8 21 12C21 19.2 19.2 21 12 21C4.8 21 3 19.2 3 12C3 4.8 4.8 3 12 3Z" stroke="#682914" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                                 <p><?php _e('Affiliates can be added to the group after it has been created.', 'solid-affiliate'); ?></p>
                            </div>
                    </div>
                    <?php echo $form ?>
                </div>
            </div>
        </div>


<?php
        return ob_get_clean();
    }
}
