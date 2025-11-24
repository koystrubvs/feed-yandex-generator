<?php
/**
 * Yandex Feed Generator Pro - Mapping Validator
 * 
 * Валидатор маппинга полей (yfgp_field_mapping_v3)
 * 
 * @package YandexFeedGeneratorPro
 * @since 4.18.17
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once YFGP_PLUGIN_DIR . 'includes/interface-validation-handler.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-validation-result.php';

class YFGP_Mapping_Validator implements YFGP_Validation_Handler_Interface {

    /**
     * @var YFGP_Validation_Handler_Interface|null Следующий обработчик в цепочке
     */
    private ?YFGP_Validation_Handler_Interface $next = null;

    /**
     * Обязательные поля для каждого типа сущности
     * 
     * @var array<string, array<string>>
     */
    private const REQUIRED_FIELDS = array(
        'doctors' => array('id', 'name', 'url'),
        'clinics' => array('id', 'name', 'url'),
        // v4.18.17: price для services НЕ обязательное поле в маппинге, т.к. берётся из offers/prices
        'services' => array('id', 'name'),
    );

    /**
     * Обработать данные валидации
     * 
     * @param array<string, mixed> $data Данные для валидации (должен содержать 'mapping' => array)
     * @return YFGP_Validation_Result
     */
    public function handle(array $data): YFGP_Validation_Result {
        $result = new YFGP_Validation_Result();

        // 1. Проверка наличия маппинга
        if (!isset($data['mapping']) || !is_array($data['mapping'])) {
            $mapping = get_option('yfgp_field_mapping_v3', array());
            // v4.18.18: Fix for corrupted serialization - check if get_option returned false
            if ($mapping === false || !is_array($mapping)) {
                // Try to get raw value and fix it
                global $wpdb;
                $raw_value = $wpdb->get_var($wpdb->prepare(
                    "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                    'yfgp_field_mapping_v3'
                ));
                
                if ($raw_value) {
                    // Clear cache and try again
                    wp_cache_delete('yfgp_field_mapping_v3', 'options');
                    wp_cache_flush();
                    $mapping = get_option('yfgp_field_mapping_v3', array());
                }
                
                if ($mapping === false || !is_array($mapping) || empty($mapping)) {
                    $result->addCriticalError('Маппинг полей (yfgp_field_mapping_v3) поврежден или имеет неверный формат. Пожалуйста, пересохраните маппинг через интерфейс WordPress или восстановите из резервной копии.');
                    return $this->passToNext($result, $data);
                }
            }
            $data['mapping'] = $mapping;
        }

        $mapping = $data['mapping'];

        // 2. Проверка обязательных полей для каждого типа
        // v4.18.17: Для doctors поля могут быть без префикса (id, name, url) или с префиксом (doctors_id)
        foreach (self::REQUIRED_FIELDS as $entity_type => $required_fields) {
            foreach ($required_fields as $field_name) {
                // Пробуем найти поле с префиксом (doctors_id, clinics_id, services_id)
                $field_key_with_prefix = $entity_type . '_' . $field_name;
                // Для doctors также пробуем без префикса (id, name, url)
                $field_key_without_prefix = ($entity_type === 'doctors') ? $field_name : null;
                
                $field_key = null;
                $field_config = null;
                
                // Сначала проверяем с префиксом
                if (isset($mapping[$field_key_with_prefix]) && !empty($mapping[$field_key_with_prefix])) {
                    $field_key = $field_key_with_prefix;
                    $field_config = $mapping[$field_key];
                } 
                // Для doctors проверяем без префикса
                elseif ($field_key_without_prefix !== null && isset($mapping[$field_key_without_prefix]) && !empty($mapping[$field_key_without_prefix])) {
                    $field_key = $field_key_without_prefix;
                    $field_config = $mapping[$field_key];
                }
                
                // Если поле не найдено - критическая ошибка
                if ($field_key === null || $field_config === null) {
                    $result->addCriticalError(
                        sprintf(
                            'Отсутствует обязательное поле маппинга: %s (для типа %s)',
                            $field_name,
                            $entity_type
                        )
                    );
                    continue;
                }
                
                // Проверяем что есть source_type и source_field (для обязательных полей)
                if (!isset($field_config['source_type']) || empty($field_config['source_type'])) {
                    $result->addCriticalError(
                        sprintf(
                            'Неполная конфигурация поля маппинга: %s (отсутствует source_type)',
                            $field_key
                        )
                    );
                }
                // source_field может быть пустым только для fixed/boolean типов
                if (!isset($field_config['source_field']) || 
                    (empty($field_config['source_field']) && 
                     !in_array($field_config['source_type'] ?? '', array('fixed', 'boolean'), true))) {
                    $result->addCriticalError(
                        sprintf(
                            'Неполная конфигурация поля маппинга: %s (отсутствует source_field)',
                            $field_key
                        )
                    );
                }
            }
        }

        // 3. Проверка корректности source_type
        foreach ($mapping as $field_key => $field_config) {
            if (!is_array($field_config)) {
                $result->addWarning("Поле маппинга '{$field_key}' имеет неверный формат (ожидается массив)");
                continue;
            }

            if (isset($field_config['source_type']) && !empty($field_config['source_type'])) {
                // v4.18.17: Все используемые source_type в плагине
                $valid_source_types = array(
                    'meta_field',           // Мета поле (прямое значение)
                    'taxonomy',             // Таксономия
                    'post_field',           // Поле поста (post_title, post_content, etc)
                    'acf_field',            // ACF поле
                    'jetengine_field',      // JetEngine поле
                    'relationship',         // Связь (общий тип)
                    'relationship_1',       // Связь 1 уровня (ACF/JetEngine)
                    'repeater',             // Repeater (общий тип)
                    'repeater_acf',         // ACF Repeater
                    'repeater_jetengine',   // JetEngine Repeater
                    'fixed',                // Произвольное значение
                    'boolean',              // Булево значение (true/false)
                );
                
                if (!in_array($field_config['source_type'], $valid_source_types, true)) {
                    $result->addWarning(
                        sprintf(
                            "Неизвестный source_type '%s' для поля '%s'",
                            $field_config['source_type'],
                            $field_key
                        )
                    );
                }
            }

            // v4.18.17: Пустой source_field - это нормально для необязательных полей или fixed/boolean типов
            if (isset($field_config['source_field']) && empty($field_config['source_field'])) {
                $source_type = $field_config['source_type'] ?? '';
                // Предупреждение только если это не fixed/boolean и поле не пустое (т.е. есть в маппинге)
                if (!in_array($source_type, array('fixed', 'boolean'), true) && !empty($field_config)) {
                    $result->addWarning("Поле маппинга '{$field_key}' имеет пустой source_field");
                }
            }
        }

        return $this->passToNext($result, $data);
    }

    /**
     * Установить следующий обработчик в цепочке
     * 
     * @param YFGP_Validation_Handler_Interface|null $next
     * @return void
     */
    public function setNext(?YFGP_Validation_Handler_Interface $next): void {
        $this->next = $next;
    }

    /**
     * Передать результат следующему обработчику в цепочке
     * 
     * @param YFGP_Validation_Result $result Текущий результат
     * @param array<string, mixed> $data Данные
     * @return YFGP_Validation_Result
     */
    private function passToNext(YFGP_Validation_Result $result, array $data): YFGP_Validation_Result {
        if ($this->next !== null) {
            $next_result = $this->next->handle($data);
            $result->merge($next_result);
        }
        return $result;
    }
}

