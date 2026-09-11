#!/usr/bin/env bash
set -Eeuo pipefail

# KPI Dashboard one-file installer.
# Run as root: sudo bash install.sh
# Supports Debian/Ubuntu/aaPanel servers using apt.

REPO="https://github.com/daoaigiangnam/kpi_dashboard.git"
BRANCH="feature/admin-foundation"
DEFAULT_PATH="/www/wwwroot/kpi.review360.id.vn"
DEFAULT_USER="www"

info(){ echo -e "\n[INFO] $*"; }
ok(){ echo "[ OK ] $*"; }
warn(){ echo "[WARN] $*"; }
die(){ echo "[ERROR] $*" >&2; exit 1; }
trap 'die "Installer stopped at line $LINENO."' ERR

[[ $(id -u) -eq 0 ]] || die "Run as root: sudo bash install.sh"
command -v apt-get >/dev/null || die "Debian/Ubuntu/aaPanel apt-based server required."

read -r -p "Install path [$DEFAULT_PATH]: " APP_PATH
APP_PATH=${APP_PATH:-$DEFAULT_PATH}
read -r -p "PHP/Supervisor runtime user [$DEFAULT_USER]: " APP_USER
APP_USER=${APP_USER:-$DEFAULT_USER}
[[ $APP_PATH = /* ]] || die "Install path must be absolute."

info "Installing base packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y git unzip curl ca-certificates supervisor
ok "Base packages ready"

info "Checking PHP 8.2+"
command -v php >/dev/null || die "PHP is not installed. Install PHP 8.2+ first."
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
php -r 'exit((PHP_MAJOR_VERSION > 8 || (PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION >= 2)) ? 0 : 1);' || die "PHP 8.2+ required; detected $PHP_VERSION"
ok "PHP $PHP_VERSION"

info "Checking PHP extensions"
MISSING=()
for ext in bcmath ctype curl fileinfo mbstring openssl pdo tokenizer xml zip; do
  php -m | grep -Eiq "^${ext}$" || MISSING+=("$ext")
done
((${#MISSING[@]} == 0)) || die "Missing PHP extensions: ${MISSING[*]}"
ok "Required extensions present"

info "Checking Composer"
if ! command -v composer >/dev/null; then
  EXPECTED=$(curl -fsSL https://composer.github.io/installer.sig)
  php -r "copy('https://getcomposer.org/installer','/tmp/composer-setup.php');"
  ACTUAL=$(php -r "echo hash_file('sha384','/tmp/composer-setup.php');")
  [[ $EXPECTED = $ACTUAL ]] || die "Composer installer signature verification failed"
  php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
fi
composer --version >/dev/null
ok "Composer ready"

if [[ -e $APP_PATH && ! -d $APP_PATH/.git ]]; then
  die "$APP_PATH exists and is not a Git repository"
fi

if [[ -d $APP_PATH/.git ]]; then
  info "Updating existing deployment"
  git -C "$APP_PATH" fetch origin "$BRANCH"
  git -C "$APP_PATH" checkout "$BRANCH"
  git -C "$APP_PATH" pull --ff-only origin "$BRANCH"
else
  info "Cloning $BRANCH"
  mkdir -p "$(dirname "$APP_PATH")"
  git clone -b "$BRANCH" "$REPO" "$APP_PATH"
fi
cd "$APP_PATH"
ok "Source ready"

[[ -f .env ]] || cp .env.example .env

# Generate APP_KEY only when the .env does not already contain one.
if ! grep -qE '^APP_KEY=.+$' .env; then
  info "Generating APP_KEY"
  php artisan key:generate --force
else
  ok "Existing APP_KEY preserved"
fi

info "Setting production/runtime defaults"
php -r '
$p=".env"; $e=file_get_contents($p);
foreach(["APP_ENV"=>"production","APP_DEBUG"=>"false","SESSION_DRIVER"=>"database","CACHE_STORE"=>"file","QUEUE_CONNECTION"=>"database","QUEUE_RETRY_AFTER"=>"90","DB_QUEUE_TABLE"=>"jobs"] as $k=>$v){$r="/^".preg_quote($k,"/")."=.*$/m";$l="$k=$v";$e=preg_match($r,$e)?preg_replace($r,$l,$e,1):$e."\n$l";} file_put_contents($p,rtrim($e)."\n");'

read -r -p "APP_URL (Enter to keep current): " APP_URL
if [[ -n $APP_URL ]]; then sed -i -E "s|^APP_URL=.*$|APP_URL=${APP_URL%/}|" .env; fi
read -r -p "DB_DATABASE (Enter to keep current): " DB_NAME
if [[ -n $DB_NAME ]]; then sed -i -E "s|^DB_DATABASE=.*$|DB_DATABASE=$DB_NAME|" .env; fi
read -r -p "DB_USERNAME (Enter to keep current): " DB_USER
if [[ -n $DB_USER ]]; then sed -i -E "s|^DB_USERNAME=.*$|DB_USERNAME=$DB_USER|" .env; fi
read -r -s -p "DB_PASSWORD (Enter to keep current): " DB_PASS; echo
if [[ -n $DB_PASS ]]; then
  python3 - "$DB_PASS" <<'PY'
import pathlib,sys
p=pathlib.Path('.env'); s=p.read_text(); v=sys.argv[1].replace('\\','\\\\').replace('\n','\\n')
import re
s=re.sub(r'^DB_PASSWORD=.*$', 'DB_PASSWORD='+v, s, count=1, flags=re.M)
p.write_text(s)
PY
fi

info "Installing Composer dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

info "Preparing Laravel runtime directories"
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
if id "$APP_USER" >/dev/null 2>&1; then
  chown -R "$APP_USER:$APP_USER" storage bootstrap/cache
  chmod -R ug+rwX storage bootstrap/cache
else
  warn "Runtime user '$APP_USER' does not exist; create it or adjust permissions before production use."
fi

php artisan optimize:clear
info "Running migrations"
php artisan migrate --force
ok "Database migrated"
php artisan storage:link 2>/dev/null || true

info "Configuring Supervisor database queue worker"
SRC=deploy/supervisor/kpi-dashboard-worker.conf
DST=/etc/supervisor/conf.d/kpi-dashboard-worker.conf
[[ -f $SRC ]] || die "Missing $SRC"
sed -e "s|directory=/www/wwwroot/kpi.review360.id.vn|directory=$APP_PATH|g" \
    -e "s|user=www|user=$APP_USER|g" \
    -e "s|/www/wwwroot/kpi.review360.id.vn/storage/logs/queue-worker.log|$APP_PATH/storage/logs/queue-worker.log|g" \
    -e "s|/usr/bin/php artisan|$(command -v php) artisan|g" "$SRC" > "$DST"
supervisorctl reread
supervisorctl update
supervisorctl restart kpi-dashboard-worker:* 2>/dev/null || supervisorctl start kpi-dashboard-worker:*
ok "Supervisor configured"

info "Building production caches"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart >/dev/null 2>&1 || true
if id "$APP_USER" >/dev/null 2>&1; then
  chown -R "$APP_USER:$APP_USER" storage bootstrap/cache
  chmod -R ug+rwX storage bootstrap/cache
fi

STATUS=$(supervisorctl status 'kpi-dashboard-worker:*' 2>/dev/null || true)
printf '\n==============================================\n'
printf ' KPI DASHBOARD INSTALLATION COMPLETE\n'
printf '==============================================\n'
printf 'Project : %s\nPHP     : %s\nQueue   : database\n' "$APP_PATH" "$PHP_VERSION"
printf 'Worker:\n%s\n' "$STATUS"
printf '\nWeb root must be: %s/public\n' "$APP_PATH"
printf 'Configure HTTPS, production .env and SMTP/System Settings before go-live.\n'
printf 'Useful: supervisorctl status\n'
printf '        tail -n 100 %s/storage/logs/queue-worker.log\n' "$APP_PATH"
printf '==============================================\n'
if ! grep -q RUNNING <<<"$STATUS"; then warn "Queue worker is not RUNNING; check Supervisor status/logs."; fi
