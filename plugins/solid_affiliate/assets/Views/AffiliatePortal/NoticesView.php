<?php

namespace SolidAffiliate\Views\AffiliatePortal;

class NoticesView
{
    /**
     * @param string $message
     * @param 'success'|'error' $type
     * @return string
     */
    public static function render($message, $type = 'error')
    {
        ob_start();
?>

        <div class="sld-ap-form_notice <?php echo ($type) ?>">
            <div class="sld-ap-form_notice_icon">
                <svg width="15" height="13" viewBox="0 0 15 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M0 13L7.34375 0.34375L14.6562 13H0ZM8 11V9.65625H6.65625V11H8ZM8 8.34375V5.65625H6.65625V8.34375H8Z" />
                </svg>
            </div>
            <div id="#" class="sld-ap-form_notice_text">
                <?php echo ($message) ?>
            </div>
        </div>
<?php
        return ob_get_clean();
    }
}
