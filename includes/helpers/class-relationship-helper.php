<?php
/**
 * Relationship Helper - Обработка связей между постами (ACF + JetEngine)
 * 
 * Этот класс отвечает за:
 * - Получение связанных постов (ACF Relationship + JetEngine Relations)
 * - Связи 1 уровня (doctor → service)
 * - Связи 2 уровня (doctor → service → price)
 * - Bidirectional support (parent→child и child→parent)
 * - Batch queries для оптимизации (избежание N+1 problem)
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Relationship Helper Class
 */
class YFGP_Relationship_Helper {
    
    /**
     * Кэш для повышения производительности
     */
    private $cache = array();
    
    /**
     * Batch кэш для оптимизации множественных запросов
     */
    private $batch_cache = array();
    
    /**
     * Static cache for relationship queries (v4.18.39: Optimized with static cache)
     * 
     * @var array<string, array<\WP_Post>>
     */
    private static $relationship_cache = array();
    
    /**
     * Получить связанные посты по конфигурации
     * 
     * @param int $post_id ID поста
     * @param array<string, mixed> $field_config Конфигурация поля
     * @return array|int|null Связанные посты или ID
     */
    public function get_relationship_data($post_id, $field_config) {
        // Валидация
        if (empty($field_config['source_type']) || empty($field_config['source_field'])) {
            return null;
        }
        
        // Проверить кэш
        $cache_key = $this->get_cache_key($post_id, $field_config);
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        $data = null;
        
        // Обработка по типу
        switch ($field_config['source_type']) {
            case 'relationship_1':
                $data = $this->get_relationship_level_1($post_id, $field_config);
                break;
            
            case 'relationship_2':
                $data = $this->get_relationship_level_2($post_id, $field_config);
                break;
            
            default:
                $data = null;
        }
        
        // Сохранить в кэш
        $this->cache[$cache_key] = $data;
        
        return $data;
    }
    
    /**
     * Получить связи 1 уровня (doctor → service)
     * 
     * @param int $post_id ID поста
     * @param array<string, mixed> $config Конфигурация поля
     * @return array<string, mixed> Массив связанных постов
     */
    private function get_relationship_level_1($post_id, $config) {
        $field_name = $config['source_field'];
        $target_cpt = isset($config['source_cpt']) ? $config['source_cpt'] : null;
        
        // v4.1.0-beta24: CRITICAL - Ensure field_name is STRING, not array
        if (is_array($field_name)) {
            error_log('YFGP CRITICAL: get_relationship_level_1 received ARRAY as source_field! Config: ' . json_encode($config));
            return array(); // Return empty to prevent ACF crash
        }
        
        if (empty($field_name) || !is_string($field_name)) {
            error_log('YFGP ERROR: Invalid field_name in get_relationship_level_1 for post ' . $post_id);
            return array();
        }
        
        $related_posts = array();
        
        // === ACF RELATIONSHIP ===
        if (function_exists('get_field')) {
            // v4.18.23 FIX: Используем get_field с format_value=false для получения сырых ID, затем извлекаем порядок из meta_value
            $acf_value = get_field($field_name, $post_id, false);
            error_log("YFGP DEBUG get_relationship_level_1: ACF get_field('{$field_name}', {$post_id}) returned: " . json_encode($acf_value));
            
            if (!empty($acf_value)) {
                // v4.18.23 FIX: Для сохранения порядка из UI, извлекаем порядок напрямую из meta_value
                // ACF relationship хранится как сериализованный массив в одной записи meta_value
                $meta_value_raw = get_post_meta($post_id, $field_name, true);
                $ordered_ids = array();
                
                if (!empty($meta_value_raw)) {
                    // Десериализуем meta_value (ACF хранит как сериализованный массив)
                    $unserialized = maybe_unserialize($meta_value_raw);
                    if (is_array($unserialized)) {
                        // Извлекаем порядок из массива (сохраняем порядок ключей)
                        foreach ($unserialized as $key => $val) {
                            if (is_numeric($val)) {
                                $ordered_ids[] = (int) $val;
                            } elseif (is_string($val) && is_numeric($val)) {
                                $ordered_ids[] = (int) $val;
                            }
                        }
                        error_log("YFGP v4.18.23: ACF order fix - extracted order from meta_value: " . json_encode($ordered_ids));
                    }
                }
                
                // ACF может вернуть массив ID или массив объектов
                if (is_array($acf_value)) {
                    // Если есть упорядоченный список из meta_value, используем его
                    if (!empty($ordered_ids)) {
                        foreach ($ordered_ids as $post_id_related) {
                            // Фильтр по CPT если указан
                            if ($target_cpt) {
                                $post_type = get_post_type($post_id_related);
                                if ($post_type !== $target_cpt) {
                                    continue;
                                }
                            }
                            $related_posts[] = $post_id_related;
                        }
                    } else {
                        // Fallback: используем порядок из get_field()
                        foreach ($acf_value as $item) {
                            $post_id_related = is_object($item) ? $item->ID : $item;
                            
                            // Фильтр по CPT если указан
                            if ($target_cpt) {
                                $post_type = get_post_type($post_id_related);
                                if ($post_type !== $target_cpt) {
                                    continue;
                                }
                            }
                            
                            $related_posts[] = $post_id_related;
                        }
                    }
                } else {
                    // Одиночное значение
                    $post_id_related = is_object($acf_value) ? $acf_value->ID : $acf_value;
                    
                    if ($target_cpt) {
                        $post_type = get_post_type($post_id_related);
                        if ($post_type === $target_cpt) {
                            $related_posts[] = $post_id_related;
                        }
                    } else {
                        $related_posts[] = $post_id_related;
                    }
                }
            }
        }
        
        // === JETENGINE RELATIONS ===
        // Используем JetEngine только если ACF не вернуло результат (fallback)
        if (empty($related_posts) && function_exists('jet_engine') && class_exists('Jet_Engine\\Relations\\Manager')) {
            error_log("YFGP DEBUG get_relationship_level_1: ACF returned empty, trying JetEngine for field '{$field_name}'");
            $related_posts = $this->get_jetengine_relations($post_id, $field_name, $target_cpt);
            error_log("YFGP DEBUG get_relationship_level_1: JetEngine returned " . count($related_posts) . " items");
        }
        
        error_log("YFGP DEBUG get_relationship_level_1: FINAL result for field '{$field_name}' post {$post_id}: " . count($related_posts) . " items");
        return $related_posts;
    }
    
