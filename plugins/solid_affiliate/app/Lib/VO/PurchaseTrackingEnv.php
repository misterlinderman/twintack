<?php

namespace SolidAffiliate\Lib\VO;

/** 
 * Notes
 * 
 * This value object represent the environment of the affiliate program,
 * the parts that are relevant to the PurchaseTracking when deciding to give a referral or not.
 *   - Relevant Solid Affiliate settings
 *   - Relevant Cookies
 *   - Relevant POST data 
 *   - Relevant DB data
 * 
 * @psalm-type PurchaseTrackingEnvType = array{
 *  setting_is_new_customer_commissions: bool,
 *  setting_is_prevent_self_referrals: bool,
 *  setting_is_enable_zero_value_referrals: bool,
 *  cookied_visit_id: int|false
 * } 
 */
class PurchaseTrackingEnv
{

    /** @var PurchaseTrackingEnvType $data */
    public $data;

    /** @var bool $setting_is_new_customer_commissions */
    public $setting_is_new_customer_commissions;

    /** @var bool $setting_is_prevent_self_referrals */
    public $setting_is_prevent_self_referrals;

    /** @var bool $setting_is_enable_zero_value_referrals */
    public $setting_is_enable_zero_value_referrals;

    /** @var int|false $cookied_visit_id */
    public $cookied_visit_id;


    /** @param PurchaseTrackingEnvType $data */
    public function __construct($data)
    {
        $this->data = $data;

        $this->setting_is_new_customer_commissions = $data['setting_is_new_customer_commissions'];
        $this->setting_is_prevent_self_referrals = $data['setting_is_prevent_self_referrals'];
        $this->setting_is_enable_zero_value_referrals = $data['setting_is_enable_zero_value_referrals'];
        $this->cookied_visit_id = $data['cookied_visit_id'];
    }
}
