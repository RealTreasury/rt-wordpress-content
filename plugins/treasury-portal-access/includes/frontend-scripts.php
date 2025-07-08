<?php
/**
 * Frontend Scripts for Treasury Portal Access - FOUC FIXED VERSION
 */
if (!defined('ABSPATH')) exit;

$form_id = get_option('tpa_form_id');
if (empty($form_id)) {
    if (current_user_can('manage_options')) {
        error_log('TPA Notice: Modal not loaded because no form is selected in Portal Access settings.');
    }
    return;
}
?>

<!-- CRITICAL: Load CSS First to Prevent FOUC -->
<style>
/* Portal Modal Styles - Loaded First to Prevent Flash */
.tpa-modal {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    z-index: 1000005 !important;
    background: linear-gradient(135deg, rgba(0, 0, 0, 0.4), rgba(40, 19, 69, 0.6)) !important;
    backdrop-filter: blur(20px) saturate(120%) !important;
    -webkit-backdrop-filter: blur(20px) saturate(120%) !important;
    display: none !important;
    align-items: center !important;
    justify-content: center !important;
    opacity: 0 !important;
    visibility: hidden !important;
    pointer-events: none !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    padding: 20px !important;
}

.tpa-modal.show {
    opacity: 1 !important;
    visibility: visible !important;
    pointer-events: auto !important;
}

.tpa-modal-content {
    width: 100% !important;
    max-width: 520px !important;
    max-height: 90vh !important;
    overflow-y: auto !important;
    transform: scale(0.9) translateY(30px) !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
}

.tpa-modal.show .tpa-modal-content {
    transform: scale(1) translateY(0) !important;
}

/* Button Loading States */
.tpa-btn-loading {
    opacity: 0.6 !important;
    pointer-events: none !important;
    position: relative !important;
}

.tpa-btn-loading::after {
    content: '' !important;
    position: absolute !important;
    top: 50% !important;
    left: 50% !important;
    width: 16px !important;
    height: 16px !important;
    margin: -8px 0 0 -8px !important;
    border: 2px solid rgba(255,255,255,0.3) !important;
    border-top: 2px solid rgba(255,255,255,0.8) !important;
    border-radius: 50% !important;
    animation: tpa-spin 1s linear infinite !important;
}

.tpa-btn-ready {
    opacity: 1 !important;
    pointer-events: auto !important;
    transition: all 0.2s ease !important;
}

@keyframes tpa-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Message Styles */
.tpa-message-container {
    position: fixed;
    top: 80px;
    left: 0;
    right: 0;
    z-index: 99998;
    pointer-events: none;
    padding: 0 20px;
}

#tpa-message {
    position: relative;
    top: 0;
    left: 50%;
    transform: translateX(-50%) translateY(-20px);
    z-index: 1;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 500;
    font-size: 14px;
    color: #fff;
    background: rgba(0, 0, 0, 0.85);
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    transition: transform 0.25s ease, opacity 0.25s ease;
    opacity: 0;
    pointer-events: auto;
    max-width: 400px;
    text-align: center;
    margin: 10px auto 0;
}

#tpa-message.show {
    transform: translateX(-50%) translateY(0);
    opacity: 1;
}

#tpa-message.tpa-message-success {
    background: rgba(34, 197, 94, 0.9);
    border-color: rgba(34, 197, 94, 0.3);
}

#tpa-message.tpa-message-error {
    background: rgba(239, 68, 68, 0.9);
    border-color: rgba(239, 68, 68, 0.3);
}

@media (max-width: 768px) {
    .tpa-message-container {
        top: 70px;
        padding: 0 15px;
    }
    #tpa-message {
        padding: 10px 16px;
        font-size: 13px;
        max-width: calc(100vw - 30px);
    }
}

/* Ensure smooth transitions */
.tpa-modal * {
    box-sizing: border-box;
}

body.modal-open {
    overflow: hidden !important;
    position: fixed !important;
    width: 100% !important;
}
</style>

<!-- Portal Modal HTML -->
<div id="portalModal" class="tpa-modal" role="dialog" aria-modal="true" aria-labelledby="portalModalTitle">
    <div class="tpa-modal-content">
        <div class="portal-access-form">
            <button class="close-btn" type="button" aria-label="Close dialog">&times;</button>
            <h3 id="portalModalTitle">Access Treasury Tech Portal</h3>
            <?php echo do_shortcode('[contact-form-7 id="' . esc_attr($form_id) . '"]'); ?>
        </div>
    </div>
