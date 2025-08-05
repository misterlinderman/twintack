<?php

namespace SolidAffiliate\Lib\FormBuilder;

use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\FormFieldArgs;

class SelectField
{
    /**
     * Undocumented function
     *
     * @param FormFieldArgs $args
     * @param boolean $disabled
     *
     * @return string
     */
    public static function build_select_field($args, $disabled = false)
    {
        $disabled = FormBuilder::set_disabled_string($disabled);
        $required = FormBuilder::set_required_string($args->required);
        $custom_attrs = FormBuilder::build_custom_attributes_string($args->custom_attributes);
        $values = array_column($args->select_options, 0);
        /** @var string|int|float|bool $selected_value */
        $selected_value = $args->value !== null ? $args->value : reset($values);

        $selects = array_map(
            /** @param array $tuple */
            static function (array $tuple) use ($selected_value) {
                list($value, $label) = $tuple;
                $selected = selected($selected_value, $value, false);

                return sprintf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr((string)$value),
                    $selected,
                    esc_html((string)$label)
                );
            },
            Validators::arr($args->select_options)
        );

        return self::_html($args, implode(' ', $selects), $disabled, $required, $custom_attrs);
    }

    /**
     * Undocumented function
     *
     * @param FormFieldArgs $args
     * @param string $selects
     * @param string $disabled
     * @param string $required
     * @param string $custom_attrs
     *
     * @return string
     */
    private static function _html($args, $selects, $disabled, $required, $custom_attrs)
    {
        return "
<div id='{$args->field_name}' class='sld_field-wrapper {$args->field_name}' >
    <div class='sld_field-left'>
            " . FormBuilder::maybe_render_field_title($args) . "
            " . FormBuilder::maybe_render_field_description($args) . "
    </div>
    <div class='sld_field-input'>
        <label for='{$args->label_for_value}' class='{$args->label_class}'>
            <select {$disabled} name='{$args->field_name}' id='{$args->field_id}' {$required} {$custom_attrs} >
                {$selects}
            </select>
        </label>
    </div>
</div>
";
    }
}
