<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Lib\AffiliateRegistrationForm\AffiliateRegistrationFormFunctions;
use SolidAffiliate\Models\Affiliate;

class ErrorMessages
{
    /**
     * Store special error messages here to be grabbed by Notices::custom_admin_notice_error or used directly. Or if the message is already complete, then it will return the message as is.
     *
     * @param string $key_or_msg
     *
     * @return string
     */
    public static function get_message($key_or_msg)
    {
        $custom_registration_form_docs_text = __('Validations and Rules', 'solid-affiliate');
        switch ($key_or_msg) {
            case AffiliateRegistrationFormFunctions::ERROR_KEY_UNIQUE_NAMES:
                return self::_add_docs_link(__('All field names must be unique.', 'solid-affiliate'), AffiliateRegistrationFormFunctions::DOCS_URL, $custom_registration_form_docs_text);
            case AffiliateRegistrationFormFunctions::ERROR_KEY_ENCODE_FAILURE:
                return self::_add_docs_link(__('There was an error encoding the custom form.', 'solid-affiliate'), AffiliateRegistrationFormFunctions::DOCS_URL, $custom_registration_form_docs_text);
            case AffiliateRegistrationFormFunctions::ERROR_KEY_DECODE_FAILURE:
                return self::_add_docs_link(__('There was an error decoding the custom form.', 'solid-affiliate'), AffiliateRegistrationFormFunctions::DOCS_URL, $custom_registration_form_docs_text);
            case AffiliateRegistrationFormFunctions::ERROR_KEY_PAST_TYPE_CONFLICT:
                return self::_add_docs_link(__('You cannot create a custom field with the same name, but of a different form field type, as a previous custom field.', 'solid-affiliate'), AffiliateRegistrationFormFunctions::DOCS_URL, $custom_registration_form_docs_text);
            case AffiliateRegistrationFormFunctions::ERROR_KEY_UNIQUE_LABELS:
                return self::_add_docs_link(__('All field labels must be unique.', 'solid-affiliate'), AffiliateRegistrationFormFunctions::DOCS_URL, $custom_registration_form_docs_text);
            case AffiliateRegistrationFormFunctions::ERROR_KEY_EMPTY_NAME:
                return self::_add_docs_link(__('All fields require a name.', 'solid-affiliate'), AffiliateRegistrationFormFunctions::DOCS_URL, $custom_registration_form_docs_text);
            case AffiliateRegistrationFormFunctions::ERROR_KEY_EMPTY_LABEL:
                return self::_add_docs_link(__('All fields require a label.', 'solid-affiliate'), AffiliateRegistrationFormFunctions::DOCS_URL, $custom_registration_form_docs_text);
            case AffiliateRegistrationFormFunctions::ERROR_KEY_EMPTY_OR_DUP_ENUM_OPTION:
                return self::_add_docs_link(__('Option labels and values for multi-checkbox groups, radio-groups, and select dropdowns cannot be empty or duplicates.', 'solid-affiliate'), AffiliateRegistrationFormFunctions::DOCS_URL, $custom_registration_form_docs_text);
            case AffiliateRegistrationFormFunctions::ERROR_KEY_RESERVED_NAME_CONFLICT:
                $msg = __('You cannot create a custom field with a name that is reserved by the Affiliate database table. These names are', 'solid-affiliate') . ':'
                    . '<br /><code>' . implode("</code><code>&nbsp;", Affiliate::RESERVED_AFFILIATE_COLUMNS) . '</code>';
                return self::_add_docs_link($msg, AffiliateRegistrationFormFunctions::DOCS_URL, $custom_registration_form_docs_text);
            default:
                return $key_or_msg;
        }
    }

    /**
     * Adds a link after the error message to a given docs URL.
     *
     * @param string $msg
     * @param string $href
     * @param string $text
     *
     * @return string
     */
    private static function _add_docs_link($msg, $href, $text)
    {
        return $msg . '<hr /><p class="docs-link">' . __('You can find the documentation here', 'solid-affiliate') . ': ' . "<a href={$href} target='_blank'>" . $text . '</a></p>';
    }
}
