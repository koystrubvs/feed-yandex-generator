<?php
/**
 * Error Handler - унифицированная обработка ошибок и логирование
 * 
 * Функциональность:
 * - Централизованное логирование ошибок
 * - Унифицированные ответы для AJAX
 * - Логирование для cron задач
 * - Интеграция с WordPress error_log
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.18.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Error Handler Class
 */
class YFGP_Error_Handler {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Лог ошибок (в памяти для текущего запроса)
     * 
     * @var array<string, mixed>
     */
    private $error_log = array();
    
    /**
     * Получить singleton instance
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
     * Конструктор
     */
    private function __construct() {
        // Регистрируем обработчик ошибок PHP
        set_error_handler(array($this, 'handle_php_error'), E_ALL);
        set_exception_handler(array($this, 'handle_exception'));
        
        // v4.18.8: Регистрируем admin_notices для критичных ошибок
        add_action('admin_notices', array($this, 'show_critical_errors_notice'));
    }
    
    /**
     * Обработка PHP ошибок
     * 
     * @param int $errno Уровень ошибки
     * @param string $errstr Сообщение об ошибке
     * @param string $errfile Файл с ошибкой
     * @param int $errline Строка с ошибкой
     * @return bool
     */
    public function handle_php_error($errno, $errstr, $errfile, $errline): bool {
        // Игнорируем ошибки, которые не должны логироваться
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $error_type = $this->get_error_type($errno);
        $message = sprintf(
            '[YFGP] %s: %s in %s on line %d',
            $error_type,
            $errstr,
            basename($errfile),
            $errline
        );
        
        $this->log_error($error_type, $message, array(
            'file' => $errfile,
            'line' => $errline,
            'errno' => $errno
        ));
        
        // Не прерываем выполнение для warning/notice
        if ($errno === E_ERROR || $errno === E_PARSE || $errno === E_CORE_ERROR || $errno === E_COMPILE_ERROR) {
            return false; // Позволяем PHP обработать критическую ошибку
        }
        
        return true;
    }
    
    /**
     * Обработка исключений
     * 
     * @param Throwable $exception Исключение
     * @return void
     */
    public function handle_exception($exception): void {
        $message = sprintf(
            '[YFGP] Exception: %s in %s on line %d',
            $exception->getMessage(),
            basename($exception->getFile()),
            $exception->getLine()
        );
        
        $this->log_error('exception', $message, array(
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ));
        
        // v4.18.8: Регистрируем критичную ошибку для отображения в admin_notices
        $user_message = sprintf(
            'Исключение: %s в файле %s на строке %d',
            $exception->getMessage(),
            basename($exception->getFile()),
            $exception->getLine()
        );
        $this->register_critical_error($user_message, 'error', array(
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        ));
    }
    
    /**
     * Логирование ошибки
     * 
     * @param string $type Тип ошибки (error, warning, exception)
     * @param string $message Сообщение
     * @param array<string, mixed> $context Дополнительный контекст
     * @return void
     */
    public function log_error(string $type, string $message, array $context = array()): void {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'type' => $type,
            'message' => $message,
            'context' => $context
        );
        
        // Добавляем в лог в памяти
        $this->error_log[] = $log_entry;
        
        // Логируем в WordPress error_log
        $log_message = $message;
        if (!empty($context)) {
            $log_message .= ' | Context: ' . wp_json_encode($context);
        }
        
        error_log($log_message);
        
