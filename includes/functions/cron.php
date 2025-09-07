<?php
/** Cron + Profit distribution scaffold */
if (!defined('ABSPATH')) exit;

// Schedule monthly distribution event
add_action('init', function(){
    if (!wp_next_scheduled('svntex2_monthly_distribution')) {
        wp_schedule_event(strtotime('first day of next month 00:05:00'), 'monthly', 'svntex2_monthly_distribution');
    }
});

// Register custom schedule if not exists (monthly)
add_filter('cron_schedules', function($schedules){
    if (!isset($schedules['monthly'])) {
        $schedules['monthly'] = [ 'interval' => 30 * DAY_IN_SECONDS, 'display' => __('Once Monthly','svntex2') ];
    }
    return $schedules;
});

add_action('svntex2_monthly_distribution','svntex2_run_monthly_distribution');
function svntex2_run_monthly_distribution(){
    // Placeholder: compute company profit & share logic.
    do_action('svntex2_distribution_run');
}

?>
