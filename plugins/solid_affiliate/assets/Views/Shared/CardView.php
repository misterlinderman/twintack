<?php

namespace SolidAffiliate\Views\Shared;

class CardView
{

    /**
     * Undocumented function
     * 
     * @param string $title
     * @param string $body
     * 
     * @return string
     */
    public static function render($title, $body)
    {
        ob_start();
?>

        <div class="sld_store-credit-card">
            <div class="title"><?php echo ($title); ?></div>
                <?php echo ($body) ?>
        </div>


<?php
        return ob_get_clean();
    }
}
