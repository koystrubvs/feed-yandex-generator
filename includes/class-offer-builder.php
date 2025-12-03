<?php
/**
 * Yandex Feed Generator Pro - Offer Builder
 *
 * @package YandexFeedGeneratorPro
 * @since 4.18.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once YFGP_PLUGIN_DIR . 'includes/trait-feed-generator-shared.php';

/**
 * Offer Builder - строит offers из данных врача, клиник и услуг
 *
 * @since 4.18.0
 */
class YFGP_Offer_Builder {
    use YFGP_Feed_Generator_Shared_Trait;

    /**
     * @var array<string, mixed>
     */
    private array $settings;

    /**
     * @var YFGP_Field_Mapper_V2
     */
    private YFGP_Field_Mapper_V2 $field_mapper;

    /**
     * @var callable
     */
    private $determine_base_service_callback;

    /**
     * @var callable
     */
    private $get_offer_additional_fields_callback;

    /**
     * @var callable|null
     * @since 4.19.3 - Callback для получения ручных настроек базовой услуги
     */
    private $get_manual_base_service_callback = null;

    /**
     * Constructor
     *
     * @param array<string, mixed> $settings Settings array
     * @param YFGP_Field_Mapper_V2 $field_mapper Field mapper instance
     * @param callable|array|null $determine_base_service_callback Callback для определения базовой услуги (может быть array для метода класса, null для DI)
     * @param callable|array|null $get_offer_additional_fields_callback Callback для получения дополнительных полей offer (может быть array для метода класса, null для DI)
     */
    public function __construct(
        array $settings,
        YFGP_Field_Mapper_V2 $field_mapper,
        callable|array|null $determine_base_service_callback = null,
        callable|array|null $get_offer_additional_fields_callback = null
    ) {
        $this->settings = $settings;
        $this->field_mapper = $field_mapper;
        
        // v4.18.17: Callbacks опциональны для DI (могут быть установлены позже через setter)
        if ($determine_base_service_callback !== null && !is_callable($determine_base_service_callback)) {
            throw new \InvalidArgumentException('$determine_base_service_callback must be callable or null');
        }
        if ($get_offer_additional_fields_callback !== null && !is_callable($get_offer_additional_fields_callback)) {
            throw new \InvalidArgumentException('$get_offer_additional_fields_callback must be callable or null');
        }
        
        $this->determine_base_service_callback = $determine_base_service_callback;
        $this->get_offer_additional_fields_callback = $get_offer_additional_fields_callback;
    }
    
    /**
     * Установка callbacks (для DI, когда callbacks недоступны при создании)
     * 
     * @param callable|array $determine_base_service_callback
     * @param callable|array $get_offer_additional_fields_callback
     * @param callable|array|null $get_manual_base_service_callback v4.19.3: Callback для получения ручных настроек
     * @return void
     */
    public function setCallbacks(
        callable|array $determine_base_service_callback,
        callable|array $get_offer_additional_fields_callback,
        callable|array|null $get_manual_base_service_callback = null
    ): void {
        if (!is_callable($determine_base_service_callback)) {
            throw new \InvalidArgumentException('$determine_base_service_callback must be callable');
        }
        if (!is_callable($get_offer_additional_fields_callback)) {
            throw new \InvalidArgumentException('$get_offer_additional_fields_callback must be callable');
        }
        if ($get_manual_base_service_callback !== null && !is_callable($get_manual_base_service_callback)) {
            throw new \InvalidArgumentException('$get_manual_base_service_callback must be callable or null');
        }
        
        $this->determine_base_service_callback = $determine_base_service_callback;
        $this->get_offer_additional_fields_callback = $get_offer_additional_fields_callback;
        $this->get_manual_base_service_callback = $get_manual_base_service_callback;
    }

