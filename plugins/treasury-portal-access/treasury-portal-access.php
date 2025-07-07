<?php
/**
 * Plugin Name: Treasury Portal Access
 * Plugin URI: https://realtreasury.com
 * Description: Complete portal access control system with Contact Form 7 integration, 6-month persistence, and localStorage backup.
 * Version: 1.0.6
 * Author: Real Treasury
 * Author URI: https://realtreasury.com
 * License: GPL v2 or later
 * Text Domain: treasury-portal-access
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TPA_VERSION', '1.0.6');
define('TPA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TPA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TPA_PLUGIN_FILE', __FILE__);

/**
 * Main Treasury Portal Access Class
 */
final class Treasury_Portal_Access {
    
    private static $instance = null;
    private $table_name;
    private $pending_emails = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'portal_access_users';
        
        // Register hooks
        add_action('init', array($this, 'init'));
        add_action('wp_footer', array($this, 'add_frontend_scripts'));
        
        // Activation/deactivation
        register_activation_hook(TPA_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(TPA_PLUGIN_FILE, array($this, 'deactivate'));
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'register_settings'));
            add_action('admin_notices', array($this, 'admin_notices'));
        }
        
        // Core functionality hooks
        add_action('wpcf7_mail_sent', array($this, 'handle_form_submission'));
        add_action('wp_ajax_restore_portal_access', array($this, 'restore_access_ajax'));
        add_action('wp_ajax_nopriv_restore_portal_access', array($this, 'restore_access_ajax'));
        add_action('wp_ajax_revoke_portal_access', array($this, 'revoke_access_ajax'));
        add_action('wp_ajax_nopriv_revoke_portal_access', array($this, 'revoke_access_ajax'));
        add_action('wp_ajax_get_current_user_access', array($this, 'get_user_access_ajax'));
        add_action('wp_ajax_nopriv_get_current_user_access', array($this, 'get_user_access_ajax'));

        add_action('tpa_send_welcome_email', array($this, 'send_welcome_email'));
        add_action('shutdown', array($this, 'process_pending_emails'));
        
        // Shortcodes
        add_shortcode('protected_content', array($this, 'protected_content_shortcode'));
        add_shortcode('portal_button', array($this, 'portal_button_shortcode'));
        add_shortcode('protected_videos', array($this, 'protected_content_shortcode')); // Backward compatibility
        add_shortcode('video_button', array($this, 'portal_button_shortcode')); // Backward compatibility
    }
    
    public function init() {
        load_plugin_textdomain('treasury-portal-access', false, dirname(plugin_basename(TPA_PLUGIN_FILE)) . '/languages');
        if (!class_exists('WPCF7')) {
            add_action('admin_notices', array($this, 'cf7_missing_notice'));
        }
    }
    
    public function activate() {
        $this->create_database_table();
        $this->set_default_options();
        flush_rewrite_rules();
        update_option('tpa_activated', true);
        error_log('✅ Treasury Portal Access Plugin activated');
    }
    
    public function deactivate() {
        error_log('🔄 Treasury Portal Access Plugin deactivated');
    }
    
    private function create_database_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            first_name varchar(50) NOT NULL,
            last_name varchar(50) NOT NULL,
            full_name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            company varchar(100) DEFAULT '' NOT NULL,
            terms_agreement varchar(3) DEFAULT 'no' NOT NULL,
            access_granted datetime NOT NULL,
            ip_address varchar(45) DEFAULT '' NOT NULL,
            user_agent text,
            access_token varchar(255) NOT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY access_token (access_token)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        update_option('tpa_db_version', TPA_VERSION);
    }
    
    private function set_default_options() {
        $defaults = array(
            'tpa_form_id' => '', // Default to empty to force admin choice
            'tpa_access_duration' => 180,
            'tpa_redirect_url' => home_url('/treasury-tech-portal/'),
            'tpa_enable_localStorage' => 1,
            'tpa_enable_email_notifications' => 1
        );
        foreach ($defaults as $key => $value) {
            if (false === get_option($key)) {
                update_option($key, $value);
            }
        }
    }

    public function register_settings() {
        register_setting('tpa_settings_group', 'tpa_form_id', 'sanitize_text_field');
        register_setting('tpa_settings_group', 'tpa_access_duration', 'absint');
        register_setting('tpa_settings_group', 'tpa_redirect_url', 'esc_url_raw');
        register_setting('tpa_settings_group', 'tpa_enable_localStorage', 'absint');
        register_setting('tpa_settings_group', 'tpa_enable_email_notifications', 'absint');
    }
    
    public function handle_form_submission($contact_form) {
        $selected_form_id = get_option('tpa_form_id');
        if (empty($selected_form_id) || $contact_form->id() != $selected_form_id) {
            return;
        }
        
        $submission = WPCF7_Submission::get_instance();
        if (!$submission) {
            return;
        }
        
        $posted_data = $submission->get_posted_data();
        $email = sanitize_email($posted_data['email-address'] ?? '');
        $first_name = sanitize_text_field($posted_data['first-name'] ?? '');
        
        if (empty($email) || empty($first_name)) {
            error_log('❌ TPA Error: Form submission missing required fields (Email or First Name).');
            return;
        }
        
        $user_data = [
            'first_name'      => $first_name,
            'last_name'       => sanitize_text_field($posted_data['last-name'] ?? ''),
            'email'           => $email,
            'company'         => sanitize_text_field($posted_data['company'] ?? ''),
            'terms_agreement' => isset($posted_data['terms-agreement']) ? 'yes' : 'no',
            'ip_address'      => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent'      => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        $user_data['full_name'] = trim($user_data['first_name'] . ' ' . $user_data['last_name']);
        
        $this->grant_portal_access_fast($user_data);
    }
    
    private function grant_portal_access($user_data) {
        $access_token = $this->generate_access_token($user_data['email']);
        
        if ($this->store_user_data($user_data, $access_token)) {
            $duration = (int) get_option('tpa_access_duration', 180) * DAY_IN_SECONDS;
            $this->set_access_cookies($access_token, $user_data['email'], $duration);
            
            if (get_option('tpa_enable_email_notifications', true)) {
                $this->send_welcome_email($user_data);
            }
            error_log("✅ TPA: Access granted to {$user_data['email']} for {$duration} seconds.");
        }
    }

    private function grant_portal_access_fast($user_data) {
        $access_token = $this->generate_access_token($user_data['email']);

        if ($this->store_user_data($user_data, $access_token)) {
            $duration = (int) get_option('tpa_access_duration', 180) * DAY_IN_SECONDS;
            $this->set_access_cookies($access_token, $user_data['email'], $duration);

            if (get_option('tpa_enable_email_notifications', true)) {
                $this->schedule_welcome_email($user_data);
                $this->pending_emails[] = $user_data;
            }

            error_log("✅ TPA Fast: Access granted to {$user_data['email']} for {$duration} seconds.");
        }
    }
    
    private function store_user_data($user_data, $access_token) {
        global $wpdb;
        
        $data = [
            'first_name'      => $user_data['first_name'],
            'last_name'       => $user_data['last_name'],
            'full_name'       => $user_data['full_name'],
            'company'         => $user_data['company'],
            'terms_agreement' => $user_data['terms_agreement'],
            'ip_address'      => $user_data['ip_address'],
            'user_agent'      => $user_data['user_agent'],
            'access_token'    => $access_token,
            'access_granted'  => current_time('mysql', 1)
        ];
        
        $existing_user_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table_name} WHERE email = %s", $user_data['email']));
        
        if ($existing_user_id) {
            $result = $wpdb->update($this->table_name, $data, ['id' => $existing_user_id]);
        } else {
            $data['email'] = $user_data['email'];
            $result = $wpdb->insert($this->table_name, $data);
        }
        
        if (false === $result) {
            error_log('❌ TPA DB Error: ' . $wpdb->last_error);
            return false;
        }
        return true;
    }
    
    private function generate_access_token($email) {
        $salt = wp_salt('auth') . $email . time();
        return hash('sha256', $salt);
    }
    
    private function set_access_cookies($access_token, $email, $duration) {
        $expiry = time() + $duration;
        $options = [
            'expires'  => $expiry,
            'path'     => COOKIEPATH,
            'domain'   => COOKIE_DOMAIN,
            'secure'   => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax'
        ];

        // Set the cookie immediately
        setcookie('portal_access_token', $access_token, $options);

        // Also set it in $_COOKIE for immediate availability
        $_COOKIE['portal_access_token'] = $access_token;

        error_log("✅ TPA: Cookie set for {$email}, expires: " . date('Y-m-d H:i:s', $expiry));
    }
    
    public function has_portal_access() {
        if (isset($_COOKIE['portal_access_token'])) {
            return $this->verify_access_token($_COOKIE['portal_access_token']);
        }
        return false;
    }
    
    private function verify_access_token($token) {
        global $wpdb;
        $sanitized_token = sanitize_text_field($token);
        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE access_token = %s", $sanitized_token));
        return !is_null($user);
    }
    
    private function user_exists($email) {
        global $wpdb;
        $user_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table_name} WHERE email = %s", $email));
        return !is_null($user_id);
    }
    
    private function send_welcome_email($user_data) {
        $to = $user_data['email'];
        $subject = 'Your Treasury Portal Access is Ready! 🏛️';
        $redirect_url = get_option('tpa_redirect_url', home_url('/treasury-tech-portal/'));
        $duration_days = get_option('tpa_access_duration', 180);
        
        $message  = "Hi " . esc_html($user_data['first_name']) . ",\n\n";
        $message .= "Thank you for completing our access form. You now have access to our exclusive Treasury Portal.\n\n";
        $message .= "Visit your portal: " . esc_url($redirect_url) . "\n\n";
        $message .= "Your access is valid for {$duration_days} days.\n\n";
        $message .= "Best regards,\n" . get_bloginfo('name');
        
        wp_mail($to, $subject, $message);
    }

    private function schedule_welcome_email($user_data) {
        wp_schedule_single_event(time() + 5, 'tpa_send_welcome_email', array($user_data));
    }

    public function process_pending_emails() {
        foreach ($this->pending_emails as $email_data) {
            $this->send_welcome_email($email_data);
        }
        $this->pending_emails = array();
    }
    
    public function restore_access_ajax() {
        check_ajax_referer('tpa_frontend_nonce', 'nonce');
        $email = sanitize_email($_POST['email'] ?? '');
        
        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Invalid email address.'], 400);
        }
        
        if ($this->user_exists($email)) {
            $access_token = $this->generate_access_token($email);
            $this->update_user_token($email, $access_token);
            $duration = (int) get_option('tpa_access_duration', 180) * DAY_IN_SECONDS;
            $this->set_access_cookies($access_token, $email, $duration);
            
            error_log("✅ TPA: Access restored via localStorage for {$email}");
            wp_send_json_success(['message' => 'Access restored.']);
        } else {
            error_log("❌ TPA: Restore failed - user not found: {$email}");
            wp_send_json_error(['message' => 'User not found.'], 404);
        }
    }
    
    public function revoke_access_ajax() {
        check_ajax_referer('tpa_frontend_nonce', 'nonce');
        $this->clear_access_cookies();
        error_log('🔐 TPA: Portal access revoked by user.');
        wp_send_json_success(['message' => 'Access revoked.']);
    }
    
    public function get_user_access_ajax() {
        check_ajax_referer('tpa_frontend_nonce', 'nonce');
        $token = sanitize_text_field($_COOKIE['portal_access_token'] ?? '');
        if (empty($token)) {
            wp_send_json_error(['message' => 'No access token found.'], 401);
        }
        
        $user = $this->get_user_by_token($token);
        if ($user) {
            wp_send_json_success([
                'token'       => $user->access_token,
                'email'       => $user->email,
                'name'        => $user->full_name,
                'access_time' => strtotime($user->access_granted)
            ]);
        } else {
            wp_send_json_error(['message' => 'Invalid or expired token.'], 403);
        }
    }
    
    private function update_user_token($email, $access_token) {
        global $wpdb;
        $wpdb->update($this->table_name, ['access_token' => $access_token], ['email' => $email], ['%s'], ['%s']);
    }
    
    private function clear_access_cookies() {
        if (isset($_COOKIE['portal_access_token'])) {
            unset($_COOKIE['portal_access_token']);
            setcookie('portal_access_token', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        }
    }
    
    private function get_user_by_token($token) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE access_token = %s", $token));
    }
    
    private function get_current_user_info() {
        $token = sanitize_text_field($_COOKIE['portal_access_token'] ?? '');
        return !empty($token) ? $this->get_user_by_token($token) : null;
    }
    
    private function get_access_time_remaining_string() {
        $user_info = $this->get_current_user_info();
        if (!$user_info) return 'Unknown';
        
        $granted_time = strtotime($user_info->access_granted);
        $duration = (int) get_option('tpa_access_duration', 180) * DAY_IN_SECONDS;
        $expiry_time = $granted_time + $duration;
        
        if (time() > $expiry_time) return 'Expired';
        
        return human_time_diff(time(), $expiry_time) . ' remaining';
    }
    
    public function protected_content_shortcode($atts, $content = null) {
        if (!$this->has_portal_access()) {
            return $this->render_access_required_message();
        }
        
        $atts = shortcode_atts(['content_ids' => ''], $atts, 'protected_content');
        return $this->render_protected_content($atts['content_ids'], $content);
    }
    
    private function render_access_required_message() {
        ob_start();
        ?>
        <div class="tpa-access-required">
            <div class="tpa-access-icon">🏛️</div>
            <h3>Portal Access Required</h3>
            <p>Complete our quick form to unlock exclusive Treasury Portal content.</p>
            <a href="#openPortalModal" class="tpa-btn tpa-btn-primary open-portal-modal">Get Portal Access</a>
            <div class="tpa-returning-user">
                <p><small>Returning user? Your access is automatically restored if available.</small></p>
            </div>
        </div>
        <style>/* Styles included for brevity, same as original */</style>
        <?php
        return ob_get_clean();
    }
    
    private function render_protected_content($content_ids, $enclosed_content) {
        $user_info = $this->get_current_user_info();
        $user_name = $user_info ? esc_html($user_info->first_name) : 'User';
        $duration_days = get_option('tpa_access_duration', 180);
        ob_start();
        ?>
        <div class="tpa-content-container">
            <div class="tpa-content-status">
                <div class="tpa-welcome">
                    <span class="tpa-access-granted">✓ Welcome back, <?php echo $user_name; ?>!</span>
                    <span class="tpa-access-duration"><?php echo esc_html($duration_days); ?>-day portal access active</span>
                </div>
                <div class="tpa-controls">
                    <a href="javascript:void(0);" onclick="window.TPA.revoke()" class="tpa-revoke">Sign Out</a>
                </div>
            </div>
            
            <?php if (!empty($content_ids)): ?>
            <div class="tpa-content-grid">
                <?php
                $items = array_map('trim', explode(',', $content_ids));
                foreach ($items as $item_url) {
                    echo '<div class="tpa-content-item">' . wp_oembed_get(esc_url($item_url)) . '</div>';
                }
                ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($enclosed_content)): ?>
            <div class="tpa-enclosed-content">
                <?php echo do_shortcode($enclosed_content); ?>
            </div>
            <?php endif; ?>
            
            <div class="tpa-access-info">
                <p><small>Your access expires in <?php echo $this->get_access_time_remaining_string(); ?>. Need help? <a href="mailto:hello@realtreasury.com">Contact us</a>.</small></p>
            </div>
        </div>
        <style>/* Styles included for brevity, same as original */</style>
        <?php
        return ob_get_clean();
    }
    
    public function portal_button_shortcode($atts) {
        $atts = shortcode_atts(['text' => 'Access Portal', 'class' => ''], $atts, 'portal_button');
        $classes = 'tpa-btn tpa-btn-primary open-portal-modal tpa-btn-loading ' . esc_attr($atts['class']);
        return '<button class="' . trim($classes) . '">' . esc_html($atts['text']) . '</button>';
    }
    
    /**
     * Loads frontend scripts and modal HTML on every page.
     *
     * This is the most reliable method to ensure the modal is always available
     * when a button is clicked, regardless of where it's placed (content,
     * widget, header, etc.). The included script file has its own check
     * to ensure it doesn't run if the plugin isn't configured.
     */
    public function add_frontend_scripts() {
        include TPA_PLUGIN_DIR . 'includes/frontend-scripts.php';
    }
    
    public function add_admin_menu() {
        add_menu_page('Portal Access', 'Portal Access', 'manage_options', 'treasury-portal-access', [$this, 'admin_page'], 'dashicons-building', 30);
        add_submenu_page('treasury-portal-access', 'Settings', 'Settings', 'manage_options', 'treasury-portal-access-settings', [$this, 'settings_page']);
    }
    
    public function admin_page() {
        require_once TPA_PLUGIN_DIR . 'includes/admin-page.php';
    }
    
    public function settings_page() {
        require_once TPA_PLUGIN_DIR . 'includes/settings-page.php';
    }
    
    public function admin_notices() {
        if (get_option('tpa_activated')) {
            echo '<div class="notice notice-success is-dismissible"><p>✅ Treasury Portal Access Plugin is active!</p></div>';
            delete_option('tpa_activated');
        }
    }
    
    public function cf7_missing_notice() {
        echo '<div class="notice notice-error"><p>⚠️ <strong>Treasury Portal Access:</strong> Contact Form 7 is not installed or active. This plugin depends on it to function.</p></div>';
    }
}

// Initialize the plugin
Treasury_Portal_Access::get_instance();
