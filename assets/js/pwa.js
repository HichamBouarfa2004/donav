// PWA Registration and Management
class PWAManager {
    constructor() {
        this.debug = true; // Enable debug mode
        this.init();
    }
    
    log(message, ...args) {
        if (this.debug) {
            console.log('[PWA]', message, ...args);
        }
    }
    
    error(message, ...args) {
        if (this.debug) {
            console.error('[PWA ERROR]', message, ...args);
        }
    }

    async init() {
        if ('serviceWorker' in navigator) {
            try {
                // Clear old caches first
                await this.clearOldCaches();
                
                // Try different service worker paths
                let swPath = '/no9ati/sw.js';
                if (window.location.pathname.includes('/no9ati/')) {
                    swPath = './sw.js';
                }
                
                const registration = await navigator.serviceWorker.register(swPath, {
                    scope: '/no9ati/'
                });
                this.log('Service Worker registered successfully:', registration);

                // Check for updates
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            this.showUpdateNotification();
                        }
                    });
                });
                
                // Force update check
                registration.update();
            } catch (error) {
                this.error('Service Worker registration failed:', error);
                // Try alternative registration
                try {
                    await navigator.serviceWorker.register('./sw.js');
                    this.log('Service Worker registered with fallback path');
                } catch (fallbackError) {
                    this.error('Fallback service worker registration failed:', fallbackError);
                }
            }
        }

        // Handle install prompt
        this.handleInstallPrompt();
        
        // Handle offline/online events
        this.handleNetworkStatus();
        
        // Initialize background sync
        this.initBackgroundSync();
    }

    handleInstallPrompt() {
        let deferredPrompt;

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            this.showInstallButton(deferredPrompt);
        });

        window.addEventListener('appinstalled', () => {
            this.log('No9ati PWA was installed');
            this.hideInstallButton();
            // Show success notification
            this.showInstallSuccess();
        });
    }

    showInstallButton(deferredPrompt) {
        const installBtn = document.createElement('button');
        installBtn.className = 'btn btn-outline-primary position-fixed';
        installBtn.style.cssText = `
            bottom: 20px;
            right: 20px;
            z-index: 1050;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        `;
        installBtn.innerHTML = '📱 Installer l\'app';
        installBtn.id = 'pwa-install-btn';

        installBtn.addEventListener('click', async () => {
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            this.log(`User response to the install prompt: ${outcome}`);
            deferredPrompt = null;
            this.hideInstallButton();
        });

        document.body.appendChild(installBtn);

        // Auto-hide after 10 seconds
        setTimeout(() => {
            this.hideInstallButton();
        }, 10000);
    }

    hideInstallButton() {
        const installBtn = document.getElementById('pwa-install-btn');
        if (installBtn) {
            installBtn.remove();
        }
    }
    
    showInstallSuccess() {
        const successAlert = document.createElement('div');
        successAlert.className = 'alert alert-success alert-dismissible fade show position-fixed';
        successAlert.style.cssText = `
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1055;
            min-width: 300px;
        `;
        successAlert.innerHTML = `
            <strong>Installation réussie!</strong>
            <p class="mb-0">No9ati a été installé sur votre appareil.</p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
        `;
        document.body.appendChild(successAlert);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (successAlert.parentNode) {
                successAlert.remove();
            }
        }, 5000);
    }

    showUpdateNotification() {
        const updateAlert = document.createElement('div');
        updateAlert.className = 'alert alert-info alert-dismissible fade show position-fixed';
        updateAlert.style.cssText = `
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1055;
            min-width: 300px;
        `;
        updateAlert.innerHTML = `
            <strong>Mise à jour disponible!</strong>
            <p class="mb-2">Une nouvelle version de No9ati est disponible.</p>
            <button class="btn btn-sm btn-primary me-2" onclick="window.location.reload()">
                Mettre à jour
            </button>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
        `;

        document.body.appendChild(updateAlert);
    }

    handleNetworkStatus() {
        const updateNetworkStatus = () => {
            const isOnline = navigator.onLine;
            const statusIndicator = document.getElementById('network-status');
            
            if (!statusIndicator) {
                this.createNetworkStatusIndicator();
            }

            const indicator = document.getElementById('network-status');
            if (isOnline) {
                // Hide the indicator when online
                indicator.style.display = 'none';
            } else {
                // Only show indicator when offline
                indicator.className = 'badge bg-danger position-fixed';
                indicator.innerHTML = '🔴 Hors ligne';
                indicator.style.display = 'block';
            }
        };

        window.addEventListener('online', updateNetworkStatus);
        window.addEventListener('offline', updateNetworkStatus);
        updateNetworkStatus(); // Initial check
    }

    createNetworkStatusIndicator() {
        const indicator = document.createElement('div');
        indicator.id = 'network-status';
        indicator.style.cssText = `
            top: 10px;
            left: 10px;
            z-index: 1060;
            font-size: 12px;
        `;
        document.body.appendChild(indicator);
    }

    async initBackgroundSync() {
        if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
            const registration = await navigator.serviceWorker.ready;
            
            // Register background sync for offline actions
            try {
                await registration.sync.register('background-sync');
                console.log('Background sync registered');
            } catch (error) {
                console.log('Background sync registration failed:', error);
            }
        }
    }

    // Store data for offline use
    storeOfflineData(key, data) {
        if ('localStorage' in window) {
            try {
                localStorage.setItem(`no9ati_offline_${key}`, JSON.stringify(data));
            } catch (error) {
                console.log('Failed to store offline data:', error);
            }
        }
    }

    // Retrieve offline data
    getOfflineData(key) {
        if ('localStorage' in window) {
            try {
                const data = localStorage.getItem(`no9ati_offline_${key}`);
                return data ? JSON.parse(data) : null;
            } catch (error) {
                console.log('Failed to retrieve offline data:', error);
                return null;
            }
        }
        return null;
    }

    // Clear offline data
    clearOfflineData(key) {
        if ('localStorage' in window) {
            localStorage.removeItem(`no9ati_offline_${key}`);
        }
    }

    // Clear old caches
    async clearOldCaches() {
        try {
            const cacheNames = await caches.keys();
            const oldCaches = cacheNames.filter(name => 
                name.startsWith('no9ati-') && name !== 'no9ati-offline-v2.0.0'
            );
            
            await Promise.all(
                oldCaches.map(cacheName => caches.delete(cacheName))
            );
            
            if (oldCaches.length > 0) {
                console.log('Cleared old caches:', oldCaches);
            }
        } catch (error) {
            console.log('Error clearing caches:', error);
        }
    }
}

