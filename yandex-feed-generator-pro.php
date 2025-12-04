<?php

/**

 * Plugin Name: Yandex Feed Generator Pro

 * Plugin URI: https://vityaz-sevastopol.ru

 * Description: Универсальный генератор YML фидов для Яндекс.Вебмастера с поддержкой ACF и JetEngine

 * Version: 4.20.2

 * Author: Vityaz Development Team

 * Author URI: https://vityaz-sevastopol.ru

 * License: GPL v2 or later

 * Text Domain: yandex-feed-generator

 * Domain Path: /languages

 */



// Security: Prevent direct access

if (!defined('ABSPATH')) {

    exit;

}



// Plugin constants

define('YFGP_VERSION', '5.0.0'); // v5.0.0: Major refactoring - removed deprecated methods, consolidated Field Mappers, cleaned up code

if (!defined('YFGP_PLUGIN_DIR')) {

    // v4.18.17: Check if plugin_dir_path function exists before calling

    if (function_exists('plugin_dir_path')) {

define('YFGP_PLUGIN_DIR', plugin_dir_path(__FILE__));

    } else {

        // Fallback: calculate path manually

        define('YFGP_PLUGIN_DIR', dirname(__FILE__) . '/');

    }

}

if (!defined('YFGP_PLUGIN_URL')) {

    if (function_exists('plugin_dir_url')) {

define('YFGP_PLUGIN_URL', plugin_dir_url(__FILE__));

    } else {

        // Fallback: calculate URL manually

        $plugin_dir = dirname(__FILE__);

        $wp_content_dir = dirname(dirname($plugin_dir));

        $wp_dir = dirname($wp_content_dir);

        $plugin_url = str_replace($wp_dir, '', $plugin_dir);

        define('YFGP_PLUGIN_URL', $plugin_url . '/');

    }

}

if (!defined('YFGP_PLUGIN_BASENAME')) {

    if (function_exists('plugin_basename')) {

define('YFGP_PLUGIN_BASENAME', plugin_basename(__FILE__));

    } else {

        // Fallback: calculate basename manually

        define('YFGP_PLUGIN_BASENAME', basename(dirname(__FILE__)) . '/' . basename(__FILE__));

    }

}



/**
 * Main Plugin Class
 */

class Yandex_Feed_Generator_Pro {

    

    /**
     * Singleton instance
     */

    private static $instance = null;

    

    /**
     * Get singleton instance
     */

    public static function get_instance() {

        if (null === self::$instance) {

            self::$instance = new self();

        }

        return self::$instance;

    }

    

    /**
     * Constructor
     */

    private function __construct() {

        $this->load_dependencies();

        $this->init_hooks();

    }

    

    /**
     * Load dependencies
     */

