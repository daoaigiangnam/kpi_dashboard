#!/usr/bin/env bash
set -Eeuo pipefail

# KPI Dashboard - one-file installer
# Usage: sudo bash install.sh
# Installs/deploys the application from feature/admin-foundation and configures
# Laravel, database queue, storage permissions and Supervisor.

REPO_URL="https://github.com/daoaigiangnam/kpi_dashboard.git"
BRANCH="feature/admin-foundation"
DEFAULT_PATH="/www/wwwroot/kpi.review360.id.vn"
DEFAULT_RUNTIME_USER="www"

log()  { printf '\n\033[1;34m[INFO]\033[0m %s\n' "$*"; }
ok()   { printf '\033[1;32m[ OK ]\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m[WARN]\033[0m %s\n' "$*"; }
die()  { printf '\n\033[1;31m[ERROR]\033[0m %s\n' "$*" >&2; exit 1; }

trap 'die "Installation stopped at line $LINENO. Check the message above."' ERR

[[ "$(id -u)" -eq 0 ]] || die "Run as root: sudo bash install.sh"

printf '\n==============================================\n'
printf '   KPI DASHBOARD - ONE FILE INSTALLER\n'
printf '==============================================\n'
printf 'Branch: %s\n' "$BRANCH"
printf 'Repo  : %s\n\n' "$REPO_URL"

command -v apt-get >/dev/null 2>&1 || die "This installer currently supports Debian/Ubuntu/aaPanel servers using apt."

read -r -p "Install path [$DEFAULT_PATH]: " APP_PATH
APP_PATH="${APP_PATH:-$DEFAULT_PATH}"
read -r -p "Runtime user [$DEFAULT_RUNTIME_USER]: " RUNTIME_USER
RUNTIME_USER="${RUNTIME_USER:-$DEFAULT_RUNTIME_USER}"

