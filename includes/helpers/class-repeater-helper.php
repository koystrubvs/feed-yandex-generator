<?php
/**
 * Repeater Helper - Обработка repeater полей (ACF + JetEngine)
 * 
 * Этот класс отвечает за:
 * - Получение данных из ACF Repeater
 * - Получение данных из JetEngine Repeater
 * - Форматирование данных для YML генерации
 * - Поддержку вложенных repeater (nested)
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repeater Helper Class
 */
class YFGP_Repeater_Helper {
    
    /**
     * Кэш для повышения производительности
     */
    private $cache = array();
    
    /**
     * Получить данные repeater по конфигурации
     * 
     * @param int $post_id ID поста
     * @param array<string, mixed> $field_config Конфигурация поля
     * @return array<string, mixed> Массив элементов repeater
     */
    public function get_data($post_id, $field_config): array {
        // Валидация
        if (empty($field_config['source_type']) || empty($field_config['source_field'])) {
            return array();
        }
        
        // Проверить кэш
        $cache_key = $this->get_cache_key($post_id, $field_config);
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        $data = array();
        
        // Обработка по типу
        switch ($field_config['source_type']) {
            case 'repeater_acf':
                $data = $this->get_acf_repeater($post_id, $field_config['source_field']);
                break;
            
            case 'repeater_jetengine':
                $data = $this->get_jetengine_repeater($post_id, $field_config['source_field']);
                break;
            
            default:
                $data = array();
        }
        
        // Сохранить в кэш
        $this->cache[$cache_key] = $data;
        
        return $data;
    }
    
    /**
     * Получить данные из ACF Repeater
     * 
     * @param int $post_id ID поста
     * @param string $field_name Название repeater поля
     * @return array<string, mixed> Массив элементов
     */
    public function get_acf_repeater($post_id, $field_name): array {
        if (!function_exists('get_field')) {
            return array();
        }
        
        // Получить данные repeater
        $repeater_data = get_field($field_name, $post_id, false);
        
        if (!is_array($repeater_data) || empty($repeater_data)) {
            return array();
        }
        
        $formatted_data = array();
        
        foreach ($repeater_data as $row) {
            if (is_array($row)) {
                $formatted_data[] = $this->format_acf_row($row);
            }
        }
        
        return $formatted_data;
    }
    
    /**
     * Получить данные из JetEngine Repeater
     * 
     * @param int $post_id ID поста
     * @param string $field_name Название repeater поля
     * @return array<string, mixed> Массив элементов
     */
    public function get_jetengine_repeater($post_id, $field_name): array {
        // JetEngine repeater хранятся как мета-поля
        $repeater_data = get_post_meta($post_id, $field_name, true);
        
        // v4.18.1: Нормализация JSON строки в массив (если JetEngine сохраняет как JSON)
        if (is_string($repeater_data) && !empty($repeater_data)) {
            $decoded = json_decode($repeater_data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $repeater_data = $decoded;
            }
        }
        
        if (!is_array($repeater_data) || empty($repeater_data)) {
            return array();
        }
        
        $formatted_data = array();
        
        foreach ($repeater_data as $row) {
            if (is_array($row)) {
                $formatted_data[] = $this->format_jetengine_row($row);
            }
        }
        
        return $formatted_data;
    }
    
    /**
     * Форматировать строку ACF repeater
     * 
     * @param array<string, mixed> $row Строка repeater
     * @return array<string, mixed> Отформатированная строка
     */
    private function format_acf_row($row): array {
        $formatted = array();
        
        foreach ($row as $key => $value) {
            // Пропустить системные ключи ACF
            if (strpos($key, '_') === 0) {
                continue;
            }
            
            // Обработка вложенных repeater (nested)
            if (is_array($value) && !empty($value)) {
                // Проверить если это вложенный repeater
                $is_nested_repeater = true;
                foreach ($value as $item) {
                    if (!is_array($item)) {
                        $is_nested_repeater = false;
                        break;
                    }
                }
                
                if ($is_nested_repeater) {
                    // Вложенный repeater - рекурсивно обработать
                    $formatted[$key] = array();
                    foreach ($value as $nested_row) {
                        $formatted[$key][] = $this->format_acf_row($nested_row);
                    }
                } else {
                    // Обычный массив - оставить как есть
                    $formatted[$key] = $value;
                }
            } else {
                // Простое значение
                $formatted[$key] = $value;
            }
        }
        
        return $formatted;
    }
    
