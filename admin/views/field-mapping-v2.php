<?php
/**
 * Field Mapping V2 - С 4 ТАБАМИ И ВСЕМИ ПОЛЯМИ
 * Референсная версия с правильной структурой табов
 * 
 * @package Yandex_Feed_Generator_Pro
 * @version 2.3.1
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_type = $settings['post_type'] ?? 'doctors';
$current_mapping = get_option('yfgp_field_mapping', array());

// Определяем обязательные и необязательные поля YML фида
$required_fields = array(
    'name' => array(
        'label' => 'Полное ФИО врача',
        'description' => 'Формат: Фамилия Имя Отчество',
        'yml_tag' => '<name>',
    ),
    'surname' => array(
        'label' => 'Фамилия',
        'description' => 'Отдельное поле для фамилии (приоритетнее чем разбор ФИО)<br><strong>💡 Автопарсинг:</strong> Если не заполнено, система автоматически извлечет из полного ФИО (поле "name")<br><em>Пример: "Иванов Петр Сергеевич" → Фамилия: "Иванов"</em>',
        'yml_tag' => '<param name="Фамилия">',
    ),
    'firstname' => array(
        'label' => 'Имя',
        'description' => 'Отдельное поле для имени<br><strong>💡 Автопарсинг:</strong> Если не заполнено, система автоматически извлечет из полного ФИО (поле "name")<br><em>Пример: "Иванов Петр Сергеевич" → Имя: "Петр"</em>',
        'yml_tag' => '<param name="Имя">',
    ),
    'patronymic' => array(
        'label' => 'Отчество',
        'description' => 'Отдельное поле для отчества<br><strong>💡 Автопарсинг:</strong> Если не заполнено, система автоматически извлечет из полного ФИО (поле "name")<br><em>Пример: "Иванов Петр Сергеевич" → Отчество: "Сергеевич"</em>',
        'yml_tag' => '<param name="Отчество">',
    ),
    'url' => array(
        'label' => 'URL страницы врача',
        'description' => 'Постоянная ссылка на страницу врача',
        'yml_tag' => '<url>',
    ),
);

$optional_fields = array(
    'picture' => array(
        'label' => 'Фото врача',
        'description' => 'URL изображения (рекомендуется 210×210px)',
        'yml_tag' => '<picture>',
    ),
    'description' => array(
        'label' => 'Описание / Образование',
        'description' => 'Текстовое описание врача',
        'yml_tag' => '<description>',
    ),
    'specialization' => array(
        'label' => 'Специализация',
        'description' => 'v2.3.0: Поддерживает множественные значения',
        'yml_tag' => '<set-ids>',
    ),
    'work_experience' => array(
        'label' => 'Стаж работы',
        'description' => 'Дата начала карьеры или количество лет',
        'yml_tag' => '<param name="Годы опыта">',
    ),
    'career_start_date' => array(
        'label' => 'Дата начала карьеры',
        'description' => 'Формат: YYYY-MM-DD (например: 2015-01-01)',
        'yml_tag' => '<career_start_date>',
    ),
    'degree' => array(
        'label' => 'Научная степень',
        'description' => 'Например: "кандидат медицинских наук"',
        'yml_tag' => '<degree>',
    ),
    'rank' => array(
        'label' => 'Научное звание',
        'description' => 'Например: "профессор", "доцент"',
        'yml_tag' => '<rank>',
    ),
    'category' => array(
        'label' => 'Категория врача',
        'description' => 'Например: "высшая", "первая", "вторая"',
        'yml_tag' => '<category>',
    ),
    'reviews_total_count' => array(
        'label' => 'Количество отзывов',
        'description' => 'Общее количество отзывов о враче<br><strong>💡 Динамический подсчет:</strong> Система автоматически подсчитает количество связанных отзывов (CPT: reviews) через связь "doctor"<br><em>Фильтрация: только опубликованные отзывы (post_status = publish)</em><br><em>Кэширование: результат кэшируется на 1 час для производительности</em>',
        'yml_tag' => '<reviews_total_count>',
    ),
    'education' => array(
        'label' => 'Образование',
        'description' => 'Массив образований врача (0-∞ элементов)<br><strong>💡 Формат:</strong> Используйте Repeater поля (ACF/JetEngine) для множественных образований<br><em>Каждое образование должно содержать: institution, year, specialty</em><br><code>&lt;education&gt;&lt;institution&gt;МГУ&lt;/institution&gt;&lt;/education&gt;</code>',
        'yml_tag' => '<education>',
    ),
    'job' => array(
        'label' => 'Места работы',
        'description' => 'Массив мест работы врача (0-∞ элементов)<br><strong>💡 Формат:</strong> Используйте Repeater поля для множественных мест работы<br><em>Каждое место: organization, position, start_date, end_date</em><br><code>&lt;job&gt;&lt;organization&gt;Поликлиника №1&lt;/organization&gt;&lt;/job&gt;</code>',
        'yml_tag' => '<job>',
    ),
    'certificate' => array(
        'label' => 'Сертификаты',
        'description' => 'Массив сертификатов врача (0-∞ элементов)<br><strong>💡 Формат:</strong> Используйте Repeater поля для множественных сертификатов<br><em>Каждый сертификат: number, issued_date, specialization</em><br><code>&lt;certificate&gt;&lt;number&gt;12345&lt;/number&gt;&lt;/certificate&gt;</code>',
        'yml_tag' => '<certificate>',
    ),
    'city' => array(
        'label' => 'Город',
        'description' => 'Город работы врача',
        'yml_tag' => '<param name="Город">',
    ),
    // v2.3.2: Поля врача (выводятся в <offer><doctor>)
    'telemed' => array(
        'label' => 'Телемедицина',
        'description' => 'Доступна ли телемедицина?<br><strong>💡 Булево значение:</strong> Используйте тип источника "Булево значение (true/false)" для удобного ввода<br><em>Значения: true (да) или false (нет)</em><br><code>&lt;offer&gt;&lt;doctor&gt;&lt;telemed&gt;true&lt;/telemed&gt;&lt;/doctor&gt;&lt;/offer&gt;</code>',
        'yml_tag' => '<offer><doctor><telemed>',
        'type' => 'boolean',
        'default' => 'false',
    ),
    'house_call' => array(
        'label' => 'Вызов на дом',
        'description' => 'Доступен ли вызов на дом?<br><strong>💡 Булево значение:</strong> Используйте тип источника "Булево значение (true/false)" для удобного ввода<br><em>Значения: true (да) или false (нет)</em><br><code>&lt;offer&gt;&lt;doctor&gt;&lt;house_call&gt;false&lt;/house_call&gt;&lt;/doctor&gt;&lt;/offer&gt;</code>',
        'yml_tag' => '<offer><doctor><house_call>',
        'type' => 'boolean',
        'default' => 'false',
    ),
    'adult_appointment' => array(
        'label' => 'Взрослый прием',
        'description' => 'Принимает ли взрослых?<br><strong>💡 Булево значение:</strong> Используйте тип источника "Булево значение (true/false)" для удобного ввода<br><em>Значения: true (да) или false (нет)</em><br><code>&lt;offer&gt;&lt;doctor&gt;&lt;adult_appointment&gt;true&lt;/adult_appointment&gt;&lt;/doctor&gt;&lt;/offer&gt;</code>',
        'yml_tag' => '<offer><doctor><adult_appointment>',
        'type' => 'boolean',
        'default' => 'true',
    ),
    'children_appointment' => array(
        'label' => 'Детский прием',
        'description' => 'Принимает ли детей (до 18 лет)?<br><strong>💡 Булево значение:</strong> Используйте тип источника "Булево значение (true/false)" для удобного ввода<br><em>Значения: true (да, принимает детей) или false (нет, только взрослые)</em><br><code>&lt;offer&gt;&lt;doctor&gt;&lt;children_appointment&gt;true&lt;/children_appointment&gt;&lt;/doctor&gt;&lt;/offer&gt;</code>',
        'yml_tag' => '<offer><doctor><children_appointment>',
        'type' => 'boolean',
        'default' => 'false',
    ),
);

// Поля для оффера (v2.3.2 - урезаны, поля врача перемещены в таб "Врачи")
$offer_fields = array(
    'appointment_url' => array(
        'label' => 'URL для записи',
        'description' => 'Ссылка на страницу записи к врачу',
        'yml_tag' => '<offer><url>',
        'default_available' => true,
    ),
    'online_schedule' => array(
        'label' => 'Онлайн-расписание',
        'description' => 'Доступно ли онлайн-расписание?',
        'yml_tag' => '<offer><online_schedule>',
        'type' => 'boolean',
        'default_available' => true,
    ),
    'appointment_available' => array(
        'label' => 'Запись доступна',
        'description' => 'Можно ли записаться на прием?',
        'yml_tag' => '<offer><appointment>',
        'type' => 'boolean',
        'default_available' => true,
    ),
    'oms_available' => array(
        'label' => 'Прием по ОМС',
        'description' => 'Доступен ли прием по ОМС?',
        'yml_tag' => '<offer><oms>',
        'type' => 'boolean',
        'default_available' => true,
    ),
    // v2.3.2: Поля врача (telemed, house_call, adult/children_appointment) ПЕРЕМЕЩЕНЫ в таб "Врачи"
    'discount_name' => array(
        'label' => 'Название скидки',
        'description' => 'Например: "Клубная карта"',
        'yml_tag' => '<offer><price><discount name="">',
        'default_available' => false,
    ),
    'discount_price' => array(
        'label' => 'Цена со скидкой',
        'description' => 'Стоимость услуги со скидкой',
        'yml_tag' => '<offer><price><discount>',
        'default_available' => false,
    ),
    'free_appointment_condition' => array(
        'label' => 'Условие бесплатного приема',
        'description' => 'Например: "При условии дальнейшего лечения"',
        'yml_tag' => '<offer><price><free_appointment>',
        'default_available' => false,
    ),
);

$relation_fields = array(
    'clinics' => array(
        'label' => 'Клиники (связь)',
        'description' => 'Связь с клиниками - создаст отдельный offer для каждой клиники',
        'yml_tag' => 'множественные <offer>',
        'sub_fields' => array(
            'name' => 'Название клиники',
            'address' => 'Адрес клиники',
            'phone' => 'Телефон клиники',
            'email' => 'Email клиники (v2.4.0)',
            'city' => 'Город клиники',
            'picture' => 'Логотип клиники (v2.3.0)',
            'internal_id' => 'Внутренний ID клиники (v2.3.0)',
            'company_id' => 'ID организации в Яндекс Бизнес (v2.3.0)',
        ),
    ),
    'services' => array(
        'label' => 'Услуги (связь)',
        'description' => 'v2.3.0: Базовая услуга создается автоматически',
        'yml_tag' => 'множественные <offer>',
        'sub_fields' => array(
            'name' => 'Название услуги',
            'price' => 'Цена услуги (необязательно)',
            'gov_id' => 'Код услуги МинЗдрава (v2.3.0)',
            'internal_id' => 'Внутренний ID услуги (v2.3.0)',
            'description' => 'Описание услуги (v2.3.0)',
        ),
    ),
    'review' => array(
        'label' => 'Отзывы (связь)',
        'description' => 'Массив отзывов о враче (0-inf элементов)',
        'yml_tag' => '<review>',
        'sub_fields' => array(
            'author' => 'Автор отзыва',
            'text' => 'Текст отзыва',
            'date' => 'Дата отзыва',
            'rating' => 'Оценка (1-5)',
        ),
    ),
);
?>

<div class="wrap">
    <h1>🗺️ Маппинг полей врачей</h1>
    <p class="description">Настройте соответствие между полями WordPress (ACF/JetEngine) и элементами YML фида</p>
    
    <form id="yfgp-mapping-form" method="post" action="">
        <?php wp_nonce_field('yfgp_mapping_nonce'); ?>
        <input type="hidden" name="post_type" value="<?php echo esc_attr($post_type); ?>">
        
        <!-- ====================== TAB NAVIGATION ====================== -->
        <h2 class="nav-tab-wrapper yfgp-tab-wrapper" style="margin-top: 20px;">
            <a href="#tab-doctors" class="nav-tab nav-tab-active" data-tab="doctors">
                👤 Врачи
                <span class="yfgp-tab-badge" data-tab-id="doctors">0/24</span>
            </a>
            <a href="#tab-offer" class="nav-tab" data-tab="offer">
                📦 Оффер
                <span class="yfgp-tab-badge" data-tab-id="offer">0/7</span>
            </a>
            <a href="#tab-relations" class="nav-tab" data-tab="relations">
                🔗 Связи
                <span class="yfgp-tab-badge" data-tab-id="relations">0/10</span>
            </a>
            <a href="#tab-directory" class="nav-tab" data-tab="directory">
                ⚙️ Справочники
                <span class="yfgp-tab-badge" data-tab-id="directory">0/5</span>
            </a>
        </h2>
        
        <!-- ====================== TAB 1: ВРАЧИ ====================== -->
        <div id="tab-doctors" class="yfgp-tab-content yfgp-tab-active">
            
            <!-- Обязательные поля -->
            <div class="yfgp-mapping-section">
                <h2 style="color: #d63638;">⚠️ Обязательные поля</h2>
                <p class="description">Эти поля должны быть заполнены для корректной работы фида</p>
                
                <div class="yfgp-mapping-grid">
                    <?php foreach ($required_fields as $field_id => $field_info): ?>
                        <div class="yfgp-mapping-card required">
                            <h3>
                                <?php echo esc_html($field_info['label']); ?>
                                <span class="yfgp-field-type-badge required">обязательно</span>
                            </h3>
                            <p class="description">
                                <?php echo esc_html($field_info['description']); ?><br>
                                <code><?php echo esc_html($field_info['yml_tag']); ?></code>
                            </p>
                            <div id="field-<?php echo esc_attr($field_id); ?>" class="yfgp-field-container"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Необязательные поля -->
            <div class="yfgp-mapping-section" style="margin-top: 30px;">
                <h2 style="color: #2271b1;">📋 Необязательные поля</h2>
                <p class="description">Заполните для более полной информации в фиде</p>
                
                <div class="yfgp-mapping-grid">
                    <?php foreach ($optional_fields as $field_id => $field_info): ?>
                        <div class="yfgp-mapping-card">
                            <h3>
                                <?php echo esc_html($field_info['label']); ?>
                                <span class="yfgp-field-type-badge">опционально</span>
                            </h3>
                            <p class="description">
                                <?php echo esc_html($field_info['description']); ?><br>
                                <code><?php echo esc_html($field_info['yml_tag']); ?></code>
                            </p>
                            <div id="field-<?php echo esc_attr($field_id); ?>" class="yfgp-field-container"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
        </div>
        
        <!-- ====================== TAB 2: ОФФЕР ====================== -->
        <div id="tab-offer" class="yfgp-tab-content">
            <div class="yfgp-mapping-section">
                <h2 style="color: #ff6b00;">📦 Дополнительные поля оффера</h2>
                <p class="description">Настройте дополнительные параметры для элемента &lt;offer&gt; в фиде. Для каждого поля можно указать источник данных или использовать значение по умолчанию.</p>
                
                <div class="yfgp-mapping-grid">
                    <?php foreach ($offer_fields as $field_id => $field_info): ?>
                        <div class="yfgp-mapping-card">
                            <h3>
                                <?php echo esc_html($field_info['label']); ?>
                                <span class="yfgp-field-type-badge"><?php echo isset($field_info['type']) && $field_info['type'] === 'boolean' ? 'boolean' : 'текст'; ?></span>
                            </h3>
                            <p class="description">
                                <?php echo esc_html($field_info['description']); ?><br>
                                <code><?php echo esc_html($field_info['yml_tag']); ?></code>
                            </p>
                            
                            <div id="field-<?php echo esc_attr($field_id); ?>" class="yfgp-field-container"></div>
                            
                            <?php if (!empty($field_info['default_available'])): ?>
                                <div class="yfgp-default-value-section" style="margin-top: 10px; padding: 10px; background: #f0f0f1; border-radius: 4px;">
                                    <label style="display: block; font-size: 12px; color: #666; margin-bottom: 5px;">
                                        <input type="checkbox" class="yfgp-use-default" data-field="<?php echo esc_attr($field_id); ?>" <?php checked(!empty($current_mapping['default_' . $field_id])); ?>>
                                        Использовать значение по умолчанию
                                    </label>
                                    <div class="yfgp-default-input" style="<?php echo empty($current_mapping['default_' . $field_id]) ? 'display: none;' : ''; ?>">
                                        <?php if (isset($field_info['type']) && $field_info['type'] === 'boolean'): ?>
                                            <select name="default_<?php echo esc_attr($field_id); ?>" class="yfgp-default-value" style="width: 100%;">
                                                <option value="true" <?php selected($current_mapping['default_' . $field_id] ?? 'true', 'true'); ?>>Да (true)</option>
                                                <option value="false" <?php selected($current_mapping['default_' . $field_id] ?? 'true', 'false'); ?>>Нет (false)</option>
                                            </select>
                                        <?php else: ?>
                                            <input type="text" name="default_<?php echo esc_attr($field_id); ?>" value="<?php echo esc_attr($current_mapping['default_' . $field_id] ?? ''); ?>" class="yfgp-default-value" placeholder="Значение по умолчанию" style="width: 100%; padding: 5px;">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- ====================== TAB 3: СВЯЗИ ====================== -->
        <div id="tab-relations" class="yfgp-tab-content">
            <div class="yfgp-mapping-section">
                <h2 style="color: #00a32a;">🔗 Связанные сущности</h2>
                <p class="description">Настройте связи с клиниками, услугами и другими постами</p>
                
                <?php foreach ($relation_fields as $relation_id => $relation_info): ?>
                    <div class="yfgp-mapping-card" style="border-left: 4px solid #00a32a; margin-bottom: 30px;">
                        <h3>
                            <?php echo esc_html($relation_info['label']); ?>
                            <span class="yfgp-field-type-badge" style="background: #edfaed; color: #00a32a;">связь</span>
                        </h3>
                        <p class="description">
                            <?php echo esc_html($relation_info['description']); ?><br>
                            <code><?php echo esc_html($relation_info['yml_tag']); ?></code>
                        </p>
                        
                        <div id="field-<?php echo esc_attr($relation_id); ?>" class="yfgp-field-container"></div>
                        
                        <?php if (!empty($relation_info['sub_fields'])): ?>
                            <div class="yfgp-sub-fields" style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                                <p style="margin: 0 0 10px; font-weight: 600;">Подполя для связи "<?php echo esc_html($relation_info['label']); ?>":</p>
                                <div class="yfgp-sub-fields-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                                    <?php foreach ($relation_info['sub_fields'] as $sub_field_id => $sub_field_label): ?>
                                        <div class="yfgp-sub-field-item">
                                            <label style="display: block; font-size: 13px; margin-bottom: 3px;">
                                                <strong><?php echo esc_html($sub_field_label); ?></strong>
                                            </label>
                                            <div id="field-<?php echo esc_attr($relation_id . '_' . $sub_field_id); ?>" class="yfgp-field-container"></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- ====================== TAB 4: СПРАВОЧНИКИ ====================== -->
        <div id="tab-directory" class="yfgp-tab-content">
            <!-- Настройки плагина v2.3.0 -->
            <div class="yfgp-mapping-section" style="background: #f0f6fc; padding: 20px; border-radius: 8px;">
                <h2 style="color: #0073aa;">⚙️ Настройки плагина (v2.3.0)</h2>
                <p class="description">Общие настройки для генерации фида</p>
            
            <table class="form-table">
                <tr>
                        <th scope="row">
                            <label for="shop_picture">Логотип площадки</label>
                        </th>
                        <td>
                            <input type="url" id="shop_picture" name="mapping[shop_picture]" 
                                   value="<?php echo esc_attr($current_mapping['shop_picture'] ?? ''); ?>" 
                                   class="regular-text" placeholder="https://example.com/logo.png">
                            <p class="description">
                                URL логотипа площадки (рекомендуется 100×100px)<br>
                                <code>&lt;shop&gt;&lt;picture&gt;</code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="base_service_default_name">Базовая услуга по умолчанию</label>
                        </th>
                        <td>
                            <input type="text" id="base_service_default_name" name="mapping[base_service_default_name]" 
                                   value="<?php echo esc_attr($current_mapping['base_service_default_name'] ?? 'Первичный приём'); ?>" 
                                   class="regular-text" placeholder="Первичный приём">
                            <p class="description">
                                Название базовой услуги, если не указано в маппинге специальностей<br>
                                <strong>v2.3.0:</strong> Используется для автоматического создания базовой услуги
                            </p>
                    </td>
                </tr>
                <tr>
                        <th scope="row">
                            <label for="base_service_fallback_price">Цена базовой услуги (fallback)</label>
                        </th>
                        <td>
                            <input type="number" id="base_service_fallback_price" name="mapping[base_service_fallback_price]" 
                                   value="<?php echo esc_attr($current_mapping['base_service_fallback_price'] ?? ''); ?>" 
                                   class="regular-text" placeholder="Оставьте пустым" min="0">
                            <p class="description">
                                Цена для автосозданной базовой услуги (опционально)<br>
                                <strong>Важно:</strong> Оставьте пустым, если базовая услуга может быть без цены
                            </p>
                    </td>
                </tr>
            </table>
            </div>
        </div>
        
        <!-- Кнопка сохранения -->
        <div class="yfgp-mapping-actions" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccc; border-radius: 4px;">
            <div style="display: flex; gap: 15px; align-items: center;">
                <?php submit_button('💾 Сохранить маппинг', 'primary large', 'yfgp_save_mapping', false); ?>
                <button type="button" id="yfgp-test-mapping-btn" class="button button-secondary button-large">
                    🧪 Тест текущей вкладки
                </button>
                <span id="yfgp-test-result" style="margin-left: 10px; display: none;"></span>
            </div>
            <p class="description" style="margin: 10px 0 0;">
                <strong>Сохранить:</strong> Сохранить все настройки маппинга<br>
                <strong>Тест:</strong> Проверить маппинг для текущей вкладки (покажет пример данных для одного поста)
            </p>
        </div>
    </form>
</div>

<style>
/* Стили для табов */
.yfgp-tab-wrapper { border-bottom: 1px solid #c3c4c7; }
.yfgp-tab-content { display: none; padding: 20px 0; }
.yfgp-tab-content.yfgp-tab-active { display: block; }
.yfgp-tab-badge { 
    display: inline-block; 
    background: #f0f0f1; 
    padding: 2px 8px; 
    border-radius: 10px; 
    font-size: 11px; 
    margin-left: 5px;
}
.nav-tab-active .yfgp-tab-badge { background: #fff; color: #2271b1; }

/* Грид для полей */
.yfgp-mapping-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}
.yfgp-mapping-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 15px;
}
.yfgp-mapping-card.required { border-left: 4px solid #d63638; }
.yfgp-field-type-badge {
    display: inline-block;
    background: #f0f0f1;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 400;
    margin-left: 5px;
}
.yfgp-field-type-badge.required { background: #d63638; color: #fff; }
.yfgp-field-container {
    margin-top: 10px;
    min-height: 40px;
    border: 1px dashed #c3c4c7;
    border-radius: 4px;
    padding: 8px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Переключение табов
    $('.yfgp-tab-wrapper .nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Убираем активный класс
        $('.nav-tab').removeClass('nav-tab-active');
        $('.yfgp-tab-content').removeClass('yfgp-tab-active');
        
        // Добавляем активный класс
        $(this).addClass('nav-tab-active');
        var tabId = $(this).attr('href');
        $(tabId).addClass('yfgp-tab-active');
    });
    
    // Обработка чекбоксов "Использовать значение по умолчанию"
    $('.yfgp-use-default').on('change', function() {
        var $defaultInput = $(this).closest('.yfgp-default-value-section').find('.yfgp-default-input');
        if ($(this).is(':checked')) {
            $defaultInput.slideDown();
        } else {
            $defaultInput.slideUp();
        }
    });
    
    // ========== ИНИЦИАЛИЗАЦИЯ DYNAMIC FIELD SELECTOR ==========
    if (typeof window.DynamicFieldSelector !== 'undefined') {
        console.log('🔄 Инициализация Dynamic Field Selector...');
        
        var currentMapping = <?php echo json_encode($current_mapping); ?>;
        var postType = '<?php echo esc_js($post_type); ?>';
        
        // Находим все контейнеры для полей
        $('.yfgp-field-container').each(function() {
            var $container = $(this);
            var fieldId = $container.attr('id').replace('field-', '');
            
            console.log('📋 Инициализирую поле:', fieldId);
            
            // Создаем экземпляр Dynamic Field Selector
            new window.DynamicFieldSelector($container, {
                postType: postType,
                fieldName: fieldId,
                currentValue: currentMapping[fieldId] || null,
                onChange: function(value) {
                    console.log('Поле изменено:', fieldId, value);
                }
            });
        });
        
        console.log('✅ Dynamic Field Selector инициализирован для всех полей!');
    } else {
        console.error('❌ Dynamic Field Selector НЕ ЗАГРУЖЕН!');
    }
    
    console.log('✅ YFGP Mapping: 4 ТАБА ИНИЦИАЛИЗИРОВАНЫ!');
    
    // ========== КНОПКА "ТЕСТ" ДЛЯ ТЕКУЩЕЙ ВКЛАДКИ ==========
    $('#yfgp-test-mapping-btn').on('click', function() {
        var $btn = $(this);
        var $result = $('#yfgp-test-result');
        
        // Определить текущую активную вкладку
        var activeTab = $('.nav-tab-active').data('tab');
        var tabNames = {
            'doctors': 'Врачи',
            'offer': 'Оффер',
            'relations': 'Связи',
            'directory': 'Справочники'
        };
        
        // Показать индикатор загрузки
        $btn.prop('disabled', true).html('⏳ Тестирование...');
        $result.show().html('<span style="color: #0073aa;">⏳ Выполняется тест...</span>');
        
        // Получить все поля текущей вкладки
        var tabFields = {};
        $('#tab-' + activeTab + ' .yfgp-field-container-v3').each(function() {
            var fieldId = $(this).data('field-id');
            var fieldName = $(this).find('.yfgp-source-field').val();
            if (fieldName) {
                tabFields[fieldId] = fieldName;
            }
        });
        
        // AJAX запрос для тестирования
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'yfgp_test_mapping',
                nonce: '<?php echo wp_create_nonce('yfgp_nonce'); ?>',
                post_type: '<?php echo esc_js($post_type); ?>',
                tab: activeTab,
                fields: tabFields
            },
            success: function(response) {
                $btn.prop('disabled', false).html('🧪 Тест текущей вкладки');
                
                if (response.success) {
                    $result.html('<span style="color: #46b450;">✅ Тест пройден! Настроено полей: ' + (response.data.count || 0) + '</span>');
                    
                    // Опционально: показать preview данных
                    if (response.data.preview) {
                        console.log('📊 Тест данные для вкладки "' + tabNames[activeTab] + '":', response.data.preview);
                    }
                } else {
                    $result.html('<span style="color: #dc3232;">❌ Ошибка: ' + (response.data || 'Неизвестная ошибка') + '</span>');
                }
                
                // Скрыть результат через 5 секунд
                setTimeout(function() {
                    $result.fadeOut();
                }, 5000);
            },
            error: function(xhr, status, error) {
                $btn.prop('disabled', false).html('🧪 Тест текущей вкладки');
                $result.html('<span style="color: #dc3232;">❌ AJAX ошибка: ' + error + '</span>');
                
                setTimeout(function() {
                    $result.fadeOut();
                }, 5000);
            }
        });
    });
});
</script>
