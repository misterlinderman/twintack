<?php

namespace SolidAffiliate\Lib\VO;

use SolidAffiliate\Lib\MikesDataModel;

/**
 * 
 * @psalm-type ListTableConfigsType = array{
 *   model_class: class-string<MikesDataModel>,
 *   singular: string,
 *   plural: string,
 *   schema: Schema<string>,
 *   page_key: string,
 *   bulk_actions: array<string, string>,
 *   search_by_key: string,
 *   search_function: Closure(array, array<string>, array<array<string>>):array<object>|Closure(array, array<string>, array<array<string>>, array<int>):array<object>,
 *   count_function: Closure():int,
 *   hidden_columns_by_default?: array<string>,
 *   computed_columns?: array<array{column_name: string, function: Closure(mixed):mixed}>,
 *   custom_css?: string,
 *   column_name_overrides?: array<string, string>,
 *   search_button_text_override?: string,
 *   default_sort?: array{orderby: string, order: 'asc'|'desc'},
 *   disable_checkbox_column?: bool,
 * }
 */
class ListTableConfigs
{
    /** @var class-string<MikesDataModel> */
    public $model_class;

    /** @var ListTableConfigsType $data */
    public $data;

    /** @var string */
    public $singular;

    /** @var string*/
    public $plural;

    /** @var Schema<string> */
    public $schema;

    /** @var string */
    public $page_key;

    /** @var array<string, string> */
    public $bulk_actions;

    /** @var string */
    public $search_by_key;

    /** @var Closure(array, array<string>, array<array<string>>, array<int>):array<object> */
    public $search_function;

    /** @var Closure():int */
    public $count_function;

    /** @var array<string> */
    public $hidden_columns_by_default;

    /** @var array<array{column_name: string, function: Closure(mixed):mixed}> */
    public $computed_columns;

    /** @var string */
    public $custom_css;

    /** @var array<string, string> */
    public $column_name_overrides;

    /** @var string|null */
    public $search_button_text_override;

    /** @var array{orderby: string, order: 'asc'|'desc'}|null */
    public $default_sort;

    /** @var bool */
    public $disable_checkbox_column;

    /**
     * @param ListTableConfigsType $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;

        $this->model_class = $data['model_class'];
        $this->singular = $data['singular'];
        $this->plural = $data['plural'];
        $this->schema = $data['schema'];
        $this->page_key = $data['page_key'];
        $this->bulk_actions = $data['bulk_actions'];
        $this->search_by_key = $data['search_by_key'];
        $this->search_function = $data['search_function'];
        $this->count_function = $data['count_function'];
        $this->hidden_columns_by_default = isset($data['hidden_columns_by_default']) ? $data['hidden_columns_by_default'] : [];
        $this->computed_columns = isset($data['computed_columns']) ? $data['computed_columns'] : [];
        $this->custom_css = isset($data['custom_css']) ? $data['custom_css'] : '';
        $this->column_name_overrides = isset($data['column_name_overrides']) ? $data['column_name_overrides'] : [];
        $this->search_button_text_override = isset($data['search_button_text_override']) ? $data['search_button_text_override'] : null;
        $this->default_sort = isset($data['default_sort']) ? $data['default_sort'] : null;
        $this->disable_checkbox_column = isset($data['disable_checkbox_column']) ? $data['disable_checkbox_column'] : false;
    }
}
