<?php

/**

 * Plugin Name: Yandex Feed Generator Pro

 * Plugin URI: https://vityaz-sevastopol.ru

 * Description: ╨г╨╜╨╕╨▓╨╡╤А╤Б╨░╨╗╤М╨╜╤Л╨╣ ╨│╨╡╨╜╨╡╤А╨░╤В╨╛╤А YML ╤Д╨╕╨┤╨╛╨▓ ╨┤╨╗╤П ╨п╨╜╨┤╨╡╨║╤Б.╨Т╨╡╨▒╨╝╨░╤Б╤В╨╡╤А╨░ ╤Б ╨┐╨╛╨┤╨┤╨╡╤А╨╢╨║╨╛╨╣ ACF ╨╕ JetEngine

 * Version: 4.20.1

 * Author: Vityaz Development Team

 * Author URI: https://vityaz-sevastopol.ru

 * License: GPL v2 or later

 * Text Domain: yandex-feed-generator

 * Domain Path: /languages

 */



// ╨Ч╨░╤Й╨╕╤В╨░ ╨╛╤В ╨┐╤А╤П╨╝╨╛╨│╨╛ ╨┤╨╛╤Б╤В╤Г╨┐╨░

if (!defined('ABSPATH')) {

    exit;

}



// ╨Ъ╨╛╨╜╤Б╤В╨░╨╜╤В╤Л ╨┐╨╗╨░╨│╨╕╨╜╨░

define('YFGP_VERSION', '4.20.1'); // v4.20.1: ╨Ш╤Б╨┐╤А╨░╨▓╨╗╨╡╨╜╨░ ╨┐╤А╨╛╨▓╨╡╤А╨║╨░ ╨╕╤Б╨║╨╗╤О╤З╨╡╨╜╨╕╨╣ ╨┤╨╗╤П ╤А╤Г╤З╨╜╤Л╤Е ╨╜╨░╤Б╤В╤А╨╛╨╡╨║ ╤Г╤Б╨╗╤Г╨│

if (!defined('YFGP_PLUGIN_DIR')) {

    // v4.18.17: ╨Я╤А╨╛╨▓╨╡╤А╤П╨╡╨╝, ╤З╤В╨╛ ╤Д╤Г╨╜╨║╤Ж╨╕╤П plugin_dir_path ╨┤╨╛╤Б╤В╤Г╨┐╨╜╨░ ╨┐╨╡╤А╨╡╨┤ ╨▓╤Л╨╖╨╛╨▓╨╛╨╝

    if (function_exists('plugin_dir_path')) {

define('YFGP_PLUGIN_DIR', plugin_dir_path(__FILE__));

    } else {

        // Fallback: ╨▓╤Л╤З╨╕╤Б╨╗╤П╨╡╨╝ ╨┐╤Г╤В╤М ╨▓╤А╤Г╤З╨╜╤Г╤О

        define('YFGP_PLUGIN_DIR', dirname(__FILE__) . '/');

    }

}

if (!defined('YFGP_PLUGIN_URL')) {

    if (function_exists('plugin_dir_url')) {

define('YFGP_PLUGIN_URL', plugin_dir_url(__FILE__));

    } else {

        // Fallback: ╨▓╤Л╤З╨╕╤Б╨╗╤П╨╡╨╝ URL ╨▓╤А╤Г╤З╨╜╤Г╤О

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

        // Fallback: ╨▓╤Л╤З╨╕╤Б╨╗╤П╨╡╨╝ basename ╨▓╤А╤Г╤З╨╜╤Г╤О

        define('YFGP_PLUGIN_BASENAME', basename(dirname(__FILE__)) . '/' . basename(__FILE__));

    }

}



/**

 * ╨У╨╗╨░╨▓╨╜╤Л╨╣ ╨║╨╗╨░╤Б╤Б ╨┐╨╗╨░╨│╨╕╨╜╨░

 */

class Yandex_Feed_Generator_Pro {

    

    /**

     * ╨Х╨┤╨╕╨╜╤Б╤В╨▓╨╡╨╜╨╜╤Л╨╣ ╤Н╨║╨╖╨╡╨╝╨┐╨╗╤П╤А ╨║╨╗╨░╤Б╤Б╨░

     */

    private static $instance = null;

    

    /**

     * ╨Я╨╛╨╗╤Г╤З╨╕╤В╤М ╤Н╨║╨╖╨╡╨╝╨┐╨╗╤П╤А ╨║╨╗╨░╤Б╤Б╨░

     */

    public static function get_instance() {

        if (null === self::$instance) {

            self::$instance = new self();

        }

        return self::$instance;

    }

    

    /**

     * ╨Ъ╨╛╨╜╤Б╤В╤А╤Г╨║╤В╨╛╤А

     */

    private function __construct() {

        $this->load_dependencies();

        $this->init_hooks();

    }

    

    /**

     * ╨Ч╨░╨│╤А╤Г╨╖╨║╨░ ╨╖╨░╨▓╨╕╤Б╨╕╨╝╨╛╤Б╤В╨╡╨╣

     */

    private function load_dependencies() {

        // v4.18.22: Constants Class (╨╖╨░╨╝╨╡╨╜╨░ ╨╝╨░╨│╨╕╤З╨╡╤Б╨║╨╕╤Е ╤З╨╕╤Б╨╡╨╗/╤Б╤В╤А╨╛╨║)
        require_once YFGP_PLUGIN_DIR . 'includes/class-constants.php';

        // v4.18.22: Logger Class (╤Ж╨╡╨╜╤В╤А╨░╨╗╨╕╨╖╨╛╨▓╨░╨╜╨╜╨╛╨╡ ╨╗╨╛╨│╨╕╤А╨╛╨▓╨░╨╜╨╕╨╡ ╤Б ╨░╨│╤А╨╡╨│╨░╤Ж╨╕╨╡╨╣)
        require_once YFGP_PLUGIN_DIR . 'includes/class-logger.php';

        // v4.18.22: Mapping Config Validator (╨▓╨░╨╗╨╕╨┤╨░╤Ж╨╕╤П ╨║╨╛╨╜╤Д╨╕╨│╤Г╤А╨░╤Ж╨╕╨╕ ╨╝╨░╨┐╨┐╨╕╨╜╨│╨░, SQL injection protection)
        require_once YFGP_PLUGIN_DIR . 'includes/class-mapping-config-validator.php';

        // v4.18.22: Encoding Normalizer (╨╜╨╛╤А╨╝╨░╨╗╨╕╨╖╨░╤Ж╨╕╤П UTF-8 ╨┤╨╗╤П ╨║╨╕╤А╨╕╨╗╨╗╨╕╤Ж╤Л)
        require_once YFGP_PLUGIN_DIR . 'includes/helpers/encoding-normalizer.php';
        
        // v4.20.0: JetEngine Helper (╨░╨▒╤Б╤В╤А╨░╨║╤Ж╨╕╤П ╨╜╨░╨┤ JetEngine API)
        require_once YFGP_PLUGIN_DIR . 'includes/helpers/class-jetengine-helper.php';
        
        // v4.20.0: ACF Compatibility Helper (╨┐╨╛╨┤╨┤╨╡╤А╨╢╨║╨░ ACF 6.2.6+ escape_html)
        require_once YFGP_PLUGIN_DIR . 'includes/helpers/acf-compat.php';
        
        // v4.20.0: Relationship Prefetcher (batch ╨╖╨░╨│╤А╤Г╨╖╨║╨░ relationships ╨┤╨╗╤П ╤А╨╡╤И╨╡╨╜╨╕╤П N+1 query problem)
        require_once YFGP_PLUGIN_DIR . 'includes/class-relationship-prefetcher.php';
        
        // v4.20.0: Cache Warmer (╨┐╤А╨╡╨┤╨╖╨░╨│╤А╤Г╨╖╨║╨░ ╨║╤Н╤И╨╡╨╣ ╨┤╨╗╤П ╤Г╨╗╤Г╤З╤И╨╡╨╜╨╕╤П ╨┐╤А╨╛╨╕╨╖╨▓╨╛╨┤╨╕╤В╨╡╨╗╤М╨╜╨╛╤Б╤В╨╕ ╨┐╨╡╤А╨▓╨╛╨╣ ╨│╨╡╨╜╨╡╤А╨░╤Ж╨╕╨╕)
        require_once YFGP_PLUGIN_DIR . 'includes/class-cache-warmer.php';

        // v4.18.22: Data Sanitizer (╤Б╨░╨╜╨╕╤В╨╕╨╖╨░╤Ж╨╕╤П ╨┤╨░╨╜╨╜╤Л╤Е ╨┐╨╡╤А╨╡╨┤ ╨▓╤Л╨▓╨╛╨┤╨╛╨╝ ╨▓ XML/UI)
        require_once YFGP_PLUGIN_DIR . 'includes/class-data-sanitizer.php';

        // v4.18.22: Post Batch Loader (╨┐╨░╨║╨╡╤В╨╜╨░╤П ╨╖╨░╨│╤А╤Г╨╖╨║╨░ ╨┐╨╛╤Б╤В╨╛╨▓ ╨┤╨╗╤П ╨┐╤А╨╡╨┤╨╛╤В╨▓╤А╨░╤Й╨╡╨╜╨╕╤П OOM)
        require_once YFGP_PLUGIN_DIR . 'includes/class-post-batch-loader.php';

        // ╨в╨╛╨╗╤М╨║╨╛ v2 ╨▓╨╡╤А╤Б╨╕╨╕ ╨║╨╗╨░╤Б╤Б╨╛╨▓

        require_once YFGP_PLUGIN_DIR . 'includes/class-feed-generator-v2.php';

        require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-v2.php';

        require_once YFGP_PLUGIN_DIR . 'includes/specialities-reference.php';

        require_once YFGP_PLUGIN_DIR . 'includes/class-cron-manager.php'; // v2.3.2: ╨Я╨╛╨┤╨║╨╗╤О╤З╨╡╨╜╨╕╨╡ Cron Manager

// DISABLED v4.18.0-hotfix1: Unused class causing headers warnings -         require_once YFGP_PLUGIN_DIR . 'includes/helpers/class-universal-field-loader.php'; // v4.18.0: Universal Field Loader

        require_once YFGP_PLUGIN_DIR . 'includes/class-migration.php'; // v4.18.0: Migration Manager

        require_once YFGP_PLUGIN_DIR . 'includes/class-error-handler.php'; // v4.18.0: Error Handler

        // v4.18.21: Sentry Integration ╨┤╨╗╤П ╨╝╨╛╨╜╨╕╤В╨╛╤А╨╕╨╜╨│╨░ ╨╛╤И╨╕╨▒╨╛╨║
        // ╨Ч╨░╨│╤А╤Г╨╢╨░╨╡╨╝ Composer autoload ╨┤╨╗╤П Sentry SDK (╨╡╤Б╨╗╨╕ ╤Г╤Б╤В╨░╨╜╨╛╨▓╨╗╨╡╨╜)
        $vendor_autoload = YFGP_PLUGIN_DIR . 'vendor/autoload.php';
        if (file_exists($vendor_autoload)) {
            try {
                require_once $vendor_autoload;
            } catch (\Throwable $e) {
                // ╨Ш╨│╨╜╨╛╤А╨╕╤А╤Г╨╡╨╝ ╨╛╤И╨╕╨▒╨║╨╕ ╨╖╨░╨│╤А╤Г╨╖╨║╨╕ autoload, ╤З╤В╨╛╨▒╤Л ╨╜╨╡ ╨╗╨╛╨╝╨░╤В╤М ╨┐╨╗╨░╨│╨╕╨╜
                if (function_exists('error_log') && defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('YFGP: Failed to load vendor/autoload.php: ' . $e->getMessage());
                }
            }
        }
        require_once YFGP_PLUGIN_DIR . 'includes/class-sentry-integration.php'; // v4.18.21: Sentry Integration

        require_once YFGP_PLUGIN_DIR . 'includes/class-entity-manager.php'; // v4.18.11: Entity Manager ╨┤╨╗╤П ╤А╨░╤Б╤И╨╕╤А╤П╨╡╨╝╨╛╤Б╤В╨╕

        // v4.18.17: Service Container ╨╕ service-factories ╨┤╨╗╤П DI (╤А╨╡╤Д╨░╨║╤В╨╛╤А╨╕╨╜╨│)

        require_once YFGP_PLUGIN_DIR . 'includes/class-service-container.php'; // v4.18.17: Service Container ╨┤╨╗╤П DI

        require_once YFGP_PLUGIN_DIR . 'includes/service-factories.php'; // v4.18.17: Service Factories ╨┤╨╗╤П ╤А╨╡╨│╨╕╤Б╤В╤А╨░╤Ж╨╕╨╕ ╤Б╨╡╤А╨▓╨╕╤Б╨╛╨▓

        require_once YFGP_PLUGIN_DIR . 'admin/class-admin-page.php';

    }

    

    /**

     * ╨Ш╨╜╨╕╤Ж╨╕╨░╨╗╨╕╨╖╨░╤Ж╨╕╤П ╤Е╤Г╨║╨╛╨▓

     */

    private function init_hooks() {

        // v4.18.21: ╨Ш╨╜╨╕╤Ж╨╕╨░╨╗╨╕╨╖╨░╤Ж╨╕╤П Sentry ╨┐╨╛╤Б╨╗╨╡ ╨┐╨╛╨╗╨╜╨╛╨╣ ╨╖╨░╨│╤А╤Г╨╖╨║╨╕ WordPress
        // ╨Ш╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╨╝ 'init' ╨▓╨╝╨╡╤Б╤В╨╛ 'plugins_loaded' ╨┤╨╗╤П ╨▒╨╛╨╗╤М╤И╨╡╨╣ ╨▒╨╡╨╖╨╛╨┐╨░╤Б╨╜╨╛╤Б╤В╨╕
        add_action('init', array($this, 'init_sentry'), 20);

        // v4.18.0: ╨Т╤Л╨┐╨╛╨╗╨╜╨╡╨╜╨╕╨╡ ╨╝╨╕╨│╤А╨░╤Ж╨╕╨╕ CPT ╨╜╨░╤Б╤В╤А╨╛╨╡╨║ ╨┐╤А╨╕ ╨╕╨╜╨╕╤Ж╨╕╨░╨╗╨╕╨╖╨░╤Ж╨╕╨╕

        add_action('init', array($this, 'run_migration'), 5);

        

        // ╨Р╨║╤В╨╕╨▓╨░╤Ж╨╕╤П/╨┤╨╡╨░╨║╤В╨╕╨▓╨░╤Ж╨╕╤П

        register_activation_hook(__FILE__, array($this, 'activate'));

        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        

        // ╨Р╨┤╨╝╨╕╨╜-╨┐╨░╨╜╨╡╨╗╤М

        if (is_admin() && class_exists('YFGP_Admin_Page')) {

            try {

                new YFGP_Admin_Page();

            } catch (Exception $e) {

                error_log('YFGP Error: ' . $e->getMessage());

            }

        }

        

        // AJAX ╤Е╤Г╨║╨╕

        add_action('wp_ajax_yfgp_generate_feed', array($this, 'ajax_generate_feed'));

        add_action('wp_ajax_yfgp_get_fields', array($this, 'ajax_get_fields'));

        add_action('wp_ajax_yfgp_save_mapping', array($this, 'ajax_save_mapping'));

        add_action('wp_ajax_yfgp_get_mapping', array($this, 'ajax_get_mapping'));

        add_action('wp_ajax_yfgp_get_posts_list', array($this, 'ajax_get_posts_list')); // v4.11.0: Test Preview post selector

        add_action('wp_ajax_yfgp_test_mapping', array($this, 'ajax_test_mapping'));

        add_action('wp_ajax_yfgp_save_edited_xml', array($this, 'ajax_save_edited_xml'));

        add_action('wp_ajax_yfgp_export_config', array($this, 'ajax_export_config'));

        add_action('wp_ajax_yfgp_import_config', array($this, 'ajax_import_config'));

        add_action('wp_ajax_yfgp_apply_template', array($this, 'ajax_apply_template'));

        add_action('wp_ajax_yfgp_get_terms', array($this, 'ajax_get_terms'));

        add_action('wp_ajax_yfgp_validate_feed', array($this, 'ajax_validate_feed')); // v4.18.0: ╨Т╨░╨╗╨╕╨┤╨░╤Ж╨╕╤П ╤Д╨╕╨┤╨░

        

        // Cron ╤Е╤Г╨║╨╕

        add_action('yfgp_auto_update_feed', array($this, 'auto_update_feed'));

        

        // Cache invalidation ╨┤╨╗╤П reviews_total_count (v3.1.1)

        add_action('save_post_reviews', array($this, 'invalidate_reviews_cache'), 10, 1);

        add_action('delete_post', array($this, 'invalidate_reviews_cache_on_delete'), 10, 1);

    }

    