    /**
     * Получить связи 2 уровня (doctor → service → price)
     * 
     * @param int $post_id ID поста
     * @param array<string, mixed> $config Конфигурация поля
     * @return mixed Значение вложенного поля
     */
    private function get_relationship_level_2($post_id, $config) {
        $first_relation = $config['source_field'];
        $intermediate_cpt = isset($config['source_cpt']) ? $config['source_cpt'] : null;

        $nested_field = isset($config['nested_field']) ? $config['nested_field'] : null;
        $second_relation = isset($config['second_relationship']) ? $config['second_relationship'] : null;
        $second_cpt = isset($config['second_cpt']) ? $config['second_cpt'] : null;

        if (empty($nested_field)) {
            return null;
        }

        $intermediate_posts = $this->get_relationship_level_1($post_id, array(
            'source_type' => 'relationship_1',
            'source_field' => $first_relation,
            'source_cpt' => $intermediate_cpt
        ));

        if (empty($intermediate_posts)) {
            return null;
        }

        $intermediate_ids = array();
        foreach ((array) $intermediate_posts as $item) {
            if (is_object($item) && isset($item->ID)) {
                $intermediate_ids[] = (int) $item->ID;
            } elseif (is_numeric($item)) {
                $intermediate_ids[] = (int) $item;
            }
        }

        if (empty($intermediate_ids)) {
            return null;
        }

        if (!empty($second_relation)) {
            foreach ($intermediate_ids as $intermediate_id) {
                $second_level = $this->get_relationship_level_1($intermediate_id, array(
                    'source_type' => 'relationship_1',
                    'source_field' => $second_relation,
                    'source_cpt' => $second_cpt,
                ));

                if (empty($second_level) && preg_match('/jet_rel_(\d+)(_reverse)?/', (string) $second_relation, $matches)) {
                    global $wpdb;

                    $relation_table = $wpdb->prefix . 'jet_rel_' . $matches[1];
                    $is_reverse_direction = !empty($matches[2]);

                    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $relation_table))) {
                        // v4.18.23 FIX: Сохраняем порядок из БД (ORDER BY _ID) для соответствия порядку в UI
                        if ($is_reverse_direction) {
                            $db_children = $wpdb->get_col($wpdb->prepare("SELECT parent_object_id FROM {$relation_table} WHERE child_object_id = %d ORDER BY _ID ASC", $intermediate_id));
                        } else {
                            $db_children = $wpdb->get_col($wpdb->prepare("SELECT child_object_id FROM {$relation_table} WHERE parent_object_id = %d ORDER BY _ID ASC", $intermediate_id));
                        }

                        if (!empty($db_children)) {
                            $second_level = array_map('intval', $db_children);
                        }
                    }
                }

