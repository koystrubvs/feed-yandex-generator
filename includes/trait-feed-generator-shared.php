<?php
if (!defined('ABSPATH')) {
    exit;
}

trait YFGP_Feed_Generator_Shared_Trait {
    /**
     * Нормализация булевых значений в строковое представление, совместимое с Yandex YML.
     */
    protected function normalize_boolean_string($value, bool $default = false): string {
        if ($value === null) {
            return $default ? 'true' : 'false';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return ((int) $value) !== 0 ? 'true' : 'false';
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === '') {
                return $default ? 'true' : 'false';
            }
            if (in_array($normalized, array('1', 'true', 'yes', 'on'), true)) {
                return 'true';
            }
            if (in_array($normalized, array('0', 'false', 'off', 'no'), true)) {
                return 'false';
            }
        }

        return !empty($value) ? 'true' : ($default ? 'true' : 'false');
    }

    /**
     * Приведение значения специализации (slug/label/array) к строке.
     */
    protected function normalize_speciality_value($value, string $fallback = ''): string {
        if (is_array($value)) {
            $value = $value['label'] ?? $value['name'] ?? $value[0] ?? '';
        }

        if (!is_string($value)) {
            $value = (string) $value;
        }

        if ($value === '') {
            $value = $fallback !== '' ? $fallback : __('specialist', 'yandex-feed-generator-pro');
        }

        return $value;
    }

    /**
     * Человекочитаемый label специализации по slug.
     */
    protected function get_speciality_label($value): string {
        if (is_array($value)) {
            $value = $value['label'] ?? $value['name'] ?? $value[0] ?? '';
        }

        if (!is_string($value)) {
            $value = (string) $value;
        }

        if ($value !== '' && preg_match('/[А-Яа-яЁё]/u', $value)) {
            return $value;
        }

        if (!class_exists('YFGP_Field_Mapper_Unified')) {
            require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';
        }

        $mapper = YFGP_Field_Mapper_Unified::get_instance();
        if (method_exists($mapper, 'getSpecialityLabelFromSlug')) {
            $label = $mapper->getSpecialityLabelFromSlug($value);
            if (!empty($label)) {
                return $label;
            }
        }

        return $value;
    }

    /**
     * Normalize long text blocks for Yandex YML: remove script/style, HTML, shortcodes.
     */
    protected function sanitize_feed_text($value): string {
        if (is_array($value)) {
            $value = implode(' ', array_map('strval', $value));
        }

        if ($value === null) {
            return '';
        }

        if (!is_string($value)) {
            $value = (string) $value;
        }

        if ($value === '') {
            return '';
        }

        $value = preg_replace('#<(script|style)[^>]*>.*?</\\1>#is', ' ', $value);
        $value = preg_replace('#<!--.*?-->#s', ' ', $value);
        $value = preg_replace('/\[[^\]]+\]/', ' ', $value);

        if (function_exists('wp_strip_all_tags')) {
            $value = wp_strip_all_tags($value, true);
        } else {
            $value = strip_tags($value);
        }

        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', trim($value));

        return $value;
    }

    /**
     * Normalize free appointment condition: strip HTML, extract captions/alt text, fallback to discount name.
     */
    protected function normalize_free_appointment_text($raw_value, ?string $discount_name = null): string {
        if (is_array($raw_value)) {
            $raw_value = implode(' ', array_map('strval', $raw_value));
        }

        if (!is_string($raw_value)) {
            return '';
        }

        $text = trim(preg_replace('/\s+/', ' ', strip_tags($raw_value)));
        if ($text !== '') {
            return $text;
        }

        $caption = $this->extract_caption_from_gutenberg_block($raw_value);
        if ($caption !== '') {
            return $caption;
        }

        $alt_text = $this->extract_img_alt_texts($raw_value);
        if ($alt_text !== '') {
            return $alt_text;
        }

        if (!empty($discount_name)) {
            return trim($discount_name);
        }

        return '';
    }

    /**
     * Extract caption/title from Gutenberg image blocks.
     */
    protected function extract_caption_from_gutenberg_block(string $raw_value): string {
        if (!preg_match_all('/<!--\s+wp:image\s+({.*?})\s+-->/', $raw_value, $matches)) {
            return '';
        }

        foreach ($matches[1] as $json) {
            $data = json_decode($json, true);
            if (!is_array($data)) {
                continue;
            }

            if (!empty($data['caption'])) {
                return trim(strip_tags($data['caption']));
            }

            if (!empty($data['title'])) {
                return trim(strip_tags($data['title']));
            }
        }

        return '';
    }

    /**
     * Extract alt/title attributes from HTML <img> tags.
     */
    protected function extract_img_alt_texts(string $raw_value): string {
        if (!preg_match_all('/<img[^>]+>/i', $raw_value, $matches)) {
            return '';
        }

        foreach ($matches[0] as $img_tag) {
            if (preg_match('/alt="([^"]+)"/i', $img_tag, $alt_match)) {
                $alt = trim($alt_match[1]);
                if ($alt !== '') {
                    return $alt;
                }
            }

            if (preg_match('/title="([^"]+)"/i', $img_tag, $title_match)) {
                $title = trim($title_match[1]);
                if ($title !== '') {
                    return $title;
                }
            }
        }

        return '';
    }

    /**
     * Список slug'ов специализаций, у которых нет базовой услуги.
     */
    protected function get_specialties_no_primary(): array {
        $text = $this->settings['specialties_no_primary'] ?? '';
        $specialties = array_map('trim', explode(',', (string) $text));
        return array_filter(array_map('strtolower', $specialties));
    }

    /**
     * Транслитерация кириллицы (используется для author_id в отзывах).
     */
    protected function transliterate_russian(string $text): string {
        $transliteration = array(
            'а' => 'a',  'б' => 'b',  'в' => 'v',  'г' => 'g',  'д' => 'd',
            'е' => 'e',  'ё' => 'yo', 'ж' => 'zh', 'з' => 'z',  'и' => 'i',
            'й' => 'y',  'к' => 'k',  'л' => 'l',  'м' => 'm',  'н' => 'n',
            'о' => 'o',  'п' => 'p',  'р' => 'r',  'с' => 's',  'т' => 't',
            'у' => 'u',  'ф' => 'f',  'х' => 'h',  'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch','ь' => '',   'ы' => 'y',  'ъ' => '',
            'э' => 'e',  'ю' => 'yu', 'я' => 'ya',
            'А' => 'A',  'Б' => 'B',  'В' => 'V',  'Г' => 'G',  'Д' => 'D',
            'Е' => 'E',  'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z',  'И' => 'I',
            'Й' => 'Y',  'К' => 'K',  'Л' => 'L',  'М' => 'M',  'Н' => 'N',
            'О' => 'O',  'П' => 'P',  'Р' => 'R',  'С' => 'S',  'Т' => 'T',
            'У' => 'U',  'Ф' => 'F',  'Х' => 'H',  'Ц' => 'Ts', 'Ч' => 'Ch',
            'Ш' => 'Sh', 'Щ' => 'Sch','Ь' => '',   'Ы' => 'Y',  'Ъ' => '',
            'Э' => 'E',  'Ю' => 'Yu', 'Я' => 'Ya',
        );

        return strtr($text, $transliteration);
    }

    /**
     * Общий XML-escape для YML.
     */
    protected function escape_xml(string $string): string {
        return htmlspecialchars($string, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Check if array is associative
     * 
     * v4.18.39: Moved from Feed_Generator_V2 and XML_Serialization_Service to trait
     * 
     * @param array<mixed> $array Array to check
     * @return bool True if associative
     */
    protected function v3_is_assoc(array $array): bool {
        if (empty($array)) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Check if value is truthy
     * 
     * v4.18.39: Moved from Feed_Generator_V2 and XML_Serialization_Service to trait
     * 
     * @param mixed $value Value to check
     * @return bool True if truthy
     */
    protected function v3_is_truthy($value): bool {
        if (is_bool($value)) {
            return $value === true;
        }
        if (is_numeric($value)) {
            return (float)$value > 0;
        }
        if (is_string($value)) {
            $lower = strtolower(trim($value));
            return in_array($lower, array('1', 'true', 'yes', 'on', 'enabled'), true);
        }
        return !empty($value);
    }

    /**
     * Wrap content in CDATA section
     * 
     * v4.18.39: Moved from Feed_Generator_V2 and Feed_XML_Writer to trait
     * 
     * @param string $content Content to wrap
     * @return string Wrapped content
     */
    protected function wrap_cdata(string $content): string {
        if (empty($content)) {
            return '';
        }
        // Escape existing CDATA sections
        $content = str_replace(']]>', ']]]]><![CDATA[>', $content);
        return '<![CDATA[' . $content . ']]>';
    }
}
