jQuery(document).ready(function($) {
    // Initialize the summary tabs
    function initSummaryTabs() {
        const $tabs = $('.woocommerce-summary-tabs');
        if (!$tabs.length) return;

        const $tabLinks = $tabs.find('.tabs li a');
        const $tabPanels = $tabs.find('.woocommerce-Tabs-panel');

        // Hide all panels except the first one
        $tabPanels.not(':first').hide();
        
        // Add active class to the first tab
        $tabs.find('.tabs li:first').addClass('active');

        // Handle tab clicks
        $tabLinks.on('click', function(e) {
            e.preventDefault();
            
            const $this = $(this);
            const target = $this.attr('href');
            
            // Remove active class from all tabs
            $tabs.find('.tabs li').removeClass('active');
            
            // Add active class to current tab
            $this.parent().addClass('active');
            
            // Hide all panels
            $tabPanels.hide();
            
            // Show target panel
            $(target).show();
        });
    }

    // Initialize tabs on document ready
    initSummaryTabs();
    
    // Re-initialize tabs on ajax complete (for dynamic loading)
    $(document).on('ajaxComplete', function() {
        initSummaryTabs();
    });
}); 