<?php
/**
 * Frontend Scripts for Treasury Portal Access
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
$form_id = get_option('tpa_form_id');
if (empty($form_id)) return; // Don't output modal if no form is selected
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
// Treasury Portal Access Frontend Script
(function() {
    'use strict';

    // Encapsulate all logic in a single object
    window.TPA = {
        modal: null,
        body: null,
        nonce: '<?php echo wp_create_nonce('tpa_frontend_nonce'); ?>',
        ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
        accessDuration: <?php echo get_option('tpa_access_duration', 180) * 86400; ?>, // in seconds
        redirectUrl: '<?php echo esc_js(get_option('tpa_redirect_url', home_url('/treasury-tech-portal/'))); ?>',
        
        init: function() {
            this.modal = document.getElementById('portalModal');
            this.body = document.body;

            if (!this.modal) return;

            document.addEventListener('DOMContentLoaded', () => {
                this.checkAndRestoreAccess();
                this.addEventListeners();
            });
        },

        checkAndRestoreAccess: function() {
            const hasCookie = document.cookie.includes('portal_access_token');
            const hasLocalStorage = !!localStorage.getItem('portal_access_token');

            if (!hasCookie && hasLocalStorage) {
                const email = localStorage.getItem('user_email');
                const accessTime = localStorage.getItem('access_granted');
                const userName = localStorage.getItem('user_name');
                
                console.log('🔄 TPA: Attempting to restore portal access for:', email);
                
                if (email && accessTime && (Date.now()/1000 - accessTime) < this.accessDuration) {
                    this.restore(email, userName);
                } else {
                    console.log('❌ TPA: Stored access has expired');
                    this.clearLocal();
                }
            } else if (hasCookie && !hasLocalStorage) {
                console.log('🔄 TPA: Syncing portal access to localStorage...');
                this.syncToLocal();
            }
        },

        restore: function(email, userName) {
            this.showMessage('Restoring your portal access...', 'info');
            
            const formData = new URLSearchParams();
            formData.append('action', 'restore_portal_access');
            formData.append('email', email);
            formData.append('nonce', this.nonce);

            fetch(this.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('✅ TPA: Portal access restored for:', email);
                    this.showMessage('Welcome back, ' + (userName || email) + '! Portal access restored.', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    console.log('❌ TPA: Portal access restoration failed', data);
                    this.showMessage('Please complete the form to access portal content.', 'error');
                    this.clearLocal();
                }
            })
            .catch(error => {
                console.error('❌ TPA: Restoration error:', error);
                this.showMessage('An error occurred. Please complete the form again.', 'error');
            });
        },

        syncToLocal: function() {
            const formData = new URLSearchParams();
            formData.append('action', 'get_current_user_access');
            formData.append('nonce', this.nonce);

            fetch(this.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    localStorage.setItem('portal_access_token', data.data.token);
                    localStorage.setItem('user_email', data.data.email);
                    localStorage.setItem('user_name', data.data.name);
                    localStorage.setItem('access_granted', data.data.access_time);
                    console.log('✅ TPA: Portal access synced to localStorage');
                }
            })
            .catch(error => console.log('TPA: Sync failed:', error));
        },

        showMessage: function(message, type = 'info') {
            const existingMessage = document.getElementById('tpa-message');
            if (existingMessage) existingMessage.remove();
            
            const messageDiv = document.createElement('div');
            messageDiv.id = 'tpa-message';
            messageDiv.style.cssText = `position: fixed; top: 20px; right: 20px; z-index: 100001; padding: 15px 20px; border-radius: 8px; font-weight: 500; font-size: 14px; max-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: all 0.3s ease; transform: translateX(120%); opacity: 0;`;
            
            const colors = {
                success: 'linear-gradient(135deg, #4CAF50, #45a049)',
                error: 'linear-gradient(135deg, #f44336, #d32f2f)',
                info: 'linear-gradient(135deg, #7216f4, #8f47f6)'
            };
            messageDiv.style.background = colors[type] || colors.info;
            messageDiv.style.color = 'white';
            
            messageDiv.textContent = message;
            document.body.appendChild(messageDiv);
            
            setTimeout(() => {
                messageDiv.style.transform = 'translateX(0)';
                messageDiv.style.opacity = '1';
            }, 100);
            
            setTimeout(() => {
                messageDiv.style.transform = 'translateX(120%)';
                messageDiv.style.opacity = '0';
                setTimeout(() => messageDiv.remove(), 300);
            }, type === 'success' ? 3000 : 5000);
        },

        clearLocal: function() {
            localStorage.removeItem('portal_access_token');
            localStorage.removeItem('user_email');
            localStorage.removeItem('user_name');
            localStorage.removeItem('access_granted');
            console.log('🧹 TPA: Cleared expired portal access data from localStorage');
        },

        clearCookies: function() {
            const cookieNames = ['portal_access_token', 'user_identifier', 'access_granted_time'];
            cookieNames.forEach(name => {
                document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/;';
            });
            console.log('🧹 TPA: Cleared portal access cookies');
        },

        openModal: function() {
            const scrollY = window.scrollY;
            this.body.style.position = 'fixed';
            this.body.style.top = `-${scrollY}px`;
            this.body.style.width = '100%';
            this.body.classList.add('modal-open');
            this.modal.style.display = 'flex';
            setTimeout(() => this.modal.classList.add('show'), 10);
        },

        closeModal: function() {
            const scrollY = this.body.style.top;
            this.modal.classList.remove('show');
            setTimeout(() => {
                this.modal.style.display = 'none';
                this.body.classList.remove('modal-open');
                this.body.style.position = '';
                this.body.style.top = '';
                this.body.style.width = '';
                window.scrollTo(0, parseInt(scrollY || '0') * -1);
            }, 300);
        },

        revoke: function() {
            if (confirm('Are you sure you want to sign out of the portal?')) {
                this.clearLocal();
                this.clearCookies();
                
                const formData = new URLSearchParams();
                formData.append('action', 'revoke_portal_access');
                formData.append('nonce', this.nonce);

                fetch(this.ajaxUrl, { method: 'POST', body: formData })
                    .then(() => location.reload());
            }
        },

        addEventListeners: function() {
            document.addEventListener('click', e => {
                if (e.target.closest('a[href="#openPortalModal"], .open-portal-modal, a[href="#openVideoModal"], .open-video-modal')) {
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

            document.addEventListener('wpcf7mailsent', () => {
                console.log('✅ TPA: Portal access form submitted successfully');
                this.showMessage('Access granted! Redirecting...', 'success');
                setTimeout(() => {
                    this.closeModal();
                    window.location.href = this.redirectUrl;
                }, 1500);
            });
        }
    };

    // Let's go!
    window.TPA.init();

})();
</script>

<style>
/* Portal Modal Styles */
.tpa-modal { position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100vw !important; height: 100vh !important; z-index: 1000003 !important; background: linear-gradient(135deg, rgba(0, 0, 0, .4), rgba(40, 19, 69, .3) 50%, rgba(0, 0, 0, .4)) !important; backdrop-filter: blur(15px) saturate(120%) !important; -webkit-backdrop-filter: blur(15px) saturate(120%) !important; display: flex !important; align-items: center !important; justify-content: center !important; opacity: 0 !important; visibility: hidden !important; pointer-events: none !important; transition: all .4s cubic-bezier(.4, 0, .2, 1) !important; }
.tpa-modal.show { opacity: 1 !important; visibility: visible !important; pointer-events: auto !important; }
.tpa-modal-content { max-width: calc(100vw - 40px) !important; max-height: calc(100vh - 40px) !important; padding: 20px !important; display: flex !important; align-items: center !important; justify-content: center !important; transform: scale(.9) !important; transition: all .4s cubic-bezier(.4, 0, .2, 1) !important; }
.tpa-modal.show .tpa-modal-content { transform: scale(1) !important; }
.portal-access-form { width: 100% !important; max-width: 520px !important; min-width: 320px !important; margin: 0 auto !important; padding: 32px !important; background: linear-gradient(135deg, hsla(0, 0%, 100%, .95), hsla(0, 0%, 97%, .98) 50%, hsla(0, 0%, 100%, .95)) !important; backdrop-filter: blur(20px) saturate(130%) !important; -webkit-backdrop-filter: blur(20px) saturate(130%) !important; border: 2px solid rgba(199, 125, 255, .3) !important; border-radius: 16px !important; box-shadow: 0 8px 32px rgba(114, 22, 244, .15) !important; position: relative !important; overflow: hidden !important; }
.portal-access-form:before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #7216f4, #8f47f6 50%, #9d4edd); border-radius: 16px 16px 0 0; }
.close-btn { position: absolute !important; top: 12px !important; right: 16px !important; background: hsla(0, 0%, 100%, .9) !important; border: 1px solid rgba(199, 125, 255, .3) !important; border-radius: 50% !important; font-size: 18px !important; color: #7216f4 !important; cursor: pointer !important; width: 32px !important; height: 32px !important; display: flex !important; align-items: center !important; justify-content: center !important; transition: all .3s ease !important; }
.close-btn:hover { background: rgba(114, 22, 244, .1) !important; transform: scale(1.1) !important; }
body.modal-open { overflow: hidden !important; }
@media (max-width: 768px) {
    .tpa-modal-content { padding: 10px !important; }
    .portal-access-form { padding: 24px 20px !important; min-width: 280px !important; }
    .close-btn { top: 8px !important; right: 12px !important; width: 28px !important; height: 28px !important; }
}
</style>
