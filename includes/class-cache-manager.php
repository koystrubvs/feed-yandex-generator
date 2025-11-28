<?php
/**
 * Yandex Feed Generator Pro - Cache Manager
 * 
 * Менеджер кэширования с изолированными областями (UI, generation, validation) и автоматической инвалидацией
 * 
 * @package YandexFeedGeneratorPro
 * @since 4.18.17
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class YFGP_Cache_Manager {

    /**
     * @var YFGP_Cache_Manager|null Единственный экземпляр менеджера (singleton)
     */
    private static ?YFGP_Cache_Manager $instance = null;

    /**
     * @var array<string> Разрешённые области кэша
     */
    private const ALLOWED_SCOPES = array('ui', 'generation', 'validation');

    /**
     * @var int TTL по умолчанию (в секундах, 1 час)
     */
    private const DEFAULT_TTL = 3600;

    /**
     * Приватный конструктор (singleton)
     */
    private function __construct() {
        // Регистрация хука для автоматической инвалидации при изменении настроек
        add_action('updated_option', array($this, 'invalidateWhenSettingsChange'), 10, 3);
    }

    /**
     * Получить единственный экземпляр менеджера
     * 
     * @return YFGP_Cache_Manager
     */
    public static function get_instance(): YFGP_Cache_Manager {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Получить значение из кэша
     * 
     * @param string $scope Область кэша ('ui', 'generation', 'validation')
     * @param string $key Ключ кэша
     * @return mixed Значение из кэша или null если не найдено
     */
    public function get(string $scope, string $key) {
        $this->validateScope($scope);
        
        $transient_key = $this->getTransientKey($scope, $key);
        $value = get_transient($transient_key);
        
        // WordPress get_transient возвращает false если transient не существует или истёк
        return ($value !== false) ? $value : null;
    }

    /**
     * Сохранить значение в кэш
     * 
     * @param string $scope Область кэша ('ui', 'generation', 'validation')
     * @param string $key Ключ кэша
     * @param mixed $value Значение для кэширования
     * @param int $ttl TTL в секундах (по умолчанию 1 час)
     * @return bool Успешность сохранения
     */
    public function set(string $scope, string $key, $value, int $ttl = self::DEFAULT_TTL): bool {
        $this->validateScope($scope);
        
        if ($ttl <= 0) {
            $ttl = self::DEFAULT_TTL;
        }
        
        $transient_key = $this->getTransientKey($scope, $key);
        $result = set_transient($transient_key, $value, $ttl);
        
        // Отслеживаем ключ для возможности инвалидации
        if ($result) {
            $cache_keys_option = 'yfgp_cache_keys_' . $scope;
            $keys = get_option($cache_keys_option, array());
            if (!is_array($keys)) {
                $keys = array();
            }
            if (!in_array($key, $keys, true)) {
                $keys[] = $key;
                update_option($cache_keys_option, $keys, false);
            }
        }
        
        return $result;
    }

    /**
     * Инвалидировать кэш (очистить)
     * 
     * @param string|null $scope Область кэша для очистки (null = все области)
     * @return void
     */
    public function invalidate(?string $scope = null): void {
        if ($scope === null) {
            // Очищаем все области
            foreach (self::ALLOWED_SCOPES as $scope_item) {
                $this->invalidateScope($scope_item);
            }
        } else {
            $this->validateScope($scope);
            $this->invalidateScope($scope);
        }
    }

    /**
     * Инвалидировать конкретную область кэша
     * 
     * @param string $scope Область кэша
     * @return void
     */
    private function invalidateScope(string $scope): void {
        // WordPress не предоставляет прямого способа удалить все transients с префиксом
        // Используем опцию для хранения списка ключей кэша
        $cache_keys_option = 'yfgp_cache_keys_' . $scope;
        $keys = get_option($cache_keys_option, array());
        
        if (is_array($keys)) {
            foreach ($keys as $key) {
                $transient_key = $this->getTransientKey($scope, $key);
                delete_transient($transient_key);
            }
            // Очищаем список ключей
            delete_option($cache_keys_option);
        }
    }

    /**
     * Автоматическая инвалидация при изменении настроек плагина
     * 
     * @param string $option_name Имя опции
     * @param mixed $old_value Старое значение
     * @param mixed $value Новое значение
     * @return void
     */
    public function invalidateWhenSettingsChange(string $option_name, $old_value, $value): void {
        // Инвалидируем кэш при изменении настроек плагина
        if ($option_name === 'yfgp_settings' || $option_name === 'yfgp_field_mapping_v3') {
            // Очищаем все области кэша
            $this->invalidate();
        }
    }

    /**
     * Получить ключ transient для области и ключа
     * 
     * @param string $scope Область кэша
     * @param string $key Ключ кэша
     * @return string Ключ transient
     */
    private function getTransientKey(string $scope, string $key): string {
        // WordPress transient keys ограничены 172 символами
        // Используем md5 для длинных ключей
        $hashed_key = strlen($key) > 100 ? md5($key) : $key;
        return 'yfgp_cache_' . $scope . '_' . $hashed_key;
    }

    /**
     * Валидация области кэша
     * 
     * @param string $scope Область кэша
     * @return void
     * @throws \InvalidArgumentException Если область недопустима
     */
    private function validateScope(string $scope): void {
        if (!in_array($scope, self::ALLOWED_SCOPES, true)) {
            throw new \InvalidArgumentException("Invalid cache scope: '{$scope}'. Allowed: " . implode(', ', self::ALLOWED_SCOPES));
        }
    }

    /**
     * Сбросить singleton (для тестирования)
     * 
     * @return void
     */
    public static function reset(): void {
        self::$instance = null;
    }
}

