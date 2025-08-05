<?php

namespace SolidAffiliate\Lib\VO;

/**
 * @psalm-import-type EnumOptionsReturnType from SchemaEntry
 *
 * @psalm-type FormFieldArgsType = array{
 *   label: string,
 *   label_for_value: string,
 *   field_name: string,
 *   field_id: string,
 *   field_type: string,
 *   value: mixed,
 *   placeholder: string,
 *   required: bool,
 *   description: string,
 *   select_options: EnumOptionsReturnType,
 *   custom_attributes: array<string, string>,
 *   wrapper_class: string,
 *   label_class: string,
 *   description_class: string,
 *   hide_title: boolean,
 *   hide_description: boolean,
 *   shows_placeholder: boolean,
 *   tooltip_content: string,
 *   tooltip_class: string,
 *   tooltip_body: string
 * }
 */
class FormFieldArgs
{

    /** @var FormFieldArgsType $data */
    public $data;

    /** @var string */
    public $label;

    /** @var string */
    public $label_for_value;

    /** @var string*/
    public $field_name;

    /** @var string */
    public $field_id;

    /** @var string */
    public $field_type;

    /** @var mixed */
    public $value;

    /** @var string */
    public $placeholder;

    /** @var string */
    public $tooltip_content;

    /** @var bool */
    public $required;

    /** @var string */
    public $description;

    /** @var EnumOptionsReturnType */
    public $select_options;

    /** @var array<string, string> */
    public $custom_attributes;

    /** @var string */
    public $wrapper_class;

    /** @var string */
    public $label_class;

    /** @var string */
    public $description_class;

    /** @var boolean */
    public $hide_title;

    /** @var boolean */
    public $hide_description;

    /** @var boolean */
    public $shows_placeholder;

    /** @var string */
    public $tooltip_class;

    /** @var string */
    public $tooltip_body;

    /** @param FormFieldArgsType $data */
    public function __construct($data)
    {
        $this->data = $data;

        $this->label = $data['label'];
        $this->label_for_value = $data['label_for_value'];
        $this->field_name = $data['field_name'];
        $this->field_id = $data['field_id'];
        $this->field_type = $data['field_type'];
        $this->value = $data['value'];
        $this->placeholder = $data['placeholder'];
        $this->required = $data['required'];
        $this->description = $data['description'];
        $this->select_options = $data['select_options'];
        $this->custom_attributes = $data['custom_attributes'];
        $this->wrapper_class = $data['wrapper_class'];
        $this->label_class = $data['label_class'];
        $this->description_class = $data['description_class'];
        $this->hide_title = $data['hide_title'];
        $this->hide_description = $data['hide_description'];
        $this->shows_placeholder = $data['shows_placeholder'];
        $this->tooltip_content = $data['tooltip_content'];
        $this->tooltip_class = $data['tooltip_class'];
        $this->tooltip_body = $data['tooltip_body'];
    }
}
