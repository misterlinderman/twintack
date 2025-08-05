<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\VO\AddonDescription;
use SolidAffiliate\Lib\VO\CsvExport;
use SolidAffiliate\Lib\VO\ItemCommission;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Creative;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\Visit;

/**
 * @psalm-import-type LoginCredentials from \SolidAffiliate\Lib\GlobalTypes
 * @psalm-import-type FormInputCallbackKeyType from \SolidAffiliate\Lib\VO\SchemaEntry
 * @psalm-import-type EnumOptionsReturnType from \SolidAffiliate\Lib\VO\SchemaEntry
 * @psalm-import-type BulkPayoutLogicRuleType from \SolidAffiliate\Lib\VO\BulkPayoutOrchestrationParams
 * @psalm-import-type Tag from \SolidAffiliate\Lib\Tags
 */
class Validators
{

    /**
     * Validates that the $val is within the $array_of_vals otherwise returns the $default
     * 
     * @template Ta
     * @template Tb
     *
     * @param array<Ta> $array_of_vals
     * @param Tb $default
     * @param mixed $val
     * @return Ta|Tb
     */
    public static function one_of($array_of_vals, $default, $val)
    {
        if (in_array($val, $array_of_vals)) {
            /** @var Ta */
            return $val;
        } else {
            return $default;
        }
    }

    /**
     * @param mixed $val
     * @return value-of<PresetDateRangeParams::DATE_RANGE_OPTIONS>
     */
    public static function one_of_date_range_options($val)
    {
        return self::one_of(PresetDateRangeParams::DATE_RANGE_OPTIONS, 'this_month', $val);
    }

    /**
     * @param mixed $val
     * @return 'flat'|'percentage'
     */
    public static function rate_type($val)
    {
        return self::one_of(['flat', 'percentage'], 'percentage', $val);
    }

    /**
     * @template T
     *
     * @psalm-param array<T|null> $array_of_maybe_null
     * @param array $array_of_maybe_null
     * 
     * @return array<T>
     */
    public static function non_null_array($array_of_maybe_null)
    {
        return array_filter(
            $array_of_maybe_null,
            /** @param mixed $val */
            function ($val) {
                return !is_null($val);
            }
        );
    }

    /**
     * @param mixed $val
     * 
     * @return array
     */
    public static function arr($val)
    {
        if (is_array($val)) {
            return $val;
        } else {
            return (array)$val;
        }
    }

    /**
     * @param mixed $val
     * 
     * @return array<int>
     */
    public static function array_of_int($val)
    {
        $val = Validators::arr($val);

        $val = array_filter(array_map('intval', $val));

        return $val;
    }

    /**
     * @param mixed $val
     * @param string|null $default
     * 
     * @return string
     */
    public static function str($val, $default = null)
    {
        if (is_string($val)) {
            return $val;
        } elseif (isset($default)) {
            return $default;
        } elseif (is_null($val) || is_object($val) || is_scalar($val)) {
            return strval($val);
        } else {
            return '';
        }
    }

    /**
     * @param array $val
     * @param string $key
     *
     * @return string
     */
    public static function str_from_array($val, $key)
    {
        if (isset($val[$key])) {
            return self::str($val[$key]);
        } else {
            return '';
        }
    }

    /**
     * @param array $val
     * @param string $key
     *
     * @return int
     */
    public static function int_from_array($val, $key)
    {
        if (isset($val[$key])) {
            return intval($val[$key]);
        } else {
            return 0;
        }
    }

    /**
     * @param mixed $val
     * @param non-empty-string $default
     *
     * @return non-empty-string
     */
    public static function non_empty_str($val, $default)
    {
        $str = self::str($val);

        if (empty($str)) {
            return $default;
        } else {
            return $str;
        }
    }

    /**
     * @param mixed $val
     * @param numeric-string|null $default
     *
     * @return numeric-string|''
     */
    public static function numeric_str($val, $default = null)
    {
        $str = self::str($val);

        if (is_numeric($str)) {
            return $str;
        } elseif (isset($default)) {
            return $default;
        } else {
            return '';
        }
    }

