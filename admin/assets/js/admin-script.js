/**
 * JavaScript для админ-панели Yandex Feed Generator Pro
 */

(function ($) {
  "use strict";

  $(document).ready(function () {
    const ajaxNonce =
      typeof yfgpAjax !== "undefined" && yfgpAjax.nonce ? yfgpAjax.nonce : "";
    const feedNonce =
      typeof yfgpAjax !== "undefined" && yfgpAjax.feed_nonce
        ? yfgpAjax.feed_nonce
        : ajaxNonce;
    const getPostType = () => {
      const $select = $("#post_type");
      const value = $select.length ? $select.val() : null;
      if (value && value.length) {
        return value;
      }
      return typeof yfgpAjax !== "undefined" && yfgpAjax.post_type
        ? yfgpAjax.post_type
        : "";
    };

    /**
     * Сохранение маппинга
     */
    $("#yfgp-save-mapping").on("click", function () {
      const button = $(this);
      button.prop("disabled", true).text("⏳ Сохранение...");

      const mapping = {};
      $('[name^="mapping["]').each(function () {
        const name = $(this)
          .attr("name")
          .match(/mapping\[(.+)\]/)[1];
        mapping[name] = $(this).val();
      });

      $.ajax({
        url: yfgpAjax.ajax_url,
        type: "POST",
        data: {
          action: "yfgp_save_mapping",
          nonce: feedNonce,
          mapping: mapping,
        },
        success: function (response) {
          if (response.success) {
            showNotice("✅ Маппинг сохранён!", "success");
          } else {
            showNotice("❌ Ошибка: " + response.data, "error");
          }
        },
        error: function () {
          showNotice("❌ Ошибка сети", "error");
        },
        complete: function () {
          button.prop("disabled", false).text("💾 Сохранить маппинг");
        },
      });
    });

    /**
     * Тестирование маппинга
     */
    $("#yfgp-test-mapping").on("click", function () {
      const button = $(this);
      button.prop("disabled", true).text("⏳ Тестирование...");

      $.ajax({
        url: yfgpAjax.ajax_url,
        type: "POST",
        data: {
          action: "yfgp_generate_feed",
          nonce: feedNonce,
          post_type: getPostType(),
          preview_only: "true",
          limit: 1,
        },
        success: function (response) {
          if (response.success) {
            showPreviewModal(response.data.yml);
          } else {
            showNotice("❌ Ошибка: " + response.data, "error");
          }
        },
        error: function () {
          showNotice("❌ Ошибка сети", "error");
        },
        complete: function () {
          button.prop("disabled", false).text("🧪 Тестировать на одном посте");
        },
      });
    });

    /**
     * Валидация фида
     */
    $("#yfgp-validate-feed").on("click", function () {
      const button = $(this);
      button.prop("disabled", true).text("⏳ Проверка...");

      $.ajax({
        url: yfgpAjax.ajax_url,
        type: "POST",
        data: {
          action: "yfgp_validate_feed",
          nonce: feedNonce,
        },
        success: function (response) {
          if (response.success) {
            showNotice("✅ Фид валиден!", "success");
          } else {
            showNotice("❌ Ошибки валидации: " + response.data, "error");
          }
        },
        error: function () {
          showNotice("❌ Ошибка сети", "error");
        },
        complete: function () {
          button.prop("disabled", false).text("✅ Проверить валидность");
        },
      });
    });

    /**
     * Показ уведомления
     */
    function showNotice(message, type) {
      const noticeClass =
        type === "success" ? "notice-success" : "notice-error";
      const notice = $(
        '<div class="notice ' +
          noticeClass +
          ' is-dismissible"><p>' +
          message +
          "</p></div>"
      );

      $(".wrap h1").after(notice);

      setTimeout(function () {
        notice.fadeOut(function () {
          $(this).remove();
        });
      }, 5000);
    }

    /**
     * Показ модального окна с превью
     */
    function showPreviewModal(yml) {
      const modal = $(
        '<div class="yfgp-modal">' +
          '<div class="yfgp-modal-content">' +
          '<span class="yfgp-modal-close">&times;</span>' +
          "<h2>Превью фида (первый пост)</h2>" +
          '<textarea readonly class="yfgp-xml-preview">' +
          yml +
          "</textarea>" +
          "</div>" +
          "</div>"
      );

      $("body").append(modal);

      modal.find(".yfgp-modal-close").on("click", function () {
        modal.remove();
      });

      modal.on("click", function (e) {
        if ($(e.target).hasClass("yfgp-modal")) {
          modal.remove();
        }
      });
    }

    // v3.4.8: Export to global scope for field-mapping-v3.php
    window.showPreviewModal = showPreviewModal;
  });
})(jQuery);
