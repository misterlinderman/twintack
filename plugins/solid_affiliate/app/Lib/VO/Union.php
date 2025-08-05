<?php

namespace SolidAffiliate\Lib\VO;

/**
 * @template T
 */
class Union
{
    /** @var T */
    public $val;

    /** @param T $val */
    public function __construct($val)
    {
        $this->val = $val;
    }
}

// new Union<SettingsStep|PluginStep>