<?php
/**
 * Класс для управления автоматической генерацией фида по расписанию
 * 
 * @package Yandex_Feed_Generator_Pro
 * @version 2.3.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class YFGP_Cron_Manager {
    
    /**
     * Получить доступные интервалы для cron
     * 
     * @return array<string, mixed> Массив интервалов для UI
     */
    public function get_intervals(): array {
        $wp_schedules = wp_get_schedules();
        
        $intervals = array(
            'disabled' => 'Отключено',
        );
        
        // Добавляем стандартные WordPress интервалы
        if (isset($wp_schedules['hourly'])) {
            $intervals['hourly'] = 'Каждый час';
        }
        if (isset($wp_schedules['twicedaily'])) {
            $intervals['twicedaily'] = 'Дважды в день';
        }
        if (isset($wp_schedules['daily'])) {
            $intervals['daily'] = 'Ежедневно';
        }
        if (isset($wp_schedules['weekly'])) {
            $intervals['weekly'] = 'Еженедельно';
        }
        
        return $intervals;
    }
    
    /**
     * Запланировать автоматическое обновление фида
     * 
     * @param string $interval Интервал (hourly, daily, etc)
     * @return bool
     */
    public function schedule_feed_update($interval = 'daily') {
        // TODO: Будет реализовано в v2.4.0
        // wp_schedule_event(time(), $interval, 'yfgp_auto_update_feed');
        
        error_log('YFGP Cron: schedule_feed_update() вызван для interval: ' . $interval);
        error_log('YFGP Cron: Функционал будет реализован в v2.4.0');
        
        return true;
    }
    
    /**
     * Отменить автоматическое обновление фида
     * 
     * @return bool
     */
    public function unschedule_feed_update() {
        // TODO: Будет реализовано в v2.4.0
        // $timestamp = wp_next_scheduled('yfgp_auto_update_feed');
        // if ($timestamp) {
        //     wp_unschedule_event($timestamp, 'yfgp_auto_update_feed');
        // }
        
        error_log('YFGP Cron: unschedule_feed_update() вызван');
        error_log('YFGP Cron: Функционал будет реализован в v2.4.0');
        
        return true;
    }
    
    /**
     * Проверить запланировано ли автообновление
     * 
     * @return bool
     */
    public function is_scheduled() {
        // TODO: Будет реализовано в v2.4.0
        // return wp_next_scheduled('yfgp_auto_update_feed') !== false;
        
        return false;
    }
    
    /**
     * Получить время следующего запуска
     * 
     * @return int|false Timestamp или false если не запланировано
     */
    public function get_next_run() {
        // TODO: Будет реализовано в v2.4.0
        // return wp_next_scheduled('yfgp_auto_update_feed');
        
        return false;
    }
}
