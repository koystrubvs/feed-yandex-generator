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
        if (!in(array(