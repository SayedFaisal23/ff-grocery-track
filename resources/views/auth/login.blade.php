<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="FFGrocery">
    <title>Log Masuk | FFGrocery</title>
    
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/images/icon-192.png">
    <link rel="stylesheet" href="/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="/pwa.js?v=4" defer></script>
    
    <style>
        .login-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.15) 0%, transparent 40%),
                        radial-gradient(circle at 90% 80%, rgba(16, 185, 129, 0.12) 0%, transparent 40%),
                        #0b0f19;
            padding: 1.5rem;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            background-color: rgba(21, 28, 44, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 2.5rem;
            box-shadow: var(--shadow-lg);
            text-align: center;
        }

        .login-logo {
            margin-bottom: 2rem;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .login-logo .icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--color-primary), var(--color-success));
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            font-weight: 700;
        }

        .login-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .login-subtitle {
            color: var(--text-muted);
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }
        
        .error-message {
            color: var(--color-danger);
            font-size: 0.85rem;
            text-align: left;
            margin-top: 6px;
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="login-card">
            
            <div class="login-logo">
                <div class="icon">F</div>
                <div class="logo-text">FFGrocery</div>
            </div>

            <h2 class="login-title">Selamat Kembali</h2>
            <p class="login-subtitle">Sila log masuk untuk mengurus inventori groseri</p>

            <form action="{{ route('login') }}" method="POST">
                @csrf

                <div class="form-group" style="text-align: left;">
                    <label for="email" class="form-label">E-mel Pengguna</label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 16px; top: 14px; color: var(--text-muted);">
                            <i class="fa-solid fa-envelope"></i>
                        </span>
                        <input type="email" name="email" id="email" class="form-control" placeholder="nama@domain.com" value="{{ old('email') }}" style="padding-left: 45px;" required autofocus>
                    </div>
                    @error('email')
                        <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group" style="text-align: left; margin-bottom: 2rem;">
                    <label for="password" class="form-label">Kata Laluan</label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 16px; top: 14px; color: var(--text-muted);">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" style="padding-left: 45px;" required>
                    </div>
                    @error('password')
                        <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem; border-radius: var(--radius-sm);">
                    <span>Log Masuk</span>
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                </button>

                <button type="button" class="btn btn-secondary btn-sm pwa-install-button" data-pwa-install hidden style="margin-top: 1rem; width: 100%; justify-content: center;">
                    <i class="fa-solid fa-download"></i>
                    <span>Pasang aplikasi</span>
                </button>
            </form>

        </div>
    </div>

</body>
</html>
