<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Models\Affiliate;

class DevHelpers
{
    /**
     * @return void
     */
    public static function register_hooks()
    {
        add_action('solid_affiliate/Affiliate/insert', [self::class, 'handle_affiliate_insert'], 10, 1);
        add_action('solid_affiliate/Affiliate/update', [self::class, 'handle_affiliate_update'], 10, 2);
    }

    /* List of Actions */
    const AFFILIATE_APPROVED = 'solid_affiliate/Affiliate/approved';

    /**
     * @param int $affiliate_id
     * @return void
     */
    public static function handle_affiliate_insert($affiliate_id)
    {
        $maybe_affiliate = Affiliate::find($affiliate_id);
        // fire an approved action
        if ($maybe_affiliate instanceof Affiliate) {
            if (Affiliate::is_approved($maybe_affiliate)) {
                do_action(self::AFFILIATE_APPROVED, $maybe_affiliate->id);
            }
        }
    }

    /**
     * When an Affiliate is updated...
     *
     * @param int       $affiliate_id The ID of the just updated Affiliate.
     * @param Affiliate $previous     An Affiliate model instance representing the Affiliate before the update.
     *
     * @return void
     */
    public static function handle_affiliate_update($affiliate_id, $previous)
    {
        $maybe_affiliate = Affiliate::find($affiliate_id);
        // fire an approved action
        if ($maybe_affiliate instanceof Affiliate) {
            if (Affiliate::is_approved($maybe_affiliate) && !Affiliate::is_approved($previous)) {
                do_action(self::AFFILIATE_APPROVED, $maybe_affiliate->id);
            }
        }
    }
}
