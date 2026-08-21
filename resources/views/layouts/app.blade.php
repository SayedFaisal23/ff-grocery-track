<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>@yield('title', 'Utama') | FFGrocery</title>
    
    <!-- Meta PWA -->
    <meta name="theme-color" content="#f4f7fb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="FFGrocery">
    
    <!-- PWA Icons -->
    <link rel="apple-touch-icon" href="/images/icon-192.png">
    <link rel="manifest" href="/manifest.json">
    <script src="/pwa.js?v=4" defer></script>
    
    <script>
        (() => {
            try {
                const savedTheme = localStorage.getItem('ffgrocery-theme');
                const theme = savedTheme === 'dark' ? 'dark' : 'light';

                document.documentElement.dataset.theme = theme;
                document.documentElement.style.colorScheme = theme;
            } catch (error) {
                document.documentElement.dataset.theme = 'light';
                document.documentElement.style.colorScheme = 'light';
            }
        })();
    </script>

    <!-- CSS Utama -->
    <link rel="stylesheet" href="/css/app.css?v=2.22">
    
    <!-- FontAwesome (untuk ikon sampingan) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner"></div>
        <div class="loading-copy">Memuatkan...</div>
    </div>

    <!-- Header Telefon Bimbit -->
    <div class="mobile-header">
        <button id="sidebarToggle" class="btn btn-secondary btn-sm" style="padding: 8px 12px;">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="logo-container" style="margin-bottom: 0;">
            <div class="logo-icon">F</div>
            <div class="logo-text" style="font-size: 1.25rem;">FFGrocery</div>
        </div>
        <button type="button" class="btn btn-secondary btn-sm theme-toggle theme-toggle-mobile" data-theme-toggle aria-label="Tukar ke mod cerah" title="Tukar ke mod cerah" style="padding: 8px 12px;">
            <i class="fa-solid fa-sun" data-theme-icon></i>
        </button>
    </div>

    <div class="app-container">
        
        <!-- Sidebar Utama -->
        <aside id="appSidebar" class="sidebar">
            <div class="logo-container">
                <div class="logo-icon">F</div>
                <span class="logo-text">FFGrocery</span>
            </div>
            
            <nav class="nav-menu">
                <a href="{{ route('inventori.index') }}" class="nav-item {{ Request::routeIs('inventori.index') || Request::is('/') ? 'active' : '' }}">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span>Inventori</span>
                </a>
                
                <a href="{{ route('inventori.restok') }}" class="nav-item {{ Request::routeIs('inventori.restok') ? 'active' : '' }}">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>Perlu Restok</span>
                </a>
                
                @hasanyrole('Superadmin|Stocker')
                @php
                    $awaitingReview = $purchaseRequestNotifications['awaiting_review'] ?? false;
                    $awaitingReceiptReview = $purchaseRequestNotifications['awaiting_receipt_review'] ?? false;
                    $awaitingRequesterDocument = ($purchaseRequestNotifications['awaiting_requester_document_upload'] ?? false)
                        || ($purchaseRequestNotifications['awaiting_receipt_upload'] ?? false);
                    $awaitingPaymentProof = $purchaseRequestNotifications['awaiting_payment_proof_upload'] ?? false;
                @endphp
                <a href="{{ route('tuntutan.index') }}" class="nav-item {{ Request::routeIs('tuntutan.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-receipt"></i>
                    <span>Purchase Request Form</span>
                    @if($awaitingReview || $awaitingReceiptReview || $awaitingRequesterDocument || $awaitingPaymentProof)
                        <span class="nav-notification-dots" aria-label="Purchase Request Form notifications">
                            @if($awaitingReview)
                                <span class="nav-notification-dot nav-notification-dot-review" aria-hidden="true"></span>
                                <span class="sr-only">A purchase request is awaiting your decision.</span>
                            @endif
                            @if($awaitingReceiptReview)
                                <span class="nav-notification-dot nav-notification-dot-uploaded-receipt" aria-hidden="true"></span>
                                <span class="sr-only">An uploaded purchase receipt is awaiting your review.</span>
                            @endif
                            @if($awaitingRequesterDocument)
                                <span class="nav-notification-dot nav-notification-dot-receipt" aria-hidden="true"></span>
                                <span class="sr-only">A purchase request needs your invoice or receipt upload.</span>
                            @endif
                            @if($awaitingPaymentProof)
                                <span class="nav-notification-dot nav-notification-dot-payment-proof" aria-hidden="true"></span>
                                <span class="sr-only">A company transfer request needs your payment proof upload.</span>
                            @endif
                        </span>
                    @endif
                </a>
                @endhasanyrole
                
                @role('Superadmin')
                <a href="{{ route('log_aktiviti.index') }}" class="nav-item {{ Request::routeIs('log_aktiviti.index') ? 'active' : '' }}">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <span>Log Aktiviti</span>
                </a>

                <div class="nav-divider" role="presentation"></div>

                <a href="{{ route('kategori.index') }}" class="nav-item {{ Request::routeIs('kategori.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-tags"></i>
                    <span>Kategori Editor</span>
                </a>

                <a href="{{ route('tuntutan-preset.index') }}" class="nav-item {{ Request::routeIs('tuntutan-preset.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-sliders"></i>
                    <span>Purchase Request Form Editor</span>
                </a>

                <a href="{{ route('pengguna.index') }}" class="nav-item {{ Request::routeIs('pengguna.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-users"></i>
                    <span>User Management</span>
                </a>
                @endrole
            </nav>
            
            <!-- Profil Pengguna & Log Keluar -->
            @auth
            <div class="user-profile-section">
                <div class="profile-info">
                    <div class="profile-avatar">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <div class="profile-details">
                        <div class="profile-name">{{ Auth::user()->name }}</div>
                        <div class="profile-role">
                            {{ Auth::user()->roles->first()?->name ?? 'Tiada Peranan' }}
                        </div>
                    </div>
                </div>

                <button type="button" class="btn btn-secondary btn-sm sidebar-action-button theme-toggle" data-theme-toggle aria-pressed="false">
                    <i class="fa-solid fa-sun" data-theme-icon></i>
                    <span data-theme-label>Mod cerah</span>
                </button>

                <button type="button" class="btn btn-secondary btn-sm sidebar-action-button pwa-install-button" data-pwa-install hidden>
                    <i class="fa-solid fa-download"></i>
                    <span>Pasang aplikasi</span>
                </button>
                
                <form action="{{ route('logout') }}" method="POST" class="sidebar-action-form">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm sidebar-action-button">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>Log Keluar</span>
                    </button>
                </form>
            </div>
            @endauth
        </aside>
        
        <!-- Kandungan Utama -->
        <main class="main-content">
            <!-- Mesej Notifikasi Sukses / Gagal -->
            @if(session('success'))
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i> {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-xmark"></i> {{ session('error') }}
                </div>
            @endif
            
            @yield('content')
        </main>
        
    </div>

    <script>
        // Pengurusan loading overlay
        window.addEventListener('load', () => {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.opacity = '0';
                setTimeout(() => {
                    overlay.style.display = 'none';
                }, 300);
            }
        });

        window.addEventListener('beforeunload', () => {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.display = 'flex';
                overlay.style.opacity = '1';
            }
        });

        const showLoadingOverlay = () => {
            const overlay = document.getElementById('loadingOverlay');

            if (overlay) {
                overlay.style.display = 'flex';
                overlay.style.opacity = '1';
            }
        };

        document.addEventListener('submit', (event) => {
            const form = event.target;

            if (!(form instanceof HTMLFormElement) || event.defaultPrevented || !form.checkValidity()) {
                return;
            }

            const dialog = form.closest('dialog[open]');
            if (dialog instanceof HTMLDialogElement) {
                dialog.close();
            }

            showLoadingOverlay();
        });

        const allowedClaimFileExtensions = new Set(['jpg', 'jpeg', 'png', 'pdf']);
        const maximumClaimFileSize = 5 * 1024 * 1024;

        const claimFileError = (file) => {
            if (!(file instanceof File)) {
                return 'Sila pilih satu fail.';
            }

            const extension = file.name.split('.').pop()?.toLowerCase();
            if (!extension || !allowedClaimFileExtensions.has(extension)) {
                return 'Hanya fail JPG, JPEG, PNG, atau PDF dibenarkan.';
            }

            if (file.size > maximumClaimFileSize) {
                return 'Saiz fail mesti 5 MB atau kurang.';
            }

            return null;
        };

        document.querySelectorAll('[data-file-dropzone]').forEach((dropzone) => {
            const uploadArea = dropzone.closest('[data-file-upload-area]');
            const input = uploadArea?.querySelector('[data-file-input]');
            const selection = uploadArea?.querySelector('[data-file-selection]');
            const fileName = uploadArea?.querySelector('[data-file-name]');
            const status = uploadArea?.querySelector('[data-file-status]');
            const removeButton = uploadArea?.querySelector('[data-file-remove]');
            const submitButton = uploadArea?.querySelector('[data-file-submit]');

            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            const setStatus = (message = '') => {
                if (status) {
                    status.textContent = message;
                }
            };

            const updateSelection = (file = null, message = '') => {
                const hasValidFile = file instanceof File;
                dropzone.classList.toggle('has-file', hasValidFile);
                dropzone.classList.remove('is-dragging', 'has-error');

                if (selection) {
                    selection.hidden = !hasValidFile;
                }

                if (fileName) {
                    fileName.textContent = hasValidFile ? file.name : '';
                }

                if (submitButton instanceof HTMLButtonElement) {
                    submitButton.disabled = !hasValidFile;
                    submitButton.setAttribute('aria-disabled', String(!hasValidFile));
                }

                setStatus(message);
            };

            const clearSelection = (message = 'Fail dibuang.') => {
                input.value = '';
                updateSelection(null, message);
            };

            const applyFile = (file, assignToInput = false) => {
                const error = claimFileError(file);

                if (error) {
                    input.value = '';
                    updateSelection(null, error);
                    dropzone.classList.add('has-error');
                    return false;
                }

                if (assignToInput) {
                    try {
                        const transfer = new DataTransfer();
                        transfer.items.add(file);
                        input.files = transfer.files;
                    } catch (error) {
                        clearSelection('Pelayar ini tidak menyokong fail seret dan lepas. Sila pilih fail menggunakan butang pilih fail.');
                        return false;
                    }
                }

                updateSelection(file, `Fail dipilih: ${file.name}`);
                return true;
            };

            input.addEventListener('change', () => {
                applyFile(input.files?.[0] ?? null);
            });

            dropzone.addEventListener('click', (event) => {
                if (input.disabled || event.target.closest('button, a, input')) {
                    return;
                }

                input.click();
            });

            dropzone.addEventListener('keydown', (event) => {
                if (input.disabled || (event.key !== 'Enter' && event.key !== ' ')) {
                    return;
                }

                event.preventDefault();
                input.click();
            });

            ['dragenter', 'dragover'].forEach((eventName) => {
                dropzone.addEventListener(eventName, (event) => {
                    if (input.disabled) {
                        return;
                    }

                    event.preventDefault();
                    dropzone.classList.add('is-dragging');
                });
            });

            dropzone.addEventListener('dragleave', (event) => {
                if (!dropzone.contains(event.relatedTarget)) {
                    dropzone.classList.remove('is-dragging');
                }
            });

            dropzone.addEventListener('drop', (event) => {
                if (input.disabled) {
                    return;
                }

                event.preventDefault();
                dropzone.classList.remove('is-dragging');
                const files = Array.from(event.dataTransfer?.files ?? []);

                if (files.length !== 1) {
                    clearSelection('Sila seret dan lepas satu fail sahaja.');
                    dropzone.classList.add('has-error');
                    return;
                }

                applyFile(files[0], true);
            });

            removeButton?.addEventListener('click', () => clearSelection());

            const documentUploadForm = dropzone.closest('form[data-file-upload-form], form[data-receipt-upload-form]');
            documentUploadForm?.addEventListener('submit', (event) => {
                if (claimFileError(input.files?.[0] ?? null)) {
                    event.preventDefault();
                    dropzone.classList.add('has-error');
                    setStatus(documentUploadForm.dataset.fileRequiredMessage || 'Sila pilih dokumen yang sah sebelum memuat naik.');
                    dropzone.focus();
                }
            });
        });

        const themeToggles = document.querySelectorAll('[data-theme-toggle]');
        const themeColorMeta = document.querySelector('meta[name="theme-color"]');

        const applyTheme = (theme, shouldPersist = true) => {
            const isLight = theme === 'light';
            const nextThemeLabel = isLight ? 'Mod gelap' : 'Mod cerah';

            document.documentElement.dataset.theme = isLight ? 'light' : 'dark';
            document.documentElement.style.colorScheme = isLight ? 'light' : 'dark';

            if (themeColorMeta) {
                themeColorMeta.content = isLight ? '#f4f7fb' : '#1e293b';
            }

            themeToggles.forEach(toggle => {
                toggle.setAttribute('aria-label', `Tukar ke ${nextThemeLabel.toLowerCase()}`);
                toggle.setAttribute('aria-pressed', String(isLight));
                toggle.title = `Tukar ke ${nextThemeLabel.toLowerCase()}`;

                const icon = toggle.querySelector('[data-theme-icon]');
                if (icon) {
                    icon.classList.toggle('fa-sun', !isLight);
                    icon.classList.toggle('fa-moon', isLight);
                }

                const label = toggle.querySelector('[data-theme-label]');
                if (label) {
                    label.textContent = nextThemeLabel;
                }
            });

            if (shouldPersist) {
                try {
                    localStorage.setItem('ffgrocery-theme', isLight ? 'light' : 'dark');
                } catch (error) {
                    // Theme still works for this visit if browser storage is unavailable.
                }
            }
        };

        applyTheme(document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light', false);

        themeToggles.forEach(toggle => {
            toggle.addEventListener('click', () => {
                applyTheme(document.documentElement.dataset.theme === 'light' ? 'dark' : 'light');
            });
        });
        
        // Pengurusan menu togol telefon bimbit
        const sidebarToggle = document.getElementById('sidebarToggle');
        const appSidebar = document.getElementById('appSidebar');
        
        if (sidebarToggle && appSidebar) {
            sidebarToggle.addEventListener('click', () => {
                appSidebar.classList.toggle('open');
            });
            
            // Klik luar menu untuk tutup sidebar di telefon bimbit
            document.addEventListener('click', (e) => {
                if (!appSidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    appSidebar.classList.remove('open');
                }
            });
        }
    </script>
</body>
</html>
