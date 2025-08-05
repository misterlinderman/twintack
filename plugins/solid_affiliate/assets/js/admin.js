// This is the primary JS file which will be included on all Admin pages.
///////////////////////////////////////////////////////////
window.SolidAffiliateAdmin = {
  format_money: function (val) {
    var formatter = new Intl.NumberFormat("en-US", {
      style: "currency",
      currency: sld_affiliate_admin_js_variables.current_currency_code,
    });

    return formatter.format(parseFloat(val));
  },
  updateURLParameter: function (url, param, paramVal) {
    var TheAnchor = null;
    var newAdditionalURL = "";
    var tempArray = url.split("?");
    var baseURL = tempArray[0];
    var additionalURL = tempArray[1];
    var temp = "";

    if (additionalURL) {
      var tmpAnchor = additionalURL.split("#");
      var TheParams = tmpAnchor[0];
      TheAnchor = tmpAnchor[1];
      if (TheAnchor)
        additionalURL = TheParams;

      tempArray = additionalURL.split("&");

      for (var i = 0; i < tempArray.length; i++) {
        if (tempArray[i].split('=')[0] != param) {
          newAdditionalURL += temp + tempArray[i];
          temp = "&";
        }
      }
    }
    else {
      var tmpAnchor = baseURL.split("#");
      var TheParams = tmpAnchor[0];
      TheAnchor = tmpAnchor[1];

      if (TheParams)
        baseURL = TheParams;
    }

    if (TheAnchor)
      paramVal += "#" + TheAnchor;

    var rows_txt = temp + "" + param + "=" + paramVal;
    return baseURL + "?" + newAdditionalURL + rows_txt;
  }

};



jQuery(document).ready(function () {
  /**
   * Conditionally hides or shows the DateRangePreset start and end date
   * inputs. Shows them when the user selects "custom" otherwise hides.
   *
   * @param string $selected_value
   *
   * @return void
   */
  var handleDateRangePresetChange = function ($selected_value) {
    if ($selected_value === "custom") {
      // Show the date inputs when 'custom' is selected
      jQuery(" .sld_field-wrapper.start_date").show();
      jQuery(" .sld_field-wrapper.end_date").show();
      jQuery(" #start_date").show();
      jQuery(" #end_date").show();
      jQuery(" label[for=start_date]").show();
      jQuery(" label[for=end_date]").show();
    } else {
      // Hide the date inputs when 'custom' is not selected
      jQuery(" .sld_field-wrapper.start_date").hide();
      jQuery(" .sld_field-wrapper.end_date").hide();
      jQuery(" #start_date").hide();
      jQuery(" #end_date").hide();
      jQuery(" label[for=start_date]").hide();
      jQuery(" label[for=end_date]").hide();
    }
  };

  // Handle initial show/hide on page load.
  var $initial_select_value = jQuery("#date_range_preset").val();
  handleDateRangePresetChange($initial_select_value);

  // Add event listener to handle user changes to the dropdown.
  jQuery("#date_range_preset").change(function (event) {
    var $selected_value = event.target.value;
    handleDateRangePresetChange($selected_value);
  });
});


///////////////////////////////////////////////////////////
// end - Admin Reports
///////////////////////////////////////////////////////////
var SOLID_AFFILIATE_AJAX_ACTIONS = {
  test_ajax_function: "sld_affiliate_test_ajax_function",
};

///////////////////////////////////////////////////////////
// ToolTips
///////////////////////////////////////////////////////////
jQuery(document).ready(function () {
  // for each element with class sld-tooltip

  jQuery(".sld-tooltip").each(function () {
    var $this = jQuery(this);
    var $tooltip_text = $this.attr("data-sld-tooltip-content");

    tippy(this, {
      theme: "light-border",
      allowHTML: true,
      interactive: true,
      interactiveBorder: 10,
      trigger: "mouseenter focus",
      maxWidth: 800,
      content: $tooltip_text
    });
  });
});

///////////////////////////////////////////////////////////
// Modals
///////////////////////////////////////////////////////////
jQuery(document).ready(function () {
  if (typeof MicroModal !== "undefined") {
    MicroModal.init();
  }
});

