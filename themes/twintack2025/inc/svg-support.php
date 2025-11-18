<?php
/**
 * Add SVG Support
 */
class TwinTack_SVG_Support {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('upload_mimes', array($this, 'add_svg_mime_type'));
        add_filter('wp_check_filetype_and_ext', array($this, 'confirm_svg_upload'), 10, 4);
        add_action('admin_head', array($this, 'add_svg_styles'));
    }

    public function add_svg_mime_type($mimes) {
        $mimes['svg'] = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
        return $mimes;
    }

    public function confirm_svg_upload($data, $file, $filename, $mimes) {
        if (isset($data['ext']) && $data['ext'] === 'svg') {
            return $data;
        }

        $filetype = wp_check_filetype($filename, $mimes);

        if ('svg' === $filetype['ext']) {
            $data['type'] = 'image/svg+xml';
            $data['ext'] = 'svg';
        }

        return $data;
    }

    public function add_svg_styles() {
        echo '<style type="text/css">
            .attachment-266x266, .thumbnail img {
                width: 100% !important;
                height: auto !important;
            }
        </style>';
    }

    /**
     * Sanitize SVG content
     */
    public static function sanitize_svg($file) {
        // Read the SVG file
        $content = file_get_contents($file);
        
        // Basic sanitization
        // Remove PHP tags
        $content = preg_replace('/<\?[\s\S]*?\?>/', '', $content);
        
        // Remove script tags
        $content = preg_replace('/<script[\s\S]*?\/script>/', '', $content);
        
        // Remove onclick and similar events
        $content = preg_replace('/on\w+="[^"]*"/', '', $content);
        
        return $content;
    }
}

// Initialize the class
add_action('init', array('TwinTack_SVG_Support', 'get_instance')); 