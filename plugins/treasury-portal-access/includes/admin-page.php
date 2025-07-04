<?php
/**
 * Admin Page for Treasury Portal Access
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get users from database
global $wpdb;
$table_name = $wpdb->prefix . 'portal_access_users';
$users = $wpdb->get_results("SELECT * FROM $table_name ORDER BY access_granted DESC");
$total_users = count($users);

// Handle export
if (isset($_GET['action']) && $_GET['action'] === 'export' && current_user_can('manage_options')) {
    if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'tpa_export_nonce')) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="portal-access-users-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, array('Name', 'Email', 'Company', 'Terms Agreed', 'Access Date', 'IP Address'));
        
        foreach ($users as $user) {
            fputcsv($output, array(
                $user->full_name,
                $user->email,
                $user->company,
                $user->terms_agreement === 'yes' ? 'Yes' : 'No',
                $user->access_granted,
                $user->ip_address
            ));
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
        <span style="background: #7216f4; color: white; padding: 4px 12px; border-radius: 20px; font-size: 14px; font-weight: 500;"><?php echo $total_users; ?> total</span>
    </h1>

    <!-- Plugin Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div style="background: linear-gradient(135deg, #7216f4, #8f47f6); color: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 12px rgba(114, 22, 244, 0.3);">
            <h3 style="margin: 0 0 10px; font-size: 2rem; color: white;"><?php echo $total_users; ?></h3>
            <p style="margin: 0; opacity: 0.9;">Total Users</p>
        </div>
        
        <div style="background: linear-gradient(135deg, #4CAF50, #45a049); color: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);">
            <?php 
            $recent_users = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE access_granted >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            ?>
            <h3 style="margin: 0 0 10px; font-size: 2rem; color: white;"><?php echo $recent_users; ?></h3>
            <p style="margin: 0; opacity: 0.9;">This Week</p>
        </div>
        
        <div style="background: linear-gradient(135deg, #FF9800, #F57C00); color: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3);">
            <?php 
            $terms_agreed = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE terms_agreement = 'yes'");
            $percentage = $total_users > 0 ? round(($terms_agreed / $total_users) * 100) : 0;
            ?>
            <h3 style="margin: 0 0 10px; font-size: 2rem; color: white;"><?php echo $percentage; ?>%</h3>
            <p style="margin: 0; opacity: 0.9;">Terms Acceptance</p>
        </div>
        
        <div style="background: linear-gradient(135deg, #9C27B0, #7B1FA2); color: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 12px rgba(156, 39, 176, 0.3);">
            <h3 style="margin: 0 0 10px; font-size: 2rem; color: white;"><?php echo get_option('tpa_access_duration', 180); ?></h3>
            <p style="margin: 0; opacity: 0.9;">Days Access</p>
        </div>
    </div>

    <?php if ($users): ?>
    
    <!-- Quick Actions -->
    <div style="background: #f9f9f9; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #7216f4;">
        <h3 style="margin-top: 0; color: #7216f4;">Quick Actions</h3>
        <p style="margin-bottom: 15px;">Export your user data or access plugin settings.</p>
        <?php $export_url = wp_nonce_url(admin_url('admin.php?page=treasury-portal-access&action=export'), 'tpa_export_nonce'); ?>
        <a href="<?php echo esc_url($export_url); ?>" class="button button-primary">📊 Export to CSV</a>
        <a href="<?php echo admin_url('admin.php?page=treasury-portal-access-settings'); ?>" class="button">⚙️ Plugin Settings</a>
        <a href="<?php echo admin_url('admin.php?page=wpcf7'); ?>" class="button">📝 Contact Forms</a>
    </div>

    <!-- Users Table -->
    <style>
    .tpa-table { border: 2px solid #c77dff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(114, 22, 244, 0.1); background: white; }
    .tpa-table thead { background: linear-gradient(135deg, #7216f4 0%, #8f47f6 100%); }
    .tpa-table thead th { color: white !important; font-weight: 600 !important; padding: 15px 12px !important; text-shadow: 0 1px 2px rgba(0,0,0,0.2); border-bottom: none !important; font-size: 14px; }
    .tpa-table tbody tr:hover { background-color: #f8f9ff; }
    .tpa-table tbody td { padding: 12px !important; vertical-align: middle; border-left: none !important; }
    .user-name { font-weight: 600; color: #333; }
    .user-email { color: #666; font-size: 13px; }
    .company-name { color: #555; }
    .terms-yes { background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 600; }
    .terms-no { background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 600; }
    .access-date { color: #666; font-size: 13px; }
    </style>

    <table class="wp-list-table widefat fixed striped tpa-table">
        <thead>
            <tr>
                <th style="width: 20%;">Name</th>
                <th style="width: 25%;">Email</th>
                <th style="width: 20%;">Company</th>
                <th style="width: 15%;">Terms Agreed</th>
                <th style="width: 20%;">Access Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td>
                    <span class="user-name"><?php echo esc_html($user->full_name); ?></span>
                </td>
                <td>
                    <span class="user-email"><?php echo esc_html($user->email); ?></span>
                </td>
                <td>
                    <span class="company-name"><?php echo esc_html($user->company ?: '—'); ?></span>
                </td>
                <td>
                    <?php $terms_status = ($user->terms_agreement ?? 'no') === 'yes'; ?>
                    <span class="<?php echo $terms_status ? 'terms-yes' : 'terms-no'; ?>">
                        <?php echo $terms_status ? '✅ Accepted' : '❌ Not Accepted'; ?>
                    </span>
                </td>
                <td>
                    <span class="access-date">
                        <?php echo esc_html(date('M j, Y', strtotime($user->access_granted))); ?><br>
                        <small><?php echo esc_html(date('g:i A', strtotime($user->access_granted))); ?></small>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php else: ?>

    <!-- No Users State -->
    <div style="text-align: center; padding: 3rem 2rem; background: linear-gradient(135deg, #f8f8f8 0%, #ffffff 100%); border: 2px solid #c77dff; border-radius: 16px; margin-top: 2rem; box-shadow: 0 4px 20px rgba(114, 22, 244, 0.1);">
        <div style="font-size: 48px; margin-bottom: 1rem;">🏛️</div>
        <h3 style="color: #281345; margin-bottom: 1rem; font-size: 1.5rem;">No users have requested portal access yet</h3>
        <p style="color: #7e7e7e; margin-bottom: 1.5rem; font-size: 1.1rem;">When users complete your Portal Access Gate Form, they'll appear here.</p>
        <a href="<?php echo admin_url('admin.php?page=treasury-portal-access-settings'); ?>" class="button button-primary">⚙️ Configure Settings</a>
    </div>

    <?php endif; ?>
</div>
