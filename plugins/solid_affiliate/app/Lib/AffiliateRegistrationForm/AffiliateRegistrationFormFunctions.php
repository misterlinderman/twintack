<?php

namespace SolidAffiliate\Lib\AffiliateRegistrationForm;

use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\Utils;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\FormBuilderControl;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliatePortal;
use Seld\JsonLint\JsonParser;

/**
 * @psalm-import-type AffiliateRegistrationFormDataReturnType from \SolidAffiliate\Models\AffiliatePortal
 */
class AffiliateRegistrationFormFunctions
{
    const ERROR_KEY_UNIQUE_NAMES = 'error_unique_names';
    const ERROR_KEY_ENCODE_FAILURE = 'error_failed_to_encode';
    const ERROR_KEY_DECODE_FAILURE = 'error_failed_to_decode';
    const ERROR_KEY_PAST_TYPE_CONFLICT = 'error_past_type_conflict';
    const ERROR_KEY_UNIQUE_LABELS = 'error_uniqe_labels';
    const ERROR_KEY_EMPTY_NAME = 'error_empty_name';
    const ERROR_KEY_EMPTY_LABEL = 'error_empty_label';
    const ERROR_KEY_EMPTY_OR_DUP_ENUM_OPTION = 'error_empty_enum_option';
    const ERROR_KEY_RESERVED_NAME_CONFLICT = 'error_reserved_name_conflict';
    const ERROR_KEYS = [
        self::ERROR_KEY_UNIQUE_NAMES,
        self::ERROR_KEY_ENCODE_FAILURE,
        self::ERROR_KEY_DECODE_FAILURE,
        self::ERROR_KEY_PAST_TYPE_CONFLICT,
        self::ERROR_KEY_UNIQUE_LABELS,
        self::ERROR_KEY_EMPTY_NAME,
        self::ERROR_KEY_EMPTY_LABEL,
        self::ERROR_KEY_EMPTY_OR_DUP_ENUM_OPTION,
        self::ERROR_KEY_RESERVED_NAME_CONFLICT
    ];

    const DOCS_URL = 'https://docs.solidaffiliate.com/affiliate-registration-form#validations_and_rules';

    /**
     * If a SchemaEntry key is a custom affiliate field, by checking if the field is part of the form schema and not part of one of the static affiliate or portal fields.
     *
     * @param string $key
     *
     * @return boolean
     */
    public static function is_a_custom_affiliate_field($key)
    {
        $schema_str = Validators::str(Settings::get(Settings::KEY_CUSTOM_REGISTRATION_FORM_SCHEMA));
        $maybe_json = self::decode_json_string($schema_str);

        if (is_null($maybe_json)) {
            return false;
        }

        $all_keys = array_keys($maybe_json);
        $is_custom = in_array($key, $all_keys) && !in_array($key, Affiliate::RESERVED_AFFILIATE_COLUMNS) && !in_array($key, Affiliate::SCHEMA_ENTRIES_THAT_CAN_ALSO_BE_ON_THE_REGISTRATION_FORM) && !in_array($key, AffiliatePortal::REQUIRED_AFFILIATE_REGISTRATION_ONLY_SCHEMA_ENTRIES);

        if ($is_custom) {
            return true;
        }

        return false;
    }

