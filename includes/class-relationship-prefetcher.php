<?php
/**
 * Relationship Prefetcher
 * 
 * Batch loads relationships to solve N+1 query problem
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.20.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class YFGP_Relationship_Prefetcher {
    
    /**
     * Cache for prefetched relationships
     * Format: [post_id_relation_id => [related_ids]]
     */
    private $cache = array();
    
    /**
     * Prefetched relation IDs
     */
    private $prefetched_relations = array();
    
    /**
     * Prefetch all relationships for a batch of posts
     * 
     * @param array $post_ids Array of post IDs
     * @param array $relation_ids Array of relation IDs (numeric or 'jet_rel_X' format)
     * @param string $direction 'children' or 'parents'
     * @return void
     */
    public function prefetch(array $post_ids, array $relation_ids, string $direction = 'children'): void {
        if (empty($post_ids) || empty($relation_ids)) {
            return;
        }
        
        // Use JetEngine Helper for batch loading
        foreach ($relation_ids as $relation_id) {
            $numeric_id = $this->extract_numeric_id($relation_id);
            if (!$numeric_id) {
                continue;
            }
            
            // Use batch_get_related_items from JetEngine Helper
            $batch_results = YFGP_JetEngine_Helper::batch_get_related_items($numeric_id, $post_ids, $direction);
            
            // Cache results
            foreach ($batch_results as $post_id => $related_ids) {
                $cache_key = $this->get_cache_key($post_id, $numeric_id);
                $this->cache[$cache_key] = $related_ids;
            }
            
            $this->prefetched_relations[$numeric_id] = true;
        }
    }
    
    /**
     * Get cached relationship
     * 
     * @param int $post_id Post ID
     * @param int|string $relation_id Relation ID
     * @param string $direction 'children' or 'parents'
     * @return array|null Array of related post IDs or null if not cached
     */
    public function get(int $post_id, $relation_id, string $direction = 'children'): ?array {
        $numeric_id = $this->extract_numeric_id($relation_id);
        if (!$numeric_id) {
            return null;
        }
        
        $cache_key = $this->get_cache_key($post_id, $numeric_id);
        return $this->cache[$cache_key] ?? null;
    }
    
    /**
     * Check if relationship is prefetched
     * 
     * @param int|string $relation_id Relation ID
     * @return bool
     */
    public function is_prefetched($relation_id): bool {
        $numeric_id = $this->extract_numeric_id($relation_id);
        return isset($this->prefetched_relations[$numeric_id]);
    }
    
    /**
     * Clear cache
     */
    public function clear(): void {
        $this->cache = array();
        $this->prefetched_relations = array();
    }
    
    /**
     * Extract numeric ID from relation identifier
     * 
     * @param int|string $relation_id Relation ID
     * @return int|null Numeric ID or null
     */
    private function extract_numeric_id($relation_id): ?int {
        if (is_numeric($relation_id)) {
            return (int) $relation_id;
        }
        
        if (preg_match('/jet_rel_(\d+)/', (string) $relation_id, $matches)) {
            return (int) $matches[1];
        }
        
        return null;
    }
    
    /**
     * Get cache key for post_id and relation_id
     * 
     * @param int $post_id Post ID
     * @param int $relation_id Relation ID
     * @return string Cache key
     */
    private function get_cache_key(int $post_id, int $relation_id): string {
        return $post_id . '_' . $relation_id;
    }
}

