# Yandex Feed Generator Pro - Main Documentation

> **Plugin version:** 4.18.49  
> **Last updated:** 2025-11-27  
> **Latest:** Удалён JetEngine fallback `wp_jet_rel_*` в unified mapper.  
> _Комментарий: `extractRelationshipBasic()` теперь использует только JetEngine API (`db->table()`), избавляясь от прямых `SHOW TABLES LIKE` запросов._ > **Purpose:** generate Yandex.Health (v2.0) YML feeds for WordPress sites  
> **v4.18.16:** JetEngine API унифицирован (`get_meta_fields_for_object`), добавлено кэширование полей, debug логи обёрнуты в `WP_DEBUG`  
> **Status:** Production Ready (unified mapping, ACF & JetEngine support, no hardcoded field names)

---

## References

- Yandex.Health YML spec: https://yandex.ru/support/webmaster/ru/search-appearance/doctors#yml
- Field matrix (81 fields, 4 entities): `../../memory-bank/YANDEX-SPEC-FIELDS-TABLE.md`
- Artifacts: `artifacts/doctors_v2_20251018.yml`, `scripts/generate_doctors_yml_V2_FORMAT.js`
- **Docker MCP:** управление Docker контейнерами через MCP (`list_containers`, `create_container`, `run_container`, `fetch_container_logs`, `stop_container`, `remove_container`). Репозиторий: https://github.com/ckreiling/mcp-server-docker
- **Vibe Check MCP:** обязательный наставник (`tools/vibe-check-mcp-server`). Конфиг в `C:\Users\Sergey\.cursor\mcp.json`: `"vibe-check": { "type": "stdio", "command": "node", "args": ["D:/feed/tools/vibe-check-mcp-server/build/index.js"], "env": {"MCP_TRANSPORT": "stdio"} }`. Вызовы `vibe_check` (10–20% шагов) и `vibe_learn` нужны после планирования и перед каждым крупным действием.
- **Ref MCP (https://ref.tools/):** используется для поиска внешней документации. Конфиг: `"Ref": { "type": "http", "url": "https://api.ref.tools/mcp?apiKey=<YOUR_API_KEY>" }`. Перед изменениями библиотек/API выполняем `ref_search_documentation` → `ref_read_url`; Context7 применяем только если Ref не дал ответ.
- **WordPress MCP:** установлен плагин `wordpress-mcp` + прокси `@automattic/mcp-wordpress-remote`. Конфиг MCP: `"wordpress-mcp": { "command": "npx", "args": ["-y", "@automattic/mcp-wordpress-remote@latest"], "env": { "WP_API_URL": "http://localhost:8000", "JWT_TOKEN": "<актуальный токен>" } }`. Используется для обращения к REST/MCP инструментам WordPress прямо из Cursor (CRUD по постам, пользователям, WooCommerce и т.д.).
- **Sentry MCP:** удалённый сервер `https://mcp.sentry.dev/mcp` (OAuth конфигурация). Позволяет просматривать ошибки, релизы, проекты и вызывать Seer для автоматического анализа. Используем в QA/REFLECT режимах, чтобы убедиться, что новые баги не появляются после правок.
- **Gemini MCP (Smart Tool Intelligence):** локальный сервер `tools/gemini-mcp-server-3` на базе Gemini 3 (чат, генерация/редактирование изображений, транскрипция аудио, анализ видео/изображений, выполнение кода). Конфиг `~/.cursor/mcp.json`: `"gemini-mcp": { "type": "stdio", "command": "node", "args": ["D:/feed/tools/gemini-mcp-server-3/gemini-server.js"], "env": { "GEMINI_API_KEY": "<SET_GEMINI_API_KEY>" } }`. Перед использованием выполните `npm install` в каталоге сервера и получите ключ в Google AI Studio.
- **Sentry SDK:** интегрирован `sentry/sentry` (v4.0+) для production мониторинга. Класс `YFGP_Sentry_Integration` (`includes/class-sentry-integration.php`) инициализируется на хуке `init`, интегрирован с `YFGP_Error_Handler`. Настройки в админке: `sentry_enabled`, `sentry_dsn`, `sentry_traces_sample_rate`. Документация: `SENTRY-INSTALLATION.md`.
- **GitHub (public release):** https://github.com/lutyi2856/feed-yandex-generator (`main`). Локальный `.git` живёт в `wp-content/plugins/yandex-feed-generator-pro-v2/`; синхронизируем production-структуру (исключаем `dev-artifacts/`, `vendor/`, `.phpunit.cache/`, backup/temp файлы по `.gitignore`). Источник правды — контейнер `wp` (порт 8000), собранный код пушим только после `Sync-Plugin-From-Container`.

---

## Overview

The plugin is a universal feed generator for Yandex.Health. It works with any WordPress installation, any custom post types, any ACF/JetEngine fields. All field names are configured through the admin UI; the code does not rely on project-specific keys.

Typical scenarios:

1. Export doctors/clinics/services/offers to Yandex.Health.
2. Map arbitrary ACF or JetEngine relationships/repeaters to required XML fields.
3. Serve large catalogues (hundreds of doctors) with caching and incremental loading.

---

## Architecture

| Component                             | Responsibility                                                                                                                                                                                                                                                                                                                                            |
| ------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `yandex-feed-generator-pro.php`       | bootstrap, cron hooks, ajax handlers, shared `save_feed_file()` (temp file + rename, writes metadata)                                                                                                                                                                                                                                                     |
| `class-field-mapper-unified.php`      | singleton mapper (v4.18.x), `skip_cache` parameter REAL-TIME UI, checkbox conditional logic для `adult/children appointments`                                                                                                                                                                                                                             |
| `class-feed-generator-v2.php`         | orchestrator — координирует работу специализированных сервисов, управляет пакетной загрузкой постов (v4.18.0+)                                                                                                                                                                                                                                            |
| `class-entity-collector.php`          | сбор сущностей (doctors, clinics, services) из постов (v4.18.0+)                                                                                                                                                                                                                                                                                          |
| `class-offer-builder.php`             | построение offers из данных врача, клиник и услуг (v4.18.0+)                                                                                                                                                                                                                                                                                              |
| `class-xml-serialization-service.php` | сериализация сущностей в XML (build_doctor_entity, build_clinic_entity, build_service_entity) (v4.18.20+). v4.18.21: добавлена логика извлечения discount_name и free_appointment_condition из prices CPT. v4.18.22: исправлена логика взаимо-вычисления career_start_date и experience_years (сохранение исходной даты из БД перед конвертацией в годы). |
| `class-feed-xml-writer.php`           | streaming XML writer using `php://temp` stream (v4.18.0+)                                                                                                                                                                                                                                                                                                 |
| `trait-feed-generator-shared.php`     | shared helper methods (normalize_boolean_string, normalize_speciality_value, escape_xml, etc.) (v4.18.0+)                                                                                                                                                                                                                                                 |
| `class-field-mapper-v2.php`           | adapter for V2 UI, proxies to unified mapper                                                                                                                                                                                                                                                                                                              |
| `class-admin-page.php`                | admin page, manual generation, notices, CPT indicator                                                                                                                                                                                                                                                                                                     |
| `field-mapping-v3.php` + JS           | UI (4 tabs, 81 fields), AJAX selectors                                                                                                                                                                                                                                                                                                                    |

**Architecture (v4.18.0+):** Orchestrator pattern — `YFGP_Feed_Generator_V2` делегирует работу специализированным сервисам:

- `YFGP_Entity_Collector` — сбор сущностей из постов
- `YFGP_Xml_Serialization_Service` — сериализация сущностей в XML (v4.18.20+)
- `YFGP_Offer_Builder` — построение offers
- `YFGP_Yml_Stream_Writer` — потоковая запись XML

Data flow: admin saves mapping → cron/manual generation вызывает `generate()` → пакетная загрузка постов через `WP_Query` (v4.18.0+) → `EntityCollector::collect_entities()` → `OfferBuilder::build_offers()` → streaming XML generation через `YFGP_Yml_Stream_Writer` → `save_feed_file()` пишет во `wp-content/uploads/feed/` и обновляет `yfgp_feed_last_generated` (`generated_at`, `mtime`, `bytes`). Ручная генерация в `render_generate_page()` также использует `save_feed_file()` (v4.18.1).

**Performance improvements (v4.18.0+):**

- Пакетная загрузка постов: `WP_Query` с настраиваемым `generation_batch_size` (default: 50) вместо `get_posts(..., -1)`
- Streaming XML writer: использование `php://temp` stream для записи XML без накопления в памяти
- Решена проблема memory leaks при генерации больших фидов (1000+ постов)
- Декомпозиция генератора: разделение ответственности (SRP) через отдельные сервисы (EntityCollector, OfferBuilder)

**Development workflow (2025-11-17):**

- **File editing with Cyrillic/large content:** Используйте встроенные инструменты Cursor (`search_replace`, `write`) вместо PowerShell команд через `run_terminal_cmd` для файлов с кириллицей или большими объёмами текста. Встроенные инструменты надёжнее обрабатывают UTF-8 и не имеют ограничений на длину команд (см. [CLAUDE.md](../../CLAUDE.md#file-editing-with-cyrilliclarge-content))

## Data sources

- **[memory-bank/db/acf-jetengine-data-map.md](../../memory-bank/db/acf-jetengine-data-map.md)** — карта JetEngine CPT, таксономий, relations, glossaries и ACF групп из актуальной выгрузки для настройки маппинга.

---

## 2025-11-19 updates (v4.18.17)

- **Clinics mapping:** `extract_post_data_v3()` теперь поднимает все `clinics_*` поля из соответствующего CPT (address/phone/email/picture/company_id/internal_id). Если у клиники нет ни thumbnail, ни поля `clinics_picture`, `build_clinic_entity()` делает fallback на `yfgp_settings['shop_picture']` и далее на `get_site_icon_url()`.
- **Service prices:** сервисы получают описание/картинку/внутренний ID и блок цен из связанного CPT `prices_*`. Алгоритм выбирает самую дешёвую запись, нормализует валюту и скидку, чтобы `<offer><price><base_price>` всегда заполнялся из реальных данных.
- **Offer flags only in `<offer>`:** поля `house_call`/`telemed` больше не выводятся внутри `<doctor>` — они наследуются в `OfferBuilder` и пишутся только на уровне `<offer>`, как требует спецификация Яндекс.Врачей.
- **XML writer sanitization:** stream writer (`class-feed-xml-writer.php`) теперь отбрасывает `house_call`/`telemed` на уровне `<doctor>`, исправляет `<reviews>` → `<review>`, убирает `<price>`/`<currency>` из `<service>`, использует `escape_xml()` для `<response>` (без CDATA); preview/test YML в админке использует тот же подход.
- **Discount/free_appointment fallback:** `<price>` теперь нормализует `free_appointment_condition` (strip Gutenberg-блоки/figure, вытаскивает caption/title/alt) и при отсутствии текста подставляет название акции, чтобы `<free_appointment>` никогда не пропадал.

## 2025-11-21 updates (v4.18.21)

- **XmlSerializationService price details extraction:** добавлена логика извлечения `discount_name` и `free_appointment_condition` из prices CPT в `build_service_entity_internal()`. Ранее эти поля извлекались только в `Feed_Generator_V2::build_service_entity()`, что приводило к их отсутствию в фиде при использовании `XmlSerializationService`. Добавлены методы: `extract_price_details_for_service_internal()`, `extract_field_value_unified()`, `extract_related_posts_unified()`, `normalize_price_value()`.
- **experience_years output logic:** изменена логика вывода `experience_years` - теперь не выводится если значение = 0 (ранее всегда выводился). Обновлен тест `test-doctor-price-fields.php` для соответствия новому поведению.
- **Settings page cleanup:** обработчик `handle_settings_save()` больше не сохраняет legacy поля `sets`, `sets_source`, `sets_taxonomy`, `sets_field`, `sets_url_template`. Эти поля отсутствуют в UI и не используются при генерации фида. См. `memory-bank/LEGACY-FIELDS-REMOVAL-REPORT.md` и обновлённый анализ `memory-bank/VAN-ANALYSIS-SETTINGS-PAGE.md`.

## 2025-11-24 updates (v4.18.22)

- **Automatic base service hardening:** `create_auto_base_service()` больше не генерирует технические описания и не проставляет «0 ₽». Название и description берутся из пользовательского поля «Название базовой услуги» (или карты специализаций), а цена появляется только если в `yfgp_settings['default_auto_service_price']` задано числовое значение. Это исключает "бесплатные" офферы и делает поведение полностью управляемым из настроек UI.
- **Offer price guards:** `class-offer-builder.php` и `class-feed-xml-writer.php` научились учитывать пустые значения (`''`, `null`, `'0'`). Если на услуги нет цены/скидки, блок `<price>` и атрибуты `currency/discount_name` вовсе не выводятся, благодаря чему smoke сравнение JSON ↔ YML проходит без диффов.
- **Fix encoding issues (cracked characters):** все кракозябры в `class-feed-generator-v2.php` и вспомогательных файлах исправлены, комментарии приведены к UTF-8 без BOM.

## 2025-11-27 updates (v4.18.49)

- **JetEngine relationship queries:** `includes/class-field-mapper-unified.php::extractRelationshipBasic()` лишился fallback'а `wp_jet_rel_*` + `SHOW TABLES LIKE`. Теперь имя таблицы берётся только через JetEngine API (`$relation->db->table()`), а при недоступности API блок SQL пропускается. Это убирает хардкод префикса, уменьшает количество прямых SQL и приводит поведение в соответствие с `findRelatedPostByCpt()`.

## 2025-11-26 updates (v4.18.37)

- **Quiet log system:** реализована централизованная система логирования в `dynamic-field-selector-v3.js` через объект `yfgpLog` (уровни debug/info/warn/error). Управление через query-параметр `?yfgp_debug` (0/1) с поддержкой session/local storage. В режиме `yfgp_debug=0` консоль тихая (только JQMIGRATE), при `yfgp_debug=1` выводятся диагностические логи.
- **Inline logs cleanup:** все `console.log` в `field-mapping-v3.php` заменены на `yfgpLog.info/debug`, что позволяет централизованно управлять выводом через `yfgpLog.isDebugEnabled`.
- **Fallback warnings elimination:** устранены предупреждения "sanitizeHtml function not defined" (функция вынесена в глобальную область) и "relationship section still not visible" (использование `requestAnimationFrame` для синхронизации DOM вместо `setTimeout`). Проверено через Playwright MCP: в тихом режиме консоль чистая, при включённом debug видны ожидаемые логи.

## 2025-11-25 updates (v4.18.22)

- **Single clinic mode (режим "одна клиника"):
