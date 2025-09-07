<?php
/** Admin UI placeholder */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function(){
    add_menu_page('SVNTeX 2.0','SVNTeX 2.0','manage_options','svntex2-admin','svntex2_admin_root','dashicons-database',56);
});

function svntex2_admin_root(){
    echo '<div class="wrap"><h1>SVNTeX 2.0 Admin</h1><p>Module dashboards coming soon (referrals, KYC, withdrawals, distributions).</p></div>';
}

?>
