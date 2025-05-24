<?php
/**
 * Email Handler for UPRA Class Action Plugin
 * 
 * Handles all email communications including confirmation emails
 * Supports multiple companies with customizable email templates
 */

if (!defined('ABSPATH')) {
    exit;
}

class UPRA_Class_Action_Email_Handler {

    /**
     * Single instance of the class
     */
    private static $instance = null;

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
        $this->options = get_option('upra_class_action_options', array());
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Filter email content type to HTML
        add_filter('wp_mail_content_type', array($this, 'set_html_mail_content_type'));
        
        // Filter email from address and name
        add_filter('wp_mail_from', array($this, 'custom_wp_mail_from'));
        add_filter('wp_mail_from_name', array($this, 'custom_wp_mail_from_name'));
    }

    /**
     * Send confirmation email to shareholder
     */
    public function send_confirmation_email($email, $company = 'atos') {
        // Check if email notifications are enabled
        if (!$this->is_email_enabled()) {
            return false;
        }

        $to = $email;
        $subject = $this->get_email_subject($company);
        $message = $this->get_email_content($company);
        $headers = $this->get_email_headers();

        $result = wp_mail($to, $subject, $message, $headers);

        // Log email sending result
        $this->log_email_result($to, $subject, $result, $company);

        return $result;
    }

    /**
     * Send admin notification email
     */
    public function send_admin_notification($shareholder_data) {
        // Check if admin notifications are enabled
        if (!$this->is_admin_notification_enabled()) {
            return false;
        }

        $admin_email = get_option('admin_email');
        $company = $shareholder_data['company'];
        
        $subject = sprintf(
            __('New %s Shareholder Registration - %s', 'upra-class-action'),
            strtoupper($company),
            $shareholder_data['stockholder_name']
        );

        $message = $this->get_admin_notification_content($shareholder_data);
        $headers = $this->get_email_headers();

        return wp_mail($admin_email, $subject, $message, $headers);
    }

    /**
     * Get email subject based on company
     */
    private function get_email_subject($company) {
        $subjects = array(
            'atos' => __('UPRA Registration - ATOS', 'upra-class-action'),
            'urpea' => __('UPRA Registration - URPEA', 'upra-class-action')
        );

        return $subjects[$company] ?? $subjects['atos'];
    }

    /**
     * Get email content based on company
     */
    private function get_email_content($company) {
        switch ($company) {
            case 'atos':
                return $this->get_atos_email_template();
            case 'urpea':
                return $this->get_urpea_email_template();
            default:
                return $this->get_default_email_template($company);
        }
    }

    /**
     * Get ATOS email template
     */
    private function get_atos_email_template() {
        ob_start();
        ?>
        <html>
        <head>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    color: #333;
                    background-color: #f4f4f4;
                    margin: 0;
                    padding: 20px;
                }
                .container {
                    background-color: #ffffff;
                    padding: 30px;
                    border-radius: 8px;
                    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                    max-width: 600px;
                    margin: 0 auto;
                }
                h1 {
                    color: #2a8bba;
                    font-size: 24px;
                    margin-bottom: 20px;
                }
                p {
                    font-size: 16px;
                    line-height: 1.6;
                    margin-bottom: 15px;
                }
                .highlight {
                    color: #2a8bba;
                    font-weight: bold;
                }
                .footer {
                    font-size: 14px;
                    margin-top: 30px;
                    color: #888;
                    border-top: 1px solid #eee;
                    padding-top: 20px;
                }
                .footer a {
                    color: #2a8bba;
                    text-decoration: none;
                }
                .logo {
                    text-align: center;
                    margin-bottom: 30px;
                }
                .contact-info {
                    background-color: #f8f9fa;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 20px 0;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="logo">
                    <h1><?php _e('UPRA - Union des Porteurs et Représentants d\'Actions', 'upra-class-action'); ?></h1>
                </div>
                
                <h1><?php _e('Merci pour votre pré-inscription à l\'UPRA - ATOS', 'upra-class-action'); ?></h1>
                
                <p><?php _e('L\'UPRA vous remercie de votre pré-inscription concernant les actions ATOS. Celle-ci a bien été prise en compte.', 'upra-class-action'); ?></p>
                
                <p><?php _e('Nous reviendrons vers vous d\'ici mi-avril lorsque la plateforme applicative de gestion de procès de groupe aura été mise en place. Cette plateforme sécurisée répondra aux normes les plus strictes de respect de la vie privée.', 'upra-class-action'); ?></p>
                
                <p><?php _e('Vous recevrez également mi-avril un email vous indiquant la liste des pièces à préparer pour constituer votre dossier.', 'upra-class-action'); ?></p>
                
                <div class="contact-info">
                    <p><strong><?php _e('Besoin d\'aide ou d\'informations ?', 'upra-class-action'); ?></strong></p>
                    <p><?php _e('Si vous avez une question d\'ici là, une seule adresse :', 'upra-class-action'); ?> 
                    <span class="highlight">info@upra.fr</span></p>
                </div>
                
                <div class="footer">
                    <p><?php _e('Cordialement,', 'upra-class-action'); ?><br>
                    <?php _e('L\'équipe de l\'UPRA', 'upra-class-action'); ?></p>
                    
                    <p><small><?php _e('Cet email a été envoyé automatiquement suite à votre inscription. Merci de ne pas y répondre directement.', 'upra-class-action'); ?></small></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get URPEA email template
     */
    private function get_urpea_email_template() {
        ob_start();
        ?>
        <html>
        <head>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    color: #333;
                    background-color: #f4f4f4;
                    margin: 0;
                    padding: 20px;
                }
                .container {
                    background-color: #ffffff;
                    padding: 30px;
                    border-radius: 8px;
                    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                    max-width: 600px;
                    margin: 0 auto;
                }
                h1 {
                    color: #d63031;
                    font-size: 24px;
                    margin-bottom: 20px;
                }
                p {
                    font-size: 16px;
                    line-height: 1.6;
                    margin-bottom: 15px;
                }
                .highlight {
                    color: #d63031;
                    font-weight: bold;
                }
                .footer {
                    font-size: 14px;
                    margin-top: 30px;
                    color: #888;
                    border-top: 1px solid #eee;
                    padding-top: 20px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1><?php _e('Thank you for your URPEA registration', 'upra-class-action'); ?></h1>
                
                <p><?php _e('UPRA thanks you for your pre-registration regarding URPEA shares. Your registration has been successfully recorded.', 'upra-class-action'); ?></p>
                
                <p><?php _e('We will contact you when our class action management platform is ready. This secure platform will meet the strictest privacy standards.', 'upra-class-action'); ?></p>
                
                <p><?php _e('If you have any questions, please contact us at:', 'upra-class-action'); ?> 
                <span class="highlight">info@upra.fr</span></p>
                
                <div class="footer">
                    <p><?php _e('Best regards,', 'upra-class-action'); ?><br>
                    <?php _e('The UPRA Team', 'upra-class-action'); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get default email template for new companies
     */
    private function get_default_email_template($company) {
        ob_start();
        ?>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; color: #333; background-color: #f4f4f4; margin: 0; padding: 20px; }
                .container { background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); max-width: 600px; margin: 0 auto; }
                h1 { color: #2a8bba; font-size: 24px; margin-bottom: 20px; }
                p { font-size: 16px; line-height: 1.6; margin-bottom: 15px; }
                .highlight { color: #2a8bba; font-weight: bold; }
                .footer { font-size: 14px; margin-top: 30px; color: #888; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1><?php printf(__('Thank you for your %s registration', 'upra-class-action'), strtoupper($company)); ?></h1>
                
                <p><?php printf(__('UPRA thanks you for your pre-registration regarding %s shares. Your registration has been successfully recorded.', 'upra-class-action'), strtoupper($company)); ?></p>
                
                <p><?php _e('We will contact you when our class action management platform is ready.', 'upra-class-action'); ?></p>
                
                <p><?php _e('If you have any questions, please contact us at:', 'upra-class-action'); ?> 
                <span class="highlight">info@upra.fr</span></p>
                
                <div class="footer">
                    <p><?php _e('Best regards,', 'upra-class-action'); ?><br>
                    <?php _e('The UPRA Team', 'upra-class-action'); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get admin notification email content
     */
    private function get_admin_notification_content($shareholder_data) {
        ob_start();
        ?>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; color: #333; }
                .data-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .data-table th, .data-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                .data-table th { background-color: #f2f2f2; }
            </style>
        </head>
        <body>
            <h2><?php printf(__('New %s Shareholder Registration', 'upra-class-action'), strtoupper($shareholder_data['company'])); ?></h2>
            
            <table class="data-table">
                <tr><th><?php _e('Name', 'upra-class-action'); ?></th><td><?php echo esc_html($shareholder_data['stockholder_name']); ?></td></tr>
                <tr><th><?php _e('Email', 'upra-class-action'); ?></th><td><?php echo esc_html($shareholder_data['email']); ?></td></tr>
                <tr><th><?php _e('Phone', 'upra-class-action'); ?></th><td><?php echo esc_html($shareholder_data['phone']); ?></td></tr>
                <tr><th><?php _e('Stock Count', 'upra-class-action'); ?></th><td><?php echo number_format($shareholder_data['stock']); ?></td></tr>
                <tr><th><?php _e('Purchase Price', 'upra-class-action'); ?></th><td><?php echo number_format($shareholder_data['purchase_price'], 2); ?></td></tr>
                <tr><th><?php _e('Sell Price', 'upra-class-action'); ?></th><td><?php echo number_format($shareholder_data['sell_price'], 2); ?></td></tr>
                <tr><th><?php _e('Loss', 'upra-class-action'); ?></th><td><?php echo number_format($shareholder_data['loss'], 2); ?></td></tr>
                <tr><th><?php _e('IP Address', 'upra-class-action'); ?></th><td><?php echo esc_html($shareholder_data['ip']); ?></td></tr>
                <tr><th><?php _e('Country', 'upra-class-action'); ?></th><td><?php echo esc_html($shareholder_data['country']); ?></td></tr>
                <?php if (!empty($shareholder_data['remarks'])): ?>
                <tr><th><?php _e('Remarks', 'upra-class-action'); ?></th><td><?php echo nl2br(esc_html($shareholder_data['remarks'])); ?></td></tr>
                <?php endif; ?>
            </table>
            
            <p><?php _e('Registration time:', 'upra-class-action'); ?> <?php echo current_time('mysql'); ?></p>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get email headers
     */
    private function get_email_headers() {
        return array(
            'Content-Type: text/html; charset=UTF-8',
            'From: UPRA <no-reply@upra.fr>',
            'Reply-To: info@upra.fr'
        );
    }

    /**
     * Set email content type to HTML
     */
    public function set_html_mail_content_type() {
        return 'text/html';
    }

    /**
     * Custom from email address
     */
    public function custom_wp_mail_from($original_email_address) {
        return 'no-reply@upra.fr';
    }

    /**
     * Custom from name
     */
    public function custom_wp_mail_from_name($original_email_from) {
        return 'UPRA';
    }

    /**
     * Check if email notifications are enabled
     */
    private function is_email_enabled() {
        return isset($this->options['email_notifications']) ? $this->options['email_notifications'] : true;
    }

    /**
     * Check if admin notifications are enabled
     */
    private function is_admin_notification_enabled() {
        return isset($this->options['admin_notifications']) ? $this->options['admin_notifications'] : false;
    }

    /**
     * Log email sending result
     */
    private function log_email_result($to, $subject, $result, $company) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'UPRA Email Log - To: %s, Subject: %s, Company: %s, Result: %s',
                $to,
                $subject,
                $company,
                $result ? 'SUCCESS' : 'FAILED'
            ));
        }
    }

    /**
     * Send bulk email to shareholders
     */
    public function send_bulk_email($company, $subject, $message, $shareholder_ids = array()) {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $database = UPRA_Class_Action_Database::get_instance();
        
        // Get shareholders based on criteria
        if (empty($shareholder_ids)) {
            $shareholders = $database->get_shareholders_data(array(
                'company' => $company,
                'limit' => 999999
            ));
        } else {
            // Get specific shareholders by IDs
            $shareholders = array(); // Implementation depends on requirements
        }

        $headers = $this->get_email_headers();
        $sent_count = 0;

        foreach ($shareholders as $shareholder) {
            if (wp_mail($shareholder->email, $subject, $message, $headers)) {
                $sent_count++;
            }
        }

        return $sent_count;
    }
}