# KPI Dashboard — New Server Deployment Guide

This document is the reproducible installation/runbook for deploying the KPI Dashboard to a new Linux server. Keep this file with the repository; do not store secrets here.

## 1. Application baseline

Current application baseline:
- PHP 8.2+
- Laravel 12
- MySQL 8+
- Composer
- Web server serving Laravel `public/`
- Database-backed sessions
- Database-backed queue
- Supervisor for production queue workers
- PhpSpreadsheet for Excel import

The current `.env.example` already defines the database queue baseline (`QUEUE_CONNECTION=database`) and queue settings. The application uses encrypted system settings for production SMTP configuration, so SMTP credentials must not be committed to Git.

## 2. New server checklist

Install:

```bash
apt update
apt install -y git unzip curl supervisor
```

Install/configure:
- PHP 8.2+ with the extensions required by Laravel and PhpSpreadsheet (including `zip`, `mbstring`, `xml`, `curl`, `fileinfo`, `pdo_mysql`, `openssl`, `tokenizer`, `ctype`, `bcmath` as applicable to the server build).
- Composer 2.x.
- MySQL 8+ (or a compatible managed MySQL service).
- Nginx/Apache/Caddy or the hosting panel's web server.

Verify:

```bash
php -v
php -m
composer --version
mysql --version
supervisorctl version
```

## 3. Clone application

Example production path used by the current server:

```bash
cd /www/wwwroot

git clone -b feature/admin-foundation https://github.com/daoaigiangnam/kpi_dashboard.git kpi.review360.id.vn
cd /www/wwwroot/kpi.review360.id.vn
```

For an existing deployment, update instead:

```bash
cd /www/wwwroot/kpi.review360.id.vn
git checkout feature/admin-foundation
git pull origin feature/admin-foundation
```

## 4. PHP dependencies

```bash
composer install --no-dev --optimize-autoloader
```

For a development server, `composer install` is acceptable.

## 5. Environment file

Create `.env` from the repository template:

```bash
cp .env.example .env
php artisan key:generate
```

Set production values in `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

SESSION_DRIVER=database
SESSION_LIFETIME=120

CACHE_STORE=file

QUEUE_CONNECTION=database
QUEUE_RETRY_AFTER=90
DB_QUEUE_TABLE=jobs
```

Do not commit `.env` or passwords/API keys. `.env` is intentionally ignored by Git.

## 6. Database

Create an empty MySQL database and user with appropriate privileges, then run:

```bash
php artisan migrate --force
```

For a brand-new development database where seed data is intentionally wanted:

```bash
php artisan migrate --seed
```

**Production warning:** do not run `migrate:fresh --seed` on a live database. It destroys existing data.

The repository's migrations contain the application schema, including users/RBAC, organization catalogs, authentication/OTP data, system settings, ticket/KPI data and queue-related schema as applicable to the current branch.

## 7. Laravel runtime directories and permissions

Ensure runtime directories exist:

```bash
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache
```

For a server where PHP-FPM and Supervisor run as `www`:

```bash
chown -R www:www storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
```

If the hosting environment uses another PHP/web user, replace `www` with that user consistently.

Create the public storage link if the application needs public storage files:

```bash
php artisan storage:link
```

## 8. Web server

The web server document root **must be the Laravel `public/` directory**, not the project root.

Example:

```text
/www/wwwroot/kpi.review360.id.vn/public
```

Ensure the server can execute PHP through the configured PHP-FPM version and that Laravel rewrite/front-controller rules are enabled.

On aaPanel/Nginx, review `open_basedir` if Laravel cannot read files under the project root. The project must be able to access `vendor/`, `storage/`, `bootstrap/cache/`, and the application source tree.

## 9. Queue system — mandatory for production email

This project uses the database queue for background email jobs. The `.env` baseline contains:

```dotenv
QUEUE_CONNECTION=database
QUEUE_RETRY_AFTER=90
DB_QUEUE_TABLE=jobs
```

After migrations, verify the queue tables exist.

Test manually before enabling Supervisor:

```bash
php artisan queue:work database --sleep=3 --tries=3 --backoff=60 --timeout=120 --max-time=3600
```

This command is for testing. Press `Ctrl+C` after confirming the worker starts.

## 10. Supervisor — production queue worker

The repository contains the production worker configuration:

```text
deploy/supervisor/kpi-dashboard-worker.conf
```

The supplied configuration currently uses:

```text
[program:kpi-dashboard-worker]
process_name=%(program_name)s_%(process_num)02d
directory=/www/wwwroot/kpi.review360.id.vn
command=/usr/bin/php artisan queue:work database --sleep=3 --tries=3 --backoff=60 --timeout=120 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www
numprocs=1
redirect_stderr=true
stdout_logfile=/www/wwwroot/kpi.review360.id.vn/storage/logs/queue-worker.log
stopwaitsecs=3600
```

Install it:

```bash
cp deploy/supervisor/kpi-dashboard-worker.conf /etc/supervisor/conf.d/kpi-dashboard-worker.conf
supervisorctl reread
supervisorctl update
supervisorctl start kpi-dashboard-worker:*
supervisorctl status
```

Expected status:

```text
kpi-dashboard-worker:kpi-dashboard-worker_00   RUNNING
```

