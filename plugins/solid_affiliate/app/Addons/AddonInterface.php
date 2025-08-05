<?php

namespace SolidAffiliate\Addons;

interface AddonInterface
{
    /**
     * @param \SolidAffiliate\Lib\VO\AddonDescription[] $addon_descriptions
     * 
     * @return \SolidAffiliate\Lib\VO\AddonDescription[]
     */
    public static function register_addon_description($addon_descriptions);
}
