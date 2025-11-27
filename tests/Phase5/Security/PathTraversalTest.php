<?php
/**
 * Path Traversal Protection Tests
 * 
 * Tests for path traversal protection in:
 * - validate_feed_filename() method
 * - save_feed_file() method
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.18.22
 */

// Load WordPress
if (!defined('ABSPATH')) {
    $wp_root = dirname(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))));
    define('ABSPATH', $wp_root . '/');
}

if (file_exists(ABSPATH . 'wp-load.php')) {
    require_once ABSPATH . 'wp-load.php';
} else {
    require_once '/var/www/html/wp-load.php';
}

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/yandex-feed-generator-pro.php';

class PathTraversalTest {
    
    private $test_results = array();
    
    public function run_all_tests() {
        echo "=== Path Traversal Protection Tests ===\n\n";
        
        $this->test_basic_path_traversal();
        $this->test_encoded_path_traversal();
        $this->test_double_encoded_path_traversal();
        $this->test_null_byte_injection();
        $this->test_absolute_paths();
        $this->test_windows_paths();
        $this->test_valid_filenames();
        $this->test_filename_extension();
        $this->test_filename_characters();
        
        $this->print_results();
    }
    
    /**
     * Test 1: Basic path traversal attempts
     */
    private function test_basic_path_traversal() {
        echo "Test 1: Basic path traversal attempts...\n";
        
        $plugin = Yandex_Feed_Generator_Pro::get_instance();
        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('validate_feed_filename');
        $method->setAccessible(true);
        
        $path_traversal_attempts = array(
            '../../../etc/passwd',
            '../../etc/passwd',
            '../etc/passwd',
            '....//....//etc/passwd',
            '..\\..\\..\\windows\\system32\\config\\sam',
            '../../../../etc/passwd',
            '/etc/passwd',
            'C:\\Windows\\System32\\config\\sam'
        );
        
        $passed = true;
        foreach ($path_traversal_attempts as $malicious) {
            try {
                $result = $method->invoke($plugin, $malicious);
                
                // basename() should strip directory traversal
                $basename_result = basename($malicious);
                
                // Check if result is safe (no .. or / or \ in result)
                // Note: basename() extracts only filename, so words like "passwd" are OK
                // The important thing is that path traversal characters are removed
                if (strpos($result, '..') !== false || 
                    strpos($result, '/') !== false || 
                    strpos($result, '\\') !== false) {
                    $passed = false;
                    echo "  ❌ FAILED: Path traversal characters in result: '$malicious' → '$result'\n";
                } else {
                    // basename() correctly extracted only filename - this is safe
                    echo "  ✅ Blocked: '$malicious' → '$result' (basename() extracted filename only)\n";
                }
            } catch (Exception $e) {
                // Exception is also acceptable (validation failed)
                echo "  ✅ Blocked (exception): '$malicious' → " . $e->getMessage() . "\n";
            }
        }
        
        if ($passed) {
            echo "  ✅ PASSED: All basic path traversal attempts blocked\n";
            $this->test_results['test_basic_path_traversal'] = 'PASSED';
        } else {
            $this->test_results['test_basic_path_traversal'] = 'FAILED';
        }
        echo "\n";
    }
    
    /**
     * Test 2: URL-encoded path traversal
     */
    private function test_encoded_path_traversal() {
        echo "Test 2: URL-encoded path traversal...\n";
        
        $plugin = Yandex_Feed_Generator_Pro::get_instance();
        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('validate_feed_filename');
        $method->setAccessible(true);
        
        $encoded_attempts = array(
            '..%2F..%2Fetc%2Fpasswd',
            '%2E%2E%2F%2E%2E%2Fetc%2Fpasswd',
            '..%5C..%5Cwindows%5Csystem32',
            '%2E%2E%5C%2E%2E%5Cwindows'
        );
        
        $passed = true;
        foreach ($encoded_attempts as $malicious) {
            try {
                $result = $method->invoke($plugin, $malicious);
                
                // URL decode first, then check
                $decoded = urldecode($malicious);
                $basename_result = basename($decoded);
                
                // Check if result is safe
                if (strpos($result, '..') !== false || 
                    strpos($result, '/') !== false || 
                    strpos($result, '\\') !== false) {
                    $passed = false;
                    echo "  ❌ FAILED: Encoded path traversal not blocked: '$malicious' → '$result'\n";
                } else {
                    echo "  ✅ Blocked: '$malicious' → '$result'\n";
                }
            } catch (Exception $e) {
                echo "  ✅ Blocked (exception): '$malicious' → " . $e->getMessage() . "\n";
            }
        }
        
        if ($passed) {
            echo "  ✅ PASSED: All encoded path traversal attempts blocked\n";
            $this->test_results['test_encoded_path_traversal'] = 'PASSED';
        } else {
            $this->test_results['test_encoded_path_traversal'] = 'FAILED';
        }
        echo "\n";
    }
    
