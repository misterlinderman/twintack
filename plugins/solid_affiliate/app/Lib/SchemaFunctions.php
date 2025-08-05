<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Lib\AffiliateRegistrationForm\AffiliateRegistrationFormFunctions;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;

/**
 * Class SchemaFunctions 
 * 
 * @psalm-import-type SchemaEntryKey_type from \SolidAffiliate\Lib\VO\SchemaEntry
 * @psalm-import-type ShowOnFormType from \SolidAffiliate\Lib\VO\SchemaEntry
 */
class SchemaFunctions
{
    /**
     * Undocumented Function 
     * 
     * @param Schema $schema
     * @param string $key
     * @param mixed $val
     *
     * @return string|array|bool|int|float
     */
    public static function sanitize_val_based_on_schema_and_key($schema, $key, $val)
    {
        if (!isset($schema->entries[$key])) {
            return (string)$val;
        }

        if (is_array($val)) {
            return $val;
        }

        $entry = $schema->entries[$key];

        $type = $entry->type;

        if ($entry->form_input_type_override == 'textarea') {
            $type = 'textarea';
        }

        $sanitized_val = SchemaFunctions::_cast_val_based_on_schema_entry_type($type, $val);

        if (AffiliateRegistrationFormFunctions::is_a_custom_affiliate_field($key)) {
            /** @var array|string|bool|int|float */
            $sanitized_val = SchemaEntry::sanitize($entry, $sanitized_val);
        }

        return $sanitized_val;
    }

    /**
     * Undocumented function
     *
     * @param SchemaEntryKey_type $schema_entry_type
     * @param mixed $val
     * 
     * @return array|string|bool|int|float
     */
    private static function _cast_val_based_on_schema_entry_type($schema_entry_type, $val)
    {
        switch ($schema_entry_type) {
            case 'multi_checkbox':
                if (!is_array($val)) {
                    return [];
                }
                return $val;
            case 'datetime':
                return (string)$val;
            case 'wp_editor':
                return wp_kses_post(stripslashes((string)$val));
            case 'wp_page':
                return (string)$val;
            case 'bool':
                if ($val === '') { // To handle the way HTML checkbox inputs post data. They post a key with val ''
                    return true;
                }
                if ($val === 'false') {
                    return false;
                }
                if ($val === 'true') {
                    return true;
                }
                return (bool)$val;
                // case 'tinyint':
                // case 'smallint':
                // case 'mediumint':
                // case 'int':
                // case 'integer':
            case 'bigint':
                return intval($val);
            case 'float':
                return floatval($val);
            case 'varchar':
            case 'text':
                # NOTE: This strips HTML tags
                return sanitize_text_field(Validators::str(wp_unslash((string)$val)));
            case 'textarea':
                return sanitize_textarea_field((string)$val);
                // default:
                //     return $val;
                //     break;
        }
        // return $val;
    }

    /**
     * @param Schema $schema
     * @param string $key
     *
     * @return float|int|string|bool|array
     */
    public static function default_for_empty_schema_key($schema, $key)
    {
        if (!isset($schema->entries[$key])) {
            return '';
        }

        if (!isset($schema->entries[$key]->type)) {
            return '';
        }
        ///////////////////////////////////////////

        $schema_entry_type = $schema->entries[$key]->type;
        $default_val = SchemaFunctions::_default_val_for_schema_entry_type($schema_entry_type);

        return $default_val;
    }

    /**
     * Undocumented function
     *
     * @param SchemaEntryKey_type $schema_entry_type
     * 
     * @return value-of<\SolidAffiliate\Lib\VO\SchemaEntry::DEFAULTS_BY_SCHEMA_ENTRY_TYPE>
     */
    private static function _default_val_for_schema_entry_type($schema_entry_type)
    {
        return SchemaEntry::DEFAULTS_BY_SCHEMA_ENTRY_TYPE[$schema_entry_type];
    }


    /**
     * @param Schema $schema
     * @param string $key
     *
     * @return string
     */
    public static function _error_msg_for_required_schema_key($schema, $key)
    {
        if (!isset($schema->entries[$key])) {
            return sprintf(__('Error: %1$s is required', 'solid-affiliate'), $key);
        }

        if (!isset($schema->entries[$key]->display_name)) {
            return sprintf(__('Error: %1$s is required', 'solid-affiliate'), $key);
        }

        /**
         * @psalm-suppress MixedArrayAccess
         */
        $display_name = (string) $schema->entries[$key]->display_name;

        return sprintf(__('Error: %1$s is required', 'solid-affiliate'), $display_name);
    }

