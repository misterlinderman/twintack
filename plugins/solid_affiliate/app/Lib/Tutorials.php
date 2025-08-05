<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Controllers\AdminDashboardController;
use SolidAffiliate\Lib\WooSoftwareLicense\WOO_SLT_Licence;
use SolidAffiliate\Views\Shared\SolidModalView;

/**
 * Class Tutorials
 * 
 * The responsibilities of this class are:
 * - Fetch tutorials from the API at solidaffiliate.com
 * - Cache the data for N amount of time
 * - Render the tutorials data for the user
 * 
 * TODO
 *  [x] cache
 *  [] type safety
 * 
 * @psalm-type TutorialData = array{
 *  id: int,
 *  title: string,
 *  link: string,
 *  youtube_link: string,
 *  category_name: string,
 *  category_link: string,
 *  category_id: int,
 * }
 * 
 */
class Tutorials
{
    const TUTORIALS_ENDPOINT = 'https://solidaffiliate.com/wp-json/wp/v2/tutorials?_embed=wp:term';
    const TRANSIENT_KEY_TUTORIALS = 'solid_affiliate_tutorials';
    const TRANSIENT_EXPIRATION_IN_SECONDS = 3600; // 1 hour
    /**
     * @return void
     */
    public static function register_hooks()
    {
    }

    /**
     * @return void
     */
    public static function add_meta_boxes()
    {
        add_meta_box(
            'solid-affiliate_meta-box_tutorials',
            __('Tutorials and stuff', 'solid-affiliate'),
            /** @param mixed $_args */
            function ($_args) {
                echo self::render_tutorials_widget();
            },
            AdminDashboardController::PAGE_PARAM_INDEX,
            'advanced',
            'default',
            []
        );
    }


    /**
     * @return false|TutorialData[]
     */
    public static function get_tutorials_from_api()
    {
        return Utils::solid_transient(
            [self::class, '_get_tutorials_from_api'],
            self::TRANSIENT_KEY_TUTORIALS,
            self::TRANSIENT_EXPIRATION_IN_SECONDS
        );
    }

    /**
     * @return false|TutorialData[]
     */
    public static function _get_tutorials_from_api()
    {
        try {
            // So that we don't DDoS the API during CI
            if (WOO_SLT_Licence::is_test_instance()) {
                return false;
            }

            $response = wp_remote_get(self::TUTORIALS_ENDPOINT);
            if (is_wp_error($response)) {
                return [];
            }
            $body = wp_remote_retrieve_body($response);
            /**
             * @var object[] $tutorials_json
             */
            $tutorials_json = Validators::arr(json_decode($body));



            // validate the data into an array of TutorialData type. If there are any issues just return false
            // use the first value in categories to get the category name and link
            $tutorials = [];
            /**
             * @psalm-suppress MixedAssignment
             */
            foreach ($tutorials_json as $tutorial) {
                /**
                 * @psalm-suppress MixedPropertyFetch
                 * @psalm-suppress MixedArrayAccess
                 */
                $category_id = $tutorial->categories[0];
                // get the cateogry name and link from the _embedded object
                /**
                 * @psalm-suppress MixedArrayAccess
                 * @psalm-suppress MixedPropertyFetch
                 */
                $category_name = $tutorial->_embedded->{'wp:term'}[0][0]->name;
                /**
                 * @psalm-suppress MixedArrayAccess
                 * @psalm-suppress MixedPropertyFetch
                 */
                $category_link = $tutorial->_embedded->{'wp:term'}[0][0]->link;

                /**
                 * @psalm-suppress MixedPropertyFetch
                 */
                $tutorial_title = (string)$tutorial->title->rendered;
                /**
                 * @psalm-suppress MixedPropertyFetch
                 */
                $youtube_link = (string)$tutorial->acf->youtube_link;
                $tutorials[] = [
                    'id' => (int)$tutorial->id,
                    'title' => $tutorial_title,
                    'link' => (string)$tutorial->link,
                    'youtube_link' => $youtube_link,
                    'category_name' => (string)$category_name,
                    'category_link' => (string)$category_link,
                    'category_id' => (int)$category_id,
                ];
            }

            /** @var TutorialData[] */
            return $tutorials;
        } catch (\Exception $e) {
            return false;
        }
    }