    /**

     * ╨Р╨║╤В╨╕╨▓╨░╤Ж╨╕╤П ╨┐╨╗╨░╨│╨╕╨╜╨░

     */

    public function activate() {

        // ╨б╨╛╨╖╨┤╨░╨╜╨╕╨╡ ╨┐╨░╨┐╨║╨╕ ╨┤╨╗╤П ╤Д╨╕╨┤╨╛╨▓

        $upload_dir = wp_upload_dir();

        $feed_dir = $upload_dir['basedir'] . '/feed';

        

        if (!file_exists($feed_dir)) {

            wp_mkdir_p($feed_dir);

        }

        

        // ╨Э╨░╤Б╤В╤А╨╛╨╣╨║╨╕ ╨┐╨╛ ╤Г╨╝╨╛╨╗╤З╨░╨╜╨╕╤О

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

        

        // ╨Я╨╗╨░╨╜╨╕╤А╨╛╨▓╨░╨╜╨╕╨╡ ╨░╨▓╤В╨╛╨╛╨▒╨╜╨╛╨▓╨╗╨╡╨╜╨╕╤П

        if (!wp_next_scheduled('yfgp_auto_update_feed')) {

            wp_schedule_event(time(), 'daily', 'yfgp_auto_update_feed');

        }

    }

    

    /**

     * ╨Ф╨╡╨░╨║╤В╨╕╨▓╨░╤Ж╨╕╤П ╨┐╨╗╨░╨│╨╕╨╜╨░

     */

