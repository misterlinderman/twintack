<?php

namespace SolidAffiliate\Lib\VO;

use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Lib\Validators;

/**
 * @psalm-type ShowOnFormType = boolean | 'hidden' | 'disabled' | 'hidden_and_disabled'
 * @psalm-type SchemaEntryKey_type = 'text' | 'varchar' | 'wp_page' | 'bigint' | 'bool' | 'float' | 'datetime' | 'wp_editor' | 'multi_checkbox' | 'textarea'
 * @psalm-type SchemaEntryKey_form_input_type_override = SchemaEntryKey_type | 'radio_buttons' | 'affiliate_select' | 'user_select' | 'woocommerce_product_select' | 'woocommerce_coupon_select'|'email'|'url'
 * @psalm-type FormInputCallbackKeyType 'text-text'|'text-email'|'text-password'|'text-url'|'checkbox-group'|'checkbox-single'|'radio-group'|'select'|'textarea'|'number'|'none'
 * @psalm-type EnumOptionsReturnType = array<array{0: string, 1: string}>
 * @psalm-type ValidateTupleType = array{0: boolean, 1: string}
 *
 * @psalm-type SchemaEntryType = array{
 *  type: SchemaEntryKey_type,
 *  form_input_type_override?: SchemaEntryKey_form_input_type_override,
 *  primary_key?: boolean,
 *  auto_increment?: boolean,
 *  length?: int,
 *  key?: boolean,
 *  unique?: boolean,
 * 
 *  display_name: string,
 *  required?: boolean,
 *  user_default?: mixed,
 *  default?: mixed,
 *  is_enum?: boolean,
 *  enum_options?: EnumOptionsReturnType|callable():EnumOptionsReturnType,
 *  show_on_new_form?: ShowOnFormType,
 *  show_on_edit_form?: ShowOnFormType,
 *  show_on_preview_form?: ShowOnFormType,
 *  show_list_table_column?: boolean,
 *  show_on_non_admin_edit_form?: ShowOnFormType,
 *  show_on_non_admin_new_form?: ShowOnFormType,
 *  form_input_placeholder?: string,
 *  form_input_description?: string,
 *  form_tooltip_content?: string,
 *  form_tooltip_class?: string,
 *  label_for_value?: string,
 *  hide_form_input_title?: boolean,
 *  hide_form_description?: boolean,
 *  shows_placeholder?: boolean,
 *  custom_form_input_attributes?: array<string, string>,
 *  form_input_wrap_class?: string,
 *  form_label_class?: string,
 *  form_description_class?: string,
 *  persist_value_on_form_submit?: boolean,
 *  validate_callback?: callable(mixed):(ValidateTupleType|boolean),
 *  sanitize_callback?: callable(mixed):mixed,
 *  form_input_callback_key?: FormInputCallbackKeyType,
 *  is_password?: boolean,
 *  nullable?: boolean,
 *  is_sortable?: boolean,
 * 
 *  settings_group?: string,
 *  settings_tab?: string,
 * 
 *  is_zero_value_allowed?: boolean,
 *
 *  is_csv_exportable?: boolean,
 *  csv_export_callback?: callable(mixed):mixed
 * }
 */
