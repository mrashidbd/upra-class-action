<?php
/**
 * Admin Handler for UPRA Class Action Plugin
 * 
 * Handles WordPress admin interface, settings, and administrative functions
 */

if (!defined('ABSPATH')) {
    exit;
}

class UPRA_Class_Action_Admin {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Database instance
     */
    private $database;

    /**
     * Plugin options
     */
    private $options;

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
        $this->options = get_option('upra_class_action_options', array());
        
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin menu and pages
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Admin styles and scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Admin AJAX handlers
        add_action('wp_ajax_upra_export_data', array($this, 'handle_data_export'));
        add_action('wp_ajax_upra_send_bulk_email', array($this, 'handle_bulk_email'));
        add_action('wp_ajax_upra_delete_shareholder', array($this, 'handle_delete_shareholder'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'show_admin_notices'));
        
        // Plugin action links
        add_filter('plugin_action_links_' . plugin_basename(UPRA_CLASS_ACTION_PLUGIN_FILE), array($this, 'add_plugin_action_links'));
        
        // Dashboard widgets
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
        
        // Bulk actions for list table
        add_filter('bulk_actions-upra_shareholders', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-upra_shareholders', array($this, 'handle_bulk_actions'), 10, 3);
    }

    /**
     * Add plugin admin menu
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('UPRA Class Action', 'upra-class-action'),
            __('UPRA Class Action', 'upra-class-action'),
            'manage_options',
            'upra-class-action',
            array($this, 'render_main_page'),
            'dashicons-groups',
            30
        );

        // Settings submenu
        add_submenu_page(
            'upra-class-action',
            __('Settings', 'upra-class-action'),
            __('Settings', 'upra-class-action'),
            'manage_options',
            'upra-class-action-settings',
            array($this, 'render_settings_page')
        );

        // Tools submenu
        add_submenu_page(
            'upra-class-action',
            __('Tools', 'upra-class-action'),
            __('Tools', 'upra-class-action'),
            'manage_options',
            'upra-class-action-tools',
            array($this, 'render_tools_page')
        );

        // Reports submenu
        add_submenu_page(
            'upra-class-action',
            __('Reports', 'upra-class-action'),
            __('Reports', 'upra-class-action'),
            'manage_options',
            'upra-class-action-reports',
            array($this, 'render_reports_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            'upra_class_action_settings',
            'upra_class_action_options',
            array($this, 'sanitize_settings')
        );

        // General settings section
        add_settings_section(
            'upra_general_settings',
            __('General Settings', 'upra-class-action'),
            array($this, 'render_general_settings_section'),
            'upra_class_action_settings'
        );

        // Email settings section
        add_settings_section(
            'upra_email_settings',
            __('Email Settings', 'upra-class-action'),
            array($this, 'render_email_settings_section'),
            'upra_class_action_settings'
        );

        // Company settings section
        add_settings_section(
            'upra_company_settings',
            __('Company Settings', 'upra-class-action'),
            array($this, 'render_company_settings_section'),
            'upra_class_action_settings'
        );

        // Add individual settings fields
        $this->add_settings_fields();
    }

    /**
     * Add settings fields
     */
    private function add_settings_fields() {
        // General settings
        add_settings_field(
            'duplicate_check',
            __('Duplicate Check', 'upra-class-action'),
            array($this, 'render_checkbox_field'),
            'upra_class_action_settings',
            'upra_general_settings',
            array(
                'field' => 'duplicate_check',
                'label' => __('Prevent duplicate registrations based on email and phone', 'upra-class-action')
            )
        );

        add_settings_field(
            'data_retention_days',
            __('Data Retention (Days)', 'upra-class-action'),
            array($this, 'render_number_field'),
            'upra_class_action_settings',
            'upra_general_settings',
            array(
                'field' => 'data_retention_days',
                'label' => __('Number of days to retain shareholder data (0 = unlimited)', 'upra-class-action'),
                'min' => 0
            )
        );

        // Email settings
        add_settings_field(
            'email_notifications',
            __('Email Notifications', 'upra-class-action'),
            array($this, 'render_checkbox_field'),
            'upra_class_action_settings',
            'upra_email_settings',
            array(
                'field' => 'email_notifications',
                'label' => __('Send confirmation emails to shareholders', 'upra-class-action')
            )
        );

        add_settings_field(
            'admin_notifications',
            __('Admin Notifications', 'upra-class-action'),
            array($this, 'render_checkbox_field'),
            'upra_class_action_settings',
            'upra_email_settings',
            array(
                'field' => 'admin_notifications',
                'label' => __('Send notifications to admin when new shareholders register', 'upra-class-action')
            )
        );

        add_settings_field(
            'email_from_name',
            __('From Name', 'upra-class-action'),
            array($this, 'render_text_field'),
            'upra_class_action_settings',
            'upra_email_settings',
            array(
                'field' => 'email_from_name',
                'placeholder' => 'UPRA'
            )
        );

        add_settings_field(
            'email_from_address',
            __('From Email', 'upra-class-action'),
            array($this, 'render_email_field'),
            'upra_class_action_settings',
            'upra_email_settings',
            array(
                'field' => 'email_from_address',
                'placeholder' => 'no-reply@upra.fr'
            )
        );

        // Company settings
        add_settings_field(
            'supported_companies',
            __('Supported Companies', 'upra-class-action'),
            array($this, 'render_companies_field'),
            'upra_class_action_settings',
            'upra_company_settings',
            array(
                'field' => 'supported_companies'
            )
        );
    }

