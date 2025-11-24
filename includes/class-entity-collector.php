<?php
/**
 * Yandex Feed Generator Pro - Entity Collector
 *
 * @package YandexFeedGeneratorPro
 * @since 4.18.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Entity Collector - collects entities (doctors, clinics, services) from posts
 *
 * @since 4.18.0
 */
class YFGP_Entity_Collector {

    /**
     * @var YFGP_Field_Mapper_V2
     */
    private YFGP_Field_Mapper_V2 $field_mapper;

    /**
     * @var callable
     */
    private $get_entity_type_callback;
    
    /**
     * @var callable
     */
    private $extract_post_data_callback;

    /**
     * @var callable
     */
    private $build_doctor_entity_callback;

    /**
     * @var callable
     */
    private $build_clinic_entity_callback;

    /**
     * @var callable
     */
    private $build_service_entity_callback;

    /**
     * @var YFGP_Offer_Builder
     */
    private YFGP_Offer_Builder $offer_builder;

    /**
     * Constructor
     *
     * @param YFGP_Field_Mapper_V2 $field_mapper Field mapper instance
     * @param YFGP_Offer_Builder $offer_builder Offer builder instance
     * @param callable|array|null $get_entity_type_callback Callback для получения entity_type (может быть array для метода класса, null для DI)
     * @param callable|array|null $build_doctor_entity_callback Callback для построения doctor entity (может быть array для метода класса, null для DI)
     * @param callable|array|null $build_clinic_entity_callback Callback для построения clinic entity (может быть array для метода класса, null для DI)
     * @param callable|array|null $build_service_entity_callback Callback для построения service entity (может быть array для метода класса, null для DI)
     * @param callable|array|null $extract_post_data_callback Callback для извлечения данных поста (v4.18.5, опционально, null для DI)
     */
    public function __construct(
        YFGP_Field_Mapper_V2 $field_mapper,
        YFGP_Offer_Builder $offer_builder,
        callable|array|null $get_entity_type_callback = null,
        callable|array|null $build_doctor_entity_callback = null,
        callable|array|null $build_clinic_entity_callback = null,
        callable|array|null $build_service_entity_callback = null,
        callable|array|null $extract_post_data_callback = null
    ) {
        $this->field_mapper = $field_mapper;
        $this->offer_builder = $offer_builder;
        
        // v4.18.17: Callbacks опциональны для DI (могут быть установлены позже через setter)
        if ($get_entity_type_callback !== null && !is_callable($get_entity_type_callback)) {
            throw new \InvalidArgumentException('$get_entity_type_callback must be callable or null');
        }
        if ($build_doctor_entity_callback !== null && !is_callable($build_doctor_entity_callback)) {
            throw new \InvalidArgumentException('$build_doctor_entity_callback must be callable or null');
        }
        if ($build_clinic_entity_callback !== null && !is_callable($build_clinic_entity_callback)) {
            throw new \InvalidArgumentException('$build_clinic_entity_callback must be callable or null');
        }
        if ($build_service_entity_callback !== null && !is_callable($build_service_entity_callback)) {
            throw new \InvalidArgumentException('$build_service_entity_callback must be callable or null');
        }
        if ($extract_post_data_callback !== null && !is_callable($extract_post_data_callback)) {
            throw new \InvalidArgumentException('$extract_post_data_callback must be callable or null');
        }
        
        $this->get_entity_type_callback = $get_entity_type_callback;
        $this->build_doctor_entity_callback = $build_doctor_entity_callback;
        $this->build_clinic_entity_callback = $build_clinic_entity_callback;
        $this->build_service_entity_callback = $build_service_entity_callback;
        $this->extract_post_data_callback = $extract_post_data_callback;
    }
    
    /**
     * Установка callbacks (для DI, когда callbacks недоступны при создании)
     * 
     * @param callable|array $get_entity_type_callback
     * @param callable|array $build_doctor_entity_callback
     * @param callable|array $build_clinic_entity_callback
     * @param callable|array $build_service_entity_callback
     * @param callable|array|null $extract_post_data_callback
     * @return void
     */
    public function setCallbacks(
        callable|array $get_entity_type_callback,
        callable|array $build_doctor_entity_callback,
        callable|array $build_clinic_entity_callback,
        callable|array $build_service_entity_callback,
        callable|array|null $extract_post_data_callback = null
    ): void {
        if (!is_callable($get_entity_type_callback)) {
            throw new \InvalidArgumentException('$get_entity_type_callback must be callable');
        }
        if (!is_callable($build_doctor_entity_callback)) {
            throw new \InvalidArgumentException('$build_doctor_entity_callback must be callable');
        }
        if (!is_callable($build_clinic_entity_callback)) {
            throw new \InvalidArgumentException('$build_clinic_entity_callback must be callable');
        }
        if (!is_callable($build_service_entity_callback)) {
            throw new \InvalidArgumentException('$build_service_entity_callback must be callable');
        }
        if ($extract_post_data_callback !== null && !is_callable($extract_post_data_callback)) {
            throw new \InvalidArgumentException('$extract_post_data_callback must be callable or null');
        }
        
        $this->get_entity_type_callback = $get_entity_type_callback;
        $this->build_doctor_entity_callback = $build_doctor_entity_callback;
        $this->build_clinic_entity_callback = $build_clinic_entity_callback;
        $this->build_service_entity_callback = $build_service_entity_callback;
        $this->extract_post_data_callback = $extract_post_data_callback;
    }
    
    /**
     * Получить OfferBuilder (для установки callbacks)
     * 
     * @return YFGP_Offer_Builder
     */
    public function getOfferBuilder(): YFGP_Offer_Builder {
        return $this->offer_builder;
    }

