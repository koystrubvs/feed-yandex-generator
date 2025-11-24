<?php
/**
 * Yandex Feed Generator Pro - Conditional Logic Processor
 * 
 * Сервисный слой для обработки условной логики полей (checkbox conditional logic для adult_appointment/children_appointment)
 * 
 * @package YandexFeedGeneratorPro
 * @since 4.18.17
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';

class YFGP_Conditional_Logic_Processor {

    /**
     * @var YFGP_Field_Mapper_Unified Экземпляр Unified Mapper (содержит логику условной обработки)
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
     * Проверка является ли значение "truthy" (для условной логики)
     * 
     * Логика условной обработки встроена в unified mapper через extractField(),
     * который автоматически применяет conditional_logic если указано в конфиге.
     * 
     * @param int $post_id ID поста
     * @param array<string, mixed> $field_config Конфигурация поля с conditional_logic
     * @param bool $skip_cache Пропустить кэш
     * @return mixed Результат условной обработки
     */
    public function processConditionalLogic(int $post_id, array $field_config, bool $skip_cache = false) {
        // Условная логика автоматически применяется в unified mapper через extractField()
        // если в конфиге указаны conditional_logic и operator
        return $this->field_mapper->getFieldValue($post_id, $field_config, $skip_cache);
    }
}