    /**
     * Render main admin page
     */
    public function render_main_page() {
        $companies = $this->get_supported_companies();
        $total_shareholders = 0;
        $company_stats = array();

        foreach ($companies as $company) {
            $stats = array(
                'shares' => $this->database->get_total_shares($company),
                'shareholders' => $this->database->get_shareholders_count($company),
                'participation' => $this->database->get_total_participation($company)
            );
            $company_stats[$company] = $stats;
            $total_shareholders += $stats['shareholders'];
        }

        ?>
        <div class="wrap">
            <h1><?php _e('UPRA Class Action Dashboard', 'upra-class-action'); ?></h1>

            <div class="upra-admin-dashboard">
                
                <!-- Summary Cards -->
                <div class="upra-summary-cards">
                    <div class="upra-card">
                        <h3><?php _e('Total Shareholders', 'upra-class-action'); ?></h3>
                        <div class="upra-card-value"><?php echo number_format($total_shareholders); ?></div>
                    </div>

                    <div class="upra-card">
                        <h3><?php _e('Active Companies', 'upra-class-action'); ?></h3>
                        <div class="upra-card-value"><?php echo count($companies); ?></div>
                    </div>

                    <div class="upra-card">
                        <h3><?php _e('Database Version', 'upra-class-action'); ?></h3>
                        <div class="upra-card-value"><?php echo get_option('upra_class_action_db_version', '1.0.0'); ?></div>
                    </div>
                </div>

                <!-- Company Statistics -->
                <div class="upra-company-stats">
                    <h2><?php _e('Company Statistics', 'upra-class-action'); ?></h2>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Company', 'upra-class-action'); ?></th>
                                <th><?php _e('Total Shares', 'upra-class-action'); ?></th>
                                <th><?php _e('Shareholders', 'upra-class-action'); ?></th>
                                <th><?php _e('Total Participation', 'upra-class-action'); ?></th>
                                <th><?php _e('Actions', 'upra-class-action'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($company_stats as $company => $stats): ?>
                            <tr>
                                <td><strong><?php echo strtoupper(esc_html($company)); ?></strong></td>
                                <td><?php echo number_format($stats['shares']); ?></td>
                                <td><?php echo number_format($stats['shareholders']); ?></td>
                                <td><?php echo number_format($stats['participation'], 2); ?></td>
                                <td>
                                    <a href="<?php echo admin_url("admin.php?page=upra_shareholders&company={$company}"); ?>" class="button">
                                        <?php _e('View Details', 'upra-class-action'); ?>
                                    </a>
                                    <button type="button" class="button upra-export-btn" data-company="<?php echo esc_attr($company); ?>">
                                        <?php _e('Export', 'upra-class-action'); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Quick Actions -->
                <div class="upra-quick-actions">
                    <h2><?php _e('Quick Actions', 'upra-class-action'); ?></h2>
                    
                    <div class="upra-action-buttons">
                        <a href="<?php echo admin_url('admin.php?page=upra-class-action-settings'); ?>" class="button button-primary">
                            <?php _e('Settings', 'upra-class-action'); ?>
                        </a>
                        
                        <a href="<?php echo admin_url('admin.php?page=upra-class-action-tools'); ?>" class="button">
                            <?php _e('Import/Export Tools', 'upra-class-action'); ?>
                        </a>
                        
                        <a href="<?php echo admin_url('admin.php?page=upra-class-action-reports'); ?>" class="button">
                            <?php _e('Generate Reports', 'upra-class-action'); ?>
                        </a>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('UPRA Class Action Settings', 'upra-class-action'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('upra_class_action_settings');
                do_settings_sections('upra_class_action_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render tools page
     */
    public function render_tools_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('UPRA Class Action Tools', 'upra-class-action'); ?></h1>

            <div class="upra-tools-container">
                
                <!-- Data Export -->
                <div class="upra-tool-section">
                    <h2><?php _e('Data Export', 'upra-class-action'); ?></h2>
                    <p><?php _e('Export shareholder data for analysis or backup purposes.', 'upra-class-action'); ?></p>
                    
                    <form id="upra-export-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Company', 'upra-class-action'); ?></th>
                                <td>
                                    <select name="company" required>
                                        <option value=""><?php _e('Select Company', 'upra-class-action'); ?></option>
                                        <?php foreach ($this->get_supported_companies() as $company): ?>
                                            <option value="<?php echo esc_attr($company); ?>"><?php echo strtoupper(esc_html($company)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Format', 'upra-class-action'); ?></th>
                                <td>
                                    <select name="format">
                                        <option value="csv"><?php _e('CSV', 'upra-class-action'); ?></option>
                                        <option value="json"><?php _e('JSON', 'upra-class-action'); ?></option>
                                        <option value="excel"><?php _e('Excel', 'upra-class-action'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        
                        <?php wp_nonce_field('upra_export_nonce', 'export_nonce'); ?>
                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php _e('Export Data', 'upra-class-action'); ?></button>
                        </p>
                    </form>
                </div>

                <!-- Bulk Email -->
                <div class="upra-tool-section">
                    <h2><?php _e('Bulk Email', 'upra-class-action'); ?></h2>
                    <p><?php _e('Send emails to all shareholders of a specific company.', 'upra-class-action'); ?></p>
                    
                    <form id="upra-bulk-email-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Company', 'upra-class-action'); ?></th>
                                <td>
                                    <select name="company" required>
                                        <option value=""><?php _e('Select Company', 'upra-class-action'); ?></option>
                                        <?php foreach ($this->get_supported_companies() as $company): ?>
                                            <option value="<?php echo esc_attr($company); ?>"><?php echo strtoupper(esc_html($company)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Subject', 'upra-class-action'); ?></th>
                                <td>
                                    <input type="text" name="subject" class="regular-text" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Message', 'upra-class-action'); ?></th>
                                <td>
                                    <textarea name="message" rows="10" class="large-text" required></textarea>
                                    <p class="description"><?php _e('HTML tags are allowed.', 'upra-class-action'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <?php wp_nonce_field('upra_bulk_email_nonce', 'bulk_email_nonce'); ?>
                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php _e('Send Bulk Email', 'upra-class-action'); ?></button>
                        </p>
                    </form>
                </div>

                <!-- Database Maintenance -->
                <div class="upra-tool-section">
                    <h2><?php _e('Database Maintenance', 'upra-class-action'); ?></h2>
                    <p><?php _e('Database maintenance and cleanup tools.', 'upra-class-action'); ?></p>
                    
                    <p class="submit">
                        <button type="button" class="button" id="upra-cleanup-btn">
                            <?php _e('Clean Up Old Data', 'upra-class-action'); ?>
                        </button>
                        <button type="button" class="button" id="upra-optimize-btn">
                            <?php _e('Optimize Database', 'upra-class-action'); ?>
                        </button>
                    </p>
                </div>

            </div>
        </div>
        <?php
    }

    /**
     * Render reports page
     */
    public function render_reports_page() {
        $companies = $this->get_supported_companies();
        
        ?>
        <div class="wrap">
            <h1><?php _e('UPRA Class Action Reports', 'upra-class-action'); ?></h1>

            <div class="upra-reports-container">
                
                <?php foreach ($companies as $company): ?>
                    <?php 
                    $stats = array(
                        'shares' => $this->database->get_total_shares($company),
                        'shareholders' => $this->database->get_shareholders_count($company),
                        'participation' => $this->database->get_total_participation($company)
                    );
                    ?>
                    
                    <div class="upra-report-section">
                        <h2><?php printf(__('%s Report', 'upra-class-action'), strtoupper($company)); ?></h2>
                        
                        <div class="upra-report-stats">
                            <div class="upra-stat-box">
                                <h3><?php _e('Total Shares', 'upra-class-action'); ?></h3>
                                <div class="upra-stat-number"><?php echo number_format($stats['shares']); ?></div>
                            </div>
                            
                            <div class="upra-stat-box">
                                <h3><?php _e('Total Shareholders', 'upra-class-action'); ?></h3>
                                <div class="upra-stat-number"><?php echo number_format($stats['shareholders']); ?></div>
                            </div>
                            
                            <div class="upra-stat-box">
                                <h3><?php _e('Total Participation', 'upra-class-action'); ?></h3>
                                <div class="upra-stat-number"><?php echo number_format($stats['participation'], 2); ?></div>
                            </div>
                        </div>
                        
                        <div class="upra-report-actions">
                            <a href="<?php echo admin_url("admin.php?page=upra_shareholders&company={$company}"); ?>" class="button">
                                <?php _e('View Detailed List', 'upra-class-action'); ?>
                            </a>
                            <button class="button upra-generate-report" data-company="<?php echo esc_attr($company); ?>">
                                <?php _e('Generate PDF Report', 'upra-class-action'); ?>
                            </button>
                        </div>
                    </div>
                
                <?php endforeach; ?>
                
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'upra') === false) {
            return;
        }

        wp_enqueue_style(
            'upra-admin-style',
            UPRA_CLASS_ACTION_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            UPRA_CLASS_ACTION_VERSION
        );

        wp_enqueue_script(
            'upra-admin-script',
            UPRA_CLASS_ACTION_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            UPRA_CLASS_ACTION_VERSION,
            true
        );

        wp_localize_script('upra-admin-script', 'upra_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('upra_admin_nonce'),
            'messages' => array(
                'confirm_delete' => __('Are you sure you want to delete this shareholder?', 'upra-class-action'),
                'confirm_bulk_email' => __('Are you sure you want to send this email to all shareholders?', 'upra-class-action'),
                'export_success' => __('Export completed successfully!', 'upra-class-action'),
                'email_sent' => __('Bulk email sent successfully!', 'upra-class-action')
            )
        ));
    }

    /**
     * Handle data export AJAX
     */
    public function handle_data_export() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'upra-class-action'));
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'upra_admin_nonce')) {
            wp_send_json_error(__('Security check failed', 'upra-class-action'));
        }

