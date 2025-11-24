<?php
/**
 * Yandex Feed Generator Pro - Field Normalization Service
 * 
 * Сервисный слой для нормализации значений полей (speciality, boolean, XML escaping)
 * 
 * @package YandexFeedGeneratorPro
 * @since 4.18.17
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
require_once YFGP_PLUGIN_DIR . 'includes/trait-feed-generator-shared.php';

class YFGP_Field_Normalization_Service {
    use YFGP_Feed_Generator_Shared_Trait;

    /**
     * @var YFGP_Field_Mapper_Unified Экземпляр Unified Mapper
     */
    private YFGP_Field_Mapper_Unified $field_mapper;

    /**
     * Конструктор
     * 
     * @param YFGP_Field_Mapper_Unified $field_mapper Экземпляр Unified Mapper
     */
    public function __construct(YFGP_Field_Mapper_Unified $field_mapper) {
        $this->field_mapper = $field_mapper;
    }

    /**
     * Нормализация поля speciality
     * 
     * @param \WP_Post $post Пост
     * @param array<string, mixed> $field_config Конфигурация поля
     * @param mixed $raw_value Сырое значение
     * @return array<string> Массив нормализованных specialities
     */
    public function normalizeSpecialityField(\WP_Post $post, array $field_config, $raw_value): array {
        return $this->field_mapper->normalizeSpecialityField($post, $field_config, $raw_value);
    }

    /**
     * Нормализация boolean строки
     * 
     * Использует метод из trait YFGP_Feed_Generator_Shared_Trait
     * 
     * @param mixed $value Значение для нормализации
     * @param bool $default Значение по умолчанию
     * @return string 'true' или 'false'
     */
    public function normalizeBooleanString($value, bool $default = false): string {
        return $this->normalize_boolean_string($value, $default);
    }

    /**
     * Экранирование XML
     * 
     * Использует метод из trait YFGP_Feed_Generator_Shared_Trait
     * 
     * @param string $string Строка для экранирования
     * @return string Экранированная строка
     */
    public function escapeXml(string $string): string {
        return $this->escape_xml($string);
    }
}

