jQuery(document).ready(function ($) {
    let content = `<div class="wwp-recommendation-main-warapper"><span class='wwpp-recommendation-notice'>
                    <span class='dashicons dashicons-info-outline'>
                    </span>
                    ${wwp_wholesale_main_object?.i18n_get_wholesale_payments}
                    </div>
                  `;
    $('.wc-settings-prevent-change-event').after(content);
})