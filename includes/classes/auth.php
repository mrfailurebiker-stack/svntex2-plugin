<?php
if (!defined('ABSPATH')) exit;

class SVNTEX2_Auth {

    const OTP_META_PREFIX = '_svntex2_otp_';

    public static function init(){
        add_shortcode('svntex_registration', [__CLASS__, 'registration_shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('wp_ajax_svntex2_send_otp', [__CLASS__, 'ajax_send_otp']);
        add_action('wp_ajax_nopriv_svntex2_send_otp', [__CLASS__, 'ajax_send_otp']);
        add_action('wp_ajax_nopriv_svntex2_register', [__CLASS__, 'ajax_register']);
    }

    public static function enqueue(){
        if (is_singular()) {
            global $post; if ($post && has_shortcode($post->post_content, 'svntex_registration')) {
                wp_enqueue_style('svntex2-style');
                wp_enqueue_script('svntex2-auth', SVNTEX2_PLUGIN_URL.'assets/js/auth.js', ['jquery'], SVNTEX2_VERSION, true);
                wp_localize_script('svntex2-auth','SVNTEX2Auth', [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('svntex2_auth'),
                ]);
            }
        }
    }

    public static function registration_shortcode(){
        ob_start();
        ?>
        <form id="svntex2RegForm" class="svntex2-registration" novalidate>
            <?php wp_nonce_field('svntex2_auth','svntex2_nonce'); ?>
            <div><label>Mobile* <input type="text" name="mobile" maxlength="15" required></label> <button type="button" id="svntex2SendOtp" class="btn-otp">Send OTP</button></div>
            <div><label>OTP* <input type="text" name="otp" maxlength="6" required></label></div>
            <div><label>Email* <input type="email" name="email" required></label></div>
            <div><label>Password* <input type="password" name="password" required minlength="8"></label></div>
            <div><label>Referral ID <input type="text" name="referral"></label></div>
            <div class="form-messages" aria-live="polite"></div>
            <button type="submit">Create Account</button>
        </form>
        <?php
        return ob_get_clean();
    }

    public static function ajax_send_otp(){
        check_ajax_referer('svntex2_auth','nonce');
        $mobile = preg_replace('/\D/','', $_POST['mobile'] ?? '');
        if (strlen($mobile) < 10) wp_send_json_error(['message' => 'Invalid mobile number']);
        $code = wp_rand(100000,999999);
        set_transient(self::OTP_META_PREFIX.$mobile, $code, 10 * MINUTE_IN_SECONDS);
        // TODO integrate SMS provider here.
        wp_send_json_success(['message' => 'OTP sent (demo): '.$code]);
    }

    public static function ajax_register(){
        check_ajax_referer('svntex2_auth','nonce');
        $mobile  = preg_replace('/\D/','', $_POST['mobile'] ?? '');
        $otp     = sanitize_text_field($_POST['otp'] ?? '');
        $email   = sanitize_email($_POST['email'] ?? '');
        $pass    = $_POST['password'] ?? '';
        $ref     = sanitize_text_field($_POST['referral'] ?? '');

        $errors = [];
        if (strlen($mobile) < 10) $errors[] = 'Mobile invalid';
        if (!$email || email_exists($email)) $errors[] = 'Email invalid or exists';
        if (strlen($pass) < 8) $errors[] = 'Password too short';
        $stored = get_transient(self::OTP_META_PREFIX.$mobile);
        if (!$stored || $stored != $otp) $errors[] = 'OTP mismatch';
        if ($errors) wp_send_json_error(['errors' => $errors]);

        $customer_id = svntex2_generate_customer_id();
        $user_id = wp_insert_user([
            'user_login' => $customer_id,
            'user_email' => $email,
            'user_pass'  => $pass,
            'display_name' => $customer_id
        ]);
        if (is_wp_error($user_id)) wp_send_json_error(['errors' => ['Registration failed']]);
        update_user_meta($user_id,'mobile',$mobile);
        update_user_meta($user_id,'customer_id',$customer_id);
        if ($ref) update_user_meta($user_id,'referral_source',$ref);
        delete_transient(self::OTP_META_PREFIX.$mobile);
        wp_send_json_success(['message' => 'Account created','customer_id'=>$customer_id]);
    }
}

SVNTEX2_Auth::init();
?>
