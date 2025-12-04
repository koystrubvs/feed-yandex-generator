<?php
/**
 * Admin AJAX Handlers для Yandex Feed Generator Pro
 * 
 * Вынесено из class-admin-page.php для модульности
 * 
 * @since 5.0.0
 * @package Yandex_Feed_Generator_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Класс для обработки Admin AJAX запросов
 */
class YFGP_Admin_Ajax_Handlers {
    
    /**
     * Инициализация AJAX хуков
     */
    public function __construct() {
        // CPT и поля
        add_action('wp_ajax_yfgp_get_available_cpts', array($this, 'ajax_get_available_cpts'));
        add_action('wp_ajax_yfgp_get_fields_v3', array($this, 'ajax_get_fields_v3'));
        add_action('wp_ajax_yfgp_get_cpts_v3', array($this, 'ajax_get_cpts_v3'));
        add_action('wp_ajax_yfgp_get_repeater_subfields', array($this, 'ajax_get_repeater_subfields'));
        
        // Специализации и услуги
        add_action('wp_ajax_yfgp_load_specialization_services_data', array($this, 'ajax_load_specialization_services_data'));
        add_action('wp_ajax_yfgp_save_specialization_services', array($this, 'ajax_save_specialization_services'));
        add_action('wp_ajax_yfgp_check_doctors_multiple_specializations', array($this, 'ajax_check_doctors_multiple_specializations'));
    }
    
    // ==========================================
    // CPT и поля
    // ==========================================
    
    /**
     * AJAX: Получить доступные CPT для связанных данных (education, jobs, etc)
     * 
     * @since 3.2.0
     */
    public function ajax_get_available_cpts(): void {
        if (!$this->validate_ajax_request()) {
            return;
        }
        
        try {
            $field_type = sanitize_text_field($_POST['field_type'] ?? '');
            
            if (!in_array($field_type, array('education', 'jobs', 'certificates', 'reviews', 'prices'))) {
                $this->send_json_error_no_bom(array('message' => 'Invalid field type'));
                return;
            }
            
            // Получить все зарегистрированные CPT
            $all_cpts = get_post_types(array('_builtin' => false), 'objects');
            
            // Исключить служебные типы
            $exclude = array('revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 
                            'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 
                            'wp_navigation', 'acf-field-group', 'acf-field');
            
            $available_cpts = array();
            $recommended_slug = null;
            $saved_slug = '';

            $settings = get_option('yfgp_settings', array());
            if (!empty($settings['source_' . $field_type . '_cpt'])) {
                $saved_slug = sanitize_key($settings['source_' . $field_type . '_cpt']);
            }

            foreach ($all_cpts as $cpt) {
                if (in_array($cpt->name, $exclude)) {
                    continue;
                }
                
                // Получить количество записей
                $count = wp_count_posts($cpt->name);
                $total = $count->publish ?? 0;
                
                // Определить рекомендуемый CPT (по имени)
                $is_recommended = false;
                if ($field_type === 'education' && in_array($cpt->name, array('education', 'education_cpt', 'doctor_education'))) {
                    $is_recommended = true;
                    $recommended_slug = $cpt->name;
                } elseif ($field_type === 'jobs' && in_array($cpt->name, array('jobs', 'jobs_cpt', 'doctor_jobs', 'work_history'))) {
                    $is_recommended = true;
                    $recommended_slug = $cpt->name;
                } elseif ($field_type === 'certificates' && in_array($cpt->name, array('certificates', 'certificates_cpt', 'doctor_certificates', 'certs'))) {
                    $is_recommended = true;
                    $recommended_slug = $cpt->name;
                } elseif ($field_type === 'reviews' && in_array($cpt->name, array('reviews', 'review', 'doctor_reviews', 'отзывы'))) {
                    $is_recommended = true;
                    $recommended_slug = $cpt->name;
                } elseif ($field_type === 'prices' && in_array($cpt->name, array('prices', 'price', 'service_prices', 'цены'))) {
                    $is_recommended = true;
                    $recommended_slug = $cpt->name;
                }
                
                // Сохраняем выделение для dropdown
                $is_selected = ($saved_slug === $cpt->name);
                
                $available_cpts[] = array(
                    'slug' => $cpt->name,
                    'label' => $cpt->label,
                    'count' => $total,
                    'recommended' => $is_recommended,
                    'selected' => $is_selected
                );
            }
            
            $status = array(
                'found' => !empty($available_cpts),
                'cpt_slug' => $recommended_slug,
                'count' => null,
                'message' => ''
            );
            $status_slug = $recommended_slug ?: $saved_slug;

            if ($status_slug) {
                // Подставляем количество записей для текущего выбранного CPT
                foreach ($available_cpts as $cpt) {
                    if ($cpt['slug'] === $status_slug) {
                        $status['count'] = $cpt['count'];
                        break;
                    }
                }
                $status['cpt_slug'] = $status_slug;
            } else {
                $status['message'] = 'CPT не найден. Переключите на Repeater field или добавьте CPT в WordPress.';
            }
            
            $this->send_json_success_no_bom(array(
                'cpts' => $available_cpts,
                'status' => $status
            ));
        } catch (Exception $e) {
            $error_handler = YFGP_Error_Handler::get_instance();
            $error_handler->handle_ajax_error($e, array('action' => 'ajax_get_available_cpts'));
        } catch (Throwable $e) {
            $error_handler = YFGP_Error_Handler::get_instance();
            $error_handler->handle_ajax_error($e, array('action' => 'ajax_get_available_cpts'));
        }
    }
    
