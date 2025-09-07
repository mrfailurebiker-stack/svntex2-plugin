<?php
if (!defined('ABSPATH')) exit;
if (!is_user_logged_in()) { echo '<p>Please login.</p>'; return; }
$user = wp_get_current_user();
$balance = svntex2_wallet_get_balance($user->ID);
?>
<div class="svntex2-dashboard">
  <h2>Welcome, <?php echo esc_html($user->display_name); ?></h2>
  <div class="dashboard-cards">
    <div class="card">
      <h3>Income Wallet</h3>
      <p><strong><?php echo function_exists('wc_price') ? wc_price($balance) : esc_html(number_format($balance,2)); ?></strong></p>
    </div>
    <div class="card">
      <h3>Referral Count</h3>
      <p><?php echo intval(get_user_meta($user->ID,'referral_count', true)); ?></p>
    </div>
    <div class="card">
      <h3>KYC Status</h3>
      <p><?php echo esc_html(get_user_meta($user->ID,'kyc_status', true) ?: 'Pending'); ?></p>
    </div>
  </div>
</div>
<?php
// SVNTeX Dashboard Page for WordPress Plugin
// Responsive, secure, WooCommerce integration, custom user meta, sidebar navigation

// 1. Ensure WordPress environment and user authentication
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url() );
    exit;
}

// 2. Get current user and custom meta
$current_user = wp_get_current_user();
$wallet_balance = get_user_meta( $current_user->ID, 'wallet_balance', true );
$referral_count = get_user_meta( $current_user->ID, 'referral_count', true );
$wallet_balance = ($wallet_balance === '') ? 0 : floatval($wallet_balance);
$referral_count = ($referral_count === '') ? 0 : intval($referral_count);

// 3. Get last 5 WooCommerce orders for current user if WooCommerce installed
$orders = [];
if ( function_exists( 'wc_get_orders' ) ) {
    $orders = wc_get_orders([
        'customer_id' => $current_user->ID,
        'limit'       => 5,
        'orderby'     => 'date',
        'order'       => 'DESC',
    ]);
}

// 4. Generate logout URL safely
$logout_url = esc_url( wp_logout_url( home_url() ) );
?>

<link rel="stylesheet" href="<?php echo esc_url( plugin_dir_url(__DIR__) . 'assets/css/style.css' ); ?>">
<div class="svntex-dashboard-wrapper">
    <!-- Sidebar Navigation -->
    <aside class="dashboard-sidebar" role="navigation" aria-label="Dashboard Navigation">
        <nav>
            <ul class="nav-list">
                <li><a href="<?php echo esc_url( home_url( '/dashboard' ) ); ?>" class="nav-link">Home</a></li>
                <li><a href="#wallet" class="nav-link">Wallet</a></li>
                <li><a href="#purchases" class="nav-link">Purchases</a></li>
                <li><a href="#referrals" class="nav-link">Referrals</a></li>
                <li><a href="#kyc" class="nav-link">KYC</a></li>
                <li><a href="<?php echo $logout_url; ?>" class="nav-link">Logout</a></li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="dashboard-content" role="main">
        <header class="content-header">
            <h1>Welcome, <?php echo esc_html( $current_user->display_name ); ?></h1>
        </header>

        <div class="dashboard-widgets">
            <!-- Wallet Balance Card -->
            <section class="widget wallet-widget" aria-label="Wallet Balance">
                <h2>Wallet Balance</h2>
                <div class="wallet-balance">
                    <?php echo function_exists('wc_price') ? wc_price( $wallet_balance ) : esc_html( number_format( $wallet_balance, 2 ) ); ?>
                </div>
            </section>

            <!-- Referral Count Card -->
            <section class="widget referral-widget" aria-label="Referral Count">
                <h2>Referral Count</h2>
                <div class="referral-count">
                    <?php echo esc_html( $referral_count ); ?>
                </div>
            </section>

            <!-- Recent Purchases Card -->
            <section class="widget purchases-widget" aria-label="Recent Purchases">
                <h2>Recent Purchases</h2>
                <ul class="purchases-list">
                    <?php if ( ! empty( $orders ) ) : ?>
                        <?php foreach ( $orders as $order ) : ?>
                            <li>
                                <span><?php echo esc_html( $order->get_order_number() ); ?></span> -
                                <span><?php echo function_exists('wc_price') ? wc_price( $order->get_total() ) : esc_html( number_format( $order->get_total(), 2 ) ); ?></span> -
                                <span><?php echo esc_html( $order->get_date_created()->date_i18n( 'M d, Y' ) ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <li>No purchases yet.</li>
                    <?php endif; ?>
                </ul>
            </section>
        </div>
    </main>
</div>