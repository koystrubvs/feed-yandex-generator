<?php
/**
 * Yandex Feed Generator Pro - XML Serialization Service
 * 
 * Координирует сериализацию сущностей в XML: build_doctor_entity, build_clinic_entity, build_service_entity
 * 
 * v4.18.20: Перенесены методы построения сущностей из Feed_Generator_V2 для улучшения архитектуры
 * 
 * @package YandexFeedGeneratorPro
 * @since 4.18.17
 * @updated 4.18.20
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class YFGP_Xml_Serialization_Service {
    use YFGP_Feed_Generator_Shared_Trait;

    /**
     * @var callable Callback для build_doctor_entity (legacy, для обратной совместимости)
     */
    private $build_doctor_entity_callback;

    /**
     * @var callable Callback для build_clinic_entity
     */
    private $build_clinic_entity_callback;

    /**
     * @var callable Callback для build_service_entity
     */
    private $build_service_entity_callback;

    /**
     * @var YFGP_Yml_Stream_Writer Сервис записи XML
     */
    private YFGP_Yml_Stream_Writer $xml_writer;

    /**
     * @var YFGP_Entity_Manager|null Entity Manager для определения типов сущностей
     */
    private $entity_manager = null;

    /**
     * @var array<string, mixed> Настройки плагина
     */
    private array $settings = array();

    // v4.18.20: Callbacks для вспомогательных методов удалены - методы работают напрямую

    /**
     * Конструктор
     * 
     * @param callable|array $build_doctor_entity_callback Callback для построения doctor entity (legacy)
     * @param callable|array $build_clinic_entity_callback Callback для построения clinic entity
     * @param callable|array $build_service_entity_callback Callback для построения service entity
     * @param YFGP_Yml_Stream_Writer $xml_writer Сервис записи XML
     * @param array<string, mixed> $settings Настройки плагина
     * @param callable|null $extract_v3_fields_callback DEPRECATED - не используется (v4.18.20+)
     * @param callable|null $extract_repeater_block_callback DEPRECATED - не используется (v4.18.20+)
     * @param callable|null $get_repeater_field_key_callback DEPRECATED - не используется (v4.18.20+)
     * @param callable|null $get_v3_value_from_unified_callback DEPRECATED - не используется (v4.18.20+)
     * @param callable|null $get_reviews_source_config_callback DEPRECATED - не используется (v4.18.20+)
     */
    public function __construct(
        $build_doctor_entity_callback,
        $build_clinic_entity_callback,
        $build_service_entity_callback,
        YFGP_Yml_Stream_Writer $xml_writer,
        array $settings = array(),
        $extract_v3_fields_callback = null,
        $extract_repeater_block_callback = null,
        $get_repeater_field_key_callback = null,
        $get_v3_value_from_unified_callback = null,
        $get_reviews_source_config_callback = null
    ) {
        // v4.18.20: Callbacks могут быть null (устанавливаются позже через setCallbacks)
        if ($build_doctor_entity_callback !== null && !is_callable($build_doctor_entity_callback)) {
            throw new \InvalidArgumentException('$build_doctor_entity_callback must be callable or null');
        }
        if ($build_clinic_entity_callback !== null && !is_callable($build_clinic_entity_callback)) {
            throw new \InvalidArgumentException('$build_clinic_entity_callback must be callable or null');
        }
        if ($build_service_entity_callback !== null && !is_callable($build_service_entity_callback)) {
            throw new \InvalidArgumentException('$build_service_entity_callback must be callable or null');
        }

        $this->build_doctor_entity_callback = $build_doctor_entity_callback;
        $this->build_clinic_entity_callback = $build_clinic_entity_callback;
        $this->build_service_entity_callback = $build_service_entity_callback;
        $this->xml_writer = $xml_writer;
        $this->settings = $settings;
        $this->entity_manager = YFGP_Entity_Manager::get_instance();
        
        // v4.18.20: Callbacks для вспомогательных методов больше не используются - методы работают напрямую
    }

    /**
     * Установка callbacks для методов из Feed_Generator_V2
     * 
     * v4.18.20: Для поэтапного переноса build_doctor_entity
     * 
     * @param callable|array|null $extract_v3_fields_callback DEPRECATED - не используется (v4.18.20+)
     * @param callable|array|null $extract_repeater_block_callback DEPRECATED - не используется (v4.18.20+)
     * @param callable|array|null $get_repeater_field_key_callback DEPRECATED - не используется (v4.18.20+)
     * @param callable|array|null $get_v3_value_from_unified_callback DEPRECATED - не используется (v4.18.20+)
     * @param callable|array|null $get_reviews_source_config_callback DEPRECATED - не используется (v4.18.20+)
     * @param array<string, mixed> $settings Настройки плагина
     * @return void
     */
    public function setCallbacks(
        $extract_v3_fields_callback = null,
        $extract_repeater_block_callback = null,
        $get_repeater_field_key_callback = null,
        $get_v3_value_from_unified_callback = null,
        $get_reviews_source_config_callback = null,
        array $settings = array()
    ): void {
        // v4.18.20: Callbacks для вспомогательных методов больше не используются - методы работают напрямую
        if (!empty($settings)) {
            $this->settings = $settings;
        }
    }

    /**
     * Построение doctor entity
     * 
     * v4.18.20: Поэтапный перенос из Feed_Generator_V2
     * Этап 1: Базовая структура с зависимостями
     * 
     * @param \WP_Post $post Пост врача
     * @param array<string, mixed> $data Данные поста
     * @param array<string, mixed> $mapping Маппинг полей
     * @return array<string, mixed> Doctor entity
     */
    public function build_doctor_entity(\WP_Post $post, array $data, array $mapping = array()): array {
        // v4.18.20: Поэтапный перенос из Feed_Generator_V2::build_doctor_entity()
        // Если callbacks для вспомогательных методов установлены - используем встроенную реализацию
        // Иначе используем legacy callback для обратной совместимости
        
        // v4.18.20: Всегда используем внутреннюю реализацию (убрана зависимость от callbacks)
        return $this->build_doctor_entity_internal($post, $data, $mapping);
    }

    /**
     * Внутренняя реализация build_doctor_entity (поэтапный перенос)
     * 
     * @param \WP_Post $post Пост врача
     * @param array<string, mixed> $data Данные поста
     * @param array<string, mixed> $mapping Маппинг полей
     * @return array<string, mixed> Doctor entity
     */
    private function build_doctor_entity_internal(\WP_Post $post, array $data, array $mapping = array()): array {
        $params = $data['params'] ?? array();
        
        // @fix: ID должен соответствовать entity_type из маппинга, а не post_type
        $entity_type = $this->get_entity_type_for_post($post->post_type);
        $doctor = array(
            'id' => $entity_type . '_' . $post->ID,
            'name' => $post->post_title,
            'url' => get_permalink($post->ID),
            'internal_id' => $post->ID,
            'description' => $this->sanitize_feed_text($data['description'] ?? get_the_excerpt($post->ID)),
        );
        $doctor['post_id'] = $post->ID;
        $doctor['speciality_terms'] = $this->get_speciality_terms_for_post($post->ID);

        // Добавляем ФИО компоненты если есть
        if (!empty($params['Фамилия'])) $doctor['surname'] = $params['Фамилия'];
        if (!empty($params['Имя'])) $doctor['first_name'] = $params['Имя'];
        if (!empty($params['Отчество'])) $doctor['patronymic'] = $params['Отчество'];
        
        // v4.18.6: FIX - если ФИО не установлены из $params, разбиваем post_title
        if (empty($doctor['surname']) && !empty($post->post_title)) {
            $parts = explode(' ', trim($post->post_title));
            if (!empty($parts[0])) $doctor['surname'] = $parts[0];
            if (!empty($parts[1])) $doctor['first_name'] = $parts[1];
            if (!empty($parts[2])) $doctor['patronymic'] = $parts[2];
        }
        
        // v4.18.20: Этап 3 - extract_v3_fields и merge логика
        // v4.1.0-beta21: Extract V3 fields using V3 API (clean separation!)
        // v4.18.5: Unified API alignment - mapping передается как параметр (не get_option)
        if (empty($mapping)) {
            $mapping = get_option('yfgp_field_mapping_v3', array()); // Fallback для обратной совместимости
        }
        
        // v4.18.6: Сохраняем ФИО перед merge с v3_fields (защита от перезаписи пустыми значениями)
        $saved_fio = array();
        if (!empty($doctor['surname'])) $saved_fio['surname'] = $doctor['surname'];
        if (!empty($doctor['first_name'])) $saved_fio['first_name'] = $doctor['first_name'];
        if (!empty($doctor['patronymic'])) $saved_fio['patronymic'] = $doctor['patronymic'];
        
        // Extract v3_fields через callback или напрямую
        $v3_fields = array();
        // v4.18.20: Извлечение V3 полей через внутренний метод (убрана зависимость от callback)
        $v3_fields = $this->extract_v3_fields_internal($post->ID, $mapping);
        
        // v4.18.6: Удаляем ФИО из v3_fields если они пустые (защита от перезаписи правильных значений)
        $fio_keys = array('surname', 'first_name', 'patronymic');
        foreach ($fio_keys as $fio_key) {
            if (isset($v3_fields[$fio_key]) && (empty($v3_fields[$fio_key]) || trim($v3_fields[$fio_key]) === '')) {
                unset($v3_fields[$fio_key]);
            }
        }
        
        // v4.18.2: Мержим v3_fields ПЕРЕД $doctor, чтобы они перезаписали значения из $data
        $doctor = array_merge($doctor, $v3_fields);
        
        // v4.18.6: Восстанавливаем ФИО если они были установлены из $params (защита от перезаписи пустыми значениями из v3_fields)
        foreach ($saved_fio as $key => $value) {
            if (!empty($value) && trim($value) !== '') {
                $doctor[$key] = $value;
            }
        }
        
        // v4.18.20: Этап 4 - валидация и нормализация
        // v4.18.20: Валидация career_start_date перенесена ниже, после обработки experience_years

        // @fix: featured image ВСЕГДА приоритетнее, чем из маппинга
        if (has_post_thumbnail($post->ID)) {
            $doctor['picture'] = get_the_post_thumbnail_url($post->ID, 'full');
        } else {
            // Только если нет миниатюры — берём из маппинга
            if (!empty($v3_fields['picture'])) {
                $doctor['picture'] = $v3_fields['picture'];
            }
        }

        // v4.10.11: Clean HTML tags from degree field (fix HTML entities issue)
        // Pattern: "Fix at build_entity (extraction), not build_xml (generation)"
        // v4.18.2: Fix strip_tags() error when degree is array
        if (!empty($doctor['degree'])) {
            $degree = $doctor['degree'];
            
            // v4.18.2: Проверка типа данных - если массив, преобразуем в строку
            if (is_array($degree)) {
                $degree = implode(', ', array_filter($degree, function($item) {
                    return !is_array($item) && $item !== null && $item !== '';
                }));
            }
            
            // Только для строк применяем strip_tags
            if (is_string($degree)) {
                // Remove <br> tags (both formats)
                $degree = str_replace('<br>', ' ', $degree);
                $degree = str_replace('<br />', ' ', $degree);
                $degree = str_replace('<BR>', ' ', $degree); // Uppercase variant
                // Strip all remaining HTML tags
                $degree = strip_tags($degree);
                // Clean up multiple spaces
                $degree = preg_replace('/\s+/', ' ', $degree);
                $degree = trim($degree);
            }
            
            $doctor['degree'] = $degree;
        }
        
        // v4.18.20: Этап 5 - repeater blocks (education, job, certificate, reviews)
        // @fix: убрать хардкод ключей repeater - брать из маппинга по паттерну (type . '_repeater_field')
        
        // Extract education block
        $education_key = $this->get_repeater_field_key_internal($mapping, 'education');
        // v4.18.20: Используем внутренний метод вместо callback
        if ($education_key !== null) {
            $education_rows = $this->extract_repeater_block_internal(
                $post->ID,
                $mapping,
                $education_key,
                array(
                    'education_organization' => 'organization',
                    'education_finish_year' => 'finish_year',
                    'education_type' => 'type',
                    'education_specialization' => 'specialization',
                )
            );
            if (!empty($education_rows)) {
                $doctor['education'] = $education_rows;
            }
        }
        
        // Extract job block
        // v4.18.20: Используем внутренний метод вместо callback
        $job_key = $this->get_repeater_field_key_internal($mapping, 'job');
        if ($job_key !== null) {
            $job_rows = $this->extract_repeater_block_internal(
                $post->ID,
                $mapping,
                $job_key,
                array(
                    'job_organization' => 'organization',
                    'job_period_years' => 'period_years',
                    'job_position' => 'position',
                )
            );
            if (!empty($job_rows)) {
                $doctor['job'] = $job_rows;
            }
        }
        
        // Extract certificate block
        // v4.18.20: Используем внутренний метод вместо callback
        $certificate_key = $this->get_repeater_field_key_internal($mapping, 'certificate');
        if ($certificate_key !== null) {
            $certificate_rows = $this->extract_repeater_block_internal(
                $post->ID,
                $mapping,
                $certificate_key,
                array(
                    'certificate_organization' => 'organization',
                    'certificate_finish_year' => 'finish_year',
                    'certificate_name' => 'name',
                )
            );
            if (!empty($certificate_rows)) {
                $doctor['certificate'] = $certificate_rows;
            }
        }
        
        // Extract reviews block (более сложная логика с автогенерацией маппинга)
        // v4.18.20: Используем внутренний метод вместо callback
        $reviews_block_key = $this->get_reviews_source_config_internal($this->settings, $mapping);
        
        if ($reviews_block_key !== null) {
            $reviews_rows = $this->extract_repeater_block_internal(
                $post->ID,
                $mapping,
                $reviews_block_key,
                array(
                    'reviews_date' => 'date',
                    'reviews_checked' => 'checked',
                    'reviews_used_in_rating' => 'used_in_rating',
                    'reviews_author' => 'author',
                    'reviews_author_id' => 'author_id',
                    'reviews_author_picture' => 'author_picture',
                    'reviews_url' => 'url',
                    'reviews_comment' => 'comment',
                    'reviews_grade' => 'grade',
                    'reviews_positive' => 'positive',
                    'reviews_negative' => 'negative',
                    'reviews_response' => 'response',
                ),
                array('reviews_total_count')
            );
            if (!empty($reviews_rows)) {
                $doctor['reviews'] = $reviews_rows;
            }
        }
        
        // v4.18.20: Этап 6 - финальная нормализация
        
        // v4.18.3: Extract appointment flags через unified mapper + conditional logic
        // v4.18.20: Используем внутренний метод вместо callback
        // v4.18.21: Универсализация - используем общую функцию для boolean полей с conditional logic
        $adult_appointment_value = $this->extract_boolean_field_with_conditional_logic($post->ID, $mapping['adult_appointment'] ?? null);
        if ($adult_appointment_value !== null) {
            $doctor['adult_appointment'] = $adult_appointment_value;
        }
        
        $children_appointment_value = $this->extract_boolean_field_with_conditional_logic($post->ID, $mapping['children_appointment'] ?? null);
        if ($children_appointment_value !== null) {
            $doctor['children_appointment'] = $children_appointment_value;
        }

        // Extract reviews_total_count
        // v4.18.20: Используем внутренний метод вместо callback
        $reviews_total_count = null;
        if (!empty($mapping['reviews_total_count'])) {
            $raw_reviews_count = $this->get_v3_value_from_unified_internal($post->ID, $mapping['reviews_total_count']);
            if (is_array($raw_reviews_count)) {
                $reviews_total_count = count($raw_reviews_count);
            } elseif (is_numeric($raw_reviews_count)) {
                $reviews_total_count = (int) $raw_reviews_count;
            } elseif (is_string($raw_reviews_count)) {
                $trimmed = trim($raw_reviews_count);
                if ($trimmed !== '' && is_numeric($trimmed)) {
                    $reviews_total_count = (int) $trimmed;
                }
            }
        }

        // Extract boolean fields (house_call, telemed) via unified mapper
        // Extract boolean fields (house_call, telemed) via universal function (supports conditional logic)
        $house_call_value = $this->extract_boolean_field_with_conditional_logic($post->ID, $mapping['house_call'] ?? null);
        if ($house_call_value !== null) {
            $doctor['house_call'] = $house_call_value;
        }

        $telemed_value = $this->extract_boolean_field_with_conditional_logic($post->ID, $mapping['telemed'] ?? null);
        if ($telemed_value !== null) {
            $doctor['telemed'] = $telemed_value;
        }

        // v4.18.3: Фильтруем отзывы без grade (согласно спецификации Яндекса)
        if (!empty($doctor['reviews']) && is_array($doctor['reviews'])) {
            $reviews_with_grade = array_filter($doctor['reviews'], function($review) {
                return !empty($review['grade']);
            });
            
            if ($reviews_total_count === null) {
                $reviews_total_count = count($reviews_with_grade);
            }
            
            // Обновляем массив reviews, оставляя только отзывы с grade
            $doctor['reviews'] = array_values($reviews_with_grade);
            
            // Нормализация полей отзывов
            foreach ($doctor['reviews'] as &$review) {
                if (!empty($review['comment'])) {
                    $review['comment'] = strip_tags($review['comment']);
                }
                if (!empty($review['positive'])) {
                    $review['positive'] = strip_tags($review['positive']);
                }
                if (!empty($review['negative'])) {
                    $review['negative'] = strip_tags($review['negative']);
                }
                if (!empty($review['response'])) {
                    $review['response'] = strip_tags($review['response']);
                }
                $review['checked'] = $this->normalize_boolean_string($review['checked'] ?? true, true);
                $review['used_in_rating'] = $this->normalize_boolean_string($review['used_in_rating'] ?? true, true);
            }
            unset($review);
        }
        
        if ($reviews_total_count !== null) {
            $doctor['reviews_total_count'] = $reviews_total_count;
        } elseif (empty($doctor['reviews_total_count']) && !empty($doctor['reviews']) && is_array($doctor['reviews'])) {
            // v4.18.3: Fallback - считаем из массива reviews (уже отфильтрованных по grade)
            $doctor['reviews_total_count'] = count($doctor['reviews']);
        }

        // Добавляем опыт
        if (!empty($params['Годы опыта'])) {
            $doctor['experience_years'] = $params['Годы опыта'];
        }
        
        // v4.18.22: Сохраняем исходное значение experience_years перед конвертацией (для использования в career_start_date)
        $original_experience_value = null;
        if (!empty($doctor['experience_years'])) {
            $original_experience_value = $doctor['experience_years'];
        }
        
        // v4.18.20: Валидация и нормализация experience_years
        if (!empty($doctor['experience_years'])) {
            $exp_years = $doctor['experience_years'];
            
            // Если это дата (формат YYYY-MM-DD или YYYY-MM-DD HH:MM:SS) - преобразуем в число лет
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $exp_years)) {
                $exp_timestamp = strtotime($exp_years);
                if ($exp_timestamp !== false) {
                    // v4.18.22: Сохраняем исходную дату для career_start_date (если оно пусто)
                    if (empty($doctor['career_start_date'])) {
                        $doctor['career_start_date'] = date('Y-m-d', $exp_timestamp);
                        error_log('YFGP v4.18.22: Saved original date from experience_years to career_start_date: ' . $doctor['career_start_date']);
                    }
                    
                    $current_year = (int) date('Y');
                    $exp_year = (int) date('Y', $exp_timestamp);
                    $calculated_years = $current_year - $exp_year;
                    if ($calculated_years > 0 && $calculated_years <= 100) {
                        $doctor['experience_years'] = (string) $calculated_years;
                        error_log('YFGP v4.18.20: Converted experience_years from date to years: ' . $exp_years . ' -> ' . $calculated_years);
                    } else {
                        // Если расчет дал нереалистичное значение - очищаем
                        unset($doctor['experience_years']);
                        error_log('YFGP v4.18.20: Warning - Invalid experience_years date (unrealistic): ' . $exp_years);
                    }
                } else {
                    // Невалидная дата - очищаем
                    unset($doctor['experience_years']);
                    error_log('YFGP v4.18.20: Warning - Invalid experience_years date format: ' . $exp_years);
                }
            } elseif (!is_numeric($exp_years)) {
                // Если не дата и не число - пытаемся извлечь число
                if (preg_match('/(\d+)/', $exp_years, $matches)) {
                    $doctor['experience_years'] = $matches[1];
                    error_log('YFGP v4.18.20: Extracted numeric value from experience_years: ' . $exp_years . ' -> ' . $matches[1]);
                } else {
                    // Не удалось извлечь число - очищаем
                    unset($doctor['experience_years']);
                    error_log('YFGP v4.18.20: Warning - Invalid experience_years value (not date, not number): ' . $exp_years);
                }
            } else {
                // Это число - нормализуем (убираем дробную часть если есть)
                $doctor['experience_years'] = (string) (int) $exp_years;
            }
        }
        
        // v4.18.20: Fallback для experience_years - рассчитываем из career_start_date если experience_years пусто
        if (empty($doctor['experience_years']) && !empty($doctor['career_start_date'])) {
            $career_date = $this->validate_and_parse_date($doctor['career_start_date']);
            if ($career_date !== null) {
                $current_year = (int) date('Y');
                $career_year = (int) date('Y', $career_date);
                $experience_years = $current_year - $career_year;
                // v4.18.21: Разрешаем 0 и отрицательные значения (для дат в будущем выводим 0)
                if ($experience_years >= 0) {
                    $doctor['experience_years'] = (string) $experience_years;
                    error_log('YFGP v4.18.21: Calculated experience_years from career_start_date: ' . $experience_years . ' years');
                } else {
                    // Дата в будущем - выводим 0
                    $doctor['experience_years'] = '0';
                    error_log('YFGP v4.18.21: Warning - career_start_date is in the future, setting experience_years to 0');
                }
            }
        }
        
        // v4.18.21: Финальный fallback - если experience_years все еще пустой, устанавливаем '0'
        if (!isset($doctor['experience_years']) || $doctor['experience_years'] === '' || $doctor['experience_years'] === null) {
            $doctor['experience_years'] = '0';
            error_log('YFGP v4.18.21: Warning - experience_years is empty, setting to 0 for doctor ' . ($doctor['id'] ?? 'unknown'));
        }
        
        // v4.18.20: Нормализация career_start_date - должен быть строго YYYY-MM-DD
        if (!empty($doctor['career_start_date'])) {
            $validated_date = $this->validate_and_parse_date($doctor['career_start_date']);
            if ($validated_date !== null) {
                // Нормализуем формат до YYYY-MM-DD
                $doctor['career_start_date'] = date('Y-m-d', $validated_date);
            } else {
                // Если формат неверный - предупреждение в лог и очистка поля
                error_log('YFGP v4.18.20: Warning - Invalid career_start_date format: ' . $doctor['career_start_date'] . ' (expected YYYY-MM-DD)');
                unset($doctor['career_start_date']);
            }
        }
        
        // v4.18.22: Взаимо-вычисление: career_start_date из experience_years (если career_start_date пусто, а experience_years заполнено)
        if (empty($doctor['career_start_date']) && !empty($doctor['experience_years']) && $doctor['experience_years'] !== '0') {
            // Преобразуем experience_years в число
            $exp_years = (int) $doctor['experience_years'];
            if ($exp_years > 0) {
                $current_year = (int) date('Y');
                $career_year = $current_year - $exp_years;
                
                // Проверяем что год разумен (не раньше 1900 и не в будущем)
                if ($career_year >= 1900 && $career_year <= $current_year) {
                    // Формат: YYYY-01-01 (точный день неизвестен, используем 1 января)
                    $calculated_date = sprintf('%04d-01-01', $career_year);
                    // Валидируем вычисленную дату
                    $validated_date = $this->validate_and_parse_date($calculated_date);
                    if ($validated_date !== null) {
                        $doctor['career_start_date'] = $calculated_date;
                        error_log('YFGP v4.18.22: Calculated career_start_date from experience_years: ' . $exp_years . ' years -> ' . $doctor['career_start_date']);
                    } else {
                        error_log('YFGP v4.18.22: Warning - Calculated career_start_date failed validation: ' . $calculated_date);
                    }
                } else {
                    error_log('YFGP v4.18.22: Warning - Calculated career_year is out of range: ' . $career_year . ' (experience_years=' . $exp_years . ')');
                }
            }
        }
        
        // v4.1.0: FIX surname - если surname совпадает с полным именем, извлечь только фамилию
        if (!empty($doctor['surname']) && !empty($doctor['name'])) {
            // Если surname содержит пробелы и начинается так же как name, взять только первое слово
            if (strpos($doctor['surname'], ' ') !== false && stripos($doctor['name'], $doctor['surname']) === 0) {
                $parts = explode(' ', trim($doctor['surname']));
                $doctor['surname'] = $parts[0]; // Только первое слово (фамилия)
            }
        }
        
        return $doctor;
    }

    /**
     * Получить entity_type для post_type
     * 
     * @param string $post_type Post type slug
     * @return string Entity type: 'doctor', 'clinic', or 'service'
     */
    private function get_entity_type_for_post(string $post_type): string {
        if ($this->entity_manager === null) {
            $this->entity_manager = YFGP_Entity_Manager::get_instance();
        }
        return $this->entity_manager->get_entity_type($post_type, $this->settings);
    }

    /**
     * Валидация и парсинг даты в формате YYYY-MM-DD
     * 
     * @param string $date_string Строка с датой
     * @return int|null Unix timestamp или null если формат неверный
     */
    private function validate_and_parse_date(string $date_string): ?int {
        if (empty($date_string)) {
            return null;
        }
        
        // Пробуем стандартный формат YYYY-MM-DD
        $timestamp = strtotime($date_string);
        if ($timestamp === false) {
            return null;
        }
        
        // Проверяем что дата соответствует формату YYYY-MM-DD
        $parsed_date = date('Y-m-d', $timestamp);
        if ($parsed_date !== $date_string) {
            return null;
        }
        
        return $timestamp;
    }

    /**
     * Получить ключ repeater поля из маппинга по паттерну
     * 
     * v4.18.20: Перенесено из Feed_Generator_V2, убрана зависимость от callback
     * 
     * @param array<string, mixed> $mapping Маппинг полей
     * @param string $type Тип repeater (education, job, certificate, reviews)
     * @return string|null Ключ поля или null если не найден
     */
    private function get_repeater_field_key_internal(array $mapping, string $type): ?string {
        // Формируем ключ по паттерну плагина: $type . '_repeater_field'
        $key = $type . '_repeater_field';
        
        // Проверяем наличие ключа в маппинге
        if (empty($mapping[$key]) || !is_array($mapping[$key])) {
            return null;
        }
        
        $config = $mapping[$key];
        
        // Проверяем что это repeater поле
        if (empty($config['source_type'])) {
            return null;
        }
        
        $source_type = $config['source_type'];
        if (!in_array($source_type, array('repeater_acf', 'repeater_jetengine'), true)) {
            return null;
        }
        
        // Всё ОК → возвращаем ключ
        return $key;
    }

    /**
     * Универсальная функция для извлечения boolean поля с поддержкой conditional logic
     * 
     * v4.18.21: Универсализация обработки boolean полей с conditional logic
     * 
     * @param int $post_id ID поста
     * @param array<string, mixed>|null $field_mapping Маппинг поля (может быть null)
     * @return string|null 'true' если поле должно быть true, null если не установлено
     */
    private function extract_boolean_field_with_conditional_logic(int $post_id, ?array $field_mapping): ?string {
        if (empty($field_mapping)) {
            return null;
        }
        
        // Получаем значение через unified mapper
        $value = $this->get_v3_value_from_unified_internal($post_id, $field_mapping);
        
        // v4.18.21: Для conditional logic проверяем, что значение не пустое (условие выполнилось)
        // unified mapper обрабатывает conditional logic и возвращает значение только если условие выполнилось
        if (!empty($field_mapping['conditional_logic'])) {
            // Если conditional logic включен и значение не пустое, значит условие выполнилось → 'true'
            if ($value !== null && $value !== '' && $value !== false) {
                return 'true';
            }
            // Если conditional logic включен, но значение пустое → условие не выполнилось, не устанавливаем поле
            return null;
        }
        
        // Для обычных полей (без conditional logic) используем стандартную проверку truthy
        if ($this->is_truthy_mapping_value($value)) {
            return 'true';
        }
        
        return null;
    }

    /**
     * Проверка что значение является "truthy" для маппинга
     * 
     * @param mixed $value Значение для проверки
     * @return bool true если значение truthy
     */
    private function is_truthy_mapping_value($value): bool {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            $lower = strtolower(trim($value));
            return in_array($lower, array('1', 'true', 'yes', 'on', 'да'), true);
        }
        if (is_numeric($value)) {
            return (int) $value > 0;
        }
        return !empty($value);
    }

    /**
     * Нормализация boolean значения в строку
     * 
     * @param mixed $value Значение для нормализации
     * @param bool $default Значение по умолчанию
     * @return string 'true' или 'false'
     */
    private function normalize_boolean_string($value, bool $default = false): string {
        if ($value === null) {
            return $default ? 'true' : 'false';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value)) {
            $lower = strtolower(trim($value));
            if (in_array($lower, array('1', 'true', 'yes', 'on', 'да'), true)) {
                return 'true';
            }
            if (in_array($lower, array('0', 'false', 'no', 'off', 'нет'), true)) {
                return 'false';
            }
        }

        if (is_numeric($value)) {
            return ((int) $value > 0) ? 'true' : 'false';
        }

        return $default ? 'true' : 'false';
    }

    /**
     * Построение clinic entity
     * 
     * v4.18.20: Перенесено из Feed_Generator_V2
     * 
     * @param array<string, mixed> $clinic_data Данные клиники
     * @return array<string, mixed> Clinic entity
     */
    public function build_clinic_entity(array $clinic_data): array {
        // Если callback установлен - используем его для обратной совместимости
        if ($this->build_clinic_entity_callback !== null) {
        return call_user_func($this->build_clinic_entity_callback, $clinic_data);
        }
        
        // Иначе используем встроенную реализацию
        return $this->build_clinic_entity_internal($clinic_data);
    }

    /**
     * Внутренняя реализация build_clinic_entity
     * 
     * @param array<string, mixed> $clinic_data Данные клиники
     * @return array<string, mixed> Clinic entity
     */
    private function build_clinic_entity_internal(array $clinic_data): array {
        // v4.2.0: FIXED - Use clinic permalink and clean internal_id
        $clinic_id = $clinic_data['id'] ?? 'default';
        $clinic_post_id = $clinic_data['post_id'] ?? null;
        
        // @fix: убрать хардкод 'clinic_' — использовать entity_type
        $clinic_post_type = $clinic_post_id 
            ? get_post_type($clinic_post_id) 
            : ($clinic_data['post_type'] ?? '');
        
        $internal_id = !empty($clinic_data['internal_id']) 
            ? $clinic_data['internal_id'] 
            : (
                !empty($clinic_post_type)
                    ? preg_replace('/^' . preg_quote($this->get_entity_type_for_post($clinic_post_type) . '_', '/') . '/', '', $clinic_id)
                    : $clinic_id // Если post_type нет - оставляем clinic_id как есть
            );
        
        // URL клиники = permalink записи (НЕ URL сайта!)
        $clinic_url = $clinic_post_id 
            ? get_permalink($clinic_post_id) 
            : ($this->settings['company_url'] ?? get_site_url());
        
        // Base fields
        $clinic = array(
            'id' => $clinic_id,
            'name' => $clinic_data['name'] ?? 'Основная клиника',
            'url' => $clinic_url,
            'city' => $clinic_data['city'] ?? $this->settings['city'] ?? '',
            'internal_id' => $internal_id,
        );
        
        // v4.1.6: Address
        if (!empty($clinic_data['address'])) {
            $clinic['address'] = is_array($clinic_data['address']) 
                ? implode(', ', $clinic_data['address']) 
                : $clinic_data['address'];
        }
        
        // v4.1.6: Phone (validation for array/string)
        if (!empty($clinic_data['phone'])) {
            $clinic['phone'] = is_array($clinic_data['phone']) 
                ? implode(', ', $clinic_data['phone']) 
                : $clinic_data['phone'];
        }
        
        // v4.1.6: Email
        if (!empty($clinic_data['email'])) {
            $clinic['email'] = $clinic_data['email'];
        }
        
        // @fix: featured image ВСЕГДА приоритетнее, чем из маппинга
        if (!empty($clinic_post_id) && has_post_thumbnail($clinic_post_id)) {
            $clinic['picture'] = get_the_post_thumbnail_url($clinic_post_id, 'full');
        } else {
            // Только если нет миниатюры — берём из маппинга
            if (!empty($clinic_data['picture'])) {
                $clinic['picture'] = $clinic_data['picture'];
            }
        }
        if (empty($clinic['picture'])) {
            if (!empty($this->settings['shop_picture'])) {
                $clinic['picture'] = $this->settings['shop_picture'];
            } elseif (function_exists('get_site_icon_url')) {
                $site_icon = get_site_icon_url();
                if (!empty($site_icon)) {
                    $clinic['picture'] = $site_icon;
                }
            }
        }
        
        // v4.1.6: Company ID
        if (!empty($clinic_data['company_id'])) {
            $clinic['company_id'] = $clinic_data['company_id'];
        }
        
        return $clinic;
    }

    /**
     * Построение service entity
     * 
     * v4.18.20: Перенесено из Feed_Generator_V2
     * 
     * @param array<string, mixed> $service_data Данные услуги
     * @return array<string, mixed> Service entity
     */
    public function build_service_entity(array $service_data): array {
        // Если callback установлен - используем его для обратной совместимости
        if ($this->build_service_entity_callback !== null) {
        return call_user_func($this->build_service_entity_callback, $service_data);
        }
        
        // Иначе используем встроенную реализацию
        return $this->build_service_entity_internal($service_data);
    }

    /**
     * Внутренняя реализация build_service_entity
     * 
     * @param array<string, mixed> $service_data Данные услуги
     * @return array<string, mixed> Service entity
     */
    private function build_service_entity_internal(array $service_data): array {
        // v4.3.1: Use service post_id for internal_id (NO URL per Yandex spec!)
        $service_post_id = $service_data['post_id'] ?? null;
        
        // v4.3.1: Internal ID - clean without 'service_' prefix
        $internal_id_clean = !empty($service_data['internal_id']) 
            ? str_replace('service_', '', $service_data['internal_id']) 
            : ($service_post_id ?? 'default');
        
        $service = array(
            'id' => $service_data['id'] ?? 'service_default',
            'name' => $service_data['name'],
            'internal_id' => $internal_id_clean, // v4.3.1: Clean ID
            'description' => '', // Will be set below
        );
        
        // v4.20.0: Description sanitization (remove script/style + HTML)
        if (!empty($service_data['description'])) {
            $service['description'] = $this->sanitize_feed_text($service_data['description']);
        }
        
        // v4.3.0: Gov ID (new field for services)
        if (!empty($service_data['gov_id'])) {
            $service['gov_id'] = $service_data['gov_id'];
        }
        
        // v4.3.0: Picture with URL validation (like clinics)
        if (!empty($service_data['picture'])) {
            $service['picture'] = $service_data['picture'];
        }
        
        // v4.5.0: Price fields (from CPT "prices" via relationship)
        if (!empty($service_data['price'])) {
            $service['price'] = $service_data['price'];
        }
        
        if (!empty($service_data['price_discount'])) {
            $service['price_discount'] = $service_data['price_discount'];
        }
        
        if (!empty($service_data['currency'])) {
            $service['currency'] = $service_data['currency'];
        }
        
        // v4.18.21: Extract discount_name and free_appointment_condition from prices CPT if not already extracted
        // This ensures discount_name and free_appointment_condition are available in offers
        if (!empty($service_post_id)) {
            $mapping = get_option('yfgp_field_mapping_v3', array());
            if (!empty($mapping) && is_array($mapping)) {
                // v4.18.21: Use get_instance() - YFGP_Field_Mapper_Unified is Singleton
                if (!class_exists('YFGP_Field_Mapper_Unified')) {
                    require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
                }
                $mapper_unified = YFGP_Field_Mapper_Unified::get_instance();
                if (!empty($mapping['prices_price_source_field'])) {
                    $price_ids = $this->extract_related_posts_unified($service_post_id, $mapping['prices_price_source_field'], $mapper_unified);
                    if (!empty($price_ids)) {
                        $price_details = $this->extract_price_details_for_service_internal($price_ids, $mapping, $mapper_unified);
                        if (!empty($price_details)) {
                            // Merge price details (price, price_discount, discount_name, free_appointment_condition)
                            // v4.18.21: Merge with priority - only overwrite if new value is not empty
                            foreach ($price_details as $key => $value) {
                                if (!empty($value)) {
                                    $service[$key] = $value;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // v4.18.21: Also copy discount fields from service_data if they exist (from extract_service_details)
        if (!empty($service_data['discount_name'])) {
            $service['discount_name'] = $service_data['discount_name'];
        }
        if (!empty($service_data['free_appointment_condition'])) {
            $service['free_appointment_condition'] = $this->normalize_free_appointment_text(
                $service_data['free_appointment_condition'],
                $service_data['discount_name'] ?? ($service['discount_name'] ?? null)
            );
        }
        
        if (!empty($service['free_appointment_condition'])) {
            $service['free_appointment_condition'] = $this->normalize_free_appointment_text(
                $service['free_appointment_condition'],
                $service['discount_name'] ?? null
            );
        }
        
        return $service;
    }
    
    /**
     * Extract price-related data for a service using related price posts.
     * 
     * v4.18.21: Перенесено из Feed_Generator_V2 для извлечения discount_name и free_appointment_condition
     * 
     * @param array<int> $price_ids Array of price post IDs
     * @param array<string, mixed> $mapping Field mapping configuration
     * @param YFGP_Field_Mapper_Unified $mapper_unified Unified mapper instance
     * @return array<string, mixed> Price details (price, currency, price_discount, discount_name, free_appointment_condition)
     */
    private function extract_price_details_for_service_internal(array $price_ids, array $mapping, $mapper_unified): array {
        if (empty($price_ids) || empty($mapping['prices_base_price'])) {
            return array();
        }
        $candidates = array();
        foreach ($price_ids as $price_id) {
            $raw_price = $this->extract_field_value_unified($price_id, $mapping['prices_base_price'], $mapper_unified, null);
            $normalized = $this->normalize_price_value($raw_price);
            if ($normalized === null) {
                continue;
            }
            $candidates[] = array('post_id' => $price_id, 'value' => $normalized);
        }
        if (empty($candidates)) {
            return array();
        }
        usort($candidates, function($a, $b) {
            return $a['value'] <=> $b['value'];
        });
        $selected = $candidates[0];
        $price_post_id = $selected['post_id'];
        $details = array(
            'price' => $selected['value'],
        );
        // v4.18.38: No hardcode - currency must be set in settings (Yandex supports only RUR/RUB)
        $currency = $this->settings['default_currency'] ?? '';
        if (empty($currency)) {
            error_log('YFGP v4.18.38: Currency not set in settings. Please configure default_currency in plugin settings.');
            $currency = 'RUR'; // Fallback only for backward compatibility
        }
        if (!empty($mapping['prices_currency'])) {
            $currency_value = $this->extract_field_value_unified($price_post_id, $mapping['prices_currency'], $mapper_unified, $currency);
            if (!empty($currency_value)) {
                $currency = is_array($currency_value) ? reset($currency_value) : $currency_value;
            }
        }
        $details['currency'] = $currency;
        if (!empty($mapping['prices_discount'])) {
            // v4.18.21: Extract price_discount - независимо от discount_name (критично!)
            try {
                $discount_value = $this->extract_field_value_unified($price_post_id, $mapping['prices_discount'], $mapper_unified, null);
                $normalized_discount = $this->normalize_price_value($discount_value);
                if ($normalized_discount !== null && $normalized_discount >= 0) {
                    $details['price_discount'] = $normalized_discount;
                }
            } catch (Exception $e) {
                error_log('YFGP v4.18.21: Error extracting price_discount for price_post_id ' . $price_post_id . ': ' . $e->getMessage());
            }
        }
        // v4.18.21: Extract discount_name from prices_discount_name mapping
        // v4.18.21: Независимо от price_discount - даже если discount_name не извлекается, price_discount должен выводиться
        if (!empty($mapping['prices_discount_name'])) {
            try {
                $discount_name_value = $this->extract_field_value_unified($price_post_id, $mapping['prices_discount_name'], $mapper_unified, null);
                if (!empty($discount_name_value)) {
                    $details['discount_name'] = is_array($discount_name_value) ? reset($discount_name_value) : $discount_name_value;
                    error_log('YFGP v4.18.21: Extracted discount_name from price_post_id ' . $price_post_id . ': ' . $details['discount_name']);
                } else {
                    error_log('YFGP v4.18.21: Warning - discount_name_value is empty for price_post_id ' . $price_post_id . ' (but price_discount will still be output if exists)');
                }
            } catch (Exception $e) {
                error_log('YFGP v4.18.21: Error extracting discount_name for price_post_id ' . $price_post_id . ': ' . $e->getMessage() . ' (but price_discount will still be output if exists)');
            }
        }
        // v4.18.21: Extract free_appointment_condition from prices_free_appointment mapping
        if (!empty($mapping['prices_free_appointment'])) {
            $free_appointment_value = $this->extract_field_value_unified($price_post_id, $mapping['prices_free_appointment'], $mapper_unified, null);
            if (!empty($free_appointment_value)) {
                $raw_free_appointment = is_array($free_appointment_value) ? reset($free_appointment_value) : $free_appointment_value;
                $details['free_appointment_condition'] = $this->normalize_free_appointment_text(
                    $raw_free_appointment,
                    $details['discount_name'] ?? null
                );
            }
        }
        return $details;
    }
    
    /**
     * Extract field value using Unified mapper
     * 
     * v4.18.21: Перенесено из Feed_Generator_V2
     * 
     * @param int $post_id Post ID
     * @param array<string, mixed> $field_config Field config from V3 mapping
     * @param YFGP_Field_Mapper_Unified $mapper_unified Unified mapper instance
     * @param mixed $fallback_value Fallback value if field is empty
     * @return mixed Extracted value
     */
    private function extract_field_value_unified(int $post_id, array $field_config, $mapper_unified, $fallback_value = '') {
        try {
            $value = $mapper_unified->getFieldValue($post_id, $field_config, true); // skip_cache for feed generation
            if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                return $fallback_value;
            }
            return $value;
        } catch (\Exception $e) {
            error_log('YFGP v4.18.21: Error extracting field value: ' . $e->getMessage());
            return $fallback_value;
        }
    }
    
    /**
     * Extract related posts using Unified mapper
     * 
     * v4.18.21: Перенесено из Feed_Generator_V2
     * 
     * @param int $post_id Post ID
     * @param array<string, mixed> $field_config Relationship field config
     * @param YFGP_Field_Mapper_Unified $mapper_unified Unified mapper instance
     * @return array<int> Array of related post IDs
     */
    private function extract_related_posts_unified(int $post_id, array $field_config, $mapper_unified): array {
        try {
            $value = $mapper_unified->getFieldValue($post_id, $field_config, true); // skip_cache for feed generation
            
            if (empty($value)) {
                return array();
            }
            
            // Convert to array of IDs
            if (is_numeric($value)) {
                return array((int) $value);
            }
            
            if (is_array($value)) {
                $ids = array();
                foreach ($value as $item) {
                    if (is_numeric($item)) {
                        $ids[] = (int) $item;
                    } elseif (is_object($item) && isset($item->ID)) {
                        $ids[] = (int) $item->ID;
                    } elseif (is_array($item) && isset($item['ID'])) {
                        $ids[] = (int) $item['ID'];
                    }
                }
                return $ids;
            }
            
            return array();
        } catch (\Exception $e) {
            error_log('YFGP v4.18.21: Error extracting related posts: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Normalize price value to integer or null.
     * 
     * v4.18.21: Перенесено из Feed_Generator_V2
     * 
     * @param mixed $value Price value
     * @return int|null Normalized price or null
     */
    private function normalize_price_value($value): ?int {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        if (is_string($value)) {
            $clean = preg_replace('/[^\d]/', '', $value);
            if ($clean === '') {
                return null;
            }
            return (int) $clean;
        }
        return null;
    }

    /**
     * Сериализация сущностей в XML
     * 
     * @param array<int, array<string, mixed>> $doctors Массив doctors
     * @param array<string, array<string, mixed>> $clinics Массив clinics
     * @param array<string, array<string, mixed>> $services Массив services
     * @param array<int, array<string, mixed>> $offers Массив offers
     * @return string XML строка фида
     */
    public function serialize_to_xml(
        array $doctors,
        array $clinics,
        array $services,
        array $offers
    ): string {
        return $this->xml_writer->build($doctors, $clinics, $services, $offers);
    }

    /**
     * v4.18.20: Вспомогательные методы для работы с данными (перенесены из Feed_Generator_V2)
     * Убрана зависимость от callbacks - методы работают напрямую
     */

    /**
     * Получить V3 значение через unified mapper
     * 
     * v4.18.20: Перенесено из Feed_Generator_V2, убрана зависимость от callback
     * 
     * @param int $post_id Post ID
     * @param array<string, mixed> $field_config Field mapping config
     * @return mixed Field value
     */
    private function get_v3_value_from_unified_internal(int $post_id, array $field_config) {
        if (!class_exists('YFGP_Field_Mapper_Unified')) {
            require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
        }

        $mapper = YFGP_Field_Mapper_Unified::get_instance();
        $value = $mapper->getFieldValue($post_id, $field_config, true); // skip_cache для точности генерации фида

        if (is_array($value)) {
            $flattened = $this->flatten_v3_array_value($value, $field_config, $post_id);
            $flattened = array_filter(array_map(function ($item) {
                if (is_string($item)) {
                    return trim($item);
                }
                return is_scalar($item) ? trim((string) $item) : '';
            }, $flattened), function ($item) {
                return $item !== '';
            });

            return !empty($flattened) ? implode(', ', $flattened) : '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return trim($value);
        }

        return $value;
    }

    /**
     * Применить calculate_type к значению
     * 
     * v4.18.20: Перенесено из Feed_Generator_V2
     * 
     * @param mixed $value Значение
     * @param array<string, mixed> $field_config Field mapping config
     * @return mixed Обработанное значение
     */
    private function apply_calculate_type($value, array $field_config) {
        if ($value === null || $value === '') {
            return $value;
        }

        $calculate_type = $field_config['calculate_type'] ?? '';

        if ($calculate_type === 'date_to_years' && strtotime((string) $value) !== false) {
            $start_date = strtotime((string) $value);
            if ($start_date !== false) {
                $years = floor((time() - $start_date) / (365.25 * 24 * 60 * 60));
                if ($years >= 0) {
                    return $years;
                }
            }
        }

        if ($calculate_type === 'date_as_is' && strtotime((string) $value) !== false) {
            return (string) $value;
        }

        return $value;
    }

    /**
     * Нормализация значения для repeater
     * 
     * v4.18.20: Перенесено из Feed_Generator_V2
     * 
     * @param mixed $value Значение
     * @param array<string, mixed> $field_config Field mapping config
     * @param int $context_post_id Context post ID
     * @return mixed Нормализованное значение
     */
    private function normalize_repeater_value($value, array $field_config, int $context_post_id) {
        if ($value === null) {
            return null;
        }

        $maybe_unserialized = maybe_unserialize($value);
        if ($maybe_unserialized !== $value) {
            return $this->normalize_repeater_value($maybe_unserialized, $field_config, $context_post_id);
        }

        if (is_array($value)) {
            $flattened = $this->flatten_v3_array_value($value, $field_config, $context_post_id);
            $flattened = array_filter(array_map(function ($item) {
                if (is_string($item)) {
                    return trim($item);
                }
                return is_scalar($item) ? trim((string) $item) : '';
            }, $flattened), function ($item) {
                return $item !== '';
            });

            return !empty($flattened) ? implode(', ', $flattened) : null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if ($value instanceof \WP_Post) {
            return (string) $value->ID;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            $string_value = trim((string) $value);
            return $string_value === '' ? null : $string_value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed === '' ? null : $trimmed;
        }

        return null;
    }

    // v4.18.39: v3_is_assoc() and v3_is_truthy() moved to YFGP_Feed_Generator_Shared_Trait

    /**
     * Развернуть массив значений V3
     * 
     * v4.18.20: Перенесено из Feed_Generator_V2
     * 
     * @param array<mixed> $value Массив значений
     * @param array<string, mixed> $field_config Field mapping config
     * @param int $post_id Post ID
     * @return array<string> Развернутый массив
     */
    private function flatten_v3_array_value(array $value, array $field_config, int $post_id): array {
        $result = array();

        if (!$this->v3_is_assoc($value)) {
            foreach ($value as $item) {
                if (is_array($item)) {
                    if (isset($item['name'])) {
                        $result[] = (string) $item['name'];
                        continue;
                    }
                    if (isset($item['label'])) {
                        $result[] = (string) $item['label'];
                        continue;
                    }
                    $result = array_merge($result, $this->flatten_v3_array_value($item, $field_config, $post_id));
                    continue;
                }

                if ($item !== null && $item !== '') {
                    $result[] = (string) $item;
                }
            }

            return $result;
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                if (isset($item['name'])) {
                    $result[] = (string) $item['name'];
                    continue;
                }
                if (isset($item['label'])) {
                    $result[] = (string) $item['label'];
                    continue;
                }
                $result = array_merge($result, $this->flatten_v3_array_value($item, $field_config, $post_id));
                continue;
            }

            if ($this->v3_is_truthy($item)) {
                $label = $this->resolve_v3_option_label($field_config, (string) $key, $post_id);
                $result[] = $label ?? (string) $key;
            } elseif (is_numeric($key) && $item !== null && $item !== '') {
                $result[] = (string) $item;
            }
        }

        return $result;
    }

    /**
     * Разрешить label для опции V3
     * 
     * v4.18.20: Перенесено из Feed_Generator_V2
     * 
     * @param array<string, mixed> $field_config Field mapping config
     * @param string $option_key Ключ опции
     * @param int $post_id Post ID
     * @return string|null Label опции или null
     */
    private function resolve_v3_option_label(array $field_config, string $option_key, int $post_id): ?string {
        $field_name = $field_config['source_field'] ?? '';
        if ($field_name === '') {
            return null;
        }

        if (function_exists('acf_get_field_object')) {
            $field_object = acf_get_field_object($field_name, $post_id);
            if ($field_object && !empty($field_object['choices']) && isset($field_object['choices'][$option_key])) {
                return (string) $field_object['choices'][$option_key];
            }
        }

        if (!class_exists('YFGP_Field_Mapper_Unified')) {
            require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
        }

        $post_type = $field_config['source_cpt'] ?? get_post_type($post_id) ?? 'doctors';
        $mapper = YFGP_Field_Mapper_Unified::get_instance();
        
        // Пробуем получить labels через Unified Mapper
        try {
            $field_info = $mapper->getFieldInfo($field_name, $post_type);
            if (!empty($field_info['choices']) && isset($field_info['choices'][$option_key])) {
                return (string) $field_info['choices'][$option_key];
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки
        }

        return null;
    }

    /**
     * Извлечь V3 поля используя прямой доступ к БД (БЕЗ ACF API для избежания конфликтов)
     * 
     * v4.18.20: Перенесено из Feed_Generator_V2, убрана зависимость от callback
     * 
     * @param int $post_id ID поста
     * @param array<string, mixed> $mapping V3 маппинг (config arrays)
     * @return array<string, mixed> Извлечённые данные
     */
    private function extract_v3_fields_internal(int $post_id, array $mapping): array {
        $data = array();
        // v4.18.2: Поля degree, rank, category обрабатываются через YFGP_Field_Mapper_V2::normalize_specialities() 
        // (тот же метод, что используется для specialization - РАБОТАЕТ!)
        $v3_fields_with_labels = array('degree', 'rank', 'category'); // Поля, которые требуют конвертации slugs в labels
        $v3_fields_sql = array('career_start_date', 'picture', 'reviews_total_count', 'experience_years',
                          'working_hours', 'description'); // Поля, которые обрабатываются через SQL (для производительности)
        
        // Step 1: Normalize degree/rank/category via unified mapper service (same pipeline as specialization)
        if (!class_exists('YFGP_Field_Mapper_Unified')) {
            require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
        }
        $mapper_unified = YFGP_Field_Mapper_Unified::get_instance();
        $post = get_post($post_id);
        
        if (!$post) {
            return $data;
        }
        
        // Обрабатываем поля с labels (degree, rank, category)
        foreach ($v3_fields_with_labels as $field_key) {
            if (!empty($mapping[$field_key])) {
                $field_config = $mapping[$field_key];
                $source_field = $field_config['source_field'] ?? '';
                $source_type = $field_config['source_type'] ?? '';
                
                // v4.18.2: Debug log
                error_log('YFGP v4.18.2: Processing ' . $field_key . ' - source_field: ' . $source_field . ', source_type: ' . $source_type);
                
                if ($source_field === '' || $source_type !== 'meta_field') {
                    error_log('YFGP v4.18.2: Skipping ' . $field_key . ' - source_field empty or source_type not meta_field');
                    continue;
                }
                
                // v4.18.8: Используем Unified mapper для получения значения (вместо get_post_meta)
                // getFieldValue() автоматически делает десериализацию
                $raw_value = $mapper_unified->getFieldValue($post_id, $field_config, true); // skip_cache для точности
                
                if ($raw_value === null || $raw_value === '' || (is_array($raw_value) && empty($raw_value))) {
                    continue;
                }
                
                // Создаём временный mapping для normalize_specialities() (как для specialities)
                // v4.18.21: Важно передать source_field_key для ACF полей (через acf_get_reference)
                $source_field_key = $field_config['source_field_key'] ?? '';
                if (empty($source_field_key) && function_exists('acf_get_reference')) {
                    // Пробуем получить ACF field reference (как в normalize_specialities())
                    $field_reference = acf_get_reference($source_field, $post_id);
                    if (!$field_reference) {
                        $field_reference = get_post_meta($post_id, '_' . $source_field, true);
                    }
                    if ($field_reference) {
                        $source_field_key = $field_reference;
                    }
                }
                
                $temp_mapping = array(
                    'specialities' => array(
                        'source_field' => $source_field,
                        'source_field_key' => $source_field_key,
                        'source_type' => $source_type,
                        'source_cpt' => $field_config['source_cpt'] ?? get_post_type($post_id),
                    )
                );
                
                // Вызываем normalize_specialities() - тот же метод, что используется для specialization!
                // Используем unified mapper для прогонки значения через тот же пайплайн, что и specialization (без Reflection)
                $normalized = $mapper_unified->normalizeSpecialityField($post, $temp_mapping['specialities'], $raw_value);

                // Берём labels из результата (как для specialization)
                if (!empty($normalized['labels']) && is_array($normalized['labels'])) {
                    // Склеиваем labels через запятую для вывода
                    $data[$field_key] = implode(', ', $normalized['labels']);
                    // v4.18.4: Debug log
                    error_log('YFGP v4.18.4: ' . $field_key . ' = ' . $data[$field_key]);
                } else {
                    // v4.18.4: Debug log - labels отсутствуют
                    error_log('YFGP v4.18.4: ' . $field_key . ' - normalized labels is empty. Normalized: ' . print_r($normalized, true));
                }
            }
        }
        
        // Step 2: Обрабатываем остальные поля через Unified mapper (вместо SQL)
        foreach ($v3_fields_sql as $field_key) {
            if (empty($mapping[$field_key])) {
                continue;
            }
            
            $field_config = $mapping[$field_key];
            $source_field = $field_config['source_field'] ?? '';
            $source_type = $field_config['source_type'] ?? '';
            
            // v4.18.20: Обработка пустого source_type или source_type=meta_field с source_field=post_date
            // Если source_type пустой, но source_field заполнен, обрабатываем как WordPress поле
            // Также обрабатываем source_type=meta_field с source_field=post_date (это WordPress поле, не meta_field)
            $is_wordpress_field = false;
            if ((empty($source_type) && !empty($source_field)) || 
                ($source_type === 'meta_field' && in_array($source_field, array('post_date', 'post_title', 'post_content', 'post_excerpt'), true))) {
                $is_wordpress_field = true;
            }
            
            if ($is_wordpress_field) {
                // Проверяем является ли это WordPress полем (post_date, post_title и т.д.)
                $post = get_post($post_id);
                if ($post) {
                    $value = null;
                    switch ($source_field) {
                        case 'post_date':
                            $value = $post->post_date;
                            break;
                        case 'post_title':
                            $value = $post->post_title;
                            break;
                        case 'post_content':
                            $value = $post->post_content;
                            break;
                        case 'post_excerpt':
                            $value = $post->post_excerpt;
                            break;
                        default:
                            // Пробуем через Unified mapper с временным config
                            $temp_config = array_merge($field_config, array('source_type' => 'meta_field'));
                            $value = $mapper_unified->getFieldValue($post_id, $temp_config, true);
                            break;
                    }
                    
                    // v4.18.20: Применяем calculate_type после получения значения из WordPress поля
                    if ($value !== null && $value !== '') {
                        $calculate_type = $field_config['calculate_type'] ?? '';
                        if ($calculate_type === 'date_to_years' && strtotime($value) !== false) {
                            // Преобразуем дату в количество лет опыта
                            $start_date = strtotime($value);
                            $years = floor((time() - $start_date) / (365.25 * 24 * 60 * 60));
                            if ($years >= 0) {
                                $data[$field_key] = $years;
                            }
                        } elseif ($calculate_type === 'date_as_is' && strtotime($value) !== false) {
                            // Берём дату как есть (формат YYYY-MM-DD)
                            $data[$field_key] = date('Y-m-d', strtotime($value));
                        } else {
                            // Обычное значение
                            $data[$field_key] = $value;
                        }
                    }
                }
                continue;
            }
            
            // v4.18.8: Используем Unified mapper для получения значения (вместо SQL запроса)
            $value = $mapper_unified->getFieldValue($post_id, $field_config, true); // skip_cache для точности
            
            if ($value === null || $value === '') {
                continue;
            }
            
            // Обработка calculate_type (date_to_years, date_as_is)
            $calculate_type = $field_config['calculate_type'] ?? '';
            if ($calculate_type === 'date_to_years' && strtotime($value) !== false) {
                // Преобразуем дату в количество лет опыта
                $start_date = strtotime($value);
                $years = floor((time() - $start_date) / (365.25 * 24 * 60 * 60));
                if ($years >= 0) {
                    $data[$field_key] = $years;
                }
            } elseif ($calculate_type === 'date_as_is' && strtotime($value) !== false) {
                // Берём дату как есть
                $data[$field_key] = $value;
            } else {
                // Обычное значение
                $data[$field_key] = $value;
            }
        }
        
        return $data;
    }

    /**
     * Построить строку repeater из данных
     * 
     * v4.18.20: Перенесено из Feed_Generator_V2
     * 
     * @param array<string, mixed> $row_data Данные строки
     * @param array<string, mixed> $mapping Маппинг полей
     * @param array<string, string> $subfield_map Маппинг подполей
     * @param int $context_post_id ID контекстного поста
     * @return array<string, mixed> Нормализованная строка
     */
    private function build_repeater_row_from_data(array $row_data, array $mapping, array $subfield_map, int $context_post_id): array {
        $row = array();

        foreach ($subfield_map as $field_key => $target_key) {
            if (empty($mapping[$field_key])) {
                continue;
            }

            $field_config = $mapping[$field_key];
            $source_field = $field_config['source_field'] ?? '';
            $raw_value = null;

            // v4.18.1: Исправление - поддержка field keys ACF (field_xxxx) и имён полей
            if ($source_field !== '') {
                // Сначала проверяем по source_field (имя поля)
                if (array_key_exists($source_field, $row_data)) {
                    $raw_value = $row_data[$source_field];
                } elseif (function_exists('acf_get_field')) {
                    // Если source_field - это field key ACF, ищем в row_data по field key
                    if (strpos($source_field, 'field_') === 0 && array_key_exists($source_field, $row_data)) {
                        $raw_value = $row_data[$source_field];
                    } else {
                        // Пробуем найти по имени поля через ACF API
                        $acf_field = acf_get_field($source_field);
                        if ($acf_field && !empty($acf_field['name'])) {
                            // Ищем по имени поля
                            if (array_key_exists($acf_field['name'], $row_data)) {
                                $raw_value = $row_data[$acf_field['name']];
                            }
                            // Ищем по field key, если он есть в row_data
                            if ($raw_value === null && !empty($acf_field['key']) && array_key_exists($acf_field['key'], $row_data)) {
                                $raw_value = $row_data[$acf_field['key']];
                            }
                        }
                    }
                }
            }
            
            // Fallback: проверяем по field_key и meta_field
            if ($raw_value === null) {
                if (array_key_exists($field_key, $row_data)) {
                    $raw_value = $row_data[$field_key];
                } elseif (!empty($field_config['meta_field']) && array_key_exists($field_config['meta_field'], $row_data)) {
                    $raw_value = $row_data[$field_config['meta_field']];
                }
            }

            if ($raw_value === null) {
                continue;
            }

            $normalized = $this->normalize_repeater_value($raw_value, $field_config, $context_post_id);
            $normalized = $this->apply_calculate_type($normalized, $field_config);

            if ($normalized !== null && $normalized !== '') {
                $row[$target_key] = $normalized;
            }
        }

        return $row;
    }

    /**
     * Extract repeater block via unified mapper
     * 
     * v4.18.20: Перенесено из Feed_Generator_V2, убрана зависимость от callback
     * 
     * @param int $post_id Post ID
     * @param array<string, mixed> $mapping V3 mapping config
     * @param string $block_key Repeater field key
     * @param array<string, string> $subfield_map Subfield mapping
     * @param array<string> $fallback_config_keys Fallback config keys
     * @return array<int, array<string, mixed>> Repeater rows
     */
    private function extract_repeater_block_internal(int $post_id, array $mapping, string $block_key, array $subfield_map, array $fallback_config_keys = array()): array {
        $config_candidates = array_merge(array($block_key), $fallback_config_keys);
        $block_config = null;
        $found_fallback_config = null;
        $is_fallback = false;

        foreach ($config_candidates as $candidate) {
            if (!empty($mapping[$candidate])) {
                // Если это основной block_key, используем его как есть
                if ($candidate === $block_key) {
                    $block_config = $mapping[$candidate];
                    $is_fallback = false;
                    break;
                }
                // Если это fallback, сохраняем для дальнейшей обработки
                if ($block_config === null) {
                    $block_config = $mapping[$candidate];
                    $found_fallback_config = $mapping[$candidate];
                    $is_fallback = true;
                }
            }
        }

        // v4.18.3: Если block_config не найден по основному ключу, но есть fallback с relationship_1,
        // создаём временный block_config без nested_field для получения полных объектов
        if (empty($block_config) || empty($block_config['source_type'])) {
            return array();
        }

        // Если block_config найден из fallback и это relationship_1, но есть nested_field,
        // создаём копию без nested_field для получения полных объектов (не только ID)
        if ($is_fallback && 
            strpos((string) $block_config['source_type'], 'relationship') === 0 &&
            !empty($block_config['nested_field'])) {
            // Создаём временный block_config без nested_field для relationship блоков
            $block_config = array_merge(array(), $block_config);
            $block_config['nested_field'] = null;
        }

        if (!class_exists('YFGP_Field_Mapper_Unified')) {
            require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
        }

        $mapper = YFGP_Field_Mapper_Unified::get_instance();
        $raw_rows = $mapper->getFieldValue($post_id, $block_config, true); // skip_cache для точности генерации фида

        if (empty($raw_rows)) {
            return array();
        }

        $result = array();
        $source_type = $block_config['source_type'];

        // v4.18.3: Если raw_rows - массив чисел (ID) для relationship, преобразуем в объекты WP_Post
        // v4.18.3: Преобразование ID в объекты только для relationship (не для repeater_acf/repeater_jetengine)
        if (is_array($raw_rows) && 
            strpos((string) $source_type, 'relationship') === 0 && 
            !in_array($source_type, array('repeater_acf', 'repeater_jetengine'), true)) {
            $all_numeric = true;
            foreach ($raw_rows as $item) {
                if (!is_numeric($item)) {
                    $all_numeric = false;
                    break;
                }
            }
            if ($all_numeric && !empty($raw_rows)) {
                // Преобразуем ID в объекты WP_Post
                $target_cpt = isset($block_config['source_cpt']) ? $block_config['source_cpt'] : 'any';
                
                // Load Post_Batch_Loader if not already loaded
                if (!class_exists('YFGP_Post_Batch_Loader')) {
                    require_once YFGP_PLUGIN_DIR . 'includes/class-post-batch-loader.php';
                }
                
                if (class_exists('YFGP_Post_Batch_Loader')) {
                    // Use batch loader for safe memory usage
                    $loader = new YFGP_Post_Batch_Loader($target_cpt, 1000);
                    $posts = $loader->get_posts_by_ids(
                        array_map('intval', $raw_rows),
                        $target_cpt,
                        'publish'
                    );
                } else {
                    // Fallback: use limited query
                    $ids = array_map('intval', $raw_rows);
                    $posts = get_posts(array(
                        'post__in' => array_slice($ids, 0, 10000), // Limit to 10k
                        'post_type' => $target_cpt,
                        'posts_per_page' => 10000,
                        'orderby' => 'post__in',
                    ));
                }
                
                if (!empty($posts)) {
                    $raw_rows = $posts;
                }
            }
        }

        if (is_array($raw_rows) && in_array($source_type, array('repeater_acf', 'repeater_jetengine'), true)) {
            foreach ($raw_rows as $row_data) {
                if (!is_array($row_data)) {
                    continue;
                }
                $normalized = $this->build_repeater_row_from_data($row_data, $mapping, $subfield_map, $post_id);
                if (!empty($normalized)) {
                    $result[] = $normalized;
                }
            }
            return $result;
        }

        if (is_array($raw_rows) && strpos((string) $source_type, 'relationship') === 0) {
            foreach ($raw_rows as $related_item) {
                $related_id = 0;
                if ($related_item instanceof \WP_Post) {
                    $related_id = (int) $related_item->ID;
                } elseif (is_object($related_item) && isset($related_item->ID)) {
                    $related_id = (int) $related_item->ID;
                } elseif (is_numeric($related_item)) {
                    $related_id = (int) $related_item;
                }

                if ($related_id <= 0) {
                    continue;
                }

                $row = array();
                foreach ($subfield_map as $field_key => $target_key) {
                    if (empty($mapping[$field_key])) {
                        continue;
                    }

                    $field_config = $mapping[$field_key];
                    $value = $mapper->getFieldValue($related_id, $field_config, true); // skip_cache для точности генерации фида
                    $normalized = $this->normalize_repeater_value($value, $field_config, $related_id);
                    $normalized = $this->apply_calculate_type($normalized, $field_config);

                    if ($normalized !== null && $normalized !== '') {
                        $row[$target_key] = $normalized;
                    }
                }

                if (!empty($row)) {
                    $result[] = $row;
                }
            }

            return $result;
        }

        if (is_array($raw_rows)) {
            foreach ($raw_rows as $row_data) {
                if (!is_array($row_data)) {
                    $row_data = array('value' => $row_data);
                }
                $normalized = $this->build_repeater_row_from_data($row_data, $mapping, $subfield_map, $post_id);
                if (!empty($normalized)) {
                    $result[] = $normalized;
                }
            }
        }

        return $result;
    }

    /**
     * Get reviews source config from settings and mapping
     * 
     * v4.18.20: Перенесено из Feed_Generator_V2, убрана зависимость от callback
     * 
     * @param array<string, mixed> $settings Plugin settings
     * @param array<string, mixed> $mapping V3 mapping config (passed by reference)
     * @return string|null Block key when configuration is valid, otherwise null
     */
    private function get_reviews_source_config_internal(array $settings, array &$mapping): ?string {
        static $warningIssued = false;

        $logFallback = function (string $message) use (&$warningIssued): void {
            if ($warningIssued) {
                return;
            }

            error_log('[YFGP] ' . $message . ' Falling back to legacy reviews_repeater_field.');
            $warningIssued = true;
        };

        $source_preference = $settings['source_reviews'] ?? 'repeater';
        $target_cpt = trim((string) ($settings['source_reviews_cpt'] ?? ''));

        // v4.18.3: Если mapping reviews отсутствует, но есть reviews_total_count с relationship_1,
        // создаем mapping['reviews'] на основе reviews_total_count, но БЕЗ nested_field
        $reviews_config = $mapping['reviews'] ?? null;
        if (empty($reviews_config) || empty($reviews_config['source_type'])) {
            if ($source_preference === 'relationship' && $target_cpt !== '') {
                // v4.18.3: Сначала проверяем reviews_total_count - это самый надежный источник
                $reviews_total_count_config = $mapping['reviews_total_count'] ?? null;
                if (!empty($reviews_total_count_config) && 
                    is_array($reviews_total_count_config) &&
                    isset($reviews_total_count_config['source_type']) &&
                    strpos($reviews_total_count_config['source_type'], 'relationship') === 0 &&
                    isset($reviews_total_count_config['source_cpt']) &&
                    $reviews_total_count_config['source_cpt'] === $target_cpt &&
                    !empty($reviews_total_count_config['source_field'])) {
                    // Создаем reviews_config на основе reviews_total_count, но БЕЗ nested_field
                    $reviews_config = array_merge(array(), $reviews_total_count_config);
                    unset($reviews_config['nested_field']); // Критично: убираем nested_field для получения полных объектов
                    $mapping['reviews'] = $reviews_config; // Добавляем в mapping
                    error_log('[YFGP v4.18.3] Auto-created reviews mapping from reviews_total_count: source_field=' . $reviews_config['source_field']);
                } else {
                    // v4.18.1: Автоматически создаем mapping для relationship источника
                    // Ищем поле relationship для CPT reviews в mapping
                    $reviews_config = array(
                        'source_type' => 'relationship_1',
                        'source_field' => '',
                        'source_cpt' => $target_cpt
                    );
                    
                    // Пробуем найти relationship поле, связанное с reviews
                    foreach ($mapping as $field_key => $field_config) {
                        if (is_array($field_config) && 
                            isset($field_config['source_type']) && 
                            strpos($field_config['source_type'], 'relationship') === 0 &&
                            isset($field_config['source_cpt']) && 
                            $field_config['source_cpt'] === $target_cpt) {
                            $reviews_config['source_field'] = $field_config['source_field'] ?? '';
                            $reviews_config['source_type'] = $field_config['source_type'];
                            // Убираем nested_field если он есть
                            if (isset($reviews_config['nested_field'])) {
                                unset($reviews_config['nested_field']);
                            }
                            break;
                        }
                    }
                    
                    // Если не нашли, пробуем стандартные имена полей для JetEngine relationships
                    if (empty($reviews_config['source_field'])) {
                        $possible_fields = array('jet_rel_reviews', 'reviews_relationship', 'reviews');
                        foreach ($possible_fields as $field_name) {
                            foreach ($mapping as $field_key => $field_config) {
                                if (is_array($field_config) && 
                                    isset($field_config['source_field']) && 
                                    $field_config['source_field'] === $field_name &&
                                    isset($field_config['source_type']) && 
                                    strpos($field_config['source_type'], 'relationship') === 0) {
                                    $reviews_config['source_field'] = $field_name;
                                    $reviews_config['source_type'] = $field_config['source_type'];
                                    if (isset($reviews_config['nested_field'])) {
                                        unset($reviews_config['nested_field']);
                                    }
                                    break 2;
                                }
                            }
                        }
                    }
                    
                    // Если нашли source_field, добавляем в mapping
                    if (!empty($reviews_config['source_field'])) {
                        $mapping['reviews'] = $reviews_config;
                        error_log('[YFGP v4.18.3] Auto-created reviews mapping: source_field=' . $reviews_config['source_field']);
                    } else {
                        $logFallback('Reviews mapping is missing and could not be auto-created from settings.');
                        return null;
                    }
                }
            } else {
                $logFallback('Reviews mapping is missing or incomplete.');
                return null;
            }
        }

        $current_source_type = $reviews_config['source_type'] ?? '';

        if ($source_preference === 'relationship') {
            if ($target_cpt === '') {
                $logFallback('Relationship reviews source selected but CPT is not configured.');
                return null;
            }

            if (strpos((string) $current_source_type, 'relationship') !== 0) {
                $logFallback('Reviews mapping does not use a relationship source.');
                return null;
            }
        } else {
            $supported_repeater_sources = array('repeater_acf', 'repeater_jetengine');
            if (!in_array($current_source_type, $supported_repeater_sources, true)) {
                $logFallback('Reviews mapping does not use a repeater source.');
                return null;
            }
        }

        return 'reviews';
    }

    /**
     * Получить термины специализации для указанного врача
     *
     * @param int $post_id
     * @return array<int, array<string, string>>
     */
    private function get_speciality_terms_for_post(int $post_id): array {
        if ($post_id <= 0) {
            return array();
        }

        $taxonomy = $this->settings['exclusions_taxonomy'] ?? '';
        if (empty($taxonomy)) {
            $taxonomy = $this->settings['specialties_taxonomy'] ?? '';
        }

        if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
            return array();
        }

        $terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'all'));
        if (is_wp_error($terms) || empty($terms)) {
            return array();
        }

        $result = array();
        foreach ($terms as $term) {
            $result[] = array(
                'slug' => $term->slug,
                'name' => $term->name,
            );
        }

        return $result;
    }
}

