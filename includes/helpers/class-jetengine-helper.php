<?php
/**
 * JetEngine API Helper
 * 
 * Provides version-compatible abstraction for JetEngine API
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.20.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class YFGP_JetEngine_Helper {
    
    /**
     * Check if JetEngine is available and has relations
     */
    public static function is_available(): bool {
        return function_exists('jet_engine') 
            && class_exists('Jet_Engine\Relations\Manager')
            && isset(jet_engine()->relations);
    }
    
    /**
     * Get JetEngine version
     */
    public static function get_version(): ?string {
        if (!defined('JET_ENGINE_VERSION')) {
            return null;
        }
        return JET_ENGINE_VERSION;
    }
    
    /**
     * Check if JetEngine version supports feature
     */
    public static function supports(string $feature): bool {
        $version = self::get_version();
        if (!$version) {
            return false;
        }
        
        $requirements = array(
            'relations_api_v2' => '3.0.0',
            'meta_fields_for_object' => '2.8.0',
            'relation_db_table' => '3.0.0',
        );
        
        if (!isset($requirements[$feature])) {
            return true;
        }
        
        return version_compare($version, $requirements[$feature], '>=');
    }
    
    /**
     * Get relation by ID
     * 
     * @param int|string $relation_id Relation ID (numeric)
     * @return object|null Relation object or null
     */
    public static function get_relation($relation_id) {
        if (!self::is_available()) {
            return null;
        }
        
        $numeric_id = is_numeric($relation_id) ? (int) $relation_id : null;
        
        // Extract numeric ID from string like "jet_rel_49"
        if (!$numeric_id && preg_match('/jet_rel_(\d+)/', (string) $relation_id, $matches)) {
            $numeric_id = (int) $matches[1];
        }
        
        if (!$numeric_id) {
            return null;
        }
        
        foreach (jet_engine()->relations->get_active_relations() as $relation) {
            if ($relation->get_id() == $numeric_id) {
                return $relation;
            }
        }
        
        return null;
    }
    
    /**
     * Get related items using API (not direct SQL)
     * 
     * @param int|string $relation_id Relation ID
     * @param int $post_id Source post ID
     * @param string $direction 'children' or 'parents'
     * @return array Array of related post IDs
     */
    public static function get_related_items($relation_id, int $post_id, string $direction = 'children'): array {
        $relation = self::get_relation($relation_id);
        if (!$relation) {
            return array();
        }
        
        if ($direction === 'parents') {
            $items = $relation->get_parents($post_id, 'ids');
        } else {
            $items = $relation->get_children($post_id, 'ids');
        }
        
        return is_array($items) ? $items : array();
    }
    
    /**
     * Batch get related items for multiple posts
     * 
     * @param int|string $relation_id Relation ID
     * @param array $post_ids Array of post IDs
     * @param string $direction 'children' or 'parents'
     * @return array Associative array [post_id => [related_ids]]
     */
    public static function batch_get_related_items($relation_id, array $post_ids, string $direction = 'children'): array {
        $relation = self::get_relation($relation_id);
        if (!$relation || empty($post_ids)) {
            return array();
        }
        
        $result = array();
        
        // If relation has db property, use batch query
        if (isset($relation->db) && method_exists($relation->db, 'query')) {
            $query_field = $direction === 'parents' ? 'child_object_id' : 'parent_object_id';
            $result_field = $direction === 'parents' ? 'parent_object_id' : 'child_object_id';
            
            $rows = $relation->db->query(array(
                $query_field => array(
                    'operator' => 'IN',
                    'value' => array_map('intval', $post_ids),
                ),
            ));
            
            foreach ($rows as $row) {
                $key = $row->{$query_field};
                if (!isset($result[$key])) {
                    $result[$key] = array();
                }
                $result[$key][] = (int) $row->{$result_field};
            }
        } else {
            // Fallback to individual queries
            foreach ($post_ids as $post_id) {
                $result[$post_id] = self::get_related_items($relation_id, $post_id, $direction);
            }
        }
        
        return $result;
    }
    
    /**
     * Get meta fields for post type
     * 
     * @param string $post_type Post type
     * @return array Meta fields
     */
    public static function get_meta_fields(string $post_type): array {
        if (!self::is_available() || !isset(jet_engine()->meta_boxes)) {
            return array();
        }
        
        if (method_exists(jet_engine()->meta_boxes, 'get_meta_fields_for_object')) {
            return jet_engine()->meta_boxes->get_meta_fields_for_object($post_type) ?: array();
        }
        
        // Fallback for older versions
        if (method_exists(jet_engine()->meta_boxes, 'get_registered_fields')) {
            $all_fields = jet_engine()->meta_boxes->get_registered_fields();
            // Filter by post type...
            return $all_fields;
        }
        
        return array();
    }
}