///////////////////////////////////////////////////////////
// Referral rate settings
///////////////////////////////////////////////////////////
jQuery(document).ready(function () {
  var $referralRateDemoDiv = jQuery(".solid-affiliate-referral-rate-demo")[0];
  if (!$referralRateDemoDiv) {
    return;
  }

  var updateReferralRateDemo = function (
    $exampleAmountDiv,
    $referralRateDiv,
    $commissionDiv,
    $referralRate,
    $referralRateType,
    $elementToHideIfSiteDefault
  ) {
    var example_price = 50.0;
    $exampleAmountDiv.innerHTML =
      SolidAffiliateAdmin.format_money(example_price);

    if ($referralRateType === "percentage") {
      $display_referral_rate = $referralRate + "%";
      $elementToHideIfSiteDefault.removeClass("sld-invisible");
    } else if ($referralRateType === "flat") {
      // $display_referral_rate = "$" + $referralRate;
      $display_referral_rate = SolidAffiliateAdmin.format_money($referralRate);
      $elementToHideIfSiteDefault.removeClass("sld-invisible");
    } else if ($referralRateType === "site_default") {
      $display_referral_rate = "Site Default";
      $elementToHideIfSiteDefault.addClass("sld-invisible");
    }

    $referralRateDiv.innerHTML =
      $display_referral_rate + " " + $referralRateType;

    if ($referralRateType === "percentage") {
      $commissionDiv.innerHTML = SolidAffiliateAdmin.format_money(
        (example_price * $referralRate) / 100
      );
    } else {
      $commissionDiv.innerHTML =
        SolidAffiliateAdmin.format_money($referralRate);
    }
  };

  ///////////////////////////////////////////////////////////

  // debugger;
  var $exampleAmountDiv = jQuery(
    ".solid-affiliate-referral-rate-demo_example-amount"
  )[0];
  var $referralRateDemoDiv = jQuery(".solid-affiliate-referral-rate-demo")[0];
  var $referralRateDiv = jQuery(".solid-affiliate-referral-rate-demo_rate")[0];
  var $commissionDiv = jQuery(
    ".solid-affiliate-referral-rate-demo_commission"
  )[0];

  var $referralRateInput = jQuery("input#referral_rate, input#commission_rate")[0];
  var $referralRateTypeInput = jQuery(
    "select#referral_rate_type, select#commission_type"
  )[0];

  var $elementToHideIfSiteDefault = jQuery(
    ".sld_field-wrapper.commission_rate, sld_field-wrapper.commission_type"
  );

  // check if element exists
  if ($referralRateDemoDiv) {
    updateReferralRateDemo(
      $exampleAmountDiv,
      $referralRateDiv,
      $commissionDiv,
      $referralRateInput.value,
      $referralRateTypeInput.value,
      $elementToHideIfSiteDefault
    );

    // add event listener to handle user changes to the inputs.
    $referralRateInput.addEventListener("input", function (event) {
      updateReferralRateDemo(
        $exampleAmountDiv,
        $referralRateDiv,
        $commissionDiv,
        event.target.value,
        $referralRateTypeInput.value,
        $elementToHideIfSiteDefault
      );
    });

    $referralRateTypeInput.addEventListener("input", function (event) {
      updateReferralRateDemo(
        $exampleAmountDiv,
        $referralRateDiv,
        $commissionDiv,
        $referralRateInput.value,
        event.target.value,
        $elementToHideIfSiteDefault
      );
    });
  }
});

//////////////////////////////////////////////////////////////////
// Hide the commission rate if the referral rate type is set to site default 
jQuery(document).ready(function () {
  var $referralRateTypeInput = jQuery("#lifetime_commissions_referral_rate_type")[0];
  var $elementToHideIfSiteDefault = jQuery(".row-lifetime_commissions_referral_rate");
  if ($referralRateTypeInput && $elementToHideIfSiteDefault) {
    if ($referralRateTypeInput.value === "site_default") {
      // set visibility attribute to collapsed
      $elementToHideIfSiteDefault.css("visibility", "collapse");
    }

    $referralRateTypeInput.addEventListener("input", function (event) {
      if (event.target.value === "site_default") {
        $elementToHideIfSiteDefault.css("visibility", "collapse");
      } else {
        $elementToHideIfSiteDefault.css("visibility", "visible");
      }
    });
  }

});