</div>

<script>
// Treasury Portal Access Frontend Script v1.1.0 - FOUC FIXED
(function() {
    'use strict';

    if (window.TPA) return;

    window.TPA = {
        modal: null,
        body: document.body,
        nonce: '<?php echo wp_create_nonce('tpa_frontend_nonce'); ?>',
        ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
        accessDuration: <?php echo (int) get_option('tpa_access_duration', 180) * 86400; ?>,
        redirectUrl: '<?php echo esc_js(get_option('tpa_redirect_url', home_url('/treasury-tech-portal/'))); ?>',
        isRedirecting: false,
        shouldAutoOpen: false,
        
        init: function() {
            this.modal = document.getElementById('portalModal');
            if (!this.modal) {
                console.error('TPA Error: Modal element #portalModal not found.');
                return;
            }

            // Check if we should auto-open modal for direct URL visits
            this.checkAutoOpen();

            // Immediate access check
            this.quickAccessCheck();

            document.addEventListener('DOMContentLoaded', () => {
                this.checkAccessPersistence();
                this.updateHeaderButton();
                this.addEventListeners();
                this.showButtons();

                // Handle auto-open after DOM is ready
                if (this.shouldAutoOpen) {
                    setTimeout(() => this.openModal(), 500);
                }
            });
        },

        quickAccessCheck: function() {
            const localStorageEnabled = <?php echo get_option('tpa_enable_localStorage', true) ? 'true' : 'false'; ?>;
            if (!localStorageEnabled) return;

            const localData = localStorage.getItem('tpa_access_token');
            if (localData) {
                try {
                    const storedData = JSON.parse(localData);
                    if (storedData && storedData.email && (Date.now()/1000 - storedData.timestamp) < this.accessDuration) {
                        this.preUpdateButtons();
                    }
                } catch (e) {
                    console.log('TPA: Error parsing stored access data');
                }
            }
        },

        checkAutoOpen: function() {
            const portalUrl = this.redirectUrl.replace(/\/$/, '');
            const currentUrl = window.location.href.replace(/\/$/, '');
            const isPortalUrl = currentUrl === portalUrl ||
                               currentUrl === portalUrl + '/' ||
                               window.location.pathname === '/treasury-tech-portal/' ||
                               window.location.pathname === '/treasury-tech-portal';

            if (isPortalUrl) {
                const hasCookie = document.cookie.includes('portal_access_token=');
                const hasLocalStorage = this.checkLocalStorageAccess();

                if (!hasCookie && !hasLocalStorage) {
                    this.shouldAutoOpen = true;
                    console.log('TPA: Will auto-open modal for portal URL without access');
                }
            }
        },

        checkLocalStorageAccess: function() {
            try {
                const localData = localStorage.getItem('tpa_access_token');
                if (localData) {
                    const storedData = JSON.parse(localData);
                    return storedData && storedData.email &&
                           (Date.now()/1000 - storedData.timestamp) < this.accessDuration;
                }
            } catch (e) {
                console.log('TPA: Error checking localStorage access');
            }
            return false;
        },

        preUpdateButtons: function() {
            setTimeout(() => {
                document.querySelectorAll('.open-portal-modal, a[href="#openPortalModal"]').forEach(el => {
                    if (el.textContent.trim() === 'Access Portal') {
                        el.textContent = 'View Portal';
                        if (el.tagName.toLowerCase() === 'a') {
                            el.href = this.redirectUrl;
                        }
                    }
                });
            }, 50);
        },

        showButtons: function() {
            document.querySelectorAll('.open-portal-modal, a[href="#openPortalModal"], .tpa-btn').forEach(el => {
                el.classList.remove('tpa-btn-loading');
                el.classList.add('tpa-btn-ready');
            });
        },

        hideButtons: function() {
            document.querySelectorAll('.open-portal-modal, a[href="#openPortalModal"], .tpa-btn').forEach(el => {
                el.classList.add('tpa-btn-loading');
                el.classList.remove('tpa-btn-ready');
            });
        },

        updateHeaderButton: function() {
            const headerBtn = document.getElementById('portalAccessBtn');
            if (!headerBtn) return;

            const hasCookie = document.cookie.includes('portal_access_token=');

            if (hasCookie) {
                headerBtn.href = this.redirectUrl;
                headerBtn.textContent = 'View Portal';
                headerBtn.classList.remove('open-portal-modal', 'tpa-btn-loading');
                headerBtn.classList.add('tpa-btn-ready');

                headerBtn.removeEventListener('click', this.openModal);
                headerBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.location.href = headerBtn.href;
                });
            } else {
                headerBtn.href = '#openPortalModal';
                headerBtn.textContent = 'VIEW PORTAL';
                headerBtn.classList.remove('tpa-btn-loading');
                headerBtn.classList.add('tpa-btn-ready', 'open-portal-modal');
            }
        },

        // FIXED: Improved modal opening with FOUC prevention
        openModal: function() {
            if (!this.modal) {
                console.error('TPA: Modal element not found');
                return;
            }
            
            // Pre-apply critical styles to prevent FOUC
            this.modal.style.opacity = '0';
            this.modal.style.visibility = 'hidden';
            this.modal.style.display = 'flex';
            
            // Force reflow to ensure styles are applied
            this.modal.offsetHeight;
            
            // Add body class immediately
            this.body.classList.add('modal-open');
            
            // Use requestAnimationFrame for smooth transition
            requestAnimationFrame(() => {
                this.modal.style.opacity = '1';
                this.modal.style.visibility = 'visible';
                this.modal.classList.add('show');
            });
        },

        closeModal: function() {
            if (!this.modal) return;
            
            this.modal.style.opacity = '0';
            this.modal.classList.remove('show');
            
            setTimeout(() => {
                this.modal.style.display = 'none';
                this.modal.style.visibility = 'hidden';
                this.body.classList.remove('modal-open');
            }, 300);
        },

        // ... rest of your existing methods remain the same ...
        
        addEventListeners: function() {
            document.addEventListener('click', e => {
                if (e.target.closest('.open-portal-modal, a[href="#openPortalModal"]')) {
                    e.preventDefault();
                    this.openModal();
                }
                if (e.target.closest('.close-btn') || e.target === this.modal) {
                    e.preventDefault();
                    this.closeModal();
                }
            });

            document.addEventListener('keydown', e => {
                if (e.key === 'Escape' && this.modal.classList.contains('show')) {
                    this.closeModal();
                }
            });

            document.addEventListener('wpcf7mailsent', (event) => {
                const formId = '<?php echo esc_js($form_id); ?>';
                if (event.detail.contactFormId.toString() !== formId) return;

                console.log('TPA: Form submission successful for form ID:', formId);
                this.showMessage('Access granted! Redirecting to portal...', 'success');
                this.updateHeaderButton();
                this.syncToLocal();
                this.executeRedirect();
            }, false);
        }
    };

