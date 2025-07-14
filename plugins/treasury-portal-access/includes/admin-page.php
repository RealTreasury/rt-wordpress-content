<?php
/**
 * Admin Page for Treasury Portal Access
 */
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_name = $wpdb->prefix . 'portal_access_users';
$attempt_table = $wpdb->prefix . 'portal_access_attempts';
$users = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY access_granted DESC");
$total_users = is_array($users) ? count($users) : 0;
$total_attempts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$attempt_table}");

$total_interactions = $total_users + $total_attempts;
$completion_rate = $total_interactions > 0 ? round(($total_users / $total_interactions) * 100) : 0;
$abandon_rate = $total_interactions > 0 ? round(($total_attempts / $total_interactions) * 100) : 0;

// Weekly abandonment rates
$current_attempts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$attempt_table} WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$current_completions = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE access_granted >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$current_total = $current_attempts + $current_completions;
$current_abandon_rate = $current_total > 0 ? round(($current_attempts / $current_total) * 100) : 0;

$previous_attempts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$attempt_table} WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND attempted_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
$previous_completions = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE access_granted >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND access_granted < DATE_SUB(NOW(), INTERVAL 7 DAY)");
$previous_total = $previous_attempts + $previous_completions;
$previous_abandon_rate = $previous_total > 0 ? round(($previous_attempts / $previous_total) * 100) : 0;

// Weekly user counts
$current_week_users = $current_completions;
$previous_week_users = $previous_completions;

// Handle CSV export
if (isset($_GET['action'], $_GET['_wpnonce']) && $_GET['action'] === 'export' && wp_verify_nonce($_GET['_wpnonce'], 'tpa_export_nonce')) {
    if (current_user_can('manage_options')) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="portal-access-users-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Name', 'Email', 'Company', 'Terms Agreed', 'Access Date', 'IP Address']);
        
        if ($users) {
            foreach ($users as $user) {
                $time = new DateTime($user->access_granted, new DateTimeZone('UTC'));
                $time->setTimeZone(new DateTimeZone('America/New_York'));
                $formatted = $time->format('M j, Y, g:i A') . ' ET';
                fputcsv($output, [
                    $user->full_name,
                    $user->email,
                    $user->company ?: 'N/A',
                    ($user->terms_agreement === 'yes' ? 'Yes' : 'No'),
                    $formatted,
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
            <h3 style="margin: 0 0 10px; font-size: 2rem; color: white;"><?php echo (int) $current_week_users; ?></h3>
            <p style="margin: 0; opacity: 0.9;">Accesses (This Week)</p>
            <p style="margin: 0; opacity: 0.7; font-size: 0.9rem;">Last Week: <?php echo (int) $previous_week_users; ?></p>
        </div>
        
        <!-- Removed Terms Acceptance and Days Access metrics -->

        <div style="background: linear-gradient(135deg, #607D8B, #455A64); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(96, 125, 139, 0.3);">
            <h3 style="margin: 0 0 10px; font-size: 2rem; color: white;"><?php echo $total_attempts; ?></h3>
            <p style="margin: 0; opacity: 0.9;">Abandoned Attempts</p>
        </div>

        <div style="background: linear-gradient(135deg, #2196F3, #1E88E5); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(33, 150, 243, 0.3);">
            <h3 style="margin: 0 0 10px; font-size: 2rem; color: white;"><?php echo $completion_rate; ?>%</h3>
            <p style="margin: 0; opacity: 0.9;">Completion Rate</p>
        </div>

        <div style="background: linear-gradient(135deg, #f44336, #d32f2f); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(244, 67, 54, 0.3);">
            <h3 style="margin: 0 0 10px; font-size: 2rem; color: white;"><?php echo $current_abandon_rate; ?>%</h3>
            <p style="margin: 0; opacity: 0.9;">Abandon Rate (This Week)</p>
            <p style="margin: 0; opacity: 0.7; font-size: 0.9rem;">Last Week: <?php echo $previous_abandon_rate; ?>%</p>
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
                <td>
                    <?php
                        $time = new DateTime($user->access_granted, new DateTimeZone('UTC'));
                        $time->setTimeZone(new DateTimeZone('America/New_York'));
                        echo $time->format('M j, Y, g:i A') . ' ET';
                    ?>
                </td>
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