    /**
     * @param mixed $val
     * 
     * @return array<string>
     */
    public static function array_of_string($val)
    {
        $val = Validators::arr($val);

        $val = array_filter($val, 'is_string');

        return $val;
    }

    /**
     * @param array $val
     * @param string $key
     *
     * @return boolean
     */
    public static function bool_from_array($val, $key)
    # TODO:1: Anything else we should be casting to true or false?
    {
        if (isset($val[$key])) {
            /** @var mixed $value */
            $value = $val[$key];

            if ($value === 'false') {
                return false;
            } else {
                return boolval($value);
            }
        } else {
            return false;
        }
    }

    /**
     * @param mixed $val
     *
     * @return array<string>
     */
    public static function array_of_coerced_string($val)
    {
        $val = Validators::arr($val);

        $val = array_map([self::class, 'str'], $val);

        return $val;
    }

    /**
     * @param mixed $val
     * 
     * @return LoginCredentials
     */
    public static function LoginCredentials($val)
    {
        $default_string = '';
        $val = Validators::array_of_string($val);

        $val = [
            'user_email' => isset($val['user_email']) ? $val['user_email'] : $default_string,
            'user_pass' => isset($val['user_pass']) ? $val['user_pass'] : $default_string,
        ];

        return $val;
    }

    /**
     * @param mixed $val
     * 
     * @return string
     */
    public static function PayoutCurrencyCode($val)
    {
        $default = 'USD';
        $val = Validators::str($val);

        $acceptable_codes = array_map(function ($a) {
            return $a[0];
        }, Settings::SUPPORTED_CURRENCIES);

        if (in_array($val, $acceptable_codes)) {
            return $val;
        } else {
            return $default;
        }
    }

    /**
     * @param mixed $val
     * 
     * @return int
     * @psalm-return positive-int
     */
    public static function positive_int($val)
    {
        $int = intval($val);
        return ($int > 0) ? $int : 1;
    }

    /**
     * @param float $val
     * 
     * @return float
     */
    public static function currency_amount_float($val)
    {
        return round($val, 2);
    }

    /**
     * Will return a proper string for a currency value. PayPal Payouts requires this.
     * examples
     *   Validators::currency_amount_string(1.0) => '1.00' # notice the 2 decimals in the string
     *   Validators::currency_amount_string(2.3123) => '2.30'
     *   Validators::currency_amount_string(2.3) => '2.30'
     * 
     * @param float $val
     * 
     * @return string
     */
    public static function currency_amount_string($val)
    {
        $rounded_float = round($val, 2);
        return number_format($rounded_float, 2);
    }

    /**
     * Validates into a proper string value for a WordPress Post title
     * @param mixed $val
     * 
     * @return string
     */
    public static function post_name($val)
    {
        $val = sanitize_title_with_dashes(trim(self::str($val)));

        return $val;
    }

    /**
     * Undocumented function
     *
     * @param mixed $val
     * 
     * @return ItemCommission[]
     */
    public static function arr_of_item_commission($val)
    {
        $val = self::arr($val);

        return array_filter($val, function ($val) {
            return $val instanceof ItemCommission;
        });
    }

    /**
     * Undocumented function
     *
     * @param mixed $val
     * 
     * @return AddonDescription[]
     */
    public static function arr_of_addon_description($val)
    {
        $val = self::arr($val);

        return array_filter($val, function ($val) {
            return $val instanceof AddonDescription;
        });
    }

    /**
     * Returns an array of CsvExport and filters any other type of out.
     *
     * @param mixed $val
     *
     * @return CsvExport[]
     */
    public static function arr_of_csv_export($val)
    {
        $val = self::arr($val);

        return array_filter($val, function ($val) {
            return $val instanceof CsvExport;
        });
    }

    /**
     * Returns an array of Referral and filters any other type of out.
     *
     * @param mixed $val
     *
     * @return Referral[]
     */
    public static function arr_of_referral($val)
    {
        $val = self::arr($val);

        return array_filter($val, function ($val) {
            return $val instanceof Referral;
        });
    }

