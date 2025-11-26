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

// v4.18.21: Безопасное создание Cron Manager с обработкой ошибок
try {
    $cron_manager = new YFGP_Cron_Manager();
    $intervals = $cron_manager->get_intervals();
} catch (\Throwable $e) {
    // Fallback на пустой массив интервалов при ошибке
    $intervals = array('disabled' => 'Отключено');
    if (function_exists('error_log') && defined('WP_DEBUG') && WP_DEBUG) {
        error_log('YFGP: Failed to create Cron Manager in settings.php: ' . $e->getMessage());
    }
}
?>

<div class="wrap">
    <h1>⚙️ Настройки Yandex Feed Generator (v<?php echo YFGP_VERSION; ?>)</h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('yfgp_settings_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th colspan="2"><h2>📋 Информация о компании</h2></th>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="shop_name">
                        Название площадки * 
                        <span style="display: block; font-weight: normal; font-size: 12px; color: #666; margin-top: 4px;">
                            (Яндекс.Здоровье)
                        </span>
                    </label>
                </th>
                <td>
                    <input type="text" 
                           id="shop_name" 
                           name="shop_name" 
                           value="<?php echo esc_attr(wp_unslash($settings['shop_name'] ?? get_bloginfo('name'))); ?>" 
                           class="regular-text" 
                           maxlength="30"
                           required
                           placeholder="Например: Яндекс.Здоровье">
                    <p class="description">
                        <strong>shop/name</strong> - Название площадки для отображения в карточке врачей (макс. 30 символов)<br>
                        <em>По умолчанию: "Яндекс.Здоровье". Можно изменить при необходимости.</em><br>
                        <span id="shop_name_counter">0/30</span> символов
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="company_name">Название организации *</label></th>
                <td>
                    <input type="text" 
                           id="company_name" 
                           name="company_name" 
                           value="<?php echo esc_attr(wp_unslash($settings['company_name'] ?? get_bloginfo('name'))); ?>" 
                           class="regular-text" 
                           required>
                    <p class="description">
                        <strong>shop/company</strong> - Название организации (автозаполнение из "Название сайта")<br>
                        Используется при проверках, в карточке врача не отображается
                    </p>
                </td>
            </tr>

            <tr>
                <th colspan="2"><h2>Специализация по умолчанию</h2></th>
            </tr>
            <tr>
                <th scope="row">
                    <label for="fallback_speciality">Выберите специализацию</label>
                </th>
                <td>
                    <?php
                    $yandex_specialities = yfgp_get_specialities_reference_labels();
                    $use_custom_speciality = !empty($settings['fallback_speciality_custom']);
                    $selected_speciality = $use_custom_speciality ? '' : trim((string) ($settings['fallback_speciality'] ?? ''));
                    $custom_speciality_value = $use_custom_speciality ? trim((string) ($settings['fallback_speciality_custom'] ?? '')) : '';
                    ?>
                    <select id="fallback_speciality" name="fallback_speciality" class="regular-text" <?php disabled($use_custom_speciality); ?>>
                        <option value="">— Не выбрано —</option>
                        <?php foreach ($yandex_specialities as $speciality_label) : ?>
                            <option value="<?php echo esc_attr($speciality_label); ?>" <?php selected($selected_speciality, $speciality_label); ?>>
                                <?php echo esc_html($speciality_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        Если у врача не заполнены поля «Специализация», будет использовано это значение. Список составлен по <a href="https://yandex.ru/support/webmaster/ru/search-appearance/doctors#yml" target="_blank" rel="noreferrer noopener">официальной документации Яндекс.Здоровье</a>.
                    </p>
                    <p>
                        <label>
                            <input type="checkbox" id="fallback_speciality_custom_toggle" name="fallback_speciality_custom_toggle" value="1" <?php checked($use_custom_speciality); ?>>
                            Или ввести свою специализацию (если нет в списке)
                        </label>
                    </p>
                    <p id="fallback_speciality_custom_wrapper" style="<?php echo $use_custom_speciality ? '' : 'display: none;'; ?>">
                        <input type="text"
                               id="fallback_speciality_custom"
                               name="fallback_speciality_custom"
                               class="regular-text"
                               value="<?php echo esc_attr($custom_speciality_value); ?>"
                               placeholder="Например: Общий врач"
                               <?php echo $use_custom_speciality ? '' : 'disabled'; ?>>
                        <br>
                        <span class="description">Текст будет использован в теге speciality, slug определится автоматически по правилам WordPress.</span>
                    </p>
                    <script>
                    jQuery(function($) {
                        const $toggle = $('#fallback_speciality_custom_toggle');
                        const $select = $('#fallback_speciality');
                        const $customWrapper = $('#fallback_speciality_custom_wrapper');
                        const $customInput = $('#fallback_speciality_custom');

                        if ($toggle.is(':checked')) {
                            $select.prop('disabled', true);
                            $customInput.prop('disabled', false);
                        } else {
                            $select.prop('disabled', false);
                            $customInput.prop('disabled', true);
                        }

                        $toggle.on('change', function() {
                            if ($toggle.is(':checked')) {
                                $customWrapper.slideDown();
                                $customInput.prop('disabled', false);
                                $select.prop('disabled', true).val('');
                            } else {
                                $customWrapper.slideUp();
                                $customInput.prop('disabled', true).val('');
                                $select.prop('disabled', false);
                            }
                        });
                    });
                    </script>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="company_url">URL площадки *</label></th>
                <td>
                    <input type="url" 
                           id="company_url" 
                           name="company_url" 
                           value="<?php echo esc_attr($settings['company_url'] ?? get_site_url()); ?>" 
                           class="regular-text" 
                           required>
                    <p class="description">
                        <strong>shop/url</strong> - URL-адрес площадки или сайта клиники (автозаполнение из домена сайта)
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="shop_picture">Логотип площадки *</label></th>
                <td>
                    <input type="url" 
                           id="shop_picture" 
                           name="shop_picture" 
                           value="<?php echo esc_attr($settings['shop_picture'] ?? ''); ?>" 
                           class="regular-text" 
                           required>
                    <p class="description">
                        <strong>shop/picture</strong> - Ссылка на логотип площадки (мин. 100×100px)<br>
                        Логотип может быть на фоне любого цвета, на белом фоне должен занимать максимальную площадь
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="company_email">Email для обращений</label></th>
                <td>
                    <input type="email" 
                           id="company_email" 
                           name="company_email" 
                           value="<?php echo esc_attr($settings['company_email'] ?? get_option('admin_email')); ?>" 
                           class="regular-text">
                    <p class="description">
                        <strong>shop/email</strong> - Адрес электронной почты для обращений (автозаполнение из email администратора)
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="city">Город</label></th>
                <td>
                    <input type="text" 
                           id="city" 
                           name="city" 
                           value="<?php echo esc_attr(wp_unslash($settings['city'] ?? '')); ?>" 
                           class="regular-text" 
                           placeholder="г. Севастополь">
                    <p class="description">
                        <strong>Город по умолчанию для клиник</strong> - Используется если город не указан в маппинге полей клиники.
                    </p>
                </td>
            </tr>
            
            <tr>
                <th colspan="2"><h2>🎯 Настройки фида</h2></th>
            </tr>
            
            <!-- v4.19.1: Feed format field removed from UI (technical info, not needed for users) -->
            <input type="hidden" id="feed_format" name="feed_format" value="v2">
            
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
                <th scope="row"><label for="cpt_clinics">Тип записи для клиник</label></th>
                <td>
                    <select id="cpt_clinics" name="cpt_clinics" class="regular-text">
                        <option value="">-- Одна клиника (заполнить через маппинг) --</option>
                        <?php foreach ($post_types as $pt): ?>
                            <option value="<?php echo esc_attr($pt->name); ?>" 
                                    <?php selected($settings['cpt_clinics'] ?? '', $pt->name); ?>>
                                <?php echo esc_html($pt->label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <strong>Режим "Одна клиника":</strong> Если не выбран CPT, все врачи будут автоматически связаны с одной клиникой. 
                        Данные клиники заполняются на странице <a href="<?php echo admin_url('admin.php?page=yandex-feed-generator-mapping'); ?>">Маппинг полей</a> в табе "Клиники".
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="cpt_services">Тип записи для услуг</label></th>
                <td>
                    <select id="cpt_services" name="cpt_services" class="regular-text">
                        <option value="">-- не выбрано --</option>
                        <?php foreach ($post_types as $pt): ?>
                            <option value="<?php echo esc_attr($pt->name); ?>" 
                                    <?php selected($settings['cpt_services'] ?? '', $pt->name); ?>>
                                <?php echo esc_html($pt->label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr><tr>
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
                <td colspan="2">
                    <div class="notice notice-info inline" style="margin: 15px 0; padding: 15px; background: #f0f8ff; border-left: 4px solid #2271b1;">
                        <h3 style="margin-top: 0; margin-bottom: 10px;">📬 Настройка отправки email</h3>
                        <p style="margin-bottom: 10px;">
                            <strong>Для работы email уведомлений требуется настройка SMTP сервера.</strong> По умолчанию WordPress использует PHP функцию <code>mail()</code>, 
                            которая может не работать на многих хостингах и в Docker контейнерах без дополнительной настройки.
                        </p>
                        <p style="margin-bottom: 10px;"><strong>Рекомендуемые способы настройки:</strong></p>
                        <ol style="margin-left: 20px; margin-bottom: 10px;">
                            <li style="margin-bottom: 8px;">
                                <strong>Установить SMTP плагин (рекомендуется для всех WordPress сайтов):</strong>
                                <ul style="margin-left: 20px; margin-top: 5px;">
                                    <li>Установите плагин <strong>WP Mail SMTP</strong> (<a href="https://wordpress.org/plugins/wp-mail-smtp/" target="_blank">wordpress.org/plugins/wp-mail-smtp</a>)</li>
                                    <li>Перейдите в <code>Настройки → WP Mail SMTP</code></li>
                                    <li>Выберите SMTP провайдера (Gmail, Outlook, SendGrid, Mailgun или другой SMTP сервер)</li>
                                    <li>Введите credentials и отправьте тестовое письмо</li>
                                    <li>✅ Работает на любом WordPress сайте (хостинг, VPS, Docker)</li>
                                </ul>
                            </li>
                            <li style="margin-bottom: 8px;">
                                <strong>Настроить системный SMTP (для VPS/серверов с доступом к конфигурации):</strong>
                                <ul style="margin-left: 20px; margin-top: 5px;">
                                    <li><strong>Для Docker контейнеров:</strong> Отредактируйте файл <code>/etc/msmtprc</code> в контейнере</li>
                                    <li><strong>Для обычных серверов:</strong> Настройте sendmail/postfix через системные конфигурационные файлы</li>
                                    <li>Укажите SMTP сервер, порт, логин и пароль</li>
                                    <li>Для Gmail используйте App Password: <a href="https://support.google.com/accounts/answer/185833" target="_blank">support.google.com/accounts/answer/185833</a></li>
                                    <li>Пример конфигурации msmtp для Gmail (Docker):
                                        <pre style="background: #f5f5f5; padding: 10px; margin-top: 5px; overflow-x: auto; font-size: 12px;"><code>account gmail
host smtp.gmail.com
port 587
from your-email@gmail.com
auth on
user your-email@gmail.com
password YOUR_APP_PASSWORD
tls on
tls_starttls on

account default : gmail</code></pre>
                                    </li>
                                </ul>
                            </li>
                        </ol>
                        <p style="margin-bottom: 0;">
                            <strong>⚠️ Важно:</strong> После настройки SMTP обязательно протестируйте отправку email, включив уведомления выше и выполнив обновление фида. 
                            Если письма не приходят, проверьте папку "Спам" и логи сервера.
                        </p>
                    </div>
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
                           value="<?php echo esc_attr(wp_unslash($settings['default_service_name'] ?? 'Первичный прием')); ?>" 
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
                    
                    <input type="text" id="exclusions_value" name="exclusions_value" value="<?php echo esc_attr(wp_unslash($settings['exclusions_value'] ?? '')); ?>" class="regular-text" placeholder="Значение" style="margin-left: 10px;">
                    
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
            
            <!-- ========================================= -->
            <!-- СЕКЦИЯ: ИСТОЧНИКИ ДАННЫХ v3.0.0          -->
            <!-- ========================================= -->
            
            <tr>
                <th colspan="2"><h2>🔧 Источники данных для врачей (v3.0)</h2></th>
            </tr>
            
            <tr>
                <td colspan="2">
                    <p class="description" style="margin-bottom: 20px;">
                        Настройте где плагин будет получать данные для образования, мест работы и сертификатов врачей.
                    </p>
                    
                    <!-- Режим определения базовой услуги -->
                        <div class="yfgp-data-source-card">
                            <div class="card-header">
                                <div class="header-left">
                                    <h3>🎯 Режим определения базовой услуги</h3>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <p class="card-description">Выберите как плагин будет определять базовую услугу для офферов:</p>
                                
                                <label class="radio-option">
                                    <input type="radio" 
                                           name="base_service_mode" 
                                           value="automatic"
                                           <?php checked(($settings['base_service_mode'] ?? 'automatic'), 'automatic'); ?>>
                                    <div class="radio-content">
                                        <strong>Автоматический (рекомендуется)</strong>
                                        <p class="radio-description">Комбинированная логика (новая + старая): явное указание → "первичный прием" → маппинг → первая услуга → автосоздание</p>
                                    </div>
                                </label>
                                
                                <label class="radio-option">
                                    <input type="radio" 
                                           name="base_service_mode" 
                                           value="strict"
                                           <?php checked(($settings['base_service_mode'] ?? 'automatic'), 'strict'); ?>>
                                    <div class="radio-content">
                                        <strong>Строгий</strong>
                                        <p class="radio-description">Только новая логика: явное указание → "первичный прием" → первая услуга → ОШИБКА (не создаёт оффер)</p>
                                    </div>
                                </label>
                                
                            </div>
                        </div>
                        

                    <div class="yfgp-data-sources-section">
                        <!-- Карточка 1: Образование -->
                        <div class="yfgp-data-source-card">
                            <div class="card-header">
                                <div class="header-left">
                                    <h3>📚 Образование (education)</h3>
                                </div>
                                <div class="header-right">
                                    <span class="status-badge" id="education-status">⏳ Проверка...</span>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <p class="card-description">Выберите источник данных для образования врачей:</p>
                                
                                <label class="radio-option" data-option="repeater">
                                    <input type="radio" 
                                           name="source_education" 
                                           value="repeater"
                                           <?php checked(($settings['source_education'] ?? 'repeater'), 'repeater'); ?>>
                                    <div class="radio-content">
                                        <strong>Repeater field</strong>
                                        <p class="radio-description">Использовать ACF/JetEngine repeater поле</p>
                                    </div>
                                </label>
                                
                                <label class="radio-option" data-option="relationship">
                                    <input type="radio" 
                                           name="source_education" 
                                           value="relationship"
                                           <?php checked(($settings['source_education'] ?? 'repeater'), 'relationship'); ?>>
                                    <div class="radio-content">
                                        <strong>Relationship к CPT</strong>
                                        <p class="radio-description">Связь с пользовательским типом записи</p>
                                        
                                        <div class="cpt-selector" style="display: <?php echo (($settings['source_education'] ?? 'repeater') === 'relationship') ? 'block' : 'none'; ?>;">
                                            <label>Выберите CPT:</label>
                                            <select name="source_education_cpt" class="cpt-dropdown" id="education-cpt-dropdown">
                                                <option value="">⏳ Загрузка...</option>
                                            </select>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="card-footer" id="education-footer">
                                <p class="info-text">⏳ Загрузка информации...</p>
                            </div>
                        </div>
                        
                        <!-- Карточка 2: Места работы -->
                        <div class="yfgp-data-source-card">
                            <div class="card-header">
                                <div class="header-left">
                                    <h3>💼 Места работы (jobs)</h3>
                                </div>
                                <div class="header-right">
                                    <span class="status-badge" id="jobs-status">⏳ Проверка...</span>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <p class="card-description">Выберите источник данных для мест работы:</p>
                                
                                <label class="radio-option" data-option="repeater">
                                    <input type="radio" 
                                           name="source_jobs" 
                                           value="repeater"
                                           <?php checked(($settings['source_jobs'] ?? 'repeater'), 'repeater'); ?>>
                                    <div class="radio-content">
                                        <strong>Repeater field</strong>
                                        <p class="radio-description">Использовать ACF/JetEngine repeater поле</p>
                                    </div>
                                </label>
                                
                                <label class="radio-option" data-option="relationship">
                                    <input type="radio" 
                                           name="source_jobs" 
                                           value="relationship"
                                           <?php checked(($settings['source_jobs'] ?? 'repeater'), 'relationship'); ?>>
                                    <div class="radio-content">
                                        <strong>Relationship к CPT</strong>
                                        <p class="radio-description">Связь с пользовательским типом записи</p>
                                        
                                        <div class="cpt-selector" style="display: <?php echo (($settings['source_jobs'] ?? 'repeater') === 'relationship') ? 'block' : 'none'; ?>;">
                                            <label>Выберите CPT:</label>
                                            <select name="source_jobs_cpt" class="cpt-dropdown" id="jobs-cpt-dropdown">
                                                <option value="">⏳ Загрузка...</option>
                                            </select>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="card-footer" id="jobs-footer">
                                <p class="info-text">⏳ Загрузка информации...</p>
                            </div>
                        </div>
                        
                        <!-- Карточка 3: Сертификаты -->
                        <div class="yfgp-data-source-card">
                            <div class="card-header">
                                <div class="header-left">
                                    <h3>📜 Сертификаты (certificates)</h3>
                                </div>
                                <div class="header-right">
                                    <span class="status-badge" id="certificates-status">⏳ Проверка...</span>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <p class="card-description">Выберите источник данных для сертификатов:</p>
                                
                                <label class="radio-option" data-option="repeater">
                                    <input type="radio" 
                                           name="source_certificates" 
                                           value="repeater"
                                           <?php checked(($settings['source_certificates'] ?? 'repeater'), 'repeater'); ?>>
                                    <div class="radio-content">
                                        <strong>Repeater field</strong>
                                        <p class="radio-description">Использовать ACF/JetEngine repeater поле</p>
                                    </div>
                                </label>
                                
                                <label class="radio-option" data-option="relationship">
                                    <input type="radio" 
                                           name="source_certificates" 
                                           value="relationship"
                                           <?php checked(($settings['source_certificates'] ?? 'repeater'), 'relationship'); ?>>
                                    <div class="radio-content">
                                        <strong>Relationship к CPT</strong>
                                        <p class="radio-description">Связь с пользовательским типом записи</p>
                                        
                                        <div class="cpt-selector" style="display: <?php echo (($settings['source_certificates'] ?? 'repeater') === 'relationship') ? 'block' : 'none'; ?>;">
                                            <label>Выберите CPT:</label>
                                            <select name="source_certificates_cpt" class="cpt-dropdown" id="certificates-cpt-dropdown">
                                                <option value="">⏳ Загрузка...</option>
                                            </select>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="card-footer" id="certificates-footer">
                                <p class="info-text">⏳ Загрузка информации...</p>
                            </div>
                        </div>
                        
                        <!-- Карточка 4: Отзывы -->
                        <div class="yfgp-data-source-card">
                            <div class="card-header">
                                <div class="header-left">
                                    <h3>💬 Отзывы (reviews)</h3>
                                </div>
                                <div class="header-right">
                                    <span class="status-badge" id="reviews-status">⏳ Проверка...</span>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <p class="card-description">Выберите источник данных для отзывов о врачах:</p>
                                
                                <label class="radio-option" data-option="repeater">
                                    <input type="radio" 
                                           name="source_reviews" 
                                           value="repeater"
                                           <?php checked(($settings['source_reviews'] ?? 'repeater'), 'repeater'); ?>>
                                    <div class="radio-content">
                                        <strong>Repeater field</strong>
                                        <p class="radio-description">Использовать ACF/JetEngine repeater поле</p>
                                    </div>
                                </label>
                                
                                <label class="radio-option" data-option="relationship">
                                    <input type="radio" 
                                           name="source_reviews" 
                                           value="relationship"
                                           <?php checked(($settings['source_reviews'] ?? 'repeater'), 'relationship'); ?>>
                                    <div class="radio-content">
                                        <strong>Relationship к CPT</strong>
                                        <p class="radio-description">Связь с пользовательским типом записи</p>
                                        
                                        <div class="cpt-selector" style="display: <?php echo (($settings['source_reviews'] ?? 'repeater') === 'relationship') ? 'block' : 'none'; ?>;">
                                            <label>Выберите CPT:</label>
                                            <select name="source_reviews_cpt" class="cpt-dropdown" id="reviews-cpt-dropdown">
                                                <option value="">⏳ Загрузка...</option>
                                            </select>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="card-footer" id="reviews-footer">
                                <p class="info-text">⏳ Загрузка информации...</p>
                            </div>
                        </div>
                        
                        <!-- Карточка 5: Цены -->
                        <div class="yfgp-data-source-card">
                            <div class="card-header">
                                <div class="header-left">
                                    <h3>💰 Цены (prices)</h3>
                                </div>
                                <div class="header-right">
                                    <span class="status-badge" id="prices-status">⏳ Проверка...</span>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <p class="card-description">Выберите источник данных для цен на услуги:</p>
                                
                                <label class="radio-option" data-option="repeater">
                                    <input type="radio" 
                                           name="source_prices" 
                                           value="repeater"
                                           <?php checked(($settings['source_prices'] ?? 'repeater'), 'repeater'); ?>>
                                    <div class="radio-content">
                                        <strong>Repeater field</strong>
                                        <p class="radio-description">Использовать ACF/JetEngine repeater поле</p>
                                    </div>
                                </label>
                                
                                <label class="radio-option" data-option="relationship">
                                    <input type="radio" 
                                           name="source_prices" 
                                           value="relationship"
                                           <?php checked(($settings['source_prices'] ?? 'repeater'), 'relationship'); ?>>
                                    <div class="radio-content">
                                        <strong>Relationship к CPT</strong>
                                        <p class="radio-description">Связь с пользовательским типом записи</p>
                                        
                                        <div class="cpt-selector" style="display: <?php echo (($settings['source_prices'] ?? 'repeater') === 'relationship') ? 'block' : 'none'; ?>;">
                                            <label>Выберите CPT:</label>
                                            <select name="source_prices_cpt" class="cpt-dropdown" id="prices-cpt-dropdown">
                                                <option value="">⏳ Загрузка...</option>
                                            </select>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="card-footer" id="prices-footer">
                                <p class="info-text">⏳ Загрузка информации...</p>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
            
            <tr>
                <th colspan="2"><h2>🔍 Sentry - Мониторинг ошибок</h2></th>
            </tr>
            
            <tr>
                <th scope="row"><label for="sentry_enabled">Включить Sentry</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="sentry_enabled" id="sentry_enabled" value="1" <?php checked($settings['sentry_enabled'] ?? false); ?>>
                        Отправлять ошибки в Sentry для мониторинга
                    </label>
                    <p class="description">
                        Включите для отправки ошибок и исключений в Sentry. Требуется установленный Sentry SDK и настроенный DSN.
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="sentry_dsn">Sentry DSN</label></th>
                <td>
                    <input type="text" 
                           id="sentry_dsn" 
                           name="sentry_dsn" 
                           value="<?php echo esc_attr($settings['sentry_dsn'] ?? ''); ?>" 
                           class="regular-text code"
                           placeholder="https://examplePublicKey@o0.ingest.sentry.io/0">
                    <p class="description">
                        DSN (Data Source Name) для вашего проекта в Sentry.<br>
                        Получить можно в настройках проекта: <strong>Settings → Client Keys (DSN)</strong><br>
                        Формат: <code>https://[PUBLIC_KEY]@[HOST]/[PROJECT_ID]</code>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="sentry_traces_sample_rate">Sample Rate для трейсинга</label></th>
                <td>
                    <input type="number" 
                           id="sentry_traces_sample_rate" 
                           name="sentry_traces_sample_rate" 
                           value="<?php echo esc_attr($settings['sentry_traces_sample_rate'] ?? '0.1'); ?>" 
                           class="small-text"
                           min="0"
                           max="1"
                           step="0.1">
                    <p class="description">
                        Доля запросов для трейсинга производительности (0.0 - 1.0).<br>
                        По умолчанию: <strong>0.1</strong> (10% запросов). Для production рекомендуется 0.1-0.2.
                    </p>
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
                // v2.4.1: Упрощённое определение URL фида
                $upload_dir = wp_upload_dir();
                $feed_url = $upload_dir['baseurl'] . '/feed/doctors.yml';
                if (file_exists($upload_dir['basedir'] . '/feed/doctors.yml')) {
                    echo '<a href="' . esc_url($feed_url) . '" target="_blank">' . esc_html($feed_url) . '</a>';
                } else {
                    echo '<em>Фид ещё не сгенерирован</em>';
                }
                ?>
            </td>
        </tr>
    </table>
</div>

<script>
// Version: <?php echo time(); ?> - Force reload
// v4.18.1: Ensure yfgpAjax is available (fallback if script not loaded yet)
if (typeof yfgpAjax === 'undefined') {
    // v4.18.13: Единый стандарт nonce для всех AJAX handlers
    var yfgpAjax = {
        ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('yfgp_ajax_nonce'); ?>'
    };
}
jQuery(document).ready(function($) {
    console.log('✅ Settings.php loaded at <?php echo date("Y-m-d H:i:s"); ?>');
    
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
    
    // Экспорт конфигурации
    $('#yfgp-export-config').on('click', function() {
        const button = $(this);
        button.prop('disabled', true).text('⏳ Экспорт...');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'yfgp_export_config',
                nonce: '<?php echo wp_create_nonce('yfgp_ajax_nonce'); ?>'
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
                    
                    // v4.18.14: Заменено alert() на showNotice() для WordPress-стандартов
                    if (typeof showNotice === 'function') {
                        showNotice('✅ Конфигурация экспортирована!', 'success');
                    } else {
                        alert('✅ Конфигурация экспортирована!');
                    }
                } else {
                    if (typeof showNotice === 'function') {
                        showNotice('❌ Ошибка: ' + response.data, 'error');
                    } else {
                        alert('❌ Ошибка: ' + response.data);
                    }
                }
            },
            error: function() {
                if (typeof showNotice === 'function') {
                    showNotice('❌ Ошибка сети', 'error');
                } else {
                    alert('❌ Ошибка сети');
                }
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
                    nonce: '<?php echo wp_create_nonce('yfgp_ajax_nonce'); ?>',
                    config_json: configJson
                },
                success: function(response) {
                    if (response.success) {
                        // v4.18.14: Заменено alert() на showNotice() для WordPress-стандартов
                        if (typeof showNotice === 'function') {
                            showNotice('✅ Конфигурация импортирована!', 'success');
                        } else {
                            alert('✅ Конфигурация импортирована!');
                        }
                        location.reload();
                    } else {
                        if (typeof showNotice === 'function') {
                            showNotice('❌ Ошибка: ' + response.data, 'error');
                        } else {
                            alert('❌ Ошибка: ' + response.data);
                        }
                    }
                },
                error: function() {
                    if (typeof showNotice === 'function') {
                        showNotice('❌ Ошибка сети', 'error');
                    } else {
                        alert('❌ Ошибка сети');
                    }
                }
            });
        };
        reader.readAsText(file);
        
        // Сброс input для возможности повторной загрузки того же файла
        $(this).val('');
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
    
    // v4.18.11: КОДОВОЕ СЛОВО ДЛЯ ПРОВЕРКИ: BOM_FIX_ACTIVE
    console.log('[YFGP v4.18.11] КОДОВОЕ СЛОВО: BOM_FIX_ACTIVE - файл settings.php загружен');
    
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
                    nonce: yfgpAjax.nonce
                },
            dataType: 'text', // v4.18.11: Используем 'text' для обработки BOM
            success: function(responseText, textStatus, xhr) {
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
                let response;
                try {
                    response = JSON.parse(rawResponse);
                } catch (e) {
                    console.error('Failed to parse response:', e, rawResponse.substring(0, 100));
                    $termsList.html('<p class="description" style="color: #dc3232;">Ошибка парсинга ответа</p>');
                    return;
                }
                // v4.18.12: Улучшенная обработка ответа с graceful fallback
                if (response.success && response.data) {
                    var terms = response.data.terms || {};
                    var savedTerms = <?php echo json_encode($settings['exclusions_terms'] ?? array()); ?>;
                    
                    // Проверяем, есть ли термины
                    var termsCount = Object.keys(terms).length;
                    
                    if (termsCount > 0) {
                        var html = '<div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">';
                        
                        $.each(terms, function(slug, name) {
                            var checked = savedTerms.indexOf(slug) !== -1 ? 'checked' : '';
                            html += '<label style="display: block; margin-bottom: 5px;">';
                            html += '<input type="checkbox" name="exclusions_terms[]" value="' + slug + '" ' + checked + '> ';
                            html += name;
                            html += '</label>';
                        });
                        
                        html += '</div>';
                        $termsList.html(html);
                    } else {
                        // v4.18.12: Graceful fallback - показываем информативное сообщение вместо ошибки
                        $termsList.html('<p class="description">В этой таксономии нет терминов</p>');
                    }
                } else {
                    // v4.18.12: Обработка случая, когда response.success === false
                    var errorMessage = (response.data && response.data.message) ? response.data.message : 'Ошибка загрузки терминов';
                    $termsList.html('<p class="description" style="color: #dc3232;">' + errorMessage + '</p>');
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
                nonce: '<?php echo wp_create_nonce('yfgp_ajax_nonce'); ?>'
            },
            dataType: 'text', // v4.18.8: Используем 'text' для обработки BOM
            success: function(responseText, textStatus, xhr) {
                $loading.hide();
                
                // v4.18.8: Используем xhr.responseText напрямую для получения сырого ответа
                let rawResponse = xhr.responseText || responseText;
                
                // v4.18.8: Remove UTF-8 BOM (U+FEFF) if present - более надёжная проверка
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
                let response;
                try {
                    response = JSON.parse(rawResponse);
                } catch (e) {
                    console.error('Failed to parse response in loadExclusionFields:', e, rawResponse.substring(0, 100));
                    $exclusionsField.html('<option value="">❌ Ошибка парсинга ответа</option>').prop('disabled', true);
                    return;
                }
                
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
                } else {
                    $exclusionsField.html('<option value="">❌ ' + (response.message || 'Ошибка загрузки') + '</option>').prop('disabled', true);
                }
            },
            error: function(xhr, status, error) {
                $loading.hide();
                console.error('AJAX error in loadExclusionFields:', status, error);
                $exclusionsField.html('<option value="">❌ Ошибка загрузки: ' + error + '</option>').prop('disabled', true);
            }
        });
    }
    
    // Инициализация
    toggleExclusionsFields();
    
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
        // v4.18.0: Load terms on page load if taxonomy already selected
        if ($('#exclusions_taxonomy').val()) {
            loadExclusionTerms();
        }
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
    
    // v2.4.1: Счётчик символов для shop_name
    function updateShopNameCounter() {
        var currentLength = $('#shop_name').val().length;
        var maxLength = 30;
        var $counter = $('#shop_name_counter');
        
        $counter.text(currentLength + '/' + maxLength);
        
        if (currentLength > maxLength) {
            $counter.css('color', '#dc3232').text(currentLength + '/' + maxLength + ' (превышено!)');
        } else if (currentLength > maxLength * 0.8) {
            $counter.css('color', '#ffb900').text(currentLength + '/' + maxLength + ' (почти лимит)');
        } else {
            $counter.css('color', '#00a32a').text(currentLength + '/' + maxLength);
        }
    }
    
    $('#shop_name').on('input', updateShopNameCounter);
    
    // Инициализация при загрузке страницы
    toggleExclusionsFields();
    updateShopNameCounter(); // v2.4.1: Инициализация счётчика
    
    // Проверка при загрузке - если уже выбран CPT для field режима
    // v2.4.1: Восстановление сохранённых значений
    var savedCpt = '<?php echo esc_js($settings['exclusions_cpt'] ?? ''); ?>';
    var savedField = '<?php echo esc_js($settings['exclusions_field'] ?? ''); ?>';
    
    if ($('#specialties_no_primary_source').val() === 'field' && savedCpt) {
        // Устанавливаем сохранённый CPT
        $('#exclusions_cpt').val(savedCpt);
        // Загружаем поля для этого CPT (savedField будет восстановлен внутри loadExclusionFields)
        loadExclusionFields();
    }
    
    // Проверка при загрузке - если уже выбрана таксономия
    if ($('#specialties_no_primary_source').val() === 'taxonomy' && $('#exclusions_taxonomy').val()) {
        loadExclusionTerms();
    }
    
    // ============================================================
    // v3.0.0: Источники данных - UI Functionality
    // ============================================================
    
    // Обработка выбора radio button
    $('.yfgp-data-source-card .radio-option input[type="radio"]').on('change', function() {
        var $card = $(this).closest('.yfgp-data-source-card');
        var $options = $card.find('.radio-option');
        
        // Убрать класс selected со всех
        $options.removeClass('selected');
        
        // Добавить selected к выбранному
        var $selectedOption = $(this).closest('.radio-option');
        $selectedOption.addClass('selected');
        
        // Показать/скрыть CPT dropdown
        var value = $(this).val();
        var $cptSelector = $selectedOption.find('.cpt-selector');
        
        if (value === 'relationship') {
            $cptSelector.show();
        } else {
            $cptSelector.hide();
        }
    });
    
    // Установить selected class при загрузке
    $('.yfgp-data-source-card .radio-option input[type="radio"]:checked').each(function() {
        $(this).closest('.radio-option').addClass('selected');
    });
    
      // v4.18.11: КОДОВОЕ СЛОВО ДЛЯ ПРОВЕРКИ: BOM_FIX_ACTIVE
      console.log('[YFGP v4.18.11] КОДОВОЕ СЛОВО: BOM_FIX_ACTIVE - функция loadAvailableCPTs определена');
      
      // AJAX загрузка доступных CPT
    function loadAvailableCPTs() {
        var fields = ['education', 'jobs', 'certificates', 'reviews', 'prices'];
        
        fields.forEach(function(fieldType) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'yfgp_get_available_cpts',
                    field_type: fieldType,
                    nonce: yfgpAjax.nonce
                },
                dataType: 'text', // v4.18.11: Используем 'text' для обработки BOM
                success: function(responseText, textStatus, xhr) {
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
                    let response;
                    try {
                        response = JSON.parse(rawResponse);
                    } catch (e) {
                        console.error('Failed to parse response for ' + fieldType + ':', e, rawResponse.substring(0, 100));
                        updateCPTStatus(fieldType, {found: false, message: 'Ошибка парсинга ответа'});
                        return;
                    }
                    if (response.success && response.data) {
                        updateCPTDropdown(fieldType, response.data.cpts);
                        updateCPTStatus(fieldType, response.data.status);
                    } else {
                        updateCPTStatus(fieldType, {found: false, message: response.message || 'Ошибка загрузки'});
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error for ' + fieldType + ':', status, error);
                    updateCPTStatus(fieldType, {found: false, message: 'Ошибка загрузки: ' + error});
                }
            });
        });
    }
    
    // Обновить dropdown CPT
    function updateCPTDropdown(fieldType, cpts) {
        var $dropdown = $('#' + fieldType + '-cpt-dropdown');
        $dropdown.empty();
        
        if (!cpts || cpts.length === 0) {
            $dropdown.append('<option value="">⏳ CPT не найдены (проверьте в WordPress)</option>');
            $dropdown.prop('disabled', true);
            $dropdown.closest('.radio-option').addClass('disabled');
        } else {
            var hasSavedSelection = cpts.some(function(cpt) {
                return !!cpt.selected;
            });
            cpts.forEach(function(cpt) {
                var label = cpt.label + (cpt.recommended ? ' (рекомендуется)' : '');
                var count_info = cpt.count ? ' - ' + cpt.count + ' записей' : '';
                var shouldSelect = !!cpt.selected || (!hasSavedSelection && cpt.recommended);
                var option = $('<option>', {
                    value: cpt.slug,
                    text: label + count_info,
                    selected: shouldSelect
                });
                $dropdown.append(option);
            });
            $dropdown.prop('disabled', false);
            $dropdown.closest('.radio-option').removeClass('disabled');
        }
    }
    
    // Обновить статус CPT
    function updateCPTStatus(fieldType, status) {
        var $statusBadge = $('#' + fieldType + '-status');
        var $footer = $('#' + fieldType + '-footer');
        
        if (status.found) {
            $statusBadge.removeClass('warning').addClass('success');
            $statusBadge.html('✅ CPT найден');
            
            var message = '<strong>Найдено CPT:</strong> ' + status.cpt_slug;
            if (status.count) {
                message += ' (' + status.count + ' записей)';
            }
            $footer.html('<p class="info-text">' + message + '</p>');
        } else {
            $statusBadge.removeClass('success').addClass('warning');
            $statusBadge.html('❌ CPT не найдено');
            
            var recommendation = status.message || 'Используйте Repeater field или создайте CPT в WordPress';
            $footer.html('<p class="warning-text">💡 <strong>Рекомендация:</strong> ' + recommendation + '</p>');
        }
    }
    
    // Загрузить CPT при загрузке страницы
    loadAvailableCPTs();
    
});
</script>

