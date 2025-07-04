<?php
/**
 * Settings Page for Treasury Portal Access
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['submit']) && wp_verify_nonce($_POST['tpa_settings_nonce'], 'tpa_settings_action')) {
    
    // Sanitize and save settings
    $settings = array(
        'tpa_form_id' => sanitize_text_field($_POST['tpa_form_id']),
        'tpa_access_duration' => intval($_POST['tpa_access_duration']),
        'tpa_redirect_url' => esc_url_raw($_POST['tpa_redirect_url']),
        'tpa_enable_localStorage' => isset($_POST['tpa_enable_localStorage']) ? 1 : 0,
        'tpa_enable_email_notifications' => isset($_POST['tpa_enable_email_notifications']) ? 1 : 0
    );
    
    foreach ($settings as $key => $value) {
        update_option($key, $value);
    }
    
    echo '<div class="notice notice-success"><p>✅ Settings saved successfully!</p></div>';
}

// Get current settings
$form_id = get_option('tpa_form_id', '0779c74');
$access_duration = get_option('tpa_access_duration', 180);
$redirect_url = get_option('tpa_redirect_url', 'https://realtreasury.com/treasury-tech-portal/');
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

    <form method="post" action="">
        <?php wp_nonce_field('tpa_settings_action', 'tpa_settings_nonce'); ?>
        
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
            <!-- Main Settings -->
            <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <h2 style="color: #7216f4; margin-top: 0;">Core Settings</h2>
                
                <!-- Contact Form Selection -->
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="tva_form_id">Contact Form 7</label>
                        </th>
                        <td>
                            <select name="tva_form_id" id="tva_form_id" class="regular-text">
                                <?php if (empty($cf7_forms)): ?>
                                    <option value="">No Contact Form 7 forms found</option>
                                <?php else: ?>
                                    <?php foreach ($cf7_forms as $form): ?>
                                        <option value="<?php echo esc_attr($form['id']); ?>" <?php selected($form_id, $form['id']); ?>>
                                            <?php echo esc_html($form['title']); ?> (ID: <?php echo $form['id']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <p class="description">Select the Contact Form 7 form that grants portal access.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="tva_access_duration">Access Duration</label>
                        </th>
                        <td>
                            <input type="number" name="tva_access_duration" id="tva_access_duration" 
                                   value="<?php echo esc_attr($access_duration); ?>" class="small-text" min="1" max="365" /> days
                            <p class="description">How long users can access content (1-365 days). Current: <?php echo $access_duration; ?> days.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="tva_redirect_url">Redirect URL</label>
                        </th>
                        <td>
                            <input type="url" name="tva_redirect_url" id="tva_redirect_url" 
                                   value="<?php echo esc_attr($redirect_url); ?>" class="large-text" />
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
                                    <input type="checkbox" name="tva_enable_localStorage" value="1" 
                                           <?php checked($enable_localStorage); ?> />
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
                                    <input type="checkbox" name="tva_enable_email_notifications" value="1" 
                                           <?php checked($enable_email_notifications); ?> />
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
                        [protected_content content_ids="item1,item2"]
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
                
                <!-- Help & Support -->
                <div style="background: linear-gradient(135deg, #7216f4, #8f47f6); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(114, 22, 244, 0.3);">
                    <h3 style="color: white; margin-top: 0;">Need Help?</h3>
                    <p style="margin-bottom: 15px; opacity: 0.9;">Having issues with the portal access system?</p>
                    
                    <a href="mailto:hello@realtreasury.com?subject=Portal Access Plugin Support" 
                       style="display: inline-block; background: rgba(255, 255, 255, 0.2); color: white; padding: 10px 15px; border-radius: 6px; text-decoration: none; margin-bottom: 10px; border: 1px solid rgba(255, 255, 255, 0.3);">
                        📧 Contact Support
                    </a>
                    
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255, 255, 255, 0.2);">
                        <p style="margin: 0; font-size: 13px; opacity: 0.8;">
                            <strong>Plugin by Real Treasury</strong><br>
                            For Treasury Technology Solutions
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </form>
    
    <!-- Testing Section -->
    <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 8px; margin-top: 30px;">
        <h3 style="color: #856404; margin-top: 0;">🧪 Testing & Troubleshooting</h3>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
            <div>
                <h4>Test Form Submission</h4>
                <p>Use your form with test data to verify the system works.</p>
                <a href="<?php echo home_url(); ?>" class="button">Visit Frontend</a>
            </div>
            
            <div>
                <h4>Clear Test Data</h4>
                <p>Remove test submissions from the database.</p>
                <button type="button" onclick="confirmClearTestData()" class="button">Clear Test Users</button>
            </div>
            
            <div>
                <h4>Check Logs</h4>
                <p>View WordPress error logs for debugging.</p>
                <a href="<?php echo admin_url('tools.php'); ?>" class="button">WordPress Tools</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmClearTestData() {
    if (confirm('⚠️ This will delete ALL test users from the database. This action cannot be undone.\n\nAre you sure you want to continue?')) {
        // You could add an AJAX call here to clear test data
        alert('Feature coming soon! For now, you can manually delete test entries from the Users page.');
    }
}

// Add some helpful validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const durationInput = document.getElementById('tva_access_duration');
    const redirectInput = document.getElementById('tva_redirect_url');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            // Validate access duration
            const duration = parseInt(durationInput.value);
            if (duration < 1 || duration > 365) {
                alert('Access duration must be between 1 and 365 days.');
                e.preventDefault();
                return;
            }
            
            // Validate redirect URL
            if (redirectInput.value && !redirectInput.value.match(/^https?:\/\/.+/)) {
                alert('Please enter a valid URL starting with http:// or https://');
                e.preventDefault();
                return;
            }
            
            console.log('✅ Settings form validation passed');
        });
    }
    
    console.log('✅ Treasury Portal Access Settings loaded');
});
</script>

<style>
.form-table th {
    width: 200px;
    font-weight: 600;
}

.form-table td {
    vertical-align: top;
    padding-top: 20px;
}

.form-table input[type="number"],
.form-table input[type="url"],
.form-table select {
    font-size: 14px;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.form-table .description {
    color: #666;
    font-style: italic;
    margin-top: 5px;
}

code {
    word-break: break-all;
}
</style>
