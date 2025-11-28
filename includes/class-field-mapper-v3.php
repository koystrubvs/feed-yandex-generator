<?php
/**
 * Field Mapper V3 Facade - Прокси для V3 AJAX Testing
 * 
 * Простой прокси к Unified классу.
 * V3 уже использует V3 array config format, так что нет необходимости в конвертации!
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.0.0
 * @version 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Загрузить Unified класс
require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
// v4.18.39: Load base class for common logic
require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-base.php';

class YFGP_Field_Mapper_V3 extends YFGP_Field_Mapper_Base {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Unified mapper instance (v4.18.39: Use protected property from base class)
     */
    private $unified;
    
    /**
     * Helper классы (сохранены для обратной совместимости)
     */
    private $repeater_helper = null;
    private $relationship_helper = null;
    
    /**
     * Кэш (делегируется в Unified)
     */
    private $cache = array();
    
    /**
     * Get singleton instance
     * 
     * @since 4.1.2
     * @return YFGP_Field_Mapper_V3
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Конструктор
     */
    public function __construct() {
        // v4.18.39: Use base class method to get unified mapper
        $this->unified_mapper = $this->get_unified_mapper();
        // Keep $unified for backward compatibility
        $this->unified = $this->unified_mapper;
        
        // Загрузить helper классы (V3 использовал их)
        if (file_exists(YFGP_PLUGIN_DIR . 'includes/helpers/class-repeater-helper.php')) {
            require_once YFGP_PLUGIN_DIR . 'includes/helpers/class-repeater-helper.php';
            if (class_exists('YFGP_Repeater_Helper')) {
                $this->repeater_helper = new YFGP_Repeater_Helper();
            }
        }
        
        if (file_exists(YFGP_PLUGIN_DIR . 'includes/helpers/class-relationship-helper.php')) {
            require_once YFGP_PLUGIN_DIR . 'includes/helpers/class-relationship-helper.php';
            if (class_exists('YFGP_Relationship_Helper')) {
                $this->relationship_helper = new YFGP_Relationship_Helper();
            }
        }
    }
    
    // ========================================================================
    // PROXY LAYER - Прямой вызов Unified (без конвертации!)
    // ========================================================================
    
    /**
     * Получить значение поля по конфигурации v3.0
     * 
     * PROXY к Unified::getFieldValue()
     * 
     * @since 3.0.0 (original)
     * @since 4.0.0 (refactored to proxy Unified)
     * @param int $post_id ID поста
     * @param array<string, mixed> $field_config V3 конфигурация поля
     * @return mixed Значение поля
     */
    public function get_field_value($post_id, $field_config) {
        // Прямой вызов Unified (формат уже V3!)
        return $this->unified->getFieldValue($post_id, $field_config);
    }
    
    /**
     * Получить доступные поля для post_type
     * 
     * PROXY к Unified::getAvailableFields()
     * 
     * @since 3.0.0 (original)
     * @since 4.0.0 (refactored to proxy Unified)
     * @param string $post_type Тип поста
     * @return array<string, mixed> Массив доступных полей
     */
    public function get_available_fields($post_type = 'doctors') {
        // Вызвать Unified
        return $this->unified->getAvailableFields($post_type);
    }
    
    // ========================================================================
    // CACHE METHODS - Делегируются в Unified
    // ========================================================================
    
    /**
     * Очистить кэш (v4.18.39: Uses base class method)
     * 
     * @since 3.0.0 (original)
     * @since 4.0.0 (delegates to Unified)
     * @since 4.18.39 (uses base class method)
     */
    public function clear_cache(): void {
        // Use base class method
        parent::clear_cache();
        // Also clear via unified for backward compatibility
        if ($this->unified !== null && method_exists($this->unified, 'clearCache')) {
            $this->unified->clearCache();
        }
    }
    
    /**
     * Статистика кэша
     * 
     * @since 3.0.0 (original)
     * @since 4.0.0 (delegates to Unified)
     * @return array
     */
    public function get_cache_stats() {
        return $this->unified->getCacheStats();
    }
    
    // ========================================================================
    // REPEATER/RELATIONSHIP SUBFIELDS - Делегируются в Unified
    // ========================================================================
    
    /**
     * Получить ACF repeater subfields
     * 
     * PROXY к Unified::getAcfRepeaterSubfields()
     * 
     * @since 3.0.0 (original)
     * @since 4.0.0 (proxies to Unified)
     * @param string $field_name Название repeater поля
     * @return array<string, mixed> Список подполей
     */
    public function get_acf_repeater_subfields($field_name) {
        return $this->unified->getAcfRepeaterSubfields($field_name);
    }
    
    /**
     * Получить JetEngine repeater subfields
     * 
     * PROXY к Unified::getJetengineRepeaterSubfields()
     * 
     * @since 3.0.0 (original)
     * @since 4.0.0 (proxies to Unified)
     * @param string $field_name Название repeater поля
     * @param string $post_type Тип поста
     * @return array<string, mixed> Список подполей
     */
    public function get_jetengine_repeater_subfields($field_name, $post_type = 'doctors') {
        return $this->unified->getJetengineRepeaterSubfields($field_name, $post_type);
    }
    
    // ========================================================================
    // VALIDATION - Делегируется в Unified
    // ========================================================================
    
    /**
     * Валидация конфигурации поля
     * 
     * PROXY к Unified::validateFieldConfig()
     * 
     * @since 3.0.0 (original)
     * @since 4.0.0 (proxies to Unified)
     * @param array<string, mixed> $config Конфигурация для валидации
     * @return array<string, mixed> [valid => bool, errors => array]
     */
    public function validate_field_config($config) {
        return $this->unified->validateFieldConfig($config);
    }
    
    // ========================================================================
    // CONVERTERS - Data format conversion utilities
    // ========================================================================
    
    /**
     * Convert structured time data to readable text
     * 
     * @since 4.1.2
     * @param mixed $time_data Array of time schedule or string
     * @return string Formatted text like "Пн: 09:00-18:00, Вт: 09:00-18:00"
     */
    public function convert_time_to_text($time_data) {
        // If already text, return as is
        if (!is_array($time_data)) {
            return $time_data;
        }
        
        $days_ru = array(
            'monday' => 'Пн',
            'tuesday' => 'Вт',
            'wednesday' => 'Ср',
            'thursday' => 'Чт',
            'friday' => 'Пт',
            'saturday' => 'Сб',
            'sunday' => 'Вс'
        );
        
        $schedule = array();
        foreach ($time_data as $day => $hours) {
            if (is_array($hours) && !empty($hours['start']) && !empty($hours['end'])) {
                $day_ru = $days_ru[$day] ?? ucfirst($day);
                $schedule[] = "{$day_ru}: {$hours['start']}-{$hours['end']}";
            } elseif (!empty($hours)) {
                // If hours is a string (simple format)
                $day_ru = $days_ru[$day] ?? ucfirst($day);
                $schedule[] = "{$day_ru}: {$hours}";
            }
        }
        
        return !empty($schedule) ? implode(', ', $schedule) : '';
    }
    
    // ========================================================================
    // DEPRECATED V3 PRIVATE METHODS - Больше не нужны!
    // ========================================================================
    
    /**
     * get_meta_field() - DEPRECATED
     * get_relationship_basic() - DEPRECATED
     * get_relationship_nested_basic() - DEPRECATED
     * get_taxonomy_terms() - DEPRECATED
     * get_taxonomy_meta() - DEPRECATED
     * get_fixed_value() - DEPRECATED
     * parse_full_name() - DEPRECATED
     * count_doctor_reviews() - DEPRECATED
     * get_cache_key() - DEPRECATED
     * estimate_cache_memory() - DEPRECATED
     * 
     * Все эти методы теперь в Unified!
     * V3 Facade просто вызывает Unified::getFieldValue() который делает всё внутри
     */
}
