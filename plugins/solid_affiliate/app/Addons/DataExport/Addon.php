<?php

namespace SolidAffiliate\Addons\DataExport;

use SolidAffiliate\Addons\AddonInterface;
use SolidAffiliate\Addons\Core;
use SolidAffiliate\Lib\Capabilities;
use SolidAffiliate\Lib\ControllerFunctions;
use SolidAffiliate\Lib\CsvExport\AffiliateExport;
use SolidAffiliate\Lib\CsvExport\CreativeExport;
use SolidAffiliate\Lib\CsvExport\PayoutExport;
use SolidAffiliate\Lib\CsvExport\ReferralExport;
use SolidAffiliate\Lib\CsvExport\VisitExport;
use SolidAffiliate\Lib\CSVExporter;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\AddonDescription;
use SolidAffiliate\Lib\VO\CsvExport;
use SolidAffiliate\Lib\VO\RouteDescription;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Views\Shared\AdminHeader;

class Addon implements AddonInterface
{
    const ADDON_SLUG = 'data-export';
    const DOCS_URL = 'https://docs.solidaffiliate.com/data-export/';
    const ADMIN_PAGE_KEY = 'solid-affiliate-export-data';
    const DEFAULT_REQUIRED_CAPABILITY = 'read';
    const CSV_EXPORTS_FILTER_NAME = 'solid_affiliate/addons/csv_exports';
    const MENU_TITLE = 'Data Export';

    /**
     * This is the function which gets called when the Addon is loaded.
     * This is the entry point for the Addon.
     *
     * Register your Addon by using the "solid_affiliate/addons/addon_descriptions" filter.
     *
     * Then check if your Addon is enabled, and if so do your stuff.
     *
     * @return void
     */
    public static function register_hooks()
    {
        add_filter("solid_affiliate/addons/addon_descriptions", [self::class, "register_addon_description"]);
        add_action("solid_affiliate/addons/before_settings_form/" . self::ADDON_SLUG, [self::class, "settings_message"]);
    }

    /**
     * This is the function which includes a call to Core::is_addon_enabled() to check if the addon is enabled.
     * 
     * Do not put anything in the register_hooks above besides add_filter and add_action calls. 
     *
     * @return void
     */
    public static function register_if_enabled_hooks()
    {
        if (Core::is_addon_enabled(self::ADDON_SLUG)) {
            add_action("solid_affiliate/admin/submenu_pages/after", [self::class, "add_page_to_submenu"]);
            add_filter("solid_affiliate/PostRequestController/routes", [self::class, "register_routes"]);
            AffiliateExport::register_export();
            ReferralExport::register_export();
            VisitExport::register_export();
            PayoutExport::register_export();
            CreativeExport::register_export();
        }
    }

    /**
     * The returned AddonDescription is used by \SolidAffiliate\Addons\Core
     * to display the addon in the admin panel, handle the settings, etc.
     *
     * @param AddonDescription[] $addon_descriptions
     * @return AddonDescription[]
     */
    public static function register_addon_description($addon_descriptions)
    {
        $settings_schema = new Schema(["entries" => []]);

        $description = new AddonDescription([
            'slug' => self::ADDON_SLUG,
            'name' => __('Export Solid Affiliate Data', 'solid-affiliate'),
            'description' => __("Enable the Data Export tool, allowing all records to be exported in an itemized format.", 'solid-affiliate'),
            'author' => 'Solid Affiliate',
            'graphic_src' => '',
            'settings_schema' => $settings_schema,
            'documentation_url' => self::DOCS_URL,
            'enabled_by_default' => true
        ]);

        $addon_descriptions[] = $description;
        return $addon_descriptions;
    }

    /**
     * Add the Data Export Addon submenu page.
     *
     * @param string $menu_slug
     *
     * @return void
     */
    public static function add_page_to_submenu($menu_slug)
    {
        add_submenu_page(
            $menu_slug,
            'Solid Affiliate - ' . self::MENU_TITLE,
            __(self::MENU_TITLE, 'solid-affiliate'),
            Capabilities::MANAGE_AFFILIATE_PROGRAM,
            self::ADMIN_PAGE_KEY,
            function () {
                echo AdminHeader::render_from_get_request($_GET);
                echo (self::admin_root());
            }
        );
    }

    /**
     * Register a POST route for each resource download.
     *
     * @param RouteDescription[] $routes
     *
     * @return RouteDescription[]
     */
    public static function register_routes($routes)
    {
        $download_routes = array(
            new RouteDescription([
                'post_param_key' => AffiliateExport::POST_PARAM,
                'nonce' => AffiliateExport::NONCE_DOWNLOAD,
                'callback' => function () {
                    self::POST_download_affiliate_csv();
                }
            ]),
            new RouteDescription([
                'post_param_key' => ReferralExport::POST_PARAM,
                'nonce' => ReferralExport::NONCE_DOWNLOAD,
                'callback' => function () {
                    self::POST_download_referral_csv();
                }
            ]),
            new RouteDescription([
                'post_param_key' => VisitExport::POST_PARAM,
                'nonce' => VisitExport::NONCE_DOWNLOAD,
                'callback' => function () {
                    self::POST_download_visit_csv();
                }
            ]),
            new RouteDescription([
                'post_param_key' => PayoutExport::POST_PARAM,
                'nonce' => PayoutExport::NONCE_DOWNLOAD,
                'callback' => function () {
                    self::POST_download_payout_csv();
                }
            ]),
            new RouteDescription([
                'post_param_key' => CreativeExport::POST_PARAM,
                'nonce' => CreativeExport::NONCE_DOWNLOAD,
                'callback' => function () {
                    self::POST_download_creative_csv();
                }
            ]),
        );

        return array_merge($routes, $download_routes);
    }

