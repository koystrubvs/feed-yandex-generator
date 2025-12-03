<?php
/**
 * Cache Warmer
 * 
 * Preloads caches before feed generation to improve first-run performance
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.20.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class YFGP_Cache_Warmer {
    
    /**
     * Warmup caches for post types
     * 
     * @param array $post_types Array of post types
     * @return void
     */
    public function warmup(array $post_types): void {
        foreach ($post_types as $post_type) {
            $this->warmup_acf_fields($post_type);
            $this->warmup_jetengine_fields($post_type);
            $this->warmup_glossaries($post_type);
        }
    }
    
    /**
     * Warmup ACF fields cache
     * 
     * @param string $post_type Post type
     * @return void
     */
    private function warmup_acf_fields(string $post_type): void {
        if (!function_exists('acf_get_field_groups')) {
            return;
        }
        
        $groups = yfgp_get_acf_field_groups($post_type);
        foreach ($groups as $group) {
            yfgp_get_acf_fields($group);
        }
    }
    
    /**
     * Warmup JetEngine fields cache
     * 
     * @param string $post_type Post type
     * @return void
     */
    private function warmup_jetengine_fields(string $post_type): void {
        if (!YFGP_JetEngine_Helper::is_available()) {
            return;
        }
        
        YFGP_JetEngine_Helper::get_meta_fields($post_type);
    }
    
    /**
     * Warmup glossary labels cache
     * 
     * @param string $post_type Post type
     * @return void
     */
    private function warmup_glossaries(string $post_type): void {
        // Trigger glossary label caching through getAvailableFields
        // This will populate transient cache for JetEngine glossary fields
        if (class_exists('YFGP_Field_Mapper_Unified')) {
            $mapper = YFGP_Field_Mapper_Unified::get_instance();
            $mapper->getAvailableFields($post_type);
        }
    }
}

