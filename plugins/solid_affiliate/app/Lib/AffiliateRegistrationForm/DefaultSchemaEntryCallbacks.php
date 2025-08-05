<?php

namespace SolidAffiliate\Lib\AffiliateRegistrationForm;

use SolidAffiliate\Lib\Utils;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\SchemaEntry;

/**
 * @psalm-import-type FormInputCallbackKeyType from \SolidAffiliate\Lib\VO\SchemaEntry
 * @psalm-import-type EnumOptionsReturnType from \SolidAffiliate\Lib\VO\SchemaEntry
 *
 * @psalm-import-type ControlNumberMinType from \SolidAffiliate\Lib\VO\FormBuilderControl
 * @psalm-import-type ControlNumberMaxType from \SolidAffiliate\Lib\VO\FormBuilderControl
 * @psalm-import-type ControlMaxlengthType from \SolidAffiliate\Lib\VO\FormBuilderControl
 */
class DefaultSchemaEntryCallbacks
{
    /**
     * Returns a SchemaEntry validate_callback function for a form field type.
     *
     * @param FormInputCallbackKeyType $key
     * @param EnumOptionsReturnType $enum_options
     * @param boolean $allow_zero
     * @param array<string, string> $attrs
     *
     * @return callable(mixed):bool
     */
    public static function get_schema_entry_validate_callback($key, $enum_options, $allow_zero, $attrs)
    # TODO:3: ControllerFunctions::_get_required_param_keys_missing_errors validates required fields before the validate_callback is called, so it should not need to be here. But, should we still check against that using the required boolean here and not rely on order dependency?
    {
        $min = Validators::numeric_str(Validators::str_from_array($attrs, 'min'));
        $max = Validators::numeric_str(Validators::str_from_array($attrs, 'max'));
        $maxlength = Validators::numeric_str(Validators::str_from_array($attrs, 'maxlength'));
        return self::_validate_callback_map($key, $enum_options, $allow_zero, $min, $max, $maxlength);
    }

    /**
     * Returns a SchemaEntry sanitize_callback function for a form field type.
     *
     * @param FormInputCallbackKeyType $key
     *
     * @return callable(mixed):mixed
     */
    public static function get_schema_entry_sanitize_callback($key)
    {
        return self::_sanitize_callback_map($key);
    }

    /**
     * Return the default validate callback based on which type and subtype the field is.
     *
     * @param FormInputCallbackKeyType $key
     * @param EnumOptionsReturnType $enum_options
     * @param boolean $allow_zero
     * @param ControlNumberMinType $min
     * @param ControlNumberMaxType $max
     * @param ControlMaxlengthType $maxlength
     *
     * @return callable(mixed):bool
     */
    private static function _validate_callback_map($key, $enum_options, $allow_zero, $min, $max, $maxlength)
    {
        switch ($key) {
            case SchemaEntry::TEXT_TEXT_KEY:
            /**
             * @param mixed $val
             * @return boolean
             */
            return function ($val) use ($maxlength) {
                if (!is_string($val)) {
                    return false;
                }
                if (!Utils::is_empty_but_allow_zero($maxlength) && strlen($val) > intval($maxlength)) {
                    return false;
                }
                return true;
            };
            case SchemaEntry::TEXT_EMAIL_KEY:
            /**
             * @param mixed $val
             * @return boolean
             */
            return function ($val) use ($maxlength) {
                if (!is_string($val)) {
                    return false;
                }
                if (!Utils::is_empty_but_allow_zero($maxlength) && strlen($val) > intval($maxlength)) {
                    return false;
                }
                return !!filter_var($val, FILTER_VALIDATE_EMAIL);
            };
            case SchemaEntry::TEXT_PASSWORD_KEY:
            /**
             * @param mixed $val
             * @return boolean
             */
            return function ($val) use ($maxlength) {
                if (!is_string($val)) {
                    return false;
                }
                if (!Utils::is_empty_but_allow_zero($maxlength) && strlen($val) > intval($maxlength)) {
                    return false;
                }
                return true;
            };
            case SchemaEntry::TEXT_URL_KEY:
            /**
             * @param mixed $val
             * @return boolean
             */
            return function ($val) use ($maxlength) {
                if (!is_string($val)) {
                    return false;
                }
                if (!Utils::is_empty_but_allow_zero($maxlength) && strlen($val) > intval($maxlength)) {
                    return false;
                }
                return !!filter_var($val, FILTER_VALIDATE_URL);
            };
            case SchemaEntry::CHECKBOX_GROUP_KEY:
            /**
             * @param mixed $val
             * @return boolean
             */
            return function ($val) use ($enum_options) {
                if (!is_array($val)) {
                    return false;
                }
                $all = count($val);
                $valid = array_filter($val, function ($item) use ($enum_options) {
                    return in_array($item, SchemaEntry::values_from_enum_options($enum_options), true);
                });
                return $all === count($valid);
            };
            case SchemaEntry::CHECKBOX_SINGLE_KEY:
            /**
             * @param mixed $_val
             * @return boolean
             */
            return function ($_val) {
                return true;
            };
            case SchemaEntry::RADIO_GROUP_KEY:
            /**
             * @param mixed $val
             * @return boolean
             */
            return function ($val) use ($enum_options) {
                return is_string($val) && in_array($val, SchemaEntry::values_from_enum_options($enum_options), true);
            };
            case SchemaEntry::SELECT_KEY:
            /**
             * @param mixed $val
             * @return boolean
             */
            return function ($val) use ($enum_options) {
                return is_string($val) && in_array($val, SchemaEntry::values_from_enum_options($enum_options), true);
            };
            case SchemaEntry::TEXTAREA_KEY:
            /**
             * @param mixed $val
             * @return boolean
             */
            return function ($val) use ($maxlength) {
                if (!is_string($val)) {
                    return false;
                }
                if (!Utils::is_empty_but_allow_zero($maxlength) && strlen($val) > intval($maxlength)) {
                    return false;
                }
                return true;
            };
            case SchemaEntry::NUMBER_KEY:
            /**
             * @param mixed $val
             * @return boolean
             */
            return function ($val) use ($allow_zero, $min, $max) {
                if (!is_int($val)) {
                    return false;
                }
                if (!$allow_zero && Utils::is_number_zero($val)) {
                    return false;
                }
                if (!Utils::is_empty_but_allow_zero($min) && $val < intval($min)) {
                    return false;
                }
                if (!Utils::is_empty_but_allow_zero($max) && $val > intval($max)) {
                    return false;
                }
                return true;
            };
            case SchemaEntry::NONE_KEY:
            /**
             * @param mixed $_val
             * @return boolean
             */
            return function ($_val) {
                return true;
            };
        }
    }

