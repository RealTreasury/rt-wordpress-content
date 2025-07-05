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
<style>
    :root {
        --primary-purple: #7216f4;
        --secondary-purple: #8f47f6;
        --light-purple: #c77dff;
        --dark-text: #281345;
        --gray-text: #7e7e7e;
    }
    .modal-bg {
        background: linear-gradient(135deg, rgba(0, 0, 0, .4), rgba(40, 19, 69, .3) 50%, rgba(0, 0, 0, .4));
        backdrop-filter: blur(15px) saturate(120%);
        -webkit-backdrop-filter: blur(15px) saturate(120%);
    }
    .form-container-bg {
        background: linear-gradient(135deg, hsla(0, 0%, 100%, .95), hsla(0, 0%, 97%, .98) 50%, hsla(0, 0%, 100%, .95));
        backdrop-filter: blur(20px) saturate(130%);
        -webkit-backdrop-filter: blur(20px) saturate(130%);
    }
    .border-light-purple {
        border-color: var(--light-purple);
    }
    .gradient-bar {
        background: linear-gradient(90deg, var(--primary-purple), var(--secondary-purple) 50%, #9d4edd);
    }

    /* Mobile enhancement styles */
    .mobile-device .portal-access-form {
        transition: all 0.3s ease !important;
    }

    .keyboard-open .portal-access-form {
        transition: all 0.2s ease !important;
    }

    /* Ensure the modal width fits smaller screens */
    @media (max-width: 480px) {
        #portalModal .max-w-lg {
            max-width: calc(100vw - 2rem);
        }
    }

    .form-progress-bar {
        width: 100%;
        height: 3px;
        background: rgba(199, 125, 255, 0.2);
        border-radius: 2px;
        margin: 16px 0 24px 0;
        overflow: hidden;
        position: relative;
        z-index: 3;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #7216f4, #8f47f6);
        width: 0%;
        transition: width 0.3s ease;
        border-radius: 2px;
    }

    .field-valid {
        border-color: #22c55e !important;
        box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1) !important;
    }

    .field-error {
        border-color: #ef4444 !important;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
    }

    /* Touch feedback improvements */
    .close-btn:active,
    .submit-btn:active,
    .wpcf7-submit:active {
        transition: none !important;
    }

    /* Mobile landscape specific fixes */
    @media (max-width: 768px) and (orientation: landscape) {
        .form-progress-bar {
            margin: 8px 0 16px 0;
        }

        .portal-access-form h3 {
            margin-bottom: 4px !important;
        }

        .portal-access-form .subtitle {
            margin-bottom: 12px !important;
        }
    }

    /* Accessibility improvements */
    @media (prefers-reduced-motion: reduce) {
        .modal,
        .modal-content,
        .portal-access-form,
        .progress-fill,
        .form-control,
        .wpcf7-form-control,
        .submit-btn,
        .close-btn {
            transition: none !important;
            animation: none !important;
        }
    }
</style>
<div id="portalModal" class="tpa-modal fixed inset-0 z-[1000003] flex items-center justify-center p-4 modal-bg transition-opacity duration-300 opacity-0 pointer-events-none" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="portalModalTitle">
    <div class="relative w-full max-w-lg mx-auto">
        <div class="relative form-container-bg rounded-2xl shadow-2xl border-2 border-light-purple overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-1.5 gradient-bar"></div>
            <button class="close-btn absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition-colors" type="button" aria-label="Close dialog">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
            <div class="p-8 md:p-10">
                <div class="portal-access-form">
                    <h3 id="portalModalTitle" class="text-2xl md:text-3xl font-bold text-dark-text text-center mb-4">Portal Access</h3>
                    <p class="text-center text-gray-text mb-8">Please enter your details to continue.</p>
                    <?php echo do_shortcode('[contact-form-7 id="' . esc_attr($form_id) . '"]'); ?>
                </div>
            </div>
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

