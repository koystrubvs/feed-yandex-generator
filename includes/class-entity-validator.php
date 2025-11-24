<?php
/**
 * Yandex Feed Generator Pro - Entity Validator
 * 
 * Валидатор собранных сущностей (doctors, clinics, services)
 * 
 * @package YandexFeedGeneratorPro
 * @since 4.18.17
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once YFGP_PLUGIN_DIR . 'includes/interface-validation-handler.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-validation-result.php';

class YFGP_Entity_Validator implements YFGP_Validation_Handler_Interface {

    /**
     * @var YFGP_Validation_Handler_Interface|null Следующий обработчик в цепочке
     */
    private ?YFGP_Validation_Handler_Interface $next = null;

    /**
     * Обработать данные валидации
     * 
     * @param array<string, mixed> $data Данные для валидации (должен содержать 'doctors', 'clinics', 'services' => array)
     * @return YFGP_Validation_Result
     */
    public function handle(array $data): YFGP_Validation_Result {
        $result = new YFGP_Validation_Result();

        // Валидация происходит ПОСЛЕ сбора сущностей
        // Если сущности ещё не собраны, пропускаем валидацию (это нормально для предварительной валидации маппинга)
        if (!isset($data['doctors']) && !isset($data['clinics']) && !isset($data['services'])) {
            // Сущности ещё не собраны - это нормально для предварительной валидации
            return $this->passToNext($result, $data);
        }

        // Валидация doctors
        if (isset($data['doctors']) && is_array($data['doctors'])) {
            $this->validateEntities($data['doctors'], 'doctors', $result);
        }

        // Валидация clinics
        if (isset($data['clinics']) && is_array($data['clinics'])) {
            $this->validateEntities($data['clinics'], 'clinics', $result);
        }

        // Валидация services
        if (isset($data['services']) && is_array($data['services'])) {
            $this->validateEntities($data['services'], 'services', $result);
        }

        return $this->passToNext($result, $data);
    }

    /**
     * Валидация массива сущностей
     * 
     * @param array<int, array<string, mixed>> $entities Массив сущностей
     * @param string $entity_type Тип сущности ('doctors', 'clinics', 'services')
     * @param YFGP_Validation_Result $result Результат валидации
     * @return void
     */
    private function validateEntities(array $entities, string $entity_type, YFGP_Validation_Result $result): void {
        $required_fields = $this->getRequiredFields($entity_type);

        foreach ($entities as $index => $entity) {
            if (!is_array($entity)) {
                $result->addCriticalError(
                    sprintf('Сущность %s[%d] имеет неверный формат (ожидается массив)', $entity_type, $index)
                );
                continue;
            }

            // Проверка обязательных полей
            foreach ($required_fields as $field_name) {
                if (!isset($entity[$field_name]) || $entity[$field_name] === '' || $entity[$field_name] === null) {
                    $result->addCriticalError(
                        sprintf(
                            'Сущность %s[%d] не содержит обязательное поле: %s',
                            $entity_type,
                            $index,
                            $field_name
                        )
                    );
                } else {
                    // Дополнительная валидация типов
                    $this->validateFieldType($entity_type, $field_name, $entity[$field_name], $index, $result);
                }
            }
        }
    }

    /**
     * Получить обязательные поля для типа сущности
     * 
     * @param string $entity_type Тип сущности
     * @return array<string>
     */
    private function getRequiredFields(string $entity_type): array {
        $required = array(
            'doctors' => array('id', 'name', 'url'),
            'clinics' => array('id', 'name', 'url'),
            // v4.18.17: price для services формируется в блоке offers → не требуем здесь
            'services' => array('id', 'name'),
        );

        return $required[$entity_type] ?? array();
    }

    /**
     * Валидация типа поля
     * 
     * @param string $entity_type Тип сущности
     * @param string $field_name Имя поля
     * @param mixed $value Значение поля
     * @param mixed $index Индекс сущности (может быть int или string)
     * @param YFGP_Validation_Result $result Результат валидации
     * @return void
     */
    private function validateFieldType(
        string $entity_type,
        string $field_name,
        $value,
        $index,
        YFGP_Validation_Result $result
    ): void {
        switch ($field_name) {
            case 'id':
                if (!is_string($value) && !is_numeric($value)) {
                    $result->addCriticalError(
                        sprintf(
                            'Сущность %s[%d].id должна быть строкой или числом, получен: %s',
                            $entity_type,
                            $index,
                            gettype($value)
                        )
                    );
                }
                break;

            case 'name':
                if (!is_string($value) || trim($value) === '') {
                    $result->addWarning(
                        sprintf(
                            'Сущность %s[%d].name должна быть непустой строкой',
                            $entity_type,
                            $index
                        )
                    );
                }
                break;

            case 'url':
                if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_URL)) {
                    $result->addWarning(
                        sprintf(
                            'Сущность %s[%d].url должна быть валидным URL',
                            $entity_type,
                            $index
                        )
                    );
                }
                break;

            case 'price':
                if (!is_numeric($value) || $value < 0) {
                    $result->addWarning(
                        sprintf(
                            'Сущность %s[%d].price должна быть положительным числом',
                            $entity_type,
                            $index
                        )
                    );
                }
                break;
        }
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

