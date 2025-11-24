<?php
/**
 * Data Inheritance Manager - Наследование данных в офферах
 * 
 * Концепция: Single Source of Truth
 * - Офферы НЕ хранят дублированные данные
 * - Данные наследуются динамически от doctor/clinic/service
 * - Возможность переопределения (override) для специфичных офферов
 * - Кэширование для производительности
 * 
 * Наследуемые поля:
 * - url (от doctor)
 * - online_schedule (от doctor)
 * - oms (от doctor/clinic)
 * - children_appointment (от doctor)
 * - adult_appointment (от doctor)
 * - house_call (от doctor)
 * - telemed (от doctor)
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Data Inheritance Manager Class
 */
class YFGP_Data_Inheritance_Manager {
    
    /**
     * Кэш для повышения производительности
     */
    private $cache = array();
    
    /**
     * Список наследуемых полей и их источников
     */
    private $inheritable_fields = array(
        'url' => 'doctor',
        'online_schedule' => 'doctor',
        'oms' => 'doctor', // может быть и от clinic
        'children_appointment' => 'doctor',
        'adult_appointment' => 'doctor',
        'house_call' => 'doctor',
        'telemed' => 'doctor',
    );
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Получить singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Получить унаследованное значение поля
     * 
     * @param array<string, mixed> $offer Данные оффера (doctor_id, clinic_id, service_id)
     * @param string $field_name Название поля
     * @param string|null $source Источник (doctor/clinic/service) - опционально
     * @return mixed Значение поля
     */
    public function get_inherited_value($offer, $field_name, $source = null) {
        // Валидация
        if (empty($offer) || empty($field_name)) {
            return null;
        }
        
        // 1. Проверить override в оффере
        if ($this->has_override($offer, $field_name)) {
            return $this->get_override_value($offer, $field_name);
        }
        
        // 2. Проверить кэш
        $cache_key = $this->get_cache_key($offer, $field_name);
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        // 3. Определить источник данных
        if ($source === null) {
            $source = $this->get_field_source($field_name);
        }
        
        // 4. Получить значение от источника
        $value = $this->fetch_from_source($offer, $field_name, $source);
        
        // 5. Сохранить в кэш
        $this->cache[$cache_key] = $value;
        
        return $value;
    }
    
    /**
     * Проверить есть ли override для поля
     * 
     * @param array<string, mixed> $offer Данные оффера
     * @param string $field_name Название поля
     * @return bool Есть ли override
     */
    public function has_override($offer, $field_name) {
        if (empty($offer['overrides'])) {
            return false;
        }
        
        return isset($offer['overrides'][$field_name]);
    }
    
    /**
     * Получить значение override
     * 
     * @param array<string, mixed> $offer Данные оффера
     * @param string $field_name Название поля
     * @return mixed Значение override
     */
    private function get_override_value($offer, $field_name) {
        if (empty($offer['overrides'][$field_name])) {
            return null;
        }
        
        return $offer['overrides'][$field_name];
    }
    
    /**
     * Определить источник данных для поля
     * 
     * @param string $field_name Название поля
     * @return string Источник (doctor/clinic/service)
     */
    private function get_field_source($field_name) {
        if (isset($this->inheritable_fields[$field_name])) {
            return $this->inheritable_fields[$field_name];
        }
        
        // По умолчанию - от doctor
        return 'doctor';
    }
    
    /**
     * Получить значение от источника
     * 
     * @param array<string, mixed> $offer Данные оффера
     * @param string $field_name Название поля
     * @param string $source Источник (doctor/clinic/service)
     * @return mixed Значение поля
     */
    private function fetch_from_source($offer, $field_name, $source) {
        $post_id = null;
        
        // Определить ID поста-источника
        switch ($source) {
            case 'doctor':
                $post_id = isset($offer['doctor_id']) ? $offer['doctor_id'] : null;
                break;
            
            case 'clinic':
                $post_id = isset($offer['clinic_id']) ? $offer['clinic_id'] : null;
                break;
            
            case 'service':
                $post_id = isset($offer['service_id']) ? $offer['service_id'] : null;
                break;
            
            default:
                $post_id = null;
        }
        
        if (empty($post_id)) {
            return null;
        }
        
        // Получить значение поля через Field Mapper V3
        $field_mapper = yfgp_field_mapper_v3();
        
        if (!$field_mapper) {
            // Fallback на прямое получение мета-поля
            return get_post_meta($post_id, $field_name, true);
        }
        
        // Получить через Field Mapper V3 (может быть сложная конфигурация)
        $field_config = $this->get_field_config_for_inheritance($field_name);
        
        return $field_mapper->get_field_value($post_id, $field_config);
    }
    
