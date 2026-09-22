<?php
/**
 * Astra functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Astra
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Define Constants
 */
define( 'ASTRA_THEME_VERSION', '4.13.4' );
define( 'ASTRA_THEME_SETTINGS', 'astra-settings' );
define( 'ASTRA_THEME_DIR', trailingslashit( get_template_directory() ) );
define( 'ASTRA_THEME_URI', trailingslashit( esc_url( get_template_directory_uri() ) ) );
define( 'ASTRA_THEME_ORG_VERSION', file_exists( ASTRA_THEME_DIR . 'inc/w-org-version.php' ) );

/**
 * Minimum Version requirement of the Astra Pro addon.
 * This constant will be used to display the notice asking user to update the Astra addon to the version defined below.
 */
define( 'ASTRA_EXT_MIN_VER', '4.12.0' );

/**
 * Load in-house compatibility.
 */
if ( ASTRA_THEME_ORG_VERSION ) {
	require_once ASTRA_THEME_DIR . 'inc/w-org-version.php';
}

/**
 * Setup helper functions of Astra.
 */
require_once ASTRA_THEME_DIR . 'inc/core/class-astra-theme-options.php';
require_once ASTRA_THEME_DIR . 'inc/core/class-theme-strings.php';
require_once ASTRA_THEME_DIR . 'inc/core/common-functions.php';
require_once ASTRA_THEME_DIR . 'inc/core/class-astra-icons.php';

define( 'ASTRA_WEBSITE_BASE_URL', 'https://wpastra.com' );

/**
 * Update theme
 */
require_once ASTRA_THEME_DIR . 'inc/theme-update/astra-update-functions.php';
require_once ASTRA_THEME_DIR . 'inc/theme-update/class-astra-theme-background-updater.php';

/**
 * Fonts Files
 */
require_once ASTRA_THEME_DIR . 'inc/customizer/class-astra-font-families.php';
if ( is_admin() ) {
	require_once ASTRA_THEME_DIR . 'inc/customizer/class-astra-fonts-data.php';
}

require_once ASTRA_THEME_DIR . 'inc/lib/webfont/class-astra-webfont-loader.php';
require_once ASTRA_THEME_DIR . 'inc/lib/docs/class-astra-docs-loader.php';
require_once ASTRA_THEME_DIR . 'inc/customizer/class-astra-fonts.php';

require_once ASTRA_THEME_DIR . 'inc/dynamic-css/custom-menu-old-header.php';
require_once ASTRA_THEME_DIR . 'inc/dynamic-css/container-layouts.php';
require_once ASTRA_THEME_DIR . 'inc/dynamic-css/astra-icons.php';
require_once ASTRA_THEME_DIR . 'inc/core/class-astra-walker-page.php';
require_once ASTRA_THEME_DIR . 'inc/core/class-astra-enqueue-scripts.php';
require_once ASTRA_THEME_DIR . 'inc/core/class-gutenberg-editor-css.php';
require_once ASTRA_THEME_DIR . 'inc/core/class-astra-wp-editor-css.php';
require_once ASTRA_THEME_DIR . 'inc/core/class-astra-command-palette.php';
require_once ASTRA_THEME_DIR . 'inc/dynamic-css/block-editor-compatibility.php';
require_once ASTRA_THEME_DIR . 'inc/dynamic-css/inline-on-mobile.php';
require_once ASTRA_THEME_DIR . 'inc/dynamic-css/content-background.php';
require_once ASTRA_THEME_DIR . 'inc/dynamic-css/dark-mode.php';
require_once ASTRA_THEME_DIR . 'inc/class-astra-dynamic-css.php';
require_once ASTRA_THEME_DIR . 'inc/class-astra-global-palette.php';

// Enable NPS Survey only if the starter templates version is < 4.3.7 or > 4.4.4 to prevent fatal error.
if ( ! defined( 'ASTRA_SITES_VER' ) || version_compare( ASTRA_SITES_VER, '4.3.7', '<' ) || version_compare( ASTRA_SITES_VER, '4.4.4', '>' ) ) {
	// NPS Survey Integration
	require_once ASTRA_THEME_DIR . 'inc/lib/class-astra-nps-notice.php';
	require_once ASTRA_THEME_DIR . 'inc/lib/class-astra-nps-survey.php';
}

/**
 * Custom template tags for this theme.
 */
require_once ASTRA_THEME_DIR . 'inc/core/class-astra-attr.php';
require_once ASTRA_THEME_DIR . 'inc/template-tags.php';

require_once ASTRA_THEME_DIR . 'inc/widgets.php';
require_once ASTRA_THEME_DIR . 'inc/core/theme-hooks.php';
require_once ASTRA_THEME_DIR . 'inc/admin-functions.php';
require_once ASTRA_THEME_DIR . 'inc/class-astra-memory-limit-notice.php';
require_once ASTRA_THEME_DIR . 'inc/core/sidebar-manager.php';

/**
 * Markup Functions
 */
require_once ASTRA_THEME_DIR . 'inc/markup-extras.php';
require_once ASTRA_THEME_DIR . 'inc/extras.php';
require_once ASTRA_THEME_DIR . 'inc/blog/blog-config.php';
require_once ASTRA_THEME_DIR . 'inc/blog/blog.php';
require_once ASTRA_THEME_DIR . 'inc/blog/single-blog.php';

/**
 * Markup Files
 */
require_once ASTRA_THEME_DIR . 'inc/template-parts.php';
require_once ASTRA_THEME_DIR . 'inc/class-astra-loop.php';
require_once ASTRA_THEME_DIR . 'inc/class-astra-mobile-header.php';

/**
 * Functions and definitions.
 */
require_once ASTRA_THEME_DIR . 'inc/class-astra-after-setup-theme.php';

