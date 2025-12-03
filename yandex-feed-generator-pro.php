<?php

/**

 * Plugin Name: Yandex Feed Generator Pro

 * Plugin URI: https://vityaz-sevastopol.ru

 * Description: Универсальный генератор YML фидов для Яндекс.Вебмастера с поддержкой ACF и JetEngine

 * Version: 4.20.1

 * Author: Vityaz Development Team

 * Author URI: https://vityaz-sevastopol.ru

 * License: GPL v2 or later

 * Text Domain: yandex-feed-generator

 * Domain Path: /languages

 */



// Защита от прямого доступа

if (!defined('ABSPATH')) {

    exit;

}



// Константы плагина

define('YFGP_VERSION', '4.20.1'); // v4.20.1: Исправлена проверка исключений для ручных настроек услуг

if (!defined('YFGP_PLUGIN_DIR')) {

    // v4.18.17: Проверяем, что функция plugin_dir_path доступна перед вызовом

    if (function_exists('plugin_dir_path')) {

define('YFGP_PLUGIN_DIR', plugin_dir_path(__FILE__));

    } else {

        // Fallback: вычисляем путь вручную

        define('YFGP_PLUGIN_DIR', dirname(__FILE__) . '/');

    }

}

if (!defined('YFGP_PLUGIN_URL')) {

    if (function_exists('plugin_dir_url')) {

define('YFGP_PLUGIN_URL', plugin_dir_url(__FILE__));

    } else {

        // Fallback: вычисляем URL вручную

        $plugin_dir = dirname(__FILE__);

        $wp_content_dir = dirname(dirname($plugin_dir));

        $wp_dir = dirname($wp_content_dir);

        $plugin_url = str_replace($wp_dir, '', $plugin_dir);

        define('YFGP_PLUGIN_URL', $plugin_url . '/');

    }

}

if (!defined('YFGP_PLUGIN_BASENAME')) {

    if (function_exists('plugin_basename')) {

define('YFGP_PLUGIN_BASENAME', plugin_basename(__FILE__));

    } else {

        // Fallback: вычисляем basename вручную

        define('YFGP_PLUGIN_BASENAME', basename(dirname(__FILE__)) . '/' . basename(__FILE__));

    }

}



/**

 * Главный класс плагина

 */

class Yandex_Feed_Generator_Pro {

    

    /**

     * Единственный экземпляр класса

     */

    private static $instance = null;

    

    /**

     * Получить экземпляр класса

     */

    public static function get_instance() {

        if (null === self::$instance) {

            self::$instance = new self();

        }

        return self::$instance;

    }

    

    /**

     * Конструктор

     */

    private function __construct() {

        $this->load_dependencies();

        $this->init_hooks();

    }

    

    /**

     * Загрузка зависимостей

     */

    private function load_dependencies() {

        // v4.18.22: Constants Class (замена магических чисел/строк)
        require_once YFGP_PLUGIN_DIR . 'includes/class-constants.php';

        // v4.18.22: Logger Class (централизованное логирование с агрегацией)
        require_once YFGP_PLUGIN_DIR . 'includes/class-logger.php';

        // v4.18.22: Mapping Config Validator (валидация конфигурации маппинга, SQL injection protection)
        require_once YFGP_PLUGIN_DIR . 'includes/class-mapping-config-validator.php';

        // v4.18.22: Encoding Normalizer (нормализация UTF-8 для кириллицы)
        require_once YFGP_PLUGIN_DIR . 'includes/helpers/encoding-normalizer.php';
        
        // v4.20.0: JetEngine Helper (абстракция над JetEngine API)
        require_once YFGP_PLUGIN_DIR . 'includes/helpers/class-jetengine-helper.php';
        
        // v4.20.0: ACF Compatibility Helper (поддержка ACF 6.2.6+ escape_html)
        require_once YFGP_PLUGIN_DIR . 'includes/helpers/acf-compat.php';
        
        // v4.20.0: Relationship Prefetcher (batch загрузка relationships для решения N+1 query problem)
        require_once YFGP_PLUGIN_DIR . 'includes/class-relationship-prefetcher.php';
        
        // v4.20.0: Cache Warmer (предзагрузка кэшей для улучшения производительности первой генерации)
        require_once YFGP_PLUGIN_DIR . 'includes/class-cache-warmer.php';

        // v4.18.22: Data Sanitizer (санитизация данных перед выводом в XML/UI)
        require_once YFGP_PLUGIN_DIR . 'includes/class-data-sanitizer.php';

        // v4.18.22: Post Batch Loader (пакетная загрузка постов для предотвращения OOM)
        require_once YFGP_PLUGIN_DIR . 'includes/class-post-batch-loader.php';

        // Только v2 версии классов

        require_once YFGP_PLUGIN_DIR . 'includes/class-feed-generator-v2.php';

        require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-v2.php';

        require_once YFGP_PLUGIN_DIR . 'includes/specialities-reference.php';

        require_once YFGP_PLUGIN_DIR . 'includes/class-cron-manager.php'; // v2.3.2: Подключение Cron Manager

// DISABLED v4.18.0-hotfix1: Unused class causing headers warnings -         require_once YFGP_PLUGIN_DIR . 'includes/helpers/class-universal-field-loader.php'; // v4.18.0: Universal Field Loader

        require_once YFGP_PLUGIN_DIR . 'includes/class-migration.php'; // v4.18.0: Migration Manager

        require_once YFGP_PLUGIN_DIR . 'includes/class-error-handler.php'; // v4.18.0: Error Handler

        // v4.18.21: Sentry Integration для мониторинга ошибок
        // Загружаем Composer autoload для Sentry SDK (если установлен)
        $vendor_autoload = YFGP_PLUGIN_DIR . 'vendor/autoload.php';
        if (file_exists($vendor_autoload)) {
            try {
                require_once $vendor_autoload;
            } catch (\Throwable $e) {
                // Игнорируем ошибки загрузки autoload, чтобы не ломать плагин
                if (function_exists('error_log') && defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('YFGP: Failed to load vendor/autoload.php: ' . $e->getMessage());
                }
            }
        }
        require_once YFGP_PLUGIN_DIR . 'includes/class-sentry-integration.php'; // v4.18.21: Sentry Integration

        require_once YFGP_PLUGIN_DIR . 'includes/class-entity-manager.php'; // v4.18.11: Entity Manager для расширяемости

        // v4.18.17: Service Container и service-factories для DI (рефакторинг)

        require_once YFGP_PLUGIN_DIR . 'includes/class-service-container.php'; // v4.18.17: Service Container для DI

        require_once YFGP_PLUGIN_DIR . 'includes/service-factories.php'; // v4.18.17: Service Factories для регистрации сервисов

        require_once YFGP_PLUGIN_DIR . 'admin/class-admin-page.php';

    }