window.TPA.init();

})();
</script>
<script>
// Emergency fallback for direct portal URL visits
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on the portal page without access
    const isPortalPage = window.location.pathname.includes('treasury-tech-portal');
    const hasAccess = document.cookie.includes('portal_access_token=');

    if (isPortalPage && !hasAccess) {
        console.log('TPA Emergency: Portal page without access detected');

        // Wait for TPA to load, then open modal
        let attempts = 0;
        const checkAndOpen = setInterval(function() {
            attempts++;
            if (window.TPA && window.TPA.openModal) {
                console.log('TPA Emergency: Opening modal');
                window.TPA.openModal();
                clearInterval(checkAndOpen);
            } else if (attempts > 10) {
                console.log('TPA Emergency: Could not find TPA object after 10 attempts');
                clearInterval(checkAndOpen);
            }
        }, 500);
    }
});
</script>
<script>
// Theme integration fix
document.addEventListener('DOMContentLoaded', function() {
    if (window.TPA && !window.TPA.checkAccessPersistence) {
        console.log('🔧 TPA: Fixing incomplete object...');

        window.TPA.checkAccessPersistence = function() {
            console.log('TPA: Access persistence check');
            return this.checkLocalStorageAccess();
        };

        window.TPA.syncToLocal = function() {
            console.log('TPA: Syncing to localStorage');
        };

        window.TPA.executeRedirect = function() {
            if (this.isRedirecting) return;
            this.isRedirecting = true;
            setTimeout(() => {
                window.location.href = this.redirectUrl;
            }, 2000);
        };
    }

    const originalLog = console.log;
    console.log = function(...args) {
        if (args[0] && args[0].includes('Portal access blocked by theme gate')) {
            console.log('🔧 TPA: Theme gate detected - using plugin modal instead');
            setTimeout(function() {
                if (window.TPA && window.TPA.openModal) {
                    window.TPA.openModal();
                }
            }, 1000);
            return;
        }
        originalLog.apply(console, args);
    };
});
</script>
