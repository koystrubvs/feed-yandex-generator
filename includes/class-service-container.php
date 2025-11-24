<?php
/**
 * Yandex Feed Generator Pro - Service Container
 * 
 * Контейнер зависимостей для управления сервисами плагина
 * 
 * @package YandexFeedGeneratorPro
 * @since 4.18.17
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class YFGP_Service_Container {

    /**
     * @var YFGP_Service_Container|null Единственный экземпляр контейнера (singleton)
     */
    private static ?YFGP_Service_Container $instance = null;

    /**
     * @var array<string, callable> Регистрированные фабрики сервисов
     */
    private array $factories = array();

    /**
     * @var array<string, object> Кэш созданных экземпляров сервисов
     */
    private array $instances = array();

    /**
     * Приватный конструктор (singleton)
     */
    private function __construct() {
    }

    /**
     * Получить единственный экземпляр контейнера
     * 
     * @return YFGP_Service_Container
     */
    public static function get_instance(): YFGP_Service_Container {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Зарегистрировать фабрику сервиса
     * 
     * @param string $name Имя сервиса
     * @param callable $factory Фабрика (callable, возвращающая экземпляр сервиса)
     * @return void
     */
    public function register(string $name, callable $factory): void {
        if (!is_callable($factory)) {
            throw new \InvalidArgumentException("Factory for service '{$name}' must be callable");
        }
        $this->factories[$name] = $factory;
        // Удаляем кэшированный экземпляр если он был создан ранее
        unset($this->instances[$name]);
    }

    /**
     * Получить экземпляр сервиса
     * 
     * @param string $name Имя сервиса
     * @return object Экземпляр сервиса
     * @throws \RuntimeException Если сервис не зарегистрирован
     */
    public function get(string $name): object {
        // Проверяем кэш
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        // Проверяем наличие фабрики
        if (!isset($this->factories[$name])) {
            throw new \RuntimeException("Service '{$name}' is not registered");
        }

        // Создаём экземпляр через фабрику
        $factory = $this->factories[$name];
        $instance = call_user_func($factory);

        if (!is_object($instance)) {
            throw new \RuntimeException("Factory for service '{$name}' must return an object");
        }

        // Кэшируем экземпляр
        $this->instances[$name] = $instance;

        return $instance;
    }

    /**
     * Проверить зарегистрирован ли сервис
     * 
     * @param string $name Имя сервиса
     * @return bool
     */
    public function has(string $name): bool {
        return isset($this->factories[$name]);
    }

    /**
     * Очистить кэш экземпляров (для тестирования)
     * 
     * @return void
     */
    public function clear_cache(): void {
        $this->instances = array();
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