    /**
     * Test 3: Double-encoded path traversal
     */
    private function test_double_encoded_path_traversal() {
        echo "Test 3: Double-encoded path traversal...\n";
        
        $plugin = Yandex_Feed_Generator_Pro::get_instance();
        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('validate_feed_filename');
        $method->setAccessible(true);
        
        $double_encoded = array(
            '%252E%252E%252F%252E%252E%252Fetc%252Fpasswd',
            '%252E%252E%255C%252E%252E%255Cwindows'
        );
        
        $passed = true;
        foreach ($double_encoded as $malicious) {
            try {
                $result = $method->invoke($plugin, $malicious);
                
                // Double decode
                $decoded = urldecode(urldecode($malicious));
                $basename_result = basename($decoded);
                
                if (strpos($result, '..') !== false || 
                    strpos($result, '/') !== false || 
                    strpos($result, '\\') !== false) {
                    $passed = false;
                    echo "  ❌ FAILED: Double-encoded path traversal not blocked: '$malicious' → '$result'\n";
                } else {
                    echo "  ✅ Blocked: '$malicious' → '$result'\n";
                }
            } catch (Exception $e) {
                echo "  ✅ Blocked (exception): '$malicious' → " . $e->getMessage() . "\n";
            }
        }
        
        if ($passed) {
            echo "  ✅ PASSED: All double-encoded path traversal attempts blocked\n";
            $this->test_results['test_double_encoded_path_traversal'] = 'PASSED';
        } else {
            $this->test_results['test_double_encoded_path_traversal'] = 'FAILED';
        }
        echo "\n";
    }
    
    /**
     * Test 4: Null byte injection
     */
    private function test_null_byte_injection() {
        echo "Test 4: Null byte injection...\n";
        
        $plugin = Yandex_Feed_Generator_Pro::get_instance();
        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('validate_feed_filename');
        $method->setAccessible(true);
        
        $null_byte_attempts = array(
            "../../etc/passwd\0.yml",
            "doctors\0.php",
            "test\0\0\0.yml"
        );
        
        $passed = true;
        foreach ($null_byte_attempts as $malicious) {
            try {
                $result = $method->invoke($plugin, $malicious);
                
                // Null bytes should be removed or cause validation failure
                if (strpos($result, "\0") !== false) {
                    $passed = false;
                    echo "  ❌ FAILED: Null byte not removed: '$malicious' → '$result'\n";
                } else {
                    echo "  ✅ Handled: '$malicious' → '$result'\n";
                }
            } catch (Exception $e) {
                echo "  ✅ Blocked (exception): '$malicious' → " . $e->getMessage() . "\n";
            }
        }
        
        if ($passed) {
            echo "  ✅ PASSED: Null byte injection handled correctly\n";
            $this->test_results['test_null_byte_injection'] = 'PASSED';
        } else {
            $this->test_results['test_null_byte_injection'] = 'FAILED';
        }
        echo "\n";
    }
    
