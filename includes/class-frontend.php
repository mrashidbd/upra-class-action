<?php
/**
 * Frontend Handler for UPRA Class Action Plugin
 * 
 * Handles frontend form rendering, asset loading, and shortcodes
 * Supports multiple companies with customizable forms
 */

if (!defined('ABSPATH')) {
    exit;
}

class UPRA_Class_Action_Frontend {

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
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Register shortcodes
        add_shortcode('upra_data_collection_form', array($this, 'render_data_collection_form'));
        add_shortcode('upra_company_stats', array($this, 'render_company_stats'));
        
        // AJAX localization
        add_action('wp_enqueue_scripts', array($this, 'localize_ajax_scripts'));
        
        // Add custom page templates
        add_filter('template_include', array($this, 'load_custom_templates'));
        
        // Add custom CSS for admin list table (moved from functions.php)
        add_action('admin_head', array($this, 'admin_list_table_css'));
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        $version = UPRA_CLASS_ACTION_VERSION;
        
        // Enqueue styles
        wp_enqueue_style(
            'upra-frontend-style',
            UPRA_CLASS_ACTION_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            $version
        );

        // Enqueue scripts based on page/template
        if ($this->should_load_data_collection_assets()) {
            wp_enqueue_script(
                'upra-data-collection',
                UPRA_CLASS_ACTION_PLUGIN_URL . 'assets/js/data-collection.js',
                array('jquery'),
                $version,
                true
            );
        }

        if ($this->should_load_stats_assets()) {
            wp_enqueue_script(
                'upra-stats-display',
                UPRA_CLASS_ACTION_PLUGIN_URL . 'assets/js/stats-display.js',
                array('jquery'),
                $version,
                true
            );
        }

