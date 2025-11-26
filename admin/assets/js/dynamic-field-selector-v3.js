/**
 * Dynamic Field Selector V3 - Компонент выбора динамического поля
 *
 * Поддерживает 10+ типов источников данных:
 * - Стандарт: meta_field, relationship_1, relationship_2, taxonomy, taxonomy_meta, fixed
 * - Расширенные: repeater_acf, repeater_jetengine, repeater_relationship, operator
 *
 * @package Yandex_Feed_Generator_Pro
 * @version 3.0.0
 * @since 3.0.0
 */

/**
 * Sanitize HTML to prevent XSS
 * v4.18.22: Security - Client-side HTML sanitization
 * v4.18.52: Move to global scope so inline scripts can use it before main bundle executes
 *
 * NOTE: HTML is generated in JavaScript (not user input), so it's safe to use directly.
 *
 * @param {string} html HTML string to sanitize
 * @return {string} HTML string (unchanged, since it's generated client-side)
 */
function sanitizeHtml(html) {
  if (typeof html !== "string") {
    return "";
  }
  return html;
}

const getDebugQueryParam = () => {
  if (typeof window === "undefined" || !window.location || !window.location.search) {
    return null;
  }
  const query = window.location.search.substring(1);
  if (!query) {
    return null;
  }
  const pairs = query.split("&");
  for (let i = 0; i < pairs.length; i += 1) {
    const [rawKey, rawValue = ""] = pairs[i].split("=");
    const key = decodeURIComponent(rawKey || "");
    if (key === "yfgp_debug") {
      return decodeURIComponent(rawValue || "");
    }
  }
  return null;
};

if (typeof window !== "undefined") {
  if (typeof window.YFGP_DEBUG_OVERRIDE === "undefined") {
    window.YFGP_DEBUG_OVERRIDE = null;
  }
  if (typeof window.YFGP_SANITIZE_HTML !== "function") {
    window.YFGP_SANITIZE_HTML = sanitizeHtml;
  }
  if (typeof window.sanitizeHtml !== "function") {
    window.sanitizeHtml = sanitizeHtml;
  }
  window.getSanitizedHtml = function getSanitizedHtml(html) {
    return sanitizeHtml(html);
  };
  window.setSanitizedHtml = function setSanitizedHtml(target, html) {
    if (!target) {
      return;
    }
    const safeHtml = sanitizeHtml(html);
    if (typeof target.html === "function") {
      target.html(safeHtml);
    } else if (typeof target.innerHTML === "string") {
      target.innerHTML = safeHtml;
    }
  };
}

(() => {
  if (typeof window === "undefined") {
    return;
  }

  const getStoredDebugPreference = () => {
    if (typeof window.sessionStorage !== "undefined") {
      const sessionValue = window.sessionStorage.getItem("YFGP_DEBUG_LOGS");
      if (sessionValue !== null) {
        return sessionValue === "1" ? "1" : "0";
      }
    }
    if (typeof window.localStorage !== "undefined") {
      const localValue = window.localStorage.getItem("YFGP_DEBUG_LOGS");
      if (localValue !== null) {
        return localValue === "1" ? "1" : "0";
      }
    }
    return null;
  };

  let debugValue = null;
  try {
    const params = new URLSearchParams(window.location.search);
    if (params.has("yfgp_debug")) {
      debugValue = params.get("yfgp_debug") === "1" ? "1" : "0";
    }
  } catch (error) {
    const fallbackValue = getDebugQueryParam();
    if (fallbackValue !== null) {
      debugValue = fallbackValue === "1" ? "1" : "0";
    }
  }

  if (debugValue === null) {
    const storedPreference = getStoredDebugPreference();
    if (storedPreference !== null) {
      debugValue = storedPreference;
    }
  }

  if (debugValue === null) {
    const serverDefault =
      typeof window.yfgpAjax !== "undefined" &&
      typeof window.yfgpAjax.debug !== "undefined" &&
      window.yfgpAjax.debug
        ? "1"
        : "0";
    debugValue = serverDefault;
  }

  if (typeof window.sessionStorage !== "undefined") {
    window.sessionStorage.setItem("YFGP_DEBUG_LOGS", debugValue);
  }
  if (typeof window.localStorage !== "undefined") {
    window.localStorage.setItem("YFGP_DEBUG_LOGS", debugValue);
  }
  window.YFGP_DEBUG_OVERRIDE = debugValue === "1";
})();

const yfgpLog = (() => {
  const getStorageOverride = () => {
    if (typeof window === "undefined") {
      return null;
    }
    const sessionValue =
      typeof window.sessionStorage !== "undefined"
        ? window.sessionStorage.getItem("YFGP_DEBUG_LOGS")
        : null;
    if (sessionValue !== null) {
      return sessionValue;
    }
    return typeof window.localStorage !== "undefined"
      ? window.localStorage.getItem("YFGP_DEBUG_LOGS")
      : null;
  };

  const getDebugFlag = () => {
    if (typeof window === "undefined") {
      return false;
    }
    const queryOverride = getDebugQueryParam();
    if (queryOverride !== null) {
      return queryOverride === "1";
    }
    if (typeof window.YFGP_DEBUG_OVERRIDE === "boolean") {
      return window.YFGP_DEBUG_OVERRIDE;
    }
    const storageOverride = getStorageOverride();
    if (storageOverride === "1") {
      return true;
    }
    if (storageOverride === "0") {
      return false;
    }
    if (typeof window.YFGP_DEBUG_LOGS !== "undefined") {
      return Boolean(window.YFGP_DEBUG_LOGS);
    }
    if (typeof window.yfgpAjax !== "undefined" && typeof window.yfgpAjax.debug !== "undefined") {
      return Boolean(window.yfgpAjax.debug);
    }
    return false;
  };

  const debugEnabled = () => getDebugFlag();
  return {
    get isDebugEnabled() {
      return debugEnabled();
    },
    debug: (...args) => {
      if (debugEnabled()) {
        console.debug(...args);
      }
    },
    info: (...args) => {
      if (!debugEnabled()) {
        return;
      }
      console.info(...args);
    },
    warn: (...args) => {
      console.warn(...args);
    },
    error: (...args) => {
      console.error(...args);
    },
  };
})();

yfgpLog.debug("[YFGP] Dynamic Field Selector v3 script enqueued");

