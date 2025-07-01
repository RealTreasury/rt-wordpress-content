<?php
/**
 * Astra functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Astra
 * @since 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Define Constants
 */
define( 'ASTRA_THEME_VERSION', '4.11.3' );
define( 'ASTRA_THEME_SETTINGS', 'astra-settings' );
define( 'ASTRA_THEME_DIR', trailingslashit( get_template_directory() ) );
define( 'ASTRA_THEME_URI', trailingslashit( esc_url( get_template_directory_uri() ) ) );
define( 'ASTRA_THEME_ORG_VERSION', file_exists( ASTRA_THEME_DIR . 'inc/w-org-version.php' ) );
/**
 * Minimum Version requirement of the Astra Pro addon.
 * This constant will be used to display the notice asking user to update the Astra addon to the version defined below.
 */
define( 'ASTRA_EXT_MIN_VER', '4.11.1' );
/**
 * Load in-house compatibility.
 */
if ( ASTRA_THEME_ORG_VERSION ) {
	require_once ASTRA_THEME_DIR . 'inc/w-org-version.php';
}
/**
 * Setup helper functions of Astra.
 */
require_once ASTRA_THEME_DIR . 'inc/core/class-astra-theme-options.php';
require_once ASTRA_THEME_DIR . 'inc/core/class-theme-strings.php';
require_once ASTRA_THEME_DIR . 'inc/core/common-functions.php';
require_once ASTRA_THEME_DIR . 'inc/core/class-astra-icons.php';
define( 'ASTRA_WEBSITE_BASE_URL', 'https://wpastra.com' );
/**
 * ToDo: Deprecate constants in future versions as they are no longer used in the codebase.
 */
define( 'ASTRA_PRO_UPGRADE_URL', ASTRA_THEME_ORG_VERSION ? astra_get_pro_url( '/pricing/', 'free-theme', 'dashboard', 'upgrade' ) : 'https://woocommerce.com/products/astra-pro/' );
define( 'ASTRA_PRO_CUSTOMIZER_UPGRADE_URL', ASTRA_THEME_ORG_VERSION ? astra_get_pro_url( '/pricing/', 'free-theme', 'customizer', 'upgrade' ) : 'https://woocommerce.com/products/astra-pro/' );
/**
 * Update theme
 */
require_once ASTRA_THEME_DIR . 'inc/theme-update/astra-update-functions.php';
require_once ASTRA_THEME_DIR . 'inc/theme-update/class-astra-theme-background-updater.php';
/**
 * Fonts Files
 */
require_once ASTRA_THEME_DIR . 'inc/customizer/class-astra-font-families.php';
if ( is_admin() ) {
	require_once ASTRA_THEME_DIR . 'inc/customizer/class-astra-fonts-data.php';
}
require_once ASTRA_THEME_DIR . 'inc/lib/webfont/class-astra-webfont-loader.php';
require_once ASTRA_THEME_DIR . 'inc/lib/docs/class-astra-docs-loader.php';
require_once ASTRA_THEME_DIR . 'inc/customizer/class-astra-fonts.php';
require_once ASTRA_THEME_DIR . 'inc/dynamic-css/custom-menu-old-header.php';
require_once ASTRA_THEME_DIR . 'inc/dynamic-css/container-layouts.php';
require_once ASTRA_THEME_DIR . 'inc/dynamic-css/astra-icons.php';
require_once ASTRA_THEME_DIR . 'inc/core/class-astra-walker-page.php';
require_once ASTRA_THEME_DIR . 'inc/core/class-astra-enqueue-scripts.php';
require_once ASTRA_THEME_DIR . 'inc/core/class-gutenberg-editor-css.php';
require_once ASTRA_THEME_DIR . 'inc/core/class-astra-wp-editor-css.php';
require_once ASTRA_THEME_DIR . 'inc/dynamic-css/block-editor-compatibility.php';
require_once ASTRA_THEME_DIR . 'inc/dynamic-css/inline-on-mobile.php';
require_once ASTRA_THEME_DIR . 'inc/dynamic-css/content-background.php';
require_once ASTRA_THEME_DIR . 'inc/dynamic-css/dark-mode.php';
require_once ASTRA_THEME_DIR . 'inc/class-astra-dynamic-css.php';
require_once ASTRA_THEME_DIR . 'inc/class-astra-global-palette.php';
// Enable NPS Survey only if the starter templates version is < 4.3.7 or > 4.4.4 to prevent fatal error.
if ( ! defined( 'ASTRA_SITES_VER' ) || version_compare( ASTRA_SITES_VER, '4.3.7', '<' ) || version_compare( ASTRA_SITES_VER, '4.4.4', '>' ) ) {
	// NPS Survey Integration
	require_once ASTRA_THEME_DIR . 'inc/lib/class-astra-nps-notice.php';
	require_once ASTRA_THEME_DIR . 'inc/lib/class-astra-nps-survey.php';
}
/**
 * Custom template tags for this theme.
 */
require_once ASTRA_THEME_DIR . 'inc/core/class-astra-attr.php';
require_once ASTRA_THEME_DIR . 'inc/template-tags.php';
require_once ASTRA_THEME_DIR . 'inc/widgets.php';
require_once ASTRA_THEME_DIR . 'inc/core/theme-hooks.php';
require_once ASTRA_THEME_DIR . 'inc/admin-functions.php';
require_once ASTRA_THEME_DIR . 'inc/core/sidebar-manager.php';
/**
 * Markup Functions
 */
require_once ASTRA_THEME_DIR . 'inc/markup-extras.php';
require_once ASTRA_THEME_DIR . 'inc/extras.php';
require_once ASTRA_THEME_DIR . 'inc/blog/blog-config.php';
require_once ASTRA_THEME_DIR . 'inc/blog/blog.php';
require_once ASTRA_THEME_DIR . 'inc/blog/single-blog.php';
/**
 * Markup Files
 */
