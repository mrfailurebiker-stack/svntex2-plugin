<?php
/**
 * Hostinger debug bootstrap (temporary helper)
 *
 * Purpose: When accessed by an authenticated WP admin, this will:
 * - Ensure PHP error logging is enabled
 * - Point PHP error_log to wp-content/debug.log
 * - Create the file if missing and write a test line
 * - Also write to uploads/svntex2-debug.log if the plugin logger is loaded
 *
 * Access: You must be logged into WordPress as an administrator in the same browser.
 * Response: JSON with the debug log path and a hint.
 */
header('Content-Type: application/json');
// Load WordPress
$root = __DIR__;
$wp_load = $root . '/wp-load.php';
if (!file_exists($wp_load)) {
    http_response_code(500);
    echo json_encode([ 'ok'=>false, 'error'=>'wp-load.php not found relative to script location' ]);
    exit;
}
require_once $wp_load;

if ( ! function_exists('is_user_logged_in') || ! is_user_logged_in() || ! current_user_can('manage_options') ) {
    http_response_code(403);
    echo json_encode([ 'ok'=>false, 'error'=>'forbidden: admin login required' ]);
    exit;
}

// Target log path
$content_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : (ABSPATH . 'wp-content');
$log_path = rtrim($content_dir,'/').'/debug.log';

// Ensure directory exists and is writable
if ( ! is_dir($content_dir) ) {
    @mkdir($content_dir, 0755, true);
}

// Enable PHP error logging to wp-content/debug.log
@ini_set('log_errors', '1');
@ini_set('error_log', $log_path);

// Touch the file and add a test line
if ( ! file_exists($log_path) ) { @file_put_contents($log_path, ""); @chmod($log_path, 0644); }
@error_log('[SVNTEX] Debug bootstrap ping at '.gmdate('c'));

// If plugin logger is present, also emit a line there
if ( function_exists('svntex2_log') ) {
    svntex2_log('hostinger-debug bootstrap invoked', [ 'ts'=>gmdate('c') ]);
}

// Try to read last few lines to confirm
$tail = [];
if ( is_readable($log_path) ) {
    $lines = @file($log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ( is_array($lines) ) { $tail = array_slice($lines, -5); }
}

echo json_encode([
    'ok' => true,
    'path' => $log_path,
    'hint' => 'Open wp-content/debug.log in File Manager. Share the last 30-50 lines here.',
    'tailPreview' => $tail,
]);
exit;
// End of file