    /**
     * Построение offers для врача
     *
     * @param \WP_Post $post Объект поста врача
     * @param array<string, mixed> $data Данные поста (из mapper)
     * @param array<int, array<string, mixed>> $clinics Массив клиник для текущего врача
     * @param array<int, array<string, mixed>> $services Массив услуг для текущего врача (raw)
     * @param array<int, array<string, mixed>> $offers Массив офферов (по ссылке)
     * @param string $doctor_id ID врача
     * @param array<string, array<string, mixed>> $global_services Глобальный массив услуг (по ссылке, для автосоздания)
     */
    public function build_offers(
        \WP_Post $post,
        array $data,
        array $clinics,
        array $services,
        array &$offers,
        string $doctor_id,
        array &$global_services
    ): void {
        // v4.18.1: DEBUG - логируем входные параметры
        error_log('YFGP v4.18.1 DEBUG build_offers_v2: Doctor ID: ' . $doctor_id . ', Post ID: ' . $post->ID . ', clinics count: ' . count($clinics) . ', services count: ' . count($services) . ', global_services count: ' . count($global_services));
        // v4.18.21: DEBUG - проверяем что в $data при входе в build_offers
        error_log("YFGP v4.18.21 DEBUG OfferBuilder build_offers: ENTRY - data[adult_appointment] = " . var_export($data['adult_appointment'] ?? 'NOT SET', true) . ", data[children_appointment] = " . var_export($data['children_appointment'] ?? 'NOT SET', true) . " for doctor_id: $doctor_id");

        $generate_all_services = $this->settings['generate_all_services'] ?? false;
        $mapping = get_option('yfgp_field_mapping_v3', array());

        // Получаем специализации врача (slugs для группировки)
        // v4.18.2: УНИВЕРСАЛЬНО - не используем хардкод fallback specialization
        $fallback_spec_slug = $this->settings['default_specialization_slug'] ?? '';
        $specialization_slugs = $data['set_ids'] ?? ($fallback_spec_slug ? array($fallback_spec_slug) : array());
        $fallback_spec_text = $this->field_mapper->get_fallback_speciality_text(); // v4.17.0: FIX #4 - from settings
        $specialization_texts = $data['specializations_text'] ?? array($fallback_spec_text);

        // Преобразуем в массив если это строка
        if (!is_array($specialization_slugs)) {
            $specialization_slugs = array($specialization_slugs);
        }
        if (!is_array($specialization_texts)) {
            $specialization_texts = array($specialization_texts);
        }

        // v4.18.1: FIX - нормализация $specialization_texts (защита от массивов внутри)
        $normalized_texts = array();
        foreach ($specialization_texts as $text) {
            if (is_array($text)) {
                // Если массив - извлечь label
                $text = $text['label'] ?? $text[0] ?? $fallback_spec_text;
            }
            if (!is_string($text)) {
                $text = (string)$text;
            }
            if ($text === '') {
                $text = $fallback_spec_text;
            }
            $normalized_texts[] = $text;
        }
        $specialization_texts = $normalized_texts;

        // Если пустой массив - используем умолчание из настроек
        if (empty($specialization_slugs)) {
            $specialization_slugs = $fallback_spec_slug ? array($fallback_spec_slug) : array();
            $specialization_texts = array($fallback_spec_text); // v4.17.0: FIX #4 - reuse from above
        }

        // v4.18.1: FIX - если после fallback specialization_slugs всё ещё пустой, создаём offers с fallback_spec_text
        if (empty($specialization_slugs)) {
            // Если fallback_spec_text пустой, используем дефолтное значение
            if (empty($fallback_spec_text)) {
                $fallback_spec_text = 'врач'; // Дефолтное значение если настройки пустые
            }
            $specialization_slugs = array('default'); // Используем 'default' как slug если нет fallback slug
            $specialization_texts = array($fallback_spec_text);
            error_log('YFGP v4.18.1 DEBUG: Using fallback specialization_slugs: default, fallback_spec_text: ' . $fallback_spec_text);
        }

        // v4.18.1: DEBUG - логируем перед циклом
        error_log('YFGP v4.18.1 DEBUG: Before foreach specialization_slugs, count: ' . count($specialization_slugs) . ', slugs: ' . implode(', ', $specialization_slugs));

        // Для КАЖДОЙ специализации создаём офферы
        foreach ($specialization_slugs as $index => $specialization_slug) {
            // Получаем оригинальный текст специализации
            $specialization_text = $specialization_texts[$index] ?? $specialization_texts[0] ?? $fallback_spec_text; // v4.17.0: FIX #4

            // v4.18.1: FIX - защита от массивов (дополнительная нормализация)
            if (is_array($specialization_text)) {
                $specialization_text = $specialization_text['label'] ?? $specialization_text[0] ?? $fallback_spec_text;
            }
            if (!is_string($specialization_text)) {
                $specialization_text = (string)$specialization_text;
            }
            if ($specialization_text === '') {
                $specialization_text = $fallback_spec_text;
            }

            // v4.5.0 CRITICAL FIX: Build services array WITH PRICES for CURRENT doctor only!
            // Problem: $global_services contains services from ALL doctors (including auto-created from previous doctors)
            // Solution: Filter $global_services to get only services for current doctor from $services raw data
            $current_doctor_services = array();
            foreach ($services as $service_raw) {
                $service_id = $service_raw['id'] ?? null;
                if ($service_id && isset($global_services[$service_id])) {
                    // Use service WITH PRICES from $global_services
                    $current_doctor_services[] = $global_services[$service_id];
                }
            }

            // v3.5.3: Определяем базовую услугу с учётом исключений
            // v4.5.0 FIX: Use $current_doctor_services (with prices, ONLY for this doctor!)
            // v4.18.1: FIX - если $current_doctor_services пустой, используем $services (raw) для автосоздания
            $services_for_determination = !empty($current_doctor_services) ? $current_doctor_services : $services;

            // v4.18.1: DEBUG - логируем для отладки
            error_log('YFGP v4.18.1 DEBUG: Doctor ' . $post->ID . ', specialization_slug: ' . $specialization_slug . ', services_for_determination count: ' . count($services_for_determination) . ', services (raw) count: ' . count($services));

            // v4.19.3: Проверка ручных настроек ПЕРЕД автоматическим определением
            $specialization_count = count($specialization_slugs);
            $base_service = null;

            if ($this->get_manual_base_service_callback !== null) {
                $get_manual = $this->get_manual_base_service_callback;
                $manual_service = $get_manual($post->ID, $specialization_slug, $mapping, $global_services);
                if ($manual_service !== null) {
                    $base_service = $manual_service;
                    error_log('YFGP v4.19.3 OfferBuilder: Using MANUAL base service for doctor ' . $post->ID . ', specialization: ' . $specialization_slug . ', service: ' . ($base_service['id'] ?? 'unknown') . ', name: ' . ($base_service['name'] ?? 'unknown'));
                } else {
                    error_log('YFGP v4.19.3 OfferBuilder: No manual service found for doctor ' . $post->ID . ', specialization: ' . $specialization_slug . ' - will use automatic determination');
                }
            }

            // v4.19.3: Автоматическое определение только если ручная настройка НЕ найдена
            if ($base_service === null) {
                // v4.18.17: Проверка наличия callback перед использованием
                if ($this->determine_base_service_callback === null) {
                    throw new \RuntimeException('determine_base_service_callback не установлен. Используйте setCallbacks() для установки callbacks.');
                }
                $determine_base = $this->determine_base_service_callback;
                $base_service = $determine_base($services_for_determination, $specialization_slug, $mapping, $data, $global_services);
                
                // v4.19.3: Log warning if manual settings are missing for doctors with 2+ specializations
                if ($specialization_count >= 2 && $base_service !== null) {
                    error_log('YFGP v4.19.3 OfferBuilder WARNING: Doctor ' . $post->ID . ' has ' . $specialization_count . ' specializations but no manual base service configured for: ' . $specialization_slug . ' - using automatic service: ' . ($base_service['id'] ?? 'unknown'));
                }
            }

            // v3.5.3: Если специализация исключена (УЗИ) и нет услуг - пропускаем создание оффера
            if ($base_service === null) {
                error_log('YFGP v3.5.3: Skipping offer creation for doctor ' . $post->ID . ' (specialty excluded, no services)');
                continue;
            }

            // v4.18.1: DEBUG - логируем успешное определение базовой услуги
            error_log('YFGP v4.18.1 DEBUG: Base service determined for doctor ' . $post->ID . ', service_id: ' . ($base_service['id'] ?? 'unknown'));

            // v4.18.1: FIX - если clinics пустой, используем дефолтную клинику
            if (empty($clinics)) {
                $clinics = array(array('id' => 'default', 'name' => '', 'address' => '', 'phone' => '', 'city' => ''));
                error_log('YFGP v4.18.1 DEBUG: Using fallback clinic (empty clinics array)');
            }

            // v4.18.1: DEBUG - логируем перед циклом clinics
            error_log('YFGP v4.18.1 DEBUG: Before foreach clinics, count: ' . count($clinics) . ', base_service: ' . ($base_service ? ($base_service['id'] ?? 'no id') : 'NULL'));

            // Создаём оффер для каждой клиники
            foreach ($clinics as $clinic) {
                $clinic_id = $clinic['id'] ?? 'default';

                // v4.4.1: Use service ID from base_service (already clean: service_число)
                $service_id = $base_service['id'] ?? ('service_' . $post->ID);

                $offer_id = 'offer_' . $post->ID . '_' . $clinic_id . '_' . $specialization_slug;

                // Собираем данные оффера
                $base_price_value = $base_service['price'] ?? null;
                $currency_value = $base_price_value !== null && $base_price_value !== ''
                    ? ($base_service['currency'] ?? ($this->settings['default_currency'] ?? 'RUR'))
                    : null;
                $discount_value = $base_service['price_discount'] ?? null;
                $discount_name_value = (!empty($discount_value) && !empty($base_service['discount_name']))
                    ? $base_service['discount_name']
                    : null;
                $free_appointment_value = (!empty($discount_value) && !empty($base_service['free_appointment_condition']))
                    ? $base_service['free_appointment_condition']
                    : null;

                $offer_data = array(
                    'id' => $offer_id,
                    'doctor_id' => $doctor_id,
                    'clinic_id' => $clinic_id, // v4.4.0: FIXED - $clinic_id already has 'clinic_' prefix from mapper!
                    'service_id' => $service_id,
                    'speciality' => mb_strtolower($this->normalize_speciality_value($specialization_text, $fallback_spec_text)), // v4.10.8: LOWERCASE согласно Яндекс docs! v4.18.1: FIX - защита от массивов
                    'price' => $base_price_value, // Цена НЕ обязательна!
                    'base_price' => $base_price_value,
                    'currency' => $currency_value, // v4.18.2: указываем только при наличии цены
                    'discount' => $discount_value, // v4.5.0: Discount from related prices CPT
                    'discount_name' => $discount_name_value,
                    'free_appointment_condition' => $free_appointment_value,
                    'is_base_service' => true,
                    'appointment_url' => $data['url'] ?? get_permalink($post->ID), // v4.4.0: Fallback to doctor URL (Yandex requires!)
                );

                // Добавляем дополнительные поля оффера
                // v4.18.17: Проверка наличия callback перед использованием
                if ($this->get_offer_additional_fields_callback === null) {
                    throw new \RuntimeException('get_offer_additional_fields_callback не установлен. Используйте setCallbacks() для установки callbacks.');
                }
                $get_additional = $this->get_offer_additional_fields_callback;
                $offer_data = array_merge($offer_data, $get_additional($post, $data, $mapping));

                // v4.4.0: Ensure offer-level boolean fields have defaults
                if (!isset($offer_data['appointment_available'])) {
                    $offer_data['appointment_available'] = 'true'; // Default: врач ведёт приём
                }
                if (!isset($offer_data['oms_available'])) {
                    $offer_data['oms_available'] = 'false'; // Default: нет ОМС
                }
                if (!isset($offer_data['online_schedule'])) {
                    $offer_data['online_schedule'] = 'false'; // Default: нет онлайн расписания
                }

                // v4.10.6: Наследование полей врача в оффер
                // Эти поля копируются из $data (которые были добавлены из $doctor_entity в collect_entities)
                // v4.18.21: DEBUG - проверяем что приходит в $data ПЕРЕД логикой наследования
                error_log("YFGP v4.18.21 DEBUG OfferBuilder: BEFORE inheritance for offer $offer_id - data[adult_appointment] = " . var_export($data['adult_appointment'] ?? 'NOT SET', true) . ", data[children_appointment] = " . var_export($data['children_appointment'] ?? 'NOT SET', true));
                error_log("YFGP v4.18.21 DEBUG OfferBuilder: BEFORE inheritance for offer $offer_id - offer_data[adult_appointment] = " . var_export($offer_data['adult_appointment'] ?? 'NOT SET', true) . ", offer_data[children_appointment] = " . var_export($offer_data['children_appointment'] ?? 'NOT SET', true));
                
                $inheritable_fields = array('adult_appointment', 'children_appointment', 'house_call', 'telemed');
                foreach ($inheritable_fields as $field) {
                    $current = $offer_data[$field] ?? null;
                    if (is_array($current) || $current === null || $current === '' || $current === 'false') {
                        if (array_key_exists($field, $data)) {
                            $doctor_value = $data[$field];
                            if ($doctor_value === null || $doctor_value === '') {
                                unset($offer_data[$field]);
                            } else {
                                $offer_data[$field] = $doctor_value;
                                // v4.18.21: DEBUG - логируем установку значения для отладки
                                error_log("YFGP v4.18.21 DEBUG OfferBuilder: Set $field = " . var_export($doctor_value, true) . " for offer $offer_id");
                            }
                        } else {
                            $offer_data[$field] = 'false';
                            // v4.18.21: DEBUG - логируем установку false (поле не в data)
                            error_log("YFGP v4.18.21 DEBUG OfferBuilder: Set $field = 'false' (not in data) for offer $offer_id");
                        }
                    } else {
                        // v4.18.21: DEBUG - логируем что значение уже установлено
                        error_log("YFGP v4.18.21 DEBUG OfferBuilder: $field already set to " . var_export($current, true) . " for offer $offer_id");
                    }
                }

                $offers[$offer_id] = $offer_data;
                
                // v4.18.21: DEBUG - проверяем что сохранилось в offers
                error_log("YFGP v4.18.21 DEBUG OfferBuilder: After saving to offers[$offer_id]: adult_appointment = " . var_export($offers[$offer_id]['adult_appointment'] ?? 'NOT SET', true) . ", children_appointment = " . var_export($offers[$offer_id]['children_appointment'] ?? 'NOT SET', true));

                // v4.18.1: DEBUG - логируем создание offer
                error_log('YFGP v4.18.1 DEBUG: Offer created! Doctor ID: ' . $doctor_id . ', Offer ID: ' . $offer_id . ', Specialization: ' . $specialization_slug);

                // v4.5.1: Logging moved to create_auto_base_service() - logs ONLY when creating NEW service!
                // Old code here logged for EVERY offer (created duplicates: 20 logs instead of 13)

                // Если включена генерация всех услуг
                // v4.5.0 FIX: Use $current_doctor_services (with prices, ONLY for this doctor!)
                if ($generate_all_services && !empty($current_doctor_services)) {
                    foreach ($current_doctor_services as $service) {
                        // Пропускаем базовую услугу
                        if ($service['name'] === $base_service['name']) {
                            continue;
                        }

                        // Проверяем что услуга не автособзданная
                        if (!empty($service['auto_created'])) {
                            continue;
                        }

                        // v4.4.1: Use service ID from array (already clean: service_число)
                        $service_id = $service['id'] ?? ('service_' . $post->ID);
                        $offer_id = 'offer_' . $post->ID . '_' . $clinic_id . '_' . $specialization_slug . '_service_' . str_replace('service_', '', $service_id);

                        $service_price_value = $service['price'] ?? null;
                        $service_currency_value = $service_price_value !== null && $service_price_value !== ''
                            ? ($service['currency'] ?? ($this->settings['default_currency'] ?? 'RUR'))
                            : null;
                        $service_discount_value = $service['price_discount'] ?? null;
                        $service_discount_name_value = (!empty($service_discount_value) && !empty($service['discount_name']))
                            ? $service['discount_name']
                            : null;
                        $service_free_appointment_value = (!empty($service_discount_value) && !empty($service['free_appointment_condition']))
                            ? $service['free_appointment_condition']
                            : null;

                        $offer_data = array(
                            'id' => $offer_id,
                            'doctor_id' => $doctor_id,
                            'clinic_id' => $clinic_id, // v4.4.0: FIXED - already has clinic_ prefix!
                            'service_id' => $service_id,
                            'speciality' => $this->normalize_speciality_value($specialization_text, $fallback_spec_text), // v2.3.0: оригинальный текст! v4.18.1: FIX - защита от массивов
                            'price' => $service_price_value,
                            'base_price' => $service_price_value,
                            'currency' => $service_currency_value,
                            'discount' => $service_discount_value, // v4.5.0: Discount from related prices CPT
                            'discount_name' => $service_discount_name_value,
                            'free_appointment_condition' => $service_free_appointment_value,
                            'is_base_service' => false,
                            'appointment_url' => $data['url'] ?? get_permalink($post->ID), // v4.4.0: URL fallback
                        );

                        // Добавляем дополнительные поля
                        $offer_data = array_merge($offer_data, $get_additional($post, $data, $mapping));

                        // v4.4.0: Boolean defaults (same as base offer)
                        if (!isset($offer_data['appointment_available'])) {
                            $offer_data['appointment_available'] = 'true';
                        }
                        if (!isset($offer_data['oms_available'])) {
                            $offer_data['oms_available'] = 'false';
                        }
                        if (!isset($offer_data['online_schedule'])) {
                            $offer_data['online_schedule'] = 'false';
                        }

                        // v4.10.6: Наследование полей врача в оффер (для дополнительных услуг)
                        // v4.18.21: DEBUG - проверяем что приходит в $data ПЕРЕД логикой наследования
                        error_log("YFGP v4.18.21 DEBUG OfferBuilder: BEFORE inheritance (additional) for offer $offer_id - data[adult_appointment] = " . var_export($data['adult_appointment'] ?? 'NOT SET', true) . ", data[children_appointment] = " . var_export($data['children_appointment'] ?? 'NOT SET', true));
                        error_log("YFGP v4.18.21 DEBUG OfferBuilder: BEFORE inheritance (additional) for offer $offer_id - offer_data[adult_appointment] = " . var_export($offer_data['adult_appointment'] ?? 'NOT SET', true) . ", offer_data[children_appointment] = " . var_export($offer_data['children_appointment'] ?? 'NOT SET', true));
                        
                        $inheritable_fields = array('adult_appointment', 'children_appointment', 'house_call', 'telemed');
                        foreach ($inheritable_fields as $field) {
                            $current = $offer_data[$field] ?? null;
                            if (is_array($current) || $current === null || $current === '' || $current === 'false') {
                                if (array_key_exists($field, $data)) {
                                    $doctor_value = $data[$field];
                                    if ($doctor_value === null || $doctor_value === '') {
                                        unset($offer_data[$field]);
                                    } else {
                                        $offer_data[$field] = $doctor_value;
                                        // v4.18.21: DEBUG - логируем установку значения для отладки
                                        error_log("YFGP v4.18.21 DEBUG OfferBuilder: Set $field = " . var_export($doctor_value, true) . " for additional service offer $offer_id");
                                    }
                                } else {
                                    $offer_data[$field] = 'false';
                                    // v4.18.21: DEBUG - логируем установку false (поле не в data)
                                    error_log("YFGP v4.18.21 DEBUG OfferBuilder: Set $field = 'false' (not in data) for additional service offer $offer_id");
                                }
                            } else {
                                // v4.18.21: DEBUG - логируем что значение уже установлено
                                error_log("YFGP v4.18.21 DEBUG OfferBuilder: $field already set to " . var_export($current, true) . " for additional service offer $offer_id");
                            }
                        }

                        $offers[$offer_id] = $offer_data;
                    }
                }
            }
        }

        // v4.18.1: DEBUG - логируем в конце функции
        error_log('YFGP v4.18.1 DEBUG: build_offers_v2 finished for doctor ' . $doctor_id . ', total offers created: ' . count($offers));
    }
}