// Required files.
require_once ASTRA_THEME_DIR . 'inc/core/class-astra-admin-helper.php';

require_once ASTRA_THEME_DIR . 'inc/schema/class-astra-schema.php';

/* Setup API */
require_once ASTRA_THEME_DIR . 'admin/includes/class-astra-learn.php';
require_once ASTRA_THEME_DIR . 'admin/includes/class-astra-api-init.php';

if ( is_admin() ) {
	/**
	 * Admin Menu Settings
	 */
	require_once ASTRA_THEME_DIR . 'inc/core/class-astra-admin-settings.php';
	require_once ASTRA_THEME_DIR . 'admin/class-astra-admin-loader.php';
	require_once ASTRA_THEME_DIR . 'inc/lib/astra-notices/class-bsf-admin-notices.php';
}

/**
 * BSF Analytics.
 */
require_once ASTRA_THEME_DIR . 'admin/class-astra-bsf-analytics.php';

/**
 * Metabox additions.
 */
require_once ASTRA_THEME_DIR . 'inc/metabox/class-astra-meta-boxes.php';
require_once ASTRA_THEME_DIR . 'inc/metabox/class-astra-meta-box-operations.php';
require_once ASTRA_THEME_DIR . 'inc/metabox/class-astra-elementor-editor-settings.php';

/**
 * Customizer additions.
 */
require_once ASTRA_THEME_DIR . 'inc/customizer/class-astra-customizer.php';

/**
 * Astra Modules.
 */
require_once ASTRA_THEME_DIR . 'inc/modules/posts-structures/class-astra-post-structures.php';
require_once ASTRA_THEME_DIR . 'inc/modules/related-posts/class-astra-related-posts.php';

/**
 * Compatibility
 */
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-gutenberg.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-jetpack.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/woocommerce/class-astra-woocommerce.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/edd/class-astra-edd.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/lifterlms/class-astra-lifterlms.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/learndash/class-astra-learndash.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-beaver-builder.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-bb-ultimate-addon.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-contact-form-7.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-visual-composer.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-site-origin.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-gravity-forms.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-bne-flyout.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-ubermeu.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-divi-builder.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-amp.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-yoast-seo.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/surecart/class-astra-surecart.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-starter-content.php';
require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-buddypress.php';
require_once ASTRA_THEME_DIR . 'inc/addons/transparent-header/class-astra-ext-transparent-header.php';
require_once ASTRA_THEME_DIR . 'inc/addons/breadcrumbs/class-astra-breadcrumbs.php';
require_once ASTRA_THEME_DIR . 'inc/addons/scroll-to-top/class-astra-scroll-to-top.php';
require_once ASTRA_THEME_DIR . 'inc/addons/heading-colors/class-astra-heading-colors.php';
require_once ASTRA_THEME_DIR . 'inc/builder/class-astra-builder-loader.php';

// Elementor Compatibility requires PHP 5.4 for namespaces.
if ( version_compare( PHP_VERSION, '5.4', '>=' ) ) {
	require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-elementor.php';
	require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-elementor-pro.php';
	require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-web-stories.php';
}

// Beaver Themer compatibility requires PHP 5.3 for anonymous functions.
if ( version_compare( PHP_VERSION, '5.3', '>=' ) ) {
	require_once ASTRA_THEME_DIR . 'inc/compatibility/class-astra-beaver-themer.php';
}

require_once ASTRA_THEME_DIR . 'inc/core/markup/class-astra-markup.php';

/**
 * Abilities API integration.
 */
require_once ASTRA_THEME_DIR . 'inc/abilities/bootstrap.php';

/**
 * Load deprecated functions
 */
require_once ASTRA_THEME_DIR . 'inc/core/deprecated/deprecated-filters.php';
require_once ASTRA_THEME_DIR . 'inc/core/deprecated/deprecated-hooks.php';
require_once ASTRA_THEME_DIR . 'inc/core/deprecated/deprecated-functions.php';

/**
 * ===============================================================
 * REAL TREASURY CUSTOM FUNCTIONS
 * ===============================================================
 * The functions below are custom additions for the Real Treasury
 * site and are preserved across Astra theme updates.
 */

// ===============================================================
// CONSENT MANAGEMENT
// ===============================================================
// Google Consent Mode v2. The defaults MUST be pushed into dataLayer
// before any Google tag loads, so this runs on wp_head at priority 1 --
// ahead of Site Kit's gtag/GTM snippets. Analytics storage defaults to
// denied; the banner grants it via a consent update.
//
// Consent is stored in a first-party cookie (rt_consent) rather than
// localStorage only, so PHP can read it and so the Cookie Policy can
// describe it truthfully.

if ( ! defined( 'RT_CONSENT_COOKIE' ) ) {
    define( 'RT_CONSENT_COOKIE', 'rt_consent' );
}

/**
 * Current analytics consent, read from the first-party cookie.
 *
 * @return bool True only when the visitor has actively granted analytics.
 */
function rt_has_analytics_consent() {
    if ( empty( $_COOKIE[ RT_CONSENT_COOKIE ] ) ) {
        return false;
    }
    $value = sanitize_text_field( wp_unslash( $_COOKIE[ RT_CONSENT_COOKIE ] ) );
    return ( 'analytics' === $value );
}

add_action( 'wp_head', 'rt_consent_mode_defaults', 1 );
function rt_consent_mode_defaults() {
    $analytics = rt_has_analytics_consent() ? 'granted' : 'denied';
    ?>
    <script id="rt-consent-defaults">
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('consent', 'default', {
        'ad_storage':              'denied',
        'ad_user_data':            'denied',
        'ad_personalization':      'denied',
        'personalization_storage': 'denied',
        'analytics_storage':       <?php echo wp_json_encode( $analytics ); ?>,
        'functionality_storage':   'granted',
        'security_storage':        'granted',
        'wait_for_update':         500
    });
    gtag('set', 'ads_data_redaction', true);
    gtag('set', 'url_passthrough', true);
    </script>
    <?php
}

