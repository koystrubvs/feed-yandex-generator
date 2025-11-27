<?php
/**
 * Centralized Logger Class
 * 
 * Handles logging with levels and message aggregation.
 * Prevents log spam in loops.
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.18.22
 */

if (!defined('ABSPATH')) {
    exit;
}

class YFGP_Logger {
    
    const ERROR = 1;
    const WARNING = 2;
    const INFO = 3;
    
    private static $instance = null;
    private $logged_messages = array();
    private $message_counts = array();
    private $min_level = self::INFO;
    
    private function __construct() {
        // Private constructor for singleton
    }
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Set minimum log level
     * 
     * @param int $level Minimum log level (ERROR, WARNING, INFO)
     * @return void
     */
    public function set_level($level) {
        $this->min_level = $level;
    }
    
    /**
     * Log a message
     * 
     * @param string $message Message to log
     * @param int $level Log level (ERROR, WARNING, INFO)
     * @return void
     */
    public function log($message, $level = self::INFO) {
        if ($level < $this->min_level) {
            return; // Skip if below minimum level
        }
        
        $message_key = md5($message . $level);
        
        // Aggregate duplicate messages
        if (isset($this->message_counts[$message_key])) {
            $this->message_counts[$message_key]++;
            return; // Don't log duplicates
        }
        
        $this->message_counts[$message_key] = 1;
        $this->logged_messages[] = array(
            'message' => $message,
            'level' => $level,
            'timestamp' => current_time('mysql'),
        );
        
        // Write to WordPress error log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $level_string = $this->get_level_string($level);
            error_log(sprintf('[YFGP %s] %s', $level_string, $message));
        }
    }
    
    /**
     * Log error
     * 
     * @param string $message Error message
     * @return void
     */
    public function error($message) {
        $this->log($message, self::ERROR);
    }
    
    /**
     * Log warning
     * 
     * @param string $message Warning message
     * @return void
     */
    public function warning($message) {
        $this->log($message, self::WARNING);
    }
    
    /**
     * Log info
     * 
     * @param string $message Info message
     * @return void
     */
    public function info($message) {
        $this->log($message, self::INFO);
    }
    
    /**
     * Get aggregated message counts
     * 
     * @return array<string, int> Message counts by message key
     */
    public function get_message_counts() {
        return $this->message_counts;
    }
    
    /**
     * Clear logs
     * 
     * @return void
     */
    public function clear() {
        $this->logged_messages = array();
        $this->message_counts = array();
    }
    
    /**
     * Get level string for display
     * 
     * @param int $level Log level
     * @return string Level string
     */
    private function get_level_string($level) {
        switch ($level) {
            case self::ERROR:
                return 'ERROR';
            case self::WARNING:
                return 'WARNING';
            case self::INFO:
            default:
                return 'INFO';
        }
    }
}

