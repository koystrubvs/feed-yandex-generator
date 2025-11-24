<?php
/**
 * Yandex Feed Generator Pro - Entity Collector Service
 * 
 * Сервисный слой для сбора сущностей (doctors, clinics, services, offers) из постов
 * 
 * @package YandexFeedGeneratorPro
 * @since 4.18.17
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once YFGP_PLUGIN_DIR . 'includes/class-entity-collector.php';

class YFGP_Entity_Collector_Service {

    /**
     * @var YFGP_Entity_Collector Экземпляр EntityCollector
     */
    private YFGP_Entity_Collector $entity_collector;

    /**
     * Конструктор
     * 
     * @param YFGP_Entity_Collector $entity_collector Экземпляр EntityCollector
     */
    public function __construct(YFGP_Entity_Collector $entity_collector) {
        $this->entity_collector = $entity_collector;
    }

    /**
     * Сбор сущностей из поста
     * 
     * @param \WP_Post $post Пост для обработки
     * @param array<string, mixed> $mapping Маппинг полей
     * @param array<int, array<string, mixed>> $doctors Массив doctors (по ссылке)
     * @param array<string, array<string, mixed>> $clinics Массив clinics (по ссылке)
     * @param array<string, array<string, mixed>> $services Массив services (по ссылке)
     * @param array<int, array<string, mixed>> $offers Массив offers (по ссылке)
     * @return void
     */
    public function collect_entities(
        \WP_Post $post,
        array $mapping,
        array &$doctors,
        array &$clinics,
        array &$services,
        array &$offers
    ): void {
        $this->entity_collector->collect_entities($post, $mapping, $doctors, $clinics, $services, $offers);
    }
}

