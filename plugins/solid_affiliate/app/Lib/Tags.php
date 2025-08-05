<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliateGroup;
use SolidAffiliate\Views\Admin\Affiliates\EditView;

/**
 * Handles the conceptual "tagging" of affiliates.
 * 
 * [] Handle tag names with spaces, quotes, commas, \/\\\!wdsfwf etc
 *   [] test everything works (deleting, creating, assigning, un-assigning, filters on affiliates table, filtering on pay affiliates)
 * [] Pay Affiliates > Tag filter ... should be a dropdown with all tags
 * [] Be able to enter the tag name.
 * [] Be able to set a color (I imagined a colorful dot next to the affiliate username)
 * [] If it's possible that it uses core WP logic similar to taxonomy or category. 
 * [] There are filters in affiliates page.
 * [] It's possible to set a default tag, and just you did with the affiliate group shortcode, if people signup with [...tag="2".. they will be automatically tagged as "2"]
 * 
 * @psalm-type Tag = array{
 *    name: string,
 *    description: string,
 *    color: string,
 * }
 * 
 * @psalm-import-type EnumOptionsReturnType from \SolidAffiliate\Lib\VO\SchemaEntry
 */
class Tags
{
    const POST_KEY_CREATE_TAG = 'solid-affiliate_submit_create_tag';
    const POST_KEY_DELETE_TAG = 'solid-affiliate_submit_delete_tag';
    const POST_KEY_UPDATE_SPECIFIC_AFFILIATE_TAGS = 'solid-affiliate_submit_update_specific_affiliate_tags';
    const TAGS_OPTION_KEY = 'solid_affiliate_tags';

    public static function register_hooks(): void
    {
        add_filter(EditView::AFFILIATE_EDIT_AFTER_FORM_FILTER, [self::class, 'render_tags_section_on_affiliate_edit'], 10, 2);
    }

    /**
     * Returns the the Affiliate Tags panel HTML for the Affiliates Edit page along with the existing panels passed in by the filter.
     *
     * @param array<string> $panels
     * @param Affiliate|null $affiliate
     *
     * @return array<string>
     */
    public static function render_tags_section_on_affiliate_edit($panels, $affiliate)
    {
        if (is_null($affiliate)) {
            return $panels;
        }

        $all_tags = self::all();
        $this_affiliates_tags = self::tags_for_affiliate_id($affiliate->id);
        $link_to_manage_all_tags = URLs::admin_path(AffiliateGroup::ADMIN_PAGE_KEY, false, ['tab' => 'tags']);

        ob_start();
?>
        <div class="sld_collapsible-section">
            <div class="sld_form-head has-icon sld_collapsible-header">
                <div class="icon-wrapper" style="display: flex; align-items: center;">
                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M24 25.3333L26.1227 23.2107C27.3277 22.0055 28.0047 20.371 28.0047 18.6667C28.0047 16.9624 27.3277 15.3279 26.1227 14.1227L20 8M9.33365 13.3333H9.32031M4 10.6667V16.2293C4.00015 16.9365 4.2812 17.6147 4.78133 18.1147L12.3947 25.728C12.9973 26.3305 13.8145 26.669 14.6667 26.669C15.5188 26.669 16.3361 26.3305 16.9387 25.728L21.728 20.9387C22.3305 20.3361 22.669 19.5188 22.669 18.6667C22.669 17.8145 22.3305 16.9973 21.728 16.3947L14.1147 8.78133C13.6147 8.2812 12.9365 8.00015 12.2293 8H6.66667C5.95942 8 5.28115 8.28095 4.78105 8.78105C4.28095 9.28115 4 9.95942 4 10.6667Z" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <div class="text-wrapper" style="margin-left: 10px;">
                    <h2 id="edit-affiliate-affiliate_meta">Tags</h2>
                    <p>Manage or create new tags for this affiliate</p>
                </div>
                <span class="toggle-icon">
                    <svg class="plus-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 4V20M20 12H4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <svg class="minus-icon" style="display:none;" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20 12H4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
            </div>

            <div class="sld_collapsible-content" style="display:none; padding-top: 20px;">
                <form method="post" action="">
                    <div class="tag-grid">
                        <?php foreach ($all_tags as $tag) : ?>
                            <?php $checked = in_array($tag, $this_affiliates_tags) ? 'checked' : ''; ?>
                            <label class="tag-checkbox">
                                <input type="checkbox" name="tags[]" value="<?= htmlspecialchars($tag['name']) ?>" <?= $checked ?>>
                                <div class="truncate"><?= htmlspecialchars($tag['name']) ?></div>
                                <div class="tag-circle" style="background-color: <?= htmlspecialchars($tag['color']) ?>;"></div>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <?php submit_button(__("Save tag", 'solid-affiliate'), 'primary', self::POST_KEY_UPDATE_SPECIFIC_AFFILIATE_TAGS); ?>

                    <?= wp_nonce_field(self::POST_KEY_UPDATE_SPECIFIC_AFFILIATE_TAGS) ?>
                    <input type="hidden" name="affiliate_id" value="<?= $affiliate->id ?>">
                </form>
            </div>
        </div>
    <?php
        $html = ob_get_clean();
        array_push($panels, $html);
        return $panels;
    }