    /**
     * AJAX: Получить доступные поля для Dynamic Field Selector V3
     * 
     * @since 3.0.0
     */
    public function ajax_get_fields_v3(): void {
        if (!$this->validate_ajax_request()) {
            return;
        }
        
        $post_type = sanitize_text_field($_POST['post_type'] ?? 'doctors');
        
        // v5.0.0: Use Unified mapper instead of V3
        if (!class_exists('YFGP_Field_Mapper_Unified')) {
            require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
        }
        
        $field_mapper = YFGP_Field_Mapper_Unified::get_instance();
        $available_fields = $field_mapper->get_available_fields($post_type);
        
        $this->send_json_success_no_bom($available_fields);
    }
    
    /**
     * AJAX: Получить доступные CPT для Dynamic Field Selector V3
     * 
     * @since 3.0.0
     */
    public function ajax_get_cpts_v3(): void {
        if (!$this->validate_ajax_request()) {
            return;
        }
        
        try {
            // Получить все кастомные CPT (включая непубличные как reviews, price)
            $cpts = get_post_types(array(
                '_builtin' => false
            ), 'objects');
            
            $result = array();
            
            foreach ($cpts as $cpt) {
                // Получить количество записей
                $count = wp_count_posts($cpt->name);
                $total = 0;
                
                if (isset($count->publish)) {
                    $total += intval($count->publish);
                }
                
                $result[$cpt->name] = array(
                    'label' => $cpt->label,
                    'slug' => $cpt->name,
                    'count' => $total,
                );
            }
            
            $this->send_json_success_no_bom($result);
        } catch (Exception $e) {
            $error_handler = YFGP_Error_Handler::get_instance();
            $error_handler->handle_ajax_error($e, array('action' => 'ajax_get_cpts_v3'));
        } catch (Throwable $e) {
            $error_handler = YFGP_Error_Handler::get_instance();
            $error_handler->handle_ajax_error($e, array('action' => 'ajax_get_cpts_v3'));
        }
    }
    
