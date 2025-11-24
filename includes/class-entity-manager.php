<?php
/**
 * Entity Manager - управление маппингом CPT → entity_type с поддержкой расширяемости
 * 
 * @package YandexFeedGeneratorPro
 * @since 4.18.11
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Менеджер сущностей для расширяемости плагина
 * 
 * Позволяет регистрировать новые CPT → entity_type маппинги через фильтры WordPress
 * 
 * @since 4.18.11
 */
class YFGP_Entity_Manager {
    
    /**
     * @var YFGP_Entity_Manager|null Singleton instance
     */
    private static ?YFGP_Entity_Manager $instance = null;
    
    /**
     * @var array<string, string> Маппинг post_type → entity_type
     */
    private array $providers = array();
    
    /**
     * @var bool Флаг инициализации
     */
    private bool $initialized = false;
    
    /**
     * Получить singleton instance
     * 
     * @return YFGP_Entity_Manager
     */
    public static function get_instance(): YFGP_Entity_Manager {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Инициализация отложена до первого вызова get_entity_type()
    }
    
    /**
     * Инициализация менеджера (загрузка дефолтных провайдеров и фильтров)
     * 
     * @param array<string, mixed> $settings Настройки плагина
     * @return void
     */
    private function initialize(array $settings = array()): void {
        if ($this->initialized) {
            return;
        }
        
        // Загружаем настройки если не переданы
        if (empty($settings)) {
            $settings = get_option('yfgp_settings', array());
        }
        
        // Дефолтные провайдеры из настроек
        if (!empty($settings['cpt_clinics'])) {
            $this->providers[$settings['cpt_clinics']] = 'clinic';
        }
        
        if (!empty($settings['cpt_services'])) {
            $this->providers[$settings['cpt_services']] = 'service';
        }
        
        // Дефолтный провайдер для всех остальных CPT → 'doctor'
        // (не регистрируем явно, используется как fallback)
        
        // v4.18.11: Фильтр для регистрации дополнительных провайдеров
        /**
         * Фильтр для регистрации дополнительных провайдеров entity_type
         * 
         * @param array<string, string> $providers Текущие провайдеры (post_type => entity_type)
         * @param array<string, mixed> $settings Настройки плагина
         * @return array<string, string> Обновлённые провайдеры
         * 
         * @example
         * add_filter('yfgp_register_entity_providers', function($providers, $settings) {
         *     $providers['lawyers'] = 'doctor'; // Lawyers CPT → doctor entity
         *     $providers['nurses'] = 'doctor';  // Nurses CPT → doctor entity
         *     return $providers;
         * }, 10, 2);
         */
        $this->providers = apply_filters('yfgp_register_entity_providers', $this->providers, $settings);
        
        $this->initialized = true;
    }
    
    /**
     * Регистрация провайдера вручную (для внутреннего использования)
     * 
     * @param string $post_type Post type slug
     * @param string $entity_type Entity type ('doctor', 'clinic', 'service')
     * @return void
     */
    public function register_provider(string $post_type, string $entity_type): void {
        if (empty($post_type) || empty($entity_type)) {
            return;
        }
        
        $this->providers[$post_type] = $entity_type;
    }
    
    /**
     * Получить entity_type для post_type
     * 
     * @param string $post_type Post type slug
     * @param array<string, mixed> $settings Настройки плагина (опционально, для инициализации)
     * @return string Entity type: 'doctor', 'clinic', or 'service'
     * 
     * @example
     * $manager = YFGP_Entity_Manager::get_instance();
     * $entity_type = $manager->get_entity_type('lawyers'); // 'doctor' (fallback)
     * $entity_type = $manager->get_entity_type('clinics'); // 'clinic' (из настроек)
     */
    public function get_entity_type(string $post_type, array $settings = array()): string {
        // Инициализация при первом вызове
        if (!$this->initialized) {
            $this->initialize($settings);
        }
        
        // Проверяем зарегистрированные провайдеры
        if (isset($this->providers[$post_type])) {
            return $this->providers[$post_type];
        }
        
        // v4.18.11: Фильтр для динамического определения entity_type
        /**
         * Фильтр для динамического определения entity_type
         * 
         * @param string|null $entity_type Текущий entity_type (null если не найден)
         * @param string $post_type Post type slug
         * @return string|null Entity type или null для использования fallback
         * 
         * @example
         * add_filter('yfgp_get_entity_type', function($entity_type, $post_type) {
         *     if ($post_type === 'lawyers') {
         *         return 'doctor';
         *     }
         *     return $entity_type;
         * }, 10, 2);
         */
        $filtered_entity_type = apply_filters('yfgp_get_entity_type', null, $post_type);
        
        if ($filtered_entity_type !== null && is_string($filtered_entity_type)) {
            return $filtered_entity_type;
        }
        
        // Fallback: по умолчанию все CPT → 'doctor'
        return 'doctor';
    }
    
    /**
     * Получить все зарегистрированные провайдеры
     * 
     * @return array<string, string> Маппинг post_type => entity_type
     */
    public function get_providers(): array {
        if (!$this->initialized) {
            $this->initialize();
        }
        
        return $this->providers;
    }
}

