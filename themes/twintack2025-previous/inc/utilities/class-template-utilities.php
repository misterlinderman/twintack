<?php
/**
 * Template Utilities Class
 * Common template helper functions
 */
class Template_Utilities {
    public static function get_formatted_date($post_id = null) {
        $post = get_post($post_id);
        return sprintf(
            '<time datetime="%s">%s</time>',
            esc_attr(get_the_date('c', $post)),
            esc_html(get_the_date('', $post))
        );
    }

    public static function get_post_categories($post_id = null) {
        $categories_list = get_the_category_list(esc_html__(', '), '', $post_id);
        if ($categories_list) {
            return sprintf(
                '<span class="cat-links">%s %s</span>',
                esc_html__('Posted in:', 'twintack2025'),
                $categories_list
            );
        }
        return '';
    }

    public static function get_post_tags($post_id = null) {
        $tags_list = get_the_tag_list('', esc_html__(', '), '', $post_id);
        if ($tags_list) {
            return sprintf(
                '<span class="tags-links">%s %s</span>',
                esc_html__('Tagged:', 'twintack2025'),
                $tags_list
            );
        }
        return '';
    }
}