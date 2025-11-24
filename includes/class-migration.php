<?php
/**
 * Migration Manager - Миграция настроек v2 → v3
 * 
 * Функциональность:
 * - Определение необходимости миграции
 * - Создание backup всех настроек v2
 * - Конвертация field mapping v2 → v3
 * - Конвертация data sources v2 → v3
 * - Rollback к v2 при необходимости
 * - Валидация результатов миграции
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migration Manager Class
 */
class YFGP_Migration {
    
    /**
     * Версия плагина для миграции
     */
    const TARGET_VERSION = '3.0.0';
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Получить singleton instance
     */
    public static function get_instance(): YFGP_Migration {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Проверить нужна ли миграция
     * 
     * @return bool Нужна ли миграция
     */
    public function needs_migration() {
        if (!$this->has_v2_data()) {
            return false;
        }
        
        $migration_status = get_option('yfgp_migration_status', false);
        if ($migration_status && $migration_status['version'] === self::TARGET_VERSION) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Проверить наличие данных v2
     * 
     * @return bool Есть ли данные v2
     */
    public function has_v2_data() {
        $v2_settings = get_option('yfgp_settings', false);
        $v2_field_mapping = get_option('yfgp_field_mapping', false);
        
        return ($v2_settings !== false || $v2_field_mapping !== false);
    }
    
    /**
     * Получить текущую версию плагина
     * 
     * @return string|false Версия плагина
     */
    public function get_current_version() {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $plugin_file = plugin_dir_path(__FILE__) . '../yandex-feed-generator-pro.php';
        $plugin_data = get_plugin_data($plugin_file);
        
        return isset($plugin_data['Version']) ? $plugin_data['Version'] : '2.0.0';
    }
    
    /**
     * Проверить наличие backup
     * 
     * @return bool Есть ли backup
     */
    public function has_migration_backup() {
        $backup = get_option('yfgp_migration_backup_v2', false);
        return ($backup !== false);
    }
    
    /**
     * Создать backup всех настроек v2
     * 
     * @return array|false Backup данные или false при ошибке
     */
    public function create_backup(): array {
        $backup = array(
            'version' => $this->get_current_version(),
            'timestamp' => current_time('timestamp'),
            'settings' => get_option('yfgp_settings', array()),
            'field_mapping' => get_option('yfgp_field_mapping', array()),
            'data_sources_v3' => get_option('yfgp_data_sources_v3', array()),
            'field_mapping_v3' => get_option('yfgp_field_mapping_v3', array())
        );
        
        $saved = update_option('yfgp_migration_backup_v2', $backup);
        
        if (!$saved) {
            return false;
        }
        
        return $backup;
    }
    
    /**
     * Восстановить из backup
     * 
     * @return bool Успех восстановления
     */
    public function restore_backup() {
        $backup = get_option('yfgp_migration_backup_v2', false);
        
        if (!$backup) {
            return false;
        }
        
        update_option('yfgp_settings', $backup['settings']);
        update_option('yfgp_field_mapping', $backup['field_mapping']);
        
        if (empty($backup['data_sources_v3'])) {
            delete_option('yfgp_data_sources_v3');
        }
        
        if (empty($backup['field_mapping_v3'])) {
            delete_option('yfgp_field_mapping_v3');
        }
        
        delete_option('yfgp_migration_status');
        
        return true;
    }
    
    /**
     * Удалить backup
     */
    public function delete_backup() {
        delete_option('yfgp_migration_backup_v2');
    }
    
    /**
     * Выполнить миграцию v2 → v3
     * 
     * @return array<string, mixed> Результат миграции
     */
    public function migrate_v2_to_v3(): array {
        $result = array(
            'success' => false,
            'message' => '',
            'details' => array(),
            'errors' => array()
        );
        
        // Шаг 1: Создать backup
        $backup = $this->create_backup();
        if (!$backup) {
            $result['errors'][] = 'Не удалось создать backup';
            $result['message'] = 'Миграция отменена: ошибка создания backup';
            return $result;
        }
        
        $result['details'][] = 'Backup создан успешно';
        
        // Шаг 2: Конвертировать field mapping
        $field_mapping_result = $this->convert_field_mapping();
        if (!$field_mapping_result['success']) {
            $result['errors'][] = $field_mapping_result['error'];
            $result['message'] = 'Ошибка конвертации field mapping';
            return $result;
        }
        
        $result['details'][] = sprintf(
            'Field mapping: %d полей сконвертировано',
            $field_mapping_result['converted_count']
        );
        
        // Шаг 3: Конвертировать data sources
        $data_sources_result = $this->convert_data_sources();
        if (!$data_sources_result['success']) {
            $result['errors'][] = $data_sources_result['error'];
            $result['message'] = 'Ошибка конвертации data sources';
            return $result;
        }
        
        $result['details'][] = 'Data sources сконвертированы успешно';
        
        // Шаг 4: Установить статус миграции
        $this->set_migration_status(true, $result['details']);
        
        $result['success'] = true;
        $result['message'] = 'Миграция v2→v3 выполнена успешно!';
        
        return $result;
    }
    
    /**
     * Конвертировать field mapping v2 → v3
     * 
     * @return array<string, mixed> Результат конвертации
     */
    private function convert_field_mapping() {
        $v2_mapping = get_option('yfgp_field_mapping', array());
        
        if (empty($v2_mapping)) {
            return array(
                'success' => true,
                'converted_count' => 0,
                'error' => null
            );
        }
        
        $v3_mapping = array();
        $converted_count = 0;
        
        foreach ($v2_mapping as $field_id => $field_value) {
            if (empty($field_value)) {
                continue;
            }
            
            $v3_mapping[$field_id] = array(
                'source_type' => 'meta_field',
                'source_field' => sanitize_text_field($field_value),
                'conditional_logic' => false,
                'operator' => null,
                'operator_value' => null
            );
            
            $converted_count++;
        }
        
        update_option('yfgp_field_mapping_v3', $v3_mapping);
        
        return array(
            'success' => true,
            'converted_count' => $converted_count,
            'error' => null
        );
    }
    
    /**
     * Конвертировать data sources v2 → v3
     * 
     * @return array<string, mixed> Результат конвертации
     */
    private function convert_data_sources() {
        $v2_settings = get_option('yfgp_settings', array());
        
        $v3_data_sources = array(
            'doctors_cpt' => isset($v2_settings['doctors_cpt']) ? $v2_settings['doctors_cpt'] : 'doctors',
            'clinics_cpt' => isset($v2_settings['clinics_cpt']) ? $v2_settings['clinics_cpt'] : 'clinics',
            'services_cpt' => isset($v2_settings['services_cpt']) ? $v2_settings['services_cpt'] : 'services',
            'offers_cpt' => isset($v2_settings['offers_cpt']) ? $v2_settings['offers_cpt'] : 'offers',
            'education_source' => array(
                'type' => 'none',
                'cpt' => '',
                'field' => ''
            ),
            'jobs_source' => array(
                'type' => 'none',
                'cpt' => '',
                'field' => ''
            ),
            'certificates_source' => array(
                'type' => 'none',
                'cpt' => '',
                'field' => ''
            ),
            'base_service_mode' => 'first'
        );
        
        update_option('yfgp_data_sources_v3', $v3_data_sources);
        
        return array(
            'success' => true,
            'error' => null
        );
    }
    
    /**
     * Установить статус миграции
     * 
     * @param bool $success Успешна ли миграция
     * @param array<string, mixed> $details Детали миграции
     */
    private function set_migration_status($success, $details = array()) {
        $status = array(
            'version' => self::TARGET_VERSION,
            'timestamp' => current_time('timestamp'),
            'success' => $success,
            'details' => $details
        );
        
        update_option('yfgp_migration_status', $status);
    }
    
    /**
     * Валидация результатов миграции
     * 
     * @return array<string, mixed> Результат валидации
     */
    public function validate_migration(): array {
        $validation = array(
            'valid' => true,
            'warnings' => array(),
            'errors' => array()
        );
        
        $v3_data_sources = get_option('yfgp_data_sources_v3', false);
        if (!$v3_data_sources) {
            $validation['errors'][] = 'Data sources v3 не найдены';
            $validation['valid'] = false;
        }
        
        $v3_field_mapping = get_option('yfgp_field_mapping_v3', false);
        if (!$v3_field_mapping) {
            $validation['warnings'][] = 'Field mapping v3 пустой (возможно у вас не было настроек v2)';
        }
        
        if (!$this->has_migration_backup()) {
            $validation['warnings'][] = 'Backup не найден (откат может быть невозможен)';
        }
        
        return $validation;
    }
    
    /**
     * Миграция legacy CPT ключей в опциях настроек
     * 
     * @since 4.18.0
     * @return bool true если миграция выполнена успешно
     */
    public function migrate_cpt_settings_option(): bool {
        $settings = get_option('yfgp_settings', array());
        
        if (empty($settings) || !is_array($settings)) {
            return false;
        }
        
        $cpt_map = array(
            'clinics_post_type' => 'cpt_clinics',
            'services_post_type' => 'cpt_services',
            'reviews_post_type' => 'cpt_reviews',
        );
        
        $migrated = false;
        
        // Мигрируем legacy ключи на новые
        foreach ($cpt_map as $legacy_key => $new_key) {
            if (isset($settings[$legacy_key]) && !isset($settings[$new_key])) {
                $settings[$new_key] = sanitize_key(trim($settings[$legacy_key]));
                unset($settings[$legacy_key]);
                $migrated = true;
            }
        }
        
        // Нормализация base_service_mode: удаляем compatibility режим
        if (isset($settings['base_service_mode']) && $settings['base_service_mode'] === 'compatibility') {
            $settings['base_service_mode'] = 'automatic';
            $migrated = true;
        }
        
        // Сохраняем настройки если была миграция
        if ($migrated) {
            update_option('yfgp_settings', $settings);
        }
        
        return $migrated;
    }

    /**
     * Получить отчёт о миграции
     * 
     * @return array<string, mixed> Отчёт о миграции
     */
    public function get_migration_report(): array {
        $migration_status = get_option('yfgp_migration_status', false);
        $backup = get_option('yfgp_migration_backup_v2', false);
        
        $report = array(
            'has_migrated' => ($migration_status !== false),
            'migration_status' => $migration_status,
            'has_backup' => ($backup !== false),
            'backup_info' => $backup ? array(
                'version' => $backup['version'],
                'timestamp' => $backup['timestamp'],
                'date' => date('Y-m-d H:i:s', $backup['timestamp'])
            ) : null,
            'can_rollback' => ($backup !== false)
        );
        
        return $report;
    }
}

/**
 * Глобальная функция для доступа к Migration Manager
 * 
 * @return YFGP_Migration
 */
function yfgp_migration() {
    return YFGP_Migration::get_instance();
}
