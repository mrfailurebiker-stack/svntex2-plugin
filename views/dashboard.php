
<?php
// Modernized SVNTeX Dashboard (Phase 1 UI polishing)
if (!defined('ABSPATH')) exit;
if (!is_user_logged_in()) {
    wp_safe_redirect( home_url('/customer-login/') );
    exit;
}

$current_user   = wp_get_current_user();
$customer_id    = get_user_meta($current_user->ID, 'customer_id', true);
$customer_id    = $customer_id ? $customer_id : ($current_user->user_login ?: '');
// Display normalized form: SVNXXXXXX (strip hyphen if present)
if ($customer_id && preg_match('/^SVN-(\d{6})$/i', $customer_id, $m)) {
    $customer_id_display = 'SVN' . $m[1];
} else {
    $customer_id_display = $customer_id;
}
$referral_count = get_user_meta($current_user->ID,'referral_count', true);
$referral_count = ($referral_count === '') ? 0 : intval($referral_count);
$wallet_balance = svntex2_wallet_get_balance($current_user->ID); // live ledger helper

$orders = [];
if ( function_exists('wc_get_orders') ) {
    $orders = wc_get_orders([
        'customer_id' => $current_user->ID,
        'limit' => 5,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);
}

$logout_url = esc_url( admin_url( 'admin-post.php?action=svntex2_logout' ) );
?>
<style>
/* Keep dashboard content width flexible. Theme (Astra) manages site chrome. */
.svntex-dashboard-wrapper { max-width: 1100px; margin: 0 auto; padding: 20px; box-sizing: border-box; }
.svntex-dash-top { display:flex; justify-content:flex-end; align-items:center; margin-bottom:18px; gap:8px; }
.widget { background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(0,0,0,0.06)); padding: 16px; border-radius: 8px; margin-bottom: 14px; }
.dash-actions { display:flex; gap:8px; align-items:center; }
.dash-actions .mini { padding:6px 10px; background:#f5f5f7; border-radius:8px; text-decoration:none; color:inherit; line-height:1; display:inline-flex; align-items:center; }
.dash-actions .mini:hover { background:#ececef; }
.dash-actions .mini-badge { margin-left:6px; font-weight:600; font-size:12px; opacity:.9; }
</style>
 
<div class="svntex-dash-top">
    <nav class="dash-actions" aria-label="Quick actions">
    <a class="mini" href="#kyc">KYC</a>
    <a class="mini" href="<?php echo esc_url( function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/products/') ); ?>">Products</a>
    <a class="mini" href="<?php echo esc_url( function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/') ); ?>">Cart</a>
        <a class="mini" href="#wallet">Wallet <span class="mini-badge"><?php echo function_exists('wc_price') ? wp_strip_all_tags( wc_price($wallet_balance) ) : esc_html(number_format($wallet_balance,2)); ?></span></a>
        <a class="mini" href="<?php echo $logout_url; ?>">Logout</a>
    </nav>
 </div>

<div class="svntex-dashboard-wrapper fade-in" data-svntex2-dashboard>
    <!-- Navigation is provided by the active theme (Astra). Remove sidebar/menu duplication. -->
    <!-- Mobile navigation handled by theme. -->
    <main class="dashboard-content" role="main">
        <header class="content-header">
            <div class="header-row">
                <?php $welcome_name = get_user_meta($current_user->ID,'first_name', true) ?: $current_user->display_name; ?>
                <h1 class="dash-title">Welcome, <?php echo esc_html($welcome_name); ?><?php if($customer_id_display){ echo ' (' . esc_html($customer_id_display) . ')'; } ?></h1>
            </div>
        </header>
        <div class="dashboard-widgets" id="wallet" aria-label="Quick Stats">
            <section class="widget wallet-widget" aria-label="Wallet Balance" data-widget="wallet">
                <h2 class="widget-title">Wallet Balance</h2>
                <div class="wallet-balance" data-wallet-balance>
                    <span class="balance-amount" data-amount>
                        <?php echo function_exists('wc_price') ? wc_price($wallet_balance) : esc_html(number_format($wallet_balance,2)); ?>
                    </span>
                    <span class="loading-indicator" hidden>Updating…</span>
                </div>
                <small class="muted">Live ledger value</small>
                <form method="post" class="wallet-topup-form" data-topup-form style="margin-top:12px;display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                    <label style="flex:1 1 160px;min-width:140px;">
                        <span class="screen-reader-text">Top-up Amount</span>
                        <input type="number" step="0.01" name="topup_amount" placeholder="Amount" min="100" required style="width:100%;" />
                    </label>
                    <button type="submit" class="btn-accent-gradient" style="padding:.65rem 1.1rem;border-radius:10px;">Top Up</button>
                    <span class="topup-msg muted" data-topup-msg style="flex-basis:100%;font-size:.7rem;"></span>
                </form>
            </section>
            <section class="widget pb-widget" aria-label="Partnership Bonus Status" data-widget="pb-status">
                <h2 class="widget-title">PB Status</h2>
                <div class="pb-status-line"><strong>Status:</strong> <span data-pb-status>—</span></div>
                <div class="pb-cycle-line"><strong>Cycle:</strong> <span data-pb-cycle-index>—</span> <small class="muted" data-pb-cycle-start></small></div>
                <div class="pb-refs-line"><strong>Cycle Referrals:</strong> <span data-pb-cycle-refs>—</span> <small class="muted">/ 6</small></div>
                <div class="pb-activation-line"><strong>Activation Month:</strong> <span data-pb-activation>—</span></div>
                <div class="pb-inclusion-line"><strong>Inclusion Start:</strong> <span data-pb-inclusion>—</span></div>
                <div class="pb-suspense-line" data-pb-suspense-wrap hidden><strong>Held Payouts:</strong> <span data-pb-suspense-total>0</span> (<span data-pb-suspense-count>0</span>)</div>
                <small class="muted" data-pb-last-sync>Syncing…</small>
            </section>
            <section class="widget pb-spend-widget" aria-label="PB Spend & Slab" data-widget="pb-spend">
                <h2 class="widget-title">PB Spend</h2>
                <div><strong>This Month:</strong> <span data-pb-spend>—</span></div>
                <div><strong>Slab %:</strong> <span data-pb-slab>—</span></div>
                <div><strong>Next Threshold:</strong> <span data-pb-next-threshold>—</span></div>
                <div class="pb-progress-bar" style="margin-top:6px;height:6px;background:#eee;border-radius:4px;overflow:hidden;">
                    <span data-pb-progress style="display:block;height:100%;width:0;background:linear-gradient(90deg,#4b6ef5,#6fd3fb);"></span>
                </div>
                <small class="muted" data-pb-progress-hint></small>
            </section>
            <section class="widget referral-widget" aria-label="Referral Count" data-widget="referrals">
                <h2 class="widget-title">Referral Count</h2>
                <div class="referral-count" data-referral-count><?php echo esc_html($referral_count); ?></div>
                <small class="muted">Direct signups</small>
            </section>
            <section class="widget purchases-widget" aria-label="Recent Purchases" id="purchases" data-widget="purchases">
                <h2 class="widget-title">Recent Purchases</h2>
                <ul class="purchases-list" data-purchases>
                    <?php if (!empty($orders)) : foreach ($orders as $order) : ?>
                        <li>
                            <span>#<?php echo esc_html($order->get_order_number()); ?></span>
                            <span class="sep">•</span>
                            <span><?php echo function_exists('wc_price') ? wc_price($order->get_total()) : esc_html(number_format($order->get_total(),2)); ?></span>
                            <span class="sep">•</span>
                            <span><?php echo esc_html($order->get_date_created()->date_i18n('M d, Y')); ?></span>
                        </li>
                    <?php endforeach; else: ?>
                        <li>No purchases yet.</li>
                    <?php endif; ?>
                </ul>
            </section>
        </div>
        <section class="panel grid" id="kyc" data-panel="kyc">
            <h2 class="panel-title">KYC Status</h2>
            <p class="muted">Current status: <strong><?php echo esc_html(get_user_meta($current_user->ID,'kyc_status', true) ?: 'Pending'); ?></strong></p>
            <p class="placeholder">KYC upload module coming in a later phase.</p>
        </section>
    </main>
</div>