class SchemaEntry
# TODO:1: Make a 'select' override
{
    const DEFAULTS_BY_SCHEMA_ENTRY_TYPE = [
        'bool' => false,
        'bigint' => 0,
        'float' => 0.0,
        'varchar' => '',
        'text' => '',
        'wp_page' => '',
        'datetime' => '',
        'multi_checkbox' => [],
        'wp_editor' => '',
        'textarea' => ''
    ];

    const FORM_TYPES_SUPPORTED_BY_FORM_BUILDER = ['text', 'varchar', 'bigint', 'bool', 'multi_checkbox', 'textarea', 'radio_buttons', 'email', 'url'];

    const TEXT_TEXT_KEY = 'text-text';
    const TEXT_EMAIL_KEY = 'text-email';
    const TEXT_PASSWORD_KEY = 'text-password';
    const TEXT_URL_KEY = 'text-url';
    const CHECKBOX_GROUP_KEY = 'checkbox-group';
    const CHECKBOX_SINGLE_KEY = 'checkbox-single';
    const RADIO_GROUP_KEY = 'radio-group';
    const SELECT_KEY = 'select';
    const TEXTAREA_KEY = 'textarea';
    const NUMBER_KEY = 'number';
    const NONE_KEY = 'none';
    const FORM_INPUT_CALLBACK_KEYS = [
        self::TEXT_TEXT_KEY, self::TEXT_EMAIL_KEY, self::TEXT_PASSWORD_KEY, self::TEXT_URL_KEY, self::CHECKBOX_GROUP_KEY, self::CHECKBOX_SINGLE_KEY, self::RADIO_GROUP_KEY, self::SELECT_KEY, self::TEXTAREA_KEY, self::NUMBER_KEY, self::NONE_KEY
    ];

    /** @var SchemaEntryType $data */
    public $data;

    /** @var boolean */
    public $primary_key;

    /** @var boolean */
    public $auto_increment;

    /** @var boolean */
    public $key;

    /** @var boolean */
    public $unique;

    /** @var SchemaEntryKey_type */
    public $type;

    /** @var SchemaEntryKey_form_input_type_override | null */
    public $form_input_type_override;

    /** @var int */
    public $length;

    /** @var boolean */
    public $required;

    /** @var string */
    public $display_name;

    /** @var boolean */
    public $is_enum;

    /** @var EnumOptionsReturnType|callable():EnumOptionsReturnType */
    public $enum_options;

    /** @var ShowOnFormType */
    public $show_on_new_form;

    /** @var ShowOnFormType */
    public $show_on_edit_form;

    /** @var ShowOnFormType */
    public $show_on_preview_form;

    /** @var boolean */
    public $show_list_table_column;

    /** @var ShowOnFormType */
    public $show_on_non_admin_edit_form;

    /** @var ShowOnFormType */
    public $show_on_non_admin_new_form;

    /** @var string */
    public $form_input_placeholder;

    /** @var string */
    public $form_input_description;

    /** @var string */
    public $form_tooltip_content;

    /** @var string */
    public $form_tooltip_class;

    /** @var string */
    public $label_for_value;

    /** @var mixed */
    public $default;

    /** @var mixed */
    public $user_default;

    /** @var string */
    public $settings_group;

    /** @var string */
    public $settings_tab;

    # TODO:3: make this its own psalm-type
    /** @var array<string, string> */
    public $custom_form_input_attributes;

    /** @var boolean */
    public $hide_form_input_title;

    /** @var boolean */
    public $hide_form_description;

    /** @var boolean */
    public $shows_placeholder;

    # TODO: Make this string only
    /** @var string|null */
    public $form_input_wrap_class;

    /** @var string */
    public $form_label_class;

    /** @var string */
    public $form_description_class;

    /** @var boolean */
    public $persist_value_on_form_submit;

    /** @var callable(mixed):(ValidateTupleType|boolean)|null */
    public $validate_callback;

    /** @var callable(mixed):mixed|null */
    public $sanitize_callback;

    /**
     * This is used to access an associative array and return the validate and sanotize callbacks.
     * PHP cannot, by default, serialize or json_encode closures, so this is the key for accessing the default callbacks.
     * @var FormInputCallbackKeyType|null
     */
    public $form_input_callback_key;

    /** @var boolean */
    public $is_password;

    /** @var boolean */
    public $nullable;

    /** @var boolean */
    public $is_sortable;

    /** @var boolean */
    public $is_zero_value_allowed;

    /** @var boolean */
    public $is_csv_exportable;

    /** @var callable(mixed):mixed|null */
    public $csv_export_callback;

    /** @param SchemaEntryType $data */
    public function __construct($data)
    {
        // // get the caller of this function
        // $caller_1 = debug_backtrace()[1]['class'] . '::' . debug_backtrace()[1]['function'];
        // $caller_2 = debug_backtrace()[2]['class'] . '::' . debug_backtrace()[2]['function'];
        // $caller_3 = debug_backtrace()[3]['class'] . '::' . debug_backtrace()[3]['function'];

        // $caller = $caller_1 . ' called by ' . $caller_2 . ' called by ' . $caller_3;
        // do_action( 'qm/debug', 'SchemaEntry::__construct by ' . $caller );

        $defaults = [
            'primary_key' => false,
            'auto_increment' => false,
            'key' => false,
            'unique' => false,
            'length' => 0,
            'required' => false,
            'is_enum' => false,
            'enum_options' => [],
            'show_on_new_form' => false,
            'show_on_edit_form' => false,
            'show_on_preview_form' => false,
            'show_list_table_column' => false,
            'show_on_non_admin_edit_form' => false,
            'show_on_non_admin_new_form' => false,
            'form_input_placeholder' => '',
            'form_input_description' => '',
            # TODO: Should this be an optional value? Feels kinda like display_name, and maybe should be required.
            #       An option would be to use the SchemaEntry key but dash, camel, etc. it in FormBuilder::get_field_args_from_schema
            #       is_accept_affiliate_policy -> is-accept-affiliate-policy
            #
            #       Another would be to keep the functionality the same by defaulting to an empty string,
            #       and use the SchemaEntry key if there is no label_for_value set.
            'label_for_value' => '',
            'form_input_type_override' => null,
            'user_default' => null,
            'default' => false,
            'settings_group' => '',
            'settings_tab' => '',
            'hide_form_input_title' => false,
            'hide_form_description' => false,
            'shows_placeholder' => true,
            'custom_form_input_attributes' => [],
            'form_input_wrap_class' => null,
            'form_label_class' => '',
            'form_description_class' => '',
            'form_tooltip_content' => '',
            'form_tooltip_class' => '',
            'persist_value_on_form_submit' => true,
            'validate_callback' => null,
            'sanitize_callback' => null,
            'form_input_callback_key' => null,
            'nullable' => false,
            'is_sortable' => true,
            'is_zero_value_allowed' => false,
            'is_csv_exportable' => false,
            'csv_export_callback' => null,
        ];

        $this->data = $data;

        $this->type = $data['type'];
        $this->display_name = $data['display_name'];

        $this->primary_key = isset($data['primary_key']) ? $data['primary_key'] : $defaults['primary_key'];
        $this->auto_increment = isset($data['auto_increment']) ? $data['auto_increment'] : $defaults['auto_increment'];
        $this->key = isset($data['key']) ? $data['key'] : $defaults['key'];
        $this->unique = isset($data['unique']) ? $data['unique'] : $defaults['unique'];
        $this->length = isset($data['length']) ? $data['length'] : $defaults['length'];
        $this->required = isset($data['required']) ? $data['required'] : $defaults['required'];
        $this->is_enum = isset($data['is_enum']) ? $data['is_enum'] : $defaults['is_enum'];
        $this->enum_options = isset($data['enum_options']) ? $data['enum_options'] : $defaults['enum_options']; // TODO better logic for this, depends on is_enum = true
        $this->show_on_new_form = isset($data['show_on_new_form']) ? $data['show_on_new_form'] : $defaults['show_on_new_form'];
        $this->show_on_edit_form = isset($data['show_on_edit_form']) ? $data['show_on_edit_form'] : $defaults['show_on_edit_form'];
        $this->show_on_preview_form = isset($data['show_on_preview_form']) ? $data['show_on_preview_form'] : $defaults['show_on_preview_form'];
        $this->show_list_table_column = isset($data['show_list_table_column']) ? $data['show_list_table_column'] : $defaults['show_list_table_column'];
        $this->show_on_non_admin_edit_form = isset($data['show_on_non_admin_edit_form']) ? $data['show_on_non_admin_edit_form'] : $defaults['show_on_non_admin_edit_form'];
        $this->show_on_non_admin_new_form = isset($data['show_on_non_admin_new_form']) ? $data['show_on_non_admin_new_form'] : $defaults['show_on_non_admin_new_form'];
        $this->form_input_placeholder = isset($data['form_input_placeholder']) ? $data['form_input_placeholder'] : $defaults['form_input_placeholder'];
        $this->form_input_description = isset($data['form_input_description']) ? $data['form_input_description'] : $defaults['form_input_description'];
        $this->form_tooltip_content = isset($data['form_tooltip_content']) ? $data['form_tooltip_content'] : $defaults['form_tooltip_content'];
        $this->form_tooltip_class = isset($data['form_tooltip_class']) ? $data['form_tooltip_class'] : $defaults['form_tooltip_class'];
        $this->label_for_value = isset($data['label_for_value']) ? $data['label_for_value'] : $defaults['label_for_value'];
        $this->form_input_type_override = isset($data['form_input_type_override']) ? $data['form_input_type_override'] : $defaults['form_input_type_override'];
        $this->default = isset($data['default']) ? $data['default'] : $defaults['default'];
        $this->user_default = isset($data['user_default']) ? $data['user_default'] : $defaults['user_default'];
        $this->settings_group = isset($data['settings_group']) ? $data['settings_group'] : $defaults['settings_group'];
        $this->settings_tab = isset($data['settings_tab']) ? $data['settings_tab'] : $defaults['settings_tab'];
        $this->hide_form_input_title = isset($data['hide_form_input_title']) ? $data['hide_form_input_title'] : $defaults['hide_form_input_title'];
        $this->hide_form_description = isset($data['hide_form_description']) ? $data['hide_form_description'] : $defaults['hide_form_description'];
        $this->shows_placeholder = isset($data['shows_placeholder']) ? $data['shows_placeholder'] : $defaults['shows_placeholder'];
        $this->custom_form_input_attributes = isset($data['custom_form_input_attributes']) ? $data['custom_form_input_attributes'] : $defaults['custom_form_input_attributes'];
        $this->form_input_wrap_class = isset($data['form_input_wrap_class']) ? $data['form_input_wrap_class'] : $defaults['form_input_wrap_class'];
        $this->form_label_class = isset($data['form_label_class']) ? $data['form_label_class'] : $defaults['form_label_class'];
        $this->form_description_class = isset($data['form_description_class']) ? $data['form_description_class'] : $defaults['form_description_class'];
        $this->persist_value_on_form_submit = isset($data['persist_value_on_form_submit']) ? $data['persist_value_on_form_submit'] : $defaults['persist_value_on_form_submit'];
        $this->sanitize_callback = isset($data['sanitize_callback']) ? $data['sanitize_callback'] : null;
        $this->validate_callback = isset($data['validate_callback']) ? $data['validate_callback'] : null;
        $this->form_input_callback_key = isset($data['form_input_callback_key']) ? $data['form_input_callback_key'] : $defaults['form_input_callback_key'];
        $this->is_password = isset($data['is_password']) ? $data['is_password'] : false;
        /** @psalm-suppress DocblockTypeContradiction */
        $this->nullable = (isset($data['nullable']) && !is_null($data['nullable'])) ? boolval($data['nullable']) : false;
        $this->is_sortable = isset($data['is_sortable']) ? $data['is_sortable'] : $defaults['is_sortable'];
        $this->is_zero_value_allowed = isset($data['is_zero_value_allowed']) ? $data['is_zero_value_allowed'] : $defaults['is_zero_value_allowed'];
        $this->is_csv_exportable = isset($data['is_csv_exportable']) ? $data['is_csv_exportable'] : $defaults['is_csv_exportable'];
        $this->csv_export_callback = isset($data['csv_export_callback']) ? $data['csv_export_callback'] : $defaults['csv_export_callback'];
    }

    /**
     * Validates a value against the schema entry.
     *
     * @since TBD
     *
     * @param SchemaEntry       $schema_entry The schema entry that should be used to validate the value.
     * @param mixed             $value        The value to validate.
     *
     * @return ValidateTupleType
     */
    public static function validate(SchemaEntry $schema_entry, $value)
    {
        $maybe_validate_callback = $schema_entry->validate_callback;
        if (is_null($maybe_validate_callback)) {
            return [true, ''];
        } else {
            $validate_result = call_user_func($maybe_validate_callback, $value);
            if (is_array($validate_result)) {
                return $validate_result;
            } else {
                return [$validate_result, ''];
            }
        }
    }

    /**
     * Sanitizes a value against the schema entry.
     *
     * @since TBD
     *
     * @param SchemaEntry       $schema_entry The schema entry that should be used to sanitize the value.
     * @param mixed             $value        The value to sanitize.
     *
     * @return mixed The sanitized value.
     */
    public static function sanitize(SchemaEntry $schema_entry, $value)
    {
        return null !== $schema_entry->sanitize_callback ?
            call_user_func($schema_entry->sanitize_callback, $value)
            : $value;
    }

    /**
     * Formats a value for a CSV export. Fallsback to the sanitize callback if no CSV callback is defined.
     *
     * @param SchemaEntry $schema_entry
     * @param mixed $value
     *
     * @return mixed
     */
    public static function csv_export($schema_entry, $value)
    {
        if (is_null($schema_entry->csv_export_callback)) {
            return Formatters::csv_default_formatter($value);
        } else {
            return call_user_func($schema_entry->csv_export_callback, $value);
        }
    }

    /**
     * Returns the values from the SchemaEntry's enum_options array.
     *
     * @param EnumOptionsReturnType $enum_options
     *
     * @return array<string>
     */
    public static function values_from_enum_options($enum_options)
    {
        return array_map(
            /** @param non-empty-array<string> $tuple */
            function ($tuple) {
                return Validators::str($tuple[0]);
            },
            # TODO:3: Why does psalm not know that EnumOptionsReturnType is an array?
            Validators::arr($enum_options)
        );
    }

    /**
     * Whether or not a SchemaEntry is a 'select' form field based on its type, form override and enum bool.
     *
     * @param SchemaEntry $entry
     *
     * @return boolean
     */
    public static function is_form_select_entry($entry)
    {
        $form_override = $entry->form_input_type_override;
        $type = $entry->type;
        $is_enum = $entry->is_enum;

        if ($form_override !== 'radio_buttons' && $type !== 'multi_checkbox' && $is_enum) {
            return true;
        } else {
            return false;
        }
    }
}