    /**
     * Test 5: Absolute paths
     */
    private function test_absolute_paths() {
        echo "Test 5: Absolute paths...\n";
        
        $plugin = Yandex_Feed_Generator_Pro::get_instance();
        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('validate_feed_filename');
        $method->setAccessible(true);
        
        $absolute_paths = array(
            '/etc/passwd',
            '/var/www/html/wp-config.php',
            'C:\\Windows\\System32\\config\\sam',
            'C:/Windows/System32/config/sam'
        );
        
        $passed = true;
        foreach ($absolute_paths as $malicious) {
            try {
                $result = $method->invoke($plugin, $malicious);
                
                // basename() should extract only filename
                $basename_result = basename($malicious);
                
                // Check if result is safe (no absolute path components)
                // basename() extracts only filename, so absolute paths become relative filenames
                if (strpos($result, '/') === 0 || 
                    preg_match('/^[A-Z]:\\\\/', $result) ||
                    strpos($result, '..') !== false ||
                    strpos($result, '\\') !== false) {
                    $passed = false;
                    echo "  ❌ FAILED: Path traversal characters in result: '$malicious' → '$result'\n";
                } else {
                    // basename() correctly extracted only filename - this is safe
                    echo "  ✅ Blocked: '$malicious' → '$result' (basename() extracted filename only)\n";
                }
            } catch (Exception $e) {
                echo "  ✅ Blocked (exception): '$malicious' → " . $e->getMessage() . "\n";
            }
        }
        
        if ($passed) {
            echo "  ✅ PASSED: All absolute paths blocked\n";
            $this->test_results['test_absolute_paths'] = 'PASSED';
        } else {
            $this->test_results['test_absolute_paths'] = 'FAILED';
        }
        echo "\n";
    }
    
    /**
     * Test 6: Windows-style paths
     */
    private function test_windows_paths() {
        echo "Test 6: Windows-style paths...\n";
        
        $plugin = Yandex_Feed_Generator_Pro::get_instance();
        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('validate_feed_filename');
        $method->setAccessible(true);
        
        $windows_paths = array(
            '..\\..\\..\\windows\\system32',
            'C:\\\\Windows\\\\System32',
            '..\\..\\etc\\passwd',
            '\\windows\\system32'
        );
        
        $passed = true;
        foreach ($windows_paths as $malicious) {
            try {
                $result = $method->invoke($plugin, $malicious);
                
                // basename() should handle Windows paths too
                $basename_result = basename($malicious);
                
                // Check if path traversal characters are removed
                // basename() extracts only filename, so directory names are OK
                if (strpos($result, '..') !== false || 
                    strpos($result, '\\') !== false) {
                    $passed = false;
                    echo "  ❌ FAILED: Path traversal characters in result: '$malicious' → '$result'\n";
                } else {
                    // basename() correctly extracted only filename - this is safe
                    echo "  ✅ Blocked: '$malicious' → '$result' (basename() extracted filename only)\n";
                }
            } catch (Exception $e) {
                echo "  ✅ Blocked (exception): '$malicious' → " . $e->getMessage() . "\n";
            }
        }
        
        if ($passed) {
            echo "  ✅ PASSED: All Windows paths blocked\n";
            $this->test_results['test_windows_paths'] = 'PASSED';
        } else {
            $this->test_results['test_windows_paths'] = 'FAILED';
        }
        echo "\n";
    }
    
    /**
     * Test 7: Valid filenames (should pass)
     */
    private function test_valid_filenames() {
        echo "Test 7: Valid filenames (should pass)...\n";
        
        $plugin = Yandex_Feed_Generator_Pro::get_instance();
        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('validate_feed_filename');
        $method->setAccessible(true);
        
        $valid_filenames = array(
            'doctors.yml',
            'clinics.yml',
            'services.yml',
            'feed-2025-11-25.yml',
            'test_file.yml',
            'my-feed.yml'
        );
        
        $passed = true;
        foreach ($valid_filenames as $valid) {
            try {
                $result = $method->invoke($plugin, $valid);
                
                // Valid filenames should pass validation
                if (empty($result) || !str_ends_with($result, '.yml')) {
                    $passed = false;
                    echo "  ❌ FAILED: Valid filename rejected: '$valid' → '$result'\n";
                } else {
                    echo "  ✅ Accepted: '$valid' → '$result'\n";
                }
            } catch (Exception $e) {
                $passed = false;
                echo "  ❌ FAILED: Valid filename rejected with exception: '$valid' → " . $e->getMessage() . "\n";
            }
        }
        
        if ($passed) {
            echo "  ✅ PASSED: All valid filenames accepted\n";
            $this->test_results['test_valid_filenames'] = 'PASSED';
        } else {
            $this->test_results['test_valid_filenames'] = 'FAILED';
        }
        echo "\n";
    }
    
