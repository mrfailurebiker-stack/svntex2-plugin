<?php
/**
 * KYC submission + status helpers (scaffold)
 */
if (!defined('ABSPATH')) exit;

/** Submit KYC basic info (hashing sensitive fields). */
function svntex2_kyc_submit($user_id, $aadhaar_raw = null, $pan_raw = null, $data = []){
    global $wpdb; $table = $wpdb->prefix.'svntex_kyc_submissions';
    $row = [
        'user_id' => $user_id,
        'status'  => 'pending',
        'aadhaar_hash' => $aadhaar_raw ? wp_hash_password( substr($aadhaar_raw, -4) ) : null,
        'pan_hash'     => $pan_raw ? wp_hash_password( $pan_raw ) : null,
        'bank_name'    => $data['bank_name'] ?? null,
        'account_last4'=> isset($data['account_number']) ? substr(preg_replace('/\D/','',$data['account_number']), -4) : null,
        'ifsc'         => $data['ifsc'] ?? null,
        'upi_id'       => $data['upi_id'] ?? null,
        'documents'    => !empty($data['documents']) ? wp_json_encode($data['documents']) : null,
        'created_at'   => current_time('mysql'),
        'updated_at'   => current_time('mysql'),
    ];
    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE user_id=%d", $user_id));
    if ($existing){
        $wpdb->update($table, $row, ['id' => $existing]);
        return $existing;
    }
    $wpdb->insert($table, $row);
    return $wpdb->insert_id;
}

function svntex2_kyc_get_status($user_id){
    global $wpdb; $table = $wpdb->prefix.'svntex_kyc_submissions';
    return $wpdb->get_var($wpdb->prepare("SELECT status FROM $table WHERE user_id=%d", $user_id)) ?: 'pending';
}

function svntex2_kyc_set_status($user_id, $status){
    global $wpdb; $table = $wpdb->prefix.'svntex_kyc_submissions';
    $wpdb->update($table, [ 'status' => sanitize_key($status), 'updated_at' => current_time('mysql') ], [ 'user_id' => $user_id ]);
}

?>
