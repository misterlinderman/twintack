<?php

namespace SolidAffiliate\Lib;

use Exception;
use SolidAffiliate\Controllers\AffiliatesController;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\Visit;

class Utils
{

    /**
     * Undocumented function
     *
     * @param array<array-key, mixed> $post
     * @param string $default
     * @return string
     */
    public static function http_referer_from_POST($post, $default = '/')
    {
        $referrer = (isset($post['_wp_http_referer'])) ? (string)$post['_wp_http_referer'] : $default;
        return $referrer;
    }

    /**
     * Returns time formatted for SQL queries
     *
     * Example: sql_time('1 month ago')
     *
     * @param string $time
     * @return string
     */
    public static function sql_time($time = 'now')
    {
        return date('Y-m-d H:i:s', strtotime($time));
    }

    /**
     * Returns time formatted for HTML input type=date Date Pickers
     *
     * Example: date_picker_time('1 month ago')
     *
     * @param string $time
     * @param int $base_time The timestamp which is used as a base for the calculation of relative dates.
     * @param string $date_format
     * @param bool $offset_for_site_timezone
     *
     * @return string
     */
    public static function date_picker_time($time = 'now', $base_time = 0, $date_format = 'Y-m-d', $offset_for_site_timezone = true)
    {
        if ($base_time === 0) {
            $base_time = time();
        }
        $unix_time = strtotime($time, $base_time);
        // account for the timezone difference between the WordPress site setting and the database GMT setting
        // Use the WordPress timezone setting get_option('timezone_string') to figure out how many hours to add or subtract from the time

        if ($offset_for_site_timezone) {
            $gmt_offset_in_seconds = (float)get_option('gmt_offset') * 3600;
            $unix_time -= $gmt_offset_in_seconds;
            $unix_time = (int)$unix_time;
        }

        $formatted_time = date($date_format, $unix_time);

        return $formatted_time;
    }


    //////////////////////////////////////////
    // Figure out where to put this
    // TODO
    /**
     * Undocumented function
     * 
     * @global \wpdb $wpdb
     *
     * @return void
     */
    public static function delete_or_fix_all_orphaned_database_entries()
    {
        global $wpdb;
        // Find all the records that violate foreign key constraints (the orphaned data)
        // Delete all of them
        // In practive we should be way more careful, if we expose this to our users there should 
        // be a workflow where they see all the data. a find_all_orphaned_database_entries() something like that.

        $wp_users_table = "wp_users";
        $affiliates_table = "wp_" . Affiliate::TABLE;
        $visits_table = "wp_" . Visit::TABLE;
        $referrals_table = "wp_" . Referral::TABLE;
        $payouts_table = "wp_" . Payout::TABLE;

        // Delete Affiliates that don't have Users
        $sql = "delete from {$affiliates_table} where user_id not in (select ID from {$wp_users_table})";
        $wpdb->query($sql);

        // Delete from Visits that don't have Affiliates
        $sql = "delete from {$visits_table} where affiliate_id not in (select id from {$affiliates_table})";
        $wpdb->query($sql);

        // Delete from Referrals that don't have Affiliates
        $sql = "delete from {$referrals_table} where affiliate_id not in (select id from {$affiliates_table})";
        $wpdb->query($sql);

        // Delete from Payouts that don't have Affiliates
        $sql = "delete from {$payouts_table} where affiliate_id not in (select id from {$affiliates_table})";
        $wpdb->query($sql);

        // TODO-v2 referrals without coupons and visits (?)
    }

    /**
     * Undocumented function
     *
     * @param array $where_clause
     * @param PresetDateRangeParams|null $maybe_preset_date_range_params
     * 
     * @return array
     */
    public static function merge_maybe_preset_date_range_params_into_where_clause($where_clause, $maybe_preset_date_range_params)
    {
        if ($maybe_preset_date_range_params instanceof PresetDateRangeParams) {
            $date_range_where_clause = [
                'created_at' => [
                    'operator' => 'BETWEEN',
                    'min' => $maybe_preset_date_range_params->computed_start_date(),
                    'max' => $maybe_preset_date_range_params->computed_end_date()
                ]
            ];
            return array_merge($where_clause, $date_range_where_clause);
        } else {
            return $where_clause;
        }
    }

