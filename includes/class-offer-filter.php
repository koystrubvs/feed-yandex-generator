<?php
/**
 * Offer Filter - Фильтрация офферов по правилам
 * 
 * Поддерживаемые операторы:
 * - Сравнение: =, !=, >, <, >=, <=
 * - Списки: ∈ (входит), ∉ (не входит)
 * - Существование: empty, not_empty
 * - Трансформация: replace, default
 * 
 * Логика:
 * - Правила объединяются через AND
 * - Оффер ИСКЛЮЧАЕТСЯ если ВСЕ правила выполняются
 * 
 * Пример:
 * Исключить офферы где:
 * - Специальность врача входит в список "узи,рентген,массаж"
 * - И цена > 0
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Offer Filter Class
 */
class YFGP_Offer_Filter {
    
    /**
     * Правила фильтрации
     */
    private $rules = array();
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Получить singleton instance
     */
    public static function get_instance(): YFGP_Offer_Filter {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Конструктор
     */
    public function __construct() {
        // Загрузить правила из настроек
        $this->load_rules();
    }
    
    /**
     * Загрузить правила из настроек
     */
    private function load_rules(): void {
        $saved_rules = get_option('yfgp_offer_filters', array());
        
        if (is_array($saved_rules)) {
            $this->rules = $saved_rules;
        }
    }
    
    /**
     * Добавить правило фильтрации
     * 
     * @param string $field Поле (doctor.speciality, offer.price, etc.)
     * @param string $operator Оператор
     * @param mixed $value Значение для сравнения
     */
    public function add_filter_rule($field, $operator, $value) {
        $this->rules[] = array(
            'field' => $field,
            'operator' => $operator,
            'value' => $value
        );
    }
    
    /**
     * Удалить правило
     * 
     * @param int $rule_index Индекс правила
     */
    public function remove_filter_rule($rule_index) {
        if (isset($this->rules[$rule_index])) {
            unset($this->rules[$rule_index]);
            $this->rules = array_values($this->rules); // reindex
        }
    }
    
    /**
     * Получить все правила
     * 
     * @return array<string, mixed> Массив правил
     */
    public function get_active_rules(): array {
        return $this->rules;
    }
    
    /**
     * Сохранить правила в настройках
     */
    public function save_rules() {
        update_option('yfgp_offer_filters', $this->rules);
    }
    
    /**
     * Проверить оффер по всем правилам
     * 
     * @param array<string, mixed> $offer_data Данные оффера
     * @return bool True если оффер ВКЛЮЧИТЬ, False если ИСКЛЮЧИТЬ
     */
    public function check_offer($offer_data) {
        if (empty($this->rules)) {
            // Нет правил - включить оффер
            return true;
        }
        
        // Проверить каждое правило
        foreach ($this->rules as $rule) {
            // Получить значение поля
            $field_value = $this->get_field_value_from_offer($offer_data, $rule['field']);
            
            // Применить оператор
            $rule_matches = $this->apply_operator($field_value, $rule['operator'], $rule['value']);
            
            // Если правило НЕ выполнено - включить оффер
            if (!$rule_matches) {
                return true;
            }
        }
        
        // Все правила выполнены - ИСКЛЮЧИТЬ оффер
        return false;
    }
    
    /**
     * Получить значение поля из данных оффера
     * 
     * @param array<string, mixed> $offer_data Данные оффера
     * @param string $field_path Путь к полю (doctor.speciality, offer.price)
     * @return mixed Значение поля
     */
    private function get_field_value_from_offer($offer_data, $field_path) {
        // Разбить путь на части
        $parts = explode('.', $field_path);
        
        if (count($parts) === 1) {
            // Прямое поле оффера
            return isset($offer_data[$field_path]) ? $offer_data[$field_path] : null;
        }
        
        // Вложенное поле (doctor.speciality)
        $source = $parts[0]; // doctor, clinic, service, offer
        $field_name = $parts[1];
        
        if ($source === 'offer') {
            return isset($offer_data[$field_name]) ? $offer_data[$field_name] : null;
        }
        
        // Получить от doctor/clinic/service
        $source_key = $source . '_data';
        
        if (isset($offer_data[$source_key][$field_name])) {
            return $offer_data[$source_key][$field_name];
        }
        
        return null;
    }
    
    /**
     * Применить оператор к значению
     * 
     * @param mixed $value Значение поля
     * @param string $operator Оператор
     * @param mixed $compare_value Значение для сравнения
     * @return bool True если условие выполнено
     */
    public function apply_operator($value, $operator, $compare_value) {
        switch ($operator) {
            // === СРАВНЕНИЕ ===
            case '=':
                return $value == $compare_value;
            
            case '!=':
                return $value != $compare_value;
            
            case '>':
                return is_numeric($value) && is_numeric($compare_value) && $value > $compare_value;
            
            case '<':
                return is_numeric($value) && is_numeric($compare_value) && $value < $compare_value;
            
            case '>=':
                return is_numeric($value) && is_numeric($compare_value) && $value >= $compare_value;
            
            case '<=':
                return is_numeric($value) && is_numeric($compare_value) && $value <= $compare_value;
            
            // === СПИСКИ ===
            case '∈': // входит в список
                $list = array_map('trim', explode(',', $compare_value));
                return in_array($value, $list);
            
            case '∉': // не входит в список
                $list = array_map('trim', explode(',', $compare_value));
                return !in_array($value, $list);
            
            // === СУЩЕСТВОВАНИЕ ===
            case 'empty':
                return empty($value);
            
            case 'not_empty':
                return !empty($value);
            
            default:
                return false;
        }
    }
    
    /**
     * Фильтровать массив офферов
     * 
     * @param array<string, mixed> $offers Массив офферов
     * @return array<string, mixed> Отфильтрованный массив
     */
    public function filter_offers($offers) {
        if (empty($offers) || !is_array($offers)) {
            return $offers;
        }
        
        if (empty($this->rules)) {
            return $offers;
        }
        
        $filtered = array();
        
        foreach ($offers as $offer) {
            if ($this->check_offer($offer)) {
                $filtered[] = $offer;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Получить статистику фильтрации
     * 
     * @param array<string, mixed> $offers_before Офферы до фильтрации
     * @param array<string, mixed> $offers_after Офферы после фильтрации
     * @return array<string, mixed> Статистика
     */
    public function get_filter_stats($offers_before, $offers_after) {
        $total_before = count($offers_before);
        $total_after = count($offers_after);
        $excluded = $total_before - $total_after;
        $percentage = $total_before > 0 ? round(($excluded / $total_before) * 100, 2) : 0;
        
        return array(
            'total_before' => $total_before,
            'total_after' => $total_after,
            'excluded' => $excluded,
            'percentage' => $percentage,
            'rules_count' => count($this->rules)
        );
    }
}

/**
 * Глобальная функция для доступа к Offer Filter
 * 
 * @return YFGP_Offer_Filter
 */
function yfgp_offer_filter() {
    return YFGP_Offer_Filter::get_instance();
}