require_once ASTRA_THEME_DIR . 'inc/template-parts.php';
require_once ASTRA_THEME_DIR . 'inc/class-astra-loop.php';
require_once ASTRA_THEME_DIR . 'inc/class-astra-mobile-header.php';
/**
 * Functions and definitions.
 */
require_once ASTRA_THEME_DIR . 'inc/class-astra-after-setup-theme.php';
// Required files.
require_once ASTRA_THEME_DIR . 'inc/core/class-astra-admin-helper.php';
require_once ASTRA_THEME_DIR . 'inc/schema/class-astra-schema.php';
/* Setup API */
require_once ASTRA_THEME_DIR . 'admin/includes/class-astra-api-init.php';
if ( is_admin() ) {
	/**
	 * Admin Menu Settings
	 */
	require_once ASTRA_THEME_DIR . 'inc/core/class-astra-admin-settings.php';
	require_once ASTRA_THEME_DIR . 'admin/class-astra-admin-loader.php';
	require_once ASTRA_THEME_DIR . 'inc/lib/astra-notices/class-astra-notices.php';
}
/**
 * Metabox additions.
 */
require_once ASTRA_THEME_DIR . 'inc/metabox/class-astra-meta-boxes.php';
require_once ASTRA_THEME_DIR . 'inc/metabox/class-astra-meta-box-operations.php';
require_once ASTRA_THEME_DIR . 'inc/metabox/class-astra-elementor-editor-settings.php';
/**
 * Customizer additions.
 */
require_once ASTRA_THEME_DIR . 'inc/customizer/class-astra-customizer.php';
/**
 * Astra Modules.
 */
require_once ASTRA_THEME_DIR . 'inc/modules/posts-structures/class-astra-post-structures.php';
require_once ASTRA_THEME_DIR . 'inc/modules/related-posts/class-astra-related-posts.php';
/**
 * Compatibility
 */
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-gutenberg.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-jetpack.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/woocommerce/class-astra-woocommerce.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/edd/class-astra-edd.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/lifterlms/class-astra-lifterlms.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/learndash/class-astra-learndash.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-beaver-builder.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-bb-ultimate-addon.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-contact-form-7.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-visual-composer.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-site-origin.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-gravity-forms.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-bne-flyout.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-ubermeu.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-divi-builder.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-amp.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-yoast-seo.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/surecart/class-astra-surecart.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-starter-content.php';
require_once ASTRA_THEME_DIR . 'inc/addons/transparent-header/class-astra-ext-transparent-header.php';
require_once ASTRA_THEME_DIR . 'inc/addons/breadcrumbs/class-astra-breadcrumbs.php';
require_once ASTRA_THEME_DIR . 'inc/addons/scroll-to-top/class-astra-scroll-to-top.php';
require_once ASTRA_THEME_DIR . 'inc/addons/heading-colors/class-astra-heading-colors.php';
require_once ASTRA_THEME_DIR . 'inc/builder/class-astra-builder-loader.php';
// Elementor Compatibility requires PHP 5.4 for namespaces.
if ( version_compare( PHP_VERSION, '5.4', '>=' ) ) {
	require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-elementor.php';
	require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-elementor-pro.php';
	require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-web-stories.php';
}
// Beaver Themer compatibility requires PHP 5.3 for anonymous functions.
if ( version_compare( PHP_VERSION, '5.3', '>=' ) ) {
	require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-beaver-themer.php';
}
require_once ASTRA_THEME_DIR . 'inc/core/markup/class-astra-markup.php';
/**
 * Load deprecated functions
 */
require_once ASTRA_THEME_DIR . 'inc/core/deprecated/deprecated-filters.php';
require_once ASTRA_THEME_DIR . 'inc/core/deprecated/deprecated-hooks.php';
require_once ASTRA_THEME_DIR . 'inc/core/deprecated/deprecated-functions.php';

// ===============================================================
// Video Access Control System for WordPress + Contact Form 7
// Updated with Brand Colors and First/Last Name Support
// WORKING VERSION - Now with improved admin styling
// ===============================================================

// Status notice (you can remove this after confirming everything works)
add_action('admin_notices', function() {
    echo '<div class="notice notice-success"><p>✅ Video Access System is loaded and running!</p></div>';
});

// 1. Handle Contact Form 7 submission and grant video access
add_action('wpcf7_mail_sent', 'handle_video_access_form_submission');
function handle_video_access_form_submission($contact_form) {
    // Your Contact Form 7 ID
    $form_id = "0779c74";
    
    // Match by form ID or title
    if ($contact_form->id() == $form_id || $contact_form->title() == "Video Access Gate Form") {
        $submission = WPCF7_Submission::get_instance();
        
        if ($submission) {
            $posted_data = $submission->get_posted_data();
            
            // Extract form data matching your exact form fields
            $user_data = array(
                'first_name' => sanitize_text_field($posted_data['first-name'] ?? ''),
                'last_name' => sanitize_text_field($posted_data['last-name'] ?? ''),
                'full_name' => sanitize_text_field(($posted_data['first-name'] ?? '') . ' ' . ($posted_data['last-name'] ?? '')),
                'email' => sanitize_email($posted_data['email-address'] ?? ''),
                'company' => sanitize_text_field($posted_data['company'] ?? ''),
                'newsletter' => 'no',
                'terms_agreement' => isset($posted_data['terms-agreement']) ? 'yes' : 'no',
                'access_granted' => current_time('mysql'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            );
            
            // Only proceed if we have essential data
            if (!empty($user_data['email']) && !empty($user_data['first_name'])) {
                // Store user data in database
                store_video_access_user($user_data);
                
                // Generate access token
                $access_token = generate_video_access_token($user_data['email']);
                
                // Set secure cookie for video access
                setcookie('video_access_token', $access_token, time() + (30 * 24 * 60 * 60), '/', '', is_ssl(), true);
                
                // Optional: Send welcome email with video links
                send_video_access_email($user_data);
                
                // Success log (you can remove this after testing)
                error_log('✅ Video access granted to: ' . $user_data['email'] . ' (' . $user_data['full_name'] . ')');
            }
        }
    }
}

// 2. Create database table for video access users
add_action('after_setup_theme', 'create_video_access_table');
function create_video_access_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'video_access_users';
    
    // Check if table already exists
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
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
            PRIMARY KEY (id),
            UNIQUE KEY email (email)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// 2b. Update existing table to include terms_agreement field
add_action('after_setup_theme', 'update_video_access_table');
function update_video_access_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'video_access_users';
    
    // Check if terms_agreement column exists
    $column_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM $table_name LIKE %s", 'terms_agreement'));
    
    if (empty($column_exists)) {
        // Add the terms_agreement column
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN terms_agreement varchar(3) DEFAULT 'no' AFTER newsletter");
        error_log('✅ Added terms_agreement column to video_access_users table');
    }
}