    private function load_dependencies() {

        // v4.18.22: Constants Class (magic numbers replacement)
        require_once YFGP_PLUGIN_DIR . 'includes/class-constants.php';

        // v4.18.22: Logger Class (centralized logging with aggregation)
        require_once YFGP_PLUGIN_DIR . 'includes/class-logger.php';

        // v4.18.22: Mapping Config Validator (mapping config validation, SQL injection protection)
        require_once YFGP_PLUGIN_DIR . 'includes/class-mapping-config-validator.php';

        // v4.18.22: Encoding Normalizer (UTF-8 normalization for Cyrillic)
        require_once YFGP_PLUGIN_DIR . 'includes/helpers/encoding-normalizer.php';
        
        // v4.20.0: JetEngine Helper (JetEngine API abstraction)
        require_once YFGP_PLUGIN_DIR . 'includes/helpers/class-jetengine-helper.php';
        
        // v4.20.0: ACF Compatibility Helper (ACF 6.2.6+ escape_html support)
        require_once YFGP_PLUGIN_DIR . 'includes/helpers/acf-compat.php';
        
        // v4.20.0: Relationship Prefetcher (batch relationships loading to solve N+1 query problem)
        require_once YFGP_PLUGIN_DIR . 'includes/class-relationship-prefetcher.php';
        
        // v4.20.0: Cache Warmer (cache preloading for first generation performance)
        require_once YFGP_PLUGIN_DIR . 'includes/class-cache-warmer.php';

        // v4.18.22: Data Sanitizer (data sanitization before XML/UI output)
        require_once YFGP_PLUGIN_DIR . 'includes/class-data-sanitizer.php';

        // v4.18.22: Post Batch Loader (batch post loading to prevent OOM)
        require_once YFGP_PLUGIN_DIR . 'includes/class-post-batch-loader.php';

        // v2 classes only

        require_once YFGP_PLUGIN_DIR . 'includes/class-feed-generator-v2.php';

        require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-v2.php';

        require_once YFGP_PLUGIN_DIR . 'includes/specialities-reference.php';

        require_once YFGP_PLUGIN_DIR . 'includes/class-cron-manager.php'; // v2.3.2: Cron Manager

        require_once YFGP_PLUGIN_DIR . 'includes/class-migration.php'; // v4.18.0: Migration Manager

        require_once YFGP_PLUGIN_DIR . 'includes/class-error-handler.php'; // v4.18.0: Error Handler

        // v4.18.21: Sentry Integration for error monitoring
        // Load Composer autoload for Sentry SDK (if installed)
        $vendor_autoload = YFGP_PLUGIN_DIR . 'vendor/autoload.php';
        if (file_exists($vendor_autoload)) {
            try {
                require_once $vendor_autoload;
            } catch (\Throwable $e) {
                // Ignore autoload errors to prevent plugin breakage
                if (function_exists('error_log') && defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('YFGP: Failed to load vendor/autoload.php: ' . $e->getMessage());
                }
            }
        }
        require_once YFGP_PLUGIN_DIR . 'includes/class-sentry-integration.php'; // v4.18.21: Sentry Integration

        require_once YFGP_PLUGIN_DIR . 'includes/class-entity-manager.php'; // v4.18.11: Entity Manager for extensibility

        // v4.18.17: Service Container and factories for DI (refactoring)

        require_once YFGP_PLUGIN_DIR . 'includes/class-service-container.php'; // v4.18.17: Service Container for DI

        require_once YFGP_PLUGIN_DIR . 'includes/service-factories.php'; // v4.18.17: Service Factories for service registration

        require_once YFGP_PLUGIN_DIR . 'admin/class-admin-page.php';

        // v5.1.0: AJAX Handlers (вынесены из главного файла)
        require_once YFGP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';
    }

    

    /**
     * Initialize hooks
     */

    private function init_hooks() {

        // v4.18.21: Initialize Sentry after full WordPress load
        // Using 'init' instead of 'plugins_loaded' for better safety
        add_action('init', array($this, 'init_sentry'), 20);

        // v4.18.0: ╨Т╤Л╨┐╨╛╨╗╨╜╨╡╨╜╨╕╨╡ ╨╝╨╕╨│╤А╨░╤Ж╨╕╨╕ CPT ╨╜╨░╤Б╤В╤А╨╛╨╡╨║ ╨┐╤А╨╕ ╨╕╨╜╨╕╤Ж╨╕╨░╨╗╨╕╨╖╨░╤Ж╨╕╨╕

        add_action('init', array($this, 'run_migration'), 5);

        

        // ╨Р╨║╤В╨╕╨▓╨░╤Ж╨╕╤П/╨┤╨╡╨░╨║╤В╨╕╨▓╨░╤Ж╨╕╤П

        register_activation_hook(__FILE__, array($this, 'activate'));

        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        

        // Admin panel

        if (is_admin() && class_exists('YFGP_Admin_Page')) {

            try {

                new YFGP_Admin_Page();

            } catch (Exception $e) {

                error_log('YFGP Error: ' . $e->getMessage());

            }

        }

        

        // v5.1.0: AJAX хуки вынесены в YFGP_Ajax_Handlers
        if (class_exists('YFGP_Ajax_Handlers')) {
            YFGP_Ajax_Handlers::get_instance($this);
        }

        

        // Cron ╤Е╤Г╨║╨╕

        add_action('yfgp_auto_update_feed', array($this, 'auto_update_feed'));

        

        // Cache invalidation ╨┤╨╗╤П reviews_total_count (v3.1.1)

        add_action('save_post_reviews', array($this, 'invalidate_reviews_cache'), 10, 1);

        add_action('delete_post', array($this, 'invalidate_reviews_cache_on_delete'), 10, 1);

    }

    