    /**
     * Renders the widget for the user.
     * 
     * Each tutorial is grouped by category. The category name is a link to the category page on solidaffiliate.com
     * 
     * @return string
     */
    public static function render_tutorials_widget()
    {
        $tutorials = self::get_tutorials_from_api();

        if (!$tutorials) {
            return 'Data could not be fetched';
        }

        $tutorials_by_category_name = self::group_tutorials_data_by_category($tutorials);

        ob_start();
?>
        <style>
            .solid-affiliate-tutorials-widget__category-name a {
                text-decoration: none;
                color: gray;
            }
        </style>


        <div class="solid-affiliate-tutorials-widget">
            <?php foreach ($tutorials_by_category_name as $category_name => $tutorials) : ?>
                <div class="solid-affiliate-tutorials-widget__category">
                    <h2 class="solid-affiliate-tutorials-widget__category-name">
                        <a href="<?php echo $tutorials[0]['category_link']; ?>" target="_blank">
                            <?php echo $category_name; ?>
                        </a>
                    </h2>
                    <ul class="solid-affiliate-tutorials-widget__tutorials">
                        <?php foreach ($tutorials as $tutorial) : ?>
                            <li class="solid-affiliate-tutorials-widget__tutorial">
                                <?php echo SolidModalView::render(
                                    '▶️ ' . $tutorial['title'],
                                    $tutorial['title'] . ' - ' . $tutorial['category_name'],
                                    self::render_youtube_video_player_from_link($tutorial['youtube_link'])
                                ); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>

    <?php
        return ob_get_clean();
    }

    /**
     * @param string $youtube_link
     * @return string
     */
    public static function render_youtube_video_player_from_link($youtube_link)
    {
        /**
         * @psalm-suppress RedundantCondition
         */
        if (true) {
            return 'TODO see function render_youtube_video_player_from_link the way the modal loads iframes and stuff is loading all the youtube videos at once into the dom and make the JS console explode with errors. Mike will fix this later when we ready.';
        }
        // remove https://www.youtube.com/watch?v= from the link
        $youtube_id = preg_replace('/https:\/\/www.youtube.com\/watch\?v=/', '', $youtube_link);
        ob_start();
    ?>
        <div class="solid-affiliate-youtube-video-player">
            <iframe width="560" height="315" src="https://www.youtube.com/embed/<?php echo $youtube_id; ?>" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
<?php
        return ob_get_clean();
    }


    ////////////////////////////////////////////////////////////////
    // Helper Functions
    ////////////////////////////////////////////////////////////////

    /**
     * This function takes all the tutorials and groups them by category name.
     * 
     * Example Output:
     * 
     * [
     *     'General' => [ ..., ... ],
     *     'Affiliate Management' => [ ..., ... ],
     * ]
     *
     * @param TutorialData[] $tutorials
     * 
     * @return array<string, TutorialData[]>
     */
    public static function group_tutorials_data_by_category($tutorials)
    {
        // First, we group the tutorials by category_name
        $tutorials_by_category_name = [];
        foreach ($tutorials as $tutorial) {
            $category_name = $tutorial['category_name'];
            if (!isset($tutorials_by_category_name[$category_name])) {
                $tutorials_by_category_name[$category_name] = [];
            }
            $tutorials_by_category_name[$category_name][] = $tutorial;
        }
        // sort the tutorials by category_id
        uksort($tutorials_by_category_name, function ($a, $b) use ($tutorials_by_category_name) {
            /**
             * @psalm-suppress MixedArrayOffset
             */
            $a_id = $tutorials_by_category_name[$a][0]['category_id'];
            /**
             * @psalm-suppress MixedArrayOffset
             */
            $b_id = $tutorials_by_category_name[$b][0]['category_id'];
            return $a_id <=> $b_id;
        });

        return $tutorials_by_category_name;
    }
}
