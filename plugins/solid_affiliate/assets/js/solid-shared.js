////////////////////////////////////////////////////////////////////////////////////////
// STORE CREDIT
// This is currently being used by the Store Credit addon to enable the
// 'apply store credit' button for logged in affiliates, on the cart and checkout pages.
////////////////////////////////////////////////////////////////////////////////////////
jQuery(document).ready(function () {
    jQuery(".sld-ajax-button").click(function (e) {
        e.preventDefault();
        $this = jQuery(this);
        var ajax_action = $this.data("ajax-action");
        var ajaxurl = sld_affiliate_js_variables.ajaxurl + "?action=" + ajax_action;
        var post_data = $this.data("postdata");

        post_data.action = ajax_action;

        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: post_data,
            beforeSend: function () {
                $this.prop("disabled", true);
                $this.addClass("sld-ajax-button-loading");
            },
            success: function (response) {
                if (response["success"] && response["data"]["valid"]) {
                    jQuery(document.body).trigger("wc_update_cart");
                    jQuery(document.body).trigger("update_checkout");
                } else if (response["success"] == false) {
                    var msg = response["data"]["error"];
                    console.log("in here: .sld-ajax-button click success == false");
                    alert(msg);
                }
                $this.prop("disabled", false);
                $this.removeClass("sld-ajax-button-loading");
            },
            error: function (response) {
                $this.prop("disabled", false);
                $this.removeClass("sld-ajax-button-loading");
                var msg = response["data"]["error"];
                console.log("in here: .sld-ajax-button click error");
                alert(msg);
            },
        });
    });
});