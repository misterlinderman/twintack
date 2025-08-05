<?php

namespace SolidAffiliate\Lib\AffiliateRegistrationForm;

use SolidAffiliate\Lib\RandomData;
use SolidAffiliate\Lib\Utils;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\FormBuilderControl;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;

/**
 * @psalm-import-type ControlValuesArrayType from \SolidAffiliate\Lib\VO\FormBuilderControl
 * @psalm-import-type ControlValueType from \SolidAffiliate\Lib\VO\FormBuilderControl
 * @psalm-import-type ControlType from \SolidAffiliate\Lib\VO\FormBuilderControl
 * @psalm-import-type TextSubtype from \SolidAffiliate\Lib\VO\FormBuilderControl
 *
 * @psalm-import-type SchemaEntryKey_type from \SolidAffiliate\Lib\VO\SchemaEntry
 * @psalm-import-type SchemaEntryKey_form_input_type_override from \SolidAffiliate\Lib\VO\SchemaEntry
 * @psalm-import-type FormInputCallbackKeyType from \SolidAffiliate\Lib\VO\SchemaEntry
 * @psalm-import-type EnumOptionsReturnType from \SolidAffiliate\Lib\VO\SchemaEntry
 */
class SchemaSerializer
{
    /**
     * Converts a json string representation of a Schema into a Schema object.
     *
     * @param string $json_str A json_encoded array of SchemaEntry->data's
     *
     * @return Schema
     */
    public static function json_string_to_schema($json_str)
    {
        $entries = Validators::arr(AffiliateRegistrationFormFunctions::decode_json_string($json_str));

        $entries = array_reduce(
            array_keys($entries),
            /**
             * @param array<empty, empty>|array<string, SchemaEntry> $arr
             * @param string|int $key
             */
            function ($arr, $key) use ($entries) {
                $key = Validators::str($key);
                $arr[$key] = self::_json_schema_entry_to_schema_entry($entries[$key]);
                return $arr;
            },
            []
        );

        return new Schema(['entries' => $entries]);
    }

    /**
     * Retruns a SchemaEntry given the json respresentation of a SchemaEntry
     *
     * @param mixed $entry
     *
     * @return SchemaEntry
     */
    private static function _json_schema_entry_to_schema_entry($entry)
    {
        $entry = Validators::arr($entry);
        $callback_key = Validators::form_input_schema_entry_key_str(Validators::str_from_array($entry, 'form_input_callback_key'));
        $enum_options = Validators::enum_options_array($entry['enum_options']);
        $input_attrs = Validators::custom_form_input_attributes_array($entry['custom_form_input_attributes']);
        $allow_zero = Validators::bool_from_array($entry, 'is_zero_value_allowed');
        $schema_entry_args = [
            'type' => Validators::str_from_array($entry, 'type'),
            'display_name' => Validators::str_from_array($entry, 'display_name'),
            'required' => Validators::bool_from_array($entry, 'required'),
            'is_enum' => Validators::bool_from_array($entry, 'is_enum'),
            'enum_options' => $enum_options,
            'show_on_new_form' => Validators::bool_from_array($entry, 'show_on_new_form'),
            'show_on_non_admin_new_form' => Validators::bool_from_array($entry, 'show_on_new_form'),
            'show_on_edit_form' => Validators::bool_from_array($entry, 'show_on_edit_form'),
            'show_on_non_admin_edit_form' => Validators::bool_from_array($entry, 'show_on_non_admin_edit_form'),
            'show_on_preview_form' => Validators::bool_from_array($entry, 'show_on_preview_form'),
            'show_list_table_column' => Validators::bool_from_array($entry, 'show_list_table_column'),
            'form_input_placeholder' => Validators::str_from_array($entry, 'form_input_placeholder'),
            'form_input_description' => Validators::str_from_array($entry, 'form_input_description'),
            'custom_form_input_attributes' => $input_attrs,
            'validate_callback' => DefaultSchemaEntryCallbacks::get_schema_entry_validate_callback($callback_key, $enum_options, $allow_zero, $input_attrs),
            'sanitize_callback' => DefaultSchemaEntryCallbacks::get_schema_entry_sanitize_callback($callback_key),
            'form_input_callback_key' => $callback_key,
            'is_password' => Validators::bool_from_array($entry, 'is_password'),
            'is_zero_value_allowed' => $allow_zero,
            'is_csv_exportable' => Validators::bool_from_array($entry, 'is_csv_exportable'),
            'form_tooltip_content' => Validators::str_from_array($entry, 'form_tooltip_content'),
            'is_sortable' => Validators::bool_from_array($entry, 'is_sortable'),
            'persist_value_on_form_submit' => Validators::bool_from_array($entry, 'persist_value_on_form_submit'),
            'nullable' => Validators::bool_from_array($entry, 'is_nullable'),
            'form_input_wrap_class' => Validators::str_from_array($entry, 'form_input_wrap_class'),
            'form_label_class' => Validators::str_from_array($entry, 'form_label_class'),
        ];

        $maybe_form_override_type = Validators::str_from_array($entry, 'form_input_type_override');
        if (!empty($maybe_form_override_type)) {
            $schema_entry_args = array_merge(
                $schema_entry_args,
                ['form_input_type_override' => $maybe_form_override_type]
            );
        }

        /** @psalm-suppress ArgumentTypeCoercion */
        return new SchemaEntry($schema_entry_args);
    }

