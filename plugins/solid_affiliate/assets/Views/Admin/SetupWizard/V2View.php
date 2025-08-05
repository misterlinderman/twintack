<?php

namespace SolidAffiliate\Views\Admin\SetupWizard;

use SolidAffiliate\Controllers\SetupWizardController;
use SolidAffiliate\Lib\License;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\SetupWizard;
use SolidAffiliate\Lib\URLs;

/**
 * TODOs
 * [x] Conditionnaly show a "warning" page if the user doesn't have WooCommerce installed
 * [x] license key step (???) (make skippable later)
 * [x] Default values where possible
 * [x] Make the coupon step actually work
 * [x] Auto create Affiliate - performance. doesn't work for more than 10 users.
 *   [x] Move to background job
 * [x] Handle error messages/error states for steps. Such as the affiliate portal creation step for example. Handle the case the page already exists etc.
 * [x] form field validations
 *  [x] server side
 * [x] Add the ability to skip certain steps
 *  [x] plz better design of skip buttons
 * [x] better design of loading state - consider that a request might be pending a long time
 * [x] Complete the final "confirmation" step/page
 * [x] proper, helpful copy
 * [x] "create new affiliate for users" - email copy. make sure it works properly and will include their coupon codes.
 * [x] Conditionally hide the "create new affiliates for users" step if there aren't any users to create affiliates for
 * [x] Conditionally hide the "recurring" commission step if the user doesn't have WooCommerce Subscriptions
 * [x] Make DEFAULT_WELCOME_EMAIL translatable?

 * 
 * Before launch
 * [x] usability testing
 * [ ] Translations
 * [x] psalm
 * [x] refactor 
 *   [x] (remember backend functions) deprecate old setup wizard when ready.
 *   [x] code cleanup of this file
 * [x] figure out how to handle people coming back to the setup wizard; refreshing the page, etc. 
 *     Should we lock down the rest of the plugin until the setup wizard is done?
 *     What happens when they "complete" the setup wizard? What even counts as "complete"? Do we hide the wizard like the v1 wizard?
 *      [x] When wizard is still active: Hide all the admin menus pages with CSS (except the setup wizard page).
 *      [x] Final step there should be a "Complete Setup" button that will hide the wizard and unlock the rest of the plugin. Hides the setup. 
 *      [x] remove the "Setup Wizard" menu item from the admin menu when complete
 * 
 * V2
 * [ ] AI blog/post announcement page step
 * [ ] incorporate the Badass book
 * [ ] Add a video to the welcome step
 * [ ] add video+documentation links
 * [ ] API data validation on incoming data
 * 
 * @psalm-type SyncedData = array{
 *     currentPage: int,
 *     isWooCommerceActive: bool,
 *     inputLicenseKey: string,
 *     isSolidAffiliateActive: bool,
 *     inputDefaultReferralRate: float,
 *     inputDefaultReferralRateType: 'percentage'|'flat',
 *     isDefaultReferralRateConfigured: bool,
 *     inputDefaultRecurringReferralRate: float,
 *     inputDefaultRecurringReferralRateType: 'percentage'|'flat',
 *     isDefaultRecurringReferralRateConfigured: bool,
 *     inputPortalPageTitle: string,
 *     inputPortalSlug: string,
 *     isPortalConfigured: bool,
 *     inputDefaultEmailFromName: string,
 *     inputDefaultFromEmail: string,
 *     inputCouponRate: float,
 *     inputCouponRateType: 'percentage'|'flat',
 *     inputIsCouponIndividualUse: bool,
 *     inputIsCouponExcludeSaleItems: bool, 
 *     inputWhichUsersToInvite: 'all-customers' | 'all-users',
 *     portalPageURL: string,
 *     totalEligibleUsersForAffiliateCreation: int,
 *     inputWelcomeEmail: string,
 *     errors: string[],
 *     isOnKeylessFreeTrial: bool,
 *     keylessFreeTrialEndsAt: int
 * }
 * 
 * 
 */
class V2View
{

    /**
     * @return string
     */
    public static function default_welcome_email()
    {
        return __("<p>We are excited to inform you of the new referral program we now have on our site. You can now earn cash or store credit by referring your friends to our site through our affiliate program. You can do this simply by sharing your unique affiliate link (found below) with your friends and family.</p><p>There's nothing you need to do - everything is automatically tracked and you'll receive an email whenever someone makes a purchase through your link. You can also access <a href='{site_portal_url}'>your affiliate portal</a> at any time to see who has clicked on your link or made a purchase.</p><p>Your affiliate link: </span><strong>{default_affiliate_link}</strong><br><span>Your coupon code: </span><strong>{default_affiliate_coupon_code_or_note}</strong><br></p><p>We appreciate your support and value your role in helping our company grow and improve through word of mouth and recommendations. Thank you for being a valued member of our community.</p><p><br></p>", 'solid-affiliate');
    }

