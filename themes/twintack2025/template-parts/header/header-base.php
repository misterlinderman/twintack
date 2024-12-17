<?php
/**
 * Base header template
 * Uses Header_Configuration and Header_Render classes
 */

// Get header configuration
$header_config = Header_Configuration::get_header_config();

// Get renderer instance
$renderer = Header_Render::get_instance();

// Render header if configuration exists
if ($header_config) {
    echo $renderer->render_header($header_config);
}