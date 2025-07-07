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
define( 'ASTRA_THEME_VERSION', '4.11.3' );
define( 'ASTRA_THEME_SETTINGS', 'astra-settings' );
define( 'ASTRA_THEME_DIR', trailingslashit( get_template_directory() ) );
define( 'ASTRA_THEME_URI', trailingslashit( esc_url( get_template_directory_uri() ) ) );
define( 'ASTRA_THEME_ORG_VERSION', file_exists( ASTRA_THEME_DIR . 'inc/w-org-version.php' ) );
/**
 * Minimum Version requirement of the Astra Pro addon.
 * This constant will be used to display the notice asking user to update the Astra addon to the version defined below.
 */
define( 'ASTRA_EXT_MIN_VER', '4.11.1' );
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
 * ToDo: Deprecate constants in future versions as they are no longer used in the codebase.
 */
define( 'ASTRA_PRO_UPGRADE_URL', ASTRA_THEME_ORG_VERSION ? astra_get_pro_url( '/pricing/', 'free-theme', 'dashboard', 'upgrade' ) : 'https://woocommerce.com/products/astra-pro/' );
define( 'ASTRA_PRO_CUSTOMIZER_UPGRADE_URL', ASTRA_THEME_ORG_VERSION ? astra_get_pro_url( '/pricing/', 'free-theme', 'customizer', 'upgrade' ) : 'https://woocommerce.com/products/astra-pro/' );
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
require_once ASTRA_THEME_DIR . 'admin/includes/class-astra-api-init.php';
if ( is_admin() ) {
	/**
	 * Admin Menu Settings
	 */
	require_once ASTRA_THEME_DIR . 'inc/core/class-astra-admin-settings.php';
	require_once ASTRA_THEME_DIR . 'admin/class-astra-admin-loader.php';
	require_once ASTRA_THEME_DIR . 'inc/lib/astra-notices/class-astra-notices.php';
}
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
 * Load deprecated functions
 */
require_once ASTRA_THEME_DIR . 'inc/core/deprecated/deprecated-filters.php';
require_once ASTRA_THEME_DIR . 'inc/core/deprecated/deprecated-hooks.php';
require_once ASTRA_THEME_DIR . 'inc/core/deprecated/deprecated-functions.php';

