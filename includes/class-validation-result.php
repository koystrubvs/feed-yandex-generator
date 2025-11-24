<?php
/**
 * Yandex Feed Generator Pro - Validation Result
 * 
 * Результат валидации с уровнями ошибок (critical, warning)
 * 
 * @package YandexFeedGeneratorPro
 * @since 4.18.17
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class YFGP_Validation_Result {

    /**
     * @var bool Успешность валидации
     */
    private bool $is_valid;

    /**
     * @var array<string> Критические ошибки (блокируют генерацию)
     */
    private array $critical_errors = array();

    /**
     * @var array<string> Предупреждения (не блокируют генерацию)
     */
    private array $warnings = array();

    /**
     * Конструктор
     * 
     * @param bool $is_valid Успешность валидации
     * @param array<string> $critical_errors Критические ошибки
     * @param array<string> $warnings Предупреждения
     */
    public function __construct(bool $is_valid = true, array $critical_errors = array(), array $warnings = array()) {
        $this->is_valid = $is_valid;
        $this->critical_errors = $critical_errors;
        $this->warnings = $warnings;
    }

    /**
     * Проверка успешности валидации
     * 
     * @return bool
     */
    public function isValid(): bool {
        return $this->is_valid && empty($this->critical_errors);
    }

    /**
     * Получить критические ошибки
     * 
     * @return array<string>
     */
    public function getCriticalErrors(): array {
        return $this->critical_errors;
    }

    /**
     * Получить предупреждения
     * 
     * @return array<string>
     */
    public function getWarnings(): array {
        return $this->warnings;
    }

    /**
     * Добавить критическую ошибку
     * 
     * @param string $error Сообщение об ошибке
     * @return void
     */
    public function addCriticalError(string $error): void {
        $this->critical_errors[] = $error;
        $this->is_valid = false;
    }

    /**
     * Добавить предупреждение
     * 
     * @param string $warning Сообщение-предупреждение
     * @return void
     */
    public function addWarning(string $warning): void {
        $this->warnings[] = $warning;
    }

    /**
     * Объединить с другим результатом валидации
     * 
     * @param YFGP_Validation_Result $other Другой результат
     * @return void
     */
    public function merge(YFGP_Validation_Result $other): void {
        $this->critical_errors = array_merge($this->critical_errors, $other->getCriticalErrors());
        $this->warnings = array_merge($this->warnings, $other->getWarnings());
        if (!$other->isValid()) {
            $this->is_valid = false;
        }
    }

    /**
     * Получить все сообщения (ошибки + предупреждения)
     * 
     * @return array<string>
     */
    public function getAllMessages(): array {
        return array_merge($this->critical_errors, $this->warnings);
    }
}

