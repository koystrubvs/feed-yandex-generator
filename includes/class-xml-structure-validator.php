<?php
/**
 * Yandex Feed Generator Pro - XML Structure Validator
 * 
 * Валидатор структуры сгенерированного XML (соответствие схеме Yandex.Health v2.0)
 * 
 * @package YandexFeedGeneratorPro
 * @since 4.18.17
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once YFGP_PLUGIN_DIR . 'includes/interface-validation-handler.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-validation-result.php';

class YFGP_Xml_Structure_Validator implements YFGP_Validation_Handler_Interface {

    /**
     * @var YFGP_Validation_Handler_Interface|null Следующий обработчик в цепочке
     */
    private ?YFGP_Validation_Handler_Interface $next = null;

    /**
     * Обработать данные валидации
     * 
     * @param array<string, mixed> $data Данные для валидации (должен содержать 'xml' => string)
     * @return YFGP_Validation_Result
     */
    public function handle(array $data): YFGP_Validation_Result {
        $result = new YFGP_Validation_Result();

        // Валидация XML происходит ПОСЛЕ генерации
        // Если XML ещё не сгенерирован, пропускаем валидацию
        if (!isset($data['xml']) || !is_string($data['xml'])) {
            // XML ещё не сгенерирован - это нормально для предварительной валидации
            return $this->passToNext($result, $data);
        }

        $xml_string = $data['xml'];

        // 1. Проверка валидности XML (парсинг)
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_string);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        if ($xml === false || !empty($errors)) {
            $error_messages = array_map(function($error) {
                return trim($error->message);
            }, $errors);
            $result->addCriticalError(
                'Сгенерированный XML содержит синтаксические ошибки: ' . implode('; ', $error_messages)
            );
            return $this->passToNext($result, $data);
        }

        // 2. Проверка корневого элемента <shop>
        // SimpleXML может вернуть объект с разными именами, проверяем через children()
        $shop = null;
        if (isset($xml->shop)) {
            $shop = $xml->shop;
        } elseif (property_exists($xml, 'shop')) {
            $shop = $xml->shop;
        } else {
            // Попробуем найти через getNamespaces и children
            $children = $xml->children();
            if (isset($children->shop)) {
                $shop = $children->shop;
            } else {
                // Проверяем имя корневого элемента
                $root_name = $xml->getName();
                if ($root_name === 'shop') {
                    $shop = $xml;
                } else {
                    $result->addCriticalError('XML не содержит корневой элемент <shop> (найден: ' . $root_name . ')');
                    return $this->passToNext($result, $data);
                }
            }
        }
        
        if ($shop === null) {
            $result->addCriticalError('XML не содержит корневой элемент <shop>');
            return $this->passToNext($result, $data);
        }

        // 3. Проверка обязательных секций
        $required_sections = array('doctors', 'clinics', 'services', 'offers');
        foreach ($required_sections as $section) {
            if (!isset($shop->$section)) {
                $result->addWarning("XML не содержит секцию <{$section}> (может быть пустой)");
            }
        }

        // 4. Проверка структуры секции doctors
        if (isset($shop->doctors)) {
            $this->validateDoctorsSection($shop->doctors, $result);
        }

        // 5. Проверка структуры секции clinics
        if (isset($shop->clinics)) {
            $this->validateClinicsSection($shop->clinics, $result);
        }

        // 6. Проверка структуры секции services
        if (isset($shop->services)) {
            $this->validateServicesSection($shop->services, $result);
        }

        // 7. Проверка структуры секции offers
        if (isset($shop->offers)) {
            $this->validateOffersSection($shop->offers, $result);
        }

        return $this->passToNext($result, $data);
    }

    /**
     * Валидация секции doctors
     * 
     * @param SimpleXMLElement $doctors Секция doctors
     * @param YFGP_Validation_Result $result Результат валидации
     * @return void
     */
    private function validateDoctorsSection(SimpleXMLElement $doctors, YFGP_Validation_Result $result): void {
        if (!isset($doctors->doctor)) {
            return; // Пустая секция - это нормально
        }

        foreach ($doctors->doctor as $index => $doctor) {
            // id передаём как атрибут
            if (!isset($doctor['id']) || (string)$doctor['id'] === '') {
                $result->addCriticalError(sprintf('doctor[%d] не содержит обязательный атрибут: id', $index));
            }

            // name и url передаются как дочерние элементы
            $required_elements = array('name', 'url');
            foreach ($required_elements as $element) {
                if (!isset($doctor->$element) || trim((string)$doctor->$element) === '') {
                    $result->addCriticalError(
                        sprintf('doctor[%d] не содержит обязательный элемент: %s', $index, $element)
                    );
                }
            }
        }
    }

    /**
     * Валидация секции clinics
     * 
     * @param SimpleXMLElement $clinics Секция clinics
     * @param YFGP_Validation_Result $result Результат валидации
     * @return void
     */
    private function validateClinicsSection(SimpleXMLElement $clinics, YFGP_Validation_Result $result): void {
        if (!isset($clinics->clinic)) {
            return; // Пустая секция - это нормально
        }

        foreach ($clinics->clinic as $index => $clinic) {
            if (!isset($clinic['id']) || (string)$clinic['id'] === '') {
                $result->addCriticalError(sprintf('clinic[%d] не содержит обязательный атрибут: id', $index));
            }

            $required_elements = array('name', 'url');
            foreach ($required_elements as $element) {
                if (!isset($clinic->$element) || trim((string)$clinic->$element) === '') {
                    $result->addCriticalError(
                        sprintf('clinic[%d] не содержит обязательный элемент: %s', $index, $element)
                    );
                }
            }
        }
    }

    /**
     * Валидация секции services
     * 
     * @param SimpleXMLElement $services Секция services
     * @param YFGP_Validation_Result $result Результат валидации
     * @return void
     */
    private function validateServicesSection(SimpleXMLElement $services, YFGP_Validation_Result $result): void {
        if (!isset($services->service)) {
            return; // Пустая секция - это нормально
        }

        foreach ($services->service as $index => $service) {
            if (!isset($service['id']) || (string)$service['id'] === '') {
                $result->addCriticalError(sprintf('service[%d] не содержит обязательный атрибут: id', $index));
            }

            if (!isset($service->name) || trim((string)$service->name) === '') {
                $result->addCriticalError(
                    sprintf('service[%d] не содержит обязательный элемент: name', $index)
                );
            }
            // price проверяется в блоке offers → здесь не требуем
        }
    }

    /**
     * Валидация секции offers
     * 
     * @param SimpleXMLElement $offers Секция offers
     * @param YFGP_Validation_Result $result Результат валидации
     * @return void
     */
    private function validateOffersSection(SimpleXMLElement $offers, YFGP_Validation_Result $result): void {
        if (!isset($offers->offer)) {
            return; // Пустая секция - это нормально
        }

        foreach ($offers->offer as $index => $offer) {
            if (!isset($offer['id']) || (string)$offer['id'] === '') {
                $result->addCriticalError(sprintf('offer[%d] не содержит обязательный атрибут: id', $index));
            }
            // Атрибут available в нашем фиде не используется (значения передаются через вложенные элементы)
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

