<?php
/**
 * List Table Handler for UPRA Class Action Plugin
 * 
 * Handles the display and management of shareholder data in WordPress admin
 * Extends WP_List_Table for native WordPress admin experience
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure WP_List_Table is available
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class UPRA_Class_Action_List_Table extends WP_List_Table {

    /**
     * Company for this list table
     */
    private $company;

    /**
     * Database instance
     */
    private $database;

    /**
     * Items per page
     */
    private $per_page;

    /**
     * Constructor
     */
    public function __construct($company = 'atos') {
        $this->company = sanitize_text_field($company);
        $this->database = UPRA_Class_Action_Database::get_instance();
        
        // Set items per page from screen options
        $this->per_page = $this->get_items_per_page('shareholders_per_page', 25);

        parent::__construct(array(
            'singular' => 'shareholder',
            'plural'   => 'shareholders',
            'ajax'     => false
        ));
    }

    /**
     * Get table columns
     */
    public function get_columns() {
        $columns = array(
            'cb'                => '<input type="checkbox" />',
            'id'                => __('ID', 'upra-class-action'),
            'stockholder_name'  => __('Name', 'upra-class-action'),
            'email'             => __('Email', 'upra-class-action'),
            'phone'             => __('Phone', 'upra-class-action'),
            'stock'             => __('Stock', 'upra-class-action'),
            'purchase_price'    => __('Purchase Price', 'upra-class-action'),
            'sell_price'        => __('Sell Price', 'upra-class-action'),
            'loss'              => __('Loss', 'upra-class-action'),
            'ip'                => __('IP Address', 'upra-class-action'),
            'country'           => __('Country', 'upra-class-action'),
            'remarks'           => __('Remarks', 'upra-class-action'),
            'created_at'        => __('Registration Date', 'upra-class-action'),
            'actions'           => __('Actions', 'upra-class-action')
        );

        return $columns;
    }

    /**
     * Get hidden columns
     */
    protected function get_hidden_columns() {
        // Hide IP and country by default for privacy
        return array('ip', 'country');
    }

    /**
     * Get sortable columns
     */
    protected function get_sortable_columns() {
        return array(
            'id'                => array('id', true),
            'stockholder_name'  => array('stockholder_name', false),
            'email'             => array('email', false),
            'stock'             => array('stock', false),
            'purchase_price'    => array('purchase_price', false),
            'sell_price'        => array('sell_price', false),
            'loss'              => array('loss', false),
            'created_at'        => array('created_at', false)
        );
    }

    /**
     * Get bulk actions
     */
    protected function get_bulk_actions() {
        return array(
            'delete'      => __('Delete', 'upra-class-action'),
            'export'      => __('Export Selected', 'upra-class-action'),
            'send_email'  => __('Send Email to Selected', 'upra-class-action')
        );
    }

    /**
     * Column checkbox
     */
    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="shareholder[]" value="%s" />',
            $item->id
        );
    }

    /**
     * Default column display
     */
    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return $item->id;
            
            case 'stockholder_name':
                return esc_html($item->stockholder_name);
            
            case 'email':
                return sprintf(
                    '<a href="mailto:%s">%s</a>',
                    esc_attr($item->email),
                    esc_html($item->email)
                );
            
            case 'phone':
                return esc_html($item->phone);
            
            case 'stock':
                return number_format($item->stock);
            
            case 'purchase_price':
                return number_format($item->purchase_price, 2);
            
            case 'sell_price':
                return number_format($item->sell_price, 2);
            
            case 'loss':
                return number_format($item->loss, 2);
            
            case 'ip':
                return esc_html($item->ip);
            
            case 'country':
                return esc_html($item->country);
            
            case 'remarks':
                $remarks = esc_html($item->remarks);
                if (strlen($remarks) > 50) {
                    return substr($remarks, 0, 50) . '...';
                }
                return $remarks;
            
            case 'created_at':
                return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->created_at));
            
            case 'actions':
                return $this->get_row_actions($item);
            
            default:
                return isset($item->$column_name) ? esc_html($item->$column_name) : '';
        }
    }

    /**
     * Column for stockholder name with row actions
     */
    protected function column_stockholder_name($item) {
        $name = esc_html($item->stockholder_name);
        
        // Build row actions
        $actions = array();
        
        $actions['edit'] = sprintf(
            '<a href="#" class="upra-edit-shareholder" data-id="%d">%s</a>',
            $item->id,
            __('Edit', 'upra-class-action')
        );
        
        $actions['view'] = sprintf(
            '<a href="#" class="upra-view-shareholder" data-id="%d">%s</a>',
            $item->id,
            __('View Details', 'upra-class-action')
        );
        
        $actions['delete'] = sprintf(
            '<a href="#" class="upra-delete-shareholder" data-id="%d" data-nonce="%s">%s</a>',
            $item->id,
            wp_create_nonce('delete_shareholder_' . $item->id),
            __('Delete', 'upra-class-action')
        );

        return sprintf('%s %s', $name, $this->row_actions($actions));
    }

    /**
     * Get row actions HTML
     */
    private function get_row_actions($item) {
        $actions = array();
        
        $actions[] = sprintf(
            '<a href="#" class="button button-small upra-edit-shareholder" data-id="%d">%s</a>',
            $item->id,
            __('Edit', 'upra-class-action')
        );
        
        $actions[] = sprintf(
            '<a href="#" class="button button-small upra-view-shareholder" data-id="%d">%s</a>',
            $item->id,
            __('View', 'upra-class-action')
        );
        
        $actions[] = sprintf(
            '<a href="mailto:%s" class="button button-small">%s</a>',
            esc_attr($item->email),
            __('Email', 'upra-class-action')
        );
        
        $actions[] = sprintf(
            '<a href="#" class="button button-small button-link-delete upra-delete-shareholder" data-id="%d" data-nonce="%s">%s</a>',
            $item->id,
            wp_create_nonce('delete_shareholder_' . $item->id),
            __('Delete', 'upra-class-action')
        );

        return implode(' ', $actions);
    }

    /**
     * Prepare table items
     */
    public function prepare_items() {
        // Handle bulk actions
        $this->process_bulk_action();

        // Get sorting parameters
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'id';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'desc';
        
        // Get search term
        $search_term = isset($_POST['s']) ? sanitize_text_field($_POST['s']) : '';
        if (empty($search_term) && isset($_GET['s'])) {
            $search_term = sanitize_text_field($_GET['s']);
        }

        // Get current page
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $this->per_page;

        // Get data
        $args = array(
            'company' => $this->company,
            'orderby' => $orderby,
            'order' => strtoupper($order),
            'search' => $search_term,
            'limit' => $this->per_page,
            'offset' => $offset
        );

        $this->items = $this->database->get_shareholders_data($args);

        // Get total items for pagination
        $total_items = $this->database->get_shareholders_total_count($this->company, $search_term);

        // Set pagination
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $this->per_page,
            'total_pages' => ceil($total_items / $this->per_page)
        ));

        // Set column headers
        $this->_column_headers = array(
            $this->get_columns(),
            $this->get_hidden_columns(),
            $this->get_sortable_columns()
        );
    }

    /**
     * Process bulk actions
     */
    protected function process_bulk_action() {
        $action = $this->current_action();
        
        if (!$action) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'bulk-shareholders')) {
            wp_die(__('Security check failed', 'upra-class-action'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action', 'upra-class-action'));
        }

        $shareholder_ids = isset($_POST['shareholder']) ? array_map('absint', $_POST['shareholder']) : array();
        
        if (empty($shareholder_ids)) {
            $this->add_notice(__('No shareholders selected.', 'upra-class-action'), 'error');
            return;
        }

        switch ($action) {
            case 'delete':
                $this->bulk_delete($shareholder_ids);
                break;
                
            case 'export':
                $this->bulk_export($shareholder_ids);
                break;
                
            case 'send_email':
                $this->bulk_email_redirect($shareholder_ids);
                break;
        }
    }

    /**
     * Bulk delete shareholders
     */
    private function bulk_delete($shareholder_ids) {
        $deleted_count = 0;
        
        foreach ($shareholder_ids as $id) {
            if ($this->database->delete_shareholder($id, $this->company)) {
                $deleted_count++;
            }
        }

        if ($deleted_count > 0) {
            $this->add_notice(
                sprintf(__('%d shareholders deleted successfully.', 'upra-class-action'), $deleted_count),
                'success'
            );
        } else {
            $this->add_notice(__('No shareholders were deleted.', 'upra-class-action'), 'error');
        }
    }

    /**
     * Bulk export shareholders
     */
    private function bulk_export($shareholder_ids) {
        // Get selected shareholders data
        $shareholders = array();
        foreach ($shareholder_ids as $id) {
            $args = array(
                'company' => $this->company,
                'limit' => 1,
                'offset' => 0
            );
            // This is a simplified approach - in practice, you'd want a more efficient method
            $data = $this->database->get_shareholders_data($args);
            foreach ($data as $shareholder) {
                if ($shareholder->id == $id) {
                    $shareholders[] = $shareholder;
                    break;
                }
            }
        }

        if (empty($shareholders)) {
            $this->add_notice(__('No shareholders found for export.', 'upra-class-action'), 'error');
            return;
        }

        // Export as CSV
        $this->export_shareholders_csv($shareholders);
    }

    /**
     * Export shareholders to CSV
     */
    private function export_shareholders_csv($shareholders) {
        $filename = "upra-{$this->company}-shareholders-selected-" . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        $headers = array(
            'ID', 'Name', 'Email', 'Phone', 'Stock', 
            'Purchase Price', 'Sell Price', 'Loss', 
            'IP', 'Country', 'Remarks', 'Registration Date'
        );
        fputcsv($output, $headers);
        
        // CSV data
        foreach ($shareholders as $shareholder) {
            fputcsv($output, array(
                $shareholder->id,
                $shareholder->stockholder_name,
                $shareholder->email,
                $shareholder->phone,
                $shareholder->stock,
                $shareholder->purchase_price,
                $shareholder->sell_price,
                $shareholder->loss,
                $shareholder->ip,
                $shareholder->country,
                $shareholder->remarks,
                $shareholder->created_at
            ));
        }
        
        fclose($output);
        exit;
    }

    /**
     * Redirect to bulk email form with selected shareholders
     */
    private function bulk_email_redirect($shareholder_ids) {
        $ids_string = implode(',', $shareholder_ids);
        $redirect_url = add_query_arg(array(
            'page' => 'upra-shareholders-tools',
            'company' => $this->company,
            'action' => 'bulk_email',
            'selected_ids' => $ids_string
        ), admin_url('admin.php'));
        
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Display table navigation
     */
    protected function display_tablenav($which) {
        ?>
        <div class="tablenav <?php echo esc_attr($which); ?>">
            <div class="alignleft actions bulkactions">
                <?php $this->bulk_actions($which); ?>
            </div>
            
            <?php if ($which === 'top'): ?>
                <div class="alignright">
                    <div class="upra-table-info">
                        <span class="displaying-num">
                            <?php printf(
                                _n('%s item', '%s items', $this->get_pagination_arg('total_items'), 'upra-class-action'),
                                number_format_i18n($this->get_pagination_arg('total_items'))
                            ); ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php
            $this->extra_tablenav($which);
            $this->pagination($which);
            ?>
            
            <br class="clear" />
        </div>
        <?php
    }

    /**
     * Extra table navigation
     */
    protected function extra_tablenav($which) {
        if ($which === 'top') {
            ?>
            <div class="alignleft actions">
                <!-- Company filter could go here if supporting multiple companies in one view -->
                <?php
                // Add company-specific filters if needed
                $this->company_filters();
                ?>
            </div>
            <?php
        }
    }

    /**
     * Company-specific filters
     */
    private function company_filters() {
        // Add any company-specific filtering options here
        // For example, date range filters, status filters, etc.
        ?>
        <select name="date_filter" id="date-filter">
            <option value=""><?php _e('All dates', 'upra-class-action'); ?></option>
            <option value="today"><?php _e('Today', 'upra-class-action'); ?></option>
            <option value="week"><?php _e('This week', 'upra-class-action'); ?></option>
            <option value="month"><?php _e('This month', 'upra-class-action'); ?></option>
        </select>
        <?php
        submit_button(__('Filter', 'upra-class-action'), '', 'filter_action', false, array('id' => 'post-query-submit'));
    }

    /**
     * Add admin notice
     */
    private function add_notice($message, $type = 'success') {
        add_action('admin_notices', function() use ($message, $type) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        });
    }

    /**
     * No items found message
     */
    public function no_items() {
        printf(
            __('No %s shareholders found.', 'upra-class-action'),
            strtoupper($this->company)
        );
    }

    /**
     * Generate search box
     */
    public function search_box($text, $input_id) {
        if (empty($_REQUEST['s']) && !$this->has_items()) {
            return;
        }

        $input_id = $input_id . '-search-input';
        
        if (!empty($_REQUEST['orderby'])) {
            echo '<input type="hidden" name="orderby" value="' . esc_attr($_REQUEST['orderby']) . '" />';
        }
        if (!empty($_REQUEST['order'])) {
            echo '<input type="hidden" name="order" value="' . esc_attr($_REQUEST['order']) . '" />';
        }
        ?>
        <p class="search-box">
            <label class="screen-reader-text" for="<?php echo esc_attr($input_id); ?>"><?php echo $text; ?>:</label>
            <input type="search" id="<?php echo esc_attr($input_id); ?>" name="s" value="<?php _admin_search_query(); ?>" placeholder="<?php esc_attr_e('Search by name or email...', 'upra-class-action'); ?>" />
            <?php submit_button($text, 'button', '', false, array('id' => 'search-submit')); ?>
        </p>
        <?php
    }

    /**
     * Get the current company
     */
    public function get_company() {
        return $this->company;
    }

    /**
     * Display the table with proper styling
     */
    public function display() {
        $singular = $this->_args['singular'];

        $this->display_tablenav('top');

        $this->screen->render_screen_reader_content('heading_list');
        ?>
        <table class="wp-list-table <?php echo implode(' ', $this->get_table_classes()); ?>">
            <thead>
            <tr>
                <?php $this->print_column_headers(); ?>
            </tr>
            </thead>

            <tbody id="the-list"<?php
            if ($singular) {
                echo " data-wp-lists='list:$singular'";
            } ?>>
                <?php $this->display_rows_or_placeholder(); ?>
            </tbody>

            <tfoot>
            <tr>
                <?php $this->print_column_headers(false); ?>
            </tr>
            </tfoot>
        </table>
        <?php
        $this->display_tablenav('bottom');
    }
}