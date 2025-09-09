
<?php
// Modernized SVNTeX Dashboard (Phase 1 UI polishing)
if (!defined('ABSPATH')) exit;
if (!is_user_logged_in()) {
    wp_safe_redirect( home_url('/customer-login/') );
    exit;
}

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

$logout_url = esc_url( admin_url( 'admin-post.php?action=svntex2_logout' ) );
?>
<style>
/* Keep dashboard content width flexible. Let the theme (Astra) manage header/navigation. */
.svntex-dashboard-wrapper { max-width: 1100px; margin: 0 auto; padding: 20px; box-sizing: border-box; }
.svntex-dash-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
.widget { background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(0,0,0,0.06)); padding: 16px; border-radius: 8px; margin-bottom: 14px; }
</style>
<!-- Theme handles mobile drawer and header; plugin does not render a separate mobile menu -->
<style>
/* Hide theme header and footer when our brand UI / My Account override is active */
body.svntex-app-shell header,
body.svntex-app-shell .site-header,
body.svntex-app-shell .site-navigation,
body.svntex-app-shell .top-navigation,
body.svntex-app-shell .site-footer,
body.svntex-app-shell footer,
body.svntex-myaccount-override header,
body.svntex-myaccount-override .site-header,
body.svntex-myaccount-override .site-navigation,
body.svntex-myaccount-override .top-navigation,
body.svntex-myaccount-override .site-footer,
body.svntex-myaccount-override footer { display: none !important; }

/* Ensure page content uses full viewport when header/footer hidden */
body.svntex-app-shell .site { padding-top: 0 !important; }
body.svntex-app-shell .site-main, body.svntex-myaccount-override .site-main { margin-top: 0 !important; }
</style>
<!-- Plugin defers mobile menu behavior to the active theme (Astra) -->
 
<div class="svntex-dash-top">
    <a href="<?php echo esc_url( home_url('/') ); ?>" class="dash-brand">SVNTeX</a>
    <div class="dash-actions">
        <button class="mini" id="svntex2DarkToggleTop" aria-label="Toggle dark mode">Theme</button>
    <a class="mini" href="<?php echo $logout_url; ?>">Logout</a>
    </div>
</div>
<!-- Debug: show build info so we can confirm deployment on live site -->
<div style="position:fixed;left:12px;bottom:12px;z-index:99999;background:rgba(0,0,0,0.6);color:#fff;padding:6px 8px;border-radius:6px;font-size:11px;opacity:0.9">build: a19d719 — 2025-09-09</div>

<!-- Collapsible server-side debug panel (toggle to inspect runtime values) -->
<div id="svntex-debug-panel" style="position:fixed;left:12px;bottom:52px;z-index:99999;background:rgba(0,0,0,0.8);color:#fff;padding:8px;border-radius:6px;font-size:12px;max-width:340px;">
    <button id="svntex-debug-toggle" style="background:#222;border:0;color:#fff;padding:6px 8px;border-radius:6px;cursor:pointer;">Debug ▾</button>
    <div id="svntex-debug-body" style="display:none;margin-top:8px;line-height:1.4;">
        <div><strong>User ID:</strong> <?php echo intval($current_user->ID); ?></div>
        <div><strong>Display name:</strong> <?php echo esc_html($current_user->display_name); ?></div>
        <div><strong>KYC status:</strong> <?php echo esc_html(get_user_meta($current_user->ID,'kyc_status', true) ?: 'None'); ?></div>
        <div><strong>Wallet raw:</strong> <?php echo esc_html($wallet_balance); ?></div>
        <div><strong>Referral count:</strong> <?php echo esc_html($referral_count); ?></div>
        <div><strong>WooCommerce orders fn:</strong> <?php echo function_exists('wc_get_orders') ? 'available' : 'missing'; ?></div>
        <div><strong>WooCommerce price fn:</strong> <?php echo function_exists('wc_price') ? 'available' : 'missing'; ?></div>
        <div style="margin-top:6px;font-size:11px;opacity:0.9;color:#d0d7ff">Note: this is a temporary debug panel; it will not change user data.</div>
    </div>
</div>
<script>document.addEventListener('DOMContentLoaded',function(){var t=document.getElementById('svntex-debug-toggle'),b=document.getElementById('svntex-debug-body');if(!t||!b)return;t.addEventListener('click',function(){if(b.style.display==='none'){b.style.display='block';t.textContent='Debug ▴';}else{b.style.display='none';t.textContent='Debug ▾';}});});</script>

<div class="svntex-dashboard-wrapper fade-in" data-svntex2-dashboard>
    <!-- Navigation is provided by the active theme (Astra). Remove sidebar/menu duplication. -->
    <!-- Mobile navigation handled by theme. -->
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