// 3. Store user data in database (updated)
function store_video_access_user($user_data) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'video_access_users';
    
    $result = $wpdb->replace(
        $table_name,
        array(
            'first_name' => $user_data['first_name'],
            'last_name' => $user_data['last_name'],
            'full_name' => $user_data['full_name'],
            'email' => $user_data['email'],
            'company' => $user_data['company'],
            'newsletter' => $user_data['newsletter'],
            'terms_agreement' => $user_data['terms_agreement'],
            'access_granted' => $user_data['access_granted'],
            'ip_address' => $user_data['ip_address'],
            'user_agent' => $user_data['user_agent'],
            'access_token' => generate_video_access_token($user_data['email'])
        ),
        array(
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
        )
    );
    
    if ($result === false) {
        error_log('❌ Failed to store video access user: ' . $wpdb->last_error);
    } else {
        error_log('✅ Successfully stored video access user: ' . $user_data['email']);
    }
}

// 4. Generate secure access token
function generate_video_access_token($email) {
    $secret_key = wp_salt('auth'); // Use WordPress salt
    $data = $email . time();
    return hash_hmac('sha256', $data, $secret_key);
}

// 5. Check if user has video access
function has_video_access() {
    if (!isset($_COOKIE['video_access_token'])) {
        return false;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'video_access_users';
    
    $token = sanitize_text_field($_COOKIE['video_access_token']);
    
    $user = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE access_token = %s",
        $token
    ));
    
    return $user !== null;
}

