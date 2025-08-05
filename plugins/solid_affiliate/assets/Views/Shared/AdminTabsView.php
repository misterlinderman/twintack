<?php

namespace SolidAffiliate\Views\Shared;

class AdminTabsView
{

    /**
     * Undocumented function
     * 
     * @param string $page_key
     * @param array<array{0:string, 1:string}> $tab_tuples - examples: [['settings', 'Admin Settings'], ['extra', 'Extra Settings']]
     * @param string|null $current_tab
     * @param string|null $add_element_id_to_tab_url
     * 
     * @return string
     */
    public static function render($page_key, $tab_tuples, $current_tab, $add_element_id_to_tab_url = null)
    {
        ob_start();
?>
        <!-- Here are our tabs -->
        <nav class="nav-tab-wrapper">
            <?php
            // Just set the first tab as the default.
            $default_tab_key = $tab_tuples[0][0];

            foreach ($tab_tuples as $tuple) {
                list($tab_key, $tab_display_value) = $tuple;
                $href_url = "?page={$page_key}&tab={$tab_key}";
                $href_url = is_null($add_element_id_to_tab_url) ? $href_url : ($href_url . '#' . $add_element_id_to_tab_url);
                // The second half of the statement adds "default" tab functionality.
                $is_active = ($current_tab === $tab_key) || (is_null($current_tab) && ($tab_key === $default_tab_key));
            ?>
                <a href="<?php echo $href_url; ?>" class="nav-tab <?php if ($is_active) : ?>nav-tab-active<?php endif; ?>"><?php echo $tab_display_value ?></a>
            <?php
            }
            ?>
        </nav>

<?php
        return ob_get_clean();
    }
}
