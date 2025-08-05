<?php

namespace SolidAffiliate\Lib;

use DateTime;
use DateTimeZone;
use GuzzleHttp\Promise\Create;
use NumberFormatter;
use SolidAffiliate\Controllers\PayAffiliatesController;
use SolidAffiliate\Lib\Formatters as LibFormatters;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Models\BulkPayout;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Views\Shared\SolidTooltipView;
use SolidAffiliate\Lib\MikesDataModel;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Creative;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Models\StoreCreditTransaction;

class Formatters
{
    /**
     * TODO add a currency param, with a default getting it from settings?
     * Make this work for more than just dollar.
     * 
     * @param mixed $val
     * @return string 
     */
    public static function money($val)
    {
        if (function_exists('wc_price')) {
            $currency_code = WooCommerceIntegration::get_current_currency();
            $output = wc_price((float)$val, ['currency' => $currency_code]);
            return $output;
        } else {
            $currency_prefix = '$';

            $output = $currency_prefix . number_format((float)$val, 2);
            return $output;
        }
    }

    /**
     * Uses WooCommerce HTML currency symbol to display money as a 2 decimal float preceded by a currency symbol.
     *
     * @param float $val
     *
     * @return string
     */
    public static function raw_money_str($val)
    {
        # NOTE: Relies on WooCommerce `get_woocommerce_currency_symbol` which currently returns the HTML entity.
        #       Do we want to copy that code over so that if they change the way they do it,
        #       then we don't start trying to decode non HTML entities?
        $symbol = html_entity_decode(WooCommerceIntegration::get_current_currency_symbol());
        return $symbol . number_format(Validators::currency_amount_float($val), 2);
    }

    /**
     * @param mixed $val
     * @return string 
     */
    public static function percentage($val)
    {
        return (string)$val . '%';
    }

    /**
     * Undocumented function
     *
     * @param mixed $val
     * @param 'flat'|'percentage' $rate_type
     * 
     * @return string
     */
    public static function commission_rate($val, $rate_type)
    {
        if (!in_array($rate_type, ['flat', 'percentage'])) {
            return (string)$val;
        }

        if ($rate_type === 'percentage') {
            return self::percentage($val);
        }

        /** @psalm-suppress RedundantConditionGivenDocblockType */
        if ($rate_type === 'flat') {
            return Formatters::money($val);
        }

        return (string)$val;
    }

    /**
     * Returns the site's date formatting setting or a default if there isn't one.
     *
     * @return string
     */
    public static function site_date_format()
    {
        $default_date_format = 'F j, Y';
        return Validators::str(get_option('date_format', $default_date_format));
    }

    /**
     * Returns time formatted for SQL queries
     *
     * @param string $time
     *
     * @return string
     */
    public static function simple_date($time = 'now')
    {
        return date(self::site_date_format(), strtotime($time));
    }

    /**
     * Takes a timestamp and returns the localized and formatted datetime.
     * The `wp_date()` function uses `wp_timezone()` which is being pulled from the global WordPress "Timezone" setting.
     * If the `wp_date()` localization fails, then it defaults to `self::date()`.
     *
     * @see https://make.wordpress.org/core/2019/09/23/date-time-improvements-wp-5-3/
     *
     * @param string $timestamp
     *
     * @return string
     */
    public static function localized_datetime($timestamp)
    {
        if (!function_exists('wp_date')) {
            return self::_localized_date($timestamp);
        }

        $maybe_datetime = wp_date(
            self::_date_format_for_localized_timestamp(),
            strtotime($timestamp)
        );

        if ($maybe_datetime) {
            return $maybe_datetime;
        } else {
            return self::_localized_date($timestamp);
        }
    }

    /**
     * Replaces the localized_datetime function for WordPress installations < 5.3
     *
     * @param string $time
     *
     * @return string
     */
    private static function _localized_date($time = 'now')
    {
        $tz = Validators::str(get_option('timezone_string'));
        $format = self::_date_format_for_localized_timestamp();
        if (Utils::is_empty($tz)) {
            return date($format, strtotime($time));
        }
        /** @psalm-suppress ArgumentTypeCoercion */
        $dt = new DateTime('now', new DateTimeZone($tz));
        $dt->setTimestamp(strtotime($time));
        return $dt->format($format);
    }

