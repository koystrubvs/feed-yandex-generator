# Git Pre-Commit Hook для PHPStan

**Версия:** 1.0  
**Дата:** 2025-11-04

## Установка:

1. Инициализировать git: `git init`
2. Скопировать hook: `cp tools/pre-commit-hook.sh .git/hooks/pre-commit`
3. Сделать исполняемым: `chmod +x .git/hooks/pre-commit`

## Что делает:

- Запускает PHPStan Level 8 перед каждым commit
- Блокирует commit если есть ошибки
- Проверяет только изменённые PHP файлы плагина

## Bypass:

```bash
git commit --no-verify -m "Emergency"
```

Подробная документация в плагине.
