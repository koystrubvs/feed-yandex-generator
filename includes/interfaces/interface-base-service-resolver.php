<?php
/**
 * Base Service Resolver Interface
 * 
 * Interface for determining base service for doctors.
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.18.39
 */

if (!defined('ABSPATH')) {
    exit;
}

interface YFGP_IBaseServiceResolver {
    
    /**
     * Determine base service for a doctor
     * 
     * @param array<string, mixed> $doctor_data Doctor entity data
     * @param array<string, mixed> $services Array of services
     * @return array<string, mixed>|null Base service data or null
     */
    public function determine_base_service(array $doctor_data, array $services): ?array;
}


