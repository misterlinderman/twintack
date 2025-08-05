<?php

namespace SolidAffiliate\Views\Shared;

use SolidAffiliate\Lib\RandomData;

class SolidModalView
{
    /**
     * @param string $modal_link_content
     * @param string $modal_title
     * @param string $modal_content
     * @return string
     */
    public static function render($modal_link_content, $modal_title, $modal_content)
    {
        $modal_id = 'sld-modal-' . RandomData::string(4);

        ob_start();
?>

        <div class="modal micromodal-slide" id="<?php echo ($modal_id); ?>" aria-hidden="true">
            <div class="modal__overlay" tabindex="-1" data-micromodal-close>
                <div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="<?php echo ($modal_id); ?>-title">
                    <header class="modal__header">
                        <h2 class="modal__title" id="<?php echo ($modal_id); ?>-title">
                            <?php echo($modal_title) ?>
                        </h2>
                        <button class="modal__close" aria-label="Close modal" data-micromodal-close></button>
                    </header>
                    <main class="modal__content" id="<?php echo ($modal_id); ?>-content">
                        <?php echo $modal_content ?>
                    </main>
                    <!-- <footer class="modal__footer">
                        <button class="modal__btn" data-micromodal-close aria-label="Close this dialog window"><?php _e('Close', 'solid-affiliate') ?></button>
                    </footer> -->
                </div>
            </div>
        </div>

        <a style="vertical-align: top" href="javascript:;" data-micromodal-trigger="<?php echo ($modal_id); ?>">
            <?php echo $modal_link_content ?>
        </a>
<?php
        return ob_get_clean();
    }

}
