<?php

namespace SolidAffiliate\Lib;

/**
 * Example script about how to setup custom tables with CT.
 *
 * Be sure to replace all instances of 'yourprefix_' with your project's prefix.
 * http://nacin.com/2010/05/11/in-wordpress-prefix-everything/
 *
 * @author GamiPress <contact@gamipress.com>, Ruben Garcia <rubengcdev@gamil.com>
 */

// Include the Custom Table (CT) library
require_once(__DIR__ . '/../../libraries/ct/init.php');

/* ----------------------------------
 * INITIALIZATION
 * Main example about how to add a custom table through CT
 * Safest way to start everything is through ct_init action
   ---------------------------------- */

use SolidAffiliate\Models\AffiliateMeta;
use SolidAffiliate\Lib\VO\DatabaseTableOptions;
use SolidAffiliate\Lib\WooSoftwareLicense\WOO_SLT_Licence;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliateCustomerLink;
use SolidAffiliate\Models\AffiliateGroup;
use SolidAffiliate\Models\AffiliateProductRate;
use SolidAffiliate\Models\BulkPayout;
use SolidAffiliate\Models\Creative;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\StoreCreditTransaction;
use SolidAffiliate\Models\Visit;

/**
 * Responsibilities:
 * - Everything related to Custom Tables.
 */
class CustomTables
{
  /**
   * Registers hooks.
   *
   * @return void
   */
  public static function register_hooks()
  {
    add_action('solid_affiliate/ct_init', [self::class, 'solid_affiliate_ct_init']);
  }
  /**
   * Undocumented function
   *
   * @return void
   */
  public static function solid_affiliate_ct_init()
  {
    self::register_table(Affiliate::TABLE, Affiliate::ct_table_options());

    self::register_table(AffiliateGroup::TABLE, AffiliateGroup::ct_table_options());

    self::register_table(Referral::TABLE, Referral::ct_table_options());

    self::register_table(Payout::TABLE, Payout::ct_table_options());

    self::register_table(Visit::TABLE, Visit::ct_table_options());

    self::register_table(Creative::TABLE, Creative::ct_table_options());

    self::register_table(BulkPayout::TABLE, BulkPayout::ct_table_options());

    self::register_table(AffiliateProductRate::TABLE, AffiliateProductRate::ct_table_options());

    self::register_table(AffiliateMeta::TABLE, AffiliateMeta::ct_table_options());

    self::register_table(StoreCreditTransaction::TABLE, StoreCreditTransaction::ct_table_options());

    self::register_table(AffiliateCustomerLink::TABLE, AffiliateCustomerLink::ct_table_options());
  }

  /**
   * @param string $table_name
   * @param DatabaseTableOptions $database_table_options
   *
   * @return void
   */
  public static function register_table($table_name, $database_table_options)
  {
    /** @psalm-suppress MixedAssignment
     * @psalm-suppress UndefinedFunction 
     **/
    $ct_table = solid_ct_register_table($table_name, $database_table_options->data_for_ct());
    // Hook on the plugin activation hook to make sure tables will be consistently created on activation.
    /** @psalm-suppress MixedPropertyFetch */
    add_action('solid_affiliate/activation', [$ct_table->db, 'maybe_upgrade']);
  }
}