    /**
     * AJAX: Получить подполя repeater поля (ACF или JetEngine)
     * 
     * @since 3.2.5
     */
    public function ajax_get_repeater_subfields(): void {
        if (!$this->validate_ajax_request()) {
            return;
        }
        
        // Get and sanitize parameters
        $repeater_field_name = sanitize_text_field($_POST['repeater_field_name'] ?? '');
        $source_type = sanitize_text_field($_POST['source_type'] ?? '');
        $post_type = sanitize_text_field($_POST['post_type'] ?? 'doctors');
        
        // Validate input
        if (empty($repeater_field_name)) {
            $this->send_json_error_no_bom(array('message' => 'Missing repeater_field_name parameter'));
            return;
        }
        
        if (empty($source_type)) {
            $this->send_json_error_no_bom(array('message' => 'Missing source_type parameter'));
            return;
        }
        
        // v5.0.0: Use Unified mapper instead of V3
        if (!class_exists('YFGP_Field_Mapper_Unified')) {
            require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
        }
        
        $field_mapper = YFGP_Field_Mapper_Unified::get_instance();
        
        // Get subfields based on source type
        $subfields = array();
        
        try {
            if ($source_type === 'repeater_acf') {
                $subfields = $field_mapper->get_acf_repeater_subfields($repeater_field_name);
            } elseif ($source_type === 'repeater_jetengine') {
                $subfields = $field_mapper->get_jetengine_repeater_subfields($repeater_field_name, $post_type);
            } else {
                $this->send_json_error_no_bom(array('message' => 'Unsupported source_type: ' . $source_type));
                return;
            }
            
            // Convert associative array to indexed array for JavaScript
            $indexed_subfields = array();
            foreach ($subfields as $slug => $field_data) {
                $indexed_subfields[] = array(
                    'slug' => $slug,
                    'label' => $field_data['label'] ?? $slug,
                    'type' => $field_data['type'] ?? 'text'
                );
            }
            
            error_log(sprintf(
                'YFGP v5.0.0: Loaded %d subfields for %s (%s)',
                count($indexed_subfields),
                $repeater_field_name,
                $source_type
            ));
            
            $this->send_json_success_no_bom($indexed_subfields);
            
        } catch (Exception $e) {
            error_log('YFGP v5.0.0: Error loading repeater subfields: ' . $e->getMessage());
            $this->send_json_error_no_bom(array('message' => $e->getMessage()));
        }
    }
    
    // ==========================================
    // Специализации и услуги
    // ==========================================
    
    /**
     * AJAX: Загрузка данных для ручных настроек услуг по специализациям
     * 
     * @since 4.20.1
     */
    public function ajax_load_specialization_services_data() {
        check_ajax_referer('yfgp_ajax_nonce', 'nonce');
        
        $post_type = sanitize_text_field($_POST['post_type'] ?? '');
        if (empty($post_type)) {
            wp_send_json_error('Post type не указан');
            return;
        }
        
        $mapping = get_option('yfgp_field_mapping_v3', array());
        if (empty($mapping['specialities'])) {
            wp_send_json_success(array('status' => 'no_mapping'));
            return;
        }
        
        $doctors = $this->get_doctors_with_multiple_specializations($post_type, $mapping);
        
        if (empty($doctors)) {
            wp_send_json_success(array('status' => 'no_doctors'));
            return;
        }
        
        $saved_settings = get_option('yfgp_doctor_specialization_services_map', array());
        
        wp_send_json_success(array(
            'status' => 'success',
            'doctors' => $doctors,
            'saved_settings' => $saved_settings,
        ));
    }
    
    /**
     * AJAX: Сохранение ручных настроек услуг по специализациям
     * 
     * @since 4.20.1
     */
    public function ajax_save_specialization_services() {
        check_ajax_referer('yfgp_ajax_nonce', 'nonce');
        
        $settings_json = $_POST['settings'] ?? '';
        if (empty($settings_json)) {
            wp_send_json_error('Настройки не переданы');
            return;
        }
        
        $settings = json_decode(stripslashes($settings_json), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Ошибка декодирования JSON: ' . json_last_error_msg());
            return;
        }
        
        $normalized_settings = array();
        foreach ($settings as $doctor_key => $specializations) {
            if (!is_array($specializations)) {
                continue;
            }
            $normalized_settings[$doctor_key] = array();
            foreach ($specializations as $specialization_slug => $service_id) {
                // v4.20.3: Сохранить 'base_service' как есть (для использования базовой услуги из настроек)
                if ($service_id === 'base_service') {
                    $normalized_settings[$doctor_key][$specialization_slug] = 'base_service';
                } else {
                    // Убрать префикс 'service_' для обычных услуг
                    $service_id_clean = str_replace('service_', '', $service_id);
                    $normalized_settings[$doctor_key][$specialization_slug] = $service_id_clean;
                }
            }
        }
        
        update_option('yfgp_doctor_specialization_services_map', $normalized_settings);
        
        wp_send_json_success('Настройки сохранены');
    }
    
