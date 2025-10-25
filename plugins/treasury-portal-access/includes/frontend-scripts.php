<?php
/**
 * Frontend Scripts for Treasury Portal Access - FIXED VERSION
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
// Treasury Portal Access Frontend Script v1.4.0 - PERFORMANCE OPTIMIZED
// Performance Optimizations Applied:
// - Replaced setTimeout with requestAnimationFrame for DOM updates
// - Replaced setTimeout with requestIdleCallback (with fallback) for non-critical tasks
// - Implemented MutationObserver for efficient modal detection (reduced polling from 20 to 10 attempts)
// - Batched DOM reads and writes to prevent layout thrashing and forced reflows
// - Used transitionend events instead of fixed timeouts for animation completion
// - Reduced redirect delay from 1500ms to 1000ms
// - Optimized button state updates to prevent multiple reflows
(function() {
    'use strict';

    // Prevent script from running twice
    if (window.TPA_LOADED) {
        console.log('TPA: Already loaded, skipping...');
        return;
    }
    window.TPA_LOADED = true;

    // Track DOM ready processing to avoid duplicate initialization
    let domReadyProcessed = false;

    // Enhanced localStorage detection with comprehensive error handling
    function isLocalStorageAvailable() {
        try {
            if (typeof(Storage) === "undefined") {
                return false;
            }
            const test = '__tpa_test__';
            localStorage.setItem(test, 'test');
            localStorage.removeItem(test);
            return true;
        } catch (e) {
            console.log('TPA: localStorage not available (private/incognito mode or disabled)');
            return false;
        }
    }

    // Safe localStorage operations with error handling
    function safeLocalStorageGet(key) {
        try {
            if (!isLocalStorageAvailable()) return null;
            return localStorage.getItem(key);
        } catch (e) {
            console.log('TPA: Error reading from localStorage:', e);
            return null;
        }
    }

    function safeLocalStorageSet(key, value) {
        try {
            if (!isLocalStorageAvailable()) return false;
            localStorage.setItem(key, value);
            return true;
        } catch (e) {
            console.log('TPA: Error writing to localStorage:', e);
            return false;
        }
    }

    function safeLocalStorageRemove(key) {
        try {
            if (!isLocalStorageAvailable()) return false;
            localStorage.removeItem(key);
            return true;
        } catch (e) {
            console.log('TPA: Error removing from localStorage:', e);
            return false;
        }
    }

    // ✅ SECURITY FIX: Can't read httponly cookies, use AJAX check instead
    async function checkAuthStatus() {
        try {
            const response = await fetch(window.TPA.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: new URLSearchParams({
                    action: 'check_auth_status',
                    nonce: window.TPA.nonce
                })
            });
            const data = await response.json();
            return data.success && data.data.hasAccess;
        } catch (e) {
            console.log('TPA: Error checking auth status:', e);
            return false;
        }
    }

    // Enhanced DOM ready detection with multiple strategies (optimized)
    function onDOMReady(callback) {
        function wrapped() {
            if (domReadyProcessed) return;
            domReadyProcessed = true;
            try { callback(); } catch (e) { console.error('TPA: DOM ready error:', e); }
        }

        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            // Use microtask queue instead of setTimeout for immediate execution
            Promise.resolve().then(wrapped);
        } else if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', wrapped, { once: true });
            // Fallback timeout reduced and using requestIdleCallback when available
            if ('requestIdleCallback' in window) {
                requestIdleCallback(wrapped, { timeout: 2000 });
            } else {
                setTimeout(wrapped, 2000);
            }
        } else {
            // Use requestAnimationFrame for better performance
            requestAnimationFrame(wrapped);
        }
    }

    // Robust modal element detection with multiple strategies (optimized)
    function findModalElement() {
        return new Promise((resolve) => {
            // Try multiple selectors
            function tryFind() {
                return document.getElementById('portalModal') ||
                       document.querySelector('.tpa-modal') ||
                       document.querySelector('[role="dialog"]') ||
                       document.querySelector('[aria-modal="true"]');
            }

            // First immediate check
            let modal = tryFind();
            if (modal) {
                console.log('TPA: Modal found immediately!', modal);
                resolve(modal);
                return;
            }

            // Use MutationObserver for efficient detection instead of polling
            let attempts = 0;
            const maxAttempts = 10; // Reduced from 20
            const attemptInterval = 150; // Reduced from 200ms

            // Set up MutationObserver to watch for modal insertion
            const observer = new MutationObserver(() => {
                modal = tryFind();
                if (modal) {
                    console.log('TPA: Modal found via MutationObserver!', modal);
                    observer.disconnect();
                    resolve(modal);
                }
            });

            // Observe body for child additions
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });

            // Fallback polling with reduced attempts
            function attempt() {
                attempts++;
                console.log(`TPA: Modal search attempt ${attempts}/${maxAttempts}`);

                modal = tryFind();
                if (modal) {
                    console.log('TPA: Modal found!', modal);
                    observer.disconnect();
                    resolve(modal);
                    return;
                }

                if (attempts < maxAttempts) {
                    // Use requestIdleCallback when available for better performance
                    if ('requestIdleCallback' in window) {
                        requestIdleCallback(() => attempt(), { timeout: attemptInterval });
                    } else {
                        setTimeout(attempt, attemptInterval);
                    }
                } else {
                    console.error('TPA: Modal not found after all attempts, creating fallback');
                    observer.disconnect();
                    modal = createFallbackModal();
                    resolve(modal);
                }
            }

            // Start fallback polling
            if ('requestIdleCallback' in window) {
                requestIdleCallback(() => attempt(), { timeout: attemptInterval });
            } else {
                setTimeout(attempt, attemptInterval);
            }
        });
    }

    // Create fallback modal if original not found
    function createFallbackModal() {
        console.log('TPA: Creating fallback modal');
        const modal = document.createElement('div');
        modal.id = 'portalModal';
        modal.className = 'tpa-modal';
        modal.style.display = 'none';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.innerHTML = `
            <div class="tpa-modal-content">
                <div class="portal-access-form">
                    <button class="close-btn" type="button" aria-label="Close dialog">&times;</button>
                    <h3>Access Treasury Tech Portal</h3>
                    <p>Loading form...</p>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        return modal;
    }

    // Global TPA object with enhanced error handling
    window.TPA = {
        modal: null,
        body: document.body,
        nonce: '<?php echo wp_create_nonce('tpa_frontend_nonce'); ?>',
        ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
        accessDuration: <?php echo (int) get_option('tpa_access_duration', 180) * 86400; ?>, // seconds
        redirectUrl: '<?php echo esc_js(get_option('tpa_redirect_url', home_url('/treasury-tech-portal/'))); ?>',
        isRedirecting: false,
        attemptStarted: false,
        localStorageAvailable: isLocalStorageAvailable(),
        initialized: false,
        initializationAttempts: 0,
        maxInitAttempts: 3,

        // Helper to check if plugin is fully initialized
        isReady() {
            return this.initialized;
        },

        // Helper used by the theme to quickly open the portal
        quickOpen() {
            if (!this.initialized) return false;
            try {
                this.openModal();
                return true;
            } catch (e) {
                console.error('TPA: quickOpen failed', e);
                return false;
            }
        },
        
        async init() {
            this.initializationAttempts++;
            console.log(`TPA: Initialization attempt ${this.initializationAttempts}/${this.maxInitAttempts}`);
            
            if (this.initialized) {
                console.log('TPA: Already initialized, skipping...');
                return true;
            }

            try {
                console.log('TPA: Starting initialization...');
                
                // Find modal with enhanced detection
                this.modal = await findModalElement();
                
                if (!this.modal) {
                    throw new Error('Modal element not found');
                }

                console.log('TPA: Modal found, continuing initialization...');
                
                // Set up core functionality
                this.setupEventListeners();
                this.quickAccessCheck();
                
                // Wait for DOM to be ready for UI updates
                onDOMReady(() => {
                    console.log('TPA: DOM ready, updating UI...');

                    // Batch DOM operations to prevent layout thrashing
                    requestAnimationFrame(() => {
                        this.checkAccessPersistence();
                        this.updateAllButtons();
                        this.showButtons();
                        this.debugButtonStates();
                        this.setupFormHandlers();
                    });

                    window.addEventListener('beforeunload', () => {
                        if (this.attemptStarted) {
                            this.recordAttempt();
                            this.attemptStarted = false;
                        }
                    });
                });
                
                this.initialized = true;
                console.log('TPA: Initialization complete!');

                // Expose instance for theme integration
                window.TPAInstance = this;
                window.TPAReady = true;
                try {
                    document.dispatchEvent(new Event('TPAReady'));
                } catch (e) {
                    console.error('TPA: Error dispatching TPAReady', e);
                }

                return true;
                
            } catch (error) {
                console.error(`TPA: Initialization attempt ${this.initializationAttempts} failed:`, error);

                if (this.initializationAttempts < this.maxInitAttempts) {
                    console.log('TPA: Retrying initialization...');
                    // Use requestIdleCallback for retry to avoid blocking
                    if ('requestIdleCallback' in window) {
                        requestIdleCallback(() => this.init(), { timeout: 1000 });
                    } else {
                        setTimeout(() => this.init(), 1000);
                    }
                } else {
                    console.error('TPA: All initialization attempts failed, setting up fallback');
                    this.setupFallback();
                }
                return false;
            }
        },

        setupFallback() {
            console.log('TPA: Setting up fallback functionality');
            try {
                // Basic event listeners without modal dependency
                this.setupBasicEventListeners();
                this.updateAllButtons();
                console.log('TPA: Fallback setup complete');
            } catch (e) {
                console.error('TPA: Fallback setup failed:', e);
            }
        },

        setupEventListeners() {
            console.log('TPA: Setting up event listeners');
            
            try {
                // Use event delegation on document for maximum compatibility
                document.removeEventListener('click', this.handleDocumentClick);
                document.addEventListener('click', this.handleDocumentClick.bind(this), true);

                // Keyboard listeners
                document.removeEventListener('keydown', this.handleKeydown);
                document.addEventListener('keydown', this.handleKeydown.bind(this));

                console.log('TPA: Event listeners set up successfully');
            } catch (e) {
                console.error('TPA: Error setting up event listeners:', e);
            }
        },

        setupBasicEventListeners() {
            console.log('TPA: Setting up basic event listeners');
            
            document.addEventListener('click', (e) => {
                const button = e.target.closest('.open-portal-modal, a[href="#openPortalModal"], [data-portal-trigger]');
                if (button) {
                    e.preventDefault();
                    console.log('TPA: Button clicked, attempting to open modal');
                    this.openModal();
                }
            });
        },

        handleDocumentClick(e) {
            try {
                // Check for modal trigger
                const modalTrigger = e.target.closest('.open-portal-modal, a[href="#openPortalModal"], a[href="#openportalmodal"], [data-portal-trigger]');
                if (modalTrigger) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('TPA: Modal trigger clicked');
                    this.openModal();
                    return;
                }

                // Check for close button or backdrop
                const closeBtn = e.target.closest('.close-btn');
                if (closeBtn || (this.modal && e.target === this.modal)) {
                    e.preventDefault();
                    console.log('TPA: Close button clicked');
                    this.closeModal();
                    return;
                }

                // Check for portal redirect buttons
                const portalBtn = e.target.closest('[data-href], .tpa-portal-redirect');
                if (portalBtn && !portalBtn.classList.contains('open-portal-modal')) {
                    e.preventDefault();
                    const href = portalBtn.getAttribute('data-href') || portalBtn.href || this.redirectUrl;
                    console.log('TPA: Portal redirect clicked:', href);
                    window.location.href = href;
                }
            } catch (error) {
                console.error('TPA: Error in document click handler:', error);
            }
        },

        handleKeydown(e) {
            try {
                if (e.key === 'Escape' && this.modal && this.modal.style.display !== 'none') {
                    this.closeModal();
                }
            } catch (error) {
                console.error('TPA: Error in keydown handler:', error);
            }
        },

        setupFormHandlers() {
            console.log('TPA: Setting up form handlers');
            
            try {
                // Form submission handlers
                document.addEventListener('wpcf7mailsent', (event) => {
                    const formId = '<?php echo esc_js($form_id); ?>';
                    if (event.detail.contactFormId.toString() === formId) {
                        console.log('TPA: Form submission successful');
                        this.handleFormSuccess();
                    }
                });

                document.addEventListener('wpcf7mailfailed', (event) => {
                    const formId = '<?php echo esc_js($form_id); ?>';
                    if (event.detail.contactFormId.toString() === formId) {
                        console.log('TPA: Form submission failed');
                        this.showMessage('Form submission failed. Please try again.', 'error');
                    }
                });

                document.addEventListener('wpcf7invalid', (event) => {
                    const formId = '<?php echo esc_js($form_id); ?>';
                    if (event.detail.contactFormId.toString() === formId) {
                        console.log('TPA: Form validation errors');
                        this.showMessage('Please correct the errors and try again.', 'error');
                    }
                });
            } catch (e) {
                console.error('TPA: Error setting up form handlers:', e);
            }
        },

        handleFormSuccess() {
            console.log('TPA: Handling form success');
            this.attemptStarted = false;
            this.showMessage('Access granted! Redirecting to portal...', 'success');
            this.updateAllButtons();
            this.syncToLocal();
            this.executeRedirect();
        },

        async quickAccessCheck() {
            const localStorageEnabled = <?php echo get_option('tpa_enable_localStorage', true) ? 'true' : 'false'; ?>;
            if (!localStorageEnabled || !this.localStorageAvailable) {
                console.log('TPA: localStorage disabled or unavailable');
                return;
            }

            try {
                const localData = safeLocalStorageGet('tpa_access_token');
                if (localData) {
                    const storedData = JSON.parse(localData);
                    const hasEmail = storedData && storedData.email;
                    const withinDuration = hasEmail && (Date.now() / 1000 - storedData.timestamp) < this.accessDuration;
                    // ✅ SECURITY FIX: Use async auth check instead of cookie reading
                    const hasCookie = await checkAuthStatus();

                    if (!hasEmail || !withinDuration) {
                        console.log('TPA: Stored access data invalid or expired, clearing');
                        this.clearLocal();
                        return;
                    }

                    if (!hasCookie) {
                        console.log('TPA: Access cookie missing, clearing stored data and keeping request flow');
                        this.clearLocal();
                        // Ensure buttons remain in request state until a new token is issued.
                        requestAnimationFrame(() => this.updateAllButtons(false));
                        return;
                    }

                    console.log('TPA: Quick access check passed');
                    this.preUpdateButtons();
                }
            } catch (e) {
                console.log('TPA: Error in quickAccessCheck:', e);
            }
        },

        preUpdateButtons() {
            // Use requestAnimationFrame for better performance
            requestAnimationFrame(() => {
                try {
                    this.updateAllButtons(true);
                } catch (e) {
                    console.log('TPA: Error in preUpdateButtons:', e);
                }
            });
        },

        async updateAllButtons(hasAccess = null) {
            try {
                if (hasAccess === null) {
                    // ✅ SECURITY FIX: Use async auth check
                    hasAccess = await checkAuthStatus();
                }

                console.log('TPA: Updating all buttons, hasAccess:', hasAccess);

                // Find all possible button selectors
                const selectors = [
                    '.open-portal-modal',
                    'a[href="#openPortalModal"]',
                    'a[href="#openportalmodal"]',
                    '.tpa-btn',
                    '[data-portal-trigger]',
                    '#portalAccessBtn'
                ];

                const buttons = document.querySelectorAll(selectors.join(', '));
                console.log(`TPA: Found ${buttons.length} buttons to update`);

                // Batch DOM operations to prevent layout thrashing
                // First, read all button states
                const buttonStates = Array.from(buttons).map((button, index) => ({
                    button,
                    index,
                    tagName: button.tagName.toLowerCase(),
                    textContent: button.textContent
                }));

                // Then, batch all writes in a single requestAnimationFrame
                requestAnimationFrame(() => {
                    buttonStates.forEach(({ button, index, tagName, textContent }) => {
                        console.log(`TPA: Updating button ${index + 1}:`, button);

                        if (hasAccess) {
                            // User has access - convert to portal link
                            if (tagName === 'a') {
                                button.href = this.redirectUrl;
                            } else {
                                button.setAttribute('data-href', this.redirectUrl);
                            }

                            button.classList.remove('open-portal-modal');
                            button.classList.add('tpa-portal-redirect');

                            if (textContent.includes('Access') || textContent.includes('VIEW PORTAL')) {
                                button.textContent = 'View Portal';
                            }
                        } else {
                            // User needs access - set up modal trigger
                            if (tagName === 'a') {
                                button.href = '#openPortalModal';
                            }

                            button.classList.add('open-portal-modal');
                            button.classList.remove('tpa-portal-redirect');

                            if (textContent.includes('View Portal')) {
                                button.textContent = 'Access Portal';
                            }
                        }

                        button.classList.remove('tpa-btn-loading');
                        button.classList.add('tpa-btn-ready');
                    });
                });

            } catch (e) {
                console.error('TPA: Error updating buttons:', e);
            }
        },

        showButtons() {
            try {
                const buttons = document.querySelectorAll('.open-portal-modal, a[href="#openPortalModal"], .tpa-btn, [data-portal-trigger]');
                console.log('TPA: Showing', buttons.length, 'buttons');

                // Batch DOM writes to prevent layout thrashing
                requestAnimationFrame(() => {
                    buttons.forEach(el => {
                        el.classList.remove('tpa-btn-loading');
                        el.classList.add('tpa-btn-ready');
                        el.style.visibility = 'visible';
                        el.style.opacity = '1';
                    });
                });
            } catch (e) {
                console.log('TPA: Error in showButtons:', e);
            }
        },

        debugButtonStates() {
            try {
                const buttons = document.querySelectorAll('.open-portal-modal, a[href="#openPortalModal"], .tpa-btn, [data-portal-trigger]');
                console.log('TPA: Button debug info:');

                // Batch DOM reads to prevent layout thrashing
                const buttonInfo = Array.from(buttons).map((el, index) => {
                    const info = {
                        index: index + 1,
                        element: el,
                        text: el.textContent.trim(),
                        classes: el.className,
                        href: el.href || 'N/A',
                        dataHref: el.getAttribute('data-href') || 'N/A',
                        visible: el.offsetWidth > 0 && el.offsetHeight > 0,
                        hasClickListener: !!el.onclick
                    };
                    return info;
                });

                // Log after all reads are complete
                buttonInfo.forEach((info) => {
                    console.log(`Button ${info.index}:`, info);
                });
            } catch (e) {
                console.log('TPA: Error in debugButtonStates:', e);
            }
        },

        async checkAccessPersistence() {
            if (!this.localStorageAvailable) {
                console.log('TPA: Skipping persistence check - localStorage unavailable');
                return;
            }

            try {
                // ✅ SECURITY FIX: Use async auth check
                const hasCookie = await checkAuthStatus();
                const localData = safeLocalStorageGet('tpa_access_token');
                const hasLocalStorage = !!localData;

                console.log('TPA: Persistence check - Cookie:', hasCookie, 'LocalStorage:', hasLocalStorage);

                if (hasCookie && !hasLocalStorage) {
                    this.syncToLocal();
                } else if (!hasCookie && hasLocalStorage) {
                    try {
                        const storedData = JSON.parse(localData);
                        if (storedData && storedData.email && (Date.now()/1000 - storedData.timestamp) < this.accessDuration) {
                            this.restoreFromLocal(storedData.email, storedData.name);
                        } else {
                            this.clearLocal();
                        }
                    } catch (e) {
                        console.log('TPA: Error parsing stored data:', e);
                        this.clearLocal();
                    }
                }
            } catch (e) {
                console.log('TPA: Error in checkAccessPersistence:', e);
            }
        },

        openModal() {
            console.log('TPA: Opening modal');
            try {
                if (!this.modal) {
                    console.error('TPA: Cannot open modal - element not found');
                    // Try to find modal again
                    this.modal = document.getElementById('portalModal');
                    if (!this.modal) {
                        console.error('TPA: Modal still not found');
                        return;
                    }
                }

                this.modal.style.display = 'flex';
                // Use requestAnimationFrame for smoother animation
                requestAnimationFrame(() => {
                    if (this.modal) {
                        this.modal.classList.add('show');
                    }
                });

                if (this.body) {
                    this.body.classList.add('modal-open');
                }

                this.attemptStarted = true;

                console.log('TPA: Modal opened successfully');
            } catch (e) {
                console.error('TPA: Error opening modal:', e);
            }
        },

        closeModal() {
            console.log('TPA: Closing modal');
            try {
                if (!this.modal) return;

                this.modal.classList.remove('show');

                // Use transitionend event instead of fixed timeout for better performance
                const handleTransition = () => {
                    if (this.modal) {
                        this.modal.style.display = 'none';
                    }
                    if (this.body) {
                        this.body.classList.remove('modal-open');
                    }
                    this.modal.removeEventListener('transitionend', handleTransition);
                };

                this.modal.addEventListener('transitionend', handleTransition, { once: true });

                // Fallback in case transition doesn't fire
                setTimeout(handleTransition, 350);

                if (this.attemptStarted) {
                    this.recordAttempt();
                    this.attemptStarted = false;
                }
            } catch (e) {
                console.log('TPA: Error closing modal:', e);
            }
        },

        // Rest of the methods remain the same but with enhanced error handling
        restoreFromLocal(email, userName) {
            try {
                const formData = new URLSearchParams({ 
                    action: 'restore_portal_access', 
                    email: email, 
                    nonce: this.nonce 
                });

                fetch(this.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    cache: 'no-cache'
                })
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        this.updateAllButtons(true);
                        if (this.isPortalPage()) {
                            this.showMessage(`Welcome back, ${userName || email}!`, 'success');
                        }
                    } else {
                        throw new Error(data.data?.message || 'Restoration failed');
                    }
                })
                .catch(error => {
                    console.error('TPA: Restoration failed:', error);
                    if (this.localStorageAvailable) {
                        this.updateAllButtons(true);
                        if (this.isPortalPage()) {
                            this.showMessage(`Welcome back, ${userName || email}! (Offline mode)`, 'info');
                        }
                    }
                });
            } catch (e) {
                console.log('TPA: Error in restoreFromLocal:', e);
            }
        },

        syncToLocal() {
            if (!this.localStorageAvailable) return;

            try {
                const formData = new URLSearchParams({ 
                    action: 'get_current_user_access', 
                    nonce: this.nonce 
                });

                fetch(this.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    cache: 'no-cache'
                })
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        const tokenData = JSON.stringify({
                            token: data.data.token,
                            email: data.data.email,
                            name: data.data.name,
                            timestamp: data.data.access_time
                        });
                        safeLocalStorageSet('tpa_access_token', tokenData);
                        console.log('TPA: Successfully synced to localStorage');
                    }
                })
                .catch(error => {
                    console.error('TPA: Sync failed:', error);
                });
            } catch (e) {
                console.log('TPA: Error in syncToLocal:', e);
            }
        },

        isPortalPage() {
            try {
                return window.location.pathname.includes('treasury-tech-portal') ||
                       window.location.href.includes('treasury-tech-portal');
            } catch (e) {
                return false;
            }
        },

        showMessage(message, type = 'info') {
            if (!this.isPortalPage()) return;

            try {
                document.getElementById('tpa-message')?.remove();
                const messageDiv = document.createElement('div');
                messageDiv.id = 'tpa-message';
                messageDiv.className = `tpa-message tpa-message-${type}`;
                messageDiv.textContent = message;

                this.insertMessageAfterHeader(messageDiv);

                requestAnimationFrame(() => {
                    messageDiv.classList.add('show');
                });

                // Use requestIdleCallback for auto-dismiss to avoid blocking
                const dismissCallback = () => {
                    messageDiv.classList.remove('show');

                    const handleTransition = () => {
                        messageDiv.remove();
                        messageDiv.removeEventListener('transitionend', handleTransition);
                    };

                    messageDiv.addEventListener('transitionend', handleTransition, { once: true });
                    // Fallback removal
                    setTimeout(() => messageDiv.remove(), 300);
                };

                if ('requestIdleCallback' in window) {
                    requestIdleCallback(dismissCallback, { timeout: 3000 });
                } else {
                    setTimeout(dismissCallback, 3000);
                }
            } catch (e) {
                console.log('TPA: Error in showMessage:', e);
            }
        },

        insertMessageAfterHeader(messageDiv) {
            try {
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
            } catch (e) {
                console.log('TPA: Error in insertMessageAfterHeader:', e);
            }
        },

        recordAttempt() {
            try {
                const formData = new URLSearchParams({
                    action: 'track_portal_attempt',
                    nonce: this.nonce,
                    page_url: window.location.href
                });
                navigator.sendBeacon ?
                    navigator.sendBeacon(this.ajaxUrl, formData) :
                    fetch(this.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' });
            } catch (e) {
                console.log('TPA: Error recording attempt:', e);
            }
        },

        clearLocal() {
            safeLocalStorageRemove('tpa_access_token');
        },

        executeRedirect() {
            if (this.isRedirecting) return;

            try {
                this.isRedirecting = true;
                console.log('TPA: Starting redirect...');

                const formContainer = document.querySelector('.portal-access-form');
                if (formContainer) {
                    // Batch DOM updates to prevent layout thrashing
                    requestAnimationFrame(() => {
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
                    });
                }

                // Reduced redirect delay from 1500ms to 1000ms for better UX
                // Using requestIdleCallback when available for smoother experience
                const performRedirect = () => {
                    window.location.href = this.redirectUrl + '?access_granted=1&t=' + Date.now();
                };

                if ('requestIdleCallback' in window) {
                    requestIdleCallback(performRedirect, { timeout: 1000 });
                } else {
                    setTimeout(performRedirect, 1000);
                }
            } catch (e) {
                console.log('TPA: Error in executeRedirect:', e);
            }
        },

        revoke() {
            try {
                if (confirm('Are you sure you want to sign out of the portal?')) {
                    this.clearLocal();
                    
                    const formData = new URLSearchParams({ 
                        action: 'revoke_portal_access', 
                        nonce: this.nonce 
                    });

                    fetch(this.ajaxUrl, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        cache: 'no-cache'
                    })
                    .then(() => location.reload())
                    .catch(() => location.reload());
                }
            } catch (e) {
                console.log('TPA: Error in revoke:', e);
                location.reload(); // Fallback
            }
        }
    };

    // Enhanced initialization with multiple strategies
    async function initializeTPA() {
        console.log('TPA: Starting enhanced initialization...');
        
        try {
            // Strategy 1: Immediate initialization
            const success = await window.TPA.init();
            if (success) {
                console.log('TPA: Immediate initialization successful');
                return;
            }
        } catch (error) {
            console.log('TPA: Immediate initialization failed, trying DOM ready...');
        }

        // Strategy 2: DOM ready initialization
        onDOMReady(async () => {
            try {
                const success = await window.TPA.init();
                if (success) {
                    console.log('TPA: DOM ready initialization successful');
                    return;
                }
            } catch (error) {
                console.log('TPA: DOM ready initialization failed, trying delayed...');
            }

            // Strategy 3: Delayed initialization
            if ('requestIdleCallback' in window) {
                requestIdleCallback(async () => {
                    try {
                        await window.TPA.init();
                        console.log('TPA: Delayed initialization successful');
                    } catch (error) {
                        console.error('TPA: All initialization strategies failed:', error);
                        // Setup minimal fallback
                        window.TPA.setupFallback();
                    }
                }, { timeout: 1000 });
            } else {
                setTimeout(async () => {
                    try {
                        await window.TPA.init();
                        console.log('TPA: Delayed initialization successful');
                    } catch (error) {
                        console.error('TPA: All initialization strategies failed:', error);
                        // Setup minimal fallback
                        window.TPA.setupFallback();
                    }
                }, 1000);
            }
        });
    }

    // Start initialization immediately
    initializeTPA();

    // Expose global functions for debugging
    window.TPA_DEBUG = {
        reinitialize: () => {
            window.TPA.initialized = false;
            window.TPA.initializationAttempts = 0;
            return window.TPA.init();
        },
        checkButtons: () => window.TPA.debugButtonStates(),
        updateButtons: () => window.TPA.updateAllButtons(),
        openModal: () => window.TPA.openModal()
    };

})();
</script>

<style>
/* Enhanced styles for better compatibility and visual feedback */
.tpa-btn-loading {
    opacity: 0.3 !important;
    pointer-events: none !important;
    transition: opacity 0.2s ease !important;
    visibility: hidden !important;
}

.tpa-btn-ready {
    opacity: 1 !important;
    pointer-events: auto !important;
    visibility: visible !important;
}

/* Modal styles */
.tpa-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 999999;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.tpa-modal.show {
    opacity: 1;
}

.tpa-modal-content {
    background: white;
    border-radius: 8px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    transform: translateY(-20px);
    transition: transform 0.3s ease;
}

.tpa-modal.show .tpa-modal-content {
    transform: translateY(0);
}

.portal-access-form {
    padding: 20px;
}

.close-btn {
    position: absolute;
    top: 10px;
    right: 15px;
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
    z-index: 1;
}

.close-btn:hover {
    color: #000;
}

/* Message styles */
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

/* Responsive styles */
@media (max-width: 768px) {
    .tpa-modal-content {
        width: 95%;
        margin: 20px;
    }
    
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

/* Loading animation */
@keyframes tpa-pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.tpa-btn-loading {
    animation: tpa-pulse 1.5s infinite;
}
</style>