    /**

     * Plugin activation

     */

    public function activate() {

        // Create feeds directory

        $upload_dir = wp_upload_dir();

        $feed_dir = $upload_dir['basedir'] . '/feed';

        

        if (!file_exists($feed_dir)) {

            wp_mkdir_p($feed_dir);

        }

        

        // Default settings

        $default_settings = array(

            'company_name' => get_bloginfo('name'),

            'company_url' => get_site_url(),

            'company_email' => get_bloginfo('admin_email'),

            'feed_category' => 'doctors',

            'auto_update' => false,

            'update_interval' => 'daily',

        );

        

        add_option('yfgp_settings', $default_settings);

        add_option('yfgp_field_mapping', array());

        add_option('yfgp_feed_history', array());

        

        // Schedule auto-update

        if (!wp_next_scheduled('yfgp_auto_update_feed')) {

            wp_schedule_event(time(), 'daily', 'yfgp_auto_update_feed');

        }

    }

    

    /**

     * Plugin deactivation

     */

    public function deactivate() {

        // Remove scheduled hooks

        wp_clear_scheduled_hook('yfgp_auto_update_feed');

    }

    

    /**

     * ╨Т╤Л╨┐╨╛╨╗╨╜╨╡╨╜╨╕╨╡ ╨╝╨╕╨│╤А╨░╤Ж╨╕╨╕ CPT ╨╜╨░╤Б╤В╤А╨╛╨╡╨║

     * 

     * @since 4.18.0

     * @return void

     */

    /**
     * ╨Ш╨╜╨╕╤Ж╨╕╨░╨╗╨╕╨╖╨░╤Ж╨╕╤П Sentry Integration
     * 
     * @since 4.18.21
     * @return void
     */
    public function init_sentry(): void {
        // v4.18.21: ╨С╨╡╨╖╨╛╨┐╨░╤Б╨╜╨░╤П ╨╕╨╜╨╕╤Ж╨╕╨░╨╗╨╕╨╖╨░╤Ж╨╕╤П Sentry ╤Б ╨╛╨▒╤А╨░╨▒╨╛╤В╨║╨╛╨╣ ╨╛╤И╨╕╨▒╨╛╨║
        try {
            if (class_exists('YFGP_Sentry_Integration')) {
                $sentry = YFGP_Sentry_Integration::get_instance();
                $sentry->init();
            }
        } catch (\Throwable $e) {
            // ╨Ш╨│╨╜╨╛╤А╨╕╤А╤Г╨╡╨╝ ╨╛╤И╨╕╨▒╨║╨╕ ╨╕╨╜╨╕╤Ж╨╕╨░╨╗╨╕╨╖╨░╤Ж╨╕╨╕ Sentry, ╤З╤В╨╛╨▒╤Л ╨╜╨╡ ╨╗╨╛╨╝╨░╤В╤М ╨┐╨╗╨░╨│╨╕╨╜
            if (function_exists('error_log') && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('YFGP: Failed to initialize Sentry: ' . $e->getMessage());
            }
        }
    }

