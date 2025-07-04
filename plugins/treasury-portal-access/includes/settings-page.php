<?php
/**
 * Settings Page for Treasury Portal Access
 */
if (!defined('ABSPATH')) exit;

// Get current settings
$form_id = get_option('tpa_form_id');
$access_duration = get_option('tpa_access_duration', 180);
$redirect_url = get_option('tpa_redirect_url', home_url('/treasury-tech-portal/'));
$enable_localStorage = get_option('tpa_enable_localStorage', true);

// Get available Contact Form 7 forms
$cf7_forms = [];
if (class_exists('WPCF7_ContactForm')) {
    $forms = WPCF7_ContactForm::find();
    if ($forms) {
        foreach ($forms as $form) {
            $cf7_forms[] = ['id' => $form->id(), 'title' => $form->title()];
        }
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
            <div style="background: white; padding: 20px 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <h2 style="color: #7216f4; margin-top: 0;">Core Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="tpa_form_id">Portal Access Form</label></th>
                        <td>
                            <select name="tpa_form_id" id="tpa_form_id" class="regular-text" required>
                                <option value="">— Select a Form —</option>
                                <?php if (empty($cf7_forms)): ?>
                                    <option value="" disabled>No Contact Form 7 forms found.</option>
                                <?php else: ?>
                                    <?php foreach ($cf7_forms as $form): ?>
                                        <option value="<?php echo esc_attr($form['id']); ?>" <?php selected($form_id, $form['id']); ?>>
                                            <?php echo esc_html($form['title']); ?> (ID: <?php echo esc_attr($form['id']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <p class="description">Select the form that will grant users portal access.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpa_access_duration">Access Duration</label></th>
                        <td>
                            <input type="number" name="tpa_access_duration" id="tpa_access_duration" value="<?php echo esc_attr($access_duration); ?>" class="small-text" min="1" max="365" /> days
                            <p class="description">How long users can access content (Default: 180).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpa_redirect_url">Redirect URL</label></th>
                        <td>
                            <input type="url" name="tpa_redirect_url" id="tpa_redirect_url" value="<?php echo esc_attr($redirect_url); ?>" class="large-text" placeholder="https://your-site.com/portal-page" required />
                            <p class="description">Page to redirect users to after successful submission.</p>
                        </td>
                    </tr>
                </table>
                <h3 style="color: #7216f4;">Advanced Features</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Persistence Backup</th>
                        <td>
                            <label><input type="checkbox" name="tpa_enable_localStorage" value="1" <?php checked($enable_localStorage, 1); ?> /> Enable localStorage backup</label>
                            <p class="description">Helps restore access if cookies are cleared. Recommended.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings', 'primary large'); ?>
            </div>
            <div>
                <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                    <h3 style="color: #7216f4; margin-top: 0;">Plugin Status</h3>
                    <?php global $wpdb; ?>
                    <p><strong>Version:</strong> <span style="color: #4CAF50;">v<?php echo TPA_VERSION; ?></span></p>
                    <p><strong>Contact Form 7:</strong> <?php echo class_exists('WPCF7') ? '<span style="color: #4CAF50;">✅ Active</span>' : '<span style="color: #f44336;">❌ Missing</span>'; ?></p>
                    <p><strong>Database Table:</strong> <?php echo $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}portal_access_users'") ? '<span style="color: #4CAF50;">✅ Ready</span>' : '<span style="color: #f44336;">❌ Missing</span>'; ?></p>
                    <p><strong>Total Users:</strong> <span style="color: #7216f4; font-weight: bold;"><?php echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}portal_access_users"); ?></span></p>
                </div>
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                    <h3 style="color: #7216f4; margin-top: 0;">Shortcode Reference</h3>
                    <p><strong>Protected Content:</strong><br><code>[protected_content]...[/protected_content]</code></p>
                    <p><strong>Portal Button:</strong><br><code>[portal_button text="Access Portal"]</code></p>
                    <p><strong>Manual Trigger:</strong><br><code>&lt;a href="#openPortalModal"&gt;Open Modal&lt;/a&gt;</code></p>
                </div>
            </div>
        </div>
    </form>
</div>
<style>
/* ... same styles as original ... */
</style>
