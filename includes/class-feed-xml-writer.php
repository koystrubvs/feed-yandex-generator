<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once YFGP_PLUGIN_DIR . 'includes/trait-feed-generator-shared.php';

class YFGP_Yml_Stream_Writer {
    use YFGP_Feed_Generator_Shared_Trait;

    /**
     * @var array<string, mixed>
     */
    private array $settings;

    public function __construct(array $settings) {
        $this->settings = $settings;
    }

    /**
     * Стриминговая запись YML. Возвращает итоговую строку без накопления промежуточных буферов.
     */
    public function build(array $doctors, array $clinics, array $services, array $offers): string {
        $handle = fopen('php://temp', 'w+');
        if (!is_resource($handle)) {
            throw new RuntimeException('Unable to open temporary stream for YML writer');
        }

        $this->write_header($handle);
        $this->write_doctors($handle, $doctors);
        $this->write_clinics($handle, $clinics);
        $this->write_services($handle, $services);
        $this->write_offers($handle, $offers);
        fwrite($handle, "</shop>\n");

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content === false ? '' : $content;
    }

    private function write_header($handle): void {
        $now = current_time('Y-m-d H:i');
        fwrite($handle, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
        fwrite($handle, '<shop version="2.0" date="' . $now . '">' . "\n");
        fwrite($handle, '  <name>' . $this->escape_xml($this->settings['shop_name'] ?? get_bloginfo('name')) . "</name>\n");
        fwrite($handle, '  <company>' . $this->escape_xml($this->settings['company_name'] ?? get_bloginfo('name')) . "</company>\n");
        fwrite($handle, '  <url>' . $this->escape_xml($this->settings['company_url'] ?? get_site_url()) . "</url>\n");

        $mapping = get_option('yfgp_field_mapping_v3', array());
        $shop_picture = $mapping['shop_picture'] ?? $this->settings['shop_picture'] ?? null;
        if (empty($shop_picture)) {
            $shop_picture = get_site_icon_url(512) ?: '';
        }
        fwrite($handle, '  <picture>' . $this->escape_xml($shop_picture) . "</picture>\n");

        if (!empty($this->settings['company_email'])) {
            fwrite($handle, '  <email>' . $this->escape_xml($this->settings['company_email']) . "</email>\n");
        }
    }

    private function write_doctors($handle, array $doctors): void {
        fwrite($handle, "  <doctors>\n");
        foreach ($doctors as $doctor) {
            fwrite($handle, $this->build_doctor_xml($doctor));
        }
        fwrite($handle, "  </doctors>\n");
    }

    private function write_clinics($handle, array $clinics): void {
        fwrite($handle, "  <clinics>\n");
        foreach ($clinics as $clinic) {
            fwrite($handle, $this->build_clinic_xml($clinic));
        }
        fwrite($handle, "  </clinics>\n");
    }

    private function write_services($handle, array $services): void {
        fwrite($handle, "  <services>\n");
        foreach ($services as $service) {
            fwrite($handle, $this->build_service_xml($service));
        }
        fwrite($handle, "  </services>\n");
    }

    private function write_offers($handle, array $offers): void {
        fwrite($handle, "  <offers>\n");
        foreach ($offers as $offer) {
            fwrite($handle, $this->build_offer_xml($offer));
        }
        fwrite($handle, "  </offers>\n");
    }

    private function build_doctor_xml(array $doctor): string {
        $xml = '    <doctor id="' . $this->escape_xml($doctor['id']) . '">' . "\n";
        $xml .= '      <name>' . $this->escape_xml($doctor['name']) . "</name>\n";
        $xml .= '      <url>' . $this->escape_xml($doctor['url']) . "</url>\n";
        $xml .= '      <internal_id>' . $this->escape_xml($doctor['internal_id']) . "</internal_id>\n";

        if (!empty($doctor['description'])) {
            $clean = strip_tags($doctor['description']);
            $xml .= '      <description>' . $this->escape_xml(mb_substr($clean, 0, 500)) . "</description>\n";
        }

        // v4.18.21: experience_years - выводим только если не пустой и не равен '0'
        if (isset($doctor['experience_years']) && $doctor['experience_years'] !== '' && $doctor['experience_years'] !== '0') {
            $xml .= '      <experience_years>' . $this->escape_xml($doctor['experience_years']) . "</experience_years>\n";
        }
        
        if (!empty($doctor['career_start_date'])) {
            // career_start_date должно быть в формате YYYY-MM-DD (уже нормализовано в build_doctor_entity_internal)
            $xml .= '      <career_start_date>' . $this->escape_xml($doctor['career_start_date']) . "</career_start_date>\n";
        }
        
        // Остальные поля
        foreach (array('surname', 'first_name', 'patronymic', 'picture', 'degree', 'rank', 'category', 'reviews_total_count') as $field) {
            if (!empty($doctor[$field])) {
                $xml .= '      <' . $field . '>' . $this->escape_xml($doctor[$field]) . '</' . $field . ">\n";
            }
        }

        if (!empty($doctor['education'])) {
            $xml .= $this->build_education_xml($doctor['education']);
        }
        if (!empty($doctor['job'])) {
            $xml .= $this->build_job_xml($doctor['job']);
        }
        if (!empty($doctor['certificate'])) {
            $xml .= $this->build_certificate_xml($doctor['certificate']);
        }
        if (!empty($doctor['reviews'])) {
            $xml .= $this->build_reviews_xml($doctor['reviews']);
        }

        $xml .= "    </doctor>\n";
        return $xml;
    }

    private function build_clinic_xml(array $clinic): string {
        $xml = '    <clinic id="' . $this->escape_xml($clinic['id']) . '">' . "\n";
        $xml .= '      <name>' . $this->escape_xml($clinic['name']) . "</name>\n";
        $xml .= '      <url>' . $this->escape_xml($clinic['url']) . "</url>\n";
        $xml .= '      <internal_id>' . $this->escape_xml($clinic['internal_id']) . "</internal_id>\n";

        foreach (array('city', 'address', 'phone', 'email', 'picture', 'company_id') as $field) {
            if (!empty($clinic[$field])) {
                $xml .= '      <' . $field . '>' . $this->escape_xml(is_array($clinic[$field]) ? implode(', ', $clinic[$field]) : $clinic[$field]) . '</' . $field . ">\n";
            }
        }

        $xml .= "    </clinic>\n";
        return $xml;
    }

    private function build_service_xml(array $service): string {
        $xml = '    <service id="' . $this->escape_xml($service['id']) . '">' . "\n";
        $xml .= '      <name>' . $this->escape_xml($service['name']) . "</name>\n";
        $xml .= '      <internal_id>' . $this->escape_xml($service['internal_id']) . "</internal_id>\n";
        if (!empty($service['description'])) {
            $desc = preg_replace('/\s+/', ' ', strip_tags($service['description']));
            $xml .= '      <description>' . $this->escape_xml(trim($desc)) . "</description>\n";
        }
        if (!empty($service['gov_id'])) {
            $xml .= '      <gov_id>' . $this->escape_xml($service['gov_id']) . "</gov_id>\n";
        }
        if (!empty($service['picture'])) {
            $xml .= '      <picture>' . $this->escape_xml($service['picture']) . "</picture>\n";
        }
        // v4.18.18: Discount removed from service - should be only in offer/price per Yandex spec
        $xml .= "    </service>\n";
        return $xml;
    }

    private function build_offer_xml(array $offer): string {
        $xml = '    <offer id="' . $this->escape_xml($offer['id']) . '">' . "\n";
        if (!empty($offer['appointment_url'])) {
            $xml .= '      <url>' . $this->escape_xml($offer['appointment_url']) . "</url>\n";
        }
        foreach (array('online_schedule' => 'online_schedule', 'appointment_available' => 'appointment', 'oms_available' => 'oms') as $field => $tag) {
            if (isset($offer[$field])) {
                $value = ($offer[$field] === 'true' || $offer[$field] === true) ? 'true' : 'false';
                $xml .= '      <' . $tag . '>' . $value . '</' . $tag . ">\n";
            }
        }
        if (!empty($offer['price']) || !empty($offer['base_price'])) {
            $xml .= "      <price>\n";
            if (!empty($offer['base_price'])) {
                $xml .= '        <base_price>' . $this->escape_xml($offer['base_price']) . "</base_price>\n";
            }
            if (!empty($offer['currency'])) {
                $xml .= '        <currency>' . $this->escape_xml($offer['currency']) . "</currency>\n";
            }
            // v4.18.19: Discount with optional name attribute (per Yandex spec)
            // v4.18.21: FIX - атрибут name опциональный, выводим <discount> без name если discount_name пустой
            // v4.18.21: free_appointment выводим ТОЛЬКО если есть <discount> (по документации Яндекс)
            $has_discount = false;
            if (!empty($offer['discount'])) {
                $discount_attr = '';
                if (!empty($offer['discount_name'])) {
                    $discount_attr = ' name="' . $this->escape_xml($offer['discount_name']) . '"';
                }
                $xml .= '        <discount' . $discount_attr . '>' . $this->escape_xml($offer['discount']) . "</discount>\n";
                $has_discount = true;
            }
            // v4.20.0: Free appointment condition with Gutenberg/alt fallback
            // v4.18.21: Выводим free_appointment ТОЛЬКО если есть <discount> (по документации Яндекс)
            if ($has_discount && !empty($offer['free_appointment_condition'])) {
                $free_appointment_text = $this->normalize_free_appointment_text($offer['free_appointment_condition'], $offer['discount_name'] ?? null);
                if ($free_appointment_text !== '') {
                    $xml .= '        <free_appointment>' . $this->escape_xml($free_appointment_text) . "</free_appointment>\n";
                }
            }
            $xml .= "      </price>\n";
        }

        $xml .= '      <service id="' . $this->escape_xml($offer['service_id']) . '"/>' . "\n";
        $xml .= '      <clinic id="' . $this->escape_xml($offer['clinic_id']) . '">' . "\n";
        $xml .= '        <doctor id="' . $this->escape_xml($offer['doctor_id']) . '">' . "\n";
        $xml .= '          <speciality>' . $this->escape_xml($this->get_speciality_label($offer['speciality'])) . "</speciality>\n";
        foreach (array('children_appointment', 'adult_appointment', 'house_call', 'telemed') as $field) {
            if (isset($offer[$field])) {
                $value = ($offer[$field] === 'true' || $offer[$field] === true) ? 'true' : 'false';
                $xml .= '          <' . $field . '>' . $value . '</' . $field . ">\n";
            }
        }
        $xml .= '          <is_base_service>' . (!empty($offer['is_base_service']) ? 'true' : 'false') . "</is_base_service>\n";
        $xml .= "        </doctor>\n";
        $xml .= "      </clinic>\n";
        $xml .= "    </offer>\n";

        return $xml;
    }

    private function build_education_xml(array $education): string {
        $xml = '';
        foreach ($education as $edu) {
            $xml .= "      <education>\n";
            if (!empty($edu['organization'])) {
                $xml .= '        <organization>' . $this->escape_xml($edu['organization']) . "</organization>\n";
            }
            if (!empty($edu['finish_year'])) {
                $xml .= '        <finish_year>' . $this->escape_xml($edu['finish_year']) . "</finish_year>\n";
            }
            if (!empty($edu['type'])) {
                $xml .= '        <type>' . $this->escape_xml($edu['type']) . "</type>\n";
            }
            if (!empty($edu['specialization'])) {
                $xml .= '        <specialization>' . $this->escape_xml($edu['specialization']) . "</specialization>\n";
            }
            $xml .= "      </education>\n";
        }
        return $xml;
    }

    private function build_job_xml(array $job): string {
        $xml = '';
        foreach ($job as $j) {
            $xml .= "      <job>\n";
            if (!empty($j['organization'])) {
                $xml .= '        <organization>' . $this->escape_xml($j['organization']) . "</organization>\n";
            }
            if (!empty($j['period_years'])) {
                $xml .= '        <period_years>' . $this->escape_xml($j['period_years']) . "</period_years>\n";
            }
            if (!empty($j['position'])) {
                $xml .= '        <position>' . $this->escape_xml($j['position']) . "</position>\n";
            }
            $xml .= "      </job>\n";
        }
        return $xml;
    }

    private function build_certificate_xml(array $certificate): string {
        $xml = '';
        foreach ($certificate as $cert) {
            $xml .= "      <certificate>\n";
            if (!empty($cert['organization'])) {
                $xml .= '        <organization>' . $this->escape_xml($cert['organization']) . "</organization>\n";
            }
            if (!empty($cert['finish_year'])) {
                $xml .= '        <finish_year>' . $this->escape_xml($cert['finish_year']) . "</finish_year>\n";
            }
            if (!empty($cert['name'])) {
                $xml .= '        <name>' . $this->escape_xml($cert['name']) . "</name>\n";
            }
            $xml .= "      </certificate>\n";
        }
        return $xml;
    }

    // v4.18.39: wrap_cdata() moved to YFGP_Feed_Generator_Shared_Trait

    private function build_reviews_xml(array $reviews): string {
        $xml = '';
        foreach ($reviews as $rev) {
            if (empty($rev['grade'])) {
                continue;
            }

            $xml .= "      <review>\n";
            foreach (array('date', 'checked', 'used_in_rating', 'author', 'url', 'comment', 'grade', 'positive', 'negative', 'response') as $field) {
                if (empty($rev[$field])) {
                    continue;
                }

                $xml .= '        <' . $field . '>' . $this->escape_xml($rev[$field]) . '</' . $field . ">\n";
            }
            if (!empty($rev['author_id'])) {
                $author_id = $this->transliterate_russian($rev['author_id']);
                if (strpos($author_id, 'author_') !== 0) {
                    $author_id = 'author_' . $author_id;
                }
                $xml .= '        <author_id>' . $this->escape_xml($author_id) . "</author_id>\n";
            }
            if (!empty($rev['author_picture'])) {
                $xml .= '        <author_picture>' . $this->escape_xml($rev['author_picture']) . "</author_picture>\n";
            }
            $xml .= "      </review>\n";
        }

        return $xml;
    }
}
