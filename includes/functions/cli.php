<?php
/** WP-CLI commands for quick testing */
if (!defined('ABSPATH')) exit;

if (defined('WP_CLI') && WP_CLI) {
    /**
     * Test wallet transaction insertion.
     * ## OPTIONS
     * <user>
     * <amount>
     */
    WP_CLI::add_command('svntex2:wallet:add', function($args){
        list($user,$amt) = $args; $bal = svntex2_wallet_add_transaction((int)$user,'test_credit',(float)$amt,null,['via'=>'cli']);
        WP_CLI::success("New balance: $bal");
    });

    /** Show last 5 ledger entries. */
    WP_CLI::add_command('svntex2:wallet:last', function($args){
        global $wpdb; $u = (int)$args[0]; $t = $wpdb->prefix.'svntex_wallet_transactions';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d ORDER BY id DESC LIMIT 5", $u));
        foreach($rows as $r){ WP_CLI::log("#{$r->id} {$r->type} {$r->amount} => {$r->balance_after}"); }
    });

    /** Simulate referral link */
    WP_CLI::add_command('svntex2:referral:link', function($args){
        list($ref,$new) = $args; $ok = svntex2_referrals_link((int)$ref,(int)$new);
        WP_CLI::success($ok ? 'Linked' : 'Already exists');
    });
}

?>
