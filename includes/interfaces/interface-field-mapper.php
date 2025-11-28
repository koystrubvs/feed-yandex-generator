<?php
/**
 * Field Mapper Interface
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.18.39
 */

if (!defined('ABSPATH')) {
    exit;
}

interface YFGP_IFieldMapper {
    
    /**
     * Get field value
     * 
     * @param int $post_id Post ID
     * @param string $field_key Field key
     * @param array<string, mixed> $mapping Field mapping configuration
     * @return mixed Field value
     */
    public function get_field_value(int $post_id, string $field_key, array $mapping);
}