[[ "$APP_PATH" = /* ]] || die "Install path must be absolute."

log "Installing basic OS packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y git unzip curl ca-certificates supervisor
ok "Git, unzip, curl and Supervisor are ready"

log "Checking PHP"
command -v php >/dev/null 2>&1 || die "PHP is not installed. Install PHP 8.2+ and required extensions first."
PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
PHP_MAJOR="$(php -r 'echo PHP_MAJOR_VERSION;')"
PHP_MINOR="$(php -r 'echo PHP_MINOR_VERSION;')"
if (( PHP_MAJOR < 8 || (PHP_MAJOR == 8 && PHP_MINOR < 2) )); then
    die "PHP 8.2+ is required; detected PHP $PHP_VERSION."
fi
ok "PHP $PHP_VERSION detected"

log "Checking required PHP extensions"
REQUIRED_EXTS=(bcmath ctype curl fileinfo mbstring openssl pdo tokenizer xml zip)
MISSING_EXTS=()
for ext in "${REQUIRED_EXTS[@]}"; do
    php -m | grep -Eiq "^${ext}$" || MISSING_EXTS+=("$ext")
done
if ((${#MISSING_EXTS[@]})); then
    die "Missing PHP extensions: ${MISSING_EXTS[*]}. Install them for the active PHP-FPM/CLI version, then rerun this installer."
fi
ok "Required PHP extensions are present"

log "Checking Composer"
if ! command -v composer >/dev/null 2>&1; then
    log "Composer not found; installing Composer 2.x"
    EXPECTED_SIGNATURE="$(curl -fsSL https://composer.github.io/installer.sig)"
    php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
    ACTUAL_SIGNATURE="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
    [[ "$EXPECTED_SIGNATURE" = "$ACTUAL_SIGNATURE" ]] || die "Composer installer signature verification failed."
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi
composer --version >/dev/null
git --version >/dev/null
ok "Composer is ready"

log "Checking MySQL client/server access"
command -v mysql >/dev/null 2>&1 || warn "mysql client is not installed. If the database is remote/managed, this may be intentional."

if [[ -e "$APP_PATH" && ! -d "$APP_PATH/.git" ]]; then
    die "$APP_PATH exists but is not a Git working tree. Choose another path or move the existing directory first."
fi

if [[ -d "$APP_PATH/.git" ]]; then
    log "Updating existing Git deployment"
    git -C "$APP_PATH" fetch origin "$BRANCH"
    git -C "$APP_PATH" checkout "$BRANCH"
    git -C "$APP_PATH" pull --ff-only origin "$BRANCH"
else
    log "Cloning application"
    mkdir -p "$(dirname "$APP_PATH")"
    git clone -b "$BRANCH" "$REPO_URL" "$APP_PATH"
fi

cd "$APP_PATH"
ok "Application source is ready"

if [[ ! -f .env ]]; then
    log "Creating .env from .env.example"
    cp .env.example .env
fi

log "Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction
ok "Composer dependencies installed"

log "Configuring production environment"
php artisan key:generate --force

# Set safe production/runtime defaults without overwriting database credentials,
# APP_URL, SMTP values or an existing APP_KEY on repeat installations.
php -r '
$path = ".env";
$env = file_exists($path) ? file_get_contents($path) : "";
$set = function($key, $value) use (&$env) {
    $line = $key . "=" . $value;
    $pattern = "/^" . preg_quote($key, "/") . "=.*$/m";
    if (preg_match($pattern, $env)) $env = preg_replace($pattern, $line, $env, 1);
    else $env .= "\n" . $line;
};
$set("APP_ENV", "production");
$set("APP_DEBUG", "false");
$set("SESSION_DRIVER", "database");
$set("CACHE_STORE", "file");
$set("QUEUE_CONNECTION", "database");
$set("QUEUE_RETRY_AFTER", "90");
$set("DB_QUEUE_TABLE", "jobs");
file_put_contents($path, rtrim($env) . "\n");
'

read -r -p "APP_URL [keep current value]: " APP_URL_INPUT
if [[ -n "$APP_URL_INPUT" ]]; then
    APP_URL_INPUT="${APP_URL_INPUT%/}"
    APP_URL_INPUT="$(printf '%s' "$APP_URL_INPUT" | sed 's/[&|]/\\&/g')"
    sed -i -E "s|^APP_URL=.*$|APP_URL=$APP_URL_INPUT|" .env
fi

read -r -p "DB_DATABASE [keep current value]: " DB_NAME_INPUT
if [[ -n "$DB_NAME_INPUT" ]]; then
    sed -i -E "s|^DB_DATABASE=.*$|DB_DATABASE=$DB_NAME_INPUT|" .env
fi
read -r -p "DB_USERNAME [keep current value]: " DB_USER_INPUT
if [[ -n "$DB_USER_INPUT" ]]; then
    sed -i -E "s|^DB_USERNAME=.*$|DB_USERNAME=$DB_USER_INPUT|" .env
fi
read -r -s -p "DB_PASSWORD [leave blank to keep current]: " DB_PASS_INPUT
printf '\n'
if [[ -n "$DB_PASS_INPUT" ]]; then
    DB_PASS_ESCAPED="$(printf '%s' "$DB_PASS_INPUT" | sed 's/[&|]/\\&/g')"
    sed -i -E "s|^DB_PASSWORD=.*$|DB_PASSWORD=$DB_PASS_ESCAPED|" .env
fi

# Avoid stale cached configuration before any database operation.
php artisan optimize:clear

log "Preparing Laravel runtime directories"
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
if id "$RUNTIME_USER" >/dev/null 2>&1; then
    chown -R "$RUNTIME_USER:$RUNTIME_USER" storage bootstrap/cache
    chmod -R ug+rwX storage bootstrap/cache
    ok "Runtime permissions set for $RUNTIME_USER"
else
    warn "Runtime user '$RUNTIME_USER' does not exist; permissions were not changed."
fi

log "Running database migrations"
php artisan migrate --force
ok "Database migrations completed"

log "Creating public storage link"
php artisan storage:link 2>/dev/null || warn "storage:link could not be created; this may be harmless if the link already exists or is not required."

log "Installing Supervisor worker configuration"
SUPERVISOR_SOURCE="deploy/supervisor/kpi-dashboard-worker.conf"
SUPERVISOR_TARGET="/etc/supervisor/conf.d/kpi-dashboard-worker.conf"
[[ -f "$SUPERVISOR_SOURCE" ]] || die "Missing $SUPERVISOR_SOURCE in repository."

# Adapt the repository's documented production path/runtime user to the chosen installer path/user.
sed -e "s|directory=/www/wwwroot/kpi.review360.id.vn|directory=$APP_PATH|g" \
    -e "s|user=www|user=$RUNTIME_USER|g" \
    -e "s|/www/wwwroot/kpi.review360.id.vn/storage/logs/queue-worker.log|$APP_PATH/storage/logs/queue-worker.log|g" \
    -e "s|/usr/bin/php artisan|$(command -v php) artisan|g" \
    "$SUPERVISOR_SOURCE" > "$SUPERVISOR_TARGET"

supervisorctl reread
supervisorctl update
supervisorctl restart kpi-dashboard-worker:* 2>/dev/null || supervisorctl start kpi-dashboard-worker:*
ok "Supervisor worker configured"

log "Building Laravel production caches"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart >/dev/null 2>&1 || true

# Re-assert runtime permissions after cache/log generation.
if id "$RUNTIME_USER" >/dev/null 2>&1; then
    chown -R "$RUNTIME_USER:$RUNTIME_USER" storage bootstrap/cache
    chmod -R ug+rwX storage bootstrap/cache
fi

WORKER_STATUS="$(supervisorctl status 'kpi-dashboard-worker:*' 2>/dev/null || true)"

printf '\n==============================================\n'
printf '   KPI DASHBOARD INSTALLATION COMPLETE\n'
printf '==============================================\n'
printf 'Project : %s\n' "$APP_PATH"
printf 'Branch  : %s\n' "$BRANCH"
printf 'PHP     : %s\n' "$PHP_VERSION"
printf 'Queue   : database\n'
printf 'Worker  :\n%s\n' "${WORKER_STATUS:-Unable to read Supervisor status}"
printf '\nNext steps:\n'
printf '1. Set the web document root to: %s/public\n' "$APP_PATH"
printf '2. Confirm HTTPS and APP_URL in .env\n'
printf '3. Confirm MySQL credentials/database in .env\n'
printf '4. Configure SMTP/System Settings after login\n'
printf '5. Test Create User / Import Users email delivery\n'
printf '\nUseful checks:\n'
printf '  cd %s\n' "$APP_PATH"
printf '  supervisorctl status\n'
printf '  php artisan queue:failed\n'
printf '  tail -n 100 storage/logs/queue-worker.log\n'
printf '==============================================\n'

if ! printf '%s\n' "$WORKER_STATUS" | grep -q 'RUNNING'; then
    warn "Queue worker is not RUNNING. Check: supervisorctl status && tail -n 100 storage/logs/queue-worker.log"
fi

if php -r 'exit(extension_loaded("zip") ? 0 : 1);' >/dev/null 2>&1; then
    ZIP_COUNT="$(php -m 2>/dev/null | grep -ic '^zip$' || true)"
    [[ "$ZIP_COUNT" -eq 1 ]] || warn "Check PHP zip configuration for duplicate extension=zip entries."
fi
