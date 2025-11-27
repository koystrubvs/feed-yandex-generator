<?php
/**
 * SQL Injection Protection Tests
 * 
 * Tests for SQL injection protection in:
 * - AJAX handlers (post_type whitelisting)
 * - Field mapper (source_cpt whitelisting)
 * - Mapping validator (source_field validation)
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.18.22
 */

// Load WordPress
if (!defined('ABSPATH')) {
    // Find WordPress root: /var/www/html/ (from plugin: /var/www/html/wp-content/plugins/...)
    // Go up: Security -> Phase5 -> tests -> yandex-feed-generator-pro-v2 -> plugins -> wp-content -> html
    $wp_root = dirname(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))));
    define('ABSPATH', $wp_root . '/');
}

// wp-load.php is in WordPress root
if (file_exists(ABSPATH . 'wp-load.php')) {
    require_once ABSPATH . 'wp-load.php';
} else {
    // Fallback: try direct path
    require_once '/var/www/html/wp-load.php';
}
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/includes/class-constants.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/includes/class-mapping-config-validator.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/includes/class-field-mapper-unified.php';

class SqlInjectionTest {
    
    private $test_results = array();
    
    public function run_all_tests() {
        echo "=== SQL Injection Protection Tests ===\n\n";
        
        $this->test_invalid_post_type_whitelisting();
        $this->test_sql_injection_in_post_type();
        $this->test_source_cpt_whitelisting();
        $this->test_sql_injection_in_source_cpt();
        $this->test_mapping_validator_source_field();
        
        $this->print_results();
    }
    
    /**
     * Test 1: Invalid post_type is rejected by whitelisting
     */
    private function test_invalid_post_type_whitelisting() {
        echo "Test 1: Invalid post_type whitelisting...\n";
        
        // Set up test settings
        $settings = array(
            'post_type' => 'doctors',
            'cpt_clinics' => 'clinics',
            'cpt_services' => 'services'
        );
        update_option('yfgp_settings', $settings);
        
        // Test invalid post_type
        $validator = new YFGP_Mapping_Config_Validator($settings);
        $allowed = $validator->get_allowed_source_cpts();
        
        $invalid_post_types = array('invalid_cpt', 'malicious_type', 'hacked');
        
        $passed = true;
        foreach ($invalid_post_types as $invalid) {
            if (in_array($invalid, $allowed, true)) {
                $passed = false;
                echo "  ❌ FAILED: Invalid post_type '$invalid' is in whitelist\n";
            }
        }
        
        if ($passed) {
            echo "  ✅ PASSED: Invalid post_types are not in whitelist\n";
            $this->test_results['test_invalid_post_type_whitelisting'] = 'PASSED';
        } else {
            $this->test_results['test_invalid_post_type_whitelisting'] = 'FAILED';
        }
        echo "\n";
    }
    
    /**
     * Test 2: SQL injection attempts in post_type are blocked
     */
    private function test_sql_injection_in_post_type() {
        echo "Test 2: SQL injection in post_type...\n";
        
        $settings = array(
            'post_type' => 'doctors',
            'cpt_clinics' => 'clinics',
            'cpt_services' => 'services'
        );
        update_option('yfgp_settings', $settings);
        
        $sql_injection_attempts = array(
            "'; DROP TABLE wp_posts; --",
            "' OR '1'='1",
            "'; DELETE FROM wp_posts; --",
            "1' UNION SELECT * FROM wp_users--",
            "doctors'; INSERT INTO wp_posts VALUES (NULL, 'hacked'); --"
        );
        
        $validator = new YFGP_Mapping_Config_Validator($settings);
        $allowed = $validator->get_allowed_source_cpts();
        
        $passed = true;
        foreach ($sql_injection_attempts as $malicious) {
            $sanitized = sanitize_key($malicious);
            if (in_array($sanitized, $allowed, true)) {
                $passed = false;
                echo "  ❌ FAILED: SQL injection attempt '$malicious' (sanitized: '$sanitized') is in whitelist\n";
            } else {
                echo "  ✅ Blocked: '$malicious' → sanitized to '$sanitized' (not in whitelist)\n";
            }
        }
        
        if ($passed) {
            echo "  ✅ PASSED: All SQL injection attempts blocked\n";
            $this->test_results['test_sql_injection_in_post_type'] = 'PASSED';
        } else {
            $this->test_results['test_sql_injection_in_post_type'] = 'FAILED';
        }
        echo "\n";
    }
    
