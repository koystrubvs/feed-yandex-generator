<?php
/**
 * Data Sanitizer Class
 * 
 * Sanitizes data recursively before output to XML/UI.
 * Prevents XSS and data leaks.
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.18.22
 */

if (!defined('ABSPATH')) {
    exit;
}

class YFGP_Data_Sanitizer {
    
    /**
     * Sanitize value based on type
     * 
     * @param mixed $value Value to sanitize
     * @param string $field_type Field type (text, textarea, url, html, int, float, email, attribute, js)
     * @return mixed Sanitized value
     */
    private function sanitize_value($value, $field_type = 'text') {
        switch ($field_type) {
            case 'text':
                return sanitize_text_field($value);
            case 'textarea':
                return sanitize_textarea_field($value);
            case 'url':
                return esc_url_raw($value);
            case 'html':
                return wp_kses_post($value);
            case 'int':
                return intval($value);
            case 'float':
                return floatval($value);
            case 'email':
                return sanitize_email($value);
            case 'attribute':
                return esc_attr($value);
            case 'js':
                return esc_js($value);
            default:
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Sanitize data recursively
     * 
     * @param mixed $data Data to sanitize
     * @param array<string, string> $field_types Field types mapping
     * @return mixed Sanitized data
     */
    public function sanitize($data, $field_types = array()) {
        if (is_array($data)) {
            $sanitized = array();
            foreach ($data as $key => $value) {
                $sanitized_key = sanitize_key($key);
                $field_type = $field_types[$key] ?? 'text';
                $sanitized[$sanitized_key] = $this->sanitize($value, $field_types);
            }
            return $sanitized;
        } elseif (is_object($data)) {
            $sanitized = new stdClass();
            foreach (get_object_vars($data) as $key => $value) {
                $field_type = $field_types[$key] ?? 'text';
                $sanitized->$key = $this->sanitize($value, $field_types);
            }
            return $sanitized;
        } elseif (is_string($data) || is_numeric($data)) {
            $field_type = $field_types[0] ?? 'text';
            return $this->sanitize_value($data, $field_type);
        }
        
        return $data;
    }
    
    /**
     * Sanitize for XML output
     * 
     * @param mixed $value Value to sanitize
     * @return mixed Sanitized value
     */
    public function sanitize_for_xml($value) {
        if (is_array($value)) {
            return array_map(array($this, 'sanitize_for_xml'), $value);
        }
        
        // Normalize encoding first
        if (function_exists('yfgp_normalize_utf8')) {
            $normalized = yfgp_normalize_utf8((string)$value);
        } else {
            $normalized = (string)$value;
        }
        
        // Then sanitize
        return sanitize_text_field($normalized);
    }
    
    /**
     * Sanitize for UI output
     * 
     * @param mixed $value Value to sanitize
     * @param string $context Context (html, url, attribute, js, text)
     * @return mixed Sanitized value
     */
    public function sanitize_for_ui($value, $context = 'text') {
        if (is_array($value)) {
            return array_map(function($item) use ($context) {
                return $this->sanitize_for_ui($item, $context);
            }, $value);
        }
        
        switch ($context) {
            case 'html':
                return wp_kses_post($value);
            case 'url':
                return esc_url($value);
            case 'attribute':
                return esc_attr($value);
            case 'js':
                return esc_js($value);
            default:
                return esc_html($value);
        }
    }
}