    /**
     * Returns an array of Visit and filters any other type of out.
     *
     * @param mixed $val
     *
     * @return Visit[]
     */
    public static function arr_of_visit($val)
    {
        $val = self::arr($val);

        return array_filter($val, function ($val) {
            return $val instanceof Visit;
        });
    }

    /**
     * Returns an array of Payout and filters any other type of out.
     *
     * @param mixed $val
     *
     * @return Payout[]
     */
    public static function arr_of_payout($val)
    {
        $val = self::arr($val);

        return array_filter($val, function ($val) {
            return $val instanceof Payout;
        });
    }

    /**
     * Returns an array of Creative and filters any other type of out.
     *
     * @param mixed $val
     *
     * @return Creative[]
     */
    public static function arr_of_creative($val)
    {
        $val = self::arr($val);

        return array_filter($val, function ($val) {
            return $val instanceof Creative;
        });
    }

    /**
     * @param mixed $val
     * 
     * @return \WC_Product[]
     */
    public static function arr_of_woocommerce_product($val)
    {
        $val = self::arr($val);

        return array_filter($val, function ($val) {
            return $val instanceof \WC_Product;
        });
    }

    /**
     * @param mixed $val
     * 
     * @return \WC_Order[]
     */
    public static function arr_of_woocommerce_order($val)
    {
        $val = self::arr($val);

        return array_filter($val, function ($val) {
            return $val instanceof \WC_Order;
        });
    }

    /**
     * @param mixed  $val
     * 
     * @return string[]
     */
    public static function arr_of_woocommerce_order_status($val)
    {
        $val = self::arr($val);

        return array_filter($val, function ($val) {
            return is_string($val) && in_array($val, WooCommerceIntegration::ORDER_STATUSES);
        });
    }

    /**
     * Converts the get_posts query into an array of valid WC_Coupons.
     *
     * @param \WP_Post[]|int[] $arr
     *
     * @return \WC_Coupon[]
     */
    public static function arr_of_wp_coupon_from_posts($arr)
    {
        return array_filter(array_map(static function ($item) {
            if (!($item instanceof \WP_Post)) {
                $maybe_coupon = new \WC_Coupon($item);
            } else {
                $maybe_coupon = new \WC_Coupon($item->ID);
            }

            return $maybe_coupon;
        }, $arr), static function ($coupon) {
            return $coupon->get_id() != 0;
        });
    }

    /**
     * @param mixed $val
     * 
     * @return \WP_Post[]
     */
    public static function arr_of_wp_post($val)
    {
        $val = self::arr($val);

        return array_filter($val, function ($val) {
            return $val instanceof \WP_Post;
        });
    }

    /**
     * @param mixed $val
     * 
     * @return \WP_User[]
     */
    public static function arr_of_wp_user($val)
    {
        $val = self::arr($val);

        return array_filter($val, function ($val) {
            return $val instanceof \WP_User;
        });
    }

    /**
     * @param mixed $val
     * 
     * @return \WP_Term[]
     */
    public static function arr_of_wp_term($val)
    {
        $val = self::arr($val);

        return array_filter($val, function ($val) {
            return $val instanceof \WP_Term;
        });
    }

    /**
     * @param mixed $val
     * 
     * @return \WP_Site[]
     */
    public static function arr_of_wp_site($val)
    {
        $val = self::arr($val);

        return array_filter($val, function ($val) {
            return $val instanceof \WP_Site;
        });
    }

    /**
     * @param mixed $val
     * 
     * @return Affiliate[]
     */
    public static function arr_of_affiliate($val)
    {
        $val = self::arr($val);

        return array_filter($val, function ($val) {
            return $val instanceof Affiliate;
        });
    }

    /**
     * @param mixed $val
     * 
     * @return BulkPayoutLogicRuleType[]
     */
    public static function arr_of_logic_rule($val)
    {
        $val = self::arr($val);

        $filtered =  array_filter($val, function ($val) {
            // $val needs to have the array shape of a logic rule
            return is_array($val) && isset($val['operator'], $val['field'], $val['value']) && is_array($val['value']);
        });

        $mapped = array_map(function ($val) {
            // $val needs to have the array shape of a logic rule, each value need to be the proper type
            $field = self::one_of(['affiliate_group_id', 'affiliate_id', 'referral_id', 'tag'], 'affiliate_id', $val['field']);
            return [
                'operator' => self::one_of(['exclude', 'include'], 'include', $val['operator']),
                'field' => $field,
                'value' => ($field == 'tag') ? self::array_of_string($val['value']) : self::array_of_int($val['value']),
            ];
        }, $filtered);

        return $mapped;
    }

