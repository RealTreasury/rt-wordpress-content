<?php
/**
 * Frontend Scripts for Treasury Portal Access
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Portal Modal HTML -->
<div id="portalModal" class="tpa-modal" style="display: none;">
    <div class="tpa-modal-content">
        <div class="portal-access-form">
            <button class="close-btn" type="button">&times;</button>
            <h3>Access Treasury Tech Portal</h3>
            <?php echo do_shortcode('[contact-form-7 id="' . get_option('tpa_form_id', '0779c74') . '" title="Video Access Gate Form"]'); ?>
        </div>
    </div>
</div>

<script>
// Treasury Portal Access Frontend Script
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('portalModal');
    const body = document.body;

    // Check and restore access from localStorage
    checkAndRestoreAccess();

    function checkAndRestoreAccess() {
        const hasCookie = document.cookie.includes('portal_access_token');
        const hasLocalStorage = localStorage.getItem('portal_access_token');
        
        if (!hasCookie && hasLocalStorage) {
            const email = localStorage.getItem('user_email');
            const accessTime = localStorage.getItem('access_granted');
            const userName = localStorage.getItem('user_name');
            
            console.log('🔄 TPA: Attempting to restore portal access for:', email);
            
            // Check if access is still valid
            const maxAge = <?php echo get_option('tpa_access_duration', 180); ?> * 24 * 60 * 60; // Convert days to seconds
            if (email && accessTime && (Date.now()/1000 - accessTime) < maxAge) {
                restorePortalAccess(email, userName);
            } else {
                console.log('❌ TPA: Stored access has expired');
                clearStoredAccess();
            }
        } else if (hasCookie && !hasLocalStorage) {
            console.log('🔄 TPA: Syncing portal access to localStorage...');
            syncAccessToLocalStorage();
        }
    }

    function restorePortalAccess(email, userName) {
        showMessage('Restoring your portal access...', 'info');
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=restore_portal_access&email=' + encodeURIComponent(email)
        })
        .then(response => response.text())
        .then(data => {
            if (data.trim() === 'success') {
                console.log('✅ TPA: Portal access restored for:', email);
                showMessage('Welcome back, ' + (userName || email) + '! Portal access restored.', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                console.log('❌ TPA: Portal access restoration failed');
                showMessage('Please complete the form to access portal content.', 'error');
                clearStoredAccess();
            }
        })
        .catch(error => {
            console.error('❌ TPA: Restoration error:', error);
            showMessage('Please complete the form to access portal content.', 'error');
        });
    }

    function syncAccessToLocalStorage() {
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=get_current_user_access'
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
    }

    function showMessage(message, type = 'info') {
        const existingMessage = document.getElementById('tpa-message');
        if (existingMessage) existingMessage.remove();
        
        const messageDiv = document.createElement('div');
        messageDiv.id = 'tpa-message';
        messageDiv.style.cssText = `
            position: fixed; top: 20px; right: 20px; z-index: 100001;
            padding: 15px 20px; border-radius: 8px; font-weight: 500;
            font-size: 14px; max-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.3s ease; transform: translateX(100%); opacity: 0;
        `;
        
        if (type === 'success') {
            messageDiv.style.background = 'linear-gradient(135deg, #4CAF50, #45a049)';
            messageDiv.style.color = 'white';
        } else if (type === 'error') {
            messageDiv.style.background = 'linear-gradient(135deg, #f44336, #d32f2f)';
            messageDiv.style.color = 'white';
        } else {
            messageDiv.style.background = 'linear-gradient(135deg, #7216f4, #8f47f6)';
            messageDiv.style.color = 'white';
        }
        
        messageDiv.textContent = message;
        document.body.appendChild(messageDiv);
        
        setTimeout(() => {
            messageDiv.style.transform = 'translateX(0)';
            messageDiv.style.opacity = '1';
        }, 100);
        
        setTimeout(() => {
            messageDiv.style.transform = 'translateX(100%)';
            messageDiv.style.opacity = '0';
            setTimeout(() => messageDiv.remove(), 300);
        }, type === 'success' ? 3000 : 5000);
    }

    function clearStoredAccess() {
        localStorage.removeItem('portal_access_token');
        localStorage.removeItem('user_email');
        localStorage.removeItem('user_name');
        localStorage.removeItem('access_granted');
        console.log('🧹 TPA: Cleared expired portal access data');
    }

    // Modal functions
    function openModal() {
        if (modal) {
            const scrollY = window.scrollY;
            body.style.position = 'fixed';
            body.style.top = `-${scrollY}px`;
            body.style.width = '100%';
            body.classList.add('modal-open');
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
        }
    }

    function closeModal() {
        if (modal) {
            const scrollY = body.style.top;
            modal.classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 300);
            body.classList.remove('modal-open');
            body.style.position = '';
            body.style.top = '';
            body.style.width = '';
            window.scrollTo(0, parseInt(scrollY || '0') * -1);
        }
    }

    // Event listeners
    document.addEventListener('click', function(e) {
        // Portal modal triggers
        if (e.target.matches('a[href="#openPortalModal"]') || 
            e.target.closest('a[href="#openPortalModal"]') ||
            e.target.matches('.open-portal-modal') || 
            e.target.closest('.open-portal-modal') ||
            // Backward compatibility
            e.target.matches('a[href="#openVideoModal"]') || 
            e.target.closest('a[href="#openVideoModal"]') ||
            e.target.matches('.open-video-modal') || 
            e.target.closest('.open-video-modal')) {
            e.preventDefault();
            openModal();
        }

        if (e.target.matches('.close-btn') || e.target.closest('.close-btn')) {
            e.preventDefault();
            closeModal();
        }

        if (e.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('show')) {
            closeModal();
        }
    });

    // Handle form submission
    document.addEventListener('wpcf7mailsent', function(event) {
        console.log('✅ TPA: Portal access form submitted successfully');
        setTimeout(() => {
            closeModal();
            window.location.href = '<?php echo get_option('tpa_redirect_url', home_url('/treasury-tech-portal/')); ?>';
        }, 1000);
    });

    // Global functions
    window.clearPortalAccess = function() {
        clearStoredAccess();
        document.cookie = 'portal_access_token=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
        document.cookie = 'user_identifier=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
        document.cookie = 'access_granted_time=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
        location.reload();
    };

    window.revokePortalAccess = function() {
        if (confirm('Are you sure you want to sign out of the portal?')) {
            clearPortalAccess();
        }
    };

    // Backward compatibility
    window.clearVideoAccess = window.clearPortalAccess;
    window.revokeVideoAccess = window.revokePortalAccess;
});
</script>

<style>
/* Portal Modal Styles */
.tpa-modal {
    position: fixed !important;
    top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important;
    width: 100vw !important; height: 100vh !important;
    z-index: 1000003 !important;
    background: linear-gradient(135deg, rgba(0, 0, 0, .4), rgba(40, 19, 69, .3) 50%, rgba(0, 0, 0, .4)) !important;
    backdrop-filter: blur(15px) saturate(120%) !important;
    display: flex !important; align-items: center !important; justify-content: center !important;
    opacity: 0 !important; visibility: hidden !important; pointer-events: none !important;
    transition: all .4s cubic-bezier(.4, 0, .2, 1) !important;
}

