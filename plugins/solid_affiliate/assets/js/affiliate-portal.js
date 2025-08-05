console.log("affiliate-portal.js");
///////////////////////////////////////////////////////////
// ToolTips
///////////////////////////////////////////////////////////
jQuery(document).ready(function () {
  jQuery(".sld-tooltip").each(function () {
    var $this = jQuery(this);
    var $tooltip_text = $this.attr("data-sld-tooltip-content");

    tippy(this, {
      theme: "light-border",
      allowHTML: true,
      interactive: true,
      interactiveBorder: 10,
      trigger: "mouseenter focus click",
      maxWidth: 800,
      content: $tooltip_text
    });
  });

  // This is an attempt to make the pagination work with aggresive smooth scroll
  // javascript which many of our customers' themes seem to use.
  jQuery('#solid-affiliate-affiliate-portal_dashboard .sld-pagination_pages a[href*="#"]').on(
    'click',
    function (e) { window.location = jQuery(this).attr('href') }
  );
});

///////////////////////////////////////////////////////////
// Modals
///////////////////////////////////////////////////////////
jQuery(document).ready(function () {
  if (typeof MicroModal !== "undefined") {
    MicroModal.init();
  }
});
