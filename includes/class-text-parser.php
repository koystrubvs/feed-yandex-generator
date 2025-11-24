<?php
/**
 * Text Parser - Автоматический парсинг текста в repeater данные
 * 
 * Поддерживаемые форматы:
 * 1. CSV - разделители: запятая (поля), перенос строки (элементы)
 * 2. Structured - формат: "key: value", разделитель элементов: "---"
 * 
 * Примеры:
 * 
 * CSV:
 * Медицинский университет, 2010, специалитет, Лечебное дело
 * Институт хирургии, 2015, ординатура, Хирургия
 * "Университет, имени Сеченова", 2012, специалитет, Терапия
 * 
 * Structured:
 * organization: Медицинский университет
 * finish_year: 2010
 * type: специалитет
 * specialization: Лечебное дело
 * ---
 * organization: Институт хирургии
 * finish_year: 2015
 * type: ординатура
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Text Parser Class
 */
class YFGP_Text_Parser {
    
    /**
     * Последняя ошибка парсинга
     */
    private $last_error = null;
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Получить singleton instance
     */
    public static function get_instance(): YFGP_Text_Parser {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Парсить текст с автоопределением формата
     * 
     * @param string $text Входной текст
     * @param array<string, mixed> $schema Схема полей (опционально)
     * @return array|false Массив элементов или false при ошибке
     */
    public function parse($text, $schema = null) {
        // Очистить предыдущую ошибку
        $this->last_error = null;
        
        // Валидация
        if (empty($text) || !is_string($text)) {
            $this->last_error = 'Текст не может быть пустым';
            return false;
        }
        
        // Автоопределение формата
        $format = $this->auto_detect_format($text);
        
        // Парсинг по формату
        switch ($format) {
            case 'structured':
                $data = $this->parse_structured($text);
                break;
            
            case 'csv':
                $data = $this->parse_csv($text, $schema);
                break;
            
            default:
                $this->last_error = 'Не удалось определить формат текста';
                return false;
        }
        
        // Валидация результата
        if ($data === false) {
            return false;
        }
        
        // Валидация по схеме (если указана)
        if ($schema !== null) {
            $data = $this->validate_data($data, $schema);
        }
        
        return $data;
    }
    
    /**
     * Парсить CSV формат
     * 
     * @param string $text Входной текст
     * @param array|null $schema Схема полей (порядок полей)
     * @return array|false Массив элементов
     */
    public function parse_csv($text, $schema = null) {
        // Разбить на строки
        $lines = explode("\n", $text);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines); // удалить пустые
        
        if (empty($lines)) {
            $this->last_error = 'CSV: нет данных для парсинга';
            return false;
        }
        
        $data = array();
        
        foreach ($lines as $line_number => $line) {
            // Парсинг CSV строки (учитывая кавычки)
            $fields = str_getcsv($line);
            
            if (empty($fields)) {
                continue;
            }
            
            $row = array();
            
            // Если есть схема - использовать её для маппинга
            if ($schema !== null && is_array($schema)) {
                foreach ($schema as $index => $field_name) {
                    if (isset($fields[$index])) {
                        $row[$field_name] = trim($fields[$index]);
                    }
                }
            } else {
                // Нет схемы - использовать числовые индексы
                foreach ($fields as $index => $value) {
                    $row[$index] = trim($value);
                }
            }
            
            if (!empty($row)) {
                $data[] = $row;
            }
        }
        
        return $data;
    }
    
    /**
     * Парсить Structured формат
     * 
     * @param string $text Входной текст
     * @return array|false Массив элементов
     */
    public function parse_structured($text) {
        // Разбить на элементы (разделитель "---")
        $elements = explode('---', $text);
        $elements = array_map('trim', $elements);
        $elements = array_filter($elements);
        
        if (empty($elements)) {
            $this->last_error = 'Structured: нет данных для парсинга';
            return false;
        }
        
        $data = array();
        
        foreach ($elements as $element_text) {
            $row = $this->parse_structured_element($element_text);
            
            if ($row !== false && !empty($row)) {
                $data[] = $row;
            }
        }
        
        if (empty($data)) {
            $this->last_error = 'Structured: не удалось распарсить ни один элемент';
            return false;
        }
        
        return $data;
    }
    
    /**
     * Парсить один элемент structured формата
     * 
     * @param string $text Текст элемента
     * @return array|false Массив полей
     */
    private function parse_structured_element($text) {
        $lines = explode("\n", $text);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines);
        
        if (empty($lines)) {
            return false;
        }
        
        $row = array();
        
