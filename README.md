# KPI Dashboard

## Admin foundation

PHP 8.2+ / Laravel 11 / MySQL 8+.

Implemented Admin foundation:
- Login / logout with session authentication
- Active/inactive Admin users
- User CRUD
- User Groups CRUD
- Group-based permission RBAC
- Permission assignment UI by module
- Permission middleware for every Admin action
- Admin dashboard summary
- Database migrations and seed data
- PhpSpreadsheet dependency prepared for the KPI Excel import phase

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
php artisan serve
```

Open `/login`.

### Next KPI phase
The Admin/RBAC core is intentionally independent from KPI calculation. The next layer can add Employee Master/Mapping, Ticket RAW Excel import, KPI configuration and calculation. Bitrix API can later become another data provider without replacing the KPI engine.
