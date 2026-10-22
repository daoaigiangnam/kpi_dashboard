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

## Customer Asset Portal and QR lookup

Customer portal accounts are distinct from internal staff accounts. A customer account is bound to one existing IT Service Customer and receives only the explicitly selected permissions:
- `customer.assets.view`: view that customer's PC Audit asset list.
- `customer.audit.view`: view detailed audit information on accessible asset records.
- `customer.assets.qr`: generate/print asset QR labels (also requires asset-list access).
- `customer_accounts.manage`: internal permission for managing customer portal accounts; assigned to Super Admin by the migration.

Asset queries in the customer portal are scoped by `users.service_customer_id` against `customer_branches.customer_id`, and additionally by branch permissions: a customer user can either access all branches of that customer or only the explicitly selected branches stored in `customer_user_branches`. The branch scope is enforced for list, detail and QR lookup queries. The QR label contains an opaque random lookup token, not the sequential database ID or asset details. The destination requires authentication and re-checks the logged-in user's customer scope, so scanning a QR does not grant public access.

### Deploy the customer asset portal
1. Review the branch `feature/customer-asset-portal-qrcode` and test it in staging.
2. Back up the production database and deploy code.
3. Run `php artisan migrate --force`.
4. Confirm SMTP/queue workers are operational; customer account creation sends the password setup email using the existing OTP and secure-link mechanism.
5. Sign in as Super Admin, open **Tài khoản khách hàng**, create a customer account, choose the exact customer, choose either all branches or specific allowed branches, select feature permissions, then confirm the setup email is delivered.
6. Test with two different customer accounts and multiple branches: each account must see only assets associated with its customer and allowed branches. Test that denied permissions return HTTP 403/404.
7. Print a QR label and scan it on a phone. The user should be asked to sign in if unauthenticated; after authentication they must only see asset information within their assigned customer scope.

Do not make QR destinations publicly accessible unless a separate public, minimal asset-information policy is intentionally designed and security-reviewed.
