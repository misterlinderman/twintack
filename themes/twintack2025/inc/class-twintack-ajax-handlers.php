<?php
class Twintack_Ajax_Handlers {
    public function __construct() {
        add_action('wp_ajax_update_cart', array($this, 'handle_cart_update'));
        add_action('wp_ajax_nopriv_update_cart', array($this, 'handle_cart_update'));
    }

    public function handle_cart_update() {
        // Cart update logic
        wp_send_json_success();
    }
}

new Twintack_Ajax_Handlers();