// Site-Wide Cookie Management System
add_action('wp_footer', 'add_sitewide_cookie_banner');
function add_sitewide_cookie_banner() {
    ?>
    <script>
    // Site-Wide Cookie Management
    document.addEventListener('DOMContentLoaded', function() {
        
        // Check if user has already made a choice
        function checkCookieConsent() {
            const consent = localStorage.getItem('cookie_consent');
            if (!consent) {
                showCookieBanner();
            } else {
                // Apply existing preferences
                const consentData = JSON.parse(consent);
                if (consentData.preferences && consentData.preferences.analytics) {
                    loadGoogleAnalytics();
                }
            }
        }
        
        // Show cookie banner
        function showCookieBanner() {
            // Don't show if banner already exists
            if (document.getElementById('cookieBanner')) {
                return;
            }
            
            // Create banner
            const banner = document.createElement('div');
            banner.id = 'cookieBanner';
            banner.className = 'cookie-banner';
            banner.innerHTML = `
                <div class="banner-content">
                    <div class="banner-text">
                        <strong>🍪 We use cookies to enhance your experience</strong>
                        This website uses cookies to provide you with a personalized browsing experience and to analyze our website traffic.
                    </div>
                    <div class="banner-buttons">
                        <button class="cookie-btn cookie-btn-accept" onclick="acceptAllCookies()">Accept All</button>
                        <button class="cookie-btn cookie-btn-decline" onclick="declineAllCookies()">Decline All</button>
                        <a href="https://realtreasury.com/cookie-policy/" class="cookie-btn cookie-btn-manage">Learn More</a>
                    </div>
                </div>
            `;
            
            document.body.appendChild(banner);
            
            // Show with animation
            setTimeout(() => {
                banner.classList.add('show');
            }, 100);
        }
        
        // Accept all cookies
        window.acceptAllCookies = function() {
            const preferences = {
                essential: true,
                analytics: true,
                marketing: true,
                preference: true
            };
            
            localStorage.setItem('cookie_consent', JSON.stringify({
                timestamp: new Date().toISOString(),
                preferences: preferences
            }));
            
            hideCookieBanner();
            loadGoogleAnalytics();
            
            // Optional: Show confirmation
            console.log('✅ All cookies accepted');
        }
        
        // Decline all cookies
        window.declineAllCookies = function() {
            const preferences = {
                essential: true,
                analytics: false,
                marketing: false,
                preference: false
            };
            
            localStorage.setItem('cookie_consent', JSON.stringify({
                timestamp: new Date().toISOString(),
                preferences: preferences
            }));
            
            hideCookieBanner();
            
            // Remove any existing analytics cookies
            removeCookiesByPattern('_ga');
            
            console.log('❌ Non-essential cookies declined');
        }
        
        // Hide banner
        function hideCookieBanner() {
            const banner = document.getElementById('cookieBanner');
            if (banner) {
                banner.classList.remove('show');
                setTimeout(() => {
                    if (banner.parentNode) {
                        banner.parentNode.removeChild(banner);
                    }
                }, 300);
            }
        }
        
        // Load Google Analytics
        function loadGoogleAnalytics() {
            // Prevent loading multiple times
            if (typeof gtag !== 'undefined') {
                return;
            }
            
            // Load GA4 script
            const script = document.createElement('script');
            script.async = true;
            script.src = 'https://www.googletagmanager.com/gtag/js?id=G-6KLBPGHTSM';
            document.head.appendChild(script);
            
            // Initialize GA4
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', 'G-6KLBPGHTSM', {
                'anonymize_ip': true,
                'cookie_flags': 'max-age=7200;secure;samesite=none'
            });
            
            console.log('📊 Google Analytics loaded');
        }
        
        // Remove cookies by pattern
        function removeCookiesByPattern(pattern) {
            const cookies = document.cookie.split(';');
            cookies.forEach(cookie => {
                const [name] = cookie.trim().split('=');
                if (name.includes(pattern)) {
                    document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; domain=${window.location.hostname}`;
                    document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`;
                }
            });
        }
        
        // Initialize cookie management
        checkCookieConsent();
        
        // Global function to show banner (for manage preferences button)
        window.showCookieBanner = showCookieBanner;
    });
    </script>
    <?php
}


// Add modal bridge script globally
function add_modal_bridge_script() {
    ?>
    <script>
    window.addEventListener('message', function(event) {
        if (event.data && event.data.action === 'openPortalModal') {
            const modalTrigger = document.querySelector('a[href="#openPortalModal"]');
            if (modalTrigger) {
                modalTrigger.click();
            }
        }
    });
    </script>
    <?php
}
add_action('wp_footer', 'add_modal_bridge_script');

