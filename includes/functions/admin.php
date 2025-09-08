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
        'distributions' => 'Profit Distributions',
        'reports' => 'Reports',
        'settings' => 'Settings'
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
    case 'reports': svntex2_admin_reports(); break;
    case 'settings': svntex2_admin_settings(); break;
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
    $suspense_table = $wpdb->prefix.'svntex_pb_suspense';
    $inputs_table = $wpdb->prefix.'svntex_profit_inputs';
    $mode = get_option('svntex2_pb_distribution_mode','normalized');
    $auto_release = (int) get_option('svntex2_pb_auto_release',0);
    // Handle save of profit inputs
    if( isset($_POST['svntex2_save_profit_inputs']) && check_admin_referer('svntex2_save_profit_inputs','svntex2_profit_inputs_nonce') ){
        $month_year = sanitize_text_field($_POST['month_year']);
        if( preg_match('/^\d{4}-\d{2}$/',$month_year) ){
            $data = [
                'revenue' => (float)$_POST['revenue'],
                'remaining_wallet' => (float)$_POST['remaining_wallet'],
                'cogs' => (float)$_POST['cogs'],
                'maintenance_percent' => (float)$_POST['maintenance_percent'],
                'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
            ];
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $inputs_table WHERE month_year=%s", $month_year));
            if($exists){
                $data['updated_at'] = current_time('mysql');
                $wpdb->update($inputs_table,$data,['month_year'=>$month_year]);
                echo '<div class="updated notice"><p>Profit inputs updated.</p></div>';
            } else {
                $data['month_year'] = $month_year; $wpdb->insert($inputs_table,$data); echo '<div class="updated notice"><p>Profit inputs saved.</p></div>';
            }
        }
    }
    // Release suspense action
    if( isset($_POST['svntex2_release_suspense']) && check_admin_referer('svntex2_release_suspense','svntex2_release_suspense_nonce') ){
        $sid = (int) $_POST['suspense_id'];
        $row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $suspense_table WHERE id=%d AND status='held'", $sid) );
        if($row){
            // Only release if user now active or lifetime
            $ustatus = get_user_meta($row->user_id,'_svntex2_pb_status', true );
            if( in_array($ustatus,['active','lifetime'], true) ){
                svntex2_wallet_add_transaction( $row->user_id, 'profit_bonus', (float)$row->amount, 'pb_release:'.$row->month_year, [ 'original_month'=>$row->month_year,'released'=>current_time('mysql'),'reason'=>$row->reason ], 'income' );
                $wpdb->update($suspense_table,[ 'status'=>'released','released_at'=>current_time('mysql') ],['id'=>$row->id]);
                echo '<div class="updated notice"><p>Suspense payout released.</p></div>';
            } else {
                echo '<div class="error notice"><p>User not active/lifetime; cannot release.</p></div>';
            }
        }
    }
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
    echo '<p><strong>Current Mode:</strong> '.esc_html( ucfirst(str_replace('_',' ', $mode)) ).' | <strong>Auto-Release:</strong> '.($auto_release? 'Enabled':'Disabled').'</p>';
    echo '<form method="post" class="svntex2-inline-form">'.wp_nonce_field('svntex2_new_distribution','svntex2_distribution_nonce', true, false).'<input type="text" name="month_year" placeholder="YYYY-MM" pattern="^[0-9]{4}-[0-9]{2}$" required /> <input type="number" step="0.01" name="company_profit" placeholder="Company Profit" required /> <button class="button button-primary" name="svntex2_new_distribution" value="1">Add</button></form>';
    echo '<table class="widefat striped svntex2-table"><thead><tr><th>ID</th><th>Month</th><th>Company Profit</th><th>Eligible Members</th><th>Profit Value</th><th>Created</th></tr></thead><tbody>';
    if($rows){
        foreach($rows as $r){
            printf('<tr><td>%d</td><td>%s</td><td>%s</td><td>%d</td><td>%s</td><td>%s</td></tr>', $r->id, esc_html($r->month_year), number_format_i18n($r->company_profit,2), $r->eligible_members, number_format_i18n($r->profit_value,4), esc_html($r->created_at));
        }
    } else { echo '<tr><td colspan="6">No distribution records.</td></tr>'; }
    echo '</tbody></table>';

    // Profit Inputs Management
    echo '<h3>Monthly Profit Inputs</h3>';
    $edit_month = isset($_GET['edit_inputs']) ? sanitize_text_field($_GET['edit_inputs']) : date('Y-m');
    $current_inputs = null;
    if( preg_match('/^\d{4}-\d{2}$/',$edit_month) ){
        $current_inputs = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $inputs_table WHERE month_year=%s", $edit_month) );
    }
    echo '<form method="post" class="svntex2-inline-form" style="flex-wrap:wrap;gap:10px">'.wp_nonce_field('svntex2_save_profit_inputs','svntex2_profit_inputs_nonce',true,false);
    echo '<input type="text" name="month_year" value="'.esc_attr($edit_month).'" placeholder="YYYY-MM" pattern="^[0-9]{4}-[0-9]{2}$" required />';
    printf('<input type="number" step="0.01" name="revenue" value="%s" placeholder="Revenue" />', esc_attr($current_inputs->revenue ?? '')); 
    printf('<input type="number" step="0.01" name="remaining_wallet" value="%s" placeholder="Remaining Wallet" />', esc_attr($current_inputs->remaining_wallet ?? ''));
    printf('<input type="number" step="0.01" name="cogs" value="%s" placeholder="COGS" />', esc_attr($current_inputs->cogs ?? ''));
    printf('<input type="number" step="0.0001" name="maintenance_percent" value="%s" placeholder="Maint % (0-1)" />', esc_attr($current_inputs->maintenance_percent ?? ''));
    echo '<textarea name="notes" placeholder="Notes" style="min-width:200px" rows="1">'.esc_textarea($current_inputs->notes ?? '').'</textarea>';
    echo '<button class="button button-primary" name="svntex2_save_profit_inputs" value="1">Save Inputs</button>';
    echo '</form>';
    $recent_inputs = $wpdb->get_results("SELECT * FROM $inputs_table ORDER BY month_year DESC LIMIT 24");
    echo '<table class="widefat striped svntex2-table"><thead><tr><th>Month</th><th>Revenue</th><th>Remaining Wallet</th><th>COGS</th><th>Maint %</th><th>Notes</th><th>Updated</th></tr></thead><tbody>';
    if($recent_inputs){
        foreach($recent_inputs as $ri){
            printf('<tr><td><a href="%s">%s</a></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>', esc_url( add_query_arg(['page'=>'svntex2-admin','tab'=>'distributions','edit_inputs'=>$ri->month_year], admin_url('admin.php')) ), esc_html($ri->month_year), number_format_i18n($ri->revenue,2), number_format_i18n($ri->remaining_wallet,2), number_format_i18n($ri->cogs,2), esc_html($ri->maintenance_percent), esc_html(wp_trim_words($ri->notes,8,'…')), esc_html($ri->updated_at ?: $ri->created_at));
        }
    } else { echo '<tr><td colspan="7">No profit inputs yet.</td></tr>'; }
    echo '</tbody></table>';

    // Suspense queue
    $srows = $wpdb->get_results("SELECT * FROM $suspense_table WHERE status='held' ORDER BY created_at DESC LIMIT 100");
    echo '<h3>Suspense (Held PB Payouts)</h3>';
    echo '<table class="widefat striped svntex2-table"><thead><tr><th>ID</th><th>User</th><th>Month</th><th>Amount</th><th>Status</th><th>Reason</th><th>Created</th><th>Action</th></tr></thead><tbody>';
    if($srows){
        foreach($srows as $r){
            echo '<tr>';
            printf('<td>%d</td><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td>', $r->id,$r->user_id, esc_html($r->month_year), number_format_i18n($r->amount,2), esc_html($r->status), esc_html($r->reason), esc_html($r->created_at));
            echo '<td><form method="post" style="display:inline">'.wp_nonce_field('svntex2_release_suspense','svntex2_release_suspense_nonce',true,false).'<input type="hidden" name="suspense_id" value="'.(int)$r->id.'" /><button class="button button-small" name="svntex2_release_suspense" value="1">Release</button></form></td>';
            echo '</tr>';
        }
    } else { echo '<tr><td colspan="8">No held payouts.</td></tr>'; }
    echo '</tbody></table>';
}

