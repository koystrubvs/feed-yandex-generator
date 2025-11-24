<?php
/**
 * Sentry Integration - интеграция с Sentry для мониторинга ошибок
 * 
 * Функциональность:
 * - Инициализация Sentry SDK
 * - Интеграция с YFGP_Error_Handler
 * - Отправка ошибок и исключений в Sentry
 * - Настройка контекста (версия плагина, окружение)
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.18.21
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sentry Integration Class
 */
class YFGP_Sentry_Integration {
    
    /**
     * Singleton instance
     * 
     * @var YFGP_Sentry_Integration|null
     */
    private static $instance = null;
    
    /**
     * Флаг инициализации Sentry
     * 
     * @var bool
     */
    private $initialized = false;
    
    /**
     * Получить singleton instance
     * 
     * @return YFGP_Sentry_Integration
     */
    public static function get_instance(): YFGP_Sentry_Integration {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Конструктор
     */
    private function __construct() {
        // Инициализация отложена до вызова init()
    }
    
    /**
     * Инициализация Sentry SDK
     * 
     * @return bool true если инициализация успешна, false в противном случае
     */
    public function init(): bool {
        // Проверяем, что Sentry SDK доступен
        if (!class_exists('\Sentry\SentrySdk')) {
            // Не логируем, если SDK не установлен - это нормально
            return false;
        }
        
        // Проверяем настройки плагина
        $settings = get_option('yfgp_settings', array());
        
        // Проверяем, включен ли Sentry
        if (empty($settings['sentry_enabled'])) {
            return false;
        }
        
        // Проверяем наличие DSN
        $dsn = $settings['sentry_dsn'] ?? '';
        if (empty($dsn)) {
            if (function_exists('error_log')) {
                error_log('YFGP: Sentry DSN not configured');
            }
            return false;
        }
        
        try {
            // Инициализация Sentry с минимальной конфигурацией для безопасности
            $sentry_config = array(
                'dsn' => $dsn,
                'environment' => $this->get_environment(),
                'release' => defined('YFGP_VERSION') ? YFGP_VERSION : 'unknown',
                'traces_sample_rate' => $this->get_traces_sample_rate($settings),
                'send_default_pii' => false, // Не отправляем PII по умолчанию
            );
            
            // Добавляем before_send callback для фильтрации событий
            $sentry_config['before_send'] = array($this, 'filter_event_callback');
            
            \Sentry\init($sentry_config);
            
            // Устанавливаем контекст плагина
            $this->set_plugin_context();
            
            $this->initialized = true;
            
            if (function_exists('error_log') && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('YFGP: Sentry initialized successfully');
            }
            
            return true;
            
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log('YFGP: Failed to initialize Sentry: ' . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Получить окружение (production, staging, development)
     * 
     * @return string
     */
    private function get_environment(): string {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return 'development';
        }
        
        // Можно расширить логику определения окружения
        return 'production';
    }
    
    /**
     * Получить sample rate для трейсинга
     * 
     * @param array<string, mixed> $settings Настройки плагина
     * @return float
     */
    private function get_traces_sample_rate(array $settings): float {
        // По умолчанию 0.1 (10% запросов)
        return (float) ($settings['sentry_traces_sample_rate'] ?? 0.1);
    }
    
    /**
     * Установить контекст плагина в Sentry
     * 
     * @return void
     */
    private function set_plugin_context(): void {
        try {
            \Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
                $scope->setTag('plugin', 'yandex-feed-generator-pro');
                $scope->setTag('plugin_version', YFGP_VERSION ?? 'unknown');
                $scope->setTag('wordpress_version', get_bloginfo('version'));
                $scope->setTag('php_version', PHP_VERSION);
                
                // Добавляем контекст
                $scope->setContext('plugin', [
                    'name' => 'Yandex Feed Generator Pro',
                    'version' => YFGP_VERSION ?? 'unknown',
                    'plugin_dir' => YFGP_PLUGIN_DIR ?? '',
                ]);
            });
        } catch (\Throwable $e) {
            // Игнорируем ошибки установки контекста
            if (function_exists('error_log') && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('YFGP: Failed to set Sentry context: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Callback для фильтрации событий перед отправкой (публичный метод для использования в callback)
     * 
     * @param \Sentry\Event $event Событие
     * @param \Sentry\EventHint|null $hint Подсказки
     * @return \Sentry\Event|null
     */
    public function filter_event_callback(\Sentry\Event $event, ?\Sentry\EventHint $hint): ?\Sentry\Event {
        return $this->filter_event($event, $hint);
    }
    
    /**
     * Фильтровать события перед отправкой
     * 
     * @param \Sentry\Event $event Событие
     * @param \Sentry\EventHint|null $hint Подсказки
     * @return \Sentry\Event|null
     */
    private function filter_event(\Sentry\Event $event, ?\Sentry\EventHint $hint): ?\Sentry\Event {
        // Можно добавить фильтрацию по типу ошибок, исключениям и т.д.
        // Например, игнорировать определенные ошибки WordPress
        
        // Игнорируем deprecation warnings в production
        if ($this->get_environment() === 'production') {
            $exceptions = $event->getExceptions();
            foreach ($exceptions as $exception) {
                if (stripos($exception->getType(), 'deprecated') !== false) {
                    return null; // Не отправляем
                }
            }
        }
        
        return $event;
    }
    
    /**
     * Отправить исключение в Sentry
     * 
     * @param \Throwable $exception Исключение
     * @param array<string, mixed> $context Дополнительный контекст
     * @return void
     */
    public function capture_exception(\Throwable $exception, array $context = array()): void {
        if (!$this->initialized) {
            return;
        }
        
        try {
            \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($context): void {
                // Добавляем контекст
                if (!empty($context)) {
                    $scope->setContext('plugin_context', $context);
                }
            });
            
            \Sentry\captureException($exception);
            
        } catch (\Throwable $e) {
            // Игнорируем ошибки отправки в Sentry
            if (function_exists('error_log') && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('YFGP: Failed to capture exception in Sentry: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Отправить сообщение об ошибке в Sentry
     * 
     * @param string $message Сообщение
     * @param string $level Уровень (error, warning, info)
     * @param array<string, mixed> $context Дополнительный контекст
     * @return void
     */
    public function capture_message(string $message, string $level = 'error', array $context = array()): void {
        if (!$this->initialized) {
            return;
        }
        
        try {
            $sentry_level = \Sentry\Severity::error();
            switch (strtolower($level)) {
                case 'warning':
                    $sentry_level = \Sentry\Severity::warning();
                    break;
                case 'info':
                    $sentry_level = \Sentry\Severity::info();
                    break;
                default:
                    $sentry_level = \Sentry\Severity::error();
            }
            
            \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($context): void {
                if (!empty($context)) {
                    $scope->setContext('plugin_context', $context);
                }
            });
            
            \Sentry\captureMessage($message, $sentry_level);
            
        } catch (\Throwable $e) {
            // Игнорируем ошибки отправки в Sentry
            if (function_exists('error_log') && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('YFGP: Failed to capture message in Sentry: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Добавить пользовательский контекст
     * 
     * @param string $key Ключ
     * @param mixed $value Значение
     * @return void
     */
    public function set_context(string $key, $value): void {
        if (!$this->initialized) {
            return;
        }
        
        try {
            \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($key, $value): void {
                $scope->setContext($key, $value);
            });
        } catch (\Throwable $e) {
            // Игнорируем ошибки
        }
    }
    
    /**
     * Добавить тег
     * 
     * @param string $key Ключ тега
     * @param string $value Значение тега
     * @return void
     */
    public function set_tag(string $key, string $value): void {
        if (!$this->initialized) {
            return;
        }
        
        try {
            \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($key, $value): void {
                $scope->setTag($key, $value);
            });
        } catch (\Throwable $e) {
            // Игнорируем ошибки
        }
    }
    
    /**
     * Проверить, инициализирован ли Sentry
     * 
     * @return bool
     */
    public function is_initialized(): bool {
        return $this->initialized;
    }
}

/**
 * Вспомогательная функция для получения Sentry Integration
 * 
 * @return YFGP_Sentry_Integration
 */
function yfgp_sentry(): YFGP_Sentry_Integration {
    return YFGP_Sentry_Integration::get_instance();
}


