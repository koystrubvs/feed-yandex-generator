<?php
/**
 * Field Mapping V3.1 - 4 Мега-таба с механизмом наследования
 * 
 * Новые возможности:
 * - 4 мега-таба: Врачи, Клиники, Услуги, Офферы
 * - Плоский список всех полей врачей (26 полей)
 * - Раздел Отзывы (repeater/relationship)
 * - Раздел Цены для услуг (repeater/relationship)
 * - Механизм наследования для офферов
 * 
 * @package Yandex_Feed_Generator_Pro
 * @version 3.1.0
 * @since 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_type = $settings['post_type'] ?? 'doctors';
$current_mapping = get_option('yfgp_field_mapping_v3', array());
$data_sources = get_option('yfgp_settings', array());

// v4.18.22: Режим "одна клиника" - проверка
$is_single_clinic_mode = empty($settings['cpt_clinics']);

$mapping_context = isset($mapping_context) && is_array($mapping_context) ? $mapping_context : array();
$mapping_context_labels = isset($mapping_context_labels) && is_array($mapping_context_labels) ? $mapping_context_labels : array();

$tab_order = array('doctors', 'clinics', 'services', 'offers');
$tab_base_titles = array(
    'doctors' => 'Врачи',
    'clinics' => 'Клиники',
    'services' => 'Услуги',
    'offers' => 'Офферы',
);
$tab_badges = array(
    'doctors' => '0/20',
    'clinics' => '0/11',
    'services' => '0/7',
    'offers' => '0/14',
);
$tab_context = array(
    'doctors' => array(
        'slug' => $mapping_context['post_type'] ?? $post_type,
        'label' => $mapping_context_labels['post_type'] ?? '',
    ),
    'clinics' => array(
        'slug' => $mapping_context['cpt_clinics'] ?? '',
        'label' => $mapping_context_labels['cpt_clinics'] ?? '',
    ),
    'services' => array(
        'slug' => $mapping_context['cpt_services'] ?? '',
        'label' => $mapping_context_labels['cpt_services'] ?? '',
    ),
    'offers' => array(
        'slug' => $mapping_context['post_type'] ?? $post_type,
        'label' => $mapping_context_labels['post_type'] ?? '',
    ),
);
$mapping_context = isset($mapping_context) && is_array($mapping_context) ? $mapping_context : array();
$mapping_context_labels = isset($mapping_context_labels) && is_array($mapping_context_labels) ? $mapping_context_labels : array();
$tab_order = array('doctors', 'clinics', 'services', 'offers');
$tab_base_titles = array(
    'doctors' => 'Врачи',
    'clinics' => 'Клиники',
    'services' => 'Услуги',
    'offers' => 'Офферы',
);
$tab_badges = array(
    'doctors' => '0/20',
    'clinics' => '0/11',
    'services' => '0/7',
    'offers' => '0/14',
);
$tab_context = array(
    'doctors' => array(
        'slug' => $mapping_context['post_type'] ?? $post_type,
        'label' => $mapping_context_labels['post_type'] ?? '',
    ),
    'clinics' => array(
        'slug' => $mapping_context['cpt_clinics'] ?? '',
        'label' => $mapping_context_labels['cpt_clinics'] ?? '',
    ),
    'services' => array(
        'slug' => $mapping_context['cpt_services'] ?? '',
        'label' => $mapping_context_labels['cpt_services'] ?? '',
    ),
    'offers' => array(
        'slug' => $mapping_context['post_type'] ?? $post_type,
        'label' => $mapping_context_labels['post_type'] ?? '',
    ),
);

// ========================================================================================
// ВРАЧИ (DOCTORS CPT)
// ========================================================================================

// Обязательные поля врачей (3 поля)
$doctors_required_fields = array(
    'id' => array(
        'label' => 'ID врача',
        'description' => 'Уникальный идентификатор врача',
        'yml_tag' => '<id>',
        'default_source' => 'meta_field',
        'required' => true,
    ),
    'name' => array(
        'label' => 'Полное ФИО врача',
        'description' => 'Формат: Фамилия Имя Отчество',
        'yml_tag' => '<name>',
        'default_source' => 'meta_field',
        'required' => true,
    ),
    'specialities' => array(
        'label' => 'Специальности',
        'description' => 'Массив специальностей врача (таксономия или произвольное поле)',
        'yml_tag' => '<specialities>',
        'default_source' => 'taxonomy',
        'required' => true,
    ),
);

// Необязательные поля врачей (15 полей) - v4.1.0: +2 critical relationships (services, clinics)
$doctors_optional_fields = array(
    'url' => array(
        'label' => 'URL страницы врача',
        'description' => 'Постоянная ссылка на страницу врача',
        'yml_tag' => '<url>',
        'default_source' => 'meta_field',
    ),
    'description' => array(
        'label' => 'Описание',
        'description' => 'Текстовое описание врача',
        'yml_tag' => '<description>',
        'default_source' => 'meta_field',
    ),
    'internal_id' => array(
        'label' => 'Внутренний ID',
        'description' => 'Внутренний идентификатор врача',
        'yml_tag' => '<internal_id>',
        'default_source' => 'meta_field',
    ),
    'first_name' => array(
        'label' => 'Имя',
        'description' => 'Отдельное поле для имени. Если не заполнено, автоматически извлекается из поля "Полное ФИО" (name). Пример: "Иванов Иван Иванович" → Имя="Иван"',
        'yml_tag' => '<first_name>',
        'default_source' => 'meta_field',
    ),
    'surname' => array(
        'label' => 'Фамилия',
        'description' => 'Отдельное поле для фамилии. Если не заполнено, автоматически извлекается из поля "Полное ФИО" (name). Пример: "Иванов Иван Иванович" → Фамилия="Иванов"',
        'yml_tag' => '<surname>',
        'default_source' => 'meta_field',
    ),
    'patronymic' => array(
        'label' => 'Отчество',
        'description' => 'Отдельное поле для отчества. Если не заполнено, автоматически извлекается из поля "Полное ФИО" (name). Пример: "Иванов Иван Иванович" → Отчество="Иванович"',
        'yml_tag' => '<patronymic>',
        'default_source' => 'meta_field',
    ),
    'experience_years' => array(
        'label' => 'Стаж (годы)',
        'description' => 'Количество лет опыта',
        'yml_tag' => '<experience_years>',
        'default_source' => 'meta_field',
    ),
    'picture' => array(
        'label' => 'Фото врача',
        'description' => 'URL изображения (рекомендуется 210×210px)',
        'yml_tag' => '<picture>',
        'default_source' => 'meta_field',
    ),
    'career_start_date' => array(
        'label' => 'Дата начала карьеры',
        'description' => 'Формат: YYYY-MM-DD',
        'yml_tag' => '<career_start_date>',
        'default_source' => 'meta_field',
    ),
    'degree' => array(
        'label' => 'Научная степень',
        'description' => 'Например: кандидат медицинских наук',
        'yml_tag' => '<degree>',
        'default_source' => 'meta_field',
    ),
    'rank' => array(
        'label' => 'Научное звание',
        'description' => 'Например: профессор, доцент',
        'yml_tag' => '<rank>',
        'default_source' => 'meta_field',
    ),
    'category' => array(
        'label' => 'Категория врача',
        'description' => 'Например: высшая, первая, вторая',
        'yml_tag' => '<category>',
        'default_source' => 'meta_field',
    ),
    'oms' => array(
        'label' => 'Онлайн-расписание (ОМС)',
        'description' => 'Доступность онлайн-расписания',
        'yml_tag' => '<oms>',
        'default_source' => 'meta_field',
    ),
    // v3.1.0: Новые поля для врачей
    'children_appointment' => array(
        'label' => 'Прием детей',
        'description' => 'Принимает ли врач детей (до 18 лет)',
        'yml_tag' => '<children_appointment>',
        'default_source' => 'meta_field',
    ),
    'reviews_total_count' => array(
        'label' => 'Количество отзывов',
        'description' => 'Общее количество отзывов о враче (вычисляется динамически)',
        'yml_tag' => '<reviews_total_count>',
        'default_source' => 'dynamic',
    ),
    'adult_appointment' => array(
        'label' => 'Прием взрослых',
        'description' => 'Принимает ли врач взрослых',
        'yml_tag' => '<adult_appointment>',
        'default_source' => 'meta_field',
    ),
    'house_call' => array(
        'label' => 'Вызов на дом',
        'description' => 'Доступен ли вызов врача на дом',
        'yml_tag' => '<house_call>',
        'default_source' => 'meta_field',
    ),
    'telemed' => array(
        'label' => 'Телемедицина',
        'description' => 'Доступна ли телемедицина',
        'yml_tag' => '<telemed>',
        'default_source' => 'meta_field',
    ),
    'appointment' => array(
        'label' => 'Ведет прием',
        'description' => 'Можно ли записаться на прием (связано с ценой)',
        'yml_tag' => '<appointment>',
        'default_source' => 'dynamic',
    ),
    // v3.5.4: Базовая услуга (явное указание) - для консистентности UI с табом "Оффер"
    'base_service_id' => array(
        'label' => 'Базовая услуга',
        'description' => 'Явное указание какая услуга является базовой для этого врача. Выберите ACF/JetEngine поле типа Post Object или Relationship (single), которое указывает на одну из услуг врача. Это поле будет использоваться в офферах с наивысшим приоритетом.',
        'yml_tag' => '<base_service_id>',
        'default_source' => 'relationship',
    ),
    // v4.1.0: КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ - Relationships для генерации фида
    'services' => array(
        'label' => 'Услуги врача',
        'description' => '⚠️ ОБЯЗАТЕЛЬНОЕ ПОЛЕ! Выберите ACF/JetEngine поле типа Relationship, которое связывает врача со всеми его услугами. Без этого поля блок <services> в YML будет пустым!',
        'yml_tag' => '<services> (блок)',
        'default_source' => 'relationship',
    ),
    'clinics' => array(
        'label' => 'Клиники врача',
        'description' => '⚠️ ОБЯЗАТЕЛЬНОЕ ПОЛЕ! Выберите ACF/JetEngine поле типа Relationship, которое связывает врача с клиниками где он работает. Без этого поля будет использована дефолтная клиника.',
        'yml_tag' => '<clinics> (блок)',
        'default_source' => 'relationship',
    ),
);

// Repeater блоки врачей (4 блока: education, jobs, certificates, reviews)
$doctors_repeater_fields = array(
    'education' => array(
        'label' => 'Образование',
        'description' => 'Массив образований врача',
        'yml_tag' => '<education>',
        'source_setting' => 'source_education',
        'cpt_setting' => 'source_education_cpt',
        'sub_fields' => array(
            'organization' => 'Учебное заведение',
            'finish_year' => 'Год окончания',
            'type' => 'Тип образования',
            'specialization' => 'Специализация',
        ),
    ),
    'job' => array(
        'label' => 'Места работы',
        'description' => 'Массив мест работы врача',
        'yml_tag' => '<job>',
        'source_setting' => 'source_jobs',
        'cpt_setting' => 'source_jobs_cpt',
        'sub_fields' => array(
            'organization' => 'Организация',
            'period_years' => 'Период работы',
            'position' => 'Должность',
        ),
    ),
    'certificate' => array(
        'label' => 'Сертификаты',
        'description' => 'Массив сертификатов врача',
        'yml_tag' => '<certificate>',
        'source_setting' => 'source_certificates',
        'cpt_setting' => 'source_certificates_cpt',
        'sub_fields' => array(
            'organization' => 'Организация',
            'finish_year' => 'Год выдачи',
            'name' => 'Название сертификата',
        ),
    ),
    'reviews' => array(
        'label' => 'Отзывы',
        'description' => 'Массив отзывов о враче',
        'yml_tag' => '<reviews>',
        'source_setting' => 'source_reviews',
        'cpt_setting' => 'source_reviews_cpt',
        'sub_fields' => array(
            'date' => 'Дата отзыва',
            'checked' => 'Проверен',
            'used_in_rating' => 'Участвует в рейтинге',
            'author' => 'Автор',
            'author_id' => 'ID автора',
            'author_picture' => 'Фото автора',
            'url' => 'Ссылка на отзыв',
            'comment' => 'Комментарий',
            'grade' => 'Оценка (1-5)',
            'positive' => 'Понравилось',
            'negative' => 'Не понравилось',
            'response' => 'Ответ'
        ),
    ),
);

// Подсчет полей врачей: 3 обязательных + 20 необязательных + 4 repeater блока = 27 карточек

// ========================================================================================
// КЛИНИКИ (CLINICS CPT)
// ========================================================================================

// Обязательные поля клиник (4 поля)
$clinics_required_fields = array(
    'id' => array(
        'label' => 'ID клиники',
        'description' => 'Уникальный идентификатор клиники',
        'yml_tag' => '<id>',
        'default_source' => 'meta_field',
        'required' => true,
    ),
    'name' => array(
        'label' => 'Название клиники',
        'description' => 'Полное название медицинской организации',
        'yml_tag' => '<name>',
        'default_source' => 'meta_field',
        'required' => true,
    ),
    'address' => array(
        'label' => 'Адрес',
        'description' => 'Полный адрес клиники',
        'yml_tag' => '<address>',
        'default_source' => 'meta_field',
        'required' => true,
    ),
    'city' => array(
        'label' => 'Город',
        'description' => 'Город расположения клиники',
        'yml_tag' => '<city>',
        'default_source' => 'meta_field',
        'required' => true,
    ),
);

// Необязательные поля клиник (6 полей)
$clinics_optional_fields = array(
    'url' => array(
        'label' => 'URL страницы клиники',
        'description' => 'Ссылка на страницу клиники на сайте',
        'yml_tag' => '<url>',
        'default_source' => 'meta_field',
    ),
    'picture' => array(
        'label' => 'Логотип клиники',
        'description' => 'URL изображения логотипа',
        'yml_tag' => '<picture>',
        'default_source' => 'meta_field',
    ),
    'email' => array(
        'label' => 'Email',
        'description' => 'Контактный email клиники',
        'yml_tag' => '<email>',
        'default_source' => 'meta_field',
    ),
    'phone' => array(
        'label' => 'Телефон',
        'description' => 'Контактный телефон клиники',
        'yml_tag' => '<phone>',
        'default_source' => 'meta_field',
    ),
    'internal_id' => array(
        'label' => 'Внутренний ID',
        'description' => 'Внутренний идентификатор клиники',
        'yml_tag' => '<internal_id>',
        'default_source' => 'meta_field',
    ),
    'company_id' => array(
        'label' => 'ID в Яндекс Картах',
        'description' => 'Идентификатор организации в Яндекс Картах',
        'yml_tag' => '<company_id>',
        'default_source' => 'meta_field',
    ),
);

// Связи клиник (1 поле)
$clinics_relationships = array(
    'doctors_relationship' => array(
        'label' => 'Связь с врачами',
        'description' => 'Связь клиники с врачами (relationship field)',
        'yml_tag' => '<doctors>',
        'default_source' => 'relationship_1',
    ),
);

// Подсчет полей клиник: 4 обязательных + 6 необязательных + 1 связь = 11 карточек

// ========================================================================================
// УСЛУГИ (SERVICES CPT)
// ========================================================================================

// Обязательные поля услуг (2 поля)
$services_required_fields = array(
    'id' => array(
        'label' => 'ID услуги',
        'description' => 'Уникальный идентификатор услуги',
        'yml_tag' => '<id>',
        'default_source' => 'meta_field',
        'required' => true,
    ),
    'name' => array(
        'label' => 'Название услуги',
        'description' => 'Полное название медицинской услуги',
        'yml_tag' => '<name>',
        'default_source' => 'meta_field',
        'required' => true,
    ),
);

// Необязательные поля услуг (3 поля)
$services_optional_fields = array(
    'description' => array(
        'label' => 'Описание',
        'description' => 'Текстовое описание услуги',
        'yml_tag' => '<description>',
        'default_source' => 'meta_field',
    ),
    'gov_id' => array(
        'label' => 'Код Минздрава',
        'description' => 'Официальный код услуги по классификации Минздрава',
        'yml_tag' => '<gov_id>',
        'default_source' => 'meta_field',
    ),
    'internal_id' => array(
        'label' => 'Внутренний ID',
        'description' => 'Внутренний идентификатор услуги',
        'yml_tag' => '<internal_id>',
        'default_source' => 'meta_field',
    ),
);

// Связи услуг (1 поле)
$services_relationships = array(
    'doctors_relationship' => array(
        'label' => 'Связь с врачами',
        'description' => 'Связь услуги с врачами (relationship field)',
        'yml_tag' => '<doctors>',
        'default_source' => 'relationship_1',
    ),
);

// Цены услуг (repeater/relationship блок)
$prices_field = array(
    'label' => 'Цены',
    'description' => 'Массив цен на услугу',
    'yml_tag' => '<prices>',
    'source_setting' => 'source_prices',
    'cpt_setting' => 'source_prices_cpt',
    'sub_fields' => array(
        'base_price' => 'Базовая цена',
        'currency' => 'Валюта (RUB/RUR)',
        'discount' => 'Скидка',
        'discount_name' => 'Название скидки',
        'free_appointment' => 'Условие бесплатного приема'
    ),
);

// Подсчет полей услуг: 2 обязательных + 3 необязательных + 1 связь + 1 блок цен = 7 карточек

// ========================================================================================
// ОФФЕРЫ (OFFERS - виртуальная сущность, генерируется динамически!)
// ========================================================================================

// ПАРАМЕТРЫ ОФФЕРА (4 поля с наследованием)
$offers_params = array(
    'url' => array(
        'label' => 'URL страницы записи',
        'description' => 'Ссылка для записи к врачу',
        'inherit_from' => 'doctor',
        'default_mode' => 'inherit',
    ),
    'online_schedule' => array(
        'label' => 'Онлайн-расписание',
        'description' => 'Доступно ли онлайн-расписание',
        'inherit_from' => 'doctor',
        'default_mode' => 'inherit',
    ),
    'oms' => array(
        'label' => 'Прием по ОМС',
        'description' => 'Доступен ли прием по ОМС',
        'inherit_from' => 'doctor',
        'default_mode' => 'inherit',
    ),
    'appointment' => array(
        'label' => 'Ведет прием',
        'description' => 'Можно ли записаться на прием',
        'inherit_from' => 'doctor',
        'default_mode' => 'inherit',
    ),
);

// ПАРАМЕТРЫ ВРАЧА В ОФФЕРЕ (5 полей с наследованием)
$offers_doctor_params = array(
    'children_appointment' => array(
        'label' => 'Прием детей',
        'description' => 'Принимает ли врач детей',
        'inherit_from' => 'doctor',
        'default_mode' => 'inherit',
    ),
    'adult_appointment' => array(
        'label' => 'Прием взрослых',
        'description' => 'Принимает ли врач взрослых',
        'inherit_from' => 'doctor',
        'default_mode' => 'inherit',
    ),
    'house_call' => array(
        'label' => 'Вызов на дом',
        'description' => 'Доступен ли вызов врача на дом',
        'inherit_from' => 'doctor',
        'default_mode' => 'inherit',
    ),
    'telemed' => array(
        'label' => 'Телемедицина',
        'description' => 'Доступна ли телемедицина',
        'inherit_from' => 'doctor',
        'default_mode' => 'inherit',
    ),
    'base_service_id' => array(
        'label' => 'Базовая услуга (явное указание)',
        'description' => 'Явное указание какая услуга является базовой ДЛЯ ЭТОГО ВРАЧА. Выберите ACF/JetEngine поле типа Post Object или Relationship (single), которое указывает на одну из услуг врача. Это поле должно быть У ВРАЧА, а не у услуги!',
        'inherit_from' => 'doctor',
        'default_mode' => 'custom',
    ),
);

// ЦЕНА В ОФФЕРЕ (5 полей с наследованием)
$offers_price_params = array(
    'base_price' => array(
        'label' => 'Базовая цена',
        'description' => 'Цена без скидки',
        'inherit_from' => 'service',
        'default_mode' => 'inherit',
    ),
    'currency' => array(
        'label' => 'Валюта',
        'description' => 'RUB или RUR (из настроек плагина)',
        'inherit_from' => 'settings',
        'default_mode' => 'inherit',
    ),
    'discount' => array(
        'label' => 'Скидка',
        'description' => 'Размер скидки',
        'inherit_from' => 'service',
        'default_mode' => 'inherit',
    ),
    'discount_name' => array(
        'label' => 'Название скидки',
        'description' => 'Например: Клубная карта',
        'inherit_from' => 'service',
        'default_mode' => 'inherit',
    ),
    'free_appointment' => array(
        'label' => 'Условие бесплатного приема',
        'description' => 'При каких условиях прием бесплатный',
        'inherit_from' => 'service',
        'default_mode' => 'inherit',
    ),
);

// Подсчет полей офферов: 4 параметра оффера + 5 параметров врача + 5 цен = 14 полей
// Загружаем конфигурацию офферов из БД
$offer_config = get_option('yfgp_offer_config', array());
?>

<div class="wrap">
    <h1>🗺️ Маппинг полей YML фида (v<?php echo YFGP_VERSION; ?>)</h1>
    <p class="description">
        Настройте соответствие между полями WordPress и элементами YML фида для всех сущностей. 
        <strong>Версия <?php echo YFGP_VERSION; ?>:</strong> 4 мега-таба (Врачи, Клиники, Услуги, Офферы) + механизм наследования + улучшения UI.
    </p>
    
    <!-- v3.4.5: Форма отправляется на admin-post.php для корректного redirect (Task #13) -->
    <form id="yfgp-mapping-form-v3" name="yfgp_mapping_form_v3" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('yfgp_mapping_v3_nonce'); ?>
        <input type="hidden" name="post_type" value="<?php echo esc_attr($post_type); ?>">
        <input type="hidden" name="yfgp_save_mapping_v3" value="1">
        
        <!-- v3.4.5: action для admin_post_{action} hook -->
        <input type="hidden" name="action" value="yfgp_save_mapping" />
        
        <!-- v3.4.1: Hidden input для JSON данных маппинга (Task #13) -->
        <input type="hidden" id="yfgp_mapping_json" name="yfgp_mapping_json" value="" />
        
        <!-- ====================== MEGA TAB NAVIGATION ====================== -->
        <h2 class="nav-tab-wrapper yfgp-mega-tab-wrapper" style="margin-top: 20px;">
            <a href="#tab-doctors" class="nav-tab nav-tab-active" data-tab="doctors">
                👨‍⚕️ Врачи<?php
                    $label = $tab_context['doctors']['label'] ?? '';
                    $display = $label !== '' ? $label : '—';
                    echo ' • ' . esc_html($display);
                ?>
                <span class="yfgp-tab-badge" data-tab-id="doctors">0/20</span>
            </a>
            <a href="#tab-clinics" class="nav-tab" data-tab="clinics">
                🏥 Клиники<?php
                    $label = $tab_context['clinics']['label'] ?? '';
                    $display = $label !== '' ? $label : '—';
                    echo ' • ' . esc_html($display);
                ?>
                <span class="yfgp-tab-badge" data-tab-id="clinics">0/11</span>
            </a>
            <a href="#tab-services" class="nav-tab" data-tab="services">
                💊 Услуги<?php
                    $label = $tab_context['services']['label'] ?? '';
                    $display = $label !== '' ? $label : '—';
                    echo ' • ' . esc_html($display);
                ?>
                <span class="yfgp-tab-badge" data-tab-id="services">0/7</span>
            </a>
            <a href="#tab-offers" class="nav-tab" data-tab="offers">
                🎯 Офферы<?php
                    $label = $tab_context['offers']['label'] ?? '';
                    $display = $label !== '' ? $label : '—';
                    echo ' • ' . esc_html($display);
                ?>
                <span class="yfgp-tab-badge" data-tab-id="offers">0/14</span>
            </a>
        </h2>
        
        <!-- ====================== TAB 1: ВРАЧИ ====================== -->
        <div id="tab-doctors" class="yfgp-tab-content yfgp-tab-active">
            
            <!-- Обязательные поля -->
            <div class="yfgp-mapping-section">
                <h2 style="color: #d63638;">⚠️ Обязательные поля врача</h2>
                <p class="description">Эти поля должны быть заполнены для корректной работы фида</p>
                
                <div class="yfgp-tab-context">
                    <span>Источник CPT: <strong><?php echo esc_html(($tab_context['doctors']['label'] ?? '') !== '' ? $tab_context['doctors']['label'] : '—'); ?></strong></span>
                    <?php if (!empty($tab_context['doctors']['slug'])): ?>
                        <code><?php echo esc_html($tab_context['doctors']['slug']); ?></code>
                    <?php endif; ?>
                </div>
                
                <div class="yfgp-mapping-grid">
                    <?php foreach ($doctors_required_fields as $field_id => $field_info): ?>
                        <div class="yfgp-mapping-card required" data-field-id="<?php echo esc_attr($field_id); ?>">
                            <div class="card-header">
                                <h3>
                                    <?php echo esc_html($field_info['label']); ?>
                                    <span class="yfgp-field-type-badge required">обязательно</span>
                                </h3>
                                <div class="card-status" data-field="<?php echo esc_attr($field_id); ?>">
                                    <span class="status-indicator" title="Не настроено">⚪</span>
                                </div>
                            </div>
                            <p class="description">
                                <?php echo esc_html($field_info['description']); ?><br>
                                <code><?php echo esc_html($field_info['yml_tag']); ?></code>
                            </p>
                            <div id="field-<?php echo esc_attr($field_id); ?>" 
                                 class="yfgp-field-container-v3" 
                                 data-field-id="<?php echo esc_attr($field_id); ?>"
                                 data-post-type="<?php echo esc_attr($post_type); ?>"
                                 data-default-source="<?php echo esc_attr($field_info['default_source']); ?>">
                                <!-- Dynamic Field Selector V3 будет вставлен сюда через JS -->
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Необязательные поля -->
            <div class="yfgp-mapping-section" style="margin-top: 30px;">
                <h2 style="color: #2271b1;">📋 Необязательные поля врача</h2>
                <p class="description">Заполните для более полной информации в фиде</p>
                
                <div class="yfgp-mapping-grid">
                    <?php foreach ($doctors_optional_fields as $field_id => $field_info): 
                        // v4.18.22: Скрыть поле связи с клиниками в режиме "одна клиника"
                        if ($field_id === 'clinics' && $is_single_clinic_mode) {
                            continue;
                        }
                    ?>
                        <div class="yfgp-mapping-card" data-field-id="<?php echo esc_attr($field_id); ?>">
                            <div class="card-header">
                                <h3>
                                    <?php echo esc_html($field_info['label']); ?>
                                    <span class="yfgp-field-type-badge">опционально</span>
                                </h3>
                                <div class="card-status" data-field="<?php echo esc_attr($field_id); ?>">
                                    <span class="status-indicator" title="Не настроено">⚪</span>
                                </div>
                            </div>
                            <p class="description">
                                <?php echo esc_html($field_info['description']); ?><br>
                                <code><?php echo esc_html($field_info['yml_tag']); ?></code>
                            </p>
                            <div id="field-<?php echo esc_attr($field_id); ?>" 
                                 class="yfgp-field-container-v3" 
                                 data-field-id="<?php echo esc_attr($field_id); ?>"
                                 data-post-type="<?php echo esc_attr($post_type); ?>"
                                 data-default-source="<?php echo esc_attr($field_info['default_source']); ?>">
                                <!-- Dynamic Field Selector V3 -->
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Repeater блоки -->
            <div class="yfgp-mapping-section" style="margin-top: 30px;">
                <h2 style="color: #8b5cf6;">🔁 Образование, работа, сертификаты, отзывы</h2>
                <p class="description">
                    <strong>v3.0:</strong> Источник данных для этих полей настраивается на странице 
                    <a href="<?php echo admin_url('admin.php?page=yandex-feed-generator'); ?>">Настройки</a> 
                    (Repeater field или Relationship к CPT).
                </p>
                
                <div class="yfgp-mapping-grid">
                    <?php foreach ($doctors_repeater_fields as $field_id => $field_info): 
                        $source_type = $data_sources[$field_info['source_setting']] ?? 'repeater';
                        $cpt_slug = $data_sources[$field_info['cpt_setting']] ?? '';
                    ?>
                        <div class="yfgp-mapping-card repeater-card" data-field-id="<?php echo esc_attr($field_id); ?>">
                            <div class="card-header">
                                <h3>
                                    <?php echo esc_html($field_info['label']); ?>
                                    <span class="yfgp-field-type-badge special">массив</span>
                                </h3>
                                <div class="card-status" data-field="<?php echo esc_attr($field_id); ?>">
                                    <span class="status-indicator" title="Не настроено">⚪</span>
                                </div>
                            </div>
                            <p class="description">
                                <?php echo esc_html($field_info['description']); ?><br>
                                <code><?php echo esc_html($field_info['yml_tag']); ?></code>
                            </p>
                            
                            <!-- Информация об источнике -->
                            <div class="source-info-box">
                                <strong>Источник данных:</strong>
                                <?php if ($source_type === 'repeater'): ?>
                                    <span class="source-badge repeater">🔁 Repeater field</span>
                                <?php else: ?>
                                    <span class="source-badge relationship">🔗 Relationship</span>
                                    <?php if ($cpt_slug): ?>
                                        <span class="cpt-info">→ CPT: <code><?php echo esc_html($cpt_slug); ?></code></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <a href="<?php echo admin_url('admin.php?page=yandex-feed-generator#data-sources'); ?>" 
                                   class="change-source-link" target="_blank">Изменить</a>
                            </div>
                            
                            <!-- v3.2.2: Основное repeater поле (контекст для подполей) -->
                            <?php if ($source_type === 'repeater'): ?>
                            <div class="main-repeater-field" style="margin: 15px 0; padding: 10px; background: #f0f0f1; border-radius: 4px;">
                                <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                                    📌 Основное repeater поле:
                                    <span style="font-weight: 400; color: #646970;">(Укажите конкретное repeater поле для извлечения подполей)</span>
                                </label>
                                <p class="description" style="margin: 5px 0 10px; font-size: 12px;">
                                    Например: <code>doctor_education</code> или <code>education_repeater</code>. 
                                    Подполя ниже будут искаться внутри выбранного repeater поля.
                                </p>
                                <div id="field-<?php echo esc_attr($field_id); ?>-repeater-main" 
                                     class="yfgp-field-container-v3" 
                                     data-field-id="<?php echo esc_attr($field_id . '_repeater_field'); ?>"
                                     data-parent-field="<?php echo esc_attr($field_id); ?>"
                                     data-post-type="<?php echo esc_attr($tab_context['doctors']['slug'] ?: $post_type); ?>"
                                     data-default-source="repeater_acf">
                                    <!-- Dynamic Field Selector V3 -->
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Подполя -->
                            <div class="sub-fields-container">
                                <h4>Подполя:</h4>
                                <?php foreach ($field_info['sub_fields'] as $sub_id => $sub_label): ?>
                                    <div class="sub-field-row" data-sub-field="<?php echo esc_attr($sub_id); ?>">
                                        <label><?php echo esc_html($sub_label); ?>:</label>
                                        <div id="field-<?php echo esc_attr($field_id); ?>-<?php echo esc_attr($sub_id); ?>" 
                                             class="yfgp-field-container-v3 sub-field" 
                                             data-field-id="<?php echo esc_attr($field_id . '_' . $sub_id); ?>"
                                             data-parent-field="<?php echo esc_attr($field_id); ?>"
                                             data-sub-field="<?php echo esc_attr($sub_id); ?>"
                                             data-post-type="<?php echo esc_attr($source_type === 'relationship' && $cpt_slug ? $cpt_slug : ($tab_context['doctors']['slug'] ?: $post_type)); ?>"
                                             data-source-type="<?php echo esc_attr($source_type); ?>"
                                             data-default-source="<?php echo esc_attr($source_type === 'repeater' ? 'repeater_acf' : 'meta_field'); ?>">
                                            <!-- Dynamic Field Selector V3 -->
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- ====================== TAB 2: КЛИНИКИ ====================== -->
        <div id="tab-clinics" class="yfgp-tab-content">
            
            <?php if ($is_single_clinic_mode): ?>
            <!-- v4.18.22: Информационное сообщение для режима "одна клиника" -->
            <div class="notice notice-info inline" style="margin: 20px 0; padding: 15px; background: #e7f5fe; border-left: 4px solid #2271b1;">
                <p style="margin: 0; font-size: 14px;">
                    <strong>🏥 Режим "Одна клиника" активен</strong><br>
                    Все врачи будут автоматически связаны с одной клиникой. Заполните данные клиники ниже. 
                    Поле связи с врачами отключено, так как связь устанавливается автоматически.
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Обязательные поля -->
            <div class="yfgp-mapping-section">
                <h2 style="color: #d63638;">⚠️ Обязательные поля клиники</h2>
                <p class="description">Эти поля должны быть заполнены для корректной работы фида</p>
                
                <div class="yfgp-mapping-grid">
                    <?php foreach ($clinics_required_fields as $field_id => $field_info): ?>
                        <div class="yfgp-mapping-card required" data-field-id="clinics_<?php echo esc_attr($field_id); ?>">
                            <div class="card-header">
                                <h3>
                                    <?php echo esc_html($field_info['label']); ?>
                                    <span class="yfgp-field-type-badge required">обязательно</span>
                                </h3>
                                <div class="card-status" data-field="clinics_<?php echo esc_attr($field_id); ?>">
                                    <span class="status-indicator" title="Не настроено">⚪</span>
                                </div>
                            </div>
                            <p class="description">
                                <?php echo esc_html($field_info['description']); ?><br>
                                <code><?php echo esc_html($field_info['yml_tag']); ?></code>
                            </p>
                            <div id="field-clinics-<?php echo esc_attr($field_id); ?>" 
                                 class="yfgp-field-container-v3" 
                                 data-field-id="clinics_<?php echo esc_attr($field_id); ?>"
                                 data-post-type="<?php echo esc_attr($tab_context['clinics']['slug'] ?: 'clinics'); ?>"
                                 data-default-source="<?php echo esc_attr($field_info['default_source']); ?>">
                                <!-- Dynamic Field Selector V3 -->
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Необязательные поля -->
            <div class="yfgp-mapping-section" style="margin-top: 30px;">
                <h2 style="color: #2271b1;">📋 Необязательные поля клиники</h2>
                <p class="description">Заполните для более полной информации в фиде</p>
                
                <div class="yfgp-mapping-grid">
                    <?php foreach ($clinics_optional_fields as $field_id => $field_info): ?>
                        <div class="yfgp-mapping-card" data-field-id="clinics_<?php echo esc_attr($field_id); ?>">
                            <div class="card-header">
                                <h3>
                                    <?php echo esc_html($field_info['label']); ?>
                                    <span class="yfgp-field-type-badge">опционально</span>
                                </h3>
                                <div class="card-status" data-field="clinics_<?php echo esc_attr($field_id); ?>">
                                    <span class="status-indicator" title="Не настроено">⚪</span>
                                </div>
                            </div>
                            <p class="description">
                                <?php echo esc_html($field_info['description']); ?><br>
                                <code><?php echo esc_html($field_info['yml_tag']); ?></code>
                            </p>
                            <div id="field-clinics-<?php echo esc_attr($field_id); ?>" 
                                 class="yfgp-field-container-v3" 
                                 data-field-id="clinics_<?php echo esc_attr($field_id); ?>"
                                 data-post-type="<?php echo esc_attr($tab_context['clinics']['slug'] ?: 'clinics'); ?>"
                                 data-default-source="<?php echo esc_attr($field_info['default_source']); ?>"
                                 <?php if (!empty($field_info['data_format_options'])): ?>
                                 data-has-format-options="true"
                                 data-format-options="<?php echo esc_attr(json_encode($field_info['data_format_options'])); ?>"
                                 <?php endif; ?>>
                                <!-- Dynamic Field Selector V3 -->
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Связи -->
            <?php if (!$is_single_clinic_mode): ?>
            <!-- v4.18.22: Скрыть связи в режиме "одна клиника" -->
            <div class="yfgp-mapping-section" style="margin-top: 30px;">
                <h2 style="color: #ea580c;">🔗 Связи клиники</h2>
                <p class="description">Настройте связи клиники с другими сущностями</p>
                
                <div class="yfgp-mapping-grid">
                    <?php foreach ($clinics_relationships as $field_id => $field_info): ?>
                        <div class="yfgp-mapping-card" data-field-id="clinics_<?php echo esc_attr($field_id); ?>">
                            <div class="card-header">
                                <h3>
                                    <?php echo esc_html($field_info['label']); ?>
                                    <span class="yfgp-field-type-badge special">связь</span>
                                </h3>
                                <div class="card-status" data-field="clinics_<?php echo esc_attr($field_id); ?>">
                                    <span class="status-indicator" title="Не настроено">⚪</span>
                                </div>
                            </div>
                            <p class="description">
                                <?php echo esc_html($field_info['description']); ?><br>
                                <code><?php echo esc_html($field_info['yml_tag']); ?></code>
                            </p>
                            <div id="field-clinics-<?php echo esc_attr($field_id); ?>" 
                                 class="yfgp-field-container-v3" 
                                 data-field-id="clinics_<?php echo esc_attr($field_id); ?>"
                                 data-post-type="<?php echo esc_attr($tab_context['clinics']['slug'] ?: 'clinics'); ?>"
                                 data-default-source="<?php echo esc_attr($field_info['default_source']); ?>">
                                <!-- Dynamic Field Selector V3 -->
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- ====================== TAB 3: УСЛУГИ ====================== -->
        <div id="tab-services" class="yfgp-tab-content">
            
            <!-- Обязательные поля -->
            <div class="yfgp-mapping-section">
                <h2 style="color: #d63638;">⚠️ Обязательные поля услуги</h2>
                <p class="description">Эти поля должны быть заполнены для корректной работы фида</p>
                
                <div class="yfgp-mapping-grid">
                    <?php foreach ($services_required_fields as $field_id => $field_info): ?>
                        <div class="yfgp-mapping-card required" data-field-id="services_<?php echo esc_attr($field_id); ?>">
                            <div class="card-header">
                                <h3>
                                    <?php echo esc_html($field_info['label']); ?>
                                    <span class="yfgp-field-type-badge required">обязательно</span>
                                </h3>
                                <div class="card-status" data-field="services_<?php echo esc_attr($field_id); ?>">
                                    <span class="status-indicator" title="Не настроено">⚪</span>
                                </div>
                            </div>
                            <p class="description">
                                <?php echo esc_html($field_info['description']); ?><br>
                                <code><?php echo esc_html($field_info['yml_tag']); ?></code>
                            </p>
                            <div id="field-services-<?php echo esc_attr($field_id); ?>" 
                                 class="yfgp-field-container-v3" 
                                 data-field-id="services_<?php echo esc_attr($field_id); ?>"
                                 data-post-type="<?php echo esc_attr($tab_context['services']['slug'] ?: 'services'); ?>"
                                 data-default-source="<?php echo esc_attr($field_info['default_source']); ?>">
                                <!-- Dynamic Field Selector V3 -->
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Необязательные поля -->
            <div class="yfgp-mapping-section" style="margin-top: 30px;">
                <h2 style="color: #2271b1;">📋 Необязательные поля услуги</h2>
                <p class="description">Заполните для более полной информации в фиде</p>
                
                <div class="yfgp-mapping-grid">
                    <?php foreach ($services_optional_fields as $field_id => $field_info): ?>
                        <div class="yfgp-mapping-card" data-field-id="services_<?php echo esc_attr($field_id); ?>">
                            <div class="card-header">
                                <h3>
                                    <?php echo esc_html($field_info['label']); ?>
                                    <span class="yfgp-field-type-badge">опционально</span>
                                </h3>
                                <div class="card-status" data-field="services_<?php echo esc_attr($field_id); ?>">
                                    <span class="status-indicator" title="Не настроено">⚪</span>
                                </div>
                            </div>
                            <p class="description">
                                <?php echo esc_html($field_info['description']); ?><br>
                                <code><?php echo esc_html($field_info['yml_tag']); ?></code>
                            </p>
                            <div id="field-services-<?php echo esc_attr($field_id); ?>" 
                                 class="yfgp-field-container-v3" 
                                 data-field-id="services_<?php echo esc_attr($field_id); ?>"
                                 data-post-type="<?php echo esc_attr($tab_context['services']['slug'] ?: 'services'); ?>"
                                 data-default-source="<?php echo esc_attr($field_info['default_source']); ?>">
                                <!-- Dynamic Field Selector V3 -->
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Связи -->
            <div class="yfgp-mapping-section" style="margin-top: 30px;">
                <h2 style="color: #ea580c;">🔗 Связи услуги</h2>
                <p class="description">Настройте связи услуги с другими сущностями</p>
                
                <div class="yfgp-mapping-grid">
                    <?php foreach ($services_relationships as $field_id => $field_info): ?>
                        <div class="yfgp-mapping-card" data-field-id="services_<?php echo esc_attr($field_id); ?>">
                            <div class="card-header">
                                <h3>
                                    <?php echo esc_html($field_info['label']); ?>
                                    <span class="yfgp-field-type-badge special">связь</span>
                                </h3>
                                <div class="card-status" data-field="services_<?php echo esc_attr($field_id); ?>">
                                    <span class="status-indicator" title="Не настроено">⚪</span>
                                </div>
                            </div>
                            <p class="description">
                                <?php echo esc_html($field_info['description']); ?><br>
                                <code><?php echo esc_html($field_info['yml_tag']); ?></code>
                            </p>
                            <div id="field-services-<?php echo esc_attr($field_id); ?>" 
                                 class="yfgp-field-container-v3" 
                                 data-field-id="services_<?php echo esc_attr($field_id); ?>"
                                 data-post-type="<?php echo esc_attr($tab_context['services']['slug'] ?: 'services'); ?>"
                                 data-default-source="<?php echo esc_attr($field_info['default_source']); ?>">
                                <!-- Dynamic Field Selector V3 -->
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Раздел Цены -->
            <div class="yfgp-mapping-section" style="margin-top: 30px;">
                <h2 style="color: #10b981;">💰 Раздел ЦЕН</h2>
                <p class="description">
                    <strong>v3.1:</strong> Источник данных для цен настраивается на странице 
                    <a href="<?php echo admin_url('admin.php?page=yandex-feed-generator'); ?>">Настройки</a> 
                    (Repeater field или Relationship к CPT).
                </p>
                
                <div class="yfgp-mapping-grid">
                    <?php 
                        $source_type = $data_sources['source_prices'] ?? 'repeater';
                        $cpt_slug = $data_sources['source_prices_cpt'] ?? '';
                    ?>
                        <div class="yfgp-mapping-card repeater-card" data-field-id="prices">
                            <div class="card-header">
                                <h3>
                                    <?php echo esc_html($prices_field['label']); ?>
                                    <span class="yfgp-field-type-badge special">массив</span>
                                </h3>
                                <div class="card-status" data-field="prices">
                                    <span class="status-indicator" title="Не настроено">⚪</span>
                                </div>
                            </div>
                            <p class="description">
                                <?php echo esc_html($prices_field['description']); ?><br>
                                <code><?php echo esc_html($prices_field['yml_tag']); ?></code>
                            </p>
                            
                            <!-- Информация об источнике -->
                            <div class="source-info-box">
                                <strong>Источник данных:</strong>
                                <?php if ($source_type === 'repeater'): ?>
                                    <span class="source-badge repeater">🔁 Repeater field</span>
                                <?php else: ?>
                                    <span class="source-badge relationship">🔗 Relationship</span>
                                    <?php if ($cpt_slug): ?>
                                        <span class="cpt-info">→ CPT: <code><?php echo esc_html($cpt_slug); ?></code></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <a href="<?php echo admin_url('admin.php?page=yandex-feed-generator#data-sources'); ?>" 
                                   class="change-source-link" target="_blank">Изменить</a>
                            </div>
                            
                            <!-- v3.2.4: Основное поле цены (Price Context Pattern) -->
                            <?php if ($source_type === 'repeater' || $source_type === 'relationship'): ?>
                            <div class="yfgp-ui-block" style="background: #fef3c7; padding: 15px; margin-bottom: 15px; border-left: 4px solid #f59e0b;">
                                <h4 style="margin: 0 0 10px 0; color: #92400e;">
                                    📍 Основное поле цены
                                </h4>
                                <p class="description" style="margin-bottom: 10px;">
                                    Выберите откуда брать данные о ценах. Это может быть:
                                    <br>• <strong>Прямые поля услуги</strong> - ACF/JetEngine поля самой услуги (например: service_price)
                                    <br>• <strong>Relationship к CPT "Цена"</strong> - отдельный CPT с ценами (например: price_relation)
                                    <br>Подполя ниже будут извлекаться ИЗ выбранного источника.
                                    <br>Например: <code>service_price_data</code> (ACF поле) или <code>price_relationship</code>
                                </p>
                                
                                <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                                    Тип источника данных:
                                </label>
                                <div id="field-prices-price_source_field" 
                                     class="yfgp-field-container-v3"
                                     data-field-id="prices_price_source_field"
                                     data-parent-field="prices"
                                     data-post-type="services"
                                     data-source-type="<?php echo esc_attr($source_type); ?>"
                                     data-default-source="meta_field">
                                    <!-- Dynamic Field Selector V3 -->
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Подполя -->
                            <div class="sub-fields-container">
                                <h4>Подполя:</h4>
                                <?php foreach ($prices_field['sub_fields'] as $sub_id => $sub_label): ?>
                                    <div class="sub-field-row" data-sub-field="<?php echo esc_attr($sub_id); ?>">
                                        <label><?php echo esc_html($sub_label); ?>:</label>
                                        <div id="field-prices-<?php echo esc_attr($sub_id); ?>" 
                                             class="yfgp-field-container-v3 sub-field" 
                                             data-field-id="<?php echo esc_attr('prices_' . $sub_id); ?>"
                                             data-parent-field="prices"
                                             data-sub-field="<?php echo esc_attr($sub_id); ?>"
                                             data-post-type="<?php echo esc_attr($source_type === 'relationship' && $cpt_slug ? $cpt_slug : 'services'); ?>"
                                             data-source-type="<?php echo esc_attr($source_type); ?>"
                                             data-default-source="<?php echo esc_attr($source_type === 'repeater' ? 'repeater_acf' : 'meta_field'); ?>">
                                            <!-- Dynamic Field Selector V3 -->
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                </div>
            </div>
        </div>
        
        <!-- ====================== TAB 4: ОФФЕРЫ ====================== -->
        <div id="tab-offers" class="yfgp-tab-content">
            
            <div class="yfgp-info-banner" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px;">
                <strong>⚠️ Важно:</strong> Офферы НЕ являются Custom Post Type! Это <strong>виртуальная сущность</strong>, которая генерируется динамически из связей между Врачами, Клиниками и Услугами.
                Настройте здесь как плагин будет получать данные для XML элементов оффера.
            </div>
            
            <!-- Раздел 1: ПАРАМЕТРЫ ОФФЕРА -->
            <div class="yfgp-mapping-section">
                <h2 style="color: #2271b1;">📋 Параметры оффера</h2>
                <p class="description">Настройте откуда брать параметры для элемента &lt;offer&gt;</p>
                
                <div class="yfgp-mapping-grid">
                    <?php foreach ($offers_params as $field_id => $field_info): 
                        $current_config = $offer_config['offer_' . $field_id] ?? array('mode' => $field_info['default_mode'], 'inherit_from' => $field_info['inherit_from']);
                        $is_manual = ($current_config['mode'] ?? 'inherit') === 'manual';
                    ?>
                        <div class="yfgp-mapping-card offer-field" data-field-id="offer_<?php echo esc_attr($field_id); ?>">
                            <div class="card-header">
                                <h3>
                                    <?php echo esc_html($field_info['label']); ?>
                                    <span class="yfgp-field-type-badge">оффер</span>
                                </h3>
                                <div class="card-status" data-field="offer_<?php echo esc_attr($field_id); ?>">
                                    <span class="status-indicator" title="<?php echo $is_manual ? 'Ручная настройка' : 'Наследование'; ?>">
                                        <?php echo $is_manual ? '✏️' : '🔗'; ?>
                                    </span>
                                </div>
                            </div>
                            <p class="description">
                                <?php echo esc_html($field_info['description']); ?><br>
                                <small>По умолчанию: наследуется от <strong><?php echo esc_html($field_info['inherit_from']); ?></strong></small>
                            </p>
                            
                            <!-- Переключатель режима -->
                            <div class="inheritance-toggle-wrapper">
                                <label class="inheritance-option">
                                    <input type="radio" 
                                           name="offer_<?php echo esc_attr($field_id); ?>_mode" 
                                           value="inherit"
                                           <?php checked(!$is_manual); ?>>
                                    <span class="option-label">🔗 Наследовать от <?php echo esc_html($field_info['inherit_from']); ?></span>
                                </label>
                                <label class="inheritance-option">
                                    <input type="radio" 
                                           name="offer_<?php echo esc_attr($field_id); ?>_mode" 
                                           value="manual"
                                           <?php checked($is_manual); ?>>
                                    <span class="option-label">✏️ Задать вручную</span>
                                </label>
                            </div>
                            
                            <input type="hidden" name="offer_<?php echo esc_attr($field_id); ?>_inherit_from" value="<?php echo esc_attr($field_info['inherit_from']); ?>">
                            
                            <!-- Dynamic Field Selector V3 (только если manual) -->
                            <div class="manual-config-container" style="display: <?php echo $is_manual ? 'block' : 'none'; ?>;">
                                <div id="field-offer-<?php echo esc_attr($field_id); ?>" 
                                     class="yfgp-field-container-v3" 
                                     data-field-id="offer_<?php echo esc_attr($field_id); ?>"
                                     data-post-type="doctors"
                                     data-default-source="meta_field">
                                    <!-- Dynamic Field Selector V3 -->
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Раздел 2: ПАРАМЕТРЫ ВРАЧА В ОФФЕРЕ -->
            <div class="yfgp-mapping-section" style="margin-top: 30px;">
                <h2 style="color: #8b5cf6;">👨‍⚕️ Параметры врача в оффере</h2>
                <p class="description">Настройте откуда брать параметры врача для элемента &lt;offer&gt;&lt;doctor&gt;</p>
                
                <div class="yfgp-mapping-grid">
                    <?php foreach ($offers_doctor_params as $field_id => $field_info): 
                        $current_config = $offer_config['doctor_' . $field_id] ?? array('mode' => $field_info['default_mode'], 'inherit_from' => $field_info['inherit_from']);
                        $is_manual = ($current_config['mode'] ?? 'inherit') === 'manual';
                    ?>
                        <div class="yfgp-mapping-card offer-field" data-field-id="doctor_<?php echo esc_attr($field_id); ?>">
                            <div class="card-header">
                                <h3>
                                    <?php echo esc_html($field_info['label']); ?>
                                    <span class="yfgp-field-type-badge">врач в оффере</span>
                                </h3>
                                <div class="card-status" data-field="doctor_<?php echo esc_attr($field_id); ?>">
                                    <span class="status-indicator" title="<?php echo $is_manual ? 'Ручная настройка' : 'Наследование'; ?>">
                                        <?php echo $is_manual ? '✏️' : '🔗'; ?>
                                    </span>
                                </div>
                            </div>
                            <p class="description">
                                <?php echo esc_html($field_info['description']); ?><br>
                                <small>По умолчанию: наследуется от <strong><?php echo esc_html($field_info['inherit_from']); ?></strong></small>
                            </p>
                            
                            <!-- Переключатель режима -->
                            <div class="inheritance-toggle-wrapper">
                                <label class="inheritance-option">
                                    <input type="radio" 
                                           name="doctor_<?php echo esc_attr($field_id); ?>_mode" 
                                           value="inherit"
                                           <?php checked(!$is_manual); ?>>
                                    <span class="option-label">🔗 Наследовать от <?php echo esc_html($field_info['inherit_from']); ?></span>
                                </label>
                                <label class="inheritance-option">
                                    <input type="radio" 
                                           name="doctor_<?php echo esc_attr($field_id); ?>_mode" 
                                           value="manual"
                                           <?php checked($is_manual); ?>>
                                    <span class="option-label">✏️ Задать вручную</span>
                                </label>
                            </div>
                            
                            <input type="hidden" name="doctor_<?php echo esc_attr($field_id); ?>_inherit_from" value="<?php echo esc_attr($field_info['inherit_from']); ?>">
                            
                            <!-- Dynamic Field Selector V3 (только если manual) -->
                            <div class="manual-config-container" style="display: <?php echo $is_manual ? 'block' : 'none'; ?>;">
                                <div id="field-doctor-<?php echo esc_attr($field_id); ?>" 
                                     class="yfgp-field-container-v3" 
                                     data-field-id="doctor_<?php echo esc_attr($field_id); ?>"
                                     data-post-type="doctors"
                                     data-default-source="meta_field">
                                    <!-- Dynamic Field Selector V3 -->
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Раздел 3: ЦЕНА В ОФФЕРЕ -->
            <div class="yfgp-mapping-section" style="margin-top: 30px;">
                <h2 style="color: #10b981;">💰 Цена в оффере</h2>
                <p class="description">Настройте откуда брать параметры цены для элемента &lt;offer&gt;&lt;price&gt;</p>
                
                <div class="yfgp-mapping-grid">
                    <?php foreach ($offers_price_params as $field_id => $field_info): 
                        $current_config = $offer_config['price_' . $field_id] ?? array('mode' => $field_info['default_mode'], 'inherit_from' => $field_info['inherit_from']);
                        $is_manual = ($current_config['mode'] ?? 'inherit') === 'manual';
                    ?>
                        <div class="yfgp-mapping-card offer-field" data-field-id="price_<?php echo esc_attr($field_id); ?>">
                            <div class="card-header">
                                <h3>
                                    <?php echo esc_html($field_info['label']); ?>
                                    <span class="yfgp-field-type-badge">цена</span>
                                </h3>
                                <div class="card-status" data-field="price_<?php echo esc_attr($field_id); ?>">
                                    <span class="status-indicator" title="<?php echo $is_manual ? 'Ручная настройка' : 'Наследование'; ?>">
                                        <?php echo $is_manual ? '✏️' : '🔗'; ?>
                                    </span>
                                </div>
                            </div>
                            <p class="description">
                                <?php echo esc_html($field_info['description']); ?><br>
                                <small>По умолчанию: наследуется от <strong><?php echo esc_html($field_info['inherit_from']); ?></strong></small>
                            </p>
                            
                            <!-- Переключатель режима -->
                            <div class="inheritance-toggle-wrapper">
                                <label class="inheritance-option">
                                    <input type="radio" 
                                           name="price_<?php echo esc_attr($field_id); ?>_mode" 
                                           value="inherit"
                                           <?php checked(!$is_manual); ?>>
                                    <span class="option-label">🔗 Наследовать от <?php echo esc_html($field_info['inherit_from']); ?></span>
                                </label>
                                <label class="inheritance-option">
                                    <input type="radio" 
                                           name="price_<?php echo esc_attr($field_id); ?>_mode" 
                                           value="manual"
                                           <?php checked($is_manual); ?>>
                                    <span class="option-label">✏️ Задать вручную</span>
                                </label>
                            </div>
                            
                            <input type="hidden" name="price_<?php echo esc_attr($field_id); ?>_inherit_from" value="<?php echo esc_attr($field_info['inherit_from']); ?>">
                            
                            <!-- Dynamic Field Selector V3 (только если manual) -->
                            <div class="manual-config-container" style="display: <?php echo $is_manual ? 'block' : 'none'; ?>;">
                                <div id="field-price-<?php echo esc_attr($field_id); ?>" 
                                     class="yfgp-field-container-v3" 
                                     data-field-id="price_<?php echo esc_attr($field_id); ?>"
                                     data-post-type="services"
                                     data-default-source="meta_field">
                                    <!-- Dynamic Field Selector V3 -->
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- ====================== SAVE BUTTON ====================== -->
        <div class="yfgp-save-section" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
            <button type="submit" class="button button-primary button-hero">
                💾 Сохранить маппинг
            </button>
            
            <!-- v4.18.21: Test Preview Post Selector (4 dropdowns, один на таб) - ПЕРЕД кнопкой теста -->
            <select id="yfgp-test-post-doctors" class="yfgp-test-post-selector" data-tab="doctors" style="display: none; min-width: 280px; padding: 8px; height: 38px;">
                <option value="">-- Выберите врача для теста --</option>
            </select>
            <select id="yfgp-test-post-clinics" class="yfgp-test-post-selector" data-tab="clinics" style="display: none; min-width: 280px; padding: 8px; height: 38px;">
                <option value="">-- Выберите клинику для теста --</option>
            </select>
            <select id="yfgp-test-post-services" class="yfgp-test-post-selector" data-tab="services" style="display: none; min-width: 280px; padding: 8px; height: 38px;">
                <option value="">-- Выберите услугу для теста --</option>
            </select>
            <select id="yfgp-test-post-offers" class="yfgp-test-post-selector" data-tab="offers" style="display: none; min-width: 280px; padding: 8px; height: 38px;">
                <option value="">-- Выберите врача для теста --</option>
            </select>
            
            <button type="button" id="yfgp-test-mapping-btn" class="button button-secondary button-large">
                🧪 Тест текущей вкладки
            </button>
            <span class="save-status" style="margin-left: 20px; display: none;"></span>
            <span id="yfgp-test-result" style="margin-left: 10px; display: none;"></span>
        </div>
    </form>
</div>

<!-- v3.1.0: CSS для маппинга -->
<style>
/* Tab Navigation */
.yfgp-mega-tab-wrapper {
    border-bottom: 1px solid #c3c4c7;
}