    /**
     * AJAX: Проверка количества врачей с несколькими специализациями
     * 
     * @since 4.20.1
     */
    public function ajax_check_doctors_multiple_specializations() {
        check_ajax_referer('yfgp_ajax_nonce', 'nonce');
        
        $post_type = sanitize_text_field($_POST['post_type'] ?? '');
        if (empty($post_type)) {
            wp_send_json_error('Post type не указан');
            return;
        }
        
        $mapping = get_option('yfgp_field_mapping_v3', array());
        $doctors = $this->get_doctors_with_multiple_specializations($post_type, $mapping);
        
        wp_send_json_success(array('count' => count($doctors)));
    }
    
    // ==========================================
    // Helper методы
    // ==========================================
    
    /**
     * Получить врачей с несколькими специализациями
     * 
     * @param string $post_type Post type врачей
     * @param array $mapping Маппинг полей
     * @return array Массив врачей с их специализациями
     */
    private function get_doctors_with_multiple_specializations($post_type, $mapping) {
        $doctors = array();
        
        if (empty($mapping['specialities'])) {
            return $doctors;
        }
        
        $specialities_config = $mapping['specialities'];
        $specialities_source = $specialities_config['source_type'] ?? '';
        
        if (empty($specialities_source)) {
            return $doctors;
        }
        
        // Получить настройки для базовой услуги
        $settings = get_option('yfgp_settings', array());
        $services_cpt = $settings['cpt_services'] ?? 'services';
        $default_service_name = $settings['default_service_name'] ?? 'Первичный прием';
        
        // Загрузить Unified Field Mapper
        if (!class_exists('YFGP_Field_Mapper_Unified')) {
            require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
        }
        $mapper = YFGP_Field_Mapper_Unified::get_instance();
        
        $posts = get_posts(array(
            'post_type' => $post_type,
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ));
        
        foreach ($posts as $post) {
            // Использовать Unified Field Mapper для извлечения специализаций
            $specializations_raw = $mapper->getFieldValue($post->ID, $specialities_config);
            
            // Преобразовать результат в массив специализаций
            $specializations = $this->normalize_specializations($specializations_raw, $specialities_source, $specialities_config);
            
            // Проверить что есть 2+ специализации
            if (count($specializations) >= 2) {
                // Получить список услуг для этого врача
                $doctor_services = $this->get_doctor_services($post->ID, $mapping, $services_cpt, $default_service_name);
                
                $doctors[] = array(
                    'id' => $post->ID,
                    'name' => $post->post_title,
                    'specializations' => $specializations,
                    'services' => $doctor_services,
                );
            }
        }
        
        return $doctors;
    }
    
