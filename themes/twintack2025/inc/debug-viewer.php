<?php
/**
 * TwinTack Debug Viewer
 *
 * Admin page to view checkout debug logs and compare theme differences
 *
 * @package twintack2025
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Debug Viewer Class
 */
class TwinTack_Debug_Viewer {
    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Get instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add AJAX handlers
        add_action('wp_ajax_clear_debug_logs', array($this, 'clear_debug_logs'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_management_page(
            'TwinTack Debug Viewer',
            'TwinTack Debug',
            'manage_options',
            'twintack-debug-viewer',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get debug logs
        $debug_log_file = WP_CONTENT_DIR . '/twintack-debug.log';
        $debug_log_content = '';
        
        if (file_exists($debug_log_file)) {
            $debug_log_content = file_get_contents($debug_log_file);
        }
        
        // Get theme conflict analysis
        $conflict_file = WP_CONTENT_DIR . '/theme-conflict-analysis.json';
        $conflict_data = array();
        
        if (file_exists($conflict_file)) {
            $conflict_json = file_get_contents($conflict_file);
            $conflict_data = json_decode($conflict_json, true);
        }
        
        // Get WooCommerce log files
        $wc_log_dir = WP_CONTENT_DIR . '/uploads/wc-logs/';
        $wc_log_files = array();
        
        if (is_dir($wc_log_dir)) {
            $files = scandir($wc_log_dir);
            foreach ($files as $file) {
                if ($file != '.' && $file != '..' && is_file($wc_log_dir . $file)) {
                    $wc_log_files[] = $file;
                }
            }
        }
        
        // Nonce for security
        $nonce = wp_create_nonce('twintack_debug_viewer_nonce');
        
        // Render page
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="twintack-debug-wrapper">
                <div class="twintack-debug-header">
                    <h2>Debug Information</h2>
                    <button id="clear-logs" class="button button-secondary">Clear Logs</button>
                </div>
                
                <h3>Theme Information</h3>
                <p>Current theme: <strong><?php echo esc_html(wp_get_theme()->get('Name')); ?></strong> (<?php echo esc_html(wp_get_theme()->get('Version')); ?>)</p>
                
                <div class="twintack-debug-tabs">
                    <ul class="twintack-debug-tab-links">
                        <li class="active"><a href="#tab-debug">Debug Log</a></li>
                        <li><a href="#tab-conflict">Theme Conflicts</a></li>
                        <li><a href="#tab-wc-logs">WooCommerce Logs</a></li>
                    </ul>
                    
                    <div class="twintack-debug-tab-content">
                        <div id="tab-debug" class="twintack-debug-tab active">
                            <h3>TwinTack Debug Log</h3>
                            <?php if (empty($debug_log_content)) : ?>
                                <p>No debug logs found.</p>
                            <?php else : ?>
                                <div class="twintack-log-filters">
                                    <label>
                                        <input type="checkbox" class="filter-checkbox" data-filter="checkout"> Checkout Events
                                    </label>
                                    <label>
                                        <input type="checkbox" class="filter-checkbox" data-filter="js_error"> JavaScript Errors
                                    </label>
                                    <label>
                                        <input type="checkbox" class="filter-checkbox" data-filter="ajax_error"> AJAX Errors
                                    </label>
                                </div>
                                <pre id="debug-log"><?php echo esc_html($debug_log_content); ?></pre>
                            <?php endif; ?>
                        </div>
                        
                        <div id="tab-conflict" class="twintack-debug-tab">
                            <h3>Theme Conflict Analysis</h3>
                            <?php if (empty($conflict_data)) : ?>
                                <p>No theme conflict data found. Visit the checkout page with the conflict detector enabled to generate data.</p>
                            <?php else : ?>
                                <div class="conflict-data">
                                    <h4>Page Information</h4>
                                    <p><strong>URL:</strong> <?php echo esc_html($conflict_data['url'] ?? 'N/A'); ?></p>
                                    <p><strong>Theme:</strong> <?php echo esc_html($conflict_data['theme'] ?? 'N/A'); ?></p>
                                    
                                    <?php if (!empty($conflict_data['errors'])) : ?>
                                        <h4>Detected Errors</h4>
                                        <ul>
                                            <?php foreach ($conflict_data['errors'] as $error) : ?>
                                                <?php if (is_array($error)) : ?>
                                                    <li>Error: <?php echo esc_html($error['message'] ?? 'Unknown error'); ?> at <?php echo esc_html($error['source'] ?? 'Unknown source'); ?>:<?php echo esc_html($error['lineno'] ?? 'Unknown line'); ?></li>
                                                <?php else : ?>
                                                    <li><?php echo esc_html($error); ?></li>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($conflict_data['domInfo'])) : ?>
                                        <h4>DOM Information</h4>
                                        <ul>
                                            <?php foreach ($conflict_data['domInfo'] as $key => $value) : ?>
                                                <li><strong><?php echo esc_html($key); ?>:</strong> <?php echo is_bool($value) ? ($value ? 'Yes' : 'No') : esc_html(is_array($value) ? implode(', ', $value) : $value); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($conflict_data['scripts'])) : ?>
                                        <h4>Loaded Scripts</h4>
                                        <ul>
                                            <?php foreach ($conflict_data['scripts'] as $script) : ?>
                                                <li><?php echo esc_html($script); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div id="tab-wc-logs" class="twintack-debug-tab">
                            <h3>WooCommerce Logs</h3>
                            <?php if (empty($wc_log_files)) : ?>
                                <p>No WooCommerce logs found.</p>
                            <?php else : ?>
                                <div class="wc-log-files">
                                    <label for="wc-log-select">Select log file:</label>
                                    <select id="wc-log-select">
                                        <option value="">Select a log file</option>
                                        <?php foreach ($wc_log_files as $log_file) : ?>
                                            <option value="<?php echo esc_attr($log_file); ?>"><?php echo esc_html($log_file); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <pre id="wc-log-content"></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
            .twintack-debug-wrapper {
                margin-top: 20px;
            }
            
            .twintack-debug-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .twintack-debug-tabs {
                margin-top: 20px;
            }
            
            .twintack-debug-tab-links {
                display: flex;
                margin: 0;
                padding: 0;
                border-bottom: 1px solid #ccc;
            }
            
            .twintack-debug-tab-links li {
                margin: 0;
                padding: 0;
                list-style: none;
            }
            
            .twintack-debug-tab-links a {
                display: block;
                padding: 10px 15px;
                text-decoration: none;
                background: #f1f1f1;
                margin-right: 5px;
                border: 1px solid #ccc;
                border-bottom: none;
            }
            
            .twintack-debug-tab-links li.active a {
                background: #fff;
                border-bottom: 1px solid #fff;
                margin-bottom: -1px;
            }
            
            .twintack-debug-tab {
                display: none;
                padding: 20px;
                border: 1px solid #ccc;
                border-top: none;
            }
            
            .twintack-debug-tab.active {
                display: block;
            }
            
            #debug-log, #wc-log-content {
                background: #f9f9f9;
                padding: 10px;
                max-height: 500px;
                overflow: auto;
                white-space: pre-wrap;
                word-wrap: break-word;
                font-family: monospace;
            }
            
