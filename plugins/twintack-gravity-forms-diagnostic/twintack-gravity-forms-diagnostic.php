<?php
/**
 * Plugin Name: TwinTack Gravity Forms Diagnostic
 * Description: Diagnostic tool to identify plugin conflicts with Gravity Forms. Safe to activate/deactivate without affecting main functionality.
 * Version: 1.2.0
 * Author: TwinTack Team
 */

if (!defined('ABSPATH')) exit;

class TwinTack_Gravity_Forms_Diagnostic {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_footer', array($this, 'add_diagnostic_tool'));
    }
    
    public function add_diagnostic_tool() {
        // Only show on pages with Gravity Forms
        if (!is_page() || !has_shortcode(get_post()->post_content, 'gravityform')) {
            return;
        }
        
        echo '
        <div id="twintack-gf-diagnostic" style="position: fixed; bottom: 10px; right: 10px; width: 400px; max-height: 500px; background: #fff; border: 2px solid #007cba; border-radius: 5px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 9999; font-family: monospace; font-size: 12px; overflow-y: auto;">
            <div style="background: #333; padding: 8px; margin-bottom: 10px; border-radius: 3px;">
                 <strong style="color: #00ff00;">Gravity Forms Conflict Diagnostic v1.2.0</strong>
                <button onclick="document.getElementById(\'twintack-gf-diagnostic\').style.display=\'none\'" style="float: right; background: #ff0000; color: #fff; border: none; padding: 2px 6px; border-radius: 2px; cursor: pointer; font-size: 11px;">×</button>
            </div>
            <div id="gf-diagnostic-content"></div>
        </div>
        
        <script>
        (function() {
            var content = document.getElementById("gf-diagnostic-content");
            var log = [];
            
            function addLog(msg, type) {
                var color = type === "error" ? "#ff6b6b" : type === "warning" ? "#ffd93d" : type === "info" ? "#6bbaff" : "#6bcf7f";
                log.push("<div style=\"color: " + color + "; margin: 3px 0; font-size: 11px;\">" + msg + "</div>");
                content.innerHTML = log.join("");
            }
            
            // Get scripts first
            var scripts = document.getElementsByTagName("script");
            
            // Check for Gravity Forms
            if (typeof window.gform !== "undefined") {
                addLog("✓ Gravity Forms loaded", "success");
            } else {
                addLog("✗ Gravity Forms NOT loaded", "error");
                
                // Check for Gravity Forms scripts
                var gfScripts = [];
                for (var i = 0; i < scripts.length; i++) {
                    var src = scripts[i].src;
                    if (src && (src.indexOf("gravityforms") !== -1 || src.indexOf("gform_") !== -1)) {
                        gfScripts.push(src);
                    }
                }
                
                if (gfScripts.length > 0) {
                    addLog("Gravity Forms scripts found but not initialized:", "warning");
                    gfScripts.forEach(function(script) {
                        addLog("  - " + script, "warning");
                    });
                } else {
                    addLog("No Gravity Forms scripts found", "error");
                }
                
                // Check for Gravity Forms CSS
                var gfStyles = document.querySelectorAll("link[href*=\"gravityforms\"], link[href*=\"gform_\"]");
                if (gfStyles.length > 0) {
                    addLog("Gravity Forms CSS found: " + gfStyles.length + " files", "info");
                } else {
                    addLog("No Gravity Forms CSS found", "warning");
                }
            }
            
            // Check for form element
            var form = document.querySelector("form[id*=\'gform_\']");
            if (form) {
                addLog("✓ Gravity Form found: " + form.id, "success");
            } else {
                addLog("✗ No Gravity Form found", "error");
            }
            
            // Script analysis
            var scriptAnalysis = {};
            var potentialConflicts = [];
            
            try {
                for (var i = 0; i < scripts.length; i++) {
                    var src = scripts[i].src;
                    if (src) {
                        var scriptName = src.split("/").pop().split("?")[0];
                        
                        // Categorize scripts
                        if (src.indexOf("klaviyo") !== -1) {
                            potentialConflicts.push("Klaviyo: " + scriptName);
                            scriptAnalysis["Klaviyo"] = (scriptAnalysis["Klaviyo"] || 0) + 1;
                        } else if (src.indexOf("gtm") !== -1 || src.indexOf("googletagmanager") !== -1) {
                            potentialConflicts.push("Google Tag Manager: " + scriptName);
                            scriptAnalysis["GTM"] = (scriptAnalysis["GTM"] || 0) + 1;
                        } else if (src.indexOf("facebook") !== -1 || src.indexOf("fbevents") !== -1) {
                            potentialConflicts.push("Facebook Pixel: " + scriptName);
                            scriptAnalysis["Facebook"] = (scriptAnalysis["Facebook"] || 0) + 1;
                        } else if (src.indexOf("gravityforms") !== -1) {
                            scriptAnalysis["Gravity Forms"] = (scriptAnalysis["Gravity Forms"] || 0) + 1;
                        } else if (src.indexOf("woocommerce") !== -1) {
                            scriptAnalysis["WooCommerce"] = (scriptAnalysis["WooCommerce"] || 0) + 1;
                        } else if (src.indexOf("jquery") !== -1) {
                            scriptAnalysis["jQuery"] = (scriptAnalysis["jQuery"] || 0) + 1;
                        }
                    }
                }
            } catch (e) {
                addLog("Script analysis error: " + e.message, "error");
            }
            
            // Display script analysis
            addLog("", "info");
            addLog("=== SCRIPT ANALYSIS ===", "info");
            for (var category in scriptAnalysis) {
                addLog(category + ": " + scriptAnalysis[category] + " scripts", "info");
            }
            
            // Enhanced script source analysis
            addLog("", "info");
            addLog("=== SCRIPT SOURCE ANALYSIS ===", "info");
            
            // Check for Facebook for WooCommerce specifically
            var facebookScripts = [];
            var gtmScripts = [];
            var klaviyoScripts = [];
            
            try {
                for (var i = 0; i < scripts.length; i++) {
                    var src = scripts[i].src;
                    if (src) {
                        if (src.indexOf("facebook") !== -1 || src.indexOf("fbevents") !== -1) {
                            facebookScripts.push(src);
                        }
                        if (src.indexOf("gtm") !== -1 || src.indexOf("googletagmanager") !== -1) {
                            gtmScripts.push(src);
                        }
                        if (src.indexOf("klaviyo") !== -1) {
                            klaviyoScripts.push(src);
                        }
                    }
                }
            } catch (e) {
                addLog("Enhanced script analysis error: " + e.message, "error");
            }
            
            if (facebookScripts.length > 0) {
                addLog("Facebook Scripts Found:", "warning");
                facebookScripts.forEach(function(script) {
                    addLog("  - " + script, "warning");
                });
                addLog("Source: Likely Facebook for WooCommerce plugin", "warning");
            }
            
            if (gtmScripts.length > 0) {
                addLog("GTM Scripts Found:", "warning");
                gtmScripts.forEach(function(script) {
                    addLog("  - " + script, "warning");
                });
                addLog("Source: Check for active GTM plugins or hardcoded scripts", "warning");
            }
            
            if (klaviyoScripts.length > 0) {
                addLog("Klaviyo Scripts Found:", "warning");
                klaviyoScripts.forEach(function(script) {
                    addLog("  - " + script, "warning");
                });
                addLog("Source: Likely hardcoded in theme or active Klaviyo plugin", "warning");
            }
            
            // Display potential conflicts
            if (potentialConflicts.length > 0) {
                addLog("", "warning");
                addLog("=== POTENTIAL CONFLICTS ===", "warning");
                potentialConflicts.forEach(function(conflict) {
                    addLog("⚠ " + conflict, "warning");
                });
            }
            
            // Check for form field visibility issues
            if (form) {
                var hiddenFields = form.querySelectorAll(".gfield[style*=\'display: none\']");
                var visibleFields = form.querySelectorAll(".gfield:not([style*=\'display: none\'])"); 
                addLog("", "info");
                addLog("=== FORM ANALYSIS ===", "info");
                addLog("Visible fields: " + visibleFields.length, "info");
                addLog("Hidden fields: " + hiddenFields.length, hiddenFields.length > 0 ? "warning" : "info");
                
                // Check for image choice fields
                var imageFields = form.querySelectorAll(".gfield_image_choice, .ginput_container_image_choice");
                if (imageFields.length > 0) {
                    addLog("Image choice fields: " + imageFields.length, "success");
                }
            }
            
            // Plugin detection
            addLog("", "info");
            addLog("=== PLUGIN DETECTION ===", "info");
            
            // Check for specific plugin indicators
            var pluginIndicators = {
                \'Facebook for WooCommerce\': false,
                \'Klaviyo\': false,
                \'GTM4WP\': false,
                \'WP Analytify\': false,
                \'Google Analytics for WordPress\': false
            };
            
            // Check for plugin-specific scripts and elements
            try {
                for (var i = 0; i < scripts.length; i++) {
                    var src = scripts[i].src;
                    if (src) {
                        if (src.indexOf("facebook") !== -1 || src.indexOf("fbevents") !== -1) {
                            pluginIndicators[\'Facebook for WooCommerce\'] = true;
                        }
                        if (src.indexOf("klaviyo") !== -1) {
                            pluginIndicators[\'Klaviyo\'] = true;
                        }
                        if (src.indexOf("gtm4wp") !== -1) {
                            pluginIndicators[\'GTM4WP\'] = true;
                        }
                        if (src.indexOf("analytify") !== -1) {
                            pluginIndicators[\'WP Analytify\'] = true;
                        }
                        if (src.indexOf("monsterinsights") !== -1) {
                            pluginIndicators[\'Google Analytics for WordPress\'] = true;
                        }
                    }
                }
            } catch (e) {
                addLog("Plugin detection error: " + e.message, "error");
            }
            
            // Check for plugin-specific DOM elements
            if (document.querySelector(\'.klaviyo-form-wrapper, .klaviyo-newsletter-form\')) {
                pluginIndicators[\'Klaviyo\'] = true;
            }
            
            // Display plugin status
            for (var plugin in pluginIndicators) {
                if (pluginIndicators[plugin]) {
                    addLog("✓ " + plugin + " - ACTIVE (scripts detected)", "warning");
                } else {
                    addLog("✗ " + plugin + " - Inactive or not detected", "info");
                }
            }
            
            addLog("=== DEBUGGING SUGGESTIONS ===", "info");
            addLog("1. If Facebook for WooCommerce is active, try disabling pixel tracking", "info");
            addLog("2. If Klaviyo scripts are found, check theme files for hardcoded scripts", "info");
            addLog("3. If GTM scripts are found, check for active GTM plugins", "info");
            addLog("4. Check browser console for JavaScript errors", "info");
            addLog("5. Use clean template to isolate form from marketing scripts", "info");
            
            // Add manual test button
            var testButton = document.createElement("div");
            testButton.style.marginTop = "10px";
            testButton.innerHTML = "<button onclick=\"testGravityForms()\" style=\"background: #007cba; color: white; border: none; padding: 8px 12px; border-radius: 3px; cursor: pointer; font-size: 11px;\">Test Gravity Forms</button>";
            content.appendChild(testButton);
            
            // Test function
            window.testGravityForms = function() {
                addLog("Testing Gravity Forms...", "info");
                if (typeof window.gform !== "undefined") {
                    addLog("✓ gform object available", "success");
                    if (typeof window.gform.addAction === "function") {
                        addLog("✓ gform.addAction available", "success");
                    } else {
                        addLog("✗ gform.addAction not available", "error");
                    }
                } else {
                    addLog("✗ gform object not available", "error");
                }
                
                if (form) {
                    addLog("Form ID: " + form.id, "info");
                    var inputs = form.querySelectorAll("input, select, textarea");
                    addLog("Total form inputs: " + inputs.length, "info");
                }
            };
            
        })();
        </script>';
    }
}

// Initialize the diagnostic plugin
TwinTack_Gravity_Forms_Diagnostic::get_instance();