// Tawk.to Chat Widget Integration for Real Treasury - SMALLER VERSION
add_action('wp_footer', 'add_tawk_to_chat_widget');
function add_tawk_to_chat_widget() {
    ?>
    <!--Start of Tawk.to Script-->
    <script type="text/javascript">
    var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
    (function(){
        var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
        s1.async=true;
        s1.src='https://embed.tawk.to/68598eb06d2be41919849c7d/1iuetaosd';
        s1.charset='UTF-8';
        s1.setAttribute('crossorigin','*');
        s0.parentNode.insertBefore(s1,s0);
    })();
    
    // Track if the user explicitly opened the chat
    window.tawkOpenedByUser = false;

    // Custom function to open chat from buttons
    window.openTawkChat = function() {
        window.tawkOpenedByUser = true;
        if (typeof Tawk_API !== 'undefined' && Tawk_API.maximize) {
            Tawk_API.maximize();
            console.log('Tawk chat opened');
        } else {
            console.log('Tawk not loaded yet, opening fallback');
            // Fallback to email if chat isn't loaded
            window.location.href = 'mailto:hello@realtreasury.com?subject=Treasury Technology Inquiry - Chat Unavailable';
        }
    };
    
    // Customize chat widget behavior
    Tawk_API.onLoad = function(){
        console.log('Tawk.to chat loaded successfully');

        // Keep the widget visible but customize its position and size
        Tawk_API.customStyle = {
            visibility: {
                desktop: {
                    position: 'br',
                    xOffset: 15,
                    yOffset: 15
                },
                mobile: {
                    position: 'br',
                    xOffset: 10,
                    yOffset: 10
                }
            }
        };

        // Prevent automatic expansion of the widget
        if (Tawk_API.minimize) {
            Tawk_API.minimize();
        }

        // Keep the widget minimized unless the user explicitly opened it
        Tawk_API.onChatMaximized = function(){
            if (!window.tawkOpenedByUser && Tawk_API.minimize) {
                Tawk_API.minimize();
            }
        };

        Tawk_API.onChatMinimized = function(){
            window.tawkOpenedByUser = false;
        };
    };
    
    // Track when someone starts a chat
    Tawk_API.onChatStarted = function(){
        console.log('Chat conversation started');
        // Optional: Add Google Analytics tracking
        if (typeof gtag !== 'undefined') {
            gtag('event', 'chat_started', { 'event_category': 'engagement' });
        }
    };
    
    // Track when chat ends
    Tawk_API.onChatEnded = function(){
        console.log('Chat conversation ended');
    };
    </script>
    <!--End of Tawk.to Script-->
    
    <style>
    /* Make Tawk.to chat widget smaller and styled */
    #tawkchat-minified {
        width: 50px !important;
        height: 50px !important;
        border-radius: 50% !important;
        box-shadow: 0 4px 12px rgba(114, 22, 244, 0.3) !important;
        border: 2px solid rgba(199, 125, 255, 0.2) !important;
        transition: all 0.3s ease !important;
    }
    
    #tawkchat-minified:hover {
        transform: translateY(-2px) scale(1.05) !important;
        box-shadow: 0 6px 16px rgba(114, 22, 244, 0.4) !important;
        border-color: rgba(199, 125, 255, 0.4) !important;
    }
    
    /* Style the chat bubble to match your brand colors */
    #tawkchat-minified .tawk-min-container {
        background: linear-gradient(135deg, #7216f4 0%, #8f47f6 100%) !important;
        width: 100% !important;
        height: 100% !important;
        border-radius: 50% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }
    
    /* Make the chat icon smaller */
    #tawkchat-minified .tawk-min-container svg,
    #tawkchat-minified .tawk-min-container img {
        width: 24px !important;
        height: 24px !important;
        color: white !important;
        fill: white !important;
    }
    
    /* Style the expanded chat panel */
    #tawkchat-container iframe {
        border-radius: 12px !important;
        box-shadow: 0 8px 32px rgba(114, 22, 244, 0.2) !important;
        border: 2px solid rgba(199, 125, 255, 0.3) !important;
    }
    
    /* Hide notification badges to keep it minimal */
    #tawkchat-minified .tawk-min-container .tawk-badge {
        display: none !important;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        #tawkchat-minified {
            width: 45px !important;
            height: 45px !important;
        }
        
        #tawkchat-minified .tawk-min-container svg,
        #tawkchat-minified .tawk-min-container img {
            width: 20px !important;
            height: 20px !important;
        }
    }
    </style>
    <?php
}

// Add the JavaScript for bank report forms
add_action('wp_footer', 'add_bank_report_javascript');

