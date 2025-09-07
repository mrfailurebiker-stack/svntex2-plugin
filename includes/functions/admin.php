<?php
/** SVNTeX 2.0 Admin Dashboard */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('admin_menu', function(){
    add_menu_page(
        'SVNTeX 2.0 Dashboard',
        'SVNTeX 2.0',
        'manage_options',
        'svntex2-admin',
        'svntex2_admin_root',
        'dashicons-chart-pie',
        56
    );
});

/**
 * Root admin page with tabbed interface.
 */
function svntex2_admin_root(){
    if ( ! current_user_can('manage_options') ) return;
    global $wpdb; $pref = $wpdb->prefix;
    $active = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
    $tabs = [
        'overview' => 'Overview',
        'referrals' => 'Referrals',
        'kyc' => 'KYC',
        'withdrawals' => 'Withdrawals',
        'distributions' => 'Profit Distributions'
    ];

    // Preload counts for widgets
    $counts = [
        'referrals_total' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$pref}svntex_referrals"),
        'referrals_qualified' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$pref}svntex_referrals WHERE qualified=1"),
        'kyc_pending' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$pref}svntex_kyc_submissions WHERE status='pending'"),
        'kyc_approved' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$pref}svntex_kyc_submissions WHERE status='approved'"),
        'withdraw_requested' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$pref}svntex_withdrawals WHERE status='requested'"),
        'withdraw_approved' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$pref}svntex_withdrawals WHERE status='approved'"),
        'distributions' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$pref}svntex_profit_distributions")
    ];

    echo '<div class="wrap svntex2-admin-wrap">';
    echo '<h1 class="wp-heading-inline">SVNTeX 2.0 Admin</h1>';
    echo '<hr class="wp-header-end" />';

    // Tabs
    echo '<nav class="nav-tab-wrapper">';
    foreach($tabs as $k=>$label){
        $class = 'nav-tab'.($active===$k ? ' nav-tab-active':'');
        printf('<a href="%s" class="%s">%s</a>', esc_url( admin_url('admin.php?page=svntex2-admin&tab='.$k) ), $class, esc_html($label));
    }
    echo '</nav>';

    echo '<div class="svntex2-tab-content">';
    switch($active){
        case 'referrals': svntex2_admin_referrals(); break;
        case 'kyc': svntex2_admin_kyc(); break;
        case 'withdrawals': svntex2_admin_withdrawals(); break;
        case 'distributions': svntex2_admin_distributions(); break;
        default: svntex2_admin_overview($counts); break;
    }
    echo '</div>';
    echo '</div>';
    svntex2_admin_inline_styles();
}

function svntex2_admin_overview($counts){
    echo '<h2 class="screen-reader-text">Overview</h2>';
    echo '<div class="svntex2-grid">';
    $cards = [
        [ 'title' => 'Total Referrals', 'value' => $counts['referrals_total'], 'desc' => 'All recorded referrals' ],
        [ 'title' => 'Qualified Referrals', 'value' => $counts['referrals_qualified'], 'desc' => 'Referrals that qualified' ],
        [ 'title' => 'KYC Pending', 'value' => $counts['kyc_pending'], 'desc' => 'Awaiting review' ],
        [ 'title' => 'KYC Approved', 'value' => $counts['kyc_approved'], 'desc' => 'Verified accounts' ],
        [ 'title' => 'Withdraw Requests', 'value' => $counts['withdraw_requested'], 'desc' => 'Open withdrawal requests' ],
        [ 'title' => 'Withdraw Approved', 'value' => $counts['withdraw_approved'], 'desc' => 'Completed payouts' ],
        [ 'title' => 'Profit Distributions', 'value' => $counts['distributions'], 'desc' => 'Recorded month runs' ],
    ];
    foreach($cards as $c){
        printf('<div class="svntex2-metric"><div class="metric-val">%s</div><div class="metric-title">%s</div><div class="metric-desc">%s</div></div>', esc_html($c['value']), esc_html($c['title']), esc_html($c['desc']));
    }
    echo '</div>';
    echo '<p>Use the tabs above to manage each module.</p>';
}

function svntex2_admin_referrals(){
    global $wpdb; $table = $wpdb->prefix.'svntex_referrals';
    $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT 100");
    echo '<h2>Referrals</h2>';
    echo '<table class="widefat striped svntex2-table"><thead><tr><th>ID</th><th>Referrer</th><th>Referee</th><th>Qualified</th><th>First Purchase</th><th>Created</th></tr></thead><tbody>';
    if($rows){
        foreach($rows as $r){
            printf('<tr><td>%d</td><td>%d</td><td>%d</td><td><span class="status-%s">%s</span></td><td>%s</td><td>%s</td></tr>', $r->id, $r->referrer_id, $r->referee_id, $r->qualified? 'ok':'pending', $r->qualified? 'Yes':'No', $r->first_purchase_amount? number_format_i18n($r->first_purchase_amount,2):'-', esc_html($r->created_at));
        }
    } else { echo '<tr><td colspan="6">No referrals found.</td></tr>'; }
    echo '</tbody></table>';
}

