# Yandex Feed Generator Pro - Main Documentation

> **Plugin version:** 4.18.21  
> **Last updated:** 2025-11-24  
> **Latest:** Исправление превью данных на странице маппинга (селект постов, `get_post_type_for_tab()`), Sentry SDK интеграция.  
> **Purpose:** generate Yandex.Health (v2.0) YML feeds for WordPress sites  
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

- **Automatic base service hardening:** `create_auto_base_service()` больше не генерирует технические описания и не проставляет «0 ₽». Название и description берутся из пользовательского поля «Название базовой услуги» (или карты специализаций), а цена появляется только если в `yfgp_settings['default_auto_service_price']` задано числовое значение. Это исключает “бесплатные” офферы и делает поведение полностью управляемым из настроек UI.
- **Offer price guards:** `class-offer-builder.php` и `class-feed-xml-writer.php` научились учитывать пустые значения (`''`, `null`, `'0'`). Если на услуги нет цены/скидки, блок `<price>` и атрибуты `currency/discount_name` вовсе не выводятся, благодаря чему smoke сравнение JSON ↔ YML проходит без диффов.
- **Fix encoding issues (cracked characters):** все кракозябры в `class-feed-generator-v2.php` и вспомогательных файлах исправлены, комментарии приведены к UTF-8 без BOM.

## 2025-11-20 updates (v4.18.20)

- **Entity Serialization Refactoring:** Методы `build_doctor_entity()`, `build_clinic_entity()`, `build_service_entity()` перенесены из `Feed_Generator_V2` в `XmlSerializationService`. Все вспомогательные методы (`extract_v3_fields()`, `extract_repeater_block()`, `get_reviews_source_config()`, и др.) также перенесены. Убрана зависимость от callbacks - методы работают напрямую. Это улучшает разделение ответственности (SRP) и делает код более поддерживаемым.

- **Description sanitizer:** `sanitize_feed_text()` удаляет `<script>/<style>`, CSS и HTML-сущности в `build_service_entity()` и `build_doctor_entity()`, поэтому длинные описания из постов больше не приносят CSS/JS в `<description>`.
- **Comparison tool:** `tests/manual/compare-feed-vs-db.php` (CLI) сравнивает `services->service->description` с данными WP, подсвечивает CSS/расхождения в `artifacts/*.txt`.
- **Test coverage:** `tests/Integration/ContentSanitizationTest.php` гарантирует, что `build_service_entity()` вычищает CSS/JS до сериализации YML.
- **Feed vs Export diff:** `tests/manual/export-entities.php` выгружает собранные doctors/clinics/services/offers в JSON (в т.ч. через `artifacts/lawyers-entities-*.json`), а `tests/manual/compare-feed-vs-export.php` сверяет JSON с реальным `lawyers.yml` (проверяет описания, gov_id, цены, скидки, appointment-флаги).
- **Free appointment chain:** `free_appointment_condition` теперь приводится к тексту на этапе `build_service_entity()` (через `normalize_free_appointment_text()` из shared-trait), поэтому JSON снапшоты, офферы и `<price><free_appointment>` в YML получают одинаковое значение (с fallback на alt/caption/discount_name).
- **Diff smoke:** `tests/manual/compare-feed-vs-export.php` сравнивает doctors/clinics/services/offers (включая образование/работу/сертификаты и ID-ссылки), а `tests/smoke/run-smoke.php` вызывает его в CI/QA (non-zero exit при расхождениях).
- **Spec cross-check:** `tests/manual/spec-assertions.php` валидирует ключевые требования Yandex.Health (обязательные поля, ISO валюты, discount<=base, корректные ссылки) и подключён к `tests/smoke/run-smoke.php`.
- **Clinics core smoke:** вкладка Clinics переключена на WP core meta (email/featured image) через `yfgp_field_mapping_v3`, снапшот `artifacts/lawyers-settings-ui-smoke-20251120-clinics-core.json`, feed пересобран `tests/manual/run-feed.php` и проверен smoke-скриптом.
- **Scenario-based smoke runner:** `tests/smoke/run-scenario.php` выполняет coverage-driven тесты (88 полей, 6 сценариев из `tests/smoke/coverage.json`), автоматически применяет снапшоты настроек/маппинга, запускает цепочку `run-feed.php → export-entities.php → run-smoke.php` и сохраняет артефакты в `artifacts/smoke/<scenario>/<timestamp>/`. Поддерживает фильтры (`--scenario`, `--only/--skip`), `--dry-run`, `--keep-going`. Helper `tests/manual/apply-snapshot.php` восстанавливает `yfgp_settings`/`yfgp_field_mapping_v3` из снапшотов, PowerShell `scripts/Reset-Smoke-Environment.ps1` управляет reset workflow (restore/clean artifacts/feed). См. `memory-bank/plans/ui-mapping-smoke-plan.md` и `tests/README.md`.