    /**
     * Sanitizes the controls json from the $_POST into a Schema.
     * This enforces the data structure of the json blob before it is saved as text.
     *
     * @param array $controls
     *
     * @return Schema<string>
     */
    public static function custom_formbuilder_post_to_schema($controls)
    {
        $schema_entries = array_reduce(
            $controls,
            /**
             * @param array<empty, empty>|array<string, SchemaEntry> $arr
             * @param mixed $control
             */
            function ($arr, $control) {
                $control = Validators::arr($control);
                $type = self::_form_builder_type(Validators::str_from_array($control, 'type'));
                $fb_control = new FormBuilderControl([
                    'label' => trim(self::_strip_breaking_characters(Validators::str_from_array($control, 'label'))),
                    'placeholder' => trim(self::_strip_breaking_characters(Validators::str_from_array($control, 'placeholder'))),
                    'name' => Validators::non_empty_str(self::_sanitize_name(trim(self::_strip_breaking_characters(Validators::str_from_array($control, 'name')))), self::_random_form_field_name()),
                    'type' => $type,
                    'descriptionText' => trim(self::_strip_breaking_characters(Validators::str_from_array($control, 'descriptionText'))),
                    'description' => trim(self::_strip_breaking_characters(Validators::str_from_array($control, 'description'))),
                    'required' => Validators::bool_from_array($control, 'required'),
                    'values' => self::_values_from_custom_form_builder_post($control),
                    'subtype' => self::_form_builder_subtype(Validators::str_from_array($control, 'subtype'), $type),
                    'maxlength' => Validators::numeric_str(Validators::str_from_array($control, 'maxlength')),
                    'rows' => Validators::numeric_str(trim(Validators::str_from_array($control, 'rows'))),
                    'min' => Validators::numeric_str(trim(Validators::str_from_array($control, 'min'))),
                    'max' => Validators::numeric_str(trim(Validators::str_from_array($control, 'max'))),
                    'editable' => Validators::bool_from_array($control, 'editable')
                ]);

                $schema_entry_args = [
                    'type' => self::_control_type_to_schema_entry_type($fb_control),
                    'display_name' => $fb_control->label,
                    'required' => $fb_control->required,
                    'is_enum' => self::_control_to_schema_entry_enum_bool($fb_control),
                    'enum_options' => self::_control_to_schema_entry_enum_options($fb_control),
                    'show_on_new_form' => true,
                    'show_on_non_admin_new_form' => true,
                    'show_on_edit_form' => true,
                    'show_on_non_admin_edit_form' => $fb_control->editable,
                    'show_on_preview_form' => false,
                    'show_list_table_column' => true,
                    'form_input_placeholder' => $fb_control->placeholder,
                    'form_input_description' => $fb_control->description_text,
                    'custom_form_input_attributes' => self::_control_to_schema_entry_custom_attrs($fb_control),
                    'form_input_callback_key' => self::_control_to_schema_entry_callback_key($fb_control),
                    'is_password' => self::_control_to_schema_entry_password_bool($fb_control),
                    'is_zero_value_allowed' => self::_control_to_schema_entry_is_zero_value_allowed($fb_control),
                    'is_csv_exportable' => true,
                    'persist_value_on_form_submit' => self::_control_to_schema_entry_should_persist_on_form_submit($fb_control),
                    'form_tooltip_content' => $fb_control->description,
                    'is_sortable' => false,
                    'nullable' => self::_control_to_schema_entry_is_nullable($fb_control),
                    'form_input_wrap_class' => 'sld-ap-form_group',
                    'form_label_class' => self::_control_to_schema_entry_form_label_class($fb_control)
                ];

                $maybe_form_override_type = self::_maybe_form_override_type($fb_control);
                if (!is_null($maybe_form_override_type)) {
                    $schema_entry_args = array_merge(
                        $schema_entry_args,
                        ['form_input_type_override' => $maybe_form_override_type]
                    );
                }

                $arr[$fb_control->name] = new SchemaEntry($schema_entry_args);
                return $arr;
            },
            []
        );

        return new Schema(['entries' => $schema_entries]);
    }

