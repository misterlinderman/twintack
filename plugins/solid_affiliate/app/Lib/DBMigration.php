<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Models\Referral;

/**
 * The purpose of this class is to handle Database migrations and upgrades.
 * 
 * As Solid Affiliate evolves, the underlying database schema may change.
 * 
 * When warranted, we need to handle "upgrade" logic such as backfilling new columns, etc.
 */
class DBMigration
{
    const CURRENT_VERSION_OF_PLUGIN = "3.1.0";
    const OPTIONS_KEY = 'sld_already_executed_migrations_at_version';

    public static function register_hooks(): void
    {
        add_action('solid_affiliate/ct_init', [self::class, 'run']);
    }

    /**
     * @return array<string, callable>
     */
    public static function migration_map()
    {
        return [
            '2.1.0' => [self::class, 'migration_2_1_0']
        ];
    }

    /**
     * This is the main function, it will run any migrations which should be ran.
     *
     * @return void
     */
    public static function run()
    {
        // Use a transient to check if a migration is already in progress
        if (get_transient('solid_affiliate_migration_lock')) {
            return; // Exit if a migration is already running
        }

        // Set the transient to lock the migration process
        set_transient('solid_affiliate_migration_lock', true, 300); // Lock for 5 minutes

        try {
            // Get the next migrations id if it's not false, run it, then check again for the next one and repeat.
            $already_executed_migrations_at_version = self::get_already_executed_migrations_at_version();
            $current_version_of_plugin = self::CURRENT_VERSION_OF_PLUGIN;

            $migrations_to_run = self::get_migrations_to_run($already_executed_migrations_at_version, $current_version_of_plugin);

            foreach ($migrations_to_run as $version => $migration) {
                try {
                    call_user_func($migration);
                    self::set_already_executed_migrations_at_version($version);
                } catch (\Exception $e) {
                    // Log the error and continue with the next migration
                    error_log("Solid Affiliate - Migration $version failed: " . $e->getMessage());
                    continue;
                }
            }
        } catch (\Exception $e) {
            // Log any general errors in the migration process
            error_log("Solid Affiliate - Migration process failed: " . $e->getMessage());
        } finally {
            // Always remove the transient after the process is done
            delete_transient('solid_affiliate_migration_lock');
        }
    }

    /**
     * Simply returns the version number that the migrations were last run at.
     *
     * @return string
     */
    public static function get_already_executed_migrations_at_version()
    {
        return (string)get_option(self::OPTIONS_KEY, '0.0');
    }

    /**
     * @param string $version
     */
    public static function set_already_executed_migrations_at_version($version): bool
    {
        return update_option(self::OPTIONS_KEY, $version);
    }

    /**
     * Returns the filtered array migrations that should be ran.
     * 
     * Notes:
     * - I think this logic is a function of 
     *   1) the current version of the plugin, and
     *   2) the version of the plugin which migrations have be ran for in the past (or false, if they never have)
     * 
     *   $already_executed_migrations_at_version <= (migrations to run) <= $current_version_of_plugin
     * 
     *   examples: 
     *   "0.0", "2.1.0"
     *   => "2.1.0"
     * 
     *   "1.5.1", "2.2.0"
     *   => "2.1.0"
     * 
     * 
     * Things to consider:
     * - Never run migrations which are a higher version than the current installation of Solid Affiliate 
     *   (if someone installs an old version, for example)
     * - Never re-run the same migrations twice.
     * 
     * @param string $already_executed_migrations_at_version
     * @param string $current_version_of_plugin
     * 
     * @return array<string, callable>
     */
    public static function get_migrations_to_run($already_executed_migrations_at_version, $current_version_of_plugin)
    {
        $migrations_mapping = self::migration_map();
        $migrations_to_run = [];

        foreach ($migrations_mapping as $version => $migration) {
            if (
                version_compare($version, $already_executed_migrations_at_version, '>') &&
                version_compare($version, $current_version_of_plugin, '<=')
            ) {
                $migrations_to_run[$version] = $migration;
            }
        }

        return $migrations_to_run;
    }

    /**
     * This migration handles updating the Referrals table. 
     * As part of version 2.1.0, we removed the need for the 'referral_type'
     * column and need to update the 'referral_source' column for some rows.
     * 
     * Referrals WHERE referral_type == 'subscription_renewal' 
     *   -> set referral_source to 'subscription_renewal'
     * 
     * @global \wpdb $wpdb
     *
     * @return void
     */
    public static function migration_2_1_0()
    {
        global $wpdb;
        if (!class_exists('\SolidAffiliate\Models\Referral')) {
            return;
        }

        $table_name = $wpdb->prefix . Referral::TABLE;

        $wpdb->query(
            (string)$wpdb->prepare(
                "UPDATE $table_name SET referral_source = %s WHERE referral_type = %s",
                'subscription_renewal',
                'subscription_renewal'
            )
        );
    }
}