    /**
     * Sort of mimics how the json blob value will be saved, read, and decoded, returning true if there are no errors.
     * In short, this a "dry run" of an update of the value.
     *
     * @param mixed $data
     *
     * @return boolean
     */
    public static function can_json_str_be_saved_read_and_decoded($data)
    {
        # NOTE: Validate the encoded $entries string can be saved, read, and decoded from the DB.
        #       This should help guard (but not guarantee) against malformed JSON from getting into the DB.
        $str = Validators::str($data);
        $test_key = 'solid_affiliate_encoded_json_test_column';

        # NOTE: To be safe, clear the option before updating because if there is no diff in the value then `update_option` will return false. https://github.com/WordPress/wordpress-develop/blob/5.9/src/wp-includes/option.php#L449-L460
        #       But is will also return false on error when it `$wpdb->update` so we want to only check against that false. https://github.com/WordPress/wordpress-develop/blob/5.9/src/wp-includes/option.php#L493-L496
        delete_option($test_key);
        $maybe_success = update_option($test_key, wp_unslash($str), 'no');
        if ($maybe_success) {
            /** @var mixed $maybe_str */
            $maybe_str = get_option($test_key);
            if ($maybe_str && is_string($maybe_str)) {
                $maybe_json = self::decode_json_string($maybe_str);
                if (!is_null($maybe_json)) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Checks to see if there was a previous form field with a different field type. If so it returns a validations error,
     * because Affiliates may have data with that old field of a different type and it could break when trying to render.
     * If there are no conflicts it merges in the new fields to the list of previous fields.
     *
     * @param Schema<string> $schema
     *
     * @return boolean
     */
    public static function has_past_name_and_type_conflict($schema)
    {
        $option_key = 'solid_affiliate_past_custom_affiliate_registration_names_and_types';
        $conflict_key = 'conflict';
        $mappings_key = 'mappings';
        $mappings_str = Validators::str(get_option($option_key));

        if (Utils::is_empty($mappings_str)) {
            $past_mappings = [];
        } else {
            $past_mappings = self::decode_json_string(Validators::str(get_option($option_key)));
        }

        if (is_null($past_mappings)) {
            return true;
        }

        $result = array_reduce(
            SchemaFunctions::keys($schema),
            /**
             * @param array{
             *      conflict: boolean,
             *      mappings: array<empty, empty>|array<'text'|'varchar'|'wp_page'|'bigint'|'bool'|'float'|'datetime'|'wp_editor'|'multi_checkbox'|'textarea'|'radio_buttons'|'affiliate_select'|'user_select'|'woocommerce_product_select'|'woocommerce_coupon_select'|'email'|'url'|'select'>
             * } $arr
             * @param string $key
             */
            function ($arr, $key) use ($schema, $past_mappings, $conflict_key, $mappings_key) {
                $entry = $schema->entries[$key];

                if ($entry->form_input_type_override) {
                    $new_type = $entry->form_input_type_override;
                } else if (SchemaEntry::is_form_select_entry($entry)) {
                    $new_type = 'select';
                } else {
                    $new_type = $entry->type;
                }

                if (isset($past_mappings[$key])) {
                    $old_type = Validators::str_from_array($past_mappings, $key);

                    if ($old_type !== $new_type) {
                        $arr[$conflict_key] = true;
                    }
                }

                $mappings = array_merge($arr[$mappings_key], [$key => $new_type]);
                $arr[$mappings_key] = $mappings;
                return $arr;
            },
            [$conflict_key => false, $mappings_key => []]
        );

        if ($result[$conflict_key]) {
            return true;
        } else {
            $new_mappings = self::encode_to_json_string(array_merge($past_mappings, $result[$mappings_key]));
            update_option($option_key, $new_mappings);
            return false;
        }
    }

    /**
     * Takes an Affiliate and merges in its custom registartion data as top level properties on an object.
     *
     * @param Affiliate $affiliate
     *
     * @return object
     */
    public static function prep_affiliate_object_form_form($affiliate)
    {
        $custom_attrs = self::decode_json_string($affiliate->custom_registration_data);
        if (is_null($custom_attrs)) {
            $item = (object)$affiliate->attributes;
        } else {
            $attrs = $affiliate->attributes;
            $item = (object)array_merge($attrs, $custom_attrs);
        }

        return $item;
    }

    /**
     * Removes all keys in the Affiliate fields array that are custom data keys so that they are persisted as top level attributes.
     *
     * @template TFieldKey of string
     * @template TKeyToRemove of string
     * @template TValues of mixed
     *
     * @param array<TFieldKey, TValues> $all_fields
     * @param array<TKeyToRemove> $keys_to_remove
     *
     * @return array<TFieldKey, TValues>
     */
    public static function remove_from_array($all_fields, $keys_to_remove)
    # TODO:3: Can I tell psalm that it is not of type TKeyToRemove ?
    {
        return array_reduce(
            $keys_to_remove,
            /**
             * @param array<TFieldKey, TValues> $arr
             * @param TKeyToRemove $key
             */
            function ($arr, $key) {
                /**
                 * @psalm-suppress InvalidArrayOffset
                 */
                unset($arr[$key]);
                return $arr;
            },
            $all_fields
        );
    }

    /**
     * Pulls out and decodes the custom registration data from an Affiliate and merges returns an Affiliate with that data as part of its top level data.
     *
     * @param Affiliate[] $affiliates
     *
     * @return array<Affiliate>
     */
    public static function flatten_custom_data_into_affiliates($affiliates)
    {
        return array_map(
            /** @param Affiliate $affiliate */
            function ($affiliate) {
                $custom_data = AffiliateRegistrationFormFunctions::decode_json_string($affiliate->custom_registration_data);
                $attrs = $affiliate->attributes;

                if (is_null($custom_data)) {
                    return $affiliate;
                } else {
                    return new Affiliate(array_merge($attrs, $custom_data));
                }
            },
            $affiliates
        );
    }

    /**
     * Builds an array with the key-value pairs of each custom data from the custom registration form.
     *
     * @param Schema<string> $custom_registration_schema
     * @param Schema<Affiliate::KEY_SHARED_WITH_PORTAL_*|AffiliatePortal::REQUIRED_SCHEMA_ENTRY_KEY_*|"affiliate_group_id"> $static_registration_schema
     * @param array $data
     *
     * @return array<string, AffiliateRegistrationFormDataReturnType>
     */
    public static function build_custom_data_from_form_submit($custom_registration_schema, $static_registration_schema, $data)
    {
        $all_entries = $custom_registration_schema->entries;
        $static_keys = SchemaFunctions::keys($static_registration_schema);
        $all_keys = SchemaFunctions::keys($custom_registration_schema);
        $custom_keys = array_diff($all_keys, $static_keys);
        return array_reduce(
            $custom_keys,
            /**
             * @param array<empty, empty>|array<string, AffiliateRegistrationFormDataReturnType> $arr
             * @param string $key
             */
            function ($arr, $key) use ($all_entries, $data) {
                $entry = $all_entries[$key];


                switch ($entry->type) {
                    case 'text':
                        $val = sanitize_text_field(Validators::str_from_array($data, $key));
                        break;
                    case 'bigint':
                        $val = Validators::str_from_array($data, $key);
                        if (!Utils::is_empty_but_allow_zero($val)) {
                            $val = Validators::int_from_array($data, $key);
                        }
                        break;
                    case 'bool':
                        $val = Validators::bool_from_array($data, $key);
                        break;
                    case 'varchar':
                        $val = Validators::str_from_array($data, $key);
                        break;
                    case 'multi_checkbox':
                        /** @var mixed $maybe_arr */
                        $maybe_arr = isset($data[$key]) ? $data[$key] : [];
                        if (is_array($maybe_arr)) {
                            $val = Validators::array_of_coerced_string($maybe_arr);
                        } else {
                            $val = [];
                        }
                        break;
                    default:
                        $val = sanitize_text_field(Validators::str_from_array($data, $key));
                        break;
                }

                $arr[$key] = $val;
                return $arr;
            },
            []
        );
    }

    /**
     * Merges in the required registration form entries and overwrites any non-required but static registration for entries into the custom entries.
     *
     * @template TSchemaEntryKey of string
     *
     * @param array<TSchemaEntryKey, SchemaEntry> $entries
     * @param array<Affiliate::KEY_SHARED_WITH_PORTAL_*, SchemaEntry> $shared_entries
     * @param array<AffiliatePortal::REQUIRED_SCHEMA_ENTRY_KEY_*, SchemaEntry> $required_entries
     *
     * @return array<TSchemaEntryKey|AffiliatePortal::REQUIRED_SCHEMA_ENTRY_KEY_*, SchemaEntry>
     */
    public static function merge_required_and_shared_schema_entries($entries, $shared_entries, $required_entries)
    # TODO:3: How to conceptually make the | a &
    {
        # TODO:2: Why does psalm not know this?
        /** @var array<TSchemaEntryKey, SchemaEntry> $clean_entries */
        $clean_entries = array_reduce(
            array_keys($shared_entries),
            /**
             * @param array<string, SchemaEntry> $arr
             * @param Affiliate::KEY_SHARED_WITH_PORTAL_* $key
             */
            function ($arr, $key) use ($shared_entries) {
                if (array_key_exists($key, $arr)) {
                    $static_data = $shared_entries[$key]->data;
                    $entry = $arr[$key];
                    $custom_props = [
                        'show_on_non_admin_edit_form' => $entry->show_on_non_admin_edit_form,
                        'show_on_non_admin_new_form' => true,
                        'required' => $entry->required,
                        'display_name' => $entry->display_name,
                        'form_input_placeholder' => $entry->form_input_placeholder,
                        'form_tooltip_content' => $entry->form_tooltip_content,
                        'custom_form_input_attributes' => $entry->custom_form_input_attributes,
                        'form_input_callback_key' => Validators::form_input_schema_entry_key_str($entry->form_input_callback_key),
                        'form_input_description' => $entry->form_input_description
                    ];
                    $new_data = array_merge($static_data, $custom_props);
                    $combined_entry = new SchemaEntry($new_data);
                    $arr[$key] = $combined_entry;
                }
                return $arr;
            },
            $entries
        );

        return array_merge($clean_entries, $required_entries);
    }

    /**
     * A hack to include the affiliate_group_id SchemaEntry on the affiliate portal new and edit forms.
     * Having it merged here before for those views allows the regular Schema for the custom registration form to exclude it.
     * This is needed (for now) because that SchemaEntry is a reserved column on Affiliate, so it cannot be merged in as a required field even though it has to be on the form to accept the group ID from the portal shortcode.
     * If it were to be a required field then a validation error will occur because it is both a "required portal" and "reserved by affiliate" SchemaEntry.
     *
     * @template TRegistrationSchemaKey of string
     *
     * @param Schema<TRegistrationSchemaKey> $registration_schema
     *
     * @param Schema<Affiliate::KEY_SHARED_WITH_PORTAL_*|AffiliatePortal::REQUIRED_SCHEMA_ENTRY_KEY_*|Affiliate::RESERVED_SCHEMA_ENTRY_KEY_AFFILIATE_GROUP_ID> $legacy_schema
     *
     * @return Schema<TRegistrationSchemaKey|Affiliate::RESERVED_SCHEMA_ENTRY_KEY_AFFILIATE_GROUP_ID>
     */
    public static function schema_with_affiliate_group_id($registration_schema, $legacy_schema)
    {
        $registration_entries = $registration_schema->entries;
        /** @var array<Affiliate::RESERVED_SCHEMA_ENTRY_KEY_AFFILIATE_GROUP_ID, SchemaEntry> */
        $group_id_entry = array_filter(
            $legacy_schema->entries,
            function ($key) {
                return $key === Affiliate::RESERVED_SCHEMA_ENTRY_KEY_AFFILIATE_GROUP_ID;
            },
            ARRAY_FILTER_USE_KEY
        );
        $entries_for_form = array_merge($registration_entries, $group_id_entry);
        return new Schema(['entries' => $entries_for_form]);
    }

    /**
     * Decodes the string representation of array-like data into an associative array.
     *
     * @param string $json_str
     *
     * @return array|null
     */
    public static function decode_json_string($json_str)
    {
        /** @var mixed $maybe_arr */
        $maybe_arr = json_decode($json_str, true, 512, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        ///////////////////////////////////////////////
        // JSON PARSER - TRYING TO FIND SPECIFIC ERROR
        ///////////////////////////////////////////////
        if (is_null($maybe_arr)) {
            $parser = new JsonParser();

            try {
                $parser->parse($json_str);
                // return 'success';  // The JSON is valid
            } catch (\Exception $e) {
                // $message =  $e->getMessage();  // Return the error message
                // TODO somehow bubble up the error message (?)
                return null;
            }
        }

        ///////////////////////////////////////////////
        ///////////////////////////////////////////////
        if (is_null($maybe_arr)) {
            return null;
        } else {
            return Validators::arr($maybe_arr);
        }
    }

    /**
     * Encodes arrays data into a json string.
     *
     * @param array $arr
     *
     * @return string|null
     */
    public static function encode_to_json_string($arr)
    {
        $maybe_str = wp_json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($maybe_str) {
            return Validators::str($maybe_str);
        } else {
            return null;
        }
    }

    /**
     * Whether or not all the schema entry names are unique.
     *
     * @param array $entries
     *
     * @return boolean
     */
    public static function has_unique_names($entries)
    {
        $uniq = array_unique(array_map(function ($entry) {
            return strtolower(trim(Validators::str_from_array(Validators::arr($entry), 'name')));
        }, $entries), SORT_STRING);

        return count($uniq) === count($entries);
    }

    /**
     * Whether or not the SchemaEntry's have all the required and unique data.
     * -> Required: name, display_name, enum_options->label, enum_options->value
     * -> Unique: display_name
     *
     * @param array<string, SchemaEntry> $entries
     *
     * @return array{0: boolean, 1: string}
     */
    public static function has_all_required_and_unique_data($entries)
    {
        if (!self::_has_unique_display_names($entries)) {
            return [false, self::ERROR_KEY_UNIQUE_LABELS];
        }

        $reserved_names = Affiliate::RESERVED_AFFILIATE_COLUMNS;
        $passing_validation_map = [
            self::ERROR_KEY_EMPTY_NAME => false,
            self::ERROR_KEY_EMPTY_LABEL => false,
            self::ERROR_KEY_EMPTY_OR_DUP_ENUM_OPTION => false,
            self::ERROR_KEY_RESERVED_NAME_CONFLICT => false
        ];

        $validation_map = array_reduce(
            array_keys($entries),
            /**
             * @param array<string, boolean> $arr
             * @param string $key
             */
            function ($arr, $key) use ($reserved_names, $entries) {
                $entry = $entries[$key];

                if (Utils::is_empty($key)) {
                    $arr[self::ERROR_KEY_EMPTY_NAME] = true;
                }

                if (Utils::is_empty($entry->display_name)) {
                    $arr[self::ERROR_KEY_EMPTY_LABEL] = true;
                }

                if (self::_has_empty_or_dup_enum_option_labels_or_values($entry)) {
                    $arr[self::ERROR_KEY_EMPTY_OR_DUP_ENUM_OPTION] = true;
                }

                if (in_array(trim(strtolower($key)), $reserved_names, true)) {
                    $arr[self::ERROR_KEY_RESERVED_NAME_CONFLICT] = true;
                }

                return $arr;
            },
            $passing_validation_map
        );

        if ($validation_map[self::ERROR_KEY_EMPTY_NAME]) {
            return [false, self::ERROR_KEY_EMPTY_NAME];
        } else if ($validation_map[self::ERROR_KEY_EMPTY_LABEL]) {
            return [false, self::ERROR_KEY_EMPTY_LABEL];
        } else if ($validation_map[self::ERROR_KEY_EMPTY_OR_DUP_ENUM_OPTION]) {
            return [false, self::ERROR_KEY_EMPTY_OR_DUP_ENUM_OPTION];
        } else if ($validation_map[self::ERROR_KEY_RESERVED_NAME_CONFLICT]) {
            return [false, self::ERROR_KEY_RESERVED_NAME_CONFLICT];
        } else {
            return [true, ''];
        }
    }

    /**
     * Check if there are eny non-unique display_names.
     *
     * @param array<string, SchemaEntry> $entries
     *
     * @return boolean
     */
    private static function _has_unique_display_names($entries)
    {
        $uniq = array_unique(array_map(function ($entry) {
            return $entry->display_name;
        }, $entries), SORT_STRING);

        return count($uniq) === count($entries);
    }

    /**
     * Makes sure that there are no duplicate or empty enum option labels or values.
     *
     * @param SchemaEntry $entry
     *
     * @return boolean
     */
    private static function _has_empty_or_dup_enum_option_labels_or_values($entry)
    {
        if (!$entry->is_enum) {
            return false;
        }


        if (is_callable($entry->enum_options)) {
            # NOTE: This should never be the case from the custom form builder, but the type allows for a callable.
            return false;
        } else {
            # TODO:3: Why does psalm not know that EnumOptionsReturnType is an array?
            $opts = Validators::arr($entry->enum_options);

            $invalids = array_filter(
                $opts,
                /** @param non-empty-array<string> $tuple */
                function ($tuple) {
                    return in_array('', array_map('trim', $tuple), true);
                }
            );

            if (count($invalids) > 0) {
                return true;
            }

            $value_key = 'values';
            $label_key = 'labels';
            $invalid_key = 'invalid';
            $opts_map = array_reduce(
                $opts,
                /**
                 * @param array{values: array<string>, labels: array<string>, invalid: boolean} $arr
                 * @param array{0: string, 1: string} $opt
                 */
                function ($arr, $opt) use ($value_key, $label_key, $invalid_key) {
                    $val = strtolower(trim($opt[0]));
                    if (in_array($val, $arr[$value_key], true)) {
                        $arr[$invalid_key] = true;
                    }

                    $label = trim($opt[1]);
                    if (in_array($label, $arr[$label_key], true)) {
                        $arr[$invalid_key] = true;
                    }

                    $arr[$value_key][] = $opt[0];
                    $arr[$label_key][] = $opt[1];

                    return $arr;
                },
                [$value_key => [], $label_key => [], $invalid_key => false]
            );

            return $opts_map[$invalid_key];
        }
    }
}