// Enhanced responsive utilities for PWA
class ResponsiveUtils {
    constructor() {
        this.init();
    }

    init() {
        this.handleViewportChanges();
        this.optimizeForTouchDevices();
        this.handleOrientationChange();
    }

    handleViewportChanges() {
        const resizeHandler = () => {
            // Update CSS custom properties for dynamic sizing
            document.documentElement.style.setProperty('--vh', `${window.innerHeight * 0.01}px`);
            
            // Adjust layout for different screen sizes
            this.adjustLayoutForScreenSize();
        };

        window.addEventListener('resize', resizeHandler);
        window.addEventListener('orientationchange', resizeHandler);
        resizeHandler(); // Initial call
    }

    adjustLayoutForScreenSize() {
        const width = window.innerWidth;
        const mainContent = document.querySelector('.main-content');
        
        if (width < 992 && mainContent) {
            // Mobile optimization
            mainContent.style.marginLeft = '0';
            mainContent.style.paddingTop = '70px';
        } else if (width >= 992 && mainContent) {
            // Desktop optimization
            mainContent.style.marginLeft = '250px';
            mainContent.style.paddingTop = '70px';
        }
    }

    optimizeForTouchDevices() {
        if ('ontouchstart' in window) {
            document.body.classList.add('touch-device');
            
            // Increase touch targets
            const style = document.createElement('style');
            style.textContent = `
                .touch-device .btn {
                    min-height: 44px;
                    padding: 12px 16px;
                }
                .touch-device .nav-link {
                    padding: 16px 20px;
                }
                .touch-device .table td,
                .touch-device .table th {
                    padding: 16px 12px;
                }
            `;
            document.head.appendChild(style);
        }
    }

    handleOrientationChange() {
        window.addEventListener('orientationchange', () => {
            setTimeout(() => {
                // Force layout recalculation after orientation change
                window.dispatchEvent(new Event('resize'));
            }, 100);
        });
    }
}

// PWA-specific enhancements to existing app
class PWAEnhancedApp extends No9atiApp {
    constructor() {
        super();
        this.pwaManager = new PWAManager();
        this.responsiveUtils = new ResponsiveUtils();
        this.initPWAFeatures();
    }

    initPWAFeatures() {
        this.addPullToRefresh();
        this.enhanceMobileNavigation();
        this.addKeyboardShortcuts();
        this.optimizeForPWA();
        this.addDebugInfo();
    }
    