                if (empty($second_level)) {
                    continue;
                }

                foreach ((array) $second_level as $candidate) {
                    $candidate_id = null;

                    if (is_object($candidate) && isset($candidate->ID)) {
                        $candidate_id = (int) $candidate->ID;
                    } elseif (is_numeric($candidate)) {
                        $candidate_id = (int) $candidate;
                    }

                    if (!$candidate_id) {
                        continue;
                    }

                    $value = get_post_meta($candidate_id, $nested_field, true);
                    if ($value !== null && $value !== '') {
                        return $value;
                    }

                    $post_obj = get_post($candidate_id);
                    if ($post_obj instanceof \WP_Post && isset($post_obj->{$nested_field}) && $post_obj->{$nested_field} !== '') {
                        return $post_obj->{$nested_field};
                    }
                }
            }

            return null;
        }

        $first_post_id = $intermediate_ids[0];

        if (class_exists('YFGP_Field_Mapper_Unified')) {
            $mapper = YFGP_Field_Mapper_Unified::get_instance();
            $value = $mapper->getFieldValue((int) $first_post_id, array(
                'source_type' => 'meta_field',
                'source_field' => $nested_field,
            ));

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        if (function_exists('get_field')) {
            $value = get_field($nested_field, $first_post_id);
            if ($value !== false && $value !== null) {
                return $value;
            }
        }

        return get_post_meta($first_post_id, $nested_field, true);
    }
    /**
     * Получить JetEngine Relations
     * 
     * @param int $post_id ID поста
     * @param string $relation_name Название связи
     * @param string|null $target_cpt Целевой CPT
     * @return array<string, mixed> Массив связанных постов
     */
    private function get_jetengine_relations($post_id, $relation_name, $target_cpt = null): array {
        $related_posts = array();
        
        if (!function_exists('jet_engine') || !class_exists('Jet_Engine\\Relations\\Manager')) {
            return $related_posts;
        }
        
        // v4.1.0-beta28: Extract numeric ID from string like "jet_rel_49" or "jet_rel_11_reverse"
        // JetEngine stores relation ID as integer, but field mapping uses "jet_rel_X" format
        $numeric_id = null;
        if (preg_match('/jet_rel_(\d+)/', $relation_name, $matches)) {
            $numeric_id = (int)$matches[1];
        }
        
        // v4.18.19: Check if this is a reverse relationship
        $is_reverse_direction = strpos($relation_name, '_reverse') !== false;
        
        error_log("YFGP DEBUG get_jetengine_relations: Looking for relation '{$relation_name}', extracted numeric ID: " . ($numeric_id ?: 'NONE') . ", post {$post_id}, is_reverse: " . ($is_reverse_direction ? 'YES' : 'NO'));
        
        // v4.18.19: For reverse relationships, use DB fallback immediately (JetEngine doesn't have reverse relations)
        if ($is_reverse_direction && $numeric_id) {
            global $wpdb;
            $relation_table = $wpdb->prefix . 'jet_rel_' . $numeric_id;
            
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $relation_table))) {
                error_log("YFGP DEBUG get_jetengine_relations: Reverse DB lookup {$relation_table} child={$post_id}");
                // v4.18.23 FIX: Сохраняем порядок из БД (ORDER BY _ID) для соответствия порядку в UI
                $related_items = $wpdb->get_col($wpdb->prepare("SELECT parent_object_id FROM {$relation_table} WHERE child_object_id = %d ORDER BY _ID ASC", $post_id));
                
                if (!empty($related_items)) {
                    error_log("YFGP DEBUG get_jetengine_relations: Reverse relationship DB returned " . count($related_items) . " items from {$relation_table} (ordered by _ID)");
                    foreach ($related_items as $item_id) {
                        if (is_object($item_id) && isset($item_id->ID)) {
                            $item_id = (int) $item_id->ID;
                        } else {
                            $item_id = (int) $item_id;
                        }
                        if ($item_id) {
                            $related_posts[] = $item_id;
                        }
                    }
                    return $related_posts;
                }
            }
        }
        
        $relations_manager = jet_engine()->relations;
        $jet_relations = $relations_manager->get_active_relations();
        
        error_log("YFGP DEBUG get_jetengine_relations: Found " . count($jet_relations) . " total JetEngine relations");
        
        if (empty($jet_relations)) {
            error_log("YFGP DEBUG get_jetengine_relations: NO relations found in JetEngine!");
            return $related_posts;
        }
        
        foreach ($jet_relations as $relation) {
            $relation_id = $relation->get_id();
            
            error_log("YFGP DEBUG get_jetengine_relations: Checking relation ID '{$relation_id}' (type: " . gettype($relation_id) . ") vs numeric ID '{$numeric_id}'");
            
            // v4.1.0-beta28: Compare numeric IDs (JetEngine uses integers)
            $is_match = ($numeric_id && $relation_id == $numeric_id);
            
            if (!$is_match) {
                error_log("YFGP DEBUG get_jetengine_relations: SKIP - relation ID '{$relation_id}' does NOT match numeric ID '{$numeric_id}'");
                continue;
            }
            
            error_log("YFGP DEBUG get_jetengine_relations: ✅ MATCH! Found relation '{$relation_id}' for post {$post_id}");
            
            // Получить связанные элементы (попробовать оба направления)
            $related_items = $relation->get_children($post_id, 'posts');
            error_log("YFGP DEBUG get_jetengine_relations: get_children() returned " . count($related_items) . " items");
            
            if (empty($related_items)) {
                // Попробовать обратное направление
                $related_items = $relation->get_parents($post_id, 'posts');
                error_log("YFGP DEBUG get_jetengine_relations: get_parents() returned " . count($related_items) . " items");
            }

            // v4.18.25 FIX: Используем JetEngine API для получения данных - API сам определяет правильную таблицу (отдельную или default)
            // API автоматически фильтрует по rel_id, поэтому не нужно делать прямые запросы к БД
            // Если API вернул данные, используем их (API сам знает, какую таблицу использовать)
            
            if (!empty($related_items)) {
                foreach ($related_items as $item_id) {
                    if (is_object($item_id) && isset($item_id->ID)) {
                        $item_id = (int) $item_id->ID;
                    } else {
                        $item_id = (int) $item_id;
                    }
                    if (!$item_id) {
                        continue;
                    }
                    // Фильтр по CPT если указан
                    if ($target_cpt) {
                        $post_type = get_post_type($item_id);
                        if ($post_type !== $target_cpt) {
                            continue;
                        }
                    }
                    
                    $related_posts[] = $item_id;
                }
            }
        }
        
        return $related_posts;
    }
    
    /**
     * Batch получение связей для массива постов (оптимизация N+1)
     * 
     * @param array<string, mixed> $post_ids Массив ID постов
     * @param array<string, mixed> $field_config Конфигурация поля
     * @return array<string, mixed> Массив связей [post_id => related_posts]
     */
    public function get_relationships_batch($post_ids, $field_config): array {
        if (empty($post_ids) || !is_array($post_ids)) {
            return array();
        }
        
        $batch_key = $this->get_batch_cache_key($post_ids, $field_config);
        
        // Проверить batch кэш
        if (isset($this->batch_cache[$batch_key])) {
            return $this->batch_cache[$batch_key];
        }
        
        $results = array();
        
        // Получить связи для каждого поста
        foreach ($post_ids as $post_id) {
            $results[$post_id] = $this->get_relationship_data($post_id, $field_config);
        }
        
        // Сохранить в batch кэш
        $this->batch_cache[$batch_key] = $results;
        
        return $results;
    }
    
    /**
     * Получить все связанные посты с полными данными (v4.18.39: Optimized with caching and batch loading)
     * 
     * @param int $post_id ID поста
     * @param array<string, mixed> $config Конфигурация поля
     * @return array<string, mixed> Массив объектов WP_Post
     */
    public function get_related_posts_full($post_id, $config): array {
        // Create cache key
        $cache_key = $this->get_relationship_cache_key($post_id, $config);
        
        // Check static cache
        if (isset(self::$relationship_cache[$cache_key])) {
            return self::$relationship_cache[$cache_key];
        }
        
        $related_ids = $this->get_relationship_data($post_id, $config);
        
        if (empty($related_ids)) {
            self::$relationship_cache[$cache_key] = array();
            return array();
        }
        
        // Преобразовать в массив если одиночное значение
        if (!is_array($related_ids)) {
            $related_ids = array($related_ids);
        }
        
        // Получить полные объекты постов (batch запрос)
        // Load Post_Batch_Loader if not already loaded
        if (!class_exists('YFGP_Post_Batch_Loader')) {
            require_once YFGP_PLUGIN_DIR . 'includes/class-post-batch-loader.php';
        }
        
        $posts = array();
        if (class_exists('YFGP_Post_Batch_Loader') && !empty($related_ids)) {
            // Use batch loader for safe memory usage
            $loader = new YFGP_Post_Batch_Loader('any', 1000);
            $posts = $loader->get_posts_by_ids(
                $related_ids,
                'any',
                'any'
            );
        } else {
            // Fallback: use limited query
            $posts = get_posts(array(
                'post__in' => array_slice($related_ids, 0, 10000), // Limit to 10k
                'post_type' => 'any',
                'posts_per_page' => 10000,
                'orderby' => 'post__in'
            ));
        }
        
        // Cache result
        self::$relationship_cache[$cache_key] = $posts;
        
        return $posts;
    }
    
    /**
     * Get cache key for relationship query (v4.18.39)
     * 
     * @param int $post_id Post ID
     * @param array<string, mixed> $config Relationship configuration
     * @return string Cache key
     */
    private function get_relationship_cache_key(int $post_id, array $config): string {
        $source_type = $config['source_type'] ?? '';
        $source_field = $config['source_field'] ?? '';
        return md5("{$post_id}_{$source_type}_{$source_field}");
    }
    
    /**
     * Проверить существует ли связь между двумя постами
     * 
     * @param int $post_id_1 ID первого поста
     * @param int $post_id_2 ID второго поста
     * @param string $relation_name Название связи
     * @return bool Существует ли связь
     */
    public function has_relationship($post_id_1, $post_id_2, $relation_name): bool {
        $config = array(
            'source_type' => 'relationship_1',
            'source_field' => $relation_name
        );
        
        $related_posts = $this->get_relationship_data($post_id_1, $config);
        
        if (empty($related_posts)) {
            return false;
        }
        
        // Преобразовать в массив если одиночное значение
        if (!is_array($related_posts)) {
            $related_posts = array($related_posts);
        }
        
        return in_array($post_id_2, $related_posts);
    }
    
    /**
     * Генерировать кэш ключ
     * 
     * @param int $post_id ID поста
     * @param array<string, mixed> $field_config Конфигурация поля
     * @return string Кэш ключ
     */
    private function get_cache_key($post_id, $field_config): string {
        return 'relationship_' . $post_id . '_' . md5(json_encode($field_config));
    }
    
    /**
     * Генерировать batch кэш ключ
     * 
     * @param array<string, mixed> $post_ids Массив ID постов
     * @param array<string, mixed> $field_config Конфигурация поля
     * @return string Batch кэш ключ
     */
    private function get_batch_cache_key($post_ids, $field_config): string {
        return 'batch_rel_' . md5(implode(',', $post_ids) . json_encode($field_config));
    }
    
    /**
     * Очистить кэш (v4.18.39: Updated to clear both static and instance cache)
     */
    public function clear_cache(): void {
        $this->cache = array();
        $this->batch_cache = array();
        self::$relationship_cache = array();
    }
    
    /**
     * Clear relationship cache (static method for external use) (v4.18.39)
     * 
     * @return void
     */
    public static function clear_relationship_cache(): void {
        self::$relationship_cache = array();
    }
}