.yfgp-tab-badge {
    display: inline-block;
    margin-left: 8px;
    padding: 2px 8px;
    background: #f0f0f1;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
    color: #646970;
}

.nav-tab-active .yfgp-tab-badge {
    background: #2271b1;
    color: white;
}

/* Tab Content */
.yfgp-tab-content {
    display: none;
    padding: 20px 0;
}

.yfgp-tab-active {
    display: block;
}

/* Mapping Grid */
.yfgp-mapping-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

/* Mapping Card */
.yfgp-mapping-card {
    background: white;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    padding: 20px;
    transition: box-shadow 0.2s;
}

.yfgp-mapping-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.yfgp-mapping-card.required {
    border-left: 4px solid #d63638;
}

/* Card Header */
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.card-header h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-status {
    flex-shrink: 0;
}

.status-indicator {
    font-size: 16px;
    cursor: help;
}

/* Field Type Badge */
.yfgp-field-type-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.yfgp-field-type-badge.required {
    background: #fef3cd;
    color: #8c5e14;
}

.yfgp-field-type-badge:not(.required):not(.special) {
    background: #e6f0ff;
    color: #2271b1;
}

.yfgp-field-type-badge.special {
    background: #f0e6ff;
    color: #8b5cf6;
}

/* Source Info Box */
.source-info-box {
    background: #f6f7f7;
    border: 1px solid #dcdcde;
    border-radius: 4px;
    padding: 12px;
    margin: 12px 0;
    font-size: 13px;
}

