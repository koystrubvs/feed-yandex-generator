<?php
/**
 * Error Handler
 * 
 * Unified error handling and logging.
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.18.39
 */

if (!defined('ABSPATH')) {
    exit;
}

class YFGP_Error_Handler {
    
    /**
     * @var YFGP_Error_Handler|null Singleton instance
     */
    private static ?YFGP_Error_Handler $instance = null;
    
    /**
     * Get singleton instance
     * 
     * @return YFGP_Error_Handler
     */
    public static function get_instance(): YFGP_Error_Handler {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor (singleton pattern)
     */
    private function __construct() {
    }
    
    /**
     * Execute callback safely with error handling
     * 
     * @param callable $callback Callback to execute
     * @param array<string, mixed>|string $context Error context (array or string)
     * @return mixed Result of callback or null on error
     */
    public function execute_safely(callable $callback, $context = '') {
        try {
            return $callback();
        } catch (\Throwable $e) {
            $context_str = is_array($context) ? ($context['action'] ?? 'General') : $context;
            $this->handle_exception($e, $context_str);
            return null;
        }
    }
    
    /**
     * Handle AJAX error
     * 
     * @param \Throwable|string $error Error exception or message
     * @param array<string, mixed> $context Error context
     * @return void
     */
    public function handle_ajax_error($error, array $context = array()): void {
        if ($error instanceof \Throwable) {
            $message = $error->getMessage();
            $this->handle_exception($error, $context['action'] ?? 'AJAX');
        } else {
            $message = (string)$error;
            self::handle($message, $context['action'] ?? 'AJAX', E_WARNING);
        }
        
        // Send JSON error response
        if (!headers_sent()) {
            wp_send_json_error(array(
                'message' => $message,
                'context' => $context
            ));
        }
    }
    
    /**
     * Handle AJAX success
     * 
     * @param array<string, mixed> $data Success data
     * @return void
     */
    public function handle_ajax_success(array $data = array()): void {
        if (!headers_sent()) {
            wp_send_json_success($data);
        }
    }
    
    /**
     * Register critical error
     * 
     * @param string $message Error message
     * @param array<string, mixed> $context Error context
     * @return void
     */
    public function register_critical_error(string $message, array $context = array()): void {
        self::handle($message, $context['action'] ?? 'Critical', E_ERROR);
    }
    
    /**
     * Handle error
     * 
     * @param string $message Error message
     * @param string $context Error context
     * @param int $severity Error severity (E_ERROR, E_WARNING, etc.)
     * @return void
     */
    public static function handle(string $message, string $context = '', int $severity = E_WARNING): void {
        $log_message = sprintf(
            '[YFGP] %s: %s',
            $context ?: 'General',
            $message
        );
        
        // Log via YFGP_Logger if available
        if (class_exists('YFGP_Logger')) {
            $logger = YFGP_Logger::get_instance();
            if ($severity === E_ERROR) {
                $logger->error($log_message);
            } else {
                $logger->warning($log_message);
            }
        } else {
            error_log($log_message);
        }
    }
    
    /**
     * Handle exception
     * 
     * @param \Throwable $exception Exception to handle
     * @param string $context Error context
     * @return void
     */
    public function handle_exception(\Throwable $exception, string $context = ''): void {
        $message = sprintf(
            'Exception: %s in %s:%d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );
        
        self::handle($message, $context, E_ERROR);
    }
}
