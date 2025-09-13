<?php
if (!defined('ABSPATH')) exit;

/**
 * Lightweight debug logger that captures PHP errors and fatals to uploads/svntex2-debug.log
 */
function svntex2_get_debug_log_path(){
    $up = wp_upload_dir();
    $dir = isset($up['basedir']) ? $up['basedir'] : WP_CONTENT_DIR;
    $path = trailingslashit($dir) . 'svntex2-debug.log';
    return $path;
}

function svntex2_write_log($level, $message, $context = []){
    $path = svntex2_get_debug_log_path();
    $dir = dirname($path);
    if(!is_dir($dir)) { wp_mkdir_p($dir); }
    $line = sprintf('[%s] [%s] %s %s', gmdate('c'), strtoupper($level), (string)$message, $context ? wp_json_encode($context) : '') . "\n";
    @file_put_contents($path, $line, FILE_APPEND);
}

function svntex2_log($message, $context = []){ svntex2_write_log('info', $message, $context); }

// Error and exception handlers
set_error_handler(function($errno, $errstr, $errfile, $errline){
    // Respect @ operator
    if(!(error_reporting() & $errno)) return false;
    $levels = [
        E_ERROR=>'error', E_WARNING=>'warning', E_PARSE=>'error', E_NOTICE=>'notice', E_CORE_ERROR=>'error',
        E_CORE_WARNING=>'warning', E_COMPILE_ERROR=>'error', E_COMPILE_WARNING=>'warning', E_USER_ERROR=>'error',
        E_USER_WARNING=>'warning', E_USER_NOTICE=>'notice', E_STRICT=>'notice', E_RECOVERABLE_ERROR=>'error', E_DEPRECATED=>'notice', E_USER_DEPRECATED=>'notice'
    ];
    $level = isset($levels[$errno]) ? $levels[$errno] : 'notice';
    svntex2_write_log($level, $errstr, [ 'file'=>$errfile, 'line'=>$errline ]);
    return false; // let WP/PHP continue normal handling
});

set_exception_handler(function($ex){
    svntex2_write_log('exception', $ex->getMessage(), [ 'file'=>$ex->getFile(), 'line'=>$ex->getLine(), 'trace'=>$ex->getTraceAsString() ]);
});

register_shutdown_function(function(){
    $e = error_get_last();
    if($e && in_array($e['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR,E_RECOVERABLE_ERROR], true)){
        svntex2_write_log('fatal', $e['message'], [ 'file'=>$e['file'], 'line'=>$e['line'] ]);
    }
});

// Optional: admin can fetch last N lines (kept simple and safe)
add_action('rest_api_init', function(){
    register_rest_route('svntex2/v1','/debug/log', [
        'methods'=>'GET',
        'permission_callback'=>function(){ return current_user_can('manage_options'); },
        'callback'=>function(WP_REST_Request $r){
            $n = min( max( (int)$r->get_param('lines'), 10 ), 1000);
            $path = svntex2_get_debug_log_path();
            if(!file_exists($path)) return [ 'path'=>$path, 'lines'=>[] ];
            $lines = [];
            $fh = fopen($path,'r'); if(!$fh) return [ 'path'=>$path, 'lines'=>[] ];
            // tail N lines
            $buffer = 4096; $pos = -1; $chunk = '';
            $stat = fstat($fh); $size = $stat['size']; $lineCount = 0; $output = '';
            while($size > 0 && $lineCount <= $n){
                $seek = max($size - $buffer, 0);
                fseek($fh, $seek);
                $read = fread($fh, $size - $seek);
                $output = $read . $output;
                $size = $seek; $lineCount = substr_count($output, "\n");
            }
            fclose($fh);
            $lines = array_slice(explode("\n", trim($output)), -$n);
            return [ 'path'=>$path, 'lines'=>$lines ];
        }
    ]);
});

// No closing tag
