<?php
/**
 * Admin Menu Handler for UPRA Class Action Plugin
 * 
 * Handles the creation of admin menu pages and manages shareholder list tables
 * Maintains backward compatibility with original ATOS shareholders page
 */

if (!defined('ABSPATH')) {
    exit;
}

class UPRA_Class_Action_Admin_Menu {

    /**
     * Single instance of the class
     */
    private static $instance = null;

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
        // Admin menu creation
        add_action('admin_menu', array($this, 'create_admin_menus'));
        
        // Handle legacy ATOS menu for backward compatibility
        add_action('admin_menu', array($this, 'create_legacy_atos_menu'));
        
        // Screen options for list tables
        add_filter('set-screen-option', array($this, 'set_screen_options'), 10, 3);
        
        // Add screen options
        add_action('load-toplevel_page_upra-shareholders', array($this, 'add_screen_options'));
        add_action('load-upra-class-action_page_upra-shareholders', array($this, 'add_screen_options'));
    }

    /**
     * Create main admin menus
     */
    public function create_admin_menus() {
        // Main shareholders menu page
        $parent_page = add_menu_page(
            __('UPRA Shareholders', 'upra-class-action'),
            __('UPRA Shareholders', 'upra-class-action'),
            'manage_options',
            'upra-shareholders',
            array($this, 'render_shareholders_page'),
            'dashicons-groups',
            25
        );

        // Company-specific submenus
        $this->add_company_submenus();

        // Add screen options for the main page
        add_action("load-{$parent_page}", array($this, 'add_screen_options'));
    }

    /**
     * Create legacy ATOS menu for backward compatibility
     */
    public function create_legacy_atos_menu() {
        // Legacy ATOS menu (maintaining original structure)
        $icon = 'dashicons-chart-pie';
        $legacy_page = add_menu_page(
            'Atos Shares', 
            'Atos Shares', 
            'manage_options', 
            'atos_shareholders', 
            array($this, 'render_legacy_atos_page'), 
            $icon, 
            80
        );

        add_action("load-{$legacy_page}", array($this, 'add_screen_options'));
    }

    /**
     * Add company-specific submenus
     */
    private function add_company_submenus() {
        $companies = $this->get_supported_companies();

        foreach ($companies as $company) {
            $company_name = strtoupper($company);
            
            add_submenu_page(
                'upra-shareholders',
                sprintf(__('%s Shareholders', 'upra-class-action'), $company_name),
                $company_name,
                'manage_options',
                "upra-shareholders-{$company}",
                array($this, 'render_company_shareholders_page')
            );
        }

        // Tools submenu
        add_submenu_page(
            'upra-shareholders',
            __('Tools & Export', 'upra-class-action'),
            __('Tools', 'upra-class-action'),
            'manage_options',
            'upra-shareholders-tools',
            array($this, 'render_tools_page')
        );

        // Statistics submenu
        add_submenu_page(
            'upra-shareholders',
            __('Statistics', 'upra-class-action'),
            __('Statistics', 'upra-class-action'),
            'manage_options',
            'upra-shareholders-stats',
            array($this, 'render_statistics_page')
        );
    }

    /**
     * Render main shareholders page
     */
    public function render_shareholders_page() {
        $current_company = $this->get_current_company();
        
        if (!$current_company) {
            $this->render_company_selection_page();
            return;
        }

        $this->render_shareholders_list($current_company);
    }

    /**
     * Render company-specific shareholders page
     */
    public function render_company_shareholders_page() {
        $page = $_GET['page'] ?? '';
        $company = str_replace('upra-shareholders-', '', $page);
        
        if (empty($company) || !in_array($company, $this->get_supported_companies())) {
            wp_die(__('Invalid company specified.', 'upra-class-action'));
        }

        $this->render_shareholders_list($company);
    }

    /**
     * Render legacy ATOS page for backward compatibility
     */
    public function render_legacy_atos_page() {
        // Use the original callback structure but with new functionality
        $this->render_shareholders_list('atos', true);
    }

    /**
     * Render company selection page
     */
    private function render_company_selection_page() {
        $companies = $this->get_supported_companies();
        ?>
        <div class="wrap">
            <h1><?php _e('UPRA Shareholders', 'upra-class-action'); ?></h1>
            
            <div class="upra-company-selection">
                <h2><?php _e('Select a Company', 'upra-class-action'); ?></h2>
                <p><?php _e('Choose a company to view its shareholder data:', 'upra-class-action'); ?></p>
                
                <div class="upra-company-cards">
                    <?php foreach ($companies as $company): ?>
                        <?php
                        $stats = array(
                            'shareholders' => $this->database->get_shareholders_count($company),
                            'shares' => $this->database->get_total_shares($company),
                            'participation' => $this->database->get_total_participation($company)
                        );
                        ?>
                        <div class="upra-company-card">
                            <h3><?php echo strtoupper(esc_html($company)); ?></h3>
                            <div class="upra-company-stats">
                                <p><strong><?php _e('Shareholders:', 'upra-class-action'); ?></strong> <?php echo number_format($stats['shareholders']); ?></p>
                                <p><strong><?php _e('Total Shares:', 'upra-class-action'); ?></strong> <?php echo number_format($stats['shares']); ?></p>
                                <p><strong><?php _e('Participation:', 'upra-class-action'); ?></strong> <?php echo number_format($stats['participation'], 2); ?></p>
                            </div>
                            <div class="upra-company-actions">
                                <a href="<?php echo admin_url("admin.php?page=upra-shareholders-{$company}"); ?>" class="button button-primary">
                                    <?php _e('View Shareholders', 'upra-class-action'); ?>
                                </a>
                                <a href="<?php echo admin_url("admin.php?page=upra-shareholders-tools&company={$company}"); ?>" class="button">
                                    <?php _e('Export Data', 'upra-class-action'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render shareholders list for a specific company
     */
    private function render_shareholders_list($company, $is_legacy = false) {
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
        $page_title = $is_legacy ? 'List of ATOS share owners' : sprintf(__('%s Shareholders', 'upra-class-action'), $company_name);
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html($page_title); ?></h1>
            
            <?php if (!$is_legacy): ?>
                <a href="<?php echo admin_url('admin.php?page=upra-shareholders-tools&company=' . $company); ?>" class="page-title-action">
                    <?php _e('Export Data', 'upra-class-action'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=upra-shareholders-tools&company=' . $company . '&action=bulk_email'); ?>" class="page-title-action">
                    <?php _e('Send Bulk Email', 'upra-class-action'); ?>
                </a>
            <?php endif; ?>

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
                <?php if ($is_legacy): ?>
                    <h2 style="text-align: center;background-color: #ccdee8;padding: 10px 0;color: #000000;">
                        Total Stakes: <span style="color:green;"><?php echo number_format($stats['total_shares']); ?></span> | 
                        Total Participation: <span style="color:green;"><?php echo number_format($stats['total_participation']); ?></span> | 
                        Number of People: <span style="color:green;"><?php echo $stats['shareholders_count']; ?></span>
                    </h2>
                <?php else: ?>
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
                <?php endif; ?>
            </div>
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
            <h1><?php _e('UPRA Shareholders Tools', 'upra-class-action'); ?></h1>

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
                    <input type="hidden" name="page" value="upra-shareholders-tools">
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
                        <a href="<?php echo wp_nonce_url(admin_url("admin-post.php?action=upra_export&company={$selected_company}&format=json"), 'upra_export_nonce'); ?>" class="button">
                            <?php _e('Export JSON', 'upra-class-action'); ?>
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
                        <a href="<?php echo admin_url("admin.php?page=upra-shareholders-tools&company={$selected_company}&action=bulk_email"); ?>" class="button button-primary">
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
                    <a href="<?php echo admin_url("admin.php?page=upra-shareholders-tools&company={$company}"); ?>" class="button">
                        <?php _e('Cancel', 'upra-class-action'); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render statistics page
     */
    public function render_statistics_page() {
        $companies = $this->get_supported_companies();
        ?>
        <div class="wrap">
            <h1><?php _e('UPRA Shareholders Statistics', 'upra-class-action'); ?></h1>

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
                            <a href="<?php echo admin_url("admin.php?page=upra-shareholders-{$company}"); ?>" class="button button-primary">
                                <?php _e('View Details', 'upra-class-action'); ?>
                            </a>
                            <a href="<?php echo admin_url("admin.php?page=upra-shareholders-tools&company={$company}"); ?>" class="button">
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
     * Get current company from URL parameters
     */
    private function get_current_company() {
        if (isset($_GET['company'])) {
            $company = sanitize_text_field($_GET['company']);
            if (in_array($company, $this->get_supported_companies())) {
                return $company;
            }
        }
        return false;
    }

    /**
     * Get supported companies
     */
    private function get_supported_companies() {
        $options = get_option('upra_class_action_options', array());
        return $options['supported_companies'] ?? array('atos');
    }
}