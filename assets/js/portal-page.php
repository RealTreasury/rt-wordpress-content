// AGGRESSIVE FOUC Prevention - Treasury Portal
add_action('wp_head', 'treasury_portal_immediate_fouc_prevention', 1);
function treasury_portal_immediate_fouc_prevention() {
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
            loader.innerHTML = '<div class="loader-content"><div style="font-size: 3rem; margin-bottom: 20px;">🏛️</div><h3 style="color: #7216f4; margin: 0 0 15px 0;">Treasury Portal</h3><p style="color: #666; margin: 0 0 20px 0;">Verifying access...</p><div class="treasury-portal-spinner"></div></div>';
            
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
            
            // Access verification
            function verifyAndShow() {
                var hasAccess = false;
                
                // Check cookie
                if (document.cookie.includes('portal_access_token=')) {
                    hasAccess = true;
                    console.log('✅ Portal access found');
                }
                
                // Check localStorage
                if (!hasAccess) {
                    try {
                        var localData = localStorage.getItem('tpa_access_token');
                        if (localData) {
                            var storedData = JSON.parse(localData);
                            var duration = 180 * 24 * 60 * 60;
                            if (storedData && storedData.email && (Date.now()/1000 - storedData.timestamp) < duration) {
                                hasAccess = true;
                                console.log('✅ Portal access found in localStorage');
                            }
                        }
                    } catch (e) {}
                }
                
                if (hasAccess) {
                    // Show content
                    setTimeout(function() {
                        document.body.classList.remove('treasury-portal-loading');
                        document.body.classList.add('treasury-portal-ready');
                        console.log('✅ Portal content shown');
                    }, 800);
                } else {
                    // Redirect to access form
                    console.log('❌ No access - redirecting');
                    setTimeout(function() {
                        window.location.href = '/?portal_access_required=1&t=' + Date.now();
                    }, 1500);
                }
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
