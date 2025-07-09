<?php
/**
 * Plugin Name:       Clean Media URLs
 * Plugin URI:        https://realtreasury.com
 * Description:       Rewrites media library file URLs to a cleaner /media/filename structure and handles the requests.
 * Version:           2.4.0
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
    $transient_key = 'cmu_media_map_v6'; // New version to force cache rebuild

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
            'suppress_filters' => true, // This explicitly tells WordPress to ignore other plugin filters.
        ]);

        if ($attachments) {
            foreach ($attachments as $attachment) {
                $attached_file = get_attached_file($attachment->ID);
                $meta = wp_get_attachment_metadata($attachment->ID);
                
                if (empty($attached_file) || !file_exists($attached_file)) {
                    continue;
                }

                // Add the full-sized original file to the map.
                $map[basename($attached_file)] = $attached_file;

                // Add all generated thumbnail sizes to the map.
                if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
                    $dir = dirname($attached_file);
                    foreach ($meta['sizes'] as $size_info) {
                        $thumbnail_path = trailingslashit($dir) . $size_info['file'];
                        if (file_exists($thumbnail_path)) {
                           $map[$size_info['file']] = $thumbnail_path;
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
    delete_transient('cmu_media_map_v6');
}
// Hooks to clear the cache automatically on media library changes.
add_action('add_attachment', 'cmu_delete_media_map_cache');
add_action('delete_attachment', 'cmu_delete_media_map_cache');
add_action('edit_attachment', 'cmu_delete_media_map_cache');


/**
 * Intercepts requests for /media/ URLs at an early stage, finds the file, and serves it.
 * This new architecture inspects the request URI directly, bypassing the rewrite system
 * entirely to avoid conflicts with themes or other plugins that heavily modify URL rewrites.
 */
function cmu_direct_request_handler() {
    // Get the request path, stripping any query strings.
    $request_uri = strtok($_SERVER['REQUEST_URI'], '?');

    // Check if the request is for our /media/ URL structure.
    // This regex handles an optional trailing slash.
    if (preg_match('#^/media/([^/]+)/?$#', $request_uri, $matches)) {
        $requested_filename = $matches[1];

        $media_map = cmu_get_media_map();
        $filepath = isset($media_map[$requested_filename]) ? $media_map[$requested_filename] : false;
        $cache_status = 'HIT';

        // If the file isn't in our map, the map might be stale.
        // As a fallback, we'll rebuild the cache and try one more time.
        if (!$filepath) {
            cmu_delete_media_map_cache();
            $media_map = cmu_get_media_map();
            $filepath = isset($media_map[$requested_filename]) ? $media_map[$requested_filename] : false;
            $cache_status = 'MISS_REBUILT';
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

        // If we still can't find it, trigger a 404 error with a detailed debug message.
        status_header(404);
        // This HTML comment will be invisible to users but helpful for debugging.
        exit('<!-- Clean Media URLs Debug: File "' . esc_html($requested_filename) . '" not found. Cache status: ' . $cache_status . '. -->');
    }
}
// Hook into 'init' at the earliest possible priority to catch the request before conflicts.
add_action('init', 'cmu_direct_request_handler', 1);

/**
 * Filters the attachment URL to use the clean /media/ format.
 */
function cmu_clean_attachment_url($url, $post_id) {
    $uploads_dir = wp_get_upload_dir();
    if (strpos($url, $uploads_dir['baseurl']) !== false) {
        $filename = basename($url);
        // Generate a clean URL without a trailing slash, as we are no longer using endpoints.
        return home_url('/media/' . $filename);
    }
    return $url;
}
add_filter('wp_get_attachment_url', 'cmu_clean_attachment_url', 10, 2);

// NOTE: All rewrite rule and endpoint functions have been removed as they are no longer needed with this direct-intercept method.
// The activate/deactivate hooks are no longer necessary as we don't need to flush rewrite rules.