    /**
     * @param mixed $val
     * 
     * @return Tag[]
     */
    public static function arr_of_tag($val)
    {
        $val = self::arr($val);

        /** @var Tag[] */
        return array_filter($val, function ($val) {
            return is_array($val) && (isset($val['name'], $val['description'], $val['color'])) && is_string($val['name']) && is_string($val['description']) && is_string($val['color']);
        });
    }

    /**
     * Returns a FormInputCallbackKeyType string given a mixed value.
     *
     * @param mixed $val
     *
     * @return FormInputCallbackKeyType 
     */
    public static function form_input_schema_entry_key_str($val)
    {
        $str = self::str($val);

        switch ($str) {
            case SchemaEntry::TEXT_TEXT_KEY:
                return $str;
            case SchemaEntry::TEXT_EMAIL_KEY:
                return $str;
            case SchemaEntry::TEXT_PASSWORD_KEY:
                return $str;
            case SchemaEntry::TEXT_URL_KEY:
                return $str;
            case SchemaEntry::CHECKBOX_GROUP_KEY:
                return $str;
            case SchemaEntry::CHECKBOX_SINGLE_KEY:
                return $str;
            case SchemaEntry::RADIO_GROUP_KEY:
                return $str;
            case SchemaEntry::SELECT_KEY:
                return $str;
            case SchemaEntry::TEXTAREA_KEY:
                return $str;
            case SchemaEntry::NUMBER_KEY:
                return $str;
            default:
                return SchemaEntry::NONE_KEY;
        }
    }

    /**
     * @param mixed $val
     *
     * @return array<string, string>
     */
    public static function custom_form_input_attributes_array($val)
    {
        if (!is_array($val)) {
            return [];
        }

        if (empty($val)) {
            return [];
        }

        return array_reduce(
            array_keys($val),
            /**
             * @param array<empty, empty>|array<string, string> $arr
             * @param int|string $key
             */
            function ($arr, $key) use ($val) {
                $value = self::str($val[$key]);
                $key = self::str($key);
                $arr[$key] = $value;
                return $arr;
            },
            []
        );
    }

    /**
     * @param mixed $val
     *
     * @return EnumOptionsReturnType
     */
    public static function enum_options_array($val)
    {

        if (!is_array($val)) {
            return [];
        }

        if (empty($val)) {
            return [];
        }

        $arr = array_filter(
            $val,
            /**
             * @param mixed $value
             * @param int|string $key
             */
            function ($value, $key) {
                return is_array($value) && is_int($key) && count($value) === 2;
            },
            ARRAY_FILTER_USE_BOTH
        );

        $options = array_reduce(
            $arr,
            /**
             * @param array<empty>|array<array{0: string, 1: string}> $arr
             * @param mixed $value
             */
            function ($arr, $value) {
                $value = self::array_of_coerced_string($value);
                $val = [];
                $val[0] = $value[0];
                $val[1] = $value[1];
                if (empty($value)) {
                    return $arr;
                } else {
                    $arr[] = $val;
                    return $arr;
                };
            },
            []
        );

        return $options;
    }

    /**
     * @param mixed $val
     *
     * @return array{payout_item: array{sender_item_id: string}, transaction_status: string}|false
     */
    public static function paypal_payout_item($val)
    {
        if (isset($val['transaction_status']) && isset($val['payout_item']) && isset($val['payout_item']['sender_item_id'])) {
            /** @psalm-suppress MixedArrayAccess */
            $sender_item_id = self::str($val['payout_item']['sender_item_id']);
            return [
                'transaction_status' => self::str($val['transaction_status']),
                'payout_item' => [
                    'sender_item_id' => $sender_item_id
                ]
            ];
        } else {
            return false;
        }
    }
}
