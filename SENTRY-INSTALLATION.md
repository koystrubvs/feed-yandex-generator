# Инструкция по установке Sentry SDK

> **Версия:** 4.18.21  
> **Дата:** 2025-11-24

## Требования

- PHP 7.2 или выше
- Composer установлен в системе
- Доступ к проекту в Sentry (DSN)

## Установка

### 1. Установка зависимостей через Composer

Перейдите в директорию плагина и выполните:

```bash
cd wp-content/plugins/yandex-feed-generator-pro-v2
composer require sentry/sentry
```

Или если Composer установлен глобально:

```bash
composer require sentry/sentry --working-dir=wp-content/plugins/yandex-feed-generator-pro-v2
```

### 2. Настройка в WordPress

1. Перейдите в **Настройки → Yandex Feed Generator**
2. Найдите секцию **"🔍 Sentry - Мониторинг ошибок"**
3. Включите **"Включить Sentry"**
4. Введите **Sentry DSN** (получить можно в настройках проекта Sentry: Settings → Client Keys (DSN))
5. Настройте **Sample Rate для трейсинга** (рекомендуется 0.1 для production)
6. Нажмите **"💾 Сохранить настройки"**

### 3. Получение DSN

1. Войдите в ваш проект Sentry: https://lut-3z.sentry.io
2. Перейдите в **Settings → Client Keys (DSN)**
3. Скопируйте DSN (формат: `https://[PUBLIC_KEY]@[HOST]/[PROJECT_ID]`)
4. Вставьте в поле "Sentry DSN" в настройках плагина

## Проверка работы

После настройки Sentry автоматически начнет отправлять ошибки и исключения в ваш проект Sentry.

Для проверки:

1. Включите WP_DEBUG в `wp-config.php` (если еще не включен)
2. Сгенерируйте тестовую ошибку (например, через админку плагина)
3. Проверьте проект в Sentry - ошибка должна появиться в Issues

## Отключение

Чтобы отключить Sentry:

1. Перейдите в **Настройки → Yandex Feed Generator**
2. Снимите галочку **"Включить Sentry"**
3. Нажмите **"💾 Сохранить настройки"**

## Дополнительная информация

- **Sentry Integration Class:** `includes/class-sentry-integration.php`
- **Error Handler Integration:** `includes/class-error-handler.php`
- **Документация Sentry PHP SDK:** https://docs.sentry.io/platforms/php/

## Troubleshooting

### Ошибка "Sentry SDK not found"

**Причина:** Зависимость `sentry/sentry` не установлена через Composer.

**Решение:** Выполните `composer require sentry/sentry` в директории плагина.

### Ошибки не отправляются в Sentry

**Проверьте:**
1. Sentry включен в настройках плагина
2. DSN указан корректно
3. Проект в Sentry активен
4. Проверьте логи WordPress (`wp-content/debug.log`)

### Composer не найден

**Решение:** Установите Composer глобально или используйте локальную установку:
- Windows: https://getcomposer.org/download/
- Linux/Mac: `curl -sS https://getcomposer.org/installer | php`