function svntex2_admin_reports(){
    global $wpdb; $pref = $wpdb->prefix;
    $wallet_table = $pref.'svntex_wallet_transactions';
    $withdraw_table = $pref.'svntex_withdrawals';
    $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : '';
    $to = isset($_GET['to']) ? sanitize_text_field($_GET['to']) : '';
    $export = isset($_GET['export']) && $_GET['export']==='csv';
    $where = '1=1';
    $params = [];
    if ( $from && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from) ) { $where .= ' AND t.created_at >= %s'; $params[] = $from.' 00:00:00'; }
    if ( $to && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to) ) { $where .= ' AND t.created_at <= %s'; $params[] = $to.' 23:59:59'; }
    $sql = "SELECT t.id,t.user_id,t.type,t.category,t.amount,t.balance_after,t.reference_id,t.meta,t.created_at FROM $wallet_table t WHERE $where ORDER BY t.id DESC LIMIT 500";
    $rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
    // Fees summary
    $fee_where = 'status=\'approved\''; $fee_params = [];
    if ( $from ) { $fee_where .= ' AND processed_at >= %s'; $fee_params[] = $from.' 00:00:00'; }
    if ( $to ) { $fee_where .= ' AND processed_at <= %s'; $fee_params[] = $to.' 23:59:59'; }
    $fee_sql = "SELECT SUM(tds_amount) tds, SUM(amc_amount) amc, SUM(net_amount) net, SUM(amount) gross FROM $withdraw_table WHERE $fee_where";
    $fees = $fee_params ? $wpdb->get_row( $wpdb->prepare($fee_sql,$fee_params) ) : $wpdb->get_row($fee_sql);
    if ( $export ) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=svntex2-report-'.date('Ymd-His').'.csv');
        $out = fopen('php://output','w');
        fputcsv($out,['ID','User','Type','Category','Amount','Balance After','Reference','Meta','Created']);
        if($rows){
            foreach($rows as $r){ fputcsv($out, [ $r->id,$r->user_id,$r->type,$r->category,$r->amount,$r->balance_after,$r->reference_id,$r->meta,$r->created_at ] ); }
        }
        fputcsv($out,[]);
        fputcsv($out,['Withdraw Fees Summary']);
        fputcsv($out,['Gross','TDS','AMC','Net']);
        fputcsv($out,[ $fees->gross ?: 0, $fees->tds ?: 0, $fees->amc ?: 0, $fees->net ?: 0 ]);
        fclose($out); exit;
    }
    echo '<h2>Reports</h2>';
    echo '<form method="get" class="svntex2-inline-form" style="margin-top:8px">';
    echo '<input type="hidden" name="page" value="svntex2-admin" />';
    echo '<input type="hidden" name="tab" value="reports" />';
    printf('<input type="date" name="from" value="%s" /> ', esc_attr($from));
    printf('<input type="date" name="to" value="%s" /> ', esc_attr($to));
    echo '<button class="button">Filter</button> ';
    if ( $rows ) {
        echo '<a class="button" href="'.esc_url( add_query_arg( ['page'=>'svntex2-admin','tab'=>'reports','from'=>$from,'to'=>$to,'export'=>'csv'], admin_url('admin.php') ) ).'">Export CSV</a>';
    }
    echo '</form>';
    echo '<h3>Wallet Transactions (latest 500)</h3>';
    echo '<table class="widefat striped svntex2-table"><thead><tr><th>ID</th><th>User</th><th>Type</th><th>Category</th><th>Amount</th><th>Balance After</th><th>Reference</th><th>Meta</th><th>Created</th></tr></thead><tbody>';
    if($rows){
        foreach($rows as $r){
            echo '<tr>'; printf('<td>%d</td><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><code style="font-size:10px">%s</code></td><td>%s</td>', $r->id,$r->user_id,esc_html($r->type),esc_html($r->category),number_format_i18n($r->amount,2),number_format_i18n($r->balance_after,2),esc_html($r->reference_id ?: '-'), esc_html($r->meta), esc_html($r->created_at) ); echo '</tr>';
        }
    } else { echo '<tr><td colspan="9">No transactions.</td></tr>'; }
    echo '</tbody></table>';
    echo '<h3>Withdrawal Fees Summary</h3>';
    echo '<table class="widefat striped svntex2-table"><thead><tr><th>Gross</th><th>TDS (2%)</th><th>AMC (8%)</th><th>Net</th></tr></thead><tbody>';
    printf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>', number_format_i18n($fees->gross ?: 0,2), number_format_i18n($fees->tds ?: 0,2), number_format_i18n($fees->amc ?: 0,2), number_format_i18n($fees->net ?: 0,2));
    echo '</tbody></table>';
}