    /**
     * Нормализация специализаций из разных источников
     * 
     * @param mixed $raw_value Сырое значение
     * @param string $source_type Тип источника
     * @param array $config Конфигурация поля
     * @return array Нормализованный массив специализаций
     */
    private function normalize_specializations($raw_value, $source_type, $config) {
        $specializations = array();
        
        if (empty($raw_value)) {
            return $specializations;
        }
        
        // Для таксономий
        if ($source_type === 'taxonomy') {
            $taxonomy_slug = $config['source_field'] ?? '';
            if (empty($taxonomy_slug)) {
                return $specializations;
            }
            
            if (is_array($raw_value) && !empty($raw_value)) {
                $first_item = reset($raw_value);
                if (is_object($first_item) && isset($first_item->slug)) {
                    // Массив объектов WP_Term
                    foreach ($raw_value as $term) {
                        if (is_object($term) && isset($term->slug)) {
                            $specializations[] = array(
                                'slug' => $term->slug,
                                'text' => $term->name ?? $term->slug,
                            );
                        }
                    }
                } elseif (is_string($first_item)) {
                    // Массив slug'ов
                    $terms = get_terms(array(
                        'taxonomy' => $taxonomy_slug,
                        'slug' => $raw_value,
                        'hide_empty' => false,
                    ));
                    
                    if (!is_wp_error($terms) && !empty($terms)) {
                        foreach ($terms as $term) {
                            $specializations[] = array(
                                'slug' => $term->slug,
                                'text' => $term->name,
                            );
                        }
                    } else {
                        foreach ($raw_value as $slug) {
                            if (is_string($slug)) {
                                $specializations[] = array(
                                    'slug' => $slug,
                                    'text' => $slug,
                                );
                            }
                        }
                    }
                }
            }
        }
        // Для meta_field (checkbox, select multiple и т.д.)
        elseif ($source_type === 'meta_field') {
            $field_name = $config['source_field'] ?? '';
            $field_options = array();
            
            if (!class_exists('YFGP_Field_Mapper_Unified')) {
                require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
            }
            $mapper = YFGP_Field_Mapper_Unified::get_instance();
            
            // Попробовать получить choices из ACF
            if (function_exists('acf_get_field') && !empty($field_name)) {
                $acf_field = acf_get_field($field_name);
                if ($acf_field && isset($acf_field['choices']) && is_array($acf_field['choices'])) {
                    $field_options = $acf_field['choices'];
                }
            }
            
            // Если не нашли в ACF, попробовать JetEngine
            if (empty($field_options) && class_exists('Jet_Engine') && !empty($field_name)) {
                $settings = get_option('yfgp_settings', array());
                $post_type = $settings['post_type'] ?? 'doctors';
                $field_options = $mapper->getJetengineFieldOptions($field_name, $post_type);
            }
            
            // Если это массив значений
            if (is_array($raw_value)) {
                $specializations = $this->process_meta_field_array($raw_value, $field_options);
            }
            // Если это строка (сериализованная)
            elseif (is_string($raw_value) && !empty($raw_value)) {
                $parsed = maybe_unserialize($raw_value);
                if (is_array($parsed)) {
                    $specializations = $this->process_meta_field_array($parsed, $field_options);
                }
            }
        }
        // Для других типов полей
        else {
            if (is_array($raw_value)) {
                foreach ($raw_value as $value) {
                    if (is_string($value) || is_numeric($value)) {
                        $slug = is_string($value) ? sanitize_title($value) : (string)$value;
                        $specializations[] = array(
                            'slug' => $slug,
                            'text' => is_string($value) ? $value : (string)$value,
                        );
                    }
                }
            }
        }
        
        return $specializations;
    }
    
    /**
     * Обработка массива meta_field для специализаций
     * 
     * @param array $raw_value Сырой массив
     * @param array $field_options Опции поля (choices)
     * @return array Массив специализаций
     */
    private function process_meta_field_array($raw_value, $field_options) {
        $specializations = array();
        
        if (empty($raw_value)) {
            return $specializations;
        }
        
        // Проверить является ли это ассоциативным массивом checkbox
        $is_associative = false;
        $has_boolean_values = false;
        
        $first_key = array_key_first($raw_value);
        $first_value = $raw_value[$first_key];
        
        $is_associative = !is_numeric($first_key) && (
            $first_value === true || $first_value === false || 
            $first_value === 'true' || $first_value === 'false' ||
            $first_value === '1' || $first_value === '0'
        );
        $has_boolean_values = $is_associative;
        
        // Если это ассоциативный массив checkbox
        if ($is_associative && $has_boolean_values) {
            foreach ($raw_value as $spec_key => $spec_value) {
                // Фильтровать только true значения
                if ($spec_value === false || $spec_value === null || $spec_value === '' || 
                    $spec_value === 'false' || $spec_value === '0' || $spec_value === 0) {
                    continue;
                }
                
                if (is_string($spec_key) || is_numeric($spec_key)) {
                    $label = $field_options[$spec_key] ?? $spec_key;
                    $slug = is_string($spec_key) ? sanitize_title($spec_key) : (string)$spec_key;
                    $specializations[] = array(
                        'slug' => $slug,
                        'text' => is_string($label) ? $label : (string)$spec_key,
                    );
                }
            }
        } else {
            // Обычный массив значений
            foreach ($raw_value as $value) {
                if ($value === false || $value === null || $value === '' || $value === 'false' || $value === '0') {
                    continue;
                }
                
                if (is_string($value) || is_numeric($value)) {
                    $label = $field_options[$value] ?? $value;
                    $slug = is_string($value) ? sanitize_title($value) : (string)$value;
                    $specializations[] = array(
                        'slug' => $slug,
                        'text' => is_string($label) ? $label : (string)$value,
                    );
                } elseif (is_array($value) && isset($value['value'])) {
                    $value_key = $value['value'];
                    if ($value_key === false || $value_key === null || $value_key === '' || $value_key === 'false') {
                        continue;
                    }
                    
                    $slug = is_string($value_key) ? sanitize_title($value_key) : (string)$value_key;
                    $text = $value['label'] ?? $field_options[$value_key] ?? $value_key ?? $slug;
                    $specializations[] = array(
                        'slug' => $slug,
                        'text' => is_string($text) ? $text : (string)$slug,
                    );
                }
            }
        }
        
        return $specializations;
    }
    
