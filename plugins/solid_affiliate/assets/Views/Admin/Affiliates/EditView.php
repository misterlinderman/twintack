<?php

namespace SolidAffiliate\Views\Admin\Affiliates;

use SolidAffiliate\Controllers\AffiliatesController;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliatePortal;

class EditView
{
    const AFFILIATE_EDIT_AFTER_FORM_FILTER = 'solid_affiliate/affiliates/edit/after_form';

    /**
     * @param object $item
     * @return string
     */
    public static function render($item)
    {
        $singular = __('Affiliate', 'solid-affiliate');
        $form_id = 'affiliates-edit';
        $schema = Affiliate::schema_with_custom_registration_data();
        $nonce = AffiliatesController::NONCE_SUBMIT_AFFILIATE;
        $submit_action = AffiliatesController::POST_PARAM_SUBMIT_AFFILIATE;
        $wp_user = get_userdata((int)$item->user_id);
        $form = FormBuilder::render_crud_form_edit($schema, $submit_action, $nonce, $form_id, $singular, $item);
        $affiliate = Affiliate::find((int)$item->id);

        ob_start();
?>
        <div class="wrap has-form">
            <div class="sld_form-container">
                <div class="sld_form-wrapper">
                    <div class="sld_form-head">
                        <h1><?php echo sprintf(__('Update %1$s', 'solid-affiliate'), $singular); ?></h1>
                        <p>Use the forms below to update your affiliate account information, adjust their store credit balance, assign custom slugs and more.</p>
                    </div>
                    <div class="edit-affiliate-shortinfo">
                        <?php
                        if ($wp_user && $affiliate instanceof Affiliate) {
                            echo self::render_affiliate_user_details($wp_user, $affiliate);
                        }
                        ?>
                    </div>
                    <div class="sld_collapsible-section">
                        <div class="sld_form-head has-icon sld_collapsible-header">
                            <div class="icon-wrapper" style="display: flex; align-items: center;">
                                <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M8 28V25.3333C8 23.9188 8.5619 22.5623 9.5621 21.5621C10.5623 20.5619 11.9188 20 13.3333 20H18.6667C20.0812 20 21.4377 20.5619 22.4379 21.5621C23.4381 22.5623 24 23.9188 24 25.3333V28M10.6667 9.33333C10.6667 10.7478 11.2286 12.1044 12.2288 13.1046C13.229 14.1048 14.5855 14.6667 16 14.6667C17.4145 14.6667 18.771 14.1048 19.7712 13.1046C20.7714 12.1044 21.3333 10.7478 21.3333 9.33333C21.3333 7.91885 20.7714 6.56229 19.7712 5.5621C18.771 4.5619 17.4145 4 16 4C14.5855 4 13.229 4.5619 12.2288 5.5621C11.2286 6.56229 10.6667 7.91885 10.6667 9.33333Z" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <div class="text-wrapper" style="margin-left: 10px;">
                                <h2 id="edit-affiliate-affiliate_meta">Account details</h2>
                                <p>Manage unique affiliate preferences and settings.</p>
                            </div>
                            <span class="toggle-icon">
                                <svg class="plus-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 4V20M20 12H4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <svg class="minus-icon" style="display:none;" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M20 12H4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>

                        <div class="sld_collapsible-content" style="display:none;">
                            <?php echo $form; ?>
                        </div>
                    </div>
                    <?php echo self::render_per_affiliate_per_product_commissions_component((int)$item->id); ?>

                    <!-- Additional panels -->
                    <?php
                    $data_panels = Validators::array_of_string(apply_filters(self::AFFILIATE_EDIT_AFTER_FORM_FILTER, [], $affiliate));
                    foreach ($data_panels as $panel) {
                    ?>
                        <div class='sld_edit-panel'>
                            <?php echo $panel; ?>
                        </div>
                    <?php
                    }
                    ?>
                </div>
            </div>
        </div>


    <?php
        return ob_get_clean();
    }

    /**
     *
     * @param \WP_User $wp_user
     * @param Affiliate $affiliate
     * @return string
     */
    public static function render_affiliate_user_details($wp_user, $affiliate)
    {
        // Ensure we have a valid user
        if ($wp_user instanceof \WP_User === false) {
            return '';
        }

        ob_start();
    ?>
        <section class="affiliate-user-details">
            <div class="affiliate-user-details-container">
                <!-- Email -->
                <div class="detail-item email">
                    <div class="muted-text-card"><?php _e('Email', 'solid-affiliate'); ?></div>
                    <a href="mailto:<?php echo esc_attr($wp_user->user_email); ?>">
                        <?php echo esc_html($wp_user->user_email); ?>
                    </a>
                </div>

                <div class="detail-item username">
                    <div class="muted-text-card"><?php _e('Username', 'solid-affiliate'); ?></div>
                    <a href="<?php echo esc_url(get_edit_user_link($wp_user->ID)); ?>" title="<?php _e('Edit User Profile', 'solid-affiliate'); ?>">
                        <?php echo esc_html($wp_user->user_login); ?>
                    </a>
                </div>

                <div class="detail-item full-name">
                    <div class="muted-text-card"><?php _e('Name', 'solid-affiliate'); ?></div>
                    <?php
                    $full_name = trim($wp_user->first_name . ' ' . $wp_user->last_name);
                    echo esc_html($full_name ?: __('Not Provided', 'solid-affiliate'));
                    ?>
                </div>

                <?php if ($affiliate instanceof Affiliate): ?>
                    <div class="detail-item affiliate-link">
                        <div class="muted-text-card"><?php _e('Default affiliate link', 'solid-affiliate'); ?></div>
                        <?php echo esc_attr(URLs::default_affiliate_link($affiliate)); ?>
                    </div>
                <?php endif; ?>

                <div class="detail-item" style="align-self: flex-end;">
                    <?php echo AffiliatePortal::render_affiliate_portal_preview_section_on_affiliate_edit($affiliate); ?>
                </div>

            </div>
        </section>
    <?php
        return ob_get_clean();
    }

    /**
     *
     * @param int $affiliate_id
     * 
     * @return string
     */
    public static function render_per_affiliate_per_product_commissions_component($affiliate_id)
    {
        $affiliate = Affiliate::find($affiliate_id);

        if (!($affiliate instanceof Affiliate)) {
            return sprintf(__('No Affiliate with ID %1$s found.', 'solid-affiliate'), $affiliate_id);
        }
        ob_start();
    ?>
<?php
        return ob_get_clean();
    }
}
