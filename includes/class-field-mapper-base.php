<?php
/**
 * Base Field Mapper
 * 
 * Common logic for V2 and V3 facades.
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.18.39
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class YFGP_Field_Mapper_Base {
    
    /**
     * @var YFGP_Field_Mapper_Unified|null Unified mapper instance
     */
    protected $unified_mapper = null;
    
    /**
     * Get unified mapper instance
     * 
     * @return YFGP_Field_Mapper_Unified
     */
    protected function get_unified_mapper(): YFGP_Field_Mapper_Unified {
        if ($this->unified_mapper === null) {
            if (!class_exists('YFGP_Field_Mapper_Unified')) {
                require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
            }
            $this->unified_mapper = YFGP_Field_Mapper_Unified::get_instance();
        }
        return $this->unified_mapper;
    }
    
    /**
     * Clear cache
     * 
     * @return void
     */
    public function clear_cache(): void {
        if ($this->unified_mapper !== null) {
            // Clear unified mapper cache if method exists
            if (method_exists($this->unified_mapper, 'clear_cache')) {
                $this->unified_mapper->clear_cache();
            }
        }
    }
}


