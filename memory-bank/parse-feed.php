<?php
/**
 * Script to parse YML feed and extract data for validation
 * Usage: php parse-feed.php <feed-file.yml>
 */

if ($argc < 2) {
    die("Usage: php parse-feed.php <feed-file.yml>\n");
}

$feedFile = $argv[1];
if (!file_exists($feedFile)) {
    die("Feed file not found: $feedFile\n");
}

$xml = simplexml_load_file($feedFile);
if ($xml === false) {
    die("Failed to parse XML feed\n");
}

$result = array(
    'doctors' => array(),
    'clinics' => array(),
    'services' => array(),
    'offers' => array(),
    'statistics' => array()
);

// Parse doctors
if (isset($xml->doctors->doctor)) {
    foreach ($xml->doctors->doctor as $doctor) {
        $doctorId = (string)$doctor['id'];
        $internalId = (string)$doctor->internal_id;
        
        $doctorData = array(
            'id' => $doctorId,
            'internal_id' => $internalId,
            'name' => (string)$doctor->name,
            'url' => (string)$doctor->url,
            'specializations' => array(),
            'reviews_count' => 0,
            'reviews' => array(),
            'offers' => array()
        );
        
        // Get specializations
        if (isset($doctor->category)) {
            $categories = explode(', ', (string)$doctor->category);
            $doctorData['specializations'] = array_merge($doctorData['specializations'], $categories);
        }
        if (isset($doctor->rank)) {
            $ranks = explode(', ', (string)$doctor->rank);
            $doctorData['specializations'] = array_merge($doctorData['specializations'], $ranks);
        }
        $doctorData['specializations'] = array_unique($doctorData['specializations']);
        
        // Get reviews
        if (isset($doctor->reviews_total_count)) {
            $doctorData['reviews_count'] = (int)$doctor->reviews_total_count;
        }
        if (isset($doctor->review)) {
            foreach ($doctor->review as $review) {
                $doctorData['reviews'][] = array(
                    'date' => (string)$review->date,
                    'author' => (string)$review->author,
                    'grade' => (string)$review->grade,
                    'url' => (string)$review->url
                );
            }
        }
        
        $result['doctors'][$internalId] = $doctorData;
    }
}

// Parse clinics
if (isset($xml->clinics->clinic)) {
    foreach ($xml->clinics->clinic as $clinic) {
        $clinicId = (string)$clinic['id'];
        $internalId = (string)$clinic->internal_id;
        
        $result['clinics'][$internalId] = array(
            'id' => $clinicId,
            'internal_id' => $internalId,
            'name' => (string)$clinic->name,
            'url' => (string)$clinic->url,
            'offers' => array()
        );
    }
}

// Parse services
if (isset($xml->services->service)) {
    foreach ($xml->services->service as $service) {
        $serviceId = (string)$service['id'];
        $internalId = (string)$service->internal_id;
        
        $result['services'][$internalId] = array(
            'id' => $serviceId,
            'internal_id' => $internalId,
            'name' => (string)$service->name,
            'description' => (string)$service->description,
            'offers' => array()
        );
    }
}

// Parse offers
if (isset($xml->offers->offer)) {
    foreach ($xml->offers->offer as $offer) {
        $offerId = (string)$offer['id'];
        
        // Extract doctor_id from clinic->doctor
        $doctorId = '';
        $specialization = '';
        if (isset($offer->clinic->doctor)) {
            $doctorId = (string)$offer->clinic->doctor['id'];
            $specialization = (string)$offer->clinic->doctor->speciality;
        }
        
        // Extract clinic_id
        $clinicId = '';
        if (isset($offer->clinic['id'])) {
            $clinicId = (string)$offer->clinic['id'];
        }
        
        // Extract service_id
        $serviceId = '';
        if (isset($offer->service['id'])) {
            $serviceId = (string)$offer->service['id'];
        }
        
        // Extract price
        $price = '';
        $currency = '';
        if (isset($offer->price->base_price)) {
            $price = (string)$offer->price->base_price;
            $currency = (string)$offer->price->currency;
        }
        
        $offerData = array(
            'id' => $offerId,
            'doctor_id' => $doctorId,
            'clinic_id' => $clinicId,
            'service_id' => $serviceId,
            'specialization' => $specialization,
            'price' => $price,
            'currency' => $currency,
            'is_base_service' => isset($offer->clinic->doctor->is_base_service) ? (string)$offer->clinic->doctor->is_base_service : 'false'
        );
        
        $result['offers'][] = $offerData;
        
        // Link to doctor
        $doctorInternalId = str_replace('doctor_', '', $doctorId);
        if (isset($result['doctors'][$doctorInternalId])) {
            $result['doctors'][$doctorInternalId]['offers'][] = $offerId;
        }
        
        // Link to clinic
        if (isset($result['clinics'][$clinicId])) {
            $result['clinics'][$clinicId]['offers'][] = $offerId;
        }
        
        // Link to service
        if (isset($result['services'][$serviceId])) {
            $result['services'][$serviceId]['offers'][] = $offerId;
        }
    }
}

// Statistics
$result['statistics'] = array(
    'doctors_count' => count($result['doctors']),
    'clinics_count' => count($result['clinics']),
    'services_count' => count($result['services']),
    'offers_count' => count($result['offers'])
);

// Output JSON
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>

