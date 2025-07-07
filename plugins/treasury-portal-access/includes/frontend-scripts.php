<?php
/**
 * Frontend Scripts for Treasury Portal Access - FIXED REDIRECT VERSION
 */
if (!defined('ABSPATH')) exit;

$form_id = get_option('tpa_form_id');
// Critical: Don't output anything if no form is selected in settings.
if (empty($form_id)) {
    // Log an error for the admin to see if they are logged in.
    if (current_user_can('manage_options')) {
        error_log('TPA Notice: Modal not loaded because no form is selected in Portal Access settings.');
    }
    return;
}
?>
<!-- Portal Modal HTML -->
<div id="portalModal" class="tpa-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="portalModalTitle">
    <div class="tpa-modal-content">
        <div class="portal-access-form">
            <button class="close-btn" type="button" aria-label="Close dialog">&times;</button>
            <h3 id="portalModalTitle">Access Treasury Tech Portal</h3>
            <?php echo do_shortcode('[contact-form-7 id="' . esc_attr($form_id) . '"]'); ?>
        </div>
    </div>
</div>

<script>
// Treasury Portal Access Frontend Script v1.0.9 - FIXED REDIRECTS
(function() {
    'use strict';

    // Prevent script from running twice
    if (window.TPA) {
        return;
    }

    window.TPA = {
        modal: null,
        body: document.body,
        nonce: '<?php echo wp_create_nonce('tpa_frontend_nonce'); ?>',
        ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
        accessDuration: <?php echo (int) get_option('tpa_access_duration', 180) * 86400; ?>, // seconds
        redirectUrl: '<?php echo esc_js(get_option('tpa_redirect_url', home_url('/treasury-tech-portal/'))); ?>',
        isRedirecting: false, // Flag to prevent double redirects
        
        init: function() {
            this.modal = document.getElementById('portalModal');
            if (!this.modal) {
                console.error('TPA Error: Modal element #portalModal not found.');
                return;
            }

            // IMMEDIATE access check (before DOMContentLoaded)
            this.quickAccessCheck();

            // Full initialization on DOM ready
            document.addEventListener('DOMContentLoaded', () => {
                this.checkAccessPersistence();
                this.addEventListeners();
                this.showButtons(); // Make buttons visible after state is determined
            });
        },

        quickAccessCheck: function() {
            // Check localStorage immediately for faster response
            const localStorageEnabled = <?php echo get_option('tpa_enable_localStorage', true) ? 'true' : 'false'; ?>;
            if (!localStorageEnabled) return;

            const localData = localStorage.getItem('tpa_access_token');
            if (localData) {
                try {
                    const storedData = JSON.parse(localData);
                    if (storedData && storedData.email && (Date.now()/1000 - storedData.timestamp) < this.accessDuration) {
                        // User likely has valid access - pre-update buttons
                        this.preUpdateButtons();
                    }
                } catch (e) {
                    console.log('TPA: Error parsing stored access data');
                }
            }
        },

        preUpdateButtons: function() {
            // Quick update before full verification
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
            // Remove loading state from all portal buttons
            document.querySelectorAll('.open-portal-modal, a[href="#openPortalModal"], .tpa-btn').forEach(el => {
                el.classList.remove('tpa-btn-loading');
                el.classList.add('tpa-btn-ready');
            });
        },

        hideButtons: function() {
            // Add loading state to portal buttons
            document.querySelectorAll('.open-portal-modal, a[href="#openPortalModal"], .tpa-btn').forEach(el => {
                el.classList.add('tpa-btn-loading');
                el.classList.remove('tpa-btn-ready');
            });
        },

        checkAccessPersistence: function() {
            const hasCookie = document.cookie.includes('portal_access_token=');
            const localStorageEnabled = <?php echo get_option('tpa_enable_localStorage', true) ? 'true' : 'false'; ?>;
            if (!localStorageEnabled) return;

            const localData = localStorage.getItem('tpa_access_token');
            const hasLocalStorage = !!localData;

            if (hasCookie && !hasLocalStorage) {
                this.syncToLocal();
            } else if (!hasCookie && hasLocalStorage) {
                const storedData = JSON.parse(localData);
                if (storedData && storedData.email && (Date.now()/1000 - storedData.timestamp) < this.accessDuration) {
                    this.restoreFromLocal(storedData.email, storedData.name);
                } else {
                    this.clearLocal();
                }
            }
        },

        restoreFromLocal: function(email, userName) {
            const formData = new URLSearchParams({ action: 'restore_portal_access', email: email, nonce: this.nonce });

            fetch(this.ajaxUrl, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (this.isPortalPage()) {
                        this.showMessage(`Welcome back, ${userName || email}!`, 'success');
                    }
                    this.updateUIAfterRestore();
                } else {
                    throw new Error(data.data?.message || 'Restoration failed.');
                }
            })
            .catch(error => {
                console.error('❌ TPA: Restoration failed:', error);
                if (this.isPortalPage()) {
                    this.showMessage('Could not restore your session. Please use the form.', 'error');
                }
                this.clearLocal();
            });
        },

        isPortalPage: function() {
            return window.location.pathname.includes('treasury-tech-portal') ||
                   window.location.href.includes('treasury-tech-portal');
        },
        
        /**
         * Replaces "Access" buttons with "View Portal" links after a successful login.
         */
        updateUIAfterRestore: function() {
            document.querySelectorAll('.open-portal-modal, a[href="#openPortalModal"]').forEach(el => {
                if (el.tagName.toLowerCase() === 'a') {
                    el.href = this.redirectUrl;
                } else {
                    el.setAttribute('data-href', this.redirectUrl);
                }

                el.classList.remove('open-portal-modal');

                if (el.textContent.trim() === 'Access Portal') {
                    el.textContent = 'View Portal';
                }

                // Ensure button is visible and ready
                el.classList.remove('tpa-btn-loading');
                el.classList.add('tpa-btn-ready');
            });

            // If a protected content block was showing an access message, reload the page to show the actual content.
            if(document.querySelector('.tpa-access-required')) {
                location.reload();
            }
        },

        syncToLocal: function() {
            const formData = new URLSearchParams({ action: 'get_current_user_access', nonce: this.nonce });
            fetch(this.ajaxUrl, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    localStorage.setItem('tpa_access_token', JSON.stringify({
                        token: data.data.token,
                        email: data.data.email,
                        name: data.data.name,
                        timestamp: data.data.access_time
                    }));
                }
            })
            .catch(error => console.error('❌ TPA: Sync to local storage failed:', error));
        },

        showMessage: function(message, type = 'info') {
            if (!this.isPortalPage()) {
                return;
            }

            document.getElementById('tpa-message')?.remove();
            const messageDiv = document.createElement('div');
            messageDiv.id = 'tpa-message';
            messageDiv.className = `tpa-message tpa-message-${type}`;
            messageDiv.textContent = message;

            this.insertMessageAfterHeader(messageDiv);

            requestAnimationFrame(() => {
                messageDiv.classList.add('show');
            });

            setTimeout(() => {
                messageDiv.classList.remove('show');
                setTimeout(() => messageDiv.remove(), 250);
            }, 3000);
        },

        insertMessageAfterHeader: function(messageDiv) {
            const header = document.querySelector('.rt-nav-container') || document.querySelector('header');
            let container = document.getElementById('tpa-message-container');

            if (!container) {
                container = document.createElement('div');
                container.id = 'tpa-message-container';
                container.className = 'tpa-message-container';

                if (header && header.nextSibling) {
                    header.parentNode.insertBefore(container, header.nextSibling);
                } else {
                    document.body.appendChild(container);
                }
            }

            container.appendChild(messageDiv);
        },

        clearLocal: function() {
            localStorage.removeItem('tpa_access_token');
        },

        openModal: function() {
            this.modal.style.display = 'flex';
            setTimeout(() => this.modal.classList.add('show'), 10);
            this.body.classList.add('modal-open');
        },

        closeModal: function() {
            this.modal.classList.remove('show');
            setTimeout(() => {
                this.modal.style.display = 'none';
                this.body.classList.remove('modal-open');
            }, 300);
        },

        executeRedirect: function() {
            if (this.isRedirecting) {
                console.log('TPA: Redirect already in progress, skipping...');
                return;
            }

            this.isRedirecting = true;
            console.log('TPA: Starting redirect process...');

            // Close modal if it exists
            if (this.modal && this.modal.classList.contains('show')) {
                console.log('TPA: Closing modal before redirect');
                this.closeModal();
            }

            // Update form with success message
            const formContainer = document.querySelector('.portal-access-form');
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

            // Execute redirect after a short delay
            setTimeout(() => {
                if (this.redirectUrl) {
                    console.log('TPA: Redirecting to:', this.redirectUrl);
                    window.location.href = this.redirectUrl + '?access_granted=1&t=' + Date.now();
                } else {
                    console.log('TPA: No redirect URL configured, reloading page');
                    location.reload();
                }
            }, 1000);
        },

        revoke: function() {
            if (confirm('Are you sure you want to sign out of the portal?')) {
                this.clearLocal();
                const formData = new URLSearchParams({ action: 'revoke_portal_access', nonce: this.nonce });
                fetch(this.ajaxUrl, { method: 'POST', body: formData }).then(() => location.reload());
            }
        },

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

            // FIXED: Redirect event handler
            document.addEventListener('wpcf7mailsent', (event) => {
                const formId = '<?php echo esc_js($form_id); ?>';
                if (event.detail.contactFormId.toString() !== formId) return;

                console.log('TPA: Form submission successful for form ID:', formId);
                
                // Show success message
                this.showMessage('Access granted! Redirecting...', 'success');
                
                // Sync to localStorage for persistence
                this.syncToLocal();
                
                // Execute redirect - ALWAYS redirect on successful form submission
                this.executeRedirect();
                
            }, false);

            // Handle form errors
            document.addEventListener('wpcf7mailfailed', (event) => {
                const formId = '<?php echo esc_js($form_id); ?>';
                if (event.detail.contactFormId.toString() !== formId) return;
                
                console.error('TPA: Form submission failed');
                this.showMessage('Form submission failed. Please try again.', 'error');
            }, false);

            // Handle validation errors
            document.addEventListener('wpcf7invalid', (event) => {
                const formId = '<?php echo esc_js($form_id); ?>';
                if (event.detail.contactFormId.toString() !== formId) return;
                
                console.log('TPA: Form validation errors');
                this.showMessage('Please correct the errors and try again.', 'error');
            }, false);
        }
    };

    window.TPA.init();

})();
</script>

<style>
/* Styles are unchanged, but included for completeness */
.tpa-btn-loading {
    opacity: 0.3 !important;
    pointer-events: none !important;
    transition: opacity 0.2s ease !important;
}

.tpa-btn-ready {
    opacity: 1 !important;
    pointer-events: auto !important;
}
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
    will-change: transform, opacity;
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

#tpa-message.tpa-message-info {
    background: rgba(59, 130, 246, 0.9);
    border-color: rgba(59, 130, 246, 0.3);
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
        margin: 8px auto 0;
        border-radius: 6px;
    }
}

@media (max-width: 480px) {
    .tpa-message-container {
        top: 60px;
        padding: 0 10px;
    }

    #tpa-message {
        padding: 8px 12px;
        font-size: 12px;
        max-width: calc(100vw - 20px);
        margin: 5px auto 0;
    }
}
</style>
