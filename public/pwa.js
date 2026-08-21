(() => {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    let deferredInstallPrompt = null;
    let refreshingAfterUpdate = false;
    let controllerReloaded = false;

    const installButton = document.querySelector('[data-pwa-install]');
    const showInstallButton = () => {
        if (installButton) installButton.hidden = false;
    };

    const hideInstallButton = () => {
        if (installButton) installButton.hidden = true;
    };

    const reloadForUpdate = () => {
        if (!refreshingAfterUpdate) return;

        window.location.reload();
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

            const activateWaitingWorker = () => {
                if (!registration.waiting || !navigator.serviceWorker.controller) {
                    return;
                }

                refreshingAfterUpdate = true;
                registration.waiting.postMessage({ type: 'SKIP_WAITING' });
            };

            activateWaitingWorker();
            registration.addEventListener('updatefound', () => {
                registration.installing?.addEventListener('statechange', () => {
                    if (registration.waiting) {
                        activateWaitingWorker();
                    }
                });
            });
        } catch (error) {
            // A failed registration must not interfere with normal web use.
            console.warn('PWA service worker could not be registered.', error);
        }
    });

    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (controllerReloaded) {
            return;
        }

        controllerReloaded = true;
        refreshingAfterUpdate = true;
        reloadForUpdate();
    });
})();