    /**
     * Fetch all tags from the database.
     *
     * @return Tag[]
     */
    public static function all()
    {
        return Validators::arr_of_tag(get_option(self::TAGS_OPTION_KEY, []));
    }

    /**
     * Save tags to the database.
     *
     * @param Tag[] $tags
     * 
     * @return bool
     */
    public static function save_tags($tags)
    {
        $did_update = update_option(self::TAGS_OPTION_KEY, $tags);

        // if it updated, we should also update the roles
        if ($did_update) {
            $all_tags = self::all();
            $all_tag_names = array_column($all_tags, 'name');
            $all_role_names = array_map(function ($tag_name) {
                return self::tag_name_to_role_name($tag_name);
            }, $all_tag_names);
            $all_existing_roles = wp_roles()->get_names();
            foreach ($all_role_names as $role_name) {
                if (!array_key_exists($role_name, $all_existing_roles)) {
                    add_role($role_name, ucfirst($role_name), []);
                }
            }
        }

        return $did_update;
    }

    /**
     * @param integer $affiliate_id
     * @param string $tag_name
     * @return true|string - True or string error message
     */
    public static function tag_affiliate(int $affiliate_id, string $tag_name)
    {
        // check if the tag exists
        if (!in_array($tag_name, array_column(self::all(), 'name'))) {
            return "Tag does not exist";
        }

        // check if the affiliate exists
        $affiliate = \SolidAffiliate\Models\Affiliate::find($affiliate_id);
        if (is_null($affiliate)) {
            return "Affiliate does not exist";
        }

        // check if the affiliate's user exists
        $user_id = $affiliate->user_id;
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return "User does not exist";
        }

        // Prepare the role based on the tag name
        $role_name = self::tag_name_to_role_name($tag_name);

        // Check if the role exists, if not, register it
        if (!wp_roles()->is_role($role_name)) {
            add_role($role_name, ucfirst($role_name), []);
        }

        // Add the role to the user
        $user->add_role($role_name);