        // Load JSON reader for specific templates (from original code)
        if (is_page_template('template-home-v2.php')) {
            wp_enqueue_script(
                'upra-json-reader',
                UPRA_CLASS_ACTION_PLUGIN_URL . 'assets/js/json-reader.js',
                array('jquery'),
                $version,
                true
            );
            
            // Add module type attribute (from original functions.php)
            add_filter('script_loader_tag', array($this, 'add_module_type_to_json_reader'), 10, 3);
        }
    }

    /**
     * Localize AJAX scripts
     */
    public function localize_ajax_scripts() {
        // For data collection pages
        if ($this->should_load_data_collection_assets()) {
            wp_localize_script('upra-data-collection', 'upra_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('upra_shareholder_nonce'),
                'messages' => array(
                    'success' => __('Data submitted successfully!', 'upra-class-action'),
                    'error' => __('An error occurred. Please try again.', 'upra-class-action'),
                    'validation_error' => __('Please fill out the following fields:', 'upra-class-action')
                )
            ));
        }

        // For stats display pages
        if ($this->should_load_stats_assets()) {
            $company = $this->get_current_page_company();
            $stats = $this->get_company_stats($company);
            
            wp_localize_script('upra-stats-display', 'upra_stats', array(
                'total_shares' => $stats['total_shares'],
                'total_people' => $stats['shareholders_count'],
                'total_participation' => $stats['total_participation']
            ));
        }
    }

    /**
     * Render data collection form via shortcode
     */
    public function render_data_collection_form($atts) {
        $atts = shortcode_atts(array(
            'company' => 'atos',
            'title' => '',
            'show_stats' => 'true'
        ), $atts);

        ob_start();
        $this->display_data_collection_form($atts['company'], $atts);
        return ob_get_clean();
    }

    /**
     * Render company statistics via shortcode
     */
    public function render_company_stats($atts) {
        $atts = shortcode_atts(array(
            'company' => 'atos',
            'format' => 'default' // default, inline, card
        ), $atts);

        ob_start();
        $this->display_company_stats($atts['company'], $atts['format']);
        return ob_get_clean();
    }

    /**
     * Display data collection form
     */
    private function display_data_collection_form($company = 'atos', $options = array()) {
        $form_config = $this->get_form_config($company);
        $stats = $this->get_company_stats($company);
        
        ?>
        <div class="upra-form-container" data-company="<?php echo esc_attr($company); ?>">
            
            <?php if (!empty($options['title'])): ?>
                <h2 class="upra-form-title"><?php echo esc_html($options['title']); ?></h2>
            <?php endif; ?>

            <?php if ($options['show_stats'] === 'true'): ?>
                <div class="upra-stats-summary">
                    <?php $this->display_company_stats($company, 'card'); ?>
                </div>
            <?php endif; ?>

            <form id="upra-shareholder-form" class="upra-data-collection-form" method="post">
                <?php wp_nonce_field('upra_shareholder_nonce', 'upra_nonce'); ?>
                <input type="hidden" name="company" value="<?php echo esc_attr($company); ?>">

                <div class="upra-form-grid">
                    
                    <!-- Personal Information -->
                    <div class="upra-form-section">
                        <h3><?php _e('Personal Information', 'upra-class-action'); ?></h3>
                        
                        <div class="upra-form-field">
                            <label for="stockholder_name"><?php echo esc_html($form_config['labels']['name']); ?></label>
                            <input type="text" 
                                   id="stockholder_name" 
                                   name="name" 
                                   required
                                   placeholder="<?php echo esc_attr($form_config['placeholders']['name']); ?>">
                        </div>

                        <div class="upra-form-field">
                            <label for="email"><?php echo esc_html($form_config['labels']['email']); ?></label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   required
                                   placeholder="<?php echo esc_attr($form_config['placeholders']['email']); ?>">
                        </div>

                        <div class="upra-form-field">
                            <label for="phone"><?php echo esc_html($form_config['labels']['phone']); ?></label>
                            <input type="tel" 
                                   id="phone" 
                                   name="phone" 
                                   required
                                   placeholder="<?php echo esc_attr($form_config['placeholders']['phone']); ?>">
                        </div>
                    </div>

                    <!-- Stock Information -->
                    <div class="upra-form-section">
                        <h3><?php _e('Stock Information', 'upra-class-action'); ?></h3>
                        
                        <div class="upra-form-field">
                            <label for="stock"><?php echo esc_html($form_config['labels']['stock']); ?></label>
                            <input type="number" 
                                   id="stock" 
                                   name="stock" 
                                   min="0"
                                   placeholder="<?php echo esc_attr($form_config['placeholders']['stock']); ?>">
                        </div>

                        <div class="upra-form-field">
                            <label for="purchase_price"><?php echo esc_html($form_config['labels']['purchase']); ?></label>
                            <input type="number" 
                                   id="purchase_price" 
                                   name="purchase" 
                                   min="0" 
                                   step="0.01"
                                   placeholder="<?php echo esc_attr($form_config['placeholders']['purchase']); ?>">
                        </div>

                        <div class="upra-form-field">
                            <label for="sell_price"><?php echo esc_html($form_config['labels']['sell']); ?></label>
                            <input type="number" 
                                   id="sell_price" 
                                   name="sell" 
                                   min="0" 
                                   step="0.01"
                                   placeholder="<?php echo esc_attr($form_config['placeholders']['sell']); ?>">
                        </div>

                        <div class="upra-form-field">
                            <label for="loss"><?php echo esc_html($form_config['labels']['loss']); ?></label>
                            <input type="number" 
                                   id="loss" 
                                   name="loss" 
                                   min="0" 
                                   step="0.01"
                                   placeholder="<?php echo esc_attr($form_config['placeholders']['loss']); ?>">
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="upra-form-section upra-full-width">
                        <h3><?php _e('Additional Information', 'upra-class-action'); ?></h3>
                        
                        <div class="upra-form-field">
                            <label for="remarks"><?php echo esc_html($form_config['labels']['remarks']); ?></label>
                            <textarea id="remarks" 
                                      name="remarks" 
                                      rows="4"
                                      placeholder="<?php echo esc_attr($form_config['placeholders']['remarks']); ?>"></textarea>
                        </div>
                    </div>

                </div>

                <!-- Form Messages -->
                <div class="upra-form-messages">
                    <div id="upra-success-message" class="upra-message upra-success hidden"></div>
                    <div id="upra-error-message" class="upra-message upra-error hidden"></div>
                    <div id="upra-validation-errors" class="upra-message upra-validation hidden">
                        <ul></ul>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="upra-form-submit">
                    <button type="submit" class="upra-submit-btn">
                        <span class="upra-submit-text"><?php echo esc_html($form_config['submit_text']); ?></span>
                        <span class="upra-submit-loading hidden"><?php _e('Submitting...', 'upra-class-action'); ?></span>
                    </button>
                </div>

            </form>
        </div>
        <?php
    }

    /**
     * Display company statistics
     */
    private function display_company_stats($company, $format = 'default') {
        $stats = $this->get_company_stats($company);
        $company_name = strtoupper($company);
        
        ?>
        <div class="upra-stats-container upra-stats-<?php echo esc_attr($format); ?>">
            <?php if ($format === 'card'): ?>
                <div class="upra-stats-card">
                    <h3><?php printf(__('%s Statistics', 'upra-class-action'), $company_name); ?></h3>
                    <div class="upra-stats-grid">
                        <div class="upra-stat-item">
                            <span class="upra-stat-label"><?php _e('Total Shares:', 'upra-class-action'); ?></span>
                            <span class="upra-stat-value" id="upra-total-shares"><?php echo number_format($stats['total_shares']); ?></span>
                        </div>
                        <div class="upra-stat-item">
                            <span class="upra-stat-label"><?php _e('Shareholders:', 'upra-class-action'); ?></span>
                            <span class="upra-stat-value" id="upra-total-people"><?php echo number_format($stats['shareholders_count']); ?></span>
                        </div>
                        <?php if ($stats['total_participation'] > 0): ?>
                        <div class="upra-stat-item">
                            <span class="upra-stat-label"><?php _e('Total Participation:', 'upra-class-action'); ?></span>
                            <span class="upra-stat-value" id="upra-total-participation"><?php echo number_format($stats['total_participation'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($format === 'inline'): ?>
                <div class="upra-stats-inline">
                    <span><?php printf(__('%s Shares: ', 'upra-class-action'), $company_name); ?></span>
                    <strong id="upra-total-shares"><?php echo number_format($stats['total_shares']); ?></strong>
                    <span> | <?php _e('Shareholders: ', 'upra-class-action'); ?></span>
                    <strong id="upra-total-people"><?php echo number_format($stats['shareholders_count']); ?></strong>
                </div>
            <?php else: ?>
                <div class="upra-stats-default">
                    <p><?php _e('Total Shares:', 'upra-class-action'); ?> <strong id="upra-total-shares"><?php echo number_format($stats['total_shares']); ?></strong></p>
                    <p><?php _e('Total Shareholders:', 'upra-class-action'); ?> <strong id="upra-total-people"><?php echo number_format($stats['shareholders_count']); ?></strong></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get form configuration based on company
     */
    private function get_form_config($company) {
        $configs = array(
            'atos' => array(
                'labels' => array(
                    'name' => __('Your Name', 'upra-class-action'),
                    'email' => __('Email address', 'upra-class-action'),
                    'phone' => __('Mobile Phone', 'upra-class-action'),
                    'stock' => __('Nombre d\'actions détenues', 'upra-class-action'),
                    'purchase' => __('Buy Price', 'upra-class-action'),
                    'sell' => __('Sell Price', 'upra-class-action'),
                    'loss' => __('Perte Totale', 'upra-class-action'),
                    'remarks' => __('Remarques', 'upra-class-action')
                ),
                'placeholders' => array(
                    'name' => __('Your Name (Required)', 'upra-class-action'),
                    'email' => __('Your Email Address (Required)', 'upra-class-action'),
                    'phone' => __('Mobile Phone (Required)', 'upra-class-action'),
                    'stock' => __('Nombre d\'actions détenues', 'upra-class-action'),
                    'purchase' => __('Buy Price', 'upra-class-action'),
                    'sell' => __('Sell Price', 'upra-class-action'),
                    'loss' => __('Perte Totale', 'upra-class-action'),
                    'remarks' => __('Remarques', 'upra-class-action')
                ),
                'submit_text' => __('Submit', 'upra-class-action')
            ),
            'urpea' => array(
                'labels' => array(
                    'name' => __('Your Name', 'upra-class-action'),
                    'email' => __('Email address', 'upra-class-action'),
                    'phone' => __('Mobile Phone', 'upra-class-action'),
                    'stock' => __('Number of Shares Held', 'upra-class-action'),
                    'purchase' => __('Purchase Price', 'upra-class-action'),
                    'sell' => __('Sell Price', 'upra-class-action'),
                    'loss' => __('Total Loss', 'upra-class-action'),
                    'remarks' => __('Remarks', 'upra-class-action')
                ),
                'placeholders' => array(
                    'name' => __('Your Name (Required)', 'upra-class-action'),
                    'email' => __('Your Email Address (Required)', 'upra-class-action'),
                    'phone' => __('Mobile Phone (Required)', 'upra-class-action'),
                    'stock' => __('Number of shares held', 'upra-class-action'),
                    'purchase' => __('Purchase price per share', 'upra-class-action'),
                    'sell' => __('Sell price per share', 'upra-class-action'),
                    'loss' => __('Total financial loss', 'upra-class-action'),
                    'remarks' => __('Additional comments', 'upra-class-action')
                ),
                'submit_text' => __('Submit Registration', 'upra-class-action')
            )
        );

        return $configs[$company] ?? $configs['atos'];
    }

    /**
     * Get company statistics
     */
    private function get_company_stats($company) {
        return array(
            'total_shares' => $this->database->get_total_shares($company),
            'shareholders_count' => $this->database->get_shareholders_count($company),
            'total_participation' => $this->database->get_total_participation($company)
        );
    }

    /**
     * Determine if data collection assets should be loaded
     */
    private function should_load_data_collection_assets() {
        return is_page_template('upra-data-collect-template.php') || 
               has_shortcode(get_post()->post_content ?? '', 'upra_data_collection_form');
    }

    /**
     * Determine if stats assets should be loaded
     */
    private function should_load_stats_assets() {
        return is_front_page() || 
               is_page_template('template-home-v2.php') || 
               has_shortcode(get_post()->post_content ?? '', 'upra_company_stats');
    }

    /**
     * Get current page company context
     */
    private function get_current_page_company() {
        // Check if company is specified in URL parameters
        if (isset($_GET['company'])) {
            return sanitize_text_field($_GET['company']);
        }
        
        // Check page template or content for company context
        // Default to ATOS for backward compatibility
        return 'atos';
    }

    /**
     * Load custom page templates
     */
    public function load_custom_templates($template) {
        if (is_page_template('upra-data-collect-template.php')) {
            $custom_template = UPRA_CLASS_ACTION_PLUGIN_DIR . 'templates/data-collection-form.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        
        return $template;
    }

    /**
     * Add module type to JSON reader script (from original functions.php)
     */
    public function add_module_type_to_json_reader($tag, $handle, $src) {
        if ('upra-json-reader' !== $handle) {
            return $tag;
        }
        
        return '<script type="module" crossorigin src="' . esc_url($src) . '"></script>';
    }

    /**
     * Add custom CSS for admin list table (from original functions.php)
     */
    public function admin_list_table_css() {
        $page = isset($_GET['page']) ? esc_attr($_GET['page']) : false;
        if ('upra_shareholders' != $page) {
            return;
        }
        
        ?>
        <style type="text/css">
        .wp-list-table .column-id { width: 5%; }
        .wp-list-table .column-stockholder_name { width: 10%; }
        .wp-list-table .column-email { width: 15%; }
        .wp-list-table .column-phone { width: 10%; }
        .wp-list-table .column-stock { width: 10%; }
        .wp-list-table .column-participation { width: 10%; }
        .wp-list-table .column-ip { width: 15%; }
        .wp-list-table .column-country { width: 10%; }
        .wp-list-table .column-remarks { width: 15%; }
        </style>
        <?php
    }
}