// v4.18.35: Wrap IIFE in try-catch to catch any parsing/execution errors
try {
  (function ($) {
    "use strict";

    const nativeConsole = window.console || {
      log: () => {},
      info: () => {},
      warn: () => {},
      error: () => {},
      debug: () => {},
    };

    const logDebug = (...args) => {
      if (!yfgpLog.isDebugEnabled) {
        return;
      }
      (nativeConsole.debug || nativeConsole.log || (() => {})).apply(nativeConsole, args);
    };

    const logInfo = (...args) => {
      if (!yfgpLog.isDebugEnabled) {
        return;
      }
      (nativeConsole.info || nativeConsole.log || (() => {})).apply(nativeConsole, args);
    };

    const logWarn = (...args) => {
      (nativeConsole.warn || nativeConsole.log || (() => {})).apply(nativeConsole, args);
    };

    const logError = (...args) => {
      (nativeConsole.error || nativeConsole.log || (() => {})).apply(nativeConsole, args);
    };

    const console = {
      log: logDebug,
      debug: logDebug,
      info: logInfo,
      warn: logWarn,
      error: logError,
    };

    const loggedCptLoads = new Set();
    const logCptLoadSuccess = (cptSlug, optionsCount) => {
      if (!yfgpLog.isDebugEnabled) {
        return;
      }
      if (loggedCptLoads.has(cptSlug)) {
        return;
      }
      logDebug(`[YFGP] Загружены поля для CPT "${cptSlug}" (${optionsCount} вариантов)`);
      loggedCptLoads.add(cptSlug);
    };
    
    // v4.18.22: Debug - verify file is loading
    logInfo("[YFGP] Dynamic Field Selector v3: файл загружен");
    
    // v4.18.22: Debug - verify IIFE is executing
    logDebug("[YFGP Debug] IIFE started, jQuery available:", typeof $ !== "undefined");

  function reportDynamicFieldError(error, contextMessage) {
    if (contextMessage) {
      console.error(contextMessage, error);
    } else {
      console.error("[YFGP] DynamicFieldSelectorV3 error:", error);
    }
    if (window?.Sentry?.captureException) {
      try {
        window.Sentry.captureException(error);
      } catch (sentryError) {
        console.warn("[YFGP] Failed to forward error to Sentry:", sentryError);
      }
    }
  }

  /**
   * Validate data size before AJAX request
   * v4.18.22: Security - Client-side size validation
   *
   * @param {Object|string} data Data to validate
   * @param {number} maxSize Maximum size in bytes (default: 5MB)
   * @return {boolean} True if size is valid
   */
  function validateDataSize(data, maxSize = 5 * 1024 * 1024) {
    const dataString = typeof data === "string" ? data : JSON.stringify(data);
    const size = new Blob([dataString]).size;

    if (size > maxSize) {
      alert(
        "Размер данных превышает максимально допустимый (" +
          Math.round(maxSize / 1024 / 1024) +
          "MB). Пожалуйста, уменьшите количество полей."
      );
      return false;
    }

    return true;
  }

  /**
   * Класс для работы с динамическими полями v3.0
   */
  class DynamicFieldSelectorV3 {
    // v4.1.0-beta14: STATIC (class-level) cache shared across ALL instances
    // Prevents multiple concurrent AJAX requests for same repeater field
    static sharedSubfieldCache = new Map();
    static sharedCacheTimestamps = new Map();
    static pendingAjaxRequests = new Map(); // Track in-flight AJAX requests by cacheKey

    constructor(container, options) {
      this.container = $(container);
      this.options = $.extend(
        {
          postType: "doctors",
          fieldName: "",
          currentValue: null,
          onChange: null,
          isRepeaterField: false, // Специальный режим для repeater полей
          repeaterConfig: null, // Конфигурация repeater (education, jobs, certificates)
        },
        options
      );

      // v4.1.0-beta5: Store fieldName as direct property for easier access
      this.fieldName = this.options.fieldName;
      this.postType = this.options.postType;

      this.availableFields = {};
      this.availableCPTs = {};
      this.currentConfig = this.options.currentValue || this.getDefaultConfig();

      // v3.2.5: Cache для подполей repeater (Task #11)
      this.subfieldCache = new Map();
      this.cacheTimestamps = new Map();
      this.isLoadingSubfields = false;

      // v3.2.5: Определить parent field для subfields
      // ИСПРАВЛЕНИЕ: читаем data-parent-field с родительского контейнера, а не wrapper'а
      const parentContainer = this.container.closest(
        ".yfgp-field-container-v3"
      );
      this.parentField =
        parentContainer.length > 0
          ? parentContainer.data("parent-field")
          : null;

      try {
        this.init();
      } catch (error) {
        reportDynamicFieldError(error, `[YFGP] Failed to initialize DynamicFieldSelectorV3 constructor for ${this.fieldName}`);
        this.renderFatalError("Не удалось инициализировать поле. Проверьте консоль браузера для деталей.");
      }
    }

    /**
     * Получить конфигурацию по умолчанию
     */
    getDefaultConfig() {
      return {
        source_type: "",
        source_field: "",
        source_cpt: null,
        nested_field: null,
        meta_field: null,
        repeater_mode: null,
        // Условная логика
        conditional_logic: false,
        operator: null,
        operator_value: null,
      };
    }

    /**
     * Инициализация
     */
    async init() {
      try {
        await this.loadAvailableFields();
        await this.loadAvailableCPTs();
        this.render();
        this.bindEvents();

        // v3.2.5: Setup event listener для subfields (Task #11)
        if (this.hasParentField()) {
          this.setupParentListener();
        }

        // Загружаем сохраненное значение
        if (this.currentConfig.source_type) {
          this.restoreValue();
        }

        // v3.2.5: Проверить initial state parent поля для subfields (Task #11)
        if (this.hasParentField()) {
          this.checkParentInitialState();
        }
      } catch (error) {
        reportDynamicFieldError(error, `DynamicFieldSelectorV3 init error for ${this.fieldName}`);
        this.renderFatalError("Загрузка конфигурации поля прервана из-за ошибки. Сообщите об этом разработчику и приложите логи консоли.");
      }
    }

    /**
     * Загрузка доступных полей (v3.3.5: из PHP или через AJAX fallback)
     */
    async loadAvailableFields() {
      return new Promise((resolve, reject) => {
        if (typeof yfgpAjax === "undefined") {
          const errorMessage = "Объект yfgpAjax недоступен. Скрипт локализации не загружен.";
          console.error(errorMessage);
          this.renderFatalError(errorMessage);
          reject(errorMessage);
          return;
        }
        
        // v3.3.5: Проверить есть ли данные переданные через wp_localize_script
        if (
          yfgpAjax?.availableFieldsByPostType &&
          yfgpAjax.availableFieldsByPostType[this.options.postType]
        ) {
          logDebug("[YFGP Init] Loading fields from PHP data (no AJAX)");
          this.availableFields =
            yfgpAjax.availableFieldsByPostType[this.options.postType];
          resolve();
          return;
        }

        // Fallback: AJAX запрос
        logDebug("[YFGP Init] Loading fields via AJAX (fallback)");
        $.ajax({
          url: yfgpAjax.ajax_url,
          type: "POST",
          data: {
            action: "yfgp_get_fields_v3",
            nonce: yfgpAjax.nonce,
            post_type: this.options.postType,
          },
          dataType: 'text', // v4.18.11: Используем 'text' для обработки BOM
          success: (responseText, textStatus, xhr) => {
            // v4.18.11: Используем xhr.responseText напрямую для получения сырого ответа
            let rawResponse = xhr.responseText || responseText;
            
            // v4.18.11: Remove UTF-8 BOM (U+FEFF) - удаляем ВСЕ BOM подряд в цикле
            if (typeof rawResponse === 'string' && rawResponse.length > 0) {
              // Удаляем ВСЕ BOM подряд (может быть несколько!)
              while (rawResponse.length > 0 && (
                rawResponse.charCodeAt(0) === 0xFEFF || 
                rawResponse.charCodeAt(0) === 65279 ||
                rawResponse.substring(0, 3) === '\xEF\xBB\xBF'
              )) {
                if (rawResponse.substring(0, 3) === '\xEF\xBB\xBF') {
                  rawResponse = rawResponse.substring(3);
                } else {
                  rawResponse = rawResponse.slice(1);
                }
              }
            }
            let parsedResponse;
            try {
              parsedResponse = JSON.parse(rawResponse);
            } catch (e) {
              console.error("Failed to parse response:", e, rawResponse.substring(0, 100));
              reject('Invalid response format');
              return;
            }
            
            if (parsedResponse && parsedResponse.success === true) {
              this.availableFields = parsedResponse.data;
              resolve();
            } else {
              console.error("Failed to load fields:", parsedResponse);
              console.error("Response success:", parsedResponse?.success);
              console.error("Response data:", parsedResponse?.data);
              const errorMessage = parsedResponse?.data?.message || parsedResponse?.data || parsedResponse?.message || 'Unknown error';
              reject(errorMessage);
            }
          },
          error: (xhr, status, error) => {
            console.error("AJAX error:", error);
            console.error("AJAX status:", status);
            console.error("AJAX xhr:", xhr);
            console.error("AJAX responseText:", xhr.responseText);
            let errorMessage = error || 'Unknown error';
            try {
              if (xhr.responseText) {
                const parsed = JSON.parse(xhr.responseText);
                errorMessage = parsed.data?.message || parsed.message || errorMessage;
              }
            } catch (e) {
              console.error("Failed to parse error response:", e);
              errorMessage = xhr.responseText || error || 'Unknown error';
            }
            reject(errorMessage);
          },
        });
      });
    }

    /**
     * Загрузка доступных CPT через AJAX
     */
    async loadAvailableCPTs() {
      return new Promise((resolve, reject) => {
        if (typeof yfgpAjax === "undefined") {
          const errorMessage = "Объект yfgpAjax недоступен. Невозможно загрузить список CPT.";
          console.error(errorMessage);
          this.renderFatalError(errorMessage);
          reject(errorMessage);
          return;
        }
        
        $.ajax({
          url: yfgpAjax.ajax_url,
          type: "POST",
          data: {
            action: "yfgp_get_cpts_v3",
            nonce: yfgpAjax.nonce,
          },
          dataType: 'text', // v4.18.11: Используем 'text' для обработки BOM
          success: (responseText, textStatus, xhr) => {
            // v4.18.11: Используем xhr.responseText напрямую для получения сырого ответа
            let rawResponse = xhr.responseText || responseText;
            
            // v4.18.11: Remove UTF-8 BOM (U+FEFF) - удаляем ВСЕ BOM подряд в цикле
            if (typeof rawResponse === 'string' && rawResponse.length > 0) {
              // Удаляем ВСЕ BOM подряд (может быть несколько!)
              while (rawResponse.length > 0 && (
                rawResponse.charCodeAt(0) === 0xFEFF || 
                rawResponse.charCodeAt(0) === 65279 ||
                rawResponse.substring(0, 3) === '\xEF\xBB\xBF'
              )) {
                if (rawResponse.substring(0, 3) === '\xEF\xBB\xBF') {
                  rawResponse = rawResponse.substring(3);
                } else {
                  rawResponse = rawResponse.slice(1);
                }
              }
            }
            let parsedResponse;
            try {
              parsedResponse = JSON.parse(rawResponse);
            } catch (e) {
              console.error("Failed to parse response:", e, rawResponse.substring(0, 100));
              reject('Invalid response format');
              return;
            }
            
            if (parsedResponse && parsedResponse.success === true) {
              this.availableCPTs = parsedResponse.data;
              resolve();
            } else {
              console.error("Failed to load CPTs:", parsedResponse);
              console.error("Response success:", parsedResponse?.success);
              console.error("Response data:", parsedResponse?.data);
              const errorMessage = parsedResponse?.data?.message || parsedResponse?.data || parsedResponse?.message || 'Unknown error';
              reject(errorMessage);
            }
          },
          error: (xhr, status, error) => {
            console.error("AJAX error:", error);
            console.error("AJAX status:", status);
            console.error("AJAX xhr:", xhr);
            console.error("AJAX responseText:", xhr.responseText);
            let errorMessage = error || 'Unknown error';
            try {
              if (xhr.responseText) {
                const parsed = JSON.parse(xhr.responseText);
                errorMessage = parsed.data?.message || parsed.message || errorMessage;
              }
            } catch (e) {
              console.error("Failed to parse error response:", e);
              errorMessage = xhr.responseText || error || 'Unknown error';
            }
            reject(errorMessage);
          },
        });
      });
    }

    /**
     * Показать фатальную ошибку в контейнере поля
     *
     * @param {string} message Текст ошибки
     */
    renderFatalError(message) {
      const fallbackMessage =
        typeof message === "string" && message.trim().length > 0
          ? message
          : "Неизвестная ошибка. Проверьте консоль браузера для подробностей.";
      const errorHtml = `
        <div class="yfgp-dfs-error">
          <strong>Не удалось загрузить конфигурацию поля</strong>
          <p>${sanitizeHtml(fallbackMessage)}</p>
        </div>
      `;
      this.container
        .addClass("yfgp-dfs-error-state")
        .html(errorHtml);
    }

    /**
     * Рендеринг компонента
     */
    render() {
      logDebug("🔧 [render] Called for field:", this.fieldName, "container:", this.container.length);
      const html = `
        <div class="yfgp-dynamic-field-v3">
          <!-- Выбор типа источника -->
          <div class="yfgp-field-row">
            <label class="yfgp-field-label">
              <span class="label-text">Тип источника данных:</span>
              <select class="yfgp-source-type-v3">
                <option value="">-- Не выбрано --</option>
                <optgroup label="📋 Стандартные">
                  <option value="meta_field">Мета-поле (прямое значение)</option>
                  <option value="relationship_1">Связь 1 уровня (через ACF/JetEngine)</option>
                  <option value="relationship_2">Связь 2 уровня (через 2 связи)</option>
                  <option value="taxonomy">Термины таксономии</option>
                  <option value="taxonomy_meta">Мета-поля терминов</option>
                  <option value="fixed">Произвольное значение</option>
                  <option value="boolean">Булево значение (true/false)</option>
                </optgroup>
                ${
                  this.options.isRepeaterField
                    ? `
                <optgroup label="🔁 Repeater поля">
                  <option value="repeater_acf">ACF Repeater</option>
                  <option value="repeater_jetengine">JetEngine Repeater</option>
                  <option value="repeater_relationship">Relationship к CPT</option>
                </optgroup>
                `
                    : ""
                }
              </select>
            </label>
          </div>

          <!-- === СЕКЦИИ ДЛЯ КАЖДОГО ТИПА === -->

          <!-- 1. meta_field: Мета-поле -->
          <div class="yfgp-config-section yfgp-config-meta-field" data-source-type="meta_field" style="display: none;">
            <div class="yfgp-field-row">
              <label class="yfgp-field-label">
                <span class="label-text">Выберите поле:</span>
                <select class="yfgp-source-field">
                  <option value="">-- Выберите поле --</option>
                  ${this.renderFieldOptionsByGroup("wordpress")}
                  ${this.renderFieldOptionsByGroup("acf")}
                  ${this.renderFieldOptionsByGroup("jetengine")}
                </select>
              </label>
            </div>
          </div>

          <!-- 2. relationship_1: Связь 1 уровня -->
          <div class="yfgp-config-section yfgp-config-relationship-1" data-source-type="relationship_1" style="display: none;">
            <div class="yfgp-field-row">
              <label class="yfgp-field-label">
                <span class="label-text">1️⃣ Поле связи (ACF/JetEngine):</span>
                <select class="yfgp-source-field">
                  <option value="">-- Выберите поле связи --</option>
                  ${this.renderFieldOptionsByGroup("acf_relationship")}
                  ${this.renderFieldOptionsByGroup("jetengine_relationship")}
                </select>
              </label>
            </div>
            <div class="yfgp-field-row">
              <label class="yfgp-field-label">
                <span class="label-text">2️⃣ Целевой CPT:</span>
                <select class="yfgp-source-cpt">
                  <option value="">-- Выберите CPT --</option>
                  ${this.renderCPTOptions()}
                </select>
              </label>
            </div>
            <div class="yfgp-field-row">
              <label class="yfgp-field-label">
                <span class="label-text">3️⃣ Вложенное поле (в целевом CPT):</span>
                <select class="yfgp-nested-field">
                  <option value="">-- Сначала выберите CPT --</option>
                </select>
              </label>
            </div>
            <div class="yfgp-help-text">
              <small>💡 Связь 1 уровня: Врач → Услуги → получить поле из услуги</small>
            </div>
          </div>

          <!-- 3. relationship_2: Связь 2 уровня -->
          <div class="yfgp-config-section yfgp-config-relationship-2" data-source-type="relationship_2" style="display: none;">
            <div class="yfgp-field-row">
              <label class="yfgp-field-label">
                <span class="label-text">1️⃣ Первая связь:</span>
                <select class="yfgp-source-field">
                  <option value="">-- Выберите поле связи --</option>
                  ${this.renderFieldOptionsByGroup("acf_relationship")}
                  ${this.renderFieldOptionsByGroup("jetengine_relationship")}
                </select>
              </label>
            </div>
            <div class="yfgp-field-row">
              <label class="yfgp-field-label">
                <span class="label-text">2️⃣ Тип 1 уровня (CPT):</span>
                <select class="yfgp-source-cpt">
                  <option value="">-- Выберите CPT --</option>
                  ${this.renderCPTOptions()}
                </select>
              </label>
            </div>
            <div class="yfgp-field-row">
              <label class="yfgp-field-label">
                <span class="label-text">3️⃣ Вторая связь:</span>
                <select class="yfgp-second-relationship">
                  <option value="">-- Сначала выберите CPT 1 уровня --</option>
                </select>
              </label>
            </div>
            <div class="yfgp-field-row">
              <label class="yfgp-field-label">
                <span class="label-text">4️⃣ Тип 2 уровня (CPT):</span>
                <select class="yfgp-second-cpt">
                  <option value="">-- Выберите CPT --</option>
                  ${this.renderCPTOptions()}
                </select>
              </label>
            </div>
            <div class="yfgp-field-row">
              <label class="yfgp-field-label">
                <span class="label-text">5️⃣ Вложенное поле (в CPT 2 уровня):</span>
                <select class="yfgp-nested-field">
                  <option value="">-- Сначала выберите CPT 2 уровня --</option>
                </select>
              </label>
            </div>
            <div class="yfgp-help-text">
              <small>💡 Связь 2 уровня: Врач → Услуги → Цена (получить цены услуг через 2 связи)</small>
            </div>
          </div>

          <!-- 4. taxonomy: Термины таксономии -->
          <div class="yfgp-config-section yfgp-config-taxonomy" data-source-type="taxonomy" style="display: none;">
            <div class="yfgp-field-row">
              <label class="yfgp-field-label">
                <span class="label-text">Выберите таксономию:</span>
                <select class="yfgp-source-field">
                  <option value="">-- Выберите таксономию --</option>
                  ${this.renderFieldOptionsByGroup("taxonomy")}
                </select>
              </label>
            </div>
            <div class="yfgp-help-text">
              <small>💡 Будут получены названия всех терминов таксономии</small>
            </div>
          </div>

          <!-- 5. taxonomy_meta: Мета-поля терминов -->
          <div class="yfgp-config-section yfgp-config-taxonomy-meta" data-source-type="taxonomy_meta" style="display: none;">
            <div class="yfgp-field-row">
              <label class="yfgp-field-label">
                <span class="label-text">1️⃣ Выберите таксономию:</span>
                <select class="yfgp-source-field">
                  <option value="">-- Выберите таксономию --</option>
                  ${this.renderFieldOptionsByGroup("taxonomy")}
                </select>
              </label>
            </div>
            <div class="yfgp-field-row">
              <label class="yfgp-field-label">
                <span class="label-text">2️⃣ Мета-поле термина:</span>
                <input type="text" class="yfgp-meta-field regular-text" placeholder="Например: gov_id">
              </label>
            </div>
            <div class="yfgp-help-text">
              <small>💡 Будут получены значения мета-поля из всех терминов</small>
            </div>
          </div>

          <!-- 6. fixed: Произвольное значение -->
          <div class="yfgp-config-section yfgp-config-fixed" data-source-type="fixed" style="display: none;">
            <div class="yfgp-field-row">
              <label class="yfgp-field-label">
                <span class="label-text">Значение:</span>
                <input type="text" class="yfgp-source-field-input regular-text" placeholder="Введите значение">
              </label>
            </div>
            <div class="yfgp-help-text">
              <small>💡 Специальные значения: <code>post_id</code>, <code>site_url</code>, <code>current_date</code></small>
            </div>
          </div>

          <!-- 7. boolean: Булевые значения -->
          <div class="yfgp-config-section yfgp-config-boolean" data-source-type="boolean" style="display: none;">
            <div class="yfgp-field-row">
              <label class="yfgp-field-label">
                <span class="label-text">Значение:</span>
              </label>
              <div class="yfgp-boolean-options" style="display: inline-block; margin-left: 10px;">
                <label style="margin-right: 15px; display: inline-block;">
                  <input type="radio" name="yfgp-boolean-value-${
                    this.options.fieldName
                  }" value="true" class="yfgp-boolean-radio">
                  Да (true)
                </label>
                <label style="display: inline-block;">
                  <input type="radio" name="yfgp-boolean-value-${
                    this.options.fieldName
                  }" value="false" class="yfgp-boolean-radio">
                  Нет (false)
                </label>
              </div>
            </div>
            <div class="yfgp-help-text">
              <small>💡 Используйте для полей типа children_appointment, adult_appointment, house_call, telemed</small>
            </div>
          </div>

          ${this.options.isRepeaterField ? this.renderRepeaterSections() : ""}

          <!-- === УСЛОВНАЯ ЛОГИКА === -->
          <div class="yfgp-conditional-logic-section" style="margin-top: 20px; border-top: 1px solid #ddd; padding-top: 15px;">
            <div class="yfgp-field-row">
              <label class="yfgp-field-label" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" class="yfgp-enable-conditional">
                <span class="label-text"><strong>⚙️ Использовать условную логику</strong></span>
              </label>
              <p class="description" style="margin-left: 26px;">
                Применить операторы сравнения к полученному значению (фильтрация, преобразование)
              </p>
            </div>

            <!-- Секция условной логики (скрыта по умолчанию) -->
            <div class="yfgp-conditional-config" style="display: none; margin-top: 15px; background: #f9f9f9; padding: 15px; border-radius: 4px;">
              <div class="yfgp-field-row">
                <label class="yfgp-field-label">
                  <span class="label-text">Оператор:</span>
                  <select class="yfgp-operator">
                    <option value="">-- Выберите оператор --</option>
                    <optgroup label="Сравнение">
                      <option value="=">=  (равно)</option>
                      <option value="!=">!= (не равно)</option>
                      <option value=">">&gt; (больше)</option>
                      <option value="<">&lt; (меньше)</option>
                      <option value=">=">&gt;= (больше или равно)</option>
                      <option value="<=">&lt;= (меньше или равно)</option>
                    </optgroup>
                    <optgroup label="Списки">
                      <option value="in_list">in_list (value must be in comma separated list)</option>
                      <option value="not_in_list">not_in_list (value must NOT be in comma separated list)</option>
                    </optgroup>
                    <optgroup label="Проверка существования">
                      <option value="empty">Пустое значение</option>
                      <option value="not_empty">Непустое значение</option>
                    </optgroup>
                    <optgroup label="Трансформация">
                      <option value="replace">Заменить значение</option>
                      <option value="default">Значение по умолчанию (если пусто)</option>
                    </optgroup>
                  </select>
                </label>
              </div>

              <div class="yfgp-field-row yfgp-operator-value-row" style="display: none;">
                <label class="yfgp-field-label">
                  <span class="label-text">Значение:</span>
                  <input type="text" class="yfgp-operator-value regular-text" placeholder="Enter operand or list (comma separated)">
                </label>
                <p class="description yfgp-operator-help"></p>
              </div>

              <div class="yfgp-conditional-examples" style="margin-top: 10px; padding: 10px; background: #fff; border-left: 3px solid #2271b1;">
                <strong>💡 Примеры использования:</strong>
                <ul style="margin: 5px 0 0 20px; font-size: 12px;">
                  <li><code>=</code> - Keep the value only when it equals the operand</li>
                  <li><code>in_list</code> - Keep the value when it matches any item in a comma separated list</li>
                  <li><code>not_in_list</code> - Drop the value when it matches any item in the list</li>
                  <li><code>empty</code> - Return null when the value is empty</li>
                  <li><code>default</code> - Use the fallback value when the current value is empty</li>
                  <li><code>replace</code> - Replace substrings using the "old|new" format</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Превью конфигурации -->
          <div class="yfgp-config-preview" style="display: none; margin-top: 15px;">
            <div class="yfgp-preview-box">
              <strong>📋 Конфигурация поля:</strong>
              <pre class="yfgp-preview-code"></pre>
            </div>
          </div>

          <!-- v3.4.1: Скрытое поле удалено! Используем ТОЛЬКО глобальный #yfgp_mapping_json -->
        </div>
      `;

      // v4.18.22: Security - Sanitize HTML to prevent XSS
      this.container.html(sanitizeHtml(html));
      
      // v4.18.41: DEBUG - Check if sections are in DOM after insertion
      const $sections = this.container.find(".yfgp-config-section");
      logDebug("✅ [render] HTML rendered for field:", this.fieldName);
      logDebug("  ↳ Container HTML length:", this.container.html().length);
      logDebug("  ↳ Sections found:", $sections.length);
      logDebug("  ↳ Container selector:", this.container.selector || "no selector (jQuery object)");
      logDebug("  ↳ Container parent:", this.container.parent().length > 0 ? "has parent" : "no parent");
      
      if ($sections.length === 0) {
        console.error("❌ [render] NO SECTIONS FOUND! HTML preview:", this.container.html().substring(0, 500));
      }
    }

    /**
     * Рендеринг секций для repeater полей
     */
    renderRepeaterSections() {
      return `
        <!-- 7. repeater_acf: ACF Repeater -->
        <div class="yfgp-config-section yfgp-config-repeater-acf" data-source-type="repeater_acf" style="display: none;">
          <div class="yfgp-field-row">
            <label class="yfgp-field-label">
              <span class="label-text">Repeater поле (ACF):</span>
              <select class="yfgp-source-field">
                <option value="">-- Выберите repeater поле --</option>
                ${this.renderFieldOptionsByGroup("repeater")}
              </select>
            </label>
          </div>
          <div class="yfgp-help-text">
            <small>💡 ACF Repeater: массив элементов с подполями</small>
          </div>
        </div>

        <!-- 8. repeater_jetengine: JetEngine Repeater -->
        <div class="yfgp-config-section yfgp-config-repeater-jetengine" data-source-type="repeater_jetengine" style="display: none;">
          <div class="yfgp-field-row">
            <label class="yfgp-field-label">
              <span class="label-text">Repeater поле (JetEngine):</span>
              <select class="yfgp-source-field">
                <option value="">-- Выберите repeater поле --</option>
                ${this.renderFieldOptionsByGroup("repeater_jetengine")}
              </select>
            </label>
          </div>
          <div class="yfgp-help-text">
            <small>💡 JetEngine Repeater: массив элементов с подполями</small>
          </div>
        </div>

        <!-- 9. repeater_relationship: Relationship к CPT -->
        <div class="yfgp-config-section yfgp-config-repeater-relationship" data-source-type="repeater_relationship" style="display: none;">
          <div class="yfgp-field-row">
            <label class="yfgp-field-label">
              <span class="label-text">1️⃣ Поле связи:</span>
              <select class="yfgp-source-field">
                <option value="">-- Выберите поле связи --</option>
                ${this.renderFieldOptionsByGroup("acf_relationship")}
                ${this.renderFieldOptionsByGroup("jetengine_relationship")}
              </select>
            </label>
          </div>
          <div class="yfgp-field-row">
            <label class="yfgp-field-label">
              <span class="label-text">2️⃣ Целевой CPT:</span>
              <select class="yfgp-source-cpt">
                <option value="">-- Выберите CPT --</option>
                ${this.renderCPTOptions()}
              </select>
            </label>
          </div>
          <div class="yfgp-help-text">
            <small>💡 Relationship к CPT: получить данные из связанных постов</small>
          </div>
        </div>
      `;
    }

    /**
     * Рендеринг опций полей по группе
     */
    renderFieldOptionsByGroup(groupKey) {
      if (!this.availableFields[groupKey]) {
        return "";
      }

      const group = this.availableFields[groupKey];
      let html = `<optgroup label="${this.getGroupLabel(groupKey)}">`;

      Object.keys(group).forEach((fieldKey) => {
        const field = group[fieldKey];
        const label = field.label || fieldKey;
        html += `<option value="${fieldKey}">${label}</option>`;
      });

      html += "</optgroup>";
      return html;
    }

    /**
     * Получить label группы полей
     */
    getGroupLabel(groupKey) {
      const labels = {
        wordpress: "WordPress (стандартные поля)",
        acf: "ACF (мета-поля)",
        jetengine: "JetEngine (мета-поля)",
        acf_relationship: "ACF Relationships",
        jetengine_relationship: "JetEngine Relations",
        taxonomy: "Таксономии",
        repeater: "ACF Repeater",
        repeater_jetengine: "JetEngine Repeater",
      };
      return labels[groupKey] || groupKey;
    }

    /**
     * Рендеринг опций CPT
     */
    renderCPTOptions() {
      let html = "";
      Object.keys(this.availableCPTs).forEach((cptSlug) => {
        const cpt = this.availableCPTs[cptSlug];
        html += `<option value="${cptSlug}">${cpt.label} (${cpt.count} записей)</option>`;
      });
      return html;
    }

    /**
     * Привязка событий
     */
    bindEvents() {
      const $container = this.container;
      logDebug("🔧 [bindEvents] Binding events for field:", this.fieldName, "container:", $container.length);

      // Изменение типа источника
      $container.on("change", ".yfgp-source-type-v3", (e) => {
        const sourceType = $(e.target).val();
        logDebug("🔧 [bindEvents] Source type changed to:", sourceType, "for field:", this.fieldName);
        this.onSourceTypeChange(sourceType);
      });

      // Изменение source_field
      $container.on("change", ".yfgp-source-field", (e) => {
        this.currentConfig.source_field = $(e.target).val();
        this.updatePreview();
        this.saveConfig();

        // v3.2.5: Broadcast event если это main repeater field (Task #11)
        if (this.isMainRepeaterField()) {
          this.broadcastRepeaterChange();
        }
      });

      // Изменение source_cpt
      $container.on("change", ".yfgp-source-cpt", (e) => {
        this.currentConfig.source_cpt = $(e.target).val();

        // Если relationship_2 - загрузить связи для CPT 1 уровня
        if (this.currentConfig.source_type === "relationship_2") {
          this.loadRelationshipsForCPT(
            this.currentConfig.source_cpt,
            ".yfgp-second-relationship"
          );
        }

        // Если relationship_1 - загрузить поля CPT
        if (this.currentConfig.source_type === "relationship_1") {
          this.loadCPTFields(this.currentConfig.source_cpt);
        }

        this.updatePreview();
        this.saveConfig();
      });

      // Изменение second_relationship (relationship_2)
      $container.on("change", ".yfgp-second-relationship", (e) => {
        this.currentConfig.second_relationship = $(e.target).val();
        this.updatePreview();
        this.saveConfig();
      });

      // Изменение second_cpt (relationship_2)
      $container.on("change", ".yfgp-second-cpt", (e) => {
        this.currentConfig.second_cpt = $(e.target).val();

        // Загрузить поля CPT 2 уровня
        if (this.currentConfig.source_type === "relationship_2") {
          this.loadCPTFields(this.currentConfig.second_cpt);
        }

        this.updatePreview();
        this.saveConfig();
      });

      // Изменение nested_field
      $container.on("change", ".yfgp-nested-field", (e) => {
        this.currentConfig.nested_field = $(e.target).val();
        this.updatePreview();
        this.saveConfig();
      });

      // Изменение meta_field (для taxonomy_meta)
      $container.on("input", ".yfgp-meta-field", (e) => {
        this.currentConfig.meta_field = $(e.target).val();
        this.updatePreview();
        this.saveConfig();
      });

      // Изменение fixed value
      $container.on("input", ".yfgp-source-field-input", (e) => {
        this.currentConfig.source_field = $(e.target).val();
        this.updatePreview();
        this.saveConfig();
      });

      // Изменение boolean value (radio buttons)
      $container.on("change", ".yfgp-boolean-radio", (e) => {
        this.currentConfig.source_field = $(e.target).val();
        this.updatePreview();
        this.saveConfig();
      });

      // === УСЛОВНАЯ ЛОГИКА ===

      // Включение/выключение условной логики
      $container.on("change", ".yfgp-enable-conditional", (e) => {
        const isEnabled = $(e.target).is(":checked");
        this.currentConfig.conditional_logic = isEnabled;

        if (isEnabled) {
          $container.find(".yfgp-conditional-config").slideDown(200);
        } else {
          $container.find(".yfgp-conditional-config").slideUp(200);
          // Сбросить значения
          this.currentConfig.operator = null;
          this.currentConfig.operator_value = null;
          $container.find(".yfgp-operator").val("");
          $container.find(".yfgp-operator-value").val("");
        }

        this.updatePreview();
        this.saveConfig();
      });

      // Изменение оператора
      $container.on("change", ".yfgp-operator", (e) => {
        const operator = $(e.target).val();
        this.currentConfig.operator = operator;

        // Показать/скрыть поле значения в зависимости от оператора
        const $valueRow = $container.find(".yfgp-operator-value-row");
        const $valueInput = $container.find(".yfgp-operator-value");
        const $helpText = $container.find(".yfgp-operator-help");

        if (operator === "empty" || operator === "not_empty") {
          // Для empty/not_empty значение не нужно
          $valueRow.hide();
          this.currentConfig.operator_value = null;
        } else {
          $valueRow.show();

          // Обновить help text в зависимости от оператора
          const helpTexts = {
            "=": "Returns the value only when it equals the operand",
            "!=": "Returns the value only when it does NOT equal the operand",
            ">": "Numeric comparison: value kept when greater than the operand",
            "<": "Numeric comparison: value kept when less than the operand",
            ">=": "Numeric comparison: value kept when greater or equal",
            "<=": "Numeric comparison: value kept when less or equal",
            in_list:
              "Comma separated list (e.g. MRI,CT,Ultrasound). Value kept only when it matches any item",
            not_in_list:
              "Comma separated list. Value removed when it matches any item",
            "?": "Legacy alias for in_list (comma separated values)",
            "∈": "Legacy alias for in_list (comma separated values)",
            "∉": "Legacy alias for not_in_list (comma separated values)",
            replace:
              'Format: "old|new" (e.g. "yes|true"). Replaces substring in the value',
            default: "Uses provided fallback when the value is empty",
          };

          $helpText.text(helpTexts[operator] || "");

          // Автоочистка значения при смене оператора
          if (operator !== this.previousOperator) {
            $valueInput.val("");
            this.currentConfig.operator_value = null;
          }
        }

        this.previousOperator = operator;
        this.updatePreview();
        this.saveConfig();
      });

      // Изменение значения оператора
      $container.on("input", ".yfgp-operator-value", (e) => {
        this.currentConfig.operator_value = $(e.target).val();
        this.updatePreview();
        this.saveConfig();
      });
    }

    /**
     * Обработчик изменения типа источника
     * v4.18.22: FIX - Сохранить старый тип ПЕРЕД изменением и очистить source_cpt для типов, которые его не используют
     */
    onSourceTypeChange(sourceType) {
      logDebug("🔧 [onSourceTypeChange] Called with sourceType:", sourceType, "for field:", this.fieldName);
      // v4.18.22: CRITICAL FIX - Сохранить старый тип ПЕРЕД изменением
      const oldSourceType = this.currentConfig.source_type;

      // Скрыть все секции
      const $allSections = this.container.find(".yfgp-config-section");
      logDebug("🔧 [onSourceTypeChange] Found", $allSections.length, "config sections");
      $allSections.hide();

      // Показать выбранную секцию
      if (sourceType) {
        const $targetSection = this.container.find(`.yfgp-config-section[data-source-type="${sourceType}"]`);
        logDebug("🔧 [onSourceTypeChange] Found target section?", $targetSection.length, "for type:", sourceType);
        if ($targetSection.length > 0) {
          $targetSection.show();
          logDebug("✅ [onSourceTypeChange] Section shown for:", sourceType);
        } else {
          console.error("❌ [onSourceTypeChange] Section NOT found for type:", sourceType);
        }
      } else {
        logDebug("⚠️ [onSourceTypeChange] sourceType is empty, not showing any section");
      }

      // v4.18.44: CRITICAL FIX - Show ONLY the correct relationship section (not all of them!)
      // The target section is already shown above, so we just need to ensure it's the right one
      if (sourceType === 'relationship_1') {
        // Hide relationship_2 section if it's visible
        this.container.find(".yfgp-config-relationship-2").hide();
        logDebug("✅ [onSourceTypeChange] relationship_1 section shown (relationship_2 hidden)");
      } else if (sourceType === 'relationship_2') {
        // Hide relationship_1 section if it's visible
        this.container.find(".yfgp-config-relationship-1").hide();
        logDebug("✅ [onSourceTypeChange] relationship_2 section shown (relationship_1 hidden)");
      }

      // Обновить конфигурацию
      this.currentConfig.source_type = sourceType;

      // v4.18.22: CRITICAL FIX - Сбросить остальные поля (если тип изменился)
      // Используем oldSourceType вместо this.currentConfig.source_type (который уже изменен!)
      if (sourceType !== oldSourceType) {
        this.currentConfig.source_field = "";
        this.currentConfig.source_cpt = null;
        this.currentConfig.nested_field = null;
        this.currentConfig.meta_field = null;
      }

      // v4.18.22: CRITICAL FIX - Очистить source_cpt для типов, которые его не используют
      // Типы, которые НЕ используют source_cpt: meta_field, fixed, boolean, taxonomy, taxonomy_meta, repeater_acf, repeater_jetengine
      const typesWithoutSourceCpt = [
        "meta_field",
        "fixed",
        "boolean",
        "taxonomy",
        "taxonomy_meta",
        "repeater_acf",
        "repeater_jetengine"
      ];

      if (typesWithoutSourceCpt.includes(sourceType)) {
        this.currentConfig.source_cpt = null;
        logDebug("🔧 [YFGP v4.18.22] Cleared source_cpt for", sourceType, "field:", this.fieldName);
      }

      this.updatePreview();
      this.saveConfig();
    }

    /**
     * Загрузить поля CPT для nested_field (relationship_2)
     */
    async loadCPTFields(cptSlug) {
      if (!cptSlug) return;

      // v4.1.0-beta10: Debug AJAX loading
      logDebug(
        "🔄 loadCPTFields() called for CPT:",
        cptSlug,
        "fieldName:",
        this.fieldName
      );

      // v4.18.22: CRITICAL FIX - Find relationship section first, then find nested_field inside it
      // This is more reliable than searching the entire container
      const $relationshipSection = this.container.find(
        ".yfgp-config-relationship-1, .yfgp-config-relationship-2"
      );
      
      if ($relationshipSection.length === 0) {
        console.error("❌ [loadCPTFields] Relationship section not found in container for:", this.fieldName);
        console.error("  ↳ Container:", this.container);
        console.error("  ↳ Container HTML:", this.container.html().substring(0, 200));
        return;
      }
      
      // v4.18.22: CRITICAL FIX - Ensure section is visible BEFORE searching for nested_field
      if (!$relationshipSection.is(":visible")) {
        logDebug("⚠️ [loadCPTFields] Relationship section not visible, showing it...");
        $relationshipSection.show();
      }
      
      // v4.18.22: CRITICAL FIX - Use setTimeout to allow DOM to update after showing section
      // Wait for DOM to update before searching for nested_field
      return new Promise((resolve, reject) => {
        // v4.18.26: Save resolve/reject references for use inside setTimeout
        const outerResolve = resolve;
        const outerReject = reject;
        setTimeout(async () => {  // v4.18.24: CRITICAL FIX - Make callback async to allow await
          // Find nested_field INSIDE the relationship section
          let $nestedField = $relationshipSection.find(".yfgp-nested-field");
          
          if ($nestedField.length === 0) {
            console.error("❌ [loadCPTFields] .yfgp-nested-field element not found in relationship section for:", this.fieldName);
            console.error("  ↳ Relationship section HTML:", $relationshipSection.html().substring(0, 300));
            console.error("  ↳ Relationship section visible?", $relationshipSection.is(":visible"));
            outerReject(new Error(".yfgp-nested-field element not found"));
            return;
          }
          
          logDebug("✅ [loadCPTFields] Found .yfgp-nested-field element, proceeding with AJAX");
          $nestedField.html('<option value="">⏳ Загрузка...</option>');

          // Continue with AJAX request
          try {
            // v4.18.11: Используем Promise с success callback для получения xhr.responseText
            const parsedResponse = await new Promise((resolve, reject) => {
              $.ajax({
                url: yfgpAjax.ajax_url,
                type: "POST",
                data: {
                  action: "yfgp_get_fields_v3",
                  nonce: yfgpAjax.nonce,
                  post_type: cptSlug,
                },
                dataType: 'text', // v4.18.11: Используем 'text' для обработки BOM
                success: (responseText, textStatus, xhr) => {
                  // v4.18.11: Используем xhr.responseText напрямую для получения сырого ответа
                  let rawResponse = xhr.responseText || responseText;
                  
                  // v4.18.11: Remove UTF-8 BOM (U+FEFF) - удаляем ВСЕ BOM подряд в цикле
                  if (typeof rawResponse === 'string' && rawResponse.length > 0) {
                    // Удаляем ВСЕ BOM подряд (может быть несколько!)
                    while (rawResponse.length > 0 && (
                      rawResponse.charCodeAt(0) === 0xFEFF || 
                      rawResponse.charCodeAt(0) === 65279 ||
                      rawResponse.substring(0, 3) === '\xEF\xBB\xBF'
                    )) {
                      if (rawResponse.substring(0, 3) === '\xEF\xBB\xBF') {
                        rawResponse = rawResponse.substring(3);
                      } else {
                        rawResponse = rawResponse.slice(1);
                      }
                    }
                  }
                  
                  try {
                    const parsed = JSON.parse(rawResponse);
                    resolve(parsed);
                  } catch (e) {
                    console.error("Failed to parse response in loadCPTFields:", e, rawResponse.substring(0, 100));
                    reject(e);
                  }
                },
                error: (xhr, status, error) => {
                  reject(new Error(error || 'AJAX request failed'));
                }
              });
            });

            if (parsedResponse && parsedResponse.success === true) {
              logDebug("✅ loadCPTFields: Response success for", cptSlug, ", data keys:", Object.keys(parsedResponse.data));

              let html = '<option value="">-- Выберите поле --</option>';

              // Добавить все группы полей
              Object.keys(parsedResponse.data).forEach((groupKey) => {
                const group = parsedResponse.data[groupKey];
                logDebug(
                  "  ↳ Processing group:",
                  groupKey,
                  "fields count:",
                  Object.keys(group).length
                );
                html += `<optgroup label="${this.getGroupLabel(groupKey)}">`;

                Object.keys(group).forEach((fieldKey) => {
                  const field = group[fieldKey];
                  const fieldLabel = field.label || fieldKey;
                  html += `<option value="${fieldKey}">${fieldLabel}</option>`;
                });

                html += "</optgroup>";
              });

              // v4.18.22: Security - Sanitize HTML to prevent XSS
              $nestedField.html(sanitizeHtml(html));

              const savedNestedField = this.currentConfig.nested_field;
              if (
                savedNestedField &&
                $nestedField.find(`option[value="${savedNestedField}"]`).length
              ) {
                $nestedField.val(savedNestedField);
              } else {
                const defaultCandidates = ["post_id", "ID"];
                const fallbackValue = defaultCandidates.find(
                  (candidate) =>
                    $nestedField.find(`option[value="${candidate}"]`).length > 0
                );
                if (fallbackValue) {
                  $nestedField.val(fallbackValue);
                  if (this.currentConfig.nested_field !== fallbackValue) {
                    this.currentConfig.nested_field = fallbackValue;
                    this.updatePreview();
                    this.saveConfig();
                  }
                }
              }

              const optionsCount = $nestedField.find("option").length;
              logCptLoadSuccess(cptSlug, optionsCount);

              logDebug(
                "✅ Dropdown updated, total options:",
                $nestedField.find("option").length
              );
              outerResolve(); // v4.18.26: Resolve outer Promise after successful completion
            } else {
              console.error("❌ Response failed:", parsedResponse);
              console.error("Response success:", parsedResponse?.success);
              console.error("Response data:", parsedResponse?.data);
              $nestedField.html('<option value="">❌ Ошибка загрузки</option>');
              outerReject(new Error("Response failed: " + (parsedResponse?.data?.message || "Unknown error")));
            }
          } catch (error) {
            console.error("❌ Failed to load CPT fields:", error);
            $nestedField.html('<option value="">❌ Ошибка загрузки</option>');
            outerReject(error);
          }
        }, 50); // v4.18.22: 50ms delay to allow DOM to update after showing section
      });
    }

    /**
     * Загрузить связи для CPT (для relationship_2)
     */
    async loadRelationshipsForCPT(cptSlug, targetSelector) {
      if (!cptSlug) return;

      const $targetSelect = this.container.find(targetSelector);
      $targetSelect.html('<option value="">⏳ Загрузка...</option>');

      try {
        // v4.18.11: Используем Promise с success callback для получения xhr.responseText и удаления BOM
        const response = await new Promise((resolve, reject) => {
          $.ajax({
            url: yfgpAjax.ajax_url,
            type: "POST",
            data: {
              action: "yfgp_get_fields_v3",
              nonce: yfgpAjax.nonce,
              post_type: cptSlug,
            },
            dataType: 'text', // v4.18.11: Используем 'text' для обработки BOM
            success: (responseText, textStatus, xhr) => {
              // v4.18.11: Используем xhr.responseText напрямую для получения сырого ответа
              let rawResponse = xhr.responseText || responseText;
              
              // v4.18.11: Remove UTF-8 BOM (U+FEFF) - удаляем ВСЕ BOM подряд в цикле
              if (typeof rawResponse === 'string' && rawResponse.length > 0) {
                // Удаляем ВСЕ BOM подряд (может быть несколько!)
                while (rawResponse.length > 0 && (
                  rawResponse.charCodeAt(0) === 0xFEFF || 
                  rawResponse.charCodeAt(0) === 65279 ||
                  rawResponse.substring(0, 3) === '\xEF\xBB\xBF'
                )) {
                  if (rawResponse.substring(0, 3) === '\xEF\xBB\xBF') {
                    rawResponse = rawResponse.substring(3);
                  } else {
                    rawResponse = rawResponse.slice(1);
                  }
                }
              }
              
              try {
                const parsed = JSON.parse(rawResponse);
                resolve(parsed);
              } catch (e) {
                console.error("Failed to parse response in loadRelationshipsForCPT:", e, rawResponse.substring(0, 100));
                reject(e);
              }
            },
            error: (xhr, status, error) => {
              reject(new Error(error || 'AJAX request failed'));
            }
          });
        });

        if (response.success) {
          let html = '<option value="">-- Выберите поле связи --</option>';

          // Добавить только связи (acf_relationship и jetengine_relationship)
          const relationshipGroups = [
            "acf_relationship",
            "jetengine_relationship",
          ];

          relationshipGroups.forEach((groupKey) => {
            if (
              response.data[groupKey] &&
              Object.keys(response.data[groupKey]).length > 0
            ) {
              const group = response.data[groupKey];
              html += `<optgroup label="${this.getGroupLabel(groupKey)}">`;

              Object.keys(group).forEach((fieldKey) => {
                const field = group[fieldKey];
                const fieldLabel = field.label || fieldKey;
                html += `<option value="${fieldKey}">${fieldLabel}</option>`;
              });

              html += "</optgroup>";
            }
          });

          // v4.18.22: Security - Sanitize HTML to prevent XSS
          $targetSelect.html(sanitizeHtml(html));
        } else {
          $targetSelect.html('<option value="">❌ Ошибка загрузки</option>');
        }
      } catch (error) {
        console.error("Failed to load relationships for CPT:", error);
        $targetSelect.html('<option value="">❌ Ошибка загрузки</option>');
      }
    }

    /**
     * Обновить превью конфигурации
     */
    updatePreview() {
      const $preview = this.container.find(".yfgp-config-preview");
      const $code = this.container.find(".yfgp-preview-code");

      // Показать превью только если есть source_type
      if (this.currentConfig.source_type) {
        const configJSON = JSON.stringify(this.currentConfig, null, 2);
        $code.text(configJSON);
        $preview.show();
      } else {
        $preview.hide();
      }
    }

    /**
     * Сохранить конфигурацию в скрытое поле
     * v3.4.1: НЕ сохраняем в individual hidden inputs - только в память!
     * Данные собираются через collectAllMappingData() при submit
     */
    saveConfig() {
      // v3.4.1: Закомментировано - НЕ используем individual hidden inputs!
      // const configJSON = JSON.stringify(this.currentConfig);
      // this.container.find(".yfgp-config-json").val(configJSON);

      // Вызвать onChange callback если задан
      if (typeof this.options.onChange === "function") {
        this.options.onChange(this.currentConfig);
      }
    }

    /**
     * Восстановить сохраненное значение
     * v4.1.0: Fixed restoration for boolean/relationship/conditional fields
     */
    restoreValue() {
      const config = this.currentConfig;

      // v4.1.0-beta8: CRITICAL FIX - Save values BEFORE .trigger("change") modifies currentConfig
      const savedNestedField = config.nested_field;
      const savedOperatorValue = config.operator_value;
      const savedSourceCpt = config.source_cpt;
      const savedSourceField = config.source_field;
      const savedConditionalLogic = config.conditional_logic;
      const savedOperator = config.operator;

      // v4.1.0-beta4: Debug logging
      logDebug("🔍 restoreValue() called for:", this.fieldName);
      logDebug("📦 Config:", JSON.stringify(config, null, 2));
      logDebug(
        "💾 Saved - nested_field:",
        savedNestedField,
        "operator_value:",
        savedOperatorValue
      );

      // v4.18.22: CRITICAL FIX - Set source_type FIRST to trigger onSourceTypeChange()
      // This will show the correct section (including relationship section)
      this.container
        .find(".yfgp-source-type-v3")
        .val(config.source_type)
        .trigger("change");

      // v4.18.22: CRITICAL FIX - Wait for DOM to update after onSourceTypeChange()
      // Use requestAnimationFrame to ensure the section is visible before proceeding
      requestAnimationFrame(() => {
        // Установить остальные поля (небольшая задержка для загрузки секций)
        setTimeout(() => {
          // v4.1.0: Boolean fields - restore radio button selection
          if (config.source_type === "boolean" && config.source_field) {
            this.container
              .find(`.yfgp-boolean-radio[value="${config.source_field}"]`)
              .prop("checked", true);
          }

          if (config.source_field) {
            this.container.find(".yfgp-source-field").val(config.source_field);
            this.container
              .find(".yfgp-source-field-input")
              .val(config.source_field);
          }

          // v4.1.0: Relationship fields - restore source_cpt and nested_field
          // v4.1.0-beta8: CRITICAL FIX - Use savedSourceCpt/savedNestedField instead of config.*
          // v4.18.22: CRITICAL FIX - Восстанавливать source_cpt ТОЛЬКО для типов, которые его используют
          const typesWithSourceCpt = [
            "relationship",
            "relationship_1",
            "relationship_2",
            "repeater_relationship"
          ];

          if (savedSourceCpt) {
            // v4.18.22: CRITICAL FIX - Очистить source_cpt для типов, которые его не используют
            if (!typesWithSourceCpt.includes(config.source_type)) {
              this.currentConfig.source_cpt = null;
              logDebug("🔧 [YFGP v4.18.22] Cleared source_cpt for", config.source_type, "field:", this.fieldName);
            } else {
              // Восстанавливаем source_cpt только для типов, которые его используют
              this.container.find(".yfgp-source-cpt").val(savedSourceCpt);

            // Trigger loadCPTFields for ALL relationship types (relationship, relationship_1)
            // v4.1.0-beta11: CRITICAL FIX - AWAIT loadCPTFields() before attempting to restore nested_field
            if (
              config.source_type === "relationship" ||
              config.source_type === "relationship_1"
            ) {
              logDebug(
                "🔄 Calling loadCPTFields with savedSourceCpt:",
                savedSourceCpt
              );

              // v4.18.22: CRITICAL FIX - Save 'this' context before Promise
              const self = this;
              
              // v4.18.22: CRITICAL FIX - Wait for relationship section to be visible before calling loadCPTFields
              // v4.18.42: CRITICAL FIX - Show the section BEFORE waiting for it to be visible
              const waitForSectionVisible = () => {
                return new Promise((resolve) => {
                  const $relationshipSection = self.container.find(
                    `.yfgp-config-section[data-source-type="${config.source_type}"]`
                  );
                  
                  if ($relationshipSection.length === 0) {
                    console.error("❌ [restoreValue] Relationship section not found for:", config.source_type);
                    resolve(false);
                    return;
                  }
                  
                  // v4.18.43 → v4.18.52: Force show the section and re-check visibility without noisy warnings
                  const ensureVisible = () => {
                    $relationshipSection.show();
                    $relationshipSection.css("display", "block");
                  };

                  const waitAttempts = 5;
                  let attempt = 0;

                  const checkVisibility = () => {
                    ensureVisible();
                    if ($relationshipSection.is(":visible") || attempt >= waitAttempts) {
                      resolve(true);
                      return;
                    }
                    attempt++;
                    requestAnimationFrame(checkVisibility);
                  };

                  checkVisibility();
                });
              };
              
              // Wait for section to be visible, then call loadCPTFields
              waitForSectionVisible().then((isVisible) => {
                if (isVisible) {
                  // AWAIT the Promise to complete before restoring nested_field
                  self.loadCPTFields(savedSourceCpt).then(() => {
                      logDebug(
                        "🔧 [Relationship] AJAX completed, attempting to restore nested_field for:",
                        self.fieldName
                      );
                      logDebug("  ↳ savedNestedField:", savedNestedField);

                      if (savedNestedField) {
                        // v4.18.22: CRITICAL FIX - Find relationship section first, then nested_field inside it
                        const $relationshipSection = self.container.find(
                          `.yfgp-config-section[data-source-type="${config.source_type}"]`
                        );
                        
                        if ($relationshipSection.length === 0) {
                          console.error(
                            "❌ [restoreValue] Relationship section not found after loadCPTFields!"
                          );
                          console.error("  ↳ Container:", self.container);
                          console.error("  ↳ Field name:", self.fieldName);
                          console.error("  ↳ Source type:", config.source_type);
                          return;
                        }
                        
                        // Find nested_field INSIDE the relationship section
                        const $nestedField = $relationshipSection.find(".yfgp-nested-field");
                        logDebug(
                          "  ↳ Found .yfgp-nested-field element?",
                          $nestedField.length > 0
                        );

                        if ($nestedField.length === 0) {
                          console.error(
                            "❌ [restoreValue] .yfgp-nested-field element not found in relationship section after loadCPTFields!"
                          );
                          console.error("  ↳ Container:", self.container);
                          console.error("  ↳ Field name:", self.fieldName);
                          console.error("  ↳ Relationship section HTML:", $relationshipSection.html().substring(0, 300));
                          return;
                        }

                        const $options = $nestedField.find("option");
                        logDebug("  ↳ Element options count:", $options.length);

                        // v4.1.0-beta9: Debug available option values
                        const availableValues = [];
                        $options.each(function () {
                          availableValues.push($(this).val());
                        });
                        logDebug("  ↳ Available option values:", availableValues);

                        if ($options.length > 0) {
                          $nestedField.val(savedNestedField).trigger("change");

                          logDebug("  ↳ Value set to:", $nestedField.val());
                          logDebug("  ↳ Expected:", savedNestedField);
                          logDebug(
                            "  ↳ Match?",
                            $nestedField.val() === savedNestedField
                          );
                        } else {
                          console.warn(
                            "⚠️ [restoreValue] No options available in dropdown, cannot restore value"
                          );
                        }
                      }
                    })
                    .catch((error) => {
                      console.error(
                        "❌ [restoreValue] Error loading CPT fields:",
                        error
                      );
                    });
                } else {
                  console.error("❌ [restoreValue] Cannot load CPT fields - section not visible");
                }
              });
            }
            } // v4.18.37: Close else block (line 1412)
          }

          // v4.1.0-beta3: Handle nested_field for non-relationship types
            if (
              config.nested_field &&
              config.source_type !== "relationship" &&
              config.source_type !== "relationship_1"
            ) {
              this.container.find(".yfgp-nested-field").val(config.nested_field);
            }
            if (config.meta_field) {
              this.container.find(".yfgp-meta-field").val(config.meta_field);
            }

            // v4.1.0-beta2: Восстановить условную логику с задержкой для operator_value
            // v4.1.0-beta8: CRITICAL FIX - Use saved values instead of config.*
            if (savedConditionalLogic) {
            this.container.find(".yfgp-enable-conditional").prop("checked", true);
            this.container.find(".yfgp-conditional-config").show();

            if (savedOperator) {
              const $operatorSelect = this.container.find(".yfgp-operator");
              let normalizedOperator = savedOperator;

              if (savedOperator === "?" || savedOperator === "∈") {
                normalizedOperator = "in_list";
              } else if (savedOperator === "∉") {
                normalizedOperator = "not_in_list";
              }

              if (
                normalizedOperator === "in_list" ||
                normalizedOperator === "not_in_list"
              ) {
                const $listOptions = $operatorSelect
                  .find("optgroup")
                  .filter((_, group) => {
                    const label = (
                      group.getAttribute("label") || ""
                    ).toLowerCase();
                    return label.indexOf("list") !== -1;
                  })
                  .find("option");
                const targetIndex = normalizedOperator === "in_list" ? 0 : 1;
                const $targetOption = $listOptions.eq(targetIndex);
                $operatorSelect.find("option").prop("selected", false);
                if ($targetOption.length) {
                  $targetOption.prop("selected", true);
                } else {
                  $operatorSelect.val(normalizedOperator);
                }
              } else {
                $operatorSelect.val(normalizedOperator);
              }

              this.currentConfig.operator = normalizedOperator;
              this.container.find(".yfgp-operator").trigger("change");
            }

            // v4.1.0-beta3: Increased delay for operator_value restoration (field might not be visible yet)
            setTimeout(() => {
              logDebug(
                "🔧 [Conditional] Attempting to restore operator_value for:",
                this.fieldName
              );
              logDebug("  ↳ savedOperatorValue:", savedOperatorValue);

              if (savedOperatorValue) {
                const $operatorValue = this.container.find(
                  ".yfgp-operator-value"
                );
                logDebug(
                  "  ↳ Found .yfgp-operator-value element?",
                  $operatorValue.length > 0
                );
                logDebug(
                  "  ↳ Element visible?",
                  $operatorValue.is(":visible")
                );
                logDebug(
                  "  ↳ Element parent .yfgp-operator-value-row display:",
                  $operatorValue.parent(".yfgp-operator-value-row").css("display")
                );

                $operatorValue.val(savedOperatorValue);
                this.currentConfig.operator_value = savedOperatorValue;
                this.saveConfig();

                logDebug("  ↳ Value set to:", $operatorValue.val());
                logDebug("  ↳ Expected:", savedOperatorValue);
                logDebug("  ↳ Match?", $operatorValue.val() === savedOperatorValue);
              }
            }, 300);
            }

            this.updatePreview();
          }, 150); // v4.18.32: Close setTimeout callback
      }); // Close requestAnimationFrame
    }

    /**
     * Получить текущую конфигурацию
     */
    getValue() {
      return this.currentConfig;
    }

    /**
     * Установить значение конфигурации
     */
    setValue(config) {
      this.currentConfig = $.extend(this.getDefaultConfig(), config);
      this.restoreValue();
    }

    // ==========================================
    // v3.2.5: Repeater Subfields Event System (Task #11)
    // ==========================================

    /**
     * Проверить является ли это главное repeater поле
     * @returns {boolean}
     */
    isMainRepeaterField() {
      return (
        this.options.fieldName &&
        this.options.fieldName.includes("_repeater_field")
      );
    }

    /**
     * Получить ID parent field из field name
     * @returns {string} Parent field ID (например: "education")
     */
    getParentFieldId() {
      if (!this.options.fieldName) return "";

      // fieldName может быть в формате "yfgp_field_mapping_v3[education_repeater_field]"
      // Извлекаем: education_repeater_field → education
      const match = this.options.fieldName.match(/\[([^\]]+)_repeater_field\]/);
      if (match) {
        return match[1]; // Возвращаем "education"
      }

      // Fallback: простая замена (для других форматов)
      return this.options.fieldName.replace("_repeater_field", "");
    }

    /**
     * Проверить является ли это subfield (имеет parent)
     * @returns {boolean}
     */
    hasParentField() {
      return this.parentField !== null && this.parentField !== undefined;
    }

    /**
     * Trigger event при изменении repeater field
     */
    broadcastRepeaterChange() {
      try {
        const eventData = {
          parentField: this.getParentFieldId(),
          repeaterFieldName: this.currentConfig.source_field,
          sourceType: this.currentConfig.source_type,
          timestamp: Date.now(),
        };

        logDebug("[YFGP Event] Broadcasting repeater-changed:", eventData);
        $(document).trigger("yfgp:repeater-changed", eventData);
      } catch (error) {
        console.error("[YFGP Event] Broadcast failed:", error);
        // Don't block UI
      }
    }

    /**
     * Setup event listener для subfields
     */
    setupParentListener() {
      $(document).on(
        "yfgp:repeater-changed",
        this.onRepeaterParentChanged.bind(this)
      );
      logDebug(
        "[YFGP Event] Subfield listening for parent:",
        this.parentField
      );
    }

    /**
     * Handle parent repeater field change event
     * @param {Event} event jQuery event
     * @param {Object} data Event data
     */
    onRepeaterParentChanged(event, data) {
      try {
        // Check if this event is for our parent
        if (data.parentField !== this.parentField) {
          return; // Not our parent
        }

        logDebug(
          "[YFGP Event] Subfield received event for parent:",
          data.parentField
        );

        // Load subfields для этого repeater
        this.loadRepeaterSubfields(data);
      } catch (error) {
        console.error("[YFGP Event] Listener error:", error);
        // Fallback to standard logic
        this.loadAvailableFields();
      }
    }

    /**
     * Проверить initial state parent поля (late binding)
     */
    checkParentInitialState(retryCount = 0) {
      if (!this.parentField) return;

      // Find parent repeater field CONTAINER
      const $parentFieldContainer = $(
        `[data-field-id="${this.parentField}_repeater_field"]`
      );

      if ($parentFieldContainer.length === 0) {
        logDebug("[YFGP Init] Parent field not found:", this.parentField);
        return;
      }

      // ИСПРАВЛЕНИЕ v3.2.9: Find WRAPPER inside container (instance хранится на wrapper'е!)
      const $parentWrapper = $parentFieldContainer.find(
        ".yfgp-dynamic-selector-wrapper"
      );

      if ($parentWrapper.length === 0) {
        logDebug("[YFGP Init] Parent wrapper not found");
        return;
      }

      // Get parent instance from WRAPPER
      const parentInstance = $parentWrapper.data("dynamicFieldSelectorV3");

      if (!parentInstance) {
        // ИСПРАВЛЕНИЕ v3.2.8: Retry mechanism для race condition
        if (retryCount < 5) {
          logDebug(
            "[YFGP Init] Parent instance not initialized yet, retrying in 200ms..."
          );
          setTimeout(() => this.checkParentInitialState(retryCount + 1), 200);
        } else {
          logDebug("[YFGP Init] Parent instance not found after 5 retries");
        }
        return;
      }

      // Check if parent has value
      const parentConfig = parentInstance.currentConfig;

      if (parentConfig.source_field && parentConfig.source_type) {
        logDebug("[YFGP Init] Parent already has value, loading subfields");

        const eventData = {
          parentField: this.parentField,
          repeaterFieldName: parentConfig.source_field,
          sourceType: parentConfig.source_type,
          timestamp: Date.now(),
        };

        this.loadRepeaterSubfields(eventData);
      }
    }

    /**
     * Load repeater subfields через AJAX с кэшированием
     * @param {Object} data Event data {parentField, repeaterFieldName, sourceType}
     */
    async loadRepeaterSubfields(data) {
      const cacheKey = `${data.sourceType}_${data.repeaterFieldName}`;
      const cacheTTL = 5 * 60 * 1000; // 5 minutes

      // v4.1.0-beta14: Check SHARED cache first (class-level, not instance-level)
      const CacheClass = DynamicFieldSelectorV3;

      if (this.isSharedCacheValid(cacheKey, cacheTTL)) {
        logDebug(
          "[YFGP Cache] SHARED HIT:",
          cacheKey,
          "for",
          this.fieldName
        );
        const cachedSubfields = CacheClass.sharedSubfieldCache.get(cacheKey);
        this.updateSubfieldDropdown(cachedSubfields, data);
        return;
      }

      // v4.1.0-beta14: Check if ANOTHER instance is already loading this repeater
      if (CacheClass.pendingAjaxRequests.has(cacheKey)) {
        logDebug(
          "[YFGP AJAX] Already loading by another instance, waiting...",
          cacheKey,
          "for",
          this.fieldName
        );

        // Wait for the existing request to complete
        try {
          const subfields = await CacheClass.pendingAjaxRequests.get(cacheKey);
          this.updateSubfieldDropdown(subfields, data);
          return;
        } catch (error) {
          console.error("[YFGP AJAX] Shared request failed:", error);
          this.showEmptyState("⚠️ Не удалось загрузить подполя");
          return;
        }
      }

      // Cache MISS → Start NEW AJAX request
      logDebug(
        "[YFGP Cache] SHARED MISS, loading via AJAX:",
        cacheKey,
        "for",
        this.fieldName
      );

      // Create Promise and store it in pendingRequests BEFORE starting AJAX
      const ajaxPromise = this.fetchRepeaterSubfields(data)
        .then((subfields) => {
          // Store in SHARED cache
          CacheClass.sharedSubfieldCache.set(cacheKey, subfields);
          CacheClass.sharedCacheTimestamps.set(cacheKey, Date.now());

          logDebug(
            "[YFGP Cache] SHARED Stored:",
            cacheKey,
            subfields.length,
            "subfields"
          );

          return subfields;
        })
        .finally(() => {
          // Remove from pending requests when done
          CacheClass.pendingAjaxRequests.delete(cacheKey);
        });

      // Store Promise so other instances can wait for it
      CacheClass.pendingAjaxRequests.set(cacheKey, ajaxPromise);

      try {
        const subfields = await ajaxPromise;
        this.updateSubfieldDropdown(subfields, data);
      } catch (error) {
        console.error("[YFGP Load] Failed to load subfields:", error);
        this.showEmptyState("⚠️ Не удалось загрузить подполя");
      }
    }

    /**
     * Fetch repeater subfields via AJAX (v3.3.7: 60s timeout for first request)
     * @param {Object} data Event data
     * @returns {Promise<Array>} Subfields array
     */
    async fetchRepeaterSubfields(data) {
      return new Promise((resolve, reject) => {
        $.ajax({
          url: yfgpAjax.ajax_url,
          type: "POST",
          data: {
            action: "yfgp_get_repeater_subfields",
            nonce: yfgpAjax.nonce,
            repeater_field_name: data.repeaterFieldName,
            source_type: data.sourceType,
            post_type: this.options.postType,
          },
          dataType: 'text', // v4.18.11: Используем 'text' для обработки BOM
          timeout: 90000, // v3.4.0: 90s for Docker dev (production 1-5s!). After first request, transient cache works!
          success: (responseText, textStatus, xhr) => {
            // v4.18.11: Используем xhr.responseText напрямую для получения сырого ответа
            let rawResponse = xhr.responseText || responseText;
            
            // v4.18.11: Remove UTF-8 BOM (U+FEFF) - удаляем ВСЕ BOM подряд в цикле
            if (typeof rawResponse === 'string' && rawResponse.length > 0) {
              // Удаляем ВСЕ BOM подряд (может быть несколько!)
              while (rawResponse.length > 0 && (
                rawResponse.charCodeAt(0) === 0xFEFF || 
                rawResponse.charCodeAt(0) === 65279 ||
                rawResponse.substring(0, 3) === '\xEF\xBB\xBF'
              )) {
                if (rawResponse.substring(0, 3) === '\xEF\xBB\xBF') {
                  rawResponse = rawResponse.substring(3);
                } else {
                  rawResponse = rawResponse.slice(1);
                }
              }
            }
            let parsedResponse;
            try {
              parsedResponse = JSON.parse(rawResponse);
            } catch (e) {
              console.error("Failed to parse response:", e, rawResponse.substring(0, 100));
              reject(new Error("Invalid response format"));
              return;
            }
            
            if (parsedResponse && parsedResponse.success && Array.isArray(parsedResponse.data)) {
              logDebug(
                "[YFGP AJAX] Loaded",
                parsedResponse.data.length,
                "subfields"
              );
              resolve(parsedResponse.data);
            } else {
              reject(new Error(parsedResponse?.data || "Invalid response"));
            }
          },
          error: (xhr, status, error) => {
            console.error("[YFGP AJAX] Request failed:", error);
            console.error("[YFGP AJAX] Status:", status);
            console.error("[YFGP AJAX] Response:", xhr.responseText);
            let errorMessage = error || "AJAX request failed";
            try {
              if (xhr.responseText) {
                const parsed = JSON.parse(xhr.responseText);
                errorMessage = parsed.data?.message || parsed.message || errorMessage;
              }
            } catch (e) {
              // Ignore parse errors
            }
            reject(new Error(errorMessage));
          },
        });
      });
    }

    /**
     * Update dropdown with repeater subfields
     * @param {Array} subfields Subfields array {label, slug, type}[]
     * @param {Object} data Event data
     */
    updateSubfieldDropdown(subfields, data) {
      try {
        if (!Array.isArray(subfields) || subfields.length === 0) {
          this.showEmptyState("ℹ️ У этого repeater поля нет подполей");
          return;
        }

        // v3.3.9: FIX БАГ! Искать dropdown СТРОГО внутри .yfgp-dynamic-field-v3
        const $field = this.container.children(".yfgp-dynamic-field-v3");
        const $dropdown = $field.find(".yfgp-source-field").first();

        if ($dropdown.length === 0) {
          console.warn("[YFGP Dropdown] Dropdown not found in field container");
          return;
        }

        // Clear current options
        $dropdown.empty();

        // Add placeholder
        $dropdown.append('<option value="">-- Выберите подполе --</option>');

        // Add optgroup with subfields
        const $optgroup = $("<optgroup></optgroup>").attr(
          "label",
          `Подполя репитора "${data.repeaterFieldName}"`
        );

        subfields.forEach((subfield) => {
          const $option = $("<option></option>")
            .val(subfield.slug)
            .text(subfield.label);

          $optgroup.append($option);
        });

        $dropdown.append($optgroup);

        // Restore selected value if exists
        if (this.currentConfig.source_field) {
          $dropdown.val(this.currentConfig.source_field);
        }

        logDebug(
          "[YFGP Dropdown] Updated with",
          subfields.length,
          "subfields"
        );
      } catch (error) {
        console.error("[YFGP Dropdown] Update failed:", error);
        this.showEmptyState("⚠️ Ошибка обновления dropdown");
      }
    }

    /**
     * Check if cache is valid
     * @param {string} cacheKey Cache key
     * @param {number} ttl Time to live in milliseconds
     * @returns {boolean}
     */
    isCacheValid(cacheKey, ttl) {
      if (!this.subfieldCache.has(cacheKey)) {
        return false;
      }

      const timestamp = this.cacheTimestamps.get(cacheKey);
      const age = Date.now() - timestamp;

      return age < ttl;
    }

    /**
     * v4.1.0-beta14: Check if SHARED cache is valid (class-level cache)
     * @param {string} cacheKey Cache key
     * @param {number} ttl Time to live in milliseconds
     * @returns {boolean}
     */
    isSharedCacheValid(cacheKey, ttl) {
      const CacheClass = DynamicFieldSelectorV3;

      if (!CacheClass.sharedSubfieldCache.has(cacheKey)) {
        return false;
      }

      const timestamp = CacheClass.sharedCacheTimestamps.get(cacheKey);
      const age = Date.now() - timestamp;

      return age < ttl;
    }

    /**
     * Show empty state message
     * @param {string} message Message to display
     */
    showEmptyState(message) {
      // v3.3.9: FIX БАГ! Искать dropdown СТРОГО внутри .yfgp-dynamic-field-v3
      // (не в родительских repeater field dropdown'ах!)
      const $field = this.container.children(".yfgp-dynamic-field-v3");
      const $dropdown = $field.find(".yfgp-source-field").first();

      if ($dropdown.length > 0) {
        $dropdown.empty();
        $dropdown.append(`<option value="">${message}</option>`);
        $dropdown.prop("disabled", true);
      } else {
        console.warn(
          "[YFGP] showEmptyState: dropdown not found in field container"
        );
      }
    }
  }

  /**
   * jQuery plugin
   */
  // v4.18.22: Debug - verify plugin is being defined
  logDebug("[YFGP Debug] Defining jQuery plugin dynamicFieldSelectorV3, $.fn available:", typeof $.fn !== 'undefined');
  $.fn.dynamicFieldSelectorV3 = function (options) {
    return this.each(function () {
      const $this = $(this);
      if (!$this.data("dynamicFieldSelectorV3")) {
        $this.data(
          "dynamicFieldSelectorV3",
          new DynamicFieldSelectorV3(this, options)
        );
      }
    });
  };

  /**
   * Глобальная инициализация для всех полей с data-field-selector-v3
   */
  $(document).ready(function () {
    $("[data-field-selector-v3]").each(function () {
      const $this = $(this);
      const fieldName = $this.data("field-name");
      const postType = $this.data("post-type") || "doctors";
      const currentValue = $this.data("current-value");
      const isRepeaterField = $this.data("is-repeater-field") || false;

      $this.dynamicFieldSelectorV3({
        fieldName: fieldName,
        postType: postType,
        currentValue: currentValue,
        isRepeaterField: isRepeaterField,
      });
    });

    logDebug("Dynamic Field Selector V3 инициализирован!");
  });
  
    // v4.18.22: Debug - verify plugin is available after definition
    logDebug("[YFGP Debug] jQuery plugin defined, available:", typeof $.fn.dynamicFieldSelectorV3 === "function");
  })(jQuery);
} catch (error) {
  console.error("[YFGP Debug] ❌ IIFE Error:", error);
  console.error("[YFGP Debug] Error stack:", error.stack);
}