<!-- v3.0.0: CSS для источников данных -->
<style>
/* Секция источников данных */
.yfgp-data-sources-section {
    max-width: 900px;
}

/* Карточка */
.yfgp-data-source-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    transition: box-shadow 0.2s;
}

.yfgp-data-source-card:hover {
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

/* Заголовок карточки */
.card-header {
    padding: 16px 20px;
    border-bottom: 1px solid #f0f0f1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #1d2327;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 500;
    white-space: nowrap;
}

.status-badge.success {
    background: #d7f0db;
    color: #2c6e49;
}

.status-badge.warning {
    background: #fef3cd;
    color: #6c5014;
}

/* Тело карточки */
.card-body {
    padding: 20px;
}

.card-description {
    color: #646970;
    font-size: 14px;
    margin-bottom: 16px;
}

/* Радиокнопки */
.radio-option {
    display: block;
    padding: 14px 16px;
    margin-bottom: 12px;
    border: 2px solid #dcdcde;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    background: #fff;
}

.radio-option:hover {
    border-color: #2271b1;
    background: #f6f7f7;
}

.radio-option.selected {
    border-color: #2271b1;
    background: #f0f6fc;
}

.radio-option.disabled {
    opacity: 0.6;
    cursor: not-allowed;
    background: #f6f7f7;
}

.radio-option input[type="radio"] {
    margin-right: 8px;
    margin-top: 2px;
}

.radio-content {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.radio-content strong {
    font-size: 15px;
    color: #1d2327;
}

.radio-description {
    font-size: 13px;
    color: #646970;
    margin: 0;
    line-height: 1.5;
}

/* CPT Selector */
.cpt-selector {
    margin-top: 12px;
    padding-left: 20px;
}

.cpt-selector label {
    display: block;
    font-size: 13px;
    color: #646970;
    margin-bottom: 6px;
    font-weight: 500;
}

.cpt-dropdown {
    width: 100%;
    max-width: 400px;
    height: 36px;
    padding: 4px 8px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    font-size: 14px;
}

.cpt-dropdown:focus {
    border-color: #2271b1;
    outline: 2px solid #2271b1;
    outline-offset: 0;
}

.cpt-dropdown:disabled {
    background: #f6f7f7;
    cursor: not-allowed;
}

/* Футер карточки */
.card-footer {
    padding: 12px 20px;
    background: #f6f7f7;
    border-top: 1px solid #f0f0f1;
    border-radius: 0 0 8px 8px;
}

.info-text {
    margin: 0;
    font-size: 13px;
    color: #2c3338;
}

.warning-text {
    margin: 0;
    font-size: 13px;
    color: #8c5e14;
}

/* Responsive */
@media (max-width: 782px) {
    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .cpt-dropdown {
        max-width: 100%;
    }
}
</style>

