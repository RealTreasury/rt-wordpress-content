<?php
/**
 * Plugin Name: Username Leaderboard
 * Plugin URI: https://realtreasury.com
 * Description: Display a leaderboard of all registered users.
 * Version: 1.0.0
 * Author: Real Treasury
 * License: GPL v2 or later
 * Text Domain: username-leaderboard
 */

if (!defined('ABSPATH')) {
    exit;
}

function ulb_enqueue_styles() {
    $css_file = plugin_dir_path(__FILE__) . 'username-leaderboard.css';
    $version  = file_exists($css_file) ? filemtime($css_file) : '1.0.0';
    wp_enqueue_style('username-leaderboard', plugin_dir_url(__FILE__) . 'username-leaderboard.css', array(), $version);
}

function ulb_get_users_table() {
    $users = get_users(array(
        'orderby' => 'registered',
        'order'   => 'DESC',
        'fields'  => array('user_login', 'user_registered'),
    ));

    if (empty($users)) {
        return '<p>No users found.</p>';
    }

    $output = '<table class="username-leaderboard"><thead><tr><th>Rank</th><th>Username</th><th>Registered</th></tr></thead><tbody>';
    $rank = 1;
    foreach ($users as $user) {
        $output .= '<tr>';
        $output .= '<td>' . esc_html($rank) . '</td>';
        $output .= '<td>' . esc_html($user->user_login) . '</td>';
        $output .= '<td>' . esc_html(date_i18n(get_option('date_format'), strtotime($user->user_registered))) . '</td>';
        $output .= '</tr>';
        $rank++;
    }
    $output .= '</tbody></table>';

    return $output;
}

function ulb_shortcode() {
    ulb_enqueue_styles();
    return ulb_get_users_table();
}
add_shortcode('username_leaderboard', 'ulb_shortcode');