## 2025-11-18 updates (v4.18.16)

- **Structure report:** `PLUGIN-STRUCTURE-AND-CODE-REPORT.md` обновлён под текущую архитектуру (EntityCollector/OfferBuilder/Stream Writer, свежие размеры файлов) и служит быстрым обзором ядра перед правками.
- **White screen fix:** удалён BOM из `wp-config.php`, а создание `YFGP_Admin_Page` теперь происходит только при `class_exists` и обёрнуто в try/catch. Если в `wp-config.php` снова появится BOM, `wp-admin` отдаст пустой `<body>` — держите файл в UTF-8 без сигнатуры (см. `memory-bank/reflection/reflection-WHITE-SCREEN-FEED-GENERATION-PAGE.md`).

## 2025-11-17 updates (v4.18.1)

- **Feed generation page:** Исправлено отображение "Последнее обновление" - `render_generate_page()` использует `save_feed_file()` вместо `file_put_contents()` для сохранения метаданных в `yfgp_feed_last_generated`, добавлено чтение метаданных для отображения времени генерации.
- **Appointment flags (v4.18.3):** `class-field-mapper-unified.php` теперь разворачивает checkbox-массивы перед conditional logic, поэтому `adult_appointment`/`children_appointment` уважают оператор `=` и выводятся только в `<offer><clinic><doctor>`. **ВАЖНО:** Appointment flags (`adult_appointment`, `children_appointment`) НЕ должны быть на уровне `<doctor>` согласно спецификации Yandex.Health v2.0 - они должны быть только внутри `<offer><clinic><doctor>`.

## 2025-11-16 updates (v4.18.1)

- **Speciality array protection:** добавлена многоуровневая защита от массивов в speciality через метод `normalize_speciality_value()` (5 уровней защиты) для предотвращения `<speciality>Array</speciality>`. Покрыты все источники данных (ACF checkbox/select/text, JetEngine checkbox/select, taxonomy) и edge cases. См. `memory-bank/reflection/reflection-SPECIALITY-ARRAY-PROTECTION-2025-11-16.md`.
- **Shop picture chain:** `<shop><picture>` теперь берётся из `yfgp_field_mapping_v3['shop_picture']` с fallback на `yfgp_settings['shop_picture']` и `get_site_icon_url(512)`; поле обязательное и всегда присутствует в XML.

## 2025-11-16 updates (v4.18.2)

- **Cache skip parameter:** добавлен параметр `skip_cache` в `getFieldValue()` и `extractField()` для отключения кэша при генерации фида (точность данных), сохраняя кэш для AJAX/preview (производительность). См. `memory-bank/reflection/reflection-CACHE-SKIP-IMPLEMENTATION-2025-11-16.md`.
- **ID prefix fix:** добавлен метод `get_entity_type_for_post()` для универсального маппинга CPT → entity*type. Исправлен хардкод ID-префиксов - теперь ID соответствуют формату Yandex YML (`doctor_1` вместо `lawyers_123`). Исправлен хардкод `str_replace('clinic*', ...)`в`build_clinic_entity()`- универсальное удаление префикса через`get_entity_type_for_post()`. См. `memory-bank/reflection/reflection-STR-REPLACE-CLINIC-PREFIX-FIX-2025-11-16.md`.
- **Featured image priority fix:** в `build_doctor_entity()` и `build_clinic_entity()` добавлена проверка featured image с приоритетом над маппингом. Если есть featured image → используется он, иначе из маппинга (v3_fields['picture'] для врачей, clinic_data['picture'] для клиник).

## 2025-11-17 updates (v4.18.0)

