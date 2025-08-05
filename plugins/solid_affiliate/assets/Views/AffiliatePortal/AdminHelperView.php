<?php

namespace SolidAffiliate\Views\AffiliatePortal;

use SolidAffiliate;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\VO\AffiliatePortal\AffiliatePortalRegistrationViewInterface;
use SolidAffiliate\Views\Shared\SolidTooltipView;

class AdminHelperView
{

    const FORM_ID = 'solid-affiliate-affiliate-portal_admin-helper';

    /**
     * @param AffiliatePortalRegistrationViewInterface $Iregistration
     *
     * @return string
     */
    public static function render($Iregistration)
    {
        $affiliate_portal_settings_url = URLs::settings(Settings::TAB_AFFILIATE_PORTAL_AND_REGISTRATION, false);
        $customize_registration_form_url = URLs::settings(Settings::TAB_CUSTOMIZE_REGISTRATION_FORM, false);
        $affiliate_portal_docs_url = "https://docs.solidaffiliate.com/configuration/#configure-your-affiliate-portal";
        $solid_affiliate_support_url = "https://solidaffiliate.com/support/";
        $affiliate_portal_preview_tool_url = URLs::admin_portal_preview_path(0, false);

        ob_start();
?>

        <?php echo (self::render_css()) ?>

        <div class="solid-affiliate-affiliate-portal_admin-helper">
            <div class="admin-helper-heading"><?php _e('Solid Affiliate Admin Helper', 'solid-affiliate') ?>: </div>

            <?php echo (SolidTooltipView::render_pretty(
                "<span class='admin-helper_tooltip-header'>" . __('Solid Affiliate Admin Helper', 'solid-affiliate') . "</span> <br> <strong>Affiliate Registration and Portal</strong>",
                __('Here are some tips on how the affiliate registration and portal works and how to customize it.', 'solid-affiliate') . '<br>' . __('Only you, as a logged in admin, will see this. Your affiliates will not see this.', 'solid-affiliate'),
                "<ul>
                    <strong>" . __('Settings', 'solid-affiliate') . "</strong> " . __('To configure your registration form or portal, start here.', 'solid-affiliate') . "
                    <li>". __('Read the', 'solid-affiliate') . " <a href='$affiliate_portal_docs_url'>" . __('affiliate portal guide', 'solid-affiliate') . "</a>.</li>
                    <li>" .__("View your site's", 'solid-affiliate') ." <a href='$affiliate_portal_settings_url'>". __('affiliate portal settings', 'solid-affiliate') ."</a>.</li>
                    <li>" .__("Add registration fields here", 'solid-affiliate')." <a href='$customize_registration_form_url'>". __('customize registration form', 'solid-affiliate'). "</a>.</li>
                </ul>
                <ul>
                    <strong>".__('Preview portals', 'solid-affiliate')."</strong> ".__('You can now inspect the portals of any one of your affiliates.', 'solid-affiliate')."
                    <li>". __("Use the", 'solid-affiliate')." <a href='$affiliate_portal_preview_tool_url'>".__('affiliate portal preview tool', 'solid-affiliate')."</a>.</li>
                </ul>",
                __("If you have any questions regarding the affiliate registration and portal, please contact",'solid-affiliate')." <a href='$solid_affiliate_support_url'>".__('Solid Affiliate support', 'solid-affiliate')."</a>."
            )) ?>

        </div>

    <?php
        return ob_get_clean();
    }

    /**
     * @return string
     */
    public static function render_css()
    {
        ob_start();
    ?>

        <style>
            .solid-affiliate-affiliate-portal_admin-helper {
                padding: 20px;
                border: 2px dashed #ed6802;
                background: #f1844417;
                margin-bottom: 20px;
                /* border: 2px dashed #ed6802; */
                /* background: #f1844417; */
                margin-bottom: 20px;
                border-color: var(--sld-ap-border-color);
                border-width: 1px;
                border-style: solid;
                border-radius: 5px;
            }

            .solid-affiliate-affiliate-portal_admin-helper .admin-helper-heading {
                display: inline-block;
            }

            .admin-helper_tooltip-header {
                color: #ed6802;
            }

            .admin-helper_section-heading {
                font-weight : 400;
                margin-bottom: -10px;
            }

            .solid-affiliate-affiliate-portal_admin-helper li {
                margin-left: 16px;
            }

            /* Solid Tooltips */
            .sld-tooltip {
                display: inline-block;
                vertical-align: sub;
                width: 15px;
            }

            .sld-tooltip-box {
                padding: 5px 9px;
            }

            .sld-tooltip_heading {
                margin-bottom: 3px;
                font-size: 14px;
                color: #000000 !important;
            }

            .sld-tooltip_sub-heading {
                font-size: 13px;
                color: #7d7d7d !important;
            }

            .sld-tooltip_body {
                margin: 5px 0;
                font-size: 13px;
                color: #606060 !important;
            }

            .sld-tooltip_hint {
                border-top: 1px solid #dedede;
                padding-top: 9px;
                font-size: 12px;
                color: #7c7c7c !important;
            }

            .sld-tooltip_hint a {
                color: #2271b1 !important;
                text-decoration: underline;
            }
        </style>

<?php
        return ob_get_clean();
    }
}
