<?php

namespace SolidAffiliate\Models;

use Exception;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use SolidAffiliate\Lib\ListTables\CreativesListTable;
use SolidAffiliate\Lib\MikesDataModel;
use SolidAffiliate\Lib\MikesDataModelTrait;
use SolidAffiliate\Lib\RandomData;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\DatabaseTableOptions;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;

/**
 * @property array $attributes
 *
 * @property int $id
 * @property 'active'|'inactive' $status
 * @property string $name
 * @property string $description
 * @property string $url
 * @property string $creative_text
 * @property string $creative_image_url
 * @property string $created_at
 * @property string $updated_at
 */
class Creative extends MikesDataModel
{
    use MikesDataModelTrait;

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    /**
     * Data table name in database (without prefix).
     * @var string
     */
    const TABLE = "solid_affiliate_creatives";

    const PRIMARY_KEY = 'id';

    /**
     * An affiliate default status.
     * @var string
     */
    const DEFAULT_STATUS = 'active';

    /**
     * The Model name, used to identify it in Filters.
     *
     * @since TBD
     *
     * @var string
     */
    const MODEL_NAME = 'Creative';

    /**
     * Used to represent the URL key on WP Admin
     * 
     * @var string
     */
    const ADMIN_PAGE_KEY = 'solid-affiliate-creatives';

    /**
     * Data table name in database (without prefix).
     * @var string
     * TODO the 'solid_affiliate_' prefix should be standardized somewhere
     */
    protected $table = self::TABLE;

    /**
     * Primary key column name. Default ID
     * @var string
     */
    protected $primary_key = self::PRIMARY_KEY;

    /**
     * @var Schema<"created_at"|"creative_image_url"|"creative_text"|"description"|"id"|"name"|"status"|"updated_at"|"url">|null
     */
    private static $schema_cache = null;

    /**
     * @return array{status: string}
     */
    public static function defaults()
    {
        return [
            'status' => self::DEFAULT_STATUS,
        ];
    }