// 6. Shortcode to display videos (only if user has access)
add_shortcode('protected_videos', 'display_protected_videos');
function display_protected_videos($atts) {
    $atts = shortcode_atts(array(
        'video_ids' => '', // Comma-separated list of video IDs or URLs
    ), $atts);
    
    if (!has_video_access()) {
        return '<div class="video-access-required">
            <h3>Access Required</h3>
            <p>Please complete the access form to view our video content.</p>
            <a href="#video-access-form" class="btn btn-primary">Complete Form</a>
        </div>';
    }
    
    // User has access, show videos
    ob_start();
    ?>
    <div class="protected-video-container">
        <div class="video-access-status">
            <span class="access-granted">✓ Access Granted</span>
            <a href="javascript:void(0)" onclick="revokeVideoAccess()" class="revoke-access">Sign Out</a>
        </div>
        
        <div class="video-grid">
            <?php
            $video_ids = explode(',', $atts['video_ids']);
            foreach ($video_ids as $video_id) {
                $video_id = trim($video_id);
                echo '<div class="video-item">';
                
                // Check if it's a YouTube video
                if (strpos($video_id, 'youtube.com') !== false || strpos($video_id, 'youtu.be') !== false) {
                    echo wp_oembed_get($video_id);
                } else {
                    // For local videos or other formats
                    echo do_shortcode('[video src="' . esc_url($video_id) . '"]');
                }
                
                echo '</div>';
            }
            ?>
        </div>
    </div>
    
    <style>
    .protected-video-container {
        max-width: 900px;
        margin: 2rem auto;
        padding: 1.5rem;
        background: linear-gradient(135deg, #f8f8f8 0%, #ffffff 100%);
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(114, 22, 244, 0.1);
    }
    
    .video-access-status {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding: 1rem 1.5rem;
        background: linear-gradient(135deg, #7216f4 0%, #8f47f6 100%);
        color: white;
        border-radius: 12px;
        box-shadow: 0 4px 16px rgba(114, 22, 244, 0.3);
    }
    
    .access-granted {
        font-weight: 600;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .access-granted::before {
        content: "✓";
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
    }
    
    .revoke-access {
        color: rgba(255, 255, 255, 0.9);
        text-decoration: none;
        font-size: 0.9rem;
        padding: 0.5rem 1rem;
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    .revoke-access:hover {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        transform: translateY(-1px);
    }
    
    .video-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 1.5rem;
    }
    
    .video-item {
        background: white;
        padding: 1.5rem;
        border-radius: 16px;
        border: 2px solid transparent;
        box-shadow: 0 4px 20px rgba(114, 22, 244, 0.08);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .video-item::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #7216f4 0%, #8f47f6 50%, #9d4edd 100%);
    }
    
    .video-item:hover {
        transform: translateY(-4px);
        border-color: rgba(114, 22, 244, 0.2);
        box-shadow: 0 8px 32px rgba(114, 22, 244, 0.15);
    }
    
    .video-access-required {
        text-align: center;
        padding: 3rem 2rem;
        background: linear-gradient(135deg, #f8f8f8 0%, #ffffff 100%);
        border: 2px solid #c77dff;
        border-radius: 16px;
        margin: 2rem auto;
        max-width: 500px;
        box-shadow: 0 8px 32px rgba(199, 125, 255, 0.1);
    }
    
    .video-access-required h3 {
        color: #281345;
        margin-bottom: 1rem;
        font-size: 1.5rem;
    }
    
    .video-access-required p {
        color: #7e7e7e;
        margin-bottom: 1.5rem;
        font-size: 1.1rem;
    }
    
    .btn {
        display: inline-block;
        padding: 1rem 2rem;
        background: linear-gradient(135deg, #7216f4 0%, #8f47f6 100%);
        color: white;
        text-decoration: none;
        border-radius: 12px;
        font-weight: 600;
        font-size: 1rem;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 16px rgba(114, 22, 244, 0.3);
    }
    
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 24px rgba(114, 22, 244, 0.4);
        background: linear-gradient(135deg, #8f47f6 0%, #9d4edd 100%);
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .protected-video-container {
            margin: 1rem;
            padding: 1rem;
        }
        
        .video-access-status {
            flex-direction: column;
            gap: 1rem;
            text-align: center;
        }
        
        .video-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .video-item {
            padding: 1rem;
        }
    }
    </style>
    <?php
    
    return ob_get_clean();
}

// 7. AJAX handler for revoking access
add_action('wp_ajax_revoke_video_access', 'revoke_video_access');
add_action('wp_ajax_nopriv_revoke_video_access', 'revoke_video_access');
function revoke_video_access() {
    setcookie('video_access_token', '', time() - 3600, '/', '', is_ssl(), true);
    wp_die('Access revoked');
}

// 8. Send welcome email with video access
function send_video_access_email($user_data) {
    $to = $user_data['email'];
    $subject = 'Your Video Access is Ready! 🎥';
    
    $message = "Hi " . $user_data['first_name'] . ",\n\n";
    $message .= "Thank you for completing our access form. You now have access to our exclusive video content.\n\n";
    $message .= "Visit our video page: " . home_url('/videos') . "\n\n";
    $message .= "Best regards,\n";
    $message .= get_bloginfo('name');
    
    wp_mail($to, $subject, $message);
}

// 9. Add JavaScript for frontend functionality
add_action('wp_footer', 'video_access_javascript');
function video_access_javascript() {
    ?>
    <script>
    function revokeVideoAccess() {
        if (confirm('Are you sure you want to sign out? You will need to complete the form again to access videos.')) {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=revoke_video_access'
            }).then(() => {
                location.reload();
            });
        }
    }
    
    // Smooth scroll to form when clicking access button
    document.addEventListener('DOMContentLoaded', function() {
        const accessButtons = document.querySelectorAll('a[href="#video-access-form"]');
        accessButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const form = document.querySelector('#video-access-form');
                if (form) {
                    form.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    });
    </script>
    <?php
}

// 10. Add admin page to view video access users
add_action('admin_menu', 'video_access_admin_menu');
function video_access_admin_menu() {
    add_menu_page(
        'Video Access Users',
        'Video Access',
        'manage_options',
        'video-access-users',
        'video_access_admin_page',
        'dashicons-video-alt3'
    );
}

function video_access_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'video_access_users';
    $users = $wpdb->get_results("SELECT * FROM $table_name ORDER BY access_granted DESC");
    
    echo '<div class="wrap">';
    echo '<h1 style="color: #7216f4; margin-bottom: 20px;">Video Access Users (' . count($users) . ' total)</h1>';
    
    // Add custom CSS for better table styling
    echo '<style>
        .video-access-table {
            border: 2px solid #c77dff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(114, 22, 244, 0.1);
            background: white;
        }
        
        .video-access-table thead {
            background: linear-gradient(135deg, #7216f4 0%, #8f47f6 100%);
        }
        
        .video-access-table thead th {
            color: white !important;
            font-weight: 600 !important;
            padding: 15px 12px !important;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
            border-bottom: none !important;
            font-size: 14px;
            letter-spacing: 0.5px;
        }
        
        .video-access-table tbody tr {
            border-bottom: 1px solid #f0f0f0;
        }
        
        .video-access-table tbody tr:hover {
            background-color: #f8f9ff;
        }
        
        .video-access-table tbody td {
            padding: 12px !important;
            vertical-align: middle;
            border-left: none !important;
        }
        
        .video-access-table tbody td:first-child {
            border-left: none !important;
        }
        
        .user-name {
            font-weight: 600;
            color: #333;
        }
        
        .user-email {
            color: #666;
            font-size: 13px;
        }
        
        .company-name {
            color: #555;
        }
        
        .terms-yes {
            background: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .terms-no {
            background: #f8d7da;
            color: #721c24;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .access-date {
            color: #666;
            font-size: 13px;
        }
        
        .export-section {
            margin-top: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
            border-left: 4px solid #7216f4;
        }
    </style>';
    
    if ($users) {
        echo '<table class="wp-list-table widefat fixed striped video-access-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th style="width: 20%;">Name</th>';
        echo '<th style="width: 25%;">Email</th>';
        echo '<th style="width: 20%;">Company</th>';
        echo '<th style="width: 15%;">Terms Agreed</th>';
        echo '<th style="width: 20%;">Access Date</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($users as $user) {
            echo '<tr>';
            echo '<td><span class="user-name">' . esc_html($user->first_name . ' ' . $user->last_name) . '</span></td>';
            echo '<td><span class="user-email">' . esc_html($user->email) . '</span></td>';
            echo '<td><span class="company-name">' . esc_html($user->company ?: '—') . '</span></td>';
            
            $terms_status = ($user->terms_agreement ?? 'no') == 'yes';
            echo '<td><span class="' . ($terms_status ? 'terms-yes' : 'terms-no') . '">';
            echo $terms_status ? '✅ Accepted' : '❌ Not Accepted';
            echo '</span></td>';
            
            echo '<td><span class="access-date">' . esc_html(date('M j, Y', strtotime($user->access_granted))) . '<br><small>' . esc_html(date('g:i A', strtotime($user->access_granted))) . '</small></span></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        // Export section
        echo '<div class="export-section">';
        echo '<h3 style="margin-top: 0; color: #7216f4;">Export Data</h3>';
        echo '<p>Download your video access user data as a CSV file for external analysis or backup.</p>';
        echo '<a href="' . admin_url('admin-post.php?action=export_video_users') . '" class="button button-primary">📊 Export to CSV</a>';
        echo '</div>';
    } else {
        echo '<div style="text-align: center; padding: 3rem 2rem; background: linear-gradient(135deg, #f8f8f8 0%, #ffffff 100%); border: 2px solid #c77dff; border-radius: 16px; margin-top: 2rem; box-shadow: 0 4px 20px rgba(114, 22, 244, 0.1);">';
        echo '<div style="font-size: 48px; margin-bottom: 1rem;">📹</div>';
        echo '<h3 style="color: #281345; margin-bottom: 1rem; font-size: 1.5rem;">No users have requested video access yet</h3>';
        echo '<p style="color: #7e7e7e; margin-bottom: 1.5rem; font-size: 1.1rem;">When users complete your Video Access Gate Form, they\'ll appear here with their details.</p>';
        echo '<div style="background: #f0f0f0; padding: 1rem; border-radius: 8px; margin-top: 1rem;">';
        echo '<strong>Form ID:</strong> 0779c74<br>';
        echo '<strong>Shortcode:</strong> [contact-form-7 id="0779c74" title="Video Access Gate Form"]';
        echo '</div>';
        echo '</div>';
    }
    
    echo '</div>';
}

// 11. Shortcode for video modal button
function video_modal_button_shortcode($atts) {
    $atts = shortcode_atts(array(
        'text' => 'Access Videos',
        'class' => 'open-video-modal',
        'style' => 'button' // button, small, text-link
    ), $atts);
    
    $class = 'open-video-modal';
    if ($atts['style'] !== 'button') {
        $class .= ' ' . $atts['style'];
    }
    
    return '<button class="' . $class . '">' . esc_html($atts['text']) . '</button>';
}
add_shortcode('video_button', 'video_modal_button_shortcode');

// 12. Video Modal JavaScript and HTML (UPDATED WITH PERFECT CENTERING)
function add_video_modal_script() {
    ?>
    <div id="videoModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="video-access-form">
                <button class="close-btn" type="button">&times;</button>
                <h3>Access Treasury Tech Demos</h3>
                <?php echo do_shortcode('[contact-form-7 id="0779c74" title="Video Access Gate Form"]'); ?>
            </div>
        </div>
    </div>
    <script>
    // Modal Perfect Centering JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('videoModal');
        const body = document.body;

        // Function to open modal with perfect centering
        function openModal() {
            if (modal) {
                // Prevent background scrolling
                const scrollY = window.scrollY;
                body.style.position = 'fixed';
                body.style.top = `-${scrollY}px`;
                body.style.width = '100%';

                // Add classes
                body.classList.add('modal-open');
                modal.style.display = 'flex';
                setTimeout(() => {
                    modal.classList.add('show');
                }, 10);

                // Force repaint to ensure centering
                modal.offsetHeight;

                // Focus management for accessibility
                modal.setAttribute('aria-hidden', 'false');
                const firstInput = modal.querySelector('input, textarea, select, button');
                if (firstInput) {
                    setTimeout(() => firstInput.focus(), 100);
                }
            }
        }

        // Function to close modal and restore scroll
        function closeModal() {
            if (modal) {
                // Get the scroll position that was saved
                const scrollY = body.style.top;

                // Remove modal
                modal.classList.remove('show');
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300);
                body.classList.remove('modal-open');

                // Restore scroll position
                body.style.position = '';
                body.style.top = '';
                body.style.width = '';
                window.scrollTo(0, parseInt(scrollY || '0') * -1);

                // Accessibility
                modal.setAttribute('aria-hidden', 'true');
            }
        }

        // Enhanced button click handlers
        document.addEventListener('click', function(e) {
            // Open modal buttons
            if (e.target.matches('a[href="#openVideoModal"]') ||
                e.target.closest('a[href="#openVideoModal"]') ||
                e.target.matches('.open-video-modal') ||
                e.target.closest('.open-video-modal')) {
                e.preventDefault();
                e.stopPropagation();
                openModal();
                return false;
            }

            // Close modal buttons
            if (e.target.matches('.close-btn') ||
                e.target.closest('.close-btn')) {
                e.preventDefault();
                e.stopPropagation();
                closeModal();
                return false;
            }

            // Click outside modal to close
            if (e.target === modal && !e.target.closest('.modal-content')) {
                e.preventDefault();
                closeModal();
            }
        });

        // Handle menu clicks (#openVideoModal)
        document.addEventListener('click', function(e) {
            if (e.target.getAttribute('href') === '#openVideoModal' ||
                e.target.closest('a[href="#openVideoModal"]')) {
                e.preventDefault();
                openModal();
            }
        });

        // Handle data attribute clicks
        document.addEventListener('click', function(e) {
            if (e.target.getAttribute('data-open-modal') === 'video' ||
                e.target.closest('[data-open-modal="video"]')) {
                e.preventDefault();
                openModal();
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal && modal.classList.contains('show')) {
                e.preventDefault();
                closeModal();
            }
        });

        // Handle Contact Form 7 events
        document.addEventListener('wpcf7mailsent', function(event) {
            console.log('Form sent successfully');
            // Close modal after successful submission
            setTimeout(function() {
                closeModal();
            }, 2000);
        });

        // Handle resize to maintain centering
        window.addEventListener('resize', function() {
            if (modal && modal.classList.contains('show')) {
                // Force recalculation of centering
                modal.style.display = 'none';
                modal.offsetHeight; // Force repaint
                modal.style.display = 'flex';
            }
        });

        // Initialize modal state
        if (modal) {
            modal.setAttribute('aria-hidden', 'true');
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
        }
    });
    </script>
    <?php
}
add_action('wp_footer', 'add_video_modal_script');

// Site-Wide Cookie Management System
add_action('wp_footer', 'add_sitewide_cookie_banner');
function add_sitewide_cookie_banner() {
    ?>
    <script>
    // Site-Wide Cookie Management
    document.addEventListener('DOMContentLoaded', function() {
        
        // Check if user has already made a choice
        function checkCookieConsent() {
            const consent = localStorage.getItem('cookie_consent');
            if (!consent) {
                showCookieBanner();
            } else {
                // Apply existing preferences
                const consentData = JSON.parse(consent);
                if (consentData.preferences && consentData.preferences.analytics) {
                    loadGoogleAnalytics();
                }
            }
        }
        
        // Show cookie banner
        function showCookieBanner() {
            // Don't show if banner already exists
            if (document.getElementById('cookieBanner')) {
                return;
            }
            
            // Create banner
            const banner = document.createElement('div');
            banner.id = 'cookieBanner';
            banner.className = 'cookie-banner';
            banner.innerHTML = `
                <div class="banner-content">
                    <div class="banner-text">
                        <strong>🍪 We use cookies to enhance your experience</strong>
                        This website uses cookies to provide you with a personalized browsing experience and to analyze our website traffic.
                    </div>
                    <div class="banner-buttons">
                        <button class="cookie-btn cookie-btn-accept" onclick="acceptAllCookies()">Accept All</button>
                        <button class="cookie-btn cookie-btn-decline" onclick="declineAllCookies()">Decline All</button>
                        <a href="/cookie-policy" class="cookie-btn cookie-btn-manage">Learn More</a>
                    </div>
                </div>
            `;
            
            document.body.appendChild(banner);
            
            // Show with animation
            setTimeout(() => {
                banner.classList.add('show');
            }, 100);
        }
        
        // Accept all cookies
        window.acceptAllCookies = function() {
            const preferences = {
                essential: true,
                analytics: true,
                marketing: true,
                preference: true
            };
            
            localStorage.setItem('cookie_consent', JSON.stringify({
                timestamp: new Date().toISOString(),
                preferences: preferences
            }));
            
            hideCookieBanner();
            loadGoogleAnalytics();
            
            // Optional: Show confirmation
            console.log('✅ All cookies accepted');
        }
        
        // Decline all cookies
        window.declineAllCookies = function() {
            const preferences = {
                essential: true,
                analytics: false,
                marketing: false,
                preference: false
            };
            
            localStorage.setItem('cookie_consent', JSON.stringify({
                timestamp: new Date().toISOString(),
                preferences: preferences
            }));
            
            hideCookieBanner();
            
            // Remove any existing analytics cookies
            removeCookiesByPattern('_ga');
            
            console.log('❌ Non-essential cookies declined');
        }
        
        // Hide banner
        function hideCookieBanner() {
            const banner = document.getElementById('cookieBanner');
            if (banner) {
                banner.classList.remove('show');
                setTimeout(() => {
                    if (banner.parentNode) {
                        banner.parentNode.removeChild(banner);
                    }
                }, 300);
            }
        }
        
        // Load Google Analytics
        function loadGoogleAnalytics() {
            // Prevent loading multiple times
            if (typeof gtag !== 'undefined') {
                return;
            }
            
            // Load GA4 script
            const script = document.createElement('script');
            script.async = true;
            script.src = 'https://www.googletagmanager.com/gtag/js?id=G-6KLBPGHTSM';
            document.head.appendChild(script);
            
            // Initialize GA4
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', 'G-6KLBPGHTSM', {
                'anonymize_ip': true,
                'cookie_flags': 'max-age=7200;secure;samesite=none'
            });
            
            console.log('📊 Google Analytics loaded');
        }
        
        // Remove cookies by pattern
        function removeCookiesByPattern(pattern) {
            const cookies = document.cookie.split(';');
            cookies.forEach(cookie => {
                const [name] = cookie.trim().split('=');
                if (name.includes(pattern)) {
                    document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; domain=${window.location.hostname}`;
                    document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`;
                }
            });
        }
        
        // Initialize cookie management
        checkCookieConsent();
        
        // Global function to show banner (for manage preferences button)
        window.showCookieBanner = showCookieBanner;
    });
    </script>
    <?php
}