    public function deactivate() {

        // ╨г╨┤╨░╨╗╨╡╨╜╨╕╨╡ ╨╖╨░╨┐╨╗╨░╨╜╨╕╤А╨╛╨▓╨░╨╜╨╜╤Л╤Е ╨╖╨░╨┤╨░╤З

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

     * AJAX: ╨│╨╡╨╜╨╡╤А╨░╤Ж╨╕╤П ╤Д╨╕╨┤╨░

     */

    public function ajax_generate_feed() {

        $error_handler = YFGP_Error_Handler::get_instance();

        

        try {

        // v4.18.13: ╨Х╨┤╨╕╨╜╤Л╨╣ ╤Б╤В╨░╨╜╨┤╨░╤А╤В nonce ╨┤╨╗╤П ╨▓╤Б╨╡╤Е AJAX handlers

        check_ajax_referer('yfgp_ajax_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

                $error_handler->handle_ajax_error('╨Э╨╡╨┤╨╛╤Б╤В╨░╤В╨╛╤З╨╜╨╛ ╨┐╤А╨░╨▓', array('action' => 'ajax_generate_feed'));

                return;

        }

        

        // v4.18.22: Security - Whitelisting ╨┤╨╗╤П post_type (SQL injection protection)
        $settings = get_option('yfgp_settings', array());
        $allowed_post_types = array();

        // Build whitelist from settings
        if (!empty($settings['post_type'])) {
            $allowed_post_types[] = sanitize_key($settings['post_type']);
        }
        if (!empty($settings['cpt_clinics'])) {
            $allowed_post_types[] = sanitize_key($settings['cpt_clinics']);
        }
        if (!empty($settings['cpt_services'])) {
            $allowed_post_types[] = sanitize_key($settings['cpt_services']);
        }

        // Fallback to default if no settings
        if (empty($allowed_post_types)) {
            $allowed_post_types = array('doctors', 'clinics', 'services');
        }

        $post_type = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : 'doctors';

        // Validate post type against whitelist
        if (!in_array($post_type, $allowed_post_types, true)) {
            if (class_exists('YFGP_Logger')) {
                YFGP_Logger::get_instance()->warning('YFGP Security: Invalid post_type attempted: ' . $post_type);
            }
            $error_handler->handle_ajax_error('Invalid post type: ' . esc_html($post_type), array('action' => 'ajax_generate_feed'));
            return;
        }

        $preview_only = isset($_POST['preview_only']) && $_POST['preview_only'] === 'true';

        

            // ╨Т╤Л╨▒╨╕╤А╨░╨╡╨╝ ╨│╨╡╨╜╨╡╤А╨░╤В╨╛╤А ╨▓ ╨╖╨░╨▓╨╕╤Б╨╕╨╝╨╛╤Б╤В╨╕ ╨╛╤В ╨╜╨░╤Б╤В╤А╨╛╨╡╨║

            $feed_format = $settings['feed_format'] ?? 'v2';

            

            if ($feed_format === 'v2') {

                $generator = new YFGP_Feed_Generator_V2();

            } else {

                $generator = new YFGP_Feed_Generator();

            }

            

            $yml = $generator->generate($post_type);

            

            if (!$preview_only) {

                $feed_result = $this->save_feed_file($yml, 'doctors.yml');

                $feed_url = $feed_result['url'] . '?v=' . time();

                

                $error_handler->handle_ajax_success(array(

                    'message'       => 'Feed generated and published',

                    'file_url'      => $feed_url,

                    'yml'           => $yml,

                    'generated_at'  => $feed_result['generated_at'] ?? current_time('mysql'),

                    'mtime'         => $feed_result['mtime'] ?? null,

                    'bytes_written' => $feed_result['bytes'] ?? null,

                ));

            } else {

                $error_handler->handle_ajax_success(array(

                    'yml' => $yml

                ));

            }

        } catch (Exception $e) {

            $error_handler->handle_ajax_error($e, array('action' => 'ajax_generate_feed', 'post_type' => $post_type ?? ''));

        } catch (Throwable $e) {

            $error_handler->handle_ajax_error($e, array('action' => 'ajax_generate_feed', 'post_type' => $post_type ?? ''));

        }

    }

    

    /**

     * AJAX: ╨Я╨╛╨╗╤Г╤З╨╡╨╜╨╕╨╡ ╨┐╨╛╨╗╨╡╨╣ CPT

     */

    public function ajax_get_fields() {

        // v4.18.13: ╨Х╨┤╨╕╨╜╤Л╨╣ ╤Б╤В╨░╨╜╨┤╨░╤А╤В nonce ╨┤╨╗╤П ╨▓╤Б╨╡╤Е AJAX handlers

        check_ajax_referer('yfgp_ajax_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            $this->send_json_error_no_bom('╨Э╨╡╨┤╨╛╤Б╤В╨░╤В╨╛╤З╨╜╨╛ ╨┐╤А╨░╨▓');

        }

        

        $post_type = sanitize_text_field($_POST['post_type'] ?? '');

        

        // ╨Ю╤В╨╗╨░╨┤╨╛╤З╨╜╨░╤П ╨╕╨╜╤Д╨╛╤А╨╝╨░╤Ж╨╕╤П

        error_log("ЁЯФН YFGP: ╨Ч╨░╨┐╤А╨╛╤Б ╨┐╨╛╨╗╨╡╨╣ ╨┤╨╗╤П ╤В╨╕╨┐╨░: {$post_type}");

        

        $mapper = new YFGP_Field_Mapper_V2();

        $fields = $mapper->get_available_fields($post_type);

        

        // ╨Ю╤В╨╗╨░╨┤╨╛╤З╨╜╨░╤П ╨╕╨╜╤Д╨╛╤А╨╝╨░╤Ж╨╕╤П

        $relations_count = isset($fields['relations']) ? count($fields['relations']) : 0;

        error_log("ЁЯУК YFGP: ╨Э╨░╨╣╨┤╨╡╨╜╨╛ ╤Б╨▓╤П╨╖╨╡╨╣ ╨┤╨╗╤П {$post_type}: {$relations_count}");

        

        if (isset($fields['relations'])) {

            foreach ($fields['relations'] as $key => $relation) {

                error_log("ЁЯФЧ YFGP: ╨б╨▓╤П╨╖╤М {$key}: {$relation['label']} тЖТ " . implode(', ', $relation['post_type']));

            }

        }

        

        $this->send_json_success_no_bom($fields);

    }

    

    /**

     * AJAX: ╨б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╡ ╨╝╨░╨┐╨┐╨╕╨╜╨│╨░

     */

    public function ajax_save_mapping() {

        // v4.18.13: ╨Х╨┤╨╕╨╜╤Л╨╣ ╤Б╤В╨░╨╜╨┤╨░╤А╤В nonce ╨┤╨╗╤П ╨▓╤Б╨╡╤Е AJAX handlers

        check_ajax_referer('yfgp_ajax_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            wp_send_json_error('╨Э╨╡╨┤╨╛╤Б╤В╨░╤В╨╛╤З╨╜╨╛ ╨┐╤А╨░╨▓');

        }

        

        // v4.20.0: Security - Sanitization ╨┤╨╗╤П mapping array
        $mapping_raw = $_POST['mapping'] ?? array();
        
        // ╨Я╤А╨╕╨╝╨╡╨╜╤П╨╡╨╝ wp_unslash() ╨┤╨╗╤П ╤Г╨┤╨░╨╗╨╡╨╜╨╕╤П magic quotes
        $mapping_raw = wp_unslash($mapping_raw);
        
        // ╨Я╤А╨╛╨▓╨╡╤А╨║╨░ ╤В╨╕╨┐╨░ - ╨┤╨╛╨╗╨╢╨╡╨╜ ╨▒╤Л╤В╤М array
        if (!is_array($mapping_raw)) {
            wp_send_json_error('Invalid mapping data type');
            return;
        }
        
        // ╨а╨╡╨║╤Г╤А╤Б╨╕╨▓╨╜╨░╤П sanitization ╤З╨╡╤А╨╡╨╖ helper ╤Д╤Г╨╜╨║╤Ж╨╕╤О
        $mapping = yfgp_sanitize_mapping_array($mapping_raw);

        // v4.18.22: Security - Check size limit (DoS protection)
        $mapping_json = json_encode($mapping);
        if (class_exists('YFGP_Constants')) {
            $max_size = YFGP_Constants::MAX_MAPPING_SIZE;
        } else {
            $max_size = 1048576; // 1MB fallback
        }
        
        if (strlen($mapping_json) > $max_size) {
            if (class_exists('YFGP_Logger')) {
                YFGP_Logger::get_instance()->warning('YFGP Security: Mapping data too large: ' . strlen($mapping_json) . ' bytes');
            }
            wp_send_json_error('╨а╨░╨╖╨╝╨╡╤А ╨┤╨░╨╜╨╜╤Л╤Е ╨╝╨░╨┐╨┐╨╕╨╜╨│╨░ ╨┐╤А╨╡╨▓╤Л╤И╨░╨╡╤В ╨╝╨░╨║╤Б╨╕╨╝╨░╨╗╤М╨╜╤Л╨╣ ╨╗╨╕╨╝╨╕╤В (' . round($max_size / 1024 / 1024, 2) . ' MB)');
            return;
        }

        // v4.18.22: Security - Validate mapping configuration
        if (class_exists('YFGP_Mapping_Config_Validator')) {
            $validator = new YFGP_Mapping_Config_Validator();
            if (!$validator->validate_mapping_array($mapping)) {
                if (class_exists('YFGP_Logger')) {
                    YFGP_Logger::get_instance()->error('YFGP Security: Mapping validation failed');
                }
                wp_send_json_error('╨Ю╤И╨╕╨▒╨║╨░ ╨▓╨░╨╗╨╕╨┤╨░╤Ж╨╕╨╕ ╨╝╨░╨┐╨┐╨╕╨╜╨│╨░. ╨Я╤А╨╛╨▓╨╡╤А╤М╤В╨╡ ╨║╨╛╨╜╤Д╨╕╨│╤Г╤А╨░╤Ж╨╕╤О ╨┐╨╛╨╗╨╡╨╣.');
                return;
            }
        }

        // v4.18.22: Security - Sanitize mapping array (XSS protection)
        if (class_exists('YFGP_Data_Sanitizer')) {
            $sanitizer = new YFGP_Data_Sanitizer();
            $mapping = $sanitizer->sanitize($mapping);
        }

        // Use correct option name (v3 or legacy)
        $option_name = class_exists('YFGP_Constants') ? YFGP_Constants::OPTION_MAPPING : 'yfgp_field_mapping_v3';
        update_option($option_name, $mapping);

        

        wp_send_json_success('╨Ь╨░╨┐╨┐╨╕╨╜╨│ ╤Б╨╛╤Е╤А╨░╨╜╤С╨╜');

    }

    

    /**

     * AJAX: ╨Я╨╛╨╗╤Г╤З╨╡╨╜╨╕╨╡ ╤В╨╡╨║╤Г╤Й╨╡╨│╨╛ ╨╝╨░╨┐╨┐╨╕╨╜╨│╨░

     */

    public function ajax_get_mapping() {

        // v4.18.13: ╨Х╨┤╨╕╨╜╤Л╨╣ ╤Б╤В╨░╨╜╨┤╨░╤А╤В nonce ╨┤╨╗╤П ╨▓╤Б╨╡╤Е AJAX handlers

        check_ajax_referer('yfgp_ajax_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            wp_send_json_error('╨Э╨╡╨┤╨╛╤Б╤В╨░╤В╨╛╤З╨╜╨╛ ╨┐╤А╨░╨▓');

        }

        

        $mapping = get_option('yfgp_field_mapping', array());

        

        wp_send_json_success($mapping);

    }

    

    /**

     * AJAX: ╨Я╨╛╨╗╤Г╤З╨╕╤В╤М ╤Б╨┐╨╕╤Б╨╛╨║ ╨┐╨╛╤Б╤В╨╛╨▓ ╨┤╨╗╤П Test Preview

     * @since v4.11.0

     */

    public function ajax_get_posts_list() {

        // v4.18.13: ╨Х╨┤╨╕╨╜╤Л╨╣ ╤Б╤В╨░╨╜╨┤╨░╤А╤В nonce ╨┤╨╗╤П ╨▓╤Б╨╡╤Е AJAX handlers

        check_ajax_referer('yfgp_ajax_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            wp_send_json_error('╨Э╨╡╨┤╨╛╤Б╤В╨░╤В╨╛╤З╨╜╨╛ ╨┐╤А╨░╨▓');

        }

        

        $tab_type = sanitize_text_field($_POST['tab_type'] ?? yfgp_get_default_post_type_safe());
        $settings = get_option('yfgp_settings', array());
        
        // v4.18.21: ╨Ш╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╨╝ ╤Г╨╜╨╕╨▓╨╡╤А╤Б╨░╨╗╤М╨╜╤Г╤О ╤Д╤Г╨╜╨║╤Ж╨╕╤О ╨┤╨╗╤П ╨╛╨┐╤А╨╡╨┤╨╡╨╗╨╡╨╜╨╕╤П post_type
        $post_type = $this->get_post_type_for_tab($tab_type, $settings);

        

        // ╨Я╨╛╨╗╤Г╤З╨░╨╡╨╝ ╨┐╨╛╤Б╤В╤Л

        $posts = get_posts(array(

            'post_type' => $post_type,

            'post_status' => 'publish',

            'posts_per_page' => 100,

            'orderby' => 'title',

            'order' => 'ASC'

        ));

        

        $posts_list = array();

        foreach ($posts as $post) {

            $posts_list[] = array(

                'id' => $post->ID,

                'title' => $post->post_title

            );

        }

        

        wp_send_json_success(array(

            'posts' => $posts_list,

            'post_type' => $post_type,

            'tab_type' => $tab_type

        ));

    }

    /**
     * ╨Ю╨┐╤А╨╡╨┤╨╡╨╗╨╕╤В╤М post_type ╨┤╨╗╤П ╤В╨░╨▒╨░ (╤Г╨╜╨╕╨▓╨╡╤А╤Б╨░╨╗╤М╨╜╤Л╨╣ ╨╝╨╡╤В╨╛╨┤)
     * 
     * @param string $tab_type ╨в╨╕╨┐ ╤В╨░╨▒╨░ (doctors/clinics/services/offers)
     * @param array $settings ╨Э╨░╤Б╤В╤А╨╛╨╣╨║╨╕ ╨┐╨╗╨░╨│╨╕╨╜╨░
     * @return string Post type
     */
    private function get_post_type_for_tab($tab_type, $settings) {
        // v4.19.2: Universal - no hardcode, use settings or empty string
        switch ($tab_type) {
            case 'doctors':
                return $settings['post_type'] ?? yfgp_get_default_post_type_safe();
            
            case 'clinics':
                return $settings['cpt_clinics'] ?? '';
            
            case 'services':
                return $settings['cpt_services'] ?? '';
            
            case 'offers':
                // Offers ╨╛╤Б╨╜╨╛╨▓╨░╨╜╤Л ╨╜╨░ ╨╛╤Б╨╜╨╛╨▓╨╜╨╛╨╝ ╤В╨╕╨┐╨╡ ╨┐╨╛╤Б╤В╨░
                return $settings['post_type'] ?? yfgp_get_default_post_type_safe();
            
            default:
                return yfgp_get_default_post_type_safe();
        }
    }

    

    /**

     * AJAX: ╨в╨╡╤Б╤В╨╕╤А╨╛╨▓╨░╨╜╨╕╨╡ ╨╝╨░╨┐╨┐╨╕╨╜╨│╨░

     */

    public function ajax_test_mapping() {

        error_log("ЁЯФ╡ YFGP: ajax_test_mapping() ENTRY");

        

        // v3.4.9: ╨г╨▓╨╡╨╗╨╕╤З╨╕╨▓╨░╨╡╨╝ ╨▓╤А╨╡╨╝╤П ╨▓╤Л╨┐╨╛╨╗╨╜╨╡╨╜╨╕╤П ╨┤╨╗╤П ╤Б╨╗╨╛╨╢╨╜╤Л╤Е ╨╝╨░╨┐╨┐╨╕╨╜╨│╨╛╨▓

        set_time_limit(60);

        ini_set('max_execution_time', 60);

        error_log("ЁЯФ╡ YFGP: Time limits set");

        

        // v4.18.13: ╨Х╨┤╨╕╨╜╤Л╨╣ ╤Б╤В╨░╨╜╨┤╨░╤А╤В nonce ╨┤╨╗╤П ╨▓╤Б╨╡╤Е AJAX handlers

        check_ajax_referer('yfgp_ajax_nonce', 'nonce');

        error_log("ЁЯФ╡ YFGP: Nonce verified");

        

        if (!current_user_can('manage_options')) {

            error_log("тЭМ YFGP: Permission denied");

            wp_send_json_error('╨Э╨╡╨┤╨╛╤Б╤В╨░╤В╨╛╤З╨╜╨╛ ╨┐╤А╨░╨▓');

        }

        error_log("ЁЯФ╡ YFGP: Permissions OK");

        

        error_log("ЁЯЪА YFGP: ajax_test_mapping STARTED");

        

        try {

            error_log("ЁЯФ╡ YFGP: Entering try block");

            $settings = get_option('yfgp_settings', array());

            error_log("ЁЯФ╡ YFGP: Settings loaded");

            // v4.18.21: ╨Я╨╛╨╗╤Г╤З╨░╨╡╨╝ tab_type ╨Я╨Х╨а╨Х╨Ф ╨╛╨┐╤А╨╡╨┤╨╡╨╗╨╡╨╜╨╕╨╡╨╝ post_type ╨┤╨╗╤П ╨┐╤А╨░╨▓╨╕╨╗╤М╨╜╨╛╨│╨╛ ╨╛╨┐╤А╨╡╨┤╨╡╨╗╨╡╨╜╨╕╤П ╤В╨╕╨┐╨░ ╨┐╨╛╤Б╤В╨░
            $tab_type = sanitize_text_field($_POST['tab_type'] ?? yfgp_get_default_post_type_safe());
            $post_type = $this->get_post_type_for_tab($tab_type, $settings);

            error_log("ЁЯФ╡ YFGP: Tab type = {$tab_type}, Post type = {$post_type}");

            

            // v4.18.21: ╨Я╨╛╨╗╤Г╤З╨░╨╡╨╝ ╨┐╨╡╤А╨╡╨┤╨░╨╜╨╜╤Л╨╣ post_id (╨Ю╨С╨п╨Ч╨Р╨в╨Х╨Ы╨м╨Э╨Ю ╨┤╨╗╤П ╨║╨╛╤А╤А╨╡╨║╤В╨╜╨╛╨│╨╛ ╨┐╤А╨╡╨▓╤М╤О!)
            // ╨Х╤Б╨╗╨╕ post_id ╨╜╨╡ ╨┐╨╡╤А╨╡╨┤╨░╨╜ - ╤Н╤В╨╛ ╨╛╤И╨╕╨▒╨║╨░, ╤В.╨║. ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М ╨┤╨╛╨╗╨╢╨╡╨╜ ╨▓╤Л╨▒╤А╨░╤В╤М ╨┐╨╛╤Б╤В ╨╕╨╖ ╤Б╨╡╨╗╨╡╨║╤В╨░

            $post_id = intval($_POST['post_id'] ?? 0);

            error_log("ЁЯФ╡ YFGP: Post ID from request = {$post_id}");

            

            if ($post_id > 0) {

                // ╨Ш╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╨╝ ╨┐╨╡╤А╨╡╨┤╨░╨╜╨╜╤Л╨╣ post_id (╨▓╤Л╨▒╤А╨░╨╜╨╜╤Л╨╣ ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╨╡╨╝ ╨╕╨╖ ╤Б╨╡╨╗╨╡╨║╤В╨░)

                $post = get_post($post_id);

                if (!$post) {

                    error_log("тЭМ YFGP: Post {$post_id} not found");

                    wp_send_json_error('╨Я╨╛╤Б╤В ╨╜╨╡ ╨╜╨░╨╣╨┤╨╡╨╜');

                }

                if ($post->post_type !== $post_type) {

                    error_log("тЭМ YFGP: Post {$post_id} type mismatch: expected {$post_type}, got {$post->post_type}");

                    wp_send_json_error('╨Э╨╡╨┐╤А╨░╨▓╨╕╨╗╤М╨╜╤Л╨╣ ╤В╨╕╨┐ ╨┐╨╛╤Б╤В╨░. ╨Ю╨╢╨╕╨┤╨░╨╡╤В╤Б╤П: ' . $post_type . ', ╨┐╨╛╨╗╤Г╤З╨╡╨╜: ' . $post->post_type);

                }

                error_log("тЬЕ YFGP: Using selected post ID {$post_id} ({$post->post_title})");

            } else {

                // v4.18.21: ╨Х╤Б╨╗╨╕ post_id ╨╜╨╡ ╨┐╨╡╤А╨╡╨┤╨░╨╜ - ╤Н╤В╨╛ ╨╛╤И╨╕╨▒╨║╨░ (╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М ╨┤╨╛╨╗╨╢╨╡╨╜ ╨▓╤Л╨▒╤А╨░╤В╤М ╨┐╨╛╤Б╤В)
                error_log("тЭМ YFGP: Post ID not provided in request");

                wp_send_json_error('╨Я╨╛╨╢╨░╨╗╤Г╨╣╤Б╤В╨░, ╨▓╤Л╨▒╨╡╤А╨╕╤В╨╡ ╨┐╨╛╤Б╤В ╨╕╨╖ ╤Б╨┐╨╕╤Б╨║╨░ ╨┤╨╗╤П ╤В╨╡╤Б╤В╨╕╤А╨╛╨▓╨░╨╜╨╕╤П');

            }

            

            $result = array(

                // v4.18.22: Performance - Use wp_count_posts instead of get_posts with -1 to prevent OOM
                'posts_found' => (int) wp_count_posts($post_type)->publish,

                'clinics_found' => 0,

                'services_found' => 0,

                'sample_post' => null

            );

            

            // v3.4.9: Use Field Mapper V3 for proper handling of v3 mapping format

            if (!class_exists('YFGP_Field_Mapper_V3')) {

                require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-v3.php';

            }

            

            $mapper_v3 = YFGP_Field_Mapper_V3::get_instance();

            $mapping = get_option('yfgp_field_mapping_v3', array());
            // v4.18.18: Fix PHP 8+ count() error - get_option can return false, ensure array
            if (!is_array($mapping)) {
                $mapping = array();
            }

            error_log("ЁЯзк YFGP: START test - Post ID={$post->ID}, title='{$post->post_title}', total_fields=" . count($mapping));

            

            // ╨б╨╛╨▒╨╕╤А╨░╨╡╨╝ ╨┤╨░╨╜╨╜╤Л╨╡ ╨╕╤Б╨┐╨╛╨╗╤М╨╖╤Г╤П V3 API (╨┐╨╛ ╨╛╨┤╨╜╨╛╨╝╤Г ╨┐╨╛╨╗╤О)

            $data = array();

            $processed = 0;

            $start_time = microtime(true);

            

            foreach ($mapping as $field_id => $field_config) {

                if (empty($field_config['source_type'])) {

                    continue; // ╨Я╤А╨╛╨┐╤Г╤Б╨║╨░╨╡╨╝ ╨╜╨╡╨╜╨░╤Б╤В╤А╨╛╨╡╨╜╨╜╤Л╨╡ ╨┐╨╛╨╗╤П

                }

                

                try {

                    $field_start = microtime(true);

                    $value = $mapper_v3->get_field_value($post->ID, $field_config);

                    $data[$field_id] = $value;

                    $processed++;

                    

                    $field_time = round((microtime(true) - $field_start) * 1000, 2);

                    if ($field_time > 100) { // ╨Ы╨╛╨│ ╤В╨╛╨╗╤М╨║╨╛ ╨┤╨╗╤П ╨╝╨╡╨┤╨╗╨╡╨╜╨╜╤Л╤Е ╨┐╨╛╨╗╨╡╨╣ (>100ms)

                        error_log("ЁЯзк YFGP: Field '{$field_id}' took {$field_time}ms");

                    }

                } catch (Exception $e) {

                    error_log("ЁЯЪи YFGP: Error processing field '{$field_id}': " . $e->getMessage());

                    $data[$field_id] = null; // ╨Я╤А╨╛╨┐╤Г╤Б╨║╨░╨╡╨╝ ╨┐╤А╨╛╨▒╨╗╨╡╨╝╨╜╤Л╨╡ ╨┐╨╛╨╗╤П

                }

            }

            

            $total_time = round((microtime(true) - $start_time) * 1000, 2);

            error_log("ЁЯзк YFGP: Processed {$processed} fields in {$total_time}ms");

            

            // v4.1.0-beta31: POST-PROCESSING ╨┤╨╗╤П ╨║╨╛╤А╤А╨╡╨║╤В╨╜╨╛╨│╨╛ ╨╛╤В╨╛╨▒╤А╨░╨╢╨╡╨╜╨╕╤П

            // Apply same logic as build_doctor_entity()

            

            // 1. Calculate experience_years from work_experience

            if (!empty($mapping['experience_years']['calculate_type']) && $mapping['experience_years']['calculate_type'] === 'date_to_years') {

                if (!empty($data['career_start_date']) && strtotime($data['career_start_date']) !== false) {

                    $start_date = strtotime($data['career_start_date']);

                    $years = floor((time() - $start_date) / (365.25 * 24 * 60 * 60));

                    if ($years >= 0) {

                        $data['experience_years'] = $years;

                    }

                }

            }

            

            // 2. Picture fallback - ╨Т╨б╨Х╨У╨Ф╨Р ╨╕╤Б╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╤М featured image ╨╡╤Б╨╗╨╕ ╨╡╤Б╤В╤М

            if (has_post_thumbnail($post->ID)) {

                $data['picture'] = get_the_post_thumbnail_url($post->ID, 'full');

            } elseif (empty($data['picture'])) {

                $data['picture'] = ''; // Fallback ╨╜╨░ ╨┐╤Г╤Б╤В╤Г╤О ╤Б╤В╤А╨╛╨║╤Г

            }

            

            // 3. Strip HTML from description

            if (!empty($data['description'])) {

                $data['description'] = strip_tags($data['description']);

            }

            

            // 4. Extract education repeater

            $education_data = get_post_meta($post->ID, 'obrazovanie_repiter', true);

            if (!empty($education_data) && is_array($education_data)) {

                $data['education'] = array();

                foreach ($education_data as $item) {

                    if (is_array($item)) {

                        $edu = array();

                        if (!empty($item['uchebnoe_zavedenie'])) $edu['name'] = $item['uchebnoe_zavedenie'];

                        if (!empty($item['god_okonchaniia'])) $edu['date_end'] = $item['god_okonchaniia'];

                        if (!empty($item['spetsializatsiia'])) $edu['specialty'] = $item['spetsializatsiia'];

                        if (!empty($edu)) $data['education'][] = $edu;

                    }

                }

            }

            

            // 5. Process children_appointment/adult_appointment from parent_child

            $parent_child = $data['children_appointment'] ?? $data['adult_appointment'] ?? null;

            if (is_array($parent_child)) {

                if (isset($parent_child['child'])) {

                    $data['children_appointment'] = $parent_child['child'] === 'true' ? 'true' : 'false';

                }

                if (isset($parent_child['parent'])) {

                    $data['adult_appointment'] = $parent_child['parent'] === 'true' ? 'true' : 'false';

                }

            }

            

            // v4.4.2: Ensure adult_appointment/children_appointment have defaults (like real feed!)

            if (!isset($data['adult_appointment'])) {

                $data['adult_appointment'] = 'true'; // Default: ╨▓╤А╨░╤З ╨┐╤А╨╕╨╜╨╕╨╝╨░╨╡╤В ╨▓╨╖╤А╨╛╤Б╨╗╤Л╤Е

            }

            if (!isset($data['children_appointment'])) {

                $data['children_appointment'] = 'false'; // Default: ╨╜╨╡ ╨┤╨╡╤В╤Б╨║╨╕╨╣ ╨▓╤А╨░╤З

            }

            

            // v4.1.0: Extract job repeater - ╨Я╨а╨п╨Ь╨Р╨п ╨┐╤А╨╛╨▓╨╡╤А╨║╨░ ╨С╨Ф (╨╜╨╡ ╨╖╨░╨▓╨╕╤Б╨╕╤В ╨╛╤В mapping!)

            // ╨Ш╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╨╝ ╤В╨╛╤В ╨╢╨╡ source ╤З╤В╨╛ ╨╕ ╨▓ build_doctor_entity: obrazovanie_repiter

            $job_data = get_post_meta($post->ID, 'obrazovanie_repiter', true);

            if (!empty($job_data) && is_array($job_data)) {

                $data['job'] = array();

                foreach ($job_data as $item) {

                    if (is_array($item)) {

                        $job = array();

                        $fields_array = array_values($item);

                        if (isset($fields_array[0])) $job['organization'] = $fields_array[0];

                        if (isset($fields_array[1])) $job['period_years'] = $fields_array[1];

                        if (isset($fields_array[3])) $job['position'] = $fields_array[3];

                        if (!empty($job)) $data['job'][] = $job;

                    }

                }

            }

            

            // v4.1.0: Extract certificate repeater - ╨Я╨а╨п╨Ь╨Р╨п ╨┐╤А╨╛╨▓╨╡╤А╨║╨░ ╨С╨Ф (╨╜╨╡ ╨╖╨░╨▓╨╕╤Б╨╕╤В ╨╛╤В mapping!)

            // ╨Ш╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╨╝ ╤В╨╛╤В ╨╢╨╡ source ╤З╤В╨╛ ╨╕ ╨▓ build_doctor_entity: obrazovanie_repiter

            $cert_data = get_post_meta($post->ID, 'obrazovanie_repiter', true);

            if (!empty($cert_data) && is_array($cert_data)) {

                $data['certificate'] = array();

                foreach ($cert_data as $item) {

                    if (is_array($item)) {

                        $cert = array();

                        $fields_array = array_values($item);

                        if (isset($fields_array[0])) $cert['organization'] = $fields_array[0];

                        if (isset($fields_array[1])) $cert['finish_year'] = $fields_array[1];

                        if (isset($fields_array[0])) $cert['name'] = $fields_array[0];

                        if (!empty($cert)) $data['certificate'][] = $cert;

                    }

                }

            }

            

            // v4.1.0: Extract reviews relationship (relation 7: reviews тЖТ doctors)

            // + strip HTML tags for comment/positive/negative/response

            if (function_exists('jet_engine') && !empty(jet_engine()->relations)) {

                $all_relations = jet_engine()->relations->get_active_relations();

                foreach ($all_relations as $relation) {

                    if ($relation->get_id() == 7) {

                        $related_review_ids = $relation->get_parents($post->ID, 'ids');

                        if (!empty($related_review_ids) && is_array($related_review_ids)) {

                            $data['reviews'] = array();

                            foreach ($related_review_ids as $review_id) {

                                $review_post = get_post($review_id);

                                if ($review_post) {

                                    $review = array();

                                    $review['date'] = get_the_date('Y-m-d', $review_id);

                                    $review['checked'] = 'true';

                                    $review['used_in_rating'] = 'true';

                                    $review['author'] = get_the_title($review_id);

                                    $review['author_id'] = get_post_meta($review_id, 'id-otzyva', true);

                                    $review['author_picture'] = get_post_meta($review_id, 'review_video_photo', true);

                                    $review['url'] = get_permalink($review_id);

                                    $review['comment'] = strip_tags(get_the_content(null, false, $review_id));

                                    $review['grade'] = get_post_meta($review_id, 'estimation', true);

                                    $review['positive'] = $review['comment']; // ╤Г╨╢╨╡ strip_tags

                                    $review['negative'] = strip_tags(get_post_meta($review_id, 'otvet-kliniki', true));

                                    $review['response'] = $review['negative']; // ╤Г╨╢╨╡ strip_tags

                                    $data['reviews'][] = $review;

                                }

                            }

                        }

                        break;

                    }

                }

            }

            

            // v4.2.0: POST-PROCESSING ╨┤╨╗╤П Clinics - ╨╕╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╨╝ ╨а╨Х╨Р╨Ы╨м╨Э╨г╨о ╨║╨╗╨╕╨╜╨╕╨║╤Г!
            // v4.18.21: tab_type ╤Г╨╢╨╡ ╨┐╨╛╨╗╤Г╤З╨╡╨╜ ╨▓╤Л╤И╨╡, ╨╜╨╡ ╨┤╤Г╨▒╨╗╨╕╤А╤Г╨╡╨╝

            if ($tab_type === 'clinics') {

                // v4.18.21: ╨Ш╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╨╝ ╨▓╤Л╨▒╤А╨░╨╜╨╜╤Л╨╣ ╨┐╨╛╤Б╤В ╨╕╨╖ ╤Б╨╡╨╗╨╡╨║╤В╨░ (╤Г╨╢╨╡ ╨┐╨╛╨╗╤Г╤З╨╡╨╜ ╨▓╤Л╤И╨╡ ╨║╨░╨║ $post)
                // ╨Э╨Х ╨╜╤Г╨╢╨╜╨╛ ╨┐╨╛╨╗╤Г╤З╨░╤В╤М ╨┐╨╡╤А╨▓╤Л╨╣ ╨┐╨╛╤Б╤В - ╨╕╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╨╝ ╨▓╤Л╨▒╤А╨░╨╜╨╜╤Л╨╣ ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╨╡╨╝!

                $clinic_post = $post; // ╨Ш╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╨╝ ╨▓╤Л╨▒╤А╨░╨╜╨╜╤Л╨╣ ╨┐╨╛╤Б╤В ╨╕╨╖ ╤Б╨╡╨╗╨╡╨║╤В╨░

                // Extract clinic fields using V3 mapper from CLINIC post

                if (!class_exists('YFGP_Field_Mapper_Unified')) {

                    require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';

                }

                $mapper_unified = YFGP_Field_Mapper_Unified::get_instance();

                $clinic_mapping = get_option('yfgp_field_mapping_v3', array());

                // Extract clinic fields via V3 API from CLINIC post

                $clinic_fields = array('clinics_address', 'clinics_phone', 'clinics_email', 'clinics_picture', 'clinics_city', 'clinics_url', 'clinics_id', 'clinics_name', 'clinics_company_id');

                    foreach ($clinic_fields as $field_key) {

                        if (!empty($clinic_mapping[$field_key])) {

                            $value = $mapper_unified->getFieldValue($clinic_post->ID, $clinic_mapping[$field_key]);

                            if (!empty($value)) {

                                // v4.2.2: UNIVERSAL picture conversion (array, string, comma-separated)

                                if ($field_key === 'clinics_picture' && !empty($value)) {

                                    // ╨Х╤Б╨╗╨╕ ╤Г╨╢╨╡ URL - ╨╛╤Б╤В╨░╨▓╨╗╤П╨╡╨╝

                                    if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {

                                        $data[$field_key] = $value;

                                    }

                                    // ╨Х╤Б╨╗╨╕ ╨╝╨░╤Б╤Б╨╕╨▓ - ╨▒╨╡╤А╨╡╨╝ ╨┐╨╡╤А╨▓╤Л╨╣

                                    elseif (is_array($value)) {

                                        $first_id = is_numeric($value[0]) ? intval($value[0]) : null;

                                        $data[$field_key] = $first_id ? wp_get_attachment_url($first_id) : '';

                                    }

                                    // ╨Х╤Б╨╗╨╕ ╤Б╤В╤А╨╛╨║╨░ ╤Б ╨╖╨░╨┐╤П╤В╤Л╨╝╨╕ - explode

                                    elseif (is_string($value) && strpos($value, ',') !== false) {

                                        $ids = array_map('trim', explode(',', $value));

                                        $first_id = is_numeric($ids[0]) ? intval($ids[0]) : null;

                                        $data[$field_key] = $first_id ? wp_get_attachment_url($first_id) : '';

                                    }

                                    // ╨Х╤Б╨╗╨╕ ╨┐╤А╨╛╤Б╤В╨╛ ╤З╨╕╤Б╨╗╨╛ - ╨║╨╛╨╜╨▓╨╡╤А╤В╨╕╨╝

                                    elseif (is_numeric($value)) {

                                        $data[$field_key] = wp_get_attachment_url(intval($value));

                                    } else {

                                        $data[$field_key] = $value; // Fallback

                                    }

                                } else {

                                    $data[$field_key] = $value;

                                }

                            }

                        }

                }

                // Fallback ╨┤╨╗╤П ╨▒╨░╨╖╨╛╨▓╤Л╤Е ╨┐╨╛╨╗╨╡╨╣

                if (empty($data['clinics_id'])) {

                    $data['clinics_id'] = 'clinic_' . $clinic_post->ID;

                }

                if (empty($data['clinics_name'])) {

                    $data['clinics_name'] = $clinic_post->post_title;

                }

                if (empty($data['clinics_url'])) {

                    $data['clinics_url'] = get_permalink($clinic_post->ID);

                }

                // Picture - ALWAYS override with featured image if available

                if (has_post_thumbnail($clinic_post->ID)) {

                    $data['clinics_picture'] = get_the_post_thumbnail_url($clinic_post->ID, 'full');

                }

                // ╨Ю╨▒╨╜╨╛╨▓╨╗╤П╨╡╨╝ post ╨┤╨╗╤П generate_test_yml_preview

                $post = $clinic_post;

                

                // Array validation for multi-value fields

                if (!empty($data['clinics_phone']) && is_array($data['clinics_phone'])) {

                    $data['clinics_phone'] = implode(', ', $data['clinics_phone']);

                }

                if (!empty($data['clinics_address']) && is_array($data['clinics_address'])) {

                    $data['clinics_address'] = implode(', ', $data['clinics_address']);

                }

                

                // HTML cleanup for text fields

                if (!empty($data['clinics_description'])) {

                    $data['clinics_description'] = strip_tags($data['clinics_description']);

                }

            }

            

            // v4.3.0: POST-PROCESSING ╨┤╨╗╤П Services (full meta extraction + pattern from clinics v4.2.2)

            if ($tab_type === 'services') {

                // v4.18.21: ╨Ш╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╨╝ ╨▓╤Л╨▒╤А╨░╨╜╨╜╤Л╨╣ ╨┐╨╛╤Б╤В ╨╕╨╖ ╤Б╨╡╨╗╨╡╨║╤В╨░ (╤Г╨╢╨╡ ╨┐╨╛╨╗╤Г╤З╨╡╨╜ ╨▓╤Л╤И╨╡ ╨║╨░╨║ $post)
                // ╨Э╨Х ╨╜╤Г╨╢╨╜╨╛ ╨┐╨╛╨╗╤Г╤З╨░╤В╤М ╨┐╨╡╤А╨▓╤Л╨╣ ╨┐╨╛╤Б╤В - ╨╕╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╨╝ ╨▓╤Л╨▒╤А╨░╨╜╨╜╤Л╨╣ ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╨╡╨╝!

                $service_post = $post; // ╨Ш╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╨╝ ╨▓╤Л╨▒╤А╨░╨╜╨╜╤Л╨╣ ╨┐╨╛╤Б╤В ╨╕╨╖ ╤Б╨╡╨╗╨╡╨║╤В╨░

                // Extract service fields via V3 mapper (same pattern as clinics!)

                if (!class_exists('YFGP_Field_Mapper_Unified')) {

                    require_once YFGP_PLUGIN_DIR . 'includes/class-field-mapper-unified.php';

                }

                $mapper_unified = YFGP_Field_Mapper_Unified::get_instance();

                $service_mapping = get_option('yfgp_field_mapping_v3', array());

                // Extract service fields via V3 API from SERVICE post

                $service_fields = array('services_description', 'services_gov_id', 'services_picture', 'services_url', 'services_id', 'services_name');

                foreach ($service_fields as $field_key) {

                        if (!empty($service_mapping[$field_key])) {

                            $value = $mapper_unified->getFieldValue($service_post->ID, $service_mapping[$field_key]);

                            if (!empty($value)) {

                                // v4.3.0: Convert picture (same as clinics v4.2.2!)

                                if ($field_key === 'services_picture' && !empty($value)) {

                                    // ╨Х╤Б╨╗╨╕ ╤Г╨╢╨╡ URL - ╨╛╤Б╤В╨░╨▓╨╗╤П╨╡╨╝

                                    if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {

                                        $data[$field_key] = $value;

                                    }

                                    // ╨Х╤Б╨╗╨╕ ╨╝╨░╤Б╤Б╨╕╨▓ - ╨▒╨╡╤А╨╡╨╝ ╨┐╨╡╤А╨▓╤Л╨╣

                                    elseif (is_array($value)) {

                                        $first_id = is_numeric($value[0]) ? intval($value[0]) : null;

                                        $data[$field_key] = $first_id ? wp_get_attachment_url($first_id) : '';

                                    }

                                    // ╨Х╤Б╨╗╨╕ ╤Б╤В╤А╨╛╨║╨░ ╤Б ╨╖╨░╨┐╤П╤В╤Л╨╝╨╕ - explode

                                    elseif (is_string($value) && strpos($value, ',') !== false) {

                                        $ids = array_map('trim', explode(',', $value));

                                        $first_id = is_numeric($ids[0]) ? intval($ids[0]) : null;

                                        $data[$field_key] = $first_id ? wp_get_attachment_url($first_id) : '';

                                    }

                                    // ╨Х╤Б╨╗╨╕ ╤З╨╕╤Б╨╗╨╛ - ╨║╨╛╨╜╨▓╨╡╤А╤В╨╕╨╝

                                    elseif (is_numeric($value)) {

                                        $data[$field_key] = wp_get_attachment_url(intval($value));

                                    } else {

                                        $data[$field_key] = $value;

                                    }

                                } else {

                                    $data[$field_key] = $value;

                                }

                            }

                        }

                    }

                // Fallback ╨┤╨╗╤П ╨▒╨░╨╖╨╛╨▓╤Л╤Е ╨┐╨╛╨╗╨╡╨╣

                if (empty($data['services_id'])) {

                    $data['services_id'] = 'service_' . $service_post->ID;

                }

                if (empty($data['services_name'])) {

                    $data['services_name'] = $service_post->post_title;

                }

                // Picture - ALWAYS override with featured image if available

                if (has_post_thumbnail($service_post->ID)) {

                    $data['services_picture'] = get_the_post_thumbnail_url($service_post->ID, 'full');

                }

                // HTML cleanup for description

                if (!empty($data['services_description'])) {

                    $data['services_description'] = strip_tags($data['services_description']);

                }

                // ╨Ю╨▒╨╜╨╛╨▓╨╗╤П╨╡╨╝ post ╨┤╨╗╤П generate_test_yml_preview

                $post = $service_post;

            }

            

            // v4.4.0: POST-PROCESSING ╨┤╨╗╤П Offers (simplified - show test data)

            if ($tab_type === 'offers') {

                // Create test offers data (mock for preview)

                $data['offers'] = array(

                    array(

                        'id' => 'offer_test_1',

                        'doctor_id' => 'doctor_' . $post->ID,

                        'clinic_id' => 'clinic_13517',

                        'service_id' => 'service_test',

                        'speciality' => '╨б╤В╨╛╨╝╨░╤В╨╛╨╗╨╛╨│',

                        'appointment_url' => get_permalink($post->ID),

                        'appointment_available' => 'true',

                        'oms_available' => 'false',

                        'online_schedule' => 'false',

                        'children_appointment' => 'false',

                        'adult_appointment' => 'true',

                        'house_call' => 'false',

                        'telemed' => 'true',

                        'is_base_service' => 'true',

                    ),

                    array(

                        'id' => 'offer_test_2',

                        'doctor_id' => 'doctor_' . $post->ID,

                        'clinic_id' => 'clinic_13525',

                        'service_id' => 'service_test',

                        'speciality' => '╨б╤В╨╛╨╝╨░╤В╨╛╨╗╨╛╨│',

                        'appointment_url' => get_permalink($post->ID),

                        'appointment_available' => 'true',

                        'oms_available' => 'false',

                        'online_schedule' => 'false',

                        'children_appointment' => 'false',

                        'adult_appointment' => 'true',

                        'house_call' => 'false',

                        'telemed' => 'true',

                        'is_base_service' => 'true',

                        'price' => 5200, // Example price

                        'currency' => 'RUR'

                    ),

                    array(

                        'id' => 'offer_test_3',

                        'doctor_id' => 'doctor_' . $post->ID,

                        'clinic_id' => 'clinic_13514',

                        'service_id' => 'service_test',

                        'speciality' => '╨б╤В╨╛╨╝╨░╤В╨╛╨╗╨╛╨│',

                        'appointment_url' => get_permalink($post->ID),

                        'appointment_available' => 'true',

                        'oms_available' => 'false',

                        'online_schedule' => 'false',

                        'children_appointment' => 'false',

                        'adult_appointment' => 'true',

                        'house_call' => 'false',

                        'telemed' => 'true',

                        'is_base_service' => 'true',

                    ),

                );

            }

            

            // v3.4.9: Generate YML preview for popup modal

            error_log("ЁЯзк YFGP: Generating YML for tab '{$tab_type}'");

            $yml = $this->generate_test_yml_preview($data, $tab_type, $post);

            error_log("ЁЯзк YFGP: YML generated, length=" . strlen($yml));

            

            $result['sample_post'] = array(

                'post_title' => $post->post_title,

                'post_id' => $post->ID,

                'mapped_data' => $data

            );

            

            $result['clinics_found'] = isset($data['clinics']) && is_array($data['clinics']) ? count($data['clinics']) : 0;

            $result['services_found'] = isset($data['services']) && is_array($data['services']) ? count($data['services']) : 0;

            $result['yml'] = $yml; // v3.4.9: Add YML for popup with V3 data

            

            error_log("ЁЯзк YFGP: ╨а╨╡╨╖╤Г╨╗╤М╤В╨░╤В - ╨║╨╗╨╕╨╜╨╕╨║: {$result['clinics_found']}, ╤Г╤Б╨╗╤Г╨│: {$result['services_found']}");

            

            wp_send_json_success($result);

        } catch (Exception $e) {

            error_log("тЭМ YFGP: ╨Ю╤И╨╕╨▒╨║╨░ ╤В╨╡╤Б╤В╨╕╤А╨╛╨▓╨░╨╜╨╕╤П: " . $e->getMessage());

            wp_send_json_error($e->getMessage());

        }

    }

    

    /**

     * Generate YML preview for test button (v3.4.8)

     * 

     * @param array $data Mapped post data

     * @param string $tab_type Current tab (doctors/offer/relations/directory)

     * @param WP_Post $post Current post

     * @return string YML preview

     */

    private function generate_test_yml_preview($data, $tab_type, $post) {

        $yml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";

        $yml .= "<test-preview tab=\"{$tab_type}\" post_id=\"{$post->ID}\" post_title=\"" . esc_attr($post->post_title) . "\">\n";

        

        switch ($tab_type) {

            case 'doctors':

                // Doctor fields preview

                $yml .= "  <doctor>\n";

                $yml .= "    <name>" . esc_html($data['name'] ?? $post->post_title) . "</name>\n";

                

                // v4.1.0: Fix ╨д╨Ш╨Ю parsing - use fallback logic from feed generator

                $surname = $data['surname'] ?? null;

                $first_name = $data['first_name'] ?? null;

                $patronymic = $data['patronymic'] ?? null;

                

                // If not configured, parse from name

                if (empty($surname) || empty($first_name)) {

                    $full_name = $data['name'] ?? $post->post_title;

                    $parts = explode(' ', trim($full_name));

                    $surname = $surname ?: ($parts[0] ?? 'N/A');

                    $first_name = $first_name ?: ($parts[1] ?? 'N/A');

                    $patronymic = $patronymic ?: ($parts[2] ?? '');

                }

                

                $yml .= "    <surname>" . esc_html($surname) . "</surname>\n";

                $yml .= "    <first_name>" . esc_html($first_name) . "</first_name>\n";

                if (!empty($patronymic)) {

                    $yml .= "    <patronymic>" . esc_html($patronymic) . "</patronymic>\n";

                }

                if (!empty($data['experience_years'])) {

                    $yml .= "    <experience_years>" . esc_html($data['experience_years']) . "</experience_years>\n";

                }

                // v4.1.0-beta16: Added missing mandatory fields

                if (!empty($data['degree'])) {

                    // v4.18.21: ╨Ю╨▒╤А╨░╨▒╨╛╤В╨║╨░ ╨╝╨░╤Б╤Б╨╕╨▓╨░ ╨┤╨╗╤П degree (Sentry YANDEX-FEED-GENERATOR-PRO-10)
                    $degree_value = is_array($data['degree']) ? implode(', ', $data['degree']) : $data['degree'];
                    $yml .= "    <degree>" . esc_html($degree_value) . "</degree>\n";

                }

                if (!empty($data['rank'])) {

                    // v4.18.21: ╨Ю╨▒╤А╨░╨▒╨╛╤В╨║╨░ ╨╝╨░╤Б╤Б╨╕╨▓╨░ ╨┤╨╗╤П rank (Sentry YANDEX-FEED-GENERATOR-PRO-10)
                    $rank_value = is_array($data['rank']) ? implode(', ', $data['rank']) : $data['rank'];
                    $yml .= "    <rank>" . esc_html($rank_value) . "</rank>\n";

                }

                if (!empty($data['category'])) {

                    // v4.18.21: ╨Ю╨▒╤А╨░╨▒╨╛╤В╨║╨░ ╨╝╨░╤Б╤Б╨╕╨▓╨░ ╨┤╨╗╤П category (Sentry YANDEX-FEED-GENERATOR-PRO-10)
                    $category_value = is_array($data['category']) ? implode(', ', $data['category']) : $data['category'];
                    $yml .= "    <category>" . esc_html($category_value) . "</category>\n";

                }

                if (!empty($data['career_start_date'])) {

                    $yml .= "    <career_start_date>" . esc_html($data['career_start_date']) . "</career_start_date>\n";

                }

                if (!empty($data['picture'])) {

                    $yml .= "    <picture>" . esc_html($data['picture']) . "</picture>\n";

                }

                if (!empty($data['reviews_total_count'])) {

                    // v4.18.21: ╨Ю╨▒╤А╨░╨▒╨╛╤В╨║╨░ ╨╝╨░╤Б╤Б╨╕╨▓╨░ ╨┤╨╗╤П reviews_total_count (Sentry YANDEX-FEED-GENERATOR-PRO-10)
                    $reviews_count = is_array($data['reviews_total_count']) ? count($data['reviews_total_count']) : $data['reviews_total_count'];
                    $yml .= "    <reviews_total_count>" . esc_html($reviews_count) . "</reviews_total_count>\n";

                }

                if (!empty($data['description'])) {

                    $yml .= "    <description>" . esc_html(mb_substr($data['description'], 0, 200)) . "...</description>\n";

                }

                

                // v4.1.0-beta31: Education repeater

                if (!empty($data['education']) && is_array($data['education'])) {

                    foreach ($data['education'] as $edu) {

                        $yml .= "    <education>\n";

                        if (!empty($edu['name'])) {

                            $yml .= "      <name>" . esc_html($edu['name']) . "</name>\n";

                        }

                        if (!empty($edu['date_end'])) {

                            $yml .= "      <date_end>" . esc_html($edu['date_end']) . "</date_end>\n";

                        }

                        if (!empty($edu['specialty'])) {

                            $yml .= "      <specialty>" . esc_html($edu['specialty']) . "</specialty>\n";

                        }

                        $yml .= "    </education>\n";

                    }

                }

                

                // v4.1.0-beta32: Job repeater

                if (!empty($data['job']) && is_array($data['job'])) {

                    foreach ($data['job'] as $j) {

                        $yml .= "    <job>\n";

                        if (!empty($j['organization'])) {

                            $yml .= "      <organization>" . esc_html($j['organization']) . "</organization>\n";

                        }

                        if (!empty($j['period_years'])) {

                            $yml .= "      <period_years>" . esc_html($j['period_years']) . "</period_years>\n";

                        }

                        if (!empty($j['position'])) {

                            $yml .= "      <position>" . esc_html($j['position']) . "</position>\n";

                        }

                        $yml .= "    </job>\n";

                    }

                }

                

                // v4.1.0-beta32: Certificate repeater

                if (!empty($data['certificate']) && is_array($data['certificate'])) {

                    foreach ($data['certificate'] as $cert) {

                        $yml .= "    <certificate>\n";

                        if (!empty($cert['organization'])) {

                            $yml .= "      <organization>" . esc_html($cert['organization']) . "</organization>\n";

                        }

                        if (!empty($cert['finish_year'])) {

                            $yml .= "      <finish_year>" . esc_html($cert['finish_year']) . "</finish_year>\n";

                        }

                        if (!empty($cert['name'])) {

                            $yml .= "      <name>" . esc_html($cert['name']) . "</name>\n";

                        }

                        $yml .= "    </certificate>\n";

                    }

                }

                

                // v4.1.0-beta32: Reviews relationship

                if (!empty($data['reviews']) && is_array($data['reviews'])) {

                    foreach ($data['reviews'] as $rev) {

                         $yml .= "    <review>\n";

                        if (!empty($rev['date'])) {

                            $yml .= "      <date>" . esc_html($rev['date']) . "</date>\n";

                        }

                        if (!empty($rev['checked'])) {

                            $yml .= "      <checked>" . esc_html($rev['checked']) . "</checked>\n";

                        }

                        if (!empty($rev['used_in_rating'])) {

                            $yml .= "      <used_in_rating>" . esc_html($rev['used_in_rating']) . "</used_in_rating>\n";

                        }

                        if (!empty($rev['author'])) {

                            $yml .= "      <author>" . esc_html($rev['author']) . "</author>\n";

                        }

                        if (!empty($rev['author_id'])) {

                            $yml .= "      <author_id>" . esc_html($rev['author_id']) . "</author_id>\n";

                        }

                        if (!empty($rev['author_picture'])) {

                            $yml .= "      <author_picture>" . esc_html($rev['author_picture']) . "</author_picture>\n";

                        }

                        if (!empty($rev['url'])) {

                            $yml .= "      <url>" . esc_html($rev['url']) . "</url>\n";

                        }

                        if (!empty($rev['comment'])) {

                            $yml .= "      <comment>" . esc_html(mb_substr($rev['comment'], 0, 100)) . "...</comment>\n";

                        }

                        if (!empty($rev['grade'])) {

                            $yml .= "      <grade>" . esc_html($rev['grade']) . "</grade>\n";

                        }

                        if (!empty($rev['positive'])) {

                            $yml .= "      <positive>" . esc_html(mb_substr($rev['positive'], 0, 100)) . "...</positive>\n";

                        }

                        if (!empty($rev['negative'])) {

                            $yml .= "      <negative>" . esc_html($rev['negative']) . "</negative>\n";

                        }

                        if (!empty($rev['response'])) {

                            $response_text = wp_strip_all_tags($rev['response']);

                            if ($response_text != '') {
                                $yml .= "      <response>" . esc_html($response_text) . "</response>\n";
                            }

                        }

                         $yml .= "    </review>\n";

                    }

                }

                

                $yml .= "  </doctor>\n";

                break;

                

            case 'offer':

                // Offer fields preview

                $yml .= "  <offer>\n";

                $yml .= "    <url>" . esc_html($data['url'] ?? get_permalink($post->ID)) . "</url>\n";

                if (!empty($data['price'])) {

                    $yml .= "    <price>\n";

                    $yml .= "      <base_price>" . esc_html($data['price']) . "</base_price>\n";

                    $yml .= "      <currency>RUR</currency>\n";

                    $yml .= "    </price>\n";

                }

                if (!empty($data['online_schedule'])) {

                    $yml .= "    <online_schedule>" . esc_html($data['online_schedule']) . "</online_schedule>\n";

                }

                $yml .= "  </offer>\n";

                break;

                

            case 'relations':

                // Clinics + Services preview

                $yml .= "  <clinics>\n";

                if (!empty($data['clinics'])) {

                    foreach ($data['clinics'] as $clinic) {

                        $yml .= "    <clinic>\n";

                        $yml .= "      <name>" . esc_html($clinic['name'] ?? 'N/A') . "</name>\n";

                        $yml .= "      <city>" . esc_html($clinic['city'] ?? 'N/A') . "</city>\n";

                        $yml .= "    </clinic>\n";

                    }

                } else {

                    $yml .= "    <!-- No clinics found -->\n";

                }

                $yml .= "  </clinics>\n";

                

                $yml .= "  <services>\n";

                if (!empty($data['services'])) {

                    foreach ($data['services'] as $service) {

                        $yml .= "    <service>\n";

                        $yml .= "      <name>" . esc_html($service['name'] ?? 'N/A') . "</name>\n";
                        $yml .= "    </service>\n";

                    }

                } else {

                    $yml .= "    <!-- No services found -->\n";

                }

                $yml .= "  </services>\n";

                break;

                

            case 'clinics':

                // v4.2.0: Clinics tab preview - FIXED ╤Б╤В╤А╤Г╨║╤В╤Г╤А╨░ XML ╤Б╨╛╨│╨╗╨░╤Б╨╜╨╛ Yandex docs

                // ID ╨┤╨╛╨╗╨╢╨╡╨╜ ╨▒╤Л╤В╤М ╨Р╨в╨а╨Ш╨С╨г╨в╨Ю╨Ь <clinic id="...">

                $clinic_id = $data['clinics_id'] ?? 'clinic_' . $post->ID;

                $yml .= "  <clinic id=\"" . esc_attr($clinic_id) . "\">\n";

                

                // URL ╨┐╨╡╤А╨▓╤Л╨╝ (╨║╨░╨║ ╨▓ ╨┤╨╛╨║╤Г╨╝╨╡╨╜╤В╨░╤Ж╨╕╨╕)

                if (!empty($data['clinics_url'])) {

                    $yml .= "    <url>" . esc_html($data['clinics_url']) . "</url>\n";

                }

                

                // Picture

                if (!empty($data['clinics_picture'])) {

                    $yml .= "    <picture>" . esc_html($data['clinics_picture']) . "</picture>\n";

                }

                

                // Name

                $yml .= "    <name>" . esc_html($data['clinics_name'] ?? $post->post_title) . "</name>\n";

                

                // City

                if (!empty($data['clinics_city'])) {

                    $yml .= "    <city>" . esc_html($data['clinics_city']) . "</city>\n";

                }

                

                // Address

                if (!empty($data['clinics_address'])) {

                    $yml .= "    <address>" . esc_html($data['clinics_address']) . "</address>\n";

                }

                

                // Email

                if (!empty($data['clinics_email'])) {

                    $yml .= "    <email>" . esc_html($data['clinics_email']) . "</email>\n";

                }

                

                // Phone

                if (!empty($data['clinics_phone'])) {

                    $yml .= "    <phone>" . esc_html($data['clinics_phone']) . "</phone>\n";

                }

                

                // Internal ID (╨С╨Х╨Ч ╨┐╤А╨╡╤Д╨╕╨║╤Б╨░ clinic_)

                $internal_id = str_replace('clinic_', '', $clinic_id);

                $yml .= "    <internal_id>" . esc_html($internal_id) . "</internal_id>\n";

                

                // Company ID

                if (!empty($data['clinics_company_id'])) {

                    $yml .= "    <company_id>" . esc_html($data['clinics_company_id']) . "</company_id>\n";

                }

                

                $yml .= "  </clinic>\n";

                break;

                

            case 'services':

                // v4.3.0: Services tab preview - FIXED to match clinics v4.2.2 pattern

                $service_id = $data['services_id'] ?? ('service_' . $post->ID);

                

                // v4.3.0: Ensure service_ prefix (like clinic_)

                if (strpos($service_id, 'service_') !== 0 && is_numeric($service_id)) {

                    $service_id = 'service_' . $service_id;

                }

                

                // ID ╨║╨░╨║ ╨░╤В╤А╨╕╨▒╤Г╤В (╨Э╨Х ╨╛╤В╨┤╨╡╨╗╤М╨╜╤Л╨╣ ╤В╨╡╨│!)

                $yml .= "  <service id=\"" . esc_attr($service_id) . "\">\n";

                

                // Name (╨Ю╨С╨п╨Ч╨Р╨в╨Х╨Ы╨м╨Э╨Ю╨Х ╨┐╨╛ ╨п╨╜╨┤╨╡╨║╤Б)

                $yml .= "    <name>" . esc_html($data['services_name'] ?? $post->post_title) . "</name>\n";

                

                // Description (without HTML)

                if (!empty($data['services_description'])) {

                    $yml .= "    <description>" . esc_html($data['services_description']) . "</description>\n";

                }

                

                // Gov ID (╨│╨╛╤Б╤Г╨┤╨░╤А╤Б╤В╨▓╨╡╨╜╨╜╤Л╨╣ ID ╤Г╤Б╨╗╤Г╨│╨╕)

                if (!empty($data['services_gov_id'])) {

                    $yml .= "    <gov_id>" . esc_html($data['services_gov_id']) . "</gov_id>\n";

                }

                

                // Internal ID (╨С╨Х╨Ч ╨┐╤А╨╡╤Д╨╕╨║╤Б╨░ service_)

                $internal_id = str_replace('service_', '', $service_id);

                $yml .= "    <internal_id>" . esc_html($internal_id) . "</internal_id>\n";

                

                $yml .= "  </service>\n";

                break;

                

            case 'offers':

                // v4.4.0: Offers tab preview - FULL structure per Yandex spec

                if (!empty($data['offers'])) {

                    // Show first 3 offers for preview

                    $preview_offers = array_slice($data['offers'], 0, 3);

                    

                    foreach ($preview_offers as $offer) {

                        $offer_id = $offer['id'] ?? 'offer_default';

                        

                        $yml .= "  <offer id=\"" . esc_attr($offer_id) . "\">\n";

                        

                        // URL (from doctor or offer)

                        if (!empty($offer['appointment_url'])) {

                            $yml .= "    <url>" . esc_html($offer['appointment_url']) . "</url>\n";

                        }

                        

                        // Price (nested structure)

                        if (!empty($offer['price'])) {

                            $yml .= "    <price>\n";

                            $yml .= "      <base_price>" . esc_html($offer['price']) . "</base_price>\n";

                            $yml .= "      <currency>" . esc_html($offer['currency']) . "</currency>\n";

                            $yml .= "    </price>\n";

                        }

                        

                        // Online schedule

                        if (isset($offer['online_schedule'])) {

                            $value = ($offer['online_schedule'] === 'true' || $offer['online_schedule'] === true) ? 'true' : 'false';

                            $yml .= "    <online_schedule>" . $value . "</online_schedule>\n";

                        }

                        

                        // Appointment

                        if (isset($offer['appointment_available'])) {

                            $value = ($offer['appointment_available'] === 'true' || $offer['appointment_available'] === true) ? 'true' : 'false';

                            $yml .= "    <appointment>" . $value . "</appointment>\n";

                        }

                        

                        // OMS

                        if (isset($offer['oms_available'])) {

                            $value = ($offer['oms_available'] === 'true' || $offer['oms_available'] === true) ? 'true' : 'false';

                            $yml .= "    <oms>" . $value . "</oms>\n";

                        }

                        

                        // Service reference (self-closing)

                        if (!empty($offer['service_id'])) {

                            $yml .= "    <service id=\"" . esc_attr($offer['service_id']) . "\"/>\n";

                        }

                        

                        // Clinic тЖТ Doctor nesting

                        if (!empty($offer['clinic_id'])) {

                            $yml .= "    <clinic id=\"" . esc_attr($offer['clinic_id']) . "\">\n";

                            

                            if (!empty($offer['doctor_id'])) {

                                $yml .= "      <doctor id=\"" . esc_attr($offer['doctor_id']) . "\">\n";

                                

                                // Speciality (required inside doctor)

                                if (!empty($offer['speciality'])) {

                                    $yml .= "        <speciality>" . esc_html($offer['speciality']) . "</speciality>\n";

                                }

                                

                                // Boolean fields inside doctor

                                if (isset($offer['children_appointment'])) {

                                    $value = ($offer['children_appointment'] === 'true' || $offer['children_appointment'] === true) ? 'true' : 'false';

                                    $yml .= "        <children_appointment>" . $value . "</children_appointment>\n";

                                }

                                

                                if (isset($offer['adult_appointment'])) {

                                    $value = ($offer['adult_appointment'] === 'true' || $offer['adult_appointment'] === true) ? 'true' : 'false';

                                    $yml .= "        <adult_appointment>" . $value . "</adult_appointment>\n";

                                }

                                

                                if (isset($offer['house_call'])) {

                                    $value = ($offer['house_call'] === 'true' || $offer['house_call'] === true) ? 'true' : 'false';

                                    $yml .= "        <house_call>" . $value . "</house_call>\n";

                                }

                                

                                if (isset($offer['telemed'])) {

                                    $value = ($offer['telemed'] === 'true' || $offer['telemed'] === true) ? 'true' : 'false';

                                    $yml .= "        <telemed>" . $value . "</telemed>\n";

                                }

                                

                                if (isset($offer['is_base_service'])) {

                                    $value = ($offer['is_base_service'] === 'true' || $offer['is_base_service'] === true) ? 'true' : 'false';

                                    $yml .= "        <is_base_service>" . $value . "</is_base_service>\n";

                                }

                                

                                $yml .= "      </doctor>\n";

                            }

                            

                            $yml .= "    </clinic>\n";

                        }

                        

                        $yml .= "  </offer>\n";

                    }

                } else {

                    $yml .= "  <!-- No offers found for this doctor -->\n";

                }

                break;

                

            case 'directory':

                // Full structure preview

                $yml .= "  <summary>\n";

                $yml .= "    <post_type>{$post->post_type}</post_type>\n";

                $yml .= "    <post_id>{$post->ID}</post_id>\n";

                $yml .= "    <clinics_count>" . count($data['clinics'] ?? array()) . "</clinics_count>\n";

                $yml .= "    <services_count>" . count($data['services'] ?? array()) . "</services_count>\n";

                $yml .= "    <reviews_count>" . count($data['reviews'] ?? array()) . "</reviews_count>\n";

                $yml .= "  </summary>\n";

                

                // Sample data

                $yml .= "  <sample_data>\n";

                $yml .= "    <name>" . esc_html($data['name'] ?? 'N/A') . "</name>\n";

                $yml .= "    <url>" . esc_html($data['url'] ?? get_permalink($post->ID)) . "</url>\n";

                $yml .= "  </sample_data>\n";

                break;

                

            default:

                $yml .= "  <!-- Unknown tab type: {$tab_type} -->\n";

        }

        

        $yml .= "</test-preview>";

        

        return $yml;

    }

    

    /**

     * ╨Р╨▓╤В╨╛╨╝╨░╤В╨╕╤З╨╡╤Б╨║╨╛╨╡ ╨╛╨▒╨╜╨╛╨▓╨╗╨╡╨╜╨╕╨╡ ╤Д╨╕╨┤╨░

     */

    public function auto_update_feed() {

        $error_handler = YFGP_Error_Handler::get_instance();

        $settings = get_option('yfgp_settings', array());

        

        if (!isset($settings['auto_update']) || !$settings['auto_update']) {

            return;

        }

        

        try {

            // ╨Т╤Л╨▒╨╕╤А╨░╨╡╨╝ ╨│╨╡╨╜╨╡╤А╨░╤В╨╛╤А ╨▓ ╨╖╨░╨▓╨╕╤Б╨╕╨╝╨╛╤Б╤В╨╕ ╨╛╤В ╨╜╨░╤Б╤В╤А╨╛╨╡╨║

            $feed_format = $settings['feed_format'] ?? 'v2';

            

            if ($feed_format === 'v2') {

                $generator = new YFGP_Feed_Generator_V2();

            } else {

                $generator = new YFGP_Feed_Generator();

            }

            

            $post_type = $settings['post_type'] ?? yfgp_get_default_post_type_safe();
            if (empty($post_type)) {
                throw new Exception('YFGP Error: Post type not configured. Please set post_type in plugin settings.');
            }
            $yml = $generator->generate($post_type);

            

            $feed_result = $this->save_feed_file($yml, 'doctors.yml');

            $generated_at = $feed_result['generated_at'] ?? current_time('mysql');

            

            // ╨Ы╨╛╨│╨╕╤А╨╛╨▓╨░╨╜╨╕╨╡

            $this->log_update('success', '╨д╨╕╨┤ ╨░╨▓╤В╨╛╨╝╨░╤В╨╕╤З╨╡╤Б╨║╨╕ ╨╛╨▒╨╜╨╛╨▓╨╗╤С╨╜ (' . $generated_at . ')');

        } catch (Exception $e) {

            $error_handler->handle_cron_error($e, 'yfgp_auto_update_feed');

            $this->log_update('error', $e->getMessage());

        } catch (Throwable $e) {

            $error_handler->handle_cron_error($e, 'yfgp_auto_update_feed');

            $this->log_update('error', $e->getMessage());

        }

    }

    

    /**

     * ╨Ы╨╛╨│╨╕╤А╨╛╨▓╨░╨╜╨╕╨╡ ╨╛╨▒╨╜╨╛╨▓╨╗╨╡╨╜╨╕╨╣

     */

    private function log_update($status, $message) {

        $history = get_option('yfgp_feed_history', array());

        

        $history[] = array(

            'date' => current_time('mysql'),

            'status' => $status,

            'message' => $message

        );

        

        // ╨е╤А╨░╨╜╨╕╨╝ ╤В╨╛╨╗╤М╨║╨╛ ╨┐╨╛╤Б╨╗╨╡╨┤╨╜╨╕╨╡ 50 ╨╖╨░╨┐╨╕╤Б╨╡╨╣

        if (count($history) > 50) {

            $history = array_slice($history, -50);

        }

        

        update_option('yfgp_feed_history', $history);

        

        // ╨Ю╤В╨┐╤А╨░╨▓╨║╨░ email ╤Г╨▓╨╡╨┤╨╛╨╝╨╗╨╡╨╜╨╕╤П

        $this->send_notification($status, $message);

    }

    

    /**

     * ╨Ю╤В╨┐╤А╨░╨▓╨║╨░ email ╤Г╨▓╨╡╨┤╨╛╨╝╨╗╨╡╨╜╨╕╤П

     */

    private function send_notification($status, $message) {

        $settings = get_option('yfgp_settings', array());

        

        // ╨Я╤А╨╛╨▓╨╡╤А╤П╨╡╨╝, ╨▓╨║╨╗╤О╤З╨╡╨╜╤Л ╨╗╨╕ ╤Г╨▓╨╡╨┤╨╛╨╝╨╗╨╡╨╜╨╕╤П

        if (empty($settings['email_notifications'])) {

            return;

        }

        

        // ╨Я╤А╨╛╨▓╨╡╤А╤П╨╡╨╝, ╨╜╤Г╨╢╨╜╨╛ ╨╗╨╕ ╨╛╤В╨┐╤А╨░╨▓╨╗╤П╤В╤М ╨┤╨╗╤П ╤Н╤В╨╛╨│╨╛ ╤Б╤В╨░╤В╤Г╤Б╨░

        if ($status === 'success' && empty($settings['notify_on_success'])) {

            return;

        }

        

        if ($status === 'error' && empty($settings['notify_on_error'])) {

            return;

        }

        

        $to = $settings['notification_email'] ?? get_option('admin_email');

        $site_name = get_bloginfo('name');

        

        if ($status === 'success') {

            $subject = '[' . $site_name . '] тЬЕ Yandex Feed: ╨г╤Б╨┐╨╡╤И╨╜╨╛╨╡ ╨╛╨▒╨╜╨╛╨▓╨╗╨╡╨╜╨╕╨╡';

            $body = "╨Ф╨╛╨▒╤А╤Л╨╣ ╨┤╨╡╨╜╤М!\n\n";

            $body .= "╨д╨╕╨┤ Yandex ╤Г╤Б╨┐╨╡╤И╨╜╨╛ ╨╛╨▒╨╜╨╛╨▓╨╗╤С╨╜.\n\n";

            $body .= "╨Ф╨░╤В╨░: " . current_time('d.m.Y H:i') . "\n";

            $body .= "╨б╨╛╨╛╨▒╤Й╨╡╨╜╨╕╨╡: " . $message . "\n\n";

            $body .= "URL ╤Д╨╕╨┤╨░: " . home_url('/wp-content/uploads/feed/doctors.yml') . "\n\n";

            $body .= "---\n";

            $body .= "╨н╤В╨╛ ╨░╨▓╤В╨╛╨╝╨░╤В╨╕╤З╨╡╤Б╨║╨╛╨╡ ╤Г╨▓╨╡╨┤╨╛╨╝╨╗╨╡╨╜╨╕╨╡ ╨╛╤В ╨┐╨╗╨░╨│╨╕╨╜╨░ Yandex Feed Generator Pro";

        } else {

            $subject = '[' . $site_name . '] тЭМ Yandex Feed: ╨Ю╤И╨╕╨▒╨║╨░ ╨╛╨▒╨╜╨╛╨▓╨╗╨╡╨╜╨╕╤П';

            $body = "╨Ф╨╛╨▒╤А╤Л╨╣ ╨┤╨╡╨╜╤М!\n\n";

            $body .= "╨Я╤А╨╕ ╨╛╨▒╨╜╨╛╨▓╨╗╨╡╨╜╨╕╨╕ ╤Д╨╕╨┤╨░ Yandex ╨┐╤А╨╛╨╕╨╖╨╛╤И╨╗╨░ ╨╛╤И╨╕╨▒╨║╨░.\n\n";

            $body .= "╨Ф╨░╤В╨░: " . current_time('d.m.Y H:i') . "\n";

            $body .= "╨Ю╤И╨╕╨▒╨║╨░: " . $message . "\n\n";

            $body .= "╨Я╨╛╨╢╨░╨╗╤Г╨╣╤Б╤В╨░, ╨┐╤А╨╛╨▓╨╡╤А╤М╤В╨╡ ╨╜╨░╤Б╤В╤А╨╛╨╣╨║╨╕ ╨┐╨╗╨░╨│╨╕╨╜╨░.\n\n";

            $body .= "---\n";

            $body .= "╨н╤В╨╛ ╨░╨▓╤В╨╛╨╝╨░╤В╨╕╤З╨╡╤Б╨║╨╛╨╡ ╤Г╨▓╨╡╨┤╨╛╨╝╨╗╨╡╨╜╨╕╨╡ ╨╛╤В ╨┐╨╗╨░╨│╨╕╨╜╨░ Yandex Feed Generator Pro";

        }

        

        wp_mail($to, $subject, $body);

    }

    

    /**

     * AJAX: ╨б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╡ ╨╛╤В╤А╨╡╨┤╨░╨║╤В╨╕╤А╨╛╨▓╨░╨╜╨╜╨╛╨│╨╛ XML

     */

    public function ajax_save_edited_xml() {

        // v4.18.13: ╨Х╨┤╨╕╨╜╤Л╨╣ ╤Б╤В╨░╨╜╨┤╨░╤А╤В nonce ╨┤╨╗╤П ╨▓╤Б╨╡╤Е AJAX handlers

        check_ajax_referer('yfgp_ajax_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            wp_send_json_error('╨Э╨╡╨┤╨╛╤Б╤В╨░╤В╨╛╤З╨╜╨╛ ╨┐╤А╨░╨▓');

        }

        

        $xml_content = wp_unslash($_POST['xml_content'] ?? '');
        
        // v4.18.22: Security - Check XML size limit (50MB)
        $max_xml_size = 50 * 1024 * 1024; // 50MB
        if (strlen($xml_content) > $max_xml_size) {
            if (class_exists('YFGP_Logger')) {
                YFGP_Logger::get_instance()->warning('YFGP Security: XML content too large: ' . strlen($xml_content) . ' bytes (max: ' . $max_xml_size . ' bytes)');
            } else {
                error_log('YFGP Security: XML content too large: ' . strlen($xml_content) . ' bytes (max: ' . $max_xml_size . ' bytes)');
            }
            wp_send_json_error('╨а╨░╨╖╨╝╨╡╤А XML ╨┐╤А╨╡╨▓╤Л╤И╨░╨╡╤В ╨╝╨░╨║╤Б╨╕╨╝╨░╨╗╤М╨╜╤Л╨╣ ╨╗╨╕╨╝╨╕╤В (' . round($max_xml_size / 1024 / 1024, 2) . ' MB)');
            return;
        }

        // ╨Я╤А╨╛╤Б╤В╨░╤П ╨▓╨░╨╗╨╕╨┤╨░╤Ж╨╕╤П XML
        // v4.20.0: XXE Protection - ╨╛╤В╨║╨╗╤О╤З╨╡╨╜╨╕╨╡ external entity loader ╨┐╨╡╤А╨╡╨┤ ╨┐╨░╤А╤Б╨╕╨╜╨│╨╛╨╝
        $libxml_previous_state = null;
        if (function_exists('libxml_disable_entity_loader')) {
            $libxml_previous_state = libxml_disable_entity_loader(true);
        }
        
        libxml_use_internal_errors(true);
        
        // ╨Ш╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╨╝ LIBXML_NONET ╨┤╨╗╤П ╨┐╤А╨╡╨┤╨╛╤В╨▓╤А╨░╤Й╨╡╨╜╨╕╤П ╤Б╨╡╤В╨╡╨▓╤Л╤Е ╨╖╨░╨┐╤А╨╛╤Б╨╛╨▓ (XXE protection)
        $xml = simplexml_load_string($xml_content, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOENT);
        
        // ╨Т╨╛╤Б╤Б╤В╨░╨╜╨░╨▓╨╗╨╕╨▓╨░╨╡╨╝ ╨┐╤А╨╡╨┤╤Л╨┤╤Г╤Й╨╡╨╡ ╤Б╨╛╤Б╤В╨╛╤П╨╜╨╕╨╡ entity loader
        if ($libxml_previous_state !== null && function_exists('libxml_disable_entity_loader')) {
            libxml_disable_entity_loader($libxml_previous_state);
        }

        

        if ($xml === false) {

            $errors = libxml_get_errors();

            $error_msg = '╨Ю╤И╨╕╨▒╨║╨░ XML: ';

            foreach ($errors as $error) {

                $error_msg .= $error->message . ' ';

            }

            libxml_clear_errors();

            wp_send_json_error($error_msg);

        }

        

        try {

            // v4.18.0: ╨Ш╤Б╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╤М ╨┤╨╕╨╜╨░╨╝╨╕╤З╨╡╤Б╨║╨╛╨╡ ╨╕╨╝╤П ╤Д╨░╨╣╨╗╨░ ╨╜╨░ ╨╛╤Б╨╜╨╛╨▓╨╡ post_type ╨╕╨╖ ╨╜╨░╤Б╤В╤А╨╛╨╡╨║

            $settings = get_option('yfgp_settings', array());

            $post_type = $settings['post_type'] ?? yfgp_get_default_post_type_safe();
            if (empty($post_type)) {
                error_log('YFGP v4.19.2 WARNING: Post type not configured. Please set post_type in plugin settings.');
                return;
            }

            $feed_filename = sanitize_file_name($post_type) . '.yml';

            

            $feed_result = $this->save_feed_file($xml_content, $feed_filename);

            

            $this->log_update('success', '╨д╨╕╨┤ ╨╛╨▒╨╜╨╛╨▓╨╗╤С╨╜ ╨▓╤А╤Г╤З╨╜╤Г╤О ╤З╨╡╤А╨╡╨╖ ╤А╨╡╨┤╨░╨║╤В╨╛╤А');

            

            wp_send_json_success(array(

                'message'       => 'XML ╤Г╤Б╨┐╨╡╤И╨╜╨╛ ╤Б╨╛╤Е╤А╨░╨╜╤С╨╜',

                'file_url'      => $feed_result['url'],

                'generated_at'  => $feed_result['generated_at'] ?? current_time('mysql'),

                'mtime'         => $feed_result['mtime'] ?? null,

                'bytes_written' => $feed_result['bytes'] ?? null,

            ));

        } catch (Exception $e) {

            wp_send_json_error($e->getMessage());

        }

    }

    

    /**

     * AJAX: ╨н╨║╤Б╨┐╨╛╤А╤В ╨║╨╛╨╜╤Д╨╕╨│╤Г╤А╨░╤Ж╨╕╨╕

     */

    public function ajax_export_config() {

        // v4.18.13: ╨Х╨┤╨╕╨╜╤Л╨╣ ╤Б╤В╨░╨╜╨┤╨░╤А╤В nonce ╨┤╨╗╤П ╨▓╤Б╨╡╤Е AJAX handlers

        check_ajax_referer('yfgp_ajax_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            wp_send_json_error('╨Э╨╡╨┤╨╛╤Б╤В╨░╤В╨╛╤З╨╜╨╛ ╨┐╤А╨░╨▓');

        }

        

        $config = array(

            'settings' => get_option('yfgp_settings', array()),

            'mapping' => get_option('yfgp_field_mapping', array()),

            'version' => YFGP_VERSION,

            'export_date' => current_time('mysql')

        );

        

        wp_send_json_success($config);

    }

    

    /**

     * AJAX: ╨Ш╨╝╨┐╨╛╤А╤В ╨║╨╛╨╜╤Д╨╕╨│╤Г╤А╨░╤Ж╨╕╨╕

     */

    public function ajax_import_config() {

        // v4.18.13: ╨Х╨┤╨╕╨╜╤Л╨╣ ╤Б╤В╨░╨╜╨┤╨░╤А╤В nonce ╨┤╨╗╤П ╨▓╤Б╨╡╤Е AJAX handlers

        check_ajax_referer('yfgp_ajax_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            wp_send_json_error('╨Э╨╡╨┤╨╛╤Б╤В╨░╤В╨╛╤З╨╜╨╛ ╨┐╤А╨░╨▓');

        }

        

        $config_json = wp_unslash($_POST['config_json'] ?? '');
        
        // v4.18.22: Security - Check JSON size limit (5MB)
        $max_json_size = 5 * 1024 * 1024; // 5MB
        if (strlen($config_json) > $max_json_size) {
            if (class_exists('YFGP_Logger')) {
                YFGP_Logger::get_instance()->warning('YFGP Security: Config JSON too large: ' . strlen($config_json) . ' bytes (max: ' . $max_json_size . ' bytes)');
            } else {
                error_log('YFGP Security: Config JSON too large: ' . strlen($config_json) . ' bytes (max: ' . $max_json_size . ' bytes)');
            }
            wp_send_json_error('╨а╨░╨╖╨╝╨╡╤А JSON ╨┐╤А╨╡╨▓╤Л╤И╨░╨╡╤В ╨╝╨░╨║╤Б╨╕╨╝╨░╨╗╤М╨╜╤Л╨╣ ╨╗╨╕╨╝╨╕╤В (' . round($max_json_size / 1024 / 1024, 2) . ' MB)');
            return;
        }

        $config = json_decode($config_json, true);
        
        // v4.18.22: Security - Validate JSON decoding
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('╨Ю╤И╨╕╨▒╨║╨░ ╨┤╨╡╨║╨╛╨┤╨╕╤А╨╛╨▓╨░╨╜╨╕╤П JSON: ' . json_last_error_msg());
            return;
        }
        
        // v4.18.22: Security - Validate config structure
        if (!$config || !is_array($config)) {
            wp_send_json_error('╨Э╨╡╨▓╨╡╤А╨╜╤Л╨╣ ╤Д╨╛╤А╨╝╨░╤В ╨║╨╛╨╜╤Д╨╕╨│╤Г╤А╨░╤Ж╨╕╨╕: ╨╛╨╢╨╕╨┤╨░╨╡╤В╤Б╤П ╨╝╨░╤Б╤Б╨╕╨▓');
            return;
        }
        
        if (!isset($config['settings']) || !is_array($config['settings'])) {
            wp_send_json_error('╨Э╨╡╨▓╨╡╤А╨╜╤Л╨╣ ╤Д╨╛╤А╨╝╨░╤В ╨║╨╛╨╜╤Д╨╕╨│╤Г╤А╨░╤Ж╨╕╨╕: ╨╛╤В╤Б╤Г╤В╤Б╤В╨▓╤Г╨╡╤В ╨╕╨╗╨╕ ╨╜╨╡╨║╨╛╤А╤А╨╡╨║╤В╨╜╨╛ ╨┐╨╛╨╗╨╡ settings');
            return;
        }
        
        if (!isset($config['mapping']) || !is_array($config['mapping'])) {
            wp_send_json_error('╨Э╨╡╨▓╨╡╤А╨╜╤Л╨╣ ╤Д╨╛╤А╨╝╨░╤В ╨║╨╛╨╜╤Д╨╕╨│╤Г╤А╨░╤Ж╨╕╨╕: ╨╛╤В╤Б╤Г╤В╤Б╤В╨▓╤Г╨╡╤В ╨╕╨╗╨╕ ╨╜╨╡╨║╨╛╤А╤А╨╡╨║╤В╨╜╨╛ ╨┐╨╛╨╗╨╡ mapping');
            return;
        }
        
        // v4.18.22: Security - Validate version compatibility
        if (isset($config['version'])) {
            $imported_version = $config['version'];
            $current_version = defined('YFGP_VERSION') ? YFGP_VERSION : '4.18.0';
            
            // Allow import from same major version or older
            if (version_compare($imported_version, $current_version, '>')) {
                wp_send_json_error('╨Т╨╡╤А╤Б╨╕╤П ╨╕╨╝╨┐╨╛╤А╤В╨╕╤А╤Г╨╡╨╝╨╛╨╣ ╨║╨╛╨╜╤Д╨╕╨│╤Г╤А╨░╤Ж╨╕╨╕ (' . $imported_version . ') ╨╜╨╛╨▓╨╡╨╡ ╤В╨╡╨║╤Г╤Й╨╡╨╣ ╨▓╨╡╤А╤Б╨╕╨╕ ╨┐╨╗╨░╨│╨╕╨╜╨░ (' . $current_version . ')');
                return;
            }
        }
        
        // v4.18.22: Security - Validate and sanitize imported config
        if (class_exists('YFGP_Data_Sanitizer')) {
            $sanitizer = new YFGP_Data_Sanitizer();
            $config['settings'] = $sanitizer->sanitize($config['settings']);
            $config['mapping'] = $sanitizer->sanitize($config['mapping']);
        } else {
            // Fallback sanitization
            $config['settings'] = array_map('sanitize_text_field', $config['settings']);
        }

        

        update_option('yfgp_settings', $config['settings']);

        update_option('yfgp_field_mapping', $config['mapping']);

        

        wp_send_json_success('╨Ъ╨╛╨╜╤Д╨╕╨│╤Г╤А╨░╤Ж╨╕╤П ╨╕╨╝╨┐╨╛╤А╤В╨╕╤А╨╛╨▓╨░╨╜╨░');

    }

    

