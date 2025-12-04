<?php
/**
 * Cron Manager - Automatic feed generation scheduling
 * 
 * @package Yandex_Feed_Generator_Pro
 * @version 5.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class YFGP_Cron_Manager {
    
    /**
     * Cron hook name
     */
    private const CRON_HOOK = 'yfgp_auto_update_feed';
    
    /**
     * Get available cron intervals
     * 
     * @return array<string, string> Intervals for UI
     */
    public function get_intervals(): array {
        $wp_schedules = wp_get_schedules();
        
        $intervals = array(
            'disabled' => __('Disabled', 'yandex-feed-generator'),
        );
        
        if (isset($wp_schedules['hourly'])) {
            $intervals['hourly'] = __('Hourly', 'yandex-feed-generator');
        }
        if (isset($wp_schedules['twicedaily'])) {
            $intervals['twicedaily'] = __('Twice daily', 'yandex-feed-generator');
        }
        if (isset($wp_schedules['daily'])) {
            $intervals['daily'] = __('Daily', 'yandex-feed-generator');
        }
        if (isset($wp_schedules['weekly'])) {
            $intervals['weekly'] = __('Weekly', 'yandex-feed-generator');
        }
        
        return $intervals;
    }
    
    /**
     * Schedule automatic feed update
     * 
     * @param string $interval Interval (hourly, daily, etc)
     * @return bool Success
     */
    public function schedule_feed_update(string $interval = 'daily'): bool {
        // First unschedule any existing event
        $this->unschedule_feed_update();
        
        if ($interval === 'disabled') {
            return true;
        }
        
        $result = wp_schedule_event(time(), $interval, self::CRON_HOOK);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('YFGP Cron: Scheduled feed update with interval: ' . $interval);
        }
        
        return $result !== false;
    }
    
    /**
     * Unschedule automatic feed update
     * 
     * @return bool Success
     */
    public function unschedule_feed_update(): bool {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        
        if (!$timestamp) {
            // Nothing to unschedule
            return true;
        }
        
        $result = wp_unschedule_event($timestamp, self::CRON_HOOK);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('YFGP Cron: Unscheduled feed update, result: ' . var_export($result, true));
        }
        
        // WP 5.1+ returns bool, older versions return void (null)
        // Check if event is still scheduled to verify success
        return !$this->is_scheduled();
    }
    
    /**
     * Check if auto-update is scheduled
     * 
     * @return bool
     */
    public function is_scheduled(): bool {
        return wp_next_scheduled(self::CRON_HOOK) !== false;
    }
    
    /**
     * Get next run timestamp
     * 
     * @return int|false Timestamp or false if not scheduled
     */
    public function get_next_run() {
        return wp_next_scheduled(self::CRON_HOOK);
    }
}
