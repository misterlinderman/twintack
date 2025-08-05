<?php

namespace SolidAffiliate\Lib\VO;

/**
 * @psalm-type AffiliatePortalCouponDataArrayType=array<array{0:string, 1:float, 2:string, 3:int}>
 * 
 * @psalm-type SlugDisplayFormat_type=\SolidAffiliate\Lib\CustomAffiliateSlugs\AffiliateCustomSlugBase::DISPLAY_FORMAT_VALUE_*
 * 
 * @psalm-type StoreCreditDataType = array{
 *   is_enabled: bool, 
 *   outstanding_store_credit: float, 
 *   redeemed_store_credit: float,
 *   total_store_credit_transactions: int,
 *   store_credit_transactions: \SolidAffiliate\Models\StoreCreditTransaction[]
 * }
 * 
 * @psalm-type AffiliatePortalViewInterfaceType=array{
 *   affiliate: \SolidAffiliate\Models\Affiliate,
 *   account_email: string,
 *   affiliate_link: string,
 *   referrals: \SolidAffiliate\Models\Referral[],
 *   referrals_count: int,
 *   payouts: \SolidAffiliate\Models\Payout[],
 *   payouts_count: int,
 *   visits: \SolidAffiliate\Models\Visit[],
 *   visits_count: int,
 *   creatives: \SolidAffiliate\Models\Creative[],
 *   coupon_data: AffiliatePortalCouponDataArrayType,
 *   total_unpaid_earnings: float,
 *   total_paid_earnings: float,
 *   custom_slug_default_display_format: SlugDisplayFormat_type,
 *   store_credit_data: StoreCreditDataType,
 *   current_tab: string
 * }
 */
class AffiliatePortalViewInterface
{
    /** @var AffiliatePortalViewInterfaceType $data */
    public $data;

    /** @var \SolidAffiliate\Models\Affiliate */
    public $affiliate;

    /** @var string */
    public $account_email;

    /** @var string */
    public $affiliate_link;

    /** @var \SolidAffiliate\Models\Referral[] */
    public $referrals;

    /** @var int */
    public $referrals_count;

    /** @var \SolidAffiliate\Models\Payout[] */
    public $payouts;

    /** @var int */
    public $payouts_count;

    /** @var \SolidAffiliate\Models\Visit[] */
    public $visits;

    /** @var int */
    public $visits_count;

    /** @var \SolidAffiliate\Models\Creative[] */
    public $creatives;

    /** @var AffiliatePortalCouponDataArrayType */
    public $coupon_data;

    /** @var float */
    public $total_unpaid_earnings;

    /** @var float */
    public $total_paid_earnings;

    /** @var SlugDisplayFormat_type */
    public $custom_slug_default_display_format;

    /** @var StoreCreditDataType */
    public $store_credit_data;

    /** @var string */
    public $current_tab;

    /** @param AffiliatePortalViewInterfaceType $data */
    public function __construct($data)
    {
        $this->data = $data;

        $this->affiliate = $data['affiliate'];
        $this->account_email = $data['account_email'];
        $this->affiliate_link = $data['affiliate_link'];
        $this->referrals = $data['referrals'];
        $this->referrals_count = $data['referrals_count'];
        $this->payouts = $data['payouts'];
        $this->payouts_count = $data['payouts_count'];
        $this->visits = $data['visits'];
        $this->visits_count = $data['visits_count'];
        $this->creatives = $data['creatives'];
        $this->coupon_data = $data['coupon_data'];
        $this->total_unpaid_earnings = $data['total_unpaid_earnings'];
        $this->total_paid_earnings = $data['total_paid_earnings'];
        $this->custom_slug_default_display_format = $data['custom_slug_default_display_format'];
        $this->store_credit_data = $data['store_credit_data'];
        $this->current_tab = $data['current_tab'];
    }
}
