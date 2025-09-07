
<?php
// Prevent direct access
if (!defined('ABSPATH')) exit;

// Register shortcode for frontend registration form
function svntex2_registration_form_shortcode() {
    if (is_admin()) return '';
    ob_start();
    ?>
    <div class="registration-container">
        <form id="registrationForm" autocomplete="off">
            <h2>Register</h2>
            <div class="form-group">
                <label>Mobile Number*</label>
                <input type="text" name="mobile" id="mobile" maxlength="15" required>
                <button type="button" id="sendOtpBtn">Send OTP</button>
                <input type="text" name="otp" id="otp" maxlength="6" placeholder="Enter OTP" required>
            </div>
            <div class="form-group">
                <label>Email*</label>
                <input type="email" name="email" id="email" required>
            </div>
            <div class="form-group">
                <label>First Name*</label>
                <input type="text" name="fname" id="fname" required>
            </div>
            <div class="form-group">
                <label>Last Name*</label>
                <input type="text" name="lname" id="lname" required>
            </div>
            <div class="form-group">
                <label>Referral ID (optional)</label>
                <input type="text" name="referral" id="referral">
            </div>
            <div class="form-group">
                <label>Employee ID (optional)</label>
                <input type="text" name="employee" id="employee">
            </div>
            <div class="form-group">
                <label>Password*</label>
                <input type="password" name="password" id="password" required>
            </div>
            <div class="form-group">
                <label>Confirm Password*</label>
                <input type="password" name="confirm" id="confirm" required>
            </div>
            <input type="hidden" name="csrf_token" value="<?php echo esc_attr(wp_create_nonce('svntex2_reg_nonce')); ?>">
            <button type="submit" class="submit-btn">Register</button>
            <div id="formErrors" class="form-errors"></div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('svntex_registration', 'svntex2_registration_form_shortcode');

// Enqueue assets only when shortcode is present
function svntex2_enqueue_registration_assets() {
    global $post;
    if (isset($post->post_content) && has_shortcode($post->post_content, 'svntex_registration')) {
        wp_enqueue_style('svntex2-customer-ui', plugins_url('customer-ui.css', __FILE__));
        wp_enqueue_script('svntex2-customer-auth', plugins_url('customer-auth.js', __FILE__), array('jquery'), null, true);
    }
}
add_action('wp_enqueue_scripts', 'svntex2_enqueue_registration_assets');

/*
USAGE:
Add [svntex_registration] to any WordPress page or post to display the registration form on the frontend.
*/