.source-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-weight: 500;
    margin: 0 6px;
}

.source-badge.repeater {
    background: #d7f0db;
    color: #2c6e49;
}

.source-badge.relationship {
    background: #cfe2ff;
    color: #084298;
}

.cpt-info {
    color: #646970;
}

.change-source-link {
    margin-left: 12px;
    font-size: 12px;
}

/* Sub-fields */
.sub-fields-container {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #f0f0f1;
}

.sub-fields-container h4 {
    margin: 0 0 12px 0;
    font-size: 13px;
    color: #646970;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.sub-field-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.sub-field-row label {
    min-width: 150px;
    font-weight: 500;
    font-size: 13px;
}

.sub-field-row .yfgp-field-container-v3 {
    flex: 1;
}

/* Field Container V3 */
.yfgp-field-container-v3 {
    margin-top: 12px;
}

.yfgp-field-container-v3.sub-field {
    margin-top: 0;
}

/* Save Section */
.yfgp-save-section {
    margin-top: 40px;
    padding: 20px;
    background: white;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    text-align: center;
}

.save-status.success {
    color: #2c6e49;
}

.save-status.error {
    color: #d63638;
}

/* Inheritance Toggle (для офферов) */
.inheritance-toggle-wrapper {
    margin: 15px 0;
    display: flex;
    gap: 10px;
}