    /**
     * Return the default sanitize callback based on which type and subtype the field is.
     *
     * @param FormInputCallbackKeyType $key
     *
     * @return callable(mixed):mixed
     */
    private static function _sanitize_callback_map($key)
    {
        switch ($key) {
            case SchemaEntry::TEXT_TEXT_KEY:
            /**
             * @param mixed $val
             * @return string
             */
            return function ($val) {
                return trim(Utils::strip_breaking_characters(Validators::str($val)));
            };
            case SchemaEntry::TEXT_EMAIL_KEY:
            /**
             * @param mixed $val
             * @return string
             */
            return function ($val) {
                return trim(Utils::strip_breaking_characters(Validators::str($val)));
            };
            case SchemaEntry::TEXT_PASSWORD_KEY:
            /**
             * @param mixed $val
             * @return string
             */
            return function ($val) {
                return trim(Utils::strip_breaking_characters(Validators::str($val)));
            };
            case SchemaEntry::TEXT_URL_KEY:
            /**
             * @param mixed $val
             * @return string
             */
            return function ($val) {
                return trim(Utils::strip_breaking_characters(Validators::str($val)));
            };
            case SchemaEntry::CHECKBOX_GROUP_KEY:
            /**
             * @param mixed $val
             * @return array<string>
             */
            return function ($val) {
                if (!is_array($val)) {
                    return [];
                }
               return $val;
            };
            case SchemaEntry::CHECKBOX_SINGLE_KEY:
            /**
             * @param mixed $val
             * @return mixed
             */
            return function ($val) {
                return $val;
            };
            case SchemaEntry::RADIO_GROUP_KEY:
            /**
             * @param mixed $val
             * @return string
             */
            return function ($val) {
                return trim(Validators::str($val));
            };
            case SchemaEntry::SELECT_KEY:
            /**
             * @param mixed $val
             * @return string
             */
            return function ($val) {
                return trim(Validators::str($val));
            };
            case SchemaEntry::TEXTAREA_KEY:
            /**
             * @param mixed $val
             * @return string
             */
            return function ($val) {
                return Utils::strip_breaking_characters(Validators::str($val));
            };
            case SchemaEntry::NUMBER_KEY:
            /**
             * @param mixed $val
             * @return int
             */
            return function ($val) {
                # TODO:3: What do we want to do about how php is nonesense with leading zeros? This will strip the leading 0 because otherwise php might change the number on intval. But this still change the value on str coversion depending on the number
                $str = ltrim(Validators::str($val), '0');
                return intval($str);
            };
            case SchemaEntry::NONE_KEY:
            /**
             * @param mixed $val
             * @return mixed
             */
            return function ($val) {
                return $val;
            };
        }
    }
}