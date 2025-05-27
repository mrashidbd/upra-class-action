<?php
/**
 * Admin Handler for UPRA Class Action Plugin
 * 
 * Handles WordPress admin interface, settings, and administrative functions
 * Merged with admin menu functionality for a unified approach
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
        // Admin menu creation
        add_action('admin_menu', array($this, 'create_admin_menus'));
        
        // Settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Admin styles and scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Admin AJAX handlers
        add_action('wp_ajax_upra_export_data', array($this, 'handle_data_export'));
        add_action('wp_ajax_upra_send_bulk_email', array($this, 'handle_bulk_email'));
        add_action('wp_ajax_upra_delete_shareholder', array($this, 'handle_delete_shareholder'));
        add_action('wp_ajax_upra_get_shareholder', array($this, 'handle_get_shareholder'));
        add_action('wp_ajax_upra_update_shareholder', array($this, 'handle_update_shareholder'));
        add_action('wp_ajax_upra_get_all_stats', array($this, 'handle_get_all_stats'));
        
        // Admin post handlers for exports
        add_action('admin_post_upra_export', array($this, 'handle_export_download'));
        add_action('admin_post_upra_bulk_email', array($this, 'handle_bulk_email_post'));
        
        // Screen options for list tables
        add_filter('set-screen-option', array($this, 'set_screen_options'), 10, 3);
        
        // Admin notices
        add_action('admin_notices', array($this, 'show_admin_notices'));
        
        // Plugin action links
        add_filter('plugin_action_links_' . plugin_basename(UPRA_CLASS_ACTION_PLUGIN_FILE), array($this, 'add_plugin_action_links'));
        
        // Dashboard widgets
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
    }

    /**
     * Create admin menus
     */
    public function create_admin_menus() {
        // Main menu page
        $main_page = add_menu_page(
            __('UPRA Class Action', 'upra-class-action'),
            __('UPRA Class Action', 'upra-class-action'),
            'manage_options',
            'upra-class-action',
            array($this, 'render_main_page'),
            'dashicons-groups',
            25
        );

        // Company-specific submenus
        $companies = $this->get_supported_companies();
        foreach ($companies as $company) {
            $company_name = strtoupper($company);
            
            $company_page = add_submenu_page(
                'upra-class-action',
                sprintf(__('%s Shareholders', 'upra-class-action'), $company_name),
                $company_name,
                'manage_options',
                "upra-class-action-{$company}",
                array($this, 'render_company_shareholders_page')
            );
            
            // Add screen options for company pages
            add_action("load-{$company_page}", array($this, 'add_screen_options'));
        }

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

        // Reports/Statistics submenu
        add_submenu_page(
            'upra-class-action',
            __('Reports & Statistics', 'upra-class-action'),
            __('Reports', 'upra-class-action'),
            'manage_options',
            'upra-class-action-reports',
            array($this, 'render_reports_page')
        );

        // Add screen options for the main page
        add_action("load-{$main_page}", array($this, 'add_screen_options'));
    }

    /**
     * Render main admin page (dashboard)
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
                                    <a href="<?php echo admin_url("admin.php?page=upra-class-action-{$company}"); ?>" class="button">
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
     * Render company-specific shareholders page
     */
    public function render_company_shareholders_page() {
        $page = $_GET['page'] ?? '';
        $company = str_replace('upra-class-action-', '', $page);
        
        if (empty($company) || !in_array($company, $this->get_supported_companies())) {
            wp_die(__('Invalid company specified.', 'upra-class-action'));
        }

        $this->render_shareholders_list($company);
    }

    /**
     * Render shareholders list for a specific company
     */
    private function render_shareholders_list($company) {
        // Create list table instance
        $list_table = new UPRA_Class_Action_List_Table($company);
        $list_table->prepare_items();

        // Get company statistics
        $stats = array(
            'total_shares' => $this->database->get_total_shares($company),
            'total_participation' => $this->database->get_total_participation($company),
            'shareholders_count' => $this->database->get_shareholders_count($company)
        );

        $company_name = strtoupper($company);
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php printf(__('%s Shareholders', 'upra-class-action'), $company_name); ?></h1>
            
            <a href="<?php echo admin_url('admin.php?page=upra-class-action-tools&company=' . $company); ?>" class="page-title-action">
                <?php _e('Export Data', 'upra-class-action'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=upra-class-action-tools&company=' . $company . '&action=bulk_email'); ?>" class="page-title-action">
                <?php _e('Send Bulk Email', 'upra-class-action'); ?>
            </a>

            <hr class="wp-header-end">

            <!-- Search Form -->
            <form method="post" name="search_shareholder" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>">
                <?php $list_table->search_box(__('Search Shareholders', 'upra-class-action'), 'search_shareholder'); ?>
            </form>

            <!-- List Table -->
            <form method="post">
                <?php $list_table->display(); ?>
            </form>

            <!-- Statistics Summary -->
            <div class="upra-statistics-summary">
                <div class="upra-stats-cards">
                    <div class="upra-stat-card">
                        <h3><?php _e('Total Shares', 'upra-class-action'); ?></h3>
                        <div class="upra-stat-value"><?php echo number_format($stats['total_shares']); ?></div>
                    </div>
                    <div class="upra-stat-card">
                        <h3><?php _e('Total Participation', 'upra-class-action'); ?></h3>
                        <div class="upra-stat-value"><?php echo number_format($stats['total_participation'], 2); ?></div>
                    </div>
                    <div class="upra-stat-card">
                        <h3><?php _e('Total Shareholders', 'upra-class-action'); ?></h3>
                        <div class="upra-stat-value"><?php echo number_format($stats['shareholders_count']); ?></div>
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
        $current_company = isset($_GET['company']) ? sanitize_text_field($_GET['company']) : '';
        $current_action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        
        ?>
        <div class="wrap">
            <h1><?php _e('UPRA Class Action Tools', 'upra-class-action'); ?></h1>

            <?php if ($current_action === 'bulk_email' && $current_company): ?>
                <?php $this->render_bulk_email_form($current_company); ?>
            <?php else: ?>
                <?php $this->render_tools_overview($current_company); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render tools overview
     */
    private function render_tools_overview($selected_company = '') {
        $companies = $this->get_supported_companies();
        ?>
        <div class="upra-tools-overview">
            
            <!-- Company Selection -->
            <div class="upra-tool-section">
                <h2><?php _e('Select Company', 'upra-class-action'); ?></h2>
                <form method="get" action="">
                    <input type="hidden" name="page" value="upra-class-action-tools">
                    <select name="company" onchange="this.form.submit()">
                        <option value=""><?php _e('Select Company', 'upra-class-action'); ?></option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?php echo esc_attr($company); ?>" <?php selected($selected_company, $company); ?>>
                                <?php echo strtoupper(esc_html($company)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($selected_company): ?>
                <!-- Export Tools -->
                <div class="upra-tool-section">
                    <h2><?php _e('Export Data', 'upra-class-action'); ?></h2>
                    <p><?php printf(__('Export %s shareholder data in various formats.', 'upra-class-action'), strtoupper($selected_company)); ?></p>
                    
                    <div class="upra-export-buttons">
                        <a href="<?php echo wp_nonce_url(admin_url("admin-post.php?action=upra_export&company={$selected_company}&format=csv"), 'upra_export_nonce'); ?>" class="button button-primary">
                            <?php _e('Export CSV', 'upra-class-action'); ?>
                        </a>
                        <a href="<?php echo wp_nonce_url(admin_url("admin-post.php?action=upra_export&company={$selected_company}&format=excel"), 'upra_export_nonce'); ?>" class="button">
                            <?php _e('Export Excel', 'upra-class-action'); ?>
                        </a>
                    </div>
                </div>

                <!-- Email Tools -->
                <div class="upra-tool-section">
                    <h2><?php _e('Email Tools', 'upra-class-action'); ?></h2>
                    <p><?php printf(__('Send bulk emails to %s shareholders.', 'upra-class-action'), strtoupper($selected_company)); ?></p>
                    
                    <div class="upra-email-buttons">
                        <a href="<?php echo admin_url("admin.php?page=upra-class-action-tools&company={$selected_company}&action=bulk_email"); ?>" class="button button-primary">
                            <?php _e('Send Bulk Email', 'upra-class-action'); ?>
                        </a>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="upra-tool-section">
                    <?php 
                    $stats = array(
                        'shareholders' => $this->database->get_shareholders_count($selected_company),
                        'shares' => $this->database->get_total_shares($selected_company),
                        'participation' => $this->database->get_total_participation($selected_company)
                    );
                    ?>
                    <h2><?php printf(__('%s Statistics', 'upra-class-action'), strtoupper($selected_company)); ?></h2>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <tbody>
                            <tr>
                                <td><strong><?php _e('Total Shareholders', 'upra-class-action'); ?></strong></td>
                                <td><?php echo number_format($stats['shareholders']); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Total Shares', 'upra-class-action'); ?></strong></td>
                                <td><?php echo number_format($stats['shares']); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Total Participation', 'upra-class-action'); ?></strong></td>
                                <td><?php echo number_format($stats['participation'], 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render bulk email form
     */
    private function render_bulk_email_form($company) {
        $shareholders_count = $this->database->get_shareholders_count($company);
        ?>
        <div class="upra-bulk-email-form">
            <h2><?php printf(__('Send Bulk Email to %s Shareholders', 'upra-class-action'), strtoupper($company)); ?></h2>
            <p><?php printf(__('This will send an email to all %d %s shareholders.', 'upra-class-action'), $shareholders_count, strtoupper($company)); ?></p>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="upra_bulk_email">
                <input type="hidden" name="company" value="<?php echo esc_attr($company); ?>">
                <?php wp_nonce_field('upra_bulk_email_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Subject', 'upra-class-action'); ?></th>
                        <td>
                            <input type="text" name="subject" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Message', 'upra-class-action'); ?></th>
                        <td>
                            <?php
                            wp_editor('', 'message', array(
                                'textarea_name' => 'message',
                                'textarea_rows' => 10,
                                'media_buttons' => false,
                                'teeny' => true
                            ));
                            ?>
                            <p class="description"><?php _e('HTML formatting is supported.', 'upra-class-action'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary" onclick="return confirm('<?php esc_attr_e('Are you sure you want to send this email to all shareholders?', 'upra-class-action'); ?>')">
                        <?php printf(__('Send to %d Shareholders', 'upra-class-action'), $shareholders_count); ?>
                    </button>
                    <a href="<?php echo admin_url("admin.php?page=upra-class-action-tools&company={$company}"); ?>" class="button">
                        <?php _e('Cancel', 'upra-class-action'); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render reports/statistics page
     */
    public function render_reports_page() {
        $companies = $this->get_supported_companies();
        ?>
        <div class="wrap">
            <h1><?php _e('UPRA Class Action Reports & Statistics', 'upra-class-action'); ?></h1>

            <div class="upra-statistics-overview">
                <?php foreach ($companies as $company): ?>
                    <?php
                    $stats = array(
                        'shareholders' => $this->database->get_shareholders_count($company),
                        'shares' => $this->database->get_total_shares($company),
                        'participation' => $this->database->get_total_participation($company)
                    );
                    ?>
                    
                    <div class="upra-company-statistics">
                        <h2><?php echo strtoupper(esc_html($company)); ?> <?php _e('Statistics', 'upra-class-action'); ?></h2>
                        
                        <div class="upra-stats-grid">
                            <div class="upra-stat-box">
                                <div class="upra-stat-number"><?php echo number_format($stats['shareholders']); ?></div>
                                <div class="upra-stat-label"><?php _e('Total Shareholders', 'upra-class-action'); ?></div>
                            </div>
                            
                            <div class="upra-stat-box">
                                <div class="upra-stat-number"><?php echo number_format($stats['shares']); ?></div>
                                <div class="upra-stat-label"><?php _e('Total Shares', 'upra-class-action'); ?></div>
                            </div>
                            
                            <div class="upra-stat-box">
                                <div class="upra-stat-number"><?php echo number_format($stats['participation'], 2); ?></div>
                                <div class="upra-stat-label"><?php _e('Total Participation', 'upra-class-action'); ?></div>
                            </div>
                        </div>

                        <div class="upra-stat-actions">
                            <a href="<?php echo admin_url("admin.php?page=upra-class-action-{$company}"); ?>" class="button button-primary">
                                <?php _e('View Details', 'upra-class-action'); ?>
                            </a>
                            <a href="<?php echo admin_url("admin.php?page=upra-class-action-tools&company={$company}"); ?>" class="button">
                                <?php _e('Export Data', 'upra-class-action'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
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
     * Add screen options
     */
    public function add_screen_options() {
        $option = 'per_page';
        $args = array(
            'label' => __('Shareholders per page', 'upra-class-action'),
            'default' => 25,
            'option' => 'shareholders_per_page'
        );
        add_screen_option($option, $args);
    }

    /**
     * Set screen options
     */
    public function set_screen_options($status, $option, $value) {
        if ('shareholders_per_page' == $option) {
            return $value;
        }
        return $status;
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

        $id = absint($_POST['id'] ?? 0);
        $company = sanitize_text_field($_POST['company'] ?? '');
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');

        // Verify the specific nonce for this shareholder
        if (!wp_verify_nonce($nonce, 'delete_shareholder_' . $id)) {
            wp_send_json_error(__('Security check failed', 'upra-class-action'));
        }

        if ($id && $company) {
            $result = $this->database->delete_shareholder($id, $company);
            if ($result) {
                wp_send_json_success(__('Shareholder deleted successfully', 'upra-class-action'));
            }
        }

        wp_send_json_error(__('Failed to delete shareholder', 'upra-class-action'));
    }

    /**
     * Handle get shareholder AJAX
     */
    public function handle_get_shareholder() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'upra-class-action'));
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'upra_admin_nonce')) {
            wp_send_json_error(__('Security check failed', 'upra-class-action'));
        }

        $id = absint($_POST['id'] ?? 0);
        $company = sanitize_text_field($_POST['company'] ?? '');

        if (!$id || !$company) {
            wp_send_json_error(__('Invalid parameters', 'upra-class-action'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'upra_shareholders_data';
        
        $shareholder = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d AND company = %s",
            $id,
            $company
        ));

        if ($shareholder) {
            wp_send_json_success($shareholder);
        } else {
            wp_send_json_error(__('Shareholder not found', 'upra-class-action'));
        }
    }

    /**
     * Handle update shareholder AJAX
     */
    public function handle_update_shareholder() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'upra-class-action'));
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'upra_admin_nonce')) {
            wp_send_json_error(__('Security check failed', 'upra-class-action'));
        }

        $form_data = array();
        wp_parse_str($_POST['form_data'] ?? '', $form_data);

        $id = absint($form_data['id'] ?? 0);
        $company = sanitize_text_field($form_data['company'] ?? '');

        if (!$id || !$company) {
            wp_send_json_error(__('Invalid parameters', 'upra-class-action'));
        }

        // Validate required fields
        if (empty($form_data['stockholder_name']) || empty($form_data['email']) || empty($form_data['phone'])) {
            wp_send_json_error(__('Name, email, and phone are required', 'upra-class-action'));
        }

        // Validate email
        if (!is_email($form_data['email'])) {
            wp_send_json_error(__('Please enter a valid email address', 'upra-class-action'));
        }

        $update_data = array(
            'stockholder_name' => sanitize_text_field($form_data['stockholder_name']),
            'email' => sanitize_email($form_data['email']),
            'phone' => sanitize_text_field($form_data['phone']),
            'stock' => absint($form_data['stock'] ?? 0),
            'purchase_price' => floatval($form_data['purchase_price'] ?? 0),
            'sell_price' => floatval($form_data['sell_price'] ?? 0),
            'loss' => floatval($form_data['loss'] ?? 0),
            'remarks' => sanitize_textarea_field($form_data['remarks'] ?? '')
        );

        $result = $this->database->update_shareholder_data($id, $update_data, $company);

        if ($result !== false) {
            wp_send_json_success(__('Shareholder updated successfully', 'upra-class-action'));
        } else {
            wp_send_json_error(__('Failed to update shareholder', 'upra-class-action'));
        }
    }
    public function handle_get_all_stats() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'upra_admin_nonce')) {
            wp_send_json_error(__('Security check failed', 'upra-class-action'));
        }

        $companies = $this->get_supported_companies();
        $stats = array();

        foreach ($companies as $company) {
            $stats[$company] = array(
                'shares' => $this->database->get_total_shares($company),
                'shareholders' => $this->database->get_shareholders_count($company),
                'participation' => $this->database->get_total_participation($company)
            );
        }

        wp_send_json_success($stats);
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
        return $this->options['supported_companies'] ?? array('atos', 'urpea');
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
        $selected_companies = isset($this->options[$args['field']]) ? $this->options[$args['field']] : array('atos', 'urpea');
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
     * Handle export download via admin-post.php
     */
    public function handle_export_download() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'upra-class-action'));
        }

        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'upra_export_nonce')) {
            wp_die(__('Security check failed', 'upra-class-action'));
        }

        $company = sanitize_text_field($_GET['company'] ?? '');
        $format = sanitize_text_field($_GET['format'] ?? 'csv');

        if (empty($company)) {
            wp_die(__('Company is required', 'upra-class-action'));
        }

        // Get all shareholders for the company
        $data = $this->database->get_shareholders_data(array(
            'company' => $company,
            'limit' => 999999,
            'offset' => 0
        ));

        if (empty($data)) {
            wp_die(__('No data found to export', 'upra-class-action'));
        }

        switch ($format) {
            case 'excel':
                $this->export_to_excel($data, $company);
                break;
            case 'csv':
            default:
                $this->export_to_csv($data, $company);
                break;
        }
    }

    /**
     * Export data to CSV format
     */
    private function export_to_csv($data, $company) {
        $filename = "upra-{$company}-shareholders-" . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for proper UTF-8 encoding in Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV headers
        $headers = array(
            'ID', 'Name', 'Email', 'Phone', 'Stock', 
            'Purchase Price', 'Sell Price', 'Loss', 
            'IP Address', 'Country', 'Remarks', 'Registration Date'
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
     * Export data to Excel format (using CSV with Excel-friendly headers)
     */
    private function export_to_excel($data, $company) {
        $filename = "upra-{$company}-shareholders-" . date('Y-m-d') . '.xls';
        
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo chr(0xEF).chr(0xBB).chr(0xBF); // BOM for UTF-8
        
        // Start HTML table for Excel
        echo '<table border="1">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>ID</th>';
        echo '<th>Name</th>';
        echo '<th>Email</th>';
        echo '<th>Phone</th>';
        echo '<th>Stock</th>';
        echo '<th>Purchase Price</th>';
        echo '<th>Sell Price</th>';
        echo '<th>Loss</th>';
        echo '<th>IP Address</th>';
        echo '<th>Country</th>';
        echo '<th>Remarks</th>';
        echo '<th>Registration Date</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($data as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row->id) . '</td>';
            echo '<td>' . esc_html($row->stockholder_name) . '</td>';
            echo '<td>' . esc_html($row->email) . '</td>';
            echo '<td>' . esc_html($row->phone) . '</td>';
            echo '<td>' . esc_html($row->stock) . '</td>';
            echo '<td>' . esc_html($row->purchase_price) . '</td>';
            echo '<td>' . esc_html($row->sell_price) . '</td>';
            echo '<td>' . esc_html($row->loss) . '</td>';
            echo '<td>' . esc_html($row->ip) . '</td>';
            echo '<td>' . esc_html($row->country) . '</td>';
            echo '<td>' . esc_html($row->remarks) . '</td>';
            echo '<td>' . esc_html($row->created_at) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        exit;
    }

    /**
     * Handle bulk email form submission via admin-post.php
     */
    public function handle_bulk_email_post() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'upra-class-action'));
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'upra_bulk_email_nonce')) {
            wp_die(__('Security check failed', 'upra-class-action'));
        }

        $company = sanitize_text_field($_POST['company'] ?? '');
        $subject = sanitize_text_field($_POST['subject'] ?? '');
        $message = wp_kses_post($_POST['message'] ?? '');

        if (empty($company) || empty($subject) || empty($message)) {
            wp_redirect(add_query_arg(array(
                'page' => 'upra-class-action-tools',
                'company' => $company,
                'action' => 'bulk_email',
                'upra_message' => __('All fields are required', 'upra-class-action'),
                'upra_type' => 'error'
            ), admin_url('admin.php')));
            exit;
        }

        $email_handler = UPRA_Class_Action_Email_Handler::get_instance();
        $sent_count = $email_handler->send_bulk_email($company, $subject, $message);

        if ($sent_count > 0) {
            wp_redirect(add_query_arg(array(
                'page' => 'upra-class-action-tools',
                'company' => $company,
                'upra_message' => sprintf(__('Email sent to %d shareholders', 'upra-class-action'), $sent_count),
                'upra_type' => 'success'
            ), admin_url('admin.php')));
        } else {
            wp_redirect(add_query_arg(array(
                'page' => 'upra-class-action-tools',
                'company' => $company,
                'action' => 'bulk_email',
                'upra_message' => __('No emails were sent', 'upra-class-action'),
                'upra_type' => 'error'
            ), admin_url('admin.php')));
        }
        exit;
    }
    public function render_general_settings_section() {
        echo '<p>' . __('Configure general plugin settings.', 'upra-class-action') . '</p>';
    }

    public function render_email_settings_section() {
        echo '<p>' . __('Configure email notification settings.', 'upra-class-action') . '</p>';
    }

    public function render_company_settings_section() {
        echo '<p>' . __('Configure which companies are supported by this plugin.', 'upra-class-action') . '</p>';
    }
}