    /**

     * AJAX: ╨Я╤А╨╕╨╝╨╡╨╜╨╡╨╜╨╕╨╡ ╤И╨░╨▒╨╗╨╛╨╜╨░

     */

    public function ajax_apply_template() {

        // v4.18.13: ╨Х╨┤╨╕╨╜╤Л╨╣ ╤Б╤В╨░╨╜╨┤╨░╤А╤В nonce ╨┤╨╗╤П ╨▓╤Б╨╡╤Е AJAX handlers

        check_ajax_referer('yfgp_ajax_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            wp_send_json_error('╨Э╨╡╨┤╨╛╤Б╤В╨░╤В╨╛╤З╨╜╨╛ ╨┐╤А╨░╨▓');

        }

        

        $template_id = sanitize_text_field($_POST['template_id'] ?? '');

        

        $templates = new YFGP_Templates();

        $result = $templates->apply_template($template_id);

        

        if ($result) {

            wp_send_json_success('╨и╨░╨▒╨╗╨╛╨╜ ╨┐╤А╨╕╨╝╨╡╨╜╤С╨╜');

        } else {

            wp_send_json_error('╨и╨░╨▒╨╗╨╛╨╜ ╨╜╨╡ ╨╜╨░╨╣╨┤╨╡╨╜');

        }

    }

    

