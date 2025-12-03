<?php
/**
 * Yandex Feed Generator Pro - Feed Generator for Yandex Feed Format v2.0
 *
 * @package YandexFeedGeneratorPro
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once YFGP_PLUGIN_DIR . 'includes/trait-feed-generator-shared.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-feed-xml-writer.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-entity-collector.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-offer-builder.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-entity-manager.php'; // v4.18.11: Entity Manager for extensibility
// v4.18.17: Load new refactoring services
// require_once YFGP_PLUGIN_DIR . 'includes/class-feed-orchestrator.php'; // v4.18.17: Feed Orchestrator - loaded via service-factories.php
// require_once YFGP_PLUGIN_DIR . 'includes/class-service-container.php'; // v4.18.17: Service Container - loaded in yandex-feed-generator-pro.php

class YFGP_Feed_Generator_V2 {
    use YFGP_Feed_Generator_Shared_Trait;

    /** @var array<string, mixed> */
    private array $settings;
    // v4.18.17: TEMPORARILY remove type hints to avoid issues with non-existent classes during parsing
    /** @var YFGP_Field_Mapper_V2|null */
    private $field_mapper = null;
    /** @var YFGP_Yml_Stream_Writer|null */
    private $xml_writer = null;
    // v4.18.17: TEMPORARILY change types to mixed to avoid issues with non-existent classes
    /** @var YFGP_Entity_Collector|null */
    private $entity_collector = null;
    /** @var YFGP_Offer_Builder|null */
    private $offer_builder = null;
    /** @var YFGP_Entity_Manager|null */
    private $entity_manager = null; // v4.18.11: Entity Manager для расширяемости
    /** @var YFGP_Feed_Orchestrator|null */
    private $orchestrator = null; // v4.18.17: Feed Orchestrator для координации процесса генерации

    public function __construct() {
        try {
            $this->settings = get_option('yfgp_settings', array());
            $this->settings = $this->normalize_settings($this->settings);
        
        // v4.18.17: Get dependencies through Service Container
        // v4.18.17: TEMPORARILY use fallback to direct initialization if Service Container is not loaded
        if (class_exists('YFGP_Service_Container')) {
            $container = YFGP_Service_Container::get_instance();
            // Get basic dependencies from container
            $this->field_mapper = $container->get('field_mapper_v2');
            $this->xml_writer = $container->get('xml_writer');
        } else {
            // Fallback: create dependencies directly (old code)
            if (!class_exists('YFGP_Field_Mapper_V2')) {
                require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-v2.php';
            }
            // Create instance directly (as in other parts of code)
            if (class_exists('YFGP_Field_Mapper_V2')) {
                $this->field_mapper = new YFGP_Field_Mapper_V2();
            } else {
                $this->field_mapper = null;
            }
            if (!class_exists('YFGP_Yml_Stream_Writer')) {
                require_once YFGP_PLUGIN_DIR . 'includes/class-feed-xml-writer.php';
            }
            if (class_exists('YFGP_Yml_Stream_Writer')) {
                $settings = get_option('yfgp_settings', array());
                $this->xml_writer = new YFGP_Yml_Stream_Writer($settings);
            } else {
                $this->xml_writer = null;
            }
        }
        
        // v4.18.11: Initialize Entity Manager for extensibility
        $this->entity_manager = YFGP_Entity_Manager::get_instance();
        
        // v4.18.0: Initialize decomposed services
        // Create OfferBuilder with callbacks (callbacks require methods from FeedGenerator)
        $this->offer_builder = new YFGP_Offer_Builder(
            $this->settings,
            $this->field_mapper,
            array($this, 'determine_base_service'),
            array($this, 'get_offer_additional_fields')
        );
        
        // Create EntityCollector with callbacks (callbacks require methods from FeedGenerator)
        $this->entity_collector = new YFGP_Entity_Collector(
            $this->field_mapper,
            $this->offer_builder,
            array($this, 'get_entity_type_for_post'),
            array($this, 'build_doctor_entity'),
            array($this, 'build_clinic_entity'),
            array($this, 'build_service_entity'),
            array($this, 'extract_post_data_v3') // v4.18.5: Unified API alignment
        );

        // v4.18.17: Инициализация Feed Orchestrator для координации процесса генерации
        if (class_exists('YFGP_Feed_Orchestrator')) {
            // Получаем Orchestrator через Service Container (DI)
            if (class_exists('YFGP_Service_Container')) {
                try {
                    $container = YFGP_Service_Container::get_instance();
                    $this->orchestrator = $container->get('feed_orchestrator');
                    
                    if ($this->orchestrator === null) {
                        if (function_exists('error_log')) {
                            error_log('YFGP Warning: Service Container returned null for feed_orchestrator. Check registration in service-factories.php');
                        }
                    } else {
                        // v4.18.17: Set callbacks in EntityCollector and OfferBuilder
                        // Callbacks require methods from FeedGenerator, so set them here
                        if (method_exists($this->orchestrator, 'getEntityCollector')) {
                            $entity_collector = $this->orchestrator->getEntityCollector();
                            if ($entity_collector !== null) {
                            // v4.18.20: Get XmlSerializationService (callbacks no longer needed - methods work directly)
                            $xml_serialization_service = null;
                            try {
                                $xml_serialization_service = $container->get('xml_serialization_service');
                            } catch (Exception $e) {
                                // XmlSerializationService may not be registered - use fallback
                                if (function_exists('error_log')) {
                                    error_log('YFGP Warning: Failed to get XmlSerializationService: ' . $e->getMessage());
                                }
                            }
                            
                            // Set callbacks in EntityCollector
                            // v4.18.20: Use XmlSerializationService methods if available, otherwise fallback to Feed_Generator_V2
                            if (method_exists($entity_collector, 'setCallbacks')) {
                                $build_doctor_callback = ($xml_serialization_service !== null) 
                                    ? array($xml_serialization_service, 'build_doctor_entity')
                                    : array($this, 'build_doctor_entity');
                                
                                $build_clinic_callback = ($xml_serialization_service !== null) 
                                    ? array($xml_serialization_service, 'build_clinic_entity')
                                    : array($this, 'build_clinic_entity');
                                
                                $build_service_callback = ($xml_serialization_service !== null) 
                                    ? array($xml_serialization_service, 'build_service_entity')
                                    : array($this, 'build_service_entity');
                                    
                                $entity_collector->setCallbacks(
                                    array($this, 'get_entity_type_for_post'),
                                    $build_doctor_callback,
                                    $build_clinic_callback,
                                    $build_service_callback,
                                    array($this, 'extract_post_data_v3')
                                );
                            }
                            
                            // Set callbacks in OfferBuilder
                            if (method_exists($entity_collector, 'getOfferBuilder')) {
                                $offer_builder = $entity_collector->getOfferBuilder();
                                if ($offer_builder !== null && method_exists($offer_builder, 'setCallbacks')) {
                                    $offer_builder->setCallbacks(
                                        array($this, 'determine_base_service'),
                                        array($this, 'get_offer_additional_fields'),
                                        array($this, 'get_manual_base_service_for_specialization') // v4.20.1: Ручные настройки
                                    );
                                }
                            }
                            }
                        }
                    }
                } catch (Exception $e) {
                    if (function_exists('error_log')) {
                        error_log('YFGP Error: Failed to get Feed Orchestrator from Service Container: ' . $e->getMessage());
                        error_log('YFGP Stack trace: ' . $e->getTraceAsString());
                    }
                    $this->orchestrator = null; // Will be restored in generate() if needed
                }
            } else {
                if (function_exists('error_log')) {
                    error_log('YFGP Warning: YFGP_Service_Container not loaded. Feed Orchestrator will not be initialized in constructor.');
                }
                $this->orchestrator = null; // Will be restored in generate() if needed
            }
        } else {
            if (function_exists('error_log')) {
                error_log('YFGP Warning: YFGP_Feed_Orchestrator not loaded. Check that class is loaded before creating FeedGenerator.');
            }
            // Fallback: orchestrator will be null, will be restored in generate() if needed
            $this->orchestrator = null;
        }
        } catch (Throwable $e) {
            // v4.18.17: Log error but don't interrupt WordPress loading
            if (function_exists('error_log')) {
                error_log('YFGP Error in FeedGenerator constructor: ' . $e->getMessage());
                error_log('YFGP Stack trace: ' . $e->getTraceAsString());
            }
            // Set default values to avoid errors
            $this->settings = array();
            $this->field_mapper = null;
            $this->xml_writer = null;
            $this->entity_manager = null;
            $this->offer_builder = null;
            $this->entity_collector = null;
            $this->orchestrator = null;
        }
    }

    /**
     * Normalize settings on load (migration, sanitization, validation)
     * 
     * @since 4.18.0
     * @param array $settings Plugin settings
     * @return array Normalized settings
     */
    private function normalize_settings($settings) {
        if (empty($settings) || !is_array($settings)) {
            return array();
        }
        
        // Migration of legacy CPT keys if needed
        $cpt_map = array(
            'clinics_post_type' => 'cpt_clinics',
            'services_post_type' => 'cpt_services',
            'reviews_post_type' => 'cpt_reviews',
        );
        
        foreach ($cpt_map as $legacy_key => $new_key) {
            if (isset($settings[$legacy_key]) && !isset($settings[$new_key])) {
                $settings[$new_key] = sanitize_key(trim($settings[$legacy_key]));
            }
        }
        
        // Normalize base_service_mode: remove compatibility mode
        if (isset($settings['base_service_mode']) && $settings['base_service_mode'] === 'compatibility') {
            $settings['base_service_mode'] = 'automatic';
        }
        
        return $settings;
    }

    /**
     * Generate YML feed for specified post type
     * 
     * @since 1.1.0
     * @param string $post_type Post type for generation (default: 'doctors')
     * @return string XML feed string
     * @throws Exception If no posts for generation
     * 
     * @deprecated v4.18.17 Logic moved to YFGP_Feed_Orchestrator::orchestrate()
     *              Method kept as facade for backward compatibility
     */
    public function generate($post_type = 'doctors'): string {
        // v4.18.17: Check Orchestrator initialization
        if ($this->orchestrator === null) {
            // Try to restore through Service Container
            if (class_exists('YFGP_Feed_Orchestrator') && class_exists('YFGP_Service_Container')) {
                try {
                    $container = YFGP_Service_Container::get_instance();
                    $this->orchestrator = $container->get('feed_orchestrator');
                    
                    if ($this->orchestrator === null) {
                        throw new Exception('Feed Orchestrator not registered in Service Container. Check service-factories.php.');
                    }
                    
                    // v4.18.17: Set callbacks in EntityCollector and OfferBuilder
                    if (method_exists($this->orchestrator, 'getEntityCollector')) {
                        $entity_collector = $this->orchestrator->getEntityCollector();
                        if ($entity_collector !== null) {
                            // v4.18.20: Get XmlSerializationService (callbacks no longer needed - methods work directly)
                            $xml_serialization_service = null;
                            try {
                                $xml_serialization_service = $container->get('xml_serialization_service');
                            } catch (Exception $e) {
                                // XmlSerializationService may not be registered - use fallback
                                if (function_exists('error_log')) {
                                    error_log('YFGP Warning: Failed to get XmlSerializationService: ' . $e->getMessage());
                                }
                            }
                            
                            // Set callbacks in EntityCollector
                            // v4.18.20: Use XmlSerializationService methods if available, otherwise fallback to Feed_Generator_V2
                            if (method_exists($entity_collector, 'setCallbacks')) {
                                $build_doctor_callback = ($xml_serialization_service !== null) 
                                    ? array($xml_serialization_service, 'build_doctor_entity')
                                    : array($this, 'build_doctor_entity');
                                
                                $build_clinic_callback = ($xml_serialization_service !== null) 
                                    ? array($xml_serialization_service, 'build_clinic_entity')
                                    : array($this, 'build_clinic_entity');
                                
                                $build_service_callback = ($xml_serialization_service !== null) 
                                    ? array($xml_serialization_service, 'build_service_entity')
                                    : array($this, 'build_service_entity');
                                    
                                $entity_collector->setCallbacks(
                                    array($this, 'get_entity_type_for_post'),
                                    $build_doctor_callback,
                                    $build_clinic_callback,
                                    $build_service_callback,
                                    array($this, 'extract_post_data_v3')
                                );
                            }
                            
                            // Set callbacks in OfferBuilder
                            if (method_exists($entity_collector, 'getOfferBuilder')) {
                                $offer_builder = $entity_collector->getOfferBuilder();
                                if ($offer_builder !== null && method_exists($offer_builder, 'setCallbacks')) {
                                    $offer_builder->setCallbacks(
                                        array($this, 'determine_base_service'),
                                        array($this, 'get_offer_additional_fields'),
                                        array($this, 'get_manual_base_service_for_specialization') // v4.20.1: Ручные настройки
                                    );
                                }
                            }
                        }
                    }
                    
                    // Log successful restoration
                    if (function_exists('error_log')) {
                        error_log('YFGP: Feed Orchestrator restored through Service Container in generate() method');
                    }
                } catch (Exception $e) {
                    if (function_exists('error_log')) {
                        error_log('YFGP Error: Failed to restore Feed Orchestrator: ' . $e->getMessage());
                        error_log('YFGP Stack trace: ' . $e->getTraceAsString());
                    }
                    throw new Exception('Feed Orchestrator not initialized. Check WordPress error logs for details.');
                }
            } else {
                $missing_classes = array();
                if (!class_exists('YFGP_Feed_Orchestrator')) {
                    $missing_classes[] = 'YFGP_Feed_Orchestrator';
                }
                if (!class_exists('YFGP_Service_Container')) {
                    $missing_classes[] = 'YFGP_Service_Container';
                }
                
                if (function_exists('error_log')) {
                    error_log('YFGP Error: Classes not loaded: ' . implode(', ', $missing_classes));
                    error_log('YFGP: Check that yfgp_load_classes() executed successfully on plugins_loaded hook');
                }
                
                throw new Exception('Feed Orchestrator not loaded. Missing classes: ' . implode(', ', $missing_classes) . '. Check WordPress error logs.');
            }
        }
        
        // v4.18.17: Delegate to Feed Orchestrator
        return $this->orchestrator->orchestrate($post_type);
    }

    /**
     * Get post data
     */
    private function get_posts(string $post_type): array {
        $args = array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        );
        
        return get_posts($args);
    }

    /**
     * Collect entities from post
     *
     * @param \WP_Post $post Post object
     * @param array<string, mixed> $mapping Field mapping configuration
     * @param array<int, array<string, mixed>> $doctors Doctors array (by reference)
     * @param array<string, array<string, mixed>> $clinics Clinics array (by reference)
     * @param array<string, array<string, mixed>> $services Services array (by reference)
     * @param array<int, array<string, mixed>> $offers Offers array (by reference)
     * @deprecated v4.18.0 Use YFGP_Entity_Collector::collect_entities() instead of this method
     */
    private function collect_entities($post, array $mapping, array &$doctors, array &$clinics, array &$services, array &$offers): void {
        // Get post data
        // v4.18.7: Unified API alignment - use extract_post_data_v3 instead of map_post_data_v2
        $data = $this->extract_post_data_v3($post, $mapping);
        
        // Create doctor entity
        // @fix: ID must match entity_type from mapping, not post_type
        $entity_type = $this->get_entity_type_for_post($post->post_type);
        $doctor_id = $entity_type . '_' . $post->ID;
        // v4.18.21: DEBUG - check mapping BEFORE calling build_doctor_entity
        error_log("YFGP v4.18.21 DEBUG collect_entities: BEFORE build_doctor_entity for post_id: " . $post->ID . ", mapping[adult_appointment] = " . var_export($mapping['adult_appointment'] ?? 'NOT SET', true) . ", mapping[children_appointment] = " . var_export($mapping['children_appointment'] ?? 'NOT SET', true));
        $doctor_entity = $this->build_doctor_entity($post, $data, $mapping);
        $doctors[$doctor_id] = $doctor_entity;
        
        // v4.10.6: CRITICAL! Copy extracted fields back to $data for offers
        // This ensures fields from build_doctor_entity() are accessible in build_offers_v2()
        // v4.18.21: DEBUG - check doctor_entity BEFORE copying
        error_log("YFGP v4.18.21 DEBUG collect_entities: AFTER build_doctor_entity - doctor_entity[adult_appointment] = " . var_export($doctor_entity['adult_appointment'] ?? 'NOT SET', true) . ", doctor_entity[children_appointment] = " . var_export($doctor_entity['children_appointment'] ?? 'NOT SET', true) . " for doctor_id: " . ($doctor_entity['id'] ?? 'unknown'));
        
        foreach (array('adult_appointment', 'children_appointment', 'house_call', 'telemed') as $boolean_field) {
            if (array_key_exists($boolean_field, $doctor_entity)) {
                $data[$boolean_field] = $doctor_entity[$boolean_field];
                // v4.18.21: DEBUG - log what we copied
                error_log("YFGP v4.18.21 DEBUG collect_entities: Copied $boolean_field = " . var_export($doctor_entity[$boolean_field], true) . " from doctor_entity to data for doctor_id: " . ($doctor_entity['id'] ?? 'unknown'));
            } else {
                $data[$boolean_field] = 'false';
                // v4.18.21: DEBUG - log setting false (field not in doctor_entity)
                error_log("YFGP v4.18.21 DEBUG collect_entities: Set $boolean_field = 'false' (not in doctor_entity) for doctor_id: " . ($doctor_entity['id'] ?? 'unknown'));
            }
        }
        
        // v4.18.21: DEBUG - check what we got in $data AFTER copying
        error_log("YFGP v4.18.21 DEBUG collect_entities: AFTER copying - data[adult_appointment] = " . var_export($data['adult_appointment'] ?? 'NOT SET', true) . ", data[children_appointment] = " . var_export($data['children_appointment'] ?? 'NOT SET', true) . " for doctor_id: " . ($doctor_entity['id'] ?? 'unknown'));
        
        // Process clinics
        $post_clinics = $data['clinics'] ?? array(array('id' => 'default', 'name' => '', 'address' => '', 'phone' => '', 'city' => ''));
        foreach ($post_clinics as $clinic_data) {
            $clinic_id = $clinic_data['id'] ?? 'clinic_' . $post->ID;
            if (!isset($clinics[$clinic_id])) {
                $clinics[$clinic_id] = $this->build_clinic_entity($clinic_data);
            }
        }
        
        // Process services (NO HARDCODE!)
        $post_services = $data['services'] ?? array();
        foreach ($post_services as $service_data) {
            // v4.4.1: Use service ID from mapper (already clean: service_ID)
            $service_id = $service_data['id'] ?? ('service_' . $post->ID);
            if (!isset($services[$service_id])) {
                $services[$service_id] = $this->build_service_entity($service_data);
            }
        }
        
        // v3.5.3: Create offers (pass global $services for auto-creation!)
        $this->build_offers_v2($post, $data, $post_clinics, $post_services, $offers, $doctor_id, $services);
    }

    /**
     * Create doctor entity
     * @internal Используется через callback в YFGP_Entity_Collector
     */
    public function build_doctor_entity($post, array $data, array $mapping = array()): array {
        $params = $data['params'] ?? array();
        
        // @fix: ID must match entity_type from mapping, not post_type
        $entity_type = $this->get_entity_type_for_post($post->post_type);
        $doctor = array(
            'id' => $entity_type . '_' . $post->ID,
            'name' => $post->post_title,
            'url' => get_permalink($post->ID),
            'internal_id' => $post->ID,
            'description' => $this->sanitize_feed_text($data['description'] ?? get_the_excerpt($post->ID)),
        );

        // Add name components if available
        if (!empty($params['surname'])) $doctor['surname'] = $params['surname'];
        if (!empty($params['first_name'])) $doctor['first_name'] = $params['first_name'];
        if (!empty($params['patronymic'])) $doctor['patronymic'] = $params['patronymic'];
        // v4.18.6: FIX - if full name not set from $params, split post_title
        if (empty($doctor['surname']) && !empty($post->post_title)) {
            $parts = explode(' ', trim($post->post_title));
            if (!empty($parts[0])) $doctor['surname'] = $parts[0];
            if (!empty($parts[1])) $doctor['first_name'] = $parts[1];
            if (!empty($parts[2])) $doctor['patronymic'] = $parts[2];
        }
        
        
        // v4.1.0-beta21: Extract V3 fields using V3 API (clean separation!)
        // v4.18.2: Important - v3_fields override values from $data (if they exist)
        // v4.18.5: Unified API alignment - mapping передается как параметр (не get_option)
        if (empty($mapping)) {
            $mapping = get_option('yfgp_field_mapping_v3', array()); // Fallback for backward compatibility
        }
        // v4.18.6: Save full name before merge with v3_fields (protect from overwriting with empty values)
        $saved_fio = array();
        if (!empty($doctor['surname'])) $saved_fio['surname'] = $doctor['surname'];
        if (!empty($doctor['first_name'])) $saved_fio['first_name'] = $doctor['first_name'];
        if (!empty($doctor['patronymic'])) $saved_fio['patronymic'] = $doctor['patronymic'];
        
        $v3_fields = $this->extract_v3_fields($post->ID, $mapping);
        // v4.18.6: Remove full name from v3_fields if empty (protect from overwriting correct values)
        $fio_keys = array('surname', 'first_name', 'patronymic');
        foreach ($fio_keys as $fio_key) {
            if (isset($v3_fields[$fio_key]) && (empty($v3_fields[$fio_key]) || trim($v3_fields[$fio_key]) === '')) {
                unset($v3_fields[$fio_key]);
            }
        }
        // v4.18.2: Мержим v3_fields ПЕРЕД $doctor, чтобы они перезаписали значения из $data
        $doctor = array_merge($doctor, $v3_fields);
        
        // v4.18.6: Restore full name if it was set from $params (protect from overwriting with empty values from v3_fields)
        error_log('YFGP v4.18.6 DEBUG build_doctor_entity: saved_fio=' . json_encode($saved_fio));
        error_log('YFGP v4.18.6 DEBUG build_doctor_entity: Before restore - surname=' . ($doctor['surname'] ?? 'NOT SET') . ', first_name=' . ($doctor['first_name'] ?? 'NOT SET') . ', patronymic=' . ($doctor['patronymic'] ?? 'NOT SET'));
        foreach ($saved_fio as $key => $value) {
            if (!empty($value) && trim($value) !== '') {
                $doctor[$key] = $value;
            }
        }
        error_log('YFGP v4.18.6 DEBUG build_doctor_entity: After restore - surname=' . ($doctor['surname'] ?? 'NOT SET') . ', first_name=' . ($doctor['first_name'] ?? 'NOT SET') . ', patronymic=' . ($doctor['patronymic'] ?? 'NOT SET'));
        
        // v4.18.6: Debug - final values before return
        error_log('YFGP v4.18.6 DEBUG build_doctor_entity: FINAL - surname=' . ($doctor['surname'] ?? 'NOT SET') . ', first_name=' . ($doctor['first_name'] ?? 'NOT SET') . ', patronymic=' . ($doctor['patronymic'] ?? 'NOT SET'));
        
        // v4.18.1: Validate career_start_date format (YYYY-MM-DD)
        if (!empty($doctor['career_start_date'])) {
            $validated_date = $this->validate_and_parse_date($doctor['career_start_date']);
            if ($validated_date === null) {
                // v4.18.1: Если формат невалидный - предупреждение в лог и очистка поля
                error_log('YFGP v4.18.1: Warning - Invalid career_start_date format: ' . $doctor['career_start_date'] . ' (expected YYYY-MM-DD)');
                unset($doctor['career_start_date']);
            } else {
                // v4.18.22: Нормализуем дату в формат YYYY-MM-DD
                $doctor['career_start_date'] = date('Y-m-d', $validated_date);
            }
        }

        // @fix: featured image ALWAYS has priority over mapping
        if (has_post_thumbnail($post->ID)) {
            $doctor['picture'] = get_the_post_thumbnail_url($post->ID, 'full');
        } else {
            // Only if no minimum - take from mapping
            if (!empty($v3_fields['picture'])) {
                $doctor['picture'] = $v3_fields['picture'];
            }
        }

        // v4.10.11: Clean HTML tags from degree field (fix HTML entities issue)
        // Pattern: "Fix at build_entity (extraction), not build_xml (generation)"
        // v4.18.2: Fix strip_tags() error when degree is array
        if (!empty($doctor['degree'])) {
            $degree = $doctor['degree'];
            
            // v4.18.2: Check data type - if array, convert to string
            if (is_array($degree)) {
                $degree = implode(', ', array_filter($degree, function($item) {
                    return !is_array($item) && $item !== null && $item !== '';
                }));
            }
            
            // Only for strings - apply strip_tags
            if (is_string($degree)) {
                // Remove <br> tags (both formats)
                $degree = str_replace('<br>', ' ', $degree);
                $degree = str_replace('<br />', ' ', $degree);
                $degree = str_replace('<BR>', ' ', $degree); // Uppercase variant
                // Strip all remaining HTML tags
                $degree = strip_tags($degree);
                // Clean up multiple spaces
                $degree = preg_replace('/\s+/', ' ', $degree);
                $degree = trim($degree);
            }
            
            $doctor['degree'] = $degree;
        }
        
        // @fix: Remove hardcode of repeater keys - get from mapping by pattern (type . '_repeater_field')
        // Extract structured blocks via unified mapper (education/job/certificate/reviews)
        $education_key = $this->get_repeater_field_key($mapping, 'education');
        if ($education_key !== null) {
            $education_rows = $this->extract_repeater_block(
                $post->ID,
                $mapping,
                $education_key,
                array(
                    'education_organization' => 'organization',
                    'education_finish_year' => 'finish_year',
                    'education_type' => 'type',
                    'education_specialization' => 'specialization',
                )
            );
            if (!empty($education_rows)) {
                $doctor['education'] = $education_rows;
            }
        }
        
        $job_key = $this->get_repeater_field_key($mapping, 'job');
        if ($job_key !== null) {
            $job_rows = $this->extract_repeater_block(
                $post->ID,
                $mapping,
                $job_key,
                array(
                    'job_organization' => 'organization',
                    'job_period_years' => 'period_years',
                    'job_position' => 'position',
                )
            );
            if (!empty($job_rows)) {
                $doctor['job'] = $job_rows;
            }
        }
        
        $certificate_key = $this->get_repeater_field_key($mapping, 'certificate');
        if ($certificate_key !== null) {
            $certificate_rows = $this->extract_repeater_block(
                $post->ID,
                $mapping,
                $certificate_key,
                array(
                    'certificate_organization' => 'organization',
                    'certificate_finish_year' => 'finish_year',
                    'certificate_name' => 'name',
                )
            );
            if (!empty($certificate_rows)) {
                $doctor['certificate'] = $certificate_rows;
            }
        }
        
        // v4.18.1: Auto-create reviews mapping, if it doesn't exist, but settings exist
        if (empty($mapping['reviews']) || empty($mapping['reviews']['source_type'])) {
            $source_preference = $this->settings['source_reviews'] ?? 'repeater';
            $target_cpt = trim((string) ($this->settings['source_reviews_cpt'] ?? ''));
            
            if ($source_preference === 'relationship' && $target_cpt !== '') {
                // Search relationship field, which is related to CPT reviews
                foreach ($mapping as $field_key => $field_config) {
                    if (is_array($field_config) && 
                        isset($field_config['source_type']) && 
                        strpos($field_config['source_type'], 'relationship') === 0 &&
                        isset($field_config['source_cpt']) && 
                        $field_config['source_cpt'] === $target_cpt) {
                        $mapping['reviews'] = array(
                            'source_type' => $field_config['source_type'],
                            'source_field' => $field_config['source_field'] ?? '',
                            'source_cpt' => $target_cpt
                        );
                        break;
                    }
                }
            }
        }
        
        $reviews_block_key = $this->get_reviews_source_config($this->settings, $mapping);
        $reviews_rows = $this->extract_repeater_block(
            $post->ID,
            $mapping,
            $reviews_block_key ?? 'reviews_repeater_field',
            array(
                'reviews_date' => 'date',
                'reviews_checked' => 'checked',
                'reviews_used_in_rating' => 'used_in_rating',
                'reviews_author' => 'author',
                'reviews_author_id' => 'author_id',
                'reviews_author_picture' => 'author_picture',
                'reviews_url' => 'url',
                'reviews_comment' => 'comment',
                'reviews_grade' => 'grade',
                'reviews_positive' => 'positive',
                'reviews_negative' => 'negative',
                'reviews_response' => 'response',
            ),
            array('reviews_total_count')
        );
        if (!empty($reviews_rows)) {
            $doctor['reviews'] = $reviews_rows;
        }
        
        // v4.18.3: Extract appointment flags через unified mapper + conditional logic
        // v4.18.21: Универсализация - используем общую функцию для boolean полей с conditional logic
        $adult_appointment_value = $this->extract_boolean_field_with_conditional_logic($post->ID, $mapping['adult_appointment'] ?? null);
        if ($adult_appointment_value !== null) {
            $doctor['adult_appointment'] = $adult_appointment_value;
        }
        
        $children_appointment_value = $this->extract_boolean_field_with_conditional_logic($post->ID, $mapping['children_appointment'] ?? null);
        if ($children_appointment_value !== null) {
            $doctor['children_appointment'] = $children_appointment_value;
        }

        $reviews_total_count = null;
        if (!empty($mapping['reviews_total_count'])) {
            $raw_reviews_count = $this->get_v3_value_from_unified($post->ID, $mapping['reviews_total_count']);
            if (is_array($raw_reviews_count)) {
                $reviews_total_count = count($raw_reviews_count);
            } elseif (is_numeric($raw_reviews_count)) {
                $reviews_total_count = (int) $raw_reviews_count;
            } elseif (is_string($raw_reviews_count)) {
                $trimmed = trim($raw_reviews_count);
                if ($trimmed !== '' && is_numeric($trimmed)) {
                    $reviews_total_count = (int) $trimmed;
                }
            }
        }

        // Extract boolean fields (house_call, telemed) via universal function (supports conditional logic)
        $house_call_value = $this->extract_boolean_field_with_conditional_logic($post->ID, $mapping['house_call'] ?? null);
        if ($house_call_value !== null) {
            $doctor['house_call'] = $house_call_value;
        }

        $telemed_value = $this->extract_boolean_field_with_conditional_logic($post->ID, $mapping['telemed'] ?? null);
        if ($telemed_value !== null) {
            $doctor['telemed'] = $telemed_value;
        }

        if (!empty($doctor['reviews']) && is_array($doctor['reviews'])) {
            // v4.18.3: Filter reviews without grade (according to Yandex specification)
            $reviews_with_grade = array_filter($doctor['reviews'], function($review) {
                return !empty($review['grade']);
            });
            
            if ($reviews_total_count === null) {
                $reviews_total_count = count($reviews_with_grade);
            }
            
            // Update reviews array, leaving only reviews with grade
            $doctor['reviews'] = array_values($reviews_with_grade);
            
            foreach ($doctor['reviews'] as &$review) {
                if (!empty($review['comment'])) {
                    $review['comment'] = strip_tags($review['comment']);
                }
                if (!empty($review['positive'])) {
                    $review['positive'] = strip_tags($review['positive']);
                }
                if (!empty($review['negative'])) {
                    $review['negative'] = strip_tags($review['negative']);
                }
                if (!empty($review['response'])) {
                    $review['response'] = strip_tags($review['response']);
                }
                $review['checked'] = $this->normalize_boolean_string($review['checked'] ?? true, true);
                $review['used_in_rating'] = $this->normalize_boolean_string($review['used_in_rating'] ?? true, true);
            }
            unset($review);
        }
        if ($reviews_total_count !== null) {
            $doctor['reviews_total_count'] = $reviews_total_count;
        } elseif (empty($doctor['reviews_total_count']) && !empty($doctor['reviews']) && is_array($doctor['reviews'])) {
            // v4.18.3: Fallback - count from reviews array (already filtered by grade) if reviews_total_count not specified
            $doctor['reviews_total_count'] = count($doctor['reviews']);
            error_log('YFGP v4.18.3: Calculated reviews_total_count from reviews array (filtered by grade): ' . $doctor['reviews_total_count']);
        }

        // Add experience
        // v4.18.21: Приоритет 1 - из v3_fields (если он был извлечен напрямую через experience_years mapping)
        // v3_fields уже были смержены в $doctor выше (строка 482), поэтому проверяем напрямую
        // Приоритет 2 - из params (legacy путь через work_experience)
        if (!isset($doctor['experience_years']) || $doctor['experience_years'] === '' || $doctor['experience_years'] === null) {
            // v4.18.21: Исправлены крокозябры в ключе массива
            if (!empty($params['Годы опыта'])) {
                $doctor['experience_years'] = $params['Годы опыта'];
            } elseif (!empty($params['Опыт работы'])) {
                // v4.18.21: Fallback на 'Опыт работы' для обратной совместимости
                $doctor['experience_years'] = $params['Опыт работы'];
            }
        }
        
        // v4.18.1: Fallback для experience_years - рассчитываем из career_start_date если есть
        if ((!isset($doctor['experience_years']) || $doctor['experience_years'] === '' || $doctor['experience_years'] === null) && !empty($doctor['career_start_date'])) {
            $career_date = $this->validate_and_parse_date($doctor['career_start_date']);
            if ($career_date !== null) {
                $current_year = (int) date('Y');
                $career_year = (int) date('Y', $career_date);
                $experience_years = $current_year - $career_year;
                // v4.18.21: Разрешаем 0 и отрицательные значения (для дат в будущем выводим 0)
                if ($experience_years >= 0) {
                    $doctor['experience_years'] = (string) $experience_years;
                    error_log('YFGP v4.18.21: Calculated experience_years from career_start_date: ' . $experience_years . ' years');
                } else {
                    // Дата в будущем - выводим 0
                    $doctor['experience_years'] = '0';
                    error_log('YFGP v4.18.21: Warning - career_start_date is in the future, setting experience_years to 0');
                }
            }
        }
        
        // v4.18.21: Финальный fallback - если experience_years все еще пустой, устанавливаем '0'
        if (!isset($doctor['experience_years']) || $doctor['experience_years'] === '' || $doctor['experience_years'] === null) {
            $doctor['experience_years'] = '0';
            error_log('YFGP v4.18.21: Warning - experience_years is empty, setting to 0 for doctor ' . ($doctor['id'] ?? 'unknown'));
        }
        
        // v4.18.21: Debug - финальное значение experience_years перед возвратом
        error_log('YFGP v4.18.21 DEBUG build_doctor_entity: FINAL experience_years=' . ($doctor['experience_years'] ?? 'NOT SET') . ' for doctor ' . ($doctor['id'] ?? 'unknown'));
        
        // v4.1.0: FIX surname - если surname совпадает с полным именем, извлечь только фамилию
        if (!empty($doctor['surname']) && !empty($doctor['name'])) {
            // If surname contains spaces and starts like name, take only first word
            if (strpos($doctor['surname'], ' ') !== false && stripos($doctor['name'], $doctor['surname']) === 0) {
                $parts = explode(' ', trim($doctor['surname']));
                $doctor['surname'] = $parts[0]; // Only first word (surname)
            }
        }
        
        // v4.1.0: FIX reviews - strip HTML tags from comment/positive/negative BEFORE XML generation
        if (!empty($doctor['reviews']) && is_array($doctor['reviews'])) {
            foreach ($doctor['reviews'] as &$review) {
                if (!empty($review['comment'])) {
                    $review['comment'] = strip_tags($review['comment']);
                }
                if (!empty($review['positive'])) {
                    $review['positive'] = strip_tags($review['positive']);
                }
                if (!empty($review['negative'])) {
                    $review['negative'] = strip_tags($review['negative']);
                }
                if (!empty($review['response'])) {
                    $review['response'] = strip_tags($review['response']);
                }
            }
        }
        
        return $doctor;
    }
    
    /**
     * Extract post data using Unified mapper with V3 mapping (replaces map_post_data_v2)
     * 
     * @since 4.18.5 - Unified API alignment (V2 ↔ V3)
     * @param \WP_Post $post Post object
     * @param array<string, mixed> $mapping V3 mapping (config arrays)
     * @return array<string, mixed> Extracted data in same format as map_post_data_v2
     */
    /**
     * Extract post data using Unified mapper with V3 mapping config
     * 
     * @since 4.18.5
     * @param \WP_Post $post Post object
     * @param array<string, mixed> $mapping V3 mapping config
     * @return array<string, mixed> Extracted data in V2-compatible format
     * 
     * @note v4.18.11: Changed visibility to public to allow callback usage in EntityCollector
     */
    public function extract_post_data_v3(\WP_Post $post, array $mapping): array {
        // v4.18.7: Unified API alignment - use Unified mapper instead of V2 mapper
        // Initialize data structure compatible with build_doctor_entity()
        $data = array(
            'post_id' => $post->ID,
            'params' => array(),
            'clinics' => array(),
            'services' => array(),
            'set_ids' => '',
            'description' => '',
            'specializations_text' => array($this->field_mapper->get_fallback_speciality_text()),
        );
        
        // Get Unified mapper instance
        if (!class_exists('YFGP_Field_Mapper_Unified')) {
            require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
        }
        $mapper_unified = YFGP_Field_Mapper_Unified::get_instance();
        
        // Extract ФИО (surname, firstname, patronymic or name)
        if (!empty($mapping['surname']) && !empty($mapping['firstname']) && !empty($mapping['patronymic'])) {
            // Use separate fields
            $surname_config = $mapping['surname'];
            $firstname_config = $mapping['firstname'];
            $patronymic_config = $mapping['patronymic'];
            
            $data['params']['Фамилия'] = $this->extract_field_value_unified($post->ID, $surname_config, $mapper_unified, $post->post_title);
            $data['params']['Имя'] = $this->extract_field_value_unified($post->ID, $firstname_config, $mapper_unified);
            $data['params']['Отчество'] = $this->extract_field_value_unified($post->ID, $patronymic_config, $mapper_unified);
        } elseif (!empty($mapping['name'])) {
            // Use full name field and split
            $name_config = $mapping['name'];
            $full_name = $this->extract_field_value_unified($post->ID, $name_config, $mapper_unified, $post->post_title);
            $parts = explode(' ', trim($full_name));
            $data['params']['Фамилия'] = $parts[0] ?? '';
            $data['params']['Имя'] = $parts[1] ?? '';
            $data['params']['Отчество'] = $parts[2] ?? '';
        } else {
            // Fallback to post_title
            $parts = explode(' ', trim($post->post_title));
            $data['params']['Фамилия'] = $parts[0] ?? '';
            $data['params']['Имя'] = $parts[1] ?? '';
            $data['params']['Отчество'] = $parts[2] ?? '';
        }
        
        // Extract work experience
        // v4.18.21: Унифицирован ключ - используем 'Годы опыта' вместо 'Опыт работы' для совместимости с build_doctor_entity()
        if (!empty($mapping['work_experience'])) {
            $experience_config = $mapping['work_experience'];
            $experience = $this->extract_field_value_unified($post->ID, $experience_config, $mapper_unified);
            $data['params']['Годы опыта'] = $this->field_mapper->calculate_experience($experience);
        } else {
            $data['params']['Годы опыта'] = '0';
        }
        
        // Extract specialities
        if (!empty($mapping['specialities'])) {
            $specialities_config = $mapping['specialities'];
            $specialization = $this->extract_field_value_unified($post->ID, $specialities_config, $mapper_unified);
            
            // v4.18.11: Use public method normalize_specialities_external() instead of private normalize_specialities()
            $normalized = $this->field_mapper->normalize_specialities_external($post, $specialities_config, $specialization);
            
            $data['set_ids'] = $normalized['set_ids'];
            $data['specializations_text'] = $normalized['labels'];
            
            $data['specialities'] = array();
            $slugs = $normalized['set_ids'];
            $labels = $normalized['labels'];
            $count = count($slugs);
            
            for ($i = 0; $i < $count; $i++) {
                $slug = $slugs[$i];
                $label = $labels[$i] ?? ($labels[0] ?? '');
                $data['specialities'][] = array(
                    'slug' => $slug,
                    'name' => function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label)
                );
            }
        } else {
            $fallback = $this->field_mapper->get_speciality_fallback();
            $data['set_ids'] = array($fallback['slug']);
            $data['specializations_text'] = array($fallback['label']);
            
            $fallback_name = function_exists('mb_strtolower') ? mb_strtolower($fallback['label'], 'UTF-8') : strtolower($fallback['label']);
            $data['specialities'] = array(
                array('slug' => $fallback['slug'], 'name' => $fallback_name)
            );
        }
        
        // Extract description
        if (!empty($mapping['description'])) {
            $description_config = $mapping['description'];
            $data['description'] = $this->extract_field_value_unified($post->ID, $description_config, $mapper_unified, $post->post_content);
        } else {
            $data['description'] = $post->post_content;
        }
        
        // Extract clinics (relationships)
        if (!empty($mapping['clinics'])) {
            $clinics_config = $mapping['clinics'];
            $clinic_ids = $this->extract_related_posts_unified($post->ID, $clinics_config, $mapper_unified);
            
            if (!empty($clinic_ids)) {
                $clinic_field_map = array(
                    'address'    => 'clinics_address',
                    'phone'      => 'clinics_phone',
                    'email'      => 'clinics_email',
                    'picture'    => 'clinics_picture',
                    'city'       => 'clinics_city',
                    'company_id' => 'clinics_company_id',
                    'internal_id'=> 'clinics_internal_id',
                );
                foreach ($clinic_ids as $clinic_id) {
                    $clinic_post = get_post($clinic_id);
                    if (!$clinic_post) {
                        continue;
                    }
                    $clinic_entry = array(
                        'id'        => $clinic_id,
                        'name'      => $clinic_post->post_title,
                        'url'       => get_permalink($clinic_id),
                        'post_id'   => $clinic_id,
                        'post_type' => $clinic_post->post_type,
                    );
                    foreach ($clinic_field_map as $target_key => $mapping_key) {
                        if (empty($mapping[$mapping_key])) {
                            continue;
                        }
                        $value = $this->extract_field_value_unified($clinic_id, $mapping[$mapping_key], $mapper_unified, '');
                        if ($value === '' || $value === null) {
                            continue;
                        }
                        if ($target_key === 'picture') {
                            $value = $this->normalize_media_value($value);
                        }
                        if (in_array($target_key, array('address', 'phone', 'email'), true) && is_array($value)) {
                            $value = implode(', ', array_filter(array_map('trim', $value)));
                        }
                        $clinic_entry[$target_key] = $value;
                    }
                    if (empty($clinic_entry['city']) && !empty($this->settings['city'])) {
                        $clinic_entry['city'] = $this->settings['city'];
                    }
                    if (empty($clinic_entry['internal_id'])) {
                        $clinic_entry['internal_id'] = $clinic_id;
                    }
                    $data['clinics'][] = $clinic_entry;
                }
            }
        }

        // v4.18.22: Single clinic mode - inject clinic from mapping when CPT is not selected
        $is_single_clinic_mode = empty($this->settings['cpt_clinics'] ?? '');
        if ($is_single_clinic_mode) {
            $single_clinic = $this->field_mapper->get_single_clinic_from_mapping($mapping, $post->ID);
            if (!empty($single_clinic)) {
                $data['clinics'] = array($single_clinic);
            }
        }
        
        // Extract services (relationships)
        if (!empty($mapping['services'])) {
            $services_config = $mapping['services'];
            $service_ids = $this->extract_related_posts_unified($post->ID, $services_config, $mapper_unified);
            
            if (!empty($service_ids)) {
                foreach ($service_ids as $service_id) {
                    $service_post = get_post($service_id);
                    if (!$service_post) {
                        continue;
                    }
                    $service_entry = array(
                        'id'        => $service_id,
                        'name'      => $service_post->post_title,
                        'url'       => get_permalink($service_id),
                        'post_id'   => $service_id,
                        'post_type' => $service_post->post_type,
                    );
                    $this->populate_service_fields_from_mapping($service_entry, $service_post->ID, $mapping, $mapper_unified);
                    $data['services'][] = $service_entry;
                }
            }
        }
        
        // v4.18.22: Extract base_service_id (explicit base service from doctor)
        if (!empty($mapping['base_service_id'])) {
            $base_service_config = $mapping['base_service_id'];
            $base_service_value = $mapper_unified->getFieldValue($post->ID, $base_service_config, true);
            
            if ($base_service_value) {
                // Normalize: support both object and ID
                if (is_object($base_service_value) && isset($base_service_value->ID)) {
                    $data['base_service_id'] = 'service_' . $base_service_value->ID;
                } elseif (is_numeric($base_service_value)) {
                    $data['base_service_id'] = 'service_' . $base_service_value;
                } elseif (is_array($base_service_value) && !empty($base_service_value)) {
                    // Handle array (take first)
                    $first = reset($base_service_value);
                    if (is_object($first) && isset($first->ID)) {
                        $data['base_service_id'] = 'service_' . $first->ID;
                    } elseif (is_numeric($first)) {
                        $data['base_service_id'] = 'service_' . $first;
                    }
                }
                
                if (isset($data['base_service_id'])) {
                    error_log('YFGP v4.18.22: Base service ID extracted for doctor ' . $post->ID . ': ' . $data['base_service_id']);
                }
            }
        }
        
        // v4.18.22: DEBUG - логирование порядка услуг
        if (!empty($data['services'])) {
            $service_ids = array_map(function($s) { 
                return $s['id']; 
            }, $data['services']);
            error_log('YFGP v4.18.22 DEBUG: Services extracted for doctor ' . $post->ID . ' (order): ' . implode(', ', $service_ids));
        }
        
        // v4.18.21: DEBUG - проверяем что возвращается из extract_post_data_v3
        error_log("YFGP v4.18.21 DEBUG extract_post_data_v3: RETURN - data[adult_appointment] = " . var_export($data['adult_appointment'] ?? 'NOT SET', true) . ", data[children_appointment] = " . var_export($data['children_appointment'] ?? 'NOT SET', true) . " for post_id: " . $post->ID);
        
        return $data;
    }
    
    /**
     * Populate service-related fields (description, media, prices) using mapping config.
     */
    private function populate_service_fields_from_mapping(array &$service_entry, int $service_post_id, array $mapping, $mapper_unified): void {
        $service_field_map = array(
            'description' => 'services_description',
            'gov_id'      => 'services_gov_id',
            'picture'     => 'services_picture',
            'internal_id' => 'services_internal_id',
        );
        foreach ($service_field_map as $target_key => $mapping_key) {
            if (empty($mapping[$mapping_key])) {
                continue;
            }
            $value = $this->extract_field_value_unified($service_post_id, $mapping[$mapping_key], $mapper_unified, '');
            if ($value === '' || $value === null) {
                continue;
            }
            if ($target_key === 'picture') {
                $value = $this->normalize_media_value($value);
            }
            $service_entry[$target_key] = $value;
        }
        if (empty($service_entry['internal_id'])) {
            $service_entry['internal_id'] = $service_post_id;
        }
        if (!empty($mapping['prices_price_source_field'])) {
            $price_ids = $this->extract_related_posts_unified($service_post_id, $mapping['prices_price_source_field'], $mapper_unified);
            $price_details = $this->extract_price_details_for_service($price_ids, $mapping, $mapper_unified);
            if (!empty($price_details)) {
                $service_entry = array_merge($service_entry, $price_details);
            }
        }
    }
    
    /**
     * Extract price-related data for a service using related price posts.
     */
    private function extract_price_details_for_service(array $price_ids, array $mapping, $mapper_unified): array {
        if (empty($price_ids) || empty($mapping['prices_base_price'])) {
            return array();
        }
        $candidates = array();
        foreach ($price_ids as $price_id) {
            $raw_price = $this->extract_field_value_unified($price_id, $mapping['prices_base_price'], $mapper_unified, null);
            $normalized = $this->normalize_price_value($raw_price);
            if ($normalized === null) {
                continue;
            }
            $candidates[] = array('post_id' => $price_id, 'value' => $normalized);
        }
        if (empty($candidates)) {
            return array();
        }
        usort($candidates, function($a, $b) {
            return $a['value'] <=> $b['value'];
        });
        $selected = $candidates[0];
        $price_post_id = $selected['post_id'];
        $details = array(
            'price' => $selected['value'],
        );
        $currency = $this->settings['default_currency'] ?? 'RUR';
        if (!empty($mapping['prices_currency'])) {
            $currency_value = $this->extract_field_value_unified($price_post_id, $mapping['prices_currency'], $mapper_unified, $currency);
            if (!empty($currency_value)) {
                $currency = is_array($currency_value) ? reset($currency_value) : $currency_value;
            }
        }
        $details['currency'] = $currency;
        // v4.18.21: Extract price_discount - независимо от discount_name (критично!)
        if (!empty($mapping['prices_discount'])) {
            try {
                $discount_value = $this->extract_field_value_unified($price_post_id, $mapping['prices_discount'], $mapper_unified, null);
                $normalized_discount = $this->normalize_price_value($discount_value);
                if ($normalized_discount !== null && $normalized_discount >= 0) {
                    $details['price_discount'] = $normalized_discount;
                }
            } catch (Exception $e) {
                error_log('YFGP v4.18.21: Error extracting price_discount for price_post_id ' . $price_post_id . ': ' . $e->getMessage());
            }
        }
        // v4.18.18: Extract discount_name from prices_discount_name mapping
        // v4.18.21: Независимо от price_discount - даже если discount_name не извлекается, price_discount должен выводиться
        if (!empty($mapping['prices_discount_name'])) {
            try {
                $discount_name_value = $this->extract_field_value_unified($price_post_id, $mapping['prices_discount_name'], $mapper_unified, null);
                if (!empty($discount_name_value)) {
                    $details['discount_name'] = is_array($discount_name_value) ? reset($discount_name_value) : $discount_name_value;
                    error_log('YFGP v4.18.21: Extracted discount_name from price_post_id ' . $price_post_id . ': ' . $details['discount_name']);
                } else {
                    error_log('YFGP v4.18.21: Warning - discount_name_value is empty for price_post_id ' . $price_post_id . ' (but price_discount will still be output if exists)');
                }
            } catch (Exception $e) {
                error_log('YFGP v4.18.21: Error extracting discount_name for price_post_id ' . $price_post_id . ': ' . $e->getMessage() . ' (but price_discount will still be output if exists)');
            }
        }
        // v4.18.18: Extract free_appointment_condition from prices_free_appointment mapping
        if (!empty($mapping['prices_free_appointment'])) {
            $free_appointment_value = $this->extract_field_value_unified($price_post_id, $mapping['prices_free_appointment'], $mapper_unified, null);
            if (!empty($free_appointment_value)) {
                $raw_free_appointment = is_array($free_appointment_value) ? reset($free_appointment_value) : $free_appointment_value;
                $details['free_appointment_condition'] = $this->normalize_free_appointment_text(
                    $raw_free_appointment,
                    $details['discount_name'] ?? null
                );
            }
        }
        return $details;
    }
    
    /**
     * Normalize various media field outputs to a single URL string.
     */
    private function normalize_media_value($value): string {
        if (empty($value)) {
            return '';
        }
        if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        if (is_array($value)) {
            $first = reset($value);
            if (is_numeric($first)) {
                $url = wp_get_attachment_url((int) $first);
                if ($url) {
                    return $url;
                }
            }
            $flattened = array_filter(array_map('trim', $value));
            if (!empty($flattened)) {
                return (string) reset($flattened);
            }
        }
        if (is_object($value) && isset($value->ID)) {
            $url = wp_get_attachment_url((int) $value->ID);
            if ($url) {
                return $url;
            }
        }
        if (is_string($value) && strpos($value, ',') !== false) {
            $ids = array_map('trim', explode(',', $value));
            $first_id = array_shift($ids);
            if ($first_id !== null && is_numeric($first_id)) {
                $url = wp_get_attachment_url((int) $first_id);
                if ($url) {
                    return $url;
                }
            }
        }
        if (is_numeric($value)) {
            $url = wp_get_attachment_url((int) $value);
            if ($url) {
                return $url;
            }
        }
        return is_string($value) ? $value : '';
    }
    
    /**
     * Normalize price value to integer or null.
     */
    private function normalize_price_value($value): ?int {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        if (is_string($value)) {
            $clean = preg_replace('/[^\d]/', '', $value);
            if ($clean === '') {
                return null;
            }
            return (int) $clean;
        }
        return null;
    }
    
    /**
     * Wrap value into CDATA, escaping nested closing tags.
     */
    private function wrap_cdata(string $text): string {
        $safe = str_replace(']]>', ']]]]><![CDATA[>', $text);
        return '<![CDATA[' . $safe . ']]>';
    }
    
    /**
     * Extract field value using Unified mapper
     * 
     * @since 4.18.7
     * @param int $post_id Post ID
     * @param array<string, mixed> $field_config Field config from V3 mapping
     * @param YFGP_Field_Mapper_Unified $mapper_unified Unified mapper instance
     * @param mixed $fallback_value Fallback value if field is empty
     * @return mixed Extracted value
     */
    private function extract_field_value_unified(int $post_id, array $field_config, $mapper_unified, $fallback_value = '') {
        try {
            $value = $mapper_unified->getFieldValue($post_id, $field_config, true); // skip_cache for feed generation
            if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                return $fallback_value;
            }
            return $value;
        } catch (\Exception $e) {
            error_log('YFGP v4.18.7: Error extracting field value: ' . $e->getMessage());
            return $fallback_value;
        }
    }
    
    /**
     * Extract related posts using Unified mapper
     * 
     * @since 4.18.7
     * @param int $post_id Post ID
     * @param array<string, mixed> $field_config Relationship field config
     * @param YFGP_Field_Mapper_Unified $mapper_unified Unified mapper instance
     * @return array<int> Array of related post IDs
     */
    private function extract_related_posts_unified(int $post_id, array $field_config, $mapper_unified): array {
        try {
            $value = $mapper_unified->getFieldValue($post_id, $field_config, true); // skip_cache for feed generation
            
            if (empty($value)) {
                return array();
            }
            
            // Convert to array of IDs
            if (is_numeric($value)) {
                return array((int) $value);
            }
            
            if (is_array($value)) {
                $ids = array();
                foreach ($value as $item) {
                    if (is_numeric($item)) {
                        $ids[] = (int) $item;
                    } elseif (is_object($item) && isset($item->ID)) {
                        $ids[] = (int) $item->ID;
                    } elseif (is_array($item) && isset($item['ID'])) {
                        $ids[] = (int) $item['ID'];
                    }
                }
                $unique_ids = array_unique($ids);
                
                // v4.18.22: DEBUG - логирование порядка услуг
                if (!empty($unique_ids)) {
                    error_log('YFGP v4.18.22 DEBUG: Services order for post ' . $post_id . ': ' . implode(', ', $unique_ids));
                }
                
                return $unique_ids;
            }
            
            return array();
        } catch (\Exception $e) {
            error_log('YFGP v4.18.7: Error extracting related posts: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Extract V3 fields using direct DB access (WITHOUT ACF API to avoid conflicts)
     * 
     * @since 4.1.0-beta22 - Sequential-thinking solution: Direct DB access bypasses ACF compatibility issues
     * @param int $post_id Post ID
     * @param array<string, mixed> $mapping V3 mapping (config arrays)
     * @return array<string, mixed> Extracted data
     */
    /**
     * Extract V3 fields using Unified mapper
     * 
     * v4.18.20: Изменена видимость на protected для использования как callback в XmlSerializationService
     * 
     * @param int $post_id Post ID
     * @param array<string, mixed> $mapping V3 mapping config
     * @return array<string, mixed> Extracted fields
     */
    protected function extract_v3_fields(int $post_id, array $mapping): array {
        $data = array();
        // v4.18.2: Fields degree, rank, category are processed through YFGP_Field_Mapper_V2::normalize_specialities() 
        // (same method used for specialization - REFACTOR!)
        $v3_fields_with_labels = array('degree', 'rank', 'category'); // Fields that require conversion of slugs to labels
        $v3_fields_sql = array('career_start_date', 'picture', 'reviews_total_count', 'experience_years',
                          'working_hours', 'description'); // Fields processed via SQL (for performance)
        
        // Step 1: Normalize degree/rank/category via unified mapper service (same pipeline as specialization)
        if (!class_exists('YFGP_Field_Mapper_Unified')) {
            require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
        }
        $mapper_unified = YFGP_Field_Mapper_Unified::get_instance();
        $post = get_post($post_id);
        
        if (!$post) {
            return $data;
        }
        
        // Используем публичный метод нормализации из маппера V2 (без reflection)
        
        foreach ($v3_fields_with_labels as $field_key) {
            if (!empty($mapping[$field_key])) {
                $field_config = $mapping[$field_key];
                $source_field = $field_config['source_field'] ?? '';
                $source_type = $field_config['source_type'] ?? '';
                
                // v4.18.2: Debug log
                error_log('YFGP v4.18.2: Processing ' . $field_key . ' - source_field: ' . $source_field . ', source_type: ' . $source_type);
                
                if ($source_field === '' || $source_type !== 'meta_field') {
                    error_log('YFGP v4.18.2: Skipping ' . $field_key . ' - source_field empty or source_type not meta_field');
                    continue;
                }
                
                // v4.18.8: Use Unified mapper to get value (instead of get_post_meta)
                // getFieldValue() automatically does deserialization
                $raw_value = $mapper_unified->getFieldValue($post_id, $field_config, true); // skip_cache for accuracy
                
                if ($raw_value === null || $raw_value === '' || (is_array($raw_value) && empty($raw_value))) {
                    continue;
                }
                
                // Create temporary mapping for normalize_specialities() (as for specialities)
                // v4.18.21: Important to pass source_field_key for ACF fields (via acf_get_reference)
                $source_field_key = $field_config['source_field_key'] ?? '';
                if (empty($source_field_key) && function_exists('acf_get_reference')) {
                    // Try to get ACF field reference (as in normalize_specialities())
                    $field_reference = acf_get_reference($source_field, $post_id);
                    if (!$field_reference) {
                        $field_reference = get_post_meta($post_id, '_' . $source_field, true);
                    }
                    if ($field_reference) {
                        $source_field_key = $field_reference;
                    }
                }
                
                $temp_mapping = array(
                    'specialities' => array(
                        'source_field' => $source_field,
                        'source_field_key' => $source_field_key,
                        'source_type' => $source_type,
                        'source_cpt' => $field_config['source_cpt'] ?? get_post_type($post_id),
                    )
                );
                
                // Call normalize_specialities() - same method used for specialization!
                // Используем unified mapper для прогонки значения через тот же пайплайн, что и specialization (без Reflection)
                $normalized = $mapper_unified->normalizeSpecialityField($post, $temp_mapping['specialities'], $raw_value);

                // Берём labels из результата (как для specialization)
                if (!empty($normalized['labels']) && is_array($normalized['labels'])) {
                    // Склеиваем labels через запятую для вывода
                    $data[$field_key] = implode(', ', $normalized['labels']);
                    // v4.18.4: Debug log
                    error_log('YFGP v4.18.4: ' . $field_key . ' = ' . $data[$field_key]);
                } else {
                    // v4.18.4: Debug log - labels отсутствуют
                    error_log('YFGP v4.18.4: ' . $field_key . ' - normalized labels is empty. Normalized: ' . print_r($normalized, true));
        // Step 2: Process remaining fields through Unified mapper (instead of SQL)
        foreach ($v3_fields_sql as $field_key) {
            if (empty($mapping[$field_key])) {
                continue;
            }
            
            
            $field_config = $mapping[$field_key];
            
            // v4.18.8: Use Unified mapper to get value (instead of SQL query)
            $value = $mapper_unified->getFieldValue($post_id, $field_config, true); // skip_cache для точности
            
            if ($value === null || $value === '') {
                continue;
            }
            
            // Process calculate_type (date_to_years, date_as_is)
            $calculate_type = $field_config['calculate_type'] ?? '';
            if ($calculate_type === 'date_to_years' && strtotime($value) !== false) {
                // Convert date to years of experience
                $start_date = strtotime($value);
                $years = floor((time() - $start_date) / (365.25 * 24 * 60 * 60));
                if ($years >= 0) {
                    $data[$field_key] = $years;
                }
            } elseif ($calculate_type === 'date_as_is' && strtotime($value) !== false) {
                // Take date as is
                $data[$field_key] = $value;
            } else {
                // Normal value
                $data[$field_key] = $value;
            }
        }
                }
            }
        }
        
        return $data;
    }

    /**
     * Create clinic entity
     * 
     * @since v4.1.2 - Bugfixes: picture priority, HTML cleanup, data validation, working_hours support
     */
    /**
     * Extract repeater block via unified mapper
     * 
     * v4.18.20: Изменена видимость на protected для использования как callback в XmlSerializationService
     * 
     * @param int $post_id Post ID
     * @param array<string, mixed> $mapping V3 mapping config
     * @param string $block_key Repeater field key
     * @param array<string, string> $subfield_map Subfield mapping
     * @param array<string> $fallback_config_keys Fallback config keys
     * @return array<int, array<string, mixed>> Repeater rows
     */
    protected function extract_repeater_block(int $post_id, array $mapping, string $block_key, array $subfield_map, array $fallback_config_keys = array()): array {
        $config_candidates = array_merge(array($block_key), $fallback_config_keys);
        $block_config = null;
        $found_fallback_config = null;
        $is_fallback = false;

        foreach ($config_candidates as $candidate) {
            if (!empty($mapping[$candidate])) {
                // If this is the main block_key, use it as is
                if ($candidate === $block_key) {
                    $block_config = $mapping[$candidate];
                    $is_fallback = false;
                    break;
                }
                // If this is fallback, save for further processing
                if ($block_config === null) {
                    $block_config = $mapping[$candidate];
                    $found_fallback_config = $mapping[$candidate];
                    $is_fallback = true;
                }
            }
        }

        // v4.18.3: If block_config not found by main key, but fallback exists with relationship_1,
        // create temporary block_config without nested_field to get full objects
        if (empty($block_config) || empty($block_config['source_type'])) {
            return array();
        }

        // If block_config found from fallback and it's relationship_1, but has nested_field,
        // create copy without nested_field to get full objects (not just IDs)
        if ($is_fallback && 
            strpos((string) $block_config['source_type'], 'relationship') === 0 &&
            !empty($block_config['nested_field'])) {
            // Create temporary block_config without nested_field for relationship blocks
            $block_config = array_merge(array(), $block_config);
            $block_config['nested_field'] = null;
        }

        if (!class_exists('YFGP_Field_Mapper_Unified')) {
            require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
        }

        $mapper = YFGP_Field_Mapper_Unified::get_instance();
        $raw_rows = $mapper->getFieldValue($post_id, $block_config, true); // skip_cache for generation accuracy

        if (empty($raw_rows)) {
            return array();
        }

        $result = array();
        $source_type = $block_config['source_type'];

        // v4.18.3: If raw_rows - array of numbers (ID) for relationship, convert to WP_Post objects
        // v4.18.3: Преобразование ID в объекты только для relationship (не для repeater_acf/repeater_jetengine)
        if (is_array($raw_rows) && 
            strpos((string) $source_type, 'relationship') === 0 && 
            !in_array($source_type, array('repeater_acf', 'repeater_jetengine'), true)) {
            $all_numeric = true;
            foreach ($raw_rows as $item) {
                if (!is_numeric($item)) {
                    $all_numeric = false;
                    break;
                }
            }
            if ($all_numeric && !empty($raw_rows)) {
                // Convert IDs to WP_Post objects
                $target_cpt = isset($block_config['source_cpt']) ? $block_config['source_cpt'] : 'any';
                $posts = get_posts(array(
                    'post__in' => array_map('intval', $raw_rows),
                    'post_type' => $target_cpt,
                    'posts_per_page' => -1,
                    'orderby' => 'post__in', // Preserve order from raw_rows
                ));
                if (!empty($posts)) {
                    $raw_rows = $posts;
                }
            }
        }

        if (is_array($raw_rows) && in_array($source_type, array('repeater_acf', 'repeater_jetengine'), true)) {
            foreach ($raw_rows as $row_data) {
                if (!is_array($row_data)) {
                    continue;
                }
                $normalized = $this->build_repeater_row_from_data($row_data, $mapping, $subfield_map, $post_id);
                if (!empty($normalized)) {
                    $result[] = $normalized;
                }
            }
            return $result;
        }

        if (is_array($raw_rows) && strpos((string) $source_type, 'relationship') === 0) {
            foreach ($raw_rows as $related_item) {
                $related_id = 0;
                if ($related_item instanceof \WP_Post) {
                    $related_id = (int) $related_item->ID;
                } elseif (is_object($related_item) && isset($related_item->ID)) {
                    $related_id = (int) $related_item->ID;
                } elseif (is_numeric($related_item)) {
                    $related_id = (int) $related_item;
                }

                if ($related_id <= 0) {
                    continue;
                }

                $row = array();
                foreach ($subfield_map as $field_key => $target_key) {
                    if (empty($mapping[$field_key])) {
                        continue;
                    }

                    $field_config = $mapping[$field_key];
                    $value = $mapper->getFieldValue($related_id, $field_config, true); // skip_cache for generation accuracy
                    $normalized = $this->normalize_repeater_value($value, $field_config, $related_id);
                    $normalized = $this->apply_calculate_type($normalized, $field_config);

                    if ($normalized !== null && $normalized !== '') {
                        $row[$target_key] = $normalized;
                    }
                }

                if (!empty($row)) {
                    $result[] = $row;
                }
            }

            return $result;
        }

        if (is_array($raw_rows)) {
            foreach ($raw_rows as $row_data) {
                if (!is_array($row_data)) {
                    $row_data = array('value' => $row_data);
                }
                $normalized = $this->build_repeater_row_from_data($row_data, $mapping, $subfield_map, $post_id);
                if (!empty($normalized)) {
                    $result[] = $normalized;
                }
            }
        }

        return $result;
    }

    /**
     * Determines which mapping block should be used for reviews extraction based on plugin settings.
     *
     * @param array $settings Plugin settings array.
     * @param array $mapping Current field mapping configuration.
     * @return string|null Returns the block key when configuration is valid, otherwise null to trigger legacy fallback.
     */
    /**
     * Get reviews source config from settings and mapping
     * 
     * v4.18.20: Изменена видимость на protected для использования как callback в XmlSerializationService
     * 
     * @param array<string, mixed> $settings Plugin settings
     * @param array<string, mixed> $mapping V3 mapping config (passed by reference)
     * @return string|null Reviews source config key or null
     */
    protected function get_reviews_source_config(array $settings, array &$mapping): ?string {
        static $warningIssued = false;

        $logFallback = function (string $message) use (&$warningIssued): void {
            if ($warningIssued) {
                return;
            }

            error_log('[YFGP] ' . $message . ' Falling back to legacy reviews_repeater_field.');
            $warningIssued = true;
        };

        $source_preference = $settings['source_reviews'] ?? 'repeater';
        $target_cpt = trim((string) ($settings['source_reviews_cpt'] ?? ''));

        // v4.18.3: Если mapping reviews отсутствует, но есть reviews_total_count с relationship_1,
        // создаем mapping['reviews'] на основе reviews_total_count, но БЕЗ nested_field
        $reviews_config = $mapping['reviews'] ?? null;
        if (empty($reviews_config) || empty($reviews_config['source_type'])) {
            if ($source_preference === 'relationship' && $target_cpt !== '') {
                // v4.18.3: Сначала проверяем reviews_total_count - это самый надежный источник
                $reviews_total_count_config = $mapping['reviews_total_count'] ?? null;
                if (!empty($reviews_total_count_config) && 
                    is_array($reviews_total_count_config) &&
                    isset($reviews_total_count_config['source_type']) &&
                    strpos($reviews_total_count_config['source_type'], 'relationship') === 0 &&
                    isset($reviews_total_count_config['source_cpt']) &&
                    $reviews_total_count_config['source_cpt'] === $target_cpt &&
                    !empty($reviews_total_count_config['source_field'])) {
                    // Создаем reviews_config на основе reviews_total_count, но БЕЗ nested_field
                    $reviews_config = array_merge(array(), $reviews_total_count_config);
                    unset($reviews_config['nested_field']); // Критично: убираем nested_field для получения полных объектов
                    $mapping['reviews'] = $reviews_config; // Добавляем в mapping
                    error_log('[YFGP v4.18.3] Auto-created reviews mapping from reviews_total_count: source_field=' . $reviews_config['source_field']);
                } else {
                    // v4.18.1: Автоматически создаем mapping для relationship источника
                    // Ищем поле relationship для CPT reviews в mapping
                    $reviews_config = array(
                        'source_type' => 'relationship_1',
                        'source_field' => '',
                        'source_cpt' => $target_cpt
                    );
                    
                    // Пробуем найти relationship поле, связанное с reviews
                    foreach ($mapping as $field_key => $field_config) {
                        if (is_array($field_config) && 
                            isset($field_config['source_type']) && 
                            strpos($field_config['source_type'], 'relationship') === 0 &&
                            isset($field_config['source_cpt']) && 
                            $field_config['source_cpt'] === $target_cpt) {
                            $reviews_config['source_field'] = $field_config['source_field'] ?? '';
                            $reviews_config['source_type'] = $field_config['source_type'];
                            // Убираем nested_field если он есть
                            if (isset($reviews_config['nested_field'])) {
                                unset($reviews_config['nested_field']);
                            }
                            break;
                        }
                    }
                    
                    // Если не нашли, пробуем стандартные имена полей для JetEngine relationships
                    if (empty($reviews_config['source_field'])) {
                        $possible_fields = array('jet_rel_reviews', 'reviews_relationship', 'reviews');
                        foreach ($possible_fields as $field_name) {
                            foreach ($mapping as $field_key => $field_config) {
                                if (is_array($field_config) && 
                                    isset($field_config['source_field']) && 
                                    $field_config['source_field'] === $field_name &&
                                    isset($field_config['source_type']) && 
                                    strpos($field_config['source_type'], 'relationship') === 0) {
                                    $reviews_config['source_field'] = $field_name;
                                    $reviews_config['source_type'] = $field_config['source_type'];
                                    if (isset($reviews_config['nested_field'])) {
                                        unset($reviews_config['nested_field']);
                                    }
                                    break 2;
                                }
                            }
                        }
                    }
                    
                    // Если нашли source_field, добавляем в mapping
                    if (!empty($reviews_config['source_field'])) {
                        $mapping['reviews'] = $reviews_config;
                        error_log('[YFGP v4.18.3] Auto-created reviews mapping: source_field=' . $reviews_config['source_field']);
                    } else {
                        $logFallback('Reviews mapping is missing and could not be auto-created from settings.');
                        return null;
                    }
                }
            } else {
                $logFallback('Reviews mapping is missing or incomplete.');
                return null;
            }
        }

        $current_source_type = $reviews_config['source_type'] ?? '';

        if ($source_preference === 'relationship') {
            if ($target_cpt === '') {
                $logFallback('Relationship reviews source selected but CPT is not configured.');
                return null;
            }

            if (strpos((string) $current_source_type, 'relationship') !== 0) {
                $logFallback('Reviews mapping does not use a relationship source.');
                return null;
            }
        } else {
            $supported_repeater_sources = array('repeater_acf', 'repeater_jetengine');
            if (!in_array($current_source_type, $supported_repeater_sources, true)) {
                $logFallback('Reviews mapping does not use a repeater source.');
                return null;
            }
        }

        return 'reviews';
    }

    /**
     * Get list of repeater field types for doctors
     * 
     * @fix: Remove hardcode of types - use configuration
     * @return array List of repeater field types (education, job, certificate)
     * @since v4.18.3
     */
    private function get_repeater_field_types(): array {
        return array('education', 'job', 'certificate');
    }

    /**
     * Get repeater field key from mapping by type
     * 
     * @fix: Remove hardcode of keys - use name pattern (type . '_repeater_field')
     * @param array $mapping Field mapping yfgp_field_mapping_v3
     * @param string $type Repeater field type (education, job, certificate)
     * @return string|null Repeater field key or null if not found
     * @since v4.18.3
     */
    /**
     * Get repeater field key from mapping
     * 
     * v4.18.20: Изменена видимость на protected для использования как callback в XmlSerializationService
     * 
     * @param array<string, mixed> $mapping V3 mapping config
     * @param string $type Repeater type (education, job, certificate, reviews)
     * @return string|null Field key or null if not found
     */
    protected function get_repeater_field_key(array $mapping, string $type): ?string {
        // Form key by plugin pattern: $type . '_repeater_field'
        $key = $type . '_repeater_field';
        
        // Check key presence in mapping
        if (empty($mapping[$key]) || !is_array($mapping[$key])) {
            return null;
        }
        
        $config = $mapping[$key];
        
        // Check that this is repeater field
        if (empty($config['source_type'])) {
            return null;
        }
        
        $source_type = $config['source_type'];
        if (!in_array($source_type, array('repeater_acf', 'repeater_jetengine'), true)) {
            return null;
        }
        
        // All OK => return key
        return $key;
    }

    /**
     * Determine entity_type (doctor/clinic/service) by post_type
     * @fix: Universal mapping CPT => entity_type, without hardcode
     * 
     * v4.18.11: Использует YFGP_Entity_Manager для расширяемости через фильтры WordPress
     * 
     * @param string $post_type Post type slug
     * @return string Entity type: 'doctor', 'clinic', or 'service'
     */
    /**
     * @internal Используется через callback в YFGP_Entity_Collector
     */
    public function get_entity_type_for_post(string $post_type): string {
        // v4.18.11: Используем Entity Manager для расширяемости
        return $this->entity_manager->get_entity_type($post_type, $this->settings);
    }

    private function build_repeater_row_from_data(array $row_data, array $mapping, array $subfield_map, int $context_post_id): array {
        $row = array();

        foreach ($subfield_map as $field_key => $target_key) {
            if (empty($mapping[$field_key])) {
                continue;
            }

            $field_config = $mapping[$field_key];
            $source_field = $field_config['source_field'] ?? '';
            $raw_value = null;

            // v4.18.1: Fix - support ACF field keys (field_xxxx) and field names
            if ($source_field !== '') {
                // First check by source_field (field name)
                if (array_key_exists($source_field, $row_data)) {
                    $raw_value = $row_data[$source_field];
                } elseif (function_exists('acf_get_field')) {
                    // If source_field is ACF field key, search in row_data by field key
                    if (strpos($source_field, 'field_') === 0 && array_key_exists($source_field, $row_data)) {
                        $raw_value = $row_data[$source_field];
                    } else {
                        // Try to find by field name via ACF API
                        $acf_field = acf_get_field($source_field);
                        if ($acf_field && !empty($acf_field['name'])) {
                            // Search by field name
                            if (array_key_exists($acf_field['name'], $row_data)) {
                                $raw_value = $row_data[$acf_field['name']];
                            }
                            // Search by field key, if it exists in row_data
                            if ($raw_value === null && !empty($acf_field['key']) && array_key_exists($acf_field['key'], $row_data)) {
                                $raw_value = $row_data[$acf_field['key']];
                            }
                        }
                    }
                }
            }
            
            // Fallback: check by field_key and meta_field
            if ($raw_value === null) {
                if (array_key_exists($field_key, $row_data)) {
                    $raw_value = $row_data[$field_key];
                } elseif (!empty($field_config['meta_field']) && array_key_exists($field_config['meta_field'], $row_data)) {
                    $raw_value = $row_data[$field_config['meta_field']];
                }
            }

            if ($raw_value === null) {
                continue;
            }

            $normalized = $this->normalize_repeater_value($raw_value, $field_config, $context_post_id);
            $normalized = $this->apply_calculate_type($normalized, $field_config);

            if ($normalized !== null && $normalized !== '') {
                $row[$target_key] = $normalized;
            }
        }

        return $row;
    }

    private function normalize_repeater_value($value, array $field_config, int $context_post_id) {
        if ($value === null) {
            return null;
        }

        $maybe_unserialized = maybe_unserialize($value);
        if ($maybe_unserialized !== $value) {
            return $this->normalize_repeater_value($maybe_unserialized, $field_config, $context_post_id);
        }

        if (is_array($value)) {
            $flattened = $this->flatten_v3_array_value($value, $field_config, $context_post_id);
            $flattened = array_filter(array_map(function ($item) {
                if (is_string($item)) {
                    return trim($item);
                }
                return is_scalar($item) ? trim((string) $item) : '';
            }, $flattened), function ($item) {
                return $item !== '';
            });

            return !empty($flattened) ? implode(', ', $flattened) : null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if ($value instanceof \WP_Post) {
            return (string) $value->ID;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            $string_value = trim((string) $value);
            return $string_value === '' ? null : $string_value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed === '' ? null : $trimmed;
        }

        return null;
    }

    private function apply_calculate_type($value, array $field_config) {
        if ($value === null || $value === '') {
            return $value;
        }

        $calculate_type = $field_config['calculate_type'] ?? '';

        if ($calculate_type === 'date_to_years' && strtotime((string) $value) !== false) {
            $start_date = strtotime((string) $value);
            if ($start_date !== false) {
                $years = floor((time() - $start_date) / (365.25 * 24 * 60 * 60));
                if ($years >= 0) {
                    return $years;
                }
            }
        }

        if ($calculate_type === 'date_as_is' && strtotime((string) $value) !== false) {
            return (string) $value;
        }

        return $value;
    }

    private function normalize_boolean_string($value, bool $default = false): string {
        if ($value === null) {
            return $default ? 'true' : 'false';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return ((int) $value) !== 0 ? 'true' : 'false';
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === '') {
                return $default ? 'true' : 'false';
            }
            if (in_array($normalized, array('1', 'true', 'yes', 'on'), true)) {
                return 'true';
            }
            if (in_array($normalized, array('0', 'false', 'off', 'no'), true)) {
                return 'false';
            }
        }

        return !empty($value) ? 'true' : ($default ? 'true' : 'false');
    }

    /**
     * @internal Используется через callback в YFGP_Entity_Collector
     */
    public function build_clinic_entity(array $clinic_data): array {
        // v4.2.0: FIXED - Use clinic permalink and clean internal_id
        $clinic_id = $clinic_data['id'] ?? 'default';
        $clinic_post_id = $clinic_data['post_id'] ?? null;
        
        // @fix: Remove hardcode 'clinic_' — use entity_type
        $clinic_post_type = $clinic_post_id 
            ? get_post_type($clinic_post_id) 
            : ($clinic_data['post_type'] ?? '');
        
        $internal_id = !empty($clinic_data['internal_id']) 
            ? $clinic_data['internal_id'] 
            : (
                !empty($clinic_post_type)
                    ? preg_replace('/^' . preg_quote($this->get_entity_type_for_post($clinic_post_type) . '_', '/') . '/', '', $clinic_id)
                    : $clinic_id // If post_type not exists - keep clinic_id as is
            );
        
        // Clinic URL = из маппинга (если есть), иначе post permalink, иначе настройки
        // v4.18.22: FIX - используем url из $clinic_data (режим "одна клиника")
        $clinic_url = !empty($clinic_data['url']) 
            ? $clinic_data['url'] 
            : ($clinic_post_id 
                ? get_permalink($clinic_post_id) 
                : ($this->settings['company_url'] ?? get_site_url()));
        
        // Base fields
        $clinic = array(
            'id' => $clinic_id,
            'name' => $clinic_data['name'] ?? 'Base clinic',
            'url' => $clinic_url,
            'city' => $clinic_data['city'] ?? $this->settings['city'] ?? '',
            'internal_id' => $internal_id,
        );
        
        // v4.1.6: Address
        if (!empty($clinic_data['address'])) {
            $clinic['address'] = is_array($clinic_data['address']) 
                ? implode(', ', $clinic_data['address']) 
                : $clinic_data['address'];
        }
        
        // v4.1.6: Phone (validation for array/string)
        if (!empty($clinic_data['phone'])) {
            $clinic['phone'] = is_array($clinic_data['phone']) 
                ? implode(', ', $clinic_data['phone']) 
                : $clinic_data['phone'];
        }
        
        // v4.1.6: Email
        if (!empty($clinic_data['email'])) {
            $clinic['email'] = $clinic_data['email'];
        }
        
        // @fix: featured image ALWAYS has priority over mapping
        if (!empty($clinic_post_id) && has_post_thumbnail($clinic_post_id)) {
            $clinic['picture'] = get_the_post_thumbnail_url($clinic_post_id, 'full');
        } else {
            // Only if no minimum - take from mapping
            if (!empty($clinic_data['picture'])) {
                $clinic['picture'] = $clinic_data['picture'];
            }
        }
        if (empty($clinic['picture'])) {
            if (!empty($this->settings['shop_picture'])) {
                $clinic['picture'] = $this->settings['shop_picture'];
            } elseif (function_exists('get_site_icon_url')) {
                $site_icon = get_site_icon_url();
                if (!empty($site_icon)) {
                    $clinic['picture'] = $site_icon;
                }
            }
        }
        
        // v4.1.6: Company ID
        if (!empty($clinic_data['company_id'])) {
            $clinic['company_id'] = $clinic_data['company_id'];
        }
        
        return $clinic;
    }

    /**
     * Create service entity
     * v4.3.0: Updated to match clinics v4.2.2 pattern
     */
    /**
     * @internal Используется через callback в YFGP_Entity_Collector
     */
    public function build_service_entity(array $service_data): array {
        // v4.3.1: Use service post_id for internal_id (NO URL per Yandex spec!)
        $service_post_id = $service_data['post_id'] ?? null;
        
        // v4.3.1: Internal ID - clean without 'service_' prefix
        $internal_id_clean = !empty($service_data['internal_id']) 
            ? str_replace('service_', '', $service_data['internal_id']) 
            : ($service_post_id ?? 'default');
        
        $service = array(
            'id' => $service_data['id'] ?? 'service_default',
            'name' => $service_data['name'],
            'internal_id' => $internal_id_clean, // v4.3.1: Clean ID
            'description' => '', // Will be set below
        );
        
        // v4.20.0: Description sanitization (remove script/style + HTML)
        if (!empty($service_data['description'])) {
            $service['description'] = $this->sanitize_feed_text($service_data['description']);
        }
        
        // v4.3.0: Gov ID (new field for services)
        if (!empty($service_data['gov_id'])) {
            $service['gov_id'] = $service_data['gov_id'];
        }
        
        // v4.3.0: Picture with URL validation (like clinics)
        if (!empty($service_data['picture'])) {
            $service['picture'] = $service_data['picture'];
        }
        
        // v4.5.0: Price fields (from CPT "prices" via relationship)
        if (!empty($service_data['price'])) {
            $service['price'] = $service_data['price'];
        }
        
        if (!empty($service_data['currency'])) {
            $service['currency'] = $service_data['currency'];
        }
        
        // v4.18.18: Extract discount data from prices CPT if not already extracted or empty
        // This ensures discount_name and free_appointment_condition are available in offers
        // v4.18.19: FIX - Always try to extract latest price data regardless of existing field values
        if (!empty($service_post_id)) {
            $mapping = get_option('yfgp_field_mapping_v3', array());
            if (!empty($mapping) && is_array($mapping)) {
                // v4.18.18: Use get_instance() - YFGP_Field_Mapper_Unified is Singleton
                if (!class_exists('YFGP_Field_Mapper_Unified')) {
                    require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
                }
                $mapper_unified = YFGP_Field_Mapper_Unified::get_instance();
                if (!empty($mapping['prices_price_source_field'])) {
                    $price_ids = $this->extract_related_posts_unified($service_post_id, $mapping['prices_price_source_field'], $mapper_unified);
                    if (!empty($price_ids)) {
                        $price_details = $this->extract_price_details_for_service($price_ids, $mapping, $mapper_unified);
                        if (!empty($price_details)) {
                            // Merge price details (price, price_discount, discount_name, free_appointment_condition)
                            // v4.18.19: Merge with priority - only overwrite if new value is not empty
                            foreach ($price_details as $key => $value) {
                                if (!empty($value)) {
                                    $service[$key] = $value;
                                }
                            }

                        }
                    }
                }
            }
        }
        
        // v4.18.18: Also copy discount fields from service_data if they exist (from extract_service_details)
        if (!empty($service_data['price_discount'])) {
            $service['price_discount'] = $service_data['price_discount'];
        }
        if (!empty($service_data['discount_name'])) {
            $service['discount_name'] = $service_data['discount_name'];
        }
        if (!empty($service_data['free_appointment_condition'])) {
            $service['free_appointment_condition'] = $this->normalize_free_appointment_text(
                $service_data['free_appointment_condition'],
                $service_data['discount_name'] ?? ($service['discount_name'] ?? null)
            );
        }

        if (!empty($service['free_appointment_condition'])) {
            $service['free_appointment_condition'] = $this->normalize_free_appointment_text(
                $service['free_appointment_condition'],
                $service['discount_name'] ?? null
            );
        }
        
        return $service;
    }

    /**
     * Build offers v2.3.0 (with base service for each doctor) [v3.5.3 - REFACTORED!]
     * 
     * According to Yandex.Health documentation:
     * - Base service is required for EACH doctor
     * - Offer for each combination doctor-clinic-speciality
     * - If doctor accepts by two specialities → two offers
     * - Price is NOT required for base service
     * 
     * @since v3.5.3 - Added parameter &$global_services for auto-creation of services with ID
     *
     * @param \WP_Post $post Doctor post
     * @param array<string, mixed> $data Post data
     * @param array<string, array<string, mixed>> $clinics Clinics array
     * @param array<int, array<string, mixed>> $services Services array
     * @param array<int, array<string, mixed>> $offers Offers array (passed by reference)
     * @param string $doctor_id ID of doctor (with prefix doctor_)
     * @param array<string, array<string, mixed>> $global_services Global services array (for auto-creation)
     */
    /**
     * @deprecated v4.18.0 Use YFGP_Offer_Builder::build_offers() instead of this method
     */
    private function build_offers_v2($post, array $data, array $clinics, array $services, array &$offers, string $doctor_id, array &$global_services): void {
        // v4.18.1: DEBUG - log input parameters
        error_log('YFGP v4.18.1 DEBUG build_offers_v2: Doctor ID: ' . $doctor_id . ', Post ID: ' . $post->ID . ', clinics count: ' . count($clinics) . ', services count: ' . count($services) . ', global_services count: ' . count($global_services));
        // v4.18.21: DEBUG - проверяем что в $data при входе в build_offers_v2
        error_log("YFGP v4.18.21 DEBUG build_offers_v2: ENTRY - data[adult_appointment] = " . var_export($data['adult_appointment'] ?? 'NOT SET', true) . ", data[children_appointment] = " . var_export($data['children_appointment'] ?? 'NOT SET', true) . " for doctor_id: $doctor_id");
        
        $generate_all_services = $this->settings['generate_all_services'] ?? false;
        $mapping = get_option('yfgp_field_mapping_v3', array());
        
        // Get doctor specializations (slugs for grouping)
        // v4.18.2: UNIVERSAL - no hardcode fallback specialization
        $fallback_spec_slug = $this->settings['default_specialization_slug'] ?? '';
        $specialization_slugs = $data['set_ids'] ?? ($fallback_spec_slug ? array($fallback_spec_slug) : array());
        $fallback_spec_text = $this->field_mapper->get_fallback_speciality_text(); // v4.17.0: FIX #4 - from settings
        $specialization_texts = $data['specializations_text'] ?? array($fallback_spec_text);
        
        // Convert to array if string
        if (!is_array($specialization_slugs)) {
            $specialization_slugs = array($specialization_slugs);
        }
        if (!is_array($specialization_texts)) {
            $specialization_texts = array($specialization_texts);
        }
        
        // v4.18.1: FIX - normalize $specialization_texts (protection from nested arrays)
        $normalized_texts = array();
        foreach ($specialization_texts as $text) {
            if (is_array($text)) {
                // If array - extract label
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
        
        // If empty array - use defaults from settings
        if (empty($specialization_slugs)) {
            $specialization_slugs = $fallback_spec_slug ? array($fallback_spec_slug) : array();
            $specialization_texts = array($fallback_spec_text); // v4.17.0: FIX #4 - reuse from above
        }
        
        // v4.18.1: FIX - if after fallback specialization_slugs is still empty, create offers with fallback_spec_text
        if (empty($specialization_slugs)) {
            // If fallback_spec_text is empty, use default value
            if (empty($fallback_spec_text)) {
                $fallback_spec_text = 'врач'; // Default value if settings are empty
            }
            $specialization_slugs = array('default'); // Use 'default' as slug if no fallback slug
            $specialization_texts = array($fallback_spec_text);
            error_log('YFGP v4.18.1 DEBUG: Using fallback specialization_slugs: default, fallback_spec_text: ' . $fallback_spec_text);
        }
        
        // v4.18.1: DEBUG - log before loop
        error_log('YFGP v4.18.1 DEBUG: Before foreach specialization_slugs, count: ' . count($specialization_slugs) . ', slugs: ' . implode(', ', $specialization_slugs));
        
        // For EACH specialization create offers
        foreach ($specialization_slugs as $index => $specialization_slug) {
            // Get original text of specialization
            $specialization_text = $specialization_texts[$index] ?? $specialization_texts[0] ?? $fallback_spec_text; // v4.17.0: FIX #4
            
            // v4.18.1: FIX - protection from arrays (additional normalization)
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
            
            // v3.5.3: Determine base service with exceptions
            // v4.5.0 FIX: Use $current_doctor_services (with prices, ONLY for this doctor!)
            // v4.18.1: FIX - if $current_doctor_services empty, use $services (raw) for auto-creation
            $services_for_determination = !empty($current_doctor_services) ? $current_doctor_services : $services;
            
            // v4.18.1: DEBUG - log for debugging
            error_log('YFGP v4.18.1 DEBUG: Doctor ' . $post->ID . ', specialization_slug: ' . $specialization_slug . ', services_for_determination count: ' . count($services_for_determination) . ', services (raw) count: ' . count($services));
            
            $base_service = $this->determine_base_service($services_for_determination, $specialization_slug, $mapping, $data, $global_services);
            
            // v3.5.3: If specialization excluded (UCHI) and no services - skip offer creation
            if ($base_service === null) {
                error_log('YFGP v3.5.3: Skipping offer creation for doctor ' . $post->ID . ' (specialty excluded, no services)');
                continue;
            }
            
            // v4.18.1: DEBUG - log successful base service determination
            error_log('YFGP v4.18.1 DEBUG: Base service determined for doctor ' . $post->ID . ', service_id: ' . ($base_service['id'] ?? 'unknown'));
            
            // v4.18.1: FIX - if clinics empty, use default clinic
            if (empty($clinics)) {
                $clinics = array(array('id' => 'default', 'name' => '', 'address' => '', 'phone' => '', 'city' => ''));
                error_log('YFGP v4.18.1 DEBUG: Using fallback clinic (empty clinics array)');
            }
            
            // v4.18.1: DEBUG - log before clinics loop
            error_log('YFGP v4.18.1 DEBUG: Before foreach clinics, count: ' . count($clinics) . ', base_service: ' . ($base_service ? ($base_service['id'] ?? 'no id') : 'NULL'));
            
            // Create offer for each clinic
            foreach ($clinics as $clinic) {
                $clinic_id = $clinic['id'] ?? 'default';
                
                // v4.4.1: Use service ID from base_service (already clean: service_ID)
                $service_id = $base_service['id'] ?? ('service_' . $post->ID);
                
                $offer_id = 'offer_' . $post->ID . '_' . $clinic_id . '_' . $specialization_slug;
                
                // Collect offer data
                $offer_data = array(
                    'id' => $offer_id,
                    'doctor_id' => $doctor_id,
                    'clinic_id' => $clinic_id, // v4.4.0: FIXED - $clinic_id already has 'clinic_' prefix from mapper!
                    'service_id' => $service_id,
                    'speciality' => mb_strtolower($this->normalize_speciality_value($specialization_text, $fallback_spec_text)), // v4.10.8: LOWERCASE according to Yandex docs! v4.18.1: FIX - protect from arrays
                    'price' => $base_service['price'] ?? null, // Price is NOT required!
                    'base_price' => $base_service['price'] ?? null,
                    'currency' => $this->settings['default_currency'] ?? 'RUR', // v4.18.2: UNIVERSAL - from settings
                    'discount' => $base_service['price_discount'] ?? null, // v4.5.0: Discount from related prices CPT
                    'discount_name' => $base_service['discount_name'] ?? null, // v4.18.18: Discount name for XML attribute
                    'free_appointment_condition' => $base_service['free_appointment_condition'] ?? null, // v4.18.18: Free appointment condition
                    'is_base_service' => true,
                    'appointment_url' => $data['url'] ?? get_permalink($post->ID), // v4.4.0: Fallback to doctor URL (Yandex requires!)
                );
                
                // Add additional fields to offer
                $offer_data = array_merge($offer_data, $this->get_offer_additional_fields($post, $data, $mapping));
                
                // v4.4.0: Ensure offer-level boolean fields have defaults
                if (!isset($offer_data['appointment_available'])) {
                    $offer_data['appointment_available'] = 'true'; // Default: doctor accepts appointments
                }
                if (!isset($offer_data['oms_available'])) {
                    $offer_data['oms_available'] = 'false'; // Default: no OMS
                }
                if (!isset($offer_data['online_schedule'])) {
                    $offer_data['online_schedule'] = 'false'; // Default: no online scheduling
                }
                
                // v4.10.6: Inheritance of doctor fields to offer
                // These fields are copied from $data (which were added from $doctor_entity in collect_entities)
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
                                error_log("YFGP v4.18.21 DEBUG: Set $field = " . var_export($doctor_value, true) . " for offer $offer_id");
                            }
                        } else {
                            $offer_data[$field] = 'false';
                            // v4.18.21: DEBUG - логируем установку false (поле не в data)
                            error_log("YFGP v4.18.21 DEBUG: Set $field = 'false' (not in data) for offer $offer_id");
                        }
                    } else {
                        // v4.18.21: DEBUG - логируем что значение уже установлено
                        error_log("YFGP v4.18.21 DEBUG: $field already set to " . var_export($current, true) . " for offer $offer_id");
                    }
                }
                
                $offers[$offer_id] = $offer_data;
                
                // v4.18.21: DEBUG - проверяем что сохранилось в offers
                error_log("YFGP v4.18.21 DEBUG: After saving to offers[$offer_id]: adult_appointment = " . var_export($offers[$offer_id]['adult_appointment'] ?? 'NOT SET', true) . ", children_appointment = " . var_export($offers[$offer_id]['children_appointment'] ?? 'NOT SET', true));
                
                // v4.18.1: DEBUG - log offer creation
                error_log('YFGP v4.18.1 DEBUG: Offer created! Doctor ID: ' . $doctor_id . ', Offer ID: ' . $offer_id . ', Specialization: ' . $specialization_slug);
                
                // v4.5.1: Logging moved to create_auto_base_service() - logs ONLY when creating NEW service!
                // Old code here logged for EVERY offer (created duplicates: 20 logs instead of 13)
                
                // If generation of all services enabled
                // v4.5.0 FIX: Use $current_doctor_services (with prices, ONLY for this doctor!)
                if ($generate_all_services && !empty($current_doctor_services)) {
                    foreach ($current_doctor_services as $service) {
                        // Skip base service
                        if ($service['name'] === $base_service['name']) {
                            continue;
                        }
                        
                        // Check that service is not auto-created
                        if (!empty($service['auto_created'])) {
                            continue;
                        }
                        
                        // v4.4.1: Use service ID from array (already clean: service_ID)
                        $service_id = $service['id'] ?? ('service_' . $post->ID);
                        $offer_id = 'offer_' . $post->ID . '_' . $clinic_id . '_' . $specialization_slug . '_service_' . str_replace('service_', '', $service_id);
                        
                        $offer_data = array(
                            'id' => $offer_id,
                            'doctor_id' => $doctor_id,
                            'clinic_id' => $clinic_id, // v4.4.0: FIXED - already has clinic_ prefix!
                            'service_id' => $service_id,
                            'speciality' => $this->normalize_speciality_value($specialization_text, $fallback_spec_text), // v2.3.0: original text! v4.18.1: FIX - protect from arrays
                            'price' => $service['price'] ?? null,
                            'base_price' => $service['price'] ?? null,
                            'currency' => $this->settings['default_currency'] ?? 'RUR', // v4.18.2: UNIVERSAL - from settings
                            'discount' => $service['price_discount'] ?? null, // v4.5.0: Discount from related prices CPT
                            'discount_name' => $service['discount_name'] ?? null, // v4.18.18: Discount name for XML attribute
                            'free_appointment_condition' => $service['free_appointment_condition'] ?? null, // v4.18.18: Free appointment condition
                            'is_base_service' => false,
                            'appointment_url' => $data['url'] ?? get_permalink($post->ID), // v4.4.0: URL fallback
                        );
                        
                        // Add additional fields
                        $offer_data = array_merge($offer_data, $this->get_offer_additional_fields($post, $data, $mapping));
                        
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
                        
                        // v4.10.6: Inheritance of doctor fields to offer (for additional services)
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
                                    }
                                } else {
                                    $offer_data[$field] = 'false';
                                }
                            }
                        }
                        
                        $offers[$offer_id] = $offer_data;
                        
                        // v4.18.21: DEBUG - проверяем что сохранилось в offers для additional service
                        error_log("YFGP v4.18.21 DEBUG: After saving additional service to offers[$offer_id]: adult_appointment = " . var_export($offers[$offer_id]['adult_appointment'] ?? 'NOT SET', true) . ", children_appointment = " . var_export($offers[$offer_id]['children_appointment'] ?? 'NOT SET', true));
                    }
                }
            }
        }
        
        // v4.18.1: DEBUG - log at end of function
        error_log('YFGP v4.18.1 DEBUG: build_offers_v2 finished for doctor ' . $doctor_id . ', total offers created: ' . count($offers));

    }

    /**
     * Get additional fields for offer
     *
     * @param \WP_Post $post Doctor post
     * @param array<string, mixed> $data Post data
     * @param array<string, mixed> $mapping Field mapping
     * @return array<string, mixed> Additional fields for offer
     */
    /**
     * @internal Used via callback in YFGP_Offer_Builder
     */
    public function get_offer_additional_fields($post, array $data, array $mapping): array {
        // v4.18.21: DEBUG - проверяем что в $data при входе в get_offer_additional_fields
        error_log("YFGP v4.18.21 DEBUG get_offer_additional_fields: ENTRY - data[adult_appointment] = " . var_export($data['adult_appointment'] ?? 'NOT SET', true) . ", data[children_appointment] = " . var_export($data['children_appointment'] ?? 'NOT SET', true) . " for post_id: " . $post->ID);
        
        $field_mapper = new YFGP_Field_Mapper_V2();
        $additional = array();
        
        // List of offer fields
        // v4.10.6: Removed children_appointment, adult_appointment, house_call, telemed
        // These fields are inherited from $data doctor, and NOT extracted again!
        // v4.18.18: discount_name и free_appointment_condition извлекаются из prices CPT через extract_price_details_for_service(),
        // поэтому их НЕ нужно извлекать здесь из doctor post - они уже установлены в offer_data из $service
        $offer_field_ids = array(
            'appointment_url', 'online_schedule', 'appointment_available', 'oms_available',
            'discount_price' // discount_name и free_appointment_condition исключены - они извлекаются из prices CPT
        );
        
        foreach ($offer_field_ids as $field_id) {
            // Check if value exists by default
            if (!empty($mapping['default_' . $field_id])) {
                $additional[$field_id] = $mapping['default_' . $field_id];
            }
            // Otherwise try to get from field
            elseif (!empty($mapping[$field_id])) {
                $value = $field_mapper->get_dynamic_value($post->ID, $mapping[$field_id], '');
                if (!empty($value)) {
                    $additional[$field_id] = $value;
                }
            }
        }
        
        // v4.18.21: DEBUG - проверяем что возвращается из get_offer_additional_fields
        error_log("YFGP v4.18.21 DEBUG get_offer_additional_fields: RETURN - additional[adult_appointment] = " . var_export($additional['adult_appointment'] ?? 'NOT SET', true) . ", additional[children_appointment] = " . var_export($additional['children_appointment'] ?? 'NOT SET', true) . " for post_id: " . $post->ID);
        
        return $additional;
    }

    /**
     * Check: is specialty excluded from creating "primary appointment"
     * According to Yandex documentation: for UZI/rentgen etc. no primary appointment needed
     * 
     * @since v3.5.3
     * @param string $speciality Specialty slug
     * @param array<string, mixed> $doctor_data Doctor data from field_mapper
     * @param array<string, mixed> $mapping Mapper settings
     * @return bool True if specialty is excluded
     */
    private function is_specialty_excluded(string $speciality, array $doctor_data, array $mapping): bool {
        // v4.18.1: FIX - fallback specialty 'default' never excluded
        if ($speciality === 'default') {
            return false;
        }
        
        // v4.18.1: Fix - exclusion settings come from $this->settings, not from $mapping
        $source = $mapping['specialties_no_primary_source'] ?? 'taxonomy';
        
        if ($source === 'taxonomy') {
            $excluded_terms = $this->settings['exclusions_terms'] ?? array();
            $excluded_terms = array_filter(array_map('strval', (array) $excluded_terms));

            if (empty($excluded_terms)) {
                return false;
            }

            // v4.18.24: FIX - проверяем ТОЛЬКО текущую специализацию, а не все специализации врача
            // Если у врача есть несколько специализаций (например, nevrolog и terapevt),
            // и terapevt исключена, то офферы должны создаваться для nevrolog
            $speciality_slug = is_array($speciality) ? ($speciality['slug'] ?? '') : (string) $speciality;
            if ($speciality_slug !== '' && in_array($speciality_slug, $excluded_terms, true)) {
                error_log('YFGP v4.18.24: Specialty excluded (direct match): ' . $speciality_slug);
                return true;
            }
            
            // v4.18.24: Если текущая специализация не в списке исключенных - возвращаем false
            // Удалена проверка всех специализаций врача - это была ошибка!
            return false;
        } elseif ($source === 'field') {
            $field = $this->settings['exclusions_field'] ?? '';
            if ($field === '') {
                return false;
            }

            $operator = $this->settings['exclusions_operator'] ?? 'equals';
            $value = $this->settings['exclusions_value'] ?? '';
            $field_raw_value = $doctor_data[$field] ?? null;
            $normalized_field_values = $this->normalize_condition_values($field_raw_value);
            $normalized_target_values = $this->normalize_condition_values(
                is_string($value) && strpos($value, ',') !== false
                    ? array_map('trim', explode(',', $value))
                    : $value
            );

            $result = false;
            switch ($operator) {
                case 'equals':
                    $target = $normalized_target_values[0] ?? '';
                    $result = $target !== '' && $this->has_condition_match($normalized_field_values, array($target));
                    break;
                case 'not_equals':
                    $target = $normalized_target_values[0] ?? '';
                    $result = $target !== '' && !$this->has_condition_match($normalized_field_values, array($target));
                    break;
                case 'in_array':
                    $result = $this->has_condition_match($normalized_field_values, $normalized_target_values);
                    break;
                case 'not_in_array':
                    $result = !$this->has_condition_match($normalized_field_values, $normalized_target_values);
                    break;
                case 'empty':
                    $result = $this->is_condition_value_empty($field_raw_value);
                    break;
                case 'not_empty':
                    $result = !$this->is_condition_value_empty($field_raw_value);
                    break;
                default:
                    $result = false;
            }

            if ($result) {
                error_log(sprintf(
                    'YFGP v4.18.23: Specialty excluded by field condition (%s %s %s)',
                    $field,
                    $operator,
                    is_scalar($value) ? $value : json_encode($value)
                ));
            }

            return $result;
        }
        
        return false;
    }

    /**
     * Determine base service with priorities (v3.5.3 - REFACTORED!)
     * 
     * Priority 0: Exclude specialties (UZI/rentgen) - according to Yandex documentation
     * Priority 1: Explicit indication from Doctor (base_service_id) - MANDATORY!
     * Priority 2: Service by specialty from mapper
     * Priority 3: Search for service with word "primary"
     * Priority 4: First service (cheapest)
     * Priority 5: Auto-creation (with ID and adding to global $services)
     * 
     * @since v3.5.3
     * @param array<int, array<string, mixed>> $services Doctor services from ACF/JetEngine (already filtered for specific doctor!)
     * @param string $speciality Doctor specialty (slug)
     * @param array<string, mixed> $mapping Mapper settings
     * @param array<string, mixed> $doctor_data Doctor data from field_mapper
     * @param array &$global_services GLOBAL services array (for auto-creation!)
     * @return array<string, mixed>|null Base service or null if excluded
     */
    /**
     * @internal Used via callback in YFGP_Offer_Builder
     */
    public function determine_base_service(array $services, string $speciality, array $mapping, array $doctor_data, array &$global_services): ?array {
        // Priority 0: Check specialty exclusions (UZI/rentgen)
        if ($this->is_specialty_excluded($speciality, $doctor_data, $mapping)) {
            error_log('YFGP v4.18.23: Specialty ' . $speciality . ' excluded, skipping offer creation');
            return null;
        }
        
        // v4.18.22: Get base_service_mode from settings
        $base_service_mode = $this->settings['base_service_mode'] ?? 'automatic';
        error_log('YFGP v4.18.22: Base service mode: ' . $base_service_mode);
        
        // Priority 1: Explicit indication from Doctor (MANDATORY!)
        $doctor_base_service_id = $doctor_data['base_service_id'] ?? null;
        if ($doctor_base_service_id) {
            // v4.18.22: Normalize IDs for comparison (support both 'service_17291' and 17291)
            $normalize_service_id = function($id) {
                if (is_numeric($id)) {
                    return (int) $id;
                }
                if (is_string($id) && strpos($id, 'service_') === 0) {
                    return (int) str_replace('service_', '', $id);
                }
                return $id;
            };
            
            $base_service_id_normalized = $normalize_service_id($doctor_base_service_id);
            
            // Сначала ищем в массиве services
            foreach ($services as $service) {
                $service_id_normalized = $normalize_service_id($service['id']);
                
                if ($service_id_normalized === $base_service_id_normalized) {
                    error_log('YFGP v4.18.22: Base service matched in services array: ' . $service['name'] . ' (ID: ' . $service['id'] . ', normalized: ' . $service_id_normalized . ')');
                    return $service;
                }
            }
            
            // v4.18.22 FIX: Если услуга не найдена в массиве services, загружаем её отдельно из БД
            // base_service_id может указывать на ЛЮБУЮ услугу, даже не из списка services
            $service_post = get_post($base_service_id_normalized);
            if ($service_post && $service_post->post_type === 'services') {
                // Создаём service_entry для базовой услуги
                $base_service_entry = array(
                    'id'        => $base_service_id_normalized,
                    'name'      => $service_post->post_title,
                    'url'       => get_permalink($base_service_id_normalized),
                    'post_id'   => $base_service_id_normalized,
                    'post_type' => $service_post->post_type,
                );
                
                // Заполняем поля через маппинг (нужен mapper_unified)
                if (class_exists('YFGP_Field_Mapper_Unified')) {
                    $mapper_unified = YFGP_Field_Mapper_Unified::get_instance();
                    $this->populate_service_fields_from_mapping($base_service_entry, $base_service_id_normalized, $mapping, $mapper_unified);
                }
                
                // Добавляем в глобальный массив services (если нужно)
                if (!isset($global_services[$base_service_id_normalized])) {
                    $global_services[$base_service_id_normalized] = $base_service_entry;
                }
                
                error_log('YFGP v4.18.22: Base service loaded separately from DB: ' . $base_service_entry['name'] . ' (ID: ' . $base_service_id_normalized . ')');
                return $base_service_entry;
            } else {
                error_log('YFGP v4.18.22: WARNING - base_service_id points to invalid post (ID: ' . $base_service_id_normalized . ', post_type: ' . ($service_post ? $service_post->post_type : 'NOT FOUND') . ')');
            }
        }
        
        // Priority 2: Search for service by specialty from mapper (only for automatic)
        if ($base_service_mode === 'automatic') {
            $speciality_map = $mapping['speciality_base_service_map'] ?? array();
            $speciality_lower = strtolower($speciality);
            
            if (!empty($speciality_map[$speciality_lower])) {
                $target_service_name = $speciality_map[$speciality_lower];
                
                foreach ($services as $service) {
                    if (mb_stripos($service['name'], $target_service_name) !== false) {
                        error_log('YFGP v4.18.22: Base service from specialty map: ' . $service['name']);
                        return $service;
                    }
                }
            }
        }
        
        // Priority 3: Search for service with word "primary"
        $suitable_services = array();
        foreach ($services as $service) {
            if (mb_stripos(mb_strtolower($service['name']), 'первич') !== false) {
                $suitable_services[] = $service;
            }
        }
        
        if (!empty($suitable_services)) {
            $preferred_service = $this->pick_discount_prioritized_service($suitable_services);
            if ($preferred_service !== null) {
                return $preferred_service;
            }
            // No "primary" services with prices, take the first "primary" service
            return $suitable_services[0];
        }
        
        // Priority 4: If services exist - take first service from list (by order in UI)
        // v4.18.50 FIX: В строгом режиме Priority 4 не должен создавать офферы
        if (!empty($services)) {
            // v4.5.0 FIX: Filter out auto-created services (they have no real price!)
            $real_services = array_filter($services, function($service) {
                return empty($service['auto_created']);
            });
            
            if (!empty($real_services)) {
                // v4.18.50 FIX: В строгом режиме Priority 4 не создаёт офферы
                if ($base_service_mode === 'strict') {
                    error_log('YFGP v4.18.50: Strict mode - Priority 4 (first service) not allowed, skipping offer creation');
                    return null; // Не создаём оффер в strict режиме
                }
                
                // v4.18.22 FIX: Выбираем ПЕРВУЮ услугу из списка (по порядку в UI), а не с discount
                // Порядок услуг соответствует порядку в UI (lawyer_uslyga_acf)
                $first_service = reset($real_services);
                error_log('YFGP v4.18.22: Using first service from list: ' . $first_service['name'] . ' (ID: ' . $first_service['id'] . ')');
                return $first_service;
            }
            
            // If ONLY auto-created services exist, use first one (only for automatic mode)
            if ($base_service_mode === 'strict') {
                error_log('YFGP v4.18.50: Strict mode - Only auto-created services available, skipping offer creation');
                return null;
            }
            
            error_log('YFGP v3.5.3: Only auto-created services available, using first');
            return reset($services);
        }
        
        // Priority 5: Auto-creation (with ID and adding to global $services!) (only for automatic)
        if ($base_service_mode === 'automatic') {
            error_log('YFGP v4.18.22: No services found, auto-creating base service (automatic mode)');
            return $this->create_auto_base_service($speciality, $doctor_data['post_id'] ?? 0, $global_services, $mapping);
        } else {
            // strict mode: не создаём оффер, если нет базовой услуги
            error_log('YFGP v4.18.22: No base service found, skipping offer creation (strict mode)');
            return null;
        }
    }
    
    /**
     * Pick service with discount priority
     * 
     * @param array $services Services array
     * @return array|null Selected service or null
     */
    private function pick_discount_prioritized_service(array $services): ?array {
        if (empty($services)) {
            return null;
        }

        $services_with_price = array_filter($services, function ($service) {
            if (!isset($service['price'])) {
                return false;
            }
            $price = $service['price'];
            return $price !== '' && $price !== null && is_numeric($price) && floatval($price) >= 0;
        });

        if (empty($services_with_price)) {
            return null;
        }

        $discount_services = array_filter($services_with_price, function ($service) {
            return $this->has_service_discount_signal($service);
        });

        if (!empty($discount_services)) {
            usort($discount_services, function ($a, $b) {
                $aValue = $this->get_service_discount_sort_value($a);
                $bValue = $this->get_service_discount_sort_value($b);
                if ($aValue === $bValue) {
                    return floatval($a['price']) <=> floatval($b['price']);
                }
                return $aValue <=> $bValue;
            });
            return $discount_services[0];
        }

        usort($services_with_price, function ($a, $b) {
            return floatval($a['price']) <=> floatval($b['price']);
        });

        return $services_with_price[0];
    }

    private function has_service_discount_signal(array $service): bool {
        // v4.18.22 FIX: Только price_discount влияет на выбор базовой услуги
        // discount_name и free_appointment_condition - это текстовые поля, они не должны влиять на выбор
        // Проверка: price_discount (единственный критерий!)
        if (isset($service['price_discount']) && $service['price_discount'] !== '' && is_numeric($service['price_discount'])) {
            return floatval($service['price_discount']) >= 0;
        }
        // discount_name УДАЛЕНА - discount_name не должен влиять на выбор базовой услуги
        // free_appointment_condition УДАЛЕНА - это текстовое поле, не числовое значение
        return false;
    }

    private function get_service_discount_sort_value(array $service): float {
        if (isset($service['price_discount']) && $service['price_discount'] !== '' && is_numeric($service['price_discount'])) {
            return floatval($service['price_discount']);
        }

        return floatval($service['price'] ?? 0);
    }

    private function create_auto_base_service(string $speciality, int $doctor_id, array &$global_services, array $mapping): array {
        // Get service name from mapper specialty or from settings
        $speciality_map = $mapping['speciality_base_service_map'] ?? array();
        $speciality_lower = strtolower($speciality);
        $service_name = $speciality_map[$speciality_lower] 
            ?? ($mapping['base_service_default_name'] 
            ?? ($this->settings['default_service_name'] ?? 'Первичный приём')); // v4.17.0: FIX #5 - add settings check
        
        // v4.4.1: Clean auto-created service ID (ONLY numeric, NO SLUG!)
        // Format: service_auto_DOCTOR_ID (Yandex requires numeric IDs)
        $service_id = 'service_auto_' . $doctor_id;
        
        // v4.5.1 FIX: Check if auto-service already exists (prevent duplicates!)
        // If doctor has multiple clinics, reuse the SAME auto-service for ALL clinics
        if (isset($global_services[$service_id])) {
            error_log("YFGP v4.5.1: Auto-service {$service_id} already exists, reusing for another clinic");
            return $global_services[$service_id];
        }
        
        $auto_service = array(
            'id' => $service_id,  // v4.4.1: service_auto_24823 (Yandex-compliant)
            'name' => $service_name,
            'internal_id' => $service_id,
            'description' => $service_name,
            'is_base_service' => true,
            'auto_created' => true  // Flag for logging
        );

        // v4.18.24: Автогенерированные услуги НЕ должны иметь цену
        // Цена должна браться только из БД (связанные посты prices)
        // Удалено: default_auto_service_price больше не используется
        
        // CRITICAL: Add to GLOBAL services array!
        // This ensures service appears in <services> block in YML
        $global_services[$service_id] = $auto_service;

        error_log('YFGP v4.5.1: Auto-created NEW service: ' . $service_id . ' (name: ' . $service_name . ')');
        
        // v4.5.1: Log ONLY when creating NEW service (not when reusing!)
        $this->log_autocreated_service($doctor_id, $service_name, null);
        
        return $auto_service;
    }
    
    /**
     * Deprecated method - kept for backward compatibility
     * @deprecated v2.3.0 Use determine_base_service()
     * @deprecated v3.5.3 Parameters changed, this method no longer works correctly!
     */
    private function find_base_service(array $services, string $specialization, array $specialties_no_primary): ?array {
        // v3.5.3: WARNING! This method is deprecated and works with limitations
        // New method requires $doctor_data and &$global_services
        $mapping = get_option('yfgp_field_mapping_v3', array());
        $dummy_doctor_data = array('post_id' => 0); // Placeholder
        $dummy_global_services = array(); // Local variable (auto-creation doesn't work!)
        
        error_log('YFGP v3.5.3: WARNING - find_base_service is deprecated, use determine_base_service directly!');
        
        return $this->determine_base_service($services, $specialization, $mapping, $dummy_doctor_data, $dummy_global_services);
    }

    /**
     * Get list of specialties without primary appointment
     *
     * @return array<string> Array of specialty slugs
     */
    private function get_specialties_no_primary(): array {
        $text = $this->settings['specialties_no_primary'] ?? '';
        $specialties = array_map('trim', explode(',', $text));
        return array_map('strtolower', $specialties);
    }

    /**
     * Получить список slug-ов специализаций для врача
     *
     * @param int $post_id
     * @return array<int, string>
     */
    private function get_doctor_speciality_terms(int $post_id): array {
        if ($post_id <= 0) {
            return array();
        }

        $taxonomy = $this->settings['exclusions_taxonomy'] ?? '';
        if (empty($taxonomy)) {
            $taxonomy = $this->settings['specialties_taxonomy'] ?? '';
        }

        if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
            return array();
        }

        $terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'id=>slug'));
        if (is_wp_error($terms) || empty($terms)) {
            return array();
        }

        return array_values(array_filter(array_map('strval', $terms)));
    }

    /**
     * Normalize doctor field values for condition comparison (case-insensitive).
     *
     * @param mixed $value
     * @return array<int, string>
     */
    private function normalize_condition_values($value): array {
        $normalized = array();

        $walker = function ($item) use (&$walker, &$normalized): void {
            if ($item === null) {
                return;
            }

            if (is_array($item)) {
                foreach ($item as $sub) {
                    $walker($sub);
                }
                return;
            }

            if (is_bool($item)) {
                $item = $item ? 'true' : 'false';
            }

            $string = trim((string) $item);
            if ($string === '') {
                return;
            }

            $normalized[] = mb_strtolower($string, 'UTF-8');
        };

        $walker($value);

        return array_values(array_unique($normalized));
    }

    /**
     * Check if at least one value matches any target (both already normalized).
     *
     * @param array<int, string> $field_values
     * @param array<int, string> $target_values
     * @return bool
     */
    private function has_condition_match(array $field_values, array $target_values): bool {
        if (empty($field_values) || empty($target_values)) {
            return false;
        }

        return count(array_intersect($field_values, $target_values)) > 0;
    }

    /**
     * Determine if value should be treated as empty for condition checks.
     *
     * @param mixed $value
     * @return bool
     */
    private function is_condition_value_empty($value): bool {
        if ($value === null) {
            return true;
        }

        if (is_array($value)) {
            foreach ($value as $sub) {
                if (!$this->is_condition_value_empty($sub)) {
                    return false;
                }
            }
            return true;
        }

        if (is_bool($value)) {
            return !$value;
        }

        return trim((string) $value) === '';
    }

    /**
     * Build YML structure v2.0
     */
    private function build_yml_stream(array $doctors, array $clinics, array $services, array $offers): string {
        return $this->xml_writer->build($doctors, $clinics, $services, $offers);
    }

    private function build_yml_v2(array $doctors, array $clinics, array $services, array $offers): string {
        // v4.18.1: DEBUG - log offers count
        error_log('YFGP v4.18.1 DEBUG build_yml_v2: doctors count: ' . count($doctors) . ', clinics count: ' . count($clinics) . ', services count: ' . count($services) . ', offers count: ' . count($offers));
        $now = current_time('Y-m-d H:i');
        
        $yml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $yml .= '<shop version="2.0" date="' . $now . '">' . "\n";
        
        // Company information
        $yml .= '  <name>' . $this->escape_xml($this->settings['shop_name'] ?? get_bloginfo('name')) . '</name>' . "\n";
        $yml .= '  <company>' . $this->escape_xml($this->settings['company_name'] ?? get_bloginfo('name')) . '</company>' . "\n";
        $yml .= '  <url>' . $this->escape_xml($this->settings['company_url'] ?? get_site_url()) . '</url>' . "\n";
        
        // Shop logo (v4.18.1: required field per Yandex specification)
        $mapping = get_option('yfgp_field_mapping_v3', array());
        $shop_picture = $mapping['shop_picture'] ?? $this->settings['shop_picture'] ?? null;
        
        // v4.18.1: Fallback - use site icon if not specified
        if (empty($shop_picture)) {
            $shop_picture = get_site_icon_url(512); // Get site icon (512x512)
            if (empty($shop_picture)) {
                // If site icon is also empty - warning in log
                error_log('YFGP v4.18.1: Warning - shop_picture is empty, using empty string (field is required by Yandex spec)');
                $shop_picture = ''; // Empty string, but field will be present in XML
            } else {
                error_log('YFGP v4.18.1: Info - shop_picture not set, using site icon: ' . $shop_picture);
            }
        }
        
        // v4.18.1: Required field, always generate
        $yml .= '  <picture>' . $this->escape_xml($shop_picture) . '</picture>' . "\n";
        
        // Email for inquiries (optional)
        if (!empty($this->settings['company_email'])) {
            $yml .= '  <email>' . $this->escape_xml($this->settings['company_email']) . '</email>' . "\n";
        }
        
        // Doctors block
        $yml .= '  <doctors>' . "\n";
        foreach ($doctors as $doctor) {
            $yml .= $this->build_doctor_xml($doctor);
        }
        $yml .= '  </doctors>' . "\n";
        
        // Clinics block
        $yml .= '  <clinics>' . "\n";
        foreach ($clinics as $clinic) {
            $yml .= $this->build_clinic_xml($clinic);
        }
        $yml .= '  </clinics>' . "\n";
        
        // Services block
        $yml .= '  <services>' . "\n";
        foreach ($services as $service) {
            $yml .= $this->build_service_xml($service);
        }
        $yml .= '  </services>' . "\n";
        
        // Offers block
        $yml .= '  <offers>' . "\n";
        foreach ($offers as $offer) {
            $yml .= $this->build_offer_xml($offer);
        }
        $yml .= '  </offers>' . "\n";
        
        $yml .= '</shop>' . "\n";
        
        return $yml;
    }

    /**
     * Build doctor XML
     *
     * @param array<string, mixed> $doctor Doctor data
     * @return string XML representation of doctor
     */
    private function build_doctor_xml(array $doctor): string {
        $xml = '    <doctor id="' . $this->escape_xml($doctor['id']) . '">' . "\n";
        $xml .= '      <name>' . $this->escape_xml($doctor['name']) . '</name>' . "\n";
        $xml .= '      <url>' . $this->escape_xml($doctor['url']) . '</url>' . "\n";
        $xml .= '      <internal_id>' . $this->escape_xml($doctor['internal_id']) . '</internal_id>' . "\n";
        
        if (!empty($doctor['description'])) {
            // v4.1.0-beta31: Strip HTML tags from description (Yandex doesn't accept HTML!)
            $clean_description = strip_tags($doctor['description']);
            $xml .= '      <description>' . $this->escape_xml(substr($clean_description, 0, 500)) . '</description>' . "\n";
        }
        
        if (!empty($doctor['surname'])) {
            $xml .= '      <surname>' . $this->escape_xml($doctor['surname']) . '</surname>' . "\n";
        }
        
        if (!empty($doctor['first_name'])) {
            $xml .= '      <first_name>' . $this->escape_xml($doctor['first_name']) . '</first_name>' . "\n";
        }
        
        if (!empty($doctor['patronymic'])) {
            $xml .= '      <patronymic>' . $this->escape_xml($doctor['patronymic']) . '</patronymic>' . "\n";
        }
        
        // v4.18.21: experience_years - выводим только если не пустой и не равен '0'
        if (isset($doctor['experience_years']) && $doctor['experience_years'] !== '' && $doctor['experience_years'] !== '0') {
            $xml .= '      <experience_years>' . $this->escape_xml($doctor['experience_years']) . '</experience_years>' . "\n";
        }
        
        if (!empty($doctor['picture'])) {
            $xml .= '      <picture>' . $this->escape_xml($doctor['picture']) . '</picture>' . "\n";
        }
        
        // New fields v2.3.0
        if (!empty($doctor['career_start_date'])) {
            $xml .= '      <career_start_date>' . $this->escape_xml($doctor['career_start_date']) . '</career_start_date>' . "\n";
        }
        
        if (!empty($doctor['degree'])) {
            $xml .= '      <degree>' . $this->escape_xml($doctor['degree']) . '</degree>' . "\n";
        }
        
        if (!empty($doctor['rank'])) {
            $xml .= '      <rank>' . $this->escape_xml($doctor['rank']) . '</rank>' . "\n";
        }
        
        if (!empty($doctor['category'])) {
            $xml .= '      <category>' . $this->escape_xml($doctor['category']) . '</category>' . "\n";
        }
        
        if (!empty($doctor['reviews_total_count'])) {
            $xml .= '      <reviews_total_count>' . $this->escape_xml($doctor['reviews_total_count']) . '</reviews_total_count>' . "\n";
        }
        
        // Offer-level availability flags (house_call/telemed) выводятся только внутри <offer>
        // поэтому не дублируем их в блоке <doctor>, чтобы не нарушать спецификацию Яндекса.
        
        // Complex fields DOCTOR
        if (!empty($doctor['education'])) {
            $xml .= $this->build_education_xml($doctor['education']);
        }
        
        if (!empty($doctor['job'])) {
            $xml .= $this->build_job_xml($doctor['job']);
        }
        
        if (!empty($doctor['certificate'])) {
            $xml .= $this->build_certificate_xml($doctor['certificate']);
        }
        
        if (!empty($doctor['reviews'])) {
            $xml .= $this->build_reviews_xml($doctor['reviews']);
        }
        
        $xml .= '    </doctor>' . "\n";
        return $xml;
    }

    /**
     * Build clinic XML
     *
     * @param array<string, mixed> $clinic Clinic data
     * @return string XML representation of clinic
     */
    private function build_clinic_xml(array $clinic): string {
        $xml = '    <clinic id="' . $this->escape_xml($clinic['id']) . '">' . "\n";
        $xml .= '      <name>' . $this->escape_xml($clinic['name']) . '</name>' . "\n";
        $xml .= '      <url>' . $this->escape_xml($clinic['url']) . '</url>' . "\n";
        $xml .= '      <internal_id>' . $this->escape_xml($clinic['internal_id']) . '</internal_id>' . "\n";
        
        if (!empty($clinic['city'])) {
            $xml .= '      <city>' . $this->escape_xml($clinic['city']) . '</city>' . "\n";
        }
        
        if (!empty($clinic['address'])) {
            $xml .= '      <address>' . $this->escape_xml($clinic['address']) . '</address>' . "\n";
        }
        
        if (!empty($clinic['phone'])) {
            $xml .= '      <phone>' . $this->escape_xml($clinic['phone']) . '</phone>' . "\n";
        }
        
        if (!empty($clinic['email'])) {
            $xml .= '      <email>' . $this->escape_xml($clinic['email']) . '</email>' . "\n";
        }
        
        // New fields v2.3.0
        if (!empty($clinic['picture'])) {
            $xml .= '      <picture>' . $this->escape_xml($clinic['picture']) . '</picture>' . "\n";
        }
        
        if (!empty($clinic['company_id'])) {
            $xml .= '      <company_id>' . $this->escape_xml($clinic['company_id']) . '</company_id>' . "\n";
        }
        
        $xml .= '    </clinic>' . "\n";
        return $xml;
    }

    /**
     * Build service XML
     *
     * @param array<string, mixed> $service Service data
     * @return string XML representation of service
     */
    private function build_service_xml(array $service): string {
        $xml = '    <service id="' . $this->escape_xml($service['id']) . '">' . "\n";
        $xml .= '      <name>' . $this->escape_xml($service['name']) . '</name>' . "\n";
        $xml .= '      <internal_id>' . $this->escape_xml($service['internal_id']) . '</internal_id>' . "\n";
        
        if (!empty($service['description'])) {
            $xml .= '      <description>' . $this->escape_xml($service['description']) . '</description>' . "\n";
        }
        
        // New fields v2.3.0
        if (!empty($service['gov_id'])) {
            $xml .= '      <gov_id>' . $this->escape_xml($service['gov_id']) . '</gov_id>' . "\n";
        }
        
        $xml .= '    </service>' . "\n";
        return $xml;
    }

    /**
     * Build offer XML
     *
     * @param array<string, mixed> $offer Offer data
     * @return string XML representation of offer
     */
    private function build_offer_xml(array $offer): string {
        $xml = '    <offer id="' . $this->escape_xml($offer['id']) . '">' . "\n";
        
        // URL for booking
        if (!empty($offer['appointment_url'])) {
            $xml .= '      <url>' . $this->escape_xml($offer['appointment_url']) . '</url>' . "\n";
        }
        
        // Online scheduling and booking
        if (isset($offer['online_schedule'])) {
            $value = ($offer['online_schedule'] === 'true' || $offer['online_schedule'] === true) ? 'true' : 'false';
            $xml .= '      <online_schedule>' . $value . '</online_schedule>' . "\n";
        }
        
        if (isset($offer['appointment_available'])) {
            $value = ($offer['appointment_available'] === 'true' || $offer['appointment_available'] === true) ? 'true' : 'false';
            $xml .= '      <appointment>' . $value . '</appointment>' . "\n";
        }
        
        // OMS
        if (isset($offer['oms_available'])) {
            $value = ($offer['oms_available'] === 'true' || $offer['oms_available'] === true) ? 'true' : 'false';
            $xml .= '      <oms>' . $value . '</oms>' . "\n";
        }
        
        // Price
        // v4.18.1: FIX - use base_price instead of price, per specification if price is present, then base_price and currency are required
        $base_price = $offer['base_price'] ?? $offer['price'] ?? null;
        $currency = $offer['currency'] ?? $this->settings['default_currency'] ?? 'RUR';
        
        if ($base_price !== null && $base_price !== '') {
            $xml .= '      <price>' . "\n";
            $xml .= '        <base_price>' . $this->escape_xml($base_price) . '</base_price>' . "\n";
            $xml .= '        <currency>' . $this->escape_xml($currency) . '</currency>' . "\n";
            
            // v4.18.18: Discount with optional name attribute (per Yandex spec)
            // v4.18.21: FIX - атрибут name опциональный, выводим <discount> без name если discount_name пустой
            // v4.18.21: free_appointment выводим ТОЛЬКО если есть <discount> (по документации Яндекс)
            $has_discount = false;
            if (!empty($offer['discount'])) {
                $discount_attr = '';
                if (!empty($offer['discount_name'])) {
                    $discount_attr = ' name="' . $this->escape_xml($offer['discount_name']) . '"';
                }
                $xml .= '        <discount' . $discount_attr . '>' . $this->escape_xml($offer['discount']) . '</discount>' . "\n";
                $has_discount = true;
            }
            
            // Условие бесплатного приема
            // v4.18.21: Выводим free_appointment ТОЛЬКО если есть <discount> (по документации Яндекс)
            if ($has_discount && !empty($offer['free_appointment_condition'])) {
                $free_appointment_text = $this->normalize_free_appointment_text($offer['free_appointment_condition'], $offer['discount_name'] ?? null);
                if ($free_appointment_text !== '') {
                    $xml .= '        <free_appointment>' . $this->escape_xml($free_appointment_text) . '</free_appointment>' . "\n";
                }
            }
            
            $xml .= '      </price>' . "\n";
        } elseif (($offer['price'] ?? null) !== null || ($offer['base_price'] ?? null) !== null) {
            // v4.18.1: Валидация - если price/base_price указан, но пустой, предупреждение в лог
            error_log('YFGP v4.18.1: Warning - price/base_price is empty for offer ' . ($offer['id'] ?? 'unknown'));
        }
        
        // Ссылка на услугу
        $xml .= '      <service id="' . $this->escape_xml($offer['service_id']) . '"/>' . "\n";
        
        // Клиника с врачом
        $xml .= '      <clinic id="' . $this->escape_xml($offer['clinic_id']) . '">' . "\n";
        $xml .= '        <doctor id="' . $this->escape_xml($offer['doctor_id']) . '">' . "\n";
        $xml .= '          <speciality>' . $this->escape_xml($this->get_speciality_label($offer['speciality'])) . '</speciality>' . "\n";
        
        // Appointment types
        // v4.18.21: DEBUG - log what comes into build_offer_xml
        error_log("YFGP v4.18.21 DEBUG build_offer_xml: offer_id = " . ($offer['id'] ?? 'unknown') . ", adult_appointment = " . var_export($offer['adult_appointment'] ?? 'NOT SET', true) . ", children_appointment = " . var_export($offer['children_appointment'] ?? 'NOT SET', true));
        
        if (isset($offer['children_appointment'])) {
            $value = ($offer['children_appointment'] === 'true' || $offer['children_appointment'] === true) ? 'true' : 'false';
            $xml .= '          <children_appointment>' . $value . '</children_appointment>' . "\n";
            // v4.18.21: DEBUG - логируем что выводится в XML
            error_log("YFGP v4.18.21 DEBUG build_offer_xml: Writing children_appointment = '$value' for offer " . ($offer['id'] ?? 'unknown'));
        }
        
        if (isset($offer['adult_appointment'])) {
            $value = ($offer['adult_appointment'] === 'true' || $offer['adult_appointment'] === true) ? 'true' : 'false';
            $xml .= '          <adult_appointment>' . $value . '</adult_appointment>' . "\n";
            // v4.18.21: DEBUG - логируем что выводится в XML
            error_log("YFGP v4.18.21 DEBUG build_offer_xml: Writing adult_appointment = '$value' for offer " . ($offer['id'] ?? 'unknown'));
        }
        
        if (isset($offer['house_call'])) {
            $value = ($offer['house_call'] === 'true' || $offer['house_call'] === true) ? 'true' : 'false';
            $xml .= '          <house_call>' . $value . '</house_call>' . "\n";
        }
        
        if (isset($offer['telemed'])) {
            $value = ($offer['telemed'] === 'true' || $offer['telemed'] === true) ? 'true' : 'false';
            $xml .= '          <telemed>' . $value . '</telemed>' . "\n";
        }
        
        // Base service
        $xml .= '          <is_base_service>' . ($offer['is_base_service'] ? 'true' : 'false') . '</is_base_service>' . "\n";
        
        $xml .= '        </doctor>' . "\n";
        $xml .= '      </clinic>' . "\n";
        
        $xml .= '    </offer>' . "\n";
        return $xml;
    }

    /**
     * Escape XML
     */
    private function escape_xml(string $string): string {
        return htmlspecialchars($string, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Normalize speciality value (protect from arrays)
     * 
     * v4.18.1: FIX - protect from arrays for all data sources (checkbox, taxonomy, select, text)
     * 
     * @param mixed $value Speciality value (can be string, array, object)
     * @param string $fallback Fallback value if value is incorrect
     * @return string Normalized string
     */
    private function normalize_speciality_value($value, string $fallback = ''): string {
        // Protect from arrays
        if (is_array($value)) {
            // If array - extract label
            $value = $value['label'] ?? $value[0] ?? $fallback;
        }
        if (!is_string($value)) {
            $value = (string)$value;
        }
        if ($value === '') {
            $value = $fallback !== '' ? $fallback : 'врач';
        }
        return $value;
    }
    
    /**
     * Convert speciality slug to label
     * 
     * v4.18.1: Fix validation YML - speciality must be string (label), not slug
     * Uses existing method getSpecialityLabelFromSlug() from Unified Mapper
     * 
     * @param mixed $value Speciality value (can be slug, label, array)
     * @return string Specialty label (human-readable name)
     */
    private function get_speciality_label($value): string {
        // v4.18.1: FIX - protect from arrays
        if (is_array($value)) {
            // If array - extract label
            $value = $value['label'] ?? $value[0] ?? '';
            if (!is_string($value)) {
                $value = (string)$value;
            }
        }
        if (!is_string($value)) {
            $value = (string)$value;
        }
        
        // Check: if string already contains Cyrillic → use as is
        if (preg_match('/[а-яё]/iu', $value)) {
            return $value;
        }
        
        // If slug (Latin) → convert through reference
        if (!class_exists('YFGP_Field_Mapper_Unified')) {
            require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
        }
        $mapper = YFGP_Field_Mapper_Unified::get_instance();
        if (method_exists($mapper, 'getSpecialityLabelFromSlug')) {
            $label = $mapper->getSpecialityLabelFromSlug($value);
            if ($label !== null && $label !== '') {
                return $label;
            }
        }
        
        // Fallback: if not found → use value as is
        return $value;
    }
    
    /**
     * Validate and parse date in ISO 8601 format (YYYY-MM-DD)
     * 
     * v4.18.1: Used for validating career_start_date
     * 
     * @param string $date_string String with date (can be in different formats)
     * @return int|null Timestamp or null if date is invalid
     */
    private function validate_and_parse_date(string $date_string): ?int {
        if (empty($date_string)) {
            return null;
        }
        
        // Try standard format YYYY-MM-DD
        $timestamp = strtotime($date_string);
        if ($timestamp !== false) {
            // Check that date is in correct format (YYYY-MM-DD)
            $parsed = date('Y-m-d', $timestamp);
            if ($parsed === $date_string || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_string)) {
                return $timestamp;
            }
        }
        
        // Try other formats
        $formats = ['Y-m-d', 'Y/m/d', 'd.m.Y', 'd/m/Y', 'Y'];
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $date_string);
            if ($date !== false) {
                return $date->getTimestamp();
            }
        }
        
        // If only year (YYYY) - use January 1st of that year
        if (preg_match('/^\d{4}$/', $date_string)) {
            $year = (int) $date_string;
            if ($year >= 1900 && $year <= (int) date('Y')) {
                return mktime(0, 0, 0, 1, 1, $year);
            }
        }
        
        return null;
    }
    
    /**
     * Deprecated method v2.2.0 - no longer used
     * @deprecated v2.3.0 Base service now created automatically via determine_base_service()
     */
    private function handle_no_services($post, array $data, array $mapping, array &$offers, int $doctor_id, array $clinics): void {
        // Method deprecated - base service now created automatically in build_offers_v2()
    }
    
    /**
     * Deprecated method v2.2.0 - no longer used
     * @deprecated v2.3.0 Price is not required for base service
     */
    private function find_any_service_with_price(array $services): ?array {
        // Method deprecated - price is not required for base service
        return null;
    }
    
    /**
     * Log auto-created service
     * v2.2.0: New logging method
     *
     * @param string $doctor_id Doctor ID (with prefix doctor_)
     * @param string $service_name Service name
     * @param mixed $price Service price
     */
    private function log_autocreated_service(int $doctor_id, string $service_name, $price): void {
        $log = get_transient('yfgp_feed_log') ?: array();
        $log[] = array(
            'type' => 'autocreated_service',
            'doctor_id' => $doctor_id,
            'doctor_name' => get_the_title($doctor_id),
            'service' => $service_name,
            'price' => $price,
            'timestamp' => time()
        );
        set_transient('yfgp_feed_log', $log, HOUR_IN_SECONDS);
    }
    
    /**
     * Log doctor without services
     * v2.2.0: New logging method
     *
     * @param string $doctor_id Doctor ID (with prefix doctor_)
     * @param string $reason Reason for missing services
     */
    private function log_missing_services(int $doctor_id, string $reason): void {
        $log = get_transient('yfgp_feed_log') ?: array();
        $log[] = array(
            'type' => 'missing_services',
            'doctor_id' => $doctor_id,
            'doctor_name' => get_the_title($doctor_id),
            'reason' => $reason,
            'timestamp' => time()
        );
        set_transient('yfgp_feed_log', $log, HOUR_IN_SECONDS);
    }
    
    /**
     * Log services without prices
     * v2.2.0: New logging method
     *
     * @param string $doctor_id Doctor ID (with prefix doctor_)
     * @param int $services_count Services count
     */
    private function log_no_prices(int $doctor_id, int $services_count): void {
        $log = get_transient('yfgp_feed_log') ?: array();
        $log[] = array(
            'type' => 'no_prices',
            'doctor_id' => $doctor_id,
            'doctor_name' => get_the_title($doctor_id),
            'services_count' => $services_count,
            'reason' => 'All services without prices',
            'timestamp' => time()
        );
        set_transient('yfgp_feed_log', $log, HOUR_IN_SECONDS);
    }
    
    /**
     * Log usage of fallback service
     * v2.2.0: New logging method
     *
     * @param string $doctor_id Doctor ID (with prefix doctor_)
     * @param string $service_name Service name
     */
    private function log_used_fallback_service(int $doctor_id, string $service_name): void {
        $log = get_transient('yfgp_feed_log') ?: array();
        $log[] = array(
            'type' => 'fallback_service',
            'doctor_id' => $doctor_id,
            'doctor_name' => get_the_title($doctor_id),
            'service' => $service_name,
            'reason' => 'Base service without price, used another',
            'timestamp' => time()
        );
        set_transient('yfgp_feed_log', $log, HOUR_IN_SECONDS);
    }
    
    /**
     * Validate URLs in offers
     * v2.4.0: Checks that URL exists in doctor OR in offer
     *
     * @param array<int, array<string, mixed>> $offers Offers array
     * @param string $doctor_id Doctor ID (with prefix doctor_) for validation
     */
    private function validate_offers_urls(array $offers, int $doctor_id): void {
        $doctor_url = get_permalink($doctor_id);
        $has_doctor_url = !empty($doctor_url);
        
        foreach ($offers as $offer_id => $offer) {
            $has_offer_url = !empty($offer['appointment_url']);
            
            // If no URL in doctor or offer - log error
            if (!$has_doctor_url && !$has_offer_url) {
                $this->log_missing_url($offer_id, $doctor_id);
            }
        }
    }
    
    /**
     * Log offer without URL
     * v2.4.0: New logging method
     *
     * @param string $offer_id Offer ID
     * @param string $doctor_id Doctor ID (with prefix doctor_)
     */
    private function log_missing_url(string $offer_id, int $doctor_id): void {
        $log = get_transient('yfgp_feed_log') ?: array();
        $log[] = array(
            'type' => 'missing_url',
            'offer_id' => $offer_id,
            'doctor_id' => $doctor_id,
            'doctor_name' => get_the_title($doctor_id),
            'reason' => 'No URL in doctor or offer (required by Yandex)',
            'timestamp' => time()
        );
        set_transient('yfgp_feed_log', $log, HOUR_IN_SECONDS);
    }
    
    /**
     * Check service suitability for primary appointment (REMOVED METHOD)
     * v2.4.0: Improved logic for base service selection
     * 
     * @deprecated v2.3.1: Simple heuristic for base service - without extended primary appointment check
     * Method is_service_suitable_for_primary removed in this version
     * 
     * @param string $service_name Service name
     * @param string $speciality Doctor specialty
     * @return bool True if service is suitable
     */
    private function is_service_suitable_for_primary_removed(string $service_name, string $speciality): bool {
        $service_lower = mb_strtolower($service_name);
        $speciality_lower = mb_strtolower($speciality);
        
        // Exclusions for specialties without primary appointments
        $no_primary_specialities = array(
            '╤Г╨╖╨╕', '╤А╨╡╨╜╤В╨│╨╡╨╜', '╨╗╨░╨▒╨╛╤А╨░╤В╨╛╤А╨╕╤П', '╨░╨╜╨░╨╗╨╕╨╖╤Л', '╨┤╨╕╨░╨│╨╜╨╛╤Б╤В╨╕╨║╨░',
            '╨╝╨░╤Б╤Б╨░╨╢', '╤Д╨╕╨╖╨╕╨╛╤В╨╡╤А╨░╨┐╨╕╤П', '╤А╨╡╨░╨▒╨╕╨╗╨╕╤В╨░╤Ж╨╕╤П'
        );
        
        // If specialty is in exclusion list - any service is suitable
        foreach ($no_primary_specialities as $excluded) {
            if (mb_stripos($speciality_lower, $excluded) !== false) {
                return true;
            }
        }
        
        // Keywords for primary appointment
        $primary_keywords = array(
            'первичн', 'консульт', 'осмотр', 'приём', 'диагност',
            'обследова', 'консультация', 'осмотр врач'
        );
        
        // Check for presence of keywords
        foreach ($primary_keywords as $keyword) {
            if (mb_stripos($service_lower, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Build XML for doctor education
     * v2.4.0: New method for complex fields
     * v4.1.0-beta30: Updated to match Yandex.Health YML v2.0 spec
     *
     * @param array<string, mixed> $education Education data
     * @return string XML representation of education
     */
    private function build_education_xml(array $education): string {
        $xml = '';
        foreach ($education as $edu) {
            $xml .= '      <education>' . "\n";
            
            // Yandex spec: organization (educational institution) - required
            if (!empty($edu['organization'])) {
                $xml .= '        <organization>' . $this->escape_xml($edu['organization']) . '</organization>' . "\n";
            }
            
            // Yandex spec: finish_year (year of completion) - required
            if (!empty($edu['finish_year'])) {
                $xml .= '        <finish_year>' . $this->escape_xml($edu['finish_year']) . '</finish_year>' . "\n";
            }
            
            // Yandex spec: type (type of education) - required
            if (!empty($edu['type'])) {
                $xml .= '        <type>' . $this->escape_xml($edu['type']) . '</type>' . "\n";
            }
            
            // Yandex spec: specialization (specialty) - optional
            if (!empty($edu['specialization'])) {
                $xml .= '        <specialization>' . $this->escape_xml($edu['specialization']) . '</specialization>' . "\n";
            }
            
            $xml .= '      </education>' . "\n";
        }
        return $xml;
    }
    
    /**
     * Build XML for doctor work places
     * v2.4.0: New method for complex fields
     *
     * @param array<string, mixed> $job Job/workplace data
     * @return string XML representation of work place
     */
    private function build_job_xml(array $job): string {
        $xml = '';
        foreach ($job as $j) {
            $xml .= '      <job>' . "\n";
            if (!empty($j['organization'])) {
                $xml .= '        <organization>' . $this->escape_xml($j['organization']) . '</organization>' . "\n";
            }
            if (!empty($j['period_years'])) {
                $xml .= '        <period_years>' . $this->escape_xml($j['period_years']) . '</period_years>' . "\n";
            }
            if (!empty($j['position'])) {
                $xml .= '        <position>' . $this->escape_xml($j['position']) . '</position>' . "\n";
            }
            $xml .= '      </job>' . "\n";
        }
        return $xml;
    }
    
    /**
     * Build XML for doctor certificates
     * v2.4.0: New method for complex fields
     *
     * @param array<string, mixed> $certificate Certificate data
     * @return string XML representation of certificate
     */
    private function build_certificate_xml(array $certificate): string {
        $xml = '';
        foreach ($certificate as $cert) {
            $xml .= '      <certificate>' . "\n";
            if (!empty($cert['organization'])) {
                $xml .= '        <organization>' . $this->escape_xml($cert['organization']) . '</organization>' . "\n";
            }
            if (!empty($cert['finish_year'])) {
                $xml .= '        <finish_year>' . $this->escape_xml($cert['finish_year']) . '</finish_year>' . "\n";
            }
            if (!empty($cert['name'])) {
                $xml .= '        <name>' . $this->escape_xml($cert['name']) . '</name>' . "\n";
            }
            $xml .= '      </certificate>' . "\n";
        }
        return $xml;
    }
    
    /**
     * Transliterate Russian text to Latin
     * 
     * @param string $text Text for transliteration
     * @return string Transliterated text
     * @since 3.2.2
     */
    private function transliterate_russian(string $text): string {
        // Full transliteration table for Cyrillic
        $transliteration = array(
            'а' => 'a',    'б' => 'b',    'в' => 'v',    'г' => 'g',    'д' => 'd',
            'е' => 'e',    'ё' => 'yo',   'ж' => 'zh',   'з' => 'z',    'и' => 'i',
            'й' => 'y',    'к' => 'k',    'л' => 'l',    'м' => 'm',    'н' => 'n',
            'о' => 'o',    'п' => 'p',    'р' => 'r',    'с' => 's',    'т' => 't',
            'у' => 'u',    'ф' => 'f',    'х' => 'h',    'ц' => 'ts',   'ч' => 'ch',
            'ш' => 'sh',   'щ' => 'sch',  'ъ' => '',     'ы' => 'y',    'ь' => '',
            'э' => 'e',    'ю' => 'yu',   'я' => 'ya',
            'А' => 'A',    'Б' => 'B',    'В' => 'V',    'Г' => 'G',    'Д' => 'D',
            'Е' => 'E',    'Ё' => 'Yo',   'Ж' => 'Zh',   'З' => 'Z',    'И' => 'I',
            'Й' => 'Y',    'К' => 'K',    'Л' => 'L',    'М' => 'M',    'Н' => 'N',
            'О' => 'O',    'П' => 'P',    'Р' => 'R',    'С' => 'S',    'Т' => 'T',
            'У' => 'U',    'Ф' => 'F',    'Х' => 'H',    'Ц' => 'Ts',   'Ч' => 'Ch',
            'Ш' => 'Sh',   'Щ' => 'Sch',  'Ъ' => '',     'Ы' => 'Y',    'Ь' => '',
            'Э' => 'E',    'Ю' => 'Yu',   'Я' => 'Ya',
        );
        
        return strtr($text, $transliteration);
    }
    
    /**
     * Build XML for doctor reviews
     * v2.4.0: New method for complex fields (reviews relationship)
     *
     * @param array<int, array<string, mixed>> $reviews Reviews array
     * @return string XML representation of reviews
     */
    private function build_reviews_xml(array $reviews): string {
        $xml = '';
        foreach ($reviews as $rev) {
            // v4.18.3: According to Yandex specification, reviews without <grade> are ignored
            if (empty($rev['grade'])) {
                continue;
            }
            
            $xml .= '      <reviews>' . "\n";
            if (!empty($rev['date'])) {
                $xml .= '        <date>' . $this->escape_xml($rev['date']) . '</date>' . "\n";
            }
            if (!empty($rev['checked'])) {
                $xml .= '        <checked>' . $this->escape_xml($rev['checked']) . '</checked>' . "\n";
            }
            if (!empty($rev['used_in_rating'])) {
                $xml .= '        <used_in_rating>' . $this->escape_xml($rev['used_in_rating']) . '</used_in_rating>' . "\n";
            }
            if (!empty($rev['author'])) {
                $xml .= '        <author>' . $this->escape_xml($rev['author']) . '</author>' . "\n";
            }
            if (!empty($rev['author_id'])) {
                // v3.2.2: Add prefix 'author_' and transliterate Cyrillic
                $author_id = $rev['author_id'];
                
                // Transliterate if contains Cyrillic
                $author_id = $this->transliterate_russian($author_id);
                
                // Add prefix 'author_' if not present
                if (strpos($author_id, 'author_') !== 0) {
                    $author_id = 'author_' . $author_id;
                }
                
                $xml .= '        <author_id>' . $this->escape_xml($author_id) . '</author_id>' . "\n";
            }
            if (!empty($rev['author_picture'])) {
                $xml .= '        <author_picture>' . $this->escape_xml($rev['author_picture']) . '</author_picture>' . "\n";
            }
            if (!empty($rev['url'])) {
                $xml .= '        <url>' . $this->escape_xml($rev['url']) . '</url>' . "\n";
            }
            if (!empty($rev['comment'])) {
                $xml .= '        <comment>' . $this->escape_xml($rev['comment']) . '</comment>' . "\n";
            }
            if (!empty($rev['grade'])) {
                $xml .= '        <grade>' . $this->escape_xml($rev['grade']) . '</grade>' . "\n";
            }
            if (!empty($rev['positive'])) {
                $xml .= '        <positive>' . $this->escape_xml($rev['positive']) . '</positive>' . "\n";
            }
            if (!empty($rev['negative'])) {
                $xml .= '        <negative>' . $this->escape_xml($rev['negative']) . '</negative>' . "\n";
            }
            if (!empty($rev['response'])) {
                $xml .= '        <response>' . $this->wrap_cdata($rev['response']) . '</response>' . "\n";
            }
            $xml .= '      </reviews>' . "\n";
        }
        return $xml;
    }

    /**
     * Универсальная функция для извлечения boolean поля с поддержкой conditional logic
     * 
     * v4.18.21: Универсализация обработки boolean полей с conditional logic
     * 
     * @param int $post_id ID поста
     * @param array<string, mixed>|null $field_mapping Маппинг поля (может быть null)
     * @return string|null 'true' если поле должно быть true, null если не установлено
     */
    private function extract_boolean_field_with_conditional_logic(int $post_id, ?array $field_mapping): ?string {
        if (empty($field_mapping)) {
            return null;
        }
        
        // Получаем значение через unified mapper
        $value = $this->get_v3_value_from_unified($post_id, $field_mapping);
        
        // v4.18.21: Для conditional logic проверяем, что значение не пустое (условие выполнилось)
        // unified mapper обрабатывает conditional logic и возвращает значение только если условие выполнилось
        if (!empty($field_mapping['conditional_logic'])) {
            // Если conditional logic включен и значение не пустое, значит условие выполнилось → 'true'
            if ($value !== null && $value !== '' && $value !== false) {
                return 'true';
            }
            // Если conditional logic включен, но значение пустое → условие не выполнилось, не устанавливаем поле
            return null;
        }
        
        // Для обычных полей (без conditional logic) используем стандартную проверку truthy
        if ($this->is_truthy_mapping_value($value)) {
            return 'true';
        }
        
        return null;
    }

    private function is_truthy_mapping_value($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((float) $value) !== 0.0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === '' || in_array($normalized, array('0', 'false', 'no', 'off'), true)) {
                return false;
            }

            return true;
        }

        if (is_array($value)) {
            return !empty($value);
        }

        return !empty($value);
    }

    /**
     * Get V3 value from unified mapper
     * 
     * v4.18.20: Изменена видимость на protected для использования как callback в XmlSerializationService
     * 
     * @param int $post_id Post ID
     * @param array<string, mixed> $field_config Field mapping config
     * @return mixed Field value
     */
    protected function get_v3_value_from_unified(int $post_id, array $field_config) {
        if (!class_exists('YFGP_Field_Mapper_Unified')) {
            require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
        }

        $mapper = YFGP_Field_Mapper_Unified::get_instance();
        $value = $mapper->getFieldValue($post_id, $field_config, true); // skip_cache for feed generation accuracy

        if (is_array($value)) {
            $flattened = $this->flatten_v3_array_value($value, $field_config, $post_id);
            $flattened = array_filter(array_map(function ($item) {
                if (is_string($item)) {
                    return trim($item);
                }
                return is_scalar($item) ? trim((string) $item) : '';
            }, $flattened), function ($item) {
                return $item !== '';
            });

            return !empty($flattened) ? implode(', ', $flattened) : '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return trim($value);
        }

        return $value;
    }

    private function flatten_v3_array_value(array $value, array $field_config, int $post_id): array {
        $result = array();

        if (!$this->v3_is_assoc($value)) {
            foreach ($value as $item) {
                if (is_array($item)) {
                    if (isset($item['name'])) {
                        $result[] = (string) $item['name'];
                        continue;
                    }
                    if (isset($item['label'])) {
                        $result[] = (string) $item['label'];
                        continue;
                    }
                    $result = array_merge($result, $this->flatten_v3_array_value($item, $field_config, $post_id));
                    continue;
                }

                if ($item !== null && $item !== '') {
                    $result[] = (string) $item;
                }
            }

            return $result;
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                if (isset($item['name'])) {
                    $result[] = (string) $item['name'];
                    continue;
                }
                if (isset($item['label'])) {
                    $result[] = (string) $item['label'];
                    continue;
                }
                $result = array_merge($result, $this->flatten_v3_array_value($item, $field_config, $post_id));
                continue;
            }

            if ($this->v3_is_truthy($item)) {
                $label = $this->resolve_v3_option_label($field_config, (string) $key, $post_id);
                $result[] = $label ?? (string) $key;
            } elseif (is_numeric($key) && $item !== null && $item !== '') {
                $result[] = (string) $item;
            }
        }

        return $result;
    }

    private function resolve_v3_option_label(array $field_config, string $option_key, int $post_id): ?string {
        $field_name = $field_config['source_field'] ?? '';
        if ($field_name === '') {
            return null;
        }

        if (function_exists('acf_get_field_object')) {
            $field_object = acf_get_field_object($field_name, $post_id);
            if ($field_object && !empty($field_object['choices']) && isset($field_object['choices'][$option_key])) {
                return (string) $field_object['choices'][$option_key];
            }
        }

        if (!class_exists('YFGP_Field_Mapper_Unified')) {
            require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
        }

        $post_type = $field_config['source_cpt'] ?? get_post_type($post_id) ?? 'doctors';
        $mapper = YFGP_Field_Mapper_Unified::get_instance();
        
        // Try to get labels via Unified Mapper
        if (method_exists($mapper, 'getJetengineFieldOptions')) {
            $labels = $mapper->getJetengineFieldOptions($field_name, $post_type);
            if (isset($labels[$option_key])) {
                return (string) $labels[$option_key];
            }
        }
        
        // If not found, try to find transient by partial match (for JetEngine fields with long names)
        if (empty($labels)) {
            global $wpdb;
            $transient_prefix = '_transient_yfgp_field_options_' . $post_type . '_';
            $transient_like = $transient_prefix . '%' . $field_name . '%';
            $transient_name = $wpdb->get_var($wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 1",
                $transient_like
            ));
            if ($transient_name) {
                $transient_key = str_replace('_transient_', '', $transient_name);
                $cached_labels = get_transient($transient_key);
                if ($cached_labels !== false && is_array($cached_labels) && isset($cached_labels[$option_key])) {
                    return (string) $cached_labels[$option_key];
                }
            }
        }
        
        // Fallback: try specialities-reference
        if (method_exists($mapper, 'getSpecialityLabelFromSlug')) {
            $label = $mapper->getSpecialityLabelFromSlug($option_key);
            if ($label !== null && $label !== '') {
                return $label;
            }
        }

        return null;
    }

    private function v3_is_assoc(array $array): bool {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function v3_is_truthy($value): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return !in_array($normalized, array('', '0', 'false', 'off', 'no'), true);
        }

        return !empty($value);
    }

    /**
     * Получает ручную настройку базовой услуги для специализации врача
     * 
     * @param int $doctor_id ID врача
     * @param string $specialization_slug Слаг специализации
     * @param array $mapping Настройки маппинга
     * @param array $global_services Глобальный список услуг
     * @return array|null Данные услуги или null
     * @since 4.20.1
     */
    public function get_manual_base_service_for_specialization($doctor_id, $specialization_slug, $mapping, $global_services) {
        // Получаем ручные настройки
        $manual_settings = get_option('yfgp_doctor_specialization_services_map', array());
        
        if (empty($manual_settings)) {
            return null;
        }
        
        // Ключ врача в формате "doctor_{id}" или просто "{id}"
        $doctor_key = 'doctor_' . $doctor_id;
        $doctor_key_simple = (string) $doctor_id;
        
        $doctor_settings = $manual_settings[$doctor_key] ?? $manual_settings[$doctor_key_simple] ?? null;
        
        if (empty($doctor_settings)) {
            return null;
        }
        
        // Нормализуем слаг специализации (URL-decode если нужно)
        $normalized_slug = urldecode($specialization_slug);
        $normalized_slug_lower = mb_strtolower($normalized_slug, 'UTF-8');
        
        // Ищем настройку для специализации
        $service_value = null;
        foreach ($doctor_settings as $spec_slug => $service_id) {
            $spec_decoded = urldecode($spec_slug);
            $spec_lower = mb_strtolower($spec_decoded, 'UTF-8');
            
            if ($spec_lower === $normalized_slug_lower || $spec_decoded === $normalized_slug || $spec_slug === $specialization_slug) {
                $service_value = $service_id;
                break;
            }
        }
        
        if (empty($service_value)) {
            return null;
        }
        
        // Нормализуем ID услуги (убираем префикс service_ если есть)
        $service_id = $service_value;
        if (strpos($service_id, 'service_') === 0) {
            $service_id = substr($service_id, 8); // Убираем "service_"
        }
        
        // Ищем услугу в глобальном списке
        foreach ($global_services as $service) {
            $global_id = $service['id'] ?? '';
            // Нормализуем глобальный ID
            if (strpos($global_id, 'service_') === 0) {
                $global_id_normalized = substr($global_id, 8);
            } else {
                $global_id_normalized = $global_id;
            }
            
            if ($global_id_normalized === $service_id || $global_id === $service_id || $global_id === $service_value) {
                return $service;
            }
        }
        
        // Если услуга не найдена в глобальном списке, пробуем получить из БД
        $service_post = get_post((int) $service_id);
        if ($service_post && $service_post->post_status === 'publish') {
            return array(
                'id' => $service_id,
                'name' => $service_post->post_title,
                'internal_id' => $service_id,
                'description' => $service_post->post_title,
                'gov_id' => $service_id,
            );
        }
        
        return null;
    }
}

