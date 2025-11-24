<?php
/**
 * Страница настроек
 */

if (!defined('ABSPATH')) {
    exit;
}

// Получаем ВСЕ типы записей (включая непубличные), кроме системных
$post_types = get_post_types(array(
    '_builtin' => false  // Исключаем встроенные типы (attachment, nav_menu_item и т.д.)
), 'objects');

// Добавляем стандартные типы WordPress (post, page)
$builtin_types = get_post_types(array('_builtin' => true, 'public' => true), 'objects');
$post_types = array_merge($builtin_types, $post_types);

// Исключаем служебные типы
$exclude = array('revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation');
$post_types = array_filter($post_types, function($pt) use ($exclude) {
    return !in_array($pt->name, $exclude);
});
$cron_manager = new YFGP_Cron_Manager();
$intervals = $cron_manager->get_intervals();
?>

<div class="wrap">
    <h1>⚙️ Настройки Yandex Feed Generator</h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('yfgp_settings_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th colspan="2"><h2>📋 Информация о компании</h2></th>
            </tr>
            
            <tr>
                <th scope="row"><label for="company_name">Название компании</label></th>
                <td>
                    <input type="text" 
                           id="company_name" 
                           name="company_name" 
                           value="<?php echo esc_attr($settings['company_name'] ?? ''); ?>" 
                           class="regular-text" 
                           required>
                    <p class="description">Будет отображаться в фиде</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="company_url">URL сайта</label></th>
                <td>
                    <input type="url" 
                           id="company_url" 
                           name="company_url" 
                           value="<?php echo esc_attr($settings['company_url'] ?? ''); ?>" 
                           class="regular-text" 
                           required>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="company_email">Email</label></th>
                <td>
                    <input type="email" 
                           id="company_email" 
                           name="company_email" 
                           value="<?php echo esc_attr($settings['company_email'] ?? ''); ?>" 
                           class="regular-text" 
                           required>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="company_logo">URL логотипа</label></th>
                <td>
                    <input type="url" 
                           id="company_logo" 
                           name="company_logo" 
                           value="<?php echo esc_attr($settings['company_logo'] ?? ''); ?>" 
                           class="regular-text">
                    <p class="description">Полный URL изображения логотипа</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="description">Описание</label></th>
                <td>
                    <textarea id="description" 
                              name="description" 
                              rows="3" 
                              class="large-text"><?php echo esc_textarea($settings['description'] ?? ''); ?></textarea>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="city">Город</label></th>
                <td>
                    <input type="text" 
                           id="city" 
                           name="city" 
                           value="<?php echo esc_attr($settings['city'] ?? ''); ?>" 
                           class="regular-text" 
                           placeholder="г. Севастополь">
                </td>
            </tr>
            
            <tr>
                <th colspan="2"><h2>🎯 Настройки фида</h2></th>
            </tr>
            
            <tr>
                <th scope="row"><label for="feed_format">Формат фида</label></th>
                <td>
                    <select id="feed_format" name="feed_format" class="regular-text">
                        <option value="v1" <?php selected($settings['feed_format'] ?? 'v2', 'v1'); ?>>Старый формат (с &lt;param name="..."&gt;)</option>
                        <option value="v2" <?php selected($settings['feed_format'] ?? 'v2', 'v2'); ?>>Формат версия 2.0 (рекомендуется Яндексом)</option>
                    </select>
                    <p class="description">
                        ✅ <strong>Версия 2.0</strong> - современный формат с отдельными блоками &lt;doctors&gt;, &lt;clinics&gt;, &lt;services&gt;, &lt;offers&gt;<br>
                        ⚠️ <strong>Старый формат</strong> - устаревший, но всё ещё поддерживается Яндексом
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="post_type">Тип записи</label></th>
                <td>
                    <select id="post_type" name="post_type" class="regular-text">
                        <?php foreach ($post_types as $pt): ?>
                            <option value="<?php echo esc_attr($pt->name); ?>" 
                                    <?php selected($settings['post_type'] ?? '', $pt->name); ?>>
                                <?php echo esc_html($pt->label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Выберите тип записи для генерации фида</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="feed_category">Категория фида</label></th>
                <td>
                    <select id="feed_category" name="feed_category" class="regular-text">
                        <option value="doctors" <?php selected($settings['feed_category'] ?? '', 'doctors'); ?>>Врачи</option>
                    </select>
                    <p class="description">Только "Врачи" поддерживается в текущей версии</p>
                </td>
            </tr>
            
            <tr>
                <th colspan="2"><h2>📦 Типы записей (Post Types)</h2></th>
            </tr>
            
            <tr>
                <th scope="row"><label for="clinics_post_type">Тип записи для клиник</label></th>
                <td>
                    <select id="clinics_post_type" name="clinics_post_type" class="regular-text">
                        <?php foreach ($post_types as $pt): ?>
                            <option value="<?php echo esc_attr($pt->name); ?>" 
                                    <?php selected($settings['clinics_post_type'] ?? 'clinics', $pt->name); ?>>
                                <?php echo esc_html($pt->label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Выберите тип записи для клиник (по умолчанию: <code>clinics</code>)</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="services_post_type">Тип записи для услуг</label></th>
                <td>
                    <select id="services_post_type" name="services_post_type" class="regular-text">
                        <?php foreach ($post_types as $pt): ?>
                            <option value="<?php echo esc_attr($pt->name); ?>" 
                                    <?php selected($settings['services_post_type'] ?? 'services', $pt->name); ?>>
                                <?php echo esc_html($pt->label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Выберите тип записи для услуг (по умолчанию: <code>services</code>)</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="reviews_post_type">Тип записи для отзывов</label></th>
                <td>
                    <select id="reviews_post_type" name="reviews_post_type" class="regular-text">
                        <?php foreach ($post_types as $pt): ?>
                            <option value="<?php echo esc_attr($pt->name); ?>" 
                                    <?php selected($settings['reviews_post_type'] ?? 'reviews', $pt->name); ?>>
                                <?php echo esc_html($pt->label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Выберите тип записи для отзывов (по умолчанию: <code>reviews</code>)</p>
                </td>
            </tr>
            
            <tr>
                <th colspan="2"><h2>🎯 Специальности (Sets)</h2></th>
            </tr>
            
            <tr>
                <th scope="row"><label for="sets_source">Источник специальностей</label></th>
                <td>
                    <select id="sets_source" name="sets_source" class="regular-text">
                        <option value="taxonomy" <?php selected($settings['sets_source'] ?? 'taxonomy', 'taxonomy'); ?>>Таксономия (уникальные URL)</option>
                        <option value="field" <?php selected($settings['sets_source'] ?? 'taxonomy', 'field'); ?>>Произвольное поле (общий URL архива)</option>
                    </select>
                    <p class="description">Выберите источник данных для специальностей</p>
                </td>
            </tr>
            
            <tr id="yfgp-sets-taxonomy-row" style="display: none;">
                <th scope="row"><label for="sets_taxonomy">Таксономия</label></th>
                <td>
                    <select id="sets_taxonomy" name="sets_taxonomy" class="regular-text">
                        <option value="">-- Выберите таксономию --</option>
                        <?php
                        $taxonomies = get_taxonomies(array('public' => true), 'objects');
                        foreach ($taxonomies as $taxonomy) {
                            if ($taxonomy->name !== 'post_format') {
                                echo '<option value="' . esc_attr($taxonomy->name) . '" ' . selected($settings['sets_taxonomy'] ?? '', $taxonomy->name, false) . '>' . esc_html($taxonomy->label) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <p class="description">Таксономия для получения специальностей</p>
                </td>
            </tr>
            
            <tr id="yfgp-sets-field-cpt-row" style="display: none;">
                <th scope="row"><label for="sets_field_cpt">Тип записи с полем</label></th>
                <td>
                    <select id="sets_field_cpt" name="sets_field_cpt" class="regular-text">
                        <option value="">-- Выберите тип записи --</option>
                        <?php foreach ($post_types as $pt): ?>
                            <option value="<?php echo esc_attr($pt->name); ?>" 
                                    <?php selected($settings['sets_field_cpt'] ?? '', $pt->name); ?>>
                                <?php echo esc_html($pt->label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Тип записи, в котором находится поле со специальностями</p>
                </td>
            </tr>
            
            <tr id="yfgp-sets-field-row" style="display: none;">
                <th scope="row"><label for="sets_field">Поле</label></th>
                <td>
                    <select id="sets_field" name="sets_field" class="regular-text" disabled>
                        <option value="">-- Сначала выберите тип записи --</option>
                    </select>
                    <p class="description">Произвольное поле (ACF/JetEngine/Meta) со специальностями</p>
                    <div id="yfgp-field-loading" style="display: none; margin-top: 10px;">
                        ⏳ Загрузка полей...
                    </div>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Формирование URL</th>
                <td>
                    <p class="description" id="sets-url-method-hint">
                        <?php
                        $source = $settings['sets_source'] ?? 'taxonomy';
                        if ($source === 'taxonomy') {
                            echo '✅ <strong>Таксономия:</strong> Уникальные URL через get_term_link() для каждой специальности';
                        } elseif ($source === 'field') {
                            echo '✅ <strong>Произвольное поле:</strong> Общий URL архива через get_post_type_archive_link()';
                        }
                        ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th colspan="2"><h2>📧 Email уведомления</h2></th>
            </tr>
            
            <tr>
                <th scope="row"><label for="email_notifications">Включить уведомления</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="email_notifications" id="email_notifications" value="1" <?php checked($settings['email_notifications'] ?? false); ?>>
                        Отправлять email при автообновлении фида
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="notification_email">Email для уведомлений</label></th>
                <td>
                    <input type="email" name="notification_email" id="notification_email" value="<?php echo esc_attr($settings['notification_email'] ?? get_option('admin_email')); ?>" class="regular-text">
                    <p class="description">Укажите email для получения уведомлений (по умолчанию: email администратора)</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="notify_on_success">Уведомлять при успехе</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="notify_on_success" id="notify_on_success" value="1" <?php checked($settings['notify_on_success'] ?? true); ?>>
                        Отправлять уведомление при успешном обновлении
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="notify_on_error">Уведомлять при ошибке</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="notify_on_error" id="notify_on_error" value="1" <?php checked($settings['notify_on_error'] ?? true); ?>>
                        Отправлять уведомление при ошибке
                    </label>
                </td>
            </tr>
            
            <tr>
                <th colspan="2"><h2>💰 Цены и валюта</h2></th>
            </tr>
            
            <tr>
                <th colspan="2">
                    <div class="notice notice-info inline" style="margin: 10px 0; padding: 10px;">
                        <p style="margin: 0;">
                            ℹ️ <strong>v2.3.1:</strong> Настройка "Цена по умолчанию" удалена!<br>
                            Теперь цена берётся <strong>только</strong> из ACF/JetEngine полей услуги.<br>
                            Офферы без цены допускаются для базовых услуг (согласно документации Яндекс.Здоровье).
                        </p>
                    </div>
                </th>
            </tr>
            
            <tr>
                <th scope="row"><label for="default_currency">Валюта по умолчанию</label></th>
                <td>
                    <select id="default_currency" name="default_currency" class="regular-text">
                        <option value="RUR" <?php selected($settings['default_currency'] ?? 'RUR', 'RUR'); ?>>RUR (российский рубль)</option>
                        <option value="USD" <?php selected($settings['default_currency'] ?? 'RUR', 'USD'); ?>>USD (доллар США)</option>
                        <option value="EUR" <?php selected($settings['default_currency'] ?? 'RUR', 'EUR'); ?>>EUR (евро)</option>
                    </select>
                    <p class="description">
                        Яндекс.Здоровье требует RUR (по умолчанию)<br>
                        ⚠️ <strong>v2.2.0:</strong> Хардкод удалён - теперь настраивается!
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="default_service_name">Название услуги по умолчанию</label></th>
                <td>
                    <input type="text" 
                           id="default_service_name" 
                           name="default_service_name" 
                           value="<?php echo esc_attr($settings['default_service_name'] ?? 'Первичный прием'); ?>" 
                           class="regular-text">
                    <p class="description">
                        Используется когда у врача нет услуг (по умолчанию: "Первичный прием")<br>
                        ⚠️ <strong>v2.2.0:</strong> Хардкод удалён - теперь настраивается!
                    </p>
                </td>
            </tr>
            
            <tr>
                <th colspan="2"><h2>🎯 Настройки v2.0 (только для формата v2.0)</h2></th>
            </tr>
            
            <tr>
                <th scope="row"><label for="specialties_no_primary_source">Источник исключений (без первичного приёма)</label></th>
                <td>
                    <select id="specialties_no_primary_source" name="specialties_no_primary_source" class="regular-text">
                        <option value="taxonomy" <?php selected($settings['specialties_no_primary_source'] ?? 'taxonomy', 'taxonomy'); ?>>Таксономия (чекбоксы терминов)</option>
                        <option value="field" <?php selected($settings['specialties_no_primary_source'] ?? 'taxonomy', 'field'); ?>>Произвольное поле (условие)</option>
                    </select>
                    <p class="description">Специальности, для которых НЕ требуется "первичный приём"</p>
                </td>
            </tr>
            
            <!-- CPT для исключений -->
            <tr id="yfgp-exclusions-cpt-row" style="display: none;">
                <th scope="row"><label for="exclusions_cpt">Тип записи</label></th>
                <td>
                    <select id="exclusions_cpt" name="exclusions_cpt" class="regular-text">
                        <option value="">-- Выберите тип записи --</option>
                        <?php foreach ($post_types as $pt): ?>
                            <option value="<?php echo esc_attr($pt->name); ?>" 
                                    <?php selected($settings['exclusions_cpt'] ?? '', $pt->name); ?>>
                                <?php echo esc_html($pt->label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Тип записи, для которого настраиваются исключения</p>
                </td>
            </tr>
            
            <!-- Таксономия для исключений -->
            <tr id="yfgp-exclusions-taxonomy-row" style="display: none;">
                <th scope="row"><label for="exclusions_taxonomy">Таксономия</label></th>
                <td>
                    <select id="exclusions_taxonomy" name="exclusions_taxonomy" class="regular-text">
                        <option value="">-- Выберите таксономию --</option>
                        <?php
                        $taxonomies = get_taxonomies(array('public' => true), 'objects');
                        foreach ($taxonomies as $taxonomy) {
                            if ($taxonomy->name !== 'post_format') {
                                echo '<option value="' . esc_attr($taxonomy->name) . '" ' . selected($settings['exclusions_taxonomy'] ?? '', $taxonomy->name, false) . '>' . esc_html($taxonomy->label) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <p class="description">Таксономия для фильтрации исключений</p>
                </td>
            </tr>
            
            <!-- Термины таксономии (чекбоксы) -->
            <tr id="yfgp-exclusions-terms-row" style="display: none;">
                <th scope="row"><label>Исключить термины</label></th>
                <td>
                    <div id="yfgp-exclusions-terms-list">
                        <p class="description">Сначала выберите таксономию</p>
                    </div>
                </td>
            </tr>
            
            <!-- Произвольное поле для исключений -->
            <tr id="yfgp-exclusions-field-row" style="display: none;">
                <th scope="row"><label for="exclusions_field">Поле</label></th>
                <td>
                    <select id="exclusions_field" name="exclusions_field" class="regular-text" disabled>
                        <option value="">-- Сначала выберите тип записи --</option>
                    </select>
                    <p class="description">Произвольное поле для условия исключения</p>
                    <div id="yfgp-exclusions-field-loading" style="display: none; margin-top: 10px;">
                        ⏳ Загрузка полей...
                    </div>
                </td>
            </tr>
            
            <!-- Условие для поля -->
            <tr id="yfgp-exclusions-condition-row" style="display: none;">
                <th scope="row"><label for="exclusions_operator">Условие</label></th>
                <td>
                    <select id="exclusions_operator" name="exclusions_operator" class="regular-text" style="width: 150px;">
                        <option value="equals" <?php selected($settings['exclusions_operator'] ?? 'equals', 'equals'); ?>>=  (равно)</option>
                        <option value="not_equals" <?php selected($settings['exclusions_operator'] ?? 'equals', 'not_equals'); ?>>!= (не равно)</option>
                        <option value="in_array" <?php selected($settings['exclusions_operator'] ?? 'equals', 'in_array'); ?>>∈ (входит в список)</option>
                        <option value="not_in_array" <?php selected($settings['exclusions_operator'] ?? 'equals', 'not_in_array'); ?>>∉ (НЕ входит в список)</option>
                        <option value="empty" <?php selected($settings['exclusions_operator'] ?? 'equals', 'empty'); ?>>Пусто</option>
                        <option value="not_empty" <?php selected($settings['exclusions_operator'] ?? 'equals', 'not_empty'); ?>>Не пусто</option>
                    </select>
                    
                    <input type="text" id="exclusions_value" name="exclusions_value" value="<?php echo esc_attr($settings['exclusions_value'] ?? ''); ?>" class="regular-text" placeholder="Значение" style="margin-left: 10px;">
                    
                    <p class="description">
                        <strong>Условие для исключения специальностей:</strong><br>
                        • <code>=</code> / <code>!=</code> - точное сравнение<br>
                        • <code>∈</code> / <code>∉</code> - входит/НЕ входит в список (через запятую)<br>
                        • <code>Пусто</code> / <code>Не пусто</code> - проверка на пустоту<br>
                        <strong>Примеры:</strong> "УЗИ, Массажист" или "false" или оставить пустым
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="generate_all_services">Генерировать все услуги</label></th>
                <td>
                    <label>
                        <input type="checkbox" id="generate_all_services" name="generate_all_services" value="1" <?php checked($settings['generate_all_services'] ?? false); ?>>
                        Создавать офферы для ВСЕХ услуг врача (не только базовой)
                    </label>
                    <p class="description">⚠️ Если включено - количество офферов увеличится в ~10 раз! Рекомендуется оставить выключенным, так как дополнительные услуги пока не отображаются в Яндексе.</p>
                </td>
            </tr>
            
            <tr>
                <th colspan="2"><h2>⏰ Автообновление</h2></th>
            </tr>
            
            <tr>
                <th scope="row"><label for="auto_update">Включить автообновление</label></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="auto_update" 
                               name="auto_update" 
                               value="1" 
                               <?php checked($settings['auto_update'] ?? false); ?>>
                        Автоматически обновлять фид по расписанию
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="update_interval">Интервал обновления</label></th>
                <td>
                    <select id="update_interval" name="update_interval" class="regular-text">
                        <?php foreach ($intervals as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" 
                                    <?php selected($settings['update_interval'] ?? 'daily', $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <button type="submit" name="yfgp_save_settings" class="button button-primary">
                💾 Сохранить настройки
            </button>
        </p>
    </form>
    
    <hr>
    
    <h2>💾 Экспорт/Импорт конфигурации</h2>
    <p class="description">Сохраните или загрузите настройки и маппинг полей для переноса между сайтами или резервного копирования.</p>
    
    <table class="form-table">
        <tr>
            <th scope="row">Экспорт</th>
            <td>
                <button type="button" id="yfgp-export-config" class="button">
                    📥 Экспортировать конфигурацию
                </button>
                <p class="description">Скачает JSON файл с настройками, маппингом и специальностями</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Импорт</th>
            <td>
                <input type="file" id="yfgp-import-file" accept=".json" style="display: none;">
                <button type="button" id="yfgp-import-config" class="button">
                    📤 Импортировать конфигурацию
                </button>
                <p class="description">Загрузите JSON файл с настройками</p>
            </td>
        </tr>
    </table>
    
    <hr>
    
    <h2>📊 Текущий статус</h2>
    <table class="widefat">
        <tr>
            <td><strong>Следующее обновление:</strong></td>
            <td>
                <?php
                $next_run = wp_next_scheduled('yfgp_auto_update_feed');
                if ($next_run) {
                    echo date('Y-m-d H:i:s', $next_run);
                } else {
                    echo 'Не запланировано';
                }
                ?>
            </td>
        </tr>
        <tr>
            <td><strong>URL фида:</strong></td>
            <td>
                <?php
                $file_manager = new YFGP_File_Manager();
                $feed_url = $file_manager->get_feed_url('doctors.yml');
                ?>
                <a href="<?php echo esc_url($feed_url); ?>" target="_blank"><?php echo esc_html($feed_url); ?></a>
            </td>
        </tr>
    </table>
</div>

<script>
// Version: <?php echo time(); ?> - Force reload
jQuery(document).ready(function($) {
    console.log('✅ Settings.php loaded at <?php echo date("Y-m-d H:i:s"); ?>');
    
    // Экспорт конфигурации
    $('#yfgp-export-config').on('click', function() {
        const button = $(this);
        button.prop('disabled', true).text('⏳ Экспорт...');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'yfgp_export_config',
                nonce: '<?php echo wp_create_nonce('yfgp_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    const config = JSON.stringify(response.data, null, 2);
                    const blob = new Blob([config], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'yandex-feed-config-' + new Date().toISOString().slice(0, 10) + '.json';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    
                    alert('✅ Конфигурация экспортирована!');
                } else {
                    alert('❌ Ошибка: ' + response.data);
                }
            },
            error: function() {
                alert('❌ Ошибка сети');
            },
            complete: function() {
                button.prop('disabled', false).text('📥 Экспортировать конфигурацию');
            }
        });
    });
    
    // Импорт конфигурации
    $('#yfgp-import-config').on('click', function() {
        $('#yfgp-import-file').click();
    });
    
    $('#yfgp-import-file').on('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        const reader = new FileReader();
        reader.onload = function(event) {
            const configJson = event.target.result;
            
            if (!confirm('Импортировать конфигурацию? Текущие настройки будут перезаписаны.')) {
                return;
            }
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'yfgp_import_config',
                    nonce: '<?php echo wp_create_nonce('yfgp_nonce'); ?>',
                    config_json: configJson
                },
                success: function(response) {
                    if (response.success) {
                        alert('✅ Конфигурация импортирована!');
                        location.reload();
                    } else {
                        alert('❌ Ошибка: ' + response.data);
                    }
                },
                error: function() {
                    alert('❌ Ошибка сети');
                }
            });
        };
        reader.readAsText(file);
        
        // Сброс input для возможности повторной загрузки того же файла
        $(this).val('');
    });
    
    // Управление отображением полей для источника специальностей
    function toggleSetsFields() {
        var source = $('#sets_source').val();
        
        // Скрываем все поля
        $('#yfgp-sets-taxonomy-row, #yfgp-sets-field-cpt-row, #yfgp-sets-field-row').hide();
        
        if (source === 'taxonomy') {
            $('#yfgp-sets-taxonomy-row').show();
        } else if (source === 'field') {
            $('#yfgp-sets-field-cpt-row').show();
            $('#yfgp-sets-field-row').show();
        }
        
        // Обновляем подсказку для URL
        updateSetsUrlHint();
    }
    
    // Обновление подсказки в зависимости от источника
    function updateSetsUrlHint() {
        var source = $('#sets_source').val();
        var $hint = $('#sets-url-method-hint');
        
        if (source === 'taxonomy') {
            $hint.html('✅ <strong>Таксономия:</strong> Уникальные URL через get_term_link() для каждой специальности');
        } else if (source === 'field') {
            $hint.html('✅ <strong>Произвольное поле:</strong> Общий URL архива через get_post_type_archive_link()');
        }
    }
    
    // Загрузка полей при выборе CPT
    $('#sets_field_cpt').on('change', function() {
        var postType = $(this).val();
        var $setsField = $('#sets_field');
        var $loading = $('#yfgp-field-loading');
        
        if (!postType) {
            $setsField.html('<option value="">-- Сначала выберите тип записи --</option>').prop('disabled', true);
            return;
        }
        
        // Показываем загрузку
        $loading.show();
        $setsField.prop('disabled', true);
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'yfgp_get_fields',
                nonce: '<?php echo wp_create_nonce('yfgp_nonce'); ?>',
                post_type: postType
            },
            success: function(response) {
                if (response.success) {
                    var fields = response.data;
                    var options = '<option value="">-- Выберите поле --</option>';
                    
                    // ACF поля
                    if (fields.acf && Object.keys(fields.acf).length > 0) {
                        options += '<optgroup label="ACF Поля">';
                        $.each(fields.acf, function(key, field) {
                            var fieldInfo = 'ACF: ' + field.label;
                            if (field.type) {
                                fieldInfo += ' (' + field.type + ')';
                            }
                            if (field.group) {
                                fieldInfo += ' [' + field.group + ']';
                            }
                            options += '<option value="' + key + '">' + fieldInfo + '</option>';
                        });
                        options += '</optgroup>';
                    }
                    
                    // JetEngine поля
                    if (fields.jetengine && Object.keys(fields.jetengine).length > 0) {
                        options += '<optgroup label="JetEngine Поля">';
                        $.each(fields.jetengine, function(key, field) {
                            var fieldInfo = 'JetEngine: ' + field.label;
                            if (field.type) {
                                fieldInfo += ' (' + field.type + ')';
                            }
                            if (field.group) {
                                fieldInfo += ' [' + field.group + ']';
                            }
                            options += '<option value="' + key + '">' + fieldInfo + '</option>';
                        });
                        options += '</optgroup>';
                    }
                    
                    // Meta поля
                    if (fields.meta && Object.keys(fields.meta).length > 0) {
                        options += '<optgroup label="Meta Поля">';
                        $.each(fields.meta, function(key, field) {
                            var fieldInfo = 'Meta: ' + field.label;
                            if (field.type) {
                                fieldInfo += ' (' + field.type + ')';
                            }
                            options += '<option value="' + key + '">' + fieldInfo + '</option>';
                        });
                        options += '</optgroup>';
                    }
                    
                    $setsField.html(options).prop('disabled', false);
                    
                    // Восстанавливаем сохранённое значение
                    var savedField = '<?php echo esc_js($settings['sets_field'] ?? ''); ?>';
                    if (savedField) {
                        $setsField.val(savedField);
                    }
                } else {
                    alert('❌ Ошибка загрузки полей: ' + response.data);
                    $setsField.html('<option value="">-- Ошибка загрузки --</option>');
                }
            },
            error: function() {
                alert('❌ Ошибка сети');
                $setsField.html('<option value="">-- Ошибка сети --</option>');
            },
            complete: function() {
                $loading.hide();
            }
        });
    });
    
    // Управление отображением полей для исключений
    // Управление отображением полей для исключений
    function toggleExclusionsFields() {
        var source = $('#specialties_no_primary_source').val();
        
        // Скрываем все поля
        $('#yfgp-exclusions-cpt-row, #yfgp-exclusions-taxonomy-row, #yfgp-exclusions-terms-row, #yfgp-exclusions-field-row, #yfgp-exclusions-condition-row').hide();
        
        if (source === 'taxonomy') {
            $('#yfgp-exclusions-cpt-row').show();
            $('#yfgp-exclusions-taxonomy-row').show();
            // Проверяем, выбрана ли таксономия - показываем термины
            if ($('#exclusions_taxonomy').val()) {
                $('#yfgp-exclusions-terms-row').show();
            }
        } else if (source === 'field') {
            $('#yfgp-exclusions-cpt-row').show();
            $('#yfgp-exclusions-field-row').show();
            $('#yfgp-exclusions-condition-row').show();
        }
    }
    
    // Загрузка терминов таксономии для чекбоксов
    function loadExclusionTerms() {
        var taxonomy = $('#exclusions_taxonomy').val();
        var $termsList = $('#yfgp-exclusions-terms-list');
        
        if (!taxonomy) {
            $termsList.html('<p class="description">Сначала выберите таксономию</p>');
            $('#yfgp-exclusions-terms-row').hide();
            return;
        }
        
        $termsList.html('<p>⏳ Загрузка терминов...</p>');
        $('#yfgp-exclusions-terms-row').show();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'yfgp_get_terms',
                taxonomy: taxonomy,
                _ajax_nonce: '<?php echo wp_create_nonce('yfgp_ajax_nonce'); ?>'
            },
            success: function(response) {
                if (response.success && response.data.terms) {
                    var savedTerms = <?php echo json_encode($settings['exclusions_terms'] ?? array()); ?>;
                    var html = '<div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">';
                    
                    $.each(response.data.terms, function(slug, name) {
                        var checked = savedTerms.indexOf(slug) !== -1 ? 'checked' : '';
                        html += '<label style="display: block; margin-bottom: 5px;">';
                        html += '<input type="checkbox" name="exclusions_terms[]" value="' + slug + '" ' + checked + '> ';
                        html += name;
                        html += '</label>';
                    });
                    
                    html += '</div>';
                    $termsList.html(html);
                } else {
                    $termsList.html('<p class="description" style="color: #dc3232;">Ошибка загрузки терминов</p>');
                }
            },
            error: function() {
                $termsList.html('<p class="description" style="color: #dc3232;">Ошибка запроса</p>');
            }
        });
    }
    
    // Загрузка полей для исключений (при выборе CPT в режиме поля)
    function loadExclusionFields() {
        var postType = $('#exclusions_cpt').val();
        var $exclusionsField = $('#exclusions_field');
        var $loading = $('#yfgp-exclusions-field-loading');
        
        if (!postType) {
            $exclusionsField.html('<option value="">-- Сначала выберите тип записи --</option>').prop('disabled', true);
            return;
        }
        
        $loading.show();
        $exclusionsField.prop('disabled', true);
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'yfgp_get_fields',
                post_type: postType,
                nonce: '<?php echo wp_create_nonce('yfgp_nonce'); ?>'
            },
            success: function(response) {
                $loading.hide();
                
                if (response.success && response.data) {
                    var fields = response.data;
                    var options = '<option value="">-- Выберите поле --</option>';
                    var savedField = '<?php echo esc_js($settings['exclusions_field'] ?? ''); ?>';
                    
                    // WordPress поля
                    if (fields.wordpress && Object.keys(fields.wordpress).length > 0) {
                        options += '<optgroup label="WordPress">';
                        $.each(fields.wordpress, function(key, field) {
                            var selected = (key === savedField) ? 'selected' : '';
                            options += '<option value="' + key + '" ' + selected + '>WP: ' + field.label + '</option>';
                        });
                        options += '</optgroup>';
                    }
                    
                    // ACF поля
                    if (fields.acf && Object.keys(fields.acf).length > 0) {
                        options += '<optgroup label="ACF">';
                        $.each(fields.acf, function(key, field) {
                            var selected = (key === savedField) ? 'selected' : '';
                            var fieldInfo = 'ACF: ' + field.label;
                            if (field.type) { fieldInfo += ' (' + field.type + ')'; }
                            options += '<option value="' + key + '" ' + selected + '>' + fieldInfo + '</option>';
                        });
                        options += '</optgroup>';
                    }
                    
                    // JetEngine поля
                    if (fields.jetengine && Object.keys(fields.jetengine).length > 0) {
                        options += '<optgroup label="JetEngine">';
                        $.each(fields.jetengine, function(key, field) {
                            var selected = (key === savedField) ? 'selected' : '';
                            var fieldInfo = 'JE: ' + field.label;
                            if (field.type) { fieldInfo += ' (' + field.type + ')'; }
                            options += '<option value="' + key + '" ' + selected + '>' + fieldInfo + '</option>';
                        });
                        options += '</optgroup>';
                    }
                    
                    // Meta поля
                    if (fields.meta && Object.keys(fields.meta).length > 0) {
                        options += '<optgroup label="Meta">';
                        $.each(fields.meta, function(key, field) {
                            var selected = (key === savedField) ? 'selected' : '';
                            options += '<option value="' + key + '" ' + selected + '>Meta: ' + field.label + '</option>';
                        });
                        options += '</optgroup>';
                    }
                    
                    $exclusionsField.html(options).prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                $loading.hide();
                alert('Ошибка загрузки полей: ' + error);
            }
        });
    }
    
    // Инициализация
    toggleSetsFields();
    toggleExclusionsFields();
    
    // Авто-загрузка полей если CPT уже выбран
    var initialCpt = $('#sets_field_cpt').val();
    if (initialCpt && $('#sets_source').val() === 'field') {
        $('#sets_field_cpt').trigger('change');
    }
    
    // Авто-выбор CPT для URL если источник = поле и метод = авто
    if ($('#sets_source').val() === 'field' && $('#sets_url_method').val() === 'auto') {
        var fieldCpt = $('#sets_field_cpt').val();
        if (fieldCpt) {
            $('#sets_url_cpt').val(fieldCpt);
        }
    }
    
    // Обработчики событий (с проверкой существования элементов)
    if ($('#sets_source').length) {
        $('#sets_source').on('change', function() {
            toggleSetsFields();
        });
    }
    
    if ($('#specialties_no_primary_source').length) {
        $('#specialties_no_primary_source').on('change', toggleExclusionsFields);
    }
    
    // Обработчики для исключений - CPT
    try {
        if ($('#exclusions_cpt').length) {
            $('#exclusions_cpt').on('change', function() {
                var source = $('#specialties_no_primary_source').val();
                if (source === 'field') {
                    loadExclusionFields();
                }
            });
        }
    } catch (error) {
        console.error('Ошибка в обработчике CPT:', error);
    }
    
    // Обработчики для исключений - таксономия
    if ($('#exclusions_taxonomy').length) {
        $('#exclusions_taxonomy').on('change', loadExclusionTerms);
    }
    
    // Обработчик изменения оператора (показ/скрытие поля значения)
    if ($('#exclusions_operator').length) {
        $('#exclusions_operator').on('change', function() {
            var operator = $(this).val();
            if (operator === 'empty' || operator === 'not_empty') {
                $('#exclusions_value').hide();
            } else {
                $('#exclusions_value').show();
            }
        });
    }
    
    // Инициализация при загрузке страницы
    toggleSetsFields();
    toggleExclusionsFields();
    
    // Проверка при загрузке - если уже выбран CPT для field режима
    if ($('#specialties_no_primary_source').val() === 'field' && $('#exclusions_cpt').val()) {
        loadExclusionFields();
    }
    
    // Проверка при загрузке - если уже выбрана таксономия
    if ($('#specialties_no_primary_source').val() === 'taxonomy' && $('#exclusions_taxonomy').val()) {
        loadExclusionTerms();
    }
    
});
</script>

