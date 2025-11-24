/**
 * Компонент выбора динамического поля
 * Поддерживает цепочки связей любой глубины
 */

(function ($) {
  "use strict";

  /**
   * Класс для работы с динамическими полями
   */
  class DynamicFieldSelector {
    constructor(container, options) {
      this.container = $(container);
      this.options = $.extend(
        {
          postType: "doctors",
          fieldName: "",
          currentValue: null,
          onChange: null,
        },
        options
      );

      this.availableFields = {};
      this.init();
    }

    /**
     * Инициализация
     */
    async init() {
      await this.loadAvailableFields();
      this.render();
      this.bindEvents();

      // Загружаем сохраненное значение
      if (this.options.currentValue) {
        this.setValue(this.options.currentValue);
      }
    }

    /**
     * Загрузка доступных полей
     */
    async loadAvailableFields() {
      return new Promise((resolve, reject) => {
        $.ajax({
          url: yfgpAjax.ajax_url,
          type: "POST",
          data: {
            action: "yfgp_get_fields",
            nonce: yfgpAjax.nonce,
            post_type: this.options.postType,
          },
          success: (response) => {
            if (response.success) {
              this.availableFields = response.data;
              resolve();
            } else {
              reject(response.data);
            }
          },
          error: reject,
        });
      });
    }

    /**
     * Рендеринг компонента
     */
    render() {
      const html = `
                <div class="yfgp-dynamic-field">
                    <div class="yfgp-field-row">
                        <label class="yfgp-field-label">
                            <span class="label-text">Источник данных:</span>
                            <select class="yfgp-source-type">
                                <option value="">-- Не выбрано --</option>
                                <option value="direct">Прямое поле</option>
                                <option value="relation_1">Через связь (1 уровень)</option>
                                <option value="relation_2">Через связь (2 уровня)</option>
                                <option value="custom">Произвольное значение</option>
                            </select>
                        </label>
                    </div>
                    
                    <!-- Прямое поле -->
                    <div class="yfgp-field-row yfgp-direct-field" style="display: none;">
                        <label class="yfgp-field-label">
                            <span class="label-text">Поле:</span>
                            <select class="yfgp-direct-select">
                                <option value="">-- Выберите поле --</option>
                                ${this.renderFieldOptions()}
                            </select>
                        </label>
                    </div>
                    
                    <!-- Связь 1 уровня -->
                    <div class="yfgp-relation-1-field" style="display: none;">
                        <div class="yfgp-field-row">
                            <label class="yfgp-field-label">
                                <span class="label-text">1. Поле связи:</span>
                                <select class="yfgp-relation-1-source">
                                    <option value="">-- Выберите поле связи --</option>
                                    ${this.renderRelationshipOptions()}
                                </select>
                            </label>
                        </div>
                        <div class="yfgp-field-row">
                            <label class="yfgp-field-label">
                                <span class="label-text">2. Целевой тип записи:</span>
                                <select class="yfgp-relation-1-post-type">
                                    <option value="">-- Выберите тип --</option>
                                </select>
                            </label>
                        </div>
                        <div class="yfgp-field-row">
                            <label class="yfgp-field-label">
                                <span class="label-text">3. Поле для извлечения:</span>
                                <select class="yfgp-relation-1-target">
                                    <option value="">-- Выберите поле --</option>
                                </select>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Связь 2 уровня -->
                    <div class="yfgp-relation-2-field" style="display: none;">
                        <div class="yfgp-field-row">
                            <label class="yfgp-field-label">
                                <span class="label-text">1. Первая связь:</span>
                                <select class="yfgp-relation-2-source-1">
                                    <option value="">-- Выберите поле связи --</option>
                                    ${this.renderRelationshipOptions()}
                                </select>
                            </label>
                        </div>
                        <div class="yfgp-field-row">
                            <label class="yfgp-field-label">
                                <span class="label-text">2. Тип 1 уровня:</span>
                                <select class="yfgp-relation-2-post-type-1">
                                    <option value="">-- Выберите тип --</option>
                                </select>
                            </label>
                        </div>
                        <div class="yfgp-field-row">
                            <label class="yfgp-field-label">
                                <span class="label-text">3. Вторая связь:</span>
                                <select class="yfgp-relation-2-source-2">
                                    <option value="">-- Выберите поле связи --</option>
                                </select>
                            </label>
                        </div>
                        <div class="yfgp-field-row">
                            <label class="yfgp-field-label">
                                <span class="label-text">4. Тип 2 уровня:</span>
                                <select class="yfgp-relation-2-post-type-2">
                                    <option value="">-- Выберите тип --</option>
                                </select>
                            </label>
                        </div>
                        <div class="yfgp-field-row">
                            <label class="yfgp-field-label">
                                <span class="label-text">5. Поле для извлечения:</span>
                                <select class="yfgp-relation-2-target">
                                    <option value="">-- Выберите поле --</option>
                                </select>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Произвольное значение -->
                    <div class="yfgp-field-row yfgp-custom-field" style="display: none;">
                        <label class="yfgp-field-label">
                            <span class="label-text">Значение:</span>
                            <input type="text" class="yfgp-custom-value regular-text" placeholder="Введите значение по умолчанию">
                        </label>
                    </div>
                    
                    <!-- Превью значения -->
                    <div class="yfgp-field-preview" style="display: none; margin-top: 10px;">
                        <div class="yfgp-preview-box">
                            <strong>Структура:</strong>
                            <pre class="yfgp-preview-code"></pre>
                        </div>
                    </div>
                </div>
            `;

      this.container.html(html);
    }

    /**
     * Рендеринг опций полей
     */
    renderFieldOptions() {
      let html = "";

      Object.keys(this.availableFields).forEach((groupKey) => {
        const group = this.availableFields[groupKey];
        if (Object.keys(group).length === 0) return;

        html += `<optgroup label="${this.getGroupLabel(groupKey)}">`;

        Object.keys(group).forEach((fieldKey) => {
          const field = group[fieldKey];
          html += `<option value="${fieldKey}" data-type="${field.type}">${field.label} (${field.type})</option>`;
        });

        html += "</optgroup>";
      });

      return html;
    }

    /**
     * Рендеринг опций связей
     */
    renderRelationshipOptions() {
      let html = "";

      if (this.availableFields.relations) {
        Object.keys(this.availableFields.relations).forEach((fieldKey) => {
          const field = this.availableFields.relations[fieldKey];
          const postTypes = Array.isArray(field.post_type)
            ? field.post_type.join(", ")
            : field.post_type;
          html += `<option value="${fieldKey}" data-post-types="${postTypes}">${field.label} → ${postTypes}</option>`;
        });
      }

      return html;
    }

    /**
     * Получить название группы
     */
    getGroupLabel(groupKey) {
      const labels = {
        wordpress: "WordPress (стандартные)",
        acf: "ACF",
        jetengine: "JetEngine",
        meta: "Мета-поля",
        relations: "Связи",
        repeater: "Repeater (массивы)", // v2.3.2
        taxonomy: "Таксономии", // v2.3.2
      };
      return labels[groupKey] || groupKey;
    }

    /**
     * Привязка событий
     */
    bindEvents() {
      const self = this;

      // Изменение типа источника
      this.container.find(".yfgp-source-type").on("change", function () {
        const sourceType = $(this).val();
        self.showSourceFields(sourceType);
      });

      // Изменение поля связи (1 уровень)
      this.container.find(".yfgp-relation-1-source").on("change", function () {
        const postTypes = $(this).find("option:selected").data("post-types");
        self.loadPostTypeFields(
          postTypes,
          self.container.find(".yfgp-relation-1-post-type"),
          self.container.find(".yfgp-relation-1-target")
        );
      });

      // Изменение типа поста (1 уровень)
      this.container
        .find(".yfgp-relation-1-post-type")
        .on("change", function () {
          const postType = $(this).val();
          if (postType) {
            self.loadFieldsForPostType(
              postType,
              self.container.find(".yfgp-relation-1-target")
            );
          }
        });

      // Аналогично для 2 уровня
      this.container
        .find(".yfgp-relation-2-source-1")
        .on("change", async function () {
          const postTypes = $(this).find("option:selected").data("post-types");
          self.loadPostTypeFields(
            postTypes,
            self.container.find(".yfgp-relation-2-post-type-1"),
            null
          );

          // ✅ ИСПРАВЛЕНИЕ: Автоматически загружаем связи после выбора первой связи
          if (postTypes) {
            const types = postTypes.split(",").map((t) => t.trim());
            if (types.length === 1) {
              // Если только один тип - сразу загружаем связи
              await self.loadRelationshipsForPostType(
                types[0],
                self.container.find(".yfgp-relation-2-source-2")
              );
            }
          }
        });

      this.container
        .find(".yfgp-relation-2-post-type-1")
        .on("change", function () {
          const postType = $(this).val();
          if (postType) {
            self.loadRelationshipsForPostType(
              postType,
              self.container.find(".yfgp-relation-2-source-2")
            );
          }
        });

      this.container
        .find(".yfgp-relation-2-source-2")
        .on("change", function () {
          const postTypes = $(this).find("option:selected").data("post-types");
          self.loadPostTypeFields(
            postTypes,
            self.container.find(".yfgp-relation-2-post-type-2"),
            self.container.find(".yfgp-relation-2-target")
          );
        });

      this.container
        .find(".yfgp-relation-2-post-type-2")
        .on("change", function () {
          const postType = $(this).val();
          if (postType) {
            self.loadFieldsForPostType(
              postType,
              self.container.find(".yfgp-relation-2-target")
            );
          }
        });

      // Обновление превью при изменении любого поля
      this.container.find("select, input").on("change input", function () {
        self.updatePreview();
        if (self.options.onChange) {
          self.options.onChange(self.getValue());
        }
      });
    }

    /**
     * Показать поля источника
     */
    showSourceFields(sourceType) {
      this.container.find(".yfgp-direct-field").hide();
      this.container.find(".yfgp-relation-1-field").hide();
      this.container.find(".yfgp-relation-2-field").hide();
      this.container.find(".yfgp-custom-field").hide();
      this.container.find(".yfgp-field-preview").hide();

      switch (sourceType) {
        case "direct":
          this.container.find(".yfgp-direct-field").show();
          this.container.find(".yfgp-field-preview").show();
          break;
        case "relation_1":
          this.container.find(".yfgp-relation-1-field").show();
          this.container.find(".yfgp-field-preview").show();
          break;
        case "relation_2":
          this.container.find(".yfgp-relation-2-field").show();
          this.container.find(".yfgp-field-preview").show();
          break;
        case "custom":
          this.container.find(".yfgp-custom-field").show();
          break;
      }

      this.updatePreview();
    }

    /**
     * Загрузить список типов постов
     */
    loadPostTypeFields(postTypes, targetSelect, fieldsSelect) {
      if (!postTypes) return;

      const types = postTypes.split(",").map((t) => t.trim());
      targetSelect
        .empty()
        .append('<option value="">-- Выберите тип --</option>');

      types.forEach((type) => {
        targetSelect.append(`<option value="${type}">${type}</option>`);
      });
    }

    /**
     * Загрузить поля для типа поста
     */
    async loadFieldsForPostType(postType, targetSelect) {
      return new Promise((resolve, reject) => {
        $.ajax({
          url: yfgpAjax.ajax_url,
          type: "POST",
          data: {
            action: "yfgp_get_fields",
            nonce: yfgpAjax.nonce,
            post_type: postType,
          },
          success: (response) => {
            if (response.success) {
              this.populateFieldSelect(targetSelect, response.data);
              resolve();
            } else {
              reject(response.data);
            }
          },
          error: reject,
        });
      });
    }

    /**
     * Загрузить связи для типа поста
     */
    async loadRelationshipsForPostType(postType, targetSelect) {
      console.log("🔍 Загружаем связи для типа:", postType);

      return new Promise((resolve, reject) => {
        $.ajax({
          url: yfgpAjax.ajax_url,
          type: "POST",
          data: {
            action: "yfgp_get_fields",
            nonce: yfgpAjax.nonce,
            post_type: postType,
          },
          success: (response) => {
            console.log("📡 Ответ сервера для", postType, ":", response);

            if (response.success && response.data.relations) {
              targetSelect
                .empty()
                .append('<option value="">-- Выберите связь --</option>');

              const relations = response.data.relations;
              console.log("🔗 Найдено связей:", Object.keys(relations).length);

              Object.keys(relations).forEach((fieldKey) => {
                const field = relations[fieldKey];
                const postTypes = Array.isArray(field.post_type)
                  ? field.post_type.join(", ")
                  : field.post_type;
                targetSelect.append(
                  `<option value="${fieldKey}" data-post-types="${postTypes}">${field.label} → ${postTypes}</option>`
                );
                console.log("✅ Добавлена связь:", fieldKey, "→", postTypes);
              });

              resolve();
            } else {
              console.log("❌ Нет связей для типа:", postType);
              console.log("📊 Данные ответа:", response.data);
              targetSelect
                .empty()
                .append('<option value="">-- Нет связей --</option>');
              resolve(); // Не reject, чтобы не ломать интерфейс
            }
          },
          error: (xhr, status, error) => {
            console.error("❌ Ошибка AJAX для", postType, ":", error);
            targetSelect
              .empty()
              .append('<option value="">-- Ошибка загрузки --</option>');
            resolve(); // Не reject, чтобы не ломать интерфейс
          },
        });
      });
    }

    /**
     * Заполнить селект полями
     */
    populateFieldSelect(select, fields) {
      select.empty().append('<option value="">-- Выберите поле --</option>');

      Object.keys(fields).forEach((groupKey) => {
        const group = fields[groupKey];
        if (groupKey === "relationships") return; // Пропускаем связи
        if (Object.keys(group).length === 0) return;

        const optgroup = $("<optgroup>").attr(
          "label",
          this.getGroupLabel(groupKey)
        );

        Object.keys(group).forEach((fieldKey) => {
          const field = group[fieldKey];
          optgroup.append(
            $("<option>").val(fieldKey).text(`${field.label} (${field.type})`)
          );
        });

        select.append(optgroup);
      });
    }

    /**
     * Обновить превью
     */
    updatePreview() {
      const value = this.getValue();
      const previewBox = this.container.find(".yfgp-preview-code");

      if (value && value.type !== "custom") {
        previewBox.text(JSON.stringify(value, null, 2));
      }
    }

    /**
     * Получить значение
     */
    getValue() {
      const sourceType = this.container.find(".yfgp-source-type").val();

      if (!sourceType) return null;

      if (sourceType === "direct") {
        const field = this.container.find(".yfgp-direct-select").val();
        return field
          ? {
              type: "direct",
              field: field,
            }
          : null;
      }

      if (sourceType === "relation_1") {
        const relationField = this.container
          .find(".yfgp-relation-1-source")
          .val();
        const postType = this.container
          .find(".yfgp-relation-1-post-type")
          .val();
        const targetField = this.container
          .find(".yfgp-relation-1-target")
          .val();

        return relationField && postType && targetField
          ? {
              type: "relation_1",
              relation_field: relationField,
              post_type: postType,
              target_field: targetField,
            }
          : null;
      }

      if (sourceType === "relation_2") {
        const relationField1 = this.container
          .find(".yfgp-relation-2-source-1")
          .val();
        const postType1 = this.container
          .find(".yfgp-relation-2-post-type-1")
          .val();
        const relationField2 = this.container
          .find(".yfgp-relation-2-source-2")
          .val();
        const postType2 = this.container
          .find(".yfgp-relation-2-post-type-2")
          .val();
        const targetField = this.container
          .find(".yfgp-relation-2-target")
          .val();

        return relationField1 &&
          postType1 &&
          relationField2 &&
          postType2 &&
          targetField
          ? {
              type: "relation_2",
              relation_field_1: relationField1,
              post_type_1: postType1,
              relation_field_2: relationField2,
              post_type_2: postType2,
              target_field: targetField,
            }
          : null;
      }

      if (sourceType === "custom") {
        const customValue = this.container.find(".yfgp-custom-value").val();
        return customValue
          ? {
              type: "custom",
              value: customValue,
            }
          : null;
      }

      return null;
    }

    /**
     * Установить значение
     */
    setValue(value) {
      if (!value) return;

      this.container
        .find(".yfgp-source-type")
        .val(value.type)
        .trigger("change");

      setTimeout(() => {
        if (value.type === "direct") {
          this.container.find(".yfgp-direct-select").val(value.field);
        } else if (value.type === "relation_1") {
          this.container
            .find(".yfgp-relation-1-source")
            .val(value.relation_field)
            .trigger("change");
          setTimeout(() => {
            this.container
              .find(".yfgp-relation-1-post-type")
              .val(value.post_type)
              .trigger("change");
            setTimeout(() => {
              this.container
                .find(".yfgp-relation-1-target")
                .val(value.target_field);
            }, 100);
          }, 100);
        } else if (value.type === "relation_2") {
          // TODO: Реализовать восстановление для 2 уровня
        } else if (value.type === "custom") {
          this.container.find(".yfgp-custom-value").val(value.value);
        }

        this.updatePreview();
      }, 100);
    }
  }

  // Добавляем в глобальный объект
  window.DynamicFieldSelector = DynamicFieldSelector;
})(jQuery);