.inheritance-option {
    flex: 1;
    padding: 12px;
    border: 2px solid #dcdcde;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
}

.inheritance-option:hover {
    border-color: #2271b1;
    background: #f6f7f7;
}

.inheritance-option input[type="radio"] {
    margin-right: 8px;
}

.inheritance-option input[type="radio"]:checked ~ .option-label {
    font-weight: 600;
    color: #2271b1;
}

.manual-config-container {
    margin-top: 15px;
    padding: 15px;
    background: #f6f7f7;
    border-radius: 4px;
    border: 1px solid #dcdcde;
}

/* Responsive */
@media (max-width: 782px) {
    .yfgp-mapping-grid {
        grid-template-columns: 1fr;
    }
    
    .card-header {
        flex-direction: column;
        gap: 8px;
    }
    
    .sub-field-row {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .sub-field-row label {
        min-width: auto;
    }
}
</style>

<!-- v3.1.0: JavaScript для маппинга -->
<script>
jQuery(document).ready(function($) {
    console.log('🗺️ Field Mapping V3.1 loaded');
    
    // v4.1.0: Check for saved flag and show success message
    if (sessionStorage.getItem('yfgp_mapping_saved') === 'true') {
        sessionStorage.removeItem('yfgp_mapping_saved');
        
        // Show success message at the top of the page
        var $notice = $('<div class="notice notice-success is-dismissible" style="margin: 20px 0;"><p><strong>✅ Успех!</strong> Настройки маппинга сохранены и данные восстановлены!</p></div>');
        $('.wrap h1').after($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(400, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // v4.1.0: Set flag before form submission
    $('#yfgp-mapping-form-v3').on('submit', function() {
        sessionStorage.setItem('yfgp_mapping_saved', 'true');
    });
    
    // Tab Switching
    $('.yfgp-mega-tab-wrapper .nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var tabId = $(this).data('tab');
        
        // Update nav tabs
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Update content
        $('.yfgp-tab-content').removeClass('yfgp-tab-active');
        $('#tab-' + tabId).addClass('yfgp-tab-active');
    });
    
    // Initialize Dynamic Field Selectors V3
    function initializeFieldSelectors() {
        var currentMapping = <?php echo json_encode($current_mapping); ?>;
        
        $('.yfgp-field-container-v3').each(function() {
            var $container = $(this);
            var fieldId = $container.data('field-id');
            var postType = $container.data('post-type');
            var isSubField = $container.hasClass('sub-field');
            var parentField = $container.data('parent-field');
            var subFieldName = $container.data('sub-field');
            var sourceType = $container.data('source-type');
            
            var isRepeaterField = isSubField ? false : $container.closest('.repeater-card').length > 0;
            var currentValue = currentMapping[fieldId] || null;
            var fieldName = 'yfgp_field_mapping_v3[' + fieldId + ']';
            
            $container.html('<div class="yfgp-dynamic-selector-wrapper" data-field-selector-v3 data-field-name="' + fieldName + '" data-post-type="' + postType + '" data-current-value=\'' + JSON.stringify(currentValue) + '\' data-is-repeater-field="' + isRepeaterField + '"></div>');
            
            var $selector = $container.find('[data-field-selector-v3]');
            $selector.dynamicFieldSelectorV3({
                fieldName: fieldName,
                postType: postType,
                currentValue: currentValue,
                isRepeaterField: isRepeaterField,
                onChange: function(config) {
                    updateFieldStatus(fieldId, config);
                    if (isSubField && parentField) {
                        updateRepeaterBlockStatus(parentField);
                    }
                    // v4.5.3: Don't call updateTabBadges() on every change - it's called once after all fields initialize
                }
            });
            
            // v4.5.2: For fields with saved values, update status immediately after initialization
            // This ensures indicators are set even if onChange doesn't trigger during restoreValue()
            if (currentValue && currentValue.source_type && !isSubField) {
                setTimeout(function() {
                    updateFieldStatus(fieldId, currentValue);
                }, 1500); // Wait for restoreValue() to complete
            }
            
            console.log('✅ Initialized selector for:', fieldId, isSubField ? '(sub-field of ' + parentField + ')' : '');
        });
        
        console.log('✅ All field selectors initialized');
        
        // v4.5.4: Progressive badge updates (accounts for async AJAX in restoreValue)
        setTimeout(function() {
            updateTabBadges();
        }, 2000);
        
        setTimeout(function() {
            updateTabBadges();
        }, 4000);
        
        setTimeout(function() {
            updateTabBadges();
        }, 6000);
    }
    
    function updateRepeaterBlockStatus(parentFieldId) {
        var $parentCard = $('.yfgp-mapping-card[data-field-id="' + parentFieldId + '"]');
        var $subFields = $parentCard.find('.sub-field-row');
        var totalSubFields = $subFields.length;
        var filledSubFields = 0;
        
        $subFields.each(function() {
            var $subField = $(this);
            var subFieldId = $subField.data('sub-field');
            var $selector = $('#field-' + parentFieldId + '-' + subFieldId).find('[data-field-selector-v3]');
            
            if ($selector.length > 0) {
                var selectorInstance = $selector.data('dynamicFieldSelectorV3');
                if (selectorInstance) {
                    var config = selectorInstance.getValue();
                    if (config && config.source_type && config.source_field) {
                        filledSubFields++;
                    }
                }
            }
        });
        
        var $indicator = $parentCard.find('.card-status .status-indicator');
        if (filledSubFields === totalSubFields && totalSubFields > 0) {
            $indicator.text('🟢').attr('title', 'Все подполя настроены (' + filledSubFields + '/' + totalSubFields + ')');
        } else if (filledSubFields > 0) {
            $indicator.text('🟡').attr('title', 'Частично настроено (' + filledSubFields + '/' + totalSubFields + ')');
        } else {
            $indicator.text('⚪').attr('title', 'Не настроено (0/' + totalSubFields + ')');
        }
    }
    
            function updateFieldStatus(fieldId, config) {
            var $card = $('.yfgp-mapping-card[data-field-id="' + fieldId + '"]');
            var $indicator = $card.find('.status-indicator');
            var isFilled = config && config.source_type && config.source_field;

            if (isFilled) {
                $indicator.text('🟢').attr('title', 'Настроено');
            } else {
                $indicator.text('⚪').attr('title', 'Не настроено');
            }
        }
    
            function updateTabBadges() {
            var tabs = {
                'doctors': { filled: 0, total: 20 },
                'clinics': { filled: 0, total: 11 },
                'services': { filled: 0, total: 7 },
                'offers': { filled: 0, total: 14 }
            };

            // v4.5.4: Find cards by data-field-id prefix (works even when tab is hidden!)
            $('.yfgp-mapping-card').each(function() {
                var $card = $(this);
                var fieldId = $card.data('field-id');
                var $indicator = $card.find('.status-indicator');
                // v4.5.6: WordPress converts emoji to <img> - read alt attribute instead of text
                var $img = $indicator.find('img');
                var indicator = $img.length > 0 ? $img.attr('alt') : $indicator.text();

                // Determine which tab this card belongs to based on field ID prefix
                if (fieldId && fieldId.toString().startsWith('clinics_')) {
                    if (indicator === '🟢') {
                        tabs.clinics.filled++;
                    }
                } else if (fieldId && fieldId.toString().startsWith('services_')) {
                    if (indicator === '🟢') {
                        tabs.services.filled++;
                    }
                } else if (fieldId && (fieldId.toString().startsWith('offer_') || fieldId.toString().startsWith('doctor_') || fieldId.toString().startsWith('price_'))) {
                    // Offers tab uses ✏️ (manual) and 🔗 (inherit) instead of 🟢
                    if (indicator === '🟢' || indicator === '✏️' || indicator === '🔗') {
                        tabs.offers.filled++;
                    }
                } else {
                    // No prefix = doctors tab
                    if (indicator === '🟢') {
                        tabs.doctors.filled++;
                    }
                }
            });
        
        $.each(tabs, function(tabId, counts) {
            var $badge = $('.yfgp-tab-badge[data-tab-id="' + tabId + '"]');
            $badge.text(counts.filled + '/' + counts.total);
            
            if (counts.filled === counts.total && counts.total > 0) {
                $badge.css('background', '#00a32a').css('color', 'white');
            } else if (counts.filled > 0) {
                $badge.css('background', '#dba617').css('color', 'white');
            } else {
                $badge.css('background', '#f0f0f1').css('color', '#646970');
            }
        });
    }
    
    // Inheritance Toggle для офферов
    $('.inheritance-toggle-wrapper input[type="radio"]').on('change', function() {
        var $card = $(this).closest('.offer-field');
        var mode = $(this).val();
        var $manualConfig = $card.find('.manual-config-container');
        
        if (mode === 'manual') {
            $manualConfig.slideDown(200);
        } else {
            $manualConfig.slideUp(200);
        }
        
        // Обновить status indicator
        var $indicator = $card.find('.status-indicator');
        if (mode === 'inherit') {
            $indicator.text('🔗').attr('title', 'Наследование');
        } else {
            $indicator.text('✏️').attr('title', 'Ручная настройка');
        }
    });
    
    // v4.18.21: Обработчик кнопки "Тест текущей вкладки" (обновлён для post selector)
    $('#yfgp-test-mapping-btn').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $result = $('#yfgp-test-result');
        
        // Определить активную вкладку
        var activeTab = $('.yfgp-mega-tab-wrapper .nav-tab-active').attr('href');
        var tabType = activeTab ? activeTab.replace('#tab-', '') : 'doctors';
        
        // v4.18.21: Получить selected post_id из dropdown (ОБЯЗАТЕЛЬНО для корректного превью!)
        var $activeSelector = $('.yfgp-test-post-selector:visible');
        var selectedPostId = $activeSelector.length > 0 ? $activeSelector.val() : '';
        
        // v4.18.21: Валидация - если пост не выбран, показать предупреждение
        if (!selectedPostId || selectedPostId === '') {
            $result.show().html('<span style="color: #dc3232;">⚠️ Пожалуйста, выберите пост из списка выше для тестирования</span>');
            setTimeout(function() {
                $result.fadeOut();
            }, 3000);
            return;
        }
        
        console.log('[YFGP Test] Tab:', tabType, 'Post ID:', selectedPostId);
        
        // Показать индикатор загрузки
        $btn.prop('disabled', true).html('⏳ Тестирование...');
        $result.show().html('<span style="color: #666;">Загрузка превью для выбранного поста...</span>');
        
        // AJAX запрос (с post_id - ОБЯЗАТЕЛЬНО!)
        // v4.18.13: Единый стандарт nonce для всех AJAX handlers
        var ajaxData = {
            action: 'yfgp_test_mapping',
            tab_type: tabType,
            post_id: selectedPostId, // v4.18.21: Всегда передаем post_id (валидация выше)
            nonce: '<?php echo wp_create_nonce("yfgp_ajax_nonce"); ?>'
        };
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                $btn.prop('disabled', false).html('🧪 Тест текущей вкладки');
                
                if (response.success) {
                    // v3.4.8: Show popup modal with YML preview (restored old functionality!)
                    if (response.data.yml && typeof showPreviewModal === 'function') {
                        showPreviewModal(response.data.yml);
                        $result.html('<span style="color: #46b450;">✅ Preview открыт в popup!</span>');
                    } else {
                        // Fallback to console if no YML
                        console.log('🧪 Test Result:', response.data);
                        $result.html('<span style="color: #46b450;">✅ Результат в консоли (YML недоступен)</span>');
                    }
                } else {
                    $result.html('<span style="color: #dc3232;">❌ Ошибка: ' + (response.data || 'Неизвестная ошибка') + '</span>');
                }
                
                // Автоскрытие через 5 секунд
                setTimeout(function() {
                    $result.fadeOut();
                }, 5000);
            },
            error: function(xhr, status, error) {
                $btn.prop('disabled', false).html('🧪 Тест текущей вкладки');
                $result.html('<span style="color: #dc3232;">❌ AJAX ошибка: ' + error + '</span>');
                console.error('Test failed:', error);
            }
        });
    });
    
    setTimeout(function() {
        initializeFieldSelectors();
        // v4.5.2: updateTabBadges() removed - called from updateFieldStatus() instead
        // This prevents race condition where badges reset to 0 before selectors initialize
        console.log('✅ Field Mapping V3.1 fully initialized');
    }, 500);
});
</script>

