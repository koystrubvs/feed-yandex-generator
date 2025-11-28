<?php
/**
 * Base Service Resolver
 * 
 * Determines base service for doctors according to Yandex.Health specification.
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.18.39
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once YFGP_PLUGIN_DIR . 'includes/interfaces/interface-base-service-resolver.php';

class YFGP_Base_Service_Resolver implements YFGP_IBaseServiceResolver {
    
    /**
     * @var array<string, mixed> Plugin settings
     */
    private $settings;
    
    /**
     * Constructor
     * 
     * @param array<string, mixed> $settings Plugin settings
     */
    public function __construct(array $settings) {
        $this->settings = $settings;
    }
    
    /**
     * Determine base service for a doctor
     * 
     * According to Yandex.Health spec:
     * "Указывайте первичный прием как базовую услугу, за исключением специальностей, 
     * где это неприменимо (например, для врача УЗИ)."
     * 
     * @param array<string, mixed> $doctor_data Doctor entity data
     * @param array<string, mixed> $services Array of services for this doctor
     * @return array<string, mixed>|null Base service data or null if not found
     */
    public function determine_base_service(array $doctor_data, array $services): ?array {
        if (empty($services)) {
            return null;
        }
        
        // Check if doctor has specialties that don't require primary appointment
        $specialties_no_primary = $this->get_specialties_no_primary();
        $doctor_specialties = $this->get_doctor_specialties($doctor_data);
        
        $skip_primary = false;
        foreach ($doctor_specialties as $specialty) {
            if (in_array($specialty, $specialties_no_primary, true)) {
                $skip_primary = true;
                break;
            }
        }
        
        // If primary is not applicable, find first service with price
        if ($skip_primary) {
            return $this->find_first_service_with_price($services);
        }
        
        // Try to find service marked as base
        foreach ($services as $service) {
            if (!empty($service['is_base_service']) && $service['is_base_service'] === true) {
                return $service;
            }
        }
        
        // Try to find service with name matching default_service_name
        $default_name = $this->get_default_service_name();
        if (!empty($default_name)) {
            foreach ($services as $service) {
                if (!empty($service['name']) && $service['name'] === $default_name) {
                    return $service;
                }
            }
        }
        
        // Fallback: first service with price
        return $this->find_first_service_with_price($services);
    }
    
    /**
     * Get specialties that don't require primary appointment
     * 
     * @return array<string> Array of specialty slugs
     */
    private function get_specialties_no_primary(): array {
        $specialties = $this->settings['specialties_no_primary'] ?? array();
        return is_array($specialties) ? $specialties : array();
    }
    
    /**
     * Get doctor specialties from doctor data
     * 
     * @param array<string, mixed> $doctor_data Doctor entity data
     * @return array<string> Array of specialty slugs
     */
    private function get_doctor_specialties(array $doctor_data): array {
        $specialties = array();
        
        if (!empty($doctor_data['specialities']) && is_array($doctor_data['specialities'])) {
            foreach ($doctor_data['specialities'] as $specialty) {
                if (is_string($specialty)) {
                    $specialties[] = $specialty;
                } elseif (is_array($specialty) && !empty($specialty['id'])) {
                    $specialties[] = $specialty['id'];
                }
            }
        }
        
        return $specialties;
    }
    
    /**
     * Find first service with price
     * 
     * @param array<array<string, mixed>> $services Array of services
     * @return array<string, mixed>|null First service with price or null
     */
    private function find_first_service_with_price(array $services): ?array {
        foreach ($services as $service) {
            if (!empty($service['price']) && is_numeric($service['price']) && $service['price'] > 0) {
                return $service;
            }
        }
        return null;
    }
    
    /**
     * Get default service name from settings
     * 
     * @return string Default service name or empty string
     */
    private function get_default_service_name(): string {
        $name = $this->settings['default_service_name'] ?? '';
        return is_string($name) ? trim($name) : '';
    }
}

