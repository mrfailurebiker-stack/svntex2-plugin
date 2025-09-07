<?php
// Registration and login logic for SVNTeX 2.0
add_shortcode('svntex2_registration', 'svntex2_registration_form');
add_shortcode('svntex2_login', 'svntex2_login_form');

function svntex2_registration_form() {
    ob_start();
    ?>
    <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__); ?>registration.css">
    <div class="svntex2-card">
        <div class="svntex2-title">Customer Registration</div>
        <form id="svntex2-registration" method="post">
            <input class="svntex2-input" type="text" name="mobile" placeholder="Mobile Number" required>
            <button class="svntex2-btn" type="button" onclick="sendOTP()">Send OTP</button>
            <input class="svntex2-input" type="text" name="otp" placeholder="Enter OTP" required>
            <input class="svntex2-input" type="email" name="email" placeholder="Email" required>
            <input class="svntex2-input" type="text" name="first_name" placeholder="First Name">
            <input class="svntex2-input" type="text" name="last_name" placeholder="Last Name">
            <input class="svntex2-input" type="password" name="password" placeholder="Password" required>
            <button class="svntex2-btn" type="submit">Register</button>
        </form>
        <div class="svntex2-status" id="svntex2-registration-result"></div>
    </div>
    <script src="<?php echo plugin_dir_url(__FILE__); ?>registration.js"></script>
    <?php
    return ob_get_clean();
}

function svntex2_login_form() {
    ob_start();
    ?>
    <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__); ?>registration.css">
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

// Backend registration and login logic will be added here...
