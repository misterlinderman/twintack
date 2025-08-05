<?php

namespace SolidAffiliate\Lib\FormBuilder;

use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\FormFieldArgs;

class TextAreaField
{
    /**
     * Undocumented function
     *
     * @param FormFieldArgs $args
     * @param boolean $disabled
     *
     * @return string
     */
    public static function build_textarea_field($args, $disabled = false)
    {
        $disabled = FormBuilder::set_disabled_string($disabled);
        $required = FormBuilder::set_required_string($args->required);
        $custom_attrs = FormBuilder::build_custom_attributes_string($args->custom_attributes);
        $value = Validators::str(esc_attr((string)$args->value));

        return self::_html($args, $value, $disabled, $required, $custom_attrs);
    }

    /**
     * Undocumented function
     *
     * @param FormFieldArgs $args
     * @param string $value
     * @param string $disabled
     * @param string $required
     * @param string $custom_attrs
     *
     * @return string
     */
    private static function _html($args, $value, $disabled, $required, $custom_attrs)
    {
        return "
<div class='sld_field-wrapper {$args->field_name}'>
    <div class='sld_field-left'>
            " . FormBuilder::maybe_render_field_title($args) . "
            " . FormBuilder::maybe_render_field_description($args) . "
    </div>
    <div class='sld_field-input'>
        <label for='{$args->label_for_value}' class='{$args->label_class}'>
            <textarea {$disabled} name='{$args->field_name}' id='{$args->field_id}' class='regular-text' placeholder='{$args->placeholder}' $required {$custom_attrs}>{$value}</textarea>
        </label>
    </div>
</div>
";
    }
}