    addDebugInfo() {
        // Add debug info for mobile troubleshooting
        if (this.pwaManager.debug) {
            const debugInfo = {
                isStandalone: window.matchMedia('(display-mode: standalone)').matches,
                hasServiceWorker: 'serviceWorker' in navigator,
                isSecure: location.protocol === 'https:' || location.hostname === 'localhost',
                userAgent: navigator.userAgent,
                viewport: {
                    width: window.innerWidth,
                    height: window.innerHeight
                }
            };
            
            this.pwaManager.log('PWA Debug Info:', debugInfo);
            
            // Store debug info for potential mobile access
            localStorage.setItem('pwa_debug_info', JSON.stringify(debugInfo));
        }
    }

    addPullToRefresh() {
        let startY = 0;
        let currentY = 0;
        let isRefreshing = false;

        document.addEventListener('touchstart', (e) => {
            startY = e.touches[0].pageY;
        });

        document.addEventListener('touchmove', (e) => {
            currentY = e.touches[0].pageY;
            
            if (window.scrollY === 0 && currentY > startY + 100 && !isRefreshing) {
                this.triggerRefresh();
            }
        });
    }

    triggerRefresh() {
        if ('vibrate' in navigator) {
            navigator.vibrate(50);
        }
        
        window.location.reload();
    }

    enhanceMobileNavigation() {
        // Add swipe gestures for mobile navigation
        let startX = 0;
        let startY = 0;

        document.addEventListener('touchstart', (e) => {
            startX = e.touches[0].pageX;
            startY = e.touches[0].pageY;
        });

        document.addEventListener('touchend', (e) => {
            const endX = e.changedTouches[0].pageX;
            const endY = e.changedTouches[0].pageY;
            const diffX = startX - endX;
            const diffY = startY - endY;

            // Horizontal swipe detection
            if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
                if (diffX > 0) {
                    // Swipe left - could close sidebar
                    this.handleSwipeLeft();
                } else {
                    // Swipe right - could open sidebar
                    this.handleSwipeRight();
                }
            }
        });
    }

    handleSwipeLeft() {
        const offcanvas = document.getElementById('sidebarOffcanvas');
        if (offcanvas && window.bootstrap) {
            const offcanvasInstance = bootstrap.Offcanvas.getInstance(offcanvas);
            if (offcanvasInstance) {
                offcanvasInstance.hide();
            }
        }
    }

    handleSwipeRight() {
        if (window.innerWidth < 992) {
            const offcanvas = document.getElementById('sidebarOffcanvas');
            if (offcanvas && window.bootstrap) {
                const offcanvasInstance = bootstrap.Offcanvas.getOrCreateInstance(offcanvas);
                offcanvasInstance.show();
            }
        }
    }

    addKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Alt + D = Dashboard
            if (e.altKey && e.key === 'd') {
                e.preventDefault();
                window.location.href = 'index.php?page=dashboard';
            }
            
            // Alt + C = Classes
            if (e.altKey && e.key === 'c') {
                e.preventDefault();
                window.location.href = 'index.php?page=manage_classes';
            }
            
            // Alt + A = Add Student
            if (e.altKey && e.key === 'a') {
                e.preventDefault();
                window.location.href = 'index.php?page=add_student';
            }
            
            // Escape = Close modals
            if (e.key === 'Escape') {
                const openModals = document.querySelectorAll('.modal.show');
                openModals.forEach(modal => {
                    if (window.bootstrap) {
                        const modalInstance = bootstrap.Modal.getInstance(modal);
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                    }
                });
            }
        });
    }

    optimizeForPWA() {
        // Prevent zoom on input focus (iOS)
        const metaViewport = document.querySelector('meta[name="viewport"]');
        if (metaViewport) {
            metaViewport.setAttribute('content', 
                'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no'
            );
        }

        // Add app-like styling
        if (window.matchMedia('(display-mode: standalone)').matches) {
            document.body.classList.add('pwa-standalone');
            
            // Add status bar spacing for standalone mode
            const style = document.createElement('style');
            style.textContent = `
                .pwa-standalone .navbar {
                    padding-top: env(safe-area-inset-top, 0px);
                }
                .pwa-standalone .sidebar {
                    padding-top: env(safe-area-inset-top, 0px);
                }
            `;
            document.head.appendChild(style);
        }
    }

    // Enhanced modal handling for PWA
    showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal && window.bootstrap) {
            const modalInstance = bootstrap.Modal.getOrCreateInstance(modal);
            modalInstance.show();
            
            // Add haptic feedback on mobile
            if ('vibrate' in navigator) {
                navigator.vibrate(10);
            }
        } else {
            // Fallback to parent class method
            super.showModal(modalId);
        }
    }
}

// Initialize PWA features when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Replace the standard app with PWA-enhanced version
    if (window.no9atiApp) {
        window.no9atiApp = new PWAEnhancedApp();
    } else {
        window.no9atiApp = new PWAEnhancedApp();
    }
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { PWAManager, ResponsiveUtils, PWAEnhancedApp };
}