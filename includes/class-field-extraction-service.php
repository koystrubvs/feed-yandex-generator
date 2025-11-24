<?php
/**
 * Yandex Feed Generator Pro - Field Extraction Service
 * 
 * Сервисный слой для извлечения полей из постов (ACF/JetEngine/native WordPress)
 * 
 * @package YandexFeedGeneratorPro
 * @since 4.18.17
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';

class YFGP_Field_Extraction_Service {

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
     * Извлечение значения поля
     * 
     * @param int $post_id ID поста
     * @param array<string, mixed> $field_config Конфигурация поля (V3 формат)
     * @param bool $skip_cache Пропустить кэш (для real-time UI)
     * @return mixed Значение поля
     */
    public function getFieldValue(int $post_id, array $field_config, bool $skip_cache = false) {
        return $this->field_mapper->getFieldValue($post_id, $field_config, $skip_cache);
    }

    /**
     * Маппинг данных поста (batch)
     * 
     * @param int $post_id ID поста
     * @param array<string, mixed> $mapping_array Массив конфигураций полей
     * @return array<string, mixed> Массив значений полей
     */
    public function mapPostDataBatch(int $post_id, array $mapping_array): array {
        return $this->field_mapper->mapPostDataBatch($post_id, $mapping_array);
    }

    /**
     * Получение доступных полей для типа поста
     * 
     * @param string $post_type Тип поста
     * @return array<string, mixed> Массив доступных полей
     */
    public function getAvailableFields(string $post_type = 'doctors'): array {
        return $this->field_mapper->getAvailableFields($post_type);
    }
}

