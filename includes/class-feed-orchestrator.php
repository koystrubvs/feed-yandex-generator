<?php
/**
 * Yandex Feed Generator Pro - Feed Orchestrator
 * 
 * Координирует процесс генерации фида: WP_Query цикл, сбор сущностей, сериализация XML
 * 
 * @package YandexFeedGeneratorPro
 * @since 4.18.17
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// v4.18.17: Feed Validation Service для валидации маппинга, сущностей и XML
if (!class_exists('YFGP_Feed_Validation_Service')) {
    require_once YFGP_PLUGIN_DIR . 'includes/class-feed-validation-service.php';
}
if (!class_exists('YFGP_Validation_Exception')) {
    require_once YFGP_PLUGIN_DIR . 'includes/class-validation-exception.php';
}

class YFGP_Feed_Orchestrator {

    /**
     * @var array<string, mixed> Настройки плагина
     */
    private array $settings;

    /**
     * @var YFGP_Entity_Collector Сервис сбора сущностей
     */
    private YFGP_Entity_Collector $entity_collector;

    /**
     * @var YFGP_Yml_Stream_Writer Сервис сериализации XML
     */
    private YFGP_Yml_Stream_Writer $xml_writer;

    /**
     * @var YFGP_Feed_Validation_Service Сервис валидации фида
     */
    private YFGP_Feed_Validation_Service $validation_service;

    /**
     * Конструктор
     * 
     * @param array<string, mixed> $settings Настройки плагина
     * @param YFGP_Entity_Collector $entity_collector Сервис сбора сущностей
     * @param YFGP_Yml_Stream_Writer $xml_writer Сервис сериализации XML
     */
    public function __construct(
        array $settings,
        YFGP_Entity_Collector $entity_collector,
        YFGP_Yml_Stream_Writer $xml_writer
    ) {
        $this->settings = $settings;
        $this->entity_collector = $entity_collector;
        $this->xml_writer = $xml_writer;
        $this->validation_service = new YFGP_Feed_Validation_Service();
    }
    
    /**
     * Получить EntityCollector (для установки callbacks в OfferBuilder)
     * 
     * @return YFGP_Entity_Collector
     */
    public function getEntityCollector(): YFGP_Entity_Collector {
        return $this->entity_collector;
    }

    /**
     * Оркестрация процесса генерации фида
     * 
     * Координирует: WP_Query цикл → сбор сущностей → сериализация XML
     * 
     * @param string $post_type Тип постов для генерации (default: 'doctors')
     * @return string XML строка фида
     * @throws Exception Если нет постов для генерации
     */
    public function orchestrate(string $post_type = 'doctors'): string {
        $mapping = get_option('yfgp_field_mapping_v3', array());
        // v4.18.18: Fix PHP 8+ count() error - get_option can return false, ensure array
        if (!is_array($mapping)) {
            $mapping = array();
        }
        error_log('YFGP v4.18.17: Orchestrator - Loading mapping from v3 - Fields: ' . count($mapping));

        // v4.18.17: Валидация маппинга ПЕРЕД сбором сущностей (fail fast)
        try {
            $this->validation_service->validateMapping($mapping);
        } catch (YFGP_Validation_Exception $e) {
            error_log('YFGP Validation Error (mapping): ' . $e->getMessage());
            // v4.18.17: Передаём ValidationException дальше с деталями, а не оборачиваем в Exception
            throw $e;
        }

        // v4.5.1: Clear UI log before generation (prevent accumulation!)
        delete_transient('yfgp_feed_log');

        $batch_size = (int) ($this->settings['generation_batch_size'] ?? 50);
        if ($batch_size <= 0) {
            $batch_size = 50;
        }

        $query_args = array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => $batch_size,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'paged' => 1,
            'cache_results' => false,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
            'no_found_rows' => false,
        );

        $doctors = array();
        $clinics = array();
        $services = array();
        $offers = array();
        $total_processed = 0;
        $max_pages = 1;
        $has_posts = false;

        do {
            $query = new \WP_Query($query_args);
            $max_pages = max($max_pages, (int) $query->max_num_pages);

            if ($query->have_posts()) {
                $has_posts = true;

                while ($query->have_posts()) {
                    $query->the_post();
                    $post = get_post(get_the_ID());
                    if (!($post instanceof \WP_Post)) {
                        continue;
                    }

                    // v4.18.0: Используем EntityCollector для сбора сущностей
                    $this->entity_collector->collect_entities($post, $mapping, $doctors, $clinics, $services, $offers);
                    $total_processed++;
                }
                wp_reset_postdata();
            } else {
                wp_reset_postdata();
                break;
            }

            $query_args['paged']++;
        } while ($query_args['paged'] <= $max_pages);

        if (!$has_posts || $total_processed === 0) {
            throw new Exception('Нет постов типа "' . $post_type . '" для генерации фида');
        }

        // v4.18.17: Валидация собранных сущностей ПОСЛЕ сбора (fail fast)
        try {
            $this->validation_service->validateEntities($doctors, $clinics, $services);
        } catch (YFGP_Validation_Exception $e) {
            error_log('YFGP Validation Error (entities): ' . $e->getMessage());
            
            // v4.18.17: Fallback - возврат последней рабочей версии
            $last_generated = get_transient('yfgp_feed_last_generated');
            if ($last_generated !== false && !empty($last_generated)) {
                error_log('YFGP Fallback: Returning last working feed version (entity validation failed)');
                return $last_generated;
            }
            
            // v4.18.17: Передаём ValidationException дальше с деталями
            throw $e;
        }

        // Сериализация XML через xml_writer
        $xml = $this->xml_writer->build($doctors, $clinics, $services, $offers);

        // v4.18.17: Валидация сгенерированного XML ПОСЛЕ генерации (fail fast)
        try {
            $this->validation_service->validateXml($xml);
        } catch (YFGP_Validation_Exception $e) {
            error_log('YFGP Validation Error (xml): ' . $e->getMessage());
            
            // v4.18.17: Fallback - возврат последней рабочей версии
            $last_generated = get_transient('yfgp_feed_last_generated');
            if ($last_generated !== false && !empty($last_generated)) {
                error_log('YFGP Fallback: Returning last working feed version');
                return $last_generated;
            }
            
            // v4.18.17: Передаём ValidationException дальше с деталями
            throw $e;
        }

        // v4.18.17: Сохранение рабочей версии для fallback
        set_transient('yfgp_feed_last_generated', $xml, 86400); // 24 часа

        return $xml;
    }
}

