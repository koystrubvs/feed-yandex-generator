<?php

/**

 * Plugin Name: Yandex Feed Generator Pro

 * Plugin URI: https://vityaz-sevastopol.ru

 * Description: Универсальный генератор YML фидов для Яндекс.Вебмастера с поддержкой ACF и JetEngine

 * Version: 4.18.21

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

define('YFGP_VERSION', '4.18.21'); // v4.18.21: Sentry Integration - интеграция с Sentry SDK для мониторинга ошибок

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

    

    /**

     * Инициализация хуков

     */

    private function init_hooks() {

        // v4.18.21: Инициализация Sentry после полной загрузки WordPress
        // Используем 'init' вместо 'plugins_loaded' для большей безопасности
        add_action('init', array($this, 'init_sentry'), 20);

        // v4.18.0: Выполнение миграции CPT настроек при инициализации

        add_action('init', array($this, 'run_migration'), 5);

        

        // Активация/деактивация

        register_activation_hook(__FILE__, array($this, 'activate'));

        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        

        // Админ-панель

        if (is_admin() && class_exists('YFGP_Admin_Page')) {

            try {

                new YFGP_Admin_Page();

            } catch (Exception $e) {

                error_log('YFGP Error: ' . $e->getMessage());

            }

        }

        

        // AJAX хуки

        add_action('wp_ajax_yfgp_generate_feed', array($this, 'ajax_generate_feed'));

        add_action('wp_ajax_yfgp_get_fields', array($this, 'ajax_get_fields'));

        add_action('wp_ajax_yfgp_save_mapping', array($this, 'ajax_save_mapping'));

        add_action('wp_ajax_yfgp_get_mapping', array($this, 'ajax_get_mapping'));

        add_action('wp_ajax_yfgp_get_posts_list', array($this, 'ajax_get_posts_list')); // v4.11.0: Test Preview post selector

        add_action('wp_ajax_yfgp_test_mapping', array($this, 'ajax_test_mapping'));

        add_action('wp_ajax_yfgp_save_edited_xml', array($this, 'ajax_save_edited_xml'));

        add_action('wp_ajax_yfgp_export_config', array($this, 'ajax_export_config'));

        add_action('wp_ajax_yfgp_import_config', array($this, 'ajax_import_config'));

        add_action('wp_ajax_yfgp_apply_template', array($this, 'ajax_apply_template'));

        add_action('wp_ajax_yfgp_get_terms', array($this, 'ajax_get_terms'));

        add_action('wp_ajax_yfgp_validate_feed', array($this, 'ajax_validate_feed')); // v4.18.0: Валидация фида

        

        // Cron хуки

        add_action('yfgp_auto_update_feed', array($this, 'auto_update_feed'));

        

        // Cache invalidation для reviews_total_count (v3.1.1)

        add_action('save_post_reviews', array($this, 'invalidate_reviews_cache'), 10, 1);

        add_action('delete_post', array($this, 'invalidate_reviews_cache_on_delete'), 10, 1);

    }

    

    /**

     * Активация плагина

     */

    public function activate() {

        // Создание папки для фидов

        $upload_dir = wp_upload_dir();

        $feed_dir = $upload_dir['basedir'] . '/feed';

        

        if (!file_exists($feed_dir)) {

            wp_mkdir_p($feed_dir);

        }

        

        // Настройки по умолчанию

        $default_settings = array(

            'company_name' => get_bloginfo('name'),

            'company_url' => get_site_url(),

            'company_email' => get_bloginfo('admin_email'),

            'feed_category' => 'doctors',

            'auto_update' => false,

            'update_interval' => 'daily',

        );

        

        add_option('yfgp_settings', $default_settings);

        add_option('yfgp_field_mapping', array());

        add_option('yfgp_feed_history', array());

        

        // Планирование автообновления

        if (!wp_next_scheduled('yfgp_auto_update_feed')) {

            wp_schedule_event(time(), 'daily', 'yfgp_auto_update_feed');

        }

    }

    

    /**

     * Деактивация плагина

     */

    public function deactivate() {

        // Удаление запланированных задач

        wp_clear_scheduled_hook('yfgp_auto_update_feed');

    }

    

    /**

     * Выполнение миграции CPT настроек

     * 

     * @since 4.18.0

     * @return void

     */

    /**
     * Инициализация Sentry Integration
     * 
     * @since 4.18.21
     * @return void
     */
    public function init_sentry(): void {
        // v4.18.21: Безопасная инициализация Sentry с обработкой ошибок
        try {
            if (class_exists('YFGP_Sentry_Integration')) {
                $sentry = YFGP_Sentry_Integration::get_instance();
                $sentry->init();
            }
        } catch (\Throwable $e) {
            // Игнорируем ошибки инициализации Sentry, чтобы не ломать плагин
            if (function_exists('error_log') && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('YFGP: Failed to initialize Sentry: ' . $e->getMessage());
            }
        }
    }

    public function run_migration(): void {

        $error_handler = YFGP_Error_Handler::get_instance();

        

        $error_handler->execute_safely(function() {

            $migration = YFGP_Migration::get_instance();

            

            // v4.18.8: Миграция CPT settings

            $migrated = $migration->migrate_cpt_settings_option();

            if ($migrated) {

                error_log('[YFGP] Migration: CPT settings migrated successfully');

            }

            

            // v4.18.8: Миграция V2 → V3 mapping (если нужно)

            if ($migration->needs_migration()) {

                $result = $migration->migrate_v2_to_v3();

                if ($result['success']) {

                    error_log('[YFGP] Migration: V2 → V3 mapping migrated successfully');

                } else {

                    // Регистрируем критичную ошибку для отображения в admin_notices

                    $error_handler->register_critical_error(

                        'Ошибка миграции V2 → V3: ' . ($result['message'] ?? 'Неизвестная ошибка'),

                        'error',

                        array('action' => 'migrate_v2_to_v3', 'details' => $result)

                    );

                }

            }

        }, array(

            'action' => 'run_migration',

            'hook' => 'init'

        ));

    }

    

    public function save_feed_file(string $yml, string $filename = 'doctors.yml'): array {

        $upload_dir = wp_upload_dir();

        if (empty($upload_dir['basedir']) || empty($upload_dir['baseurl'])) {

            throw new Exception('Не удалось определить директорию загрузок WordPress');

        }



        $feed_dir = trailingslashit($upload_dir['basedir']) . 'feed';

        if (!file_exists($feed_dir) && !wp_mkdir_p($feed_dir)) {

            throw new Exception('Не удалось создать директорию фида: ' . $feed_dir);

        }



        if (!function_exists('wp_tempnam')) {

            require_once ABSPATH . 'wp-admin/includes/file.php';

        }



        $feed_path = trailingslashit($feed_dir) . ltrim($filename, '/');

        $temp_file = wp_tempnam($filename, $feed_dir);



        if (!$temp_file) {

            throw new Exception('Не удалось создать временный файл для фида');

        }



        $bytes_written = file_put_contents($temp_file, $yml);

        if ($bytes_written === false) {

            @unlink($temp_file);

            $last_error = error_get_last();

            if (!empty($last_error['message'])) {

                error_log('YFGP ERROR: file_put_contents temp failed: ' . $last_error['message']);

            }

            throw new Exception('Не удалось сохранить файл фида: ' . $temp_file);

        }



        if (!@rename($temp_file, $feed_path)) {

            $copied = @copy($temp_file, $feed_path);

            @unlink($temp_file);

            if (!$copied) {

                throw new Exception('Не удалось переместить временный файл фида в целевую директорию');

            }

        }



        if (function_exists('chmod')) {

            @chmod($feed_path, 0644);

        }



        clearstatcache(true, $feed_path);



        $feed_mtime = file_exists($feed_path) ? filemtime($feed_path) : time();

        $generated_at = current_time('mysql');



        $feed_url = trailingslashit($upload_dir['baseurl']) . 'feed/' . ltrim($filename, '/');



        update_option('yfgp_feed_last_generated', array(

            'generated_at' => $generated_at,

            'mtime'        => $feed_mtime,

            'path'         => $feed_path,

            'bytes'        => $bytes_written,

        ));



        return array(

            'path'         => $feed_path,

            'url'          => $feed_url,

            'bytes'        => $bytes_written,

            'mtime'        => $feed_mtime,

            'generated_at' => $generated_at,

        );

    }



    /**

     * AJAX: генерация фида

     */

    public function ajax_generate_feed() {

        $error_handler = YFGP_Error_Handler::get_instance();

        

        try {

        // v4.18.13: Единый стандарт nonce для всех AJAX handlers

        check_ajax_referer('yfgp_ajax_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

                $error_handler->handle_ajax_error('Недостаточно прав', array('action' => 'ajax_generate_feed'));

                return;

        }

        

        $post_type = sanitize_text_field($_POST['post_type'] ?? '');

        $preview_only = isset($_POST['preview_only']) && $_POST['preview_only'] === 'true';

        

            // Выбираем генератор в зависимости от настроек

            $settings = get_option('yfgp_settings', array());

            $feed_format = $settings['feed_format'] ?? 'v2';

            

            if ($feed_format === 'v2') {

                $generator = new YFGP_Feed_Generator_V2();

            } else {

                $generator = new YFGP_Feed_Generator();

            }

            

            $yml = $generator->generate($post_type);

            

            if (!$preview_only) {

                $feed_result = $this->save_feed_file($yml, 'doctors.yml');

                $feed_url = $feed_result['url'] . '?v=' . time();

                

                $error_handler->handle_ajax_success(array(

                    'message'       => 'Feed generated and published',

                    'file_url'      => $feed_url,

                    'yml'           => $yml,

                    'generated_at'  => $feed_result['generated_at'] ?? current_time('mysql'),

                    'mtime'         => $feed_result['mtime'] ?? null,

                    'bytes_written' => $feed_result['bytes'] ?? null,

                ));

            } else {

                $error_handler->handle_ajax_success(array(

                    'yml' => $yml

                ));

            }

        } catch (Exception $e) {

            $error_handler->handle_ajax_error($e, array('action' => 'ajax_generate_feed', 'post_type' => $post_type ?? ''));

        } catch (Throwable $e) {

            $error_handler->handle_ajax_error($e, array('action' => 'ajax_generate_feed', 'post_type' => $post_type ?? ''));

        }

    }

    

    /**

     * AJAX: Получение полей CPT

     */

    public function ajax_get_fields() {

        // v4.18.13: Единый стандарт nonce для всех AJAX handlers

        check_ajax_referer('yfgp_ajax_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            $this->send_json_error_no_bom('Недостаточно прав');

        }

        

        $post_type = sanitize_text_field($_POST['post_type'] ?? '');

        

        // Отладочная информация

        error_log("🔍 YFGP: Запрос полей для типа: {$post_type}");

        

        $mapper = new YFGP_Field_Mapper_V2();

        $fields = $mapper->get_available_fields($post_type);

        

        // Отладочная информация

        $relations_count = isset($fields['relations']) ? count($fields['relations']) : 0;

        error_log("📊 YFGP: Найдено связей для {$post_type}: {$relations_count}");

        

        if (isset($fields['relations'])) {

            foreach ($fields['relations'] as $key => $relation) {

                error_log("🔗 YFGP: Связь {$key}: {$relation['label']} → " . implode(', ', $relation['post_type']));

            }

        }

        

        $this->send_json_success_no_bom($fields);

    }

    

    /**

     * AJAX: Сохранение маппинга

     */

    public function ajax_save_mapping() {

        // v4.18.13: Единый стандарт nonce для всех AJAX handlers

        check_ajax_referer('yfgp_ajax_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            wp_send_json_error('Недостаточно прав');

        }

        

        $mapping = $_POST['mapping'] ?? array();

        

        update_option('yfgp_field_mapping', $mapping);

        

        wp_send_json_success('Маппинг сохранён');

    }

    

    /**

     * AJAX: Получение текущего маппинга

     */

    public function ajax_get_mapping() {

        // v4.18.13: Единый стандарт nonce для всех AJAX handlers

        check_ajax_referer('yfgp_ajax_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            wp_send_json_error('Недостаточно прав');

        }

        

        $mapping = get_option('yfgp_field_mapping', array());

        

        wp_send_json_success($mapping);

    }

    

    /**

     * AJAX: Получить список постов для Test Preview

     * @since v4.11.0

     */

    public function ajax_get_posts_list() {

        // v4.18.13: Единый стандарт nonce для всех AJAX handlers

        check_ajax_referer('yfgp_ajax_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            wp_send_json_error('Недостаточно прав');

        }

        

        $tab_type = sanitize_text_field($_POST['tab_type'] ?? 'doctors');
        $settings = get_option('yfgp_settings', array());
        
        // v4.18.21: Используем универсальную функцию для определения post_type
        $post_type = $this->get_post_type_for_tab($tab_type, $settings);

        

        // Получаем посты

        $posts = get_posts(array(

            'post_type' => $post_type,

            'post_status' => 'publish',

            'posts_per_page' => 100,

            'orderby' => 'title',

            'order' => 'ASC'

        ));

        

        $posts_list = array();

        foreach ($posts as $post) {

            $posts_list[] = array(

                'id' => $post->ID,

                'title' => $post->post_title

            );

        }

        

        wp_send_json_success(array(

            'posts' => $posts_list,

            'post_type' => $post_type,

            'tab_type' => $tab_type

        ));

    }

    /**
     * Определить post_type для таба (универсальный метод)
     * 
     * @param string $tab_type Тип таба (doctors/clinics/services/offers)
     * @param array $settings Настройки плагина
     * @return string Post type
     */
    private function get_post_type_for_tab($tab_type, $settings) {
        switch ($tab_type) {
            case 'doctors':
                return $settings['post_type'] ?? 'doctors';
            
            case 'clinics':
                return $settings['cpt_clinics'] ?? 'clinics';
            
            case 'services':
                return $settings['cpt_services'] ?? 'services';
            
            case 'offers':
                // Offers основаны на врачах
                return $settings['post_type'] ?? 'doctors';
            
            default:
                return 'doctors';
        }
    }

    

    /**

     * AJAX: Тестирование маппинга

     */

    public function ajax_test_mapping() {

        error_log("🔵 YFGP: ajax_test_mapping() ENTRY");

        

        // v3.4.9: Увеличиваем время выполнения для сложных маппингов

        set_time_limit(60);

        ini_set('max_execution_time', 60);

        error_log("🔵 YFGP: Time limits set");

        

        // v4.18.13: Единый стандарт nonce для всех AJAX handlers

        check_ajax_referer('yfgp_ajax_nonce', 'nonce');

        error_log("🔵 YFGP: Nonce verified");

        

        if (!current_user_can('manage_options')) {

            error_log("❌ YFGP: Permission denied");

            wp_send_json_error('Недостаточно прав');

        }

        error_log("🔵 YFGP: Permissions OK");

        

        error_log("🚀 YFGP: ajax_test_mapping STARTED");

        

        try {

            error_log("🔵 YFGP: Entering try block");

            $settings = get_option('yfgp_settings', array());

            error_log("🔵 YFGP: Settings loaded");

            // v4.18.21: Получаем tab_type ПЕРЕД определением post_type для правильного определения типа поста
            $tab_type = sanitize_text_field($_POST['tab_type'] ?? 'doctors');
            $post_type = $this->get_post_type_for_tab($tab_type, $settings);

            error_log("🔵 YFGP: Tab type = {$tab_type}, Post type = {$post_type}");

            

            // v4.18.21: Получаем переданный post_id (ОБЯЗАТЕЛЬНО для корректного превью!)
            // Если post_id не передан - это ошибка, т.к. пользователь должен выбрать пост из селекта

            $post_id = intval($_POST['post_id'] ?? 0);

            error_log("🔵 YFGP: Post ID from request = {$post_id}");

            

            if ($post_id > 0) {

                // Используем переданный post_id (выбранный пользователем из селекта)

                $post = get_post($post_id);

                if (!$post) {

                    error_log("❌ YFGP: Post {$post_id} not found");

                    wp_send_json_error('Пост не найден');

                }

                if ($post->post_type !== $post_type) {

                    error_log("❌ YFGP: Post {$post_id} type mismatch: expected {$post_type}, got {$post->post_type}");

                    wp_send_json_error('Неправильный тип поста. Ожидается: ' . $post_type . ', получен: ' . $post->post_type);

                }

                error_log("✅ YFGP: Using selected post ID {$post_id} ({$post->post_title})");

            } else {

                // v4.18.21: Если post_id не передан - это ошибка (пользователь должен выбрать пост)
                error_log("❌ YFGP: Post ID not provided in request");

                wp_send_json_error('Пожалуйста, выберите пост из списка для тестирования');

            }

            

            $result = array(

                'posts_found' => count(get_posts(array('post_type' => $post_type, 'post_status' => 'publish', 'posts_per_page' => -1))),

                'clinics_found' => 0,

                'services_found' => 0,

                'sample_post' => null

            );

            

            // v3.4.9: Use Field Mapper V3 for proper handling of v3 mapping format

            if (!class_exists('YFGP_Field_Mapper_V3')) {

                require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-v3.php';

            }

            

            $mapper_v3 = YFGP_Field_Mapper_V3::get_instance();

            $mapping = get_option('yfgp_field_mapping_v3', array());
            // v4.18.18: Fix PHP 8+ count() error - get_option can return false, ensure array
            if (!is_array($mapping)) {
                $mapping = array();
            }

            error_log("🧪 YFGP: START test - Post ID={$post->ID}, title='{$post->post_title}', total_fields=" . count($mapping));

            

            // Собираем данные используя V3 API (по одному полю)

            $data = array();

            $processed = 0;

            $start_time = microtime(true);

            

            foreach ($mapping as $field_id => $field_config) {

                if (empty($field_config['source_type'])) {

                    continue; // Пропускаем ненастроенные поля

                }

                

                try {

                    $field_start = microtime(true);

                    $value = $mapper_v3->get_field_value($post->ID, $field_config);

                    $data[$field_id] = $value;

                    $processed++;

                    

                    $field_time = round((microtime(true) - $field_start) * 1000, 2);

                    if ($field_time > 100) { // Лог только для медленных полей (>100ms)

                        error_log("🧪 YFGP: Field '{$field_id}' took {$field_time}ms");

                    }

                } catch (Exception $e) {

                    error_log("🚨 YFGP: Error processing field '{$field_id}': " . $e->getMessage());

                    $data[$field_id] = null; // Пропускаем проблемные поля

                }

            }

            

            $total_time = round((microtime(true) - $start_time) * 1000, 2);

            error_log("🧪 YFGP: Processed {$processed} fields in {$total_time}ms");

            

            // v4.1.0-beta31: POST-PROCESSING для корректного отображения

            // Apply same logic as build_doctor_entity()

            

            // 1. Calculate experience_years from work_experience

            if (!empty($mapping['experience_years']['calculate_type']) && $mapping['experience_years']['calculate_type'] === 'date_to_years') {

                if (!empty($data['career_start_date']) && strtotime($data['career_start_date']) !== false) {

                    $start_date = strtotime($data['career_start_date']);

                    $years = floor((time() - $start_date) / (365.25 * 24 * 60 * 60));

                    if ($years >= 0) {

                        $data['experience_years'] = $years;

                    }

                }

            }

            

            // 2. Picture fallback - ВСЕГДА использовать featured image если есть

            if (has_post_thumbnail($post->ID)) {

                $data['picture'] = get_the_post_thumbnail_url($post->ID, 'full');

            } elseif (empty($data['picture'])) {

                $data['picture'] = ''; // Fallback на пустую строку

            }

            

            // 3. Strip HTML from description

            if (!empty($data['description'])) {

                $data['description'] = strip_tags($data['description']);

            }

            

            // 4. Extract education repeater

            $education_data = get_post_meta($post->ID, 'obrazovanie_repiter', true);

            if (!empty($education_data) && is_array($education_data)) {

                $data['education'] = array();

                foreach ($education_data as $item) {

                    if (is_array($item)) {

                        $edu = array();

                        if (!empty($item['uchebnoe_zavedenie'])) $edu['name'] = $item['uchebnoe_zavedenie'];

                        if (!empty($item['god_okonchaniia'])) $edu['date_end'] = $item['god_okonchaniia'];

                        if (!empty($item['spetsializatsiia'])) $edu['specialty'] = $item['spetsializatsiia'];

                        if (!empty($edu)) $data['education'][] = $edu;

                    }

                }

            }

            

            // 5. Process children_appointment/adult_appointment from parent_child

            $parent_child = $data['children_appointment'] ?? $data['adult_appointment'] ?? null;

            if (is_array($parent_child)) {

                if (isset($parent_child['child'])) {

                    $data['children_appointment'] = $parent_child['child'] === 'true' ? 'true' : 'false';

                }

                if (isset($parent_child['parent'])) {

                    $data['adult_appointment'] = $parent_child['parent'] === 'true' ? 'true' : 'false';

                }

            }

            

            // v4.4.2: Ensure adult_appointment/children_appointment have defaults (like real feed!)

            if (!isset($data['adult_appointment'])) {

                $data['adult_appointment'] = 'true'; // Default: врач принимает взрослых

            }

            if (!isset($data['children_appointment'])) {

                $data['children_appointment'] = 'false'; // Default: не детский врач

            }

            

            // v4.1.0: Extract job repeater - ПРЯМАЯ проверка БД (не зависит от mapping!)

            // Используем тот же source что и в build_doctor_entity: obrazovanie_repiter

            $job_data = get_post_meta($post->ID, 'obrazovanie_repiter', true);

            if (!empty($job_data) && is_array($job_data)) {

                $data['job'] = array();

                foreach ($job_data as $item) {

                    if (is_array($item)) {

                        $job = array();

                        $fields_array = array_values($item);

                        if (isset($fields_array[0])) $job['organization'] = $fields_array[0];

                        if (isset($fields_array[1])) $job['period_years'] = $fields_array[1];

                        if (isset($fields_array[3])) $job['position'] = $fields_array[3];

                        if (!empty($job)) $data['job'][] = $job;

                    }

                }

            }

            

            // v4.1.0: Extract certificate repeater - ПРЯМАЯ проверка БД (не зависит от mapping!)

            // Используем тот же source что и в build_doctor_entity: obrazovanie_repiter

            $cert_data = get_post_meta($post->ID, 'obrazovanie_repiter', true);

            if (!empty($cert_data) && is_array($cert_data)) {

                $data['certificate'] = array();

                foreach ($cert_data as $item) {

                    if (is_array($item)) {

                        $cert = array();

                        $fields_array = array_values($item);

                        if (isset($fields_array[0])) $cert['organization'] = $fields_array[0];

                        if (isset($fields_array[1])) $cert['finish_year'] = $fields_array[1];

                        if (isset($fields_array[0])) $cert['name'] = $fields_array[0];

                        if (!empty($cert)) $data['certificate'][] = $cert;

                    }

                }

            }

            

            // v4.1.0: Extract reviews relationship (relation 7: reviews → doctors)

            // + strip HTML tags for comment/positive/negative/response

            if (function_exists('jet_engine') && !empty(jet_engine()->relations)) {

                $all_relations = jet_engine()->relations->get_active_relations();

                foreach ($all_relations as $relation) {

                    if ($relation->get_id() == 7) {

                        $related_review_ids = $relation->get_parents($post->ID, 'ids');

                        if (!empty($related_review_ids) && is_array($related_review_ids)) {

                            $data['reviews'] = array();

                            foreach ($related_review_ids as $review_id) {

                                $review_post = get_post($review_id);

                                if ($review_post) {

                                    $review = array();

                                    $review['date'] = get_the_date('Y-m-d', $review_id);

                                    $review['checked'] = 'true';

                                    $review['used_in_rating'] = 'true';

                                    $review['author'] = get_the_title($review_id);

                                    $review['author_id'] = get_post_meta($review_id, 'id-otzyva', true);

                                    $review['author_picture'] = get_post_meta($review_id, 'review_video_photo', true);

                                    $review['url'] = get_permalink($review_id);

                                    $review['comment'] = strip_tags(get_the_content(null, false, $review_id));

                                    $review['grade'] = get_post_meta($review_id, 'estimation', true);

                                    $review['positive'] = $review['comment']; // уже strip_tags

                                    $review['negative'] = strip_tags(get_post_meta($review_id, 'otvet-kliniki', true));

                                    $review['response'] = $review['negative']; // уже strip_tags

                                    $data['reviews'][] = $review;

                                }

                            }

                        }

                        break;

                    }

                }

            }

            

            // v4.2.0: POST-PROCESSING для Clinics - используем РЕАЛЬНУЮ клинику!
            // v4.18.21: tab_type уже получен выше, не дублируем

            if ($tab_type === 'clinics') {

                // v4.18.21: Используем выбранный пост из селекта (уже получен выше как $post)
                // НЕ нужно получать первый пост - используем выбранный пользователем!

                $clinic_post = $post; // Используем выбранный пост из селекта

                // Extract clinic fields using V3 mapper from CLINIC post

                if (!class_exists('YFGP_Field_Mapper_Unified')) {

                    require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';

                }

                $mapper_unified = YFGP_Field_Mapper_Unified::get_instance();

                $clinic_mapping = get_option('yfgp_field_mapping_v3', array());

                // Extract clinic fields via V3 API from CLINIC post

                $clinic_fields = array('clinics_address', 'clinics_phone', 'clinics_email', 'clinics_picture', 'clinics_city', 'clinics_url', 'clinics_id', 'clinics_name', 'clinics_company_id');

                    foreach ($clinic_fields as $field_key) {

                        if (!empty($clinic_mapping[$field_key])) {

                            $value = $mapper_unified->getFieldValue($clinic_post->ID, $clinic_mapping[$field_key]);

                            if (!empty($value)) {

                                // v4.2.2: UNIVERSAL picture conversion (array, string, comma-separated)

                                if ($field_key === 'clinics_picture' && !empty($value)) {

                                    // Если уже URL - оставляем

                                    if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {

                                        $data[$field_key] = $value;

                                    }

                                    // Если массив - берем первый

                                    elseif (is_array($value)) {

                                        $first_id = is_numeric($value[0]) ? intval($value[0]) : null;

                                        $data[$field_key] = $first_id ? wp_get_attachment_url($first_id) : '';

                                    }

                                    // Если строка с запятыми - explode

                                    elseif (is_string($value) && strpos($value, ',') !== false) {

                                        $ids = array_map('trim', explode(',', $value));

                                        $first_id = is_numeric($ids[0]) ? intval($ids[0]) : null;

                                        $data[$field_key] = $first_id ? wp_get_attachment_url($first_id) : '';

                                    }

                                    // Если просто число - конвертим

                                    elseif (is_numeric($value)) {

                                        $data[$field_key] = wp_get_attachment_url(intval($value));

                                    } else {

                                        $data[$field_key] = $value; // Fallback

                                    }

                                } else {

                                    $data[$field_key] = $value;

                                }

                            }

                        }

                }

                // Fallback для базовых полей

                if (empty($data['clinics_id'])) {

                    $data['clinics_id'] = 'clinic_' . $clinic_post->ID;

                }

                if (empty($data['clinics_name'])) {

                    $data['clinics_name'] = $clinic_post->post_title;

                }

                if (empty($data['clinics_url'])) {

                    $data['clinics_url'] = get_permalink($clinic_post->ID);

                }

                // Picture - ALWAYS override with featured image if available

                if (has_post_thumbnail($clinic_post->ID)) {

                    $data['clinics_picture'] = get_the_post_thumbnail_url($clinic_post->ID, 'full');

                }

                // Обновляем post для generate_test_yml_preview

                $post = $clinic_post;

                

                // Array validation for multi-value fields

                if (!empty($data['clinics_phone']) && is_array($data['clinics_phone'])) {

                    $data['clinics_phone'] = implode(', ', $data['clinics_phone']);

                }

                if (!empty($data['clinics_address']) && is_array($data['clinics_address'])) {

                    $data['clinics_address'] = implode(', ', $data['clinics_address']);

                }

                

                // HTML cleanup for text fields

                if (!empty($data['clinics_description'])) {

                    $data['clinics_description'] = strip_tags($data['clinics_description']);

                }

            }

            

            // v4.3.0: POST-PROCESSING для Services (full meta extraction + pattern from clinics v4.2.2)

            if ($tab_type === 'services') {

                // v4.18.21: Используем выбранный пост из селекта (уже получен выше как $post)
                // НЕ нужно получать первый пост - используем выбранный пользователем!

                $service_post = $post; // Используем выбранный пост из селекта

                // Extract service fields via V3 mapper (same pattern as clinics!)

                if (!class_exists('YFGP_Field_Mapper_Unified')) {

                    require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';

                }

                $mapper_unified = YFGP_Field_Mapper_Unified::get_instance();

                $service_mapping = get_option('yfgp_field_mapping_v3', array());

                // Extract service fields via V3 API from SERVICE post

                $service_fields = array('services_description', 'services_gov_id', 'services_picture', 'services_url', 'services_id', 'services_name');

                foreach ($service_fields as $field_key) {

                        if (!empty($service_mapping[$field_key])) {

                            $value = $mapper_unified->getFieldValue($service_post->ID, $service_mapping[$field_key]);

                            if (!empty($value)) {

                                // v4.3.0: Convert picture (same as clinics v4.2.2!)

                                if ($field_key === 'services_picture' && !empty($value)) {

                                    // Если уже URL - оставляем

                                    if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {

                                        $data[$field_key] = $value;

                                    }

                                    // Если массив - берем первый

                                    elseif (is_array($value)) {

                                        $first_id = is_numeric($value[0]) ? intval($value[0]) : null;

                                        $data[$field_key] = $first_id ? wp_get_attachment_url($first_id) : '';

                                    }

                                    // Если строка с запятыми - explode

                                    elseif (is_string($value) && strpos($value, ',') !== false) {

                                        $ids = array_map('trim', explode(',', $value));

                                        $first_id = is_numeric($ids[0]) ? intval($ids[0]) : null;

                                        $data[$field_key] = $first_id ? wp_get_attachment_url($first_id) : '';

                                    }

                                    // Если число - конвертим

                                    elseif (is_numeric($value)) {

                                        $data[$field_key] = wp_get_attachment_url(intval($value));

                                    } else {

                                        $data[$field_key] = $value;

                                    }

                                } else {

                                    $data[$field_key] = $value;

                                }

                            }

                        }

                    }

                // Fallback для базовых полей

                if (empty($data['services_id'])) {

                    $data['services_id'] = 'service_' . $service_post->ID;

                }

                if (empty($data['services_name'])) {

                    $data['services_name'] = $service_post->post_title;

                }

                // Picture - ALWAYS override with featured image if available

                if (has_post_thumbnail($service_post->ID)) {

                    $data['services_picture'] = get_the_post_thumbnail_url($service_post->ID, 'full');

                }

                // HTML cleanup for description

                if (!empty($data['services_description'])) {

                    $data['services_description'] = strip_tags($data['services_description']);

                }

                // Обновляем post для generate_test_yml_preview

                $post = $service_post;

            }

            

            // v4.4.0: POST-PROCESSING для Offers (simplified - show test data)

            if ($tab_type === 'offers') {

                // Create test offers data (mock for preview)

                $data['offers'] = array(

                    array(

                        'id' => 'offer_test_1',

                        'doctor_id' => 'doctor_' . $post->ID,

                        'clinic_id' => 'clinic_13517',

                        'service_id' => 'service_test',

                        'speciality' => 'Стоматолог',

                        'appointment_url' => get_permalink($post->ID),

                        'appointment_available' => 'true',

                        'oms_available' => 'false',

                        'online_schedule' => 'false',

                        'children_appointment' => 'false',

                        'adult_appointment' => 'true',

                        'house_call' => 'false',

                        'telemed' => 'true',

                        'is_base_service' => 'true',

                    ),

                    array(

                        'id' => 'offer_test_2',

                        'doctor_id' => 'doctor_' . $post->ID,

                        'clinic_id' => 'clinic_13525',

                        'service_id' => 'service_test',

                        'speciality' => 'Стоматолог',

                        'appointment_url' => get_permalink($post->ID),

                        'appointment_available' => 'true',

                        'oms_available' => 'false',

                        'online_schedule' => 'false',

                        'children_appointment' => 'false',

                        'adult_appointment' => 'true',

                        'house_call' => 'false',

                        'telemed' => 'true',

                        'is_base_service' => 'true',

                        'price' => 5200, // Example price

                        'currency' => 'RUR'

                    ),

                    array(

                        'id' => 'offer_test_3',

                        'doctor_id' => 'doctor_' . $post->ID,

                        'clinic_id' => 'clinic_13514',

                        'service_id' => 'service_test',

                        'speciality' => 'Стоматолог',

                        'appointment_url' => get_permalink($post->ID),

                        'appointment_available' => 'true',

                        'oms_available' => 'false',

                        'online_schedule' => 'false',

                        'children_appointment' => 'false',

                        'adult_appointment' => 'true',

                        'house_call' => 'false',

                        'telemed' => 'true',

                        'is_base_service' => 'true',

                    ),

                );

            }

            

            // v3.4.9: Generate YML preview for popup modal

            error_log("🧪 YFGP: Generating YML for tab '{$tab_type}'");

            $yml = $this->generate_test_yml_preview($data, $tab_type, $post);

            error_log("🧪 YFGP: YML generated, length=" . strlen($yml));

            

            $result['sample_post'] = array(

                'post_title' => $post->post_title,

                'post_id' => $post->ID,

                'mapped_data' => $data

            );

            

            $result['clinics_found'] = isset($data['clinics']) && is_array($data['clinics']) ? count($data['clinics']) : 0;

            $result['services_found'] = isset($data['services']) && is_array($data['services']) ? count($data['services']) : 0;

            $result['yml'] = $yml; // v3.4.9: Add YML for popup with V3 data

            

            error_log("🧪 YFGP: Результат - клиник: {$result['clinics_found']}, услуг: {$result['services_found']}");

            

            wp_send_json_success($result);

        } catch (Exception $e) {

            error_log("❌ YFGP: Ошибка тестирования: " . $e->getMessage());

            wp_send_json_error($e->getMessage());

        }

    }

    

    /**

     * Generate YML preview for test button (v3.4.8)

     * 

     * @param array $data Mapped post data

     * @param string $tab_type Current tab (doctors/offer/relations/directory)

     * @param WP_Post $post Current post

     * @return string YML preview

     */

    private function generate_test_yml_preview($data, $tab_type, $post) {

        $yml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";

        $yml .= "<test-preview tab=\"{$tab_type}\" post_id=\"{$post->ID}\" post_title=\"" . esc_attr($post->post_title) . "\">\n";

        

        switch ($tab_type) {

            case 'doctors':

                // Doctor fields preview

                $yml .= "  <doctor>\n";

                $yml .= "    <name>" . esc_html($data['name'] ?? $post->post_title) . "</name>\n";

                

                // v4.1.0: Fix ФИО parsing - use fallback logic from feed generator

                $surname = $data['surname'] ?? null;

                $first_name = $data['first_name'] ?? null;

                $patronymic = $data['patronymic'] ?? null;

                

                // If not configured, parse from name

                if (empty($surname) || empty($first_name)) {

                    $full_name = $data['name'] ?? $post->post_title;

                    $parts = explode(' ', trim($full_name));

                    $surname = $surname ?: ($parts[0] ?? 'N/A');

                    $first_name = $first_name ?: ($parts[1] ?? 'N/A');

                    $patronymic = $patronymic ?: ($parts[2] ?? '');

                }

                

                $yml .= "    <surname>" . esc_html($surname) . "</surname>\n";

                $yml .= "    <first_name>" . esc_html($first_name) . "</first_name>\n";

                if (!empty($patronymic)) {

                    $yml .= "    <patronymic>" . esc_html($patronymic) . "</patronymic>\n";

                }

                if (!empty($data['experience_years'])) {

                    $yml .= "    <experience_years>" . esc_html($data['experience_years']) . "</experience_years>\n";

                }

                // v4.1.0-beta16: Added missing mandatory fields

                if (!empty($data['degree'])) {

                    // v4.18.21: Обработка массива для degree (Sentry YANDEX-FEED-GENERATOR-PRO-10)
                    $degree_value = is_array($data['degree']) ? implode(', ', $data['degree']) : $data['degree'];
                    $yml .= "    <degree>" . esc_html($degree_value) . "</degree>\n";

                }

                if (!empty($data['rank'])) {

                    // v4.18.21: Обработка массива для rank (Sentry YANDEX-FEED-GENERATOR-PRO-10)
                    $rank_value = is_array($data['rank']) ? implode(', ', $data['rank']) : $data['rank'];
                    $yml .= "    <rank>" . esc_html($rank_value) . "</rank>\n";

                }

                if (!empty($data['category'])) {

                    // v4.18.21: Обработка массива для category (Sentry YANDEX-FEED-GENERATOR-PRO-10)
                    $category_value = is_array($data['category']) ? implode(', ', $data['category']) : $data['category'];
                    $yml .= "    <category>" . esc_html($category_value) . "</category>\n";

                }

                if (!empty($data['career_start_date'])) {

                    $yml .= "    <career_start_date>" . esc_html($data['career_start_date']) . "</career_start_date>\n";

                }

                if (!empty($data['picture'])) {

                    $yml .= "    <picture>" . esc_html($data['picture']) . "</picture>\n";

                }

                if (!empty($data['reviews_total_count'])) {

                    // v4.18.21: Обработка массива для reviews_total_count (Sentry YANDEX-FEED-GENERATOR-PRO-10)
                    $reviews_count = is_array($data['reviews_total_count']) ? count($data['reviews_total_count']) : $data['reviews_total_count'];
                    $yml .= "    <reviews_total_count>" . esc_html($reviews_count) . "</reviews_total_count>\n";

                }

                if (!empty($data['description'])) {

                    $yml .= "    <description>" . esc_html(mb_substr($data['description'], 0, 200)) . "...</description>\n";

                }

                

                // v4.1.0-beta31: Education repeater

                if (!empty($data['education']) && is_array($data['education'])) {

                    foreach ($data['education'] as $edu) {

                        $yml .= "    <education>\n";

                        if (!empty($edu['name'])) {

                            $yml .= "      <name>" . esc_html($edu['name']) . "</name>\n";

                        }

                        if (!empty($edu['date_end'])) {

                            $yml .= "      <date_end>" . esc_html($edu['date_end']) . "</date_end>\n";

                        }

                        if (!empty($edu['specialty'])) {

                            $yml .= "      <specialty>" . esc_html($edu['specialty']) . "</specialty>\n";

                        }

                        $yml .= "    </education>\n";

                    }

                }

                

                // v4.1.0-beta32: Job repeater

                if (!empty($data['job']) && is_array($data['job'])) {

                    foreach ($data['job'] as $j) {

                        $yml .= "    <job>\n";

                        if (!empty($j['organization'])) {

                            $yml .= "      <organization>" . esc_html($j['organization']) . "</organization>\n";

                        }

                        if (!empty($j['period_years'])) {

                            $yml .= "      <period_years>" . esc_html($j['period_years']) . "</period_years>\n";

                        }

                        if (!empty($j['position'])) {

                            $yml .= "      <position>" . esc_html($j['position']) . "</position>\n";

                        }

                        $yml .= "    </job>\n";

                    }

                }

                

                // v4.1.0-beta32: Certificate repeater

                if (!empty($data['certificate']) && is_array($data['certificate'])) {

                    foreach ($data['certificate'] as $cert) {

                        $yml .= "    <certificate>\n";

                        if (!empty($cert['organization'])) {

                            $yml .= "      <organization>" . esc_html($cert['organization']) . "</organization>\n";

                        }

                        if (!empty($cert['finish_year'])) {

                            $yml .= "      <finish_year>" . esc_html($cert['finish_year']) . "</finish_year>\n";

                        }

                        if (!empty($cert['name'])) {

                            $yml .= "      <name>" . esc_html($cert['name']) . "</name>\n";

                        }

                        $yml .= "    </certificate>\n";

                    }

                }

                

                // v4.1.0-beta32: Reviews relationship

                if (!empty($data['reviews']) && is_array($data['reviews'])) {

                    foreach ($data['reviews'] as $rev) {

                         $yml .= "    <review>\n";

                        if (!empty($rev['date'])) {

                            $yml .= "      <date>" . esc_html($rev['date']) . "</date>\n";

                        }

                        if (!empty($rev['checked'])) {

                            $yml .= "      <checked>" . esc_html($rev['checked']) . "</checked>\n";

                        }

                        if (!empty($rev['used_in_rating'])) {

                            $yml .= "      <used_in_rating>" . esc_html($rev['used_in_rating']) . "</used_in_rating>\n";

                        }

                        if (!empty($rev['author'])) {

                            $yml .= "      <author>" . esc_html($rev['author']) . "</author>\n";

                        }

                        if (!empty($rev['author_id'])) {

                            $yml .= "      <author_id>" . esc_html($rev['author_id']) . "</author_id>\n";

                        }

                        if (!empty($rev['author_picture'])) {

                            $yml .= "      <author_picture>" . esc_html($rev['author_picture']) . "</author_picture>\n";

                        }

                        if (!empty($rev['url'])) {

                            $yml .= "      <url>" . esc_html($rev['url']) . "</url>\n";

                        }

                        if (!empty($rev['comment'])) {

                            $yml .= "      <comment>" . esc_html(mb_substr($rev['comment'], 0, 100)) . "...</comment>\n";

                        }

                        if (!empty($rev['grade'])) {

                            $yml .= "      <grade>" . esc_html($rev['grade']) . "</grade>\n";

                        }

                        if (!empty($rev['positive'])) {

                            $yml .= "      <positive>" . esc_html(mb_substr($rev['positive'], 0, 100)) . "...</positive>\n";

                        }

                        if (!empty($rev['negative'])) {

                            $yml .= "      <negative>" . esc_html($rev['negative']) . "</negative>\n";

                        }

                        if (!empty($rev['response'])) {

                            $response_text = wp_strip_all_tags($rev['response']);

                            if ($response_text != '') {
                                $yml .= "      <response>" . esc_html($response_text) . "</response>\n";
                            }

                        }

                         $yml .= "    </review>\n";

                    }

                }

                

                $yml .= "  </doctor>\n";

                break;

                

            case 'offer':

                // Offer fields preview

                $yml .= "  <offer>\n";

                $yml .= "    <url>" . esc_html($data['url'] ?? get_permalink($post->ID)) . "</url>\n";

                if (!empty($data['price'])) {

                    $yml .= "    <price>\n";

                    $yml .= "      <base_price>" . esc_html($data['price']) . "</base_price>\n";

                    $yml .= "      <currency>RUR</currency>\n";

                    $yml .= "    </price>\n";

                }

                if (!empty($data['online_schedule'])) {

                    $yml .= "    <online_schedule>" . esc_html($data['online_schedule']) . "</online_schedule>\n";

                }

                $yml .= "  </offer>\n";

                break;

                

            case 'relations':

                // Clinics + Services preview

                $yml .= "  <clinics>\n";

                if (!empty($data['clinics'])) {

                    foreach ($data['clinics'] as $clinic) {

                        $yml .= "    <clinic>\n";

                        $yml .= "      <name>" . esc_html($clinic['name'] ?? 'N/A') . "</name>\n";

                        $yml .= "      <city>" . esc_html($clinic['city'] ?? 'N/A') . "</city>\n";

                        $yml .= "    </clinic>\n";

                    }

                } else {

                    $yml .= "    <!-- No clinics found -->\n";

                }

                $yml .= "  </clinics>\n";

                

                $yml .= "  <services>\n";

                if (!empty($data['services'])) {

                    foreach ($data['services'] as $service) {

                        $yml .= "    <service>\n";

                        $yml .= "      <name>" . esc_html($service['name'] ?? 'N/A') . "</name>\n";
                        $yml .= "    </service>\n";

                    }

                } else {

                    $yml .= "    <!-- No services found -->\n";

                }

                $yml .= "  </services>\n";

                break;

                

            case 'clinics':

                // v4.2.0: Clinics tab preview - FIXED структура XML согласно Yandex docs

                // ID должен быть АТРИБУТОМ <clinic id="...">

                $clinic_id = $data['clinics_id'] ?? 'clinic_' . $post->ID;

                $yml .= "  <clinic id=\"" . esc_attr($clinic_id) . "\">\n";

                

                // URL первым (как в документации)

                if (!empty($data['clinics_url'])) {

                    $yml .= "    <url>" . esc_html($data['clinics_url']) . "</url>\n";

                }

                

                // Picture

                if (!empty($data['clinics_picture'])) {

                    $yml .= "    <picture>" . esc_html($data['clinics_picture']) . "</picture>\n";

                }

                

                // Name

                $yml .= "    <name>" . esc_html($data['clinics_name'] ?? $post->post_title) . "</name>\n";

                

                // City

                if (!empty($data['clinics_city'])) {

                    $yml .= "    <city>" . esc_html($data['clinics_city']) . "</city>\n";

                }

                

                // Address

                if (!empty($data['clinics_address'])) {

                    $yml .= "    <address>" . esc_html($data['clinics_address']) . "</address>\n";

                }

                

                // Email

                if (!empty($data['clinics_email'])) {

                    $yml .= "    <email>" . esc_html($data['clinics_email']) . "</email>\n";

                }

                

                // Phone

                if (!empty($data['clinics_phone'])) {

                    $yml .= "    <phone>" . esc_html($data['clinics_phone']) . "</phone>\n";

                }

                

                // Internal ID (БЕЗ префикса clinic_)

                $internal_id = str_replace('clinic_', '', $clinic_id);

                $yml .= "    <internal_id>" . esc_html($internal_id) . "</internal_id>\n";

                

                // Company ID

                if (!empty($data['clinics_company_id'])) {

                    $yml .= "    <company_id>" . esc_html($data['clinics_company_id']) . "</company_id>\n";

                }

                

                $yml .= "  </clinic>\n";

                break;

                

            case 'services':

                // v4.3.0: Services tab preview - FIXED to match clinics v4.2.2 pattern

                $service_id = $data['services_id'] ?? ('service_' . $post->ID);

                

                // v4.3.0: Ensure service_ prefix (like clinic_)

                if (strpos($service_id, 'service_') !== 0 && is_numeric($service_id)) {

                    $service_id = 'service_' . $service_id;

                }

                

                // ID как атрибут (НЕ отдельный тег!)

                $yml .= "  <service id=\"" . esc_attr($service_id) . "\">\n";

                

                // Name (ОБЯЗАТЕЛЬНОЕ по Яндекс)

                $yml .= "    <name>" . esc_html($data['services_name'] ?? $post->post_title) . "</name>\n";

                

                // Description (without HTML)

                if (!empty($data['services_description'])) {

                    $yml .= "    <description>" . esc_html($data['services_description']) . "</description>\n";

                }

                

                // Gov ID (государственный ID услуги)

                if (!empty($data['services_gov_id'])) {

                    $yml .= "    <gov_id>" . esc_html($data['services_gov_id']) . "</gov_id>\n";

                }

                

                // Internal ID (БЕЗ префикса service_)

                $internal_id = str_replace('service_', '', $service_id);

                $yml .= "    <internal_id>" . esc_html($internal_id) . "</internal_id>\n";

                

                $yml .= "  </service>\n";

                break;

                

            case 'offers':

                // v4.4.0: Offers tab preview - FULL structure per Yandex spec

                if (!empty($data['offers'])) {

                    // Show first 3 offers for preview

                    $preview_offers = array_slice($data['offers'], 0, 3);

                    

                    foreach ($preview_offers as $offer) {

                        $offer_id = $offer['id'] ?? 'offer_default';

                        

                        $yml .= "  <offer id=\"" . esc_attr($offer_id) . "\">\n";

                        

                        // URL (from doctor or offer)

                        if (!empty($offer['appointment_url'])) {

                            $yml .= "    <url>" . esc_html($offer['appointment_url']) . "</url>\n";

                        }

                        

                        // Price (nested structure)

                        if (!empty($offer['price'])) {

                            $yml .= "    <price>\n";

                            $yml .= "      <base_price>" . esc_html($offer['price']) . "</base_price>\n";

                            $yml .= "      <currency>" . esc_html($offer['currency']) . "</currency>\n";

                            $yml .= "    </price>\n";

                        }

                        

                        // Online schedule

                        if (isset($offer['online_schedule'])) {

                            $value = ($offer['online_schedule'] === 'true' || $offer['online_schedule'] === true) ? 'true' : 'false';

                            $yml .= "    <online_schedule>" . $value . "</online_schedule>\n";

                        }

                        

                        // Appointment

                        if (isset($offer['appointment_available'])) {

                            $value = ($offer['appointment_available'] === 'true' || $offer['appointment_available'] === true) ? 'true' : 'false';

                            $yml .= "    <appointment>" . $value . "</appointment>\n";

                        }

                        

                        // OMS

                        if (isset($offer['oms_available'])) {

                            $value = ($offer['oms_available'] === 'true' || $offer['oms_available'] === true) ? 'true' : 'false';

                            $yml .= "    <oms>" . $value . "</oms>\n";

                        }

                        

                        // Service reference (self-closing)

                        if (!empty($offer['service_id'])) {

                            $yml .= "    <service id=\"" . esc_attr($offer['service_id']) . "\"/>\n";

                        }

                        

                        // Clinic → Doctor nesting

                        if (!empty($offer['clinic_id'])) {

                            $yml .= "    <clinic id=\"" . esc_attr($offer['clinic_id']) . "\">\n";

                            

                            if (!empty($offer['doctor_id'])) {

                                $yml .= "      <doctor id=\"" . esc_attr($offer['doctor_id']) . "\">\n";

                                

                                // Speciality (required inside doctor)

                                if (!empty($offer['speciality'])) {

                                    $yml .= "        <speciality>" . esc_html($offer['speciality']) . "</speciality>\n";

                                }

                                

                                // Boolean fields inside doctor

                                if (isset($offer['children_appointment'])) {

                                    $value = ($offer['children_appointment'] === 'true' || $offer['children_appointment'] === true) ? 'true' : 'false';

                                    $yml .= "        <children_appointment>" . $value . "</children_appointment>\n";

                                }

                                

                                if (isset($offer['adult_appointment'])) {

                                    $value = ($offer['adult_appointment'] === 'true' || $offer['adult_appointment'] === true) ? 'true' : 'false';

                                    $yml .= "        <adult_appointment>" . $value . "</adult_appointment>\n";

                                }

                                

                                if (isset($offer['house_call'])) {

                                    $value = ($offer['house_call'] === 'true' || $offer['house_call'] === true) ? 'true' : 'false';

                                    $yml .= "        <house_call>" . $value . "</house_call>\n";

                                }

                                

                                if (isset($offer['telemed'])) {

                                    $value = ($offer['telemed'] === 'true' || $offer['telemed'] === true) ? 'true' : 'false';

                                    $yml .= "        <telemed>" . $value . "</telemed>\n";

                                }

                                

                                if (isset($offer['is_base_service'])) {

                                    $value = ($offer['is_base_service'] === 'true' || $offer['is_base_service'] === true) ? 'true' : 'false';

                                    $yml .= "        <is_base_service>" . $value . "</is_base_service>\n";

                                }

                                

                                $yml .= "      </doctor>\n";

                            }

                            

                            $yml .= "    </clinic>\n";

                        }

                        

                        $yml .= "  </offer>\n";

                    }

                } else {

                    $yml .= "  <!-- No offers found for this doctor -->\n";

                }

                break;

                

            case 'directory':

                // Full structure preview

                $yml .= "  <summary>\n";

                $yml .= "    <post_type>{$post->post_type}</post_type>\n";

                $yml .= "    <post_id>{$post->ID}</post_id>\n";

                $yml .= "    <clinics_count>" . count($data['clinics'] ?? array()) . "</clinics_count>\n";

                $yml .= "    <services_count>" . count($data['services'] ?? array()) . "</services_count>\n";

                $yml .= "    <reviews_count>" . count($data['reviews'] ?? array()) . "</reviews_count>\n";

                $yml .= "  </summary>\n";

                

                // Sample data

                $yml .= "  <sample_data>\n";

                $yml .= "    <name>" . esc_html($data['name'] ?? 'N/A') . "</name>\n";

                $yml .= "    <url>" . esc_html($data['url'] ?? get_permalink($post->ID)) . "</url>\n";

                $yml .= "  </sample_data>\n";

                break;

                

            default:

                $yml .= "  <!-- Unknown tab type: {$tab_type} -->\n";

        }

        

        $yml .= "</test-preview>";

        

        return $yml;

    }

    

    /**

     * Автоматическое обновление фида

     */

    public function auto_update_feed() {

        $error_handler = YFGP_Error_Handler::get_instance();

        $settings = get_option('yfgp_settings', array());

        

        if (!isset($settings['auto_update']) || !$settings['auto_update']) {

            return;

        }

        

        try {

            // Выбираем генератор в зависимости от настроек

            $feed_format = $settings['feed_format'] ?? 'v2';

            

            if ($feed_format === 'v2') {

                $generator = new YFGP_Feed_Generator_V2();

            } else {

                $generator = new YFGP_Feed_Generator();

            }

            

            $yml = $generator->generate($settings['post_type'] ?? 'doctors');

            

            $feed_result = $this->save_feed_file($yml, 'doctors.yml');

            $generated_at = $feed_result['generated_at'] ?? current_time('mysql');

            

            // Логирование

            $this->log_update('success', 'Фид автоматически обновлён (' . $generated_at . ')');

        } catch (Exception $e) {

            $error_handler->handle_cron_error($e, 'yfgp_auto_update_feed');

            $this->log_update('error', $e->getMessage());

        } catch (Throwable $e) {

            $error_handler->handle_cron_error($e, 'yfgp_auto_update_feed');

            $this->log_update('error', $e->getMessage());

        }

    }

    

    /**

     * Логирование обновлений

     */

    private function log_update($status, $message) {

        $history = get_option('yfgp_feed_history', array());

        

        $history[] = array(

            'date' => current_time('mysql'),

            'status' => $status,

            'message' => $message

        );

        

        // Храним только последние 50 записей

        if (count($history) > 50) {

            $history = array_slice($history, -50);

        }

        

        update_option('yfgp_feed_history', $history);

        

        // Отправка email уведомления

        $this->send_notification($status, $message);

    }

    

    /**

     * Отправка email уведомления

     */

    private function send_notification($status, $message) {

        $settings = get_option('yfgp_settings', array());

        

        // Проверяем, включены ли уведомления

        if (empty($settings['email_notifications'])) {

            return;

        }

        

        // Проверяем, нужно ли отправлять для этого статуса

        if ($status === 'success' && empty($settings['notify_on_success'])) {

            return;

        }

        

        if ($status === 'error' && empty($settings['notify_on_error'])) {

            return;

        }

        

        $to = $settings['notification_email'] ?? get_option('admin_email');

        $site_name = get_bloginfo('name');

        

        if ($status === 'success') {

            $subject = '[' . $site_name . '] ✅ Yandex Feed: Успешное обновление';

            $body = "Добрый день!\n\n";

            $body .= "Фид Yandex успешно обновлён.\n\n";

            $body .= "Дата: " . current_time('d.m.Y H:i') . "\n";

            $body .= "Сообщение: " . $message . "\n\n";

            $body .= "URL фида: " . home_url('/wp-content/uploads/feed/doctors.yml') . "\n\n";

            $body .= "---\n";

            $body .= "Это автоматическое уведомление от плагина Yandex Feed Generator Pro";

        } else {

            $subject = '[' . $site_name . '] ❌ Yandex Feed: Ошибка обновления';

            $body = "Добрый день!\n\n";

            $body .= "При обновлении фида Yandex произошла ошибка.\n\n";

            $body .= "Дата: " . current_time('d.m.Y H:i') . "\n";

            $body .= "Ошибка: " . $message . "\n\n";

            $body .= "Пожалуйста, проверьте настройки плагина.\n\n";

            $body .= "---\n";

            $body .= "Это автоматическое уведомление от плагина Yandex Feed Generator Pro";

        }

        

        wp_mail($to, $subject, $body);

    }

    

    /**

     * AJAX: Сохранение отредактированного XML

     */

    public function ajax_save_edited_xml() {

        // v4.18.13: Единый стандарт nonce для всех AJAX handlers

        check_ajax_referer('yfgp_ajax_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            wp_send_json_error('Недостаточно прав');

        }

        

        $xml_content = wp_unslash($_POST['xml_content'] ?? '');

        

        // Простая валидация XML

        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($xml_content);

        

        if ($xml === false) {

            $errors = libxml_get_errors();

            $error_msg = 'Ошибка XML: ';

            foreach ($errors as $error) {

                $error_msg .= $error->message . ' ';

            }

            libxml_clear_errors();

            wp_send_json_error($error_msg);

        }

        

        try {

            // v4.18.0: Использовать динамическое имя файла на основе post_type из настроек

            $settings = get_option('yfgp_settings', array());

            $post_type = $settings['post_type'] ?? 'doctors';

            $feed_filename = sanitize_file_name($post_type) . '.yml';

            

            $feed_result = $this->save_feed_file($xml_content, $feed_filename);

            

            $this->log_update('success', 'Фид обновлён вручную через редактор');

            

            wp_send_json_success(array(

                'message'       => 'XML успешно сохранён',

                'file_url'      => $feed_result['url'],

                'generated_at'  => $feed_result['generated_at'] ?? current_time('mysql'),

                'mtime'         => $feed_result['mtime'] ?? null,

                'bytes_written' => $feed_result['bytes'] ?? null,

            ));

        } catch (Exception $e) {

            wp_send_json_error($e->getMessage());

        }

    }

    

    /**

     * AJAX: Экспорт конфигурации

     */

    public function ajax_export_config() {

        // v4.18.13: Единый стандарт nonce для всех AJAX handlers

        check_ajax_referer('yfgp_ajax_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            wp_send_json_error('Недостаточно прав');

        }

        

        $config = array(

            'settings' => get_option('yfgp_settings', array()),

            'mapping' => get_option('yfgp_field_mapping', array()),

            'version' => YFGP_VERSION,

            'export_date' => current_time('mysql')

        );

        

        wp_send_json_success($config);

    }

    

    /**

     * AJAX: Импорт конфигурации

     */

    public function ajax_import_config() {

        // v4.18.13: Единый стандарт nonce для всех AJAX handlers

        check_ajax_referer('yfgp_ajax_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            wp_send_json_error('Недостаточно прав');

        }

        

        $config_json = wp_unslash($_POST['config_json'] ?? '');

        $config = json_decode($config_json, true);

        

        if (!$config || !isset($config['settings']) || !isset($config['mapping'])) {

            wp_send_json_error('Неверный формат конфигурации');

        }

        

        update_option('yfgp_settings', $config['settings']);

        update_option('yfgp_field_mapping', $config['mapping']);

        

        wp_send_json_success('Конфигурация импортирована');

    }

    

    /**

     * AJAX: Применение шаблона

     */

    public function ajax_apply_template() {

        // v4.18.13: Единый стандарт nonce для всех AJAX handlers

        check_ajax_referer('yfgp_ajax_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            wp_send_json_error('Недостаточно прав');

        }

        

        $template_id = sanitize_text_field($_POST['template_id'] ?? '');

        

        $templates = new YFGP_Templates();

        $result = $templates->apply_template($template_id);

        

        if ($result) {

            wp_send_json_success('Шаблон применён');

        } else {

            wp_send_json_error('Шаблон не найден');

        }

    }

    

    /**

     * AJAX: Получение терминов таксономии

     */

    /**

     * v4.18.10: Wrapper для wp_send_json_success с удалением BOM

     */

    private function send_json_success_no_bom($data = null) {

        // Очистка всех output buffers

        while (ob_get_level()) {

            ob_end_clean();

        }

        

        // Получаем JSON ответ

        $json = wp_json_encode(array('success' => true, 'data' => $data));

        

        // v4.18.11: Удаляем ВСЕ BOM подряд (может быть несколько!)

        while (strlen($json) > 0 && (

            substr($json, 0, 3) === "\xEF\xBB\xBF" ||

            (strlen($json) > 0 && ord($json[0]) === 0xEF && ord($json[1]) === 0xBB && ord($json[2]) === 0xBF)

        )) {

            $json = substr($json, 3);

        }

        // Также удаляем Unicode BOM (U+FEFF) если есть

        while (strlen($json) > 0 && (

            mb_substr($json, 0, 1, 'UTF-8') === "\xEF\xBB\xBF" ||

            (function_exists('mb_ord') && mb_ord(mb_substr($json, 0, 1, 'UTF-8'), 'UTF-8') === 0xFEFF)

        )) {

            $json = mb_substr($json, 1, null, 'UTF-8');

        }

        

        // Отправляем очищенный ответ

        header('Content-Type: application/json; charset=utf-8');

        echo $json;

        wp_die();

    }

    

    /**

     * v4.18.10: Wrapper для wp_send_json_error с удалением BOM

     */

    private function send_json_error_no_bom($data = null) {

        // Очистка всех output buffers

        while (ob_get_level()) {

            ob_end_clean();

        }

        

        // Получаем JSON ответ

        $json = wp_json_encode(array('success' => false, 'data' => $data));

        

        // v4.18.11: Удаляем ВСЕ BOM подряд (может быть несколько!)

        while (strlen($json) > 0 && (

            substr($json, 0, 3) === "\xEF\xBB\xBF" ||

            (strlen($json) > 0 && ord($json[0]) === 0xEF && ord($json[1]) === 0xBB && ord($json[2]) === 0xBF)

        )) {

            $json = substr($json, 3);

        }

        // Также удаляем Unicode BOM (U+FEFF) если есть

        while (strlen($json) > 0 && (

            mb_substr($json, 0, 1, 'UTF-8') === "\xEF\xBB\xBF" ||

            (function_exists('mb_ord') && mb_ord(mb_substr($json, 0, 1, 'UTF-8'), 'UTF-8') === 0xFEFF)

        )) {

            $json = mb_substr($json, 1, null, 'UTF-8');

        }

        

        // Отправляем очищенный ответ

        header('Content-Type: application/json; charset=utf-8');

        echo $json;

        wp_die();

    }

    

    public function ajax_get_terms() {

        // v4.18.0: Исправлена проверка nonce - используем 'nonce' вместо '_ajax_nonce'

        // v4.18.13: Единый стандарт nonce для всех AJAX handlers

        if (!check_ajax_referer('yfgp_ajax_nonce', 'nonce', false)) {

            $this->send_json_error_no_bom(array('message' => 'Invalid nonce'));

            return;

        }

        

        if (!current_user_can('manage_options')) {

            $this->send_json_error_no_bom(array('message' => 'Недостаточно прав'));

            return;

        }

        

        $taxonomy = sanitize_text_field($_POST['taxonomy'] ?? '');

        

        if (empty($taxonomy)) {

            // v4.18.12: Graceful fallback - возвращаем пустой массив вместо ошибки

            error_log('[YFGP] ajax_get_terms: Таксономия не указана, возвращаем пустой массив');

            $this->send_json_success_no_bom(array('terms' => array()));

            return;

        }

        

        // v4.18.12: Проверка существования таксономии перед запросом

        if (!taxonomy_exists($taxonomy)) {

            // Graceful fallback - возвращаем пустой массив вместо ошибки

            error_log('[YFGP] ajax_get_terms: Таксономия "' . $taxonomy . '" не существует, возвращаем пустой массив');

            $this->send_json_success_no_bom(array('terms' => array()));

            return;

        }

        

        $terms = get_terms(array(

            'taxonomy' => $taxonomy,

            'hide_empty' => false,

        ));

        

        // v4.18.12: Graceful fallback для WP_Error - возвращаем пустой массив вместо ошибки

        if (is_wp_error($terms)) {

            error_log('[YFGP] ajax_get_terms: Ошибка получения терминов для таксономии "' . $taxonomy . '": ' . $terms->get_error_message() . ', возвращаем пустой массив');

            $this->send_json_success_no_bom(array('terms' => array()));

            return;

        }

        

        $terms_array = array();

        foreach ($terms as $term) {

            $terms_array[$term->slug] = $term->name;

        }

        

        // v4.18.12: Логирование для отладки (только если терминов нет)

        if (empty($terms_array)) {

            error_log('[YFGP] ajax_get_terms: Таксономия "' . $taxonomy . '" существует, но терминов не найдено');

        }

        

        $this->send_json_success_no_bom(array('terms' => $terms_array));

    }

    

    /**

     * AJAX: Валидация фида (v4.18.0)

     */

    public function ajax_validate_feed() {

        // v4.18.13: Единый стандарт nonce для всех AJAX handlers

        check_ajax_referer('yfgp_ajax_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            wp_send_json_error('Недостаточно прав');

        }

        

        try {

            // Получаем настройки и генерируем фид для валидации

            $settings = get_option('yfgp_settings', array());

            $post_type = $settings['post_type'] ?? 'doctors';

            $feed_format = $settings['feed_format'] ?? 'v2';

            

            // Используем тот же генератор, что и ajax_generate_feed

            if ($feed_format === 'v2') {

                $feed_generator = new YFGP_Feed_Generator_V2();

            } else {

                $feed_generator = new YFGP_Feed_Generator();

            }

            

            $yml = $feed_generator->generate($post_type);

            

            if (empty($yml)) {

                wp_send_json_error('Не удалось сгенерировать фид для валидации');

            }

            

            // Валидация XML структуры

            libxml_use_internal_errors(true);

            $xml = simplexml_load_string($yml);

            

            if ($xml === false) {

                $errors = libxml_get_errors();

                $error_messages = array();

                foreach ($errors as $error) {

                    $error_messages[] = trim($error->message);

                }

                libxml_clear_errors();

                wp_send_json_error('Ошибки валидации XML: ' . implode('; ', array_unique($error_messages)));

            }

            

            // Дополнительная валидация структуры YML для Яндекс.Здоровье

            $validation_errors = array();

            

            // Корневой элемент должен быть <shop>

            $root_name = $xml->getName();

            if ($root_name !== 'shop') {

                $validation_errors[] = 'Ожидался корневой элемент &lt;shop&gt;, получен &lt;' . esc_html($root_name) . '&gt;';

            } else {

                if (!isset($xml->name) || $xml->name === '') {

                    $validation_errors[] = 'Отсутствует элемент &lt;shop&gt;&lt;name&gt;';

                }

                if (!isset($xml->company) || $xml->company === '') {

                    $validation_errors[] = 'Отсутствует элемент &lt;shop&gt;&lt;company&gt;';

                }

            }

            

            if (!empty($validation_errors)) {

                wp_send_json_error('Ошибки структуры YML: ' . implode('; ', $validation_errors));

            }

            

            wp_send_json_success('Фид валиден');

            

        } catch (Exception $e) {

            wp_send_json_error('Ошибка валидации: ' . $e->getMessage());

        }

    }

    

    /**

     * Инвалидация кэша reviews_total_count при сохранении отзыва

     * 

     * @param int $post_id ID отзыва

     * @since 3.1.1

     */

    private function wrap_cdata_preview(string $text): string {
        // Убираем лишние пробелы и переносы строк в начале и конце
        $trimmed = trim($text);
        $safe = str_replace(']]>', ']]]]><![CDATA[>', $trimmed);
        return '<![CDATA[' . $safe . ']]>';

    }



    public function invalidate_reviews_cache($post_id) {

        if (get_post_type($post_id) !== 'reviews') {

            return;

        }

        

        $doctor_id = get_post_meta($post_id, 'doctor', true);

        if ($doctor_id) {

            delete_transient('yfgp_reviews_count_' . $doctor_id);

            error_log("YFGP: Cache invalidated for doctor {$doctor_id} (review saved/updated)");

        }

    }

    

    /**

     * Инвалидация кэша reviews_total_count при удалении поста

     * 

     * @param int $post_id ID поста

     * @since 3.1.1

     */

    public function invalidate_reviews_cache_on_delete($post_id) {

        // Проверяем до удаления (post_type еще доступен)

        if (get_post_type($post_id) !== 'reviews') {

            return;

        }

        

        $doctor_id = get_post_meta($post_id, 'doctor', true);

        if ($doctor_id) {

            delete_transient('yfgp_reviews_count_' . $doctor_id);

            error_log("YFGP: Cache invalidated for doctor {$doctor_id} (review deleted)");

        }

    }

}



