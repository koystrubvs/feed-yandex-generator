<?php
/**
 * AJAX Handlers - вынесены из главного файла плагина
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 5.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class YFGP_Ajax_Handlers {
    
    /**
     * Ссылка на главный класс плагина
     * @var Yandex_Feed_Generator_Pro
     */
    private $plugin;
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance($plugin = null) {
        if (null === self::$instance) {
            self::$instance = new self($plugin);
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct($plugin = null) {
        $this->plugin = $plugin;
        $this->init_hooks();
    }
    
    /**
     * Регистрация AJAX хуков
     */
    private function init_hooks() {
        add_action('wp_ajax_yfgp_generate_feed', array($this, 'ajax_generate_feed'));
        add_action('wp_ajax_yfgp_get_fields', array($this, 'ajax_get_fields'));
        add_action('wp_ajax_yfgp_save_mapping', array($this, 'ajax_save_mapping'));
        add_action('wp_ajax_yfgp_get_mapping', array($this, 'ajax_get_mapping'));
        add_action('wp_ajax_yfgp_get_posts_list', array($this, 'ajax_get_posts_list'));
        add_action('wp_ajax_yfgp_test_mapping', array($this, 'ajax_test_mapping'));
        add_action('wp_ajax_yfgp_save_edited_xml', array($this, 'ajax_save_edited_xml'));
        add_action('wp_ajax_yfgp_export_config', array($this, 'ajax_export_config'));
        add_action('wp_ajax_yfgp_import_config', array($this, 'ajax_import_config'));
        add_action('wp_ajax_yfgp_apply_template', array($this, 'ajax_apply_template'));
        add_action('wp_ajax_yfgp_get_terms', array($this, 'ajax_get_terms'));
        add_action('wp_ajax_yfgp_validate_feed', array($this, 'ajax_validate_feed'));
    }
    
    // ========================================================================
    // HELPER METHODS
    // ========================================================================
    
    /**
     * Wrapper для wp_send_json_success с удалением BOM
     */
    private function send_json_success_no_bom($data = null) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        $json = wp_json_encode(array('success' => true, 'data' => $data));
        
        // Удаляем ВСЕ BOM подряд
        while (strlen($json) > 0 && (
            substr($json, 0, 3) === "\xEF\xBB\xBF" ||
            (strlen($json) > 0 && ord($json[0]) === 0xEF && ord($json[1]) === 0xBB && ord($json[2]) === 0xBF)
        )) {
            $json = substr($json, 3);
        }
        
        // Также удаляем Unicode BOM (U+FEFF)
        while (strlen($json) > 0 && (
            mb_substr($json, 0, 1, 'UTF-8') === "\xEF\xBB\xBF" ||
            (function_exists('mb_ord') && mb_ord(mb_substr($json, 0, 1, 'UTF-8'), 'UTF-8') === 0xFEFF)
        )) {
            $json = mb_substr($json, 1, null, 'UTF-8');
        }
        
        header('Content-Type: application/json; charset=utf-8');
        echo $json;
        wp_die();
    }
    
    /**
     * Wrapper для wp_send_json_error с удалением BOM
     */
    private function send_json_error_no_bom($data = null) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        $json = wp_json_encode(array('success' => false, 'data' => $data));
        
        // Удаляем ВСЕ BOM подряд
        while (strlen($json) > 0 && (
            substr($json, 0, 3) === "\xEF\xBB\xBF" ||
            (strlen($json) > 0 && ord($json[0]) === 0xEF && ord($json[1]) === 0xBB && ord($json[2]) === 0xBF)
        )) {
            $json = substr($json, 3);
        }
        
        // Также удаляем Unicode BOM (U+FEFF)
        while (strlen($json) > 0 && (
            mb_substr($json, 0, 1, 'UTF-8') === "\xEF\xBB\xBF" ||
            (function_exists('mb_ord') && mb_ord(mb_substr($json, 0, 1, 'UTF-8'), 'UTF-8') === 0xFEFF)
        )) {
            $json = mb_substr($json, 1, null, 'UTF-8');
        }
        
        header('Content-Type: application/json; charset=utf-8');
        echo $json;
        wp_die();
    }
    
    /**
     * Определить post_type для таба
     */
    private function get_post_type_for_tab($tab_type, $settings) {
        switch ($tab_type) {
            case 'doctors':
                return $settings['post_type'] ?? yfgp_get_default_post_type_safe();
            
            case 'clinics':
                return $settings['cpt_clinics'] ?? '';
            
            case 'services':
                return $settings['cpt_services'] ?? '';
            
            case 'offers':
                return $settings['post_type'] ?? yfgp_get_default_post_type_safe();
            
            default:
                return yfgp_get_default_post_type_safe();
        }
    }
    
    /**
     * Получить ссылку на главный класс плагина
     */
    private function get_plugin() {
        if ($this->plugin === null) {
            $this->plugin = Yandex_Feed_Generator_Pro::get_instance();
        }
        return $this->plugin;
    }
    
    // ========================================================================
    // AJAX HANDLERS
    // ========================================================================
    
    /**
     * AJAX: Генерация фида
     */
    public function ajax_generate_feed() {
        $error_handler = YFGP_Error_Handler::get_instance();
        
        try {
            check_ajax_referer('yfgp_ajax_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                $error_handler->handle_ajax_error('Недостаточно прав', array('action' => 'ajax_generate_feed'));
                return;
            }
            
            // Security - Whitelisting для post_type
            $settings = get_option('yfgp_settings', array());
            $allowed_post_types = array();

            if (!empty($settings['post_type'])) {
                $allowed_post_types[] = sanitize_key($settings['post_type']);
            }
            if (!empty($settings['cpt_clinics'])) {
                $allowed_post_types[] = sanitize_key($settings['cpt_clinics']);
            }
            if (!empty($settings['cpt_services'])) {
                $allowed_post_types[] = sanitize_key($settings['cpt_services']);
            }

            if (empty($allowed_post_types)) {
                $allowed_post_types = array('doctors', 'clinics', 'services');
            }

            $post_type = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : 'doctors';

            if (!in_array($post_type, $allowed_post_types, true)) {
                if (class_exists('YFGP_Logger')) {
                    YFGP_Logger::get_instance()->warning('YFGP Security: Invalid post_type attempted: ' . $post_type);
                }
                $error_handler->handle_ajax_error('Invalid post type: ' . esc_html($post_type), array('action' => 'ajax_generate_feed'));
                return;
            }

            $preview_only = isset($_POST['preview_only']) && $_POST['preview_only'] === 'true';
            
            $feed_format = $settings['feed_format'] ?? 'v2';
            
            if ($feed_format === 'v2') {
                $generator = new YFGP_Feed_Generator_V2();
            } else {
                $generator = new YFGP_Feed_Generator();
            }
            
            $yml = $generator->generate($post_type);
            
            if (!$preview_only) {
                $feed_result = $this->get_plugin()->save_feed_file($yml, 'doctors.yml');
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
        check_ajax_referer('yfgp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            $this->send_json_error_no_bom('Недостаточно прав');
        }
        
        $post_type = sanitize_text_field($_POST['post_type'] ?? '');
        
        $mapper = new YFGP_Field_Mapper_V2();
        $fields = $mapper->get_available_fields($post_type);
        
        $this->send_json_success_no_bom($fields);
    }
    
    /**
     * AJAX: Сохранение маппинга
     */
    public function ajax_save_mapping() {
        check_ajax_referer('yfgp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
        }
        
        $mapping_raw = $_POST['mapping'] ?? array();
        $mapping_raw = wp_unslash($mapping_raw);
        
        if (!is_array($mapping_raw)) {
            wp_send_json_error('Invalid mapping data type');
            return;
        }
        
        $mapping = yfgp_sanitize_mapping_array($mapping_raw);

        // Security - Check size limit
        $mapping_json = json_encode($mapping);
        $max_size = class_exists('YFGP_Constants') ? YFGP_Constants::MAX_MAPPING_SIZE : 1048576;
        
        if (strlen($mapping_json) > $max_size) {
            if (class_exists('YFGP_Logger')) {
                YFGP_Logger::get_instance()->warning('YFGP Security: Mapping data too large: ' . strlen($mapping_json) . ' bytes');
            }
            wp_send_json_error('Размер данных маппинга превышает максимальный лимит (' . round($max_size / 1024 / 1024, 2) . ' MB)');
            return;
        }

        // Validate mapping configuration
        if (class_exists('YFGP_Mapping_Config_Validator')) {
            $validator = new YFGP_Mapping_Config_Validator();
            if (!$validator->validate_mapping_array($mapping)) {
                if (class_exists('YFGP_Logger')) {
                    YFGP_Logger::get_instance()->error('YFGP Security: Mapping validation failed');
                }
                wp_send_json_error('Ошибка валидации маппинга. Проверьте конфигурацию полей.');
                return;
            }
        }

        // Sanitize mapping array
        if (class_exists('YFGP_Data_Sanitizer')) {
            $sanitizer = new YFGP_Data_Sanitizer();
            $mapping = $sanitizer->sanitize($mapping);
        }

        $option_name = class_exists('YFGP_Constants') ? YFGP_Constants::OPTION_MAPPING : 'yfgp_field_mapping_v3';
        update_option($option_name, $mapping);
        
        wp_send_json_success('Маппинг сохранён');
    }
    
    /**
     * AJAX: Получение текущего маппинга
     */
    public function ajax_get_mapping() {
        check_ajax_referer('yfgp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
        }
        
        $mapping = get_option('yfgp_field_mapping', array());
        
        wp_send_json_success($mapping);
    }
    
    /**
     * AJAX: Получить список постов для Test Preview
     */
    public function ajax_get_posts_list() {
        check_ajax_referer('yfgp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
        }
        
        $tab_type = sanitize_text_field($_POST['tab_type'] ?? yfgp_get_default_post_type_safe());
        $settings = get_option('yfgp_settings', array());
        $post_type = $this->get_post_type_for_tab($tab_type, $settings);
        
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
     * AJAX: Тестирование маппинга
     */
    public function ajax_test_mapping() {
        set_time_limit(60);
        ini_set('max_execution_time', 60);
        
        check_ajax_referer('yfgp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
        }
        
        try {
            $settings = get_option('yfgp_settings', array());
            $tab_type = sanitize_text_field($_POST['tab_type'] ?? yfgp_get_default_post_type_safe());
            $post_type = $this->get_post_type_for_tab($tab_type, $settings);
            $post_id = intval($_POST['post_id'] ?? 0);
            
            if ($post_id > 0) {
                $post = get_post($post_id);
                if (!$post) {
                    wp_send_json_error('Пост не найден');
                }
                if ($post->post_type !== $post_type) {
                    wp_send_json_error('Неправильный тип поста. Ожидается: ' . $post_type . ', получен: ' . $post->post_type);
                }
            } else {
                wp_send_json_error('Пожалуйста, выберите пост из списка для тестирования');
            }
            
            $result = array(
                'posts_found' => (int) wp_count_posts($post_type)->publish,
                'clinics_found' => 0,
                'services_found' => 0,
                'sample_post' => null
            );
            
            if (!class_exists('YFGP_Field_Mapper_Unified')) {
                require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
            }
            
            $mapper_v3 = YFGP_Field_Mapper_Unified::get_instance();
            $mapping = get_option('yfgp_field_mapping_v3', array());
            if (!is_array($mapping)) {
                $mapping = array();
            }
            
            // Collect data using V3 API
            $data = array();
            $processed = 0;
            $start_time = microtime(true);
            
            foreach ($mapping as $field_id => $field_config) {
                if (empty($field_config['source_type'])) {
                    continue;
                }
                
                try {
                    $value = $mapper_v3->get_field_value($post->ID, $field_config);
                    $data[$field_id] = $value;
                    $processed++;
                } catch (Exception $e) {
                    $data[$field_id] = null;
                }
            }
            
            // POST-PROCESSING
            $this->apply_test_mapping_post_processing($data, $mapping, $post);
            
            // Tab-specific processing
            if ($tab_type === 'clinics') {
                $this->process_clinics_tab($data, $post, $mapping);
            } elseif ($tab_type === 'services') {
                $this->process_services_tab($data, $post, $mapping);
            } elseif ($tab_type === 'offers') {
                $this->process_offers_tab($data, $post);
            }
            
            // Generate YML preview
            $yml = $this->generate_test_yml_preview($data, $tab_type, $post);
            
            $result['sample_post'] = array(
                'post_title' => $post->post_title,
                'post_id' => $post->ID,
                'mapped_data' => $data
            );
            
            $result['clinics_found'] = isset($data['clinics']) && is_array($data['clinics']) ? count($data['clinics']) : 0;
            $result['services_found'] = isset($data['services']) && is_array($data['services']) ? count($data['services']) : 0;
            $result['yml'] = $yml;
            
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Применить post-processing к данным теста маппинга
     */
    private function apply_test_mapping_post_processing(&$data, $mapping, $post) {
        // Calculate experience_years from career_start_date
        if (!empty($mapping['experience_years']['calculate_type']) && $mapping['experience_years']['calculate_type'] === 'date_to_years') {
            if (!empty($data['career_start_date']) && strtotime($data['career_start_date']) !== false) {
                $start_date = strtotime($data['career_start_date']);
                $years = floor((time() - $start_date) / (365.25 * 24 * 60 * 60));
                if ($years >= 0) {
                    $data['experience_years'] = $years;
                }
            }
        }
        
        // Picture fallback
        if (has_post_thumbnail($post->ID)) {
            $data['picture'] = get_the_post_thumbnail_url($post->ID, 'full');
        } elseif (empty($data['picture'])) {
            $data['picture'] = '';
        }
        
        // Strip HTML from description
        if (!empty($data['description'])) {
            $data['description'] = strip_tags($data['description']);
        }
        
        // Process children_appointment/adult_appointment
        $parent_child = $data['children_appointment'] ?? $data['adult_appointment'] ?? null;
        if (is_array($parent_child)) {
            if (isset($parent_child['child'])) {
                $data['children_appointment'] = $parent_child['child'] === 'true' ? 'true' : 'false';
            }
            if (isset($parent_child['parent'])) {
                $data['adult_appointment'] = $parent_child['parent'] === 'true' ? 'true' : 'false';
            }
        }
        
        // Defaults
        if (!isset($data['adult_appointment'])) {
            $data['adult_appointment'] = 'true';
        }
        if (!isset($data['children_appointment'])) {
            $data['children_appointment'] = 'false';
        }
        
        // Extract education repeater
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
        
        // Extract reviews from JetEngine relation 7
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
                                $review = array(
                                    'date' => get_the_date('Y-m-d', $review_id),
                                    'checked' => 'true',
                                    'used_in_rating' => 'true',
                                    'author' => get_the_title($review_id),
                                    'author_id' => get_post_meta($review_id, 'id-otzyva', true),
                                    'author_picture' => get_post_meta($review_id, 'review_video_photo', true),
                                    'url' => get_permalink($review_id),
                                    'comment' => strip_tags(get_the_content(null, false, $review_id)),
                                    'grade' => get_post_meta($review_id, 'estimation', true),
                                );
                                $review['positive'] = $review['comment'];
                                $review['negative'] = strip_tags(get_post_meta($review_id, 'otvet-kliniki', true));
                                $review['response'] = $review['negative'];
                                $data['reviews'][] = $review;
                            }
                        }
                    }
                    break;
                }
            }
        }
    }
    
    /**
     * Обработка вкладки Clinics
     */
    private function process_clinics_tab(&$data, $post, $mapping) {
        $clinic_post = $post;
        
        if (!class_exists('YFGP_Field_Mapper_Unified')) {
            require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
        }
        $mapper_unified = YFGP_Field_Mapper_Unified::get_instance();
        
        $clinic_fields = array('clinics_address', 'clinics_phone', 'clinics_email', 'clinics_picture', 'clinics_city', 'clinics_url', 'clinics_id', 'clinics_name', 'clinics_company_id');
        foreach ($clinic_fields as $field_key) {
            if (!empty($mapping[$field_key])) {
                $value = $mapper_unified->getFieldValue($clinic_post->ID, $mapping[$field_key]);
                if (!empty($value)) {
                    if ($field_key === 'clinics_picture') {
                        $data[$field_key] = $this->process_picture_value($value);
                    } else {
                        $data[$field_key] = $value;
                    }
                }
            }
        }
        
        // Fallbacks
        if (empty($data['clinics_id'])) {
            $data['clinics_id'] = 'clinic_' . $clinic_post->ID;
        }
        if (empty($data['clinics_name'])) {
            $data['clinics_name'] = $clinic_post->post_title;
        }
        if (empty($data['clinics_url'])) {
            $data['clinics_url'] = get_permalink($clinic_post->ID);
        }
        if (has_post_thumbnail($clinic_post->ID)) {
            $data['clinics_picture'] = get_the_post_thumbnail_url($clinic_post->ID, 'full');
        }
        
        // Array validation
        if (!empty($data['clinics_phone']) && is_array($data['clinics_phone'])) {
            $data['clinics_phone'] = implode(', ', $data['clinics_phone']);
        }
        if (!empty($data['clinics_address']) && is_array($data['clinics_address'])) {
            $data['clinics_address'] = implode(', ', $data['clinics_address']);
        }
        if (!empty($data['clinics_description'])) {
            $data['clinics_description'] = strip_tags($data['clinics_description']);
        }
    }
    
    /**
     * Обработка вкладки Services
     */
    private function process_services_tab(&$data, $post, $mapping) {
        $service_post = $post;
        
        if (!class_exists('YFGP_Field_Mapper_Unified')) {
            require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
        }
        $mapper_unified = YFGP_Field_Mapper_Unified::get_instance();
        
        $service_fields = array('services_description', 'services_gov_id', 'services_picture', 'services_url', 'services_id', 'services_name');
        foreach ($service_fields as $field_key) {
            if (!empty($mapping[$field_key])) {
                $value = $mapper_unified->getFieldValue($service_post->ID, $mapping[$field_key]);
                if (!empty($value)) {
                    if ($field_key === 'services_picture') {
                        $data[$field_key] = $this->process_picture_value($value);
                    } else {
                        $data[$field_key] = $value;
                    }
                }
            }
        }
        
        // Fallbacks
        if (empty($data['services_id'])) {
            $data['services_id'] = 'service_' . $service_post->ID;
        }
        if (empty($data['services_name'])) {
            $data['services_name'] = $service_post->post_title;
        }
        if (has_post_thumbnail($service_post->ID)) {
            $data['services_picture'] = get_the_post_thumbnail_url($service_post->ID, 'full');
        }
        if (!empty($data['services_description'])) {
            $data['services_description'] = strip_tags($data['services_description']);
        }
    }
    
    /**
     * Обработка вкладки Offers
     */
    private function process_offers_tab(&$data, $post) {
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
                'price' => 5200,
                'currency' => 'RUR'
            ),
        );
    }
    
    /**
     * Обработка значения picture
     */
    private function process_picture_value($value) {
        if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        if (is_array($value)) {
            $first_id = is_numeric($value[0]) ? intval($value[0]) : null;
            return $first_id ? wp_get_attachment_url($first_id) : '';
        }
        if (is_string($value) && strpos($value, ',') !== false) {
            $ids = array_map('trim', explode(',', $value));
            $first_id = is_numeric($ids[0]) ? intval($ids[0]) : null;
            return $first_id ? wp_get_attachment_url($first_id) : '';
        }
        if (is_numeric($value)) {
            return wp_get_attachment_url(intval($value));
        }
        return $value;
    }
    
    /**
     * AJAX: Сохранение отредактированного XML
     */
    public function ajax_save_edited_xml() {
        check_ajax_referer('yfgp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
        }
        
        $xml_content = wp_unslash($_POST['xml_content'] ?? '');
        
        // Security - Check XML size limit (50MB)
        $max_xml_size = 50 * 1024 * 1024;
        if (strlen($xml_content) > $max_xml_size) {
            wp_send_json_error('Размер XML превышает максимальный лимит (' . round($max_xml_size / 1024 / 1024, 2) . ' MB)');
            return;
        }

        // XXE Protection
        $libxml_previous_state = null;
        if (function_exists('libxml_disable_entity_loader')) {
            $libxml_previous_state = libxml_disable_entity_loader(true);
        }
        
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_content, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOENT);
        
        if ($libxml_previous_state !== null && function_exists('libxml_disable_entity_loader')) {
            libxml_disable_entity_loader($libxml_previous_state);
        }
        
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
            $settings = get_option('yfgp_settings', array());
            $post_type = $settings['post_type'] ?? yfgp_get_default_post_type_safe();
            if (empty($post_type)) {
                wp_send_json_error('Post type not configured');
                return;
            }
            
            $feed_filename = sanitize_file_name($post_type) . '.yml';
            $feed_result = $this->get_plugin()->save_feed_file($xml_content, $feed_filename);
            
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
        check_ajax_referer('yfgp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
        }
        
        $config_json = wp_unslash($_POST['config_json'] ?? '');
        
        // Security - Check JSON size limit (5MB)
        $max_json_size = 5 * 1024 * 1024;
        if (strlen($config_json) > $max_json_size) {
            wp_send_json_error('Размер JSON превышает максимальный лимит (' . round($max_json_size / 1024 / 1024, 2) . ' MB)');
            return;
        }

        $config = json_decode($config_json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Ошибка декодирования JSON: ' . json_last_error_msg());
            return;
        }
        
        if (!$config || !is_array($config)) {
            wp_send_json_error('Неверный формат конфигурации');
            return;
        }
        
        if (!isset($config['settings']) || !is_array($config['settings'])) {
            wp_send_json_error('Отсутствует или некорректно поле settings');
            return;
        }
        
        if (!isset($config['mapping']) || !is_array($config['mapping'])) {
            wp_send_json_error('Отсутствует или некорректно поле mapping');
            return;
        }
        
        // Version compatibility check
        if (isset($config['version'])) {
            $current_version = defined('YFGP_VERSION') ? YFGP_VERSION : '4.18.0';
            if (version_compare($config['version'], $current_version, '>')) {
                wp_send_json_error('Версия конфигурации (' . $config['version'] . ') новее версии плагина (' . $current_version . ')');
                return;
            }
        }
        
        // Sanitize
        if (class_exists('YFGP_Data_Sanitizer')) {
            $sanitizer = new YFGP_Data_Sanitizer();
            $config['settings'] = $sanitizer->sanitize($config['settings']);
            $config['mapping'] = $sanitizer->sanitize($config['mapping']);
        }
        
        update_option('yfgp_settings', $config['settings']);
        update_option('yfgp_field_mapping', $config['mapping']);
        
        wp_send_json_success('Конфигурация импортирована');
    }
    
    /**
     * AJAX: Применение шаблона
     */
    public function ajax_apply_template() {
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
    public function ajax_get_terms() {
        if (!check_ajax_referer('yfgp_ajax_nonce', 'nonce', false)) {
            $this->send_json_error_no_bom(array('message' => 'Invalid nonce'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            $this->send_json_error_no_bom(array('message' => 'Недостаточно прав'));
            return;
        }
        
        $taxonomy = sanitize_key($_POST['taxonomy'] ?? '');
        
        if (empty($taxonomy)) {
            wp_send_json_error('Таксономия не указана');
            return;
        }
        
        if (!taxonomy_exists($taxonomy)) {
            wp_send_json_error('Таксономия "' . esc_html($taxonomy) . '" не существует');
            return;
        }

        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ));
        
        if (is_wp_error($terms)) {
            wp_send_json_error('Ошибка получения терминов: ' . $terms->get_error_message());
            return;
        }
        
        $terms_array = array();
        foreach ($terms as $term) {
            $terms_array[$term->slug] = $term->name;
        }
        
        $this->send_json_success_no_bom(array('terms' => $terms_array));
    }
    
    /**
     * AJAX: Валидация фида
     */
    public function ajax_validate_feed() {
        check_ajax_referer('yfgp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
        }
        
        try {
            $settings = get_option('yfgp_settings', array());
            $post_type = $settings['post_type'] ?? yfgp_get_default_post_type_safe();
            if (empty($post_type)) {
                wp_send_json_error('Post type not configured');
                return;
            }
            
            $feed_format = $settings['feed_format'] ?? 'v2';
            
            if ($feed_format === 'v2') {
                $feed_generator = new YFGP_Feed_Generator_V2();
            } else {
                $feed_generator = new YFGP_Feed_Generator();
            }
            
            $yml = $feed_generator->generate($post_type);
            
            if (empty($yml)) {
                wp_send_json_error('Не удалось сгенерировать фид для валидации');
            }
            
            // Validate XML structure
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($yml, 'SimpleXMLElement', LIBXML_NONET);
            
            if ($xml === false) {
                $errors = array();
                foreach (libxml_get_errors() as $error) {
                    $errors[] = array(
                        'level' => $error->level,
                        'message' => trim($error->message),
                        'line' => $error->line,
                        'column' => $error->column
                    );
                }
                libxml_clear_errors();
                
                wp_send_json_success(array(
                    'valid' => false,
                    'errors' => $errors,
                    'yml_preview' => substr($yml, 0, 5000)
                ));
            } else {
                wp_send_json_success(array(
                    'valid' => true,
                    'message' => 'XML структура валидна',
                    'yml_preview' => substr($yml, 0, 5000)
                ));
            }
        } catch (Exception $e) {
            wp_send_json_error('Ошибка валидации: ' . $e->getMessage());
        }
    }
    
    // ========================================================================
    // YML PREVIEW GENERATION
    // ========================================================================
    
    /**
     * Generate YML preview for test button
     */
    private function generate_test_yml_preview($data, $tab_type, $post) {
        $yml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $yml .= "<test-preview tab=\"{$tab_type}\" post_id=\"{$post->ID}\" post_title=\"" . esc_attr($post->post_title) . "\">\n";
        
        switch ($tab_type) {
            case 'doctors':
                $yml .= $this->generate_doctor_yml_preview($data, $post);
                break;
            case 'clinics':
                $yml .= $this->generate_clinic_yml_preview($data, $post);
                break;
            case 'services':
                $yml .= $this->generate_service_yml_preview($data, $post);
                break;
            case 'offers':
                $yml .= $this->generate_offers_yml_preview($data, $post);
                break;
            default:
                $yml .= "  <!-- Unknown tab type: {$tab_type} -->\n";
        }
        
        $yml .= "</test-preview>";
        
        return $yml;
    }
    
    /**
     * Generate doctor YML preview
     */
    private function generate_doctor_yml_preview($data, $post) {
        $yml = "  <doctor>\n";
        $yml .= "    <name>" . esc_html($data['name'] ?? $post->post_title) . "</name>\n";
        
        $surname = $data['surname'] ?? null;
        $first_name = $data['first_name'] ?? null;
        $patronymic = $data['patronymic'] ?? null;
        
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
        
        $simple_fields = array('experience_years', 'degree', 'rank', 'category', 'career_start_date', 'picture');
        foreach ($simple_fields as $field) {
            if (!empty($data[$field])) {
                $value = is_array($data[$field]) ? implode(', ', $data[$field]) : $data[$field];
                $yml .= "    <{$field}>" . esc_html($value) . "</{$field}>\n";
            }
        }
        
        if (!empty($data['description'])) {
            $yml .= "    <description>" . esc_html(mb_substr($data['description'], 0, 200)) . "...</description>\n";
        }
        
        // Education
        if (!empty($data['education']) && is_array($data['education'])) {
            foreach ($data['education'] as $edu) {
                $yml .= "    <education>\n";
                if (!empty($edu['name'])) $yml .= "      <name>" . esc_html($edu['name']) . "</name>\n";
                if (!empty($edu['date_end'])) $yml .= "      <date_end>" . esc_html($edu['date_end']) . "</date_end>\n";
                if (!empty($edu['specialty'])) $yml .= "      <specialty>" . esc_html($edu['specialty']) . "</specialty>\n";
                $yml .= "    </education>\n";
            }
        }
        
        // Reviews
        if (!empty($data['reviews']) && is_array($data['reviews'])) {
            foreach (array_slice($data['reviews'], 0, 3) as $rev) {
                $yml .= "    <review>\n";
                if (!empty($rev['date'])) $yml .= "      <date>" . esc_html($rev['date']) . "</date>\n";
                if (!empty($rev['author'])) $yml .= "      <author>" . esc_html($rev['author']) . "</author>\n";
                if (!empty($rev['grade'])) $yml .= "      <grade>" . esc_html($rev['grade']) . "</grade>\n";
                if (!empty($rev['comment'])) $yml .= "      <comment>" . esc_html(mb_substr($rev['comment'], 0, 100)) . "...</comment>\n";
                $yml .= "    </review>\n";
            }
        }
        
        $yml .= "  </doctor>\n";
        return $yml;
    }
    
    /**
     * Generate clinic YML preview
     */
    private function generate_clinic_yml_preview($data, $post) {
        $clinic_id = $data['clinics_id'] ?? 'clinic_' . $post->ID;
        $yml = "  <clinic id=\"" . esc_attr($clinic_id) . "\">\n";
        
        if (!empty($data['clinics_url'])) $yml .= "    <url>" . esc_html($data['clinics_url']) . "</url>\n";
        if (!empty($data['clinics_picture'])) $yml .= "    <picture>" . esc_html($data['clinics_picture']) . "</picture>\n";
        $yml .= "    <name>" . esc_html($data['clinics_name'] ?? $post->post_title) . "</name>\n";
        if (!empty($data['clinics_city'])) $yml .= "    <city>" . esc_html($data['clinics_city']) . "</city>\n";
        if (!empty($data['clinics_address'])) $yml .= "    <address>" . esc_html($data['clinics_address']) . "</address>\n";
        if (!empty($data['clinics_email'])) $yml .= "    <email>" . esc_html($data['clinics_email']) . "</email>\n";
        if (!empty($data['clinics_phone'])) $yml .= "    <phone>" . esc_html($data['clinics_phone']) . "</phone>\n";
        
        $internal_id = str_replace('clinic_', '', $clinic_id);
        $yml .= "    <internal_id>" . esc_html($internal_id) . "</internal_id>\n";
        
        if (!empty($data['clinics_company_id'])) $yml .= "    <company_id>" . esc_html($data['clinics_company_id']) . "</company_id>\n";
        
        $yml .= "  </clinic>\n";
        return $yml;
    }
    
    /**
     * Generate service YML preview
     */
    private function generate_service_yml_preview($data, $post) {
        $service_id = $data['services_id'] ?? ('service_' . $post->ID);
        if (strpos($service_id, 'service_') !== 0 && is_numeric($service_id)) {
            $service_id = 'service_' . $service_id;
        }
        
        $yml = "  <service id=\"" . esc_attr($service_id) . "\">\n";
        $yml .= "    <name>" . esc_html($data['services_name'] ?? $post->post_title) . "</name>\n";
        
        if (!empty($data['services_description'])) {
            $yml .= "    <description>" . esc_html($data['services_description']) . "</description>\n";
        }
        if (!empty($data['services_gov_id'])) {
            $yml .= "    <gov_id>" . esc_html($data['services_gov_id']) . "</gov_id>\n";
        }
        
        $internal_id = str_replace('service_', '', $service_id);
        $yml .= "    <internal_id>" . esc_html($internal_id) . "</internal_id>\n";
        
        $yml .= "  </service>\n";
        return $yml;
    }
    
    /**
     * Generate offers YML preview
     */
    private function generate_offers_yml_preview($data, $post) {
        $yml = '';
        
        if (!empty($data['offers'])) {
            foreach (array_slice($data['offers'], 0, 3) as $offer) {
                $offer_id = $offer['id'] ?? 'offer_default';
                $yml .= "  <offer id=\"" . esc_attr($offer_id) . "\">\n";
                
                if (!empty($offer['appointment_url'])) {
                    $yml .= "    <url>" . esc_html($offer['appointment_url']) . "</url>\n";
                }
                
                if (!empty($offer['price'])) {
                    $yml .= "    <price>\n";
                    $yml .= "      <base_price>" . esc_html($offer['price']) . "</base_price>\n";
                    $yml .= "      <currency>" . esc_html($offer['currency'] ?? 'RUR') . "</currency>\n";
                    $yml .= "    </price>\n";
                }
                
                $bool_fields = array('online_schedule', 'appointment_available', 'oms_available');
                foreach ($bool_fields as $field) {
                    if (isset($offer[$field])) {
                        $value = ($offer[$field] === 'true' || $offer[$field] === true) ? 'true' : 'false';
                        $yml .= "    <{$field}>" . $value . "</{$field}>\n";
                    }
                }
                
                if (!empty($offer['service_id'])) {
                    $yml .= "    <service id=\"" . esc_attr($offer['service_id']) . "\"/>\n";
                }
                
                if (!empty($offer['clinic_id'])) {
                    $yml .= "    <clinic id=\"" . esc_attr($offer['clinic_id']) . "\">\n";
                    
                    if (!empty($offer['doctor_id'])) {
                        $yml .= "      <doctor id=\"" . esc_attr($offer['doctor_id']) . "\">\n";
                        
                        if (!empty($offer['speciality'])) {
                            $yml .= "        <speciality>" . esc_html($offer['speciality']) . "</speciality>\n";
                        }
                        
                        $doctor_bool_fields = array('children_appointment', 'adult_appointment', 'house_call', 'telemed', 'is_base_service');
                        foreach ($doctor_bool_fields as $field) {
                            if (isset($offer[$field])) {
                                $value = ($offer[$field] === 'true' || $offer[$field] === true) ? 'true' : 'false';
                                $yml .= "        <{$field}>" . $value . "</{$field}>\n";
                            }
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
        
        return $yml;
    }
}

