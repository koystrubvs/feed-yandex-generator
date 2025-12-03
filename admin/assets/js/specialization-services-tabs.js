/**
 * Specialization Services Tabs - JavaScript для настройки услуг по специальностям
 *
 * Обрабатывает загрузку данных, отображение таблицы и сохранение настроек
 * для врачей с 2+ специальностями на странице маппинга полей.
 *
 * @package Yandex_Feed_Generator_Pro
 * @version 4.19.0
 */

(function ($) {
  "use strict";

  // Get post type from page context (mapping page)
  function getPostType() {
    // Primary source: Use post_type from localized script data (from plugin settings)
    // This is the ONLY reliable source - comes directly from plugin settings
    if (typeof yfgpAjax !== "undefined" && yfgpAjax.post_type) {
      return yfgpAjax.post_type;
    }

    // Fallback: Try to get from active tab context (only if yfgpAjax.post_type is not available)
    // This is a secondary fallback for edge cases
    const $activeTab = $(".yfgp-mega-tab-wrapper .nav-tab-active");
    if ($activeTab.length) {
      const tabType = $activeTab.data("tab");
      // Check if this tab type uses post_type context (not hardcoded list!)
      // Tabs that use post_type: doctors, offers, specialization-services
      // Tabs that use other CPT: clinics, services
      // We check dynamically by looking for post_type_labels mapping
      if (
        tabType &&
        typeof yfgpAjax !== "undefined" &&
        yfgpAjax.post_type_labels
      ) {
        // Get post type from context label
        const contextLabel = $activeTab.text().match(/•\s*([^•]+)/);
        if (contextLabel && contextLabel[1]) {
          const labelText = contextLabel[1].trim();
          // Try to find matching post type slug from labels
          for (const [slug, label] of Object.entries(
            yfgpAjax.post_type_labels
          )) {
            if (label === labelText) {
              return slug;
            }
          }
          // If no match found, try to convert label to slug format
          // This is a last resort - should not happen if settings are correct
          return labelText.toLowerCase().replace(/\s+/g, "-");
        }
      }
    }

    // If we reach here, something is wrong with settings
    // Log error but don't use hardcoded fallback - let the error be visible
    if (typeof console !== "undefined" && console.error) {
      console.error(
        "YFGP Error: post_type not found in yfgpAjax. Check plugin settings."
      );
    }
    // Return null instead of hardcoded value - let calling code handle the error
    return null;
  }

  // Check for doctors with multiple specializations
  function checkDoctorsMultipleSpecializations() {
    const postType = getPostType();
    if (!postType) {
      // If post_type is not available, skip the check
      return;
    }

    $.ajax({
      url: yfgpAjax.ajax_url,
      type: "POST",
      data: {
        action: "yfgp_check_doctors_multiple_specializations",
        nonce: yfgpAjax.nonce,
        post_type: postType,
      },
      success: function (response) {
        if (response.success && response.data.count > 0) {
          const $badge = $(
            '.yfgp-tab-badge[data-tab-id="specialization-services"]'
          );
          $badge.text(response.data.count).show();
        } else {
          const $badge = $(
            '.yfgp-tab-badge[data-tab-id="specialization-services"]'
          );
          $badge.text("0").hide();
        }
      },
      error: function () {
        // Silent fail - badge just won't show
      },
    });
  }

  // Load specialization services data
  function loadSpecializationServicesData() {
    const postType = getPostType();
    if (!postType) {
      // If post_type is not available, show error message
      const $empty = $("#yfgp-specialization-services-empty");
      $empty
        .html(
          '<p class="description" style="color: #d63638;">Ошибка: не удалось определить тип записей. Проверьте настройки плагина.</p>'
        )
        .show();
      return;
    }

    const $container = $("#yfgp-specialization-services-container");
    const $loading = $("#yfgp-specialization-services-loading");
    const $empty = $("#yfgp-specialization-services-empty");
    const $noDoctors = $("#yfgp-specialization-services-no-doctors");
    const $tableContainer = $("#yfgp-specialization-services-table-container");

    $loading.show();
    $empty.hide();
    $noDoctors.hide();
    $tableContainer.hide();

    $.ajax({
      url: yfgpAjax.ajax_url,
      type: "POST",
      data: {
        action: "yfgp_load_specialization_services_data",
        nonce: yfgpAjax.nonce,
        post_type: postType,
      },
      success: function (response) {
        $loading.hide();

        if (!response.success) {
          $empty.show();
          return;
        }

        const data = response.data;

        if (data.status === "no_mapping") {
          $empty.show();
        } else if (data.status === "no_doctors") {
          $noDoctors.show();
        } else if (data.status === "success" && data.doctors) {
          // Debug: Log saved settings to console for troubleshooting
          if (typeof console !== "undefined" && console.log) {
            console.log(
              "YFGP: Loaded saved settings:",
              JSON.stringify(data.saved_settings, null, 2)
            );
            console.log("YFGP: Doctors keys:", Object.keys(data.doctors));
            console.log("YFGP: First doctor ID:", Object.keys(data.doctors)[0]);
            if (Object.keys(data.doctors).length > 0) {
              const firstDoctorId = Object.keys(data.doctors)[0];
              console.log(
                "YFGP: Saved settings for first doctor:",
                data.saved_settings[firstDoctorId]
              );
            }
          }
          renderSpecializationServicesTable(
            data.doctors,
            data.saved_settings || {}
          );
          $tableContainer.show();
        }
      },
      error: function () {
        $loading.hide();
        $empty.show();
      },
    });
  }

  // Render specialization services table
  function renderSpecializationServicesTable(doctors, savedSettings) {
    // Debug: Log input data
    if (typeof console !== "undefined" && console.log) {
      console.log(
        "YFGP renderSpecializationServicesTable: doctors keys:",
        Object.keys(doctors)
      );
      console.log(
        "YFGP renderSpecializationServicesTable: savedSettings keys:",
        Object.keys(savedSettings || {})
      );
    }

    const $tbody = $("#yfgp-specialization-services-tbody");
    $tbody.empty();

    // Build header with all specializations
    const allSpecializations = new Set();
    Object.values(doctors).forEach((doctor) => {
      doctor.specializations.forEach((spec) => {
        allSpecializations.add(spec.slug);
      });
    });

    // Update header
    const $header = $("#yfgp-specializations-header");
    $header.html("Специальности (" + allSpecializations.size + ")");

    // Render each doctor
    Object.entries(doctors).forEach(([doctorId, doctor]) => {
      const row = $("<tr></tr>");
      row.append(
        "<td><strong>" +
          escapeHtml(doctor.name) +
          "</strong><br><small>ID: " +
          doctor.id +
          "</small></td>"
      );

      const specsCell = $("<td></td>");
      specsCell.css("padding", "10px");

      doctor.specializations.forEach((spec) => {
        const specDiv = $('<div class="specialization-cell"></div>');
        specDiv.append(
          '<label style="display: block; margin-bottom: 5px; font-weight: 600;">' +
            escapeHtml(spec.text) +
            " <small>(" +
            escapeHtml(spec.slug) +
            ")</small></label>"
        );

        const select = $(
          '<select class="yfgp-service-selector" style="width: 100%; max-width: 400px;"></select>'
        );
        select.attr("data-doctor-id", doctorId);
        select.attr("data-specialization-slug", spec.slug);

        // Add empty option with visual indicator
        const emptyOption = $(
          '<option value="">-- Выберите услугу (не выбрано) --</option>'
        );
        select.append(emptyOption);

        // v4.20.3: Add services with optgroup support (grouped by "Связанные услуги" / "Все услуги")
        if (spec.services && spec.services.length > 0) {
          // Group services by group field
          const groupedServices = {};
          spec.services.forEach((service) => {
            const group = service.group || "";
            if (!groupedServices[group]) {
              groupedServices[group] = [];
            }
            groupedServices[group].push(service);
          });

          // Add services grouped by optgroup
          Object.keys(groupedServices).forEach((groupName) => {
            const servicesInGroup = groupedServices[groupName];
            
            // If group is empty (no grouping) - add options directly
            if (!groupName) {
              servicesInGroup.forEach((service) => {
                const option = $("<option></option>");
                // v4.19.0 FIX: Normalize service ID format - ensure 'service_' prefix for consistency
                let serviceId = service.id || "";
                if (serviceId && !serviceId.toString().startsWith("service_")) {
                  // Extract numeric part if it's in format 'service_123' or just '123'
                  const numericId = serviceId.toString().replace(/^service_/, "");
                  serviceId = "service_" + numericId;
                }
                option.val(serviceId);
                option.text(escapeHtml(service.name));
                select.append(option);
              });
            } else {
              // Create optgroup for grouped services
              const optgroup = $("<optgroup></optgroup>");
              optgroup.attr("label", escapeHtml(groupName));
              
              servicesInGroup.forEach((service) => {
                const option = $("<option></option>");
                // v4.19.0 FIX: Normalize service ID format - ensure 'service_' prefix for consistency
                let serviceId = service.id || "";
                if (serviceId && !serviceId.toString().startsWith("service_")) {
                  // Extract numeric part if it's in format 'service_123' or just '123'
                  const numericId = serviceId.toString().replace(/^service_/, "");
                  serviceId = "service_" + numericId;
                }
                option.val(serviceId);
                option.text(escapeHtml(service.name));
                optgroup.append(option);
              });
              
              select.append(optgroup);
            }
          });
        }

        // Set saved value if exists
        const savedServiceId =
          savedSettings[doctorId] && savedSettings[doctorId][spec.slug]
            ? savedSettings[doctorId][spec.slug]
            : null;

        // Debug: Log for troubleshooting
        if (typeof console !== "undefined" && console.log) {
          console.log(
            "YFGP: Doctor:",
            doctorId,
            "Specialization:",
            spec.slug,
            "Saved service:",
            savedServiceId
          );
        }

        if (savedServiceId) {
          // v4.19.0 FIX: Normalize saved service ID format for comparison
          let normalizedSavedId = savedServiceId.toString();
          if (!normalizedSavedId.startsWith("service_")) {
            normalizedSavedId =
              "service_" + normalizedSavedId.replace(/^service_/, "");
          }

          // Try to set the value (try both formats for compatibility)
          let valueSet = false;
          if (
            select.find('option[value="' + normalizedSavedId + '"]').length > 0
          ) {
            select.val(normalizedSavedId);
            valueSet = select.val() === normalizedSavedId;
          } else if (
            select.find('option[value="' + savedServiceId + '"]').length > 0
          ) {
            select.val(savedServiceId);
            valueSet = select.val() === savedServiceId;
          }

          // Verify the value was set
          if (!valueSet) {
            // Value not found in options - log warning
            if (typeof console !== "undefined" && console.warn) {
              console.warn(
                "YFGP: Saved service ID not found in options:",
                savedServiceId,
                "(normalized:",
                normalizedSavedId + ")",
                "for doctor:",
                doctorId,
                "specialization:",
                spec.slug,
                "Available options:",
                Array.from(select.find("option")).map((opt) => opt.value)
              );
            }
            select.addClass("yfgp-service-not-selected");
            select.val(""); // Reset to empty
          } else {
            // Value set successfully
            select.removeClass("yfgp-service-not-selected");
          }
        } else {
          // Mark as not selected with visual indicator
          select.addClass("yfgp-service-not-selected");
          select.val(""); // Ensure empty value is selected
        }

        // Add change handler to update visual state
        select.on("change", function () {
          if ($(this).val()) {
            $(this).removeClass("yfgp-service-not-selected");
          } else {
            $(this).addClass("yfgp-service-not-selected");
          }
        });

        specDiv.append(select);
        specsCell.append(specDiv);
      });

      row.append(specsCell);
      $tbody.append(row);
    });
  }

  // Save specialization services
  function saveSpecializationServices() {
    // v4.19.0 FIX: Check if yfgpAjax is available
    if (typeof yfgpAjax === "undefined") {
      console.error(
        "YFGP: saveSpecializationServices - yfgpAjax is not defined! Check if script is localized correctly."
      );
      alert(
        "Ошибка: не удалось загрузить настройки AJAX. Пожалуйста, обновите страницу."
      );
      return;
    }

    if (!yfgpAjax.ajax_url || !yfgpAjax.nonce) {
      console.error(
        "YFGP: saveSpecializationServices - yfgpAjax.ajax_url or yfgpAjax.nonce is missing!",
        yfgpAjax
      );
      alert(
        "Ошибка: не удалось загрузить настройки AJAX. Пожалуйста, обновите страницу."
      );
      return;
    }

    const $button = $("#yfgp-save-specialization-services");
    const $message = $("#yfgp-save-specialization-services-message");

    if ($button.length === 0) {
      console.error(
        "YFGP: saveSpecializationServices - Save button not found in DOM!"
      );
      return;
    }

    // Collect data
    const settings = {};
    let hasErrors = false;
    const selectorsCount = $(".yfgp-service-selector").length;

    // Debug: Log selector count
    if (typeof console !== "undefined" && console.log) {
      console.log(
        "YFGP: saveSpecializationServices - Found selectors:",
        selectorsCount
      );
    }

    if (selectorsCount === 0) {
      console.error(
        "YFGP: saveSpecializationServices - No selectors found! Table may not be rendered."
      );
      $message.html(
        '<span style="color: #d63638;">❌ Ошибка: таблица не загружена. Пожалуйста, обновите страницу.</span>'
      );
      return;
    }

    $(".yfgp-service-selector").each(function (index) {
      const $select = $(this);
      const doctorId = $select.data("doctor-id");
      const specializationSlug = $select.data("specialization-slug");
      const serviceId = $select.val();

      // Debug: Log each selector
      if (typeof console !== "undefined" && console.log) {
        console.log(
          "YFGP: Selector",
          index + 1,
          "- doctor:",
          doctorId,
          "specialization:",
          specializationSlug,
          "service:",
          serviceId
        );
      }

      if (!doctorId || !specializationSlug) {
        console.error(
          "YFGP: Missing data attributes - doctor:",
          doctorId,
          "specialization:",
          specializationSlug
        );
        hasErrors = true;
        return;
      }

      if (!serviceId) {
        hasErrors = true;
        $select.css("border-color", "#d63638");
        return;
      }

      $select.css("border-color", "");

      // v4.19.0 FIX: Normalize service ID format - ensure 'service_' prefix
      let normalizedServiceId = serviceId;
      if (typeof serviceId === "string" && serviceId.length > 0) {
        // If it's a numeric string or doesn't start with 'service_', add prefix
        if (!serviceId.startsWith("service_")) {
          normalizedServiceId = "service_" + serviceId;
        }
      } else if (typeof serviceId === "number") {
        normalizedServiceId = "service_" + serviceId;
      }

      if (!settings[doctorId]) {
        settings[doctorId] = {};
      }

      settings[doctorId][specializationSlug] = normalizedServiceId;
    });

    if (hasErrors) {
      $message.html(
        '<span style="color: #d63638;">⚠️ Пожалуйста, выберите услугу для всех специальностей</span>'
      );
      return;
    }

    // Debug: Log collected settings
    if (typeof console !== "undefined" && console.log) {
      console.log(
        "YFGP: Collected settings:",
        JSON.stringify(settings, null, 2)
      );
      console.log("YFGP: Settings count:", Object.keys(settings).length);
    }

    $button.prop("disabled", true).text("⏳ Сохранение...");
    $message.html("");

    // Debug: Log settings before sending
    if (typeof console !== "undefined" && console.log) {
      console.log(
        "YFGP: saveSpecializationServices - Sending settings:",
        JSON.stringify(settings, null, 2)
      );
      console.log(
        "YFGP: saveSpecializationServices - Settings count:",
        Object.keys(settings).length
      );
      console.log(
        "YFGP: saveSpecializationServices - AJAX URL:",
        yfgpAjax.ajax_url
      );
      console.log(
        "YFGP: saveSpecializationServices - Nonce:",
        yfgpAjax.nonce ? yfgpAjax.nonce.substring(0, 10) + "..." : "MISSING"
      );
    }

    // v4.19.0 FIX: Verify jQuery is available
    if (typeof jQuery === "undefined" || typeof jQuery.ajax === "undefined") {
      console.error(
        "YFGP: saveSpecializationServices - jQuery or jQuery.ajax is not available!"
      );
      alert("Ошибка: jQuery не загружен. Пожалуйста, обновите страницу.");
      return;
    }

    $.ajax({
      url: yfgpAjax.ajax_url,
      type: "POST",
      dataType: "json", // v4.19.0 FIX: Explicitly expect JSON response
      data: {
        action: "yfgp_save_specialization_services",
        nonce: yfgpAjax.nonce,
        settings: JSON.stringify(settings),
      },
      success: function (response) {
        // Debug: Log response
        if (typeof console !== "undefined" && console.log) {
          console.log(
            "YFGP: saveSpecializationServices - AJAX response:",
            response
          );
        }

        if (response.success) {
          $message.html(
            '<span style="color: #00a32a;">✅ Настройки сохранены! Обновление данных...</span>'
          );
          // Reload data to show saved values - increased delay to ensure data is saved
          setTimeout(function () {
            loadSpecializationServicesData();
            // Update message after reload
            setTimeout(function () {
              $message.html(
                '<span style="color: #00a32a;">✅ Настройки сохранены и применены!</span>'
              );
            }, 500);
          }, 1500);
        } else {
          const errorMessage = response.data || "Неизвестная ошибка";
          if (typeof console !== "undefined" && console.error) {
            console.error(
              "YFGP: saveSpecializationServices - Server error:",
              errorMessage,
              "Full response:",
              response
            );
          }
          $message.html(
            '<span style="color: #d63638;">❌ Ошибка: ' +
              escapeHtml(errorMessage) +
              "</span>"
          );
        }
      },
      error: function (xhr, status, error) {
        // Debug: Log error details
        if (typeof console !== "undefined" && console.error) {
          console.error("YFGP: saveSpecializationServices - AJAX error:", {
            status: status,
            error: error,
            xhr: xhr,
            responseText: xhr.responseText,
            statusCode: xhr.status,
          });
        }

        let errorMessage = "Ошибка сети";
        if (xhr.responseText) {
          try {
            const errorResponse = JSON.parse(xhr.responseText);
            if (errorResponse.data) {
              errorMessage = errorResponse.data;
            }
          } catch (e) {
            // If response is not JSON, use status text
            errorMessage = xhr.statusText || error || "Ошибка сети";
          }
        }

        $message.html(
          '<span style="color: #d63638;">❌ Ошибка сети: ' +
            escapeHtml(errorMessage) +
            "</span>"
        );
      },
      complete: function () {
        $button.prop("disabled", false).text("💾 Сохранить настройки");
      },
    });
  }

  // Helper function to escape HTML
  function escapeHtml(text) {
    const map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    };
    return String(text).replace(/[&<>"']/g, function (m) {
      return map[m];
    });
  }

  // Initialize on document ready
  $(document).ready(function () {
    // Check for doctors with multiple specializations on page load
    checkDoctorsMultipleSpecializations();

    // Handle tab switching - check if specialization-services tab is clicked
    $(document).on(
      "click",
      '.yfgp-mega-tab-wrapper .nav-tab[data-tab="specialization-services"]',
      function () {
        // Small delay to ensure tab content is visible
        setTimeout(function () {
          loadSpecializationServicesData();
        }, 100);
      }
    );

    // Handle save button click
    $(document).on("click", "#yfgp-save-specialization-services", function (e) {
      e.preventDefault();
      e.stopPropagation(); // v4.19.0 FIX: Prevent event bubbling

      // Debug: Log button click
      if (typeof console !== "undefined" && console.log) {
        console.log("YFGP: Save button clicked");
      }

      saveSpecializationServices();
    });

    // v4.19.0 FIX: Also check if button exists and log if not
    $(document).ready(function () {
      const $saveButton = $("#yfgp-save-specialization-services");
      if ($saveButton.length === 0) {
        console.warn(
          "YFGP: Save button #yfgp-save-specialization-services not found in DOM on page load"
        );
      } else {
        console.log("YFGP: Save button found, handlers registered");
      }

      // Check yfgpAjax availability
      if (typeof yfgpAjax === "undefined") {
        console.error(
          "YFGP: yfgpAjax is not defined! AJAX requests will fail."
        );
      } else {
        console.log("YFGP: yfgpAjax available, ajax_url:", yfgpAjax.ajax_url);
      }
    });
  });
})(jQuery);