    /**
     * @return string
     */
    public static function render()
    {
        ob_start();
?>

        <script src="https://cdn.jsdelivr.net/npm/@caneara/iodine@8.3.0/dist/iodine.min.umd.js" defer></script>
        <script src="https://unpkg.com/@alpinejs/persist@3.x.x/dist/cdn.min.js"></script>
        <!-- <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script> -->
        <script>
            document.addEventListener('alpine:init', () => {
                // Set up Alpine.store to handle the state of our setup/onboarding wizard
                Alpine.store('setupWizard', {
                    init() {
                        Alpine.effect(() => {
                            this.syncedData.inputDefaultReferralRate = this.validateRate(this.syncedData.inputDefaultReferralRate, this.syncedData.inputDefaultReferralRateType);
                        });
                        Alpine.effect(() => {
                            this.syncedData.inputDefaultRecurringReferralRate = this.validateRate(this.syncedData.inputDefaultRecurringReferralRate, this.syncedData.inputDefaultRecurringReferralRateType);
                        })
                        Alpine.effect(() => {
                            this.syncedData.inputCouponRate = this.validateRate(this.syncedData.inputCouponRate, this.syncedData.inputCouponRateType);
                        })
                    },
                    pendingAjax: false,
                    previousAjaxResponse: null,
                    /////////////////////////////// 
                    // syncedData: data that is synced with the server. The server will respond
                    // with the current state of the setup wizard and this data will be updated
                    syncedData: {
                        errors: [],
                        currentPage: 1,
                        isWooCommerceActive: <?php echo SetupWizard::is_woocommerce_active() ? 'true' : 'false'; ?>,
                        isWooCommerceSubscriptionsActive: <?php echo SetupWizard::is_woocommerce_subscriptions_active() ? 'true' : 'false'; ?>,
                        inputLicenseKey: <?php echo json_encode(\SolidAffiliate\Lib\License::get_license_key()) ?>,
                        isSolidAffiliateActive: <?php echo \SolidAffiliate\Lib\License::is_solid_affiliate_activated_and_not_expired() ? 'true' : 'false'; ?>,
                        isOnKeylessFreeTrial: <?php echo \SolidAffiliate\Lib\License::is_on_keyless_free_trial() ? 'true' : 'false'; ?>,
                        keylessFreeTrialEndsAt: <?php echo \SolidAffiliate\Lib\License::get_keyless_free_trial_end_timestamp() ? json_encode(\SolidAffiliate\Lib\License::get_keyless_free_trial_end_timestamp()) : 'null'; ?>,

                        inputDefaultReferralRate: '',
                        inputDefaultReferralRateType: 'percentage',
                        isDefaultReferralRateConfigured: false,

                        inputDefaultRecurringReferralRate: '',
                        inputDefaultRecurringReferralRateType: 'percentage',
                        isDefaultRecurringReferralRateConfigured: false,

                        inputPortalPageTitle: 'Affiliate Portal',
                        inputPortalSlug: 'affiliates',
                        isPortalConfigured: false,

                        inputDefaultEmailFromName: <?php echo Settings::get(Settings::KEY_EMAIL_FROM_NAME) ? json_encode(Settings::get(Settings::KEY_EMAIL_FROM_NAME)) : json_encode(''); ?>,
                        inputDefaultFromEmail: <?php echo Settings::get(Settings::KEY_FROM_EMAIL) ? json_encode(Settings::get(Settings::KEY_FROM_EMAIL)) : json_encode(''); ?>,

                        /////////////////////////////////////
                        // Coupon Configs
                        inputCouponRate: '',
                        inputCouponRateType: 'percentage',
                        inputIsCouponIndividualUse: true,
                        inputIsCouponExcludeSaleItems: true,
                        /////////////////////////////////////

                        inputWhichUsersToInvite: 'all-users',

                        portalPageURL: '',

                        totalEligibleUsersForAffiliateCreation: <?php echo SetupWizard::total_eligible_users_for_affiliate_creation(); ?>,
                        inputWelcomeEmail: <?php echo json_encode(self::default_welcome_email()); ?>,
                    },
                    nextPage() {
                        this.syncedData.errors = []
                        this.syncedData.currentPage++
                    },
                    prevPage() {
                        this.syncedData.errors = []
                        this.syncedData.currentPage--
                    },
                    computedIsDefaultReferralRateInputsValid() {
                        return this.syncedData.inputDefaultReferralRateType && this.syncedData.inputDefaultReferralRate
                    },
                    computedIsDefaultRecurringReferralRateInputsValid() {
                        return this.syncedData.inputDefaultRecurringReferralRateType && this.syncedData.inputDefaultRecurringReferralRate
                    },
                    computedIsPortalInputsValid() {
                        return this.syncedData.inputPortalPageTitle && this.syncedData.inputPortalSlug
                    },
                    computedIsCouponInputsValid() {
                        return this.syncedData.inputCouponRateType && this.syncedData.inputCouponRate
                    },
                    computedIsEmailInputsValid() {
                        return this.syncedData.inputDefaultEmailFromName && this.syncedData.inputDefaultFromEmail
                    },
                    computedIsInviteUsersInputsValid() {
                        return this.syncedData.inputWhichUsersToInvite
                    },
                    computedDefaultCommissionPreview() {
                        examplePrice = 200.0
                        if (this.syncedData.inputDefaultReferralRateType && this.syncedData.inputDefaultReferralRate) {
                            if (this.syncedData.inputDefaultReferralRateType == 'percentage') {
                                return SolidAffiliateAdmin.format_money(examplePrice * (this.syncedData.inputDefaultReferralRate / 100.0))
                            } else {
                                return SolidAffiliateAdmin.format_money(this.syncedData.inputDefaultReferralRate)
                            }
                        } else {
                            return '-';
                        }
                    },
                    computedDefaultRecurringCommissionPreview() {
                        examplePrice = 90.0
                        if (this.syncedData.inputDefaultRecurringReferralRateType && this.syncedData.inputDefaultRecurringReferralRate) {
                            if (this.syncedData.inputDefaultRecurringReferralRateType == 'percentage') {
                                return SolidAffiliateAdmin.format_money(examplePrice * (this.syncedData.inputDefaultRecurringReferralRate / 100.0))
                            } else {
                                return SolidAffiliateAdmin.format_money(this.syncedData.inputDefaultRecurringReferralRate)
                            }
                        } else {
                            return '-';
                        }
                    },
                    computedExampleCoupon() {
                        couponDiscount = this.syncedData.inputCouponRate || '—';
                        return "JOHN" + couponDiscount;
                    },
                    computedMaskedLicenseKey() {
                        licenseKey = this.syncedData.inputLicenseKey;
                        if (licenseKey) {
                            return licenseKey.substring(0, 12) + '-xxxxxxxx-xxxxxxxx';
                        } else {
                            return '';
                        }

                    },
                    validateSyncedData(data) {
                        // TODO this validation is not actually doing anything, or complete. 
                        // Will do at the end once our data model is finalized
                        rules = {
                            currentage: ['optional', 'integer'],
                            isWooCommerceActive: ['optional', 'boolean'],
                            inputLicenseKey: ['optional', 'string'],
                            isSolidAffiliateActive: ['optional', 'boolean'],

                            inputDefaultEmailFromName: ['optional', 'string'],
                            inputDefaultFromEmail: ['optional', 'string'],

                            inputDefaultReferralRate: ['optional', 'numeric'],
                            inputDefaultReferralRateType: ['optional', 'string', 'in:percentage,fixed'],
                            isDefaultReferralRateConfigured: ['optional', 'boolean'],

                            inputPortalSlug: ['optional', 'string'],
                            isPortalConfigured: ['optional', 'boolean'],
                        }

                        return Iodine.assert(data, rules);
                    },
                    validateRate(rate, rateType) {
                        if (rate != '' && rate != null) {
                            rate = Math.max(0, rate);

                            if (rateType == 'percentage') {
                                rate = Math.min(100, rate);
                            }
                        }
                        return rate;
                    },
                    postData(wizardAction) {
                        this.pendingAjax = true;
                        jQuery.post(ajaxurl, {
                                action: 'sld_affiliate_setup_wizard_v2_post',
                                wizardAction: wizardAction,
                                syncedData: this.syncedData,
                            }, (response) => {
                                this.previousAjaxResponse = JSON.parse(JSON.stringify(response));

                                if (response.data && response.data.redirectUrl) {
                                    window.location.href = response.data.redirectUrl;
                                    return;
                                }

                                // TODO add Validator
                                // validation = this.validateSyncedData(response.data.syncedData)
                                this.syncedData = Object.assign(this.syncedData, response.data.syncedData)
                            })
                            .fail((error) => {
                                console.log(error);
                            })
                            .always(() => {
                                this.pendingAjax = false;
                            });
                    },
                    isCurrentPage(int) {
                        return this.syncedData.currentPage == int;
                    },
                    shouldShowStep(step) {
                        currentPage = this.syncedData.currentPage;

                        stepsInOrder = [
                            'welcome',
                            'portal',
                            'referral_rate',
                            'recurring_referral_rate',
                            'email_settings',
                            'coupons',
                            'import_existing_users',
                            'license',
                            'final'
                        ]
                        // if (!isOnKeylessFreeTrial) then we need to flip the 'license' and 'import_existing_users' steps
                        // solve the problem using functional programming
                        if (!this.syncedData.isOnKeylessFreeTrial) {
                            stepsInOrder = stepsInOrder.map((step) => {
                                if (step == 'license') {
                                    return 'import_existing_users';
                                } else if (step == 'import_existing_users') {
                                    return 'license';
                                } else {
                                    return step;
                                }
                            })
                        }

                        ////////////////////////////////////////////////////////////////    
                        // Update the stepsInOrder if we need to skip any steps. we need to 
                        // remove the skipped steps from the stepsInOrder
                        shouldSkipRecurringReferralRateStep = !this.syncedData.isWooCommerceSubscriptionsActive;
                        shouldSkipImportExistingUsersStep = (this.syncedData.totalEligibleUsersForAffiliateCreation <= 0); // || (!this.syncedData.isSolidAffiliateActive && !this.syncedData.isOnKeylessFreeTrial))
                        stepsInOrder = stepsInOrder.filter((step) => {
                            if (step == 'recurring_referral_rate' && shouldSkipRecurringReferralRateStep) {
                                return false;
                            }

                            if (step == 'import_existing_users' && shouldSkipImportExistingUsersStep) {
                                return false;
                            }

                            return true;
                        })

                        // Adjust currentPage if it exceeds the number of steps
                        // This is important, as it will catch edge cases where the user sees a blank screen.
                        // This happened once to Louis https://www.loom.com/share/5c5f8fb7e6bb444c9df72bb158bce2cf 
                        // The situation was that totalEligibleUsersForAffiliateCreation changed during the setup wizard flow,
                        // because it started out as 100 eligible users but then changed to 0 after the import_existing_users step.
                        // This caused the setup wizard to show a blank screen on the final step, because the currentPage was higher than the number of steps.
                        if (currentPage > stepsInOrder.length) {
                            currentPage = stepsInOrder.length;
                        }

                        $shouldShow = stepsInOrder.indexOf(step) == (currentPage - 1);
                        ////////////////////////////////////////////////////////////////////
                        // Hack to focus the proper element once a new step is shown
                        if ($shouldShow) {
                            const steps = {
                                referral_rate: '[x-ref="inputDefaultReferralRate"]',
                                recurring_referral_rate: '[x-ref="inputDefaultRecurringReferralRate"]',
                                coupons: '[x-ref="inputCouponRate"]',
                            };
                            if (steps[step]) {
                                Alpine.nextTick(() => {
                                    $el = document.querySelector(steps[step]);
                                    setTimeout(() => {
                                        $el.focus();
                                    }, 100);
                                });
                            }
                        }
                        ////////////////////////////////////////////////////////////////////

                        return $shouldShow;
                    },
                    getEmailEditorContent() {
                        id = 'sld-setup_welcome-email-editor';

                        if (jQuery("#wp-" + id + "-wrap").hasClass("tmce-active")) {
                            return tinyMCE.get(id).getContent();
                        } else {
                            return jQuery("#" + id).val();
                        }
                    },
                    updateWelcomeEmail() {
                        this.syncedData.inputWelcomeEmail = this.getEmailEditorContent();
                    },
                });
            })
        </script>

        <style>
            #wpbody-content {
                padding-bottom: 0 !important;
            }