    /**
     * Получить конфигурацию поля для наследования
     * 
     * @param string $field_name Название поля
     * @return array<string, mixed> Конфигурация поля
     */
    private function get_field_config_for_inheritance($field_name) {
        // По умолчанию - мета-поле
        return array(
            'source_type' => 'meta_field',
            'source_field' => $field_name
        );
    }
    
    /**
     * Получить все наследуемые значения для оффера
     * 
     * @param array<string, mixed> $offer Данные оффера
     * @param array<string, mixed> $fields Список полей для получения (опционально)
     * @return array<string, mixed> Массив значений [field_name => value]
     */
    public function get_all_inherited_values($offer, $fields = null) {
        if ($fields === null) {
            // Получить все наследуемые поля
            $fields = array_keys($this->inheritable_fields);
        }
        
        $values = array();
        
        foreach ($fields as $field_name) {
            $values[$field_name] = $this->get_inherited_value($offer, $field_name);
        }
        
        return $values;
    }
    
    /**
     * Установить override для поля
     * 
     * @param array &$offer Данные оффера (передается по ссылке)
     * @param string $field_name Название поля
     * @param mixed $value Значение override
     */
    public function set_override(&$offer, $field_name, $value) {
        if (!isset($offer['overrides'])) {
            $offer['overrides'] = array();
        }
        
        $offer['overrides'][$field_name] = $value;
    }
    
    /**
     * Удалить override для поля
     * 
     * @param array &$offer Данные оффера (передается по ссылке)
     * @param string $field_name Название поля
     */
    public function remove_override(&$offer, $field_name) {
        if (isset($offer['overrides'][$field_name])) {
            unset($offer['overrides'][$field_name]);
        }
    }
    
    /**
     * Получить список всех наследуемых полей
     * 
     * @return array<string, mixed> Список полей и их источников
     */
    public function get_inheritable_fields() {
        return $this->inheritable_fields;
    }
    
    /**
     * Добавить кастомное наследуемое поле
     * 
     * @param string $field_name Название поля
     * @param string $source Источник (doctor/clinic/service)
     */
    public function add_inheritable_field($field_name, $source = 'doctor') {
        $this->inheritable_fields[$field_name] = $source;
    }
    
    /**
     * Batch получение унаследованных значений для массива офферов
     * 
     * @param array<string, mixed> $offers Массив офферов
     * @param string $field_name Название поля
     * @return array<string, mixed> Массив значений [offer_index => value]
     */
    public function get_inherited_values_batch($offers, $field_name) {
        if (empty($offers) || !is_array($offers)) {
            return array();
        }
        
        $values = array();
        
        foreach ($offers as $index => $offer) {
            $values[$index] = $this->get_inherited_value($offer, $field_name);
        }
        
        return $values;
    }
    
    /**
     * Генерировать кэш ключ
     * 
     * @param array<string, mixed> $offer Данные оффера
     * @param string $field_name Название поля
     * @return string Кэш ключ
     */
    private function get_cache_key($offer, $field_name) {
        $offer_id = isset($offer['id']) ? $offer['id'] : md5(json_encode($offer));
        return 'inheritance_' . $offer_id . '_' . $field_name;
    }
    
    /**
     * Очистить кэш
     * 
     * @param array|null $offer Конкретный оффер (опционально)
     */
    public function clear_cache($offer = null) {
        if ($offer === null) {
            // Очистить весь кэш
            $this->cache = array();
        } else {
            // Очистить кэш для конкретного оффера
            $offer_id = isset($offer['id']) ? $offer['id'] : md5(json_encode($offer));
            
            foreach ($this->cache as $key => $value) {
                if (strpos($key, 'inheritance_' . $offer_id) === 0) {
                    unset($this->cache[$key]);
                }
            }
        }
    }
}

/**
 * Глобальная функция для доступа к Data Inheritance Manager
 * 
 * @return YFGP_Data_Inheritance_Manager
 */
function yfgp_inheritance_manager() {
    return YFGP_Data_Inheritance_Manager::get_instance();
}
