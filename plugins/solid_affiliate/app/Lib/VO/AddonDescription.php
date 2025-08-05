<?php

namespace SolidAffiliate\Lib\VO;

use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\Integrations\WooCommerceSubscriptionsIntegration;

/** 
 * @psalm-type AddonDescriptionType = array{
 *  slug: string, 
 *  name: string, 
 *  addon_category?: string, 
 *  description: string, 
 *  author: string, 
 *  graphic_src: string,
 *  settings_schema: Schema<string>,
 *  documentation_url: string,
 *  enabled_by_default?: boolean,
 *  ff_disabled?: boolean
 * } 
 */
class AddonDescription
{
    /** @var AddonDescriptionType $data */
    public $data;

    /** @var string */
    public $slug;

    /** @var string */
    public $name;

    /** @var string */
    public $addon_category;

    /** @var string */
    public $description;

    /** @var string */
    public $author;

    /** @var string */
    public $graphic_src;

    /** @var Schema<string> */
    public $settings_schema;

    /** @var string */
    public $documentation_url;

    /** @var boolean */
    public $enabled_by_default;

    /** @var boolean */
    public $ff_disabled;

    /** @param AddonDescriptionType $data */
    public function __construct($data)
    {
        $this->data = $data;

        $this->slug = $data['slug'];
        $this->name = $data['name'];
        $this->addon_category = isset($data['addon_category']) ? $data['addon_category'] : __('uncategorized', 'solid-affiliate');
        $this->description = $data['description'];
        $this->author = $data['author'];
        $this->graphic_src = $data['graphic_src'];
        $this->settings_schema = $data['settings_schema'];
        $this->documentation_url = $data['documentation_url'];
        $this->enabled_by_default = isset($data['enabled_by_default']) ? $data['enabled_by_default'] : false;
        $this->ff_disabled = isset($data['ff_disabled']) ? $data['ff_disabled'] : false;
    }
}
