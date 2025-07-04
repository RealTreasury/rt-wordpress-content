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

// Contact Form 7 Redirect - Faster Version
add_action('wp_footer', function() {
?>
<script>
document.addEventListener('wpcf7mailsent', function(event) {
    console.log('Form submitted successfully!');
    
    // Close modal immediately
    var modal = document.querySelector('.modal');
    if (modal) modal.classList.remove('show');
    
    // Redirect immediately (no delay)
    window.location.href = 'https://realtreasury.com/treasury-tech-portal/';
});
</script>
<?php
});

// Add modal bridge script globally
function add_modal_bridge_script() {
    ?>
    <script>
    window.addEventListener('message', function(event) {
        if (event.data && event.data.action === 'openVideoModal') {
            const modalTrigger = document.querySelector('a[href="#openVideoModal"]');
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
    
    // Custom function to open chat from buttons
    window.openTawkChat = function() {
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
// WordPress REST API Fixes for Insights Page
// ===============================================================

// Force enable REST API (in case it's disabled)
add_filter('rest_enabled', '__return_true');
add_filter('rest_jsonp_enabled', '__return_true');

// Add CORS headers for REST API requests
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        $allowed_origin = getenv('RT_ALLOWED_ORIGIN') ?: 'https://realtreasury.com';
        header('Access-Control-Allow-Origin: ' . $allowed_origin);
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        header('Access-Control-Allow-Credentials: true');
        return $value;
    });
});

// Alternative REST API rewrite rules (backup routes)
add_action('init', function() {
    add_rewrite_rule('^api/posts/?', 'index.php?rest_route=/wp/v2/posts', 'top');
    add_rewrite_rule('^api/categories/?', 'index.php?rest_route=/wp/v2/categories', 'top');
    
    // Flush rewrite rules if needed (only runs once)
    if (get_option('custom_api_rules_flushed') != '1') {
        flush_rewrite_rules();
        update_option('custom_api_rules_flushed', '1');
    }
});

// Add debug endpoint to test API functionality
add_action('rest_api_init', function() {
    register_rest_route('custom/v1', '/test', array(
        'methods' => 'GET',
        'callback' => function() {
            return array(
                'status' => 'success',
                'message' => 'Custom REST API is working!',
                'timestamp' => current_time('mysql'),
                'posts_count' => wp_count_posts()->publish
            );
        },
        'permission_callback' => '__return_true'
    ));
});

// Custom posts endpoint with better error handling
add_action('rest_api_init', function() {
    register_rest_route('custom/v1', '/posts', array(
        'methods' => 'GET',
        'callback' => function($request) {
            $per_page = $request->get_param('per_page') ?: 12;
            $posts = get_posts(array(
                'numberposts' => $per_page,
                'post_status' => 'publish',
                'post_type' => 'post'
            ));
            
            $formatted_posts = array();
            foreach ($posts as $post) {
                $formatted_posts[] = array(
                    'id' => $post->ID,
                    'title' => array('rendered' => $post->post_title),
                    'excerpt' => array('rendered' => wp_trim_words($post->post_content, 30)),
                    'link' => get_permalink($post->ID),
                    'date' => $post->post_date,
                    'featured_media' => get_post_thumbnail_id($post->ID)
                );
            }
            
            return $formatted_posts;
        },
        'permission_callback' => '__return_true'
    ));
});

// Debug function to check REST API status (fixed version)
function debug_rest_api_status() {
    $status = array(
        'rest_url' => rest_url(),
        'posts_count' => wp_count_posts()->publish,
        'current_user_can' => current_user_can('read'),
        'permalink_structure' => get_option('permalink_structure'),
        'wp_version' => get_bloginfo('version')
    );
    
    error_log('REST API Debug: ' . print_r($status, true));
    return $status;
}

// Add debug info to admin footer (only for admins)
add_action('admin_footer', function() {
    if (current_user_can('manage_options')) {
        $status = debug_rest_api_status();
        echo '<script>console.log("REST API Status:", ' . json_encode($status) . ');</script>';
    }
});
?>
