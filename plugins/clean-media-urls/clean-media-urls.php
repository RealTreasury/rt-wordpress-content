<?php
/**
 * Plugin Name:       Clean Media URLs
 * Plugin URI:        https://realtreasury.com
 * Description:       Rewrites media library file URLs to a cleaner /downloads/filename structure and handles the requests.
 * Version:           2.4.2
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
 * This version uses get_posts with 'suppress_filters' => true to bypass potential
 * conflicts with media library filtering plugins.
 *
 * @return array The map of filenames to filepaths.
 */
function cmu_get_media_map() {
    $transient_key = 'cmu_media_map_v7'; // Increment version to force rebuild

    // Add a manual cache-breaking mechanism for debugging.
    if (isset($_GET['rebuild_media_map']) && current_user_can('manage_options')) {
        delete_transient($transient_key);
    }

    $map = get_transient($transient_key);

    if (false === $map) {
        $map = [];

        // Use get_posts with 'suppress_filters' => true to ensure we get an unfiltered list of all attachments.
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
                    $upload_dir = wp_get_upload_dir();
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

        // Cache the newly built map for 1 hour.
        set_transient($transient_key, $map, HOUR_IN_SECONDS);
    }
    return $map;
}

/**
 * Deletes the cached media map. This is called when files are added or removed.
 */
function cmu_delete_media_map_cache() {
    delete_transient('cmu_media_map_v7');
}
// Hooks to clear the cache automatically on media library changes.
add_action('add_attachment', 'cmu_delete_media_map_cache');
add_action('delete_attachment', 'cmu_delete_media_map_cache');
add_action('edit_attachment', 'cmu_delete_media_map_cache');

/**
 * Intercepts requests for /downloads/ URLs at an early stage, finds the file, and serves it.
 * This new architecture inspects the request URI directly, bypassing the rewrite system
 * entirely to avoid conflicts with themes or other plugins that heavily modify URL rewrites.
 */
function cmu_direct_request_handler() {
    // Get the request path, stripping any query strings.
    $request_uri = strtok($_SERVER['REQUEST_URI'], '?');

    // Check if the request is for our /downloads/ URL structure.
    if (preg_match('#^/downloads/([^/]+)/?$#', $request_uri, $matches)) {
        $requested_filename = $matches[1];

        // DEBUG: Log what we're looking for
        error_log('Clean Media URLs: Looking for file: ' . $requested_filename);
        error_log('Clean Media URLs: Request URI: ' . $request_uri);

        $media_map = cmu_get_media_map();
        $filepath = isset($media_map[$requested_filename]) ? $media_map[$requested_filename] : false;
        $cache_status = 'HIT';

        // DEBUG: Log media map info
        error_log('Clean Media URLs: Media map has ' . count($media_map) . ' files');
        error_log('Clean Media URLs: File found in map: ' . ($filepath ? 'YES - ' . $filepath : 'NO'));

        // If the file isn't in our map, the map might be stale.
        if (!$filepath) {
            cmu_delete_media_map_cache();
            $media_map = cmu_get_media_map();
            $filepath = isset($media_map[$requested_filename]) ? $media_map[$requested_filename] : false;
            $cache_status = 'MISS_REBUILT';
            error_log('Clean Media URLs: After rebuild - File found: ' . ($filepath ? 'YES - ' . $filepath : 'NO'));
        }

        if ($filepath && file_exists($filepath)) {
            error_log('Clean Media URLs: Serving file: ' . $filepath);
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

        // DEBUG: More detailed error info
        error_log('Clean Media URLs: File not found or doesnt exist. Filepath: ' . ($filepath ?: 'NULL') . ', Exists: ' . (file_exists($filepath ?: '') ? 'YES' : 'NO'));

        status_header(404);
        exit('<!-- Clean Media URLs Debug: File "' . esc_html($requested_filename) . '" not found. Cache status: ' . $cache_status . '. -->');
    }
}
// Hook into 'init' at the earliest possible priority to catch the request before conflicts.
add_action('init', 'cmu_direct_request_handler', 1);

/**
 * Filters the attachment URL to use the clean /downloads/ format.
 */
function cmu_clean_attachment_url($url, $post_id) {
    $uploads_dir = wp_get_upload_dir();
    if (strpos($url, $uploads_dir['baseurl']) !== false) {
        $filename = basename($url);
        // Generate a clean URL without a trailing slash, as we are no longer using endpoints.
        return home_url('/downloads/' . $filename);
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
            $image[0] = home_url('/downloads/' . $filename);
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
                $sources[$width]['url'] = home_url('/downloads/' . $filename);
            }
        }
    }
    return $sources;
}

/**
 * Handle filename conflicts by ensuring unique filenames
 * This function checks for duplicate filenames and adds a suffix if needed
 */
function cmu_handle_filename_conflicts($filename, $filepath) {
    $media_map = cmu_get_media_map();
    
    // If filename already exists and points to a different file, we need to handle the conflict
    if (isset($media_map[$filename]) && $media_map[$filename] !== $filepath) {
        $info = pathinfo($filename);
        $name = $info['filename'];
        $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
        $counter = 1;
        
        do {
            $new_filename = $name . '-' . $counter . $ext;
            $counter++;
        } while (isset($media_map[$new_filename]) && $media_map[$new_filename] !== $filepath);
        
        return $new_filename;
    }
    
    return $filename;
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
    // Clear any existing cache on activation
    cmu_delete_media_map_cache();
    
    // Create the media map to ensure it's ready
    cmu_get_media_map();
}
register_activation_hook(__FILE__, 'cmu_activate');

/**
 * Plugin deactivation hook
 */
function cmu_deactivate() {
    // Clean up transients on deactivation
    cmu_delete_media_map_cache();
}
register_deactivation_hook(__FILE__, 'cmu_deactivate');
