<?php

namespace SolidAffiliate\Views\Admin\Settings;

use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Models\Affiliate;

class DocumentationTabView
{

  /**
   * Renders a Tab on Admin Settings page.
   *
   * @param Schema $settings_schema
   * @param Object $current_settings
   * 
   * @return string
   */
  public static function render($settings_schema, $current_settings)
  {
    ob_start();
?>

    <?php
    // $setting_groups = [
    //   'Referral' => Settings::schema_for_settings_group('Referral'),
    //   'Integrations' => Settings::schema_for_settings_group('Integrations')
    // ];

    $setting_groups = Settings::schema_grouped_by_settings_groups($settings_schema);

    foreach ($setting_groups as $group_name => $schema) {
      # code...
    ?>

      <?php
      echo ('<h2>' . $group_name . '</h2>');
      echo (implode(array_values(array_map('self::schema_entry_to_html', $schema->entries))));
      ?>

    <?php
    }
    ?>
    <!-- Settings Group -->

<?php

    $res = ob_get_clean();
    if ($res) {
      return $res;
    } else {
      return __("Error rendering documentation.", 'solid-affiliate');
    }
  }

  /**
   * Undocumented function
   * 
   * @param SchemaEntry $entry
   *
   * @return string
   */
  public static function schema_entry_to_html($entry)
  {
    return '<h4>' . $entry->display_name . '</h4>' .
      '<h5>' . $entry->form_input_description . '</h5>';
  }
}
