<?php

namespace SolidAffiliate\Views\Shared;

class DeleteResourceView
{
    /**
     * Renders delete resource confirmation screen and confirm button with proper form params.
     *
     * @param array{singular_name: string, plural_name: string, form_id: string, nonce: string, submit_action: string, children_classes: array<string>} $configs
     * @param array<int> $ids
     * 
     * @return string
     */
    public static function render($configs, $ids)
    {

        $plural_name = $configs['plural_name'];
        $form_id = $configs['form_id'];
        $nonce = $configs['nonce'];
        $submit_action = $configs['submit_action'];
        $children_classes = $configs['children_classes'];
        $singular_name = $configs['singular_name'];

        ob_start();
?>


        <form method="post" name="<?php echo $form_id ?>" id="<?php echo $form_id ?>">
            <?php wp_nonce_field($nonce); ?>
            <div class="wrap">
                <h1><?php _e('Delete', 'solid-affiliate') ?> <?php echo $plural_name ?></h1>

                <h2><?php _e('You have specified these', 'solid-affiliate') ?> <?php echo $plural_name ?> <?php _e('for deletion', 'solid-affiliate') ?>:</h2>

                    <ol>
                <?php foreach ($ids as $id) { ?>
                        <li style='font-weight: bold;'><input type="hidden" name="id[]" value="<?php echo ($id) ?>"><?php echo ($singular_name); ?> <?php _e('ID', 'solid-affiliate') ?> #<?php echo ($id) ?></li>
                <?php } ?>
                    </ol>

                <input type="hidden" name="delete_option" value="delete">
                <input type="hidden" name="action" value="dodelete">

                <?php if (!empty($children_classes)) {
                    $joined_classes = implode(", ", $children_classes);
                ?>
                    <div class='notice notice-warning'>
                        <p>
                            <strong><?php _e('Warning', 'solid-affiliate') ?></strong> <?php _e('Deleting', 'solid-affiliate') ?> <strong><?php echo ($plural_name); ?></strong> <?php _e('will permanently delete the following associated records', 'solid-affiliate') ?>: <strong><?php echo ($joined_classes); ?></strong>
                        </p>
                    </div>
                <?php }; ?>
                <?php submit_button(__('Confirm Deletion', 'solid-affiliate'), 'primary', $submit_action); ?>
            </div>
        </form>


<?php
        $res = ob_get_clean();
        if ($res) {
            return $res;
        } else {
            return __("Error rendering.", 'solid-affiliate');
        }
    }
}
