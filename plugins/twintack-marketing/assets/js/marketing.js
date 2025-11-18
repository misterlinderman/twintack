/**
 * TwinTack Marketing JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Announcement Bar Dismissible
        $('.twintack-announcement-bar.dismissible .twintack-announcement-close').on('click', function() {
            var $bar = $(this).closest('.twintack-announcement-bar');
            var dismissed = localStorage.getItem('twintack_announcement_dismissed');
            
            if (!dismissed) {
                localStorage.setItem('twintack_announcement_dismissed', '1');
                $bar.fadeOut(300, function() {
                    $bar.remove();
                });
            } else {
                $bar.fadeOut(300, function() {
                    $bar.remove();
                });
            }
        });
        
        // Check if announcement was previously dismissed
        var dismissed = localStorage.getItem('twintack_announcement_dismissed');
        if (dismissed && $('.twintack-announcement-bar.dismissible').length) {
            $('.twintack-announcement-bar.dismissible').hide();
        }
    });
})(jQuery);

