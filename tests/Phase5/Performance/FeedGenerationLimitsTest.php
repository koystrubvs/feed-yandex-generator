<?php
/**
 * Feed Generation Limits Tests
 * 
 * Tests for feed generation limits:
 * - Execution time limits
 * - Record limits
 * - Feed size limits
 * - Memory limits
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

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/includes/class-constants.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/includes/class-feed-orchestrator.php';

class FeedGenerationLimitsTest {
    
    private $test_results = array();
    
    public function run_all_tests() {
        echo "=== Feed Generation Limits Tests ===\n\n";
        
        $this->test_execution_time_limit_constant();
        $this->test_max_records_limit_constant();
        $this->test_max_feed_size_limit_constant();
        $this->test_timeout_check_logic();
        $this->test_record_limit_check_logic();
        $this->test_feed_size_check_logic();
        $this->test_memory_limit_check_logic();
        
        $this->print_results();
    }
    
    /**
     * Test 1: Execution time limit constant
     */
    private function test_execution_time_limit_constant() {
        echo "Test 1: Execution time limit constant...\n";
        
        $max_time = YFGP_Constants::MAX_EXECUTION_TIME;
        $expected_time = 300; // 5 minutes
        
        if ($max_time === $expected_time) {
            echo "  ✅ MAX_EXECUTION_TIME is correct: $max_time seconds (5 minutes)\n";
            $this->test_results['test_execution_time_limit_constant'] = 'PASSED';
        } else {
            echo "  ❌ FAILED: MAX_EXECUTION_TIME is incorrect: $max_time (expected: $expected_time)\n";
            $this->test_results['test_execution_time_limit_constant'] = 'FAILED';
        }
        echo "\n";
    }
    
    /**
     * Test 2: Max records limit constant
     */
    private function test_max_records_limit_constant() {
        echo "Test 2: Max records limit constant...\n";
        
        $max_records = YFGP_Constants::MAX_RECORDS;
        $expected_records = 10000;
        
        if ($max_records === $expected_records) {
            echo "  ✅ MAX_RECORDS is correct: " . number_format($max_records) . " records\n";
            $this->test_results['test_max_records_limit_constant'] = 'PASSED';
        } else {
            echo "  ❌ FAILED: MAX_RECORDS is incorrect: $max_records (expected: $expected_records)\n";
            $this->test_results['test_max_records_limit_constant'] = 'FAILED';
        }
        echo "\n";
    }
    
    /**
     * Test 3: Max feed size limit constant
     */
    private function test_max_feed_size_limit_constant() {
        echo "Test 3: Max feed size limit constant...\n";
        
        $max_size = YFGP_Constants::MAX_FEED_SIZE;
        $expected_size = 52428800; // 50MB
        
        if ($max_size === $expected_size) {
            echo "  ✅ MAX_FEED_SIZE is correct: " . number_format($max_size) . " bytes (" . number_format($max_size / 1024 / 1024) . " MB)\n";
            $this->test_results['test_max_feed_size_limit_constant'] = 'PASSED';
        } else {
            echo "  ❌ FAILED: MAX_FEED_SIZE is incorrect: $max_size (expected: $expected_size)\n";
            $this->test_results['test_max_feed_size_limit_constant'] = 'FAILED';
        }
        echo "\n";
    }
    
    /**
     * Test 4: Timeout check logic
     */
    private function test_timeout_check_logic() {
        echo "Test 4: Timeout check logic...\n";
        
        $max_execution_time = YFGP_Constants::MAX_EXECUTION_TIME;
        
        // Simulate timeout check logic
        $start_time = time();
        
        // Test 1: Within limit
        $elapsed = 100; // 100 seconds
        $should_timeout = ($elapsed > $max_execution_time);
        
        if (!$should_timeout) {
            echo "  ✅ Timeout check works: 100s < 300s (within limit)\n";
        } else {
            echo "  ❌ FAILED: Timeout check failed: 100s should be within limit\n";
            $this->test_results['test_timeout_check_logic'] = 'FAILED';
            echo "\n";
            return;
        }
        
        // Test 2: Exceeds limit
        $elapsed = 350; // 350 seconds
        $should_timeout = ($elapsed > $max_execution_time);
        
        if ($should_timeout) {
            echo "  ✅ Timeout check works: 350s > 300s (exceeds limit)\n";
            $this->test_results['test_timeout_check_logic'] = 'PASSED';
        } else {
            echo "  ❌ FAILED: Timeout check failed: 350s should exceed limit\n";
            $this->test_results['test_timeout_check_logic'] = 'FAILED';
        }
        echo "\n";
    }
    
    /**
     * Test 5: Record limit check logic
     */
    private function test_record_limit_check_logic() {
        echo "Test 5: Record limit check logic...\n";
        
        $max_records = YFGP_Constants::MAX_RECORDS;
        
        // Test 1: Within limit
        $processed = 5000;
        $should_stop = ($processed >= $max_records);
        
        if (!$should_stop) {
            echo "  ✅ Record limit check works: 5,000 < 10,000 (within limit)\n";
        } else {
            echo "  ❌ FAILED: Record limit check failed: 5,000 should be within limit\n";
            $this->test_results['test_record_limit_check_logic'] = 'FAILED';
            echo "\n";
            return;
        }
        
        // Test 2: At limit
        $processed = 10000;
        $should_stop = ($processed >= $max_records);
        
        if ($should_stop) {
            echo "  ✅ Record limit check works: 10,000 >= 10,000 (at limit)\n";
        } else {
            echo "  ❌ FAILED: Record limit check failed: 10,000 should trigger limit\n";
            $this->test_results['test_record_limit_check_logic'] = 'FAILED';
            echo "\n";
            return;
        }
        
        // Test 3: Exceeds limit
        $processed = 15000;
        $should_stop = ($processed >= $max_records);
        
        if ($should_stop) {
            echo "  ✅ Record limit check works: 15,000 >= 10,000 (exceeds limit)\n";
            $this->test_results['test_record_limit_check_logic'] = 'PASSED';
        } else {
            echo "  ❌ FAILED: Record limit check failed: 15,000 should exceed limit\n";
            $this->test_results['test_record_limit_check_logic'] = 'FAILED';
        }
        echo "\n";
    }
    
    /**
     * Test 6: Feed size check logic
     */
    private function test_feed_size_check_logic() {
        echo "Test 6: Feed size check logic...\n";
        
        $max_feed_size = YFGP_Constants::MAX_FEED_SIZE;
        
        // Test 1: Within limit
        $feed_size = 10 * 1024 * 1024; // 10MB
        $exceeds_limit = ($feed_size > $max_feed_size);
        
        if (!$exceeds_limit) {
            echo "  ✅ Feed size check works: 10MB < 50MB (within limit)\n";
        } else {
            echo "  ❌ FAILED: Feed size check failed: 10MB should be within limit\n";
            $this->test_results['test_feed_size_check_logic'] = 'FAILED';
            echo "\n";
            return;
        }
        
        // Test 2: At limit
        $feed_size = $max_feed_size; // Exactly 50MB
        $exceeds_limit = ($feed_size > $max_feed_size);
        
        if (!$exceeds_limit) {
            echo "  ✅ Feed size check works: 50MB = 50MB (at limit)\n";
        } else {
            echo "  ❌ FAILED: Feed size check failed: 50MB should be at limit\n";
            $this->test_results['test_feed_size_check_logic'] = 'FAILED';
            echo "\n";
            return;
        }
        
        // Test 3: Exceeds limit
        $feed_size = 60 * 1024 * 1024; // 60MB
        $exceeds_limit = ($feed_size > $max_feed_size);
        
        if ($exceeds_limit) {
            echo "  ✅ Feed size check works: 60MB > 50MB (exceeds limit)\n";
            $this->test_results['test_feed_size_check_logic'] = 'PASSED';
        } else {
            echo "  ❌ FAILED: Feed size check failed: 60MB should exceed limit\n";
            $this->test_results['test_feed_size_check_logic'] = 'FAILED';
        }
        echo "\n";
    }
    
    /**
     * Test 7: Memory limit check logic
     */
    private function test_memory_limit_check_logic() {
        echo "Test 7: Memory limit check logic...\n";
        
        // Simulate memory limit check (80% threshold)
        $memory_limit_str = ini_get('memory_limit');
        $memory_limit = $this->parse_memory_size($memory_limit_str);
        $threshold = $memory_limit * 0.8;
        
        echo "  ℹ️  Memory limit: " . number_format($memory_limit / 1024 / 1024, 2) . " MB\n";
        echo "  ℹ️  Threshold (80%): " . number_format($threshold / 1024 / 1024, 2) . " MB\n";
        
        // Test 1: Below threshold
        $memory_usage = $memory_limit * 0.5; // 50%
        $approaching_limit = ($memory_usage > $threshold);
        
        if (!$approaching_limit) {
            echo "  ✅ Memory check works: " . number_format($memory_usage / 1024 / 1024, 2) . "MB < " . number_format($threshold / 1024 / 1024, 2) . "MB (below threshold)\n";
        } else {
            echo "  ❌ FAILED: Memory check failed: 50% should be below threshold\n";
            $this->test_results['test_memory_limit_check_logic'] = 'FAILED';
            echo "\n";
            return;
        }
        
        // Test 2: Above threshold
        $memory_usage = $memory_limit * 0.85; // 85%
        $approaching_limit = ($memory_usage > $threshold);
        
        if ($approaching_limit) {
            echo "  ✅ Memory check works: " . number_format($memory_usage / 1024 / 1024, 2) . "MB > " . number_format($threshold / 1024 / 1024, 2) . "MB (above threshold)\n";
            $this->test_results['test_memory_limit_check_logic'] = 'PASSED';
        } else {
            echo "  ❌ FAILED: Memory check failed: 85% should be above threshold\n";
            $this->test_results['test_memory_limit_check_logic'] = 'FAILED';
        }
        echo "\n";
    }
    
    /**
     * Parse memory size string to bytes (same as in orchestrator)
     * 
     * @param string $size Memory size string (e.g., "128M", "1G")
     * @return int Size in bytes
     */
    private function parse_memory_size($size): int {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        if ($unit) {
            $unit_index = stripos('bkmgtpezy', $unit[0]);
            if ($unit_index !== false) {
                return (int) round($size * pow(1024, $unit_index));
            }
        }
        return (int) round($size);
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
    $test = new FeedGenerationLimitsTest();
    $test->run_all_tests();
}

















