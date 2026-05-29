<?php
if (!defined('ABSPATH')) exit;

/**
 * Legacy Grip Designs Importer (CSV)
 * - Admin UI to upload CSVs
 * - Dry-run validation mode
 * - Optional media import for artwork/mockup URLs
 *
 * Expected CSV headers (case-insensitive):
 * legacy_id, customer_email, customer_name, team_name, design_type, design_layout,
 * primary_color, secondary_color, tertiary_color, quantity,
 * artwork_url, artwork_filename, mockup_asset_url,
 * monday_item_id, artwork_status, final_order_id, production_started
 */
class TwinTack_Grip_Importer {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_import_menu'));
    }

    public function add_import_menu() {
        add_submenu_page(
            'edit.php?post_type=grip_design',
            'Import Legacy Grip Designs',
            'Import Legacy Grips',
            'manage_options',
            'grip-importer',
            array($this, 'render_import_page')
        );
    }

    public function render_import_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page', 'twintack-grip-manager'));
        }

        $results = null;
        $dry_run = true;
        $import_media = false;
        $set_author = true;
        $update_existing = true;
        $default_status = 'customer_approved';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('twintack_grip_import');

            $dry_run = !empty($_POST['dry_run']);
            $import_media = !empty($_POST['import_media']);
            $set_author = !empty($_POST['set_author']);
            $update_existing = !empty($_POST['update_existing']);
            $default_status = isset($_POST['default_status']) ? sanitize_text_field($_POST['default_status']) : 'customer_approved';

            $results = $this->handle_import_request($dry_run, $import_media, $set_author, $update_existing, $default_status);
        }

        $sample_url = trailingslashit(plugin_dir_url(__FILE__)) . '../assets/samples/grip-import-sample.csv';

        echo '<div class="wrap">';
        echo '<h1>Import Legacy Grip Designs</h1>';

        echo '<p>Upload a CSV of past customer grip designs to create or update `grip_design` posts that customers can reorder from their account. Match is performed by `legacy_id` (stored in meta as `_grip_legacy_id`).</p>';

        echo '<p><a class="button" href="' . esc_url($sample_url) . '">Download Sample CSV</a></p>';

        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('twintack_grip_import');
        echo '<table class="form-table">';

        echo '<tr><th scope="row">CSV File</th><td><input type="file" name="grip_import_csv" accept=".csv" required></td></tr>';

        echo '<tr><th scope="row">Mode</th><td>';
        echo '<label><input type="checkbox" name="dry_run" value="1" ' . checked($dry_run, true, false) . '> Dry run (no changes)</label>';
        echo '<p class="description">Validate and preview without creating/updating any posts.</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Media Import</th><td>';
        echo '<label><input type="checkbox" name="import_media" value="1" ' . checked($import_media, true, false) . '> Download artwork/mockup URLs into Media Library</label>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Authorship</th><td>';
        echo '<label><input type="checkbox" name="set_author" value="1" ' . checked($set_author, true, false) . '> Assign post author by customer email if user exists</label>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Update Strategy</th><td>';
        echo '<label><input type="checkbox" name="update_existing" value="1" ' . checked($update_existing, true, false) . '> Update existing posts when `legacy_id` matches</label>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Default Artwork Status</th><td>';
        echo '<select name="default_status">';
        $statuses = array(
            'artwork_pending' => 'Mockup Required',
            'pending_review' => 'Customer Review',
            'customer_requested_changes' => 'Customer Changes',
            'customer_approved' => 'Customer Approved',
            'in_production' => 'In Production',
            'in_production' => 'In Production',
            'shipped' => 'Shipped'
        );
        foreach ($statuses as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($default_status, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">Used if CSV row has no `artwork_status`.</p>';
        echo '</td></tr>';

        echo '</table>';
        submit_button($dry_run ? 'Validate CSV (Dry Run)' : 'Import Now');
        echo '</form>';

        if ($results) {
            $this->render_results($results, $dry_run);
        }

        echo '</div>';
    }

    private function handle_import_request($dry_run, $import_media, $set_author, $update_existing, $default_status) {
        if (!isset($_FILES['grip_import_csv']) || empty($_FILES['grip_import_csv']['tmp_name'])) {
            return array(
                'summary' => array('created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 1),
                'rows' => array(array('row' => 0, 'result' => 'error', 'message' => 'No CSV file uploaded'))
            );
        }

        $tmp = $_FILES['grip_import_csv']['tmp_name'];
        $fh = fopen($tmp, 'r');
        if (!$fh) {
            return array(
                'summary' => array('created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 1),
                'rows' => array(array('row' => 0, 'result' => 'error', 'message' => 'Could not open uploaded file'))
            );
        }

        // Normalize header map (lowercase)
        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            return array(
                'summary' => array('created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 1),
                'rows' => array(array('row' => 0, 'result' => 'error', 'message' => 'CSV missing header row'))
            );
        }
        $map = array();
        foreach ($header as $idx => $col) {
            $map[strtolower(trim($col))] = $idx;
        }

        $required = array('legacy_id', 'customer_email', 'customer_name', 'team_name', 'quantity');
        $missing = array();
        foreach ($required as $req) {
            if (!array_key_exists($req, $map)) {
                $missing[] = $req;
            }
        }
        if (!empty($missing)) {
            fclose($fh);
            return array(
                'summary' => array('created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 1),
                'rows' => array(array('row' => 0, 'result' => 'error', 'message' => 'Missing required columns: ' . implode(', ', $missing)))
            );
        }

        // Prepare WP includes for media sideload if needed
        if ($import_media && !$dry_run) {
            if (!function_exists('media_handle_sideload')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
            }
        }

        $row_num = 1; // header consumed
        $created = 0; $updated = 0; $skipped = 0; $errors = 0;
        $row_results = array();

        while (($row = fgetcsv($fh)) !== false) {
            $row_num++;
            if ($this->row_is_empty($row)) {
                $skipped++;
                $row_results[] = array('row' => $row_num, 'result' => 'skipped', 'message' => 'Empty row');
                continue;
            }

            $data = $this->extract_row_data($row, $map);

            // Basic validation
            if (empty($data['legacy_id'])) {
                $errors++;
                $row_results[] = array('row' => $row_num, 'result' => 'error', 'message' => 'Missing legacy_id');
                continue;
            }
            if (empty($data['customer_email']) || !is_email($data['customer_email'])) {
                $errors++;
                $row_results[] = array('row' => $row_num, 'result' => 'error', 'message' => 'Invalid customer_email');
                continue;
            }

            $result = $this->import_row($data, array(
                'dry_run' => $dry_run,
                'import_media' => $import_media,
                'set_author' => $set_author,
                'update_existing' => $update_existing,
                'default_status' => $default_status,
            ));

            if ($result['result'] === 'created') $created++;
            elseif ($result['result'] === 'updated') $updated++;
            elseif ($result['result'] === 'skipped') $skipped++;
            else $errors++;

            $result['row'] = $row_num;
            $row_results[] = $result;
        }

        fclose($fh);

        return array(
            'summary' => compact('created', 'updated', 'skipped', 'errors'),
            'rows' => $row_results
        );
    }

    private function row_is_empty($row) {
        if (!is_array($row)) return true;
        foreach ($row as $cell) {
            if (trim((string)$cell) !== '') return false;
        }
        return true;
    }

    private function extract_row_data($row, $map) {
        $get = function($key) use ($row, $map) {
            $k = strtolower($key);
            if (!isset($map[$k])) return '';
            $val = isset($row[$map[$k]]) ? $row[$map[$k]] : '';
            return is_string($val) ? trim($val) : $val;
        };

        return array(
            'legacy_id' => $get('legacy_id'),
            'customer_email' => strtolower($get('customer_email')),
            'customer_name' => $get('customer_name'),
            'team_name' => $get('team_name'),
            'design_type' => $get('design_type'),
            'design_layout' => $get('design_layout'),
            'primary_color' => $get('primary_color'),
            'secondary_color' => $get('secondary_color'),
            'tertiary_color' => $get('tertiary_color'),
            'quantity' => (int)$get('quantity'),
            'artwork_url' => esc_url_raw($get('artwork_url')),
            'artwork_filename' => $get('artwork_filename'),
            'mockup_asset_url' => esc_url_raw($get('mockup_asset_url')),
            'monday_item_id' => $get('monday_item_id'),
            'artwork_status' => $get('artwork_status'),
            'final_order_id' => $get('final_order_id'),
            'production_started' => $get('production_started'),
        );
    }

    private function import_row($data, $options) {
        $legacy_id = sanitize_text_field($data['legacy_id']);
        $customer_email = sanitize_email($data['customer_email']);

        // Find existing by legacy id
        $existing = get_posts(array(
            'post_type' => 'grip_design',
            'meta_key' => '_grip_legacy_id',
            'meta_value' => $legacy_id,
            'posts_per_page' => 1,
            'post_status' => 'any',
            'fields' => 'ids'
        ));
        $post_id = $existing ? (int)$existing[0] : 0;

        if ($post_id && empty($options['update_existing'])) {
            return array('result' => 'skipped', 'message' => 'Existing legacy_id, updates disabled', 'grip_id' => $post_id);
        }

        $postarr = array(
            'post_type' => 'grip_design',
            'post_status' => 'publish',
            'post_title' => $this->build_post_title($data),
        );

        if (!$options['dry_run']) {
            if ($post_id) {
                $postarr['ID'] = $post_id;
                $post_id = wp_update_post($postarr, true);
                if (is_wp_error($post_id)) {
                    return array('result' => 'error', 'message' => $post_id->get_error_message());
                }
            } else {
                $post_id = wp_insert_post($postarr, true);
                if (is_wp_error($post_id)) {
                    return array('result' => 'error', 'message' => $post_id->get_error_message());
                }
            }
        }

        // Meta fields
        $meta = array(
            '_grip_legacy_id' => $legacy_id,
            '_grip_customer_email' => $customer_email,
            '_grip_customer_name' => sanitize_text_field($data['customer_name']),
            '_grip_team_name' => sanitize_text_field($data['team_name']),
            '_grip_design_type' => sanitize_text_field($data['design_type']),
            '_grip_design_layout' => sanitize_text_field($data['design_layout']),
            '_grip_primary_color' => sanitize_text_field($data['primary_color']),
            '_grip_secondary_color' => sanitize_text_field($data['secondary_color']),
            '_grip_tertiary_color' => sanitize_text_field($data['tertiary_color']),
            '_grip_quantity' => max(1, absint($data['quantity'])),
            '_grip_artwork_url' => $data['artwork_url'],
            '_grip_artwork_filename' => sanitize_text_field($data['artwork_filename']),
            '_grip_mockup_asset_url' => $data['mockup_asset_url'],
            '_grip_monday_item_id' => sanitize_text_field($data['monday_item_id']),
            '_grip_final_order_id' => sanitize_text_field($data['final_order_id']),
        );

        $status = !empty($data['artwork_status']) ? sanitize_text_field($data['artwork_status']) : $options['default_status'];
        $meta['_grip_artwork_status'] = $status;

        if (!empty($data['production_started'])) {
            $meta['_grip_production_started'] = sanitize_text_field($data['production_started']);
        }

        if (!$options['dry_run']) {
            foreach ($meta as $k => $v) {
                if ($v === '' || $v === null) continue;
                update_post_meta($post_id, $k, $v);
            }
        }

        // Assign author by email if user exists
        if ($options['set_author'] && !$options['dry_run']) {
            $user = get_user_by('email', $customer_email);
            if ($user) {
                wp_update_post(array('ID' => $post_id, 'post_author' => $user->ID));
            }
        }

        // Media import
        $media_notes = array();
        if ($options['import_media'] && !$options['dry_run']) {
            $mockup_id = 0;
            if (!empty($data['mockup_asset_url'])) {
                $mockup_id = $this->sideload_media($data['mockup_asset_url'], $post_id, 'Grip Mockup');
                if (is_wp_error($mockup_id)) {
                    $media_notes[] = 'Mockup import failed: ' . $mockup_id->get_error_message();
                } elseif ($mockup_id) {
                    set_post_thumbnail($post_id, $mockup_id);
                }
            }

            if (!empty($data['artwork_url'])) {
                $artwork_id = $this->sideload_media($data['artwork_url'], $post_id, 'Original Artwork');
                if (is_wp_error($artwork_id)) {
                    $media_notes[] = 'Artwork import failed: ' . $artwork_id->get_error_message();
                }
            }
        }

        $message = $post_id ? ('Grip ID ' . $post_id . ' ready' . (!empty($media_notes) ? ' (' . implode('; ', $media_notes) . ')' : '')) : 'Validated';
        if ($existing) {
            return array('result' => $options['dry_run'] ? 'updated' : 'updated', 'message' => $message, 'grip_id' => $post_id);
        }
        return array('result' => $options['dry_run'] ? 'created' : 'created', 'message' => $message, 'grip_id' => $post_id);
    }

    private function build_post_title($data) {
        $parts = array();
        if (!empty($data['team_name'])) $parts[] = $data['team_name'];
        if (!empty($data['design_type'])) $parts[] = $data['design_type'];
        if (!empty($data['legacy_id'])) $parts[] = '#' . $data['legacy_id'];
        $title = trim(implode(' - ', $parts));
        if ($title === '') {
            $title = 'Legacy Grip ' . (string)$data['legacy_id'];
        }
        return $title;
    }

    private function sideload_media($url, $post_id, $desc = '') {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return 0;
        }
        // Attempt to sideload and return attachment ID
        $attachment_id = 0;
        $result = media_sideload_image($url, $post_id, $desc, 'id');
        if (is_wp_error($result)) {
            return $result;
        }
        $attachment_id = intval($result);
        return $attachment_id;
    }

    private function render_results($results, $dry_run) {
        $s = $results['summary'];
        echo '<hr>';
        echo '<h2>Results (' . ($dry_run ? 'Dry Run' : 'Import') . ')</h2>';
        echo '<p><strong>Created:</strong> ' . intval($s['created']) . ' &nbsp; '
            . '<strong>Updated:</strong> ' . intval($s['updated']) . ' &nbsp; '
            . '<strong>Skipped:</strong> ' . intval($s['skipped']) . ' &nbsp; '
            . '<strong>Errors:</strong> ' . intval($s['errors']) . '</p>';

        echo '<table class="widefat striped" style="margin-top:10px">';
        echo '<thead><tr>';
        echo '<th style="width:80px">Row</th><th>Result</th><th>Message</th><th>Grip ID</th>';
        echo '</tr></thead><tbody>';
        foreach ($results['rows'] as $r) {
            echo '<tr>';
            echo '<td>' . intval($r['row']) . '</td>';
            $result_label = esc_html(ucfirst($r['result']));
            $color = ($r['result'] === 'error') ? '#d63638' : (($r['result'] === 'skipped') ? '#999' : '#2271b1');
            echo '<td style="color:' . esc_attr($color) . '">' . $result_label . '</td>';
            echo '<td>' . esc_html($r['message'] ?? '') . '</td>';
            $gid = isset($r['grip_id']) && $r['grip_id'] ? intval($r['grip_id']) : '';
            if ($gid) {
                $link = get_edit_post_link($gid);
                echo '<td><a href="' . esc_url($link) . '">' . $gid . '</a></td>';
            } else {
                echo '<td></td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
}


