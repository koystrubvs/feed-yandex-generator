<?php
/**
 * Базовый класс маппинга полей
 * Минимальная реализация для YFGP_Field_Mapper_V2
 */

if (!defined('ABSPATH')) {
    exit;
}

class YFGP_Field_Mapper {
    
    /**
     * Получить значение поля из поста
     */
    protected function get_field_value($post, $field_name, $default = '') {
        if (!$post) {
            return $default;
        }
        
        // ACF поле
        if (function_exists('get_field')) {
            $acf_value = get_field($field_name, $post->ID);
            if (!empty($acf_value)) {
                return $acf_value;
            }
        }
        
        // JetEngine meta
        $jet_value = get_post_meta($post->ID, $field_name, true);
        if (!empty($jet_value)) {
            return $jet_value;
        }
        
        // Стандартные поля WordPress
        switch ($field_name) {
            case 'post_title':
                return $post->post_title;
            case 'post_content':
                return $post->post_content;
            case 'post_excerpt':
                return $post->post_excerpt;
            case 'permalink':
                return get_permalink($post->ID);
            case 'featured_image':
                return get_the_post_thumbnail_url($post->ID, 'full');
        }
        
        return $default;
    }
    
    /**
     * Получить связанные посты
     */
    protected function get_related_posts($post_id, $field_name, $field_type = 'auto') {
        $related_posts = array();
        
        // 1. ACF Relationship/Post Object
        if (function_exists('get_field')) {
            $acf_value = get_field($field_name, $post_id);
            
            if (!empty($acf_value)) {
                // Если это массив постов
                if (is_array($acf_value)) {
                    foreach ($acf_value as $item) {
                        if (is_object($item) && isset($item->ID)) {
                            $related_posts[] = $item;
                        } elseif (is_numeric($item)) {
                            $post = get_post($item);
                            if ($post) {
                                $related_posts[] = $post;
                            }
                        }
                    }
                }
                // Если это один пост
                elseif (is_object($acf_value) && isset($acf_value->ID)) {
                    $related_posts[] = $acf_value;
                } elseif (is_numeric($acf_value)) {
                    $post = get_post($acf_value);
                    if ($post) {
                        $related_posts[] = $post;
                    }
                }
            }
        }
        
        // 2. JetEngine Relations
        if (empty($related_posts) && function_exists('jet_engine')) {
            // Пытаемся получить через JetEngine
            if (class_exists('Jet_Engine_Relations')) {
                $relations = jet_engine()->relations->get_active_relations();
                
                foreach ($relations as $relation) {
                    $relation_id = $relation->get_args('id');
                    
                    // Проверяем имя поля
                    if ('jet_rel_' . $relation_id === $field_name) {
                        $items = $relation->get_related_items($post_id);
                        
                        if (!empty($items)) {
                            foreach ($items as $item_id) {
                                $post = get_post($item_id);
                                if ($post) {
                                    $related_posts[] = $post;
                                }
                            }
                        }
                        break;
                    }
                }
            }
        }
        
        return $related_posts;
    }
    
    /**
     * Вычислить стаж работы
     */
    protected function calculate_experience($value) {
        if (empty($value)) {
            return '0';
        }
        
        // Если это уже число
        if (is_numeric($value)) {
            return (string)intval($value);
        }
        
        // v4.18.22: Если это дата в формате YYYY-MM-DD
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value)) {
            $start_date = strtotime($value);
            if ($start_date !== false) {
                $current_date = time();
                $years = floor(($current_date - $start_date) / (365 * 24 * 60 * 60));
                return (string)max(0, $years);
            }
        }
        
        // v4.18.22: Если это дата в формате DD/MM/YYYY (например, "15/11/2025")
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})(?:\s+.*)?$/', $value, $matches)) {
            $day = (int)$matches[1];
            $month = (int)$matches[2];
            $year = (int)$matches[3];
            // Проверяем что это валидная дата (год >= 1900 и <= текущий год)
            $current_year = (int)date('Y');
            if ($year >= 1900 && $year <= $current_year && $month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
                $start_date = strtotime(sprintf('%04d-%02d-%02d', $year, $month, $day));
                if ($start_date !== false) {
                    $current_date = time();
                    $years = floor(($current_date - $start_date) / (365 * 24 * 60 * 60));
                    return (string)max(0, $years);
                }
            }
        }
        
        // Если это текст типа "15 лет" (только если не похоже на дату)
        if (preg_match('/^(\d+)\s*(?:лет|год|года|years?)?$/i', $value, $matches)) {
            return $matches[1];
        }
        
        return '0';
    }
    
    /**
     * Получить set-id специализации для Яндекса
     */
    protected function get_set_ids($specialization) {
        if (empty($specialization)) {
            return $this->get_fallback_speciality_slug();
        }
        
        // v4.18.0: BUG-001 FIX - Universal type handling for taxonomy arrays
        // Taxonomy returns array: ['term_id' => 118, 'name' => 'РўРµСЂР°РїРµРІС‚', 'slug' => 'terapevt']
        if (is_array($specialization)) {
            // Extract slug (preferred) or name from taxonomy term array
            if (isset($specialization['slug'])) {
                $specialization = $specialization['slug'];
            } elseif (isset($specialization['name'])) {
                $specialization = $specialization['name'];
            } else {
                // Legacy array of strings - join them
                $specialization = implode(', ', $specialization);
            }
        }
        
        // Маппинг специализаций на set-id
        $mapping = array(
            'стоматолог' => 'stomatolog',
            'терапевт' => 'stomatolog-terapevt',
            'хирург' => 'stomatolog-khirurg',
            'ортопед' => 'stomatolog-ortoped',
            'ортодонт' => 'ortodont',
            'пародонтолог' => 'parodontolog',
            'имплантолог' => 'implantolog',
            'детский стоматолог' => 'detskii-stomatolog',
        );
        
        $spec_lower = mb_strtolower(trim($specialization));
        
        foreach ($mapping as $keyword => $set_id) {
            if (strpos($spec_lower, $keyword) !== false) {
                return $set_id;
            }
        }
        
        return $this->get_fallback_speciality_slug();
    }

    /**
     * Returns fallback speciality slug configured in plugin settings.
     */
    protected function get_fallback_speciality_slug(): string {
        $settings = get_option('yfgp_settings', array());
        $custom_label = isset($settings['fallback_speciality_custom']) ? trim((string) $settings['fallback_speciality_custom']) : '';
        $select_label_raw = isset($settings['fallback_speciality']) ? trim((string) $settings['fallback_speciality']) : '';

        $slug_source = $custom_label !== '' ? $custom_label : $select_label_raw;
        $slug = '';

        if ($slug_source !== '') {
            if (!function_exists('yfgp_specialities_slugify')) {
                require_once YFGP_PLUGIN_DIR . 'includes/specialities-reference.php';
            }

            if (function_exists('yfgp_specialities_slugify')) {
                $slug = yfgp_specialities_slugify($slug_source);
            }

            if ($slug === '') {
                $slug = sanitize_title($slug_source);
            }
        }

        if ($slug === '') {
            $slug = 'specialist';
        }

        return $slug;
    }
}
