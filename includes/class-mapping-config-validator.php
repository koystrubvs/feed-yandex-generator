<?php
/**
 * Mapping Configuration Validator
 * 
 * Validates mapping configuration data against predefined schema.
 * Prevents SQL injection and XSS through source_type, source_field, source_cpt validation.
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.18.22
 */

if (!defined('ABSPATH')) {
    exit;
}

class YFGP_Mapping_Config_Validator {
    
    private $allowed_source_types = array(
        'meta_field',
        'taxonomy',
        'acf_relationship',
        'acf_post_object',
        'repeater_acf',
        'repeater_jetengine',
        'relationship_1',
        'relationship_2',
        'post_field',
        'acf_field',
        'jetengine_field',
        'relationship',
        'repeater',
        'fixed',
        'boolean',
    );
    
    private $settings = null;
    
    public function __construct($settings = null) {
        if ($settings === null) {
            $this->settings = get_option('yfgp_settings', array());
        } else {
            $this->settings = $settings;
        }
    }
    
    /**
     * Get allowed source CPTs from settings
     * 
     * @return array<string> Allowed post types
     */
    public function get_allowed_source_cpts() {
        $allowed = array();
        
        if (!empty($this->settings['post_type'])) {
            $allowed[] = sanitize_key($this->settings['post_type']);
        }
        if (!empty($this->settings['cpt_clinics'])) {
            $allowed[] = sanitize_key($this->settings['cpt_clinics']);
        }
        if (!empty($this->settings['cpt_services'])) {
            $allowed[] = sanitize_key($this->settings['cpt_services']);
        }
        
        // Fallback to defaults
        if (empty($allowed)) {
            $allowed = array('doctors', 'clinics', 'services');
        }
        
        return $allowed;
    }
    
    /**
     * Validate mapping configuration
     * 
     * @param array<string, mixed> $mapping_data Mapping configuration data
     * @return bool True if valid
     * @throws InvalidArgumentException If validation fails
     */
    public function validate($mapping_data) {
        if (!is_array($mapping_data)) {
            throw new InvalidArgumentException('Mapping data must be an array');
        }
        
        // Validate source_type
        if (isset($mapping_data['source_type'])) {
            if (!is_string($mapping_data['source_type'])) {
                throw new InvalidArgumentException('source_type must be a string');
            }
            if (!in_array($mapping_data['source_type'], $this->allowed_source_types, true)) {
                throw new InvalidArgumentException('Invalid source_type: ' . esc_html($mapping_data['source_type']));
            }
        }
        
        // Validate source_field
        if (isset($mapping_data['source_field'])) {
            if (!is_string($mapping_data['source_field'])) {
                throw new InvalidArgumentException('source_field must be a string');
            }
            // Additional validation: max length, allowed characters
            if (strlen($mapping_data['source_field']) > 255) {
                throw new InvalidArgumentException('source_field exceeds maximum length (255)');
            }
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $mapping_data['source_field'])) {
                throw new InvalidArgumentException('source_field contains invalid characters');
            }
        }
        
        // Validate source_cpt
        if (isset($mapping_data['source_cpt'])) {
            if (!is_string($mapping_data['source_cpt'])) {
                throw new InvalidArgumentException('source_cpt must be a string');
            }
            $allowed_cpts = $this->get_allowed_source_cpts();
            if (!in_array($mapping_data['source_cpt'], $allowed_cpts, true)) {
                throw new InvalidArgumentException('Invalid source_cpt: ' . esc_html($mapping_data['source_cpt']));
            }
            // Additional check: verify post type exists
            if (!post_type_exists($mapping_data['source_cpt'])) {
                throw new InvalidArgumentException('source_cpt does not exist: ' . esc_html($mapping_data['source_cpt']));
            }
        }
        
        return true;
    }
    
    /**
     * Validate entire mapping array recursively
     * 
     * @param array<string, mixed> $mapping Mapping array
     * @return bool True if all fields are valid
     */
    public function validate_mapping_array($mapping) {
        if (!is_array($mapping)) {
            return false;
        }
        
        foreach ($mapping as $field_key => $field_config) {
            if (is_array($field_config)) {
                try {
                    $this->validate($field_config);
                } catch (InvalidArgumentException $e) {
                    if (class_exists('YFGP_Logger')) {
                        YFGP_Logger::get_instance()->error('YFGP Validation Error for field ' . $field_key . ': ' . $e->getMessage());
                    } else {
                        error_log('YFGP Validation Error for field ' . $field_key . ': ' . $e->getMessage());
                    }
                    return false;
                }
            }
        }
        
        return true;
    }
}

