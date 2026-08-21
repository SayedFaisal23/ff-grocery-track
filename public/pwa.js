(() => {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    let deferredInstallPrompt = null;
    let refreshingAfterUpdate = false;

    const installButton = document.querySelector('[data-pwa-install]');
    const updateNotice = document.querySelector('[data-pwa-update]');
    const updateButton = document.querySelector('[data-pwa-update-button]');

    const showInstallButton = () => {
        if (installButton) installButton.hidden = false;
    };

    const hideInstallButton = () => {
        if (installButton) installButton.hidden = true;
    };

    const showUpdateNotice = () => {
        if (updateNotice) updateNotice.hidden = false;
    };

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;
        showInstallButton();
    });

    window.addEventListener('appinstalled', () => {
        deferredInstallPrompt = null;
        hideInstallButton();
    });

    installButton?.addEventListener('click', async () => {
        if (!deferredInstallPrompt) return;

        deferredInstallPrompt.prompt();
        await deferredInstallPrompt.userChoice;
        deferredInstallPrompt = null;
        hideInstallButton();
    });

    window.addEventListener('load', async () => {
        try {
            const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });

            const promptForUpdate = () => {
                if (registration.waiting && navigator.serviceWorker.controller) {
                    showUpdateNotice();
                }
            };

            promptForUpdate();
            registration.addEventListener('updatefound', () => {
                registration.installing?.addEventListener('statechange', promptForUpdate);
            });

            updateButton?.addEventListener('click', () => {
                if (!registration.waiting) return;

                refreshingAfterUpdate = true;
                registration.waiting.postMessage({ type: 'SKIP_WAITING' });
            });
        } catch (error) {
            // A failed registration must not interfere with normal web use.
            console.warn('PWA service worker could not be registered.', error);
        }
    });

    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (refreshingAfterUpdate) {
            window.location.reload();
        }
    });
})();