        $company = sanitize_text_field($_POST['company'] ?? '');
        $format = sanitize_text_field($_POST['format'] ?? 'csv');

        if (empty($company)) {
            wp_send_json_error(__('Company is required', 'upra-class-action'));
        }

        // Get company-specific handler
        $company_class = 'UPRA_Class_Action_' . strtoupper($company);
        if (class_exists($company_class)) {
            $company_handler = $company_class::get_instance();
            $result = $company_handler->export_data($format);
            
            if ($result) {
                wp_send_json_success(__('Export completed successfully', 'upra-class-action'));
            }
        }

        wp_send_json_error(__('Export failed', 'upra-class-action'));
    }

    /**
     * Handle bulk email AJAX
     */
    public function handle_bulk_email() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'upra-class-action'));
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'upra_admin_nonce')) {
            wp_send_json_error(__('Security check failed', 'upra-class-action'));
        }

        $company = sanitize_text_field($_POST['company'] ?? '');
        $subject = sanitize_text_field($_POST['subject'] ?? '');
        $message = wp_kses_post($_POST['message'] ?? '');

        if (empty($company) || empty($subject) || empty($message)) {
            wp_send_json_error(__('All fields are required', 'upra-class-action'));
        }

        $email_handler = UPRA_Class_Action_Email_Handler::get_instance();
        $sent_count = $email_handler->send_bulk_email($company, $subject, $message);

        if ($sent_count > 0) {
            wp_send_json_success(sprintf(__('Email sent to %d shareholders', 'upra-class-action'), $sent_count));
        } else {
            wp_send_json_error(__('No emails were sent', 'upra-class-action'));
        }
    }

    /**
     * Handle delete shareholder AJAX
     */
    public function handle_delete_shareholder() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'upra-class-action'));
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'upra_admin_nonce')) {
            wp_send_json_error(__('Security check failed', 'upra-class-action'));
        }

        $id = absint($_POST['id'] ?? 0);
        $company = sanitize_text_field($_POST['company'] ?? '');

        if ($id && $company) {
            $result = $this->database->delete_shareholder($id, $company);
            if ($result) {
                wp_send_json_success(__('Shareholder deleted successfully', 'upra-class-action'));
            }
        }

        wp_send_json_error(__('Failed to delete shareholder', 'upra-class-action'));
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        $sanitized['duplicate_check'] = isset($input['duplicate_check']) ? 1 : 0;
        $sanitized['email_notifications'] = isset($input['email_notifications']) ? 1 : 0;
        $sanitized['admin_notifications'] = isset($input['admin_notifications']) ? 1 : 0;
        $sanitized['data_retention_days'] = absint($input['data_retention_days'] ?? 0);
        $sanitized['email_from_name'] = sanitize_text_field($input['email_from_name'] ?? 'UPRA');
        $sanitized['email_from_address'] = sanitize_email($input['email_from_address'] ?? 'no-reply@upra.fr');
        $sanitized['supported_companies'] = isset($input['supported_companies']) ? array_map('sanitize_text_field', $input['supported_companies']) : array('atos');

        return $sanitized;
    }

    /**
     * Get supported companies
     */
    private function get_supported_companies() {
        return $this->options['supported_companies'] ?? array('atos');
    }

    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        // Show notices for updates, errors, etc.
        if (isset($_GET['upra_message'])) {
            $message = sanitize_text_field($_GET['upra_message']);
            $type = sanitize_text_field($_GET['upra_type'] ?? 'success');
            
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible">';
            echo '<p>' . esc_html($message) . '</p>';
            echo '</div>';
        }
    }

    /**
     * Add plugin action links
     */
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=upra-class-action-settings') . '">' . __('Settings', 'upra-class-action') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Add dashboard widgets
     */
    public function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'upra_dashboard_widget',
            __('UPRA Class Action Summary', 'upra-class-action'),
            array($this, 'render_dashboard_widget')
        );
    }

    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        $companies = $this->get_supported_companies();
        $total_shareholders = 0;

        foreach ($companies as $company) {
            $total_shareholders += $this->database->get_shareholders_count($company);
        }

        ?>
        <div class="upra-dashboard-widget">
            <p><strong><?php _e('Total Shareholders:', 'upra-class-action'); ?></strong> <?php echo number_format($total_shareholders); ?></p>
            <p><strong><?php _e('Active Companies:', 'upra-class-action'); ?></strong> <?php echo count($companies); ?></p>
            <p>
                <a href="<?php echo admin_url('admin.php?page=upra-class-action'); ?>" class="button button-primary">
                    <?php _e('View Dashboard', 'upra-class-action'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Render settings field functions
     */
    public function render_checkbox_field($args) {
        $value = isset($this->options[$args['field']]) ? $this->options[$args['field']] : 0;
        ?>
        <label>
            <input type="checkbox" name="upra_class_action_options[<?php echo esc_attr($args['field']); ?>]" value="1" <?php checked(1, $value); ?>>
            <?php echo esc_html($args['label']); ?>
        </label>
        <?php
    }

    public function render_text_field($args) {
        $value = isset($this->options[$args['field']]) ? $this->options[$args['field']] : '';
        ?>
        <input type="text" 
               name="upra_class_action_options[<?php echo esc_attr($args['field']); ?>]" 
               value="<?php echo esc_attr($value); ?>" 
               placeholder="<?php echo esc_attr($args['placeholder'] ?? ''); ?>"
               class="regular-text">
        <?php
    }

    public function render_email_field($args) {
        $value = isset($this->options[$args['field']]) ? $this->options[$args['field']] : '';
        ?>
        <input type="email" 
               name="upra_class_action_options[<?php echo esc_attr($args['field']); ?>]" 
               value="<?php echo esc_attr($value); ?>" 
               placeholder="<?php echo esc_attr($args['placeholder'] ?? ''); ?>"
               class="regular-text">
        <?php
    }

    public function render_number_field($args) {
        $value = isset($this->options[$args['field']]) ? $this->options[$args['field']] : 0;
        ?>
        <input type="number" 
               name="upra_class_action_options[<?php echo esc_attr($args['field']); ?>]" 
               value="<?php echo esc_attr($value); ?>" 
               min="<?php echo esc_attr($args['min'] ?? 0); ?>"
               class="small-text">
        <p class="description"><?php echo esc_html($args['label'] ?? ''); ?></p>
        <?php
    }

    public function render_companies_field($args) {
        $selected_companies = isset($this->options[$args['field']]) ? $this->options[$args['field']] : array('atos');
        $available_companies = array(
            'atos' => 'ATOS',
            'urpea' => 'URPEA',
            'other' => __('Other', 'upra-class-action')
        );
        ?>
        <fieldset>
            <?php foreach ($available_companies as $company_id => $company_name): ?>
                <label>
                    <input type="checkbox" 
                           name="upra_class_action_options[<?php echo esc_attr($args['field']); ?>][]" 
                           value="<?php echo esc_attr($company_id); ?>"
                           <?php checked(in_array($company_id, $selected_companies)); ?>>
                    <?php echo esc_html($company_name); ?>
                </label><br>
            <?php endforeach; ?>
        </fieldset>
        <p class="description"><?php _e('Select which companies this plugin should support.', 'upra-class-action'); ?></p>
        <?php
    }

    /**
     * Render settings sections
     */
    public function render_general_settings_section() {
        echo '<p>' . __('Configure general plugin settings.', 'upra-class-action') . '</p>';
    }

    public function render_email_settings_section() {
        echo '<p>' . __('Configure email notification settings.', 'upra-class-action') . '</p>';
    }

    public function render_company_settings_section() {
        echo '<p>' . __('Configure which companies are supported by this plugin.', 'upra-class-action') . '</p>';
    }

    /**
     * Add bulk actions to list table
     */
    public function add_bulk_actions($actions) {
        $actions['delete'] = __('Delete', 'upra-class-action');
        $actions['export'] = __('Export Selected', 'upra-class-action');
        $actions['send_email'] = __('Send Email', 'upra-class-action');
        return $actions;
    }

    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_to, $doaction, $post_ids) {
        if (!current_user_can('manage_options')) {
            return $redirect_to;
        }

        switch ($doaction) {
            case 'delete':
                $deleted = 0;
                foreach ($post_ids as $id) {
                    if ($this->database->delete_shareholder($id)) {
                        $deleted++;
                    }
                }
                $redirect_to = add_query_arg(array(
                    'upra_message' => sprintf(__('%d shareholders deleted.', 'upra-class-action'), $deleted),
                    'upra_type' => 'success'
                ), $redirect_to);
                break;

            case 'export':
                // Handle bulk export
                $redirect_to = add_query_arg(array(
                    'upra_message' => __('Selected shareholders exported.', 'upra-class-action'),
                    'upra_type' => 'success'
                ), $redirect_to);
                break;

            case 'send_email':
                // Redirect to bulk email form with selected IDs
                $redirect_to = add_query_arg(array(
                    'page' => 'upra-class-action-tools',
                    'action' => 'bulk_email',
                    'selected_ids' => implode(',', $post_ids)
                ), admin_url('admin.php'));
                break;
        }

        return $redirect_to;
    }

    /**
     * Get plugin statistics for reporting
     */
    public function get_plugin_statistics() {
        $companies = $this->get_supported_companies();
        $stats = array(
            'total_shareholders' => 0,
            'total_shares' => 0,
            'total_participation' => 0,
            'companies' => array()
        );

        foreach ($companies as $company) {
            $company_stats = array(
                'shareholders' => $this->database->get_shareholders_count($company),
                'shares' => $this->database->get_total_shares($company),
                'participation' => $this->database->get_total_participation($company)
            );

            $stats['companies'][$company] = $company_stats;
            $stats['total_shareholders'] += $company_stats['shareholders'];
            $stats['total_shares'] += $company_stats['shares'];
            $stats['total_participation'] += $company_stats['participation'];
        }

        return $stats;
    }

    /**
     * Check if plugin needs updates
     */
    public function check_plugin_updates() {
        $current_version = get_option('upra_class_action_version', '0.0.0');
        
        if (version_compare($current_version, UPRA_CLASS_ACTION_VERSION, '<')) {
            $this->run_plugin_update($current_version);
            update_option('upra_class_action_version', UPRA_CLASS_ACTION_VERSION);
        }
    }

    /**
     * Run plugin update procedures
     */
    private function run_plugin_update($from_version) {
        // Update database if needed
        $this->database->check_database_version();
        
        // Update options if needed
        $options = get_option('upra_class_action_options', array());
        
        // Add any new default options
        $default_options = array(
            'version' => UPRA_CLASS_ACTION_VERSION,
            'companies' => array('atos'),
            'email_notifications' => true,
            'duplicate_check' => true,
            'admin_notifications' => false,
            'data_retention_days' => 0,
            'email_from_name' => 'UPRA',
            'email_from_address' => 'no-reply@upra.fr'
        );
        
        $updated_options = wp_parse_args($options, $default_options);
        update_option('upra_class_action_options', $updated_options);
        
        // Log the update
        error_log("UPRA Class Action Plugin updated from {$from_version} to " . UPRA_CLASS_ACTION_VERSION);
    }

    /**
     * Clean up old data based on retention settings
     */
    public function cleanup_old_data() {
        $retention_days = $this->options['data_retention_days'] ?? 0;
        
        if ($retention_days > 0) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'upra_shareholders_data';
            $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
            
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table_name} WHERE created_at < %s",
                $cutoff_date
            ));
            
            if ($deleted > 0) {
                error_log("UPRA Class Action: Cleaned up {$deleted} old records older than {$retention_days} days");
            }
            
            return $deleted;
        }
        
        return 0;
    }

    /**
     * Schedule cleanup cron job
     */
    public function schedule_cleanup() {
        if (!wp_next_scheduled('upra_cleanup_old_data')) {
            wp_schedule_event(time(), 'daily', 'upra_cleanup_old_data');
        }
    }

    /**
     * Unschedule cleanup cron job
     */
    public function unschedule_cleanup() {
        wp_clear_scheduled_hook('upra_cleanup_old_data');
    }
}