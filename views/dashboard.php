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

$logout_url = esc_url( wp_logout_url( home_url() ) );
?>
<div class="svntex-dashboard-wrapper" data-svntex2-dashboard>
    <aside class="dashboard-sidebar" role="navigation" aria-label="Dashboard Navigation">
        <nav>
            <ul class="nav-list">
                <li><a href="<?php echo esc_url( home_url('/dashboard') ); ?>" class="nav-link active" data-nav="home">Home</a></li>
                <li><a href="#wallet" class="nav-link" data-nav="wallet">Wallet</a></li>
                <li><a href="#purchases" class="nav-link" data-nav="purchases">Purchases</a></li>
                <li><a href="#referrals" class="nav-link" data-nav="referrals">Referrals</a></li>
                <li><a href="#kyc" class="nav-link" data-nav="kyc">KYC</a></li>
                <li><a href="<?php echo $logout_url; ?>" class="nav-link">Logout</a></li>
            </ul>
        </nav>
    </aside>
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