function svntex2_admin_kyc(){
    global $wpdb; $table = $wpdb->prefix.'svntex_kyc_submissions';
    if( isset($_POST['svntex2_kyc_action']) && check_admin_referer('svntex2_kyc_action','svntex2_kyc_nonce') ){
        $user_id = (int)$_POST['user_id'];
        $action = sanitize_key($_POST['svntex2_kyc_action']);
        if( in_array($action, ['approve','reject'], true) ){
            svntex2_kyc_set_status($user_id, $action === 'approve' ? 'approved' : 'rejected');
            echo '<div class="updated notice"><p>KYC status updated.</p></div>';
        }
    }
    $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY updated_at DESC, id DESC LIMIT 100");
    echo '<h2>KYC Submissions</h2>';
    echo '<table class="widefat striped svntex2-table"><thead><tr><th>User</th><th>Status</th><th>Bank</th><th>IFSC</th><th>UPI</th><th>Updated</th><th>Actions</th></tr></thead><tbody>';
    if($rows){
        foreach($rows as $r){
            $actions = '';
            if($r->status === 'pending'){
                $actions .= '<form method="post" style="display:inline">'.wp_nonce_field('svntex2_kyc_action','svntex2_kyc_nonce', true, false).'<input type="hidden" name="user_id" value="'.(int)$r->user_id.'" />';
                $actions .= '<button class="button button-small" name="svntex2_kyc_action" value="approve">Approve</button> ';
                $actions .= '<button class="button button-small" name="svntex2_kyc_action" value="reject">Reject</button></form>';
            } else {
                $actions = '<span class="status-badge status-'.$r->status.'">'.esc_html(ucfirst($r->status)).'</span>';
            }
            printf('<tr><td>%d</td><td><span class="status-badge status-%s">%s</span></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $r->user_id,
                esc_attr($r->status),
                esc_html(ucfirst($r->status)),
                esc_html($r->bank_name ?: '-'),
                esc_html($r->ifsc ?: '-'),
                esc_html($r->upi_id ?: '-'),
                esc_html($r->updated_at ?: $r->created_at),
                $actions
            );
        }
    } else { echo '<tr><td colspan="7">No submissions.</td></tr>'; }
    echo '</tbody></table>';
}