    /**
     * Форматировать строку JetEngine repeater
     * 
     * @param array<string, mixed> $row Строка repeater
     * @return array<string, mixed> Отформатированная строка
     */
    private function format_jetengine_row($row): array {
        $formatted = array();
        
        foreach ($row as $key => $value) {
            // Обработка вложенных repeater (nested)
            if (is_array($value) && !empty($value)) {
                // Проверить если это вложенный repeater
                $is_nested_repeater = true;
                foreach ($value as $item) {
                    if (!is_array($item)) {
                        $is_nested_repeater = false;
                        break;
                    }
                }
                
                if ($is_nested_repeater) {
                    // Вложенный repeater - рекурсивно обработать
                    $formatted[$key] = array();
                    foreach ($value as $nested_row) {
                        $formatted[$key][] = $this->format_jetengine_row($nested_row);
                    }
                } else {
                    // Обычный массив - оставить как есть
                    $formatted[$key] = $value;
                }
            } else {
                // Простое значение
                $formatted[$key] = $value;
            }
        }
        
        return $formatted;
    }
    
    /**
     * Получить подполя repeater (для UI)
     * 
     * @param string $field_name Название repeater поля
     * @param string $post_type Тип поста
     * @return array<string, mixed> Массив подполей
     */
    public function get_sub_fields($field_name, $post_type = 'doctors'): array {
        // ACF
        if (function_exists('acf_get_field')) {
            $field = acf_get_field($field_name);
            
            if ($field && $field['type'] === 'repeater' && !empty($field['sub_fields'])) {
                $sub_fields = array();
                
                foreach ($field['sub_fields'] as $sub_field) {
                    $sub_fields[] = array(
                        'name' => $sub_field['name'],
                        'label' => $sub_field['label'],
                        'type' => $sub_field['type'],
                        'key' => $sub_field['key']
                    );
                }
                
                return $sub_fields;
            }
        }
        
        // JetEngine
        if (function_exists('jet_engine') && class_exists('Jet_Engine\\Meta_Boxes\\Manager')) {
            $meta_boxes_manager = jet_engine()->meta_boxes;
            $meta_boxes = $meta_boxes_manager->get_registered_meta_boxes();
            
            foreach ($meta_boxes as $meta_box) {
                $post_types = isset($meta_box['args']['object_type']) ? $meta_box['args']['object_type'] : array();
                
                if (!in_array($post_type, $post_types)) {
                    continue;
                }
                
                if (!empty($meta_box['meta_fields'])) {
                    foreach ($meta_box['meta_fields'] as $field) {
                        if ($field['name'] === $field_name && $field['type'] === 'repeater') {
                            $sub_fields = array();
                            
                            if (!empty($field['repeater-fields'])) {
                                foreach ($field['repeater-fields'] as $sub_field) {
                                    $sub_fields[] = array(
                                        'name' => $sub_field['name'],
                                        'label' => $sub_field['title'],
                                        'type' => $sub_field['type']
                                    );
                                }
                            }
                            
                            return $sub_fields;
                        }
                    }
                }
            }
        }
        
        return array();
    }
    
    /**
     * Генерировать кэш ключ
     * 
     * @param int $post_id ID поста
     * @param array<string, mixed> $field_config Конфигурация поля
     * @return string Кэш ключ
     */
    private function get_cache_key($post_id, $field_config): string {
        return 'repeater_' . $post_id . '_' . md5(json_encode($field_config));
    }
    
    /**
     * Очистить кэш
     */
    public function clear_cache(): void {
        $this->cache = array();
    }
}
