<?php
/**
 * Admin Page for Treasury Portal Access
 */
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_name = $wpdb->prefix . 'portal_access_users';
$users = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY access_granted DESC");
$total_users = is_array($users) ? count($users) : 0;

// Handle CSV export
if (isset($_GET['action'], $_GET['_wpnonce']) && $_GET['action'] === 'export' && wp_verify_nonce($_GET['_wpnonce'], 'tpa_export_nonce')) {
    if (current_user_can('manage_options')) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="portal-access-users-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Name', 'Email', 'Company', 'Terms Agreed', 'Access Date', 'IP Address']);
        
        if ($users) {
            foreach ($users as $user) {
                fputcsv($output, [
                    $user->full_name,
                    $user->email,
                    $user->company ?: 'N/A',
                    ($user->terms_agreement === 'yes' ? 'Yes' : 'No'),
                    $user->access_granted,
                    $user->ip_address
                ]);
            }
        }
        
        fclose($output);
        exit;
    }
}
?>
<div class="wrap">
    <h1 style="color: #7216f4; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
        <span class="dashicons dashicons-building" style="font-size: 32px;"></span>
        Treasury Portal Access Users
        <span style="background: #7216f4; color: white; padding: 4px 12px; border-radius: 20px; font-size: 14px; font-weight: 500;"><?php echo (int) $total_users; ?> total</span>
    </h1>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div style="background: linear-gradient(135deg, #7216f4, #8f47f6); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(114, 22, 244, 0.3);">
            <h3 style="margin: 0 0 10px; font-size: 2rem; color: white;"><?php echo (int) $total_users; ?></h3>
            <p style="margin: 0; opacity: 0.9;">Total Users</p>
        </div>
        
        <div style="background: linear-gradient(135deg, #4CAF50, #45a049); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);">
            <?php $recent_users = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE access_granted >= DATE_SUB(NOW(), INTERVAL 7 DAY)"); ?>
            <h3 style="margin: 0 0 10px; font-size: 2rem; color: white;"><?php echo (int) $recent_users; ?></h3>
            <p style="margin: 0; opacity: 0.9;">This Week</p>
        </div>
        
        <div style="background: linear-gradient(135deg, #FF9800, #F57C00); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3);">
            <?php 
            $terms_agreed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE terms_agreement = 'yes'");
            $percentage = $total_users > 0 ? round(($terms_agreed / $total_users) * 100) : 0;
            ?>
            <h3 style="margin: 0 0 10px; font-size: 2rem; color: white;"><?php echo $percentage; ?>%</h3>
            <p style="margin: 0; opacity: 0.9;">Terms Acceptance</p>
        </div>
        
        <div style="background: linear-gradient(135deg, #9C27B0, #7B1FA2); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(156, 39, 176, 0.3);">
            <h3 style="margin: 0 0 10px; font-size: 2rem; color: white;"><?php echo (int) get_option('tpa_access_duration', 180); ?></h3>
            <p style="margin: 0; opacity: 0.9;">Days Access</p>
        </div>
    </div>

    <?php if ($users): ?>
    <div style="background: #f9f9f9; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #7216f4;">
        <h3 style="margin-top: 0; color: #7216f4;">Quick Actions</h3>
        <?php $export_url = wp_nonce_url(admin_url('admin.php?page=treasury-portal-access&action=export'), 'tpa_export_nonce'); ?>
        <a href="<?php echo esc_url($export_url); ?>" class="button button-primary">📊 Export to CSV</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=treasury-portal-access-settings')); ?>" class="button">⚙️ Plugin Settings</a>
    </div>

    <table class="wp-list-table widefat fixed striped tpa-table">
        <thead><tr><th>Name</th><th>Email</th><th>Company</th><th>Terms Agreed</th><th>Access Date</th></tr></thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><strong><?php echo esc_html($user->full_name); ?></strong></td>
                <td><a href="mailto:<?php echo esc_attr($user->email); ?>"><?php echo esc_html($user->email); ?></a></td>
                <td><?php echo esc_html($user->company ?: '—'); ?></td>
                <td>
                    <?php if ($user->terms_agreement === 'yes'): ?>
                        <span class="terms-yes">✅ Accepted</span>
                    <?php else: ?>
                        <span class="terms-no">❌ Not Accepted</span>
                    <?php endif; ?>
                </td>
                <td><?php echo date('M j, Y, g:i A', strtotime($user->access_granted)); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div style="text-align: center; padding: 3rem 2rem; background: #fff; border: 2px dashed #ddd; border-radius: 16px;">
        <div style="font-size: 48px; margin-bottom: 1rem;">🏛️</div>
        <h3 style="color: #281345; font-size: 1.5rem;">No users have requested portal access yet</h3>
        <p style="color: #666; margin-bottom: 1.5rem;">When users complete your selected form, they'll appear here.</p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=treasury-portal-access-settings')); ?>" class="button button-primary">⚙️ Configure Plugin Settings</a>
    </div>
    <?php endif; ?>
</div>
