<?php
// Registration form shortcode
function svntex2_registration_form() {
    ob_start();
    ?>
    <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__); ?>svntex2-ui.css">
    <div class="svntex2-card">
        <div class="svntex2-title">Customer Registration</div>
        <form id="svntex2-registration" method="post">
            <input class="svntex2-input" type="text" name="mobile" placeholder="Mobile Number" required>
            <input class="svntex2-input" type="email" name="email" placeholder="Email" required>
            <input class="svntex2-input" type="text" name="first_name" placeholder="First Name">
            <input class="svntex2-input" type="text" name="last_name" placeholder="Last Name">
            <input class="svntex2-input" type="text" name="referral_id" placeholder="Referral ID">
            <input class="svntex2-input" type="text" name="employee_id" placeholder="Employee ID">
            <input class="svntex2-input" type="password" name="password" placeholder="Password" required>
            <button class="svntex2-btn" type="submit">Register</button>
        </form>
        <div class="svntex2-status" id="svntex2-registration-result"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('svntex2_registration', 'svntex2_registration_form');
