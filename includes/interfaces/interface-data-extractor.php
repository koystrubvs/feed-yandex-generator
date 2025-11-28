<?php
/**
 * Data Extractor Interface
 * 
 * Interface for extracting data from posts and fields.
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.18.39
 */

if (!defined('ABSPATH')) {
    exit;
}

interface YFGP_IDataExtractor {
    
    /**
     * Extract data from post
     * 
     * @param int $post_id Post ID
     * @param array<string, mixed> $mapping Field mapping configuration
     * @return array<string, mixed> Extracted data
     */
    public function extract_post_data(int $post_id, array $mapping): array;
}


