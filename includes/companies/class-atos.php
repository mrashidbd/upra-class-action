<?php
/**
 * ATOS Company Handler for UPRA Class Action Plugin
 * 
 * Handles ATOS-specific functionality and maintains backward compatibility
 * with the original theme-based implementation
 */

if (!defined('ABSPATH')) {
    exit;
}

class UPRA_Class_Action_ATOS {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Company identifier
     */
    const COMPANY_ID = 'atos';

    /**
     * Database instance
     */
    private $database;

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
        // AJAX hooks for backward compatibility
        add_action('wp_ajax_kdb_add_member', array($this, 'handle_legacy_ajax'));
        add_action('wp_ajax_nopriv_kdb_add_member', array($this, 'handle_legacy_ajax'));
        
        // Enqueue ATOS-specific scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_atos_scripts'));
        
        // Add backward compatibility functions
        add_action('init', array($this, 'register_legacy_functions'));
        
        // Custom page template detection
        add_filter('template_include', array($this, 'load_atos_templates'));
    }

    /**
     * Handle legacy AJAX requests for backward compatibility
     */
    public function handle_legacy_ajax() {
        // Check if the new AJAX handler exists and redirect
        if (class_exists('UPRA_Class_Action_Ajax_Handler')) {
            // Transform legacy request to new format
            if (isset($_POST['add_member'])) {
                $_POST['shareholder_data'] = $_POST['add_member'];
                $_POST['nonce'] = wp_create_nonce('upra_shareholder_nonce');
                
                // Call new handler
                $ajax_handler = UPRA_Class_Action_Ajax_Handler::get_instance();
                $ajax_handler->handle_add_shareholder();
                return;
            }
        }
        
        // Fallback to legacy handling if needed
        $this->legacy_add_member();
    }

    /**
     * Legacy member addition function (backup compatibility)
     */
    private function legacy_add_member() {
        if (isset($_POST['add_member'])) {
            $form = array();
            wp_parse_str($_POST['add_member'], $form);
            
            $errors = $this->legacy_form_validation($form);
            if ($errors) {
                $error = array();
                foreach ($errors as $key => $val) {
                    $error[] = $val;
                }
                wp_send_json_error($error);
            } else {
                $duplicate = $this->legacy_member_exists($form);
                if ($duplicate) {
                    wp_send_json_error($duplicate);
                } else {
                    $this->legacy_insert_data($form);
                    $this->legacy_send_success_mail($form['email']);
                    wp_send_json_success('Vos données ont été comptabilisées avec succès! <br> Veuillez rafraichir la page pour voir le nouveau total d\'actions cumulées');
                }
            }
        } else {
            wp_send_json_error("Sorry, something went wrong, please reload the page and try again.");
        }
    }

    /**
     * Enqueue ATOS-specific scripts
     */
    public function enqueue_atos_scripts() {
        // Load scripts for ATOS data collection template
        if (is_page_template('atos-data-collect-template.php')) {
            wp_enqueue_script(
                'upra-atos-legacy',
                UPRA_CLASS_ACTION_PLUGIN_URL . 'assets/js/atos-legacy.js',
                array('jquery'),
                UPRA_CLASS_ACTION_VERSION,
                true
            );
            
            wp_localize_script('upra-atos-legacy', 'ajax', array(
                'url' => admin_url('admin-ajax.php'),
                'total_share' => $this->get_total_shares()
            ));
        }

        // Load scripts for homepage statistics
        if (is_front_page() || is_page_template('template-custom-home.php') || is_page_template('template-home-v2.php')) {
            wp_enqueue_script(
                'upra-atos-front',
                UPRA_CLASS_ACTION_PLUGIN_URL . 'assets/js/atos-front.js',
                array('jquery'),
                UPRA_CLASS_ACTION_VERSION,
                true
            );
            
            wp_localize_script('upra-atos-front', 'ajax_front', array(
                'total_share' => $this->get_total_shares(),
                'total_people' => $this->get_total_people()
            ));
        }
    }

    /**
     * Register legacy functions for backward compatibility
     */
    public function register_legacy_functions() {
        // Register global functions that may be called from theme templates
        if (!function_exists('getAtosData')) {
            function getAtosData() {
                $atos = UPRA_Class_Action_ATOS::get_instance();
                return $atos->get_total_shares();
            }
        }

        if (!function_exists('getAtosRows')) {
            function getAtosRows() {
                $atos = UPRA_Class_Action_ATOS::get_instance();
                return $atos->get_total_people();
            }
        }

        if (!function_exists('getAtosParticipation')) {
            function getAtosParticipation() {
                $atos = UPRA_Class_Action_ATOS::get_instance();
                return $atos->get_total_participation();
            }
        }
    }

    /**
     * Load ATOS-specific templates
     */
    public function load_atos_templates($template) {
        if (is_page_template('atos-data-collect-template.php')) {
            $custom_template = UPRA_CLASS_ACTION_PLUGIN_DIR . 'templates/atos-data-collection.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        
        return $template;
    }

    /**
     * Get total ATOS shares
     */
    public function get_total_shares() {
        return $this->database->get_total_shares(self::COMPANY_ID);
    }

    /**
     * Get total ATOS shareholders count
     */
    public function get_total_people() {
        return $this->database->get_shareholders_count(self::COMPANY_ID);
    }

    /**
     * Get total ATOS participation value
     */
    public function get_total_participation() {
        return $this->database->get_total_participation(self::COMPANY_ID);
    }

    /**
     * Get ATOS-specific form configuration
     */
    public function get_form_config() {
        return array(
            'company' => self::COMPANY_ID,
            'title' => __('ATOS Shareholder Registration', 'upra-class-action'),
            'description' => __('Please provide your ATOS shareholding information for the class action lawsuit.', 'upra-class-action'),
            'fields' => array(
                'name' => array(
                    'label' => __('Your Name', 'upra-class-action'),
                    'placeholder' => __('Your Name (Required)', 'upra-class-action'),
                    'required' => true,
                    'type' => 'text'
                ),
                'email' => array(
                    'label' => __('Email address', 'upra-class-action'),
                    'placeholder' => __('Your Email Address (Required)', 'upra-class-action'),
                    'required' => true,
                    'type' => 'email'
                ),
                'phone' => array(
                    'label' => __('Mobile Phone', 'upra-class-action'),
                    'placeholder' => __('Mobile Phone (Required)', 'upra-class-action'),
                    'required' => true,
                    'type' => 'tel'
                ),
                'stock' => array(
                    'label' => __('Nombre d\'actions détenues', 'upra-class-action'),
                    'placeholder' => __('Nombre d\'actions détenues', 'upra-class-action'),
                    'required' => false,
                    'type' => 'number'
                ),
                'purchase' => array(
                    'label' => __('Buy Price', 'upra-class-action'),
                    'placeholder' => __('Buy Price', 'upra-class-action'),
                    'required' => false,
                    'type' => 'number'
                ),
                'sell' => array(
                    'label' => __('Sell Price', 'upra-class-action'),
                    'placeholder' => __('Sell Price', 'upra-class-action'),
                    'required' => false,
                    'type' => 'number'
                ),
                'loss' => array(
                    'label' => __('Perte Totale', 'upra-class-action'),
                    'placeholder' => __('Perte Totale', 'upra-class-action'),
                    'required' => false,
                    'type' => 'number'
                ),
                'remarks' => array(
                    'label' => __('Remarques', 'upra-class-action'),
                    'placeholder' => __('Remarques', 'upra-class-action'),
                    'required' => false,
                    'type' => 'textarea'
                )
            ),
            'submit_text' => __('Submit', 'upra-class-action'),
            'success_message' => __('Vos données ont été comptabilisées avec succès! <br> Veuillez rafraichir la page pour voir le nouveau total d\'actions cumulées', 'upra-class-action')
        );
    }

    /**
     * Get ATOS statistics for display
     */
    public function get_statistics() {
        return array(
            'total_shares' => $this->get_total_shares(),
            'total_people' => $this->get_total_people(),
            'total_participation' => $this->get_total_participation(),
            'company' => self::COMPANY_ID,
            'company_name' => 'ATOS'
        );
    }

    /**
     * Legacy form validation (for backward compatibility)
     */
    private function legacy_form_validation($form) {
        $errors = array();
        
        if (empty($form['name'])) {
            $errors['name'] = 'Please enter your name';
        }
        if (empty($form['email'])) {
            $errors['email'] = 'Please enter email address';
        }
        if (empty($form['phone'])) {
            $errors['phone'] = 'Please enter valid phone number';
        }

        // Set defaults for optional fields
        if (empty($form['stock'])) {
            $form['stock'] = '0';
        }
        if (empty($form['purchase'])) {
            $form['purchase'] = '0';
        }
        if (empty($form['sell'])) {
            $form['sell'] = '0';
        }
        if (empty($form['loss'])) {
            $form['loss'] = '0';
        }
        if (empty($form['remarks'])) {
            $form['remarks'] = '-';
        }
        
        return $errors;
    }

    /**
     * Legacy member exists check (for backward compatibility)
     */
    private function legacy_member_exists($form) {
        $error = array();
        $ip = $form['ip'] ?? '';
        $email = $form['email'];
        $phone = $form['phone'];
        
        $existing_id = $this->database->check_duplicate_shareholder($email, $phone, self::COMPANY_ID);
        
        if ($existing_id) {
            $error['message'] = 'Another entry matched with the phone number or email address you entered. Please contact admin@upra.fr, if you mistakenly submitted wrong information.';
        }
        
        return $error;
    }

    /**
     * Legacy data insertion (for backward compatibility)
     */
    private function legacy_insert_data($form) {
        $data = array(
            'company' => self::COMPANY_ID,
            'stockholder_name' => sanitize_text_field($form['name']),
            'email' => sanitize_email($form['email']),
            'phone' => sanitize_text_field($form['phone']),
            'stock' => absint($form['stock'] ?? 0),
            'purchase_price' => floatval($form['purchase'] ?? 0),
            'sell_price' => floatval($form['sell'] ?? 0),
            'loss' => floatval($form['loss'] ?? 0),
            'ip' => sanitize_text_field($form['ip'] ?? 'Unknown'),
            'country' => sanitize_text_field($form['country'] ?? 'Unknown'),
            'remarks' => sanitize_textarea_field($form['remarks'] ?? '')
        );
        
        return $this->database->insert_shareholder_data($data);
    }

    /**
     * Legacy success email sending (for backward compatibility)
     */
    private function legacy_send_success_mail($email) {
        $email_handler = UPRA_Class_Action_Email_Handler::get_instance();
        return $email_handler->send_confirmation_email($email, self::COMPANY_ID);
    }

    /**
     * Get ATOS-specific email templates
     */
    public function get_email_templates() {
        return array(
            'confirmation' => array(
                'subject' => __('UPRA Registration - ATOS', 'upra-class-action'),
                'template' => 'atos-confirmation'
            ),
            'reminder' => array(
                'subject' => __('ATOS Class Action - Important Update', 'upra-class-action'),
                'template' => 'atos-reminder'
            ),
            'notification' => array(
                'subject' => __('ATOS Class Action - Next Steps', 'upra-class-action'),
                'template' => 'atos-notification'
            )
        );
    }

    /**
     * Export ATOS data (admin function)
     */
    public function export_data($format = 'csv') {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $data = $this->database->get_shareholders_data(array(
            'company' => self::COMPANY_ID,
            'limit' => 999999 // Get all records
        ));

        switch ($format) {
            case 'csv':
                return $this->export_to_csv($data);
            case 'excel':
                return $this->export_to_excel($data);
            case 'json':
                return $this->export_to_json($data);
            default:
                return $data;
        }
    }

    /**
     * Export data to CSV format
     */
    private function export_to_csv($data) {
        if (empty($data)) {
            return false;
        }

        $filename = 'atos-shareholders-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        $headers = array(
            'ID', 'Name', 'Email', 'Phone', 'Stock', 
            'Purchase Price', 'Sell Price', 'Loss', 
            'IP', 'Country', 'Remarks', 'Created Date'
        );
        fputcsv($output, $headers);
        
        // CSV data
        foreach ($data as $row) {
            fputcsv($output, array(
                $row->id,
                $row->stockholder_name,
                $row->email,
                $row->phone,
                $row->stock,
                $row->purchase_price,
                $row->sell_price,
                $row->loss,
                $row->ip,
                $row->country,
                $row->remarks,
                $row->created_at
            ));
        }
        
        fclose($output);
        exit;
    }

    /**
     * Export data to JSON format
     */
    private function export_to_json($data) {
        $filename = 'atos-shareholders-' . date('Y-m-d') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo json_encode(array(
            'export_date' => current_time('mysql'),
            'company' => self::COMPANY_ID,
            'total_records' => count($data),
            'data' => $data
        ), JSON_PRETTY_PRINT);
        
        exit;
    }

    /**
     * Get company-specific validation rules
     */
    public function get_validation_rules() {
        return array(
            'required_fields' => array('name', 'email', 'phone'),
            'optional_fields' => array('stock', 'purchase', 'sell', 'loss', 'remarks'),
            'email_validation' => true,
            'phone_validation' => true,
            'numeric_fields' => array('stock', 'purchase', 'sell', 'loss'),
            'duplicate_check' => array('email', 'phone')
        );
    }
}