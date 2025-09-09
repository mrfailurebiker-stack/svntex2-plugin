<?php
// Modernized SVNTeX Dashboard (Phase 1 UI polishing)
if (!defined('ABSPATH')) exit;
if (!is_user_logged_in()) { echo '<p>Please login.</p>'; return; }

$current_user   = wp_get_current_user();
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

$logout_url = esc_url( wp_logout_url( home_url('/') ) . '&_wpnonce=' . wp_create_nonce('log-out') );
?>
<div class="svntex-dash-top">
    <a href="<?php echo esc_url( home_url('/') ); ?>" class="dash-brand">SVNTeX</a>
    <div class="dash-actions">
        <button class="mini" id="svntex2DarkToggleTop" aria-label="Toggle dark mode">Theme</button>
    <a class="mini" href="<?php echo $logout_url; ?>">Logout</a>
    </div>
</div>
<div class="svntex-dashboard-wrapper fade-in" data-svntex2-dashboard>
    <aside class="dashboard-sidebar" role="navigation" aria-label="Dashboard Navigation">
        <nav>
            <ul class="nav-list">
                <li><a href="<?php echo esc_url( home_url('/dashboard') ); ?>" class="nav-link active" data-nav="home"><span class="nav-ico">🏠</span>Home</a></li>
                <li><a href="#wallet" class="nav-link" data-nav="wallet"><span class="nav-ico">💰</span>Wallet</a></li>
                <li><a href="#purchases" class="nav-link" data-nav="purchases"><span class="nav-ico">🛒</span>Purchases</a></li>
                <li><a href="#referrals" class="nav-link" data-nav="referrals"><span class="nav-ico">👥</span>Referrals</a></li>
                <li><a href="#kyc" class="nav-link" data-nav="kyc"><span class="nav-ico">🛡️</span>KYC</a></li>
            <li><a href="<?php echo $logout_url; ?>" class="nav-link"><span class="nav-ico">🚪</span>Logout</a></li>
            </ul>
        </nav>
    </aside>
    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-nav" aria-label="Mobile Navigation">
    <a href="<?php echo esc_url( home_url('/dashboard') ); ?>" class="mobile-nav-link" data-nav="home"><span class="nav-ico">🏠</span><span class="nav-label">Home</span></a>
    <a href="#wallet" class="mobile-nav-link" data-nav="wallet"><span class="nav-ico">💰</span><span class="nav-label">Wallet</span></a>
    <a href="#purchases" class="mobile-nav-link" data-nav="purchases"><span class="nav-ico">🛒</span><span class="nav-label">Purchases</span></a>
    <a href="#referrals" class="mobile-nav-link" data-nav="referrals"><span class="nav-ico">👥</span><span class="nav-label">Referrals</span></a>
    <a href="#kyc" class="mobile-nav-link" data-nav="kyc"><span class="nav-ico">🛡️</span><span class="nav-label">KYC</span></a>
    <a href="<?php echo $logout_url; ?>" class="mobile-nav-link"><span class="nav-ico">🚪</span><span class="nav-label">Logout</span></a>
    </nav>
    <main class="dashboard-content" role="main">
        <header class="content-header">
            <div class="header-row">
                <h1 class="dash-title">Welcome, <?php echo esc_html($current_user->display_name); ?></h1>
                <div class="header-actions">
                    <button type="button" class="btn-ghost" id="svntex2DarkToggle" aria-pressed="false" aria-label="Toggle dark mode">🌙</button>
                    <button type="button" class="btn-refresh" id="svntex2WalletRefresh" aria-label="Refresh wallet balance">↻</button>
                </div>
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