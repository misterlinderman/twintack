<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Models\Affiliate;

class Links
{
    /**
     * Returns a string of an HTML anchor tag. The link is for the admin edit page.
     * 
     * Designed to work with many different types of inputs.
     * 
     * Example:
     *     Links::render('www.google.com', 'Google it');
     *     => '<a href="www.google.com">Google it</a>'
     * 
     *     Links::render($affiliate, 'Edit Affiliate');
     *     => '<a href="https://site.com/wp-admin/admin.php?page=solid-affiliate-affiliates&action=edit&id=1">Edit Affiliate</a>'
     *
     * @psalm-suppress RedundantConditionGivenDocblockType
     * 
     * @param string | \WP_Post | \WP_User | \WC_Order | \WC_Subscription | MikesDataModel $input
     * @param string $text
     * @param '_self'|'_blank' $target
     * 
     * @return string
     */
    public static function render($input, $text, $target = '_self')
    {
        /** @psalm-suppress DocblockTypeContradiction */
        if (is_string($input)) {
            $url = $input;
        } elseif ($input instanceof \WP_Post) {
            $url = get_edit_post_link($input);
        } elseif ($input instanceof \WP_User) {
            $url = get_edit_user_link($input->ID);
        } elseif ($input instanceof \WC_Order) {
            $url = $input->get_edit_order_url();
        } elseif ($input instanceof \WC_Subscription) {
            $url = $input->get_edit_order_url();
        } elseif ($input instanceof MikesDataModel) {
            $url = URLs::edit(get_class($input), (int)$input->id);
        } else {
            throw new \Exception("Links::render - Unsupported input type: " . gettype($input));
        }

        if (is_null($url)) {
            $url = '#';
        }

        $url = esc_url($url);
        return "<a href=\"{$url}\" target=\"{$target}\">{$text}</a>";
    }
}