/** Settings tab */
function svntex2_admin_settings(){
    if( isset($_POST['svntex2_save_settings']) && check_admin_referer('svntex2_save_settings','svntex2_settings_nonce') ){
        $mode = sanitize_key($_POST['pb_distribution_mode'] ?? 'normalized');
        if( ! in_array($mode,['normalized','direct_formula'], true) ) $mode='normalized';
        update_option('svntex2_pb_distribution_mode',$mode);
        $auto = isset($_POST['pb_auto_release']) ? 1 : 0; update_option('svntex2_pb_auto_release',$auto);
        $manual_profit = isset($_POST['manual_profit_value']) ? floatval($_POST['manual_profit_value']) : 0.0; update_option('svntex2_manual_profit_value',$manual_profit);
        echo '<div class="updated notice"><p>Settings saved.</p></div>';
    }
    $mode = get_option('svntex2_pb_distribution_mode','normalized');
    $auto = (int)get_option('svntex2_pb_auto_release',0);
    $manual_profit = (float) get_option('svntex2_manual_profit_value',0);
    echo '<h2>Settings</h2>';
    echo '<form method="post" class="svntex2-inline-form" style="flex-direction:column;align-items:flex-start;gap:14px;max-width:520px">';
    wp_nonce_field('svntex2_save_settings','svntex2_settings_nonce');
    echo '<label><strong>PB Distribution Mode</strong><br/>';
    echo '<select name="pb_distribution_mode">';
    foreach(['normalized'=>'Normalized Share (Σ slab %)','direct_formula'=>'Direct Formula (profit_value * slab %)'] as $k=>$label){
        printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($mode,$k,false), esc_html($label));
    }
    echo '</select></label>';
    echo '<label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="pb_auto_release" value="1" '.checked($auto,1,false).'/> Auto-release suspense when user becomes Active</label>';
    echo '<label><strong>Manual Profit Override (current month)</strong><br/><input type="number" step="0.01" name="manual_profit_value" value="'.esc_attr($manual_profit).'" placeholder="0.00"/> <small>0 = disabled (use computed profit)</small></label>';
    echo '<button class="button button-primary" name="svntex2_save_settings" value="1">Save Settings</button>';
    echo '</form>';
    echo '<h3>Info</h3><p>Normalized mode divides company profit proportionally by total slab % values. Direct Formula mode uses average profit value times each user slab %.</p>';
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
