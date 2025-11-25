<?php
/**
 * Field Mapper V2 Facade - Адаптер для V2 Production
 * 
 * Тонкая обёртка которая конвертирует V2 простой формат (строки)
 * в V3 array config и вызывает Unified класс.
 * 
 * Сохраняет ПОЛНУЮ обратную совместимость с V2 production кодом!
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.0.0
 * @version 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Загрузить Unified класс
require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';

// Загрузить старый базовый класс (для наследования helper методов)
require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper.php';

class YFGP_Field_Mapper_V2 extends YFGP_Field_Mapper {
    
    /**
     * Unified mapper instance
     */
    private $unified;
    
    /**
     * Конструктор
     */
    public function __construct() {
        // Получить singleton Unified instance
        $this->unified = YFGP_Field_Mapper_Unified::get_instance();
    }
    
    /**
     * Get fallback speciality text from settings
     * 
     * v4.17.0: FIX #4 - Converts slug to human-readable label
     * Supports custom text override
     * 
     * @return string Fallback speciality label (e.g. 'Стоматолог' or 'Адвокат')
     */
    public function get_fallback_speciality_text(): string {
        $fallback = $this->get_speciality_fallback();

        return $fallback['label'];
    }

    /**
     * Returns fallback speciality slug to be reused in generators (offers, doctors, services)
     */
    public function get_fallback_speciality_slug(): string {
        $fallback = $this->get_speciality_fallback();

        return $fallback['slug'];
    }
    
    // ========================================================================
    // ADAPTER LAYER - V2 Simple Format → V3 Array Config → Unified
    // ========================================================================
    
    /**
     * Получить значение поля (ПЕРЕОПРЕДЕЛЕНО для вызова Unified)
     * 
     * Конвертирует V2 простой формат в V3 array config
     * 
     * @since 4.0.0 (v2 method refactored to use Unified)
     * @param WP_Post $post WordPress пост
     * @param string $field_name Название поля (V2 простой формат)
     * @param mixed $default Значение по умолчанию
     * @return mixed Значение поля
     */
    protected function get_field_value($post, $field_name, $default = '') {
        if (!$post) {
            return $default;
        }
        
        try {
            // v4.1.0-beta23: Comprehensive error handling for ACF/Unified API issues
            // Debug: Log what config we're processing
            if (is_array($field_name)) {
                error_log('YFGP DEBUG: get_field_value received V3 config for post ' . $post->ID . ': ' . json_encode($field_name));
                $config = $field_name;
            } else {
                error_log('YFGP DEBUG: get_field_value received V2 string for post ' . $post->ID . ': ' . $field_name);
                $config = $this->convertV2toV3($field_name);
            }
            
            // Вызвать Unified
            $value = $this->unified->getFieldValue($post->ID, $config);
            
            // Вернуть default если нет значения
            return ($value !== null && $value !== '') ? $value : $default;
            
        } catch (Exception $e) {
            error_log('YFGP ERROR: get_field_value failed for post ' . $post->ID . ': ' . $e->getMessage());
            error_log('YFGP ERROR: Field config: ' . json_encode($field_name));
            return $default;
        } catch (Error $e) {
            // Catch PHP Error (ACF offset errors)
            error_log('YFGP FATAL ERROR: get_field_value for post ' . $post->ID);
            error_log('YFGP FATAL: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            error_log('YFGP FATAL: Field config: ' . json_encode($field_name));
                return $default;
        }
    }
    
    /**
     * Получить связанные посты (ПЕРЕОПРЕДЕЛЕНО для вызова Unified)
     * 
     * @since 4.0.0 (v2 method refactored to use Unified)
     * @param int $post_id ID поста
     * @param string $field_name Название поля связи
     * @param string $field_type Тип поля (не используется в Unified)
     * @return array<string, mixed> Массив WP_Post объектов
     */
    protected function get_related_posts($post_id, $field_name, $field_type = 'auto') {
        // v4.1.0-beta26: Handle both V2 (string) and V3 (config array) formats + debug
        error_log('YFGP DEBUG get_related_posts: post_id=' . $post_id . ', field_name=' . (is_array($field_name) ? json_encode($field_name) : $field_name));
        
        if (is_array($field_name)) {
            // Already V3 config - use directly!
            $config = $field_name;
            error_log('YFGP DEBUG: Using V3 config, source_type=' . ($config['source_type'] ?? 'NONE'));
        } else {
            // V2 string format - convert to V3 config
            $config = array(
                'source_type' => 'relationship_1',
                'source_field' => $field_name,
            );
            error_log('YFGP DEBUG: Converted V2 string to V3 config');
        }
        
        // Вызвать Unified
        $related_posts = $this->unified->getFieldValue($post_id, $config);
        
        error_log('YFGP DEBUG get_related_posts: Got ' . (is_array($related_posts) ? count($related_posts) : '0') . ' related posts');
        
        // Unified возвращает array of WP_Post objects
        return is_array($related_posts) ? $related_posts : array();
    }
    
    // ========================================================================
    // V2 → V3 CONVERSION (PRIVATE)
    // ========================================================================
    
    /**
     * Конвертировать V2 простой формат (строка) в V3 array config
     * 
     * @since 4.0.0
     * @param string $field_name V2 field name (простая строка)
     * @return array<string, mixed> V3 config array
     */
    private function convertV2toV3($field_name): array {
        // Определить source_type по названию поля
        
        // WordPress standard fields
        $wp_fields = array('post_title', 'post_content', 'post_excerpt', 'post_date', 
                          'post_modified', 'post_author', 'post_status', 'post_name', 
                          'post_parent', 'menu_order', 'permalink', 'featured_image', 'post_id');
        
        if (in_array($field_name, $wp_fields)) {
            return array(
                'source_type' => 'meta_field',
                'source_field' => $field_name,
            );
        }
        
        // JetEngine Relationship (jet_rel_123)
        if (preg_match('/^jet_rel_\d+/', $field_name)) {
            return array(
                'source_type' => 'relationship_1',
                'source_field' => $field_name,
            );
        }
        
        // Default: meta_field (ACF или custom meta)
        return array(
            'source_type' => 'meta_field',
            'source_field' => $field_name,
        );
    }
    
    // ========================================================================
    // V2 PUBLIC API - get_available_fields() → Unified
    // ========================================================================
    
    /**
     * Получить доступные поля для UI (ПЕРЕОПРЕДЕЛЕНО для вызова Unified)
     * 
     * @since 4.0.0 (v2 method refactored to use Unified)
     * @param string $post_type Тип поста
     * @return array<string, mixed> Массив доступных полей
     */
    public function get_available_fields($post_type = 'doctors'): array {
        // Вызвать Unified для получения полей
        $unified_fields = $this->unified->getAvailableFields($post_type);
        
        // Конвертировать в V2 формат (структура отличается!)
        $v2_fields = array(
            'acf' => $unified_fields['acf'] ?? array(),
            'wordpress' => $unified_fields['wordpress'] ?? array(),
            'jetengine' => $unified_fields['jetengine'] ?? array(), // v4.18.0: Добавлена поддержка JetEngine полей для исключений
            'meta' => $unified_fields['meta'] ?? array(), // v4.18.0: Добавлена поддержка обычных meta полей
            'relations' => array_merge(
                $unified_fields['acf_relationship'] ?? array(),
                $unified_fields['jetengine_relationship'] ?? array()
            ),
            'taxonomy' => $unified_fields['taxonomy'] ?? array(),
        );
        
        return $v2_fields;
    }
    
    // ========================================================================
    // V2 PUBLIC API - get_dynamic_value() (ИСПОЛЬЗУЕТСЯ В map_post_data_v2!)
    // ========================================================================
    
    /**
     * Получить динамическое значение поля
     * 
     * Этот метод используется в PRODUCTION коде map_post_data_v2!
     * КРИТИЧНО для обратной совместимости!
     * 
     * @since 4.0.0 (v2 method preserved, delegates to get_field_value)
     * @param int $post_id ID поста
     * @param string $field_name Название поля
     * @param mixed $default Значение по умолчанию
     * @return mixed Значение поля
     */
    public function get_dynamic_value($post_id, $field_name, $default = '') {
        $post = get_post($post_id);
        
        if (!$post) {
            return $default;
        }
        
        // Делегировать в get_field_value (который вызывает Unified)
        return $this->get_field_value($post, $field_name, $default);
    }
    
    // ========================================================================
    // V2 PRODUCTION METHODS - СОХРАНЕНЫ БЕЗ ИЗМЕНЕНИЙ!
    // ========================================================================
    
    /**
     * Маппинг данных поста с использованием новой системы
     * 
     * ВСЯ бизнес-логика СОХРАНЕНА из оригинального V2!
     * Только низкоуровневые вызовы извлечения полей теперь через Unified
     * 
     * @since 2.3.0 (original)
     * @since 4.0.0 (refactored to use Unified internally via get_dynamic_value)
     * @param WP_Post $post WordPress пост
     * @param array<string, mixed> $mapping Маппинг полей
     * @return array<string, mixed> Данные для фида
     */
    public function map_post_data_v2($post, $mapping): array {
        $data = array(
            'post_id' => $post->ID,
            'params' => array(),
            'clinics' => array(),
            'services' => array(),
            'set_ids' => '',
            'description' => '',
            'specializations_text' => array($this->get_fallback_speciality_text()), // v4.17.0: FIX #4 - from settings
        );
        
        // ФИО врача
        // v4.18.6: Debug - логируем маппинг для диагностики
        error_log('YFGP v4.18.6 DEBUG map_post_data_v2: post_id=' . $post->ID . ', post_type=' . $post->post_type . ', post_title=' . $post->post_title);
        error_log('YFGP v4.18.6 DEBUG map_post_data_v2: mapping keys=' . implode(', ', array_keys($mapping)));
        error_log('YFGP v4.18.6 DEBUG map_post_data_v2: mapping[surname]=' . (isset($mapping['surname']) ? 'SET' : 'NOT SET') . ', mapping[firstname]=' . (isset($mapping['firstname']) ? 'SET' : 'NOT SET') . ', mapping[patronymic]=' . (isset($mapping['patronymic']) ? 'SET' : 'NOT SET') . ', mapping[name]=' . (isset($mapping['name']) ? 'SET' : 'NOT SET'));
        
        if (!empty($mapping['surname']) && !empty($mapping['firstname']) && !empty($mapping['patronymic'])) {
            $data['params']['Фамилия'] = $this->get_dynamic_value($post->ID, $mapping['surname']);
            $data['params']['Имя'] = $this->get_dynamic_value($post->ID, $mapping['firstname']);
            $data['params']['Отчество'] = $this->get_dynamic_value($post->ID, $mapping['patronymic']);
            error_log('YFGP v4.18.6 DEBUG map_post_data_v2: Using separate fields - Фамилия=' . $data['params']['Фамилия'] . ', Имя=' . $data['params']['Имя'] . ', Отчество=' . $data['params']['Отчество']);
        } elseif (!empty($mapping['name'])) {
            $full_name = $this->get_dynamic_value($post->ID, $mapping['name'], $post->post_title);
            error_log('YFGP v4.18.6 DEBUG map_post_data_v2: Using mapping[name], full_name=' . $full_name);
            $parts = explode(' ', trim($full_name));
            $data['params']['Фамилия'] = $parts[0] ?? '';
            $data['params']['Имя'] = $parts[1] ?? '';
            $data['params']['Отчество'] = $parts[2] ?? '';
            error_log('YFGP v4.18.6 DEBUG map_post_data_v2: After explode - Фамилия=' . $data['params']['Фамилия'] . ', Имя=' . $data['params']['Имя'] . ', Отчество=' . $data['params']['Отчество']);
        } else {
            error_log('YFGP v4.18.6 DEBUG map_post_data_v2: Using post_title fallback, post_title=' . $post->post_title);
            $parts = explode(' ', trim($post->post_title));
            $data['params']['Фамилия'] = $parts[0] ?? '';
            $data['params']['Имя'] = $parts[1] ?? '';
            $data['params']['Отчество'] = $parts[2] ?? '';
            error_log('YFGP v4.18.6 DEBUG map_post_data_v2: After post_title explode - Фамилия=' . $data['params']['Фамилия'] . ', Имя=' . $data['params']['Имя'] . ', Отчество=' . $data['params']['Отчество']);
        }
        
        // Стаж
        if (!empty($mapping['work_experience'])) {
            $experience = $this->get_dynamic_value($post->ID, $mapping['work_experience']);
            $data['params']['Годы опыта'] = $this->calculate_experience($experience);
        } else {
            $data['params']['Годы опыта'] = '0';
        }
        
        // Специализация (v2.3.0: поддержка множественных специальностей)
        // v4.10.7: CRITICAL FIX! Key name 'specialities' (V3) not 'specialization' (V2)
        if (!empty($mapping['specialities'])) {
            $specialization = $this->get_dynamic_value($post->ID, $mapping['specialities']);

            $normalized = $this->normalize_specialities($post, $mapping, $specialization);

            $data['set_ids'] = $normalized['set_ids'];
            $data['specializations_text'] = $normalized['labels'];

            $data['specialities'] = array();
            $slugs = $normalized['set_ids'];
            $labels = $normalized['labels'];
            $count = count($slugs);

            for ($i = 0; $i < $count; $i++) {
                $slug = $slugs[$i];
                $label = $labels[$i] ?? ($labels[0] ?? '');
                $data['specialities'][] = array(
                    'slug' => $slug,
                    'name' => function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label)
                );
            }
        } else {
            $fallback = $this->get_speciality_fallback();
            $data['set_ids'] = array($fallback['slug']);
            $data['specializations_text'] = array($fallback['label']);

            $fallback_name = function_exists('mb_strtolower') ? mb_strtolower($fallback['label'], 'UTF-8') : strtolower($fallback['label']);
            $data['specialities'] = array(
                array('slug' => $fallback['slug'], 'name' => $fallback_name)
            );
        }
        
        
        // Описание
        if (!empty($mapping['description'])) {
            $data['description'] = $this->get_dynamic_value($post->ID, $mapping['description'], $post->post_content);
        } else {
            $data['description'] = $post->post_content;
        }
        
        // v4.1.0-beta21: Degree, rank, category и другие V3 поля теперь извлекаются
        // через метод extract_v3_fields() в Feed Generator (использует Field Mapper V3 API)
        // Это обеспечивает чистое разделение V2/V3 логики без конфликтов с ACF
        
        // Образование, Работа, Сертификаты (repeater fields)
        // ПРИМЕЧАНИЕ: V2 НЕ использовал repeater helper, логика упрощена
        
        // Клиники (связь)
        // v4.1.0-beta28: Получаем связи с клиниками
        if (!empty($mapping['clinics'])) {
            $clinic_ids = $this->get_related_posts($post->ID, $mapping['clinics']);
            
            if (!empty($clinic_ids)) {
                foreach ($clinic_ids as $clinic_id) {
                    // v4.1.0-beta28: get_related_posts returns IDs, not WP_Post objects
                    $clinic_post = is_object($clinic_id) ? $clinic_id : get_post($clinic_id);
                    
                    if ($clinic_post) {
                        $clinic_data = array(
                            'id' => 'clinic_' . $clinic_post->ID,
                            'internal_id' => $clinic_post->ID,
                            'name' => $clinic_post->post_title,
                            'post_id' => $clinic_post->ID, // v4.1.7: For V3 mapper access
                        );
                        
                        // v4.1.7: UNIVERSAL extraction via V3 mapper (NO HARDCODE!)
                        if (!class_exists('YFGP_Field_Mapper_Unified')) {
                            require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
                        }
                        
                        $mapper_unified = YFGP_Field_Mapper_Unified::get_instance();
                        $clinic_mapping = get_option('yfgp_field_mapping_v3', array());
                        
                        // v4.1.9: FIXED - Use CORRECT mapping keys (clinics_*)
                        $clinic_field_mappings = array(
                            'clinics_address' => 'address',
                            'clinics_phone' => 'phone',
                            'clinics_email' => 'email',
                            'clinics_picture' => 'picture',
                            'clinics_company_id' => 'company_id',
                            'clinics_city' => 'city'
                        );
                        
                        foreach ($clinic_field_mappings as $mapping_key => $output_key) {
                            if (!empty($clinic_mapping[$mapping_key])) {
                                $value = $mapper_unified->getFieldValue($clinic_post->ID, $clinic_mapping[$mapping_key]);
                                if (!empty($value)) {
                                    // v4.2.2: UNIVERSAL picture conversion (array, string, comma-separated)
                                    if ($output_key === 'picture' && !empty($value)) {
                                        // Если уже URL - оставляем как есть
                                        if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                                            $clinic_data[$output_key] = $value;
                                        }
                                        // Если массив - берем первый элемент
                                        elseif (is_array($value)) {
                                            $first_id = is_numeric($value[0]) ? intval($value[0]) : null;
                                            $clinic_data[$output_key] = $first_id ? wp_get_attachment_url($first_id) : '';
                                        }
                                        // Если строка с запятыми (gallery IDs) - explode и берем первый
                                        elseif (is_string($value) && strpos($value, ',') !== false) {
                                            $ids = array_map('trim', explode(',', $value));
                                            $first_id = is_numeric($ids[0]) ? intval($ids[0]) : null;
                                            $clinic_data[$output_key] = $first_id ? wp_get_attachment_url($first_id) : '';
                                        }
                                        // Если просто число/строка с числом - конвертим
                                        elseif (is_numeric($value)) {
                                            $clinic_data[$output_key] = wp_get_attachment_url(intval($value));
                                        } else {
                                            $clinic_data[$output_key] = $value; // Fallback
                                        }
                                    } else {
                                        $clinic_data[$output_key] = $value;
                                    }
                                }
                            }
                        }
                        
                        // v4.2.0: Featured image ALWAYS overrides mapping
                        if (has_post_thumbnail($clinic_post->ID)) {
                            $clinic_data['picture'] = get_the_post_thumbnail_url($clinic_post->ID, 'full');
                        }
                        
                        $data['clinics'][] = $clinic_data;
                    }
                }
            }
        }
        
        // Услуги (связь)
        if (!empty($mapping['services'])) {
            $service_ids = $this->get_related_posts($post->ID, $mapping['services']);
            
            // v4.3.0: Get service mapping for field extraction (same pattern as clinics)
            $service_mapping = get_option('yfgp_field_mapping_v3', array());
            
            foreach ($service_ids as $service_id) {
                // v4.1.0-beta28: get_related_posts returns IDs, not WP_Post objects
                $service_post = is_object($service_id) ? $service_id : get_post($service_id);
                
                if ($service_post) {
                    $service_data = array(
                        'id' => 'service_' . $service_post->ID,
                        'post_id' => $service_post->ID, // v4.3.0: For permalink
                        'internal_id' => $service_post->ID,
                        'name' => $service_post->post_title,
                    );
                    
                    // v4.3.0: Extract service fields via V3 mapper (same as clinics!)
                    $service_field_mappings = array(
                        'services_description' => 'description',
                        'services_gov_id' => 'gov_id',
                        'services_picture' => 'picture'
                    );
                    
                    foreach ($service_field_mappings as $mapping_key => $output_key) {
                        if (!empty($service_mapping[$mapping_key])) {
                            $value = $mapper_unified->getFieldValue($service_post->ID, $service_mapping[$mapping_key]);
                            if (!empty($value)) {
                                // v4.3.0: UNIVERSAL picture conversion (same as clinics v4.2.2!)
                                if ($output_key === 'picture' && !empty($value)) {
                                    // Если уже URL - оставляем как есть
                                    if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                                        $service_data[$output_key] = $value;
                                    }
                                    // Если массив - берем первый элемент
                                    elseif (is_array($value)) {
                                        $first_id = is_numeric($value[0]) ? intval($value[0]) : null;
                                        $service_data[$output_key] = $first_id ? wp_get_attachment_url($first_id) : '';
                                    }
                                    // Если строка с запятыми (gallery IDs) - explode и берем первый
                                    elseif (is_string($value) && strpos($value, ',') !== false) {
                                        $ids = array_map('trim', explode(',', $value));
                                        $first_id = is_numeric($ids[0]) ? intval($ids[0]) : null;
                                        $service_data[$output_key] = $first_id ? wp_get_attachment_url($first_id) : '';
                                    }
                                    // Если просто число/строка с числом - конвертим
                                    elseif (is_numeric($value)) {
                                        $service_data[$output_key] = wp_get_attachment_url(intval($value));
                                    } else {
                                        $service_data[$output_key] = $value; // Fallback
                                    }
                                } else {
                                    $service_data[$output_key] = $value;
                                }
                            }
                        }
                    }
                    
                    // v4.3.0: Featured image ALWAYS overrides mapping (same as clinics)
                    if (has_post_thumbnail($service_post->ID)) {
                        $service_data['picture'] = get_the_post_thumbnail_url($service_post->ID, 'full');
                    }
                    
                    // v4.5.0: Extract PRICES via relationship (context-based sub-fields!)
                    // v4.5.1: Handle MULTIPLE prices - select the CHEAPEST one!
                    if (!empty($service_mapping['prices_price_source_field'])) {
                        $price_source_config = $service_mapping['prices_price_source_field'];
                        
                        // Get related price posts via relationship
                        $price_ids = $mapper_unified->getFieldValue($service_post->ID, $price_source_config);
                        
                        if (!empty($price_ids)) {
                            // Convert to array if single value
                            if (!is_array($price_ids)) {
                                $price_ids = array($price_ids);
                            }
                            
                            // v4.5.1 FIX: Extract prices from ALL related price posts, find CHEAPEST!
                            // Apply Smart Sorting Pattern #25 (systemPatterns.md)
                            $all_prices_data = array();
                            
                            foreach ($price_ids as $price_id_raw) {
                                $price_id = is_object($price_id_raw) ? $price_id_raw->ID : $price_id_raw;
                                
                                // Extract base_price (cost field) for comparison
                                if (!empty($service_mapping['prices_base_price'])) {
                                    $price_value_raw = $mapper_unified->getFieldValue($price_id, $service_mapping['prices_base_price']);
                                    
                                    // Clean and validate price
                                    $price_value = null;
                                    if (!empty($price_value_raw)) {
                                        if (is_numeric($price_value_raw)) {
                                            $price_value = intval($price_value_raw);
                                        } elseif (is_string($price_value_raw)) {
                                            // Clean formatted prices: "3 500 ₽" → 3500
                                            $clean_value = preg_replace('/[^\d]/', '', $price_value_raw);
                                            $price_value = intval($clean_value);
                                        }
                                    }
                                    
                                    // v4.5.1 CRITICAL: Only add prices > 0 (exclude null AND 0!)
                                    // Semantic: null = "no price info", 0 = "free" - both excluded from "cheapest" selection
                                    if ($price_value !== null && $price_value > 0) {
                                        $all_prices_data[] = array(
                                            'price_id' => $price_id,
                                            'value' => $price_value
                                        );
                                    }
                                }
                            }
                            
                            // v4.5.1: Sort by price and take the SMALLEST one!
                            if (!empty($all_prices_data)) {
                                // Sort ascending (smallest first)
                                usort($all_prices_data, function($a, $b) {
                                    return $a['value'] - $b['value'];
                                });
                                
                                // Use the CHEAPEST price
                                $cheapest_price_id = $all_prices_data[0]['price_id'];
                                
                                // Extract ALL sub-fields from the CHEAPEST price
                                $price_subfields = array(
                                    'prices_base_price' => 'price',
                                    'prices_discount' => 'price_discount',
                                    'prices_currency' => 'currency'
                                );
                                
                                foreach ($price_subfields as $mapping_key => $output_key) {
                                    if (!empty($service_mapping[$mapping_key])) {
                                        $value = $mapper_unified->getFieldValue($cheapest_price_id, $service_mapping[$mapping_key]);
                                        
                                        if (!empty($value)) {
                                            // Convert price to integer
                                            if ($output_key === 'price' || $output_key === 'price_discount') {
                                                if (is_numeric($value)) {
                                                    $service_data[$output_key] = intval($value);
                                                } elseif (is_string($value)) {
                                                    // Clean formatted prices: "3 500 ₽" → 3500
                                                    $clean_value = preg_replace('/[^\d]/', '', $value);
                                                    $service_data[$output_key] = intval($clean_value);
                                                }
                                            } else {
                                                $service_data[$output_key] = $value;
                                            }
                                        }
                                    }
                                }
                                
                                // v4.18.1: Fallback для валюты, если не извлечена
                                if (empty($service_data['currency']) && !empty($this->settings['default_currency'])) {
                                    $service_data['currency'] = $this->settings['default_currency'];
                                }
                            }
                        }
                    }
                
                $data['services'][] = $service_data;
                }
            }
        }
        
        // v3.5.3: Базовая услуга (явное указание от врача)
        if (!empty($mapping['base_service_id'])) {
            $field_name = $mapping['base_service_id'];
            $base_service_post = $this->get_dynamic_value($post->ID, $field_name, 'auto');
            
            if ($base_service_post && is_object($base_service_post) && isset($base_service_post->ID)) {
                $data['base_service_id'] = 'service_' . $base_service_post->ID;
                error_log('YFGP v4.0.0 Facade: Base service explicitly set for doctor ' . $post->ID . ': ' . $data['base_service_id']);
            } elseif ($base_service_post && is_numeric($base_service_post)) {
                $data['base_service_id'] = 'service_' . $base_service_post;
                error_log('YFGP v4.0.0 Facade: Base service ID for doctor ' . $post->ID . ': ' . $data['base_service_id']);
            }
        }
        
        return $data;
    }
    
    // ========================================================================
    // HELPER METHODS - НАСЛЕДУЮТСЯ ОТ БАЗОВОГО КЛАССА
    // ========================================================================
    
    // calculate_experience() - наследуется от YFGP_Field_Mapper
    // get_set_ids() - наследуется от YFGP_Field_Mapper

    /**
     * Получить fallback значение для специализации
     * 
     * @return array Массив с ключами 'slug' и 'label'
     * @since 4.18.17 Изменён на public для использования в FeedGenerator
     */
    public function get_speciality_fallback(): array {
        $settings = get_option('yfgp_settings', array());
        $custom_label = isset($settings['fallback_speciality_custom']) ? trim((string) $settings['fallback_speciality_custom']) : '';
        $select_label_raw = isset($settings['fallback_speciality']) ? trim((string) $settings['fallback_speciality']) : '';

        $label = $custom_label !== '' ? $custom_label : ($select_label_raw !== '' ? $select_label_raw : 'Специалист');
        $label = trim(wp_strip_all_tags($label));

        $slug_source = $custom_label !== '' ? $custom_label : $select_label_raw;
        $slug = '';

        if ($slug_source !== '') {
            if (!function_exists('yfgp_specialities_slugify')) {
                require_once YFGP_PLUGIN_DIR . 'includes/specialities-reference.php';
            }

            if (function_exists('yfgp_specialities_slugify')) {
                $slug = yfgp_specialities_slugify($slug_source);
            }

            if ($slug === '') {
                $slug = sanitize_title($slug_source);
            }
        }

        if ($slug === '') {
            $slug = 'specialist';
        }

        return array(
            'slug' => $slug,
            'label' => $label,
        );
    }

    /**
     * External normalizer for specialization-like fields (used by generator/services).
     *
     * @since 4.18.4
     * @param \WP_Post $post
     * @param array<string, mixed> $field_config
     * @param mixed $raw_value
     * @return array{set_ids: array<int, string>, labels: array<int, string>}
     */
    public function normalize_specialities_external($post, array $field_config, $raw_value): array {
        $mapping = array('specialities' => $field_config);

        return $this->normalize_specialities($post, $mapping, $raw_value);
    }

    /**
     * Normalize speciality values from various data structures.
     *
     * @param WP_Post $post
     * @param array<string, mixed> $mapping
     * @param mixed $raw_value
     * @return array{set_ids: array<int, string>, labels: array<int, string>}
     */
    private function normalize_specialities($post, array $mapping, $raw_value): array {
        $fallback = $this->get_speciality_fallback();

        $field_config = $mapping['specialities'] ?? array();
        $source_field = '';
        $source_field_key = '';

        if (is_array($field_config)) {
            $source_field = isset($field_config['source_field']) ? (string) $field_config['source_field'] : '';
            $source_field_key = isset($field_config['source_field_key']) ? (string) $field_config['source_field_key'] : '';
        }

        $acf_definition = array();
        if ($source_field_key !== '') {
            $acf_definition = $this->unified->getAcfFieldDefinition($source_field_key, $post->ID);
        }
        if (empty($acf_definition) && $source_field !== '') {
            $acf_definition = $this->unified->getAcfFieldDefinition($source_field, $post->ID);
        }

        if (empty($acf_definition) && $source_field !== '' && function_exists('acf_get_reference')) {
            $field_reference = acf_get_reference($source_field, $post->ID);
            if (!$field_reference && isset($post->ID)) {
                $field_reference = get_post_meta($post->ID, '_' . $source_field, true);
            }
            if ($field_reference) {
                $acf_definition = $this->unified->getAcfFieldDefinition($field_reference, $post->ID);
                if (empty($acf_definition) && function_exists('acf_get_field')) {
                    $raw_field = acf_get_field($field_reference);
                    if (is_array($raw_field)) {
                        $acf_definition = array(
                            'choices' => $raw_field['choices'] ?? array(),
                            'return_format' => $raw_field['return_format'] ?? 'value',
                            'type' => $raw_field['type'] ?? null,
                            'allow_null' => !empty($raw_field['allow_null']),
                            'multiple' => !empty($raw_field['multiple']),
                        );
                    }
                }
                if (empty($acf_definition)) {
                    $acf_definition = $this->unified->getAcfFieldDefinition($field_reference);
                }
            }
        }

        $field_options = array();
        // v4.18.2: Сначала пробуем ACF choices (если есть), потом JetEngine options
        // Это важно для ACF полей, чтобы не использовать неправильные JetEngine options
        if (!empty($acf_definition['choices']) && is_array($acf_definition['choices'])) {
            $field_options = $acf_definition['choices'];
        }
        // Если ACF choices не найдены, пробуем JetEngine options
        if (empty($field_options) && $source_field !== '') {
            $field_options = $this->unified->getJetengineFieldOptions($source_field, $post->post_type);
        }
        // Если JetEngine options не найдены, пробуем JetEngine glossary
        if (empty($field_options) && $source_field !== '' && method_exists($this->unified, 'getJetengineGlossaryLabels')) {
            $field_options = $this->unified->getJetengineGlossaryLabels($source_field, $post->post_type);
        }

        $entries = array();
        $store_entry = function (?array $entry) use (&$entries, $fallback) {
            if (!is_array($entry) || empty($entry['slug'])) {
                return;
            }

            $raw_slug = isset($entry['slug']) ? (string) $entry['slug'] : '';
            if ($raw_slug !== '' && strpos($raw_slug, '%') !== false) {
                $decoded = urldecode($raw_slug);
                if ($decoded !== '') {
                    $raw_slug = $decoded;
                }
            }

            $slug = sanitize_title($raw_slug);
            if ($slug === '') {
                return;
            }

            if (function_exists('yfgp_specialities_slugify') && $label = (isset($entry['label']) ? trim(wp_strip_all_tags((string) $entry['label'])) : '')) {
                $ascii_slug = yfgp_specialities_slugify($label);
                if ($ascii_slug !== '') {
                    $slug = $ascii_slug;
                }
            }

            $label = isset($entry['label']) ? trim(wp_strip_all_tags((string) $entry['label'])) : '';
            $slug_compare = strtolower(str_replace(array('-', '_'), ' ', $slug));
            $label_compare = strtolower(str_replace(array('-', '_'), ' ', $label));

            if ($label_compare === '' || $label_compare === $slug_compare) {
                $reference_label = method_exists($this->unified, 'getSpecialityLabelFromSlug') ? $this->unified->getSpecialityLabelFromSlug($slug) : null;
                if ($reference_label !== null) {
                    $label = $reference_label;
                } else {
                    $label = $this->humanize_speciality_label($slug);
                }
            }

            if ($label === '') {
                $label = $fallback['label'];
            }

            $entries[$slug] = $label;
        };

        if (is_array($raw_value)) {
            if ($this->is_jetengine_checkbox_map($raw_value)) {
                foreach ($raw_value as $candidate_slug => $flag) {
                    if ($this->is_truthy_flag($flag)) {
                        $store_entry($this->build_speciality_entry(array('slug' => $candidate_slug), $field_options, $acf_definition, $fallback));
                    }
                }
            } else {
                foreach ($raw_value as $item) {
                    $store_entry($this->build_speciality_entry($item, $field_options, $acf_definition, $fallback));
                }
            }
        } elseif (is_string($raw_value) && $raw_value !== '') {
            $parts = preg_split('/<br\s*\/?>|\r\n|\n|,/', $raw_value);
            foreach ($parts as $part) {
                $part = trim(wp_strip_all_tags($part));
                if ($part === '') {
                    continue;
                }
                $store_entry($this->build_speciality_entry($part, $field_options, $acf_definition, $fallback));
            }
        } else {
            $store_entry($this->build_speciality_entry($raw_value, $field_options, $acf_definition, $fallback));
        }

        if (empty($entries)) {
            $entries[$fallback['slug']] = $fallback['label'];
        }

        return array(
            'set_ids' => array_keys($entries),
            'labels'  => array_values($entries),
        );
    }

    /**
     * Build speciality entry (slug + label) from raw value.
     *
     * @param mixed $raw_item
     * @param array<string, mixed> $field_options
     * @param array<string, mixed> $acf_definition
     * @param array{slug:string,label:string} $fallback
     * @return array{slug:string,label:string}|null
     */
    private function build_speciality_entry($raw_item, array $field_options, array $acf_definition, array $fallback): ?array {
        if (is_object($raw_item)) {
            $raw_item = (array) $raw_item;
        }

        $slug = null;
        $label = null;

        if (is_array($raw_item)) {
            if (isset($raw_item['slug']) && $raw_item['slug'] !== '') {
                $slug = (string) $raw_item['slug'];
            }

            if (isset($raw_item['value']) && $raw_item['value'] !== '') {
                if ($slug === null || $slug === '') {
                    $slug = (string) $raw_item['value'];
                }
            }

            if (isset($raw_item['name']) && $raw_item['name'] !== '') {
                $label = (string) $raw_item['name'];
            }

            if (isset($raw_item['label']) && $raw_item['label'] !== '') {
                $label = (string) $raw_item['label'];
            }

            if ($slug === null && isset($raw_item[0]) && !is_array($raw_item[0])) {
                $slug = (string) $raw_item[0];
            }
        } else {
            $scalar = trim((string) $raw_item);
            if ($scalar === '') {
                return null;
            }
            $slug = $scalar;
            $label = $scalar;
        }

        $matched_slug = null;

        if ($slug !== null && isset($field_options[$slug])) {
            $label = $field_options[$slug];
        } elseif ($label !== null) {
            $found = array_search($label, $field_options, true);
            if ($found !== false) {
                $matched_slug = (string) $found;
            }
        } elseif ($slug !== null) {
            $found = array_search($slug, $field_options, true);
            if ($found !== false) {
                $matched_slug = (string) $found;
                $label = $field_options[$found];
            }
        }

        if ($matched_slug !== null) {
            $slug = $matched_slug;
        }

        if (($label === null || $label === '') && $slug !== null && method_exists($this->unified, 'getSpecialityLabelFromSlug')) {
            $reference_label = $this->unified->getSpecialityLabelFromSlug($slug);
            if ($reference_label !== null) {
                $label = $reference_label;
            }
        }

        if ($label === null && isset($acf_definition['return_format']) && $acf_definition['return_format'] === 'label' && $slug !== null) {
            $found = array_search($slug, $field_options, true);
            if ($found !== false) {
                $label = $field_options[$found];
                $slug = (string) $found;
            }
        }

        return array(
            'slug'  => $slug !== null ? (string) $slug : '',
            'label' => $label !== null ? (string) $label : '',
        );
    }

    private function is_jetengine_checkbox_map(array $value): bool {
        if (empty($value)) {
            return false;
        }

        $keys = array_keys($value);
        if (empty($keys) || is_numeric($keys[0])) {
            return false;
        }

        foreach ($value as $flag) {
            if (is_array($flag)) {
                return false;
            }
            if (!$this->is_truthy_flag($flag) && !$this->is_falsey_flag($flag)) {
                return false;
            }
        }

        return true;
    }

    private function is_truthy_flag($value): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, array('1', 'true', 'yes', 'on'), true);
        }

        return false;
    }

    private function is_falsey_flag($value): bool {
        if (is_bool($value)) {
            return !$value;
        }

        if (is_numeric($value)) {
            return (int) $value === 0;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, array('0', 'false', 'off', ''), true);
        }

        return !$value;
    }

    private function humanize_speciality_label(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = str_replace(array('-', '_'), ' ', $value);
        if (function_exists('mb_convert_case')) {
            return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
        }

        return ucwords($value);
    }
}

