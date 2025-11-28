<?php
/**
 * Entity Builder Interface
 * 
 * Interface for building entity data from post objects.
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.18.39
 */

if (!defined('ABSPATH')) {
    exit;
}

interface YFGP_IEntityBuilder {
    
    /**
     * Build entity data from post
     * 
     * @param \WP_Post $post Post object
     * @param array<string, mixed> $mapping Field mapping configuration
     * @return array<string, mixed>|null Entity data or null if failed
     */
    public function build_entity(\WP_Post $post, array $mapping): ?array;
}


