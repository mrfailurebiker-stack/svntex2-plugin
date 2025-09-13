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
                wp_enqueue_script('svntex2-auth', SVNTEX2_PLUGIN_URL+'assets/js/auth.js', ['jquery'], SVNTEX2_VERSION, true);
                // Provide ajax settings; also flag to disable legacy handler for styled template
                wp_localize_script('svntex2-auth','SVNTEX2Auth', [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('svntex2_auth'),
                ]);
                wp_add_inline_script('svntex2-auth','window.SVNTEX2_DISABLE_LEGACY_REG = true;', 'before');
            }
        }
    }

    public static function registration_shortcode(){
    wp_enqueue_style('svntex2-style');
    $file = SVNTEX2_PLUGIN_DIR . 'views/customer-registration.php';
        if ( file_exists( $file ) ) {
            ob_start();
            include $file;
            return ob_get_clean();
        }
    // fallback
    $fallback = SVNTEX2_PLUGIN_DIR . 'views/registration.php';
    if ( file_exists( $fallback ) ) { ob_start(); include $fallback; return ob_get_clean(); }
        return '<p>Registration view missing.</p>';
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
        $first   = sanitize_text_field($_POST['first_name'] ?? '');
        $last    = sanitize_text_field($_POST['last_name'] ?? '');
        $email   = sanitize_email($_POST['email'] ?? '');
        $dob_raw = trim((string)($_POST['dob'] ?? ''));
        // Normalize DOB to YYYY-MM-DD; accept YYYY-MM-DD, DD/MM/YYYY, MM/DD/YYYY
        $dob = '';
        if ($dob_raw) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob_raw)) {
                $dob = $dob_raw;
            } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dob_raw, $m)) {
                // Assume DD/MM/YYYY if day>12 else could be MM/DD; try both and pick one that yields >=18 if possible
                $d = (int)$m[1]; $mo=(int)$m[2]; $y=(int)$m[3];
                $cand1 = sprintf('%04d-%02d-%02d', $y, $mo, $d); // MM/DD -> YYYY-MM-DD if originally MM/DD
                $cand2 = sprintf('%04d-%02d-%02d', $y, $d, $mo); // DD/MM -> YYYY-MM-DD
                // Prefer cand2 when day>12 (more likely DD/MM)
                $dob = ($d > 12) ? $cand2 : $cand1;
            }
        }
        $gender  = sanitize_text_field($_POST['gender'] ?? '');
        $mobile  = preg_replace('/\D/','', $_POST['mobile'] ?? '');
        $ref     = sanitize_text_field($_POST['referral'] ?? '');
        $emp     = sanitize_text_field($_POST['employee_id'] ?? '');
        $pass    = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm'] ?? '');

        $errors = [];
        if (!$first) $errors[] = 'First name required';
        if (!$last) $errors[] = 'Last name required';
        if (!$email || !is_email($email)) $errors[] = 'Valid email required';
        // Validate DOB and 18+
        $computed_age = 0;
    if ($dob && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            $dob_ts = strtotime($dob . ' 00:00:00');
            if ($dob_ts === false) {
                $errors[] = 'Valid date of birth required';
            } else {
                $now = current_time('timestamp');
                $computed_age = (int) floor( ($now - $dob_ts) / (365.2425 * DAY_IN_SECONDS) );
                if ($computed_age < 18) $errors[] = 'You must be at least 18 years old';
            }
        } else {
            $errors[] = 'Valid date of birth required';
        }
        if (!$gender) $errors[] = 'Gender required';
        if (strlen($mobile) < 10) $errors[] = 'Valid mobile required';
        if (strlen($pass) < 4) $errors[] = 'Password must be at least 4 characters';
        if ($pass !== $confirm) $errors[] = 'Passwords do not match';
        // Enforce unique mobile
        $dupe = get_users([ 'meta_key'=>'mobile', 'meta_value'=>$mobile, 'number'=>1, 'fields'=>'ids' ]);
        if ($dupe) $errors[] = 'Mobile already registered';
        if ($email && email_exists($email)) $errors[] = 'Email already registered';
        if ($errors) wp_send_json_error(['errors' => $errors]);

        // Ensure unique customer login
        $customer_id = svntex2_generate_customer_id(); // SVNXXXXXX
        $attempts = 0;
        while ( username_exists($customer_id) && $attempts < 5 ) { $customer_id = svntex2_generate_customer_id(); $attempts++; }
        if ( username_exists($customer_id) ) {
            wp_send_json_error(['errors'=>['Temporary error creating account (ID conflict). Please try again.']]);
        }
        $display_name = trim($first);
        $user_id = wp_insert_user([
            'user_login' => $customer_id,
            'user_pass'  => $pass,
            'user_email' => $email,
            'first_name' => $first,
            'last_name'  => $last,
            'display_name' => $display_name
        ]);
        if (is_wp_error($user_id)) {
            $msg = $user_id->get_error_message() ?: 'Registration failed';
            wp_send_json_error(['errors' => [$msg]]);
        }
        update_user_meta($user_id,'mobile',$mobile);
        update_user_meta($user_id,'customer_id',$customer_id);
        // Store DOB and also maintain computed age for backward compatibility
        update_user_meta($user_id,'dob',$dob);
        update_user_meta($user_id,'age',$computed_age);
        update_user_meta($user_id,'gender',$gender);
        if ($ref) update_user_meta($user_id,'referral_source',$ref);
        if ($emp) update_user_meta($user_id,'employee_id',$emp);
        wp_send_json_success(['message' => 'Account created','customer_id'=>$customer_id]);
    }
}

SVNTEX2_Auth::init();
?>