////////////////////////////////////////////////////////////////////////



////////////////////////////////////////////////////////////////////////
// Select2 - https://select2.org/
jQuery(document).ready(function () {
  if (typeof jQuery.fn.select2 !== "undefined") {
    // Affiliate Search
    jQuery("select.solid-affiliate-affiliate-search-select").select2({
      ajax: {
        url:
          sld_affiliate_admin_js_variables.ajaxurl +
          "?action=sld_affiliate_affiliate_search",
        dataType: "json",
      },
      placeholder: "Select an Affiliate",
      allowClear: true,
      width: "350px",
      templateResult: function (data) {
        if (data.loading) {
          return data.text;
        }

        var $result = jQuery(data.result_html);

        return $result;
      },
      templateSelection: function (data) {
        return data.text;
      },
    });

    // WP User Search
    jQuery("select.solid-affiliate-user-search-select").select2({
      ajax: {
        url:
          sld_affiliate_admin_js_variables.ajaxurl +
          "?action=sld_affiliate_user_search",
        dataType: "json",
      },
      placeholder: "Select a User",
      allowClear: true,
      width: "350px",
      templateResult: function (data) {
        if (data.loading) {
          return data.text;
        }

        var $result = jQuery(data.result_html);

        return $result;
      },
      templateSelection: function (data) {
        return data.text;
      },
    });

    // WooCommerce Product Search
    jQuery("select.solid-affiliate-woocommerce-product-search-select").select2({
      ajax: {
        url:
          sld_affiliate_admin_js_variables.ajaxurl +
          "?action=sld_affiliate_woocommerce_product_search",
        dataType: "json",
      },
      placeholder: "Select a Product",
      allowClear: true,
      width: "350px",
      templateResult: function (data) {
        if (data.loading) {
          return data.text;
        }

        var $result = jQuery(data.result_html);

        return $result;
      },
      templateSelection: function (data) {
        return data.text;
      },
    });

    // WooCommerce Coupon Search
    jQuery("select.solid-affiliate-woocommerce-coupon-search-select").select2({
      ajax: {
        url:
          sld_affiliate_admin_js_variables.ajaxurl +
          "?action=sld_affiliate_woocommerce_coupon_search",
        dataType: "json",
      },
      placeholder: "Select a Coupon",
      allowClear: true,
      width: "350px",
      templateResult: function (data) {
        if (data.loading) {
          return data.text;
        }

        var $result = jQuery(data.result_html);

        return $result;
      },
      templateSelection: function (data) {
        return data.text;
      },
    });
  }
});

jQuery(document).ready(function () {
  jQuery("#solid-affiliate_change-affiliate-preview-id").click(function (e) {
    // prevent the default behavior
    e.preventDefault();
    var affiliateID = jQuery("#admin_portal_preview_affiliate_id").val();
    // redirect to same URL but change the current id query param to the new one
    var newURL = window.SolidAffiliateAdmin.updateURLParameter(window.location.href, 'id', affiliateID);
    var newURL = window.SolidAffiliateAdmin.updateURLParameter(newURL, 'sld_paged', '1');

    window.location.href = newURL;
  });
});

jQuery(document).ready(function () {
  // select jQuery element by data-id = 12
  var $elem1 = jQuery("[data-sld-pay-affiliates='onclick_1']");

  $elem1.click(function (e) {
    $onclick_1_text = jQuery("input[name='onclick_1_text']").val();
    if (!confirm($onclick_1_text)) {
      return false;
    } else {
      jQuery('#bulk-payout-submit-buttons').hide('slow');
      jQuery('#bulk-payout-after-submit').show('slow');
      return true;
    }
  });

  var $elem2 = jQuery("[data-sld-pay-affiliates='onclick_2']");
  $elem2.click(function (e) {
    $onclick_2_text = jQuery("input[name='onclick_2_text']").val();
    if (!confirm($onclick_2_text)) {
      return false;
    }
  })
});

