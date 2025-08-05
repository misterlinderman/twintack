<?php

namespace SolidAffiliate\Views\Shared;

class SolidTooltipView
{
    /**
     * @param string $tooltip_body
     * @param null|string $icon_html
     * @return string
     */
    public static function render($tooltip_body, $icon_html = null)
    {
        $default_icon_html = '
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
<g clip-path="url(#clip0_449_134)">
<path d="M7.99967 1.33325C4.31967 1.33325 1.33301 4.31992 1.33301 7.99992C1.33301 11.6799 4.31967 14.6666 7.99967 14.6666C11.6797 14.6666 14.6663 11.6799 14.6663 7.99992C14.6663 4.31992 11.6797 1.33325 7.99967 1.33325ZM8.66634 12.6666H7.33301V11.3333H8.66634V12.6666ZM10.0463 7.49992L9.44634 8.11325C8.96634 8.59992 8.66634 8.99992 8.66634 9.99992H7.33301V9.66658C7.33301 8.93325 7.63301 8.26659 8.11301 7.77992L8.93967 6.93992C9.18634 6.69992 9.33301 6.36659 9.33301 5.99992C9.33301 5.26659 8.73301 4.66659 7.99967 4.66659C7.26634 4.66659 6.66634 5.26659 6.66634 5.99992H5.33301C5.33301 4.52659 6.52634 3.33325 7.99967 3.33325C9.47301 3.33325 10.6663 4.52659 10.6663 5.99992C10.6663 6.58659 10.4263 7.11992 10.0463 7.49992Z" fill="#A7A7A7"/>
</g>
<defs>
<clipPath id="clip0_449_134">
<rect width="16" height="16" fill="white"/>
</clipPath>
</defs>
</svg>

        ';

        $icon_html = is_null($icon_html) ? $default_icon_html : $icon_html;
        ob_start();
?>
        <div data-html="true" data-sld-tooltip-content="<?php echo ($tooltip_body) ?>" class="sld-tooltip">
            <?php echo ($icon_html) ?>
        </div>
<?php
        return ob_get_clean();
    }

    /**
     * Render a tooltip but with styling for the content.
     *
     * @param string $heading
     * @param string $sub_heading
     * @param string $body
     * @param string $hint
     * 
     * @return string
     */
    public static function render_pretty($heading, $sub_heading, $body, $hint)
    {
        $tooltip_body = self::_render_pretty_tooltip_body($heading, $sub_heading, $body, $hint);
        $tooltip_body = (esc_html(stripslashes($tooltip_body)));
        return self::render($tooltip_body);
    }

    /**
     * Return the tooltip body for the pretty tooltip.
     *
     * @param string $heading
     * @param string $sub_heading
     * @param string $body
     * @param string $hint
     * @param string $max_width
     * 
     * @return string
     */
    public static function _render_pretty_tooltip_body($heading, $sub_heading, $body, $hint, $max_width = '800px')
    {
        $tooltip_body = "
        <div class='sld-tooltip-box' style='max-width: {$max_width}'>
            <p class='sld-tooltip_heading'>{$heading}</p>
            <p class='sld-tooltip_sub-heading'>{$sub_heading}</p>
            <p class='sld-tooltip_body'>{$body}</p>
            <p class='sld-tooltip_hint'>{$hint}</p>
        </div>
        ";

        return $tooltip_body;
    }
}