    public function run_migration(): void {

        $error_handler = YFGP_Error_Handler::get_instance();

        

        $error_handler->execute_safely(function() {

            $migration = YFGP_Migration::get_instance();

            

            // v4.18.8: ╨Ь╨╕╨│╤А╨░╤Ж╨╕╤П CPT settings

            $migrated = $migration->migrate_cpt_settings_option();

            if ($migrated) {

                error_log('[YFGP] Migration: CPT settings migrated successfully');

            }

            

            // v4.18.8: ╨Ь╨╕╨│╤А╨░╤Ж╨╕╤П V2 тЖТ V3 mapping (╨╡╤Б╨╗╨╕ ╨╜╤Г╨╢╨╜╨╛)

            if ($migration->needs_migration()) {

                $result = $migration->migrate_v2_to_v3();

                if ($result['success']) {

                    error_log('[YFGP] Migration: V2 тЖТ V3 mapping migrated successfully');

                } else {

                    // ╨а╨╡╨│╨╕╤Б╤В╤А╨╕╤А╤Г╨╡╨╝ ╨║╤А╨╕╤В╨╕╤З╨╜╤Г╤О ╨╛╤И╨╕╨▒╨║╤Г ╨┤╨╗╤П ╨╛╤В╨╛╨▒╤А╨░╨╢╨╡╨╜╨╕╤П ╨▓ admin_notices

                    $error_handler->register_critical_error(

                        '╨Ю╤И╨╕╨▒╨║╨░ ╨╝╨╕╨│╤А╨░╤Ж╨╕╨╕ V2 тЖТ V3: ' . ($result['message'] ?? '╨Э╨╡╨╕╨╖╨▓╨╡╤Б╤В╨╜╨░╤П ╨╛╤И╨╕╨▒╨║╨░'),

                        'error',

                        array('action' => 'migrate_v2_to_v3', 'details' => $result)

                    );

                }

            }

        }, array(

            'action' => 'run_migration',

            'hook' => 'init'

        ));

    }

    