    /**
     * The message to be displayed on the settings page above the settings form.
     *
     * @return void
     */
    public static function settings_message()
    {
        _e("There are no settings for this Addon. Instead it adds a menu link ", 'solid-affiliate');
        echo self::link_to_admin_page();
        _e(
            " where you can export your data for a given resource (Affiliate, Visit, Referral, etc.).",
            'solid-affiliate'
        );
    }

    /**
     * The POST action that handles downloading Affiliates as CSV.
     *
     * @return never
     */
    public static function POST_download_affiliate_csv()
    {
        self::enforce_capability();
        self::handle_export(AffiliateExport::csv_export());
        exit();
    }

    /**
     * The POST action that handles downloading Referrals as CSV.
     *
     * @return never
     */
    public static function POST_download_referral_csv()
    {
        self::enforce_capability();
        self::handle_export(ReferralExport::csv_export());
        exit();
    }

    /**
     * The POST action that handles downloading Visits as CSV.
     *
     * @return never
     */
    public static function POST_download_visit_csv()
    {
        self::enforce_capability();
        self::handle_export(VisitExport::csv_export());
        exit();
    }

    /**
     * The POST action that handles downloading Payouts as CSV.
     *
     * @return never
     */
    public static function POST_download_payout_csv()
    {
        self::enforce_capability();
        self::handle_export(PayoutExport::csv_export());
        exit();
    }

    /**
     * The POST action that handles downloading Creatives as CSV.
     *
     * @return never
     */
    public static function POST_download_creative_csv()
    {
        self::enforce_capability();
        self::handle_export(CreativeExport::csv_export());
        exit();
    }

    /**
     * The list page for the Data Export UI.
     *
     * @return string
     */
    public static function admin_root()
    {
        $html = "";

        $html .= self::page_heading();

        foreach (self::get_all_csv_exports() as $export) {
            $html .= self::resource_panel($export);
        }
        return $html;
    }

    /**
     * Returns all CsvExports registered.
     *
     * @return CsvExport[]
     */
    private static function get_all_csv_exports()
    {
        return Validators::arr_of_csv_export(apply_filters(self::CSV_EXPORTS_FILTER_NAME, []));
    }

    /**
     * Returns the HTML for the Data Export page heading.
     *
     * @return string
     */
    private static function page_heading()
    {
        ob_start();
?>
        <div class="wrap">
            <h1></h1>
            <div class="addons-note">
                <?php _e("Use this tool to export data for any of the resources below. <strong>This will not change any data or alter the database in anyway</strong>, it will only read and export the data.", 'solid-affiliate') ?>
            </div>
        <?php
        return ob_get_clean();
    }

    /**
     * The HTML for the resource export UI panel.
     *
     * @param CsvExport $export
     *
     * @return string
     */
    private static function resource_panel($export)
    {
        $name = $export->resource_name;

        ob_start();
        ?>
            <div class="sld-card medium setting">
                <div class="sld_setting-heading">
                    <h2>
                        <?php echo sprintf(__('%1$s Export', 'solid-affiliate'), __($name, 'solid-affiliate')) ?>
                    </h2>
                </div>
                <div class="sld_setting-body">
                    <p>
                        <?php echo $export->sub_heading ?>
                        <?php echo sprintf(__('(Total Count: %1$s)', 'solid-affiliate'), number_format(call_user_func($export->record_count_callback))) ?>
                    </p>
                    <p>
                        <?php _e('Columns that will be exported:', 'solid-affiliate') ?>
                    </p>
                    <p>
                        <?php foreach ($export->columns as $column) { ?>
                            <span class="sld_badge"><?php echo $column->name ?></span>
                        <?php } ?>
                    </p>

                    <form action="" method="post" id="<?php echo sprintf('download-%1$s-csv', __($name, 'solid-affiliate')) ?>">
                        <?php wp_nonce_field($export->nonce_download) ?>
                        <?php submit_button(__('Export all CSV', 'solid-affiliate'), 'secondary', $export->post_param) ?>
                    </form>
                </div>

            </div>
    <?php
        return ob_get_clean();
    }

    /**
     * Enforces the user capability before downloading a CSV.
     *
     * @param string $cap
     *
     * @return void
     */
    private static function enforce_capability($cap = self::DEFAULT_REQUIRED_CAPABILITY)
    {
        ControllerFunctions::enforce_current_user_capabilities([$cap]);
    }

    /**
     * Handles downloading the CSV file.
     *
     * @param CsvExport $export
     *
     * @return void
     */
    private static function handle_export($export)
    {
        CSVExporter::download_resource($export);
    }

    /**
     * Returns the HTML that links to the Data Export page.
     *
     * @return string
     */
    private static function link_to_admin_page()
    {
        return sprintf('<a href="%1$s">%2$s</a>', admin_url('admin.php?page=' . self::ADMIN_PAGE_KEY), __(self::MENU_TITLE, 'solid-affiliate'));
    }
}
