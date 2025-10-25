<?php
/**
 * Plugin Name:       Clean Media URLs
 * Plugin URI:        https://realtreasury.com
 * Description:       Rewrites media library file URLs to a cleaner structure and handles the requests.
 * Version:           2.5.0
 * Author:            Gemini
 * Author URI:        https://gemini.google.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       clean-media-urls
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds and caches a map of all media filenames to their server filepaths.
 */
function cmu_get_media_map() {
    $transient_key = 'cmu_media_map_v8';

    if (isset($_GET['rebuild_media_map']) && current_user_can('manage_options')) {
        delete_transient($transient_key);
    }

    $map = get_transient($transient_key);

    if (false === $map) {
        $map = [];

        $attachments = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'suppress_filters' => true,
        ]);

        if ($attachments) {
            foreach ($attachments as $attachment) {
                $attached_file = get_attached_file($attachment->ID);
                
                if (empty($attached_file) || !file_exists($attached_file)) {
                    continue;
                }

                // Add the full-sized original file to the map.
                $map[basename($attached_file)] = $attached_file;

                // Get attachment metadata for thumbnail sizes
                $meta = wp_get_attachment_metadata($attachment->ID);
                
                // Add all generated thumbnail sizes to the map.
                if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
                    $dir = dirname($attached_file);
                    
                    foreach ($meta['sizes'] as $size_name => $size_info) {
                        if (isset($size_info['file'])) {
                            $thumbnail_path = trailingslashit($dir) . $size_info['file'];
                            if (file_exists($thumbnail_path)) {
                                $map[$size_info['file']] = $thumbnail_path;
                            }
                        }
                    }
                }
            }
        }
        
        set_transient($transient_key, $map, HOUR_IN_SECONDS);
    }
    return $map;
}

/**
 * Deletes the cached media map.
 */
function cmu_delete_media_map_cache() {
    delete_transient('cmu_media_map_v8');
}

// Hooks to clear the cache automatically on media library changes.
add_action('add_attachment', 'cmu_delete_media_map_cache');
add_action('delete_attachment', 'cmu_delete_media_map_cache');
add_action('edit_attachment', 'cmu_delete_media_map_cache');

/**
 * ✅ SECURITY FIX: Check rate limiting for file downloads
 */
function cmu_is_rate_limited() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'cmu_rate_limit_' . md5($ip);
    $downloads = get_transient($key);

    // Allow 30 file downloads per minute per IP
    if ($downloads && $downloads >= 30) {
        error_log('CMU: Rate limit exceeded for IP ' . $ip);
        return true;
    }

    set_transient($key, ($downloads + 1), MINUTE_IN_SECONDS);
    return false;
}

/**
 * Handles file serving requests using query parameters
 * ✅ SECURITY FIX: Added rate limiting
 */
function cmu_query_request_handler() {
    if (isset($_GET['cmu_file'])) {
        // ✅ SECURITY FIX: Rate limiting check FIRST
        if (cmu_is_rate_limited()) {
            status_header(429);
            exit('Too many requests. Please slow down.');
        }

        $requested_filename = sanitize_file_name($_GET['cmu_file']);

        $media_map = cmu_get_media_map();
        $filepath = isset($media_map[$requested_filename]) ? $media_map[$requested_filename] : false;

        // If not found, try rebuilding cache once
        if (!$filepath) {
            cmu_delete_media_map_cache();
            $media_map = cmu_get_media_map();
            $filepath = isset($media_map[$requested_filename]) ? $media_map[$requested_filename] : false;
        }

        if ($filepath && file_exists($filepath)) {
            $mime_type = mime_content_type($filepath);
            header('Content-Type: ' . $mime_type);
            header('Content-Length: ' . filesize($filepath));
            header('Content-Disposition: inline; filename="' . basename($requested_filename) . '"');
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + (60 * 60 * 24 * 365)) . ' GMT');
            header('Cache-Control: public, max-age=31536000');

            while (ob_get_level()) {
                ob_end_clean();
            }
            readfile($filepath);
            exit;
        }

        status_header(404);
        exit('File not found: ' . esc_html($requested_filename));
    }
}
add_action('init', 'cmu_query_request_handler', 1);

/**
 * Filters the attachment URL to use the clean query parameter format.
 */
function cmu_clean_attachment_url($url, $post_id) {
    $uploads_dir = wp_get_upload_dir();
    if (strpos($url, $uploads_dir['baseurl']) !== false) {
        $filename = basename($url);
        return home_url('/?cmu_file=' . urlencode($filename));
    }
    return $url;
}
add_filter('wp_get_attachment_url', 'cmu_clean_attachment_url', 10, 2);
add_filter('wp_get_attachment_image_src', 'cmu_clean_attachment_image_src', 10, 4);
add_filter('wp_get_attachment_thumb_url', 'cmu_clean_attachment_url', 10, 2);
add_filter('wp_calculate_image_srcset', 'cmu_clean_srcset_urls', 10, 5);

/**
 * Filters the attachment image source array to use clean URLs
 */
function cmu_clean_attachment_image_src($image, $attachment_id, $size, $icon) {
    if ($image && is_array($image)) {
        $uploads_dir = wp_get_upload_dir();
        if (strpos($image[0], $uploads_dir['baseurl']) !== false) {
            $filename = basename($image[0]);
            $image[0] = home_url('/?cmu_file=' . urlencode($filename));
        }
    }
    return $image;
}

/**
 * Filters srcset URLs to use clean format
 */
function cmu_clean_srcset_urls($sources, $size_array, $image_src, $image_meta, $attachment_id) {
    if (is_array($sources)) {
        $uploads_dir = wp_get_upload_dir();
        foreach ($sources as $width => $source) {
            if (strpos($source['url'], $uploads_dir['baseurl']) !== false) {
                $filename = basename($source['url']);
                $sources[$width]['url'] = home_url('/?cmu_file=' . urlencode($filename));
            }
        }
    }
    return $sources;
}

/**
 * Add debug information for administrators
 */
function cmu_add_debug_info() {
    if (current_user_can('manage_options') && isset($_GET['debug_media_urls'])) {
        $media_map = cmu_get_media_map();
        echo '<div style="background: #fff; padding: 20px; margin: 20px; border: 1px solid #ccc;">';
        echo '<h3>Clean Media URLs Debug Information</h3>';
        echo '<p>Total files in map: ' . count($media_map) . '</p>';
        echo '<p>Add ?rebuild_media_map=1 to force cache rebuild</p>';
        echo '<details><summary>Media Map (click to expand)</summary>';
        echo '<pre>' . print_r($media_map, true) . '</pre>';
        echo '</details>';
        echo '</div>';
    }
}
add_action('wp_footer', 'cmu_add_debug_info');
add_action('admin_footer', 'cmu_add_debug_info');

/**
 * Plugin activation hook
 */
function cmu_activate() {
    cmu_delete_media_map_cache();
    cmu_get_media_map();
}
register_activation_hook(__FILE__, 'cmu_activate');

/**
 * Plugin deactivation hook
 */
function cmu_deactivate() {
    cmu_delete_media_map_cache();
}
register_deactivation_hook(__FILE__, 'cmu_deactivate');
