# FFGroceryTrack

FFGroceryTrack is a Malay-language grocery inventory and purchase-request system. It gives teams one place to track stock, expiry dates, restock needs, requests, receipts, and the activity behind each change.

Built with Laravel, Blade, MariaDB, Spatie Laravel Permission, and a Tailwind CSS/Vite frontend toolchain, it is also available as an installable progressive web app with a basic offline fallback.

## What it does

- Tracks inventory by category, item type, capacity, available quantity, percentage remaining, expiry date, and restock threshold.
- Provides searchable, sortable inventory and restock views, including quick stock adjustments and expired-item totals.
- Records category colours, audit logs, and inventory changes.
- Supports three request types: **Pantry**, **General**, and weekly **Lunch** claims.
- Guides Pantry and General requests through approval, receipt upload, and completion; receipt files can be JPEG, PNG, or PDF up to 5 MB.
- Lets Superadmins manage users, categories, purchasing-platform and payment-method presets, and activity logs.
- Sends optional Telegram reminders for items that are out of stock or at/below their restock threshold.

The application uses the `Asia/Kuala_Lumpur` timezone and is configured for Malay (`ms`) by default.

## Roles and access

| Role | Access |
| --- | --- |
| **Superadmin** | Inventory and restock management, all purchase-request review, user management, categories, request presets, and activity logs. |
| **Stocker** | Inventory and restock management, personal purchase-request history, new requests, and receipt uploads for approved Pantry/General requests. |
| **Tracker** | Inventory and restock management only. |

All application pages other than the login page require authentication.

## Purchase-request workflow

1. A Stocker submits a Pantry, General, or weekly Lunch request.
2. A Superadmin approves or rejects the request.
3. A Pantry or General request may include one optional quotation/invoice supporting document before approval.
4. An approved Pantry or General request remains pending until its owner uploads the purchase receipt.
5. Uploading the receipt completes the request. The first Superadmin to open a completed purchase receipt is recorded in the activity log.

Lunch requests are submitted as daily entries for a selected week and are completed when the Superadmin records the decision.

## Requirements

- PHP 8.3 or a compatible PHP 8.x release
- Composer 2
- Node.js (current LTS) and npm
- MySQL or MariaDB

The included Docker environment uses PHP 8.4-FPM, MariaDB 11.8, and Nginx. The frontend uses Tailwind CSS 4 and Vite 8.

## Local setup

Create an empty MySQL or MariaDB database, then copy and configure the environment file.

```bash
# macOS / Linux
cp .env.example .env

# Windows PowerShell
Copy-Item .env.example .env
```

For a host-native setup, update at least these values in `.env` to match your local database:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ffgrocerytrack
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

# Local HTTP does not use secure cookies.
SESSION_SECURE_COOKIE=false
```

Install the dependencies, generate the application key, run migrations, build the frontend assets, and seed the initial roles and development account:

```bash
composer run setup
php artisan db:seed
```

Start the development processes:

```bash
composer run dev
```

This starts Laravel, the queue listener, Laravel Pail, and the Vite development server. If you prefer separate processes, use `php artisan serve` and `npm run dev`.

### Development seed account

`php artisan db:seed` creates a development-only Superadmin account:

| Field | Value |
| --- | --- |
| Email | `user@email.com` |
| Password | `12345678` |

Change this password immediately after signing in. Never expose an environment that retains these seeded credentials.

## Docker setup

The Compose stack exposes the app at [http://localhost:8094](http://localhost:8094), phpMyAdmin at [http://localhost:8095](http://localhost:8095), and MariaDB on port `8306`.

Copy `.env.example` to `.env`, then use these Docker-oriented settings:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8094

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=ffgrocerytrack
DB_USERNAME=laravel
DB_PASSWORD=secret

SESSION_SECURE_COOKIE=false
```

Start the containers and finish the application setup:

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

If MariaDB is still initialising and the migration cannot connect, wait briefly and rerun the migration command.

The `app` image deliberately does not contain Node.js. The current web UI serves its tracked stylesheet from `public/css/app.css`, so a Vite build is not required just to run the Docker-served application. Use Node.js on the host (or in CI) when developing or rebuilding the frontend assets:

```bash
npm install
npm run build
```

The Compose database usernames and passwords are local-development defaults only. Replace them with strong, separately managed values before any non-local deployment.

## Telegram restock alerts

Set the following values in `.env` to enable notifications:

```dotenv
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_CHAT_ID=your_chat_id
```

Run an alert manually with:

```bash
php artisan telegram:send-restock-alert
```

Alerts include items whose available quantity is less than or equal to their restock threshold. The scheduler runs this command every weekday at 18:00 Malaysia time; the Docker `scheduler` service keeps it running automatically.

## API

The application exposes a token-authenticated API under `/api`, intended for the companion mobile client. Sign in through `POST /api/login`, then send the returned token in an `Authorization: Bearer <token>` header. Do not place tokens in URLs or source control.

| Area | Endpoints |
| --- | --- |
| Authentication | `POST /api/login`, `POST /api/logout`, `GET /api/user` |
| Inventory | `/api/inventori`, `/api/inventori/restok`, `/api/kategori` |
| Requests | `/api/tuntutan`, `/api/tuntutan/{id}/status`, `POST /api/tuntutan/{id}/lampiran`, `GET /api/tuntutan/{id}/lampiran`, `GET /api/tuntutan/{id}/supporting-document`, `POST /api/tuntutan/{id}/detail-reviewed` |
| Presets | `/api/tuntutan-preset` |
| Superadmin | `/api/pengguna`, `/api/log-aktiviti` |

Use multipart form data to include the optional `purchase_attachment` (JPG, JPEG, PNG, or PDF; maximum 5 MB) when submitting a Pantry or General request. The final `attachment` receipt remains a separate upload after approval. Claim responses include `documents` metadata plus availability and authorised endpoint URLs for each document. Role checks are enforced by the application. See [routes/api.php](routes/api.php) and the API controller for the exact HTTP methods, validation rules, and response payloads.

## Testing

Run the test suite with:

```bash
composer run test
```

Tests use an in-memory SQLite database, so they do not modify the configured development database.

## Production notes

- Configure the web server document root as `public`.
- Use HTTPS, set `APP_ENV=production`, set `APP_DEBUG=false`, and keep `SESSION_SECURE_COOKIE=true`.
- Keep `.env`, Telegram credentials, database passwords, and API tokens out of source control.
- Run `php artisan migrate --force` as part of deployment, and build frontend assets when your deployment includes frontend-source changes.
- Ensure `storage` and `bootstrap/cache` are writable by the web-server user.
- Keep the scheduler running with `php artisan schedule:work` or an equivalent scheduled-job runner.

For shared-hosting deployments, the application can also load an environment file from a sibling `../private/.env` directory, keeping it outside the project web root.
