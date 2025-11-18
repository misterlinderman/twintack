<?php
/**
 * Klaviyo API Proxy
 * 
 * This file handles server-side requests to Klaviyo API to avoid CORS issues.
 * 
 * @package twintack2025
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

/**
 * Register the AJAX action to handle Klaviyo newsletter subscriptions
 */
function twintack_register_klaviyo_ajax_handler() {
    add_action('wp_ajax_klaviyo_subscribe', 'twintack_klaviyo_subscribe_handler');
    add_action('wp_ajax_nopriv_klaviyo_subscribe', 'twintack_klaviyo_subscribe_handler');
}
add_action('init', 'twintack_register_klaviyo_ajax_handler');

/**
 * Handle Klaviyo subscribe requests via AJAX
 */
function twintack_klaviyo_subscribe_handler() {
    // Debug: Log that the handler was called
    error_log('Klaviyo subscribe handler called');
    
    // Check for nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'klaviyo_subscribe_nonce')) {
        error_log('Klaviyo nonce verification failed');
        wp_send_json_error(array(
            'message' => 'Security check failed.'
        ));
        exit;
    }
    
    // Get the email from the request
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    error_log('Klaviyo subscribe email: ' . $email);
    
    // Validate email
    if (empty($email) || !is_email($email)) {
        error_log('Klaviyo invalid email: ' . $email);
        wp_send_json_error(array(
            'message' => 'Please provide a valid email address.'
        ));
        exit;
    }
    
    // Get Klaviyo keys
    $klaviyo_data = twintack_get_klaviyo_data();
    $public_key = $klaviyo_data['publicApiKey'];
    $private_key = $klaviyo_data['privateApiKey'];
    $list_id = $klaviyo_data['listId'];
    
    // Make sure we have the required keys
    if (empty($private_key) || empty($list_id)) {
        error_log('Klaviyo API keys not configured');
        wp_send_json_error(array(
            'message' => 'Klaviyo API keys are not configured.'
        ));
        exit;
    }
    
    // Log request details
    error_log('Klaviyo API request - List ID: ' . $list_id . ', Email: ' . $email);
    
    // First, let's try the simplest approach using Klaviyo's List API to add a member
    $list_api_url = 'https://a.klaviyo.com/api/lists/' . $list_id . '/subscribe';
    
    // Create simple subscribe request (v2 API which is still supported)
    $subscribe_data = array(
        'api_key' => $private_key,
        'profiles' => array(
            array(
                'email' => $email,
                '$source' => 'TwinTack Website Newsletter Form'
            )
        )
    );
    
    error_log('Klaviyo subscribe request body: ' . json_encode($subscribe_data));
    
    // Subscribe directly using v2 API
    $response = wp_remote_post($list_api_url, array(
        'method' => 'POST',
        'timeout' => 45,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking' => true,
        'headers' => array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ),
        'body' => json_encode($subscribe_data),
        'cookies' => array()
    ));
    
    // Check for errors in subscription
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log('Klaviyo subscribe API error: ' . $error_message);
        wp_send_json_error(array(
            'message' => 'Error: ' . $error_message
        ));
        exit;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    error_log('Klaviyo API response code: ' . $response_code);
    error_log('Klaviyo API response body: ' . $response_body);
    
    // Success - v2 API should return success
    if ($response_code == 200) {
        wp_send_json_success(array(
            'message' => 'Thank you for subscribing!'
        ));
        exit;
    } else {
        // If v2 API fails, log the error
        error_log('Klaviyo v2 API failed with code ' . $response_code . ': ' . $response_body);
        
        // Fall back to direct list subscription with basic auth
        $direct_sub_url = 'https://a.klaviyo.com/api/v2/list/' . $list_id . '/subscribe';
        
        $direct_data = array(
            'api_key' => $public_key,
            'profiles' => array(
                array(
                    'email' => $email
                )
            )
        );
        
        error_log('Attempting direct v2 API subscription: ' . json_encode($direct_data));
        
        $direct_response = wp_remote_post($direct_sub_url, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'body' => json_encode($direct_data),
            'cookies' => array()
        ));
        
        if (is_wp_error($direct_response)) {
            $error_message = $direct_response->get_error_message();
            error_log('Klaviyo direct API error: ' . $error_message);
            wp_send_json_error(array(
                'message' => 'Error: ' . $error_message
            ));
            exit;
        }
        
        $direct_code = wp_remote_retrieve_response_code($direct_response);
        $direct_body = wp_remote_retrieve_body($direct_response);
        
        error_log('Klaviyo direct API response code: ' . $direct_code);
        error_log('Klaviyo direct API response body: ' . $direct_body);
        
        if ($direct_code >= 200 && $direct_code < 300) {
            wp_send_json_success(array(
                'message' => 'Thank you for subscribing!'
            ));
        } else {
            // Extract error message if possible
            $error = json_decode($direct_body, true);
            $error_message = "Error subscribing to newsletter. Please try again.";
            
            if (isset($error['message'])) {
                $error_message = $error['message'];
            }
            
            error_log('Klaviyo subscription failed: ' . $error_message);
            
            wp_send_json_error(array(
                'message' => $error_message
            ));
        }
    }
    
    exit;
} 