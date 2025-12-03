<?php
/**
 * ACF Compatibility Helper
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.20.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Version-aware wrapper for get_field()
 * Supports escape_html parameter added in ACF 6.2.6
 * 
 * @param string $selector Field name or key
 * @param int|false $post_id Post ID
 * @param bool $format_value Format value (default true)
 * @param bool $escape_html Escape HTML (ACF 6.2.6+, default false)
 * @return mixed Field value
 */
function yfgp_get_field($selector, $post_id = false, $format_value = true, $escape_html = false) {
    if (!function_exists('get_field')) {
        return null;
    }
    
    // ACF 6.2.6+ supports escape_html parameter
    if ($escape_html && defined('ACF_VERSION') && version_compare(ACF_VERSION, '6.2.6', '>=')) {
        return get_field($selector, $post_id, $format_value, $escape_html);
    }
    
    $value = get_field($selector, $post_id, $format_value);
    
    // Manual escaping for older versions
    if ($escape_html) {
        if (is_string($value)) {
            return esc_html($value);
        }
        if (is_array($value)) {
            return array_map(function($v) {
                return is_string($v) ? esc_html($v) : $v;
            }, $value);
        }
    }
    
    return $value;
}

/**
 * Get ACF field groups for post type
 * 
 * @param string $post_type Post type
 * @return array Field groups
 */
function yfgp_get_acf_field_groups(string $post_type): array {
    if (!function_exists('acf_get_field_groups')) {
        return array();
    }
    
    return acf_get_field_groups(array('post_type' => $post_type));
}

/**
 * Get ACF fields for group
 * Uses 'key' parameter for compatibility
 * 
 * @param array $group Field group
 * @return array Fields
 */
function yfgp_get_acf_fields(array $group): array {
    if (!function_exists('acf_get_fields')) {
        return array();
    }
    
    // Use key (recommended) or fallback to ID
    $group_identifier = isset($group['key']) ? $group['key'] : (isset($group['ID']) ? $group['ID'] : null);
    
    if (!$group_identifier) {
        return array();
    }
    
    return acf_get_fields($group_identifier) ?: array();
}