    /**
     * Validator for FormBuilderControl ControlType
     *
     * @param string $str
     *
     * @return ControlType
     */
    private static function _form_builder_type($str)
    {
        $type = strtolower(trim($str));

        switch ($type) {
            case FormBuilderControl::CONTROL_TYPE_TEXT:
                return $type;
            case FormBuilderControl::CONTROL_TYPE_CHECKBOX_GROUP:
                return $type;
            case FormBuilderControl::CONTROL_TYPE_RADIO_GROUP:
                return $type;
            case FormBuilderControl::CONTROL_TYPE_SELECT:
                return $type;
            case FormBuilderControl::CONTROL_TYPE_TEXTAREA:
                return $type;
            case FormBuilderControl::CONTROL_TYPE_NUMBER:
                return $type;
            default:
                return FormBuilderControl::CONTROL_TYPE_TEXT;
        }
    }

    /**
     * Validator for FormBuilderControl ControlValuesArrayType from the $_POST
     *
     * @param array $control
     *
     * @return ControlValuesArrayType
     */
    private static function _values_from_custom_form_builder_post($control)
    {
        if (!isset($control['values'])) {
            return [];
        }

        if (!is_array($control['values'])) {
            return [];
        }

        return array_map(
            /** @param mixed $value */
            function ($value) {
                return [
                    'label' => trim(self::_strip_breaking_characters(Validators::str_from_array(Validators::arr($value), 'label'))),
                    'value' => strtolower(trim(self::_strip_breaking_characters(Validators::str_from_array(Validators::arr($value), 'value')))),
                    'selected' => false
                ];
            },
            $control['values']
        );
    }

    /**
     * Validator for FormBuilderControl TextSubType
     *
     * @param string $str
     * @param ControlType $type
     *
     * @return TextSubtype
     */
    private static function _form_builder_subtype($str, $type)
    {
        if ($type !== FormBuilderControl::CONTROL_TYPE_TEXT) {
            return '';
        }

        $subtype = strtolower(trim($str));

        switch ($subtype) {
            case FormBuilderControl::TEXT_SUBTYPE_TEXT:
                return $subtype;
            case FormBuilderControl::TEXT_SUBTYPE_EMAIL:
                return $subtype;
            case FormBuilderControl::TEXT_SUBTYPE_PASSWORD:
                return $subtype;
            case FormBuilderControl::TEXT_SUBTYPE_URL:
                return $subtype;
            default:
                return FormBuilderControl::TEXT_SUBTYPE_TEXT;
        }
    }

    /**
     * Returns the SchemaEntry type given a FormBuidlerControl
     *
     * @param FormBuilderControl $control
     *
     * @return SchemaEntryKey_type
     */
    private static function _control_type_to_schema_entry_type($control)
    {
        switch ($control->type) {
            case FormBuilderControl::CONTROL_TYPE_TEXT:
                return 'varchar';
            case FormBuilderControl::CONTROL_TYPE_CHECKBOX_GROUP:
                return self::_bool_or_multi_checkbox_type($control);
            case FormBuilderControl::CONTROL_TYPE_RADIO_GROUP:
                return 'varchar';
            case FormBuilderControl::CONTROL_TYPE_SELECT:
                return 'varchar';
            case FormBuilderControl::CONTROL_TYPE_TEXTAREA:
                return 'text';
            case FormBuilderControl::CONTROL_TYPE_NUMBER:
                return 'bigint';
        }
    }