    /**
     * Test 3: source_cpt whitelisting in field mapper
     */
    private function test_source_cpt_whitelisting() {
        echo "Test 3: source_cpt whitelisting in field mapper...\n";
        
        $settings = array(
            'post_type' => 'doctors',
            'cpt_clinics' => 'clinics',
            'cpt_services' => 'services'
        );
        update_option('yfgp_settings', $settings);
        
        // Use singleton instance
        $mapper = YFGP_Field_Mapper_Unified::get_instance();
        $reflection = new ReflectionClass($mapper);
        $method = $reflection->getMethod('get_whitelisted_post_type');
        $method->setAccessible(true);
        
        // Test valid post_type
        $result = $method->invoke($mapper, 'doctors', $settings);
        if ($result === 'doctors') {
            echo "  ✅ Valid post_type 'doctors' accepted\n";
        } else {
            echo "  ❌ FAILED: Valid post_type 'doctors' rejected (got: " . var_export($result, true) . ")\n";
            $this->test_results['test_source_cpt_whitelisting'] = 'FAILED';
            echo "\n";
            return;
        }
        
        // Test invalid post_type
        $result = $method->invoke($mapper, 'invalid_cpt', $settings);
        if ($result === null) {
            echo "  ✅ Invalid post_type 'invalid_cpt' rejected (returned null)\n";
            $this->test_results['test_source_cpt_whitelisting'] = 'PASSED';
        } else {
            echo "  ❌ FAILED: Invalid post_type 'invalid_cpt' accepted (got: " . var_export($result, true) . ")\n";
            $this->test_results['test_source_cpt_whitelisting'] = 'FAILED';
        }
        echo "\n";
    }
    
    /**
     * Test 4: SQL injection in source_cpt
     */
    private function test_sql_injection_in_source_cpt() {
        echo "Test 4: SQL injection in source_cpt...\n";
        
        $settings = array(
            'post_type' => 'doctors',
            'cpt_clinics' => 'clinics',
            'cpt_services' => 'services'
        );
        update_option('yfgp_settings', $settings);
        
        $sql_injection_attempts = array(
            "'; DROP TABLE wp_posts; --",
            "' OR '1'='1",
            "doctors'; DELETE FROM wp_posts; --"
        );
        
        $mapper = YFGP_Field_Mapper_Unified::get_instance();
        $reflection = new ReflectionClass($mapper);
        $method = $reflection->getMethod('get_whitelisted_post_type');
        $method->setAccessible(true);
        
        $passed = true;
        foreach ($sql_injection_attempts as $malicious) {
            $result = $method->invoke($mapper, $malicious, $settings);
            if ($result !== null) {
                $passed = false;
                echo "  ❌ FAILED: SQL injection attempt '$malicious' accepted (got: " . var_export($result, true) . ")\n";
            } else {
                echo "  ✅ Blocked: '$malicious' → rejected (returned null)\n";
            }
        }
        
        if ($passed) {
            echo "  ✅ PASSED: All SQL injection attempts in source_cpt blocked\n";
            $this->test_results['test_sql_injection_in_source_cpt'] = 'PASSED';
        } else {
            $this->test_results['test_sql_injection_in_source_cpt'] = 'FAILED';
        }
        echo "\n";
    }
    
    /**
     * Test 5: Mapping validator source_field validation
     */
    private function test_mapping_validator_source_field() {
        echo "Test 5: Mapping validator source_field validation...\n";
        
        $validator = new YFGP_Mapping_Config_Validator();
        
        // Test malicious source_field
        $malicious_mapping = array(
            'source_type' => 'meta_field',
            'source_field' => "'; DROP TABLE wp_posts; --",
            'source_cpt' => 'doctors'
        );
        
        try {
            $result = $validator->validate($malicious_mapping);
            if ($result === false) {
                echo "  ✅ PASSED: Malicious source_field rejected\n";
                $this->test_results['test_mapping_validator_source_field'] = 'PASSED';
            } else {
                echo "  ❌ FAILED: Malicious source_field accepted\n";
                $this->test_results['test_mapping_validator_source_field'] = 'FAILED';
            }
        } catch (Exception $e) {
            echo "  ✅ PASSED: Malicious source_field rejected with exception: " . $e->getMessage() . "\n";
            $this->test_results['test_mapping_validator_source_field'] = 'PASSED';
        }
        echo "\n";
    }
    
    /**
     * Print test results summary
     */
    private function print_results() {
        echo "=== Test Results Summary ===\n\n";
        
        $passed = 0;
        $failed = 0;
        
        foreach ($this->test_results as $test => $result) {
            echo "$test: $result\n";
            if ($result === 'PASSED') {
                $passed++;
            } else {
                $failed++;
            }
        }
        
        echo "\nTotal: " . count($this->test_results) . " tests\n";
        echo "Passed: $passed\n";
        echo "Failed: $failed\n";
        
        if ($failed === 0) {
            echo "\n✅ ALL TESTS PASSED!\n";
        } else {
            echo "\n❌ SOME TESTS FAILED!\n";
        }
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli') {
    $test = new SqlInjectionTest();
    $test->run_all_tests();
}