jQuery(document).ready(function () {
  jQuery(".sld-ajax-button").click(function (e) {
    e.preventDefault();
    $this = jQuery(this);
    var ajax_action = $this.data("ajax-action");
    var ajaxurl = sld_affiliate_admin_js_variables.ajaxurl + "?action=" + ajax_action;
    var post_data = $this.data("postdata");

    post_data.action = ajax_action;

    jQuery.ajax({
      url: ajaxurl,
      type: 'POST',
      data: post_data,
      beforeSend: function () {
        $this.addClass("sld-ajax-button-loading");
        $this.prop("disabled", true);
      },
      success: function (response) {
        console.log(response);
        if (response["success"] && response["data"]["valid"]) {
          var recipients = response["data"]["recipients"];
          alert('Email was sent to the following email addresses: ' + recipients);
        } else if (response["success"] && response["data"]["valid"] == false) {
          var msg = response["data"]["error"];

          alert('There was an error sending this email. Error message: ' + msg);
        }
        $this.removeClass("sld-ajax-button-loading");
        $this.prop("disabled", false);
      },
      error: function (response) {
        console.log(response);
        var msg = response["data"]["error"];
        $this.removeClass("sld-ajax-button-loading");
        $this.prop("disabled", false);
      },
    });
  });
});

////////////////////////////////////////////////////////////////////////////////
// Scroll navigation in Admin > Affiliate > Edit
jQuery(document).ready(function () {
  if (jQuery(".edit-affiliate-navigation").length == 0) { return; };

  jQuery('.sld-card.large h2[id^="edit"]').each(function ($i, $elem) {
    // get the id of the h3 element
    var id = $elem.id;
    // get the inner text of the h3 element
    var text = $elem.innerText;
    // create a new anchor element
    var anchor = document.createElement("a");
    // set the href attribute of the anchor element to the id of the h3 element
    anchor.href = "#" + id;
    // set the inner text of the anchor element to the inner text of the h3 element
    anchor.innerText = text;
    // wrape the anchor in a ul and li element
    var li = document.createElement("li");
    li.append(anchor);
    // append the anchor element to the h3 element
    jQuery(".edit-affiliate-navigation ul").append(li);

  });

  var handleScroll = function () {
    var position = jQuery(this).scrollTop();

    jQuery('.sld-card.large h3[id^="edit"]').each(function () {
      var target = jQuery(this).offset().top;
      var id = jQuery(this).attr('id');

      if (position >= (target - 450)) {
        jQuery(".edit-affiliate-navigation a[href!='#" + id + "']").removeClass('active');
        var active = jQuery(".edit-affiliate-navigation a[href='#" + id + "']");

        if (!active.hasClass('active')) {
          active.addClass('active');
        }
      }
    });
  }

  jQuery(window).scroll(
    _.throttle(handleScroll, 50)
  );
});



/////////////////// Collapsable content in Edit Affiliate page

jQuery(document).ready(function($) {
  jQuery('.sld_collapsible-header').on('click', function() {
      var $content = jQuery(this).next('.sld_collapsible-content');
      var $plusIcon = jQuery(this).find('.plus-icon');
      var $minusIcon = jQuery(this).find('.minus-icon');

      $content.slideToggle(300);
      $plusIcon.toggle();
      $minusIcon.toggle();
  });
});


jQuery(document).ready(function() {
  // Function to toggle visibility based on checkbox state
  function toggleSettingCard() {
      if (jQuery('#affiliate_manager-new_affiliate').is(':checked')) {
          jQuery('.sld-card.setting.setting-group-New_affiliate_registration').show(); // Show the setting card
      } else {
          jQuery('.sld-card.setting.setting-group-New_affiliate_registration').hide(); // Hide the setting card
      }
  }

  // Initial check when the page loads
  toggleSettingCard();

  // Add event listener to the checkbox
  jQuery('#affiliate_manager-new_affiliate').change(function() {
      toggleSettingCard(); // Call function to toggle visibility when the checkbox is changed
  });
});