        // Verify that the role was added
        if (in_array($role_name, $user->roles)) {
            return true;
        } else {
            return "Role was not added";
        }
    }

    /**
     * @param integer $affiliate_id
     * @param string $tag_name
     * @return true|string - True or string error message
     */
    public static function untag_affiliate(int $affiliate_id, string $tag_name)
    {
        // check if the tag exists
        if (!in_array($tag_name, array_column(self::all(), 'name'))) {
            return "Tag does not exist";
        }

        // check if the affiliate exists
        $affiliate = \SolidAffiliate\Models\Affiliate::find($affiliate_id);
        if (is_null($affiliate)) {
            return "Affiliate does not exist";
        }

        // check if the affiliate's user exists
        $user_id = $affiliate->user_id;
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return "User does not exist";
        }

        // Prepare the role based on the tag name
        $role_name = self::tag_name_to_role_name($tag_name);

        // Remove the role from the user
        $user->remove_role($role_name);

        // Verify that the role was removed
        if (!in_array($role_name, $user->roles)) {
            return true;
        } else {
            return "Role was not removed";
        }
    }

    /**
     * @param int $affiliate_id
     * @return Tag[]
     */
    public static function tags_for_affiliate_id(int $affiliate_id): array
    {
        $affiliate = \SolidAffiliate\Models\Affiliate::find($affiliate_id);
        if (is_null($affiliate)) {
            return [];
        } else {
            $user_id = $affiliate->user_id;
            $user = get_user_by('ID', $user_id);
            if (!$user) {
                return [];
            } else {
                // Get the Tags from the user's roles
                $mapped_roles = array_map(function ($role_name) {
                    return self::role_name_to_tag_name($role_name);
                }, $user->roles);

                // Filter out the non-tag roles
                $all_tags = self::all();
                return array_filter($all_tags, function ($tag) use ($mapped_roles) {
                    return in_array($tag['name'], $mapped_roles);
                });
            }
        }
    }

    /**
     * @param string $tag_name
     * @return Affiliate[]
     */
    public static function affiliates_for_tag_name(string $tag_name)
    {
        $role_name = self::tag_name_to_role_name($tag_name);
        $users = Validators::arr_of_wp_user(get_users(['role' => $role_name]));
        $affiliates = [];
        foreach ($users as $user) {
            $affiliate = \SolidAffiliate\Models\Affiliate::find_where(['user_id' => $user->ID]);
            if ($affiliate) {
                $affiliates[] = $affiliate;
            }
        }
        return $affiliates;
    }

    /**
     * @param string $tag_name
     * @return int[]
     */
    public static function affiliate_ids_for_tag_name(string $tag_name): array
    {
        $affiliates = self::affiliates_for_tag_name($tag_name);
        return array_map(function ($affiliate) {
            return $affiliate->id;
        }, $affiliates);
    }

    /**
     * Renders the admin page to manage affiliate tags
     *
     * @return void
     */
    public static function admin_root(): void
    {
        ob_start();
        echo \SolidAffiliate\Controllers\AffiliateGroupsController::tabs_for_affiliate_groups_and_tags('tags');
    ?>
        <style>
            .manage-tag {
                display: flex;
                flex-direction: row;
                gap: 40px;
                max-width: calc(100% - 20px);
                overflow: hidden;
                margin-top: 20px;
            }

            .create-tag {
                min-width: 500px;
            }

            input[type="color"] {
                background: transparent !important;
                width: 40px;
                height: 40px;
                border: none;
                outline: none;
                cursor: pointer;
                padding: 0 !important;
                box-sizing: border-box;
            }

            input[type="color"]:focus {
                box-shadow: none;
            }

            .create-tag form div {
                width: 100%;
                display: flex;
                gap: 5px;
                flex-direction: column;
                margin-bottom: 20px;
            }

            .create-tag label {
                font-weight: 500;
            }
        </style>
        <?php echo FF::maybe_overlay('affiliate_tags'); ?>
        <h2>Manage Tags</h2>
        <div class="manage-tag">
            <div class="create-tag">
                <h3>Add new tag</h3>
                <form action="" method="post">
                    <?php echo wp_nonce_field(self::POST_KEY_CREATE_TAG); ?>
                    <div>
                        <label for="tag_name">Name</label>
                        <input type="text" id="tag_name" name="tag_name" placeholder="Enter a valid name" pattern="[a-zA-Z ]+" required title="Only letters and spaces are valid.">
                        <small>Enter a name for the tag. Only letters and spaces are valid.</small>
                    </div>
                    <div>
                        <label for="tag_description">Description</label>
                        <input type="text" id="tag_description" name="tag_description" placeholder="Enter a description (optional)">
                        <small>The description is optional and not prominent. Only seen by adminstrators.</small>
                    </div>
                    <div>
                        <label for="tag_color">Color</label>
                        <input type="color" id="tag_color" name="tag_color" required>
                        <small>Select a color for the tag.</small>
                    </div>
                    <?php
                    if (FF::is_on('affiliate_tags')) {
                        $button_args = [];
                    } else {
                        $button_args = [
                            'disabled' => true,
                            'title' => __('Upgrade to Plus plan to add custom tags', 'solid-affiliate'),
                        ];
                    }
                    ?>
                    <?php submit_button(__("Add New Tag (pro feature)", 'solid-affiliate'), 'primary', self::POST_KEY_CREATE_TAG, true, $button_args); ?>
                </form>
            </div>
            <div class="">
                <table class="wp-list-table widefat fixed striped tags">
                    <thead>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Color</th>
                        <th># of affiliates</th>
                        <th>Actions</th>
                    </thead>
                    <?php
                    $all_tags = self::all();
                    if (empty($all_tags)) {
                        echo '<tr><td colspan="5">No tags found</td></tr>';
                    }
                    foreach ($all_tags as $tag) {
                        $number_of_affiliates = count(self::affiliates_for_tag_name($tag['name']));
                        $url_to_affiliates_table_with_tag_filter = URLs::admin_path(Affiliate::ADMIN_PAGE_KEY, false, ['tag' => $tag['name']]);
                        if ($number_of_affiliates === 0) {
                            $link = $number_of_affiliates . ' affiliates';
                        } else {
                            $link = Links::render($url_to_affiliates_table_with_tag_filter, $number_of_affiliates . ' affiliates');
                        }
                    ?>
                        <tr>
                            <td><?php echo esc_html($tag['name']); ?></td>
                            <td><?php echo esc_html($tag['description']); ?></td>
                            <td><span style="background-color: <?php echo $tag['color']; ?>; width: 14px; height: 14px; border-radius:14px; display: inline-block; border:1px solid var(--sld-border);"></span></td>
                            <td><?php echo $link; ?></td>
                            <td>
                                <form action="" method="post" onsubmit="return confirm('Are you sure you want to delete this tag?');">
                                    <?php echo wp_nonce_field(self::POST_KEY_DELETE_TAG); ?>
                                    <input type="hidden" name="tag_name" value="<?php echo $tag['name']; ?>">
                                    <?php submit_button(__('Delete', 'solid-affiliate'), 'delete small', self::POST_KEY_DELETE_TAG, false); ?>
                                </form>
                            </td>
                        </tr>
                    <?php
                    }
                    ?>
                </table>
                <p>Learn more about how to use <a href="https://docs.solidaffiliate.com/affiliate-tags" target="_blank">affiliate tags</a>.</p>

            </div>
        </div>
<?php
        echo ob_get_clean();
    }


    public static function POST_create_tag(): void
    {
        if (FF::is_off('affiliate_tags')) {
            ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', ['This is a pro feature'], [], 'admin');
            return;
        }

        $tag_name = ((string)$_POST['tag_name']);
        $tag_description = sanitize_text_field((string)$_POST['tag_description']);
        $tag_color = $_POST['tag_color'];

        $tags = self::all(); // Fetch current tags
        $tags[] = [
            'name' => $tag_name,
            'description' => $tag_description,
            'color' => $tag_color,
        ];

        $tags = Validators::arr_of_tag($tags); // Validate tags
        self::save_tags($tags); // Save updated tags
        // Redirect or display a success message
    }

    public static function POST_delete_tag(): void
    {
        $tag_name = (string)$_POST['tag_name'];

        $tags = self::all(); // Fetch current tags

        // Remove the tag
        $tags = array_filter($tags, function ($tag) use ($tag_name) {
            return $tag['name'] !== $tag_name;
        });

        $tags = Validators::arr_of_tag($tags); // Validate tags

        self::save_tags($tags); // Save updated tags

        // check if the tag was deleted
        if (!in_array($tag_name, array_column(self::all(), 'name'))) {
            // Delete the role
            $role_name = self::tag_name_to_role_name($tag_name);
            remove_role($role_name);
        } else {
            // TODO it was not deleted
        }
    }

    /**
     * @param int $affiliate_id
     * @param string[] $selected_tag_names
     * @return void
     */
    public static function set_affiliate_tags_by_tag_names($affiliate_id, $selected_tag_names): void
    {
        $all_available_tags = self::all();
        $all_available_tag_names = array_column($all_available_tags, 'name');

        foreach ($all_available_tag_names as $tag_name) {
            if (in_array($tag_name, $selected_tag_names)) {
                self::tag_affiliate($affiliate_id, $tag_name);
            } else {
                self::untag_affiliate($affiliate_id, $tag_name);
            }
        }

        // TODO also untag
    }


    public static function POST_update_specific_affiliate_tags(): void
    {
        $tag_names = Validators::array_of_string($_POST['tags']);
        $affiliate_id = (int)($_POST['affiliate_id']);

        self::set_affiliate_tags_by_tag_names($affiliate_id, $tag_names);
    }

    private static function tag_name_to_role_name(string $tag_name): string
    {
        return 'Affiliate (' . $tag_name . ')';
    }

    private static function role_name_to_tag_name(string $role_name): string
    {
        return str_replace('Affiliate (', '', str_replace(')', '', $role_name));
    }

    /**
     * Get's a tuple of available Affiliate Groups, for use in enum_options / select / dropdown.
     * 
     * @return EnumOptionsReturnType
     * 
     */
    public static function tags_enum_options()
    {
        $group_tuples = array_map(static function ($tag) {
            return [
                Validators::str($tag['name']),
                Validators::str($tag['name']),
            ];
        }, self::all());

        // PHP is insane, how tf do i do this properly.
        $group_tuples[] = ['', __('Select an affiliate tag', 'solid-affiliate')];
        return array_reverse($group_tuples);
    }
}