// Contact Form 7 Redirect - Faster Version
add_action('wp_footer', function() {
?>
<script>
document.addEventListener('wpcf7mailsent', function(event) {
    console.log('Form submitted successfully!');
    
    // Close modal immediately
    var modal = document.querySelector('.modal');
    if (modal) modal.classList.remove('show');
    
    // Redirect immediately (no delay)
    window.location.href = 'https://aisbee08e5bdvaliantmaker.wpcomstaging.com/treasury-tech-stack/';
});
</script>
<?php
});

// Add modal bridge script globally
function add_modal_bridge_script() {
    ?>
    <script>
    window.addEventListener('message', function(event) {
        if (event.data && event.data.action === 'openVideoModal') {
            const modalTrigger = document.querySelector('a[href="#openVideoModal"]') || 
                               document.querySelector('.mega-menu-link[href="#openVideoModal"]');
            if (modalTrigger) {
                modalTrigger.click();
            }
        }
    });
    </script>
    <?php
}
add_action('wp_footer', 'add_modal_bridge_script');

// Tawk.to Chat Widget Integration for Real Treasury - SMALLER VERSION
add_action('wp_footer', 'add_tawk_to_chat_widget');
function add_tawk_to_chat_widget() {
    ?>
    <!--Start of Tawk.to Script-->
    <script type="text/javascript">
    var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
    (function(){
        var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
        s1.async=true;
        s1.src='https://embed.tawk.to/68598eb06d2be41919849c7d/1iuetaosd';
        s1.charset='UTF-8';
        s1.setAttribute('crossorigin','*');
        s0.parentNode.insertBefore(s1,s0);
    })();
    
    // Custom function to open chat from buttons
    window.openTawkChat = function() {
        if (typeof Tawk_API !== 'undefined' && Tawk_API.maximize) {
            Tawk_API.maximize();
            console.log('Tawk chat opened');
        } else {
            console.log('Tawk not loaded yet, opening fallback');
            // Fallback to email if chat isn't loaded
            window.location.href = 'mailto:hello@realtreasury.com?subject=Treasury Technology Inquiry - Chat Unavailable';
        }
    };
    
    // Customize chat widget behavior
    Tawk_API.onLoad = function(){
        console.log('Tawk.to chat loaded successfully');
        
        // Keep the widget visible but customize its position and size
        Tawk_API.customStyle = {
            visibility: {
                desktop: {
                    position: 'br',
                    xOffset: 15,
                    yOffset: 15
                },
                mobile: {
                    position: 'br',
                    xOffset: 10,
                    yOffset: 10
                }
            }
        };
    };
    
    // Track when someone starts a chat
    Tawk_API.onChatStarted = function(){
        console.log('Chat conversation started');
        // Optional: Add Google Analytics tracking
        if (typeof gtag !== 'undefined') {
            gtag('event', 'chat_started', { 'event_category': 'engagement' });
        }
    };
    
    // Track when chat ends
    Tawk_API.onChatEnded = function(){
        console.log('Chat conversation ended');
    };
    </script>
    <!--End of Tawk.to Script-->
    
    <style>
    /* Make Tawk.to chat widget smaller and styled */
    #tawkchat-minified {
        width: 50px !important;
        height: 50px !important;
        border-radius: 50% !important;
        box-shadow: 0 4px 12px rgba(114, 22, 244, 0.3) !important;
        border: 2px solid rgba(199, 125, 255, 0.2) !important;
        transition: all 0.3s ease !important;
    }
    
    #tawkchat-minified:hover {
        transform: translateY(-2px) scale(1.05) !important;
        box-shadow: 0 6px 16px rgba(114, 22, 244, 0.4) !important;
        border-color: rgba(199, 125, 255, 0.4) !important;
    }
    
    /* Style the chat bubble to match your brand colors */
    #tawkchat-minified .tawk-min-container {
        background: linear-gradient(135deg, #7216f4 0%, #8f47f6 100%) !important;
        width: 100% !important;
        height: 100% !important;
        border-radius: 50% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }
    
    /* Make the chat icon smaller */
    #tawkchat-minified .tawk-min-container svg,
    #tawkchat-minified .tawk-min-container img {
        width: 24px !important;
        height: 24px !important;
        color: white !important;
        fill: white !important;
    }
    
    /* Style the expanded chat panel */
    #tawkchat-container iframe {
        border-radius: 12px !important;
        box-shadow: 0 8px 32px rgba(114, 22, 244, 0.2) !important;
        border: 2px solid rgba(199, 125, 255, 0.3) !important;
    }
    
    /* Hide notification badges to keep it minimal */
    #tawkchat-minified .tawk-min-container .tawk-badge {
        display: none !important;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        #tawkchat-minified {
            width: 45px !important;
            height: 45px !important;
        }
        
        #tawkchat-minified .tawk-min-container svg,
        #tawkchat-minified .tawk-min-container img {
            width: 20px !important;
            height: 20px !important;
        }
    }
    </style>
    <?php
}
// Add the JavaScript for bank report forms
add_action('wp_footer', 'add_bank_report_javascript');

