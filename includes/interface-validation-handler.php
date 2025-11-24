<?php
/**
 * Yandex Feed Generator Pro - Validation Handler Interface
 * 
 * Интерфейс для валидаторов в цепочке обязанностей
 * 
 * @package YandexFeedGeneratorPro
 * @since 4.18.17
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once YFGP_PLUGIN_DIR . 'includes/class-validation-result.php';

interface YFGP_Validation_Handler_Interface {

    /**
     * Обработать данные валидации
     * 
     * @param array<string, mixed> $data Данные для валидации
     * @return YFGP_Validation_Result Результат валидации
     */
    public function handle(array $data): YFGP_Validation_Result;

    /**
     * Установить следующий обработчик в цепочке
     * 
     * @param YFGP_Validation_Handler_Interface|null $next Следующий обработчик
     * @return void
     */
    public function setNext(?YFGP_Validation_Handler_Interface $next): void;
}