Check worker logs:

```bash
tail -f storage/logs/queue-worker.log
```

### Why this is required

Create User, Import Users, first-time password setup and password reset use queued email notifications. The web request should create the account and enqueue the mail job, then return without waiting for SMTP delivery. Supervisor keeps the queue worker alive after SSH sessions close and restarts it if it exits.

## 11. SMTP / System Settings

Do not put production SMTP credentials into Git.

After login, configure SMTP through the application's **System Settings** when the authorized account has the required permission. SMTP credentials are stored through the application's encrypted settings mechanism.

Then verify:
- SMTP host
- SMTP port
- encryption/security mode as supported by the configured mail provider
- SMTP username
- SMTP password
- From address/name
- System notification email

Run an actual test email after the queue worker is running.

## 12. Optimize application after deployment

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If configuration changes later:

```bash
php artisan optimize:clear
php artisan config:cache
```

After deploying code changes that affect queued jobs, restart workers gracefully:

```bash
php artisan queue:restart
supervisorctl status
```

Supervisor should start the worker again automatically.

## 13. Deployment update runbook

For normal code updates on an existing production server:

```bash
cd /www/wwwroot/kpi.review360.id.vn

git status
git checkout feature/admin-foundation
git pull origin feature/admin-foundation

composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
supervisorctl status
```

Only run `composer install` when dependencies may have changed; it is safe to run as part of a standard deployment if maintenance time is acceptable.

## 14. First production smoke test

After deployment verify in this order:

1. `/login` loads.
2. Admin can sign in.
3. A user without `admin.view` lands on **Account Information** rather than Dashboard.
4. Account Information is available to every authenticated user.
5. RBAC menu visibility follows permissions.
6. Direct access to an unauthorized feature returns a clear 403.
7. Create one User and verify the setup email is queued and delivered.
8. Import several new Users and verify emails are processed in the background without keeping the browser request open.
9. Reset one User password and verify the queued email arrives.
10. `supervisorctl status` shows the worker as `RUNNING`.
11. `storage/logs/queue-worker.log` has no unexpected queue failures.
12. Check failed jobs if an email/provider failure occurs.

Useful commands:

```bash
php artisan queue:failed
php artisan queue:retry all
supervisorctl status
tail -n 100 storage/logs/queue-worker.log
tail -n 100 storage/logs/laravel.log
```

Only retry failed jobs after confirming the underlying SMTP/application problem is fixed.

## 15. Security checklist

- `APP_ENV=production`
- `APP_DEBUG=false`
- HTTPS enabled
- Strong database password
- `.env` is not committed
- `APP_KEY` is generated and preserved for the lifetime of the installation
- Production SMTP credentials are not stored in Git
- Web root points to `public/`
- `storage/` and `bootstrap/cache/` are writable by the runtime user only as required
- Database user has only the required database privileges
- Default development credentials are changed/disabled before production use
- Supervisor worker runs under a non-root application user such as `www`
- Never run `php artisan migrate:fresh` on production

## 16. Important current-server notes

The current deployment path is:

```text
/www/wwwroot/kpi.review360.id.vn
```

The current environment showed this PHP warning:

```text
PHP Warning: Module "zip" is already loaded in Unknown on line 0
```

This is a PHP configuration duplication warning, not a Laravel queue failure. It should be cleaned up separately by finding the duplicate `extension=zip` configuration entry.

If the server is rebuilt, do not copy the warning itself as configuration. Install `zip` once and verify:

```bash
php --ini
php -m | grep -i '^zip$'
```

## 17. What must NOT be copied to a new server from Git

Do not expect Git to contain:
- `.env`
- database contents
- SMTP passwords
- application encryption key unless deliberately backed up through a secure secret-management process
- runtime logs
- queued jobs currently waiting in the production database
- `vendor/`
- `node_modules/`

A new server gets application code from Git and gets environment/database/secrets from the deployment process or secure backup.

## 18. Minimum new-server command sequence

For a prepared Linux server, the shortest reproducible sequence is:

```bash
cd /www/wwwroot
git clone -b feature/admin-foundation https://github.com/daoaigiangnam/kpi_dashboard.git kpi.review360.id.vn
cd kpi.review360.id.vn
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
# Edit .env with production DB/APP values
php artisan migrate --force
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www:www storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
php artisan storage:link
cp deploy/supervisor/kpi-dashboard-worker.conf /etc/supervisor/conf.d/kpi-dashboard-worker.conf
supervisorctl reread
supervisorctl update
supervisorctl start kpi-dashboard-worker:*
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
supervisorctl status
```

Before the application is opened to users, configure the web server, HTTPS, production `.env`, SMTP/System Settings and MySQL backup policy.

## 19. Recovery principle

If the server is lost, rebuild in this order:

```text
New Linux server
  -> PHP / extensions / Composer / MySQL / Supervisor
  -> Git clone branch
  -> .env + APP_KEY + database credentials
  -> composer install
  -> migrate
  -> storage/bootstrap permissions
  -> web server + HTTPS
  -> Supervisor queue worker
  -> SMTP/System Settings
  -> smoke tests
```

The repository is the source of truth for application code and deployment configuration. Production data and secrets must be backed up separately and securely.