function add_bank_report_javascript() {
    ?>
    <script>
    // Dynamic title and subtitle based on URL parameter
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const quarter = urlParams.get('quarter');
        
        const quarterData = {
            'q4-2024': {
                title: 'Q4 2024 Bank Report Access',
                subtitle: 'Get comprehensive banking insights and analysis for Q4 2024'
            },
            'q3-2024': {
                title: 'Q3 2024 Bank Report Access',
                subtitle: 'Access key banking trends and insights from Q3 2024'
            },
            'q2-2024': {
                title: 'Q2 2024 Bank Report Access', 
                subtitle: 'Download detailed banking analysis and strategic insights from Q2 2024'
            },
            'q1-2024': {
                title: 'Q1 2024 Bank Report Access',
                subtitle: 'Access comprehensive banking insights and strategies from Q1 2024'
            }
        };
        
        if (quarter && quarterData[quarter]) {
            // Update form title and subtitle dynamically
            const titleElement = document.querySelector('.gated-download-form h3');
            const subtitleElement = document.querySelector('.gated-download-form .subtitle');
            
            if (titleElement) titleElement.textContent = quarterData[quarter].title;
            if (subtitleElement) subtitleElement.textContent = quarterData[quarter].subtitle;
        }
    });

    // Handle download for AJAX forms
    document.addEventListener('wpcf7mailsent', function(event) {
        const formData = new FormData(event.target);
        const quarter = formData.get('quarter-report');
        
        const downloadUrls = {
            'q4-2024': 'https://dropbox.com/s/your-q4-link/q4-2024-bank-report.pdf?dl=1',
            'q3-2024': 'https://dropbox.com/s/your-q3-link/q3-2024-bank-report.pdf?dl=1',
            'q2-2024': 'https://dropbox.com/s/your-q2-link/q2-2024-bank-report.pdf?dl=1',
            'q1-2024': 'https://dropbox.com/s/your-q1-link/q1-2024-bank-report.pdf?dl=1'
        };
        
        if (quarter && downloadUrls[quarter]) {
            // Open download in new tab
            window.open(downloadUrls[quarter], '_blank');
        }
    }, false);
    </script>
    <?php
}
// Remove default Astra post footer elements
function remove_astra_default_footer() {
    if (is_single()) {
        // Remove post meta
        remove_action('astra_entry_after', 'astra_single_post_navigation_markup');
        
        // Remove author box if enabled
        remove_action('astra_entry_after', 'astra_author_box_markup');
        
        // Remove related posts if Pro version
        remove_action('astra_entry_after', 'astra_single_post_related_posts_markup');
    }
}
add_action('wp', 'remove_astra_default_footer');
// Custom related posts function
function custom_related_posts() {
    if (!is_single()) return;
    
    global $post;
    
    // Get current post categories
    $categories = get_the_category($post->ID);
    $category_ids = array();
    
    if ($categories) {
        foreach($categories as $category) {
            $category_ids[] = $category->term_id;
        }
    }
    
    // Query for related posts
    $related_posts = new WP_Query(array(
        'post_type' => 'post',
        'posts_per_page' => 3, // Adjust number as needed
        'post__not_in' => array($post->ID),
        'category__in' => $category_ids,
        'orderby' => 'rand'
    ));
    
    if ($related_posts->have_posts()) {
        echo '<div class="custom-related-posts">';
        echo '<h3>Related Posts</h3>';
        echo '<div class="related-posts-grid">';
        
        while ($related_posts->have_posts()) {
            $related_posts->the_post();
            echo '<div class="related-post-item">';
            
            // Featured image
            if (has_post_thumbnail()) {
                echo '<div class="related-post-image">';
                echo '<a href="' . get_permalink() . '">';
                the_post_thumbnail('medium');
                echo '</a>';
                echo '</div>';
            }
            
            // Title and excerpt
            echo '<div class="related-post-content">';
            echo '<h4><a href="' . get_permalink() . '">' . get_the_title() . '</a></h4>';
            echo '<p>' . get_the_excerpt() . '</p>';
            echo '<a href="' . get_permalink() . '" class="read-more">Read More</a>';
            echo '</div>';
            
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    wp_reset_postdata();
}

// Hook the custom related posts to Astra
function add_custom_related_posts() {
    if (is_single()) {
        add_action('astra_entry_after', 'custom_related_posts');
    }
}
add_action('wp', 'add_custom_related_posts');

<?php
// Remove default Astra post footer elements
function remove_astra_post_footer_elements() {
    if (is_single()) {
        // Remove the post navigation (Previous/Next post links)
        remove_action('astra_entry_after', 'astra_single_post_navigation_markup', 15);
        
        // Remove author box if it exists
        remove_action('astra_entry_after', 'astra_author_box_markup', 10);
        
        // Remove any existing related posts (if Astra Pro)
        remove_action('astra_entry_after', 'astra_single_post_related_posts_markup', 20);
        
        // Remove post meta from bottom if it exists
        remove_action('astra_entry_bottom', 'astra_entry_meta', 10);
    }
}
add_action('wp', 'remove_astra_post_footer_elements');

// Custom Related Posts Function
function custom_related_posts_section() {
    if (!is_single()) return;
    
    global $post;
    
    // Get current post categories
    $categories = get_the_category($post->ID);
    if (empty($categories)) return;
    
    $category_ids = wp_list_pluck($categories, 'term_id');
    
    // Query for related posts
    $related_posts = new WP_Query(array(
        'post_type' => 'post',
        'posts_per_page' => 3,
        'post__not_in' => array($post->ID),
        'category__in' => $category_ids,
        'orderby' => 'rand',
        'meta_query' => array(
            array(
                'key' => '_thumbnail_id',
                'compare' => 'EXISTS'
            ),
        ),
    ));
    
    if ($related_posts->have_posts()) : ?>
        <div class="custom-related-posts-wrapper">
            <div class="custom-related-posts">
                <h3 class="related-posts-title">Related Posts</h3>
                <div class="related-posts-grid">
                    <?php while ($related_posts->have_posts()) : $related_posts->the_post(); ?>
                        <article class="related-post-item">
                            <?php if (has_post_thumbnail()) : ?>
                                <div class="related-post-thumbnail">
                                    <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
                                        <?php the_post_thumbnail('medium', array('class' => 'related-post-img')); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <div class="related-post-content">
                                <h4 class="related-post-title">
                                    <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
                                        <?php the_title(); ?>
                                    </a>
                                </h4>
                                
                                <div class="related-post-meta">
                                    <span class="related-post-date"><?php echo get_the_date(); ?></span>
                                </div>
                                
                                <div class="related-post-excerpt">
                                    <?php echo wp_trim_words(get_the_excerpt(), 20, '...'); ?>
                                </div>
                                
                                <a href="<?php the_permalink(); ?>" class="related-post-link">Read More →</a>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    <?php 
    endif;
    
    wp_reset_postdata();
}

// Add custom related posts to the post footer area
function add_custom_related_posts() {
    if (is_single()) {
        add_action('astra_entry_after', 'custom_related_posts_section', 25);
    }
}
add_action('wp', 'add_custom_related_posts');
?>
