// AGGRESSIVE FOUC Prevention - Treasury Portal
add_action('wp_head', 'treasury_portal_immediate_fouc_prevention', 1);
function treasury_portal_immediate_fouc_prevention() {
    // Skip if the Treasury Portal Access plugin is active
    if (class_exists('Treasury_Portal_Access')) {
        return;
    }

    if (is_page('treasury-tech-portal') ||
        strpos($_SERVER['REQUEST_URI'], 'treasury-tech-portal') !== false) {
        ?>
        <style>
        /* IMMEDIATE FOUC PREVENTION - HIGHEST PRIORITY */
        body.treasury-portal-page,
        body[class*="treasury-tech-portal"],
        .treasury-portal,
        .entry-content {
            opacity: 0 !important;
            visibility: hidden !important;
            transition: none !important;
        }
        
        /* Loading screen shows immediately */
        .treasury-portal-loader {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            background: linear-gradient(135deg, rgba(40, 19, 69, 0.95), rgba(114, 22, 244, 0.9)) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            z-index: 999999 !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        .treasury-portal-loader .loader-content {
            background: white !important;
            padding: 40px !important;
            border-radius: 20px !important;
            text-align: center !important;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3) !important;
        }
        
        .treasury-portal-spinner {
            width: 40px !important;
            height: 40px !important;
            margin: 20px auto !important;
            border: 4px solid #7216f4 !important;
            border-top: 4px solid transparent !important;
            border-radius: 50% !important;
            animation: portal-spin 1s linear infinite !important;
        }
        
        @keyframes portal-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Show content only when ready */
        body.treasury-portal-ready .treasury-portal,
        body.treasury-portal-ready .entry-content {
            opacity: 1 !important;
            visibility: visible !important;
            transition: opacity 0.3s ease !important;
        }
        
        body.treasury-portal-ready .treasury-portal-loader {
            opacity: 0 !important;
            visibility: hidden !important;
            pointer-events: none !important;
        }
        </style>
        
        <script>
        // IMMEDIATE execution - no waiting
        (function() {
            // Add body classes immediately
            document.documentElement.classList.add('treasury-portal-loading');
            
            // Create loader immediately
            var loader = document.createElement('div');
            loader.className = 'treasury-portal-loader';
            loader.innerHTML = '<div class="loader-content"><div style="font-size: 3rem; margin-bottom: 20px;">🏛️</div><h3 style="color: #7216f4; margin: 0 0 15px 0;">Treasury Portal</h3><p style="color: #666; margin: 0 0 20px 0;">Loading portal...</p><div class="treasury-portal-spinner"></div></div>';
            
            // Add to page immediately
            if (document.body) {
                document.body.appendChild(loader);
                document.body.classList.add('treasury-portal-loading');
            } else {
                document.addEventListener('DOMContentLoaded', function() {
                    document.body.appendChild(loader);
                    document.body.classList.add('treasury-portal-loading');
                });
            }
            
            // Access verification - DISABLED when plugin is not active
            function verifyAndShow() {
                // Plugin is disabled, so skip access verification and show content
                console.log('ℹ️ Portal Access plugin is disabled - showing content without verification');
                setTimeout(function() {
                    document.body.classList.remove('treasury-portal-loading');
                    document.body.classList.add('treasury-portal-ready');
                    console.log('✅ Portal content shown (public access)');
                }, 800);
            }
            
            // Run verification after short delay
            setTimeout(verifyAndShow, 500);
            
            // Fallback
            setTimeout(function() {
                if (!document.body.classList.contains('treasury-portal-ready')) {
                    console.log('⚠️ Fallback: Showing content');
                    document.body.classList.remove('treasury-portal-loading');
                    document.body.classList.add('treasury-portal-ready');
                }
            }, 4000);
        })();
        </script>
        <?php
    }
}