    /**
     * @param Schema $schema
     *
     * @return string[]
     * @psalm-return array<array-key, string>
     */
    public static function required_fields_from_schema($schema)
    {
        return self::_keys_that_have_prop_true_from_schema($schema, 'required');
    }

    /**
     * @param Schema $schema
     *
     * @return string[]
     * @psalm-return array<array-key, string>
     */
    public static function keys_on_new_form_from_schema($schema)
    {
        $true = self::_keys_that_have_prop_true_from_schema($schema, 'show_on_new_form');
        $hidden = self::_keys_that_have_prop_some_value_from_schema($schema, 'show_on_new_form', 'hidden');
        $hidden_and_disabled = self::_keys_that_have_prop_some_value_from_schema($schema, 'show_on_new_form', 'hidden_and_disabled');

        return array_unique(array_merge($true, $hidden, $hidden_and_disabled));
    }

    /**
     * Returns the keys for all SchemaEntries that are to be shown on the new form.
     *
     * @param Schema $schema
     *
     * @return array<array-key, string>
     */
    public static function keys_shown_on_new_form_from_schema($schema)
    {
        $true = self::_keys_that_have_prop_true_from_schema($schema, 'show_on_new_form');
        $disabled = self::_keys_that_have_prop_some_value_from_schema($schema, 'show_on_new_form', 'hidden_and_disabled');
        return array_unique(array_merge($true, $disabled));
    }

    /**
     * Returns the keys for all SchemaEntry's that are to be on the non-admin new form given the an array of show on form types.
     *
     * @param Schema<string> $schema
     * @param array<ShowOnFormType> $show_on_form_types
     *
     * @return array<string>
     */
    public static function keys_on_non_admin_new_form_from_schema($schema, $show_on_form_types)
    {
        return array_filter(
            SchemaFunctions::keys($schema),
            /** @param string $key */
            function ($key) use ($schema, $show_on_form_types) {
                $entry = $schema->entries[$key];
                return in_array($entry->show_on_non_admin_new_form, $show_on_form_types, true);
            }
        );
    }

    /**
     * Returns the keys for all SchemaEntry's that are to be on the non-admin edit form given the an array of show on form types.
     *
     * @param Schema<string> $schema
     * @param array<ShowOnFormType> $show_on_form_types
     *
     * @return array<string>
     */
    public static function keys_on_non_admin_edit_form_from_schema($schema, $show_on_form_types)
    {
        return array_filter(
            SchemaFunctions::keys($schema),
            /** @param string $key */
            function ($key) use ($schema, $show_on_form_types) {
                $entry = $schema->entries[$key];
                return in_array($entry->show_on_non_admin_edit_form, $show_on_form_types, true);
            }
        );
    }

    /**
     * @param Schema $schema
     *
     * @return string[]
     * @psalm-return array<array-key, string>
     */
    public static function keys_on_edit_form_from_schema($schema)
    {
        $true = self::_keys_that_have_prop_true_from_schema($schema, 'show_on_edit_form');
        $hidden = self::_keys_that_have_prop_some_value_from_schema($schema, 'show_on_edit_form', 'hidden');
        $hidden_and_disabled = self::_keys_that_have_prop_some_value_from_schema($schema, 'show_on_edit_form', 'hidden_and_disabled');

        return array_unique(array_merge($true, $hidden, $hidden_and_disabled));
    }

    /**
     * Computes an array which WP_List_Table expects for it's columns. Uses
     * show_list_table_column and display_name from Schema.
     *
     * NOTE the results are in this shape:
     * array(
     *     'id' => __('ID', 'solid-affiliate'),
     *     'user_id' => __('User ID', 'solid-affiliate'),
     *     'commission_rate' => __('Commission Rate', 'solid-affiliate'),
     * )
     *
     * @param Schema<string> $schema
     *
     * @return array<string, string>
     */
    public static function columns_for_list_table_from_schema($schema)
    {
        $column_fields = self::_keys_that_have_prop_true_from_schema($schema, 'show_list_table_column');

        $results = array_reduce(
            $column_fields,
            /**
             * @param array<empty, empty>|array<string, string> $result
             * @param string $item
             **/
            function ($result, $item) use ($schema) {
                $schema_entry = $schema->entries[$item];
                $column_name = $schema_entry->display_name ? $schema_entry->display_name : $item;
                $column_name = __($column_name, 'solid-affiliate');
                $result[$item] = $column_name;
                return $result;
            },
            array()
        );

        return $results;
    }

