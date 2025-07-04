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

        showMessage: function(message, type = 'info') {
            document.getElementById('tpa-message')?.remove();
            const messageDiv = document.createElement('div');
            messageDiv.id = 'tpa-message';
            messageDiv.className = `tpa-message tpa-message-${type}`;
            messageDiv.textContent = message;
            this.body.appendChild(messageDiv);
            setTimeout(() => messageDiv.classList.add('show'), 10);
            setTimeout(() => {
                messageDiv.classList.remove('show');
                setTimeout(() => messageDiv.remove(), 300);
            }, 4000);
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

<style>
/* Styles are unchanged, but included for completeness */
body.modal-open { overflow: hidden !important; }
.tpa-modal { position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100vw !important; height: 100vh !important; z-index: 1000003 !important; background: linear-gradient(135deg, rgba(0, 0, 0, .4), rgba(40, 19, 69, .3) 50%, rgba(0, 0, 0, .4)) !important; backdrop-filter: blur(15px) saturate(120%) !important; -webkit-backdrop-filter: blur(15px) saturate(120%) !important; display: flex !important; align-items: center !important; justify-content: center !important; opacity: 0 !important; visibility: hidden !important; pointer-events: none !important; transition: all .4s cubic-bezier(.4, 0, .2, 1) !important; }
.tpa-modal.show { opacity: 1 !important; visibility: visible !important; pointer-events: auto !important; }
.tpa-modal-content { max-width: calc(100vw - 40px) !important; max-height: calc(100vh - 40px) !important; padding: 20px !important; display: flex !important; align-items: center !important; justify-content: center !important; transform: scale(.9) !important; transition: all .4s cubic-bezier(.4, 0, .2, 1) !important; }
.tpa-modal.show .tpa-modal-content { transform: scale(1) !important; }
.portal-access-form { width: 100% !important; max-width: 520px !important; min-width: 320px !important; margin: 0 auto !important; padding: 32px !important; background: linear-gradient(135deg, hsla(0, 0%, 100%, .95), hsla(0, 0%, 97%, .98) 50%, hsla(0, 0%, 100%, .95)) !important; backdrop-filter: blur(20px) saturate(130%) !important; -webkit-backdrop-filter: blur(20px) saturate(130%) !important; border: 2px solid rgba(199, 125, 255, .3) !important; border-radius: 16px !important; box-shadow: 0 8px 32px rgba(114, 22, 244, .15) !important; position: relative !important; overflow: hidden !important; }
.portal-access-form:before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #7216f4, #8f47f6 50%, #9d4edd); border-radius: 16px 16px 0 0; }
.close-btn { position: absolute !important; top: 12px !important; right: 16px !important; background: hsla(0, 0%, 100%, .9) !important; border: 1px solid rgba(199, 125, 255, .3) !important; border-radius: 50% !important; font-size: 18px !important; color: #7216f4 !important; cursor: pointer !important; width: 32px !important; height: 32px !important; display: flex !important; align-items: center !important; justify-content: center !important; transition: all .3s ease !important; }
.close-btn:hover { background: rgba(114, 22, 244, .1) !important; transform: scale(1.1) !important; }
#tpa-message { position: fixed; top: 20px; right: 20px; z-index: 100001; padding: 15px 20px; border-radius: 8px; font-weight: 500; background: #333; color: white; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: transform 0.3s ease, opacity 0.3s ease; transform: translateX(120%); opacity: 0; }
#tpa-message.show { transform: translateX(0); opacity: 1; }
#tpa-message.tpa-message-success { background: linear-gradient(135deg, #4CAF50, #45a049); }
#tpa-message.tpa-message-error { background: linear-gradient(135deg, #f44336, #d32f2f); }
#tpa-message.tpa-message-info { background: linear-gradient(135deg, #7216f4, #8f47f6); }
</style>