    /**
     * v4.18.22: Security - Validates and sanitizes filename to prevent path traversal
     * 
     * @param string $filename Filename to validate
     * @return string Validated filename
     * @throws Exception If filename is invalid
     */
    /**
     * Validate feed filename to prevent path traversal attacks
     * 
     * @since 4.18.22: Enhanced security validation
     * @param string $filename Filename to validate
     * @return string Validated filename
     * @throws Exception If filename is invalid
     */
    private function validate_feed_filename($filename) {
        // v4.18.22: Security - Validate filename to prevent path traversal
        $filename = sanitize_file_name($filename);
        
        // CRITICAL: basename() prevents directory traversal
        $filename = basename($filename);

        // Additional validation: only allow .yml and .xml extensions
        $allowed_extensions = array('yml', 'xml');
        $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions, true)) {
            // If no extension or invalid, default to .yml
            $filename = pathinfo($filename, PATHINFO_FILENAME) . '.yml';
        }

        // Only allow letters, numbers, dashes, dots, and underscores
        $filename = preg_replace('/[^a-zA-Z0-9\-\._]+/', '', $filename);

        // Prevent empty filename
        if (empty($filename)) {
            throw new Exception('Invalid filename: empty after sanitization');
        }

        // Prevent hidden files (starting with dot)
        if (strpos($filename, '.') === 0) {
            throw new Exception('Invalid filename: cannot start with dot');
        }

        // Ensure valid extension
        $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_extensions, true)) {
            $filename = pathinfo($filename, PATHINFO_FILENAME) . '.yml';
        }

        // Ensure filename doesn't contain dangerous characters (double check)
        if (preg_match('/[^a-zA-Z0-9._-]/', $filename)) {
            throw new Exception('Invalid filename. Only alphanumeric characters, dots, underscores, and hyphens are allowed.');
        }

        return $filename;
    }

    public function save_feed_file(string $yml, string $filename = 'doctors.yml'): array {

        $upload_dir = wp_upload_dir();

        if (empty($upload_dir['basedir']) || empty($upload_dir['baseurl'])) {

            throw new Exception('╨Э╨╡ ╤Г╨┤╨░╨╗╨╛╤Б╤М ╨╛╨┐╤А╨╡╨┤╨╡╨╗╨╕╤В╤М ╨┤╨╕╤А╨╡╨║╤В╨╛╤А╨╕╤О ╨╖╨░╨│╤А╤Г╨╖╨╛╨║ WordPress');

        }



        // v4.19.2 FIX: Ensure feed directory has trailing slash for wp_tempnam()
        $feed_dir = trailingslashit($upload_dir['basedir']) . 'feed' . DIRECTORY_SEPARATOR;

        if (!file_exists($feed_dir) && !wp_mkdir_p($feed_dir)) {

            throw new Exception('╨Э╨╡ ╤Г╨┤╨░╨╗╨╛╤Б╤М ╤Б╨╛╨╖╨┤╨░╤В╤М ╨┤╨╕╤А╨╡╨║╤В╨╛╤А╨╕╤О ╤Д╨╕╨┤╨░: ' . $feed_dir);

        }

        // v4.18.22: Security - Validate and sanitize filename (path traversal protection)
        try {
            $filename = $this->validate_feed_filename($filename);
        } catch (Exception $e) {
            if (class_exists('YFGP_Logger')) {
                YFGP_Logger::get_instance()->error('YFGP Security: ' . $e->getMessage());
            } else {
                error_log('YFGP Security: ' . $e->getMessage());
            }
            throw new Exception('Invalid filename provided');
        }

        if (!function_exists('wp_tempnam')) {

            require_once ABSPATH . 'wp-admin/includes/file.php';

        }

        // Build full path (now safe - filename validated)
        $feed_path = trailingslashit($feed_dir) . $filename;
        
        // v4.18.22: Security - Additional path validation using realpath
        $upload_dir_real = realpath($upload_dir['basedir']);
        $feed_dir_real = realpath($feed_dir);
        
        if (!$feed_dir_real || !$upload_dir_real || strpos($feed_dir_real, $upload_dir_real) !== 0) {
            throw new Exception('Invalid feed directory path. Path traversal detected.');
        }
        
        // Final validation: ensure final path is within allowed directory
        $feed_path_real = realpath(dirname($feed_path));
        if (!$feed_path_real || $feed_path_real !== $feed_dir_real) {
            throw new Exception('Invalid feed file path. Path traversal detected.');
        }

        $temp_file = wp_tempnam($filename, $feed_dir);



        if (!$temp_file) {

            throw new Exception('╨Э╨╡ ╤Г╨┤╨░╨╗╨╛╤Б╤М ╤Б╨╛╨╖╨┤╨░╤В╤М ╨▓╤А╨╡╨╝╨╡╨╜╨╜╤Л╨╣ ╤Д╨░╨╣╨╗ ╨┤╨╗╤П ╤Д╨╕╨┤╨░');

        }



        $bytes_written = file_put_contents($temp_file, $yml);

        if ($bytes_written === false) {

            @unlink($temp_file);

            $last_error = error_get_last();

            if (!empty($last_error['message'])) {

                error_log('YFGP ERROR: file_put_contents temp failed: ' . $last_error['message']);

            }

            throw new Exception('╨Э╨╡ ╤Г╨┤╨░╨╗╨╛╤Б╤М ╤Б╨╛╤Е╤А╨░╨╜╨╕╤В╤М ╤Д╨░╨╣╨╗ ╤Д╨╕╨┤╨░: ' . $temp_file);

        }



        if (!@rename($temp_file, $feed_path)) {

            $copied = @copy($temp_file, $feed_path);

            @unlink($temp_file);

            if (!$copied) {

                throw new Exception('╨Э╨╡ ╤Г╨┤╨░╨╗╨╛╤Б╤М ╨┐╨╡╤А╨╡╨╝╨╡╤Б╤В╨╕╤В╤М ╨▓╤А╨╡╨╝╨╡╨╜╨╜╤Л╨╣ ╤Д╨░╨╣╨╗ ╤Д╨╕╨┤╨░ ╨▓ ╤Ж╨╡╨╗╨╡╨▓╤Г╤О ╨┤╨╕╤А╨╡╨║╤В╨╛╤А╨╕╤О');

            }

        }



        if (function_exists('chmod')) {

            @chmod($feed_path, 0644);

        }



        clearstatcache(true, $feed_path);



        $feed_mtime = file_exists($feed_path) ? filemtime($feed_path) : time();

        $generated_at = current_time('mysql');



        // Filename already validated and sanitized above (path traversal protection)
        $feed_url = trailingslashit($upload_dir['baseurl']) . 'feed/' . $filename;



        update_option('yfgp_feed_last_generated', array(

            'generated_at' => $generated_at,

            'mtime'        => $feed_mtime,

            'path'         => $feed_path,

            'bytes'        => $bytes_written,

        ));



        return array(

            'path'         => $feed_path,

            'url'          => $feed_url,

            'bytes'        => $bytes_written,

            'mtime'        => $feed_mtime,

            'generated_at' => $generated_at,

        );

    }


    /**

     * ╨Ш╨╜╨▓╨░╨╗╨╕╨┤╨░╤Ж╨╕╤П ╨║╤Н╤И╨░ reviews_total_count ╨┐╤А╨╕ ╤Б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╕ ╨╛╤В╨╖╤Л╨▓╨░

     * 

     * @param int $post_id ID ╨╛╤В╨╖╤Л╨▓╨░

     * @since 3.1.1

     */

    private function wrap_cdata_preview(string $text): string {
        // ╨г╨▒╨╕╤А╨░╨╡╨╝ ╨╗╨╕╤И╨╜╨╕╨╡ ╨┐╤А╨╛╨▒╨╡╨╗╤Л ╨╕ ╨┐╨╡╤А╨╡╨╜╨╛╤Б╤Л ╤Б╤В╤А╨╛╨║ ╨▓ ╨╜╨░╤З╨░╨╗╨╡ ╨╕ ╨║╨╛╨╜╤Ж╨╡
        $trimmed = trim($text);
        $safe = str_replace(']]>', ']]]]><![CDATA[>', $trimmed);
        return '<![CDATA[' . $safe . ']]>';

    }



    public function invalidate_reviews_cache($post_id) {

        if (get_post_type($post_id) !== 'reviews') {

            return;

        }

        

        $doctor_id = get_post_meta($post_id, 'doctor', true);

        if ($doctor_id) {

            delete_transient('yfgp_reviews_count_' . $doctor_id);

            error_log("YFGP: Cache invalidated for doctor {$doctor_id} (review saved/updated)");

        }

    }

    

    /**

     * ╨Ш╨╜╨▓╨░╨╗╨╕╨┤╨░╤Ж╨╕╤П ╨║╤Н╤И╨░ reviews_total_count ╨┐╤А╨╕ ╤Г╨┤╨░╨╗╨╡╨╜╨╕╨╕ ╨┐╨╛╤Б╤В╨░

     * 

     * @param int $post_id ID ╨┐╨╛╤Б╤В╨░

     * @since 3.1.1

     */

    public function invalidate_reviews_cache_on_delete($post_id) {

        // ╨Я╤А╨╛╨▓╨╡╤А╤П╨╡╨╝ ╨┤╨╛ ╤Г╨┤╨░╨╗╨╡╨╜╨╕╤П (post_type ╨╡╤Й╨╡ ╨┤╨╛╤Б╤В╤Г╨┐╨╡╨╜)

        if (get_post_type($post_id) !== 'reviews') {

            return;

        }

        

        $doctor_id = get_post_meta($post_id, 'doctor', true);

        if ($doctor_id) {

            delete_transient('yfgp_reviews_count_' . $doctor_id);

            error_log("YFGP: Cache invalidated for doctor {$doctor_id} (review deleted)");

        }

    }

}