    /**
     * Gets all the columns that are shown on the list table, then filters out the ones that are declared to not be sortabled on their SchemaEntry.
     *
     * @param Schema<string> $schema
     *
     * @return array<string, string>
     */
    public static function sortable_columns_for_list_table_from_schema($schema)
    {
        $all_list_table_columns = self::columns_for_list_table_from_schema($schema);
        $entries = $schema->entries;
        return array_filter(
            $all_list_table_columns,
            /** @param string $key */
            function ($key) use ($entries) {
                return $entries[$key]->is_sortable;
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Undocumented function
     *
     * @param Schema $schema
     * @param string $prop
     * @return string[]
     */
    public static function _keys_that_have_prop_true_from_schema($schema, $prop)
    {
        return self::_keys_that_have_prop_some_value_from_schema($schema, $prop, true);
    }


    /**
     * A filter by some_key === some_value
     *
     * @param Schema $schema
     * @param string $prop
     * @param mixed $some_value
     *
     * @return string[]
     */
    public static function _keys_that_have_prop_some_value_from_schema($schema, $prop, $some_value)
    {
        $fields = array_keys($schema->entries);

        $required_fields = array_filter($fields, function ($field) use ($schema, $prop, $some_value) {
            return (isset($schema->entries[$field]->$prop) && ($schema->entries[$field]->$prop) === $some_value);
        });

        return array_values($required_fields);
    }

    /**
     * Undocumented function
     *
     * @param Schema $schema
     * @param string[] $fields
     * @psalm-param list<string> $fields
     * @param string $key
     *
     * @return array<array-key, mixed|null>
     */
    public static function values_from_schema_by_key($schema, $fields, $key)
    {
        $default = null;

        $values = array_reduce(
            $fields,
            /**
             * @param array $result
             * @param string $item
             *
             * @return array<array-key, mixed|null>
             **/
            function ($result, $item) use ($schema, $key, $default) {
                $schema_entry = $schema->entries[$item];
                /** @var mixed $value */
                $value = isset($schema_entry->$key) ? $schema_entry->$key : $default;
                /** @psalm-suppress MixedAssignment  */
                $result[$item] = $value;
                return $result;
            },
            array()
        );

        return $values;
    }

    /**
     * Undocumented function
     *
     * @param Schema $schema
     * @param string $key
     *
     * @return array<array-key, mixed>
     */
    public static function unique_values_from_schema_by_key($schema, $key)
    {
        $all_values = array_map(
            /**
             * @param SchemaEntry $schema_entry
             *
             * @return mixed
             **/
            function ($schema_entry) use ($key) {
                /** @var mixed */
                $val = $schema_entry->$key;
                return $val;
            },
            $schema->entries
        );


        $unique_values = array_unique($all_values);

        return $unique_values;
    }

    /**
     * Returns a new Schema with a subset of Schema Entries.
     * 
     * @template TKey of string
     *
     * @param Schema $schema
     * @param TKey[] $keys
     *
     * @return Schema<TKey>
     */
    public static function _filter_schema_entries_by_keys($schema, $keys)
    {
        /** @var array<TKey, SchemaEntry> $filtered_schema_entries */
        $filtered_schema_entries = array_intersect_key($schema->entries, array_flip($keys));

        return new Schema(['entries' => $filtered_schema_entries]);
    }

    /**
     * Undocumented function
     *
     * @param Schema $schema
     * @return array
     */
    public static function defaults_from_schema($schema)
    {
        $all_fields = array_keys($schema->entries);
        return self::values_from_schema_by_key($schema, $all_fields, 'user_default');
    }

    /**
     * Checks if a schema entry has the is_zero_value_allowed key set to true.
     *
     * @param Schema $schema
     * @param string $schema_key
     * 
     * @return bool
     */
    public static function check_is_zero_value_allowed($schema, $schema_key)
    {
        $value_map = self::values_from_schema_by_key($schema, [$schema_key], 'is_zero_value_allowed');
        return $value_map[$schema_key] === true;
    }

    /**
     * Gets all the keys for a schema.
     *
     * @param Schema $schema
     * @return string[]
     */
    public static function keys($schema)
    {
        return array_keys($schema->entries);
    }

    /**
     * Returns all SchemaEntry that are CSV exportable.
     *
     * @param Schema $schema
     * @return SchemaEntry[]
     */
    public static function csv_exportable_entries($schema)
    {
        return array_filter($schema->entries, function ($entry) {
            return $entry->is_csv_exportable;
        });
    }

    /**
     * Returns all SchemaEntry that should not persist their value on a form submit.
     *
     * @param Schema $schema
     * @return SchemaEntry[]
     */
    public static function persist_value_on_form_submit_entries($schema)
    {
        return array_filter($schema->entries, function ($entry) {
            return $entry->persist_value_on_form_submit;
        });
    }
}