function svntex2_admin_withdrawals(){
    global $wpdb; $table = $wpdb->prefix.'svntex_withdrawals';
    if( isset($_POST['svntex2_withdraw_action']) && check_admin_referer('svntex2_withdraw_action','svntex2_withdraw_nonce') ){
        $wid = (int)$_POST['withdrawal_id'];
        $action = sanitize_key($_POST['svntex2_withdraw_action']);
        $note = sanitize_text_field($_POST['admin_note'] ?? '');
        if( in_array($action, ['approve','reject'], true) ){
            svntex2_withdraw_process($wid, $action === 'approve' ? 'approved':'rejected', $note);
            echo '<div class="updated notice"><p>Withdrawal updated.</p></div>';
        }
    }
    $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY requested_at DESC, id DESC LIMIT 100");
    echo '<h2>Withdrawals</h2>';
    echo '<table class="widefat striped svntex2-table"><thead><tr><th>ID</th><th>User</th><th>Amount</th><th>Status</th><th>Method</th><th>Destination</th><th>Requested</th><th>Processed</th><th>Action</th></tr></thead><tbody>';
    if($rows){
        foreach($rows as $r){
            $actions='';
            if($r->status==='requested'){
                $actions .= '<form method="post" style="display:inline">'.wp_nonce_field('svntex2_withdraw_action','svntex2_withdraw_nonce', true, false).'<input type="hidden" name="withdrawal_id" value="'.(int)$r->id.'" />';
                $actions .= '<input type="text" name="admin_note" placeholder="Note" class="small-text" /> ';
                $actions .= '<button class="button button-small" name="svntex2_withdraw_action" value="approve">Approve</button> ';
                $actions .= '<button class="button button-small" name="svntex2_withdraw_action" value="reject">Reject</button></form>';
            } else {
                $actions = '<span class="status-badge status-'.$r->status.'">'.esc_html(ucfirst($r->status)).'</span>';
            }
            printf('<tr><td>%d</td><td>%d</td><td>%s</td><td><span class="status-%s">%s</span></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $r->id,$r->user_id, number_format_i18n($r->amount,2), $r->status==='approved'?'ok':($r->status==='rejected'?'bad':'pending'), ucfirst($r->status), esc_html($r->method ?: '-'), esc_html($r->destination ?: '-'), esc_html($r->requested_at), esc_html($r->processed_at ?: '-'), $actions);
        }
    } else { echo '<tr><td colspan="9">No withdrawals.</td></tr>'; }
    echo '</tbody></table>';
}

function svntex2_admin_distributions(){
    global $wpdb; $table = $wpdb->prefix.'svntex_profit_distributions';
    if( isset($_POST['svntex2_new_distribution']) && check_admin_referer('svntex2_new_distribution','svntex2_distribution_nonce') ){
        $month_year = sanitize_text_field($_POST['month_year']);
        $company_profit = (float)$_POST['company_profit'];
        if($month_year && $company_profit>0){
            $wpdb->insert($table,[
                'month_year' => $month_year,
                'company_profit' => $company_profit,
                'eligible_members' => 0,
                'profit_value' => 0,
                'created_at' => current_time('mysql')
            ]);
            echo '<div class="updated notice"><p>Distribution record added (placeholder logic).</p></div>';
        }
    }
    $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT 60");
    echo '<h2>Profit Distributions</h2>';
    echo '<form method="post" class="svntex2-inline-form">'.wp_nonce_field('svntex2_new_distribution','svntex2_distribution_nonce', true, false).'<input type="text" name="month_year" placeholder="YYYY-MM" pattern="^[0-9]{4}-[0-9]{2}$" required /> <input type="number" step="0.01" name="company_profit" placeholder="Company Profit" required /> <button class="button button-primary" name="svntex2_new_distribution" value="1">Add</button></form>';
    echo '<table class="widefat striped svntex2-table"><thead><tr><th>ID</th><th>Month</th><th>Company Profit</th><th>Eligible Members</th><th>Profit Value</th><th>Created</th></tr></thead><tbody>';
    if($rows){
        foreach($rows as $r){
            printf('<tr><td>%d</td><td>%s</td><td>%s</td><td>%d</td><td>%s</td><td>%s</td></tr>', $r->id, esc_html($r->month_year), number_format_i18n($r->company_profit,2), $r->eligible_members, number_format_i18n($r->profit_value,4), esc_html($r->created_at));
        }
    } else { echo '<tr><td colspan="6">No distribution records.</td></tr>'; }
    echo '</tbody></table>';
}

/** Inline styles (kept minimal & scoped) */
function svntex2_admin_inline_styles(){
    ?>
    <style>
    .svntex2-admin-wrap .nav-tab-wrapper { margin-top:20px; }
    .svntex2-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:18px; margin:25px 0 10px; }
    .svntex2-metric { background:#fff; border:1px solid #e2e8f0; padding:16px 18px 14px; border-radius:12px; position:relative; overflow:hidden; }
    .svntex2-metric:before { content:""; position:absolute; inset:0; background:linear-gradient(135deg, rgba(236,72,153,0.08), rgba(219,39,119,0.05)); opacity:.9; pointer-events:none; }
    .svntex2-metric .metric-val { font-size:28px; font-weight:700; margin:0 0 4px; color:#db2777; }
    .svntex2-metric .metric-title { font-weight:600; font-size:14px; letter-spacing:.5px; text-transform:uppercase; color:#334155; }
    .svntex2-metric .metric-desc { font-size:12px; color:#64748b; }
    .svntex2-table .status-ok, .status-badge.status-approved { color:#16a34a; font-weight:600; }
    .svntex2-table .status-pending, .status-badge.status-pending { color:#d97706; font-weight:600; }
    .svntex2-table .status-bad, .status-badge.status-rejected { color:#dc2626; font-weight:600; }
    .status-badge { display:inline-block; padding:2px 8px; background:#f1f5f9; border-radius:16px; font-size:11px; }
    .svntex2-inline-form { margin:12px 0 18px; display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .svntex2-inline-form input[type=text], .svntex2-inline-form input[type=number] { padding:6px 8px; border-radius:6px; }
    .svntex2-admin-wrap .form-wrap { margin-top:18px; }
    .svntex2-tab-content h2 { margin-top:26px; }
    .svntex2-table td, .svntex2-table th { vertical-align:middle; }
    @media (prefers-color-scheme: dark){
      .svntex2-metric { background:#1e293b; border-color:#334155; }
      .svntex2-metric .metric-title { color:#e2e8f0; }
      .svntex2-metric .metric-desc { color:#94a3b8; }
      .status-badge { background:#334155; }
    }
    </style>
    <?php
}

?>