    /**

     * AJAX: ╨Я╨╛╨╗╤Г╤З╨╡╨╜╨╕╨╡ ╤В╨╡╤А╨╝╨╕╨╜╨╛╨▓ ╤В╨░╨║╤Б╨╛╨╜╨╛╨╝╨╕╨╕

     */

    /**

     * v4.18.10: Wrapper ╨┤╨╗╤П wp_send_json_success ╤Б ╤Г╨┤╨░╨╗╨╡╨╜╨╕╨╡╨╝ BOM

     */

    private function send_json_success_no_bom($data = null) {

        // ╨Ю╤З╨╕╤Б╤В╨║╨░ ╨▓╤Б╨╡╤Е output buffers

        while (ob_get_level()) {

            ob_end_clean();

        }

        

        // ╨Я╨╛╨╗╤Г╤З╨░╨╡╨╝ JSON ╨╛╤В╨▓╨╡╤В

        $json = wp_json_encode(array('success' => true, 'data' => $data));

        

        // v4.18.11: ╨г╨┤╨░╨╗╤П╨╡╨╝ ╨Т╨б╨Х BOM ╨┐╨╛╨┤╤А╤П╨┤ (╨╝╨╛╨╢╨╡╤В ╨▒╤Л╤В╤М ╨╜╨╡╤Б╨║╨╛╨╗╤М╨║╨╛!)

        while (strlen($json) > 0 && (

            substr($json, 0, 3) === "\xEF\xBB\xBF" ||

            (strlen($json) > 0 && ord($json[0]) === 0xEF && ord($json[1]) === 0xBB && ord($json[2]) === 0xBF)

        )) {

            $json = substr($json, 3);

        }

        // ╨в╨░╨║╨╢╨╡ ╤Г╨┤╨░╨╗╤П╨╡╨╝ Unicode BOM (U+FEFF) ╨╡╤Б╨╗╨╕ ╨╡╤Б╤В╤М

        while (strlen($json) > 0 && (

            mb_substr($json, 0, 1, 'UTF-8') === "\xEF\xBB\xBF" ||

            (function_exists('mb_ord') && mb_ord(mb_substr($json, 0, 1, 'UTF-8'), 'UTF-8') === 0xFEFF)

        )) {

            $json = mb_substr($json, 1, null, 'UTF-8');

        }

        

        // ╨Ю╤В╨┐╤А╨░╨▓╨╗╤П╨╡╨╝ ╨╛╤З╨╕╤Й╨╡╨╜╨╜╤Л╨╣ ╨╛╤В╨▓╨╡╤В

        header('Content-Type: application/json; charset=utf-8');

        echo $json;

        wp_die();

    }

    

