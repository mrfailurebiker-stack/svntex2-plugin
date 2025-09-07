<?php
// Login form shortcode
function svntex2_login_form() {
    ob_start();
    ?>
    <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__); ?>svntex2-ui.css">
    <div class="svntex2-card">
        <div class="svntex2-title">Customer Login</div>
        <form id="svntex2-login" method="post">
            <input class="svntex2-input" type="text" name="login_id" placeholder="Customer ID / Email / Mobile" required>
            <input class="svntex2-input" type="password" name="password" placeholder="Password" required>
            <button class="svntex2-btn" type="submit">Login</button>
        </form>
        <div class="svntex2-status" id="svntex2-login-result"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('svntex2_login', 'svntex2_login_form');
