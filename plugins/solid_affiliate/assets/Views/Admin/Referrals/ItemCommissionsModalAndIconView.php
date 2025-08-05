<?php

namespace SolidAffiliate\Views\Admin\Referrals;

use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Lib\RandomData;
use SolidAffiliate\Lib\VO\ItemCommission;
use SolidAffiliate\Views\Shared\SolidTooltipView;

class ItemCommissionsModalAndIconView
{

    /**
     * @param ItemCommission[] $item_commissions 
     * @param int $referral_id
     * @param float $total_commission
     * @return string
     */
    public static function render($item_commissions, $referral_id, $total_commission)
    {
        $modal_id = 'sld-modal-' . RandomData::string(4);

        ob_start();
?>

        <div class="modal micromodal-slide" id="<?php echo ($modal_id); ?>" aria-hidden="true">
            <div class="modal__overlay" tabindex="-1" data-micromodal-close>
                <div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="<?php echo ($modal_id); ?>-title">
                    <header class="modal__header">
                        <h2 class="modal__title" id="<?php echo ($modal_id); ?>-title">
                            <?php _e('Commission Insight', 'solid-affiliate') ?>
                        </h2>
                        <button class="modal__close" aria-label="Close modal" data-micromodal-close></button>
                    </header>
                    <main class="modal__content" id="<?php echo ($modal_id); ?>-content">
                        <?php echo ItemCommissionsView::render($item_commissions, $referral_id, $total_commission) ?>
                    </main>
                </div>
            </div>
        </div>

        <a style="font-size:10px; font-weight : 400; text-decoration:underline; display:block;  font-family:'IBM Plex Mono', monospace; color:var(--sld-primary); vertical-align: top; line-height:18px;" href="javascript:;" data-micromodal-trigger="<?php echo ($modal_id); ?>">
        Details
        </a>
<?php
        return ob_get_clean();
    }
}
