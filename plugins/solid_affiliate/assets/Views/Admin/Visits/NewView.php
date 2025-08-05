<?php

namespace SolidAffiliate\Views\Admin\Visits;

use SolidAffiliate\Controllers\VisitsController;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Models\Visit;

class NewView
{

    /**
     * @return string
     */
    public static function render()
    {
        $singular = __('Visit', 'solid-affiliate');
        $form_id = 'visits-new';
        $schema = Visit::schema();
        $nonce = VisitsController::NONCE_SUBMIT_VISIT;
        $submit_action = VisitsController::POST_PARAM_SUBMIT_VISIT;

        $form = FormBuilder::render_crud_form_new($schema, $submit_action, $nonce, $form_id, $singular);
        ob_start();
?>
        <div class="wrap">
            <h1><?php echo sprintf(__('Add New %1$s', 'solid-affiliate'), $singular); ?></h1>
            <?php echo $form ?>
        </div>

<?php
        return ob_get_clean();
    }
}
