<?php
/**
 * Hero carousel for Homepage V2.
 *
 * @package twintack2025
 */

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('TwinTack_Marketing_Hero_Carousel')) {
    echo do_shortcode('[twintack_hero_carousel context="homepage"]');
}