    /**
     * Collect entities from post
     *
     * @param \WP_Post $post Post object (doctor)
     * @param array<string, mixed> $mapping Field mapping configuration
     * @param array<int, array<string, mixed>> $doctors Doctors array (by reference)
     * @param array<string, array<string, mixed>> $clinics Clinics array (by reference)
     * @param array<string, array<string, mixed>> $services Services array (by reference)
     * @param array<int, array<string, mixed>> $offers Offers array (by reference)
     */
    public function collect_entities(
        \WP_Post $post,
        array $mapping,
        array &$doctors,
        array &$clinics,
        array &$services,
        array &$offers
    ): void {
        // Get post data
        // v4.18.8: Unified API alignment - extract_post_data_callback is required (no V2 fallback)
        if (!$this->extract_post_data_callback || !is_callable($this->extract_post_data_callback)) {
            throw new \RuntimeException('extract_post_data_callback is required for V3 API alignment');
        }
        $data = call_user_func($this->extract_post_data_callback, $post, $mapping);

        // Create doctor
        // v4.18.17: Проверка наличия callback перед использованием
        if ($this->get_entity_type_callback === null) {
            throw new \RuntimeException('get_entity_type_callback не установлен. Используйте setCallbacks() для установки callbacks.');
        }
        $get_entity_type = $this->get_entity_type_callback;
        $entity_type = $get_entity_type($post->post_type);
        $doctor_id = $entity_type . '_' . $post->ID;

        // v4.18.17: Проверка наличия callback перед использованием
        if ($this->build_doctor_entity_callback === null) {
            throw new \RuntimeException('build_doctor_entity_callback не установлен. Используйте setCallbacks() для установки callbacks.');
        }
        $build_doctor = $this->build_doctor_entity_callback;
        $doctor_entity = $build_doctor($post, $data, $mapping);
        $doctors[$doctor_id] = $doctor_entity;

        // v4.10.6: CRITICAL! Copy extracted fields back to $data for offers
        // This ensures fields from build_doctor_entity() are accessible in build_offers_v2()
        // v4.18.21: DEBUG - проверяем что в doctor_entity ПЕРЕД копированием
        error_log("YFGP v4.18.21 DEBUG EntityCollector collect_entities: AFTER build_doctor_entity - doctor_entity[adult_appointment] = " . var_export($doctor_entity['adult_appointment'] ?? 'NOT SET', true) . ", doctor_entity[children_appointment] = " . var_export($doctor_entity['children_appointment'] ?? 'NOT SET', true) . " for doctor_id: " . ($doctor_entity['id'] ?? 'unknown'));
        foreach (array('adult_appointment', 'children_appointment', 'house_call', 'telemed') as $boolean_field) {
            if (array_key_exists($boolean_field, $doctor_entity)) {
                $data[$boolean_field] = $doctor_entity[$boolean_field];
                // v4.18.21: DEBUG - логируем что скопировали
                error_log("YFGP v4.18.21 DEBUG EntityCollector collect_entities: Copied $boolean_field = " . var_export($doctor_entity[$boolean_field], true) . " from doctor_entity to data for doctor_id: " . ($doctor_entity['id'] ?? 'unknown'));
            } else {
                $data[$boolean_field] = 'false';
                // v4.18.21: DEBUG - логируем установку false (поле не в doctor_entity)
                error_log("YFGP v4.18.21 DEBUG EntityCollector collect_entities: Set $boolean_field = 'false' (not in doctor_entity) for doctor_id: " . ($doctor_entity['id'] ?? 'unknown'));
            }
        }
        // v4.18.21: DEBUG - проверяем что получилось в $data ПОСЛЕ копирования
        error_log("YFGP v4.18.21 DEBUG EntityCollector collect_entities: AFTER copying - data[adult_appointment] = " . var_export($data['adult_appointment'] ?? 'NOT SET', true) . ", data[children_appointment] = " . var_export($data['children_appointment'] ?? 'NOT SET', true) . " for doctor_id: " . ($doctor_entity['id'] ?? 'unknown'));

        // Process clinics
        $post_clinics = $data['clinics'] ?? array(array('id' => 'default', 'name' => '', 'address' => '', 'phone' => '', 'city' => ''));
        foreach ($post_clinics as $clinic_data) {
            $clinic_id = $clinic_data['id'] ?? 'clinic_' . $post->ID;
            if (!isset($clinics[$clinic_id])) {
                // v4.18.17: Проверка наличия callback перед использованием
                if ($this->build_clinic_entity_callback === null) {
                    throw new \RuntimeException('build_clinic_entity_callback не установлен. Используйте setCallbacks() для установки callbacks.');
                }
                $build_clinic = $this->build_clinic_entity_callback;
                $clinics[$clinic_id] = $build_clinic($clinic_data);
            }
        }

        // Process services (NO HARDCODE!)
        $post_services = $data['services'] ?? array();
        foreach ($post_services as $service_data) {
            // v4.4.1: Use service ID from mapper (already clean: service_ID)
            $service_id = $service_data['id'] ?? ('service_' . $post->ID);
            if (!isset($services[$service_id])) {
                // v4.18.17: Проверка наличия callback перед использованием
                if ($this->build_service_entity_callback === null) {
                    throw new \RuntimeException('build_service_entity_callback не установлен. Используйте setCallbacks() для установки callbacks.');
                }
                $build_service = $this->build_service_entity_callback;
                $services[$service_id] = $build_service($service_data);
            }
        }

        // v3.5.3: Create offers (pass global $services for auto-creation!)
        $this->offer_builder->build_offers(
            $post,
            $data,
            $post_clinics,
            $post_services,
            $offers,
            $doctor_id,
            $services
        );
    }
}

