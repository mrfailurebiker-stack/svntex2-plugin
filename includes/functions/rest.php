<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function(){
    register_rest_route('svntex2/v1','/wallet/balance', [
        'methods' => 'GET',
        'permission_callback' => function(){ return is_user_logged_in(); },
        'callback' => function(){ return ['balance' => svntex2_wallet_get_balance(get_current_user_id())]; }
    ]);
});
?>