<script>
(function() {
    'use strict';

    const MobileModalEnhancer = {
        init: function() {
            this.addViewportMeta();
            this.enhanceModalForMobile();
            this.addTouchSupport();
            this.preventBodyScroll();
            this.addKeyboardSupport();
        },

        addViewportMeta: function() {
            if (!document.querySelector('meta[name="viewport"]')) {
                const meta = document.createElement('meta');
                meta.name = 'viewport';
                meta.content = 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no';
                document.head.appendChild(meta);
            }
        },

        enhanceModalForMobile: function() {
            const modal = document.getElementById('portalModal');
            if (!modal) return;

            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || window.innerWidth <= 768;

            if (isMobile) {
                modal.classList.add('mobile-device');
                modal.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: false });
                modal.addEventListener('touchmove', this.handleTouchMove.bind(this), { passive: false });

                window.addEventListener('orientationchange', () => {
                    setTimeout(() => this.adjustModalForOrientation(), 100);
                });

                this.handleMobileKeyboard();
            }
        },

        handleTouchStart: function(e) {
            this.touchStartY = e.touches[0].clientY;
        },

        handleTouchMove: function(e) {
            const modal = document.getElementById('portalModal');
            const form = modal.querySelector('.portal-access-form');
            if (!form) return;

            if (!form.contains(e.target)) {
                e.preventDefault();
            }
        },

        adjustModalForOrientation: function() {
            const modal = document.getElementById('portalModal');
            const form = modal.querySelector('.portal-access-form');
            if (!form) return;

            if (window.matchMedia('(orientation: landscape)').matches) {
                form.style.maxHeight = 'calc(100vh - 20px)';
                form.style.padding = '16px';
            } else {
                form.style.maxHeight = 'calc(100vh - 60px)';
                form.style.padding = '24px 20px';
            }
        },

        handleMobileKeyboard: function() {
            const modal = document.getElementById('portalModal');
            const form = modal.querySelector('.portal-access-form');
            if (!form) return;
            let initialViewportHeight = window.innerHeight;

            const checkViewport = () => {
                const currentHeight = window.innerHeight;
                const heightDifference = initialViewportHeight - currentHeight;

                if (heightDifference > 150) {
                    modal.classList.add('keyboard-open');
                    form.style.maxHeight = `${currentHeight - 40}px`;
                    form.style.transform = 'translateY(-20px)';
                } else {
                    modal.classList.remove('keyboard-open');
                    form.style.maxHeight = 'calc(100vh - 60px)';
                    form.style.transform = 'translateY(0)';
                }
            };

            window.addEventListener('resize', checkViewport);

            const inputs = form.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                input.addEventListener('focus', () => {
                    setTimeout(checkViewport, 300);
                    setTimeout(() => {
                        input.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
                    }, 500);
                });
                input.addEventListener('blur', () => setTimeout(checkViewport, 300));
            });
        },

        addTouchSupport: function() {
            const modal = document.getElementById('portalModal');
            const closeBtn = modal.querySelector('.close-btn');

            [closeBtn].forEach(el => {
                if (!el) return;
                el.addEventListener('touchstart', function() { this.style.transform = 'scale(0.95)'; }, { passive: true });
                el.addEventListener('touchend', function() {
                    this.style.transform = 'scale(1.05)';
                    setTimeout(() => { this.style.transform = ''; }, 150);
                }, { passive: true });
            });

            const submitBtn = modal.querySelector('.wpcf7-submit, .submit-btn');
            if (submitBtn) {
                submitBtn.addEventListener('touchstart', function() { this.style.transform = 'translateY(-1px) scale(0.98)'; }, { passive: true });
                submitBtn.addEventListener('touchend', function() {
                    this.style.transform = 'translateY(-2px)';
                    setTimeout(() => { this.style.transform = ''; }, 200);
                }, { passive: true });
            }
        },

        preventBodyScroll: function() {
            let scrollPosition = 0;

            const openModal = () => {
                scrollPosition = window.pageYOffset;
                document.body.style.overflow = 'hidden';
                document.body.style.position = 'fixed';
                document.body.style.top = `-${scrollPosition}px`;
                document.body.style.width = '100%';
            };

            const closeModal = () => {
                document.body.style.removeProperty('overflow');
                document.body.style.removeProperty('position');
                document.body.style.removeProperty('top');
                document.body.style.removeProperty('width');
                window.scrollTo(0, scrollPosition);
            };

            if (window.TPA) {
                const originalOpenModal = window.TPA.openModal;
                const originalCloseModal = window.TPA.closeModal;

                window.TPA.openModal = function() {
                    openModal();
                    originalOpenModal.call(this);
                };

                window.TPA.closeModal = function() {
                    closeModal();
                    originalCloseModal.call(this);
                };
            }
        },

        addKeyboardSupport: function() {
            document.addEventListener('keydown', (e) => {
                const modal = document.getElementById('portalModal');
                if (!modal || modal.style.display !== 'flex') return;

                if (e.key === 'Tab') {
                    const focusable = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];

                    if (e.shiftKey) {
                        if (document.activeElement === first) {
                            last.focus();
                            e.preventDefault();
                        }
                    } else {
                        if (document.activeElement === last) {
                            first.focus();
                            e.preventDefault();
                        }
                    }
                }
            });
        }
    };

    const MobileFormEnhancer = {
        init: function() {
            this.improveFormValidation();
            this.addProgressIndicator();
            this.enhanceInputExperience();
        },

        improveFormValidation: function() {
            const modal = document.getElementById('portalModal');
            if (!modal) return;

            const inputs = modal.querySelectorAll('input[type="email"], input[type="text"], input[required]');
            inputs.forEach(input => {
                input.addEventListener('blur', this.validateField);
                input.addEventListener('input', this.clearFieldError);
            });
        },

        validateField: function(e) {
            const field = e.target;
            const isValid = field.checkValidity();
            field.classList.remove('field-error', 'field-valid');
            if (field.value.trim() !== '') {
                if (isValid) {
                    field.classList.add('field-valid');
                } else {
                    field.classList.add('field-error');
                }
            }
        },

        clearFieldError: function(e) {
            e.target.classList.remove('field-error');
        },

        addProgressIndicator: function() {
            const form = document.querySelector('#portalModal form');
            if (!form) return;

            const requiredFields = form.querySelectorAll('input[required], select[required]');
            let progressBar = document.createElement('div');
            progressBar.className = 'form-progress-bar';
            progressBar.innerHTML = '<div class="progress-fill"></div>';

            const title = form.querySelector('h3');
            if (title) {
                title.insertAdjacentElement('afterend', progressBar);
            }

            const updateProgress = () => {
                const completed = Array.from(requiredFields).filter(field => {
                    if (field.type === 'checkbox') {
                        return field.checked;
                    }
                    return field.value.trim() !== '' && field.checkValidity();
                }).length;

                const percentage = (completed / requiredFields.length) * 100;
                const fill = progressBar.querySelector('.progress-fill');
                fill.style.width = `${percentage}%`;
            };

            requiredFields.forEach(field => {
                field.addEventListener('input', updateProgress);
                field.addEventListener('change', updateProgress);
                field.addEventListener('blur', updateProgress);
            });
        },

        enhanceInputExperience: function() {
            const emailInputs = document.querySelectorAll('input[type="email"]');
            emailInputs.forEach(input => {
                input.setAttribute('autocomplete', 'email');
                input.setAttribute('inputmode', 'email');
            });

            const textInputs = document.querySelectorAll('input[type="text"]');
            textInputs.forEach(input => {
                if (input.name && input.name.toLowerCase().includes('name')) {
                    input.setAttribute('autocomplete', 'name');
                    input.setAttribute('inputmode', 'text');
                }
                if (input.name && input.name.toLowerCase().includes('company')) {
                    input.setAttribute('autocomplete', 'organization');
                    input.setAttribute('inputmode', 'text');
                }
            });
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            MobileModalEnhancer.init();
            MobileFormEnhancer.init();
        });
    } else {
        MobileModalEnhancer.init();
        MobileFormEnhancer.init();
    }

})();
</script>
<script>
// Mobile modal and form enhancements
// TODO: Replace placeholder implementations with the correct enhancement code.
const MobileModalEnhancer = {
    init: function() {
        if (window.innerWidth <= 768) {
            const modal = document.getElementById('portalModal');
            if (modal) modal.classList.add('mobile-enhanced');
        }
    }
};

const MobileFormEnhancer = {
    init: function() {
        if (window.innerWidth <= 768) {
            const form = document.querySelector('#portalModal form');
            if (form) form.setAttribute('autocomplete', 'on');
        }
    }
};

window.addEventListener('load', () => {
    if (window.TPA && typeof window.TPA.init === 'function') {
        MobileModalEnhancer.init();
        MobileFormEnhancer.init();
    }
});
</script>

