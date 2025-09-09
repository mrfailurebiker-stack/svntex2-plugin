
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
/* Hide WooCommerce My Account navigation and content so only our custom dashboard shows */
.woocommerce-MyAccount-navigation,
.woocommerce-MyAccount-content,
.woocommerce nav.woocommerce-MyAccount-navigation,
.woocommerce-account .woocommerce-MyAccount-navigation,
.woocommerce-account .woocommerce-MyAccount-content { display: none !important; }

/* Remove theme greeting block that can show "Hello ... Log out" when inside My Account */
.woocommerce-MyAccount .woocommerce-MyAccount-content p { display: none !important; }

/* Dashboard layout: sidebar + content for desktop/tablet, mobile uses bottom nav */
.svntex-dashboard-wrapper { max-width: 1100px; margin: 0 auto; padding: 20px; box-sizing: border-box; }
.dashboard-sidebar { width: 260px; float: left; margin-right: 24px; }
.dashboard-content { margin-left: 284px; }

/* Make sure our top bar actions align */
.svntex-dash-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }

/* Responsive: mobile and small tablets collapse sidebar and use bottom nav */
@media (max-width: 900px) {
    /* Keep sidebar container visible on mobile so compact tab can show; hide full nav list */
    .dashboard-sidebar { display: block !important; }
    .dashboard-sidebar .nav-list { display: none !important; }
    .dashboard-content { margin-left: 0 !important; padding-bottom: 76px; }
    .svntex-dash-top { padding-right: 12px; }
    .mobile-nav { position: fixed; bottom: 0; left: 0; right: 0; display: flex; justify-content: space-around; background: rgba(10,10,20,0.95); padding: .5rem 0; z-index: 9999; }
}

/* Tablet layout tweaks */
@media (min-width: 901px) and (max-width: 1199px) {
    .dashboard-sidebar { width: 220px; margin-right: 16px; }
    .dashboard-content { margin-left: 236px; }
}

/* Nav list visual polish */
.nav-list { list-style:none; margin:0; padding:0; }
.nav-list .nav-link { display:block; padding:.8rem 1rem; color: #e6eefb; text-decoration:none; border-left:4px solid transparent; }
.nav-list .nav-link.active, .nav-list .nav-link:hover { background: rgba(255,255,255,0.03); color: #fff; border-left-color: #7c5cff; }

/* Ensure our widget area is readable on dark themes */
.widget { background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(0,0,0,0.06)); padding: 16px; border-radius: 8px; margin-bottom: 14px; }

/* Compact 3-line tab styles */
.compact-tab { display:flex; align-items:center; gap:8px; background:transparent; border:0; color:inherit; cursor:pointer; padding:6px 8px; border-radius:8px; }
.compact-lines { display:inline-block; width:22px; }
.compact-line { display:block; height:2px; background:#dfe9ff; margin:3px 0; border-radius:2px; }
.compact-label { font-size:12px; opacity:0.9; }

/* Hidden by default on desktop; visible on small screens and tablet */
@media (max-width: 900px) {
    .compact-tab { display:flex; }
}

</* When menu is open, previously used full-screen overlay. We'll show in-sidebar panel instead */
/* sidebar open state */
.dashboard-sidebar.open { position: relative; }
.dashboard-sidebar.open .nav-list { display:block !important; position: absolute; left: 8px; top: 56px; width: 220px; max-width: 80vw; z-index: 1000; box-shadow: 0 8px 30px rgba(0,0,0,0.6); background: linear-gradient(180deg, rgba(18,18,30,0.98), rgba(10,10,20,0.95)); padding:12px; border-radius:8px; }
</style>
<style>
/* Mobile drawer: when menu opens, show a white full-height panel similar to site nav */
#svntex-compact-menu { display: none; }
#svntex-compact-menu .nav-list { list-style:none; margin:0; padding:0; }
#svntex-compact-menu .nav-item { border-bottom:1px solid rgba(0,0,0,0.06); }
#svntex-compact-menu .nav-link { display:block; padding:16px 18px; color:#222; text-decoration:none; background:transparent; }
#svntex-compact-menu .nav-link .nav-ico { margin-right:10px; }
#svntex-compact-menu .nav-link:hover { background: rgba(0,0,0,0.03); }
#svntex-compact-menu .nav-head { display:flex; align-items:center; justify-content:space-between; padding:12px 14px; border-bottom:1px solid rgba(0,0,0,0.06); }
#svntex-compact-menu .nav-head .brand { font-weight:700; color:#1b1b1b; }
#svntex-compact-menu .close-btn { background:transparent;border:0;font-size:20px;cursor:pointer;color:#111 }

/* Drawer visible state when body gets .menu-open */
body.menu-open #svntex-compact-menu { display:block !important; position:fixed; left:0; top:0; bottom:0; width:100%; max-width:420px; background:#fff; z-index:10050; overflow:auto; box-shadow: 0 8px 30px rgba(0,0,0,0.25); }

/* For larger screens keep previous compact behaviour hidden */
@media (min-width: 901px) {
    #svntex-compact-menu { display:none !important; }
}
</style>
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
<script>
// Mobile drawer behavior: toggle body.menu-open and manage focus
document.addEventListener('DOMContentLoaded', function(){
    var hamburger = document.querySelector('.compact-tab');
    var menu = document.getElementById('svntex-compact-menu');
    var closeBtn = document.getElementById('svntex-menu-close');
    if (!hamburger || !menu) return;
    // ensure hamburger visible on small screens
    hamburger.style.display = 'flex';
    hamburger.addEventListener('click', function(e){
        document.body.classList.add('menu-open');
        menu.setAttribute('aria-hidden','false');
        hamburger.setAttribute('aria-expanded','true');
        closeBtn && closeBtn.focus();
    });
    function closeMenu(){
        document.body.classList.remove('menu-open');
        menu.setAttribute('aria-hidden','true');
        hamburger.setAttribute('aria-expanded','false');
        hamburger.focus();
    }
    closeBtn && closeBtn.addEventListener('click', closeMenu);
    // click outside closes menu
    document.addEventListener('click', function(e){
        if (!document.body.classList.contains('menu-open')) return;
        if (menu.contains(e.target) || hamburger.contains(e.target)) return;
        closeMenu();
    });
    // escape
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && document.body.classList.contains('menu-open')) closeMenu(); });
});
</script>
 
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
    <aside class="dashboard-sidebar" role="navigation" aria-label="Dashboard Navigation">
        <!-- Compact 3-line tab: clicking toggles full menu visibility -->
        <button class="compact-tab" aria-expanded="false" aria-controls="svntex-compact-menu" title="Open menu">
            <span class="compact-lines">
                <em class="compact-line"></em>
                <em class="compact-line"></em>
                <em class="compact-line"></em>
            </span>
            <span class="compact-label">Menu</span>
        </button>
        <nav id="svntex-compact-menu">
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