        foreach ($lines as $line) {
            // Формат: "key: value"
            if (strpos($line, ':') === false) {
                continue;
            }
            
            list($key, $value) = explode(':', $line, 2);
            
            $key = trim($key);
            $value = trim($value);
            
            if (!empty($key)) {
                $row[$key] = $value;
            }
        }
        
        return !empty($row) ? $row : false;
    }
    
    /**
     * Автоопределение формата текста
     * 
     * @param string $text Входной текст
     * @return string Формат (csv|structured)
     */
    public function auto_detect_format($text) {
        // Проверить на structured формат
        // Признаки: есть ":" и есть "---"
        if (strpos($text, ':') !== false && strpos($text, '---') !== false) {
            return 'structured';
        }
        
        // Проверить на structured без разделителя (один элемент)
        if (strpos($text, ':') !== false && substr_count($text, "\n") >= 2) {
            // Проверить что хотя бы 2 строки содержат ":"
            $lines = explode("\n", $text);
            $lines_with_colon = 0;
            
            foreach ($lines as $line) {
                if (strpos($line, ':') !== false) {
                    $lines_with_colon++;
                }
            }
            
            if ($lines_with_colon >= 2) {
                return 'structured';
            }
        }
        
        // По умолчанию - CSV
        return 'csv';
    }
    
    /**
     * Валидация парсированных данных
     * 
     * @param array<string, mixed> $data Парсированные данные
     * @param array<string, mixed> $schema Схема валидации
     * @return array<string, mixed> Валидированные данные
     */
    private function validate_data($data, $schema) {
        if (!is_array($data) || empty($data)) {
            return $data;
        }
        
        $validated = array();
        
        foreach ($data as $row) {
            $validated_row = array();
            
            foreach ($schema as $field_name => $field_rules) {
                $value = isset($row[$field_name]) ? $row[$field_name] : '';
                
                // Проверка обязательности
                if (isset($field_rules['required']) && $field_rules['required'] && empty($value)) {
                    // Пропустить строку если обязательное поле пустое
                    continue 2;
                }
                
                // Валидация типа
                if (isset($field_rules['type'])) {
                    $value = $this->validate_type($value, $field_rules['type']);
                }
                
                $validated_row[$field_name] = $value;
            }
            
            if (!empty($validated_row)) {
                $validated[] = $validated_row;
            }
        }
        
        return $validated;
    }
    
    /**
     * Валидация типа значения
     * 
     * @param mixed $value Значение
     * @param string $type Тип (text|number|year|url|email)
     * @return mixed Валидированное значение
     */
    private function validate_type($value, $type) {
        switch ($type) {
            case 'number':
                return is_numeric($value) ? intval($value) : 0;
            
            case 'year':
                $year = intval($value);
                return ($year >= 1900 && $year <= 2100) ? $year : null;
            
            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
            
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
            
            case 'text':
            default:
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Получить последнюю ошибку
     * 
     * @return string|null Сообщение об ошибке
     */
    public function get_last_error() {
        return $this->last_error;
    }
    
    /**
     * Парсить education данные (специализированный метод)
     * 
     * @param string $text Входной текст
     * @return array|false Массив education элементов
     */
    public function parse_education($text) {
        $schema = array(
            'organization' => array('required' => true, 'type' => 'text'),
            'finish_year' => array('required' => true, 'type' => 'year'),
            'type' => array('required' => false, 'type' => 'text'),
            'specialization' => array('required' => false, 'type' => 'text'),
        );
        
        return $this->parse($text, $schema);
    }
    
    /**
     * Парсить jobs данные (специализированный метод)
     * 
     * @param string $text Входной текст
     * @return array|false Массив jobs элементов
     */
    public function parse_jobs($text) {
        $schema = array(
            'organization' => array('required' => true, 'type' => 'text'),
            'period_years' => array('required' => false, 'type' => 'text'),
            'position' => array('required' => false, 'type' => 'text'),
        );
        
        return $this->parse($text, $schema);
    }
    
    /**
     * Парсить certificates данные (специализированный метод)
     * 
     * @param string $text Входной текст
     * @return array|false Массив certificates элементов
     */
    public function parse_certificates($text) {
        $schema = array(
            'organization' => array('required' => true, 'type' => 'text'),
            'finish_year' => array('required' => true, 'type' => 'year'),
            'name' => array('required' => false, 'type' => 'text'),
        );
        
        return $this->parse($text, $schema);
    }
}

/**
 * Глобальная функция для доступа к Text Parser
 * 
 * @return YFGP_Text_Parser
 */
function yfgp_text_parser() {
    return YFGP_Text_Parser::get_instance();
}