- **Generator decomposition:** Монолитный класс `YFGP_Feed_Generator_V2` декомпозирован на отдельные сервисы:
  - `YFGP_Entity_Collector` — сбор сущностей (doctors, clinics, services) из постов
  - `YFGP_Offer_Builder` — построение offers из данных врача, клиник и услуг
  - `YFGP_Feed_Generator_V2` теперь выступает как orchestrator, координирующий работу всех сервисов
- **Dependency injection:** Новые сервисы используют callback'и для методов генератора (`determine_base_service`, `get_offer_additional_fields`, `build_doctor_entity`, etc.)
- **Deprecated methods:** Старые методы `collect_entities()` и `build_offers_v2()` помечены как `@deprecated v4.18.0`
- **Architecture improvement:** Решена проблема нарушения Single Responsibility Principle (SRP)

---

## 2025-11-16 updates (v4.18.0)

- **extract_v3_fields improvements:** обработка полей `degree`, `rank`, `category` теперь использует сервис `normalizeSpecialityField()` из Unified mapper (под капотом — `normalize_specialities()`), так что больше не нужен Reflection и прямой доступ к V2. Добавлена обработка serialized данных через `maybe_unserialize()` для корректной работы с ACF полями.
- **Exclusions fix:** исправлена логика `is_specialty_excluded()` для правильного использования `$mapping` и `$settings`. Exclusions теперь берутся из `wp_options.yfgp_settings`, а не из field mapping.
- **Repeaters fix:** исправлена обработка repeater полей (education, job, certificate) через правильное получение field keys из ACF API вместо использования маппинга напрямую.
- **Repeater fields hardcode fix (v4.18.3):** Убран хардкод ключей `education_repeater_field`, `job_repeater_field`, `certificate_repeater_field` в `build_doctor_entity()`. Добавлены методы `get_repeater_field_types()` и `get_repeater_field_key()` для динамического получения ключей из маппинга по паттерну `$type . '_repeater_field'`.
- **Appointment conditional logic:** исправлена логика условий для `adult_appointment` и `children_appointment` с правильной проверкой parent_child отношений.
- **Reviews relationship:** улучшена обработка reviews через relationship поля с корректным получением связанных постов.
- **Metadata exclusions:** исправлена обработка metadata через правильное использование источников данных (`$mapping` vs `$settings`).

**Related patterns:** Unified Field Processing Pattern, Serialized Data Handling Pattern, Mapping vs Settings Separation Pattern (см. `memory-bank/reflection/reflection-FEED-FIXES-IMPLEMENTATION.md`).

---

## 2025-11-14 updates (v4.18.0)

- **Reviews source mapping:** `class-feed-generator-v2.php` теперь использует `get_reviews_source_config()` и настройки `source_reviews`/`source_reviews_cpt`, убирая жёсткую привязку к `reviews_repeater_field` и сохраняя fallback.
- **Relationship support:** `build_doctor_entity()` передаёт нужный block key в `extract_repeater_block()`, поэтому отзывы подтягиваются из repeater или relationship без дополнительных правок.
- **Settings UX:** карточка "Режим определения базовой услуги" перенесена выше, прямо перед секцией "Источники данных для врачей", чтобы пользователям было проще находить глобальный режим.
- **Reviews dropdown fix:** `updateCPTDropdown()` в `admin/views/settings.php` сначала ищет сохранённый `cpt.selected`, и только при его отсутствии выбирает рекомендованный CPT.
- **Source status badge fix:** `admin/class-admin-page.php::ajax_get_available_cpts()` сохраняет `$saved_slug` и подставляет его в ответ (slug/count), если подходящего "рекомендованного" CPT нет. Благодаря этому бейдж "CPT найден" и подсказки в UI теперь отражают реальный выбор пользователя.

```javascript
var hasSavedSelection = cpts.some(function (cpt) {
  return !!cpt.selected;
});
var shouldSelect = !!cpt.selected || (!hasSavedSelection && cpt.recommended);
```

## 2025-11-13 updates (v4.18.0)