    /**
     * Whether or not a SchemaEntry is_enum given a FormBuilderControl
     *
     * @param FormBuilderControl $control
     *
     * @return boolean
     */
    private static function _control_to_schema_entry_enum_bool($control)
    {
        return FormBuilderControl::is_enum($control);
    }

    /**
     * Returns a SchemaEntry enum options array given a FormBuilderControl values array
     *
     * @param FormBuilderControl $control
     *
     * @return EnumOptionsReturnType
     */
    private static function _control_to_schema_entry_enum_options($control)
    {
        return array_map(
            /** @param ControlValueType $value */
            function ($value) {
                return [$value['value'], $value['label']];
            },
            Validators::arr($control->values)
        );
    }

    /**
     * Returns a SchemaEntry custom_form_input_attributes array given a FormBuilderControl
     *
     * @param FormBuilderControl $control
     *
     * @return array<string, string>
     */
    private static function _control_to_schema_entry_custom_attrs($control)
    {
        $custom_attrs = [];

        if (!in_array($control->type, FormBuilderControl::TYPES_WITH_CUSTOM_ATTRS)) {
            return $custom_attrs;
        }

        if (!empty($control->maxlength) && in_array($control->type, FormBuilderControl::TYPES_WITH_MAXLENGTH)) {
            $custom_attrs = array_merge($custom_attrs, ['maxlength' => $control->maxlength]);
        }

        if (!empty($control->rows) && in_array($control->type, FormBuilderControl::TYPES_WITH_ROWS)) {
            $custom_attrs = array_merge($custom_attrs, ['rows' => $control->rows]);
        }

        if (!Utils::is_empty_but_allow_zero($control->min) && in_array($control->type, FormBuilderControl::TYPES_WITH_MIN)) {
            $custom_attrs = array_merge($custom_attrs, ['min' => $control->min]);
        }

        if (!Utils::is_empty_but_allow_zero($control->max) && in_array($control->type, FormBuilderControl::TYPES_WITH_MAX)) {
            $custom_attrs = array_merge($custom_attrs, ['max' => $control->max]);
        }

        return $custom_attrs;
    }

