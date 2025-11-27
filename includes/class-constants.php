<?php
/**
 * Plugin Constants Class
 * 
 * Defines all constants used throughout the plugin.
 * Replaces magic numbers and strings.
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.18.22
 */

if (!defined('ABSPATH')) {
    exit;
}

class YFGP_Constants {
    
    // Post Types
    const POST_TYPE_DOCTORS = 'doctors';
    const POST_TYPE_CLINICS = 'clinics';
    const POST_TYPE_SERVICES = 'services';
    
    // Default Values
    const DEFAULT_POST_TYPE = 'doctors';
    const DEFAULT_CURRENCY = 'RUR';
    const DEFAULT_BATCH_SIZE = 1000;
    
    // Limits
    const MAX_FEED_SIZE = 52428800; // 50MB in bytes
    const MAX_EXECUTION_TIME = 300; // 5 minutes in seconds
    const MAX_RECORDS = 10000;
    const MAX_MAPPING_SIZE = 1048576; // 1MB in bytes
    
    // Pagination
    const POSTS_PER_PAGE_UNLIMITED = -1;
    const POSTS_PER_PAGE_BATCH = 1000;
    
    // Log Levels
    const LOG_LEVEL_ERROR = 1;
    const LOG_LEVEL_WARNING = 2;
    const LOG_LEVEL_INFO = 3;
    
    // File Operations
    const FILE_PERMISSIONS = 0644;
    const FEED_DIRECTORY = 'feed';
    const FEED_EXTENSION = '.yml';
    
    // Option Names
    const OPTION_SETTINGS = 'yfgp_settings';
    const OPTION_MAPPING = 'yfgp_field_mapping_v3';
    const OPTION_MAPPING_LEGACY = 'yfgp_field_mapping';
    
    // AJAX Actions
    const AJAX_GENERATE_FEED = 'yfgp_generate_feed';
    const AJAX_SAVE_MAPPING = 'yfgp_save_mapping';
    const AJAX_TEST_MAPPING = 'yfgp_test_mapping';
    const AJAX_GET_FIELDS = 'yfgp_get_fields_v3';
    const AJAX_GET_AVAILABLE_CPTS = 'yfgp_get_available_cpts';
    const AJAX_GET_CPTS_V3 = 'yfgp_get_cpts_v3';
    const AJAX_GET_REPEATER_SUBFIELDS = 'yfgp_get_repeater_subfields';
    
    // Nonce Names
    const NONCE_AJAX = 'yfgp_ajax_nonce';
    const NONCE_SETTINGS = 'yfgp_settings_nonce';
    const NONCE_MAPPING_V3 = 'yfgp_mapping_v3_nonce';
    const NONCE_GENERATE = 'yfgp_generate_nonce';
    
    // Capabilities
    const CAPABILITY_REQUIRED = 'manage_options';
}

