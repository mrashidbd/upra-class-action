<?php
/**
 * Database handler for UPRA Class Action Plugin
 * 
 * Handles all database operations for shareholder data collection
 * Supports multiple companies with flexible table structure
 */

if (!defined('ABSPATH')) {
    exit;
}

class UPRA_Class_Action_Database {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * WordPress database object
     */
    private $wpdb;

    /**
     * Database version for upgrades
     */
    const DB_VERSION = '1.0.0';

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
        global $wpdb;
        $this->wpdb = $wpdb;
        
        add_action('init', array($this, 'check_database_version'));
    }

    /**
     * Create all necessary database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $table_name = $wpdb->prefix . 'upra_shareholders_data';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            company varchar(50) NOT NULL DEFAULT 'atos',
            stockholder_name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(50) NOT NULL,
            stock bigint(20) DEFAULT 0,
            purchase_price decimal(15,2) DEFAULT 0.00,
            sell_price decimal(15,2) DEFAULT 0.00,
            loss decimal(15,2) DEFAULT 0.00,
            ip varchar(45) DEFAULT NULL,
            country varchar(100) DEFAULT NULL,
            remarks text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY company_idx (company),
            KEY email_idx (email),
            KEY phone_idx (phone),
            KEY created_at_idx (created_at),
            UNIQUE KEY unique_email_company (email, company),
            UNIQUE KEY unique_phone_company (phone, company)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        update_option('upra_class_action_db_version', self::DB_VERSION);
    }

    /**
     * Check if database needs updates
     */
    public function check_database_version() {
        $installed_version = get_option('upra_class_action_db_version', '0.0.0');
        
        if (version_compare($installed_version, self::DB_VERSION, '<')) {
            self::create_tables();
        }
    }

    /**
     * Insert shareholder data
     */
    public function insert_shareholder_data($data) {
        $table_name = $this->wpdb->prefix . 'upra_shareholders_data';
        
        $insert_data = array(
            'company' => sanitize_text_field($data['company'] ?? 'atos'),
            'stockholder_name' => sanitize_text_field($data['stockholder_name']),
            'email' => sanitize_email($data['email']),
            'phone' => sanitize_text_field($data['phone']),
            'stock' => absint($data['stock'] ?? 0),
            'purchase_price' => floatval($data['purchase_price'] ?? 0),
            'sell_price' => floatval($data['sell_price'] ?? 0),
            'loss' => floatval($data['loss'] ?? 0),
            'ip' => sanitize_text_field($data['ip'] ?? ''),
            'country' => sanitize_text_field($data['country'] ?? ''),
            'remarks' => sanitize_textarea_field($data['remarks'] ?? '')
        );
        
        $result = $this->wpdb->insert($table_name, $insert_data);
        
        if ($result === false) {
            return new WP_Error('db_insert_error', 
                sprintf(__('Failed to insert data: %s', 'upra-class-action'), $this->wpdb->last_error)
            );
        }
        
        return $this->wpdb->insert_id;
    }

    /**
     * Check if shareholder already exists
     */
    public function check_duplicate_shareholder($email, $phone, $company = 'atos') {
        $table_name = $this->wpdb->prefix . 'upra_shareholders_data';
        
        $query = $this->wpdb->prepare(
            "SELECT id FROM $table_name WHERE (email = %s OR phone = %s) AND company = %s",
            $email,
            $phone,
            $company
        );
        
        return $this->wpdb->get_var($query);
    }

    /**
     * Get total shares for a company
     */
    public function get_total_shares($company = 'atos') {
        $table_name = $this->wpdb->prefix . 'upra_shareholders_data';
        
        $query = $this->wpdb->prepare(
            "SELECT COALESCE(SUM(stock), 0) FROM $table_name WHERE company = %s",
            $company
        );
        
        return (int) $this->wpdb->get_var($query);
    }

    /**
     * Get total participation (purchase_price * stock) for a company
     */
    public function get_total_participation($company = 'atos') {
        $table_name = $this->wpdb->prefix . 'upra_shareholders_data';
        
        $query = $this->wpdb->prepare(
            "SELECT COALESCE(SUM(purchase_price * stock), 0) FROM $table_name WHERE company = %s",
            $company
        );
        
        return (float) $this->wpdb->get_var($query);
    }

    /**
     * Get total number of shareholders for a company
     */
    public function get_shareholders_count($company = 'atos') {
        $table_name = $this->wpdb->prefix . 'upra_shareholders_data';
        
        $query = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE company = %s",
            $company
        );
        
        return (int) $this->wpdb->get_var($query);
    }

    /**
     * Get shareholders data with pagination and search
     */
    public function get_shareholders_data($args = array()) {
        $table_name = $this->wpdb->prefix . 'upra_shareholders_data';
        
        $defaults = array(
            'company' => 'atos',
            'orderby' => 'id',
            'order' => 'DESC',
            'search' => '',
            'limit' => 25,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = $this->wpdb->prepare("WHERE company = %s", $args['company']);
        
        if (!empty($args['search'])) {
            $search = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where .= $this->wpdb->prepare(
                " AND (stockholder_name LIKE %s OR email LIKE %s OR phone LIKE %s)",
                $search,
                $search,
                $search
            );
        }
        
        $allowed_orderby = array('id', 'stockholder_name', 'email', 'stock', 'purchase_price', 'sell_price', 'loss', 'created_at');
        if (!in_array($args['orderby'], $allowed_orderby)) {
            $args['orderby'] = 'id';
        }
        
        $args['order'] = strtoupper($args['order']);
        if (!in_array($args['order'], array('ASC', 'DESC'))) {
            $args['order'] = 'DESC';
        }
        
        $orderby = "ORDER BY {$args['orderby']} {$args['order']}";
        $limit = $this->wpdb->prepare("LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        
        $query = "SELECT * FROM $table_name $where $orderby $limit";
        
        return $this->wpdb->get_results($query);
    }

    /**
     * Get total count for pagination
     */
    public function get_shareholders_total_count($company = 'atos', $search = '') {
        $table_name = $this->wpdb->prefix . 'upra_shareholders_data';
        
        $where = $this->wpdb->prepare("WHERE company = %s", $company);
        
        if (!empty($search)) {
            $search = '%' . $this->wpdb->esc_like($search) . '%';
            $where .= $this->wpdb->prepare(
                " AND (stockholder_name LIKE %s OR email LIKE %s OR phone LIKE %s)",
                $search,
                $search,
                $search
            );
        }
        
        $query = "SELECT COUNT(*) FROM $table_name $where";
        
        return (int) $this->wpdb->get_var($query);
    }

    /**
     * Delete shareholder data
     */
    public function delete_shareholder($id, $company = 'atos') {
        $table_name = $this->wpdb->prefix . 'upra_shareholders_data';
        
        return $this->wpdb->delete(
            $table_name,
            array(
                'id' => $id,
                'company' => $company
            ),
            array('%d', '%s')
        );
    }

    /**
     * Update shareholder data
     */
    public function update_shareholder_data($id, $data, $company = 'atos') {
        $table_name = $this->wpdb->prefix . 'upra_shareholders_data';
        
        $update_data = array();
        $allowed_fields = array(
            'stockholder_name', 'email', 'phone', 'stock', 
            'purchase_price', 'sell_price', 'loss', 'remarks'
        );
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                switch ($field) {
                    case 'email':
                        $update_data[$field] = sanitize_email($data[$field]);
                        break;
                    case 'stock':
                        $update_data[$field] = absint($data[$field]);
                        break;
                    case 'purchase_price':
                    case 'sell_price':
                    case 'loss':
                        $update_data[$field] = floatval($data[$field]);
                        break;
                    case 'remarks':
                        $update_data[$field] = sanitize_textarea_field($data[$field]);
                        break;
                    default:
                        $update_data[$field] = sanitize_text_field($data[$field]);
                        break;
                }
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $this->wpdb->update(
            $table_name,
            $update_data,
            array(
                'id' => $id,
                'company' => $company
            ),
            null,
            array('%d', '%s')
        );
    }

    /**
     * Get single shareholder by ID
     */
    public function get_shareholder_by_id($id, $company = 'atos') {
        $table_name = $this->wpdb->prefix . 'upra_shareholders_data';
        
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND company = %s",
            $id,
            $company
        ));
    }

    /**
     * Get all supported companies
     */
    public function get_supported_companies() {
        $table_name = $this->wpdb->prefix . 'upra_shareholders_data';
        
        $query = "SELECT DISTINCT company FROM $table_name ORDER BY company";
        
        return $this->wpdb->get_col($query);
    }

    /**
     * Drop tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'upra_shareholders_data';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        delete_option('upra_class_action_db_version');
    }

    /**
     * Get database statistics
     */
    public function get_database_statistics() {
        $table_name = $this->wpdb->prefix . 'upra_shareholders_data';
        
        $stats = array();
        
        // Get company breakdown
        $company_stats = $this->wpdb->get_results(
            "SELECT company, 
                    COUNT(*) as shareholders,
                    COALESCE(SUM(stock), 0) as total_shares,
                    COALESCE(SUM(purchase_price * stock), 0) as total_participation
             FROM $table_name 
             GROUP BY company"
        );
        
        foreach ($company_stats as $row) {
            $stats[$row->company] = array(
                'shareholders' => (int) $row->shareholders,
                'shares' => (int) $row->total_shares,
                'participation' => (float) $row->total_participation
            );
        }
        
        return $stats;
    }

    /**
     * Cleanup old data based on retention period
     */
    public function cleanup_old_data($retention_days = 0) {
        if ($retention_days <= 0) {
            return 0;
        }
        
        $table_name = $this->wpdb->prefix . 'upra_shareholders_data';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        return $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$table_name} WHERE created_at < %s",
            $cutoff_date
        ));
    }
}