function add_bank_report_javascript() {
    ?>
    <script>
    // Dynamic title and subtitle based on URL parameter
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const quarter = urlParams.get('quarter');
        
        const quarterData = {
            'q4-2024': {
                title: 'Q4 2024 Bank Report Access',
                subtitle: 'Get comprehensive banking insights and analysis for Q4 2024'
            },
            'q3-2024': {
                title: 'Q3 2024 Bank Report Access',
                subtitle: 'Access key banking trends and insights from Q3 2024'
            },
            'q2-2024': {
                title: 'Q2 2024 Bank Report Access', 
                subtitle: 'Download detailed banking analysis and strategic insights from Q2 2024'
            },
            'q1-2024': {
                title: 'Q1 2024 Bank Report Access',
                subtitle: 'Access comprehensive banking insights and strategies from Q1 2024'
            }
        };
        
        if (quarter && quarterData[quarter]) {
            // Update form title and subtitle dynamically
            const titleElement = document.querySelector('.gated-download-form h3');
            const subtitleElement = document.querySelector('.gated-download-form .subtitle');
            
            if (titleElement) titleElement.textContent = quarterData[quarter].title;
            if (subtitleElement) subtitleElement.textContent = quarterData[quarter].subtitle;
        }
    });

    // Handle download for AJAX forms
    document.addEventListener('wpcf7mailsent', function(event) {
        const formData = new FormData(event.target);
        const quarter = formData.get('quarter-report');
        
        const downloadUrls = {
            'q4-2024': 'https://dropbox.com/s/your-q4-link/q4-2024-bank-report.pdf?dl=1',
            'q3-2024': 'https://dropbox.com/s/your-q3-link/q3-2024-bank-report.pdf?dl=1',
            'q2-2024': 'https://dropbox.com/s/your-q2-link/q2-2024-bank-report.pdf?dl=1',
            'q1-2024': 'https://dropbox.com/s/your-q1-link/q1-2024-bank-report.pdf?dl=1'
        };
        
        if (quarter && downloadUrls[quarter]) {
            // Open download in new tab
            window.open(downloadUrls[quarter], '_blank');
        }
    }, false);
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

// 3. Enhanced CORS headers for REST API requests
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        $origin = get_http_origin();
        $allowed_origins = [
            home_url(),
            site_url(),
            'https://realtreasury.com',
            'http://localhost:3000', // For development
        ];

        if (in_array($origin, $allowed_origins) || !$origin) {
            $origin = $origin ?: '*';
        }

        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages');

        return $value;
    });
});

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
        'permission_callback' => '__return_true'
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
        'permission_callback' => '__return_true'
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
        'permission_callback' => '__return_true'
    ));
});

// 6. Ensure WordPress default REST API endpoints work
add_action('init', function() {
    // Verify that WordPress REST API is enabled
    if (!function_exists('rest_get_url_prefix')) {
        return;
    }

    // Ensure posts endpoint includes featured media by default
    add_filter('rest_post_query', function($args, $request) {
        // Only add thumbnail requirement if not specifically requesting all posts
        if (!$request->get_param('include_no_featured')) {
            $args['meta_query'] = array(
                array(
                    'key' => '_thumbnail_id',
                    'compare' => 'EXISTS'
                )
            );
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

// 9. Handle OPTIONS requests for CORS preflight
add_action('init', function() {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
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
        'callback' => function($request) {
            $per_page = max(1, min(20, intval($request->get_param('per_page') ?: 8)));
            $exclude = $request->get_param('exclude');
            $category = sanitize_text_field($request->get_param('category') ?: '');

            $args = array(
                'posts_per_page' => $per_page,
                'post_status'    => 'publish',
                'post_type'      => 'post',
                'orderby'        => 'date',
                'order'          => 'DESC',
                'meta_query'     => array(
                    array(
                        'key'     => '_thumbnail_id',
                        'compare' => 'EXISTS'
                    )
                )
            );

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
        'permission_callback' => '__return_true'
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
        'permission_callback' => '__return_true'
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
        'permission_callback' => '__return_true'
    ));
});

add_action('rest_api_init', function() {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
        status_header(200);
        exit;
    }
});

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
        'permission_callback' => '__return_true'
    ));
});

?>
<?php
// ===============================================================
// REMOVE OR COMMENT OUT THE OLD PORTAL ACCESS SYSTEM
// ===============================================================

/*
// OLD SYSTEM - COMMENT OUT OR REMOVE THESE FUNCTIONS:

add_action('template_redirect', 'rt_gate_portal_access');
function rt_gate_portal_access() {
    // This function is causing the redirect loop - REMOVE IT
}

function rt_has_portal_access() {
    // This function conflicts with the plugin - REMOVE IT  
}

function rt_grant_portal_access($email, $name) {
    // This function is redundant with the plugin - REMOVE IT
}

add_action('wpcf7_mail_sent', 'rt_handle_portal_form_submission');
function rt_handle_portal_form_submission($contact_form) {
    // This function conflicts with the plugin - REMOVE IT
}

add_action('wp_footer', 'rt_portal_gating_javascript');
function rt_portal_gating_javascript() {
    // This function conflicts with the plugin - REMOVE IT
}
*/

