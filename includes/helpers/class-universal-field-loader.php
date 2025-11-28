<?php
/**
 * Universal Field Loader for Yandex Feed Generator Pro
 * 
 * @package    Yandex_Feed_Generator_Pro
 * @subpackage Helpers
 * @since      4.18.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class YFGP_Universal_Field_Loader {
    
    public function get_all_fields_for_cpt($post_type) {
        $all_fields = array();
        
        // 1. WP Native fields
        $wp_fields = $this->get_wp_native_fields();
        $all_fields = array_merge($all_fields, $wp_fields);
        
        // 2. ACF fields
        if (function_exists('acf_get_field_groups')) {
            $acf_fields = $this->get_acf_fields($post_type);
            $all_fields = array_merge($all_fields, $acf_fields);
        }
        
        // 3. JetEngine meta fields
        if (function_exists('jet_engine') && jet_engine()->meta_boxes) {
            $je_meta_fields = $this->get_jetengine_meta_fields($post_type);
            $all_fields = array_merge($all_fields, $je_meta_fields);
        }
        
        // 4. Taxonomies
        $taxonomies = $this->get_taxonomies($post_type);
        $all_fields = array_merge($all_fields, $taxonomies);
        
        // Remove duplicates
        $all_fields = $this->remove_duplicate_fields($all_fields);
        
        return $all_fields;
    }
    
    protected function get_wp_native_fields() {
        return array(
            array('name' => 'post_title', 'label' => 'Post Title', 'type' => 'text', 'source' => 'wp_native'),
            array('name' => 'post_content', 'label' => 'Post Content', 'type' => 'textarea', 'source' => 'wp_native'),
            array('name' => 'post_excerpt', 'label' => 'Post Excerpt', 'type' => 'textarea', 'source' => 'wp_native'),
            array('name' => 'featured_image', 'label' => 'Featured Image', 'type' => 'image', 'source' => 'wp_native')
        );
    }
    
    protected function get_acf_fields($post_type) {
        $fields = array();
        $field_groups = acf_get_field_groups(array('post_type' => $post_type));
        foreach ($field_groups as $group) {
            $group_fields = acf_get_fields($group['key']);
            if ($group_fields) {
                foreach ($group_fields as $field) {
                    $fields[] = array('name' => $field['name'], 'label' => $field['label'] . ' (ACF)', 'type' => $field['type'], 'source' => 'acf');
                }
            }
        }
        return $fields;
    }
    
    protected function get_jetengine_meta_fields($post_type) {
        $fields = array();
        $meta_boxes = jet_engine()->meta_boxes->get_registered_fields();
        if (empty($meta_boxes)) return $fields;
        
        foreach ($meta_boxes as $meta_box) {
            if (isset($meta_box['args']['object_type']) && $meta_box['args']['object_type'] === 'post' &&
                isset($meta_box['args']['allowed_post_type']) &&
                in_array($post_type, (array)$meta_box['args']['allowed_post_type'])) {
                if (!empty($meta_box['meta_fields'])) {
                    foreach ($meta_box['meta_fields'] as $field) {
                        $fields[] = array('name' => $field['name'], 'label' => $field['title'] . ' (JetEngine)', 'type' => $field['type'], 'source' => 'jetengine_meta');
                    }
                }
            }
        }
        return $fields;
    }
    
    protected function get_taxonomies($post_type) {
        $fields = array();
        $taxonomies = get_object_taxonomies($post_type, 'objects');
        foreach ($taxonomies as $taxonomy) {
            $fields[] = array('name' => $taxonomy->name, 'label' => $taxonomy->label . ' (Taxonomy)', 'type' => 'taxonomy', 'source' => 'taxonomy');
        }
        return $fields;
    }
    
    protected function remove_duplicate_fields($fields) {
        $unique_fields = array();
        $seen_names = array();
        foreach ($fields as $field) {
            if (!in_array($field['name'], $seen_names)) {
                $unique_fields[] = $field;
                $seen_names[] = $field['name'];
            }
        }
        return $unique_fields;
    }
}