    /**

     * v4.18.10: Wrapper ╨┤╨╗╤П wp_send_json_error ╤Б ╤Г╨┤╨░╨╗╨╡╨╜╨╕╨╡╨╝ BOM

     */

    private function send_json_error_no_bom($data = null) {

        // ╨Ю╤З╨╕╤Б╤В╨║╨░ ╨▓╤Б╨╡╤Е output buffers

        while (ob_get_level()) {

            ob_end_clean();

        }

        

        // ╨Я╨╛╨╗╤Г╤З╨░╨╡╨╝ JSON ╨╛╤В╨▓╨╡╤В

        $json = wp_json_encode(array('success' => false, 'data' => $data));

        

        // v4.18.11: ╨г╨┤╨░╨╗╤П╨╡╨╝ ╨Т╨б╨Х BOM ╨┐╨╛╨┤╤А╤П╨┤ (╨╝╨╛╨╢╨╡╤В ╨▒╤Л╤В╤М ╨╜╨╡╤Б╨║╨╛╨╗╤М╨║╨╛!)

        while (strlen($json) > 0 && (

            substr($json, 0, 3) === "\xEF\xBB\xBF" ||

            (strlen($json) > 0 && ord($json[0]) === 0xEF && ord($json[1]) === 0xBB && ord($json[2]) === 0xBF)

        )) {

            $json = substr($json, 3);

        }

        // ╨в╨░╨║╨╢╨╡ ╤Г╨┤╨░╨╗╤П╨╡╨╝ Unicode BOM (U+FEFF) ╨╡╤Б╨╗╨╕ ╨╡╤Б╤В╤М

        while (strlen($json) > 0 && (

            mb_substr($json, 0, 1, 'UTF-8') === "\xEF\xBB\xBF" ||

            (function_exists('mb_ord') && mb_ord(mb_substr($json, 0, 1, 'UTF-8'), 'UTF-8') === 0xFEFF)

        )) {

            $json = mb_substr($json, 1, null, 'UTF-8');

        }

        

        // ╨Ю╤В╨┐╤А╨░╨▓╨╗╤П╨╡╨╝ ╨╛╤З╨╕╤Й╨╡╨╜╨╜╤Л╨╣ ╨╛╤В╨▓╨╡╤В

        header('Content-Type: application/json; charset=utf-8');

        echo $json;

        wp_die();

    }

    

