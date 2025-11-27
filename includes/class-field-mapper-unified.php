<?php
/**
 * Field Mapper Unified - Единый класс маппинга полей
 * 
 * Объединяет логику из V2 и V3 в один класс для устранения дублирования.
 * Работает ТОЛЬКО с V3 формат конфигурации (array config).
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.0.0
 * @version 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// v4.18.17: Cache Manager для кэширования с разделением областей
if (!class_exists('YFGP_Cache_Manager')) {
    require_once YFGP_PLUGIN_DIR . 'includes/class-cache-manager.php';
}

class YFGP_Field_Mapper_Unified {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Кэш результатов для производительности
     * 
     * @deprecated v4.18.17 Используется CacheManager вместо локального массива
     */
    private $cache = array();
    /**
     * Cached post meta values per post to avoid repeated DB lookups.
     *
     * @var array<int, array<string, array<int, mixed>>>
     */
    private $post_meta_cache = array();
    /**
     * Cached ACF field definitions (choices, formats).
     *
     * @var array<string, array<string, mixed>>
     */
    private $acf_field_cache = array();
    private $speciality_reference_map = null;
    
    /**
     * Legacy mapper V2 instance for shared normalization logic.
     *
     * @var YFGP_Field_Mapper_V2|null
     */
    private $legacy_mapper_v2 = null;
    
    /**
     * Helper классы
     */
    private $repeater_helper = null;
    private $relationship_helper = null;
    
    /**
     * Получить singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Конструктор (private для Singleton)
     */
    private function __construct() {
        // Загрузить Repeater Helper если существует
        if (file_exists(YFGP_PLUGIN_DIR . 'includes/helpers/class-repeater-helper.php')) {
            require_once YFGP_PLUGIN_DIR . 'includes/helpers/class-repeater-helper.php';
            if (class_exists('YFGP_Repeater_Helper')) {
                $this->repeater_helper = new YFGP_Repeater_Helper();
            }
        }
        
        // Загрузить Relationship Helper если существует
        if (file_exists(YFGP_PLUGIN_DIR . 'includes/helpers/class-relationship-helper.php')) {
            require_once YFGP_PLUGIN_DIR . 'includes/helpers/class-relationship-helper.php';
            if (class_exists('YFGP_Relationship_Helper')) {
                $this->relationship_helper = new YFGP_Relationship_Helper();
            }
        }
    }
    
    // ========================================================================
    // PUBLIC API - ДЛЯ V2 (BATCH MODE)
    // ========================================================================
    
    /**
     * Batch extraction - для V2 production feed generation
     * 
     * Извлекает ВСЕ поля поста сразу
     * 
     * @since 4.0.0
     * @param int $post_id ID поста
     * @param array<string, mixed> $mapping_array Массив маппинга всех полей
     * @return array<string, mixed> Массив извлечённых данных
     */
    public function mapPostDataBatch($post_id, $mapping_array): array {
        $data = array();
        
        foreach ($mapping_array as $field_key => $field_config) {
            $data[$field_key] = $this->extractField($post_id, $field_config);
        }
        
        return $data;
    }
    
    // ========================================================================
    // PUBLIC API - ДЛЯ V3 (SINGLE FIELD MODE)
    // ========================================================================
    
    /**
     * Single field extraction - для V3 AJAX testing
     * 
     * Извлекает ОДНО поле по запросу
     * 
     * @since 4.0.0
     * @param int $post_id ID поста
     * @param array<string, mixed> $field_config V3 конфигурация поля
     * @param bool $skip_cache Пропустить кэш (для точности при генерации фида)
     * @return mixed Значение поля
     */
    public function getFieldValue($post_id, $field_config, $skip_cache = false) {
        return $this->extractField($post_id, $field_config, $skip_cache);
    }
    
    /**
     * Normalize speciality-like fields (degree/rank/category) using legacy mapper logic.
     *
     * @since 4.18.4
     * @param int|\WP_Post $post Source post or ID
     * @param array<string, mixed> $field_config Field configuration (meta_field)
     * @param mixed $raw_value Raw value from storage
     * @return array{set_ids: array<int, string>, labels: array<int, string>}
     */
    public function normalizeSpecialityField($post, array $field_config, $raw_value): array {
        if (!($post instanceof \WP_Post)) {
            $post = get_post($post);
        }
        
        if (!$post) {
            return array(
                'set_ids' => array(),
                'labels' => array(),
            );
        }
        
        $mapper_v2 = $this->getLegacyMapperV2();
        if (!$mapper_v2 || !method_exists($mapper_v2, 'normalize_specialities_external')) {
            return array(
                'set_ids' => array(),
                'labels' => array(),
            );
        }
        
        return $mapper_v2->normalize_specialities_external($post, $field_config, $raw_value);
    }
    
    // ========================================================================
    // PUBLIC API - UTILITIES
    // ========================================================================
    
    /**
     * Получить доступные поля для UI (V2 + V3 combined best)
     * 
     * @since 4.0.0
     * @param string $post_type Тип поста
     * @return array<string, mixed> Массив доступных полей
     */
    public function getAvailableFields($post_type = 'doctors'): array {
        // Используем ЛУЧШУЮ реализацию из V3 (более полная)
        
        $fields = array(
            'wordpress' => array(),
            'acf' => array(),
            'jetengine' => array(),
            'acf_relationship' => array(),
            'jetengine_relationship' => array(),
            'taxonomy' => array(),
            'repeater' => array(),
            'repeater_jetengine' => array(),
        );
        
        // 1. WordPress стандартные поля
        $fields['wordpress'] = array(
            'post_id' => array('label' => 'ID поста', 'type' => 'number'),
            'post_title' => array('label' => 'Заголовок', 'type' => 'text'),
            'post_content' => array('label' => 'Содержимое', 'type' => 'textarea'),
            'post_excerpt' => array('label' => 'Цитата', 'type' => 'textarea'),
            'permalink' => array('label' => 'Постоянная ссылка', 'type' => 'url'),
            'featured_image' => array('label' => 'Изображение записи', 'type' => 'image'),
            'post_date' => array('label' => 'Дата публикации', 'type' => 'datetime'),
            'post_modified' => array('label' => 'Дата изменения', 'type' => 'datetime'),
            'post_author' => array('label' => 'Автор (ID)', 'type' => 'number'),
            'post_status' => array('label' => 'Статус', 'type' => 'text'),
            'post_name' => array('label' => 'Slug', 'type' => 'text'),
            'post_parent' => array('label' => 'Родитель (ID)', 'type' => 'number'),
            'menu_order' => array('label' => 'Порядок в меню', 'type' => 'number'),
        );
        
        // 2. ACF поля (из V3 реализации)
        if (function_exists('acf_get_field_groups')) {
            $field_groups = acf_get_field_groups(array('post_type' => $post_type));
            
            foreach ($field_groups as $group) {
                $acf_fields = acf_get_fields($group['ID']);
                
                if ($acf_fields) {
                    foreach ($acf_fields as $field) {
                        // Обычные поля
                        if (!in_array($field['type'], array('relationship', 'post_object', 'repeater'))) {
                            $fields['acf'][$field['name']] = array(
                                'label' => $field['label'],
                                'type' => $field['type'],
                                'key' => $field['key']
                            );
                        }
                        
                        // ACF Relationships
                        if (in_array($field['type'], array('relationship', 'post_object'))) {
                            $fields['acf_relationship'][$field['name']] = array(
                                'label' => $field['label'] . ' (ACF Relation)',
                                'type' => 'acf_relationship',
                                'key' => $field['key'],
                                'post_type' => $field['post_type'] ?? array()
                            );
                        }
                        
                        // ACF Repeaters
                        if ($field['type'] === 'repeater') {
                            $fields['repeater'][$field['name']] = array(
                                'label' => $field['label'] . ' (ACF Repeater)',
                                'type' => 'repeater',
                                'key' => $field['key']
                            );
                        }
                    }
                }
            }
        }
        
        // 3. JetEngine meta fields
        // v4.18.15: Унифицирован API метод (get_meta_fields_for_object вместо get_registered_fields)
        // v4.18.15: Добавлено кэширование через transient
        if (function_exists('jet_engine')) {
            $engine = jet_engine();
            
            // Проверить кэш
            $cache_key = 'yfgp_jetengine_fields_' . $post_type;
            $cached_jetengine_fields = get_transient($cache_key);
            
            if ($cached_jetengine_fields !== false && is_array($cached_jetengine_fields)) {
                // Использовать кэшированные данные
                $fields['jetengine'] = $cached_jetengine_fields['jetengine'] ?? array();
                $fields['repeater_jetengine'] = $cached_jetengine_fields['repeater_jetengine'] ?? array();
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf('[YFGP DEBUG] JetEngine fields cache HIT for post_type=%s, fields=%d, repeaters=%d', 
                        $post_type, 
                        count($fields['jetengine']), 
                        count($fields['repeater_jetengine'])
                    ));
                }
            } else {
                // Получить поля из JetEngine API
                if (isset($engine->meta_boxes) && method_exists($engine->meta_boxes, 'get_meta_fields_for_object')) {
                    $jet_meta_fields = $engine->meta_boxes->get_meta_fields_for_object($post_type);
                    
                    if ($jet_meta_fields && is_array($jet_meta_fields)) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log(sprintf('[YFGP DEBUG] JetEngine fields cache MISS for post_type=%s, got %d fields', 
                                $post_type, 
                                count($jet_meta_fields)
                            ));
                        }
                        
                        foreach ($jet_meta_fields as $field) {
                            $field_name = $field['name'] ?? 'NO_NAME';
                            $field_type = $field['type'] ?? 'NO_TYPE';
                            
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log(sprintf('[YFGP DEBUG] Processing JetEngine field: %s (type: %s)', $field_name, $field_type));
                            }
                            
                            if ($field_type !== 'repeater') {
                                $fields['jetengine'][$field_name] = array(
                                    'label' => $field['title'] ?? $field_name,
                                    'type' => $field_type
                                );
                            } else {
                                $fields['repeater_jetengine'][$field_name] = array(
                                    'label' => ($field['title'] ?? $field_name) . ' (JetEngine Repeater)',
                                    'type' => 'repeater_jetengine'
                                );
                            }
                        }
                        
                        // Сохранить в кэш (5 минут TTL, как в других местах)
                        set_transient($cache_key, array(
                            'jetengine' => $fields['jetengine'],
                            'repeater_jetengine' => $fields['repeater_jetengine']
                        ), 5 * MINUTE_IN_SECONDS);
                        
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log(sprintf('[YFGP DEBUG] JetEngine fields cached: fields=%d, repeaters=%d', 
                                count($fields['jetengine']), 
                                count($fields['repeater_jetengine'])
                            ));
                        }
                    } else {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log(sprintf('[YFGP DEBUG] JetEngine get_meta_fields_for_object(%s) returned empty or invalid data', $post_type));
                        }
                    }
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[YFGP DEBUG] JetEngine meta_boxes or get_meta_fields_for_object() method not available');
                    }
                }
            }
        }
        
        // 4. JetEngine Relations (из V2 улучшенная реализация)
        if (function_exists('jet_engine') && class_exists('Jet_Engine\\Relations\\Manager')) {
            $relations_manager = jet_engine()->relations;
            $jet_relations = $relations_manager->get_active_relations();
            
            foreach ($jet_relations as $relation) {
                $parent_object = $relation->get_args('parent_object');
                $child_object = $relation->get_args('child_object');
                $relation_id = $relation->get_args('id');
                $labels = $relation->get_args('labels');
                $relation_name = isset($labels['name']) ? $labels['name'] : 'Связь #' . $relation_id;
                
                // Извлекаем post_type из объекта (posts::doctors -> doctors)
                $parent_post_type = str_replace('posts::', '', $parent_object);
                $child_post_type = str_replace('posts::', '', $child_object);
                
                // Parent to child direction
                if ($parent_post_type === $post_type) {
                    $fields['jetengine_relationship']['jet_rel_' . $relation_id] = array(
                        'label' => $relation_name . ' → ' . $child_post_type,
                        'type' => 'relation',
                        'post_type' => array($child_post_type),
                        'direction' => 'parent_to_child',
                        'relation_id' => $relation_id
                    );
                }
                
                // Child to parent direction (reverse)
                if ($child_post_type === $post_type && $parent_post_type !== $post_type) {
                    $fields['jetengine_relationship']['jet_rel_' . $relation_id . '_reverse'] = array(
                        'label' => $parent_post_type . ' ← ' . $relation_name,
                        'type' => 'relation',
                        'post_type' => array($parent_post_type),
                        'direction' => 'child_to_parent',
                        'relation_id' => $relation_id
                    );
                }
            }
        }
        
        // 5. Taxonomies (из V2)
        $taxonomies = get_object_taxonomies($post_type, 'objects');
        foreach ($taxonomies as $taxonomy) {
            $fields['taxonomy'][$taxonomy->name] = array(
                'label' => $taxonomy->label,
                'type' => 'taxonomy'
            );
        }
        
        return $fields;
    }
    
    /**
     * Очистить кэш
     * 
     * @since 4.0.0
     */
    public function clearCache(): void {
        // v4.18.17: Используем CacheManager для очистки области 'generation'
        $cache_manager = YFGP_Cache_Manager::get_instance();
        $cache_manager->invalidate('generation');
        
        // Очищаем локальные кэши
        $this->post_meta_cache = array();
        $this->acf_field_cache = array();
    }
    
    /**
     * Статистика кэша
     * 
     * @since 4.0.0
     * @return array
     */
    public function getCacheStats(): array {
        // v4.18.17: CacheManager использует transients, статистика сложнее получить
        // Возвращаем базовую информацию
        return array(
            'size' => 0, // CacheManager использует transients, размер не отслеживается
            'memory' => 0, // CacheManager использует transients, память не отслеживается
            'cache_manager' => 'enabled' // Индикатор что используется CacheManager
        );
    }
    
    // ========================================================================
    // CORE EXTRACTION (PRIVATE) - UNIFIED LOGIC
    // ========================================================================
    
    /**
     * Извлечь значение поля (ЦЕНТРАЛЬНЫЙ МЕТОД)
     * 
     * @since 4.0.0
     * @param int $post_id ID поста
     * @param mixed $config Конфигурация поля (V3 array format)
     * @return mixed Значение поля
     */
    /**
     * Нормализация CPT slug
     * 
     * @since 4.18.0
     * @param string $value CPT slug для нормализации
     * @return string Нормализованный CPT slug
     */
    private function normalizeCptSlug($value) {
        if (empty($value)) {
            return '';
        }
        return sanitize_key(trim($value));
    }

    /**
     * Проверка наличия CPT в настройках (guard для защиты от пустых CPT)
     * 
     * @since 4.18.0
     * @param string $cpt_slug CPT slug для проверки
     * @param array $settings Настройки плагина (опционально, если не переданы - получаются из БД)
     * @return bool true если нужно пропустить извлечение (CPT пустой или не выбран)
     */
    private function shouldSkipEmptyCpt($cpt_slug, $settings = null) {
        if (empty($cpt_slug)) {
            return true;
        }
        
        $normalized_slug = $this->normalizeCptSlug($cpt_slug);
        if (empty($normalized_slug)) {
            return true;
        }
        
        // Получаем настройки если не переданы
        if ($settings === null) {
            $settings = get_option('yfgp_settings', array());
        }
        
        // Проверяем наличие CPT в настройках
        $cpt_keys = array('cpt_clinics', 'cpt_services'); // cpt_reviews removed - using source_reviews + source_reviews_cpt instead
        foreach ($cpt_keys as $key) {
            $setting_slug = $this->normalizeCptSlug($settings[$key] ?? '');
            if ($setting_slug === $normalized_slug) {
                return false; // CPT найден в настройках
            }
        }
        
        // v4.18.3: Проверяем source_reviews_cpt для CPT reviews (и других source_*_cpt настроек)
        $source_cpt_keys = array('source_reviews_cpt', 'source_prices_cpt', 'source_education_cpt', 'source_jobs_cpt', 'source_certificates_cpt');
        foreach ($source_cpt_keys as $key) {
            $setting_slug = $this->normalizeCptSlug($settings[$key] ?? '');
            if ($setting_slug === $normalized_slug) {
                return false; // CPT найден в настройках source_*_cpt
            }
        }
        
        // v4.18.19: если CPT зарегистрирован в WP, не пропускаем даже если не выбран в настройках
        if (post_type_exists($normalized_slug)) {
            return false;
        }
        
        return true; // CPT не найден в настройках - пропускаем
    }

    /**
     * Извлечь значение поля с возможностью пропуска кэша
     * 
     * @param int $post_id ID поста
     * @param array<string, mixed> $config V3 конфигурация поля
     * @param bool $skip_cache Пропустить кэш (для точности при генерации фида)
     * @return mixed Значение поля
     */
    private function extractField($post_id, $config, $skip_cache = false) {
        // 1. Валидация: ТОЛЬКО V3 формат (array)!
        if (!is_array($config)) {
            error_log('YFGP Unified: Config must be V3 array format, got ' . gettype($config));
            return null;
        }
        
        if (!isset($config['source_field'])) {
            error_log('YFGP Unified: Invalid V3 config - missing source_field');
            return null;
        }
        
        // v4.18.21: Обработка пустого source_type - если source_field это WordPress поле
        $source_type = isset($config['source_type']) ? $config['source_type'] : '';
        $source_field = $config['source_field'];
        
        // Если source_type пустой, но source_field задан - проверяем WordPress поля
        if (empty($source_type) && !empty($source_field)) {
            $wp_fields = array('post_id', 'post_title', 'post_content', 'post_excerpt', 'post_date', 'post_date_gmt',
                              'post_modified', 'post_modified_gmt', 'post_author', 'post_status', 'post_name', 
                              'post_slug', 'post_parent', 'menu_order', 'permalink', 'featured_image');
            if (in_array($source_field, $wp_fields, true)) {
                // Это WordPress поле - извлекаем напрямую
                $value = $this->getWordpressPostFieldValue($post_id, $source_field);
                
                // v4.18.21: Обработка calculate_type ПОСЛЕ извлечения значения из WordPress поля
                $calculate_type = $config['calculate_type'] ?? '';
                
                // v4.18.21: Fallback для experience_years - если source_field = post_date и source_type пустой, применяем date_to_years по умолчанию
                if (empty($calculate_type) && $source_field === 'post_date') {
                    $calculate_type = 'date_to_years';
                }
                
                if (!empty($calculate_type) && $value !== null && $value !== '') {
                    if ($calculate_type === 'date_to_years' && strtotime((string) $value) !== false) {
                        $date_timestamp = strtotime((string) $value);
                        $current_year = (int) date('Y');
                        $value_year = (int) date('Y', $date_timestamp);
                        $calculated_years = $current_year - $value_year;
                        if ($calculated_years >= 0 && $calculated_years <= 100) {
                            $value = (string) $calculated_years;
                            error_log('YFGP Unified: Converted date to years for empty source_type: ' . $source_field . ' -> ' . $calculated_years);
                        } else {
                            error_log('YFGP Unified: Invalid calculated years: ' . $calculated_years);
                            $value = null;
                        }
                    } elseif ($calculate_type === 'date_as_is' && strtotime((string) $value) !== false) {
                        $value = date('Y-m-d', strtotime((string) $value));
                        error_log('YFGP Unified: Normalized date to YYYY-MM-DD: ' . $value);
                    }
                }
                
                // Кэшируем результат
                if (!$skip_cache) {
                    $cache_key = $this->getCacheKey($post_id, $config);
                    $cache_manager = YFGP_Cache_Manager::get_instance();
                    $cache_manager->set('generation', $cache_key, $value);
                }
                
                return $value;
            } else {
                // Неизвестное поле с пустым source_type - пробуем через extractMetaField (может быть это meta_field с пустым source_type)
                return $this->extractMetaField($post_id, $config);
            }
        }
        
        // Обычная обработка с source_type
        // 2. Check cache
        $cache_key = $this->getCacheKey($post_id, $config);
        if (!$skip_cache) {
            // v4.18.17: Используем CacheManager с областью 'generation'
            $cache_manager = YFGP_Cache_Manager::get_instance();
            $cached_value = $cache_manager->get('generation', $cache_key);
            if ($cached_value !== null) {
                return $cached_value;
            }
        }
        
        // 3. Extract based on source_type
        $value = $this->extractBySourceType($post_id, $config);
        
        // v4.18.21: Обработка calculate_type ПОСЛЕ извлечения значения
        $calculate_type = $config['calculate_type'] ?? '';
        
        if (!empty($calculate_type) && $value !== null && $value !== '') {
            if ($calculate_type === 'date_to_years' && strtotime((string) $value) !== false) {
                $date_timestamp = strtotime((string) $value);
                $current_year = (int) date('Y');
                $value_year = (int) date('Y', $date_timestamp);
                $calculated_years = $current_year - $value_year;
                if ($calculated_years >= 0 && $calculated_years <= 100) {
                    $value = (string) $calculated_years;
                    error_log('YFGP Unified: Converted date to years: ' . $source_field . ' -> ' . $calculated_years);
                } else {
                    error_log('YFGP Unified: Invalid calculated years: ' . $calculated_years);
                    $value = null;
                }
            } elseif ($calculate_type === 'date_as_is' && strtotime((string) $value) !== false) {
                $value = date('Y-m-d', strtotime((string) $value));
                error_log('YFGP Unified: Normalized date to YYYY-MM-DD: ' . $value);
            }
        }

        if (!empty($config['conditional_logic']) && !empty($config['operator'])) {
            $value = $this->applyConditionalLogic($post_id, $value, $config);
        }
        
        // 4. Cache and return (только если была обычная обработка с source_type)
        if (!empty($source_type) && !$skip_cache) {
            $cache_key = $this->getCacheKey($post_id, $config);
            // v4.18.17: Используем CacheManager с областью 'generation'
            $cache_manager = YFGP_Cache_Manager::get_instance();
            $cache_manager->set('generation', $cache_key, $value);
        }
        
        return $value;
    }
    
    /**
     * Извлечение по типу источника
     * 
     * @since 4.0.0
     * @param int $post_id ID поста
     * @param array<string, mixed> $config V3 конфигурация
     * @return mixed Значение поля
     */
    private function extractBySourceType($post_id, $config) {
        $source_type = $config['source_type'] ?? '';
        $source_field = $config['source_field'] ?? '';
        
        // v4.18.21: Обработка пустого source_type - если source_field это WordPress поле
        if (empty($source_type) && !empty($source_field)) {
            $wp_fields = array('post_id', 'post_title', 'post_content', 'post_excerpt', 'post_date', 'post_date_gmt',
                              'post_modified', 'post_modified_gmt', 'post_author', 'post_status', 'post_name', 
                              'post_slug', 'post_parent', 'menu_order', 'permalink', 'featured_image');
            if (in_array($source_field, $wp_fields, true)) {
                // Это WordPress поле - извлекаем напрямую
                $value = $this->getWordpressPostFieldValue($post_id, $source_field);
                
                // v4.18.21: Обработка calculate_type ПОСЛЕ извлечения значения из WordPress поля
                $calculate_type = $config['calculate_type'] ?? '';
                
                // v4.18.21: Fallback для experience_years - если source_field = post_date и source_type пустой, применяем date_to_years по умолчанию
                if (empty($calculate_type) && $source_field === 'post_date') {
                    $calculate_type = 'date_to_years';
                }
                
                if (!empty($calculate_type) && $value !== null && $value !== '') {
                    if ($calculate_type === 'date_to_years' && strtotime((string) $value) !== false) {
                        $date_timestamp = strtotime((string) $value);
                        $current_year = (int) date('Y');
                        $value_year = (int) date('Y', $date_timestamp);
                        $calculated_years = $current_year - $value_year;
                        if ($calculated_years >= 0 && $calculated_years <= 100) {
                            $value = (string) $calculated_years;
                            error_log('YFGP Unified: Converted date to years in extractBySourceType: ' . $source_field . ' -> ' . $calculated_years);
                        } else {
                            error_log('YFGP Unified: Invalid calculated years: ' . $calculated_years);
                            $value = null;
                        }
                    } elseif ($calculate_type === 'date_as_is' && strtotime((string) $value) !== false) {
                        $value = date('Y-m-d', strtotime((string) $value));
                        error_log('YFGP Unified: Normalized date to YYYY-MM-DD in extractBySourceType: ' . $value);
                    }
                }
                
                return $value;
            }
            // Если не WordPress поле - пробуем через extractMetaField (может быть это meta_field с пустым source_type)
            return $this->extractMetaField($post_id, $config);
        }
        
        switch ($source_type) {
            // === БАЗОВЫЕ ТИПЫ ===
            
            case 'meta_field':
                return $this->extractMetaField($post_id, $config);
            
            case 'relationship_1':
                return $this->extractRelationship1($post_id, $config);
            
            case 'relationship_2':
                return $this->extractRelationship2($post_id, $config);
            
            case 'taxonomy':
                error_log('[UNIFIED getFieldValue] INSIDE case taxonomy! Calling extractTaxonomy...');
                return $this->extractTaxonomy($post_id, $config);
            
            case 'taxonomy_meta':
                return $this->extractTaxonomyMeta($post_id, $config);
            
            case 'fixed':
                return $this->extractFixed($config);
            
            case 'boolean':
                return $config['source_field']; // 'true' or 'false' as string
            
            // === REPEATER TYPES ===
            
            case 'repeater_acf':
            case 'repeater_jetengine':
                if ($this->repeater_helper) {
                    return $this->repeater_helper->get_data($post_id, $config);
                } else {
                    error_log('YFGP Unified: Repeater Helper not loaded');
                    return array();
                }
            
            default:
                // v4.18.21: Не логируем для пустого source_type (уже обработано выше)
                if (!empty($source_type)) {
                    error_log('YFGP Unified: Unknown source_type: ' . $source_type);
                }
                return null;
        }
    }
    
    // ========================================================================
    // EXTRACTION METHODS (PRIVATE) - MERGED FROM V2 + V3
    // ========================================================================
    
    /**
     * Извлечь meta field (ACF или WordPress meta)
     * 
     * Объединяет логику из V2::get_direct_field() и V3::get_meta_field()
     * 
     * @since 4.0.0
     * @param int $post_id ID поста
     * @param array<string, mixed> $config Конфигурация поля
     * @return mixed Значение поля
     */
    private function extractMetaField($post_id, $config) {
        $field_name = $config['source_field'];
        $source_cpt = isset($config['source_cpt']) ? trim((string) $config['source_cpt']) : '';
        
        // v4.18.21: Если указан source_cpt, сначала найти связанный пост этого CPT через relationship
        // Это нужно для случаев, когда нужно извлечь поле из связанного поста (например, post_title из discount)
        if (!empty($source_cpt)) {
            // Пробуем найти связанный пост через все возможные relationships
            $related_post_id = $this->findRelatedPostByCpt($post_id, $source_cpt);
            if ($related_post_id !== null) {
                // Найден связанный пост - извлекаем поле из него
                $wp_fields = array('post_id', 'post_title', 'post_content', 'post_excerpt', 'post_date', 'post_date_gmt', 
                                  'post_modified', 'post_modified_gmt', 'post_author', 'post_status', 'post_name', 
                                  'post_slug', 'post_parent', 'menu_order', 'permalink', 'featured_image');
                if (in_array($field_name, $wp_fields, true)) {
                    $post_value = $this->getWordpressPostFieldValue($related_post_id, $field_name);
                    if ($post_value !== null) {
                        error_log('YFGP v4.18.21: Extracted ' . $field_name . ' from related ' . $source_cpt . ' post ' . $related_post_id . ' for post ' . $post_id);
                        return $post_value;
                    }
                }
                // Если не WordPress поле, пробуем через ACF или meta
                if (function_exists('get_field')) {
                    $value = get_field($field_name, $related_post_id);
                    if ($value !== false && $value !== null && $value !== '') {
                        error_log('YFGP v4.18.21: Extracted ' . $field_name . ' from related ' . $source_cpt . ' post ' . $related_post_id . ' via ACF for post ' . $post_id);
                        return $value;
                    }
                }
                $value = $this->getMetaValueFromPost($related_post_id, $field_name);
                if ($value !== null && $value !== '') {
                    error_log('YFGP v4.18.21: Extracted ' . $field_name . ' from related ' . $source_cpt . ' post ' . $related_post_id . ' via meta for post ' . $post_id);
                    return $value;
                }
            } else {
                error_log('YFGP v4.18.21: Warning - Could not find related ' . $source_cpt . ' post for post ' . $post_id);
            }
        }
        
        // v4.18.21: Приоритет 1: WordPress standard post fields (если source_field это WordPress поле)
        $wp_fields = array('post_id', 'post_title', 'post_content', 'post_excerpt', 'post_date', 'post_date_gmt', 
                          'post_modified', 'post_modified_gmt', 'post_author', 'post_status', 'post_name', 
                          'post_slug', 'post_parent', 'menu_order', 'permalink', 'featured_image');
        if (in_array($field_name, $wp_fields, true)) {
            $post_value = $this->getWordpressPostFieldValue($post_id, $field_name);
            if ($post_value !== null) {
                return $post_value;
            }
        }
        
        // Приоритет 2: ACF get_field() (поддерживает и ACF и meta)
        if (function_exists('get_field')) {
            $value = get_field($field_name, $post_id);
            if ($value !== false && $value !== null && $value !== '') {
                return $value;
            }
        }
        
        // Приоритет 3: WordPress standard post fields fallback (если не проверили выше)
        if (!in_array($field_name, $wp_fields, true)) {
            $post_value = $this->getWordpressPostFieldValue($post_id, $field_name);
            if ($post_value !== null) {
                return $post_value;
            }
        }
        
        // Приоритет 4: get_post_meta fallback
        $value = $this->getMetaValueFromPost($post_id, $field_name);

        return $value;
    }
    
    /**
     * Найти связанный пост указанного CPT через relationships
     * 
     * @param int $post_id ID поста
     * @param string $target_cpt CPT связанного поста
     * @return int|null ID связанного поста или null
     */
    private function findRelatedPostByCpt($post_id, $target_cpt) {
        global $wpdb;
        
        // Пробуем найти через JetEngine relationships (обратная связь)
        // Используем API JetEngine вместо хардкода названий таблиц
        if (function_exists('jet_engine') && isset(jet_engine()->relations)) {
            $relations = jet_engine()->relations->get_active_relations();
            
            foreach ($relations as $relation) {
                // Получаем имя таблицы через API
                if (isset($relation->db) && method_exists($relation->db, 'table')) {
                    $table = $relation->db->table();
                    
                    // Проверяем прямую связь (parent_object_id = $post_id)
                    $related_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT child_object_id FROM {$table} 
                        WHERE parent_object_id = %d 
                        AND child_object_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish')
                        LIMIT 1",
                        $post_id,
                        $target_cpt
                    ));
                    
                    if ($related_id) {
                        return (int) $related_id;
                    }
                    
                    // Проверяем обратную связь (child_object_id = $post_id)
                    $related_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT parent_object_id FROM {$table} 
                        WHERE child_object_id = %d 
                        AND parent_object_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish')
                        LIMIT 1",
                        $post_id,
                        $target_cpt
                    ));
                    
                    if ($related_id) {
                        return (int) $related_id;
                    }
                }
            }
        }
        
        // Пробуем через ACF relationships
        if (function_exists('get_field_objects')) {
            $fields = get_field_objects($post_id);
            if ($fields) {
                foreach ($fields as $field) {
                    if ($field['type'] === 'relationship' || $field['type'] === 'post_object') {
                        $value = get_field($field['name'], $post_id);
                        if (!empty($value)) {
                            $posts = is_array($value) ? $value : array($value);
                            foreach ($posts as $post) {
                                $related_post_id = is_object($post) ? $post->ID : $post;
                                $related_post = get_post($related_post_id);
                                if ($related_post && $related_post->post_type === $target_cpt) {
                                    return $related_post_id;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return null;
    }

    private function getWordpressPostFieldValue(int $post_id, string $field_name, $post_object = null)
    {
        $field_name = trim((string) $field_name);

        if ($field_name === '') {
            return null;
        }

        $post = null;
        if ($post_object instanceof \WP_Post) {
            $post = $post_object;
        } else {
            $post = get_post($post_id);
        }

        if (!$post) {
            return null;
        }

        switch ($field_name) {
            case 'post_id':
            case 'ID':
                return $post->ID;
            case 'post_title':
                return $post->post_title;
            case 'post_content':
                return $post->post_content;
            case 'post_excerpt':
                return $post->post_excerpt;
            case 'post_date':
                return $post->post_date;
            case 'post_date_gmt':
                return $post->post_date_gmt;
            case 'post_modified':
                return $post->post_modified;
            case 'post_modified_gmt':
                return $post->post_modified_gmt;
            case 'post_author':
                return (int) $post->post_author;
            case 'post_status':
                return $post->post_status;
            case 'post_name':
            case 'post_slug':
                return $post->post_name;
            case 'post_parent':
                return (int) $post->post_parent;
            case 'menu_order':
                return (int) $post->menu_order;
            case 'guid':
                return $post->guid;
            case 'post_type':
                return $post->post_type;
            case 'post_mime_type':
                return $post->post_mime_type;
            case 'comment_count':
                return (int) $post->comment_count;
            case 'permalink':
                return get_permalink($post->ID);
            case 'featured_image':
            case 'thumbnail':
                $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'full');

                return $thumbnail_url ? $thumbnail_url : null;
            default:
                return null;
        }
    }

    /**
     * Resolve meta value with suffix-based fallback and serialization handling.
     *
     * @param int $post_id
     * @param string $field_name
     * @return mixed
     */
    private function getMetaValueFromPost($post_id, $field_name) {
        if (empty($field_name)) {
            return null;
        }

        $value = get_post_meta($post_id, $field_name, true);

        if ($this->isEmptyMetaValue($value)) {
            $value = $this->getMetaValueWithSuffixFallback($post_id, $field_name);
        }

        if (is_string($value)) {
            $unserialized = maybe_unserialize($value);
            if ($unserialized !== false || $value === 'b:0;') {
                $value = $unserialized;
            } else {
                $parsed = $this->parseSerializedCheckboxFallback($value);
                if ($parsed !== null) {
                    $value = $parsed;
                }
            }
        }

        return ($value === false) ? null : $value;
    }

    /**
     * Determine if meta value should be treated as empty.
     *
     * @param mixed $value
     * @return bool
     */
    private function isEmptyMetaValue($value): bool {
        if ($value === null || $value === false) {
            return true;
        }

        if (is_string($value)) {
            return $value === '';
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_array($item) && !empty($item)) {
                    return false;
                }

                if (!in_array($item, array('', null, false, 'false', '0', 0), true)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Try to locate meta value by matching keys that end with the target field name.
     * Handles ACF cases where the meta key is prefixed.
     *
     * @param int $post_id
     * @param string $field_name
     * @return mixed
     */
    private function getMetaValueWithSuffixFallback($post_id, $field_name) {
        $meta_map = $this->getPostMetaMap($post_id);

        if (empty($meta_map)) {
            return null;
        }

        $normalized_field = $this->normalizeMetaKey($field_name);

        foreach ($meta_map as $meta_key => $values) {
            if ($meta_key === $field_name) {
                continue;
            }

            if ($meta_key !== '' && $meta_key[0] === '_') {
                continue;
            }

            $normalized_key = $this->normalizeMetaKey($meta_key);

            $matched = false;
            if ($normalized_key === $normalized_field) {
                $matched = true;
            } elseif ($this->metaKeyEndsWith($normalized_key, $normalized_field)) {
                $prefix = substr($normalized_key, 0, -strlen($normalized_field));
                $prefix = rtrim($prefix, '_');

                if ($prefix === '' || preg_match('/^[a-z0-9_]+$/', $prefix)) {
                    $matched = true;
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log(sprintf('[YFGP DEBUG] meta_key "%s" skipped: non-ascii prefix "%s" for field "%s"', $meta_key, $prefix, $field_name));
                    }
                    continue;
                }
            }

            if (!$matched) {
                continue;
            }

            $candidate = $this->firstMetaValue($values);

            if (!$this->isEmptyMetaValue($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Return cached full meta map for a post.
     *
     * @param int $post_id
     * @return array<string, array<int, mixed>>
     */
    private function getPostMetaMap($post_id): array {
        if (!isset($this->post_meta_cache[$post_id])) {
            $this->post_meta_cache[$post_id] = get_post_meta($post_id);
        }

        return $this->post_meta_cache[$post_id];
    }

    /**
     * Normalize meta key for comparisons.
     *
     * @param string $key
     * @return string
     */
    private function normalizeMetaKey($key): string {
        $normalized = strtolower((string) $key);
        $normalized = str_replace('-', '_', $normalized);
        $normalized = preg_replace('/_+/', '_', $normalized);

        return (string) $normalized;
    }

    /**
     * Determine if normalized meta key ends with the normalized field name.
     *
     * @param string $normalized_key
     * @param string $normalized_field
     * @return bool
     */
    private function metaKeyEndsWith($normalized_key, $normalized_field): bool {
        if ($normalized_field === '') {
            return false;
        }

        if (strlen($normalized_key) < strlen($normalized_field)) {
            return false;
        }

        return substr($normalized_key, -strlen($normalized_field)) === $normalized_field;
    }

    /**
     * Extract the first value from meta storage array.
     *
     * @param mixed $values
     * @return mixed
     */
    private function firstMetaValue($values) {
        if (is_array($values)) {
            if (empty($values)) {
                return null;
            }

            return reset($values);
        }

        return $values;
    }

    /**
     * Parse legacy serialized checkbox string if default unserialize fails.
     *
     * @param string $raw_value
     * @return array<string, string>|null
     */
    private function parseSerializedCheckboxFallback($raw_value) {
        $raw_value = trim((string) $raw_value);

        if ($raw_value === '') {
            return null;
        }

        $json = json_decode($raw_value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json;
        }

        if (strpos($raw_value, 's:') !== false && strpos($raw_value, '";s:') !== false) {
            if (preg_match_all('/s:\\d+:"([^\"]+)";s:\\d+:"(true|false|1|0)"/i', $raw_value, $matches, PREG_SET_ORDER)) {
                $result = array();
                foreach ($matches as $match) {
                    $result[$match[1]] = $match[2];
                }

                return $result;
            }
        }

        return null;
    }
    
    /**
     * Извлечь relationship 1 уровня
     * 
     * Объединяет V2::get_relation_1_field() и V3::get_relationship_basic()
     * Использует relationship_helper если доступен
     * 
     * @since 4.0.0
     * @param int $post_id ID поста
     * @param array<string, mixed> $config Конфигурация связи
     * @return mixed Данные связанных постов
     */
    private function extractRelationship1($post_id, $config) {
        // Guard: проверка наличия CPT в настройках если используется source_cpt
        if (!empty($config['source_cpt'])) {
            $cpt_slug = $config['source_cpt'];
            if ($this->shouldSkipEmptyCpt($cpt_slug)) {
                return array(); // Пропускаем извлечение если CPT не выбран
            }
        }
        
        if ($this->relationship_helper) {
            $related = $this->relationship_helper->get_relationship_data($post_id, $config);
        } else {
            $related = $this->extractRelationshipBasic($post_id, $config);
        }

        $items = $this->normalizeRelationshipItems($related);
        $nested_field = isset($config['nested_field']) ? trim((string) $config['nested_field']) : '';

        if ($nested_field === '') {
            return $items;
        }

        $values = array();

        foreach ($items as $item) {
            $value = $this->resolveNestedRelationshipValue($item, $nested_field);

            if (is_array($value)) {
                foreach ($value as $sub_value) {
                    if ($sub_value !== null && $sub_value !== '') {
                        $values[] = $sub_value;
                    }
                }
            } elseif ($value !== null && $value !== '') {
                $values[] = $value;
            }
        }

        return !empty($values) ? $values : $items;
    }

    private function extractRelationship2($post_id, $config) {
        // Guard: проверка наличия CPT в настройках если используется source_cpt
        if (!empty($config['source_cpt'])) {
            $cpt_slug = $config['source_cpt'];
            if ($this->shouldSkipEmptyCpt($cpt_slug)) {
                return array(); // Пропускаем извлечение если CPT не выбран
            }
        }
        
        if ($this->relationship_helper) {
            $related = $this->relationship_helper->get_relationship_data($post_id, $config);
        } else {
            $related = $this->extractRelationshipNested($post_id, $config);
        }

        $nested_field = isset($config['nested_field']) ? trim((string) $config['nested_field']) : '';
        if ($nested_field === '') {
            return $related;
        }

        $items = $this->normalizeRelationshipItems($related);
        if (empty($items)) {
            return null;
        }

        $value = $this->resolveNestedRelationshipValue($items[0], $nested_field);

        if (is_array($value)) {
            $value = reset($value);
        }

        if ($value !== null && $value !== '') {
            return $value;
        }

        return $related;
    }    /**
     * Извлечь taxonomy terms
     * 
     * @since 4.0.0
     * @param int $post_id ID поста
     * @param array<string, mixed> $config Конфигурация таксономии
     * @return array|string Термины таксономии
     */
    private function extractTaxonomy($post_id, $config) {
        $taxonomy = $config['source_field'];
        error_log('[UNIFIED extractTaxonomy] post_id=' . $post_id . ', taxonomy=' . $taxonomy);
        
        $terms = get_the_terms($post_id, $taxonomy);
        error_log('[UNIFIED extractTaxonomy] get_the_terms result: ' . print_r($terms, true));
        
        if (is_wp_error($terms) || !$terms) {
            return array();
        }
        
        // Вернуть массив терминов с полной информацией
        $result = array();
        foreach ($terms as $term) {
            $result[] = array(
                'term_id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug
            );
        }
        
        return $result;
    }
    
    /**
     * Извлечь taxonomy meta
     * 
     * @since 4.0.0
     * @param int $post_id ID поста
     * @param array<string, mixed> $config Конфигурация
     * @return mixed Значение term meta
     */
    private function extractTaxonomyMeta($post_id, $config) {
        $taxonomy = $config['source_field'];
        $meta_field = $config['nested_field'] ?? '';
        
        if (empty($meta_field)) {
            return null;
        }
        
        $terms = get_the_terms($post_id, $taxonomy);
        
        if (is_wp_error($terms) || !$terms) {
            return null;
        }
        
        // Берём первый термин
        $term = $terms[0];
        
        return get_term_meta($term->term_id, $meta_field, true);
    }
    
    /**
     * Извлечь фиксированное значение
     * 
     * @since 4.0.0
     * @param array<string, mixed> $config Конфигурация
     * @return mixed Фиксированное значение
     */
    private function extractFixed($config) {
        return $config['source_field'] ?? null;
    }
    
    private function normalizeRelationshipItems($items): array {
        if ($items === null) {
            return array();
        }

        if (is_array($items)) {
            return array_values($items);
        }

        return array($items);
    }

    private function extractPostIdFromRelationshipItem($item): ?int {
        if (is_object($item) && isset($item->ID)) {
            return (int) $item->ID;
        }

        if (is_array($item)) {
            if (isset($item['ID'])) {
                return (int) $item['ID'];
            }

            if (isset($item['id'])) {
                return (int) $item['id'];
            }

            if (isset($item['post_id'])) {
                return (int) $item['post_id'];
            }

            if (isset($item['value']) && is_numeric($item['value'])) {
                return (int) $item['value'];
            }
        }

        if (is_numeric($item)) {
            return (int) $item;
        }

        return null;
    }

    private function resolveNestedRelationshipValue($item, string $nested_field) {
        $nested_field = trim($nested_field);

        if ($nested_field === '') {
            return $item;
        }

        if (in_array($nested_field, array('post_id', 'ID'), true)) {
            return $this->extractPostIdFromRelationshipItem($item);
        }

        if (is_array($item) && array_key_exists($nested_field, $item)) {
            return $item[$nested_field];
        }

        $post_id = $this->extractPostIdFromRelationshipItem($item);
        if ($post_id !== null) {
            $post_object = (is_object($item) && $item instanceof \WP_Post) ? $item : null;

            $wp_value = $this->getWordpressPostFieldValue($post_id, $nested_field, $post_object);
            if ($wp_value !== null) {
                return $wp_value;
            }

            $meta_value = $this->getMetaValueFromPost($post_id, $nested_field);
            if ($meta_value !== null && $meta_value !== '') {
                return $meta_value;
            }
        }

        if (!is_array($item) && !is_object($item)) {
            return $item;
        }

        return null;
    }
    
    // ========================================================================
    // RELATIONSHIP EXTRACTION (FALLBACK WITHOUT HELPER)
    // ========================================================================
    
    /**
     * Базовое извлечение relationship (без helper)
     * 
     * @since 4.0.0
     * @param int $post_id ID поста
     * @param array<string, mixed> $config Конфигурация связи
     * @return array<string, mixed> Массив связанных постов
     */
    private function extractRelationshipBasic($post_id, $config) {
        $field_name = $config['source_field'];
        $related_posts = array();
        
        // 1. ACF Relationship/Post Object
        if (function_exists('get_field')) {
            $acf_value = get_field($field_name, $post_id);
            
            if (!empty($acf_value)) {
                // Array of posts
                if (is_array($acf_value)) {
                    foreach ($acf_value as $item) {
                        if (is_object($item) && isset($item->ID)) {
                            $related_posts[] = $item;
                        } elseif (is_numeric($item)) {
                            $post = get_post($item);
                            if ($post) {
                                $related_posts[] = $post;
                            }
                        }
                    }
                }
                // Single post (ACF Post Object single)
                elseif (is_object($acf_value) && isset($acf_value->ID)) {
                    $related_posts[] = $acf_value;
                } elseif (is_numeric($acf_value)) {
                    $post = get_post($acf_value);
                    if ($post) {
                        $related_posts[] = $post;
                    }
                }
            }
        }
        
        // 2. JetEngine Relations (если ACF не вернуло результат)
        if (empty($related_posts) && function_exists('jet_engine')) {
            // Извлечь relation ID из field_name (jet_rel_30 → 30)
            if (preg_match('/jet_rel_(\d+)(_reverse)?/', $field_name, $matches)) {
                $relation_id = intval($matches[1]);
                $is_reverse = isset($matches[2]);
                
                $relations = jet_engine()->relations->get_active_relations();
                
                foreach ($relations as $relation) {
                    if ($relation->get_args('id') == $relation_id) {
                        $items = $relation->get_related_items($post_id, !$is_reverse);
                        
                        // v4.18.23 FIX: Если JetEngine API не сохранил порядок, используем прямой запрос к БД с ORDER BY _ID
                        // v4.18.25 FIX: Используем API метод для получения имени таблицы вместо хардкода
                        if (empty($items)) {
                            global $wpdb;
                            $relation_table = '';
                            
                            // Используем API метод если доступен
                            if (isset($relation->db) && method_exists($relation->db, 'table')) {
                                $relation_table = $relation->db->table();
                                // Если таблица получена через API, она должна существовать
                                $table_exists = true;
                            } else {
                                // Fallback только если API недоступен (для обратной совместимости)
                                $relation_table = $wpdb->prefix . 'jet_rel_' . $relation_id;
                                // Проверяем существование таблицы для fallback
                                $table_exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $relation_table));
                            }
                            
                            if ($relation_table && $table_exists) {
                                if ($is_reverse) {
                                    $items = $wpdb->get_col($wpdb->prepare("SELECT parent_object_id FROM {$relation_table} WHERE child_object_id = %d ORDER BY _ID ASC", $post_id));
                                } else {
                                    $items = $wpdb->get_col($wpdb->prepare("SELECT child_object_id FROM {$relation_table} WHERE parent_object_id = %d ORDER BY _ID ASC", $post_id));
                                }
                                error_log("YFGP v4.18.25: JetEngine order fix - using DB query with ORDER BY _ID for relation {$relation_id}, post {$post_id}");
                            }
                        }
                        
                        if (!empty($items)) {
                            foreach ($items as $item_id) {
                                $post = get_post($item_id);
                                if ($post) {
                                    $related_posts[] = $post;
                                }
                            }
                        }
                        break;
                    }
                }
            }
        }
        
        return $related_posts;
    }
    
    /**
     * Вложенное извлечение relationship 2 уровня
     * 
     * @since 4.0.0
     * @param int $post_id ID поста
     * @param array<string, mixed> $config Конфигурация вложенной связи
     * @return mixed Данные из вложенной связи
     */
    private function extractRelationshipNested($post_id, $config) {
        // Guard: проверка наличия CPT в настройках если используется source_cpt
        if (!empty($config['source_cpt'])) {
            $cpt_slug = $config['source_cpt'];
            if ($this->shouldSkipEmptyCpt($cpt_slug)) {
                return array(); // Пропускаем извлечение если CPT не выбран
            }
        }
        $level1_posts = $this->normalizeRelationshipItems($this->extractRelationshipBasic($post_id, $config));

        if (empty($level1_posts)) {
            return null;
        }

        $nested_field = isset($config['nested_field']) ? trim((string) $config['nested_field']) : '';
        if ($nested_field === '') {
            return $level1_posts[0];
        }

        $value = $this->resolveNestedRelationshipValue($level1_posts[0], $nested_field);

        if (is_array($value)) {
            $value = reset($value);
        }

        if ($value !== null && $value !== '') {
            return $value;
        }

        return null;
    }
    private function parseFullName($full_name) {
        $parts = preg_split('/\s+/', trim($full_name));
        
        return array(
            'surname' => $parts[0] ?? '',
            'firstname' => $parts[1] ?? '',
            'patronymic' => $parts[2] ?? ''
        );
    }
    
    /**
     * Генерация cache key
     * 
     * Из V3
     * 
     * @since 4.0.0
     * @param int $post_id ID поста
     * @param array<string, mixed> $config Конфигурация поля
     * @return string Cache key
     */
    private function getCacheKey($post_id, $config) {
        return md5($post_id . '_' . json_encode($config));
    }
    
    /**
     * Оценка памяти кэша
     * 
     * Из V3
     * 
     * @since 4.0.0
     * @return int Приблизительный размер в байтах
     */
    private function estimateCacheMemory() {
        $memory = 0;
        foreach ($this->cache as $key => $value) {
            $memory += strlen($key) + strlen(serialize($value));
        }
        return $memory;
    }
    
    /**
     * Валидация конфигурации поля
     * 
     * Из V3
     * 
     * @since 4.0.0
     * @param array<string, mixed> $config Конфигурация для валидации
     * @return array<string, mixed> [valid => bool, errors => array]
     */
    public function validateFieldConfig($config) {
        $errors = array();
        
        if (!is_array($config)) {
            $errors[] = 'Config must be V3 array format';
        }
        
        if (!isset($config['source_type'])) {
            $errors[] = 'Missing source_type';
        }
        
        if (!isset($config['source_field'])) {
            $errors[] = 'Missing source_field';
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Получить ACF repeater subfields
     * 
     * Из V3
     * 
     * @since 4.0.0
     * @param string $field_name Название repeater поля
     * @return array<string, mixed> Список подполей
     */
    public function getAcfRepeaterSubfields($field_name) {
        if (!function_exists('acf_get_field')) {
            return array();
        }
        
        $field = acf_get_field($field_name);
        
        if (!$field || $field['type'] !== 'repeater') {
            return array();
        }
        
        $subfields = array();
        
        if (isset($field['sub_fields']) && is_array($field['sub_fields'])) {
            foreach ($field['sub_fields'] as $subfield) {
                $subfields[$subfield['name']] = array(
                    'label' => $subfield['label'],
                    'type' => $subfield['type'],
                    'key' => $subfield['key']
                );
            }
        }
        
        return $subfields;
    }
    
    /**
     * Получить JetEngine repeater subfields
     * 
     * Из V3 (с WordPress Transient cache для производительности)
     * 
     * @since 4.0.0
     * @param string $field_name Название repeater поля
     * @param string $post_type Тип поста
     * @return array<string, mixed> Список подполей
     */
    public function getJetengineRepeaterSubfields($field_name, $post_type = 'doctors') {
        // v4.18.15: Debug логи обёрнуты в if (WP_DEBUG)
        $start_time = microtime(true);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[YFGP DEBUG] getJetengineRepeaterSubfields START: field=%s, post_type=%s', $field_name, $post_type));
        }
        
        if (!function_exists('jet_engine')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[YFGP DEBUG] jet_engine() not found!');
            }
            return array();
        }
        
        // Проверить WordPress Transient cache (5 минут TTL)
        $cache_key = 'yfgp_jetengine_fields_' . $post_type;
        $cached_fields = get_transient($cache_key);
        
        if ($cached_fields !== false && isset($cached_fields[$field_name])) {
            $elapsed = round((microtime(true) - $start_time) * 1000, 2);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[YFGP DEBUG] Cache HIT! Returned in %sms', $elapsed));
            }
            return $cached_fields[$field_name];
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[YFGP DEBUG] Cache MISS for key: %s', $cache_key));
        }
        
        // v4.1.4: CORRECT METHOD - Use get_meta_fields_for_object() from Task #11 v3.4.0
        if (!function_exists('jet_engine')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[YFGP DEBUG] jet_engine() function NOT exists!');
            }
            return array();
        }
        
        $engine = jet_engine();
        if (!isset($engine->meta_boxes)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[YFGP DEBUG] meta_boxes property NOT exists!');
            }
            return array();
        }
        
        // Use get_meta_fields_for_object() - WORKING method from v3.4.0
        $before_get = microtime(true);
        $jet_meta_fields = $engine->meta_boxes->get_meta_fields_for_object($post_type);
        $get_time = round((microtime(true) - $before_get) * 1000, 2);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[YFGP DEBUG] get_meta_fields_for_object(%s) took %sms, got %d fields', 
                $post_type, $get_time, count($jet_meta_fields ?? [])));
        }
        
        if (!$jet_meta_fields) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[YFGP DEBUG] get_meta_fields_for_object() returned NULL!');
            }
            return array();
        }
        
        // Search for repeater field
        foreach ($jet_meta_fields as $field) {
            if ($field['name'] === $field_name && $field['type'] === 'repeater') {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf('[YFGP DEBUG] FOUND repeater field "%s"!', $field_name));
                }
                
                $subfields = array();
                
                if (isset($field['repeater-fields']) && is_array($field['repeater-fields'])) {
                    foreach ($field['repeater-fields'] as $subfield) {
                        $subfields[$subfield['name']] = array(
                            'label' => $subfield['title'] ?? $subfield['name'],
                            'type' => $subfield['type'] ?? 'text'
                        );
                    }
                }
                
                // Cache result
                set_transient($cache_key, array($field_name => $subfields), 5 * MINUTE_IN_SECONDS);
                
                $total_time = round((microtime(true) - $start_time) * 1000, 2);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf('[YFGP DEBUG] SUCCESS! Total time: %sms, returned %d subfields', 
                        $total_time, count($subfields)));
                }
                
                return $subfields;
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[YFGP DEBUG] WARNING: Repeater field "%s" NOT FOUND in %d fields', 
                $field_name, count($jet_meta_fields)));
        }
        
        
        $total_time = round((microtime(true) - $start_time) * 1000, 2);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[YFGP DEBUG] FAILED! Total time: %sms, returning empty array', $total_time));
        }
        
        return array();
    }
    
    /**
     * Получить labels из JetEngine Glossary для checkbox field
     * 
     * @since v4.10.8
     * @param string $field_name Название checkbox поля
     * @param string $post_type Тип поста
     * @param array<string, mixed> $selected_keys Выбранные keys (опционально, для фильтрации)
     * @return array<string, mixed> Маппинг value => label (например: ['terapevt' => 'Терапевт'])
     */
    public function getJetengineGlossaryLabels($field_name, $post_type = 'doctors', $selected_keys = array()) {
        global $wpdb;
        
        if (!function_exists('jet_engine')) {
            return array();
        }
        
        // Проверить transient cache (5 минут TTL)
        $cache_key = 'yfgp_glossary_labels_' . $post_type . '_' . $field_name;
        $cached_labels = get_transient($cache_key);
        
        if ($cached_labels !== false) {
            // Если указаны selected_keys - фильтруем
            if (!empty($selected_keys)) {
                return array_intersect_key($cached_labels, array_flip($selected_keys));
            }
            return $cached_labels;
        }
        
        // Получить field definition
        $engine = jet_engine();
        if (!isset($engine->meta_boxes)) {
            return array();
        }
        
        $jet_meta_fields = $engine->meta_boxes->get_meta_fields_for_object($post_type);
        
        if (!$jet_meta_fields) {
            return array();
        }
        
        // Найти поле и его glossary_id
        $glossary_id = null;
        foreach ($jet_meta_fields as $field) {
            if ($field['name'] === $field_name && $field['type'] === 'checkbox') {
                $glossary_id = $field['glossary_id'] ?? null;
                break;
            }
        }
        
        if (!$glossary_id) {
            return array();
        }
        
        // Получить glossary из БД
        $table = $wpdb->prefix . 'jet_post_types';
        $glossary_row = $wpdb->get_row($wpdb->prepare(
            "SELECT meta_fields FROM {$table} WHERE id = %d AND status = 'glossary'",
            $glossary_id
        ));
        
        if (!$glossary_row) {
            return array();
        }
        
        // Распарсить meta_fields (PHP serialized array)
        $options = maybe_unserialize($glossary_row->meta_fields);
        
        if (!is_array($options)) {
            return array();
        }
        
        // Создать маппинг value => label
        $labels = array();
        foreach ($options as $option) {
            if (isset($option['value']) && isset($option['label'])) {
                $labels[$option['value']] = $option['label'];
            }
        }
        
        // Cache на 5 минут
        set_transient($cache_key, $labels, 5 * MINUTE_IN_SECONDS);
        
        // Фильтровать по selected_keys если указано
        if (!empty($selected_keys)) {
            return array_intersect_key($labels, array_flip($selected_keys));
        }
        
        return $labels;
    }
    
    public function getAcfFieldDefinition($field_name, $post_id = 0): array {
        if (!function_exists('acf_get_field_object')) {
            return array();
        }

        $cache_key = $field_name . ':' . (string) $post_id;

        if (!isset($this->acf_field_cache[$cache_key])) {
            $definition = acf_get_field_object($field_name, $post_id);

            if (!$definition && function_exists('acf_get_field')) {
                $definition = acf_get_field($field_name);
            }

            if (!$definition && $post_id !== 0) {
                $definition = acf_get_field_object($field_name);
            }

            if (!is_array($definition)) {
                $this->acf_field_cache[$cache_key] = array();
            } else {
                $choices = array();
                if (!empty($definition['choices']) && is_array($definition['choices'])) {
                    $choices = $definition['choices'];
                }

                $this->acf_field_cache[$cache_key] = array(
                    'choices' => $choices,
                    'return_format' => $definition['return_format'] ?? 'value',
                    'type' => $definition['type'] ?? null,
                    'allow_null' => !empty($definition['allow_null']),
                    'multiple' => !empty($definition['multiple']),
                );
            }
        }

        return $this->acf_field_cache[$cache_key];
    }

    /**
     * Convenience wrapper returning only ACF choices array.
     *
     * @param string $field_name
     * @param int $post_id
     * @return array<string, mixed>
     */
    public function getAcfFieldChoices($field_name, $post_id = 0): array {
        $definition = $this->getAcfFieldDefinition($field_name, $post_id);

        return $definition['choices'] ?? array();
    }

    /**
     * Return speciality reference map (slug => label).
     *
     * @return array<string, string>
     */
    public function getSpecialityReferenceMap(): array {
        if ($this->speciality_reference_map === null) {
            if (!function_exists('yfgp_get_specialities_reference_map')) {
                require_once YFGP_PLUGIN_DIR . 'includes/specialities-reference.php';
            }

            $this->speciality_reference_map = yfgp_get_specialities_reference_map();
        }

        return $this->speciality_reference_map;
    }

    /**
     * Try to resolve speciality label by slug using reference list.
     *
     * @param string $slug
     * @return string|null
     */
    public function getSpecialityLabelFromSlug($slug): ?string {
        $normalized = sanitize_title((string) $slug);
        if ($normalized === '') {
            return null;
        }

        $map = $this->getSpecialityReferenceMap();

        return $map[$normalized] ?? null;
    }

    /**
     * Lazy-load and cache the legacy V2 mapper for shared normalization routines.
     */
    private function getLegacyMapperV2(): ?YFGP_Field_Mapper_V2 {
        if ($this->legacy_mapper_v2 instanceof YFGP_Field_Mapper_V2) {
            return $this->legacy_mapper_v2;
        }

        if (!class_exists('YFGP_Field_Mapper_V2')) {
            $mapper_path = YFGP_PLUGIN_DIR . 'includes/class-field-mapper-v2.php';
            if (file_exists($mapper_path)) {
                require_once $mapper_path;
            }
        }

        if (class_exists('YFGP_Field_Mapper_V2')) {
            $this->legacy_mapper_v2 = new YFGP_Field_Mapper_V2();
            return $this->legacy_mapper_v2;
        }

        return null;
    }
 
    /**
     * Get JetEngine checkbox field options labels (value => label)
     * v4.10.9: UNIVERSAL solution - reads from field definition, not glossary!
     * 
     * @param string $field_name Field name (e.g. 'specialization')
     * @param string $post_type Post type (e.g. 'doctors')
     * @return array<string, mixed> Associative array [value => label] (e.g. ['terapevt' => 'Label'])
     */
    public function getJetengineFieldOptions($field_name, $post_type = 'doctors') {
        if (!function_exists('jet_engine')) {
            return array();
        }
        
        // Transient cache (5 minutes)
        $cache_key = 'yfgp_field_options_' . $post_type . '_' . $field_name;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $engine = jet_engine();
        if (!isset($engine->meta_boxes)) {
            return array();
        }
        
        $jet_meta_fields = $engine->meta_boxes->get_meta_fields_for_object($post_type);
        
        if (!$jet_meta_fields) {
            return array();
        }
        
        $supported_types = array('checkbox', 'select', 'select_advanced', 'select2', 'multi-select', 'multiselect', 'radio');
        foreach ($jet_meta_fields as $field) {
            // v4.18.2: Ищем поле по точному совпадению или по частичному (для полей с длинными именами)
            $field_matches = ($field['name'] === $field_name) || (strpos($field['name'] ?? '', $field_name) !== false);
            if ($field_matches && in_array($field['type'] ?? '', $supported_types, true)) {
                $labels = array();

                $options_sources = array('options', 'field_options', 'select_options');
                foreach ($options_sources as $source_key) {
                    if (!empty($field[$source_key]) && is_array($field[$source_key])) {
                        foreach ($field[$source_key] as $option) {
                            if (is_array($option) && isset($option['value']) && isset($option['label'])) {
                                $labels[$option['value']] = $option['label'];
                            } elseif (is_array($option) && isset($option['key']) && isset($option['value'])) {
                                $labels[$option['key']] = $option['value'];
                            } elseif (is_string($option)) {
                                $labels[$option] = $option;
                            }
                        }
                    }
                }

                if (empty($labels) && !empty($field['glossary_id']) && method_exists($this, 'getJetengineGlossaryLabels')) {
                    $labels = $this->getJetengineGlossaryLabels($field_name, $post_type);
                }

                if (!empty($labels)) {
                    set_transient($cache_key, $labels, 5 * MINUTE_IN_SECONDS);
                    return $labels;
                }
            }
        }

        if (method_exists($this, 'getJetengineGlossaryLabels')) {
            $labels = $this->getJetengineGlossaryLabels($field_name, $post_type);
            if (!empty($labels)) {
                set_transient($cache_key, $labels, 5 * MINUTE_IN_SECONDS);
                return $labels;
            }
        }
        
        return array();
    }

    /**
     * Applies conditional logic to a field value when configured.
     *
     * @param mixed $value Original value extracted from source.
     * @param array<string, mixed> $config Field configuration containing operator metadata.
     * @return mixed Filtered value after conditional evaluation.
     */
    private function applyConditionalLogic($post_id, $value, array $config)
    {
        $operator = $config['operator'];
        $operator_value = $config['operator_value'] ?? null;

        if (is_string($operator_value)) {
            $operator_value = trim($operator_value);
        }

        if (is_array($value)) {
            $converted = $this->convertBooleanChoiceArrayToScalar($post_id, $config, $value);

            if (is_array($converted)) {
                $labels = $this->flattenConditionalArrayValues($converted);

                if (empty($labels)) {
                    return null;
                }

                return $this->evaluateConditionalArrayOperator($labels, $operator, $operator_value);
            }

            $value = $converted;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        switch ($operator) {
            case '=':
                return ($value == $operator_value) ? $value : null;

            case '!=':
                return ($value != $operator_value) ? $value : null;

            case '>':
                return (is_numeric($value) && $value > $operator_value) ? $value : null;

            case '<':
                return (is_numeric($value) && $value < $operator_value) ? $value : null;

            case '>=':
                return (is_numeric($value) && $value >= $operator_value) ? $value : null;

            case '<=':
                return (is_numeric($value) && $value <= $operator_value) ? $value : null;

            case '?':
            case '∈':
            case 'in_list':
                $list = array_map('trim', explode(',', (string) $operator_value));
                return in_array($value, $list, false) ? $value : null;

            case '∉':
            case 'not_in_list':
                $list = array_map('trim', explode(',', (string) $operator_value));
                return in_array($value, $list, false) ? null : $value;

            case 'empty':
                return empty($value) ? $value : null;

            case 'not_empty':
                return empty($value) ? null : $value;

            case 'replace':
                if (!is_scalar($value)) {
                    return $value;
                }
                $replacement = (string) $operator_value;
                if (strpos($replacement, '|') === false) {
                    return str_replace($replacement, '', (string) $value);
                }
                list($old, $new) = explode('|', $replacement, 2);
                return str_replace($old, $new, (string) $value);

            case 'default':
                return empty($value) ? $operator_value : $value;

            default:
                return $value;
        }
    }

    private function convertBooleanChoiceArrayToScalar(int $post_id, array $config, array $value)
    {
        $truthy_keys = array();

        foreach ($value as $key => $raw) {
            if ($this->isTruthyFlag($raw)) {
                $truthy_keys[] = (string) $key;
            }
        }

        if (count($truthy_keys) !== 1) {
            return $value;
        }

        $selected_key = $truthy_keys[0];
        $label = $this->resolveOptionLabelForConditional($post_id, $config, $selected_key);

        if ($label !== null && $label !== '') {
            return $label;
        }

        $raw_truthy_value = $value[$selected_key];
        if (is_scalar($raw_truthy_value) && $this->isTruthyFlag($raw_truthy_value)) {
            return 'true';
        }

        return $selected_key;
    }

    private function isTruthyFlag($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((float) $value) !== 0.0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return in_array($normalized, array('1', 'true', 'yes', 'on'), true);
        }

        return false;
    }

    private function resolveOptionLabelForConditional(int $post_id, array $config, string $option_key): ?string
    {
        $field_name = isset($config['source_field']) ? (string) $config['source_field'] : '';

        if ($field_name === '') {
            return null;
        }

        $choices = $this->getAcfFieldChoices($field_name, $post_id);
        if (!empty($choices) && isset($choices[$option_key])) {
            return (string) $choices[$option_key];
        }

        $post_type = $this->getPostTypeForConditional($post_id, $config);
        if ($post_type !== '') {
            $options = $this->getJetengineFieldOptions($field_name, $post_type);
            if (!empty($options) && isset($options[$option_key])) {
                return (string) $options[$option_key];
            }
        }

        return null;
    }

    private function getPostTypeForConditional(int $post_id, array $config): string
    {
        if (!empty($config['source_cpt'])) {
            return (string) $config['source_cpt'];
        }

        $post = get_post($post_id);

        if ($post instanceof \WP_Post && !empty($post->post_type)) {
            return (string) $post->post_type;
        }

        return '';
    }

    private function flattenConditionalArrayValues(array $value): array
    {
        $labels = array();

        array_walk_recursive($value, function ($item) use (&$labels) {
            if (is_scalar($item)) {
                $label = trim((string) $item);
                if ($label !== '') {
                    $labels[] = $label;
                }
            }
        });

        return $labels;
    }

    private function evaluateConditionalArrayOperator(array $labels, $operator, $operator_value)
    {
        $first = reset($labels);

        switch ($operator) {
            case '=':
                return in_array($operator_value, $labels, false) ? $operator_value : null;

            case '!=':
                return in_array($operator_value, $labels, false) ? null : $first;

            case '?':
            case '∈':
            case 'in_list':
                $list = array_map('trim', explode(',', (string) $operator_value));
                foreach ($labels as $label) {
                    if (in_array($label, $list, false)) {
                        return $label;
                    }
                }
                return null;

            case '∉':
            case 'not_in_list':
                $list = array_map('trim', explode(',', (string) $operator_value));
                foreach ($labels as $label) {
                    if (in_array($label, $list, false)) {
                        return null;
                    }
                }
                return $first;

            case 'empty':
                return null;

            case 'not_empty':
                return $first;

            case 'default':
                return $first !== false ? $first : $operator_value;

            case 'replace':
                if ($first === false) {
                    return $operator_value;
                }
                $replacement = (string) $operator_value;
                if (strpos($replacement, '|') === false) {
                    return str_replace($replacement, '', $first);
                }
                list($old, $new) = explode('|', $replacement, 2);
                return str_replace($old, $new, $first);

            default:
                return $first;
        }
    }
}

/**
 * Глобальная функция для быстрого доступа
 * 
 * @since 4.0.0
 * @return YFGP_Field_Mapper_Unified
 */
function yfgp_field_mapper_unified() {
    return YFGP_Field_Mapper_Unified::get_instance();
}
