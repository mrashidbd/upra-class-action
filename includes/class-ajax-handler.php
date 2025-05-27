<?php
/**
 * AJAX Handler for UPRA Class Action Plugin
 * 
 * Handles all AJAX requests for shareholder data submission
 * Supports multiple companies with validation and error handling
 */

if (!defined('ABSPATH')) {
    exit;
}

class UPRA_Class_Action_Ajax_Handler {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Database instance
     */
    private $database;

    /**
     * Email handler instance
     */
    private $email_handler;

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
        $this->database = UPRA_Class_Action_Database::get_instance();
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // AJAX hooks for logged in and non-logged in users
        add_action('wp_ajax_upra_add_shareholder', array($this, 'handle_add_shareholder'));
        add_action('wp_ajax_nopriv_upra_add_shareholder', array($this, 'handle_add_shareholder'));
        
        // Get company statistics via AJAX
        add_action('wp_ajax_upra_get_company_stats', array($this, 'handle_get_company_stats'));
        add_action('wp_ajax_nopriv_upra_get_company_stats', array($this, 'handle_get_company_stats'));
        
        // Legacy ATOS support
        add_action('wp_ajax_kdb_add_member', array($this, 'handle_legacy_atos'));
        add_action('wp_ajax_nopriv_kdb_add_member', array($this, 'handle_legacy_atos'));
    }

    /**
     * Handle shareholder data submission
     */
    public function handle_add_shareholder() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'upra_shareholder_nonce')) {
            wp_send_json_error(array(
                'message' => __('Security check failed. Please refresh the page and try again.', 'upra-class-action')
            ));
        }

        // Check if form data exists
        if (!isset($_POST['shareholder_data'])) {
            wp_send_json_error(array(
                'message' => __('No form data received. Please try again.', 'upra-class-action')
            ));
        }

        // Parse form data
        $form_data = array();
        wp_parse_str($_POST['shareholder_data'], $form_data);

        // Validate form data
        $validation_result = $this->validate_form_data($form_data);
        if (is_wp_error($validation_result)) {
            wp_send_json_error($validation_result->get_error_data());
        }

        // Check for duplicates
        $company = sanitize_text_field($form_data['company'] ?? 'atos');
        $duplicate_check = $this->check_duplicate_entry($form_data, $company);
        if (is_wp_error($duplicate_check)) {
            wp_send_json_error(array(
                'message' => $duplicate_check->get_error_message()
            ));
        }

        // Prepare data for insertion
        $shareholder_data = $this->prepare_shareholder_data($form_data);

        // Insert data
        $result = $this->database->insert_shareholder_data($shareholder_data);
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => __('Failed to save data. Please try again.', 'upra-class-action')
            ));
        }

        // Send confirmation email
        $this->send_confirmation_email($shareholder_data);

        // Return success response
        wp_send_json_success(array(
            'message' => $this->get_success_message($company),
            'stats' => $this->get_company_statistics($company)
        ));
    }

    /**
     * Handle legacy ATOS AJAX requests
     */
    public function handle_legacy_atos() {
        if (isset($_POST['add_member'])) {
            // Parse legacy form data
            $form_data = array();
            wp_parse_str($_POST['add_member'], $form_data);
            
            // Add company identifier
            $form_data['company'] = 'atos';
            
            // Create nonce for security
            $_POST['nonce'] = wp_create_nonce('upra_shareholder_nonce');
            $_POST['shareholder_data'] = $_POST['add_member'];
            
            // Call the main handler
            $this->handle_add_shareholder();
        } else {
            wp_send_json_error(__('No form data received', 'upra-class-action'));
        }
    }

    /**
     * Handle getting company statistics via AJAX
     */
    public function handle_get_company_stats() {
        $company = sanitize_text_field($_POST['company'] ?? 'atos');
        $stats = $this->get_company_statistics($company);
        wp_send_json_success($stats);
    }

    /**
     * Validate form data
     */
    private function validate_form_data($form_data) {
        $errors = array();

        // Required fields validation
        $required_fields = array(
            'name' => __('Please enter your name', 'upra-class-action'),
            'email' => __('Please enter email address', 'upra-class-action'),
            'phone' => __('Please enter valid phone number', 'upra-class-action')
        );

        foreach ($required_fields as $field => $error_message) {
            if (empty($form_data[$field])) {
                $errors[] = $error_message;
            }
        }

        // Email validation
        if (!empty($form_data['email']) && !is_email($form_data['email'])) {
            $errors[] = __('Please enter a valid email address', 'upra-class-action');
        }

        // Numeric fields validation
        $numeric_fields = array('stock', 'purchase', 'sell', 'loss');
        foreach ($numeric_fields as $field) {
            if (!empty($form_data[$field]) && !is_numeric($form_data[$field])) {
                $errors[] = sprintf(
                    __('Please enter a valid number for %s', 'upra-class-action'),
                    $field
                );
            }
        }

        if (!empty($errors)) {
            return new WP_Error('validation_failed', __('Validation failed', 'upra-class-action'), $errors);
        }

        return true;
    }

    /**
     * Check for duplicate entries
     */
    private function check_duplicate_entry($form_data, $company) {
        $email = sanitize_email($form_data['email']);
        $phone = sanitize_text_field($form_data['phone']);

        $existing_id = $this->database->check_duplicate_shareholder($email, $phone, $company);

        if ($existing_id) {
            return new WP_Error(
                'duplicate_entry',
                __('Another entry matched with the phone number or email address you entered. Please contact admin@upra.fr, if you mistakenly submitted wrong information.', 'upra-class-action')
            );
        }

        return true;
    }

    /**
     * Prepare shareholder data for database insertion
     */
    private function prepare_shareholder_data($form_data) {
        return array(
            'company' => sanitize_text_field($form_data['company'] ?? 'atos'),
            'stockholder_name' => sanitize_text_field($form_data['name']),
            'email' => sanitize_email($form_data['email']),
            'phone' => sanitize_text_field($form_data['phone']),
            'stock' => !empty($form_data['stock']) ? absint($form_data['stock']) : 0,
            'purchase_price' => !empty($form_data['purchase']) ? floatval($form_data['purchase']) : 0,
            'sell_price' => !empty($form_data['sell']) ? floatval($form_data['sell']) : 0,
            'loss' => !empty($form_data['loss']) ? floatval($form_data['loss']) : 0,
            'ip' => $this->get_client_ip(),
            'country' => $this->get_client_country(),
            'remarks' => sanitize_textarea_field($form_data['remarks'] ?? '')
        );
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }

    /**
     * Get client country
     */
    private function get_client_country() {
        return 'Unknown';
    }

    /**
     * Send confirmation email
     */
    private function send_confirmation_email($shareholder_data) {
        // Check if email notifications are enabled
        $options = get_option('upra_class_action_options', array());
        $email_enabled = isset($options['email_notifications']) ? $options['email_notifications'] : true;
        
        if (!$email_enabled) {
            return false;
        }

        if (!$this->email_handler) {
            $this->email_handler = UPRA_Class_Action_Email_Handler::get_instance();
        }

        $result = $this->email_handler->send_confirmation_email(
            $shareholder_data['email'],
            $shareholder_data['company']
        );
        
        // Log email result for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('UPRA Email Result: ' . ($result ? 'SUCCESS' : 'FAILED') . ' for ' . $shareholder_data['email']);
        }
        
        return $result;
    }

    /**
     * Get company statistics
     */
    private function get_company_statistics($company) {
        return array(
            'total_shares' => $this->database->get_total_shares($company),
            'total_participation' => $this->database->get_total_participation($company),
            'shareholders_count' => $this->database->get_shareholders_count($company)
        );
    }

    /**
     * Get success message based on company
     */
    private function get_success_message($company) {
        $messages = array(
            'atos' => __('Vos données ont été comptabilisées avec succès! <br> Veuillez rafraichir la page pour voir le nouveau total d\'actions cumulées', 'upra-class-action'),
            'urpea' => __('Your URPEA data has been successfully recorded! <br> Please refresh the page to see the updated totals.', 'upra-class-action')
        );

        return $messages[$company] ?? $messages['atos'];
    }
}