    public function ajax_get_terms() {

        // v4.18.0: ╨Ш╤Б╨┐╤А╨░╨▓╨╗╨╡╨╜╨░ ╨┐╤А╨╛╨▓╨╡╤А╨║╨░ nonce - ╨╕╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╨╝ 'nonce' ╨▓╨╝╨╡╤Б╤В╨╛ '_ajax_nonce'

        // v4.18.13: ╨Х╨┤╨╕╨╜╤Л╨╣ ╤Б╤В╨░╨╜╨┤╨░╤А╤В nonce ╨┤╨╗╤П ╨▓╤Б╨╡╤Е AJAX handlers

        if (!check_ajax_referer('yfgp_ajax_nonce', 'nonce', false)) {

            $this->send_json_error_no_bom(array('message' => 'Invalid nonce'));

            return;

        }

        

        if (!current_user_can('manage_options')) {

            $this->send_json_error_no_bom(array('message' => '╨Э╨╡╨┤╨╛╤Б╤В╨░╤В╨╛╤З╨╜╨╛ ╨┐╤А╨░╨▓'));

            return;

        }

        

        $taxonomy = sanitize_key($_POST['taxonomy'] ?? '');
        
        // v4.18.22: Security - Validate taxonomy exists
        if (empty($taxonomy)) {
            if (class_exists('YFGP_Logger')) {
                YFGP_Logger::get_instance()->warning('YFGP: Taxonomy not specified in ajax_get_terms');
            } else {
                error_log('[YFGP] ajax_get_terms: ╨в╨░╨║╤Б╨╛╨╜╨╛╨╝╨╕╤П ╨╜╨╡ ╤Г╨║╨░╨╖╨░╨╜╨░');
            }
            wp_send_json_error('╨в╨░╨║╤Б╨╛╨╜╨╛╨╝╨╕╤П ╨╜╨╡ ╤Г╨║╨░╨╖╨░╨╜╨░');
            return;
        }
        
        // v4.18.22: Security - Validate taxonomy exists
        if (!taxonomy_exists($taxonomy)) {
            if (class_exists('YFGP_Logger')) {
                YFGP_Logger::get_instance()->warning('YFGP: Taxonomy does not exist: ' . $taxonomy);
            } else {
                error_log('YFGP: Taxonomy does not exist: ' . $taxonomy);
            }
            wp_send_json_error('╨в╨░╨║╤Б╨╛╨╜╨╛╨╝╨╕╤П "' . esc_html($taxonomy) . '" ╨╜╨╡ ╤Б╤Г╤Й╨╡╤Б╤В╨▓╤Г╨╡╤В');
            return;
        }

        $terms = get_terms(array(

            'taxonomy' => $taxonomy,

            'hide_empty' => false,

        ));

        

        // v4.18.22: Improved error handling
        if (is_wp_error($terms)) {
            if (class_exists('YFGP_Logger')) {
                YFGP_Logger::get_instance()->error('YFGP: Error getting terms: ' . $terms->get_error_message());
            } else {
                error_log('YFGP: Error getting terms: ' . $terms->get_error_message());
            }
            wp_send_json_error('╨Ю╤И╨╕╨▒╨║╨░ ╨┐╨╛╨╗╤Г╤З╨╡╨╜╨╕╤П ╤В╨╡╤А╨╝╨╕╨╜╨╛╨▓: ' . $terms->get_error_message());
            return;
        }

        

        $terms_array = array();

        foreach ($terms as $term) {

            $terms_array[$term->slug] = $term->name;

        }

        

        // v4.18.22: Logging for debugging (only if no terms found)
        if (empty($terms_array)) {
            if (class_exists('YFGP_Logger')) {
                YFGP_Logger::get_instance()->info('YFGP: Taxonomy "' . $taxonomy . '" exists, but no terms found');
            } else {
                error_log('[YFGP] ajax_get_terms: ╨в╨░╨║╤Б╨╛╨╜╨╛╨╝╨╕╤П "' . $taxonomy . '" ╤Б╤Г╤Й╨╡╤Б╤В╨▓╤Г╨╡╤В, ╨╜╨╛ ╤В╨╡╤А╨╝╨╕╨╜╨╛╨▓ ╨╜╨╡ ╨╜╨░╨╣╨┤╨╡╨╜╨╛');
            }
        }

        

        $this->send_json_success_no_bom(array('terms' => $terms_array));

    }

    