// ===============================================================
// PORTAL REDIRECT FIX - additional helpers
// ===============================================================

/**
 * Add custom JavaScript to handle modal form submissions and redirects
 * This fixes the issue where the plugin prevents redirects from modals
 */
/* add_action('wp_footer', 'tpa_modal_redirect_fix');
function tpa_modal_redirect_fix() {
    $form_id = get_option('tpa_form_id');
    if (empty($form_id)) {
        return;
    }

    $redirect_url = get_option('tpa_redirect_url', home_url('/treasury-tech-portal/'));
    ?>
    <script>
    // Portal Modal Redirect Fix
    (function() {
        'use strict';

        document.addEventListener('wpcf7mailsent', function(event) {
            const formId = '<?php echo esc_js($form_id); ?>';
            const redirectUrl = '<?php echo esc_js($redirect_url); ?>';

            if (event.detail.contactFormId.toString() === formId) {
                try {
                    document.cookie = "portal_access_token=granted; path=/; max-age=15552000; secure; samesite=lax";
                } catch (e) {
                    console.error('Cookie setting failed:', e);
                }

                try {
                    localStorage.setItem('tpa_access_token', JSON.stringify({
                        token: 'granted',
                        email: document.querySelector('input[name="email-address"]')?.value || '',
                        name: document.querySelector('input[name="first-name"]')?.value || '',
                        timestamp: Math.floor(Date.now() / 1000)
                    }));
                } catch (e) {
                    console.error('localStorage failed:', e);
                }

                const formContainer = document.querySelector('.portal-form-container');
                if (formContainer) {
                    formContainer.innerHTML = `
                        <div style="text-align: center; padding: 40px 20px; background: #f0f9ff; border-radius: 12px; border: 2px solid #10b981;">
                            <div style="font-size: 48px; margin-bottom: 20px;">✅</div>
                            <h3 style="color: #10b981; margin: 0 0 15px 0; font-size: 24px;">Access Granted!</h3>
                            <p style="margin: 0 0 20px 0; color: #059669; font-size: 16px;">Redirecting to Treasury Portal...</p>
                            <div style="width: 40px; height: 40px; border: 4px solid #10b981; border-top: 4px solid transparent; border-radius: 50%; animation: spin 1s linear infinite; margin: 20px auto;"></div>
                        </div>
                        <style>
                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                        </style>
                    `;
                }

                // window.location.href = redirectUrl + '?access_granted=1&t=' + Date.now();
            }
        }, false);

        setTimeout(function() {
            if (window.TPA && window.TPA.showMessage) {
                const originalShowMessage = window.TPA.showMessage;
                window.TPA.showMessage = function(message, type) {
                    if (message.includes('redirecting') || message.includes('granted')) {
                        return;
                    }
                    return originalShowMessage.call(this, message, type);
                };
            }
        }, 1000);
    })();
    </script>
    <?php
}
*/

/**
 * Ensure the access token verification works properly
 */
add_action('init', 'tpa_ensure_access_verification');
function tpa_ensure_access_verification() {
    if (isset($_GET['access_granted']) && $_GET['access_granted'] === '1') {
        if (!session_id()) session_start();
        $_SESSION['tpa_just_granted_access'] = true;
        $_SESSION['tpa_access_time'] = time();

        if (!isset($_COOKIE['portal_access_token'])) {
            setcookie('portal_access_token', 'granted', time() + (180 * 24 * 60 * 60), '/', '', is_ssl(), true);
        }
    }
}

/**
 * Enhanced portal access verification for the integrated system
 */
function tpa_verify_portal_access() {
    if (class_exists('Treasury_Portal_Access')) {
        $tpa_instance = Treasury_Portal_Access::get_instance();
        if ($tpa_instance->has_portal_access()) {
            return true;
        }
    }

    if (isset($_GET['access_granted']) && $_GET['access_granted'] === '1') {
        return true;
    }

    if (!session_id()) session_start();
    if (isset($_SESSION['tpa_just_granted_access']) &&
        isset($_SESSION['tpa_access_time']) &&
        (time() - $_SESSION['tpa_access_time']) < 300) {
        return true;
    }

    if (isset($_COOKIE['portal_access_token']) && $_COOKIE['portal_access_token'] === 'granted') {
        return true;
    }

    return false;
}