/**
 * ╨а╨╡╨║╤Г╤А╤Б╨╕╨▓╨╜╨░╤П sanitization ╨┤╨╗╤П mapping array
 * 
 * @param mixed $data ╨Ф╨░╨╜╨╜╤Л╨╡ ╨┤╨╗╤П sanitization
 * @return mixed ╨б╨░╨╜╨╕╤В╨╕╨╖╨╕╤А╨╛╨▓╨░╨╜╨╜╤Л╨╡ ╨┤╨░╨╜╨╜╤Л╨╡
 * @since 4.20.0
 */
function yfgp_sanitize_mapping_array($data) {
    if (is_array($data)) {
        $sanitized = array();
        foreach ($data as $key => $value) {
            // ╨б╨░╨╜╨╕╤В╨╕╨╖╨╕╤А╤Г╨╡╨╝ ╨║╨╗╤О╤З
            $sanitized_key = sanitize_key($key);
            // ╨а╨╡╨║╤Г╤А╤Б╨╕╨▓╨╜╨╛ ╤Б╨░╨╜╨╕╤В╨╕╨╖╨╕╤А╤Г╨╡╨╝ ╨╖╨╜╨░╤З╨╡╨╜╨╕╨╡
            $sanitized[$sanitized_key] = yfgp_sanitize_mapping_array($value);
        }
        return $sanitized;
    } elseif (is_string($data)) {
        // ╨Ф╨╗╤П ╤Б╤В╤А╨╛╨║ ╨╕╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╨╝ sanitize_text_field ╨╕╨╗╨╕ wp_kses_post ╨▓ ╨╖╨░╨▓╨╕╤Б╨╕╨╝╨╛╤Б╤В╨╕ ╨╛╤В ╨║╨╛╨╜╤В╨╡╨║╤Б╤В╨░
        return sanitize_text_field($data);
    } elseif (is_numeric($data)) {
        return $data; // ╨з╨╕╤Б╨╗╨░ ╨╜╨╡ ╤В╤А╨╡╨▒╤Г╤О╤В sanitization
    } elseif (is_bool($data)) {
        return $data; // ╨С╤Г╨╗╨╡╨▓╤Л ╨╖╨╜╨░╤З╨╡╨╜╨╕╤П ╨╜╨╡ ╤В╤А╨╡╨▒╤Г╤О╤В sanitization
    } else {
        return null; // ╨Э╨╡╨╕╨╖╨▓╨╡╤Б╤В╨╜╤Л╨╣ ╤В╨╕╨┐ - ╨▓╨╛╨╖╨▓╤А╨░╤Й╨░╨╡╨╝ null
    }
}