        // Сохраняем в опцию для истории (последние 100 ошибок)
        $this->save_error_to_history($log_entry);
    }
    
    /**
     * Сохранение ошибки в историю
     * 
     * @param array<string, mixed> $log_entry Запись лога
     * @return void
     */
    private function save_error_to_history(array $log_entry): void {
        $history = get_option('yfgp_error_history', array());
        
        $history[] = $log_entry;
        
        // Оставляем только последние 100 ошибок
        if (count($history) > 100) {
            $history = array_slice($history, -100);
        }
        
        update_option('yfgp_error_history', $history);
    }
    
    /**
     * Получить тип ошибки по коду
     * 
     * @param int $errno Код ошибки
     * @return string Тип ошибки
     */
    private function get_error_type(int $errno): string {
        $types = array(
            E_ERROR => 'Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated'
        );
        
        return $types[$errno] ?? 'Unknown';
    }
    
    /**
     * Обработка AJAX ошибки с унифицированным ответом
     * 
     * @param string|Exception|Throwable $error Ошибка (строка или исключение)
     * @param array<string, mixed> $context Дополнительный контекст
     * @return void
     */
    public function handle_ajax_error($error, array $context = array()): void {
        $message = '';
        $error_data = array();
        
        if ($error instanceof Exception || $error instanceof Throwable) {
            $message = $error->getMessage();
            $error_data = array(
                'file' => basename($error->getFile()),
                'line' => $error->getLine(),
                'trace' => defined('WP_DEBUG') && WP_DEBUG ? $error->getTraceAsString() : null
            );
        } else {
            $message = (string) $error;
        }
        
        // Логируем ошибку
        $this->log_error('ajax_error', $message, array_merge($context, $error_data));
        
        // v4.18.8: Регистрируем критичную ошибку для отображения в admin_notices
        // Определяем тип ошибки: если это системная ошибка (не валидация) - это критично
        $is_critical = !isset($context['action']) || 
                       strpos($message, 'Invalid nonce') === false && 
                       strpos($message, 'Insufficient permissions') === false;
        
        if ($is_critical) {
            $this->register_critical_error($message, 'error', array_merge($context, $error_data));
        }
        
        // Отправляем унифицированный ответ
        wp_send_json_error(array(
            'message' => $message,
            'context' => $context,
            'error_id' => uniqid('yfgp_', true)
        ));
    }
    
    /**
     * Обработка AJAX успеха с унифицированным ответом
     * 
     * @param mixed $data Данные для ответа
     * @param string $message Сообщение об успехе
     * @return void
     */
    public function handle_ajax_success($data = null, string $message = ''): void {
        $response = array();
        
        if ($data !== null) {
            $response = is_array($data) ? $data : array('data' => $data);
        }
        
        if (!empty($message)) {
            $response['message'] = $message;
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Обработка ошибки в cron задаче
     * 
     * @param string|Exception|Throwable $error Ошибка
     * @param string $cron_hook Название cron хука
     * @return void
     */
    public function handle_cron_error($error, string $cron_hook = ''): void {
        $message = '';
        
        if ($error instanceof Exception || $error instanceof Throwable) {
            $message = $error->getMessage();
        } else {
            $message = (string) $error;
        }
        
        $context = array(
            'cron_hook' => $cron_hook,
            'timestamp' => current_time('mysql')
        );
        
        $this->log_error('cron_error', $message, $context);
        
        // Можно добавить отправку email уведомления
        if (function_exists('wp_mail')) {
            $this->send_error_notification($message, $context);
        }
    }
    
    /**
     * Отправка уведомления об ошибке
     * 
     * @param string $message Сообщение об ошибке
     * @param array<string, mixed> $context Контекст
     * @return void
     */
    private function send_error_notification(string $message, array $context = array()): void {
        $settings = get_option('yfgp_settings', array());
        
        // Проверяем, включены ли уведомления об ошибках
        if (empty($settings['error_notifications']) || empty($settings['error_notification_email'])) {
            return;
        }
        
        $to = $settings['error_notification_email'];
        $subject = sprintf('[%s] YFGP Error: %s', get_bloginfo('name'), substr($message, 0, 50));
        
        $body = "Произошла ошибка в Yandex Feed Generator Pro:\n\n";
        $body .= "Сообщение: {$message}\n\n";
        
        if (!empty($context)) {
            $body .= "Контекст:\n";
            foreach ($context as $key => $value) {
                $body .= "  {$key}: " . (is_array($value) ? wp_json_encode($value) : $value) . "\n";
            }
        }
        
        $body .= "\n---\n";
        $body .= "Это автоматическое уведомление от Yandex Feed Generator Pro";
        
        wp_mail($to, $subject, $body);
    }
    
    /**
     * Получить историю ошибок
     * 
     * @param int $limit Лимит записей
     * @return array<string, mixed> История ошибок
     */
    public function get_error_history(int $limit = 50): array {
        $history = get_option('yfgp_error_history', array());
        
        if ($limit > 0 && count($history) > $limit) {
            $history = array_slice($history, -$limit);
        }
        
        return $history;
    }
    
    /**
     * Очистить историю ошибок
     * 
     * @return bool
     */
    public function clear_error_history(): bool {
        return delete_option('yfgp_error_history');
    }
    
    /**
     * Получить лог ошибок текущего запроса
     * 
     * @return array<string, mixed>
     */
    public function get_current_log(): array {
        return $this->error_log;
    }
    
    /**
     * Обёртка для выполнения кода с обработкой ошибок
     * 
     * @param callable $callback Функция для выполнения
     * @param array<string, mixed> $context Контекст для логирования
     * @return mixed Результат выполнения или null при ошибке
     */
    public function execute_safely(callable $callback, array $context = array()) {
        try {
            return call_user_func($callback);
        } catch (Exception $e) {
            $this->log_error('execution_error', $e->getMessage(), array_merge($context, array(
                'file' => $e->getFile(),
                'line' => $e->getLine()
            )));
            return null;
        } catch (Throwable $e) {
            $this->log_error('execution_error', $e->getMessage(), array_merge($context, array(
                'file' => $e->getFile(),
                'line' => $e->getLine()
            )));
            return null;
        }
    }
    
    /**
     * Регистрация критичной ошибки для отображения в admin_notices
     * 
     * @since 4.18.8
     * @param string $message Сообщение об ошибке
     * @param string $type Тип ошибки (error, warning, info)
     * @param array<string, mixed> $context Дополнительный контекст
     * @return void
     */
    public function register_critical_error(string $message, string $type = 'error', array $context = array()): void {
        // Логируем ошибку
        $this->log_error('critical_' . $type, $message, $context);
        
        // Сохраняем в transient для отображения в admin_notices
        $critical_errors = get_transient('yfgp_critical_errors');
        if (!is_array($critical_errors)) {
            $critical_errors = array();
        }
        
        $error_entry = array(
            'message' => $message,
            'type' => $type,
            'context' => $context,
            'timestamp' => current_time('mysql'),
            'id' => uniqid('yfgp_crit_', true)
        );
        
        $critical_errors[] = $error_entry;
        
        // Оставляем только последние 10 критичных ошибок
        if (count($critical_errors) > 10) {
            $critical_errors = array_slice($critical_errors, -10);
        }
        
        // Сохраняем на 1 час (3600 секунд)
        set_transient('yfgp_critical_errors', $critical_errors, 3600);
    }
    
    /**
     * Отображение критичных ошибок через admin_notices
     * 
     * @since 4.18.8
     * @return void
     */
    public function show_critical_errors_notice(): void {
        // Показываем только на страницах плагина
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'yandex-feed') === false) {
            return;
        }
        
        $critical_errors = get_transient('yfgp_critical_errors');
        if (empty($critical_errors) || !is_array($critical_errors)) {
            return;
        }
        
        // Группируем ошибки по типу
        $errors_by_type = array(
            'error' => array(),
            'warning' => array(),
            'info' => array()
        );
        
        foreach ($critical_errors as $error) {
            $error_type = $error['type'] ?? 'error';
            if (isset($errors_by_type[$error_type])) {
                $errors_by_type[$error_type][] = $error;
            }
        }
        
        // Отображаем ошибки по приоритету: error > warning > info
        foreach (array('error', 'warning', 'info') as $type) {
            if (empty($errors_by_type[$type])) {
                continue;
            }
            
            $notice_class = 'notice-' . ($type === 'error' ? 'error' : ($type === 'warning' ? 'warning' : 'info'));
            $icon = $type === 'error' ? '❌' : ($type === 'warning' ? '⚠️' : 'ℹ️');
            
            echo '<div class="notice ' . esc_attr($notice_class) . ' is-dismissible">';
            echo '<p><strong>' . esc_html($icon) . ' Yandex Feed Generator: Критичная ошибка</strong></p>';
            echo '<ul style="margin-left: 20px;">';
            
            foreach ($errors_by_type[$type] as $error) {
                echo '<li>' . esc_html($error['message']);
                if (!empty($error['context']['action'])) {
                    echo ' <em>(' . esc_html($error['context']['action']) . ')</em>';
                }
                echo '</li>';
            }
            
            echo '</ul>';
            echo '</div>';
        }
        
        // Очищаем ошибки после отображения (пользователь их увидел)
        delete_transient('yfgp_critical_errors');
    }
}

/**
 * Вспомогательная функция для получения Error Handler
 * 
 * @return YFGP_Error_Handler
 */
function yfgp_error_handler() {
    return YFGP_Error_Handler::get_instance();
}

