<?php
/**
 * Plugin Name: Clean Media URLs
 * Plugin URI: https://realtreasury.com
 * Description: Sanitizes media file names on upload so URLs contain only lowercase alphanumeric characters and hyphens.
 * Version: 1.0.0
 * Author: Real Treasury
 * Author URI: https://realtreasury.com
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function cmu_clean_filename($filename) {
    $info = pathinfo($filename);
    $ext  = isset($info['extension']) ? '.' . $info['extension'] : '';
    $name = basename($filename, $ext);

    // Remove accents and convert to ASCII
    $name = remove_accents($name);

    // Replace spaces and underscores with hyphens
    $name = preg_replace('/[\s_]+/', '-', $name);

    // Remove any character that is not alphanumeric or hyphen
    $name = preg_replace('/[^A-Za-z0-9-]/', '', $name);

    // Consolidate multiple hyphens and trim edges
    $name = trim(preg_replace('/-+/', '-', $name), '-');

    // Lowercase for consistency
    $name = strtolower($name);

    return $name . $ext;
}
add_filter('sanitize_file_name', 'cmu_clean_filename');