- **AJAX validation:** `yfgp_validate_feed` endpoint added; uses the same unified nonce (`yfgp_ajax`) and returns structural errors when present.
- **Dynamic feed naming:** manual edits (`ajax_save_edited_xml`) now honour the selected CPT, saving to `<post_type>.yml`.
- **Exclusions UI:** JetEngine/meta pools are exposed through Field Mapper V2, so “Произвольное поле (условие)” sees custom fields again.
- **Preview page UX:** all alerts replaced with `showNotice()`, and the header shows `Генерация для: <CPT>` via `updateCptIndicator()`.
- **Mirror sync:** `Sync-MirrorAndFeed.ps1` always passes `/XD yfgp-plugin` to `robocopy` to prevent recursive mirrors; see `scripts/README-sync.md`.

---

## Feed persistence & metadata

- `save_feed_file()` writes into a temporary file and then performs a rename. This keeps `mtime` fresh even on the Windows bind mount.
- The option `yfgp_feed_last_generated` is updated after every save (`generated_at`, `mtime`, `path`, `bytes`).
- Manual UI, AJAX and cron share the same "Last updated" widget (timestamp, file size, path).
- `wp-content/uploads/feed/doctors.yml` is rewritten atomically; on failure the old file remains untouched so we never ship a partial YML.

---

## Repository layout

```
yandex-feed-generator-pro-v2/
|- yandex-feed-generator-pro.php
|- admin/
|  |- class-admin-page.php
|  |- views/field-mapping-v3.php
|  \- assets/js/dynamic-field-selector-v3.js
|- includes/
|  |- class-field-mapper-unified.php
|  |- class-field-mapper-v2.php
|  |- class-feed-generator-v2.php
|  |- class-file-manager.php
|  |- class-cron-manager.php
|  \- helpers/
\- tests/
```

---

## Entity coverage

| Entity   | Coverage | Notes                                                              |
| -------- | -------- | ------------------------------------------------------------------ |
| Doctors  | 23/42    | mandatory fields + repeaters (education, experience, certificates) |
| Clinics  | 10/10    | full set including working hours, description, photo               |
| Services | 4/4      | name, price, description                                           |
| Offers   | 12/18    | doctor G- clinic G- specialisation (discount block pending)        |
| Reviews  | 12/12    | rating, author, comment, response, flags                           |

Fallback chain: repeater v-' single meta fields v-' text parsing.

---

## Mapping sources

- standard post fields (`post_title`, `post_content`, featured image)
- meta fields (no hardcoded keys)
- ACF Pro relationships, post objects, repeaters, booleans
- JetEngine relations, repeaters, meta
- taxonomies (speciality terms)
- calculated values (experience years, IDs, slugs)

Mapping is stored in `wp_options.yfgp_field_mapping_v3` (JSON). Settings and defaults live in `yfgp_settings`.

### Critical field: `specialities`

- В UI доступны три источника: таксономия, ACF meta, JetEngine meta.
- Для direct meta (checkbox/select) slug и label тянем из ACF choices или JetEngine options/глоссария; fallback срабатывает только если источники пусты.
- С ноября 2025 suffix-поиск пропускает «склеенные» кириллические ключи (`специализация_текстspecialty_text`), оставляя только пустые/ASCII префиксы (`doctor_specialty_text`).
- JetEngine map `slug => flag` и бинарные значения берутся напрямую из меты; количество `<offer>` зависит от числа specialities.
- JetEngine select возвращает slug, который транслируется в label через `specialities-reference.php` (например, `nevrolog` → `Невролог`).
- На карточках всегда контролируем fallback из `yfgp_settings`, если значение не назначено.
- При отсутствии данных срабатывает fallback из `yfgp_settings`, так что генерация не прерывается.
- Settings UI now exposes a **Default Speciality (fallback)** block: dropdown с 81 официальной специализацией Яндекса и чекбокс для произвольного текста. Пользовательское значение сохраняется как есть, slug собирается через `yfgp_specialities_slugify()` (ASCII-транслитерация) с резервом `sanitize_title()`, дефолтное состояние = лейбл `Специалист` → slug `specialist`.

### Relationship fields (nested_field)

- UI dropdown теперь восстанавливает сохранённое значение и, при его отсутствии, автоматически выбирает `post_id` → `ID` (см. `dynamic-field-selector-v3.js`).
- Mapper использует helper `getWordpressPostFieldValue()` → `getMetaValueFromPost()` для `relationship_1/2`, поэтому `post_title`/`permalink` возвращаются без дополнительных запросов.
- `
