<?php
/**
 * Frontend Scripts for Treasury Portal Access
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
<div id="portalModal" class="tpa-modal fixed inset-0 z-[1000003] flex items-center justify-center p-4 bg-gradient-to-br from-black/40 via-[#281345]/30 to-black/40 backdrop-blur-lg transition-opacity duration-300 opacity-0 pointer-events-none" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="portalModalTitle">
    <div class="tpa-modal-content transform transition-all duration-300 max-w-full max-h-full">
        <div class="portal-access-form relative w-full max-w-lg mx-auto p-8 rounded-2xl shadow-2xl border border-purple-300/30 bg-white/70 backdrop-blur-xl backdrop-saturate-150 space-y-6">
            <div class="absolute top-0 left-0 w-full h-1 rounded-t-2xl bg-gradient-to-r from-[#7216f4] via-[#8f47f6] to-[#9d4edd]"></div>
            <button class="close-btn absolute top-3 right-4 w-8 h-8 flex items-center justify-center text-[#7216f4] bg-white/90 border border-purple-200 rounded-full hover:bg-purple-50 transition" type="button" aria-label="Close dialog">&times;</button>
            <h3 id="portalModalTitle" class="text-center text-xl font-semibold text-[#281345]">Access Treasury Tech Portal</h3>
            <?php echo do_shortcode('[contact-form-7 id="' . esc_attr($form_id) . '"]'); ?>
        </div>
    </div>
</div>

<script>
// Treasury Portal Access Frontend Script v1.0.8
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
        
        init: function() {
            this.modal = document.getElementById('portalModal');
            if (!this.modal) {
                console.error('TPA Error: Modal element #portalModal not found.');
                return;
            }

            // Defer event listener setup until DOM is fully loaded
            document.addEventListener('DOMContentLoaded', () => {
                this.checkAccessPersistence();
                this.addEventListeners();
                this.styleForm();
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
            this.showMessage('Restoring your portal access...', 'info');
            const formData = new URLSearchParams({ action: 'restore_portal_access', email: email, nonce: this.nonce });
            
            fetch(this.ajaxUrl, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.showMessage(`Welcome back, ${userName || email}! Access restored.`, 'success');
                    this.updateUIAfterRestore();
                } else {
                    throw new Error(data.data?.message || 'Restoration failed.');
                }
            })
            .catch(error => {
                console.error('❌ TPA: Restoration failed:', error);
                this.showMessage('Could not restore your session. Please use the form.', 'error');
                this.clearLocal();
            });
        },
        
        /**
         * Replaces "Access" buttons with "View Portal" links after a successful login.
         */
        updateUIAfterRestore: function() {
            document.querySelectorAll('.open-portal-modal, a[href="#openPortalModal"]').forEach(el => {
                const newLink = document.createElement('a');
                newLink.href = this.redirectUrl;
                
                // Copy classes from the original button for consistent styling.
                newLink.className = el.className;
                newLink.classList.remove('open-portal-modal');
                newLink.textContent = 'View Portal';

                // Try to replace the parent button block if it exists, otherwise replace the element itself.
                const parentWrapper = el.closest('.wp-block-button');
                if (parentWrapper) {
                    parentWrapper.innerHTML = ''; // Clear the old button
                    parentWrapper.appendChild(newLink);
                } else {
                    el.parentNode.replaceChild(newLink, el);
                }
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

        styleForm: function() {
            const form = this.modal.querySelector('form');
            if (!form) return;
            form.classList.add('space-y-4');
            form.querySelectorAll('input:not([type=submit]):not([type=checkbox]), textarea, select').forEach(el => {
                el.classList.add('w-full', 'p-3', 'rounded-md', 'border', 'border-purple-300', 'bg-white/75', 'placeholder-[#9d4edd]', 'focus:outline-none', 'focus:ring-2', 'focus:ring-[#8f47f6]');
            });
            form.querySelectorAll('input[type=checkbox]').forEach(cb => {
                cb.classList.add('mr-2', 'rounded', 'text-[#7216f4]', 'focus:ring-2', 'focus:ring-[#8f47f6]');
                const parent = cb.closest('label') || cb.parentElement;
                if (parent) parent.classList.add('flex', 'items-start');
            });
            form.querySelectorAll('input[type=submit], button[type=submit]').forEach(btn => {
                btn.classList.add('w-full', 'bg-[#7216f4]', 'hover:bg-[#8f47f6]', 'text-white', 'font-semibold', 'py-3', 'px-6', 'rounded-md', 'shadow', 'transition-colors', 'cursor-pointer');
            });
        },

        showMessage: function(message, type = 'info') {
            document.getElementById('tpa-message')?.remove();
            const messageDiv = document.createElement('div');
            messageDiv.id = 'tpa-message';
            const base = 'fixed top-5 right-5 z-[100001] px-4 py-3 rounded-lg font-medium text-white shadow-lg transform transition-all duration-300 translate-x-full opacity-0';
            let color = 'bg-[#7216f4]';
            if (type === 'success') color = 'bg-green-600';
            if (type === 'error') color = 'bg-red-600';
            messageDiv.className = `${base} ${color}`;
            messageDiv.textContent = message;
            this.body.appendChild(messageDiv);
            setTimeout(() => messageDiv.classList.remove('translate-x-full', 'opacity-0'), 10);
            setTimeout(() => {
                messageDiv.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => messageDiv.remove(), 300);
            }, 4000);
        },

        clearLocal: function() {
            localStorage.removeItem('tpa_access_token');
        },

        openModal: function() {
            this.modal.style.display = 'flex';
            setTimeout(() => this.modal.classList.remove('opacity-0', 'pointer-events-none'), 10);
            this.body.classList.add('modal-open');
        },

        closeModal: function() {
            this.modal.classList.add('opacity-0', 'pointer-events-none');
            setTimeout(() => {
                this.modal.style.display = 'none';
                this.body.classList.remove('modal-open');
            }, 300);
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
                if (e.key === 'Escape' && this.modal.style.display === 'flex') {
                    this.closeModal();
                }
            });

            document.addEventListener('wpcf7mailsent', (event) => {
                const formId = '<?php echo esc_js($form_id); ?>';
                if (event.detail.contactFormId.toString() !== formId) return;

                this.showMessage('Access granted! Redirecting...', 'success');
                this.syncToLocal();
                
                setTimeout(() => {
                    if (this.redirectUrl) {
                        window.location.href = this.redirectUrl;
                    } else {
                        location.reload();
                    }
                }, 1500);
            }, false);
        }
    };

    window.TPA.init();

})();
</script>

