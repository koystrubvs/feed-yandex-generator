<?php
/**
 * Script to check relationships through JetEngine API
 * Usage: docker exec wp php /tmp/check-relationships.php
 */

require_once '/var/www/html/wp-load.php';

$result = array(
    'doctors' => array(),
    'errors' => array()
);

// Doctor IDs to check
$doctorIds = array(24978, 24979, 24980, 25040, 25041, 25042, 25043, 25044);

// Check if JetEngine is available
if (!function_exists('jet_engine') || !isset(jet_engine()->relations)) {
    $result['errors'][] = 'JetEngine relations API not available';
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit(1);
}

$relations = jet_engine()->relations->get_active_relations();

foreach ($doctorIds as $doctorId) {
    $doctorData = array(
        'id' => $doctorId,
        'clinics' => array(),
        'services' => array(),
        'reviews' => array(),
        'base_service_id' => null
    );
    
    // Get doctor post
    $doctorPost = get_post($doctorId);
    if (!$doctorPost) {
        $doctorData['error'] = 'Doctor post not found';
        $result['doctors'][$doctorId] = $doctorData;
        continue;
    }
    
    // Check each relation
    foreach ($relations as $relation) {
        if (!isset($relation->db) || !method_exists($relation->db, 'table')) {
            continue;
        }
        
        $table = $relation->db->table();
        $relationId = $relation->get_id();
        $relationName = $relation->get_name();
        
        global $wpdb;
        
        // Check parent_object_id (doctor is parent)
        $relatedAsParent = $wpdb->get_col($wpdb->prepare(
            "SELECT child_object_id FROM {$table} WHERE parent_object_id = %d ORDER BY _ID ASC",
            $doctorId
        ));
        
        // Check child_object_id (doctor is child)
        $relatedAsChild = $wpdb->get_col($wpdb->prepare(
            "SELECT parent_object_id FROM {$table} WHERE child_object_id = %d ORDER BY _ID ASC",
            $doctorId
        ));
        
        // Determine relation type based on post types
        $parentType = $relation->get_args('parent_object');
        $childType = $relation->get_args('child_object');
        
        // Check if this is doctors -> clinics relation
        if (($parentType === 'lawyers' && $childType === 'clinics') || 
            ($parentType === 'doctors' && $childType === 'clinics')) {
            foreach ($relatedAsParent as $clinicId) {
                $clinicPost = get_post($clinicId);
                if ($clinicPost) {
                    $doctorData['clinics'][] = array(
                        'id' => $clinicId,
                        'name' => $clinicPost->post_title,
                        'relation_id' => $relationId,
                        'relation_name' => $relationName
                    );
                }
            }
        }
        
        // Check if this is doctors -> services relation
        if (($parentType === 'lawyers' && $childType === 'services') || 
            ($parentType === 'doctors' && $childType === 'services')) {
            foreach ($relatedAsParent as $serviceId) {
                $servicePost = get_post($serviceId);
                if ($servicePost) {
                    $doctorData['services'][] = array(
                        'id' => $serviceId,
                        'name' => $servicePost->post_title,
                        'relation_id' => $relationId,
                        'relation_name' => $relationName
                    );
                }
            }
        }
        
        // Check if this is doctors -> reviews relation
        if (($parentType === 'lawyers' && $childType === 'reviews') || 
            ($parentType === 'doctors' && $childType === 'reviews')) {
            foreach ($relatedAsParent as $reviewId) {
                $reviewPost = get_post($reviewId);
                if ($reviewPost) {
                    $doctorData['reviews'][] = array(
                        'id' => $reviewId,
                        'name' => $reviewPost->post_title,
                        'relation_id' => $relationId,
                        'relation_name' => $relationName
                    );
                }
            }
        }
    }
    
    // Get base_service_id from meta
    $baseServiceId = get_post_meta($doctorId, 'base_service_id', true);
    if ($baseServiceId) {
        $doctorData['base_service_id'] = $baseServiceId;
    }
    
    // Get base_service_id from ACF if available
    if (function_exists('get_field')) {
        $acfBaseServiceId = get_field('base_service_id', $doctorId);
        if ($acfBaseServiceId) {
            $doctorData['base_service_id'] = $acfBaseServiceId;
        }
    }
    
    // Get specializations from taxonomy
    $specializations = wp_get_post_terms($doctorId, 'specialization', array('fields' => 'slugs'));
    $doctorData['specializations_slugs'] = $specializations;
    
    $result['doctors'][$doctorId] = $doctorData;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>

