<?php

namespace SolidAffiliate\Lib\WooSoftwareLicense;

if (!defined('ABSPATH')) {
    exit;
}

class WOO_SLT
{
    /** @var WOO_SLT_Licence */
    var $licence;

    /** @var WOO_SLT_Options_Interface */
    var $interface;

    function __construct()
    {
        $licence_instance = new WOO_SLT_Licence();
        $this->licence              =   $licence_instance;
        $this->interface            =   new WOO_SLT_Options_Interface($licence_instance);
    }
}
