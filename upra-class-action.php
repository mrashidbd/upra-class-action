<?php
/**
 * Plugin Name: UPRA Class Action
 * Plugin URI: https://mrashid.me/
 * Description: A comprehensive plugin for collecting shareholder data for class action lawsuits. Supports multiple companies including ATOS, URPEA, and others.
 * Version: 1.0.0
 * Author: Mamunur Rashid
 * License: GPL v2 or later
 * Text Domain: upra-class-action
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('UPRA_CLASS_ACTION_VERSION', '1.0.0');
define('UPRA_CLASS_ACTION_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UPRA_CLASS_ACTION_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UPRA_CLASS_ACTION_PLUGIN_FILE', __FILE__);

/**
 * Main UPRA Class Action Plugin Class
 */
class UPRA_Class_Action {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Get single instance of the class
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'));
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Remove WordPress scheduled delete (as in original code)
        add_action('init', array($this, 'remove_schedule_delete'));
        
        // REST API authentication
        add_filter('rest_authentication_errors', array($this, 'rest_authentication_errors'));
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once UPRA_CLASS_ACTION_PLUGIN_DIR . 'includes/class-database.php';
        require_once UPRA_CLASS_ACTION_PLUGIN_DIR . 'includes/class-admin.php';
        require_once UPRA_CLASS_ACTION_PLUGIN_DIR . 'includes/class-frontend.php';
        require_once UPRA_CLASS_ACTION_PLUGIN_DIR . 'includes/class-ajax-handler.php';
        require_once UPRA_CLASS_ACTION_PLUGIN_DIR . 'includes/class-email-handler.php';
        
        // Company-specific classes
        require_once UPRA_CLASS_ACTION_PLUGIN_DIR . 'includes/companies/class-atos.php';
        
        // Admin classes (only for admin users)
        if (is_admin()) {
            require_once UPRA_CLASS_ACTION_PLUGIN_DIR . 'admin/class-admin-menu.php';
            require_once UPRA_CLASS_ACTION_PLUGIN_DIR . 'admin/class-list-table.php';
        }
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize core components
        UPRA_Class_Action_Database::get_instance();
        UPRA_Class_Action_Frontend::get_instance();
        UPRA_Class_Action_Ajax_Handler::get_instance();
        UPRA_Class_Action_Email_Handler::get_instance();
        
        // Initialize company-specific components
        UPRA_Class_Action_ATOS::get_instance();
        
        // Initialize admin components (only for admin users)
        if (is_admin()) {
            UPRA_Class_Action_Admin::get_instance();
            UPRA_Class_Action_Admin_Menu::get_instance();
        }
    }

    /**
     * Load plugin textdomain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'upra-class-action',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        UPRA_Class_Action_Database::create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $default_options = array(
            'version' => UPRA_CLASS_ACTION_VERSION,
            'companies' => array('atos'), // Default to ATOS, can be extended
            'email_notifications' => true,
            'duplicate_check' => true
        );
        
        add_option('upra_class_action_options', $default_options);
    }

    /**
     * Remove WordPress scheduled delete (from original code)
     */
    public function remove_schedule_delete() {
        remove_action('wp_scheduled_delete', 'wp_scheduled_delete');
    }

    /**
     * REST API authentication (from original code)
     */
    public function rest_authentication_errors($result) {
        if (true === $result || is_wp_error($result)) {
            return $result;
        }

        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_not_logged_in',
                __('You are not currently logged in.', 'upra-class-action'),
                array('status' => 401)
            );
        }

        return $result;
    }

    /**
     * Get plugin version
     */
    public function get_version() {
        return UPRA_CLASS_ACTION_VERSION;
    }

    /**
     * Get plugin directory path
     */
    public function get_plugin_dir() {
        return UPRA_CLASS_ACTION_PLUGIN_DIR;
    }

    /**
     * Get plugin URL
     */
    public function get_plugin_url() {
        return UPRA_CLASS_ACTION_PLUGIN_URL;
    }
}

/**
 * Initialize the plugin
 */
function upra_class_action() {
    return UPRA_Class_Action::get_instance();
}

// Start the plugin
upra_class_action();