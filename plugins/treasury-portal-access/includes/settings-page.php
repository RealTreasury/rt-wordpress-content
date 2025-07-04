<?php
/**
 * Settings Page for Treasury Portal Access
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$form_id = get_option('tpa_form_id');
$access_duration = get_option('tpa_access_duration', 180);
$redirect_url = get_option('tpa_redirect_url', home_url('/treasury-tech-portal/'));
$enable_localStorage = get_option('tpa_enable_localStorage', true);
$enable_email_notifications = get_option('tpa_enable_email_notifications', true);

// Get Contact Form 7 forms
$cf7_forms = array();
if (class_exists('WPCF7_ContactForm')) {
    $forms = WPCF7_ContactForm::find();
    foreach ($forms as $form) {
        $cf7_forms[] = array(
            'id' => $form->id(),
            'title' => $form->title()
        );
    }
}
?>

<div class="wrap">
    <h1 style="color: #7216f4; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
        <span class="dashicons dashicons-admin-settings" style="font-size: 32px;"></span>
        Treasury Portal Access Settings
    </h1>

    <?php settings_errors(); ?>

    <form method="post" action="options.php">
        <?php settings_fields('tpa_settings_group'); ?>
        
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
            <!-- Main Settings -->
            <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <h2 style="color: #7216f4; margin-top: 0;">Core Settings</h2>
                
                <!-- Contact Form Selection -->
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="tpa_form_id">Contact Form 7</label>
                        </th>
                        <td>
                            <select name="tpa_form_id" id="tpa_form_id" class="regular-text">
                                <option value="">-- Select a Form --</option>
                                <?php if (empty($cf7_forms)): ?>
                                    <option value="" disabled>No Contact Form 7 forms found</option>
                                <?php else: ?>
                                    <?php foreach ($cf7_forms as $form): ?>
                                        <option value="<?php echo esc_attr($form['id']); ?>" <?php selected($form_id, $form['id']); ?>>
                                            <?php echo esc_html($form['title']); ?> (ID: <?php echo esc_attr($form['id']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <p class="description">Select the Contact Form 7 form that grants portal access.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="tpa_access_duration">Access Duration</label>
                        </th>
                        <td>
                            <input type="number" name="tpa_access_duration" id="tpa_access_duration" 
                                   value="<?php echo esc_attr($access_duration); ?>" class="small-text" min="1" max="365" /> days
                            <p class="description">How long users can access content (1-365 days). Default: 180 days.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="tpa_redirect_url">Redirect URL</label>
                        </th>
                        <td>
                            <input type="url" name="tpa_redirect_url" id="tpa_redirect_url" 
                                   value="<?php echo esc_attr($redirect_url); ?>" class="large-text" placeholder="https://example.com/portal-page" />
                            <p class="description">Where to redirect users after successful form submission.</p>
                        </td>
                    </tr>
                </table>
                
                <h3 style="color: #7216f4;">Advanced Features</h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">localStorage Backup</th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="tpa_enable_localStorage" value="1" 
                                           <?php checked($enable_localStorage, 1); ?> />
                                    Enable localStorage backup system
                                </label>
                                <p class="description">Automatically restore portal access if cookies are cleared. Recommended.</p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Email Notifications</th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="tpa_enable_email_notifications" value="1" 
                                           <?php checked($enable_email_notifications, 1); ?> />
                                    Send welcome emails to new users
                                </label>
                                <p class="description">Send an email with access instructions when users complete the form.</p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save Settings', 'primary large'); ?>
            </div>
            
            <!-- Sidebar Info -->
            <div>
                <!-- Current Status -->
                <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <h3 style="color: #7216f4; margin-top: 0;">Current Status</h3>
                    
                    <div style="margin-bottom: 15px;">
                        <strong>Plugin Version:</strong><br>
                        <span style="color: #4CAF50;">v<?php echo TPA_VERSION; ?></span>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <strong>Contact Form 7:</strong><br>
                        <span style="color: <?php echo class_exists('WPCF7') ? '#4CAF50' : '#f44336'; ?>;">
                            <?php echo class_exists('WPCF7') ? '✅ Active' : '❌ Missing'; ?>
                        </span>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <strong>Database Table:</strong><br>
                        <?php 
                        global $wpdb;
                        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}portal_access_users'");
                        ?>
                        <span style="color: <?php echo $table_exists ? '#4CAF50' : '#f44336'; ?>;">
                            <?php echo $table_exists ? '✅ Created' : '❌ Missing'; ?>
                        </span>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <strong>Total Users:</strong><br>
                        <?php 
                        $user_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}portal_access_users");
                        ?>
                        <span style="color: #7216f4;"><?php echo intval($user_count); ?> registered</span>
                    </div>
                </div>
                
                <!-- Shortcode Reference -->
                <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <h3 style="color: #7216f4; margin-top: 0;">Shortcode Reference</h3>
                    
                    <h4>Protected Content</h4>
                    <code style="background: #f1f1f1; padding: 5px; border-radius: 3px; font-size: 12px; display: block; margin-bottom: 10px;">
                        [protected_content]Your content here...[/protected_content]
                    </code>
                    
                    <h4>Portal Access Button</h4>
                    <code style="background: #f1f1f1; padding: 5px; border-radius: 3px; font-size: 12px; display: block; margin-bottom: 10px;">
                        [portal_button text="Get Portal Access"]
                    </code>
                    
                    <h4>Manual Modal Trigger</h4>
                    <code style="background: #f1f1f1; padding: 5px; border-radius: 3px; font-size: 12px; display: block;">
                        &lt;a href="#openPortalModal"&gt;Access Portal&lt;/a&gt;
                    </code>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[action="options.php"]');
    const durationInput = document.getElementById('tpa_access_duration');
    const redirectInput = document.getElementById('tpa_redirect_url');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            // Validate access duration
            if (durationInput) {
                const duration = parseInt(durationInput.value, 10);
                if (isNaN(duration) || duration < 1 || duration > 365) {
                    alert('Access duration must be a number between 1 and 365 days.');
                    e.preventDefault();
                    return;
                }
            }
            
            // Validate redirect URL
            if (redirectInput && redirectInput.value) {
                try {
                    new URL(redirectInput.value);
                } catch (_) {
                    alert('Please enter a valid URL for the redirect.');
                    e.preventDefault();
                    return;
                }
            }
            
            console.log('✅ Settings form validation passed');
        });
    }
    
    console.log('✅ Treasury Portal Access Settings loaded');
});
</script>

<style>
.form-table th { width: 200px; font-weight: 600; }
.form-table td { vertical-align: top; padding-top: 20px; }
.form-table input[type="number"], .form-table input[type="url"], .form-table select { font-size: 14px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
.form-table .description { color: #666; font-style: italic; margin-top: 5px; }
code { word-break: break-all; }
</style>