const yfgpGlobalDebug = (...args) => {
  const queryValue = getDebugQueryParam();
  if (queryValue !== null) {
    if (queryValue !== "1") {
      return;
    }
    if (typeof console !== "undefined" && typeof console.debug === "function") {
      console.debug(...args);
      return;
    }
  }
  if (
    typeof window !== "undefined" &&
    window.yfgpLog &&
    typeof window.yfgpLog.debug === "function"
  ) {
    window.yfgpLog.debug(...args);
    return;
  }
  if (typeof console !== "undefined" && typeof console.debug === "function") {
    console.debug(...args);
  }
};

// ==========================================
// v3.4.1: Global Mapping Data Collection (Task #13 - Production-Ready Save)
// ==========================================

/**
 * Собрать ВСЕ данные маппинга с формы для сохранения через JSON
 * Includes: field configs + offer inheritance settings
 *
 * @returns {Object} {fields: {...}, settings: {...}}
 */
window.collectAllMappingData = function () {
  yfgpGlobalDebug("[YFGP Save] Collecting all mapping data...");

  const mappingData = {
    fields: {},
    settings: {},
  };

  // 1. Собрать ВСЕ field configurations от Dynamic Field Selectors
  jQuery("[data-field-selector-v3]").each(function () {
    let fieldName = jQuery(this).data("field-name");

    // КРИТИЧНО: Убираем префикс yfgp_field_mapping_v3[...] → чистый ID
    if (fieldName && fieldName.includes("[") && fieldName.includes("]")) {
      const match = fieldName.match(/\[([^\]]+)\]/);
      if (match) {
        fieldName = match[1];
      }
    }

    const instance = jQuery(this).data("dynamicFieldSelectorV3");
    if (instance && instance.currentConfig) {
      mappingData.fields[fieldName] = instance.currentConfig;
    }
  });

  // 2. v3.4.1: Собрать offer inheritance settings (radio buttons)
  jQuery('.inheritance-toggle-wrapper input[type="radio"]:checked').each(
    function () {
      const name = jQuery(this).attr("name");
      const value = jQuery(this).val();

      if (name) {
        mappingData.settings[name] = value;
      }
    }
  );

  // 3. v3.4.1: Собрать hidden fields для offer inheritance (inherit_from)
  jQuery('input[name$="_inherit_from"]').each(function () {
    const name = jQuery(this).attr("name");
    const value = jQuery(this).val();

    if (name) {
      mappingData.settings[name] = value;
    }
  });

  yfgpGlobalDebug(
    "[YFGP Save] Collected:",
    Object.keys(mappingData.fields).length,
    "fields,",
    Object.keys(mappingData.settings).length,
    "settings"
  );

  return mappingData;
};

