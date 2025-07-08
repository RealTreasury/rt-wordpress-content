// Portal Page FOUC Prevention - Inline JavaScript
add_action('wp_footer', 'add_portal_page_fouc_script', 5);
function add_portal_page_fouc_script() {
    // Only run on treasury portal page
    if (!is_page('treasury-tech-portal') && 
        strpos($_SERVER['REQUEST_URI'], 'treasury-tech-portal') === false) {
        return;
    }
    ?>
    <script>
    // Portal Page FOUC Prevention Script - Enhanced Version
    document.addEventListener('DOMContentLoaded', function() {
        
        // Only run on treasury portal page
        if (!window.location.pathname.includes('treasury-tech-portal')) {
            return;
        }
        
        console.log('🏛️ Treasury Portal: Initializing FOUC prevention...');
        
        // Add loading class immediately if not already added
        if (!document.body.classList.contains('treasury-portal-loading')) {
            document.body.classList.add('treasury-portal-loading');
        }
        
        // Create and show loading screen
        let loader = document.querySelector('.treasury-portal-loader');
        if (!loader) {
            loader = document.createElement('div');
            loader.className = 'treasury-portal-loader';
            loader.innerHTML = \`
                <div class="loader-content">
                    <div class="loader-icon">🏛️</div>
                    <h3>Treasury Portal</h3>
                    <p>Verifying access and loading content...</p>
                    <div class="treasury-portal-spinner"></div>
                </div>
            \`;
            document.body.appendChild(loader);
        }
        
        // Function to hide loader and show content
        function showPortalContent() {
            console.log('✅ Treasury Portal: Showing content');
            
            // Hide loader
            if (loader) {
                loader.classList.add('hidden');
            }
            
            // Show portal content
            document.body.classList.remove('treasury-portal-loading');
            document.body.classList.add('treasury-portal-ready');
            
            // Notify TPA if available
            if (window.TPA && typeof window.TPA.onPortalReady === 'function') {
                window.TPA.onPortalReady();
            }
            
            // Remove loader after animation
            setTimeout(() => {
                if (loader && loader.parentNode) {
                    loader.parentNode.removeChild(loader);
                }
            }, 500);
        }
        
        // Check if user has access and content is ready
        let accessVerified = false;
        let contentReady = false;
        
        function checkReadyState() {
            if (accessVerified && contentReady) {
                showPortalContent();
            }
        }
        
        // Verify access (check for cookie or TPA instance)
        function verifyAccess() {
            // Check cookie first
            if (document.cookie.includes('portal_access_token=')) {
                console.log('✅ Portal access cookie found');
                accessVerified = true;
                checkReadyState();
                return;
            }
            
            // Check TPA instance
            if (window.TPA && typeof window.TPA.has_portal_access === 'function') {
                if (window.TPA.has_portal_access()) {
                    console.log('✅ Portal access verified via TPA');
                    accessVerified = true;
                    checkReadyState();
                    return;
                }
            }
            
            // Check localStorage backup
            const localData = localStorage.getItem('tpa_access_token');
            if (localData) {
                try {
                    const storedData = JSON.parse(localData);
                    const duration = 180 * 24 * 60 * 60; // 180 days in seconds
                    if (storedData && storedData.email && (Date.now()/1000 - storedData.timestamp) < duration) {
                        console.log('✅ Portal access found in localStorage');
                        accessVerified = true;
                        checkReadyState();
                        return;
                    }
                } catch (e) {
                    console.log('❌ Error parsing localStorage access data');
                }
            }
            
            // No access found - redirect or show access required
            console.log('❌ No portal access found');
            setTimeout(() => {
                // Update loader to show access required message
                if (loader) {
                    loader.innerHTML = \`
                        <div class="loader-content">
                            <div class="loader-icon">🔐</div>
                            <h3>Access Required</h3>
                            <p>Redirecting to access form...</p>
                            <div class="treasury-portal-spinner"></div>
                        </div>
                    \`;
                }
                
                // Redirect after a moment
                setTimeout(() => {
                    window.location.href = '/?portal_access_required=1&t=' + Date.now();
                }, 2000);
            }, 1000);
        }
        
        // Check if content is loaded
        function checkContentReady() {
            // Wait for critical elements to be present
            const portalContainer = document.querySelector('.treasury-portal');
            const navigation = document.querySelector('.rt-nav-container');
            
            if (portalContainer && navigation) {
                // Wait a bit more for full initialization
                setTimeout(() => {
                    console.log('✅ Portal content ready');
                    contentReady = true;
                    checkReadyState();
                }, 500);
            } else {
                // Keep checking
                setTimeout(checkContentReady, 100);
            }
        }
        
        // Start verification processes
        setTimeout(() => {
            verifyAccess();
            checkContentReady();
        }, 300);
        
        // Fallback: Show content after maximum wait time
        setTimeout(() => {
            if (!accessVerified || !contentReady) {
                console.log('⚠️ Portal: Fallback timeout reached, showing content');
                showPortalContent();
            }
        }, 5000);
        
        // Handle access granted parameter
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('access_granted') === '1') {
            console.log('🎉 Portal: Access just granted, expediting content display');
            accessVerified = true;
            // Shorter wait time for newly granted access
            setTimeout(() => {
                if (!contentReady) {
                    contentReady = true;
                    checkReadyState();
                }
            }, 1000);
        }
        
        // Expose functions for TPA integration
        window.PortalPage = {
            showContent: showPortalContent,
            verifyAccess: verifyAccess,
            isReady: () => accessVerified && contentReady
        };
    });
    </script>
    <?php
}

// Add critical inline script to head to prevent FOUC immediately
add_action('wp_head', 'add_critical_portal_fouc_prevention', 1);
function add_critical_portal_fouc_prevention() {
    if (is_page('treasury-tech-portal') || 
        strpos($_SERVER['REQUEST_URI'], 'treasury-tech-portal') !== false) {
        ?>
        <script>
        // Immediate FOUC prevention
        if (document.body) {
            document.body.classList.add('treasury-portal-loading');
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                document.body.classList.add('treasury-portal-loading');
            });
        }
        </script>
        <?php
    }
}
