# KPI Dashboard

## Admin foundation

PHP 8.2+ / Laravel 12 / MySQL 8+.

Implemented Admin foundation:
- Login / logout with session authentication
- Active/inactive Admin users
- User CRUD
- User Groups CRUD
- Group-based permission RBAC
- Permission assignment UI by module
- Permission middleware for Admin actions
- Admin dashboard summary
- Database migrations and seed data
- PhpSpreadsheet dependency prepared for KPI Excel import
- Database-backed queue for background email processing

### Default groups
- **Super Admin** — all permissions
- **KPI Admin** — administration and KPI operations
- **KPI Viewer** — dashboard read-only

### Default development account
`admin@example.com` / `ChangeMe123!`

Change it immediately outside local development.

### Setup
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan view:cache
php artisan serve
```

Open `/login`.

### Production notes
- Point the web server document root to `public/`.
- PHP must be able to read the whole project directory, including `vendor/` and `storage/`.
- Do not commit `.env`, `vendor/`, runtime cache, or application logs.
- `CACHE_STORE=file` is the recommended baseline for this initial deployment.
- `QUEUE_CONNECTION=database` is the production baseline for background email jobs.
- Run a persistent queue worker under Supervisor in production; see `deploy/supervisor/kpi-dashboard-worker.conf`.
- On aaPanel/Nginx, disable or adjust the site-level open_basedir restriction so the project root is allowed.
- Ensure Laravel runtime directories exist: `storage/framework/cache/data`, `storage/framework/sessions`, `storage/framework/views`, and `storage/logs`.

### New server / recovery
See **[deployment/NEW_SERVER_SETUP.md](deployment/NEW_SERVER_SETUP.md)** for the complete reproducible new-server installation, production update, queue/Supervisor setup, SMTP configuration, permissions, smoke tests and recovery runbook.

### Next KPI phase
The Admin/RBAC core is intentionally independent from KPI calculation. The next layer can add Employee Master/Mapping, Job Titles, Ticket RAW Excel import, KPI configuration, calculation and reporting. Bitrix API can later become another data provider without replacing the KPI engine.
