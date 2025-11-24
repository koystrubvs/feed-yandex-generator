<?php
/**
 * Страница генерации и превью
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>🚀 Генерация YML фида
        <span id="yfgp-current-cpt-indicator" style="margin-left: 15px; font-size: 0.6em; font-weight: normal; color: #2271b1;">
            <span class="dashicons dashicons-admin-post" style="font-size: 16px; vertical-align: middle;"></span>
            <span id="yfgp-current-cpt-text">Загрузка...</span>
        </span>
    </h1>
    
    <div class="yfgp-generate-section">
        <div class="yfgp-card">
            <h2>📋 Информация о фиде</h2>
            
            <?php if ($feed_content): ?>
                <table class="widefat">
                    <tr>
                        <td><strong>URL фида:</strong></td>
                        <td>
                            <a href="<?php echo esc_url($feed_url); ?>" target="_blank">
                                <?php echo esc_html($feed_url); ?>
                            </a>
                            <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js($feed_url); ?>')">
                                📋 Копировать
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Размер файла:</strong></td>
                        <td><?php echo size_format(strlen($feed_content)); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Последнее обновление:</strong></td>
                        <td>
                            <?php
                            if (!empty($last_generated_at)) {
                                echo esc_html(mysql2date('Y-m-d H:i:s', $last_generated_at));
                                if (!empty($feed_mtime)) {
                                    echo '<br /><span class="description">' . esc_html(date_i18n('Y-m-d H:i:s', $feed_mtime)) . '</span>';
                                }
                            } elseif (!empty($feed_mtime)) {
                                echo esc_html(date_i18n('Y-m-d H:i:s', $feed_mtime));
                            } else {
                                echo '&mdash;';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
            <?php else: ?>
                <div class="notice notice-warning inline">
                    <p>Фид ещё не создан. Нажмите кнопку "Сгенерировать фид" ниже.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="yfgp-card">
            <h2>⚡ Действия</h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('yfgp_generate_nonce'); ?>
                
                <p>
                    <button type="submit" name="yfgp_generate_now" class="button button-primary button-hero">
                        🚀 Сгенерировать и опубликовать фид
                    </button>
                </p>
                
                <p>
                    <button type="button" id="yfgp-preview-feed" class="button button-secondary">
                        👁️ Превью (без публикации)
                    </button>
                    
                    <button type="button" id="yfgp-validate-feed" class="button button-secondary">
                        ✅ Проверить валидность
                    </button>
                </p>
            </form>
        </div>
        
        <?php if ($feed_content): ?>
            <div class="yfgp-card">
                <h2>📄 Превью фида</h2>
                
                <div class="yfgp-tabs">
                    <button class="yfgp-tab active" data-tab="xml">Превью</button>
                    <button class="yfgp-tab" data-tab="editor">✏️ Редактор XML</button>
                    <button class="yfgp-tab" data-tab="stats">Статистика</button>
                </div>
                
                <div class="yfgp-tab-content active" id="xml-content">
                    <textarea readonly class="yfgp-xml-preview"><?php echo esc_textarea($feed_content); ?></textarea>
                    <p class="description"><a href="<?php echo esc_url($feed_url); ?>" target="_blank">Открыть полный файл</a></p>
                </div>
                
                <div class="yfgp-tab-content" id="editor-content">
                    <p class="description">
                        <strong>⚠️ Внимание:</strong> Ручное редактирование XML. Убедитесь в корректности синтаксиса перед сохранением.
                    </p>
                    <textarea id="yfgp-xml-editor"><?php echo esc_textarea($feed_content); ?></textarea>
                    <p style="margin-top: 15px;">
                        <button type="button" id="yfgp-save-edited-xml" class="button button-primary">
                            💾 Сохранить изменения
                        </button>
                        <button type="button" id="yfgp-reset-editor" class="button">
                            ↩️ Сбросить изменения
                        </button>
                    </p>
                </div>
                
                <div class="yfgp-tab-content" id="stats-content">
                    <?php
                    $offers_count = substr_count($feed_content, '<offer ');
                    $doctors_count = substr_count($feed_content, '<doctor ');
                    $clinics_count = substr_count($feed_content, '<clinic ');
                    $services_count = substr_count($feed_content, '<service ');
                    $reviews_count = substr_count($feed_content, '<review ');
                    $sets_count = substr_count($feed_content, '<set ');
                    
                    // Определяем формат фида
                    $is_v2 = strpos($feed_content, 'version="2.0"') !== false;
                    ?>
                    <table class="widefat">
                        <?php if ($is_v2): ?>
                            <tr>
                                <th colspan="2" style="background: #f0f0f1; font-size: 14px;">
                                    📊 Статистика фида (формат v2.0)
                                </th>
                            </tr>
                            <tr>
                                <td><strong>👤 Врачей:</strong></td>
                                <td><span style="font-size: 18px; color: #2271b1; font-weight: bold;"><?php echo $doctors_count; ?></span></td>
                            </tr>
                            <tr>
                                <td><strong>🏥 Клиник:</strong></td>
                                <td><span style="font-size: 18px; color: #00a32a; font-weight: bold;"><?php echo $clinics_count; ?></span></td>
                            </tr>
                            <tr>
                                <td><strong>💊 Услуг:</strong></td>
                                <td><span style="font-size: 18px; color: #d63638; font-weight: bold;"><?php echo $services_count; ?></span></td>
                            </tr>
                            <?php if ($reviews_count > 0): ?>
                            <tr>
                                <td><strong>⭐ Отзывов:</strong></td>
                                <td><span style="font-size: 18px; color: #f0b849; font-weight: bold;"><?php echo $reviews_count; ?></span></td>
                            </tr>
                            <?php endif; ?>
                            <tr style="background: #f9f9f9;">
                                <td><strong>📦 Офферов:</strong></td>
                                <td><span style="font-size: 20px; color: #135e96; font-weight: bold;"><?php echo $offers_count; ?></span></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <th colspan="2" style="background: #f0f0f1; font-size: 14px;">
                                    📊 Статистика фида (формат v1.0)
                                </th>
                            </tr>
                            <tr>
                                <td><strong>Количество офферов:</strong></td>
                                <td><?php echo $offers_count; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Количество специальностей:</strong></td>
                                <td><?php echo $sets_count; ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td><strong>📂 Размер файла:</strong></td>
                            <td><?php echo size_format(strlen($feed_content)); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="yfgp-card">
            <h2>📖 Инструкция по загрузке в Яндекс.Вебмастер</h2>
            
            <ol>
                <li>Скопируйте URL фида выше</li>
                <li>Откройте <a href="https://webmaster.yandex.ru/" target="_blank">Яндекс.Вебмастер</a></li>
                <li>Выберите ваш сайт</li>
                <li>Перейдите: <strong>Услуги и предложения → Фиды и ошибки</strong></li>
                <li>Нажмите <strong>Загрузить фид</strong></li>
                <li>Выберите категорию: <strong>Врачи</strong></li>
                <li>Укажите регион: <strong><?php echo esc_html($settings['city'] ?? 'Ваш город'); ?></strong></li>
                <li>Вставьте URL фида и нажмите <strong>Готово</strong></li>
            </ol>
        </div>
    </div>
</div>

<style>
.yfgp-generate-section {
    margin-top: 20px;
}

.yfgp-card {
    background: #fff;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.yfgp-xml-preview {
    width: 100%;
    height: 400px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    background: #f6f7f7;
    border: 1px solid #c3c4c7;
    padding: 10px;
}

.yfgp-tabs {
    border-bottom: 1px solid #c3c4c7;
    margin-bottom: 15px;
}

.yfgp-tab {
    padding: 10px 20px;
    border: none;
    background: none;
    cursor: pointer;
    border-bottom: 2px solid transparent;
}

.yfgp-tab.active {
    border-bottom-color: #2271b1;
    color: #2271b1;
    font-weight: 600;
}

.yfgp-tab-content {
    display: none;
}

.yfgp-tab-content.active {
    display: block;
}
</style>

<script>
jQuery(document).ready(function($) {
    // v4.18.14: Функция showNotice() для WordPress-стандартов (если не определена в admin-script.js)
    if (typeof showNotice === 'undefined') {
        window.showNotice = function(message, type) {
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap').first().prepend(notice);
            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        };
    }
    
    // v4.18.13: Единый стандарт nonce для всех AJAX handlers
    const ajaxNonce =
        typeof yfgpAjax !== "undefined" && yfgpAjax.nonce ? yfgpAjax.nonce : "";
    const defaultPostType = '<?php echo esc_js($settings['post_type'] ?? 'doctors'); ?>';
    const getPostType = () => {
        const $select = $("#post_type");
        const value = $select.length ? $select.val() : null;
        if (value && value.length) {
            return value;
        }
        if (typeof yfgpAjax !== "undefined" && yfgpAjax.post_type) {
            return yfgpAjax.post_type;
        }
        return defaultPostType;
    };
    
    // v4.18.0: Обновление индикатора текущего CPT
    const updateCptIndicator = () => {
        const postType = getPostType();
        const $indicator = $('#yfgp-current-cpt-text');
        
        if (!$indicator.length) {
            return;
        }
        
        // Получаем человекочитаемое название CPT
        // Пробуем найти в селекте (если есть)
        const $select = $("#post_type");
        let cptLabel = postType;
        
        if ($select.length) {
            const $selectedOption = $select.find('option:selected');
            if ($selectedOption.length && $selectedOption.text()) {
                cptLabel = $selectedOption.text().trim();
            }
        }
        
        // Если не нашли в селекте, пробуем получить из локализации или используем slug
        if (cptLabel === postType && typeof yfgpAjax !== "undefined" && yfgpAjax.post_type_labels) {
            cptLabel = yfgpAjax.post_type_labels[postType] || postType;
        }
        
        // Если всё ещё slug, пробуем получить из WordPress
        if (cptLabel === postType) {
            // Fallback: используем slug с заглавной буквой
            cptLabel = postType.charAt(0).toUpperCase() + postType.slice(1);
        }
        
        $indicator.text('Генерация для: ' + cptLabel);
    };
    
    // Обновляем индикатор при загрузке страницы
    $(document).ready(function() {
        updateCptIndicator();
    });

    // Инициализация CodeMirror редактора
    let xmlEditor = null;
    
    // Переключение табов
    $('.yfgp-tab').on('click', function() {
        const tab = $(this).data('tab');
        
        $('.yfgp-tab').removeClass('active');
        $(this).addClass('active');
        
        $('.yfgp-tab-content').removeClass('active');
        $('#' + tab + '-content').addClass('active');
        
        // Инициализируем CodeMirror при первом открытии вкладки "Редактор"
        if (tab === 'editor' && !xmlEditor) {
            xmlEditor = CodeMirror.fromTextArea(document.getElementById('yfgp-xml-editor'), {
                mode: 'xml',
                theme: 'material',
                lineNumbers: true,
                lineWrapping: true,
                indentUnit: 2,
                tabSize: 2,
                autoCloseTags: true
            });
        }
    });
    
    // Превью без публикации
    $('#yfgp-preview-feed').on('click', function() {
        const button = $(this);
        button.prop('disabled', true).text('⏳ Генерация...');
        
        $.ajax({
            url: yfgpAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'yfgp_generate_feed',
                nonce: ajaxNonce,
                post_type: getPostType(),
                preview_only: 'true',
                limit: 1
            },
            success: function(response) {
                if (response.success) {
                    $('.yfgp-xml-preview').val(response.data.yml);
                    showNotice('✅ Превью создан!', 'success');
                } else {
                    showNotice('❌ Ошибка: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotice('❌ Ошибка сети', 'error');
            },
            complete: function() {
                button.prop('disabled', false).text('👁️ Превью (без публикации)');
            }
        });
    });
    
    // Сохранение отредактированного XML
    $('#yfgp-save-edited-xml').on('click', function() {
        if (!xmlEditor) return;
        
        if (!confirm('Сохранить отредактированный XML? Текущий фид будет перезаписан.')) {
            return;
        }
        
        const button = $(this);
        button.prop('disabled', true).text('⏳ Сохранение...');
        
        const xmlContent = xmlEditor.getValue();
        
        $.ajax({
            url: yfgpAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'yfgp_save_edited_xml',
                nonce: ajaxNonce,
                xml_content: xmlContent
            },
            success: function(response) {
                if (response.success) {
                    showNotice('✅ XML сохранён!', 'success');
                    location.reload();
                } else {
                    showNotice('❌ Ошибка: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotice('❌ Ошибка сети', 'error');
            },
            complete: function() {
                button.prop('disabled', false).text('💾 Сохранить изменения');
            }
        });
    });
    
    // Сброс изменений в редакторе
    $('#yfgp-reset-editor').on('click', function() {
        if (!xmlEditor) return;
        
        if (confirm('Сбросить все изменения?')) {
            location.reload();
        }
    });
});
</script>