    /**
     * Undocumented function
     *
     * @param string $str
     * @return bool
     */
    public static function current_page_contains($str)
    {
        if (function_exists('get_current_screen')) {
            $maybe_screen = get_current_screen();
            if ($maybe_screen instanceof \WP_Screen) {
                return (strpos($maybe_screen->id, $str) !== false);
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Wrapper around empty() because empty(' ') return false.
     *
     * @param mixed $val
     *
     * @return boolean
     */
    public static function is_empty($val)
    {
        if (is_string($val) && empty(trim($val))) {
            return true;
        }

        return empty($val);
    }

    /**
     * A way to check if a value is empty, but you want to allow 0-y values.
     *
     * @param mixed $val
     *
     * @return boolean
     */
    public static function is_empty_but_allow_zero($val)
    {
        if (is_string($val)) {
            if (trim($val) === "") {
                return true;
            } else {
                return false;
            }
        }

        if (self::is_number_zero($val)) {
            return false;
        }

        return empty($val);
    }

    /**
     * Whether or not a value is equal to a number representing 0.
     *
     * @param mixed $val
     *
     * @return boolean
     */
    public static function is_number_zero($val)
    {
        return in_array($val, [0, 0.0], true);
    }

    /**
     * Returns true for values that are 0 numbers and their SchemaEntry allows them to be zero.
     *
     * @param Schema<string> $schema
     * @param array<string, mixed> $map
     * @param string $key
     *
     * @return boolean
     */
    public static function is_zero_number_and_not_considered_empty($schema, $map, $key)
    {
        return self::is_number_zero($map[$key]) && SchemaFunctions::check_is_zero_value_allowed($schema, $key);
    }

    /**
     * Replaces characters that break the jquery formBuilder and json decoding with an empty string.
     *
     * @param string $str
     *
     * @return string
     */
    public static function strip_breaking_characters($str)
    {
        return str_replace(['\'', '\\'], '', str_replace('"', "'", $str));
    }

    /**
     * @return boolean
     */
    public static function is_current_page_admin_affiliate_portal_preview()
    {
        return isset($_GET['action']) && strpos((string)$_GET['action'], AffiliatesController::ADMIN_PREVIEW_AFFILIATE_PORTAL_ACTION) !== false;
    }

    /**
     * If a string query param value should be urlencoded based on if it has an & or space, both of which will break the ability to pass form values via the url properly.
     *
     * @param string $val
     *
     * @return boolean
     */
    public static function should_be_url_encoded($val)
    {
        return strpos($val, '&') || strpos($val, ' ') || strpos($val, '+');
    }


    /**
     * Checks if the transient is set, otherwise sets it.
     * Type safe version of get_transient() + set_transient().
     * 
     * So instead of:
     * 
     *  $maybe_val = get_transient($transient_name);
     *  if ($maybe_val === false) {
     *    $val = get_some_data();
     *    set_transient($transient_name, $val, $expiration);
     *  } else {
     *    $val = $maybe_val;
     *  }
     * 
     * You can do:
     * 
     *  $val = solid_transient($transient_name, function() {
     *    return get_some_data();
     *  }, $transient_name, $expiration);
     * 
     * And $val will be type safe, it will be the type of 
     * the return value of get_some_data().
     * 
     * ======================================================
     * 
     * 
     * @template T
     *
     * @param callable():T $callback
     * @param string $transient_name
     * @param integer $expiration
     * 
     * @return T
     */
    public static function solid_transient($callback, $transient_name, $expiration = 3600)
    {
        /** @var T|false */
        $transient_value = get_transient($transient_name);

        if ($transient_value === false) {
            $transient_value = $callback();
            set_transient($transient_name, $transient_value, $expiration);
        }
        return $transient_value;
    }
}
