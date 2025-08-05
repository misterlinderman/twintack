<?php

namespace SolidAffiliate\Lib\FormBuilder;

use SolidAffiliate\Lib\VO\FormFieldArgs;

class CheckBoxField
{
    /**
     * Undocumented function
     *
     * @param FormFieldArgs $args
     * @param boolean $disabled
     *
     * @return string
     */
    public static function build_checkbox_field($args, $disabled = false)
    {
        $disabled = FormBuilder::set_disabled_string($disabled);
        $required = FormBuilder::set_required_string($args->required);
        $custom_attrs = FormBuilder::build_custom_attributes_string($args->custom_attributes);
        $checked = $args->value ? 'checked' : '';

        return self::_html($args, $checked, $disabled, $required, $custom_attrs);
    }

    /**
     * Undocumented function
     *
     * @param FormFieldArgs $args
     * @param string $checked
     * @param string $disabled
     * @param string $required
     * @param string $custom_attrs
     *
     * @return string
     */
    private static function _html($args, $checked, $disabled, $required, $custom_attrs)
    {
        return "
<div class='sld_field-wrapper {$args->field_name}'>
    <div class='sld_field-left'>
            " . FormBuilder::maybe_render_field_title($args) . "
            " . FormBuilder::maybe_render_field_description($args) . "
    </div>
    <div class='sld_field-input'>
        <label for='{$args->field_name}' class='{$args->label_class}'>
            <input {$disabled} type='checkbox' name='{$args->field_name}' id='{$args->field_id}' class='regular-text' {$required} {$custom_attrs} {$checked} />
        </label>
    </div>
</div>
";
    }
}