/**
 * Debug helper to inspect state in the browser console
 */
add_action('wp_footer', 'tpa_debug_access_state');
function tpa_debug_access_state() {
    if (current_user_can('manage_options')) {
        ?>
        <script>
        console.log('🔍 TPA Debug Info:', {
            'cookies': document.cookie,
            'localStorage': localStorage.getItem('tpa_access_token'),
            'url': window.location.href,
            'hasPlugin': <?php echo class_exists('Treasury_Portal_Access') ? 'true' : 'false'; ?>,
            'isPortalPage': <?php echo (is_page('treasury-tech-portal') || $_SERVER['REQUEST_URI'] === '/treasury-tech-portal/') ? 'true' : 'false'; ?>
        });
        </script>
        <?php
    }
}


// ===============================================================
// NEW INTEGRATED SOLUTION - USE THIS INSTEAD
// ===============================================================

/**
 * Integrated portal access gate that works with Treasury Portal Access plugin
 * This replaces the old rt_gate_portal_access function
 */
add_action('template_redirect', 'tpa_integrated_gate_portal_access');
function tpa_integrated_gate_portal_access() {
    // Only gate the treasury-tech-portal page
    if (!is_page('treasury-tech-portal') && $_SERVER['REQUEST_URI'] !== '/treasury-tech-portal/') {
        return;
    }

    // Use the enhanced verification to determine access
    if (tpa_verify_portal_access()) {
        return;
    }

    // User doesn't have access, redirect to show the modal
    if (!session_id()) session_start();
    $_SESSION['tpa_portal_redirect'] = home_url('/treasury-tech-portal/');
    wp_redirect(home_url('/?show_portal_modal=1'));
    exit;
}

/**
 * Add the modal trigger JavaScript only when needed
 * This replaces the old rt_portal_gating_javascript function
 */
add_action('wp_footer', 'tpa_integrated_modal_trigger');
function tpa_integrated_modal_trigger() {
    // Only show modal trigger if the URL parameter is set
    if (!isset($_GET['show_portal_modal']) || $_GET['show_portal_modal'] !== '1') {
        return;
    }

    // Only add this if the Treasury Portal Access plugin is active
    if (!class_exists('Treasury_Portal_Access')) {
        return;
    }

    ?>
    <script>
    // Integrated portal modal trigger that works with Treasury Portal Access plugin
    document.addEventListener('DOMContentLoaded', function() {
        // Wait a bit for the plugin's scripts to load
        setTimeout(function() {
            if (window.TPA && typeof window.TPA.openModal === 'function') {
                // Use the plugin's modal system
                window.TPA.openModal();
            } else if (typeof window.showPortalModal === 'function') {
                // Fallback to old system if available
                window.showPortalModal();
            } else {
                console.log('⚠️ Portal modal system not found. Plugin may not be loaded.');
            }
        }, 500);
    });
    </script>
    <?php
}

/**
 * Update the plugin's redirect URL handling to prevent loops
 * This hooks into the plugin's form submission process
 */
/* add_action('wpcf7_mail_sent', 'tpa_integrated_form_handler', 5); // Priority 5 to run before plugin
function tpa_integrated_form_handler($contact_form) {
    $selected_form_id = get_option('tpa_form_id');
    if (empty($selected_form_id) || $contact_form->id() != $selected_form_id) {
        return;
    }

    // Let the plugin handle the form submission, but ensure proper redirect
    if (!session_id()) session_start();
    $_SESSION['tpa_form_just_submitted'] = true;
    $_SESSION['tpa_form_submission_time'] = time();
}
*/

/**
 * Clean up any conflicting cookies from the old system
 */
add_action('init', 'tpa_cleanup_old_cookies');
function tpa_cleanup_old_cookies() {
    // Remove old system cookies that might conflict
    $old_cookies = ['rt_portal_access', 'rt_portal_access_token'];
    foreach ($old_cookies as $cookie_name) {
        if (isset($_COOKIE[$cookie_name])) {
            setcookie($cookie_name, '', time() - 3600, '/', '', is_ssl(), true);
        }
    }
}
?>