            .twintack-log-filters {
                margin-bottom: 10px;
            }
            
            .twintack-log-filters label {
                margin-right: 15px;
            }
            
            .highlighted {
                background-color: #ffff99;
            }
        </style>
        
        <script>
            jQuery(document).ready(function($) {
                // Tab functionality
                $('.twintack-debug-tab-links a').on('click', function(e) {
                    e.preventDefault();
                    
                    var target = $(this).attr('href');
                    
                    $('.twintack-debug-tab-links li').removeClass('active');
                    $(this).parent().addClass('active');
                    
                    $('.twintack-debug-tab').removeClass('active');
                    $(target).addClass('active');
                });
                
                // Log filtering
                $('.filter-checkbox').on('change', function() {
                    var filter = $(this).data('filter');
                    var isChecked = $(this).prop('checked');
                    
                    if (isChecked) {
                        // Highlight matching lines
                        var pattern = new RegExp('\\[' + filter + '\\]', 'gi');
                        var logContent = $('#debug-log').html();
                        logContent = logContent.replace(pattern, function(match) {
                            return '<span class="highlighted">' + match + '</span>';
                        });
                        $('#debug-log').html(logContent);
                        
                        // Scroll to first occurrence
                        var firstHighlight = $('#debug-log .highlighted').first();
                        if (firstHighlight.length) {
                            $('#debug-log').scrollTop(
                                firstHighlight.offset().top - $('#debug-log').offset().top + $('#debug-log').scrollTop()
                            );
                        }
                    } else {
                        // Remove highlights
                        var logContent = $('#debug-log').html();
                        logContent = logContent.replace(/<span class="highlighted">(\[.*?\])<\/span>/gi, '$1');
                        $('#debug-log').html(logContent);
                    }
                });
                
                // WooCommerce log selection
                $('#wc-log-select').on('change', function() {
                    var logFile = $(this).val();
                    
                    if (!logFile) {
                        $('#wc-log-content').empty();
                        return;
                    }
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'get_wc_log_content',
                            log_file: logFile,
                            security: '<?php echo $nonce; ?>'
                        },
                        success: function(response) {
                            $('#wc-log-content').text(response.data);
                        }
                    });
                });
                
                // Clear logs button
                $('#clear-logs').on('click', function() {
                    if (confirm('Are you sure you want to clear all debug logs?')) {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'clear_debug_logs',
                                security: '<?php echo $nonce; ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert('Logs cleared successfully!');
                                    location.reload();
                                } else {
                                    alert('Failed to clear logs: ' + response.data);
                                }
                            }
                        });
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * AJAX handler to clear debug logs
     */
    public function clear_debug_logs() {
        // Check nonce
        check_ajax_referer('twintack_debug_viewer_nonce', 'security');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have permission to clear logs.');
            wp_die();
        }
        
        // Clear debug log
        $debug_log_file = WP_CONTENT_DIR . '/twintack-debug.log';
        if (file_exists($debug_log_file)) {
            file_put_contents($debug_log_file, '');
        }
        
        // Clear theme conflict analysis
        $conflict_file = WP_CONTENT_DIR . '/theme-conflict-analysis.json';
        if (file_exists($conflict_file)) {
            file_put_contents($conflict_file, '{}');
        }
        
        wp_send_json_success();
        wp_die();
    }
}

// Initialize the debug viewer
add_action('init', array('TwinTack_Debug_Viewer', 'get_instance'));

/**
 * AJAX handler to get WooCommerce log content
 */
function twintack_get_wc_log_content() {
    // Check nonce
    check_ajax_referer('twintack_debug_viewer_nonce', 'security');
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to view logs.');
        wp_die();
    }
    
    // Get log file
    $log_file = isset($_POST['log_file']) ? sanitize_text_field($_POST['log_file']) : '';
    
    if (empty($log_file)) {
        wp_send_json_error('No log file specified.');
        wp_die();
    }
    
    // Check if file exists and is within WooCommerce logs directory
    $wc_log_dir = WP_CONTENT_DIR . '/uploads/wc-logs/';
    $log_path = $wc_log_dir . $log_file;
    
    if (!file_exists($log_path) || !is_file($log_path)) {
        wp_send_json_error('Log file not found.');
        wp_die();
    }
    
    // Get log content
    $log_content = file_get_contents($log_path);
    
    wp_send_json_success($log_content);
    wp_die();
}
add_action('wp_ajax_get_wc_log_content', 'twintack_get_wc_log_content'); 