    /**
     * @param string $status
     * @param boolean $include_icon_html
     * 
     * @return string
     */
    public static function status_old($status, $include_icon_html = true)
    {
        $approved = '#fdd9d9';
        // $pending = '#2dccff';
        $rejected = '#ff3838';

        $default = '#777';

        switch ($status) {
            case 'draft':
                $color = 'yellow';
                break;
            case 'active':
            case 'approved':
            case 'paid':
            case 'enabled':
            case BulkPayout::SUCCESS_STATUS:
                $color = $approved;
                break;
            case 'rejected':
            case BulkPayout::FAIL_STATUS:
                $color = $rejected;
                break;
            case 'pending':
            case 'unpaid':
            case 'disabled':
            case BulkPayout::PROCESSING_STATUS:
                $color = $default;
                break;
            default:
                $color = $default;
                break;
        }


        $capitalized_status = ucfirst($status);

        return "<span style='text-shadow: 0px 0px 1px black; font-size: 25px; vertical-align: sub; color: {$color}'>&bull;</span> " . __($capitalized_status, 'solid-affiliate');
    }

    /**
     * @param string $status
     * @param class-string<MikesDataModel> $model_class
     * @param 'admin'|'non_admin' $admin_or_non_admin
     * 
     * @return string
     */
    public static function status_with_tooltip($status, $model_class, $admin_or_non_admin)
    {
        $generic_explanation = '';
        $short_name = substr(strrchr((string)$model_class, "\\"), 1);   // Get the class name without the namespace.
        switch ($model_class) {
            case Affiliate::class:
                switch ($status) {
                    case Affiliate::STATUS_APPROVED:
                        $generic_explanation = __('affiliate has been approved and can actively earn referrals.', 'solid-affiliate');
                        break;
                    case Affiliate::STATUS_PENDING:
                        $generic_explanation = __('affiliate is awaiting approval. The affiliate can access their affiliate portal, use their links and creatives to generate visits, but cannot generate referrals until approved.', 'solid-affiliate');
                        break;
                    case Affiliate::STATUS_REJECTED:
                        // TODO make pending affiliates not able to generate referrals; block them out of the affiliate portal.
                        $generic_explanation = __('affiliate was rejected by an administrator. This affiliate cannot access their affiliate portal, and cannot generate referrals.', 'solid-affiliate');;
                        break;
                }
                break;
            case Referral::class:
                switch ($status) {
                    case Referral::STATUS_UNPAID:
                        $generic_explanation = __('referral has been approved and commission is owed to the referring affiliate.', 'solid-affiliate');
                        break;
                    case Referral::STATUS_PAID:
                        $generic_explanation = __('referral is approved and the commission has been paid to the associated affiliate. This status was marked as paid automatically by the Solid Affiliate > Pay Affiliates tool, or manually by an administrator.', 'solid-affiliate');
                        break;
                    case Referral::STATUS_REJECTED:
                        $generic_explanation = __('referral has been rejected either manually by an admin, or automatically by the Solid Affiliate > WooCommerce integration. <br><br> Reasons for automatic rejection include: the underlying purchase failed, or was canceled or refunded <em>before</em> the referral was paid. <br><br> This referral will not be displayed within the affiliate portals.', 'solid-affiliate');;
                        break;
                    case Referral::STATUS_DRAFT:
                        $generic_explanation = __('referral is pending due to one of the following reasons: the underlying order status is still <em>pending</em> or <em>on hold</em> or <em>checkout-draft</em>. The referral status will be updated automatically once the underlying order status is updated. <br><br> This referral will not be displayed within the affiliate portals, unless you explicitly tell Solid Affiliate to do so in Solid Affiliate > Settings > Affiliate Portal Settings.', 'solid-affiliate');
                        break;
                }
                break;
            case Payout::class:
                switch ($status) {
                    case Payout::STATUS_PAID:
                        $generic_explanation = __('payout has been been marked as paid automatically by the Solid Affiliate > Pay Affiliates tool.', 'solid-affiliate');
                        break;
                    case Payout::STATUS_FAILED:
                        $generic_explanation = __('payout has been marked as failed, meaning the affiliate never received the payment or the payment was returned to you.', 'solid-affiliate');
                        break;
                }
                break;
            case BulkPayout::class:
                switch ($status) {
                    case BulkPayout::PROCESSING_STATUS:
                        $generic_explanation = __('bulk payout is still processing. In the case of a PayPal API payout, PayPal returned a processing status.', 'solid-affiliate');
                        break;
                    case BulkPayout::SUCCESS_STATUS:
                        $generic_explanation = __('bulk payout was succesfully sent. In the case of a PayPal API payout, PayPal returned a success status.', 'solid-affiliate');
                        break;
                    case BulkPayout::FAIL_STATUS:
                        $generic_explanation = __('bulk payout failed. In the case of a PayPal API payout, PayPal returned a fail status. If you see this status, please contact the Solid Affiliate support team.', 'solid-affiliate');
                        break;
                }
                break;
            case Creative::class:
                switch ($status) {
                    case Creative::STATUS_ACTIVE:
                        $generic_explanation = __('creative is active and will appear in the affiliate portals.', 'solid-affiliate');
                        break;
                    case Creative::STATUS_INACTIVE:
                        $generic_explanation = __('creative is inactive and will not appear in the affiliate portals.', 'solid-affiliate');
                        break;
                }
                break;
            default:
                $generic_explanation = '';
                break;
        }

        $tooltip_body = SolidTooltipView::_render_pretty_tooltip_body(
            __('Status Explanation', 'solid-affiliate'),
            self::status($status),
            // __('sub heading', 'solid-affiliate'),
            __("{$short_name} status of", 'solid-affiliate') . ' <strong>' . $status . '</strong> ' . __('means that the', 'solid-affiliate') . ' ' . $generic_explanation,
            __('To learn more, visit ', 'solid-affiliate') . "<a href='https://docs.solidaffiliate.com/statuses/' target='_blank'>" . __('status documentation', 'solid-affiliate') . '</a>.',
            '300px'
        );

        return self::status($status, true, $tooltip_body);
    }