/**
 * Form submit handler - JSON serialization
 */
jQuery(document).ready(function ($) {
  // v3.4.1: Submit handler для формы маппинга
  $("form#yfgp-mapping-form-v3").on("submit", function (e) {
    yfgpGlobalDebug("[YFGP Save] Form submit triggered");

    try {
      // Собрать данные
      const mappingData = window.collectAllMappingData();

      // Валидация: проверить что есть хоть какие-то данные
      if (
        Object.keys(mappingData.fields).length === 0 &&
        Object.keys(mappingData.settings).length === 0
      ) {
        alert("⚠️ Нет данных для сохранения! Настройте хотя бы одно поле.");
        e.preventDefault();
        return false;
      }

      // JSON serialization
      const jsonString = JSON.stringify(mappingData);

      // v4.18.22: Security - Client-side validation of mapping size (DoS protection)
      const MAX_MAPPING_SIZE = 1048576; // 1MB (matches server-side limit)
      const mappingSize = new Blob([jsonString]).size;
      
      if (mappingSize > MAX_MAPPING_SIZE) {
        const sizeMB = (mappingSize / (1024 * 1024)).toFixed(2);
        const maxMB = (MAX_MAPPING_SIZE / (1024 * 1024)).toFixed(2);
        alert("⚠️ Размер маппинга (" + sizeMB + " MB) превышает максимальный лимит (" + maxMB + " MB).\n\nПожалуйста, уменьшите количество данных или обратитесь к администратору.");
        e.preventDefault();
        return false;
      }

      yfgpGlobalDebug("[YFGP Save] JSON size:", jsonString.length, "bytes");
      yfgpGlobalDebug(
        "[YFGP Save] JSON preview:",
        jsonString.substring(0, 200) + "..."
      );

      // Заполнить hidden input
      $("#yfgp_mapping_json").val(jsonString);

      yfgpGlobalDebug("[YFGP Save] JSON готов к отправке!");

      // Form submit продолжится нормально
      return true;
    } catch (error) {
      console.error("[YFGP Save] Error collecting data:", error);
      alert("❌ Ошибка при сборе данных: " + error.message);
      e.preventDefault();
      return false;
    }
  });

  yfgpGlobalDebug(
    "[YFGP Save] Submit handler registered for #yfgp-mapping-form-v3"
  );

  // ==========================================
  // v4.11.0: Test Preview Post Selector
  // ==========================================

  /**
   * Загрузить список постов для Test Preview
   * @param {string} tabType - doctors, clinics, services, offers
   */
  function loadTestPostsList(tabType) {
    yfgpGlobalDebug("[YFGP Test] Loading posts list for tab:", tabType);

    const $selector = $("#yfgp-test-post-" + tabType);

    // Показать loading state
    $selector.html('<option value="">⏳ Загрузка...</option>');

    $.ajax({
      url: typeof yfgpAjax !== 'undefined' ? yfgpAjax.ajax_url : ajaxurl, // v4.18.21: Используем yfgpAjax.ajax_url для консистентности
      type: "POST",
      data: {
        action: "yfgp_get_posts_list",
        tab_type: tabType,
        nonce: typeof yfgpAjax !== 'undefined' ? yfgpAjax.nonce : '', // v4.18.21: Используем единый nonce для всех AJAX handlers
      },
      success: function (response) {
        if (response.success && response.data.posts) {
          const posts = response.data.posts;
          const postType = response.data.post_type;

          // Создать опции
          let options =
            '<option value="">-- Выберите ' +
            getPostTypeLabel(tabType) +
            " для теста --</option>";
          posts.forEach(function (post) {
            options +=
              '<option value="' + post.id + '">' + post.title + "</option>";
          });

          // v4.18.22: Security - Sanitize HTML to prevent XSS
          $selector.html(sanitizeHtml(options));
          yfgpGlobalDebug("[YFGP Test] Loaded", posts.length, "posts for", tabType);
        } else {
          $selector.html('<option value="">❌ Ошибка загрузки</option>');
          console.error("[YFGP Test] Error:", response);
        }
      },
      error: function (xhr, status, error) {
        $selector.html('<option value="">❌ Ошибка AJAX</option>');
        console.error("[YFGP Test] AJAX error:", error);
      },
    });
  }

  /**
   * Получить label для post type
   */
  function getPostTypeLabel(tabType) {
    const labels = {
      doctors: "врача",
      clinics: "клинику",
      services: "услугу",
      offers: "врача",
    };
    return labels[tabType] || "пост";
  }

  /**
   * Показать/скрыть нужный dropdown при переключении таба
   * v4.18.21: Исправлен селектор для .yfgp-mega-tab-wrapper и regex для #tab-* формата
   */
  function updateTestPostSelector() {
    // Определить активный таб (используем .yfgp-mega-tab-wrapper вместо .nav-tab-wrapper)
    const $activeTab = $(".yfgp-mega-tab-wrapper .nav-tab-active");
    if ($activeTab.length === 0) return;

    const activeTabHref = $activeTab.attr("href");
    if (!activeTabHref) return;

    // Извлечь tab ID (например: #tab-doctors → doctors)
    // v4.18.21: Исправлен regex для формата #tab-* (было #*-tab)
    const tabMatch = activeTabHref.match(/#tab-(\w+)/);
    if (!tabMatch) {
      console.warn("[YFGP Test] Could not extract tab ID from href:", activeTabHref);
      return;
    }

    const activeTab = tabMatch[1];
    yfgpGlobalDebug("[YFGP Test] Active tab:", activeTab);

    // Скрыть все dropdowns
    $(".yfgp-test-post-selector").hide();

    // Показать нужный dropdown
    const $activeSelector = $("#yfgp-test-post-" + activeTab);
    if ($activeSelector.length > 0) {
      $activeSelector.show();

      // Загрузить список постов если ещё не загружен
      if ($activeSelector.find("option").length <= 1) {
        loadTestPostsList(activeTab);
      }
    } else {
      console.warn("[YFGP Test] Selector not found for tab:", activeTab);
    }
  }

  // v4.11.0: Test button handler находится в field-mapping-v3.php (inline script)
  // Здесь только логика dropdown (loadTestPostsList, updateTestPostSelector)

  /**
   * Event listener для переключения табов
   * v4.18.21: Исправлен селектор для .yfgp-mega-tab-wrapper
   */
  $(".yfgp-mega-tab-wrapper .nav-tab").on("click", function () {
    // Небольшая задержка чтобы WordPress успел обновить .nav-tab-active
    setTimeout(updateTestPostSelector, 50);
  });

  // Инициализация при загрузке страницы
  updateTestPostSelector();

  yfgpGlobalDebug("[YFGP Test] Test Preview Post Selector initialized");
});
