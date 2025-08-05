<?php

namespace SolidAffiliate\Views\Admin\Creatives;

use SolidAffiliate\Controllers\CreativesController;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Models\Creative;

class EditView
{
    /**
     * @param object $item
     * @return string
     */
    public static function render($item)
    {
        $singular = __('Creative', 'solid-affiliate');
        $form_id = 'creatives-edit';
        $schema = Creative::schema();
        $nonce = CreativesController::NONCE_SUBMIT_CREATIVE;
        $submit_action = CreativesController::POST_PARAM_SUBMIT_CREATIVE;

        $form = FormBuilder::render_crud_form_edit($schema, $submit_action, $nonce, $form_id, $singular, $item);
        ob_start();
?>

        <div class="wrap has-form">
            <div class="sld_form-container">
                <div class="sld_form-wrapper">
                    <div class="sld_form-head">
                        <h1><?php echo sprintf(__('Update %1$s', 'solid-affiliate'), $singular); ?></h1>
                    </div>
                    <?php echo $form ?>
                </div>
            </div>
        </div>
<?php
        return ob_get_clean();
    }
}
