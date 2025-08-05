<?php

namespace SolidAffiliate\Lib\VO;

use SolidAffiliate\Lib\Validators;

/**
 * @psalm-type TextSubtype = 'text'|'email'|'password'|'url'|''
 * @psalm-type ControlType = 'text'|'checkbox-group'|'radio-group'|'select'|'textarea'|'number'
 * @psalm-type ControlMaxlengthType = numeric-string|''
 * @psalm-type ControlTextareaRowsType = numeric-string|''
 * @psalm-type ControlNumberMinType = numeric-string|''
 * @psalm-type ControlNumberMaxType = numeric-string|''
 * 
 * @psalm-type ControlValueType = array{
 *   label: string,
 *   value: string,
 *   selected: boolean
 * }
 * @psalm-type ControlValuesArrayType = array<ControlValueType>
 *
 * @psalm-type FormBuilderControlType = array{
 *   label: string,
 *   placeholder: string,
 *   name: string,
 *   type: ControlType,
 *   descriptionText: string,
 *   description: string,
 *   required: boolean,
 *   values: ControlValuesArrayType,
 *   subtype: TextSubtype,
 *   maxlength: ControlMaxlengthType,
 *   rows: ControlTextareaRowsType,
 *   min: ControlNumberMinType,
 *   max: ControlNumberMaxType,
 *   editable: boolean
 * }
 */
class FormBuilderControl
{
    const CONTROL_TYPE_TEXT = 'text';
    const CONTROL_TYPE_CHECKBOX_GROUP = 'checkbox-group';
    const CONTROL_TYPE_RADIO_GROUP = 'radio-group';
    const CONTROL_TYPE_SELECT = 'select';
    const CONTROL_TYPE_TEXTAREA = 'textarea';
    const CONTROL_TYPE_NUMBER = 'number';
    /** @var array<ControlType> */
    const CONTROL_TYPES = [
        self::CONTROL_TYPE_TEXT, self::CONTROL_TYPE_CHECKBOX_GROUP, self::CONTROL_TYPE_RADIO_GROUP, self::CONTROL_TYPE_SELECT, self::CONTROL_TYPE_TEXTAREA, self::CONTROL_TYPE_NUMBER
    ];

    const TEXT_SUBTYPE_TEXT = 'text';
    const TEXT_SUBTYPE_EMAIL = 'email';
    const TEXT_SUBTYPE_PASSWORD = 'password';
    const TEXT_SUBTYPE_URL = 'url';
    /** @var array<TextSubtype> */
    const TEXT_SUBTYPES = [
        self::TEXT_SUBTYPE_TEXT, self::TEXT_SUBTYPE_EMAIL, self::TEXT_SUBTYPE_PASSWORD, self::TEXT_SUBTYPE_URL
    ];
    const TYPES_WITH_CUSTOM_ATTRS = [self::CONTROL_TYPE_TEXT, self::CONTROL_TYPE_TEXTAREA, self::CONTROL_TYPE_NUMBER];
    const TYPES_WITH_MAXLENGTH = [self::CONTROL_TYPE_TEXT, self::CONTROL_TYPE_TEXTAREA];
    const TYPES_WITH_ROWS = [self::CONTROL_TYPE_TEXT, self::CONTROL_TYPE_TEXTAREA];
    const TYPES_WITH_MIN = [self::CONTROL_TYPE_NUMBER];
    const TYPES_WITH_MAX = [self::CONTROL_TYPE_NUMBER];
    const TYPES_WITH_SUBTYPES = [self::CONTROL_TYPE_TEXT];

    /** @var FormBuilderControlType $data */
    public $data;

    /** @var string $label */
    public $label;

    /** @var string $placeholder */
    public $placeholder;

    # TODO:2: Name should be a non-empty-string
    /** @var string $name */
    public $name;

    /** @var ControlType $type */
    public $type;

    /** @var string $description_text */
    public $description_text;

    /** @var string $description */
    public $description;

    /** @var boolean $required */
    public $required;

    /** @var ControlValuesArrayType $values */
    public $values;

    /** @var TextSubtype $subtype */
    public $subtype;

    /** @var ControlMaxlengthType $maxlength */
    public $maxlength;

    /** @var ControlTextareaRowsType $rows */
    public $rows;

    /** @var ControlNumberMinType $min */
    public $min;

    /** @var ControlNumberMaxType $max */
    public $max;

    /** @var boolean $editable */
    public $editable;

    /** @param FormBuilderControlType $data */
    public function __construct($data)
    {
        $this->data = $data;

        $this->label = $data['label'];
        $this->placeholder = $data['placeholder'];
        $this->name = $data['name'];
        $this->type = $data['type'];
        $this->description_text = $data['descriptionText'];
        $this->description = $data['description'];
        $this->required = $data['required'];
        $this->values = $data['values'];
        $this->subtype = $data['subtype'];
        $this->maxlength = $data['maxlength'];
        $this->rows = $data['rows'];
        $this->min = $data['min'];
        $this->max = $data['max'];
        $this->editable = $data['editable'];
    }

    /**
     * Whether or not a FormBuilderControl maps to a SchemaEntry enum
     *
     * @param FormBuilderControl $control
     *
     * @return boolean
     */
    public static function is_enum($control)
    {
        switch ($control->type) {
            case 'select':
                return true;
            case 'radio-group':
                return true;
            case 'checkbox-group':
                # TODO:3: Why does psalm not know that this type is an array? The count($arg) function does not accept ControlValuesArrayType
                if (count(Validators::arr($control->values)) > 1) {
                    return true;
                } else {
                    return false;
                }
            default:
                return false;
        }
    }
}