    /**
     * Returns a SchemaEntry is_zero_value_allowed given a FormBuilderControl's type and required boolean.
     *
     * @param FormBuilderControl $control
     *
     * @return boolean
     */
    private static function _control_to_schema_entry_is_zero_value_allowed($control)
    {
        if ($control->type === FormBuilderControl::CONTROL_TYPE_NUMBER) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Based on the FormBuilderControl type it returns the approriate CSS class for the form field's label.
     *
     * @param FormBuilderControl $control
     *
     * @return string
     */
    private static function _control_to_schema_entry_form_label_class($control)
    {
        if ($control->type === FormBuilderControl::CONTROL_TYPE_CHECKBOX_GROUP) {
            return 'sld-ap-form_checkbox';
        } else {
            return '';
        }
    }

    /**
     * Returns a SchemaEntry validate_callback function given a FormBuilderControl
     *
     * @param FormBuilderControl $control
     *
     * @return FormInputCallbackKeyType
     */
    private static function _control_to_schema_entry_callback_key($control)
    {
        switch ($control->type) {
            case FormBuilderControl::CONTROL_TYPE_TEXT:
                switch ($control->subtype) {
                    case FormBuilderControl::TEXT_SUBTYPE_TEXT:
                        return SchemaEntry::TEXT_TEXT_KEY;
                    case FormBuilderControl::TEXT_SUBTYPE_EMAIL:
                        return SchemaEntry::TEXT_EMAIL_KEY;
                    case FormBuilderControl::TEXT_SUBTYPE_PASSWORD:
                        return SchemaEntry::TEXT_PASSWORD_KEY;
                    case FormBuilderControl::TEXT_SUBTYPE_URL:
                        return SchemaEntry::TEXT_URL_KEY;
                }
            case FormBuilderControl::CONTROL_TYPE_CHECKBOX_GROUP:
                if (FormBuilderControl::is_enum($control)) {
                    return SchemaEntry::CHECKBOX_GROUP_KEY;
                } else {
                    return SchemaEntry::CHECKBOX_SINGLE_KEY;
                }
            case FormBuilderControl::CONTROL_TYPE_RADIO_GROUP:
                return SchemaEntry::RADIO_GROUP_KEY;
            case FormBuilderControl::CONTROL_TYPE_SELECT:
                return SchemaEntry::SELECT_KEY;
            case FormBuilderControl::CONTROL_TYPE_TEXTAREA:
                return SchemaEntry::TEXTAREA_KEY;
            case FormBuilderControl::CONTROL_TYPE_NUMBER:
                return SchemaEntry::NUMBER_KEY;
        }
    }

    /**
     * Returns a boolean for whether or not a SchemaEntry is a password given a FormBuilderControl
     *
     * @param FormBuilderControl $control
     *
     * @return boolean
     */
    private static function _control_to_schema_entry_password_bool($control)
    {
        if ($control->type === FormBuilderControl::CONTROL_TYPE_TEXT && $control->subtype === FormBuilderControl::TEXT_SUBTYPE_PASSWORD) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Whether or not a SchemaEntry should persist its field value on a failed form submit given a FormBuilderControl's type and subtype.
     *
     * @param FormBuilderControl $control
     *
     * @return boolean
     */
    private static function _control_to_schema_entry_should_persist_on_form_submit($control)
    {
        switch ($control->type) {
            case FormBuilderControl::CONTROL_TYPE_TEXTAREA:
                return false;
            case FormBuilderControl::CONTROL_TYPE_TEXT:
                if ($control->subtype === FormBuilderControl::TEXT_SUBTYPE_PASSWORD) {
                    return false;
                } else {
                    return true;
                }
            default:
                return true;
        }
    }

    /**
     * Whether or not a SchemaEntry value should be nullable at the DB level.
     *
     * @param FormBuilderControl $control
     *
     * @return boolean
     */
    private static function _control_to_schema_entry_is_nullable($control)
    {
        if ($control->required) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Returns the ScehmaEntry form_input_type_override given a FormBuilderControl
     *
     * @param FormBuilderControl $control
     *
     * @return 'radio_buttons'|'textarea'|'email'|'url'|null
     */
    private static function _maybe_form_override_type($control)
    {
        switch ($control->type) {
            case FormBuilderControl::CONTROL_TYPE_RADIO_GROUP:
                return 'radio_buttons';
            case FormBuilderControl::CONTROL_TYPE_TEXTAREA:
                return 'textarea';
            case FormBuilderControl::CONTROL_TYPE_TEXT:
                switch ($control->subtype) {
                    case FormBuilderControl::TEXT_SUBTYPE_EMAIL:
                        return 'email';
                    case FormBuilderControl::TEXT_SUBTYPE_URL:
                        return 'url';
                    default:
                        return null;
                }
            default:
                return null;
        }
    }

    /**
     * Whether a checkbox-group control type is a multi_checkbox or bool SchemaEntry type
     *
     * @param FormBuilderControl $control
     *
     * @return 'bool'|'multi_checkbox'
     */
    private static function _bool_or_multi_checkbox_type($control)
    {
        # TODO:3: Why does psalm not know that this type is an array? The count($arg) function does not accept ControlValuesArrayType
        if (count(Validators::arr($control->values)) > 1) {
            return 'multi_checkbox';
        } else {
            return 'bool';
        }
    }

    /**
     * Do not allow - or spaces in a field name.
     *
     * @param string $name
     *
     * @return string
     */
    private static function _sanitize_name($name)
    {
        return str_replace(['-', ' '], '_', $name);
    }

    /**
     * Returns a random string to be used as a SchemaEntry's key and form field's name.
     *
     * @return non-empty-string
     */
    private static function _random_form_field_name()
    # NOTE: This should not be needed, but is here as a fallback if someone bypasses the client side validation for presence of name.
    {
        return 'form_field_' . RandomData::string(8);
    }

    /**
     * Replaces characters that break the jquery formBuilder with an empty string.
     *
     * @param string $str
     *
     * @return string
     */
    private static function _strip_breaking_characters($str)
    {
        return Utils::strip_breaking_characters($str);
    }
}