    /**

     * AJAX: ╨Т╨░╨╗╨╕╨┤╨░╤Ж╨╕╤П ╤Д╨╕╨┤╨░ (v4.18.0)

     */

    public function ajax_validate_feed() {

        // v4.18.13: ╨Х╨┤╨╕╨╜╤Л╨╣ ╤Б╤В╨░╨╜╨┤╨░╤А╤В nonce ╨┤╨╗╤П ╨▓╤Б╨╡╤Е AJAX handlers

        check_ajax_referer('yfgp_ajax_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            wp_send_json_error('╨Э╨╡╨┤╨╛╤Б╤В╨░╤В╨╛╤З╨╜╨╛ ╨┐╤А╨░╨▓');

        }

        

        try {

            // ╨Я╨╛╨╗╤Г╤З╨░╨╡╨╝ ╨╜╨░╤Б╤В╤А╨╛╨╣╨║╨╕ ╨╕ ╨│╨╡╨╜╨╡╤А╨╕╤А╤Г╨╡╨╝ ╤Д╨╕╨┤ ╨┤╨╗╤П ╨▓╨░╨╗╨╕╨┤╨░╤Ж╨╕╨╕

            $settings = get_option('yfgp_settings', array());

            $post_type = $settings['post_type'] ?? yfgp_get_default_post_type_safe();
            if (empty($post_type)) {
                error_log('YFGP v4.19.2 WARNING: Post type not configured. Please set post_type in plugin settings.');
                return;
            }

            $feed_format = $settings['feed_format'] ?? 'v2';

            

            // ╨Ш╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╨╝ ╤В╨╛╤В ╨╢╨╡ ╨│╨╡╨╜╨╡╤А╨░╤В╨╛╤А, ╤З╤В╨╛ ╨╕ ajax_generate_feed

            if ($feed_format === 'v2') {

                $feed_generator = new YFGP_Feed_Generator_V2();

            } else {

                $feed_generator = new YFGP_Feed_Generator();

            }

            

            $yml = $feed_generator->generate($post_type);

            

            if (empty($yml)) {

                wp_send_json_error('╨Э╨╡ ╤Г╨┤╨░╨╗╨╛╤Б╤М ╤Б╨│╨╡╨╜╨╡╤А╨╕╤А╨╛╨▓╨░╤В╤М ╤Д╨╕╨┤ ╨┤╨╗╤П ╨▓╨░╨╗╨╕╨┤╨░╤Ж╨╕╨╕');

            }

            

            // ╨Т╨░╨╗╨╕╨┤╨░╤Ж╨╕╤П XML ╤Б╤В╤А╤Г╨║╤В╤Г╤А╤Л
            // v4.20.0: XXE Protection - ╨╛╤В╨║╨╗╤О╤З╨╡╨╜╨╕╨╡ external entity loader ╨┐╨╡╤А╨╡╨┤ ╨┐╨░╤А╤Б╨╕╨╜╨│╨╛╨╝
            $libxml_previous_state = null;
            if (function_exists('libxml_disable_entity_loader')) {
                $libxml_previous_state = libxml_disable_entity_loader(true);
            }
            
            libxml_use_internal_errors(true);
            
            // ╨Ш╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╨╝ LIBXML_NONET ╨┤╨╗╤П ╨┐╤А╨╡╨┤╨╛╤В╨▓╤А╨░╤Й╨╡╨╜╨╕╤П ╤Б╨╡╤В╨╡╨▓╤Л╤Е ╨╖╨░╨┐╤А╨╛╤Б╨╛╨▓ (XXE protection)
            $xml = simplexml_load_string($yml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOENT);
            
            // ╨Т╨╛╤Б╤Б╤В╨░╨╜╨░╨▓╨╗╨╕╨▓╨░╨╡╨╝ ╨┐╤А╨╡╨┤╤Л╨┤╤Г╤Й╨╡╨╡ ╤Б╨╛╤Б╤В╨╛╤П╨╜╨╕╨╡ entity loader
            if ($libxml_previous_state !== null && function_exists('libxml_disable_entity_loader')) {
                libxml_disable_entity_loader($libxml_previous_state);
            }

            

            if ($xml === false) {

                $errors = libxml_get_errors();

                $error_messages = array();

                foreach ($errors as $error) {

                    $error_messages[] = trim($error->message);

                }

                libxml_clear_errors();

                wp_send_json_error('╨Ю╤И╨╕╨▒╨║╨╕ ╨▓╨░╨╗╨╕╨┤╨░╤Ж╨╕╨╕ XML: ' . implode('; ', array_unique($error_messages)));

            }

            

            // ╨Ф╨╛╨┐╨╛╨╗╨╜╨╕╤В╨╡╨╗╤М╨╜╨░╤П ╨▓╨░╨╗╨╕╨┤╨░╤Ж╨╕╤П ╤Б╤В╤А╤Г╨║╤В╤Г╤А╤Л YML ╨┤╨╗╤П ╨п╨╜╨┤╨╡╨║╤Б.╨Ч╨┤╨╛╤А╨╛╨▓╤М╨╡

            $validation_errors = array();

            

            // ╨Ъ╨╛╤А╨╜╨╡╨▓╨╛╨╣ ╤Н╨╗╨╡╨╝╨╡╨╜╤В ╨┤╨╛╨╗╨╢╨╡╨╜ ╨▒╤Л╤В╤М <shop>

            $root_name = $xml->getName();

            if ($root_name !== 'shop') {

                $validation_errors[] = '╨Ю╨╢╨╕╨┤╨░╨╗╤Б╤П ╨║╨╛╤А╨╜╨╡╨▓╨╛╨╣ ╤Н╨╗╨╡╨╝╨╡╨╜╤В &lt;shop&gt;, ╨┐╨╛╨╗╤Г╤З╨╡╨╜ &lt;' . esc_html($root_name) . '&gt;';

            } else {

                if (!isset($xml->name) || $xml->name === '') {

                    $validation_errors[] = '╨Ю╤В╤Б╤Г╤В╤Б╤В╨▓╤Г╨╡╤В ╤Н╨╗╨╡╨╝╨╡╨╜╤В &lt;shop&gt;&lt;name&gt;';

                }

                if (!isset($xml->company) || $xml->company === '') {

                    $validation_errors[] = '╨Ю╤В╤Б╤Г╤В╤Б╤В╨▓╤Г╨╡╤В ╤Н╨╗╨╡╨╝╨╡╨╜╤В &lt;shop&gt;&lt;company&gt;';

                }

            }

            

            if (!empty($validation_errors)) {

                wp_send_json_error('╨Ю╤И╨╕╨▒╨║╨╕ ╤Б╤В╤А╤Г╨║╤В╤Г╤А╤Л YML: ' . implode('; ', $validation_errors));

            }

            

            wp_send_json_success('╨д╨╕╨┤ ╨▓╨░╨╗╨╕╨┤╨╡╨╜');

            

        } catch (Exception $e) {

            wp_send_json_error('╨Ю╤И╨╕╨▒╨║╨░ ╨▓╨░╨╗╨╕╨┤╨░╤Ж╨╕╨╕: ' . $e->getMessage());

        }

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