/**

 * ╨Ш╨╜╨╕╤Ж╨╕╨░╨╗╨╕╨╖╨░╤Ж╨╕╤П ╨┐╨╗╨░╨│╨╕╨╜╨░

 */

function yfgp_init() {

    // v4.18.17: ╨Ъ╨╗╨░╤Б╤Б╤Л ╤Г╨╢╨╡ ╨╖╨░╨│╤А╤Г╨╢╨╡╨╜╤Л ╨┐╤А╨╕ ╨╖╨░╨│╤А╤Г╨╖╨║╨╡ ╨┐╨╗╨░╨│╨╕╨╜╨░ (╤Б╨╝. ╨║╨╛╨┤ ╨▓╤Л╤И╨╡)

    // ╨Я╤А╨╛╤Б╤В╨╛ ╨▓╨╛╨╖╨▓╤А╨░╤Й╨░╨╡╨╝ ╤Н╨║╨╖╨╡╨╝╨┐╨╗╤П╤А ╨┐╨╗╨░╨│╨╕╨╜╨░

    return Yandex_Feed_Generator_Pro::get_instance();

}



/**

 * ╨Ч╨░╨│╤А╤Г╨╖╨║╨░ ╨║╨╗╨░╤Б╤Б╨╛╨▓ ╨┐╨╗╨░╨│╨╕╨╜╨░

 */