    /**
     * Получить список услуг для врача
     * 
     * @param int $doctor_id ID врача
     * @param array $mapping Маппинг полей
     * @param string $services_cpt Post type услуг
     * @param string $default_service_name Название базовой услуги из настроек
     * @return array Массив услуг
     */
    private function get_doctor_services($doctor_id, $mapping, $services_cpt, $default_service_name) {
        $services = array();
        
        // Добавить опцию "Использовать базовую услугу из настроек"
        $services[] = array(
            'id' => 'base_service',
            'name' => '— Использовать базовую услугу (' . esc_html($default_service_name) . ') —',
            'group' => '',
        );
        
        // Загрузить Unified Field Mapper
        if (!class_exists('YFGP_Field_Mapper_Unified')) {
            require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
        }
        $mapper = YFGP_Field_Mapper_Unified::get_instance();
        
        // Получить связанные услуги через маппинг
        $related_services = array();
        if (!empty($mapping['services'])) {
            $services_config = $mapping['services'];
            $related_services_raw = $mapper->getFieldValue($doctor_id, $services_config);
            
            if (!empty($related_services_raw)) {
                if (!is_array($related_services_raw)) {
                    $related_services_raw = array($related_services_raw);
                }
                
                foreach ($related_services_raw as $service_item) {
                    $service_id = is_object($service_item) ? $service_item->ID : $service_item;
                    if (is_numeric($service_id)) {
                        $related_services[] = intval($service_id);
                    }
                }
            }
        }
        
        // Получить все услуги
        $all_posts = get_posts(array(
            'post_type' => $services_cpt,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ));
        
        // Если есть связанные услуги - разделить на группы
        if (!empty($related_services)) {
            $related_posts = array();
            $all_posts_filtered = array();
            
            foreach ($all_posts as $post) {
                if (in_array($post->ID, $related_services)) {
                    $related_posts[] = $post;
                } else {
                    $all_posts_filtered[] = $post;
                }
            }
            
            // Добавить связанные услуги
            foreach ($related_posts as $post) {
                $services[] = array(
                    'id' => 'service_' . $post->ID,
                    'name' => $post->post_title,
                    'group' => 'Связанные услуги',
                );
            }
            
            // Добавить все остальные услуги
            foreach ($all_posts_filtered as $post) {
                $services[] = array(
                    'id' => 'service_' . $post->ID,
                    'name' => $post->post_title,
                    'group' => 'Все услуги',
                );
            }
        } else {
            // Если нет связанных услуг - показать все услуги без групп
            foreach ($all_posts as $post) {
                $services[] = array(
                    'id' => 'service_' . $post->ID,
                    'name' => $post->post_title,
                    'group' => '',
                );
            }
        }
        
        return $services;
    }
    
    /**
     * Валидация AJAX запроса
     * 
     * @param string $nonce_action Nonce action
     * @param string $nonce_name Nonce field name
     * @param string $capability Required capability
     * @return bool
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
     * Wrapper для wp_send_json_success с удалением BOM
     */
    private function send_json_success_no_bom($data = null): void {
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        $json = wp_json_encode(array('success' => true, 'data' => $data));
        
        // Удаление BOM
        while (strlen($json) > 0 && substr($json, 0, 3) === "\xEF\xBB\xBF") {
            $json = substr($json, 3);
        }
        
        header('Content-Type: application/json; charset=utf-8');
        echo $json;
        wp_die();
    }
    
    /**
     * Wrapper для wp_send_json_error с удалением BOM
     */
    private function send_json_error_no_bom($data = null): void {
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        $json = wp_json_encode(array('success' => false, 'data' => $data));
        
        // Удаление BOM
        while (strlen($json) > 0 && substr($json, 0, 3) === "\xEF\xBB\xBF") {
            $json = substr($json, 3);
        }
        
        header('Content-Type: application/json; charset=utf-8');
        echo $json;
        wp_die();
    }
}