    /**
     * @return Schema<"created_at"|"creative_image_url"|"creative_text"|"description"|"id"|"name"|"status"|"updated_at"|"url">
     */
    public static function schema()
    {
        if (!is_null(self::$schema_cache)) {
            return self::$schema_cache;
        }

        $entries =  [
            'id' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'auto_increment' => true,
                'primary_key' => true,
                'show_list_table_column' => true,
                'display_name' => __('ID', 'solid-affiliate'),
                'show_on_edit_form' => 'disabled',
                'user_default' => null,
                'validate_callback' =>
                /** @param mixed $id */
                static function ($id) {
                    return is_int($id);
                },
                'sanitize_callback' =>
                /** @param mixed $id */
                static function ($id) {
                    return (int) $id;
                },
                'is_csv_exportable' => true
            ]),
            'status' => new SchemaEntry([
                'type' => 'varchar',
                'length' => 255,
                'required' => true,
                'is_enum' => true,
                'enum_options' => [
                    ['active', __('Active', 'solid-affiliate')],
                    ['inactive', __('Inactive', 'solid-affiliate')],
                ],
                'display_name' => __('Status', 'solid-affiliate'),
                'form_input_description' => __("Activate to show this creative to affiliates. Inactive creatives remain hidden.", 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'user_default' => static::defaults()['status'],
                'validate_callback' =>
                /** @param string $status */
                static function ($status) {
                    return in_array(trim($status), ['active', 'inactive'], true);
                },
                'sanitize_callback' =>
                /** @param string $status */
                static function ($status) {
                    return trim($status);
                },
                'key' => true,
                'is_csv_exportable' => true
            ]),
            'name' => new SchemaEntry([
                'type' => 'text',
                'display_name' => __('Name', 'solid-affiliate'),
                'form_input_description' => __('Name this creative. For internal use only.', 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'validate_callback' =>
                /** @param string $_val */
                static function ($_val) {
                    return true;
                },
                'sanitize_callback' =>
                /** @param string $val */
                static function ($val) {
                    return trim($val);
                },
                'is_csv_exportable' => true
            ]),
            'description' => new SchemaEntry([
                'type' => 'text',
                'display_name' => __('Description', 'solid-affiliate'),
                'form_input_type_override' => 'textarea',
                'form_input_description' => __('A description of this creative. Displayed to affiliates in their portal.', 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => false,
                'validate_callback' =>
                /** @param string $_val */
                static function ($_val) {
                    return true;
                },
                'sanitize_callback' =>
                /** @param string $val */
                static function ($val) {
                    return trim($val);
                },
                'is_csv_exportable' => true
            ]),
            'url' => new SchemaEntry([
                'type' => 'text',
                'required' => true,
                'display_name' => __('Creative URL', 'solid-affiliate'),
                'form_input_description' => __("Where users will land when they click. We'll automatically add the affiliate's unique tracking ID.", 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'validate_callback' =>
                /** @param string $_val */
                static function ($_val) {
                    return true; // TODO URL?
                },
                'sanitize_callback' =>
                /** @param string $val */
                static function ($val) {
                    return trim($val); // TODO URL?
                },
                'is_csv_exportable' => true
            ]),
            'creative_text' => new SchemaEntry([
                'type' => 'text',
                'display_name' => __('Creative Text', 'solid-affiliate'),
                'form_input_description' => __("Enter a text to be used when generating this creative. If you want a text-only creative, do not upload a creative image below.", 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => false,
                'validate_callback' =>
                /** @param string $_val */
                static function ($_val) {
                    return true;
                },
                'sanitize_callback' =>
                /** @param string $val */
                static function ($val) {
                    return trim($val);
                },
                'is_csv_exportable' => true
            ]),
            'creative_image_url' => new SchemaEntry([
                'type' => 'text',
                'display_name' => __('Image URL', 'solid-affiliate'),
                'form_input_description' => __("Enter an URL of the image to be used when generating this image banner creative.", 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => false,
                'validate_callback' =>
                /** @param string $_val */
                static function ($_val) {
                    return true; // TODO URL?
                },
                'sanitize_callback' =>
                /** @param string $val */
                static function ($val) {
                    return trim($val); // TODO URL?
                },
                'is_csv_exportable' => true
            ]),
            'created_at' => new SchemaEntry([
                'type' => 'datetime',
                'display_name' => __('Created at', 'solid-affiliate'),
                'show_list_table_column' => false,
                'validate_callback' =>
                /** @param string $created_at */
                static function ($created_at) {
                    return is_int(strtotime($created_at));
                },
                'sanitize_callback' =>
                /** @param mixed $created_at */
                static function ($created_at) {
                    return Validators::str($created_at);
                },
                'key' => true,
                'is_csv_exportable' => true
            ]),
            'updated_at' => new SchemaEntry([
                'type' => 'datetime',
                'display_name' => __('Updated at', 'solid-affiliate'),
                'validate_callback' =>
                /** @param string $updated_at */
                static function ($updated_at) {
                    return is_int(strtotime($updated_at));
                },
                'sanitize_callback' =>
                /** @param mixed $updated_at */
                static function ($updated_at) {
                    return Validators::str($updated_at);
                },
                'is_csv_exportable' => true
            ]),
        ];

        self::$schema_cache = new Schema(['entries' => $entries]);

        return self::$schema_cache;
    }

    /**
     * @return DatabaseTableOptions
     */
    public static function ct_table_options()
    {
        return new DatabaseTableOptions(array(
            'singular'      => 'Creative',
            'plural'        => 'Creatives',
            'show_ui'       => false,        // Make custom table visible on admin area (check 'views' parameter)
            'show_in_rest'  => false,        // Make custom table visible on rest API
            'version'       => 1,           // Change the version on schema changes to run the schema auto-updater
            'primary_key' => self::PRIMARY_KEY,    // If not defined will be checked on the field that hsa primary_key as true on schema
            'schema'        => self::schema()
        ));
    }

    /**
     * @return string[]
     *
     * @psalm-return array<array-key, string>
     */
    public static function required_fields()
    {
        $schema = self::schema();
        $required_fields = SchemaFunctions::required_fields_from_schema($schema);

        return $required_fields;
    }

    /**
     * Model properties, data column list.
     * @var string[]
     * TODO make this a function of the schema so we can DRY things up.
     */
    protected $attributes = [
        self::PRIMARY_KEY,
        'status',
        'name',
        'description',
        'url',
        'creative_text',
        'creative_image_url',
        'created_at',
        'updated_at'
    ];

    /**
     * @return CreativesListTable
     */
    public static function admin_list_table()
    {
        return new CreativesListTable();
    }


    /**
     * Undocumented function
     *
     * @param \SolidAffiliate\Models\Creative $creative
     * @param int $affiliate_id
     * @return string
     */
    public static function affiliate_link_for_creative($creative, $affiliate_id)
    {
        $referral_variable = (string)Settings::get(Settings::KEY_REFERRAL_VARIABLE);
        $link = add_query_arg([$referral_variable => $affiliate_id], $creative->url);

        return $link;
    }

    /**
     * Undocumented function
     *
     * @param \SolidAffiliate\Models\Creative $creative
     * @param int $affiliate_id
     * @param null|string $img_width
     * 
     * @return string
     */
    public static function generate_html_for_creative($creative, $affiliate_id, $img_width = null)
    {

        $href = Creative::affiliate_link_for_creative($creative, $affiliate_id);
        $text = $creative->creative_text;
        $img_url = $creative->creative_image_url;

        if (empty($img_url)) {
            return "<a href='{$href}' title='{$text}' referrerpolicy='origin'>{$text}</a>";
        } else {
            $img_style = $img_width ? "width: {$img_width};" : '';
            return "<a href='{$href}' title='{$text}' referrerpolicy='origin'><img src='{$img_url}' alt='{$text}' style='{$img_style}' /></a>";
        }
    }

    /**
     *
     * @param \SolidAffiliate\Models\Creative $creative
     * @param int $affiliate_id
     * 
     * @return string
     */
    public static function render_affiliate_portal_creative($creative, $affiliate_id)
    {
        $creative_html = Creative::generate_html_for_creative($creative, $affiliate_id);
        $escaped_creative_html = htmlspecialchars($creative_html);

        $str = "
        <div id='sld-ap-modal-{$creative->id}' class='sld-ap-modal'>
            <div class='sld-ap-modal-content'>
                <span class='sld-ap-close-modal' onclick='window.sldHideCreativeModal({$creative->id}); return false;'>&times;</span>
                <p>" . __('Copy the HTML embed code below and use it to embed on your site, blog, etc. Any clicks on the embedded creative will automatically use your affiliate link', 'solid-affiliate') . ":</p>
                <pre style='max-width: 80%; padding: 40px; border: 1px solid #ccc; margin-top: 20px;'><code>{$escaped_creative_html}</code></pre>
            </div>
        </div>
        <div class='sld-ap-creative_wrapper-card'>
        <div class='sld-ap-creative_wrapper-card_box'>
           <div class='sld-ap-creative_wrapper-card_box-text'>
              <p>{$creative_html} </p>
              <span>" . __('Creative name', 'solid-affiliate') . ":</span>
              <p>{$creative->name}</p>
              <span>" . __('Creative description', 'solid-affiliate') . ":</span>
              <p>{$creative->description}</p>
              <div class='sld-ap-creative_wrapper-card_box-copy'>
              <a class='' href='{$creative->creative_image_url}' download>" . __('Download', 'solid-affiliate') . "</a>
              <a class='' onclick='window.sldShowCreativeModal({$creative->id}); return false;' href='#'>" . __('View Embed Code', 'solid-affiliate') . "</a>
              </div>
              </div>
        </div>
     </div>
        ";

        return $str;
    }

    /**
     * Very WIP, used in a couple tests currently.
     * 
     * Goal: Creates and returns a new Record with random data.
     * 
     * @return self
     */
    public static function random()
    {
        $random_args = [
            'status' => RandomData::from_array(['active', 'inactive']), // TODO these shouldn't be magic strings
            'name' => 'Creative name - ' . RandomData::string(),
            'description' => 'Random Creative description - ' . RandomData::string(),
            'url' => RandomData::url(),
            'creative_text' => 'Random Creative Text - ' . RandomData::string(),
            'creative_image_url' => RandomData::image_url('painting')
        ];

        $either_id = self::upsert($random_args, true);
        if ($either_id->isLeft) {
            throw new Exception(implode(" ", $either_id->left));
        }
        $id = $either_id->right;

        $self = self::find($id);

        /** @var self */
        return $self;
    }
}