.tpa-modal.show {
    opacity: 1 !important;
    visibility: visible !important;
    pointer-events: auto !important;
}

.tpa-modal-content {
    max-width: calc(100vw - 40px) !important;
    max-height: calc(100vh - 40px) !important;
    padding: 20px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    transform: scale(.9) !important;
    transition: all .4s cubic-bezier(.4, 0, .2, 1) !important;
}

.tpa-modal.show .tpa-modal-content {
    transform: scale(1) !important;
}

.portal-access-form {
    width: 100% !important; max-width: 520px !important; min-width: 320px !important;
    margin: 0 auto !important; padding: 32px !important;
    background: linear-gradient(135deg, hsla(0, 0%, 100%, .95), hsla(0, 0%, 97%, .98) 50%, hsla(0, 0%, 100%, .95)) !important;
    backdrop-filter: blur(20px) saturate(130%) !important;
    border: 2px solid rgba(199, 125, 255, .3) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(114, 22, 244, .15) !important;
    position: relative !important;
    overflow: hidden !important;
}

.portal-access-form:before {
    content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, #7216f4, #8f47f6 50%, #9d4edd);
    border-radius: 16px 16px 0 0;
}

.close-btn {
    position: absolute !important; top: 12px !important; right: 16px !important;
    background: hsla(0, 0%, 100%, .9) !important;
    border: 1px solid rgba(199, 125, 255, .3) !important;
    border-radius: 50% !important; font-size: 18px !important;
    color: #7216f4 !important; cursor: pointer !important;
    width: 32px !important; height: 32px !important;
    display: flex !important; align-items: center !important; justify-content: center !important;
    transition: all .3s ease !important;
}

.close-btn:hover {
    background: rgba(114, 22, 244, .1) !important;
    transform: scale(1.1) !important;
}

body.modal-open {
    overflow: hidden !important;
}

@media (max-width: 768px) {
    .tpa-modal-content { padding: 10px !important; }
    .portal-access-form { padding: 24px 20px !important; min-width: 280px !important; }
    .close-btn { top: 8px !important; right: 12px !important; width: 28px !important; height: 28px !important; }
}
</style>