            #wpfooter {
                display: none !important;
            }

            #wpcontent {
                padding-left: 0 !important;
            }

            .intro-glass {
                margin-top: 20px;
                padding: 20px;
                background: rgba(255, 255, 255, 0.2);
                border-radius: 16px;
                box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
                backdrop-filter: blur(5.1px);
                -webkit-backdrop-filter: blur(5.1px);
                border: 1px solid rgba(255, 255, 255, 0.44);
            }

            .intro-glass img {
                width: 50px;
            }

            .sld-free-trial-license-notice {
                border: 1px solid #ffa9a9;
                padding: 10px 20px;
                margin: 20px 20px 0 0;
                display: block;
                background: #fff5ef;
                border-radius: var(--sld-radius-sm);
            }
        </style>

        <div class="wrap full-screen">

            <div x-data class='sld-setup-v2'>
                <!-- ########################################################### -->
                <!-- START DEBUG section- section which outputs the entire store -->
                <div x-show="false">
                    <pre x-html="function() { $a = $store.setupWizard; $a.syncedData.inputWelcomeEmail = 'hi'; return JSON.stringify($a, null, 2) }"></pre>
                </div>
                <!-- END DEBUG section -->
                <!-- ########################################################### -->

                <div class="sld-wizard-intro">
                    <img id="setup-v2-solid-icon" height="20" src="https://solidaffiliate.com/brand/logo.svg" alt="">
                    <?php _e('Setup wizard', 'solid-affiliate') ?>
                </div>

                <!-- render error if there are any -->
                <?php echo (self::render_errors()); ?>

                <div x-cloak x-show="!$store.setupWizard.syncedData.isWooCommerceActive">
                    <?php echo (self::render_woocommerce_not_active()); ?>
                </div>

                <div x-cloak x-show="$store.setupWizard.syncedData.isWooCommerceActive">
                    <!-- step 1: welcome screen -->
                    <?php echo (self::render_step_welcome()); ?>
                    <!-- step 4: Affiliate Portal creation -->
                    <?php echo (self::render_step_portal()); ?>
                    <!-- step 2: default commission rate -->
                    <?php echo (self::render_step_referral_rate()); ?>
                    <!-- step 3: default recurring commission rate -->
                    <?php echo (self::render_step_recurring_referral_rate()); ?>
                    <!-- step 5: Email setting -->
                    <?php echo (self::render_step_email_settings()); ?>
                    <!-- step 6: Coupon settings -->
                    <?php echo (self::render_step_coupons()); ?>
                    <!-- step 7: Auto create affiliates for existing users -->
                    <?php echo (self::render_step_import_existing_users()); ?>
                    <!-- step: license key -->
                    <?php echo (self::render_step_license_key()); ?>
                    <!-- step final: Success Page -->
                    <?php echo (self::render_final_step()); ?>

                    <!-- step ???: Conditional steps. I'm keeping these on page 20 as work in progress. -->
                    <?php //echo (self::render_work_in_progress_steps()); 
                    ?>

                </div>


            </div>

        <?php
        return ob_get_clean();
    }

    ///////////////////////////////////////////////////////////////////////////
    // Supporting functions
    ///////////////////////////////////////////////////////////////////////////
    private static function render_debug(): string
    {
        ob_start(); ?>
        <?php
        return ob_get_clean();
    }
    private static function render_woocommerce_not_active(): string
    {
        ob_start(); ?>

            <div class="step" x-show="!$store.setupWizard.syncedData.isWooCommerceActive">
                <div class="sld-card large has-shadow preface">
                    <div class="content reversed">
                        <div class="main">
                            <div class="head">
                                <h2 class="lead"><?php _e('Whoops! WooCommerce is not activated', 'solid-affiliate') ?></h2>
                                <div class="plain"><?php _e('WooCommerce must be installed and activated on your website before you can use Solid Affiliate.', 'solid-affiliate') ?></div>
                            </div>
                            <a href="<?php echo admin_url('plugins.php'); ?>" class="sld-button primary large"><?php _e('Open plugins page', 'solid-affiliate') ?></a>
                        </div>
                    </div>
                </div>
            </div>

        <?php
        return ob_get_clean();
    }


    private static function render_step_welcome(): string
    {
        ob_start(); ?>
            <div class="step" x-show="$store.setupWizard.shouldShowStep('welcome')">
                <div class="sld-card large has-shadow preface">
                    <div class="content">
                        <div class="side intro">
                            <div class="intro-glass">
                                <img src="https://solidaffiliate.com/brand/favicon.svg" alt="Brand">
                            </div>
                        </div>
                        <div class="main">
                            <div class="head">
                                <h2 class="lead"><?php _e('Let\'s get started', 'solid-affiliate') ?></h2>
                                <div class="plain"><?php _e("We're excited to help you set up and activate an affiliate program for your WooCommerce store. The setup process is easy and straightforward, and we'll be here to guide you every step of the way!", 'solid-affiliate') ?></div>
                            </div>
                            <!-- <button class="sld-button primary large" x-on:click="$store.setupWizard.nextPage()"><?php _e('Get started', 'solid-affiliate') ?></button> -->
                            <button class="sld-button primary large" x-on:click="$store.setupWizard.postData('test')"><?php _e('Get started', 'solid-affiliate') ?></button>
                        </div>
                    </div>
                </div>
            </div>
        <?php
        return ob_get_clean();
    }


    private static function render_step_portal(): string
    {
        ob_start(); ?>
            <div class="step" x-show="$store.setupWizard.shouldShowStep('portal')">
                <div class="sld-card large has-shadow">
                    <div class="content reversed">
                        <div class="main">
                            <div class="head">
                                <h2 class="lead"><?php _e('Affiliate portal setup', 'solid-affiliate') ?></h2>
                                <div class="plain"><?php _e('Where should we add the affiliate portal page on your site? This is for affiliates to sign up and manage their accounts.', 'solid-affiliate') ?></div>
                            </div>
                            <div class="sld-form">
                                <div class="sld-field">
                                    <label for=""><?php _e('Page title', 'solid-affiliate') ?></label>
                                    <input type="text" placeholder="<?php _e('Enter page title', 'solid-affiliate') ?>" x-model="$store.setupWizard.syncedData.inputPortalPageTitle">
                                </div>
                                <div class="sld-field">
                                    <label for=""><?php _e('Page slug', 'solid-affiliate') ?></label>
                                    <input type="text" placeholder="<?php _e('e.g. affiliate-portal', 'solid-affiliate') ?>" x-model="$store.setupWizard.syncedData.inputPortalSlug">
                                </div>
                                <div style="margin-left:auto" x-show="$store.setupWizard.syncedData.isPortalConfigured">
                                    <!-- Let them know that the page already exists -->
                                    <div style="border: 1px solid orange; padding: 5px; background: #ffa50052; border-left-width: 5px;">
                                        You already have an affiliate portal configured at <a x-bind:href="$store.setupWizard.syncedData.portalPageURL" target="_blank" x-text="$store.setupWizard.syncedData.portalPageURL"></a>, we recommend that you skip this step.
                                    </div>
                                </div>
                                <div class="sld-actions">
                                    <button class="sld-button secondary large" x-on:click="$store.setupWizard.prevPage()">
                                        <?php _e('Back', 'solid-affiliate') ?>
                                    </button>
                                    <div style="margin-left:auto"  x-show="$store.setupWizard.syncedData.isPortalConfigured">
                                        <?php echo (self::render_skip_step_button()) ?>
                                    </div>
                                    <button x-bind:disabled="!$store.setupWizard.computedIsPortalInputsValid() || $store.setupWizard.pendingAjax" class="sld-button active large" x-on:click="$store.setupWizard.postData('create-portal-page')">
                                        <?php echo (self::render_ajaxspinner()); ?>
                                        <?php _e('Continue', 'solid-affiliate') ?>
                                    </button>
                                </div>

                            </div>
                        </div>
                        <div class="side sync-wizard">

                            <div class="step-preview">
                                <div class="sld-tiny-heading">
                                    <div class="lead"><?php _e('Portal', 'solid-affiliate') ?></div>
                                    <div class="plain"><?php _e('This will be a new page on your site for affiliates to register and see their portals.', 'solid-affiliate') ?></div>
                                </div>
                                <div class="sld-tiny-flex no-gap">
                                    <div style="margin-right:5px;">
                                        <svg width="11" height="14" viewBox="0 0 11 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M3 6.33333V3.66667C3 2.95942 3.28095 2.28115 3.78105 1.78105C4.28115 1.28095 4.95942 1 5.66667 1C6.37391 1 7.05219 1.28095 7.55228 1.78105C8.05238 2.28115 8.33333 2.95942 8.33333 3.66667V6.33333M2.33333 6.33333H9C9.73638 6.33333 10.3333 6.93029 10.3333 7.66667V11.6667C10.3333 12.403 9.73638 13 9 13H2.33333C1.59695 13 1 12.403 1 11.6667V7.66667C1 6.93029 1.59695 6.33333 2.33333 6.33333ZM6.33333 9.66667C6.33333 10.0349 6.03486 10.3333 5.66667 10.3333C5.29848 10.3333 5 10.0349 5 9.66667C5 9.29848 5.29848 9 5.66667 9C6.03486 9 6.33333 9.29848 6.33333 9.66667Z" stroke="#090915" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </div>
                                    <div style="opacity:.75"><?php echo (preg_replace('#^https?://#i', '', home_url())) ?>/</div><span class="value truncate" x-text="$store.setupWizard.syncedData.inputPortalSlug || 'slug'"></span>
                                </div>
                                <div class="sld-tiny-flex page-skeleton">
                                    <div class="value mb-5" x-text="$store.setupWizard.syncedData.inputPortalPageTitle || 'Page title'"></div>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php
        return ob_get_clean();
    }

    private static function render_step_referral_rate(): string
    {
        ob_start(); ?>
            <div class="step" x-show="$store.setupWizard.shouldShowStep('referral_rate')">
                <div class="sld-card large has-shadow">
                    <div class="content reversed">
                        <div class="main">

                            <div class="head">
                                <h2 class="lead"><?php _e('Default commission rate', 'solid-affiliate') ?></h2>
                                <div class="plain"><?php _e('Set the baseline commission rate that your affiliates will earn. Don\'t worry, you can always add different rates and change this later.', 'solid-affiliate') ?></div>
                            </div>
                            <div class="sld-form">
                                <!-- inputs bound to the store: inputDefaultReferralRate and inputDefaultReferralRateType. Also check if isDefaultReferralRateConfigured -->
                                <!-- label for the select -->
                                <div class="sld-field">
                                    <label for="default-referral-rate-type"><?php _e('Commission rate type', 'solid-affiliate') ?></label>
                                    <select x-model="$store.setupWizard.syncedData.inputDefaultReferralRateType">
                                        <option value="percentage"><?php _e('Percentage (%)', 'solid-affiliate') ?></option>
                                        <option value="flat"><?php _e('Flat ($)', 'solid-affiliate') ?></option>
                                    </select>
                                </div>
                                <div class="sld-field">
                                    <label for="default-referral-rate"><?php _e('Commission rate', 'solid-affiliate') ?></label>
                                    <input min="0" step="1" type="number" placeholder="<?php _e('Enter a commission rate (ex: 10 or 22.5)', 'solid-affiliate') ?>" x-model.number="$store.setupWizard.syncedData.inputDefaultReferralRate" x-ref="inputDefaultReferralRate">
                                </div>
                                <div class="sld-actions">
                                    <button class="sld-button secondary large" x-on:click="$store.setupWizard.prevPage()" disabled>
                                        <?php _e('Back', 'solid-affiliate') ?>
                                    </button>
                                    <button x-bind:disabled="!$store.setupWizard.computedIsDefaultReferralRateInputsValid()" class="sld-button active large" x-on:click="$store.setupWizard.postData('commission-rate')">
                                        <?php echo (self::render_ajaxspinner()); ?>
                                        <?php _e('Continue', 'solid-affiliate') ?>
                                    </button>
                                </div>

                            </div>
                        </div>
                        <div class="side sync-wizard">
                            <div class="step-preview">
                                <div class="sld-tiny-heading">
                                    <div class="lead"><?php _e('New referral', 'solid-affiliate') ?></div>
                                    <div class="plain"><?php _e('Update the commission rate on the right to see an example calculation', 'solid-affiliate') ?></div>
                                </div>
                                <div class="sld-tiny-flex">
                                    <div class="icon">
                                        <svg width="14" height="16" viewBox="0 0 14 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M2.5 11.75C1.67157 11.75 1 12.4216 1 13.25C1 14.0784 1.67157 14.75 2.5 14.75C3.32843 14.75 4 14.0784 4 13.25C4 12.4216 3.32843 11.75 2.5 11.75ZM2.5 11.75H10.75M2.5 11.75V1.25H1M10.75 11.75C9.92157 11.75 9.25 12.4216 9.25 13.25C9.25 14.0784 9.92157 14.75 10.75 14.75C11.5784 14.75 12.25 14.0784 12.25 13.25C12.25 12.4216 11.5784 11.75 10.75 11.75ZM2.5 2.75L13 3.5L12.25 8.75H2.5" stroke="#090915" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </div>
                                    <div class="body">
                                        <div class="lead"><?php _e('Referral type', 'solid-affiliate') ?></div>
                                        <div class="value"><?php _e('Purchase', 'solid-affiliate') ?></div>
                                    </div>
                                </div>
                                <div class="sld-tiny-flex">
                                    <div class="icon">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M10.1 5.75C9.96414 5.51432 9.76674 5.32003 9.52894 5.18791C9.29113 5.0558 9.02188 4.99085 8.75 5H7.25C6.85218 5 6.47064 5.15804 6.18934 5.43934C5.90804 5.72064 5.75 6.10218 5.75 6.5C5.75 6.89782 5.90804 7.27936 6.18934 7.56066C6.47064 7.84196 6.85218 8 7.25 8H8.75C9.14782 8 9.52936 8.15804 9.81066 8.43934C10.092 8.72064 10.25 9.10218 10.25 9.5C10.25 9.89782 10.092 10.2794 9.81066 10.5607C9.52936 10.842 9.14782 11 8.75 11H7.25C6.97812 11.0092 6.70887 10.9442 6.47106 10.8121C6.23326 10.68 6.03586 10.4857 5.9 10.25M8 4.25V11.75M14.75 8C14.75 11.7279 11.7279 14.75 8 14.75C4.27208 14.75 1.25 11.7279 1.25 8C1.25 4.27208 4.27208 1.25 8 1.25C11.7279 1.25 14.75 4.27208 14.75 8Z" stroke="#090915" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </div>
                                    <div class="body">
                                        <div class="lead"><?php _e('Order amount', 'solid-affiliate') ?></div>
                                        <div class="value">$200.00</div>
                                    </div>
                                </div>
                                <div class="sld-tiny-flex">
                                    <div class="icon">
                                        <svg width="16" height="13" viewBox="0 0 16 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M6.5 6L5 4.35L5.45 3.6M3.5 0.75H12.5L14.75 4.5L8.375 11.625C8.32612 11.6749 8.26777 11.7145 8.20338 11.7416C8.13899 11.7686 8.06985 11.7826 8 11.7826C7.93015 11.7826 7.86101 11.7686 7.79662 11.7416C7.73223 11.7145 7.67388 11.6749 7.625 11.625L1.25 4.5L3.5 0.75Z" stroke="#090915" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </div>
                                    <div class="body">
                                        <div class="lead"><?php _e('Commission amount', 'solid-affiliate') ?></div>
                                        <div class="value" x-text="$store.setupWizard.computedDefaultCommissionPreview()"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php
        return ob_get_clean();
    }

    private static function render_step_recurring_referral_rate(): string
    {
        ob_start(); ?>
            <div class="step" x-show="$store.setupWizard.shouldShowStep('recurring_referral_rate')">
                <div class="sld-card large has-shadow">
                    <div class="content reversed">
                        <div class="main">
                            <div class="head">
                                <h2 class="lead"><?php _e('Subscription renewal rate', 'solid-affiliate') ?></h2>
                                <div class="plain"><?php _e('Set a commission rate for subscription renewals. If an affiliate sends you a customer whom signs up for a subscription, this rate will apply whenever the subscription renews.', 'solid-affiliate') ?></div>
                            </div>
                            <div class="sld-form">
                                <div class="sld-field">
                                    <label for="default-referral-rate-type"><?php _e('Commission type', 'solid-affiliate') ?></label>
                                    <select x-model="$store.setupWizard.syncedData.inputDefaultRecurringReferralRateType">
                                        <option value="percentage"><?php _e('Percentage', 'solid-affiliate') ?></option>
                                        <option value="flat"><?php _e('Flat', 'solid-affiliate') ?></option>
                                    </select>
                                </div>
                                <div class="sld-field">
                                    <label for="default-referral-rate-type"><?php _e('Commission rate', 'solid-affiliate') ?></label>
                                    <input min="0" step="1" type="number" placeholder="<?php _e('Enter a commission rate (ex: 10 or 22.5)', 'solid-affiliate') ?>" x-model.number="$store.setupWizard.syncedData.inputDefaultRecurringReferralRate" x-ref="inputDefaultRecurringReferralRate">
                                </div>
                                <div class="sld-actions">
                                    <button class="sld-button secondary large" x-on:click="$store.setupWizard.prevPage()">
                                        <?php _e('Back', 'solid-affiliate') ?>
                                    </button>
                                    <button x-bind:disabled="!$store.setupWizard.computedIsDefaultRecurringReferralRateInputsValid()" class="sld-button active large" x-on:click="$store.setupWizard.postData('recurring-commission-rate')">
                                        <?php echo (self::render_ajaxspinner()); ?>
                                        <?php _e('Continue', 'solid-affiliate') ?>
                                    </button>
                                </div>

                            </div>
                        </div>
                        <div class="side sync-wizard">
                            <div class="step-preview">
                                <div class="sld-tiny-heading">
                                    <div class="lead"><?php _e('New subscription renewal', 'solid-affiliate') ?></div>
                                    <div class="plain"><?php _e('This is for subscription renewals. Please choose a rate to the left.', 'solid-affiliate') ?></div>
                                </div>
                                <div class="sld-tiny-flex">
                                    <div class="icon">
                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M13 6.25002C12.8166 4.93019 12.2043 3.70727 11.2575 2.76965C10.3107 1.83202 9.08182 1.23171 7.76025 1.06119C6.43869 0.890664 5.09772 1.15939 3.9439 1.82596C2.79009 2.49253 1.88744 3.51998 1.375 4.75002M1 1.75002V4.75002H4M1 7.75002C1.18342 9.06986 1.7957 10.2928 2.74252 11.2304C3.68934 12.168 4.91818 12.7683 6.23975 12.9389C7.56131 13.1094 8.90228 12.8407 10.0561 12.1741C11.2099 11.5075 12.1126 10.4801 12.625 9.25002M13 12.25V9.25002H10" stroke="#090915" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>

                                    </div>
                                    <div class="body">
                                        <div class="lead"><?php _e('Referral type', 'solid-affiliate') ?></div>
                                        <div class="value"><?php _e('Subscription renewal', 'solid-affiliate') ?></div>
                                    </div>
                                </div>
                                <div class="sld-tiny-flex">
                                    <div class="icon">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M10.1 5.75C9.96414 5.51432 9.76674 5.32003 9.52894 5.18791C9.29113 5.0558 9.02188 4.99085 8.75 5H7.25C6.85218 5 6.47064 5.15804 6.18934 5.43934C5.90804 5.72064 5.75 6.10218 5.75 6.5C5.75 6.89782 5.90804 7.27936 6.18934 7.56066C6.47064 7.84196 6.85218 8 7.25 8H8.75C9.14782 8 9.52936 8.15804 9.81066 8.43934C10.092 8.72064 10.25 9.10218 10.25 9.5C10.25 9.89782 10.092 10.2794 9.81066 10.5607C9.52936 10.842 9.14782 11 8.75 11H7.25C6.97812 11.0092 6.70887 10.9442 6.47106 10.8121C6.23326 10.68 6.03586 10.4857 5.9 10.25M8 4.25V11.75M14.75 8C14.75 11.7279 11.7279 14.75 8 14.75C4.27208 14.75 1.25 11.7279 1.25 8C1.25 4.27208 4.27208 1.25 8 1.25C11.7279 1.25 14.75 4.27208 14.75 8Z" stroke="#090915" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </div>
                                    <div class="body">
                                        <div class="lead"><?php _e('Renewal amount', 'solid-affiliate') ?></div>
                                        <div class="value">$90.00</div>
                                    </div>
                                </div>

                                <div class="sld-tiny-flex">
                                    <div class="icon">
                                        <svg width="16" height="13" viewBox="0 0 16 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M6.5 6L5 4.35L5.45 3.6M3.5 0.75H12.5L14.75 4.5L8.375 11.625C8.32612 11.6749 8.26777 11.7145 8.20338 11.7416C8.13899 11.7686 8.06985 11.7826 8 11.7826C7.93015 11.7826 7.86101 11.7686 7.79662 11.7416C7.73223 11.7145 7.67388 11.6749 7.625 11.625L1.25 4.5L3.5 0.75Z" stroke="#090915" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </div>
                                    <div class="body">
                                        <div class="lead"><?php _e('Commission amount', 'solid-affiliate') ?></div>
                                        <div class="value" x-text="$store.setupWizard.computedDefaultRecurringCommissionPreview()"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php
        return ob_get_clean();
    }


    private static function render_step_email_settings(): string
    {
        ob_start(); ?>
            <div class="step" x-show="$store.setupWizard.shouldShowStep('email_settings')">
                <div class="sld-card large has-shadow">
                    <div class="content reversed">
                        <div class="main">
                            <div class="head">
                                <h2 class="lead"><?php _e('Email notifications', 'solid-affiliate') ?></h2>
                                <div class="plain"> <?php _e('Solid Affiliate sends out email notifications to your affiliates and admins on your behalf.', 'solid-affiliate') ?></div>
                            </div>
                            <div class="sld-form">
                                <div class="sld-field">
                                    <label for=""><?php _e('From name', 'solid-affiliate') ?>
                                        <span class="sld-tooltip" data-html="true" data-sld-tooltip-content="<div style='max-width:150px'><?php esc_html_e('Enter your email from name. The standard is to use your site name.', 'solid-affiliate') ?></div>" aria-expanded="false">
                                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M6 8.77778V8.78333M6 6.83333C5.98977 6.65299 6.03842 6.4742 6.13862 6.3239C6.23882 6.1736 6.38515 6.05992 6.55556 6C6.76437 5.92015 6.95181 5.79291 7.10309 5.62831C7.25438 5.46371 7.3654 5.26623 7.4274 5.05144C7.48941 4.83664 7.5007 4.61039 7.46041 4.39048C7.42011 4.17058 7.32931 3.96303 7.19518 3.78417C7.06104 3.60532 6.88721 3.46005 6.68739 3.35979C6.48756 3.25953 6.26719 3.20702 6.04363 3.2064C5.82006 3.20578 5.5994 3.25707 5.39902 3.35621C5.19865 3.45536 5.02402 3.59967 4.88889 3.77778M11 6C11 8.76142 8.76142 11 6 11C3.23858 11 1 8.76142 1 6C1 3.23858 3.23858 1 6 1C8.76142 1 11 3.23858 11 6Z" stroke="#B7BBC4" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </span>
                                    </label>
                                    <input type="text" placeholder="<?php _e('Enter a name', 'solid-affiliate') ?>" x-model="$store.setupWizard.syncedData.inputDefaultEmailFromName">
                                </div>
                                <div class="sld-field">

                                    <label for=""><?php _e('From email', 'solid-affiliate') ?>
                                        <span class="sld-tooltip" data-html="true" data-sld-tooltip-content="<div style='max-width:150px'><?php esc_html_e('Set the email address which emails will be sent from. This will set the <strong>from</strong> and <strong>reply-to</strong> address.', 'solid-affiliate') ?></div>" aria-expanded="false">
                                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M6 8.77778V8.78333M6 6.83333C5.98977 6.65299 6.03842 6.4742 6.13862 6.3239C6.23882 6.1736 6.38515 6.05992 6.55556 6C6.76437 5.92015 6.95181 5.79291 7.10309 5.62831C7.25438 5.46371 7.3654 5.26623 7.4274 5.05144C7.48941 4.83664 7.5007 4.61039 7.46041 4.39048C7.42011 4.17058 7.32931 3.96303 7.19518 3.78417C7.06104 3.60532 6.88721 3.46005 6.68739 3.35979C6.48756 3.25953 6.26719 3.20702 6.04363 3.2064C5.82006 3.20578 5.5994 3.25707 5.39902 3.35621C5.19865 3.45536 5.02402 3.59967 4.88889 3.77778M11 6C11 8.76142 8.76142 11 6 11C3.23858 11 1 8.76142 1 6C1 3.23858 3.23858 1 6 1C8.76142 1 11 3.23858 11 6Z" stroke="#B7BBC4" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </span>
                                    </label>
                                    <input type="text" placeholder="<?php _e('Enter an email address', 'solid-affiliate') ?>" x-model="$store.setupWizard.syncedData.inputDefaultFromEmail">
                                </div>
                                <div class="sld-actions">
                                    <button class="sld-button secondary large" x-on:click="$store.setupWizard.prevPage()">
                                        <?php _e('Back', 'solid-affiliate') ?>
                                    </button>

                                    <button x-bind:disabled="!$store.setupWizard.computedIsEmailInputsValid()" class="sld-button active large" x-on:click="$store.setupWizard.postData('email-settings')">
                                        <?php echo (self::render_ajaxspinner()); ?>
                                        <?php _e('Continue', 'solid-affiliate') ?></button>
                                </div>

                            </div>
                        </div>
                        <div class="side sync-wizard">
                            <div class="step-preview">
                                <div class="sld-tiny-heading">
                                    <div class="lead"><?php _e('Inbox', 'solid-affiliate') ?></div>
                                    <div class="plain"><?php _e('Below is an example email notification sent to an affiliate.', 'solid-affiliate') ?></div>
                                </div>
                                <div class="sld-tiny-flex no-gap">
                                    <div style="margin-right:5px; width:20px;">
                                        <svg width="10" height="16" viewBox="0 0 10 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M0.5 14.75V13.25C0.5 12.4544 0.81607 11.6913 1.37868 11.1287C1.94129 10.5661 2.70435 10.25 3.5 10.25H6.5C7.29565 10.25 8.05871 10.5661 8.62132 11.1287C9.18393 11.6913 9.5 12.4544 9.5 13.25V14.75M8 4.25C8 5.90685 6.65685 7.25 5 7.25C3.34315 7.25 2 5.90685 2 4.25C2 2.59315 3.34315 1.25 5 1.25C6.65685 1.25 8 2.59315 8 4.25Z" stroke="#090915" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>

                                    </div>
                                    <span class="value truncate" x-text="$store.setupWizard.syncedData.inputDefaultEmailFromName || 'From name'"></span>
                                </div>
                                <div class="sld-tiny-flex no-gap">
                                    <div style="margin-right:5px; width:20px;">
                                        <svg width="16" height="12" viewBox="0 0 16 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M14.75 2.25C14.75 1.42157 14.0784 0.75 13.25 0.75H2.75C1.92157 0.75 1.25 1.42157 1.25 2.25M14.75 2.25V9.75C14.75 10.5784 14.0784 11.25 13.25 11.25H2.75C1.92157 11.25 1.25 10.5784 1.25 9.75V2.25M14.75 2.25L8 6.75L1.25 2.25" stroke="#090915" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </div>
                                    <span class="value truncate" x-text="$store.setupWizard.syncedData.inputDefaultFromEmail || 'From email'"></span>
                                </div>
                                <div class="sld-tiny-flex page-skeleton">
                                    <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php
        return ob_get_clean();
    }

    private static function render_step_coupons(): string
    {
        ob_start(); ?>
            <div class="step" x-show="$store.setupWizard.shouldShowStep('coupons')">

                <div class="sld-card large has-shadow">
                    <div class="content reversed">
                        <div class="main">
                            <div class="head">
                                <h2 class="lead"><?php _e('Affiliate coupons', 'solid-affiliate') ?></h2>
                                <div class="plain"><?php _e('Automatically create and assign a coupon to your affiliates to share with their friends and audience when they sign up.', 'solid-affiliate') ?></div>
                            </div>
                            <div class="sld-form">
                                <div class="sld-row">
                                    <div class="sld-field" style="width:60%">
                                        <label for=""><?php _e('Discount type', 'solid-affiliate') ?></label>
                                        <select x-model="$store.setupWizard.syncedData.inputCouponRateType">
                                            <option value="percentage"><?php _e('Percentage (%)', 'solid-affiliate') ?></option>
                                            <option value="flat"><?php _e('Flat ($)', 'solid-affiliate') ?></option>
                                        </select>
                                    </div>
                                    <div class="sld-field">
                                        <label for=""><?php _e('Discount amount', 'solid-affiliate') ?></label>
                                        <input min="0" step="0.001" type="number" placeholder="<?php _e('Enter an amount', 'solid-affiliate') ?>" x-model.number="$store.setupWizard.syncedData.inputCouponRate" x-ref="inputCouponRate">
                                    </div>
                                </div>
                                <div class="sld-field">
                                    <label for=""><?php _e('Advanced settings', 'solid-affiliate') ?></label>
                                    <button class="sld-button secondary small has-icon" data-micromodal-trigger="sld-setup_coupon-config-2">
                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M5.88333 1.878C6.16733 0.707333 7.83267 0.707333 8.11667 1.878C8.15928 2.05387 8.24281 2.21719 8.36047 2.35467C8.47813 2.49215 8.62659 2.5999 8.79377 2.66916C8.96094 2.73843 9.14211 2.76723 9.32252 2.75325C9.50294 2.73926 9.6775 2.68287 9.832 2.58867C10.8607 1.962 12.0387 3.13933 11.412 4.16867C11.3179 4.3231 11.2616 4.49756 11.2477 4.67785C11.2337 4.85814 11.2625 5.03918 11.3317 5.20625C11.4009 5.37333 11.5085 5.52172 11.6458 5.63937C11.7831 5.75702 11.9463 5.8406 12.122 5.88333C13.2927 6.16733 13.2927 7.83267 12.122 8.11667C11.9461 8.15928 11.7828 8.24281 11.6453 8.36047C11.5079 8.47813 11.4001 8.62659 11.3308 8.79377C11.2616 8.96094 11.2328 9.14211 11.2468 9.32252C11.2607 9.50294 11.3171 9.6775 11.4113 9.832C12.038 10.8607 10.8607 12.0387 9.83133 11.412C9.6769 11.3179 9.50244 11.2616 9.32215 11.2477C9.14186 11.2337 8.96082 11.2625 8.79375 11.3317C8.62667 11.4009 8.47828 11.5085 8.36063 11.6458C8.24298 11.7831 8.1594 11.9463 8.11667 12.122C7.83267 13.2927 6.16733 13.2927 5.88333 12.122C5.84072 11.9461 5.75719 11.7828 5.63953 11.6453C5.52187 11.5079 5.37341 11.4001 5.20623 11.3308C5.03906 11.2616 4.85789 11.2328 4.67748 11.2468C4.49706 11.2607 4.3225 11.3171 4.168 11.4113C3.13933 12.038 1.96133 10.8607 2.588 9.83133C2.68207 9.6769 2.73837 9.50244 2.75232 9.32215C2.76628 9.14186 2.7375 8.96082 2.66831 8.79375C2.59913 8.62667 2.49151 8.47828 2.35418 8.36063C2.21686 8.24298 2.05371 8.1594 1.878 8.11667C0.707333 7.83267 0.707333 6.16733 1.878 5.88333C2.05387 5.84072 2.21719 5.75719 2.35467 5.63953C2.49215 5.52187 2.5999 5.37341 2.66916 5.20623C2.73843 5.03906 2.76723 4.85789 2.75325 4.67748C2.73926 4.49706 2.68287 4.3225 2.58867 4.168C1.962 3.13933 3.13933 1.96133 4.16867 2.588C4.83533 2.99333 5.69933 2.63467 5.88333 1.878Z" stroke="#777E8C" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                            <path d="M7 9C8.10457 9 9 8.10457 9 7C9 5.89543 8.10457 5 7 5C5.89543 5 5 5.89543 5 7C5 8.10457 5.89543 9 7 9Z" stroke="#777E8C" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <?php _e('Configure', 'solid-affiliate') ?>
                                    </button>
                                </div>
                                <div class="sld-actions">
                                    <button class="sld-button secondary large" x-on:click="$store.setupWizard.prevPage()">
                                        <?php _e('Back', 'solid-affiliate') ?>
                                    </button>
                                    <?php echo (self::render_skip_step_button()) ?>
                                    <button x-bind:disabled="!$store.setupWizard.computedIsCouponInputsValid() || $store.setupWizard.pendingAjax" class="sld-button active large" x-on:click="$store.setupWizard.postData('coupon-settings')">
                                        <?php echo (self::render_ajaxspinner()); ?>
                                        <?php _e('Continue', 'solid-affiliate') ?>
                                    </button>
                                </div>

                            </div>
                        </div>
                        <div class="side sync-wizard">

                            <div class="step-preview">
                                <div class="sld-tiny-heading">
                                    <div class="lead"><?php _e('Coupon template', 'solid-affiliate') ?></div>
                                    <div class="plain"><?php _e('These coupon settings will be used to automatically generate a coupon for each of your affiliates to share! Woot woot!', 'solid-affiliate') ?></div>
                                </div>
                                <div class="sld-tiny-flex">
                                    <div class="icon">
                                        <svg width="16" height="12" viewBox="0 0 16 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M10.25 0.75V2.25M10.25 5.25V6.75M10.25 9.75V11.25M2.75 0.75H13.25C13.6478 0.75 14.0294 0.908035 14.3107 1.18934C14.592 1.47064 14.75 1.85218 14.75 2.25V4.5C14.3522 4.5 13.9706 4.65804 13.6893 4.93934C13.408 5.22064 13.25 5.60218 13.25 6C13.25 6.39782 13.408 6.77936 13.6893 7.06066C13.9706 7.34196 14.3522 7.5 14.75 7.5V9.75C14.75 10.1478 14.592 10.5294 14.3107 10.8107C14.0294 11.092 13.6478 11.25 13.25 11.25H2.75C2.35218 11.25 1.97064 11.092 1.68934 10.8107C1.40804 10.5294 1.25 10.1478 1.25 9.75V7.5C1.64782 7.5 2.02936 7.34196 2.31066 7.06066C2.59196 6.77936 2.75 6.39782 2.75 6C2.75 5.60218 2.59196 5.22064 2.31066 4.93934C2.02936 4.65804 1.64782 4.5 1.25 4.5V2.25C1.25 1.85218 1.40804 1.47064 1.68934 1.18934C1.97064 0.908035 2.35218 0.75 2.75 0.75Z" stroke="#090915" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>

                                    </div>
                                    <div class="body">
                                        <div class="lead"><?php _e('Discount amount', 'solid-affiliate') ?></div>
                                        <div class="value" x-text="$store.setupWizard.syncedData.inputCouponRate || '—' "></div>
                                    </div>
                                </div>
                                <div class="sld-tiny-flex">
                                    <div class="icon">
                                        <svg width="14" height="16" viewBox="0 0 14 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M5.125 1.25H8.875C9.17337 1.25 9.45952 1.36853 9.6705 1.5795C9.88147 1.79048 10 2.07663 10 2.375C10 3.07119 9.72344 3.73887 9.23116 4.23116C8.73887 4.72344 8.07119 5 7.375 5H6.625C5.92881 5 5.26113 4.72344 4.76884 4.23116C4.27656 3.73887 4 3.07119 4 2.375C4 2.07663 4.11853 1.79048 4.3295 1.5795C4.54048 1.36853 4.82663 1.25 5.125 1.25Z" stroke="#090915" stroke-linecap="round" stroke-linejoin="round" />
                                            <path d="M1 11.75V11C1 9.4087 1.63214 7.88258 2.75736 6.75736C3.88258 5.63214 5.4087 5 7 5C8.5913 5 10.1174 5.63214 11.2426 6.75736C12.3679 7.88258 13 9.4087 13 11V11.75C13 12.5456 12.6839 13.3087 12.1213 13.8713C11.5587 14.4339 10.7956 14.75 10 14.75H4C3.20435 14.75 2.44129 14.4339 1.87868 13.8713C1.31607 13.3087 1 12.5456 1 11.75Z" stroke="#090915" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </div>
                                    <div class="body">
                                        <div class="lead"><?php _e('Discount type', 'solid-affiliate') ?></div>
                                        <div class="value" x-text="$store.setupWizard.syncedData.inputCouponRateType"></div>
                                    </div>
                                </div>
                                <div class="sld-tiny-flex">
                                    <div class="icon">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M1.25 8H14.75M8 1.25V14.75M11.375 2.375L13.625 4.625M13.625 2.375L11.375 4.625M3.5 2V5M2 3.5H5M12.5 11H12.5075M12.5 14H12.5075M2 12.5H5" stroke="#090915" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </div>
                                    <div class="body">
                                        <div class="lead"><?php _e('Example coupon code', 'solid-affiliate') ?></div>
                                        <div class="value" x-text="$store.setupWizard.computedExampleCoupon()"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- coupon config 2 modal -->
                <div class="modal micromodal-slide" id="sld-setup_coupon-config-2" aria-hidden="true">
                    <div class="modal__overlay" tabindex="-1" data-micromodal-close>
                        <div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="sld-setup_coupon-config-2-title">
                            <header class="modal__header">
                                <h2 class="modal__title" id="sld-setup_coupon-config-2-title">
                                    <?php _e('Coupon advanced settings', 'solid-affiliate') ?>
                                </h2>
                                <a class="modal__close" aria-label="Close modal" data-micromodal-close></a>
                            </header>
                            <main class="modal__content" id="sld-setup_coupon-config-2-content">
                                <label for="" class="sld_field-label">
                                    <input type="checkbox" x-model="$store.setupWizard.syncedData.inputIsCouponIndividualUse">
                                    <?php _e('Individual use only', 'solid-affiliate') ?>
                                </label>
                                <small><?php _e('Generated coupons cannot be used in conjunction with others active coupons.', 'solid-affiliate') ?></small>
                                <label for="" class="sld_field-label">
                                    <input type="checkbox" x-model="$store.setupWizard.syncedData.inputIsCouponExcludeSaleItems">
                                    <?php _e('Exclude sale items', 'solid-affiliate') ?>
                                </label>
                                <small><?php _e('Generated coupons should not apply to products on sale. Per-cart coupons do not work if a sale item is added afterward.', 'solid-affiliate') ?></small>
                            </main>
                            <footer class="modal__footer">
                                <a class="modal__btn" data-micromodal-close aria-label="Close this dialog window">
                                    <?php _e('Close', 'solid-affiliate') ?>
                                </a>
                            </footer>
                        </div>
                    </div>
                </div>
                <!-- END coupon config 2 modal -->
            </div>
        <?php
        return ob_get_clean();
    }

    private static function render_step_import_existing_users(): string
    {
        $total_eligible_users = SetupWizard::total_eligible_users_for_affiliate_creation();
        $total_eligible_customers = SetupWizard::total_eligible_users_for_affiliate_creation(['customer']);
        ob_start(); ?>
            <div class="step" x-show="$store.setupWizard.shouldShowStep('import_existing_users')">
                <div class="sld-card large has-shadow">
                    <div class="content reversed">
                        <div class="main">
                            <div class="head">
                                <h2 class="lead"><?php _e('Work with your existing users', 'solid-affiliate') ?></h2>
                                <div class="plain"><?php _e('Jumpstart your affiliate program by allowing your existing users to promote your store. Each user will receive an email with their affiliate link and optionally a coupon.', 'solid-affiliate') ?></div>
                            </div>
                            <div class="sld-form">
                                <!-- inputs bound to the store: inputDefaultReferralRate and inputDefaultReferralRateType. Also check if isDefaultReferralRateConfigured -->
                                <!-- label for the select -->
                                <div class="sld-field">
                                    <label for=""><?php _e('Users to add', 'solid-affiliate') ?>
                                        <span class="sld-tooltip" data-html="true" data-sld-tooltip-content="<div style='max-width:150px'><?php esc_html_e('Select the existing WordPress users you want to register as affiliates during this setup. Admins will not be included.', 'solid-affiliate') ?></div>" aria-expanded="false">
                                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M6 8.77778V8.78333M6 6.83333C5.98977 6.65299 6.03842 6.4742 6.13862 6.3239C6.23882 6.1736 6.38515 6.05992 6.55556 6C6.76437 5.92015 6.95181 5.79291 7.10309 5.62831C7.25438 5.46371 7.3654 5.26623 7.4274 5.05144C7.48941 4.83664 7.5007 4.61039 7.46041 4.39048C7.42011 4.17058 7.32931 3.96303 7.19518 3.78417C7.06104 3.60532 6.88721 3.46005 6.68739 3.35979C6.48756 3.25953 6.26719 3.20702 6.04363 3.2064C5.82006 3.20578 5.5994 3.25707 5.39902 3.35621C5.19865 3.45536 5.02402 3.59967 4.88889 3.77778M11 6C11 8.76142 8.76142 11 6 11C3.23858 11 1 8.76142 1 6C1 3.23858 3.23858 1 6 1C8.76142 1 11 3.23858 11 6Z" stroke="#B7BBC4" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </span>
                                    </label>
                                    <select x-model="$store.setupWizard.syncedData.inputWhichUsersToInvite">
                                        <?php if ($total_eligible_users > 0) { ?>
                                            <option value="all-users"><?php _e('All Users', 'solid-affiliate') ?> (<?php echo ($total_eligible_users) ?>)</option>
                                        <?php } ?>
                                        <?php if ($total_eligible_customers > 0) { ?>
                                            <option value="all-customers"><?php _e('All Customers', 'solid-affiliate') ?> (<?php echo ($total_eligible_customers) ?>)</option>
                                        <?php } ?>
                                    </select>
                                </div>

                                <div class="sld-field">
                                    <label for=""><?php _e('Welcome email', 'solid-affiliate') ?></label>
                                    <button class="sld-button small secondary has-icon" data-micromodal-trigger="sld-setup_edit-email-modal">
                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M8.43716 2.4314L11.5686 5.56284M1 13H4.13144L12.3515 4.77998C12.7667 4.36472 13 3.80152 13 3.21426C13 2.627 12.7667 2.0638 12.3515 1.64854C11.9362 1.23329 11.373 1 10.7857 1C10.1985 1 9.63528 1.23329 9.22002 1.64854L1 9.86856V13Z" stroke="#777E8C" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>

                                        <?php _e('Edit content', 'solid-affiliate') ?>
                                    </button>
                                </div>
                                <div class="sld-actions">
                                    <button class="sld-button secondary large" x-on:click="$store.setupWizard.prevPage()">
                                        <?php _e('Back', 'solid-affiliate') ?>
                                    </button>
                                    <?php echo (self::render_skip_step_button()) ?>
                                    <button x-bind:disabled="!$store.setupWizard.computedIsInviteUsersInputsValid() || $store.setupWizard.pendingAjax" class="sld-button active large" x-on:click="$store.setupWizard.postData('invite-users')">
                                        <?php echo (self::render_ajaxspinner()); ?>
                                        <?php _e('Continue', 'solid-affiliate') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="side sync-wizard">
                            <div class="step-preview">
                                <div class="sld-tiny-heading">
                                    <div class="lead"><?php _e('Register as affiliates', 'solid-affiliate') ?></div>
                                    <div class="plain"><?php _e('Add users to your list of affiliates.', 'solid-affiliate') ?></div>
                                </div>
                                <div class="sld-tiny-flex">
                                    <div class="icon">
                                        <svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M1 15V13.4444C1 12.6193 1.32778 11.828 1.91122 11.2446C2.49467 10.6611 3.28599 10.3333 4.11111 10.3333H7.22222C8.04734 10.3333 8.83866 10.6611 9.42211 11.2446C10.0056 11.828 10.3333 12.6193 10.3333 13.4444V15M11.1111 7.22222H15.7778M13.4444 4.88889V9.55556M8.77778 4.11111C8.77778 5.82933 7.38489 7.22222 5.66667 7.22222C3.94845 7.22222 2.55556 5.82933 2.55556 4.11111C2.55556 2.39289 3.94845 1 5.66667 1C7.38489 1 8.77778 2.39289 8.77778 4.11111Z" stroke="#090915" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>

                                    </div>
                                    <div class="body">
                                        <div class="lead"><?php _e('Total eligible users', 'solid-affiliate') ?></div>
                                        <div class="value" x-text="$store.setupWizard.syncedData.totalEligibleUsersForAffiliateCreation"></div>
                                    </div>
                                </div>
                                <div class="sld-tiny-flex">
                                    <div class="icon">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M11.1111 7.99746C11.1111 9.71568 9.71821 11.1086 8 11.1086C6.28178 11.1086 4.88889 9.71568 4.88889 7.99746C4.88889 6.27924 6.28178 4.88635 8 4.88635C9.71821 4.88635 11.1111 6.27924 11.1111 7.99746ZM11.1111 7.99746V9.16412C11.1111 9.67982 11.316 10.1744 11.6806 10.5391C12.0453 10.9037 12.5399 11.1086 13.0555 11.1086C13.5712 11.1086 14.0658 10.9037 14.4305 10.5391C14.7951 10.1744 15 9.67982 15 9.16412V7.99746C15.0019 6.49332 14.5193 5.02856 13.6236 3.82016C12.728 2.61177 11.4669 1.72402 10.0272 1.28843C8.5875 0.852829 7.04579 0.892555 5.63045 1.40172C4.2151 1.91088 3.00141 2.8624 2.16916 4.11532C1.33691 5.36824 0.930367 6.85591 1.00976 8.35795C1.08916 9.86 1.65026 11.2965 2.60996 12.4547C3.56966 13.6129 4.8769 14.4312 6.33804 14.7883C7.79917 15.1454 9.33647 15.0224 10.7222 14.4375" stroke="#090915" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </div>
                                    <div class="body">
                                        <div class="lead"><?php _e('Notification type', 'solid-affiliate') ?></div>
                                        <div class="value"><?php _e('Welcome email', 'solid-affiliate') ?></div>
                                    </div>
                                </div>

                                <div class="sld-tiny-flex page-skeleton">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>


                            </div>
                        </div>
                    </div>
                </div>

                <!-- edit email template modal -->
                <div class="modal micromodal-slide" id="sld-setup_edit-email-modal" aria-hidden="true">
                    <div class="modal__overlay" tabindex="-1" data-micromodal-close>
                        <div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="sld-setup_edit-email-modal-title">
                            <header class="modal__header">
                                <h2 class="modal__title" id="sld-setup_edit-email-modal-title">
                                    <?php _e('Welcome Email', 'solid-affiliate') ?>
                                </h2>
                                <a class="modal__close" aria-label="Close modal" data-micromodal-close></a>
                            </header>
                            <main class="modal__content" id="sld-setup_edit-email-modal-content">
                                <?php
                                /** @psalm-suppress InvalidArgument */
                                wp_editor(self::default_welcome_email(), 'sld-setup_welcome-email-editor', array(
                                    'textarea_name' => 'sld-setup_edit-email-modal-content',
                                    'editor_height' => 510,
                                    'media_buttons' => false,
                                    'teeny' => true,
                                    'quicktags' => false,
                                    'tinymce' => array(
                                        'toolbar1' => 'bold,italic,underline,|,bullist,numlist,|,link,unlink,|,undo,redo',
                                        'toolbar2' => '',
                                        'toolbar3' => '',
                                        'toolbar4' => '',
                                    ),
                                )); ?>
                            </main>
                            <footer class="modal__footer sld-actions">
                                <a x-on:click="$store.setupWizard.updateWelcomeEmail()" style="text-align:center;" class="sld-button primary large" data-micromodal-close aria-label="Confirm update">
                                    <?php _e('Confirm', 'solid-affiliate') ?>
                                </a>
                                <a class="sld-button secondary large" style="text-align:center;" data-micromodal-close aria-label="Close this dialog window">
                                    <?php _e('Cancel', 'solid-affiliate') ?>
                                </a>
                            </footer>
                        </div>
                    </div>
                </div>
                <!-- END edit email template modal -->
            </div>
        <?php
        return ob_get_clean();
    }

    private static function render_step_announcement_page(): string
    {
        ob_start(); ?>
            <div class="step" x-show="$store.setupWizard.isCurrentPage(11)">

                <div class="sld-card large has-shadow">
                    <div class="content reversed">
                        <div class="main">
                            <div class="head">
                                <h2 class="lead"><?php _e('Add an announcement page to your site', 'solid-affiliate') ?></h2>
                                <div class="plain"><?php _e('Lorem', 'solid-affiliate') ?></div>
                            </div>
                            <div class="sld-form">
                                <div class="sld-field">
                                    <label for=""><?php _e('Content type', 'solid-affiliate') ?></label>
                                    <select x-model="$store.setupWizard.syncedData.inputCouponRateType">
                                        <option value="page"><?php _e('WordPress page', 'solid-affiliate') ?></option>
                                        <option value="post"><?php _e('WordPress post', 'solid-affiliate') ?></option>
                                    </select>
                                </div>
                                <div class="sld-field">
                                    <label for=""><?php _e('Page title', 'solid-affiliate') ?></label>
                                    <input type="text" placeholder="<?php _e('Enter an page title', 'solid-affiliate') ?>">
                                </div>
                                <div class="sld-field">
                                    <label for=""><?php _e('Page content', 'solid-affiliate') ?></label>
                                    <button class="sld-button small secondary has-icon" data-micromodal-trigger="">
                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M8.43716 2.4314L11.5686 5.56284M1 13H4.13144L12.3515 4.77998C12.7667 4.36472 13 3.80152 13 3.21426C13 2.627 12.7667 2.0638 12.3515 1.64854C11.9362 1.23329 11.373 1 10.7857 1C10.1985 1 9.63528 1.23329 9.22002 1.64854L1 9.86856V13Z" stroke="#777E8C" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <?php _e('Edit content', 'solid-affiliate') ?>
                                    </button>
                                </div>
                                <div class="sld-actions">
                                    <button class="sld-button secondary large" x-on:click="$store.setupWizard.prevPage()">
                                        <?php _e('Back', 'solid-affiliate') ?>
                                    </button>
                                    <button class="sld-button active large">
                                        <?php echo (self::render_ajaxspinner()); ?>
                                        <?php _e('Continue', 'solid-affiliate') ?></button>
                                </div>

                            </div>
                        </div>
                        <div class="side sync-wizard">
                            <div class="step-preview">
                                Blog post things

                                <hr>

                                TODO
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php
        return ob_get_clean();
    }

    private static function render_skip_step_button(): string
    {
        ob_start(); ?>
            <button x-bind:disabled="false" class="sld-button link large skip" x-on:click="$store.setupWizard.nextPage();"><?php _e('Skip', 'solid-affiliate') ?></button>
        <?php
        return ob_get_clean();
    }

    private static function render_final_step(): string
    {
        ob_start(); ?>
            <div class="step" x-show="$store.setupWizard.shouldShowStep('final')">
                <div class="sld-card large has-shadow preface">
                    <div class="content reversed">
                        <div class="main">
                            <div class="head">
                                <h2 class="lead"><?php _e('You\'re all set!', 'solid-affiliate') ?></h2>
                                <div class="plain"><?php _e('Congratulations! Your affiliate program is now set up and ready to go. Your store is now open to a whole new world of earning potential.', 'solid-affiliate') ?></div>
                                <div class="plain"><?php _e('Next, we suggest getting the word out about your affiliate program and directing potential affiliates to you new sign up page.', 'solid-affiliate') ?></div>
                            </div>
                            <div class="sld-actions">
                                <button class="sld-button primary large" x-on:click="$store.setupWizard.postData('complete')"><?php _e('Complete setup and go to dashboard', 'solid-affiliate') ?></button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        <?php
        return ob_get_clean();
    }

    private static function render_step_license_key(): string
    {
        $keyless_trial_ends_at = License::get_keyless_free_trial_end_timestamp();
        $expires_in = human_time_diff($keyless_trial_ends_at, time());
        ob_start(); ?>
            <div class="step" x-show="$store.setupWizard.shouldShowStep('license')">
                <div class="sld-notice has-action trial" x-show="$store.setupWizard.syncedData.isOnKeylessFreeTrial">
                    <div class="content reversed">
                        <div class="lead"><?php _e('You are currently on a free trial', 'solid-affiliate') ?></div>
                        <div class="plain"><?php _e(sprintf('Your trial will expire in <b>%1$s</b>. You can purchase and enter a valid license key now, or skip this step and purchase it later.', $expires_in), 'solid-affiliate') ?> </div>
                    </div>
                    <div class="actions">
                        <a class="sld-button primary small" href="http://solidaffiliate.com/pricing/" target="_blank"><?php _e('Purchase now', 'solid-affiliate') ?></a>
                    </div>
                </div>
                <div class="sld-notice has-action trial" x-show="!$store.setupWizard.syncedData.isOnKeylessFreeTrial">
                    <div class="content reversed">
                        <div class="lead"><?php _e('Activate Solid Affiliate', 'solid-affiliate') ?></div>
                        <div class="plain"><?php _e('Complete setup by purchasing and activating Solid Affiliate. Already purchased? Your license key is waiting for you either in an email or on your <a href="https://solidaffiliate.com/my-account/" target="_blank">account page</a>.', 'solid-affiliate') ?></div>
                    </div>
                    <div class="actions">
                        <a class="sld-button primary small" href="http://solidaffiliate.com/pricing/" target="_blank"><?php _e('Purchase', 'solid-affiliate') ?></a>
                        <a class="sld-button secondary small" href="http://solidaffiliate.com/my-account/" target="_blank"><?php _e('My account', 'solid-affiliate') ?></a>

                    </div>
                </div>

                <div class="sld-card large has-shadow">
                    <!-- If not active -->
                    <div class="content reversed" x-show="!$store.setupWizard.syncedData.isSolidAffiliateActive">
                        <div class="main">
                            <div class="head">
                                <!-- show a message if isOnKeylessFreeTrial -->
                                <h2 class="lead"><?php _e('Connect your license key', 'solid-affiliate') ?></h2>
                                <div class="plain"><?php _e('You\'re almost there! Just enter your license key to receive plugin updates and support.', 'solid-affiliate') ?></div>
                                <div class="sld-form">
                                    <div class="sld-field">
                                        <label for=""><?php _e('License key', 'solid-affiliate') ?></label>
                                        <input type="text" x-model="$store.setupWizard.syncedData.inputLicenseKey" placeholder="<?php _e('Enter your license key', 'solid-affiliate') ?>">
                                    </div>
                                    <button x-bind:disabled="$store.setupWizard.pendingAjax" class="sld-button primary large" x-on:click="$store.setupWizard.postData('license-key')"><?php _e('Activate', 'solid-affiliate') ?></button>
                                    <button class="sld-button active large" x-show="$store.setupWizard.syncedData.isOnKeylessFreeTrial" x-on:click="$store.setupWizard.nextPage()">
                                        <?php echo (self::render_ajaxspinner()); ?>
                                        <?php _e('Skip for now', 'solid-affiliate') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- END - If not active -->

                    <!-- If already active -->
                    <div class="content reversed" x-show="$store.setupWizard.syncedData.isSolidAffiliateActive">
                        <div class="main">
                            <div class="head">
                                <h2 class="lead"><?php _e('Solid Affiliate is activated', 'solid-affiliate') ?></h2>
                                <div class="plain"><?php _e('You have a valid license key associated with this site. Thank you!', 'solid-affiliate') ?></div>
                            </div>
                            <div class="sld-form">
                                <div class="sld-tiny-flex no-gap">
                                    <div style="margin-right:5px;">
                                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M9 15.75C12.7279 15.75 15.75 12.7279 15.75 9C15.75 5.27208 12.7279 2.25 9 2.25C5.27208 2.25 2.25 5.27208 2.25 9C2.25 12.7279 5.27208 15.75 9 15.75Z" stroke="#FF5349" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                            <path d="M6.75 9L8.25 10.5L11.25 7.5" stroke="#FF5349" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </div>
                                    <span class="value truncate" x-text="$store.setupWizard.computedMaskedLicenseKey()"></span>
                                </div>
                                <button class="sld-button active large" x-on:click="$store.setupWizard.nextPage()">
                                    <?php echo (self::render_ajaxspinner()); ?>
                                    <?php _e('Continue', 'solid-affiliate') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- END - If already active -->
                </div>
            </div>
        </div>
    <?php
        return ob_get_clean();
    }


    private static function render_ajaxspinner(): string
    {
        ob_start(); ?>
        <div x-cloak x-show="$store.setupWizard.pendingAjax" class="sld-loading">
            <div class="sld-loader"></div>
        </div>


    <?php
        return ob_get_clean();
    }

    private static function render_errors(): string
    {
        ob_start(); ?>
        <!-- if there are errors, show them -->
        <div class="sld-notice alert" x-cloak x-show="$store.setupWizard.syncedData.errors.length !== 0">
            <div class="icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                    <path d="M12 9v2m0 4v.01"></path>
                    <path d="M5 19h14a2 2 0 0 0 1.84 -2.75l-7.1 -12.25a2 2 0 0 0 -3.5 0l-7.1 12.25a2 2 0 0 0 1.75 2.75"></path>
                </svg>
            </div>
            <div class="content reversed">
                <div class="lead"><?php _e('Oops! Something went wrong.', 'solid-affiliate') ?></div>
                <template x-for="error in $store.setupWizard.syncedData.errors">
                    <div class="plain" x-text="error"></div>
                </template>
            </div>
        </div>
<?php
        return ob_get_clean();
    }
}