    /**
     * @param string $status
     * @param boolean $include_icon_html
     * @param string|null $tooltip_body
     * 
     * @return string
     */
    public static function status($status, $include_icon_html = true, $tooltip_body = null)
    {
        $approved = '#fff';
        $rejected = '#fff';
        $default = '#111127';

        switch ($status) {
            case 'active':
            case 'approved':
            case 'paid':
            case 'enabled':
            case BulkPayout::SUCCESS_STATUS:
                $color = $approved;
                $background = '#2FA449';
                break;
            case 'rejected':
            case BulkPayout::FAIL_STATUS:
                $color = $rejected;
                $background = '#BC4B43';
                break;
            case 'pending':
            case 'unpaid':
            case 'disabled':
            case BulkPayout::PROCESSING_STATUS:
                $color = $default;
                $background = '#E1E1E1';
                break;
            default:
                $color = '#ffffff';
                $background = '#AEAEAE';
                break;
        }


        $capitalized_status = ucfirst($status);

        $random = RandomData::string(6);

        $style = "
        <style>
        .referral-status_pill-$random {
            font-family: IBM Plex Mono, monospace;
            background:{$background};
            color:{$color};
            display:inline-block;
            padding: 6px;
            border-radius: 4px;
            position:relative;
            vertical-align: middle;
            font-size: 12px;
            line-height: 12px;
            width: auto;
        }

        .referral-status_pill-$random:hover {
            cursor: pointer;
        }

        </style>
        ";
        ob_start();
?>
        <?php if (is_null($tooltip_body)) { ?>
            <div class='referral-status_pill-<?php echo ($random) ?>'>
            <?php } else { ?>
                <div class='referral-status_pill-<?php echo ($random) ?> sld-tooltip' data-html='true' data-sld-tooltip-content="<?php echo (esc_html($tooltip_body)) ?>">
                <?php }; ?>
                <span class='referral-status-<?php echo ($random) ?>'> <?php _e($capitalized_status, 'solid-affiliate') ?></span>
                </div>
        <?php
        $status_html = ob_get_clean();
        return $style . $status_html;
    }

