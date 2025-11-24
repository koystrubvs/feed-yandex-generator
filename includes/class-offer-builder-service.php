<?php
/**
 * Yandex Feed Generator Pro - Offer Builder Service
 * 
 * Сервисный слой для построения offers из сущностей (doctors, clinics, services)
 * 
 * @package YandexFeedGeneratorPro
 * @since 4.18.17
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once YFGP_PLUGIN_DIR . 'includes/class-offer-builder.php';

class YFGP_Offer_Builder_Service {

    /**
     * @var YFGP_Offer_Builder Экземпляр OfferBuilder
     */
    private YFGP_Offer_Builder $offer_builder;

    /**
     * Конструктор
     * 
     * @param YFGP_Offer_Builder $offer_builder Экземпляр OfferBuilder
     */
    public function __construct(YFGP_Offer_Builder $offer_builder) {
        $this->offer_builder = $offer_builder;
    }

    /**
     * Построение offers из сущностей
     * 
     * @param \WP_Post $post Пост врача
     * @param array<string, mixed> $data Данные поста
     * @param array<string, array<string, mixed>> $clinics Массив клиник
     * @param array<string, array<string, mixed>> $services Массив услуг
     * @param array<int, array<string, mixed>> $offers Массив offers (по ссылке)
     * @param string $doctor_id ID врача
     * @param array<string, array<string, mixed>> $global_services Глобальный массив услуг (по ссылке)
     * @return void
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
        $this->offer_builder->build_offers($post, $data, $clinics, $services, $offers, $doctor_id, $global_services);
    }
}

