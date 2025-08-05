<?php

namespace SolidAffiliate\Lib;


// Vertical Navigation with the following menu items and sub items.
// Each item also needs an icon.
// The + indicates that the item has sub items.
// The [] indicates the page slug.
//
//  - Dashboard [AdminDashboardController::PAGE_PARAM_V2]
//  - Affiliates [Affiliate::ADMIN_PAGE_KEY] +
//    - All Affiliates ['TODO']
//    - Groups [AffiliateGroup::ADMIN_PAGE_KEY]
//    - Product Rates [AffiliateProductRate::ADMIN_PAGE_KEY]
//    - Linked Customers ['TODO']
//    - Affiliate Assets [Creative::ADMIN_PAGE_KEY]
//  - Conversions ['TODO'] + 
//    - Visits [Visit::ADMIN_PAGE_KEY]
//    - Referrals [Referral::ADMIN_PAGE_KEY]
//  - Payments ['TODO'] + 
//    - Create a Payout [BulkPayout::ADMIN_PAGE_KEY]
//    - Payouts [Payout::ADMIN_PAGE_KEY]
//    - Bulk Payouts History ['TODO']
//  - Reports ['AdminReportsController::ADMIN_PAGE_KEY'] +
//    - Commission Rates ['TODO']
//    - Affiliates ['TODO']
//    - Referrals ['TODO']
//    - Payouts ['TODO']
//    - Visits ['TODO']
//  - Add-ons [Addons\Core::ADDONS_PAGE_SLUG]
//  - Settings ['solid-affiliate-settings'] + 
//    - General ['TODO']
//    - Affiliate portal ['TODO']
//    - Registration Form ['TODO']
//    - Integrations ['TODO']
//    - Emails ['TODO']
//    - Misc. ['TODO']


// - Use Alpine.JS for interactivity where needed
// - 200 px wide
// - white background
// - 

class NewNav
{
    /**
     * @return string
     */
    public static function render()
    {
        return 'New Nav';
    }
}