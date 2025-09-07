<?php
// Wallet management shortcode
function svntex2_wallet_dashboard() {
    ob_start();
    ?>
    <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__); ?>svntex2-ui.css">
    <div class="svntex2-card">
        <div class="svntex2-title">Wallet Dashboard</div>
        <div class="wallet-balances">
            <div><span class="material-icons" style="vertical-align:middle;color:#0077cc;">account_balance_wallet</span> Top-up Wallet: <strong>₹0.00</strong></div>
            <div><span class="material-icons" style="vertical-align:middle;color:#00bfae;">savings</span> SVNTEX Wallet (Withdrawals, PB, RB): <strong>₹0.00</strong></div>
        </div>
        <form class="topup-form" method="post">
            <input class="svntex2-input" type="number" name="topup_amount" min="1" step="0.01" placeholder="Top-up Amount" required>
            <button class="svntex2-btn" type="submit">Top-up Wallet</button>
        </form>
        <div class="svntex2-status" id="wallet-topup-result"></div>
        <div class="wallet-history">
            <h3 style="margin-top:24px;">Transaction History</h3>
            <div>No transactions yet.</div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('svntex2_wallet', 'svntex2_wallet_dashboard');
