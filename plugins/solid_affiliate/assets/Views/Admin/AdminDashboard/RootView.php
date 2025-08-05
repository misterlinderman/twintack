<?php

namespace SolidAffiliate\Views\Admin\AdminDashboard;

use SolidAffiliate\Controllers\AdminDashboardController;
use SolidAffiliate\Lib\AdminNotifications;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Views\Shared\AdminHeader;

class RootView
{

    /**
     * Undocumented function
     *
     * @return string
     */
    public static function render()
    {
        do_action('add_meta_boxes', AdminDashboardController::PAGE_PARAM_INDEX, null);
        ob_start();
?>
        <script>
            jQuery(document).ready(function() {
                postboxes.add_postbox_toggles('solid-affiliate-admin-dashboard');
            });
        </script>

        <style>
            .postbox table.widefat {
                margin: 20px 0;
            }
        </style>

        <div class='wrap'>
            <h1></h1>

            <?php echo AdminNotifications::render_dashboard_notifications(); ?>

            <form name="my_form" method="post">
                <?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false); ?>
                <?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false); ?>
            </form>
            <div id="dashboard-widgets-wrap">
                <div id="dashboard-widgets" class="metabox-holder">

                    <div id="postbox-container-1" class="postbox-container">
                        <?php do_meta_boxes(AdminDashboardController::PAGE_PARAM_INDEX, 'normal', null); ?>
                    </div>

                    <div id="postbox-container-2" class="postbox-container">
                        <?php do_meta_boxes(AdminDashboardController::PAGE_PARAM_INDEX, 'advanced', null); ?>
                    </div>

                    <div id="postbox-container-3" class="postbox-container">
                        <?php do_meta_boxes(AdminDashboardController::PAGE_PARAM_INDEX, 'side', null); ?>
                    </div>

                </div>
            </div>
        </div>
<?php
        return ob_get_clean();
    }
}
