<?php
/**
 * Uninstall Script for UPRA Class Action Plugin
 * 
 * This file is executed when the plugin is deleted from WordPress admin.
 * It cleans up all plugin data, options, and database tables.
 * 
 * @package UPRA_Class_Action
 * @version 1.0.0
 */

//define('UPRA_BACKUP_ON_UNINSTALL', true);

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Include WordPress functions if needed
if (!function_exists('get_option')) {
    require_once(ABSPATH . 'wp-config.php');
}

/**
 * Main uninstall class
 */
class UPRA_Class_Action_Uninstaller {

    /**
     * Run the uninstallation process
     */
    public static function uninstall() {
        // Check if user has permission to delete plugins
        if (!current_user_can('delete_plugins')) {
            return;
        }

        // Get plugin options to check what needs to be cleaned
        $options = get_option('upra_class_action_options', array());
        
        // Show confirmation in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('UPRA Class Action Plugin: Starting uninstallation process');
        }

        // Clean up database tables
        self::drop_database_tables();
        
        // Clean up plugin options
        self::delete_plugin_options();
        
        // Clean up user meta
        self::delete_user_meta();
        
        // Clean up transients
        self::delete_transients();
        
        // Clean up scheduled events
        self::clear_scheduled_events();
        
        // Clean up uploaded files (if any)
        self::clean_uploaded_files();
        
        // Final cleanup
        self::final_cleanup();
        
