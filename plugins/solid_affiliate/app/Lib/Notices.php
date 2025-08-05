<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Lib\ControllerFunctions;

/**
 * Notices
 * 
 * Responsible for the message/error notices on the top of the screen.
 * For example when successfully creating a record, you get a nice success message.
 * 
 * @psalm-suppress PropertyNotSetInConstructor
 */
class Notices
{
    const URL_PARAM_MESSAGE = 'sld_message';
    const URL_PARAM_ERROR = 'sld_error';
    /**
     * @return void
     */
    public static function register_hooks()
    {
        add_filter(
            'removable_query_args',
            /**
             * @param mixed $args
             */
            function ($args) {
                if (is_array($args)) {
                    $args[] = self::URL_PARAM_MESSAGE;
                    $args[] = self::URL_PARAM_ERROR;
                }
                return $args;
            }
        );

        if (isset($_GET[self::URL_PARAM_MESSAGE])) {
            self::add_notice_hook();
        }
        if (isset($_GET[self::URL_PARAM_ERROR])) {
            self::add_notice_error_hook();
        }
    }

    /**
     * @return void
     */
    public static function add_notice_hook()
    {
        add_action('admin_notices', 'SolidAffiliate\Lib\Notices::custom_admin_notice');
    }

    /**
     * @return void
     */
    public static function add_notice_error_hook()
    {
        add_action('admin_notices', 'SolidAffiliate\Lib\Notices::custom_admin_notice_error');
    }

    /**
     * @return void
     */
    public static function custom_admin_notice()
    {
        if (!isset($_GET[self::URL_PARAM_MESSAGE])) {
            return;
        }

        $msg = (string) $_GET[self::URL_PARAM_MESSAGE];

        echo ('<div class="notice notice-info is-dismissible">');
        echo ('<p>');
        echo (__($msg, 'solid-affiliate'));
        echo ('</p>');
        echo ('</div>');
    }

    /**
     * @return void
     */
    public static function custom_admin_notice_error()
    {
        if (!isset($_GET[self::URL_PARAM_ERROR])) {
            return;
        }

        $msg = ErrorMessages::get_message(Validators::str_from_array($_GET, self::URL_PARAM_ERROR));

        echo ('<div class="notice notice-error is-dismissible">');
        echo ('<p>');
        echo (__($msg, 'solid-affiliate'));
        echo ('</p>');
        echo ('</div>');
    }

    ///////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////

}
