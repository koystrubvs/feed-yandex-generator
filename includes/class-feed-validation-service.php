<?php
/**
 * Yandex Feed Generator Pro - Feed Validation Service
 * 
 * Главный сервис валидации фида с цепочкой обязанностей
 * 
 * @package YandexFeedGeneratorPro
 * @since 4.18.17
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once YFGP_PLUGIN_DIR . 'includes/class-validation-result.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-validation-exception.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-mapping-validator.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-entity-validator.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-xml-structure-validator.php';

class YFGP_Feed_Validation_Service {

    /**
     * @var YFGP_Mapping_Validator Валидатор маппинга
     */
    private YFGP_Mapping_Validator $mapping_validator;

    /**
     * @var YFGP_Entity_Validator Валидатор сущностей
     */
    private YFGP_Entity_Validator $entity_validator;

    /**
     * @var YFGP_Xml_Structure_Validator Валидатор XML структуры
     */
    private YFGP_Xml_Structure_Validator $xml_validator;

    /**
     * Конструктор
     */
    public function __construct() {
        // Создаём цепочку валидаторов
        $this->mapping_validator = new YFGP_Mapping_Validator();
        $this->entity_validator = new YFGP_Entity_Validator();
        $this->xml_validator = new YFGP_Xml_Structure_Validator();

        // Строим цепочку: MappingValidator → EntityValidator → XmlStructureValidator
        $this->mapping_validator->setNext($this->entity_validator);
        $this->entity_validator->setNext($this->xml_validator);
    }

    /**
     * Валидация маппинга полей
     * 
     * @param array<string, mixed>|null $mapping Маппинг (если null, загружается из wp_options)
     * @return YFGP_Validation_Result
     * @throws YFGP_Validation_Exception При критических ошибках
     */
    public function validateMapping(?array $mapping = null): YFGP_Validation_Result {
        if ($mapping === null) {
            $mapping = get_option('yfgp_field_mapping_v3', array());
        }

        $data = array('mapping' => $mapping);
        $result = $this->mapping_validator->handle($data);

        if (!$result->isValid()) {
            $this->logValidationResult($result, 'mapping');
            throw new YFGP_Validation_Exception($result, 'Ошибка валидации маппинга полей');
        }

        return $result;
    }

    /**
     * Валидация собранных сущностей
     * 
     * @param array<int, array<string, mixed>> $doctors Массив doctors
     * @param array<int, array<string, mixed>> $clinics Массив clinics
     * @param array<int, array<string, mixed>> $services Массив services
     * @return YFGP_Validation_Result
     * @throws YFGP_Validation_Exception При критических ошибках
     */
    public function validateEntities(
        array $doctors = array(),
        array $clinics = array(),
        array $services = array()
    ): YFGP_Validation_Result {
        $data = array(
            'doctors' => $doctors,
            'clinics' => $clinics,
            'services' => $services,
        );

        $result = $this->entity_validator->handle($data);

        if (!$result->isValid()) {
            $this->logValidationResult($result, 'entities');
            throw new YFGP_Validation_Exception($result, 'Ошибка валидации сущностей');
        }

        return $result;
    }

    /**
     * Валидация сгенерированного XML
     * 
     * @param string $xml XML строка
     * @return YFGP_Validation_Result
     * @throws YFGP_Validation_Exception При критических ошибках
     */
    public function validateXml(string $xml): YFGP_Validation_Result {
        $data = array('xml' => $xml);
        $result = $this->xml_validator->handle($data);

        if (!$result->isValid()) {
            $this->logValidationResult($result, 'xml');
            throw new YFGP_Validation_Exception($result, 'Ошибка валидации XML структуры');
        }

        return $result;
    }

    /**
     * Полная валидация (маппинг → сущности → XML)
     * 
     * @param array<string, mixed> $data Данные для валидации (mapping, doctors, clinics, services, xml)
     * @return YFGP_Validation_Result
     * @throws YFGP_Validation_Exception При критических ошибках
     */
    public function validateAll(array $data): YFGP_Validation_Result {
        // Запускаем цепочку валидаторов
        $result = $this->mapping_validator->handle($data);

        if (!$result->isValid()) {
            $this->logValidationResult($result, 'all');
            throw new YFGP_Validation_Exception($result, 'Ошибка валидации фида');
        }

        return $result;
    }

    /**
     * Логирование результата валидации
     * 
     * @param YFGP_Validation_Result $result Результат валидации
     * @param string $context Контекст валидации ('mapping', 'entities', 'xml', 'all')
     * @return void
     */
    private function logValidationResult(YFGP_Validation_Result $result, string $context): void {
        $critical_errors = $result->getCriticalErrors();
        $warnings = $result->getWarnings();

        if (!empty($critical_errors)) {
            error_log(sprintf(
                'YFGP Validation [%s] - Critical errors: %s',
                $context,
                implode('; ', $critical_errors)
            ));
        }

        if (!empty($warnings)) {
            error_log(sprintf(
                'YFGP Validation [%s] - Warnings: %s',
                $context,
                implode('; ', $warnings)
            ));
        }
    }
}