    /**
     * @param string $payout_method
     * 
     * @return string
     */
    public static function payout_method($payout_method)
    {
        if ($payout_method == PayAffiliatesController::BULK_PAYOUT_METHOD_PAYPAL) {
            return 'PayPal';
        }

        return self::humanize($payout_method);
    }

    /**
     * Attempts to make a string looks good for labels and such.
     * 
     * Example:
     * humanize("start_date");
     *   => "Start Date";
     *
     * @param string $str
     * @param bool $capitalize
     * @param string $separator
     * @param array<string> $forbidden_words
     * 
     * @return string
     */
    public static function humanize($str, $capitalize = true, $separator = '_', $forbidden_words = [])
    {
        if ($str == 'id') {
            return 'ID';
        }

        $humanized = trim(strtolower((string) preg_replace(['/([A-Z])/', sprintf('/[%s\s]+/', $separator)], ['_$1', ' '], $str)));
        $humanized = trim(str_replace($forbidden_words, '', $humanized));

        $humanized = $capitalize ?  ucwords($humanized, " ") : $humanized;
        return __($humanized, 'solid-affiliate');
    }

    /**
     * @param string $str
     * 
     * @return string
     */
    public static function slug_to_title($str)
    {
        $str = self::humanize($str, true, '-', []);
        $str = self::humanize($str, true, '_', []);

        return $str;
    }

    /**
     * Uses the global WordPress date and time formats to build out a timestamp format string.
     * The default format, which will be used if the settings are not set, looks like:
     * -> 'F j, Y g:i a (T)'
     * -> February 15, 2022 5:58 pm (PST)
     *
     * @return string
     */
    private static function _date_format_for_localized_timestamp()
    {
        $wp_date_format = self::site_date_format();
        $default_time_format = 'g:i a';
        $wp_time_format = Validators::str(get_option('time_format', $default_time_format));
        $timezone_format = '(e)';

        return "{$wp_date_format} {$wp_time_format} {$timezone_format}";
    }

    /**
     * Handles formatting custom data values to be shown in list tables.
     *
     * @param array $arr
     * @param string $key
     *
     * @return string
     */
    public static function custom_data_for_view($arr, $key)
    # TODO:3: Could be a formatter callback on SchemaEntry per entry, and a default could live here
    {
        if (isset($arr[$key])) {
            /** @var mixed $val */
            $val = $arr[$key];
            if (is_bool($val)) {
                if ($val === false) {
                    return __('No', 'solid-affiliate');
                } else {
                    return __('Yes', 'solid-affiliate');
                }
            } else if (is_array($val)) {
                return implode(', ', Validators::array_of_coerced_string($val));
            } else {
                return Validators::str($val);
            }
        } else {
            return '';
        }
    }

    /**
     * Default formatter function for all data to be shown in a CSV export.
     *
     * @param mixed $val
     *
     * @return string
     */
    public static function csv_default_formatter($val)
    {
        if (isset($val)) {
            if (is_bool($val)) {
                if ($val === false) {
                    return __('No', 'solid-affiliate');
                } else {
                    return __('Yes', 'solid-affiliate');
                }
            } else if (is_array($val)) {
                return implode(', ', Validators::array_of_coerced_string($val));
            } else {
                return Validators::str($val);
            }
        } else {
            return '';
        }
    }

    /**
     * @param float $amount
     * @param StoreCreditTransaction::TYPE_* $type
     * 
     * @return string
     */
    public static function store_credit_amount($amount, $type)
    {
        $formatted_money = self::money($amount);

        // format it with colors and styling. If it's a debit it's wrapped in a green pill, if it's a credit it's wrapped in a red pill.
        // if its a debit we need to add a + sign to the front of the amount.
        if ($type === StoreCreditTransaction::TYPE_DEBIT) {
            $formatted_money = '<span class="sld-store-credit-transaction-type sld-debit">+' . $formatted_money . '</span>';
        } else {
            $formatted_money = '<span class="sld-store-credit-transaction-type sld-credit">-' . $formatted_money . '</span>';
        }

        return $formatted_money;
    }
}
