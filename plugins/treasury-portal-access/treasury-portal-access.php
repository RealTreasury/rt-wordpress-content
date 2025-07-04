<?php
/**
 * Plugin Name: Treasury Portal Access
 * Plugin URI: https://realtreasury.com
 * Description: Complete portal access control system with Contact Form 7 integration, 6-month persistence, and localStorage backup.
 * Version: 1.0.1
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
define('TPA_VERSION', '1.0.1');
define('TPA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TPA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TPA_PLUGIN_FILE', __FILE__);

/**
 * Main Treasury Portal Access Class
 */
class Treasury_Portal_Access {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Database table name
     */
    private $table_name;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'portal_access_users';
        
        // Hook into WordPress
        add_action('init', array($this, 'init'));
        add_action('wp_footer', array($this, 'add_frontend_scripts'));
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'register_settings'));
            add_action('admin_notices', array($this, 'admin_notices'));
        }
        
        // Contact Form 7 integration
        add_action('wpcf7_mail_sent', array($this, 'handle_form_submission'));
        
        // AJAX handlers
        add_action('wp_ajax_restore_portal_access', array($this, 'restore_access_ajax'));
        add_action('wp_ajax_nopriv_restore_portal_access', array($this, 'restore_access_ajax'));
        add_action('wp_ajax_revoke_portal_access', array($this, 'revoke_access_ajax'));
        add_action('wp_ajax_nopriv_revoke_portal_access', array($this, 'revoke_access_ajax'));
        add_action('wp_ajax_get_current_user_access', array($this, 'get_user_access_ajax'));
        add_action('wp_ajax_nopriv_get_current_user_access', array($this, 'get_user_access_ajax'));
        
        // Shortcodes
        add_shortcode('protected_content', array($this, 'protected_content_shortcode'));
        add_shortcode('portal_button', array($this, 'portal_button_shortcode'));
        
        // Backward compatibility
        add_shortcode('protected_videos', array($this, 'protected_content_shortcode'));
        add_shortcode('video_button', array($this, 'portal_button_shortcode'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load textdomain for translations
        load_plugin_textdomain('treasury-portal-access', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Check for Contact Form 7
        if (!class_exists('WPCF7')) {
            add_action('admin_notices', array($this, 'cf7_missing_notice'));
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        $this->create_database_table();
        $this->set_default_options();
        
        // Clear any caches
        flush_rewrite_rules();
        
        // Log activation
        error_log('✅ Treasury Portal Access Plugin activated');
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up any temporary data if needed
        error_log('🔄 Treasury Portal Access Plugin deactivated');
    }
    
    /**
     * Create database table
     */
    private function create_database_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            first_name varchar(50) NOT NULL,
            last_name varchar(50) NOT NULL,
            full_name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            company varchar(100),
            newsletter varchar(3),
            terms_agreement varchar(3) DEFAULT 'no',
            access_granted datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45),
            user_agent text,
            access_token varchar(255),
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY access_token (access_token)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Update plugin version
        update_option('tpa_db_version', TPA_VERSION);
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = array(
            'form_id' => '',
            'access_duration' => 180, // days
            'redirect_url' => home_url('/treasury-tech-portal/'),
            'enable_localStorage' => true,
            'enable_email_notifications' => true
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option("tpa_{$key}") === false) {
                update_option("tpa_{$key}", $value);
            }
        }
    }

    /**
     * Register settings using the Settings API
     */
    public function register_settings() {
        register_setting('tpa_settings_group', 'tpa_form_id', 'sanitize_text_field');
        register_setting('tpa_settings_group', 'tpa_access_duration', 'intval');
        register_setting('tpa_settings_group', 'tpa_redirect_url', 'esc_url_raw');
        register_setting('tpa_settings_group', 'tpa_enable_localStorage', 'intval');
        register_setting('tpa_settings_group', 'tpa_enable_email_notifications', 'intval');
    }
    
    /**
     * Handle Contact Form 7 submission
     */
    public function handle_form_submission($contact_form) {
        $form_id = get_option('tpa_form_id');
        
        // Check if this is our target form by its ID
        if ($contact_form->id() != $form_id) {
            return;
        }
        
        $submission = WPCF7_Submission::get_instance();
        if (!$submission) {
            return;
        }
        
        $posted_data = $submission->get_posted_data();
        
        // Extract and sanitize form data
        $user_data = array(
            'first_name' => sanitize_text_field($posted_data['first-name'] ?? ''),
            'last_name' => sanitize_text_field($posted_data['last-name'] ?? ''),
            'email' => sanitize_email($posted_data['email-address'] ?? ''),
            'company' => sanitize_text_field($posted_data['company'] ?? ''),
            'terms_agreement' => isset($posted_data['terms-agreement']) ? 'yes' : 'no',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        
        $user_data['full_name'] = trim($user_data['first_name'] . ' ' . $user_data['last_name']);
        
        // Validate required fields
        if (empty($user_data['email']) || empty($user_data['first_name'])) {
            error_log('❌ TPA: Missing required fields in form submission');
            return;
        }
        
        // Process the access grant
        $this->grant_portal_access($user_data);
    }
    
    /**
     * Grant portal access to user
     */
    private function grant_portal_access($user_data) {
        // Generate access token
        $access_token = $this->generate_access_token($user_data['email']);
        
        // Store in database
        $this->store_user_data($user_data, $access_token);
        
        // Set cookies
        $duration = get_option('tpa_access_duration', 180) * DAY_IN_SECONDS;
        $this->set_access_cookies($access_token, $user_data['email'], $duration);
        
        // Send welcome email if enabled
        if (get_option('tpa_enable_email_notifications', true)) {
            $this->send_welcome_email($user_data);
        }
        
        // Add localStorage script
        $this->add_localStorage_script($access_token, $user_data);
        
        error_log("✅ TPA: Portal access granted to {$user_data['email']} ({$user_data['full_name']}) for " . get_option('tpa_access_duration', 180) . " days");
    }
    
    /**
     * Store user data in database
     */
    private function store_user_data($user_data, $access_token) {
        global $wpdb;
        
        $data_to_store = array_merge($user_data, array(
            'access_token' => $access_token,
            'access_granted' => current_time('mysql')
        ));

        // Check if user exists by email
        $existing_user_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table_name} WHERE email = %s", $user_data['email']));

        if ($existing_user_id) {
            // Update existing user
            $result = $wpdb->update(
                $this->table_name,
                $data_to_store,
                array('id' => $existing_user_id)
            );
        } else {
            // Insert new user
            $result = $wpdb->insert($this->table_name, $data_to_store);
        }
        
        if ($result === false) {
            error_log('❌ TPA: Failed to store user data: ' . $wpdb->last_error);
            return false;
        }
        
        return true;
    }
    
    /**
     * Generate access token
     */
    private function generate_access_token($email) {
        $secret_key = wp_salt('auth');
        $data = $email . time() . wp_generate_password(12, false);
        return hash_hmac('sha256', $data, $secret_key);
    }
    
    /**
     * Set access cookies
     */
    private function set_access_cookies($access_token, $email, $duration) {
        $secure = is_ssl();
        $timestamp = time();
        $expiry = $timestamp + $duration;
        
        setcookie('portal_access_token', $access_token, $expiry, COOKIEPATH, COOKIE_DOMAIN, $secure, true);
        setcookie('user_identifier', base64_encode($email), $expiry, COOKIEPATH, COOKIE_DOMAIN, $secure, true);
        setcookie('access_granted_time', $timestamp, $expiry, COOKIEPATH, COOKIE_DOMAIN, $secure, true);
    }
    
    /**
     * Add localStorage backup script
     */
    private function add_localStorage_script($access_token, $user_data) {
        if (!get_option('tpa_enable_localStorage', true)) {
            return;
        }
        
        add_action('wp_footer', function() use ($access_token, $user_data) {
            echo '<script>
                try {
                    localStorage.setItem("portal_access_token", "' . esc_js($access_token) . '");
                    localStorage.setItem("user_email", "' . esc_js($user_data['email']) . '");
                    localStorage.setItem("user_name", "' . esc_js($user_data['full_name']) . '");
                    localStorage.setItem("access_granted", "' . time() . '");
                    console.log("✅ TPA: Portal access data stored in localStorage");
                } catch (e) {
                    console.error("TPA: Could not write to localStorage.", e);
                }
            </script>';
        }, 999); // High priority to run late
    }
    
    /**
     * Check if user has portal access
     */
    public function has_portal_access() {
        // Check primary access token
        if (isset($_COOKIE['portal_access_token'])) {
            if ($this->verify_access_token($_COOKIE['portal_access_token'])) {
                return true;
            }
        }
        
        // Check backup cookies
        if (isset($_COOKIE['user_identifier']) && isset($_COOKIE['access_granted_time'])) {
            $email = base64_decode($_COOKIE['user_identifier']);
            $access_time = intval($_COOKIE['access_granted_time']);
            $duration = get_option('tpa_access_duration', 180) * DAY_IN_SECONDS;
            
            if ((time() - $access_time) < $duration && $this->user_exists($email)) {
                // Regenerate access token
                $new_token = $this->generate_access_token($email);
                $this->set_access_cookies($new_token, $email, $duration);
                $this->update_user_token($email, $new_token);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verify access token
     */
    private function verify_access_token($token) {
        global $wpdb;
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE access_token = %s",
            $token
        ));
        
        return $user !== null;
    }
    
    /**
     * Check if user exists in database
     */
    private function user_exists($email) {
        global $wpdb;
        
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE email = %s",
            $email
        ));
        
        return $user_id !== null;
    }
    
    /**
     * Send welcome email
     */
    private function send_welcome_email($user_data) {
        $to = $user_data['email'];
        $subject = 'Your Treasury Portal Access is Ready! 🏛️';
        
        $message = "Hi {$user_data['first_name']},\n\n";
        $message .= "Thank you for completing our access form. You now have access to our exclusive Treasury Portal.\n\n";
        $message .= "Visit your portal: " . get_option('tpa_redirect_url', home_url('/treasury-tech-portal/')) . "\n\n";
        $message .= "Your access is valid for " . get_option('tpa_access_duration', 180) . " days.\n\n";
        $message .= "Best regards,\n" . get_bloginfo('name');
        
        wp_mail($to, $subject, $message);
    }
    
    /**
     * AJAX: Restore portal access
     */
    public function restore_access_ajax() {
        check_ajax_referer('tpa_frontend_nonce', 'nonce');
        $email = sanitize_email($_POST['email'] ?? '');
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error('invalid_email');
        }
        
        if ($this->user_exists($email)) {
            $access_token = $this->generate_access_token($email);
            $duration = get_option('tpa_access_duration', 180) * DAY_IN_SECONDS;
            
            $this->set_access_cookies($access_token, $email, $duration);
            $this->update_user_token($email, $access_token);
            
            error_log("✅ TPA: Portal access restored for {$email}");
            wp_send_json_success('success');
        } else {
            error_log("❌ TPA: Restoration failed - user not found: {$email}");
            wp_send_json_error('user_not_found');
        }
    }
    
    /**
     * AJAX: Revoke portal access
     */
    public function revoke_access_ajax() {
        check_ajax_referer('tpa_frontend_nonce', 'nonce');
        $this->clear_access_cookies();
        error_log('🔐 TPA: Portal access revoked for session');
        wp_send_json_success('Access revoked');
    }
    
    /**
     * AJAX: Get current user access data
     */
    public function get_user_access_ajax() {
        check_ajax_referer('tpa_frontend_nonce', 'nonce');
        if (!isset($_COOKIE['portal_access_token'])) {
            wp_send_json_error('No access token found');
        }
        
        $token = sanitize_text_field($_COOKIE['portal_access_token']);
        $user = $this->get_user_by_token($token);
        
        if ($user) {
            wp_send_json_success(array(
                'token' => $token,
                'email' => $user->email,
                'name' => $user->full_name,
                'access_time' => strtotime($user->access_granted)
            ));
        } else {
            wp_send_json_error('Invalid token');
        }
    }
    
    /**
     * Update user access token
     */
    private function update_user_token($email, $access_token) {
        global $wpdb;
        
        $wpdb->update(
            $this->table_name,
            array('access_token' => $access_token),
            array('email' => $email),
            array('%s'),
            array('%s')
        );
    }
    
    /**
     * Clear access cookies
     */
    private function clear_access_cookies() {
        $secure = is_ssl();
        $past_time = time() - YEAR_IN_SECONDS;
        setcookie('portal_access_token', '', $past_time, COOKIEPATH, COOKIE_DOMAIN, $secure, true);
        setcookie('user_identifier', '', $past_time, COOKIEPATH, COOKIE_DOMAIN, $secure, true);
        setcookie('access_granted_time', '', $past_time, COOKIEPATH, COOKIE_DOMAIN, $secure, true);
    }
    
    /**
     * Get user by access token
     */
    private function get_user_by_token($token) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE access_token = %s",
            $token
        ));
    }
    
    /**
     * Get current user info
     */
    private function get_current_user_info() {
        if (!isset($_COOKIE['portal_access_token'])) {
            return null;
        }
        
        $token = sanitize_text_field($_COOKIE['portal_access_token']);
        return $this->get_user_by_token($token);
    }
    
    /**
     * Calculate remaining access time
     */
    private function get_access_time_remaining() {
        if (!isset($_COOKIE['access_granted_time'])) {
            return 'Unknown';
        }
        
        $access_time = intval($_COOKIE['access_granted_time']);
        $duration = get_option('tpa_access_duration', 180) * DAY_IN_SECONDS;
        $expiry_time = $access_time + $duration;
        $remaining = $expiry_time - time();
        
        if ($remaining <= 0) {
            return 'Expired';
        }
        
        return human_time_diff($expiry_time, time());
    }
    
    /**
     * Protected Content Shortcode
     */
    public function protected_content_shortcode($atts, $content = null) {
        $atts = shortcode_atts(array(
            'video_ids' => '',
            'content_ids' => '',
        ), $atts);
        
        if (!$this->has_portal_access()) {
            return $this->render_access_required_message();
        }
        
        $content_ids = !empty($atts['content_ids']) ? $atts['content_ids'] : $atts['video_ids'];
        return $this->render_protected_content($content_ids, $content);
    }
    
    /**
     * Render access required message
     */
    private function render_access_required_message() {
        ob_start();
        ?>
        <div class="tpa-access-required">
            <div class="tpa-access-icon">🏛️</div>
            <h3>Portal Access Required</h3>
            <p>Complete our quick form to unlock exclusive Treasury Portal content.</p>
            <a href="#openPortalModal" class="tpa-btn tpa-btn-primary open-portal-modal">Get Portal Access</a>
            <div class="tpa-returning-user">
                <p><small>Returning user? Your access may be automatically restored.</small></p>
            </div>
        </div>
        
        <style>
        .tpa-access-required { text-align: center; padding: 3rem 2rem; background: linear-gradient(135deg, #f8f8f8 0%, #ffffff 100%); border: 2px solid #c77dff; border-radius: 16px; margin: 2rem auto; max-width: 500px; box-shadow: 0 8px 32px rgba(199, 125, 255, 0.1); }
        .tpa-access-icon { font-size: 3rem; margin-bottom: 1rem; }
        .tpa-access-required h3 { color: #281345; margin-bottom: 1rem; font-size: 1.5rem; }
        .tpa-access-required p { color: #7e7e7e; margin-bottom: 1.5rem; font-size: 1.1rem; }
        .tpa-returning-user { margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e0e0e0; }
        .tpa-btn { display: inline-block; padding: 1rem 2rem; background: linear-gradient(135deg, #7216f4 0%, #8f47f6 100%); color: white !important; text-decoration: none; border-radius: 12px; font-weight: 600; font-size: 1rem; transition: all 0.3s ease; border: none; cursor: pointer; box-shadow: 0 4px 16px rgba(114, 22, 244, 0.3); }
        .tpa-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(114, 22, 244, 0.4); background: linear-gradient(135deg, #8f47f6 0%, #9d4edd 100%); color: white !important; text-decoration: none; }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render protected content
     */
    private function render_protected_content($content_ids, $enclosed_content) {
        $user_info = $this->get_current_user_info();
        $user_name = $user_info ? $user_info->first_name : 'User';
        
        ob_start();
        ?>
        <div class="tpa-content-container">
            <div class="tpa-content-status">
                <div class="tpa-welcome">
                    <span class="tpa-access-granted">✓ Welcome back, <?php echo esc_html($user_name); ?>!</span>
                    <span class="tpa-access-duration"><?php echo get_option('tpa_access_duration', 180); ?>-day portal access active</span>
                </div>
                <div class="tpa-controls">
                    <a href="javascript:void(0)" onclick="window.TPA.revoke()" class="tpa-revoke">Sign Out</a>
                </div>
            </div>
            
            <?php if (!empty($content_ids)): ?>
            <div class="tpa-content-grid">
                <?php
                $content_items = explode(',', $content_ids);
                foreach ($content_items as $content_id) {
                    $content_id = trim($content_id);
                    echo '<div class="tpa-content-item">';
                    
                    if (strpos($content_id, 'youtube.com') !== false || strpos($content_id, 'youtu.be') !== false) {
                        echo wp_oembed_get($content_id);
                    } else {
                        echo do_shortcode('[video src="' . esc_url($content_id) . '"]');
                    }
                    
                    echo '</div>';
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
                <p><small>Your portal access expires in <?php echo $this->get_access_time_remaining(); ?>. Need help? <a href="mailto:hello@realtreasury.com">Contact us</a>.</small></p>
            </div>
        </div>
        
        <style>
        .tpa-content-container { max-width: 900px; margin: 2rem auto; padding: 1.5rem; background: linear-gradient(135deg, #f8f8f8 0%, #ffffff 100%); border-radius: 16px; box-shadow: 0 8px 32px rgba(114, 22, 244, 0.1); }
        .tpa-content-status { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding: 1rem 1.5rem; background: linear-gradient(135deg, #7216f4 0%, #8f47f6 100%); color: white; border-radius: 12px; box-shadow: 0 4px 16px rgba(114, 22, 244, 0.3); }
        .tpa-welcome { display: flex; flex-direction: column; gap: 0.25rem; }
        .tpa-access-granted { font-weight: 600; font-size: 1.1rem; }
        .tpa-access-duration { font-size: 0.85rem; opacity: 0.9; font-style: italic; }
        .tpa-revoke { color: rgba(255, 255, 255, 0.9) !important; text-decoration: none; font-size: 0.9rem; padding: 0.5rem 1rem; border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 8px; transition: all 0.3s ease; }
        .tpa-revoke:hover { background: rgba(255, 255, 255, 0.1); color: white !important; transform: translateY(-1px); }
        .tpa-content-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem; }
        .tpa-content-item { background: white; padding: 1.5rem; border-radius: 16px; box-shadow: 0 4px 20px rgba(114, 22, 244, 0.08); transition: all 0.3s ease; position: relative; overflow: hidden; }
        .tpa-content-item::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #7216f4 0%, #8f47f6 50%, #9d4edd 100%); }
        .tpa-content-item:hover { transform: translateY(-4px); box-shadow: 0 8px 32px rgba(114, 22, 244, 0.15); }
        .tpa-access-info { text-align: center; padding: 1rem; background: rgba(114, 22, 244, 0.05); border-radius: 8px; border: 1px solid rgba(114, 22, 244, 0.1); }
        .tpa-access-info a { color: #7216f4 !important; text-decoration: none; font-weight: 500; }
        .tpa-enclosed-content { margin-bottom: 1.5rem; }
        @media (max-width: 768px) {
            .tpa-content-container { margin: 1rem; padding: 1rem; }
            .tpa-content-status { flex-direction: column; gap: 1rem; text-align: center; }
            .tpa-content-grid { grid-template-columns: 1fr; gap: 1rem; }
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Portal Button Shortcode
     */
    public function portal_button_shortcode($atts) {
        $atts = shortcode_atts(array(
            'text' => 'Access Portal',
            'class' => 'tpa-btn tpa-btn-primary open-portal-modal',
            'style' => 'button'
        ), $atts);
        
        return '<button class="' . esc_attr($atts['class']) . '">' . esc_html($atts['text']) . '</button>';
    }
    
    /**
     * Add frontend scripts
     */
    public function add_frontend_scripts() {
        // Only add scripts if the shortcode is present or we need to check localStorage
        if (get_option('tpa_enable_localStorage', true) || (is_singular() && has_shortcode(get_post()->post_content, 'protected_content'))) {
            include TPA_PLUGIN_DIR . 'includes/frontend-scripts.php';
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Portal Access Users',
            'Portal Access',
            'manage_options',
            'treasury-portal-access',
            array($this, 'admin_page'),
            'dashicons-building',
            30
        );
        
        add_submenu_page(
            'treasury-portal-access',
            'Settings',
            'Settings',
            'manage_options',
            'treasury-portal-access-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        include TPA_PLUGIN_DIR . 'includes/admin-page.php';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        include TPA_PLUGIN_DIR . 'includes/settings-page.php';
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        if (get_current_screen()->id === 'toplevel_page_treasury-portal-access' || get_current_screen()->id === 'portal-access_page_treasury-portal-access-settings') {
            echo '<div class="notice notice-success is-dismissible"><p>✅ Treasury Portal Access Plugin is active and running!</p></div>';
        }
    }
    
    /**
     * Contact Form 7 missing notice
     */
    public function cf7_missing_notice() {
        echo '<div class="notice notice-warning"><p>⚠️ <strong>Treasury Portal Access:</strong> Contact Form 7 is not active. This plugin requires Contact Form 7 to function.</p></div>';
    }
}

// Initialize the plugin
Treasury_Portal_Access::get_instance();
