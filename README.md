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
- File cache configuration for simple deployment

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

### Next KPI phase
The Admin/RBAC core is intentionally independent from KPI calculation. The next layer can add Employee Master/Mapping, Job Titles, Ticket RAW Excel import, KPI configuration, calculation and reporting. Bitrix API can later become another data provider without replacing the KPI engine.
