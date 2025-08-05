window.SolidAffiliate = {
  ajaxurl: sld_affiliate_js_variables.ajaxurl,
  affiliate_param: sld_affiliate_js_variables.affiliate_param,
  visit_cookie_key: sld_affiliate_js_variables.visit_cookie_key,
  visit_cookie_expiration_in_days: sld_affiliate_js_variables.visit_cookie_expiration_in_days,
  is_landing_pages_enabled: sld_affiliate_js_variables.landing_pages.is_landing_pages_enabled,
  is_home_page_a_landing_page: sld_affiliate_js_variables.landing_pages.is_home_page_a_landing_page,
  is_cookies_disabled: sld_affiliate_js_variables.is_cookies_disabled,
  is_automatic_visit_tracking_disabled: sld_affiliate_js_variables.is_automatic_visit_tracking_disabled,

  get_affiliate_slug_from_url: function() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(this.affiliate_param);
  },

  maybe_track_visit: function() {
    try {
      let maybe_affiliate_slug = this.get_affiliate_slug_from_url();
      let is_slug_missing_from_url = !maybe_affiliate_slug;
      let is_currently_on_home_page = window.location.pathname === "/";

      if (this.is_cookies_disabled || this.getCookie(this.visit_cookie_key) != null) {
        return;
      }
      if (is_slug_missing_from_url && (!this.is_landing_pages_enabled || (this.is_landing_pages_enabled && !this.is_home_page_a_landing_page && is_currently_on_home_page))) {
        return;
      }

      let data = {
        action: "sld_affiliate_track_visit",
        affiliate_slug: maybe_affiliate_slug,
        landing_url: window.location.href,
        http_referer: document.referrer,
        visit_ip: "",
        previous_visit_id: 0,
      };

      fetch(this.ajaxurl, {
        method: "POST",
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(data)
      })
      .then(response => response.json())
      .then(response => {
        if (response.success && response.data.created_visit_id) {
          this.setCookie(this.visit_cookie_key, response.data.visit_id, this.visit_cookie_expiration_in_days);
        }
      })
      .catch(error => {
        console.error("Error tracking visit:", error);
      });
    } catch (error) {
      console.error("Error in maybe_track_visit:", error);
    }
  },

  setCookie: function(name, value, days) {
    if (this.is_cookies_disabled) return;
    let expires = "";
    if (days) {
      let date = new Date();
      date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
      expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + (value || "") + expires + "; path=/";
  },

  getCookie: function(name) {
    let nameEQ = name + "=";
    let ca = document.cookie.split(';');
    for (let i = 0; i < ca.length; i++) {
      let c = ca[i];
      while (c.charAt(0) === ' ') c = c.substring(1);
      if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
  },

  eraseCookie: function(name) {
    document.cookie = name + "=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;";
  },
};

document.addEventListener("DOMContentLoaded", function() {
  if (window.SolidAffiliate && window.SolidAffiliate.maybe_track_visit && !window.SolidAffiliate.is_automatic_visit_tracking_disabled) {
    try {
      window.SolidAffiliate.maybe_track_visit();
    } catch (error) {
      console.error("Error initializing SolidAffiliate tracking:", error);
    }
  }
});
