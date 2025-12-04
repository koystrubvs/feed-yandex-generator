<?php
/**
 * Админ-панель плагина
 */

if (!defined('ABSPATH')) {
    exit;
}

class YFGP_Admin_Page {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_notices', array($this, 'show_feed_log_notice'));
        // Миграция v2.3.1: удаление default_price
        add_action('admin_init', array($this, 'migrate_v231_remove_default_price'));
        // v3.4.5: Обработка сохранения через admin_post (Task #13 - BEST PRACTICE!)
        // admin_post_{action} - WordPress best practice для обработки форм с redirect
        add_action('admin_post_yfgp_save_mapping', array($this, 'handle_mapping_save_v3'));
        // v4.18.21: Автоматическое добавление calculate_type при чтении маппинга
        add_filter('option_yfgp_field_mapping_v3', array($this, 'auto_add_calculate_type'), 10, 1);
        
        // v5.0.0: Admin AJAX handlers вынесены в отдельный класс
        if (class_exists('YFGP_Admin_Ajax_Handlers')) {
            new YFGP_Admin_Ajax_Handlers();
        }
    }
    
    /**
     * Добавление страницы в меню
     */
    public function add_menu_page(): void {
        add_menu_page(
            'Yandex Feed Generator',
            'Yandex Feeds',
            'manage_options',
            'yandex-feed-generator',
            array($this, 'render_main_page'),
            'dashicons-rss',
            30
        );
        
        // Подстраницы
        add_submenu_page(
            'yandex-feed-generator',
            'Настройки',
            'Настройки',
            'manage_options',
            'yandex-feed-generator',
            array($this, 'render_main_page')
        );
        
        add_submenu_page(
            'yandex-feed-generator',
            'Маппинг полей',
            'Маппинг полей',
            'manage_options',
            'yandex-feed-mapping',
            array($this, 'render_mapping_page')
        );
        
        add_submenu_page(
            'yandex-feed-generator',
            'Генерация фида',
            'Генерация фида',
            'manage_options',
            'yandex-feed-generate',
            array($this, 'render_generate_page')
        );
        
        add_submenu_page(
            'yandex-feed-generator',
            'История',
            'История',
            'manage_options',
            'yandex-feed-history',
            array($this, 'render_history_page')
        );
        
        // v3.5.0: CPT Scanner removed (dead code - 850+ lines)
    }
    
    /**
     * Подключение скриптов
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'yandex-feed') === false) {
            return;
        }
        
        // CodeMirror для редактора XML
        wp_enqueue_style(
            'codemirror',
            'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css',
            array(),
            '5.65.2'
        );
        
        wp_enqueue_style(
            'codemirror-theme',
            'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/material.min.css',
            array(),
            '5.65.2'
        );
        
        wp_enqueue_script(
            'codemirror',
            'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js',
            array(),
            '5.65.2',
            true
        );
        
        wp_enqueue_script(
            'codemirror-xml',
            'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js',
            array('codemirror'),
            '5.65.2',
            true
        );
        
        // Стили плагина
        wp_enqueue_style(
            'yfgp-admin-style',
            YFGP_PLUGIN_URL . 'admin/assets/css/admin-style.css',
            array(),
            YFGP_VERSION
        );
        
        // Стили для динамических полей
        wp_enqueue_style(
            'yfgp-dynamic-fields',
            YFGP_PLUGIN_URL . 'admin/assets/css/dynamic-fields.css',
            array(),
            YFGP_VERSION
        );
        
        // Скрипты плагина
        wp_enqueue_script(
            'yfgp-admin-script',
            YFGP_PLUGIN_URL . 'admin/assets/js/admin-script.js',
            array('jquery', 'codemirror'),
            YFGP_VERSION,
            true
        );
        
        $legacy_selector_enabled = !defined('YFGP_DISABLE_LEGACY_SELECTOR') || !YFGP_DISABLE_LEGACY_SELECTOR;
        /**
         * Позволяет включить/отключить загрузку legacy-скрипта динамических полей.
         *
         * @since 4.18.50
         *
         * @param bool   $enabled Загружать ли legacy-скрипт.
         * @param string $hook    Текущий admin hook.
         */
        $legacy_selector_enabled = apply_filters('yfgp_enable_legacy_dynamic_fields', $legacy_selector_enabled, $hook);
        
        if ($legacy_selector_enabled) {
            wp_enqueue_script(
                'yfgp-dynamic-fields',
                YFGP_PLUGIN_URL . 'admin/assets/js/dynamic-field-selector.js',
                array('jquery'),
                YFGP_VERSION . '.' . time(), // v4.18.22: Cache busting for async fix
                true
            );
        }
        
        // Скрипт динамических полей V3 (v3.0.0)
        $v3_dependencies = array('jquery');
        if ($legacy_selector_enabled) {
            $v3_dependencies[] = 'yfgp-dynamic-fields';
        }
        wp_enqueue_script(
            'yfgp-dynamic-fields-v3',
            YFGP_PLUGIN_URL . 'admin/assets/js/dynamic-field-selector-v3.js',
            $v3_dependencies,
            YFGP_VERSION . '.' . time(), // v4.18.22: Force cache busting for JS fixes
            true
        );
        
        // v4.20.1: Скрипт для ручных настроек услуг по специализациям
        wp_enqueue_script(
            'yfgp-specialization-services-tabs',
            YFGP_PLUGIN_URL . 'admin/assets/js/specialization-services-tabs.js',
            array('jquery'),
            YFGP_VERSION,
            true
        );
        
        // v3.3.5: Передаем available fields для ВСЕХ CPT при загрузке (избегаем 81 AJAX!)
        if (!class_exists('YFGP_Field_Mapper_Unified')) {
            require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
        }
        $field_mapper = YFGP_Field_Mapper_Unified::get_instance();
        $settings = get_option('yfgp_settings', array());
        
        // v4.18.0: Получаем человекочитаемые названия CPT для индикатора
        $post_type_labels = array();
        $all_cpts = get_post_types(array('public' => true), 'objects');
        foreach ($all_cpts as $cpt) {
            $post_type_labels[$cpt->name] = $cpt->label;
        }
        
        // Передаем AJAX данные в ВСЕ скрипты
        // v4.18.13: Единый стандарт nonce для всех AJAX handlers
        $ajax_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('yfgp_ajax_nonce'),
            'post_type' => $settings['post_type'] ?? 'doctors',
            'post_type_labels' => $post_type_labels, // v4.18.0: Человекочитаемые названия CPT для индикатора
            'availableFieldsByPostType' => array(
                'doctors' => $field_mapper->get_available_fields('doctors'),
                'clinics' => $field_mapper->get_available_fields('clinics'),
                'services' => $field_mapper->get_available_fields('services'),
                'prices' => $field_mapper->get_available_fields('prices')
            )
        );

        $debug_logging_enabled = defined('YFGP_DEBUG_LOGS') ? (bool) YFGP_DEBUG_LOGS : false;
        /**
         * Позволяет включить подробное логирование на странице маппинга.
         *
         * @since 4.18.51
         *
         * @param bool   $enabled Включено ли логирование.
         * @param string $hook    Текущий admin hook.
         */
        $debug_logging_enabled = apply_filters('yfgp_dynamic_field_debug_mode', $debug_logging_enabled, $hook);

        if (isset($_GET['yfgp_debug'])) {
            $debug_logging_enabled = sanitize_text_field(wp_unslash($_GET['yfgp_debug'])) === '1';
        }

        $ajax_data['debug'] = $debug_logging_enabled;
        
        wp_localize_script('yfgp-admin-script', 'yfgpAjax', $ajax_data);
        if ($legacy_selector_enabled) {
            wp_localize_script('yfgp-dynamic-fields', 'yfgpAjax', $ajax_data);
        }
        wp_localize_script('yfgp-dynamic-fields-v3', 'yfgpAjax', $ajax_data);
        
        // v4.20.1: Локализация для скрипта ручных настроек услуг
        wp_localize_script('yfgp-specialization-services-tabs', 'yfgpAjax', $ajax_data);
    }
    
    /**
     * Главная страница (настройки)
     */
    public function render_main_page(): void {
        // Проверка прав доступа уже выполнена WordPress через 'manage_options' в add_menu_page()
        // Дополнительная проверка не требуется
        
        // Сохранение настроек
        if (isset($_POST['yfgp_save_settings']) && check_admin_referer('yfgp_settings_nonce')) {
            $settings = array(
                // v2.4.1: Поля shop согласно Яндекс документации (убрано двойное экранирование)
                'shop_name' => sanitize_text_field(wp_unslash($_POST['shop_name'] ?? get_bloginfo('name'))),
                'shop_picture' => esc_url_raw($_POST['shop_picture'] ?? ''),
                'company_name' => sanitize_text_field(wp_unslash($_POST['company_name'] ?? get_bloginfo('name'))),
                'company_url' => esc_url_raw($_POST['company_url'] ?? get_site_url()),
                'company_email' => sanitize_email($_POST['company_email'] ?? get_option('admin_email')),
                'post_type' => sanitize_text_field($_POST['post_type'] ?? 'doctors'),
                'feed_category' => sanitize_text_field($_POST['feed_category'] ?? 'doctors'),
                'city' => sanitize_text_field(wp_unslash($_POST['city'] ?? '')),
                'feed_format' => sanitize_text_field($_POST['feed_format'] ?? 'v2'),
                // v4.18.21: Legacy поля sets/sets_source/sets_taxonomy/sets_field/sets_url_template удалены - не используются в UI и генерации фида
                'specialties_no_primary' => sanitize_textarea_field(wp_unslash($_POST['specialties_no_primary'] ?? '')),
                'specialties_no_primary_source' => sanitize_text_field($_POST['specialties_no_primary_source'] ?? 'field'),
                'specialties_taxonomy' => sanitize_text_field($_POST['specialties_taxonomy'] ?? ''),
                // v2.4.1: Новые поля для исключений (Настройки v2.0)
                'exclusions_cpt' => sanitize_text_field($_POST['exclusions_cpt'] ?? ''),
                'exclusions_taxonomy' => sanitize_text_field($_POST['exclusions_taxonomy'] ?? ''),
                'exclusions_field' => sanitize_text_field($_POST['exclusions_field'] ?? ''),
                'exclusions_operator' => sanitize_text_field($_POST['exclusions_operator'] ?? 'equals'),
                'exclusions_value' => sanitize_text_field(wp_unslash($_POST['exclusions_value'] ?? '')),
                'exclusions_terms' => isset($_POST['exclusions_terms']) ? array_map('sanitize_text_field', (array)$_POST['exclusions_terms']) : array(),
                // Остальные настройки
                'generate_all_services' => isset($_POST['generate_all_services']),
                'email_notifications' => isset($_POST['email_notifications']),
                'notification_email' => sanitize_email($_POST['notification_email'] ?? get_option('admin_email')),
                'notify_on_success' => isset($_POST['notify_on_success']),
                'notify_on_error' => isset($_POST['notify_on_error']),
                'auto_update' => isset($_POST['auto_update']),
                'update_interval' => sanitize_text_field($_POST['update_interval'] ?? 'daily'),
                // v2.4.1: Настройки типов записей
                'cpt_clinics' => sanitize_text_field($_POST['cpt_clinics'] ?? 'clinics'),
                'cpt_services' => sanitize_text_field($_POST['cpt_services'] ?? 'services'),
                // 'cpt_reviews' removed - using source_reviews + source_reviews_cpt instead
                // v2.4.1: Настройки валюты
                'default_currency' => sanitize_text_field($_POST['default_currency'] ?? 'RUR'),
                'default_service_name' => sanitize_text_field(wp_unslash($_POST['default_service_name'] ?? 'Первичный прием')),
                // v3.0.0: Источники данных
                'source_education' => sanitize_text_field($_POST['source_education'] ?? 'repeater'),
                'source_education_cpt' => sanitize_text_field($_POST['source_education_cpt'] ?? ''),
                'source_jobs' => sanitize_text_field($_POST['source_jobs'] ?? 'repeater'),
                'source_jobs_cpt' => sanitize_text_field($_POST['source_jobs_cpt'] ?? ''),
                'source_certificates' => sanitize_text_field($_POST['source_certificates'] ?? 'repeater'),
                'source_certificates_cpt' => sanitize_text_field($_POST['source_certificates_cpt'] ?? ''),
                // v3.0.0: Добавлены настройки для reviews и prices
                'source_reviews' => sanitize_text_field($_POST['source_reviews'] ?? 'repeater'),
                'source_reviews_cpt' => sanitize_text_field($_POST['source_reviews_cpt'] ?? ''),
                'source_prices' => sanitize_text_field($_POST['source_prices'] ?? 'repeater'),
                'source_prices_cpt' => sanitize_text_field($_POST['source_prices_cpt'] ?? ''),
                'base_service_mode' => sanitize_text_field($_POST['base_service_mode'] ?? 'automatic'),
                // v4.19.0: Обработка fallback_speciality (специализация по умолчанию)
                'fallback_speciality' => isset($_POST['fallback_speciality_custom_toggle']) ? '' : sanitize_text_field(wp_unslash($_POST['fallback_speciality'] ?? '')),
                'fallback_speciality_custom' => isset($_POST['fallback_speciality_custom_toggle']) ? sanitize_text_field(wp_unslash($_POST['fallback_speciality_custom'] ?? '')) : '',
                // v4.18.21: Sentry Integration настройки
                'sentry_enabled' => isset($_POST['sentry_enabled']),
                'sentry_dsn' => sanitize_text_field($_POST['sentry_dsn'] ?? ''),
                'sentry_traces_sample_rate' => isset($_POST['sentry_traces_sample_rate']) ? (float) $_POST['sentry_traces_sample_rate'] : 0.1,
            );
            
            // Миграция legacy CPT ключей и нормализация настроек
            $settings = $this->maybe_migrate_cpt_settings($settings);
            $settings = $this->with_normalized_cpts($settings);
            
            update_option('yfgp_settings', $settings);
            
            // Обновление расписания
            $cron = new YFGP_Cron_Manager();
            if ($settings['auto_update']) {
                $cron->schedule_feed_update($settings['update_interval']);
            } else {
                $cron->unschedule_feed_update();
            }
            
            echo '<div class="notice notice-success"><p>Настройки сохранены!</p></div>';
        }
        
        $settings = get_option('yfgp_settings', array());
        include YFGP_PLUGIN_DIR . 'admin/views/settings.php';
    }
    
    /**
     * Страница маппинга полей
     * 
     * @since 3.0.0 Используется field-mapping-v3.php с поддержкой множественных источников данных
     */
    public function render_mapping_page(): void {
        // v3.4.2: OLD handler REMOVED - now handled by handle_mapping_save_v3() on admin_init hook
        // This prevents duplicate save logic and allows clean JSON architecture

        $settings = get_option('yfgp_settings', array());
        $current_context = $this->build_mapping_context($settings);
        $previous_context = get_option('yfgp_field_mapping_context', array());
        if (!is_array($previous_context)) {
            $previous_context = array();
        }

        // v3.0.0: Проверяем наличие настроек источников данных
        $has_v3_settings = isset($settings['source_education']) ||
                           isset($settings['source_jobs']) ||
                           isset($settings['source_certificates']);

        if ($has_v3_settings || version_compare(YFGP_VERSION, '3.0.0', '>=')) {
            $current_mapping = get_option('yfgp_field_mapping_v3', array());
            $entities_to_reset = $this->detect_mapping_context_changes($previous_context, $current_context);

            if (!empty($entities_to_reset) && !empty($current_mapping) && is_array($current_mapping)) {
                $current_mapping = $this->reset_mapping_for_entities($current_mapping, $entities_to_reset);
                update_option('yfgp_field_mapping_v3', $current_mapping, false);
            }

            update_option('yfgp_field_mapping_context', array_merge($current_context, array('updated_at' => time())), false);

            $mapping_context = $current_context;
            $mapping_context_labels = $this->build_mapping_context_labels($current_context);
            $current_mapping = is_array($current_mapping) ? $current_mapping : array();
            $post_type = $settings['post_type'] ?? 'doctors';
            $data_sources = $settings;

            include YFGP_PLUGIN_DIR . 'admin/views/field-mapping-v3.php';
        } else {
            $mapping = get_option('yfgp_field_mapping', array());
            update_option('yfgp_field_mapping_context', array_merge($current_context, array('updated_at' => time())), false);
            include YFGP_PLUGIN_DIR . 'admin/views/field-mapping-v2.php';
        }
    }
    
    /**
     * Страница генерации
     */
    public function render_generate_page(): void {
        $settings = get_option('yfgp_settings', array());
        
        // v3.4.8: Direct file handling (YFGP_File_Manager doesn't exist!)
        $upload_dir = wp_upload_dir();
        $feed_dir = $upload_dir['basedir'] . '/feed';
        // v4.19.1: Dynamic filename based on selected post_type (universal, no hardcode!)
        $post_type = $settings['post_type'] ?? 'doctors';
        $feed_filename = sanitize_file_name($post_type) . '.yml';
        $feed_path = $feed_dir . '/' . $feed_filename;
        $feed_url = $upload_dir['baseurl'] . '/feed/' . $feed_filename;
        
        // Ручная генерация
        if (isset($_POST['yfgp_generate_now']) && check_admin_referer('yfgp_generate_nonce')) {
            try {
                // v4.1.0-beta18: Debug logging
                error_log('YFGP DEBUG: Starting feed generation...');
                error_log('YFGP DEBUG: Post type: ' . ($settings['post_type'] ?? 'doctors'));
                
                // Check if class exists
                if (!class_exists('YFGP_Feed_Generator_V2')) {
                    throw new Exception('Class YFGP_Feed_Generator_V2 not found!');
                }
                
                $generator = new YFGP_Feed_Generator_V2();
                error_log('YFGP DEBUG: Generator created successfully');
                
                $yml = $generator->generate($settings['post_type'] ?? 'doctors');
                error_log('YFGP DEBUG: Feed generated, length: ' . strlen($yml));
                
                // v4.18.1: Use save_feed_file() instead of file_put_contents() to save metadata
                if (!class_exists('Yandex_Feed_Generator_Pro')) {
                    throw new Exception('Class Yandex_Feed_Generator_Pro not found!');
                }
                
                $plugin_instance = Yandex_Feed_Generator_Pro::get_instance();
                $feed_result = $plugin_instance->save_feed_file($yml, $feed_filename);
                error_log('YFGP DEBUG: File saved via save_feed_file(), bytes: ' . $feed_result['bytes']);
                
                // Update feed_url from result (may include cache-busting)
                $feed_url = $feed_result['url'];
                
                // v4.1.0-beta31: Add cache-busting timestamp to URL
                $cache_buster = '?v=' . time();
                echo '<div class="notice notice-success"><p>Фид успешно создан! <a href="' . esc_url($feed_url . $cache_buster) . '" target="_blank" download>Скачать фид</a> | <a href="' . esc_url($feed_url . $cache_buster) . '" target="_blank">Открыть</a></p></div>';
            } catch (Exception $e) {
                error_log('YFGP ERROR: ' . $e->getMessage());
                error_log('YFGP ERROR: Stack trace: ' . $e->getTraceAsString());
                
                // v4.18.17: Детальная обработка ошибок валидации
                $error_message = '';
                $error_details = array();
                
                // Проверяем, является ли это ValidationException
                if (class_exists('YFGP_Validation_Exception') && $e instanceof YFGP_Validation_Exception) {
                    $validation_result = $e->getValidationResult();
                    if ($validation_result !== null) {
                        $critical_errors = $validation_result->getCriticalErrors();
                        $warnings = $validation_result->getWarnings();
                        
                        // Формируем общее сообщение
                        if (!empty($critical_errors)) {
                            $error_message = 'Ошибка валидации маппинга полей';
                            
                            $error_details[] = '<strong>Критические ошибки:</strong>';
                            $error_details[] = '<ul style="margin-left: 20px; margin-top: 5px;">';
                            foreach ($critical_errors as $error) {
                                $error_details[] = '<li>' . esc_html($error) . '</li>';
                            }
                            $error_details[] = '</ul>';
                        }
                        
                        if (!empty($warnings)) {
                            $error_details[] = '<strong>Предупреждения:</strong>';
                            $error_details[] = '<ul style="margin-left: 20px; margin-top: 5px;">';
                            foreach ($warnings as $warning) {
                                $error_details[] = '<li>' . esc_html($warning) . '</li>';
                            }
                            $error_details[] = '</ul>';
                        }
                    }
                }
                
                // Если не ValidationException, используем стандартное сообщение
                if (empty($error_message)) {
                    $error_message = esc_html($e->getMessage());
                }
                
                echo '<div class="notice notice-error"><p><strong>Ошибка:</strong> ' . $error_message . '</p>';
                if (!empty($error_details)) {
                    echo '<div style="margin-top: 10px;">' . implode("\n", $error_details) . '</div>';
                } else {
                    // Если нет деталей, показываем файл и строку для отладки
                    echo '<p style="margin-top: 5px; font-size: 12px; color: #666;">Файл: ' . esc_html($e->getFile()) . ' Строка: ' . $e->getLine() . '</p>';
                }
                echo '</div>';
            } catch (Error $e) {
                error_log('YFGP FATAL ERROR: ' . $e->getMessage());
                error_log('YFGP FATAL ERROR: File: ' . $e->getFile() . ' Line: ' . $e->getLine());
                error_log('YFGP FATAL ERROR: Stack trace: ' . $e->getTraceAsString());
                echo '<div class="notice notice-error"><p>Критическая ошибка: ' . esc_html($e->getMessage()) . '</p></div>';
                echo '<div class="notice notice-error"><p>Файл: ' . esc_html($e->getFile()) . ' Строка: ' . $e->getLine() . '</p></div>';
            }
        }
        
        // Get feed content
        $feed_content = file_exists($feed_path) ? file_get_contents($feed_path) : '';
        
        // v4.18.1: Read last generation metadata for display
        $last_generation = get_option('yfgp_feed_last_generated', array());
        $last_generated_at = $last_generation['generated_at'] ?? '';
        $feed_mtime = isset($last_generation['mtime']) ? $last_generation['mtime'] : (file_exists($feed_path) ? filemtime($feed_path) : null);
        
        include YFGP_PLUGIN_DIR . 'admin/views/preview.php';
    }
    
    /**
     * Страница истории
     */
    public function render_history_page(): void {
        $history = get_option('yfgp_feed_history', array());
        
        // v3.5.1: Simple backups list (YFGP_File_Manager doesn't exist)
        $backups = array();
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/feed/backups';
        
        if (file_exists($backup_dir)) {
            $files = glob($backup_dir . '/*.yml');
            if ($files) {
                foreach ($files as $file) {
                    $backups[] = array(
                        'file' => basename($file),
                        'date' => date('Y-m-d H:i:s', filemtime($file)),
                        'size' => filesize($file)
                    );
                }
            }
        }
        
        include YFGP_PLUGIN_DIR . 'admin/views/history.php';
    }
    
    /**
     * Показать отчёт о проблемах с ценами
     * v2.2.0: Новый метод для отображения отчёта после генерации фида
     */
    public function show_feed_log_notice(): void {
        // Показываем только на страницах плагина
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'yandex-feed') === false) {
            return;
        }
        
        // v3.4.6: Проверка transient для success message (URL params НЕ работают - WP Rocket убирает!)
        $mapping_saved = get_transient('yfgp_mapping_saved_' . get_current_user_id());
        if ($mapping_saved) {
            delete_transient('yfgp_mapping_saved_' . get_current_user_id());
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>✅ Успех!</strong> Настройки маппинга сохранены (84 поля).</p>';
            echo '</div>';
        }
        
        $log = get_transient('yfgp_feed_log');
        if (empty($log)) {
            return;
        }
        
        $counts = array(
            'autocreated_service' => 0,
            'fallback_service' => 0,
            'missing_services' => 0,
            'no_prices' => 0
        );
        
        foreach ($log as $entry) {
            $counts[$entry['type']]++;
        }
        
        $total_issues = array_sum($counts);
        if ($total_issues === 0) {
            return;
        }
        
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<h3>⚠️ Yandex Feed: найдены проблемы с ценами</h3>';
        echo '<p>При последней генерации фида обнаружено проблем: <strong>' . esc_html($total_issues) . '</strong></p>';
        
        if ($counts['autocreated_service'] > 0) {
            echo '<p>✅ <strong>Автосозданы услуги:</strong> ' . esc_html($counts['autocreated_service']) . '</p>';
            echo '<ul style="list-style: disc; margin-left: 20px;">';
            foreach ($log as $entry) {
                if ($entry['type'] === 'autocreated_service') {
                    echo '<li>';
                    echo '<a href="' . esc_url(get_edit_post_link($entry['doctor_id'])) . '" target="_blank">';
                    echo esc_html($entry['doctor_name']);
                    echo '</a> → "' . esc_html($entry['service']) . '" (' . esc_html($entry['price']) . '₽)';
                    echo '</li>';
                }
            }
            echo '</ul>';
        }
        
        if ($counts['fallback_service'] > 0) {
            echo '<p>⚠️ <strong>Использованы альтернативные услуги:</strong> ' . esc_html($counts['fallback_service']) . '</p>';
            echo '<ul style="list-style: disc; margin-left: 20px;">';
            foreach ($log as $entry) {
                if ($entry['type'] === 'fallback_service') {
                    echo '<li>';
                    echo '<a href="' . esc_url(get_edit_post_link($entry['doctor_id'])) . '" target="_blank">';
                    echo esc_html($entry['doctor_name']);
                    echo '</a> → "' . esc_html($entry['service']) . '" <em>(базовая без цены)</em>';
                    echo '</li>';
                }
            }
            echo '</ul>';
        }
        
        if ($counts['missing_services'] > 0 || $counts['no_prices'] > 0) {
            echo '<p>❌ <strong>Врачи пропущены:</strong> ' . esc_html($counts['missing_services'] + $counts['no_prices']) . '</p>';
            echo '<ul style="list-style: disc; margin-left: 20px;">';
            foreach ($log as $entry) {
                if ($entry['type'] === 'missing_services' || $entry['type'] === 'no_prices') {
                    echo '<li>';
                    echo '<a href="' . esc_url(get_edit_post_link($entry['doctor_id'])) . '" target="_blank">';
                    echo esc_html($entry['doctor_name']);
                    echo '</a> → ' . esc_html($entry['reason']);
                    if ($entry['type'] === 'no_prices') {
                        echo ' (услуг: ' . esc_html($entry['services_count']) . ')';
                    }
                    echo '</li>';
                }
            }
            echo '</ul>';
        }
        
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=yandex-feed-mapping')) . '" class="button">→ Перейти к настройкам маппинга</a></p>';
        echo '</div>';
    }

    /**
     * v2.3.1 Migration: remove deprecated default_price setting
     * Выполняется один раз, безопасно
     */
    public function migrate_v231_remove_default_price(): void {
        if (get_option('yfgp_migrated_v231')) {
            return;
        }

        $settings = get_option('yfgp_settings', array());
        $changed = false;

        if (isset($settings['default_price'])) {
            unset($settings['default_price']);
            $changed = true;
        }

        // Иногда дефолтная цена могла храниться отдельно
        // Пробуем удалить возможные одиночные опции
        if (get_option('yfgp_default_price', null) !== null) {
            delete_option('yfgp_default_price');
            $changed = true;
        }

        if ($changed) {
            update_option('yfgp_settings', $settings);
        }

        update_option('yfgp_migrated_v231', 1);
    }
    
    /**
     * AJAX: Получить доступные CPT для источников данных
     * 
     * @since 3.0.0
     */
    /**
     * v4.18.10: Wrapper для wp_send_json_success с удалением BOM
     */
    private function send_json_success_no_bom($data = null): void {
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
    private function send_json_error_no_bom($data = null): void {
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
    
    // ==========================================
    // v3.4.1: Mapping Save Handler (Task #13 - Production-Ready Save)
    // ==========================================
    
    /**
     * Обработка сохранения маппинга через JSON
     * Восстановлено из Task #7 + адаптировано для v3.4.0 (offer inheritance)
     * 
     * @since 3.4.1
     */
    public function handle_mapping_save_v3(): void {
        // v3.4.5: admin_post hook - НЕ нужны проверки REQUEST_URI, WordPress сам роутит!
        // v4.18.11: Добавлена обработка ошибок для предотвращения белого экрана
        try {
            error_log('YFGP SAVE: ✅ Starting save process via admin_post hook...');
            
            if (!isset($_POST['yfgp_save_mapping_v3'])) {
                error_log('YFGP SAVE ERROR: Missing yfgp_save_mapping_v3 parameter');
                wp_die('Ошибка: Неверный запрос', 'Ошибка сохранения', array('response' => 400));
            }
            
            // Проверка nonce
            if (!check_admin_referer('yfgp_mapping_v3_nonce')) {
                error_log('YFGP SAVE ERROR: Invalid nonce');
                wp_die('Ошибка безопасности!', 'Ошибка безопасности', array('response' => 403));
            }
            
            // КРИТИЧНО: используем wp_unslash() для получения RAW JSON данных
            $mapping_json = isset($_POST['yfgp_mapping_json']) ? wp_unslash($_POST['yfgp_mapping_json']) : '';
            
            // v4.18.22: Security - Check JSON size limit (5MB)
            $max_json_size = 5 * 1024 * 1024; // 5MB
            if (strlen($mapping_json) > $max_json_size) {
                if (class_exists('YFGP_Logger')) {
                    YFGP_Logger::get_instance()->warning('YFGP Security: Mapping JSON too large: ' . strlen($mapping_json) . ' bytes (max: ' . $max_json_size . ' bytes)');
                } else {
                    error_log('YFGP Security: Mapping JSON too large: ' . strlen($mapping_json) . ' bytes (max: ' . $max_json_size . ' bytes)');
                }
                while (ob_get_level()) {
                    ob_end_clean();
                }
                $error_url = admin_url('admin.php?page=yandex-feed-mapping&error=json_too_large');
                if (!headers_sent()) {
                    wp_safe_redirect($error_url);
                } else {
                    echo '<script>window.location.href = "' . esc_js($error_url) . '";</script>';
                    wp_die('Перенаправление...', 'Ошибка', array('response' => 200));
                }
                exit;
            }
            
            if (empty($mapping_json)) {
                error_log('YFGP ERROR: JSON данные пустые!');
                // v4.18.11: Очистка output buffers перед redirect
                while (ob_get_level()) {
                    ob_end_clean();
                }
                $error_url = admin_url('admin.php?page=yandex-feed-mapping&error=empty_data');
                if (!headers_sent()) {
                    wp_safe_redirect($error_url);
                } else {
                    echo '<script>window.location.href = "' . esc_js($error_url) . '";</script>';
                    wp_die('Перенаправление...', 'Ошибка', array('response' => 200));
                }
                exit;
            }
            
            // Декодируем JSON
            $mapping_data = json_decode($mapping_json, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('YFGP ERROR: JSON decode error: ' . json_last_error_msg());
                // v4.18.11: Очистка output buffers перед redirect
                while (ob_get_level()) {
                    ob_end_clean();
                }
                $error_url = admin_url('admin.php?page=yandex-feed-mapping&error=invalid_json');
                if (!headers_sent()) {
                    wp_safe_redirect($error_url);
                } else {
                    echo '<script>window.location.href = "' . esc_js($error_url) . '";</script>';
                    wp_die('Перенаправление...', 'Ошибка', array('response' => 200));
                }
                exit;
            }
            
            // Валидация структуры данных
            if (!$this->validate_mapping_data($mapping_data)) {
                error_log('YFGP ERROR: Валидация данных провалена');
                // v4.18.11: Очистка output buffers перед redirect
                while (ob_get_level()) {
                    ob_end_clean();
                }
                $error_url = admin_url('admin.php?page=yandex-feed-mapping&error=validation_failed');
                if (!headers_sent()) {
                    wp_safe_redirect($error_url);
                } else {
                    echo '<script>window.location.href = "' . esc_js($error_url) . '";</script>';
                    wp_die('Перенаправление...', 'Ошибка', array('response' => 200));
                }
                exit;
            }
            
            // Санитизация и сохранение field mapping
            $fields = $this->sanitize_mapping_fields($mapping_data['fields'] ?? array());
            update_option('yfgp_field_mapping_v3', $fields, false);
            
            // v3.4.1: Обработка offer inheritance settings (NEW!)
            $settings = $this->sanitize_offer_settings($mapping_data['settings'] ?? array());
            if (!empty($settings)) {
                // Преобразовать flat array в структурированный offer_config
                $offer_config = $this->build_offer_config($settings);
                update_option('yfgp_offer_config', $offer_config, false);
                error_log('YFGP SAVE: ✅ SUCCESS! Saved ' . count($fields) . ' fields + offer config');
            } else {
                error_log('YFGP SAVE: ✅ SUCCESS! Saved ' . count($fields) . ' fields (no offer config)');
            }

            $current_settings = get_option('yfgp_settings', array());
            $current_context = $this->build_mapping_context($current_settings);
            update_option('yfgp_field_mapping_context', array_merge($current_context, array('updated_at' => time())), false);
            
            // v3.4.6: Используем TRANSIENT вместо URL параметра (WP Rocket убирает query params!)
            set_transient('yfgp_mapping_saved_' . get_current_user_id(), true, 30);
            $redirect_url = admin_url('admin.php?page=yandex-feed-mapping');
            error_log('YFGP SAVE: ✅ SUCCESS! Redirecting + transient set');
            
            // v4.18.11: Очистка всех output buffers перед redirect (предотвращение белого экрана)
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // v4.18.11: Проверка, что headers не отправлены
            if (headers_sent($file, $line)) {
                error_log("YFGP SAVE WARNING: Headers already sent in $file on line $line");
                // Если headers уже отправлены, используем JavaScript redirect
                echo '<script>window.location.href = "' . esc_js($redirect_url) . '";</script>';
                echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_url($redirect_url) . '"></noscript>';
                wp_die('Перенаправление...', 'Сохранение завершено', array('response' => 200));
            }
            
            // v4.18.11: Используем wp_safe_redirect() для безопасности
            wp_safe_redirect($redirect_url);
            exit;
        
        } catch (Exception $e) {
            error_log('YFGP SAVE FATAL ERROR: ' . $e->getMessage());
            error_log('YFGP SAVE FATAL ERROR Stack trace: ' . $e->getTraceAsString());
            wp_die(
                'Критическая ошибка при сохранении: ' . esc_html($e->getMessage()),
                'Ошибка сохранения',
                array('response' => 500, 'back_link' => true)
            );
        } catch (Throwable $e) {
            error_log('YFGP SAVE FATAL ERROR (Throwable): ' . $e->getMessage());
            error_log('YFGP SAVE FATAL ERROR Stack trace: ' . $e->getTraceAsString());
            wp_die(
                'Критическая ошибка при сохранении: ' . esc_html($e->getMessage()),
                'Ошибка сохранения',
                array('response' => 500, 'back_link' => true)
            );
        }
    }
    
    /**
     * Валидация структуры mapping data
     * 
     * @param array $data Decoded JSON data
     * @return bool
     */
    private function validate_mapping_data($data) {
        if (!is_array($data)) {
            error_log('YFGP VALIDATION: Данные не являются массивом');
            return false;
        }
        
        if (!isset($data['fields']) || !is_array($data['fields'])) {
            error_log('YFGP VALIDATION: Отсутствует ключ fields');
            return false;
        }
        
        foreach ($data['fields'] as $field_id => $field_data) {
            if (!is_array($field_data)) {
                error_log('YFGP VALIDATION: Поле ' . $field_id . ' не массив');
                return false;
            }
            
            // v4.10.1: Разрешаем пустой source_type (поле не настроено)
            // ИСПРАВЛЕНО: НЕ блокируем сохранение если source_type отсутствует
            // Поля могут иметь пустой source_type если еще не настроены
            if (!isset($field_data['source_type']) && !empty($field_data)) {
                // Логируем но НЕ блокируем
                error_log('YFGP VALIDATION: ⚠️ Поле ' . $field_id . ' без source_type (не настроено)');
            }
        }
        
        error_log('YFGP VALIDATION: ✅ Данные валидны! Полей: ' . count($data['fields']));
        return true;
    }
    
    /**
     * Санитизация field mapping данных
     * 
     * @param array $fields Field configurations
     * @return array Sanitized fields
     */
    private function sanitize_mapping_fields($fields) {
        $sanitized = array();
        
        if (!is_array($fields)) {
            return $sanitized;
        }
        
        foreach ($fields as $field_id => $field_data) {
            if (!is_array($field_data)) {
                continue;
            }
            
            $sanitized_field = array();
            
            foreach ($field_data as $key => $value) {
                if (is_string($value)) {
                    $sanitized_field[$key] = sanitize_text_field($value);
                } elseif (is_array($value)) {
                    // v4.18.20: Рекурсивная санитизация вложенных массивов (conditional_logic, nested_field и т.д.)
                    $sanitized_field[$key] = $this->sanitize_array_recursive($value, 0);
                } elseif (is_null($value)) {
                    $sanitized_field[$key] = null;
                } elseif (is_bool($value)) {
                    $sanitized_field[$key] = (bool) $value;
                } elseif (is_numeric($value)) {
                    $sanitized_field[$key] = $value;
                } else {
                    // Для других типов - сохраняем as is
                    $sanitized_field[$key] = $value;
                }
            }
            
              $sanitized[sanitize_key($field_id)] = $sanitized_field;
          }
          
          // v4.18.21: Автоматически добавляем calculate_type для полей с post_date
          if (isset($sanitized['experience_years']) && 
              isset($sanitized['experience_years']['source_field']) && 
              $sanitized['experience_years']['source_field'] === 'post_date' &&
              (empty($sanitized['experience_years']['source_type']) || $sanitized['experience_years']['source_type'] === '')) {
              $sanitized['experience_years']['calculate_type'] = 'date_to_years';
              error_log('YFGP SAVE: Added calculate_type=date_to_years for experience_years (post_date)');
          }
          
          if (isset($sanitized['career_start_date']) && 
              isset($sanitized['career_start_date']['source_field']) && 
              $sanitized['career_start_date']['source_field'] === 'post_date') {
              $sanitized['career_start_date']['calculate_type'] = 'date_as_is';
              error_log('YFGP SAVE: Added calculate_type=date_as_is for career_start_date (post_date)');
          }
          
          return $sanitized;
      }
      
      /**
       * Автоматическое добавление calculate_type при чтении маппинга
       * v4.18.21: Добавляет calculate_type для experience_years и career_start_date если отсутствует
       * 
       * @param mixed $value Значение опции
       * @return mixed Обновлённое значение
       */
      public function auto_add_calculate_type($value) {
          if (!is_array($value) || empty($value)) {
              return $value;
          }
          
          $updated = false;
          
          // Для experience_years: если source_field = post_date и source_type пустой, добавляем calculate_type = date_to_years
          if (isset($value['experience_years']) && is_array($value['experience_years'])) {
              $exp = &$value['experience_years'];
              if (isset($exp['source_field']) && $exp['source_field'] === 'post_date' &&
                  (empty($exp['source_type']) || $exp['source_type'] === '') &&
                  empty($exp['calculate_type'])) {
                  $exp['calculate_type'] = 'date_to_years';
                  $updated = true;
                  error_log('YFGP AUTO: Added calculate_type=date_to_years for experience_years (post_date)');
              }
          }
          
          // Для career_start_date: если source_field = post_date, добавляем calculate_type = date_as_is
          if (isset($value['career_start_date']) && is_array($value['career_start_date'])) {
              $career = &$value['career_start_date'];
              if (isset($career['source_field']) && $career['source_field'] === 'post_date' &&
                  empty($career['calculate_type'])) {
                  $career['calculate_type'] = 'date_as_is';
                  $updated = true;
                  error_log('YFGP AUTO: Added calculate_type=date_as_is for career_start_date (post_date)');
              }
          }
          
          // Если были изменения - сохраняем обновлённый маппинг
          if ($updated) {
              update_option('yfgp_field_mapping_v3', $value, false);
              error_log('YFGP AUTO: Updated mapping with calculate_type values');
          }
          
          return $value;
      }
      
    /**
     * Common AJAX request validation
     * 
     * @since 4.18.22: Refactoring - Common method for AJAX validation
     * @param string $nonce_action Nonce action name
     * @param string $nonce_name Nonce field name
     * @param string $capability Required capability
     * @return bool True if valid, false otherwise
     */
    protected function validate_ajax_request($nonce_action = 'yfgp_ajax_nonce', $nonce_name = 'nonce', $capability = 'manage_options'): bool {
        if (!check_ajax_referer($nonce_action, $nonce_name, false)) {
            $this->send_json_error_no_bom(array('message' => 'Invalid nonce'));
            return false;
        }
        
        if (!current_user_can($capability)) {
            $this->send_json_error_no_bom(array('message' => 'Insufficient permissions'));
            return false;
        }
        
        return true;
    }
    
    /**
     * Рекурсивная санитизация массива (для вложенных структур)
     * 
     * @since 4.18.20
     * @param array $array Массив для санитизации
     * @param int $depth Текущая глубина вложенности (для защиты от бесконечной рекурсии)
     * @return array Санитизированный массив
     */
    private function sanitize_array_recursive($array, $depth = 0) {
        // v4.18.22: Security - Limit recursion depth (max 10 levels)
        $max_depth = 10;
        if ($depth > $max_depth) {
            if (class_exists('YFGP_Logger')) {
                YFGP_Logger::get_instance()->warning('YFGP Security: Array nesting too deep (' . $depth . ' levels, max: ' . $max_depth . ')');
            } else {
                error_log('YFGP Security: Array nesting too deep (' . $depth . ' levels, max: ' . $max_depth . ')');
            }
            return array(); // Return empty array if depth exceeded
        }

        if (!is_array($array)) {
            return is_string($array) ? sanitize_text_field($array) : $array;
        }
        
        $sanitized = array();
        foreach ($array as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = sanitize_text_field($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitize_array_recursive($value, $depth + 1);
            } elseif (is_numeric($value)) {
                $sanitized[$key] = $value;
            } elseif (is_bool($value)) {
                $sanitized[$key] = $value;
            } elseif (is_null($value)) {
                $sanitized[$key] = null;
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Санитизация offer settings (inheritance mode)
     * 
     * @param array $settings Offer inheritance settings
     * @return array Sanitized settings
     */
    private function sanitize_offer_settings($settings) {
        $sanitized = array();
        
        if (!is_array($settings)) {
            return $sanitized;
        }
        
        foreach ($settings as $key => $value) {
            $sanitized_key = sanitize_key($key);
            
            if (is_string($value)) {
                $sanitized[$sanitized_key] = sanitize_text_field($value);
            } elseif (is_array($value)) {
                // v4.18.20: Рекурсивная санитизация массивов в settings
                $sanitized[$sanitized_key] = $this->sanitize_array_recursive($value);
            } elseif (is_numeric($value)) {
                $sanitized[$sanitized_key] = $value;
            } elseif (is_bool($value)) {
                $sanitized[$sanitized_key] = $value;
            } elseif (is_null($value)) {
                $sanitized[$sanitized_key] = null;
            }
            // Другие типы пропускаем
        }
        
        return $sanitized;
    }
    
    /**
     * Build offer_config структуру из flat settings array
     * v3.4.1: NEW - конвертирует {offer_url_mode: "inherit", ...} в структурированный config
     * 
     * @param array $settings Flat settings array
     * @return array Structured offer config
     */
    private function build_offer_config($settings) {
        $offer_config = array();
        
        if (!is_array($settings)) {
            return $offer_config;
        }
        
        // Parse offer settings (offer_url_mode, doctor_children_appointment_mode, price_base_price_mode и т.д.)
        foreach ($settings as $key => $value) {
            // v4.18.20: Пропускаем массивы - они не должны быть в offer config
            if (is_array($value)) {
                continue;
            }
            
            // Extract prefix и field_id из "offer_url_mode" → prefix="offer", field="url", type="mode"
            if (preg_match('/^(offer|doctor|price)_([^_]+(?:_[^_]+)*)_(mode|inherit_from)$/', $key, $matches)) {
                $prefix = $matches[1]; // "offer", "doctor", "price"
                $field = $matches[2];  // "url", "children_appointment", "base_price"
                $type = $matches[3];   // "mode" или "inherit_from"
                
                $config_key = $prefix . '_' . $field;
                
                if (!isset($offer_config[$config_key])) {
                    $offer_config[$config_key] = array();
                }
                
                $offer_config[$config_key][$type] = $value;
            }
        }
        
        return $offer_config;
    }

    /**
     * Получить маппинг legacy CPT ключей на новые универсальные ключи
     * 
     * @since 4.18.0
     * @return array Маппинг 'legacy_key' => 'new_key'
     */
    private function get_cpt_option_map() {
        return array(
            'clinics_post_type' => 'cpt_clinics',
            'services_post_type' => 'cpt_services',
            'reviews_post_type' => 'cpt_reviews',
        );
    }

    /**
     * Нормализация CPT slug (санитизация и trim)
     * 
     * @since 4.18.0
     * @param string $value CPT slug для нормализации
     * @return string Нормализованный CPT slug
     */
    private function normalize_cpt_slug($value) {
        if (empty($value)) {
            return '';
        }
        // v4.18.20: Защита от массивов - предотвращает "Array to string conversion"
        if (is_array($value)) {
            error_log('YFGP WARNING: normalize_cpt_slug received array instead of string: ' . print_r($value, true));
            return '';
        }
        if (!is_string($value) && !is_numeric($value)) {
            error_log('YFGP WARNING: normalize_cpt_slug received invalid type: ' . gettype($value));
            return '';
        }
        return sanitize_key(trim((string)$value));
    }

    /**
     * Миграция legacy CPT ключей на новые универсальные ключи
     * 
     * @since 4.18.0
     * @param array $settings Настройки плагина
     * @return array Настройки с мигрированными ключами
     */
    private function maybe_migrate_cpt_settings($settings) {
        $cpt_map = $this->get_cpt_option_map();
        
        // Мигрируем legacy ключи на новые
        foreach ($cpt_map as $legacy_key => $new_key) {
            if (isset($settings[$legacy_key]) && !isset($settings[$new_key])) {
                $settings[$new_key] = $this->normalize_cpt_slug($settings[$legacy_key]);
                // Удаляем legacy ключ после миграции
                unset($settings[$legacy_key]);
            }
        }
        
        // Нормализация base_service_mode: удаляем compatibility режим
        if (isset($settings['base_service_mode']) && $settings['base_service_mode'] === 'compatibility') {
            $settings['base_service_mode'] = 'automatic';
        }
        
        return $settings;
    }

    /**
     * Извлечение и нормализация выбранных CPT из настроек
     * 
     * @since 4.18.0
     * @param array $settings Настройки плагина
     * @return array Массив нормализованных CPT ['cpt_clinics' => slug, ...]
     */
    private function get_selected_cpts($settings) {
        $cpts = array();
        $cpt_keys = array('cpt_clinics', 'cpt_services'); // cpt_reviews removed - using source_reviews + source_reviews_cpt instead
        
        foreach ($cpt_keys as $key) {
            if (!empty($settings[$key])) {
                $cpts[$key] = $this->normalize_cpt_slug($settings[$key]);
            }
        }
        
        return $cpts;
    }

    /**
     * Мердж нормализованных CPT в настройки
     * 
     * @since 4.18.0
     * @param array $settings Настройки плагина
     * @return array Настройки с нормализованными CPT
     */
    private function with_normalized_cpts($settings) {
        $normalized_cpts = $this->get_selected_cpts($settings);
        return array_merge($settings, $normalized_cpts);
    }

    /**
     * Отображение admin notice если требуемые CPT не выбраны
     * 
     * @since 4.18.0
     * @param array $settings Настройки плагина
     * @return void
     */
    private function maybe_render_missing_cpt_notice($settings) {
        $missing = array();
        $cpt_labels = array(
            'cpt_clinics' => 'клиник',
            'cpt_services' => 'услуг'
            // cpt_reviews removed - using source_reviews + source_reviews_cpt instead
        );
        
        foreach ($cpt_labels as $key => $label) {
            if (empty($settings[$key])) {
                $missing[] = $label;
            }
        }
        
        if (!empty($missing)) {
            $message = 'Внимание: не выбран тип записи для ' . implode(', ', $missing) . '. Генерация фида может работать некорректно.';
            echo '<div class="notice notice-warning"><p>' . esc_html($message) . '</p></div>';
        }
    }

    private function build_mapping_context($settings) {
        return array(
            'post_type' => $this->normalize_cpt_slug($settings['post_type'] ?? 'doctors'),
            'cpt_clinics' => $this->normalize_cpt_slug($settings['cpt_clinics'] ?? ''),
            'cpt_services' => $this->normalize_cpt_slug($settings['cpt_services'] ?? ''),
            // cpt_reviews removed - using source_reviews + source_reviews_cpt instead
        );
    }

    private function detect_mapping_context_changes($previous_context, $current_context) {
        if (!is_array($previous_context) || empty($previous_context)) {
            return array();
        }

        $entities = array();

        if (($previous_context['post_type'] ?? '') !== ($current_context['post_type'] ?? '')) {
            $entities[] = 'doctors';
        }

        if (($previous_context['cpt_clinics'] ?? '') !== ($current_context['cpt_clinics'] ?? '')) {
            $entities[] = 'clinics';
        }

        if (($previous_context['cpt_services'] ?? '') !== ($current_context['cpt_services'] ?? '')) {
            $entities[] = 'services';
        }

        // cpt_reviews removed - using source_reviews + source_reviews_cpt instead

        return array_values(array_unique($entities));
    }

    private function reset_mapping_for_entities($mapping, $entities) {
        if (empty($entities) || empty($mapping) || !is_array($mapping)) {
            return $mapping;
        }

        $entities = array_unique($entities);

        foreach ($mapping as $field_id => $config) {
            $entity = $this->resolve_mapping_field_entity((string) $field_id);
            if (in_array($entity, $entities, true)) {
                unset($mapping[$field_id]);
            }
        }

        return $mapping;
    }

    private function resolve_mapping_field_entity($field_id) {
        if (strpos($field_id, 'clinics_') === 0) {
            return 'clinics';
        }

        if (strpos($field_id, 'services_') === 0 || strpos($field_id, 'prices_') === 0) {
            return 'services';
        }

        if (strpos($field_id, 'offer_') === 0 || strpos($field_id, 'doctor_') === 0 || strpos($field_id, 'price_') === 0) {
            return 'offers';
        }

        return 'doctors';
    }

    private function build_mapping_context_labels($context) {
        return array(
            'post_type' => $this->resolve_post_type_label($context['post_type'] ?? ''),
            'cpt_clinics' => $this->resolve_post_type_label($context['cpt_clinics'] ?? ''),
            'cpt_services' => $this->resolve_post_type_label($context['cpt_services'] ?? ''),
            // cpt_reviews removed - using source_reviews + source_reviews_cpt instead
        );
    }

    private function resolve_post_type_label($slug) {
        if (empty($slug)) {
            return '';
        }

        $post_type_object = get_post_type_object($slug);
        if ($post_type_object && !empty($post_type_object->label)) {
            return $post_type_object->label;
        }

        return '';
    }
}







