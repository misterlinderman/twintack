<?php

namespace SolidAffiliate\Views\Admin\Settings;

use SolidAffiliate\Controllers\CommissionRatesController;
use SolidAffiliate\Controllers\SettingsController;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\SetupWizard;
use SolidAffiliate\Lib\URLs;

class DocumentationView
{


  /**
   * Renders the entire Admin Settings page.
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
    <?php //echo (self::render_settings_nav_tabs($current_tab_key)); 
    ?>


    <?php
    $tab = Settings::TAB_AFFILIATE_PORTAL_AND_REGISTRATION;
    echo '<h2>' . $tab . '</h2>';
    $tab_schema = Settings::schema_for_tab($tab);
    echo DocumentationTabView::render($tab_schema, $current_settings);

    $tab = Settings::TAB_INTEGRATIONS;
    echo '<h2>' . $tab . '</h2>';
    $tab_schema = Settings::schema_for_tab($tab);
    echo DocumentationTabView::render($tab_schema, $current_settings);
    // case 'opt-in_form':
    //   $tab = "Opt-In Form";
    //   $tab_schema = Settings::schema_for_tab($tab);
    //   echo DocumentationTabView::render($tab_schema, $current_settings);
    //   break;

    $tab = Settings::TAB_EMAILS;
    echo '<h2>' . $tab . '</h2>';
    $tab_schema = Settings::schema_for_tab($tab);
    echo DocumentationTabView::render($tab_schema, $current_settings);

    $tab = Settings::TAB_MISC;
    echo '<h2>' . $tab . '</h2>';
    $tab_schema = Settings::schema_for_tab($tab);
    echo DocumentationTabView::render($tab_schema, $current_settings);

    $tab = Settings::TAB_RECURRING_REFERRALS;
    echo '<h2>' . $tab . '</h2>';
    $tab_schema = Settings::schema_for_tab($tab);
    echo DocumentationTabView::render($tab_schema, $current_settings);

    $tab = Settings::TAB_GENERAL;
    echo '<h2>' . $tab . '</h2>';
    $tab_schema = Settings::schema_for_tab($tab);
    echo DocumentationTabView::render($tab_schema, $current_settings);
    ?>


    <?php
    $res = ob_get_clean();
    if ($res) {
      return $res;
    } else {
      return __("Error rendering documentation.", 'solid-affiliate');
    }
  }

  /**
   * @param string|null $current_tab
   * @return string
   */
  public static function render_settings_nav_tabs($current_tab)
  {
    ob_start();
    ?>
    <nav class="nav-tab-wrapper">
      <a href="?page=solid-affiliate-settings" class="nav-tab <?php if ($current_tab === null) : ?>nav-tab-active<?php endif; ?>"><?php _e('General', 'solid-affiliate') ?></a>
      <a href="?page=solid-affiliate-settings&tab=affiliate_portal" class="nav-tab <?php if ($current_tab === 'affiliate_portal') : ?>nav-tab-active<?php endif; ?>"><?php _e('Affiliate Portal & Registration', 'solid-affiliate') ?></a>
      <a href="?page=solid-affiliate-settings&tab=integrations" class="nav-tab <?php if ($current_tab === 'integrations') : ?>nav-tab-active<?php endif; ?>"><?php _e('Integrations', 'solid-affiliate') ?></a>
      <a href="?page=solid-affiliate-settings&tab=emails" class="nav-tab <?php if ($current_tab === 'emails') : ?>nav-tab-active<?php endif; ?>"><?php _e('Emails', 'solid-affiliate') ?></a>
      <a href="?page=solid-affiliate-settings&tab=misc" class="nav-tab <?php if ($current_tab === 'misc') : ?>nav-tab-active<?php endif; ?>"><?php _e('Misc', 'solid-affiliate') ?></a>
      <a href="?page=solid-affiliate-settings&tab=recurring_referrals" class="nav-tab <?php if ($current_tab === 'recurring_referrals') : ?>nav-tab-active<?php endif; ?>"><?php _e('Subscription Renewal Referrals', 'solid-affiliate') ?></a>
    </nav>

<?php
    $res = ob_get_clean();
    return $res;
  }
}
