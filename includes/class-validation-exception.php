<?php
/**
 * Yandex Feed Generator Pro - Validation Exception
 * 
 * Исключение для критических ошибок валидации
 * 
 * @package YandexFeedGeneratorPro
 * @since 4.18.17
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once YFGP_PLUGIN_DIR . 'includes/class-validation-result.php';

class YFGP_Validation_Exception extends Exception {

    /**
     * @var YFGP_Validation_Result Результат валидации с ошибками
     */
    private YFGP_Validation_Result $validation_result;

    /**
     * Конструктор
     * 
     * @param YFGP_Validation_Result $validation_result Результат валидации
     * @param string $message Сообщение об ошибке
     * @param int $code Код ошибки
     * @param Throwable|null $previous Предыдущее исключение
     */
    public function __construct(
        YFGP_Validation_Result $validation_result,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        if (empty($message)) {
            $errors = $validation_result->getCriticalErrors();
            $message = !empty($errors) ? implode('; ', $errors) : 'Validation failed';
        }
        
        parent::__construct($message, $code, $previous);
        $this->validation_result = $validation_result;
    }

    /**
     * Получить результат валидации
     * 
     * @return YFGP_Validation_Result
     */
    public function getValidationResult(): YFGP_Validation_Result {
        return $this->validation_result;
    }
}

