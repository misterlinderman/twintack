var doSubmit = false;

function SLDreCaptchaCallback() {
    grecaptcha.render('solid-affiliate-g-recaptcha', {
        'callback': SLDreCaptchaVerify,
        'expired-callback': SLDreCaptchaExpired
    });
}
window.SLDreCaptchaCallback = SLDreCaptchaCallback;

function SLDreCaptchaVerify(response) {
    if (response === grecaptcha.getResponse()) {
        doSubmit = true;
    }
}
window.SLDreCaptchaVerify = SLDreCaptchaVerify;

function SLDreCaptchaExpired() {
    console.log('reCAPTCHA Expired');
}
window.SLDreCaptchaExpired = SLDreCaptchaExpired;

jQuery(function ($) {
    $(document).ready(function () {
        document.getElementById('solid-affiliate-affiliate-portal_new_affiliate').addEventListener('submit', function (e) {

            if (!doSubmit) {
                e.preventDefault();
                var errorMsg = $('#solid-affiliate-g-recaptcha').data('error_msg');
                alert(errorMsg);
            }
        });
    });
});