<?php
// wallet_dashboard.php - Main wallet dashboard UI
// Connect to MySQL
$mysqli = new mysqli('localhost', 'username', 'password', 'svntex2');
if ($mysqli->connect_errno) {
    die('Database connection failed: ' . $mysqli->connect_error);
}
// Assume customer_id is set via session
session_start();
$customer_id = $_SESSION['customer_id'] ?? 1; // Example
// Fetch wallet balances
$wallet = $mysqli->query("SELECT topup_balance, income_balance FROM wallets WHERE customer_id=$customer_id")->fetch_assoc();
$bonuses = $mysqli->query("SELECT referral_bonus, partnership_bonus FROM bonuses WHERE customer_id=$customer_id")->fetch_assoc();
// Handle top-up form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topup_amount'])) {
    $amount = floatval($_POST['topup_amount']);
    // Here, integrate WooCommerce payment gateway (simulate success)
    $mysqli->query("UPDATE wallets SET topup_balance = topup_balance + $amount WHERE customer_id=$customer_id");
    $mysqli->query("INSERT INTO transactions (customer_id, type, amount) VALUES ($customer_id, 'topup', $amount)");
    header('Location: wallet_dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Wallet Dashboard</title>
    <link rel="stylesheet" href="wallet.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="wallet.js"></script>
</head>
<body>
<div class="dashboard">
    <h2>Wallet Dashboard</h2>
    <div class="wallet-balance">
        <div>Top-up Wallet: ₹<?php echo number_format($wallet['topup_balance'],2); ?></div>
        <div>Income Wallet: ₹<?php echo number_format($wallet['income_balance'],2); ?></div>
    </div>
    <div class="bonus">Referral Bonus (RB): ₹<?php echo number_format($bonuses['referral_bonus'],2); ?></div>
    <div class="bonus">Partnership Bonus (PB): ₹<?php echo number_format($bonuses['partnership_bonus'],2); ?></div>
    <div class="progress-bar">
        <div class="progress" style="width:<?php echo min(100, $wallet['topup_balance']/1000*100); ?>%"></div>
    </div>
    <canvas id="walletChart" width="400" height="200"></canvas>
    <script>
    window.walletChartData = {
        data: [<?php echo $wallet['topup_balance']; ?>, <?php echo $wallet['income_balance']; ?>, <?php echo $bonuses['referral_bonus']; ?>, <?php echo $bonuses['partnership_bonus']; ?>],
        label: ['Top-up', 'Income', 'RB', 'PB']
    };
    </script>
    <form class="topup-form" method="post">
        <input type="number" name="topup_amount" min="1" step="0.01" placeholder="Top-up Amount" required>
        <button type="submit">Top-up Wallet (WooCommerce)</button>
    </form>
</div>
</body>
</html>