/**
 * Jetpack ships two trackers we do not want:
 *
 *  - google-analytics: a second GA4 tag (G-6KLBPGHTSM) duplicating the
 *    Site Kit tag. It double-counts pageviews and ignores Consent Mode.
 *  - stats: Automattic's own pixel (stats.wp.com). Not Consent Mode aware,
 *    undisclosed in our policies, and redundant next to GA4 + Search Console.
 *
 * Both are removed unconditionally rather than varied on the consent cookie,
 * so page output stays cacheable.
 */
add_filter( 'jetpack_active_modules', 'rt_disable_jetpack_trackers' );
function rt_disable_jetpack_trackers( $modules ) {
    if ( ! is_array( $modules ) ) {
        return $modules;
    }
    return array_values( array_diff( $modules, array( 'google-analytics', 'stats' ) ) );
}

// Consent banner + preference panel.
add_action( 'wp_footer', 'rt_consent_banner' );
function rt_consent_banner() {
    ?>
    <style id="rt-consent-styles">
    .cookie-btn-manage{background:hsla(0,0%,100%,.14);color:#fff;border:1px solid hsla(0,0%,100%,.28)}
    .cookie-btn-manage:hover{background:hsla(0,0%,100%,.24);color:#fff}
    .rt-consent-panel{position:fixed;inset:0;z-index:99997;display:flex;align-items:center;
        justify-content:center;background:rgba(10,6,20,.6);padding:20px}
    .rt-consent-panel[hidden]{display:none}
    .rt-consent-panel__card{background:#fff;color:#281345;max-width:520px;width:100%;
        border-radius:14px;padding:28px;box-shadow:0 18px 50px rgba(0,0,0,.35);
        max-height:85vh;overflow:auto}
    .rt-consent-panel__card h2{font-size:1.25rem;margin:0 0 6px}
    .rt-consent-panel__card p{font-size:.92rem;line-height:1.55;color:#444;margin:0 0 18px}
    .rt-consent-row{display:flex;gap:12px;align-items:flex-start;padding:14px 0;
        border-top:1px solid rgba(40,19,69,.12)}
    .rt-consent-row input{margin-top:3px;width:16px;height:16px;flex:none}
    .rt-consent-row strong{display:block;font-size:.95rem}
    .rt-consent-row span{font-size:.85rem;color:#555;line-height:1.45}
    .rt-consent-panel__actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}
    .rt-consent-panel__actions .cookie-btn{margin-left:0}
    </style>
    <script id="rt-consent-banner">
    (function () {
        var COOKIE  = <?php echo wp_json_encode( RT_CONSENT_COOKIE ); ?>;
        var MAX_AGE = 60 * 60 * 24 * 180; // 180 days

        function readConsent() {
            var match = document.cookie.match(
                new RegExp('(?:^|;\\s*)' + COOKIE + '=([^;]*)')
            );
            return match ? decodeURIComponent(match[1]) : null;
        }

        function writeConsent(value) {
            var secure = (location.protocol === 'https:') ? '; Secure' : '';
            document.cookie = COOKIE + '=' + encodeURIComponent(value) +
                '; path=/; max-age=' + MAX_AGE + '; SameSite=Lax' + secure;
            // Our own record of when consent was given, for audit purposes.
            try {
                localStorage.setItem('rt_consent_at', new Date().toISOString());
            } catch (e) {}
        }

        function applyConsent(granted) {
            if (typeof window.gtag !== 'function') {
                window.dataLayer = window.dataLayer || [];
                window.gtag = function () { window.dataLayer.push(arguments); };
            }
            window.gtag('consent', 'update', {
                'analytics_storage': granted ? 'granted' : 'denied'
            });
            if (!granted) {
                clearAnalyticsCookies();
            }
        }

        function clearAnalyticsCookies() {
            var host = location.hostname;
            var scopes = ['', '; domain=' + host, '; domain=.' + host.replace(/^www\./, '')];
            document.cookie.split(';').forEach(function (raw) {
                var name = raw.split('=')[0].trim();
                if (!/^_ga|^_gid$|^_gat|^_gcl/.test(name)) { return; }
                scopes.forEach(function (scope) {
                    document.cookie = name +
                        '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/' + scope;
                });
            });
        }

        function decide(value) {
            writeConsent(value);
            applyConsent(value === 'analytics');
            hideBanner();
            closePanel();
        }

        // ---- banner ----------------------------------------------------
        function showBanner() {
            if (document.getElementById('cookieBanner')) { return; }
            var banner = document.createElement('div');
            banner.id = 'cookieBanner';
            banner.className = 'cookie-banner';
            banner.setAttribute('role', 'region');
            banner.setAttribute('aria-label', 'Cookie consent');
            banner.innerHTML =
                '<div class="banner-content">' +
                    '<div class="banner-text">' +
                        '<strong>We use analytics cookies</strong> ' +
                        'They tell us which pages people read. Nothing loads until you choose. ' +
                        'See our <a href="/cookie-policy/" style="color:#c77dff">Cookie Policy</a>.' +
                    '</div>' +
                    '<div class="banner-buttons">' +
                        '<button type="button" class="cookie-btn cookie-btn-accept" data-rt-consent="accept">Accept</button>' +
                        '<button type="button" class="cookie-btn cookie-btn-decline" data-rt-consent="reject">Reject</button>' +
                        '<button type="button" class="cookie-btn cookie-btn-manage" data-rt-consent="manage">Preferences</button>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(banner);
            banner.addEventListener('click', function (e) {
                var action = e.target.getAttribute('data-rt-consent');
                if (action === 'accept')      { decide('analytics'); }
                else if (action === 'reject') { decide('essential'); }
                else if (action === 'manage') { openPanel(); }
            });
            setTimeout(function () { banner.classList.add('show'); }, 100);
        }

        function hideBanner() {
            var banner = document.getElementById('cookieBanner');
            if (!banner) { return; }
            banner.classList.remove('show');
            setTimeout(function () {
                if (banner.parentNode) { banner.parentNode.removeChild(banner); }
            }, 300);
        }

        // ---- preference panel ------------------------------------------
        function buildPanel() {
            var panel = document.createElement('div');
            panel.className = 'rt-consent-panel';
            panel.id = 'rtConsentPanel';
            panel.hidden = true;
            panel.innerHTML =
                '<div class="rt-consent-panel__card" role="dialog" aria-modal="true" aria-labelledby="rtConsentTitle">' +
                    '<h2 id="rtConsentTitle">Cookie preferences</h2>' +
                    '<p>We keep this short because we only use two kinds of cookie.</p>' +
                    '<div class="rt-consent-row">' +
                        '<input type="checkbox" checked disabled aria-label="Essential cookies, always on">' +
                        '<label><strong>Essential</strong>' +
                        '<span>Needed for the site to work &mdash; security, spam filtering on forms, ' +
                        'and remembering this choice. Always on.</span></label>' +
                    '</div>' +
                    '<div class="rt-consent-row">' +
                        '<input type="checkbox" id="rtConsentAnalytics">' +
                        '<label for="rtConsentAnalytics"><strong>Analytics</strong>' +
                        '<span>Google Analytics 4, so we can see which pages get read. ' +
                        'Off unless you turn it on.</span></label>' +
                    '</div>' +
                    '<div class="rt-consent-panel__actions">' +
                        '<button type="button" class="cookie-btn cookie-btn-accept" data-rt-panel="save">Save choices</button>' +
                        '<button type="button" class="cookie-btn cookie-btn-decline" data-rt-panel="close">Cancel</button>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(panel);
            panel.addEventListener('click', function (e) {
                var action = e.target.getAttribute('data-rt-panel');
                if (action === 'save') {
                    decide(document.getElementById('rtConsentAnalytics').checked ? 'analytics' : 'essential');
                } else if (action === 'close' || e.target === panel) {
                    closePanel();
                }
            });
            return panel;
        }

        function openPanel() {
            var panel = document.getElementById('rtConsentPanel') || buildPanel();
            document.getElementById('rtConsentAnalytics').checked = (readConsent() === 'analytics');
            panel.hidden = false;
        }

        function closePanel() {
            var panel = document.getElementById('rtConsentPanel');
            if (panel) { panel.hidden = true; }
        }

        // Let any page open the preference panel. The Cookie Policy page
        // links to this, so the "manage your preferences" promise is real.
        window.rtOpenCookiePreferences = openPanel;

        function init() {
            if (!readConsent()) {
                showBanner();
            }
            document.addEventListener('click', function (e) {
                var trigger = e.target.closest && e.target.closest('[data-rt-cookie-preferences]');
                if (trigger) {
                    e.preventDefault();
                    openPanel();
                }
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
    </script>
    <?php
}

// Remove default Astra post footer elements
function remove_astra_default_footer() {
    if (is_single()) {
        // Remove post meta
        remove_action('astra_entry_after', 'astra_single_post_navigation_markup');

        // Remove author box if enabled
        remove_action('astra_entry_after', 'astra_author_box_markup');

        // Remove related posts if Pro version
        remove_action('astra_entry_after', 'astra_single_post_related_posts_markup');
    }
}
add_action('wp', 'remove_astra_default_footer');

// Remove default Astra post footer elements
function remove_astra_post_footer_elements() {
    if (is_single()) {
        // Remove the post navigation (Previous/Next post links)
        remove_action('astra_entry_after', 'astra_single_post_navigation_markup', 15);

        // Remove author box if it exists
        remove_action('astra_entry_after', 'astra_author_box_markup', 10);

        // Remove any existing related posts (if Astra Pro)
        remove_action('astra_entry_after', 'astra_single_post_related_posts_markup', 20);

        // Remove post meta from bottom if it exists
        remove_action('astra_entry_bottom', 'astra_entry_meta', 10);
    }
}
add_action('wp', 'remove_astra_post_footer_elements');

/**
 * Display related posts after the single post content.
 * Uses a simple slider layout with horizontal scrolling.
 */
function display_related_posts() {
    if ( ! is_single() ) {
        return;
    }

    global $post;

    $categories = get_the_category( $post->ID );
    if ( empty( $categories ) ) {
        return;
    }

    $category_ids = wp_list_pluck( $categories, 'term_id' );

    $related = new WP_Query(
        array(
            'post_type'      => 'post',
            'posts_per_page' => 6,
            'post__not_in'   => array( $post->ID ),
            'category__in'   => $category_ids,
            'orderby'        => 'rand',
            'meta_query'     => array(
                array(
                    'key'     => '_thumbnail_id',
                    'compare' => 'EXISTS',
                ),
            ),
        )
    );

    if ( $related->have_posts() ) {
        echo '<aside class="rt-related-posts">';
        echo '<h3 class="rt-related-heading">Related Posts</h3>';
        echo '<div class="rt-related-container">';

        while ( $related->have_posts() ) {
            $related->the_post();
            echo '<article class="rt-related-item">';
            if ( has_post_thumbnail() ) {
                echo '<a href="' . esc_url( get_permalink() ) . '" class="rt-related-thumb-link">';
                the_post_thumbnail( 'medium', array( 'class' => 'rt-related-thumb' ) );
                echo '</a>';
            }
            echo '<h4 class="rt-related-title"><a href="' . esc_url( get_permalink() ) . '">' . get_the_title() . '</a></h4>';
            echo '</article>';
        }

        echo '</div></aside>';
    }

    wp_reset_postdata();
}

// ===============================================================
// WordPress REST API Fixes for Insights Page - ENHANCED VERSION
// ===============================================================

// ✅ SECURITY FIX: Add REST API rate limiting
add_filter('rest_pre_dispatch', 'rt_rest_rate_limit', 10, 3);
function rt_rest_rate_limit($result, $server, $request) {
    // Only limit custom endpoints
    $route = $request->get_route();
    if (strpos($route, '/rt/v1/') !== 0) {
        return $result;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'rest_limit_' . md5($ip . $route);
    $requests = get_transient($key);

    // Allow 60 requests per minute per endpoint per IP
    if ($requests && $requests >= 60) {
        error_log('REST API Rate Limit: IP ' . $ip . ' exceeded limit on ' . $route);
        return new WP_Error(
            'rest_rate_limit',
            'Too many requests. Please slow down.',
            ['status' => 429]
        );
    }

    set_transient($key, ($requests + 1), MINUTE_IN_SECONDS);
    return $result;
}

// 1. Force enable REST API
add_filter('rest_enabled', '__return_true');
add_filter('rest_jsonp_enabled', '__return_true');

// 2. Ensure REST API is accessible to all users
add_filter('rest_authentication_errors', function($result) {
    // If a previous check has already determined access, respect it
    if (true === $result || is_wp_error($result)) {
        return $result;
    }

    // Allow all requests to proceed without authentication for public content
    return true;
});

/**
 * ===============================================================
 * SECURITY AUDIT FIX: SECURE CORS POLICY (FIX H-01)
 * ===============================================================
 */
add_action('rest_api_init', function() {
    // Remove default WordPress CORS filters
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');

    add_filter('rest_pre_serve_request', function( $value ) {
        $origin = get_http_origin();

        // Define a strict whitelist of allowed origins
        $allowed_origins = [
            'https://realtreasury.com',
            'https://www.realtreasury.com',
            'https://realtreasury.github.io',
        ];

        // Only allow requests from the whitelist
        if ( $origin && in_array( $origin, $allowed_origins, true ) ) {
            header( 'Access-Control-Allow-Origin: ' . $origin );
            header( 'Access-Control-Allow-Credentials: true' );
            header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
            header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce' );
            header( 'Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages' );
        }

        // Handle preflight OPTIONS request
        if ( 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
            status_header( 200 );
            exit();
        }

        return $value;
    });
}, 15);

// 4. Enhanced rewrite rules for custom API endpoints
add_action('init', function() {
    // Add multiple rewrite patterns for better compatibility
    add_rewrite_rule('^api/posts/?$', 'index.php?rest_route=/wp/v2/posts', 'top');
    add_rewrite_rule('^api/posts/([0-9]+)/?$', 'index.php?rest_route=/wp/v2/posts/$matches[1]', 'top');
    add_rewrite_rule('^api/categories/?$', 'index.php?rest_route=/wp/v2/categories', 'top');

    // Alternative endpoints
    add_rewrite_rule('^wp-api/posts/?$', 'index.php?rest_route=/wp/v2/posts', 'top');
    add_rewrite_rule('^rest/posts/?$', 'index.php?rest_route=/wp/v2/posts', 'top');

    // Check if rewrite rules need to be flushed
    $rules_version = get_option('rt_api_rules_version', '1.0');
    if (version_compare($rules_version, '1.1', '<')) {
        flush_rewrite_rules();
        update_option('rt_api_rules_version', '1.1');
    }
});

// 5. Custom REST API endpoint with better error handling and multiple formats
add_action('rest_api_init', function() {
    // Test endpoint
    register_rest_route('rt/v1', '/test', array(
        'methods' => 'GET',
        'callback' => function() {
            return array(
                'status' => 'success',
                'message' => 'Real Treasury REST API is working!',
                'timestamp' => current_time('mysql'),
                'posts_count' => wp_count_posts()->publish,
                'rest_url' => rest_url(),
                'home_url' => home_url(),
                'site_url' => site_url()
            );
        },
        'permission_callback' => 'rt_rest_permission_check'
    ));

    // Enhanced posts endpoint
    register_rest_route('rt/v1', '/posts', array(
        'methods' => 'GET',
        'callback' => function($request) {
            $per_page = max(1, min(100, intval($request->get_param('per_page') ?: 12)));
            $page = max(1, intval($request->get_param('page') ?: 1));
            $search = sanitize_text_field($request->get_param('search') ?: '');
            $category = sanitize_text_field($request->get_param('category') ?: '');

            $args = array(
                'posts_per_page' => $per_page,
                'paged' => $page,
                'post_status' => 'publish',
                'post_type' => 'post',
                'meta_query' => array(
                    array(
                        'key' => '_thumbnail_id',
                        'compare' => 'EXISTS'
                    )
                )
            );

            if (!empty($search)) {
                $args['s'] = $search;
            }

            if (!empty($category)) {
                $args['category_name'] = $category;
            }

            $posts_query = new WP_Query($args);
            $posts = $posts_query->posts;

            $formatted_posts = array();
            foreach ($posts as $post) {
                $categories = get_the_category($post->ID);
                $featured_image_id = get_post_thumbnail_id($post->ID);
                $featured_image = wp_get_attachment_image_src($featured_image_id, 'large');

                $formatted_post = array(
                    'id' => $post->ID,
                    'title' => array('rendered' => $post->post_title),
                    'excerpt' => array('rendered' => get_the_excerpt($post)),
                    'content' => array('rendered' => apply_filters('the_content', $post->post_content)),
                    'link' => get_permalink($post->ID),
                    'date' => $post->post_date,
                    'date_gmt' => $post->post_date_gmt,
                    'modified' => $post->post_modified,
                    'modified_gmt' => $post->post_modified_gmt,
                    'slug' => $post->post_name,
                    'status' => $post->post_status,
                    'featured_media' => $featured_image_id,
                    'featured_image_url' => $featured_image ? $featured_image[0] : null,
                    'categories' => array_map(function($cat) {
                        return array(
                            'id' => $cat->term_id,
                            'name' => $cat->name,
                            'slug' => $cat->slug
                        );
                    }, $categories),
                    '_embedded' => array(
                        'wp:featuredmedia' => $featured_image_id ? array(array(
                            'id' => $featured_image_id,
                            'source_url' => $featured_image ? $featured_image[0] : null,
                            'media_details' => array(
                                'width' => $featured_image ? $featured_image[1] : null,
                                'height' => $featured_image ? $featured_image[2] : null,
                            )
                        )) : array(),
                        'wp:term' => array($categories ? array_map(function($cat) {
                            return array(
                                'id' => $cat->term_id,
                                'name' => $cat->name,
                                'slug' => $cat->slug,
                                'taxonomy' => 'category'
                            );
                        }, $categories) : array()),
                        'author' => array(array(
                            'id' => $post->post_author,
                            'name' => get_the_author_meta('display_name', $post->post_author)
                        ))
                    )
                );

                $formatted_posts[] = $formatted_post;
            }

            // Set pagination headers
            $total_posts = $posts_query->found_posts;
            $total_pages = $posts_query->max_num_pages;

            return new WP_REST_Response($formatted_posts, 200, array(
                'X-WP-Total' => $total_posts,
                'X-WP-TotalPages' => $total_pages
            ));
        },
        'permission_callback' => 'rt_rest_permission_check'
    ));

    // Categories endpoint
    register_rest_route('rt/v1', '/categories', array(
        'methods' => 'GET',
        'callback' => function($request) {
            $categories = get_categories(array(
                'hide_empty' => true,
                'exclude' => array(1) // Exclude "Uncategorized"
            ));

            $formatted_categories = array_map(function($cat) {
                return array(
                    'id' => $cat->term_id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'count' => $cat->count
                );
            }, $categories);

            return $formatted_categories;
        },
        'permission_callback' => 'rt_rest_permission_check'
    ));
});

// 6. Ensure WordPress default REST API endpoints work
add_action('init', function() {
    // Verify that WordPress REST API is enabled
    if (!function_exists('rest_get_url_prefix')) {
        return;
    }

    // Ensure posts endpoint includes featured media by default
    add_filter('rest_post_collection_params', function($query_params) {
        $query_params['include_no_featured'] = array(
            'description'       => 'Include posts without featured images in results.',
            'type'              => 'boolean',
            'default'           => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        );

        return $query_params;
    });

    add_filter('rest_post_query', function($args, $request) {
        $include_no_featured = filter_var($request->get_param('include_no_featured'), FILTER_VALIDATE_BOOLEAN);

        if (false === $include_no_featured) {
            $thumbnail_query = array(
                'key'     => '_thumbnail_id',
                'compare' => 'EXISTS',
            );

            if (isset($args['meta_query']) && is_array($args['meta_query'])) {
                $args['meta_query'][] = $thumbnail_query;
            } else {
                $args['meta_query'] = array($thumbnail_query);
            }
        }

        return $args;
    }, 10, 2);
});

// 7. Add REST API status to admin dashboard
add_action('wp_dashboard_setup', function() {
    if (current_user_can('manage_options')) {
        wp_add_dashboard_widget('rt_api_status', 'REST API Status', function() {
            $rest_url = rest_url('wp/v2/posts');
            $custom_rest_url = rest_url('rt/v1/test');

            echo '<div style="padding: 10px;">';
            echo '<h4>API Endpoints Status:</h4>';
            echo '<p><strong>Standard API:</strong> <a href="' . esc_url($rest_url) . '" target="_blank">' . esc_html($rest_url) . '</a></p>';
            echo '<p><strong>Custom API:</strong> <a href="' . esc_url($custom_rest_url) . '" target="_blank">' . esc_html($custom_rest_url) . '</a></p>';
            echo '<p><strong>Posts Count:</strong> ' . wp_count_posts()->publish . '</p>';

            // Test if REST API is accessible
            $response = wp_remote_get($custom_rest_url);
            if (is_wp_error($response)) {
                echo '<p style="color: red;"><strong>Status:</strong> Error - ' . $response->get_error_message() . '</p>';
            } else {
                $code = wp_remote_retrieve_response_code($response);
                $color = $code === 200 ? 'green' : 'orange';
                echo '<p style="color: ' . $color . ';"><strong>Status:</strong> HTTP ' . $code . '</p>';
            }
            echo '</div>';
        });
    }
});

// 8. Debug function (only for admins)
if (current_user_can('manage_options') && isset($_GET['debug_api'])) {
    add_action('wp_footer', function() {
        $debug_info = array(
            'rest_enabled' => rest_get_url_prefix() ? true : false,
            'rest_url' => rest_url(),
            'wp_rest_url' => rest_url('wp/v2/posts'),
            'custom_rest_url' => rest_url('rt/v1/posts'),
            'permalink_structure' => get_option('permalink_structure'),
            'posts_count' => wp_count_posts()->publish,
            'rewrite_rules' => get_option('rewrite_rules') ? 'exists' : 'missing'
        );

        echo '<script>console.log("REST API Debug Info:", ' . json_encode($debug_info) . ');</script>';
    });
}

// 9. ✅ SECURITY FIX: Handle OPTIONS requests for CORS preflight (no wildcard)
add_action('init', function() {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed_origins = [
            'https://realtreasury.com',
            'https://www.realtreasury.com',
            'https://realtreasury.github.io',
        ];

        // Only allow specific origins
        if (in_array($origin, $allowed_origins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
        }

        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce');
        header('Access-Control-Max-Age: 86400');
        exit(0);
    }
});

// 10. Troubleshooting function - add ?fix_api=1 to any page URL as admin
if (current_user_can('manage_options') && isset($_GET['fix_api'])) {
    add_action('init', function() {
        // Force flush rewrite rules
        flush_rewrite_rules();

        // Update options
        update_option('rt_api_rules_version', '1.2');

        // Show success message
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>REST API rewrite rules have been flushed and updated!</p></div>';
        });
    });
}

// Enhanced custom posts endpoint optimized for carousel
add_action('rest_api_init', function() {
    register_rest_route('rt/v1', '/posts/recent', array(
        'methods' => 'GET',
        'args'    => array(
            'include_no_featured' => array(
                'description'       => 'Include posts regardless of whether they have featured media.',
                'type'              => 'boolean',
                'default'           => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ),
        ),
        'callback' => function($request) {
            $per_page = max(1, min(20, intval($request->get_param('per_page') ?: 8)));
            $exclude = $request->get_param('exclude');
            $category = sanitize_text_field($request->get_param('category') ?: '');

            $include_no_featured = rest_sanitize_boolean($request->get_param('include_no_featured'));

            $args = array(
                'posts_per_page' => $per_page,
                'post_status'    => 'publish',
                'post_type'      => 'post',
                'orderby'        => 'date',
                'order'          => 'DESC',
            );

            if (!$include_no_featured) {
                $args['meta_query'] = array(
                    array(
                        'key'     => '_thumbnail_id',
                        'compare' => 'EXISTS'
                    )
                );
            }

            if ($exclude) {
                $args['post__not_in'] = array_map('intval', explode(',', $exclude));
            }

            if (!empty($category)) {
                $args['category_name'] = $category;
            }

            $posts_query = new WP_Query($args);
            $posts = $posts_query->posts;

            $formatted_posts = array();
            foreach ($posts as $post) {
                $categories = get_the_category($post->ID);
                $featured_image_id = get_post_thumbnail_id($post->ID);
                $image_data = wp_get_attachment_image_src($featured_image_id, 'medium');
                $image_url_large = wp_get_attachment_image_src($featured_image_id, 'large');

                $excerpt = get_the_excerpt($post);
                if (empty($excerpt)) {
                    $excerpt = wp_trim_words(strip_tags($post->post_content), 25, '...');
                }

                $formatted_posts[] = array(
                    'id'                   => $post->ID,
                    'title'                => array('rendered' => $post->post_title),
                    'excerpt'              => array('rendered' => $excerpt),
                    'link'                 => get_permalink($post->ID),
                    'date'                 => $post->post_date,
                    'date_gmt'             => $post->post_date_gmt,
                    'featured_media'       => $featured_image_id,
                    'featured_image_url'   => $image_data ? $image_data[0] : null,
                    'featured_image_large' => $image_url_large ? $image_url_large[0] : null,
                    'categories'           => array_map(function($cat) {
                        return array(
                            'id'   => $cat->term_id,
                            'name' => $cat->name,
                            'slug' => $cat->slug
                        );
                    }, $categories),
                    'reading_time' => rt_calculate_reading_time($post->post_content),
                    'author' => array(
                        'id'   => $post->post_author,
                        'name' => get_the_author_meta('display_name', $post->post_author)
                    )
                );
            }

            return new WP_REST_Response($formatted_posts, 200, array(
                'X-RT-Cache-Time'  => time(),
                'X-RT-Posts-Found' => count($formatted_posts)
            ));
        },
        'permission_callback' => 'rt_rest_permission_check'
    ));
});

function rt_calculate_reading_time($content) {
    $word_count   = str_word_count(strip_tags($content));
    $reading_time = ceil($word_count / 200);
    return max(1, $reading_time);
}

// Optimized media endpoint with WebP support if available
add_action('rest_api_init', function() {
    register_rest_route('rt/v1', '/media/(?P<id>\d+)/optimized', array(
        'methods' => 'GET',
        'callback' => function($request) {
            $media_id = $request['id'];
            $size     = $request->get_param('size') ?: 'medium';

            $image_data = wp_get_attachment_image_src($media_id, $size);
            $image_meta = wp_get_attachment_metadata($media_id);

            if (!$image_data) {
                return new WP_Error('no_image', 'Image not found', array('status' => 404));
            }

            $response = array(
                'id'    => $media_id,
                'url'   => $image_data[0],
                'width' => $image_data[1],
                'height'=> $image_data[2],
                'alt'   => get_post_meta($media_id, '_wp_attachment_image_alt', true),
                'sizes' => array()
            );

            if ($image_meta && isset($image_meta['sizes'])) {
                foreach ($image_meta['sizes'] as $size_name => $size_data) {
                    $size_url = wp_get_attachment_image_src($media_id, $size_name);
                    if ($size_url) {
                        $response['sizes'][$size_name] = array(
                            'url'    => $size_url[0],
                            'width'  => $size_url[1],
                            'height' => $size_url[2]
                        );
                    }
                }
            }

            $webp_url = str_replace(array('.jpg', '.jpeg', '.png'), '.webp', $image_data[0]);
            if (file_exists(str_replace(site_url(), ABSPATH, $webp_url))) {
                $response['webp_url'] = $webp_url;
            }

            return $response;
        },
        'permission_callback' => 'rt_rest_permission_check'
    ));
});

add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
    if (strpos($request->get_route(), '/rt/v1/') === 0) {
        header('Cache-Control: public, max-age=300');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');
        header('X-RT-API: 1.0');
    }
    return $served;
}, 10, 4);

add_action('rest_api_init', function() {
    register_rest_route('rt/v1', '/performance', array(
        'methods' => 'GET',
        'callback' => function() {
            $start_time = microtime(true);
            $query_start = microtime(true);
            $posts = get_posts(array('numberposts' => 1));
            $query_time = microtime(true) - $query_start;
            $image_start = microtime(true);
            $attachments = get_posts(array(
                'post_type' => 'attachment',
                'post_mime_type' => 'image',
                'numberposts' => 1
            ));
            $image_time = microtime(true) - $image_start;
            $total_time = microtime(true) - $start_time;

            return array(
                'response_time_ms'  => round($total_time * 1000, 2),
                'database_query_ms' => round($query_time * 1000, 2),
                'image_query_ms'    => round($image_time * 1000, 2),
                'memory_usage_mb'   => round(memory_get_usage() / 1024 / 1024, 2),
                'posts_count'       => wp_count_posts()->publish,
                'timestamp'         => current_time('mysql'),
                'status'            => 'healthy'
            );
        },
        'permission_callback' => 'rt_rest_permission_check'
    ));
});

// ✅ SECURITY FIX: Removed duplicate wildcard CORS handler (now handled above)

add_action('rest_api_init', function() {
    add_filter('rest_request_before_callbacks', function($response, $handler, $request) {
        if (strpos($request->get_route(), '/rt/v1/') === 0) {
            $request->set_param('_rt_start_time', microtime(true));
        }
        return $response;
    }, 10, 3);

    add_filter('rest_request_after_callbacks', function($response, $handler, $request) {
        if (strpos($request->get_route(), '/rt/v1/') === 0 && $request->get_param('_rt_start_time')) {
            $execution_time = microtime(true) - $request->get_param('_rt_start_time');
            if ($execution_time > 1.0) {
                error_log('RT API Slow Request: ' . $request->get_route() . ' took ' . $execution_time . 's');
            }
            if (is_a($response, 'WP_REST_Response')) {
                $response->header('X-RT-Execution-Time', round($execution_time * 1000, 2) . 'ms');
            }
        }
        return $response;
    }, 10, 3);
});

add_action('rest_api_init', function() {
    register_rest_route('rt/v1', '/connectivity', array(
        'methods' => 'GET',
        'callback' => function() {
            $tests = array(
                'wordpress_api' => false,
                'custom_api'    => true,
                'database'      => false,
                'uploads'       => false
            );
            try {
                $wp_posts = get_posts(array('numberposts' => 1));
                $tests['wordpress_api'] = !empty($wp_posts);
            } catch (Exception $e) {
                $tests['wordpress_api'] = false;
            }
            global $wpdb;
            try {
                $result = $wpdb->get_var('SELECT 1');
                $tests['database'] = ($result == 1);
            } catch (Exception $e) {
                $tests['database'] = false;
            }
            $upload_dir = wp_upload_dir();
            $tests['uploads'] = is_writable($upload_dir['path']);
            $all_passed = !in_array(false, $tests, true);

            return array(
                'status'    => $all_passed ? 'healthy' : 'degraded',
                'tests'     => $tests,
                'timestamp' => current_time('c'),
                'site_url'  => site_url(),
                'rest_url'  => rest_url()
            );
        },
        'permission_callback' => 'rt_rest_permission_check'
    ));
});

add_action('wp_enqueue_scripts', 'rt_enqueue_iframe_resizer_script', 20);
function rt_enqueue_iframe_resizer_script() {
    $script_path = get_stylesheet_directory() . '/assets/js/iframe-resizer.js';

    if (!file_exists($script_path)) {
        return;
    }

    $script_uri = get_stylesheet_directory_uri() . '/assets/js/iframe-resizer.js';

    wp_enqueue_script(
        'rt-iframe-resizer',
        $script_uri,
        array(),
        filemtime($script_path),
        true
    );
}

add_action('wp_enqueue_scripts', 'rt_enqueue_shared_styles', 100);
function rt_enqueue_shared_styles() {
    if (is_admin()) {
        return;
    }

    $shared_path = get_stylesheet_directory() . '/assets/css/shared.css';
    if (!file_exists($shared_path)) {
        return;
    }

    $shared_uri = get_stylesheet_directory_uri() . '/assets/css/shared.css';
    $dependencies = array();

    if (wp_style_is('astra-theme-css', 'registered') || wp_style_is('astra-theme-css', 'enqueued')) {
        $dependencies[] = 'astra-theme-css';
    }

    wp_enqueue_style(
        'rt-shared-styles',
        $shared_uri,
        $dependencies,
        filemtime($shared_path)
    );
}

/**
 * ===============================================================
 * SECURITY AUDIT FIX: RATE LIMITING & PERMISSIONS (FIX C-01)
 * ===============================================================
 */

/**
 * Checks if a request for the custom REST API is rate-limited.
 *
 * @return bool|WP_Error True if OK, WP_Error if rate-limited.
 */
function rt_rest_permission_check() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'rest_rate_limit_' . md5( $ip );
    $requests = get_transient( $key );

    // Allow 60 requests per minute per IP.
    if ( false === $requests ) {
        set_transient( $key, 1, MINUTE_IN_SECONDS );
        $requests = 1;
    } elseif ( $requests > 60 ) {
        return new WP_Error(
            'rest_rate_limit_exceeded',
            'Too many requests. Please slow down.',
            array( 'status' => 429 )
        );
    } else {
        set_transient( $key, $requests + 1, MINUTE_IN_SECONDS );
    }

    // Passed rate limiting
    return true;
}
