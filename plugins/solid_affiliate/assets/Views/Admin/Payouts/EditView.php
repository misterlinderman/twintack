<?php

namespace SolidAffiliate\Views\Admin\Payouts;

use SolidAffiliate\Controllers\PayoutsController;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Models\Payout;

class EditView
{
    /**
     * @param object $item
     * @return string
     */
    public static function render($item)
    {
        $singular = __('Payout', 'solid-affiliate');
        $form_id = 'payouts-edit';
        $schema = Payout::schema();
        $nonce = PayoutsController::NONCE_SUBMIT_PAYOUT;
        $submit_action = PayoutsController::POST_PARAM_SUBMIT_PAYOUT;
        $form = FormBuilder::render_crud_form_edit($schema, $submit_action, $nonce, $form_id, $singular, $item);
        ob_start();
?>

        <div class="wrap">
            <h1><?php echo sprintf(__('Update %1$s', 'solid-affiliate'), $singular); ?></h1>
            <?php echo $form ?>
        </div>
<?php
        return ob_get_clean();
    }
}