/**

 * Инициализация плагина

 */

function yfgp_init() {

    // v4.18.17: Классы уже загружены при загрузке плагина (см. код выше)

    // Просто возвращаем экземпляр плагина

    return Yandex_Feed_Generator_Pro::get_instance();

}



/**

 * Загрузка классов плагина

 */

function yfgp_load_classes() {

    // Проверяем, что WordPress полностью загружен

    if (!function_exists('get_option') || !function_exists('add_action')) {

        return; // WordPress ещё не загружен

    }



    // v4.18.17: Загружаем Service Container и service-factories с обработкой ошибок

    // ВАЖНО: Не прерываем загрузку WordPress при ошибках, просто логируем

    try {

        if (!class_exists('YFGP_Service_Container')) {

            $file = YFGP_PLUGIN_DIR . 'includes/class-service-container.php';

            if (file_exists($file)) {

                require_once $file;

            } else {

                if (function_exists('error_log')) {

                    error_log('YFGP Error: class-service-container.php not found at ' . $file);

                }

                return;

            }

        }

        

        if (!function_exists('yfgp_register_services')) {

            $file = YFGP_PLUGIN_DIR . 'includes/service-factories.php';

            if (file_exists($file)) {

                try {

                    require_once $file;

                } catch (Throwable $e) {

                    if (function_exists('error_log')) {

                        error_log('YFGP Error loading service-factories.php: ' . $e->getMessage());

                        error_log('YFGP Stack trace: ' . $e->getTraceAsString());

                    }

                    return;

                }

            } else {

                if (function_exists('error_log')) {

                    error_log('YFGP Error: service-factories.php not found at ' . $file);

                }

                return;

            }

        }



        if (function_exists('yfgp_register_services') && class_exists('YFGP_Service_Container') && function_exists('get_option')) {

            try {

                $container = YFGP_Service_Container::get_instance();

                yfgp_register_services($container);

            } catch (Throwable $e) {

                if (function_exists('error_log')) {

                    error_log('YFGP Error during service registration: ' . $e->getMessage());

                    error_log('YFGP Stack trace: ' . $e->getTraceAsString());

                }

            }

        }

    } catch (Throwable $e) {

        if (function_exists('error_log')) {

            error_log('YFGP Error during class loading: ' . $e->getMessage());

            error_log('YFGP Stack trace: ' . $e->getTraceAsString());

        }

    }

}



// Запуск плагина

// v4.18.17: Загружаем классы через хук 'plugins_loaded' с высоким приоритетом

// Это гарантирует, что WordPress полностью загружен перед загрузкой классов

if (function_exists('add_action')) {

    add_action('plugins_loaded', 'yfgp_load_classes', 1);

    add_action('plugins_loaded', 'yfgp_init', 2);

}



