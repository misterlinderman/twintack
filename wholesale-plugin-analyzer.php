<?php
/**
 * Plugin Name: TwinTack Wholesale Plugin Analyzer
 * Description: Analyzes wholesale plugin hooks to identify order duplication conflicts
 * Version: 1.0.0
 * Author: TwinTack
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TwinTack_Wholesale_Plugin_Analyzer {
    
    private $wholesale_plugins = array(
        'woocommerce-wholesale-lead-capture',
        'woocommerce-wholesale-order-form', 
        'woocommerce-wholesale-payments',
        'woocommerce-wholesale-prices',
        'woocommerce-wholesale-prices-premium'
    );
    
    private $order_hooks = array(
        'woocommerce_checkout_order_processed',
        'woocommerce_new_order',
        'woocommerce_payment_complete',
        'woocommerce_order_status_changed',
        'woocommerce_create_order',
        'woocommerce_checkout_create_order',
        'woocommerce_update_order',
        'woocommerce_new_order_item',
        'woocommerce_update_order_item',
        'woocommerce_rest_insert_shop_order_object'
    );
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            'Wholesale Plugin Analyzer',
            'Wholesale Plugin Analyzer',
            'manage_options',
            'twintack-wholesale-analyzer',
            array($this, 'admin_page')
        );
    }
    
    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        echo '<div class="wrap">';
        echo '<h1>Wholesale Plugin Hook Analyzer</h1>';
        
        echo '<div class="postbox">';
        echo '<div class="postbox-header"><h2 class="hndle">🔍 Wholesale Plugin Status</h2></div>';
        echo '<div class="inside">';
        
        $this->display_plugin_status();
        
        echo '</div></div>';
        
        echo '<div class="postbox">';
        echo '<div class="postbox-header"><h2 class="hndle">🎯 Order Creation Hook Analysis</h2></div>';
        echo '<div class="inside">';
        
        $this->analyze_order_hooks();
        
        echo '</div></div>';
        
        echo '<div class="postbox">';
        echo '<div class="postbox-header"><h2 class="hndle">⚠️ Potential Conflicts</h2></div>';
        echo '<div class="inside">';
        
        $this->identify_conflicts();
        
        echo '</div></div>';
        
        echo '<div class="postbox">';
        echo '<div class="postbox-header"><h2 class="hndle">🛠️ Recommended Actions</h2></div>';
        echo '<div class="inside">';
        
        $this->recommend_actions();
        
        echo '</div></div>';
        
        echo '</div>';
    }
    
    private function display_plugin_status() {
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>Plugin</th><th>Status</th><th>Version</th><th>Active Hooks</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($this->wholesale_plugins as $plugin_slug) {
            $plugin_file = $plugin_slug . '/' . $plugin_slug . '.php';
            $is_active = is_plugin_active($plugin_file);
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($plugin_data['Name'] ?? $plugin_slug) . '</strong></td>';
            echo '<td>';
            if ($is_active) {
                echo '<span style="color: green;">✅ Active</span>';
            } else {
                echo '<span style="color: red;">❌ Inactive</span>';
            }
            echo '</td>';
            echo '<td>' . esc_html($plugin_data['Version'] ?? 'Unknown') . '</td>';
            echo '<td>' . $this->count_plugin_hooks($plugin_slug) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    private function count_plugin_hooks($plugin_slug) {
        global $wp_filter;
        $count = 0;
        
        foreach ($this->order_hooks as $hook_name) {
            if (isset($wp_filter[$hook_name])) {
                foreach ($wp_filter[$hook_name]->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $callback_id => $callback_data) {
                        $callback_info = $this->get_callback_info($callback_data['function']);
                        if (strpos($callback_info, $this->get_plugin_class_prefix($plugin_slug)) !== false) {
                            $count++;
                        }
                    }
                }
            }
        }
        
        return $count;
    }
    
    private function get_plugin_class_prefix($plugin_slug) {
        $prefixes = array(
            'woocommerce-wholesale-lead-capture' => 'WWLC',
            'woocommerce-wholesale-order-form' => 'WWOF',
            'woocommerce-wholesale-payments' => 'WWP',
            'woocommerce-wholesale-prices' => 'WWP',
            'woocommerce-wholesale-prices-premium' => 'WWPP'
        );
        
        return $prefixes[$plugin_slug] ?? '';
    }
    
    private function analyze_order_hooks() {
        global $wp_filter;
        
        echo '<h3>Hook Registration Analysis</h3>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>Hook Name</th><th>Total Callbacks</th><th>Wholesale Callbacks</th><th>Details</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($this->order_hooks as $hook_name) {
            $total_callbacks = 0;
            $wholesale_callbacks = array();
            
            if (isset($wp_filter[$hook_name])) {
                foreach ($wp_filter[$hook_name]->callbacks as $priority => $callbacks) {
                    $total_callbacks += count($callbacks);
                    
                    foreach ($callbacks as $callback_id => $callback_data) {
                        $callback_info = $this->get_callback_info($callback_data['function']);
                        
                        // Check if this is a wholesale plugin callback
                        foreach ($this->wholesale_plugins as $plugin_slug) {
                            $prefix = $this->get_plugin_class_prefix($plugin_slug);
                            if ($prefix && strpos($callback_info, $prefix) !== false) {
                                $wholesale_callbacks[] = array(
                                    'plugin' => $plugin_slug,
                                    'callback' => $callback_info,
                                    'priority' => $priority
                                );
                            }
                        }
                    }
                }
            }
            
            echo '<tr>';
            echo '<td><code>' . esc_html($hook_name) . '</code></td>';
            echo '<td>' . $total_callbacks . '</td>';
            echo '<td>' . count($wholesale_callbacks) . '</td>';
            echo '<td>';
            
            if (!empty($wholesale_callbacks)) {
                echo '<details><summary>View Details</summary>';
                echo '<ul style="margin: 5px 0; font-size: 12px;">';
                foreach ($wholesale_callbacks as $callback) {
                    echo '<li><strong>' . esc_html($callback['plugin']) . '</strong> (Priority: ' . $callback['priority'] . ') - <code>' . esc_html($callback['callback']) . '</code></li>';
                }
                echo '</ul></details>';
            } else {
                echo '<em>No wholesale plugin hooks</em>';
            }
            
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    private function identify_conflicts() {
        global $wp_filter;
        
        echo '<h3>Potential Order Duplication Conflicts</h3>';
        
        $conflicts = array();
        
        foreach ($this->order_hooks as $hook_name) {
            if (isset($wp_filter[$hook_name])) {
                $wholesale_callbacks = array();
                
                foreach ($wp_filter[$hook_name]->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $callback_id => $callback_data) {
                        $callback_info = $this->get_callback_info($callback_data['function']);
                        
                        foreach ($this->wholesale_plugins as $plugin_slug) {
                            $prefix = $this->get_plugin_class_prefix($plugin_slug);
                            if ($prefix && strpos($callback_info, $prefix) !== false) {
                                $wholesale_callbacks[] = array(
                                    'plugin' => $plugin_slug,
                                    'callback' => $callback_info,
                                    'priority' => $priority
                                );
                            }
                        }
                    }
                }
                
                if (count($wholesale_callbacks) > 1) {
                    $conflicts[$hook_name] = $wholesale_callbacks;
                }
            }
        }
        
        if (empty($conflicts)) {
            echo '<div class="notice notice-success inline">';
            echo '<p><strong>✅ No obvious conflicts detected</strong></p>';
            echo '<p>No order creation hooks have multiple wholesale plugin callbacks.</p>';
            echo '</div>';
        } else {
            echo '<div class="notice notice-warning inline">';
            echo '<p><strong>⚠️ Potential conflicts detected</strong></p>';
            echo '<p>The following hooks have multiple wholesale plugin callbacks that could cause order duplication:</p>';
            echo '</div>';
            
            foreach ($conflicts as $hook_name => $callbacks) {
                echo '<h4><code>' . esc_html($hook_name) . '</code> - ' . count($callbacks) . ' wholesale callbacks</h4>';
                echo '<ul>';
                foreach ($callbacks as $callback) {
                    echo '<li><strong>' . esc_html($callback['plugin']) . '</strong> (Priority: ' . $callback['priority'] . ') - <code>' . esc_html($callback['callback']) . '</code></li>';
                }
                echo '</ul>';
            }
        }
        
        // Check for duplicate order creation patterns
        echo '<h3>Duplicate Order Creation Pattern Analysis</h3>';
        $this->analyze_duplicate_patterns();
    }
    
    private function analyze_duplicate_patterns() {
        // Look for hooks that might create orders multiple times
        $suspicious_hooks = array(
            'woocommerce_checkout_order_processed' => 'This hook fires when an order is processed - multiple plugins hooking here could create duplicates',
            'woocommerce_new_order' => 'This hook fires when a new order is created - multiple callbacks could trigger multiple order creations',
            'woocommerce_payment_complete' => 'This hook fires when payment is complete - could trigger additional order processing',
            'woocommerce_update_order' => 'This hook fires when an order is updated - could trigger additional order creation'
        );
        
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>Hook</th><th>Risk Level</th><th>Description</th><th>Wholesale Callbacks</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($suspicious_hooks as $hook_name => $description) {
            $wholesale_count = $this->count_wholesale_callbacks($hook_name);
            $risk_level = $wholesale_count > 1 ? 'HIGH' : ($wholesale_count == 1 ? 'MEDIUM' : 'LOW');
            $risk_color = $wholesale_count > 1 ? 'red' : ($wholesale_count == 1 ? 'orange' : 'green');
            
            echo '<tr>';
            echo '<td><code>' . esc_html($hook_name) . '</code></td>';
            echo '<td><span style="color: ' . $risk_color . '; font-weight: bold;">' . $risk_level . '</span></td>';
            echo '<td>' . esc_html($description) . '</td>';
            echo '<td>' . $wholesale_count . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    private function count_wholesale_callbacks($hook_name) {
        global $wp_filter;
        $count = 0;
        
        if (isset($wp_filter[$hook_name])) {
            foreach ($wp_filter[$hook_name]->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback_id => $callback_data) {
                    $callback_info = $this->get_callback_info($callback_data['function']);
                    
                    foreach ($this->wholesale_plugins as $plugin_slug) {
                        $prefix = $this->get_plugin_class_prefix($plugin_slug);
                        if ($prefix && strpos($callback_info, $prefix) !== false) {
                            $count++;
                            break; // Count each plugin only once per hook
                        }
                    }
                }
            }
        }
        
        return $count;
    }
    
    private function recommend_actions() {
        echo '<h3>Immediate Actions</h3>';
        echo '<ol>';
        echo '<li><strong>Use the Order Creation Debugger:</strong> Activate debugging and place a test order to see exactly which hooks are firing</li>';
        echo '<li><strong>Test with bypass active:</strong> Use the Product Save Bypass tool to temporarily disable wholesale plugin hooks</li>';
        echo '<li><strong>Deactivate plugins one by one:</strong> Temporarily deactivate wholesale plugins to identify the specific culprit</li>';
        echo '</ol>';
        
        echo '<h3>Long-term Solutions</h3>';
        echo '<ol>';
        echo '<li><strong>Update wholesale plugins:</strong> Check for updates to wholesale plugins that may fix WooCommerce 10 compatibility</li>';
        echo '<li><strong>Implement hook priority management:</strong> Adjust hook priorities to prevent conflicts</li>';
        echo '<li><strong>Create custom order creation logic:</strong> Implement a single, controlled order creation process</li>';
        echo '</ol>';
        
        echo '<h3>Quick Test Commands</h3>';
        echo '<div style="background: #f1f1f1; padding: 10px; border-radius: 4px; font-family: monospace;">';
        echo '<p><strong>1. Activate Order Debugging:</strong></p>';
        echo '<p><a href="' . admin_url('tools.php?page=twintack-order-debug&twintack_debug=1') . '" class="button button-primary">Activate Order Debugging</a></p>';
        
        echo '<p><strong>2. Activate Product Save Bypass:</strong></p>';
        echo '<p><a href="' . admin_url('tools.php?page=twintack-bypass') . '" class="button button-secondary">Open Product Save Bypass</a></p>';
        
        echo '<p><strong>3. View Recent Orders:</strong></p>';
        echo '<p><a href="' . admin_url('edit.php?post_type=shop_order') . '" class="button button-secondary">View Orders</a></p>';
        echo '</div>';
    }
    
    private function get_callback_info($callback) {
        if (is_string($callback)) {
            return $callback;
        } elseif (is_array($callback)) {
            if (is_object($callback[0])) {
                return get_class($callback[0]) . '::' . $callback[1];
            } else {
                return $callback[0] . '::' . $callback[1];
            }
        } elseif (is_object($callback)) {
            return get_class($callback);
        }
        return 'Unknown callback';
    }
}

// Initialize the analyzer
new TwinTack_Wholesale_Plugin_Analyzer();

?>