        // Log completion
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('UPRA Class Action Plugin: Uninstallation completed successfully');
        }
    }

    /**
     * Drop all plugin database tables
     */
    private static function drop_database_tables() {
        global $wpdb;

        // List of tables to drop
        $tables = array(
            $wpdb->prefix . 'upra_shareholders_data'
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("UPRA Class Action Plugin: Dropped table {$table}");
            }
        }

        // Also drop any legacy tables that might exist
        $legacy_tables = array(
            $wpdb->prefix . 'atos_stock_data'
        );

        foreach ($legacy_tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("UPRA Class Action Plugin: Dropped legacy table {$table}");
            }
        }
    }

    /**
     * Delete all plugin options
     */
    private static function delete_plugin_options() {
        // Main plugin options
        $options_to_delete = array(
            'upra_class_action_options',
            'upra_class_action_version',
            'upra_class_action_db_version',
            'upra_class_action_activation_time',
            'upra_class_action_last_cleanup',
        );

        foreach ($options_to_delete as $option) {
            delete_option($option);
            delete_site_option($option); // For multisite
        }

        // Delete any options that start with our prefix
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE 'upra_class_action_%' 
             OR option_name LIKE 'upra_%'"
        );

        // For multisite
        if (is_multisite()) {
            $wpdb->query(
                "DELETE FROM {$wpdb->sitemeta} 
                 WHERE meta_key LIKE 'upra_class_action_%' 
                 OR meta_key LIKE 'upra_%'"
            );
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('UPRA Class Action Plugin: Deleted plugin options');
        }
    }

    /**
     * Delete user meta data related to the plugin
     */
    private static function delete_user_meta() {
        global $wpdb;

        // Delete user meta for screen options, preferences, etc.
        $meta_keys_to_delete = array(
            'shareholders_per_page',
            'upra_admin_notices_dismissed',
            'upra_dashboard_widget_options',
            'upra_user_preferences'
        );

        foreach ($meta_keys_to_delete as $meta_key) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
                    $meta_key
                )
            );
        }

        // Delete any meta keys that start with our prefix
        $wpdb->query(
            "DELETE FROM {$wpdb->usermeta} 
             WHERE meta_key LIKE 'upra_%'"
        );

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('UPRA Class Action Plugin: Deleted user meta data');
        }
    }

    /**
     * Delete transients created by the plugin
     */
    private static function delete_transients() {
        global $wpdb;

        // Delete specific transients
        $transients_to_delete = array(
            'upra_company_stats_atos',
            'upra_company_stats_urpea',
            'upra_shareholder_counts',
            'upra_email_queue',
            'upra_export_data'
        );

        foreach ($transients_to_delete as $transient) {
            delete_transient($transient);
            delete_site_transient($transient);
        }

        // Delete all transients that start with our prefix
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_upra_%' 
             OR option_name LIKE '_transient_timeout_upra_%'"
        );

        // For multisite
        if (is_multisite()) {
            $wpdb->query(
                "DELETE FROM {$wpdb->sitemeta} 
                 WHERE meta_key LIKE '_site_transient_upra_%' 
                 OR meta_key LIKE '_site_transient_timeout_upra_%'"
            );
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('UPRA Class Action Plugin: Deleted transients');
        }
    }

    /**
     * Clear scheduled events/cron jobs
     */
    private static function clear_scheduled_events() {
        // List of scheduled events to clear
        $scheduled_events = array(
            'upra_cleanup_old_data',
            'upra_send_scheduled_emails',
            'upra_update_statistics',
            'upra_database_maintenance'
        );

        foreach ($scheduled_events as $event) {
            wp_clear_scheduled_hook($event);
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('UPRA Class Action Plugin: Cleared scheduled events');
        }
    }

    /**
     * Clean up uploaded files (if any)
     */
    private static function clean_uploaded_files() {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/upra-class-action/';

        if (is_dir($plugin_upload_dir)) {
            self::recursive_delete($plugin_upload_dir);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('UPRA Class Action Plugin: Cleaned upload directory');
            }
        }

        // Clean up any temporary export files
        $temp_files = glob($upload_dir['basedir'] . '/upra-*');
        foreach ($temp_files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Recursively delete directory and its contents
     */
    private static function recursive_delete($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                self::recursive_delete($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }

    /**
     * Final cleanup and optimization
     */
    private static function final_cleanup() {
        // Clean up any remaining database references
        global $wpdb;

        // Remove any orphaned metadata
        $wpdb->query(
            "DELETE pm FROM {$wpdb->postmeta} pm
             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.ID IS NULL 
             AND pm.meta_key LIKE 'upra_%'"
        );

        // Remove any orphaned term metadata
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->termmeta}'")) {
            $wpdb->query(
                "DELETE tm FROM {$wpdb->termmeta} tm
                 LEFT JOIN {$wpdb->terms} t ON tm.term_id = t.term_id
                 WHERE t.term_id IS NULL 
                 AND tm.meta_key LIKE 'upra_%'"
            );
        }

        // Clear any cached data
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        // Clear object cache if available
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('upra_class_action');
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('UPRA Class Action Plugin: Final cleanup completed');
        }
    }

    /**
     * Backup data before deletion (optional safety measure)
     */
    private static function backup_data() {
        // This is an optional safety measure
        // Only create backup if specifically requested via constant
        if (!defined('UPRA_BACKUP_ON_UNINSTALL') || !UPRA_BACKUP_ON_UNINSTALL) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'upra_shareholders_data';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
            $upload_dir = wp_upload_dir();
            $backup_file = $upload_dir['basedir'] . '/upra-backup-' . date('Y-m-d-H-i-s') . '.sql';
            
            // Simple backup - export table structure and data
            $sql = "-- UPRA Class Action Plugin Backup\n";
            $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
            
            // Get table structure
            $create_table = $wpdb->get_row("SHOW CREATE TABLE {$table}", ARRAY_N);
            if ($create_table) {
                $sql .= $create_table[1] . ";\n\n";
            }
            
            // Get table data
            $rows = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);
            foreach ($rows as $row) {
                $values = array_map(function($value) use ($wpdb) {
                    return $wpdb->prepare('%s', $value);
                }, array_values($row));
                
                $sql .= "INSERT INTO {$table} VALUES (" . implode(', ', $values) . ");\n";
            }
            
            file_put_contents($backup_file, $sql);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("UPRA Class Action Plugin: Backup created at {$backup_file}");
            }
        }
    }

    /**
     * Get confirmation from user (for manual uninstall)
     */
    private static function confirm_uninstall() {
        // This method can be used if you want to add an admin interface
        // for controlled uninstallation with user confirmation
        
        $options = get_option('upra_class_action_options', array());
        
        // Check if there's important data
        global $wpdb;
        $table = $wpdb->prefix . 'upra_shareholders_data';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        
        if ($count > 0) {
            // There's data - you might want to show a warning
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("UPRA Class Action Plugin: Warning - Uninstalling with {$count} shareholder records");
            }
        }
        
        return true; // Proceed with uninstall
    }
}

// Check if we should create a backup before uninstalling
if (defined('UPRA_BACKUP_ON_UNINSTALL') && UPRA_BACKUP_ON_UNINSTALL) {
    UPRA_Class_Action_Uninstaller::backup_data();
}

// Run the uninstaller
UPRA_Class_Action_Uninstaller::uninstall();

// Clear any remaining PHP opcache
if (function_exists('opcache_reset')) {
    opcache_reset();
}