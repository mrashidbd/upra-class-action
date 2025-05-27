<?php
/**
 * URPEA Company Handler for UPRA Class Action Plugin
 * 
 * Handles URPEA-specific functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class UPRA_Class_Action_URPEA {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Company identifier
     */
    const COMPANY_ID = 'urpea';

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
        // URPEA-specific hooks can be added here
    }

    /**
     * Get total URPEA shares
     */
    public function get_total_shares() {
        return $this->database->get_total_shares(self::COMPANY_ID);
    }

    /**
     * Get total URPEA shareholders count
     */
    public function get_total_people() {
        return $this->database->get_shareholders_count(self::COMPANY_ID);
    }

    /**
     * Get total URPEA participation value
     */
    public function get_total_participation() {
        return $this->database->get_total_participation(self::COMPANY_ID);
    }

    /**
     * Get URPEA-specific form configuration
     */
    public function get_form_config() {
        return array(
            'company' => self::COMPANY_ID,
            'title' => __('URPEA Shareholder Registration', 'upra-class-action'),
            'description' => __('Please provide your URPEA shareholding information for the class action lawsuit.', 'upra-class-action'),
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
                    'label' => __('Number of Shares Held', 'upra-class-action'),
                    'placeholder' => __('Number of shares held', 'upra-class-action'),
                    'required' => false,
                    'type' => 'number'
                ),
                'purchase' => array(
                    'label' => __('Purchase Price', 'upra-class-action'),
                    'placeholder' => __('Purchase price per share', 'upra-class-action'),
                    'required' => false,
                    'type' => 'number'
                ),
                'sell' => array(
                    'label' => __('Sell Price', 'upra-class-action'),
                    'placeholder' => __('Sell price per share', 'upra-class-action'),
                    'required' => false,
                    'type' => 'number'
                ),
                'loss' => array(
                    'label' => __('Total Loss', 'upra-class-action'),
                    'placeholder' => __('Total financial loss', 'upra-class-action'),
                    'required' => false,
                    'type' => 'number'
                ),
                'remarks' => array(
                    'label' => __('Remarks', 'upra-class-action'),
                    'placeholder' => __('Additional comments', 'upra-class-action'),
                    'required' => false,
                    'type' => 'textarea'
                )
            ),
            'submit_text' => __('Submit Registration', 'upra-class-action'),
            'success_message' => __('Your URPEA data has been successfully recorded! <br> Please refresh the page to see the updated totals.', 'upra-class-action')
        );
    }

    /**
     * Get URPEA statistics for display
     */
    public function get_statistics() {
        return array(
            'total_shares' => $this->get_total_shares(),
            'total_people' => $this->get_total_people(),
            'total_participation' => $this->get_total_participation(),
            'company' => self::COMPANY_ID,
            'company_name' => 'URPEA'
        );
    }

    /**
     * Export URPEA data (admin function)
     */
    public function export_data($format = 'csv') {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $data = $this->database->get_shareholders_data(array(
            'company' => self::COMPANY_ID,
            'limit' => 999999
        ));

        switch ($format) {
            case 'csv':
                return $this->export_to_csv($data);
            case 'excel':
                return $this->export_to_excel($data);
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

        $filename = 'urpea-shareholders-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        $headers = array(
            'ID', 'Name', 'Email', 'Phone', 'Stock', 
            'Purchase Price', 'Sell Price', 'Loss', 
            'IP', 'Country', 'Remarks', 'Created Date'
        );
        fputcsv($output, $headers);
        
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
     * Export data to Excel format (placeholder)
     */
    private function export_to_excel($data) {
        // For now, fallback to CSV
        return $this->export_to_csv($data);
    }
}