<?php

namespace SolidAffiliate\Lib\CustomAffiliateSlugs;

use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\Either;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliateMeta;

class CustomSlugDbFunctions
{
    /**
     * Wrapper function to create a Custom Slug AffiliateMeta.
     * This should always be used to create this type of meta over using AffiliateMeta or MikesDataModelTrait functions directly.
     *
     * @param int $affiliate_id
     * @param string $slug
     *
     * @return Either<int>
     */
    public static function create_custom_slug_affiliate_meta($affiliate_id, $slug)
    {
        return AffiliateMeta::upsert_with_value_sanitization_and_validation([
            AffiliateMeta::SCHEMA_KEY_AFFILIATE_ID => $affiliate_id,
            AffiliateMeta::SCHEMA_KEY_META_KEY => AffiliateMeta::META_KEY_CUSTOM_AFFILIATE_SLUG,
            AffiliateMeta::SCHEMA_KEY_META_VALUE => $slug
        ]);
    }

    /**
     * Wrapper function to create a Can Affiliate Edit Custom Slugs AffiliateMeta.
     * This should always be used to upsert this type of meta over using AffiliateMeta or MikesDataModelTrait functions directly.
     *
     * @param int $affiliate_id
     * @param boolean $can_edit
     * @param int $id
     *
     * @return Either<int>
     */
    public static function upsert_can_edit_custom_slugs_meta($affiliate_id, $can_edit, $id)
    {
        return AffiliateMeta::upsert_with_value_sanitization_and_validation([
            AffiliateMeta::SCHEMA_KEY_AFFILIATE_ID => $affiliate_id,
            AffiliateMeta::SCHEMA_KEY_META_KEY => AffiliateMeta::META_KEY_CUSTOM_AFFILIATE_SLUG_IS_EDITABLE_BY_AFFILIATE,
            AffiliateMeta::SCHEMA_KEY_META_VALUE => $can_edit,
            AffiliateMeta::PRIMARY_KEY => $id
        ]);
    }

    /**
     * Wrapper function to query the AffiliateMeta model for all custom slug metas for an affiliate.
     *
     * @param int $affiliate_id
     *
     * @return array<AffiliateMeta>
     */
    public static function slug_metas_for_affiliate($affiliate_id)
    {
        return AffiliateMeta::where([
            AffiliateMeta::SCHEMA_KEY_AFFILIATE_ID => $affiliate_id,
            AffiliateMeta::SCHEMA_KEY_META_KEY => AffiliateMeta::META_KEY_CUSTOM_AFFILIATE_SLUG
        ]);
    }

    /**
     * Queries the AffiliateMeta model for the Affiliate ID given a custom slug.
     * If there is no custom slug meta for the give slug, then return null.
     *
     * @global \wpdb $wpdb
     * @param string $slug
     *
     * @return null|int
     */
    public static function maybe_get_affiliate_id_from_slug($slug)
    {
        global $wpdb;
        $table = $wpdb->prefix . AffiliateMeta::TABLE;
        $col1 = AffiliateMeta::SCHEMA_KEY_META_KEY;
        $col2 = AffiliateMeta::SCHEMA_KEY_META_VALUE;
        $query = Validators::str($wpdb->prepare(
            "SELECT affiliate_id FROM {$table} WHERE {$col1} = %s AND {$col2} = %s",
            AffiliateMeta::META_KEY_CUSTOM_AFFILIATE_SLUG,
            $slug
        ));
        $maybe_id = $wpdb->get_var($query);

        if (is_null($maybe_id)) {
            return null;
        } else {
            return (int)$maybe_id;
        }
    }

    /**
     * Checks this count of custom slug metas against the per affiliate limit setting.
     *
     * @param int $affiliate_id
     *
     * @return boolean
     */
    public static function has_affiliate_reached_slug_limit($affiliate_id)
    {
        $count = self::_custom_slug_meta_count_for_affiliate($affiliate_id);
        return $count >= AffiliateCustomSlugBase::get_per_affiliate_slug_limit();
    }

    /**
     * Queries the AffiliateMeta model for the count of custom slug metas an affiliate has.
     *
     * @param int $affiliate_id
     *
     * @return int
     */
    private static function _custom_slug_meta_count_for_affiliate($affiliate_id)
    {
        return AffiliateMeta::count([
            AffiliateMeta::SCHEMA_KEY_META_KEY => AffiliateMeta::META_KEY_CUSTOM_AFFILIATE_SLUG,
            AffiliateMeta::SCHEMA_KEY_AFFILIATE_ID => $affiliate_id
        ]);
    }

    /**
     * Queries the AffiliateMeta model for an existing record given a custom slug. If it exists return true, otherwise false.
     *
     * @param string $slug
     *
     * @return boolean
     */
    public static function does_slug_already_exist($slug)
    {
        return (bool)self::_maybe_find_custom_slug_meta_by_slug($slug);
    }

    /**
     * Queries the AffiliateMeta model for an existing record given a custom slug, returning null if it does not exist.
     *
     * @param string $slug
     *
     * @return AffiliateMeta|null
     */
    private static function _maybe_find_custom_slug_meta_by_slug($slug)
    {
        return AffiliateMeta::find_where(
            [
                AffiliateMeta::SCHEMA_KEY_META_KEY => AffiliateMeta::META_KEY_CUSTOM_AFFILIATE_SLUG,
                AffiliateMeta::SCHEMA_KEY_META_VALUE => $slug
            ]
        );
    }

    /**
     * Queries the AffiliateMeta model for the Affiliate's "can edit custom slugs" permissions meta.
     * Do to not backfilling this meta, and teh system expecting it to always exist, a "null" meta that does not grant permission will be returned if the record does not exist.
     *
     * @param Affiliate $affiliate
     *
     * @return AffiliateMeta
     */
    public static function can_edit_slugs_meta_for_affiliate($affiliate)
    {
        $maybe_meta = AffiliateMeta::find_where([
            AffiliateMeta::SCHEMA_KEY_AFFILIATE_ID => $affiliate->id,
            AffiliateMeta::SCHEMA_KEY_META_KEY => AffiliateMeta::META_KEY_CUSTOM_AFFILIATE_SLUG_IS_EDITABLE_BY_AFFILIATE
        ]);

        if (is_null($maybe_meta)) {
            return self::_null_can_edit_slugs_meta($affiliate);
        } else {
            return $maybe_meta;
        }
    }

    /**
     * Represents a "null" meta that does not grant permission to edit custom slugs for an Affiliate.
     * The ID is 0, so if it passed back to the POST_edit... controller action the upsert function will create the meta and this "null" meta will not have to be used for the Affiliate again.
     *
     * @param Affiliate $affiliate
     *
     * @return AffiliateMeta
     */
    private static function _null_can_edit_slugs_meta($affiliate)
    {
        return new AffiliateMeta([
            AffiliateMeta::SCHEMA_KEY_AFFILIATE_ID => $affiliate->id,
            AffiliateMeta::SCHEMA_KEY_META_KEY => AffiliateMeta::META_KEY_CUSTOM_AFFILIATE_SLUG_IS_EDITABLE_BY_AFFILIATE,
            AffiliateMeta::SCHEMA_KEY_META_VALUE => false,
            AffiliateMeta::PRIMARY_KEY => 0
        ]);
    }
}