    /**
     * Test 8: Filename extension validation
     */
    private function test_filename_extension() {
        echo "Test 8: Filename extension validation...\n";
        
        $plugin = Yandex_Feed_Generator_Pro::get_instance();
        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('validate_feed_filename');
        $method->setAccessible(true);
        
        $extension_tests = array(
            'doctors' => true,  // Should add .yml
            'doctors.yml' => true,
            'doctors.xml' => true,  // Should change to .yml
            'doctors.php' => true,  // Should change to .yml
            'doctors.txt' => true,  // Should change to .yml
        );
        
        $passed = true;
        foreach ($extension_tests as $filename => $should_pass) {
            try {
                $result = $method->invoke($plugin, $filename);
                
                // All should end with .yml
                if (!str_ends_with($result, '.yml')) {
                    $passed = false;
                    echo "  ❌ FAILED: Extension not corrected: '$filename' → '$result'\n";
                } else {
                    echo "  ✅ Extension OK: '$filename' → '$result'\n";
                }
            } catch (Exception $e) {
                if ($should_pass) {
                    $passed = false;
                    echo "  ❌ FAILED: Valid filename rejected: '$filename' → " . $e->getMessage() . "\n";
                } else {
                    echo "  ✅ Rejected (expected): '$filename' → " . $e->getMessage() . "\n";
                }
            }
        }
        
        if ($passed) {
            echo "  ✅ PASSED: Filename extension validation works correctly\n";
            $this->test_results['test_filename_extension'] = 'PASSED';
        } else {
            $this->test_results['test_filename_extension'] = 'FAILED';
        }
        echo "\n";
    }
    
    /**
     * Test 9: Filename character validation
     */
    private function test_filename_characters() {
        echo "Test 9: Filename character validation...\n";
        
        $plugin = Yandex_Feed_Generator_Pro::get_instance();
        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('validate_feed_filename');
        $method->setAccessible(true);
        
        $character_tests = array(
            'test-file.yml' => true,  // Valid: letters, dash
            'test_file.yml' => true,  // Valid: letters, underscore
            'test123.yml' => true,    // Valid: letters, numbers
            'test.file.yml' => true,  // Valid: letters, dot
            'test@file.yml' => false, // Invalid: @ symbol
            'test#file.yml' => false, // Invalid: # symbol
            'test$file.yml' => false, // Invalid: $ symbol
            'test file.yml' => false, // Invalid: space
        );
        
        $passed = true;
        foreach ($character_tests as $filename => $should_pass) {
            try {
                $result = $method->invoke($plugin, $filename);
                
                // Check if invalid characters are removed
                $invalid_chars = array('@', '#', '$', ' ');
                $has_invalid = false;
                foreach ($invalid_chars as $char) {
                    if (strpos($result, $char) !== false) {
                        $has_invalid = true;
                        break;
                    }
                }
                
                if ($has_invalid) {
                    $passed = false;
                    echo "  ❌ FAILED: Invalid characters not removed: '$filename' → '$result'\n";
                } else {
                    echo "  ✅ Sanitized: '$filename' → '$result'\n";
                }
            } catch (Exception $e) {
                if ($should_pass) {
                    $passed = false;
                    echo "  ❌ FAILED: Valid filename rejected: '$filename' → " . $e->getMessage() . "\n";
                } else {
                    echo "  ✅ Rejected (expected): '$filename' → " . $e->getMessage() . "\n";
                }
            }
        }
        
        if ($passed) {
            echo "  ✅ PASSED: Filename character validation works correctly\n";
            $this->test_results['test_filename_characters'] = 'PASSED';
        } else {
            $this->test_results['test_filename_characters'] = 'FAILED';
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
    $test = new PathTraversalTest();
    $test->run_all_tests();
}