function yfgp_load_classes() {

    // ╨Я╤А╨╛╨▓╨╡╤А╤П╨╡╨╝, ╤З╤В╨╛ WordPress ╨┐╨╛╨╗╨╜╨╛╤Б╤В╤М╤О ╨╖╨░╨│╤А╤Г╨╢╨╡╨╜

    if (!function_exists('get_option') || !function_exists('add_action')) {

        return; // WordPress ╨╡╤Й╤С ╨╜╨╡ ╨╖╨░╨│╤А╤Г╨╢╨╡╨╜

    }



    // v4.18.17: ╨Ч╨░╨│╤А╤Г╨╢╨░╨╡╨╝ Service Container ╨╕ service-factories ╤Б ╨╛╨▒╤А╨░╨▒╨╛╤В╨║╨╛╨╣ ╨╛╤И╨╕╨▒╨╛╨║

    // ╨Т╨Р╨Ц╨Э╨Ю: ╨Э╨╡ ╨┐╤А╨╡╤А╤Л╨▓╨░╨╡╨╝ ╨╖╨░╨│╤А╤Г╨╖╨║╤Г WordPress ╨┐╤А╨╕ ╨╛╤И╨╕╨▒╨║╨░╤Е, ╨┐╤А╨╛╤Б╤В╨╛ ╨╗╨╛╨│╨╕╤А╤Г╨╡╨╝

    try {

        if (!class_exists('YFGP_Service_Container')) {

            $file = YFGP_PLUGIN_DIR . 'includes/class-service-container.php';

            if (file_exists($file)) {

                require_once $file;

            } else {

                if (function_exists('error_log')) {

                    error_log('YFGP Error: class-service-container.php not found at ' . $file);

                }

                return;

            }

        }

        

        if (!function_exists('yfgp_register_services')) {

            $file = YFGP_PLUGIN_DIR . 'includes/service-factories.php';

            if (file_exists($file)) {

                try {

                    require_once $file;

                } catch (Throwable $e) {

                    if (function_exists('error_log')) {

                        error_log('YFGP Error loading service-factories.php: ' . $e->getMessage());

                        error_log('YFGP Stack trace: ' . $e->getTraceAsString());

                    }

                    return;

                }

            } else {

                if (function_exists('error_log')) {

                    error_log('YFGP Error: service-factories.php not found at ' . $file);

                }

                return;

            }

        }



        if (function_exists('yfgp_register_services') && class_exists('YFGP_Service_Container') && function_exists('get_option')) {

            try {

                $container = YFGP_Service_Container::get_instance();

                yfgp_register_services($container);

            } catch (Throwable $e) {

                if (function_exists('error_log')) {

                    error_log('YFGP Error during service registration: ' . $e->getMessage());

                    error_log('YFGP Stack trace: ' . $e->getTraceAsString());

                }

            }

        }

    } catch (Throwable $e) {

        if (function_exists('error_log')) {

            error_log('YFGP Error during class loading: ' . $e->getMessage());

            error_log('YFGP Stack trace: ' . $e->getTraceAsString());

        }

    }

}



// ╨Ч╨░╨┐╤Г╤Б╨║ ╨┐╨╗╨░╨│╨╕╨╜╨░

// v4.18.17: ╨Ч╨░╨│╤А╤Г╨╢╨░╨╡╨╝ ╨║╨╗╨░╤Б╤Б╤Л ╤З╨╡╤А╨╡╨╖ ╤Е╤Г╨║ 'plugins_loaded' ╤Б ╨▓╤Л╤Б╨╛╨║╨╕╨╝ ╨┐╤А╨╕╨╛╤А╨╕╤В╨╡╤В╨╛╨╝

// ╨н╤В╨╛ ╨│╨░╤А╨░╨╜╤В╨╕╤А╤Г╨╡╤В, ╤З╤В╨╛ WordPress ╨┐╨╛╨╗╨╜╨╛╤Б╤В╤М╤О ╨╖╨░╨│╤А╤Г╨╢╨╡╨╜ ╨┐╨╡╤А╨╡╨┤ ╨╖╨░╨│╤А╤Г╨╖╨║╨╛╨╣ ╨║╨╗╨░╤Б╤Б╨╛╨▓

if (function_exists('add_action')) {

    add_action('plugins_loaded', 'yfgp_load_classes', 1);

    add_action('plugins_loaded', 'yfgp_init', 2);

}

/**
 * Get default post type safely (universal, no hardcode)
 * 
 * @since 4.19.2
 * @return string Post type slug or empty string if not found
 */
function yfgp_get_default_post_type_safe(): string {
    // Try to get from settings first
    $settings = get_option('yfgp_settings', array());
    if (!empty($settings['post_type'])) {
        return $settings['post_type'];
    }
    
    // Try to get from mapping v3 (first tab key)
    $mapping = get_option('yfgp_field_mapping_v3', array());
    if (!empty($mapping) && is_array($mapping)) {
        $keys = array_keys($mapping);
        if (!empty($keys[0])) {
            return $keys[0];
        }
    }
    
    // Try to get from available CPTs (first one that exists)
    $all_cpts = get_post_types(array('_builtin' => false), 'objects');
    $exclude = array('revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 
                    'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 
                    'wp_navigation', 'acf-field-group', 'acf-field');
    
    foreach ($all_cpts as $cpt) {
        if (!in_array($cpt->name, $exclude)) {
            return $cpt->name;
        }
    }
    
    // Last resort: return empty string (code should handle this)
    error_log('YFGP v4.19.2 WARNING: Could not determine default post_type. Please configure post_type in settings.');
    return '';
}

