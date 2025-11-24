<?php
/**
 * Yandex Feed Generator Pro - Service Factories
 * 
 * Фабрики для создания сервисов плагина
 * 
 * @package YandexFeedGeneratorPro
 * @since 4.18.17
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once YFGP_PLUGIN_DIR . 'includes/class-service-container.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-v2.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-feed-xml-writer.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-entity-collector.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-offer-builder.php';
// v4.18.17: Загружаем validation classes ДО feed-orchestrator, так как он их использует
// Сначала загружаем интерфейс, потом классы, которые его реализуют
require_once YFGP_PLUGIN_DIR . 'includes/interface-validation-handler.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-validation-result.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-validation-exception.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-mapping-validator.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-entity-validator.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-xml-structure-validator.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-feed-validation-service.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-feed-orchestrator.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-entity-collector-service.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-offer-builder-service.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-xml-serialization-service.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-field-extraction-service.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-conditional-logic-processor.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-field-normalization-service.php';
require_once YFGP_PLUGIN_DIR . 'includes/class-cache-manager.php';

/**
 * Регистрация всех сервисов в контейнере
 * 
 * @param YFGP_Service_Container $container Контейнер сервисов
 * @return void
 */
function yfgp_register_services(YFGP_Service_Container $container): void {
    // Получаем настройки
    $settings = get_option('yfgp_settings', array());
    if (empty($settings) || !is_array($settings)) {
        $settings = array();
    }

    // 1. Cache Manager (базовый сервис для кэширования)
    $container->register('cache_manager', function() {
        return YFGP_Cache_Manager::get_instance();
    });

    // 2. Unified Field Mapper (базовый сервис)
    $container->register('field_mapper_unified', function() {
        return YFGP_Field_Mapper_Unified::get_instance();
    });

    // 3. Field Mapper V2 (фасад над unified)
    $container->register('field_mapper_v2', function() use ($container) {
        return new YFGP_Field_Mapper_V2();
    });

    // 4. Field Extraction Service
    $container->register('field_extraction_service', function() use ($container) {
        $unified_mapper = $container->get('field_mapper_unified');
        return new YFGP_Field_Extraction_Service($unified_mapper);
    });

    // 5. Conditional Logic Processor
    $container->register('conditional_logic_processor', function() use ($container) {
        $unified_mapper = $container->get('field_mapper_unified');
        return new YFGP_Conditional_Logic_Processor($unified_mapper);
    });

    // 6. Field Normalization Service
    $container->register('field_normalization_service', function() use ($container) {
        $unified_mapper = $container->get('field_mapper_unified');
        return new YFGP_Field_Normalization_Service($unified_mapper);
    });

    // 7. XML Writer
    $container->register('xml_writer', function() use ($settings) {
        return new YFGP_Yml_Stream_Writer($settings);
    });

    // 8. Offer Builder
    $container->register('offer_builder', function() use ($container, $settings) {
        $field_mapper_v2 = $container->get('field_mapper_v2');
        // Callbacks будут переданы из FeedGenerator
        return new YFGP_Offer_Builder(
            $settings,
            $field_mapper_v2,
            null, // determine_base_service callback
            null  // get_offer_additional_fields callback
        );
    });

    // 9. Entity Collector
    $container->register('entity_collector', function() use ($container) {
        $field_mapper_v2 = $container->get('field_mapper_v2');
        $offer_builder = $container->get('offer_builder');
        // Callbacks будут переданы из FeedGenerator
        return new YFGP_Entity_Collector(
            $field_mapper_v2,
            $offer_builder,
            null, // get_entity_type_for_post callback
            null, // build_doctor_entity callback
            null, // build_clinic_entity callback
            null, // build_service_entity callback
            null  // extract_post_data_v3 callback
        );
    });

    // 10. Entity Collector Service
    $container->register('entity_collector_service', function() use ($container) {
        $entity_collector = $container->get('entity_collector');
        return new YFGP_Entity_Collector_Service($entity_collector);
    });

    // 11. Offer Builder Service
    $container->register('offer_builder_service', function() use ($container) {
        $offer_builder = $container->get('offer_builder');
        return new YFGP_Offer_Builder_Service($offer_builder);
    });

    // 12. XML Serialization Service
    $container->register('xml_serialization_service', function() use ($container, $settings) {
        $xml_writer = $container->get('xml_writer');
        // v4.18.20: Передаём settings для поэтапного переноса build_doctor_entity
        // Callbacks будут установлены через setCallbacks() из FeedGenerator
        return new YFGP_Xml_Serialization_Service(
            null, // build_doctor_entity callback (legacy, для обратной совместимости)
            null, // build_clinic_entity callback
            null, // build_service_entity callback
            $xml_writer,
            $settings // Настройки плагина
        );
    });

    // 13. Feed Validation Service
    $container->register('feed_validation_service', function() {
        // class-feed-validation-service.php уже загружен выше
        return new YFGP_Feed_Validation_Service();
    });

    // 14. Feed Orchestrator
    $container->register('feed_orchestrator', function() use ($container, $settings) {
        $entity_collector = $container->get('entity_collector');
        $xml_writer = $container->get('xml_writer');
        return new YFGP_Feed_Orchestrator($settings, $entity_collector, $xml_writer);
    });
}

