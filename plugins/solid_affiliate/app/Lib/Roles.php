<?php

namespace SolidAffiliate\Lib;

class Roles
{
    const ROLE_KEY_AFFILIATE = 'affiliate';

    /**
     * @return void
     */
    public static function register_hooks()
    {
        add_action('solid_affiliate/Affiliate/insert', [self::class, 'add_affiliate_role_to_an_affiliates_user']);
    }

    /**
     * @return void
     */
    public static function register_affiliate_manager_role()
    {
        // Get the administrator role to copy capabilities from
        $admin_role = get_role('administrator');

        // Create new role with custom capability
        remove_role('affiliate_manager');
        if ($admin_role) { //$shop_manager_role) {
            $admin_role->add_cap(Capabilities::MANAGE_AFFILIATE_PROGRAM);
            add_role(
                'affiliate_manager',
                'Affiliate Manager',
                array_merge($admin_role->capabilities, [
                    Capabilities::MANAGE_AFFILIATE_PROGRAM => true,
                ])
            );
        }
    }


    /**
     * Register all of our custom WordPress roles.
     * 
     * @return void
     */
    public static function register_affiliate_related_custom_roles()
    {
        add_role(
            Roles::ROLE_KEY_AFFILIATE,
            'Affiliate',
            array(
                // TODO add appropriate capabilities
            )
        );
    }

    /**
     * Remove all our custom WordPress roles.
     *
     * @return void
     */
    public static function remove_affiliate_related_custom_roles()
    {
        remove_role(Roles::ROLE_KEY_AFFILIATE);
    }

    /**
     * Given an affiliate_id, add the Affiliate role to the associated user.
     *
     * @param int $affiliate_id
     * 
     * @return bool
     */
    public static function add_affiliate_role_to_an_affiliates_user($affiliate_id)
    {
        $affiliate = \SolidAffiliate\Models\Affiliate::find($affiliate_id);
        if (is_null($affiliate)) {
            return false;
        } else {
            $user_id = $affiliate->user_id;
            return self::add_affiliate_role_to_wp_user_ids([$user_id]);
        }
    }

    /**
     * Given an affiliate_id, add the Affiliate role to the associated user.
     *
     * @param int $affiliate_id
     * 
     * @return bool
     */
    public static function remove_affiliate_role_from_an_affiliates_user($affiliate_id)
    {
        $affiliate = \SolidAffiliate\Models\Affiliate::find($affiliate_id);
        if (is_null($affiliate)) {
            return false;
        } else {
            $user_id = $affiliate->user_id;
            return self::remove_affiliate_role_from_wp_user_ids([$user_id]);
        }
    }

    /**
     * Given an array of wp_user_ids, this function attempts to assign the affiliate role to each of them.
     *
     * @param array<int> $wp_user_ids
     * @return bool
     */
    public static function add_affiliate_role_to_wp_user_ids($wp_user_ids)
    {
        Roles::register_affiliate_related_custom_roles();

        array_map(function ($wp_user_id) {
            $maybe_wp_user = new \WP_User($wp_user_id);
            /** @psalm-suppress RedundantCondition */
            if ($maybe_wp_user instanceof \WP_User) {
                return $maybe_wp_user->add_role(Roles::ROLE_KEY_AFFILIATE);
            };
        }, $wp_user_ids);

        return true;
    }

    /**
     * Given an array of wp_user_ids, this function attempts to remove the affiliate role to each of them.
     *
     * @param array<int> $wp_user_ids
     * @return bool
     */
    public static function remove_affiliate_role_from_wp_user_ids($wp_user_ids)
    {
        array_map(function ($wp_user_id) {
            $maybe_wp_user = new \WP_User($wp_user_id);
            /** @psalm-suppress RedundantCondition */
            if ($maybe_wp_user instanceof \WP_User) {
                return $maybe_wp_user->remove_role(Roles::ROLE_KEY_AFFILIATE);
            };
        }, $wp_user_ids);

        return true;
    }



    /**
     * Check if a user has a certain role.
     *
     * @param \WP_User $wp_user
     * @return boolean
     */
    public static function does_wp_user_have_role_affiliate($wp_user)
    {
        /** @psalm-suppress RedundantCondition */
        if ($wp_user instanceof \WP_User) {
            return in_array(Roles::ROLE_KEY_AFFILIATE, (array) $wp_user->roles);
        } else {
            return false;
        };
    }
}
