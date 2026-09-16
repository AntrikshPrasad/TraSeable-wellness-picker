#!/usr/bin/env bash
#
# Deploys the current branch on the server. Run it from the application
# directory as the user that owns the files:
#
#     cd /var/www/wellness && ./deploy.sh
#
# The first deploy is done by hand; this is for every one after it.

set -euo pipefail

cd "$(dirname "$0")"

# Derive the service name from the PHP actually in use rather than hardcoding a
# version that will be wrong after the next upgrade.
PHP_FPM="php$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')-fpm"

echo "==> Maintenance mode on"
php artisan down --retry=15 || true
# Bring the site back even if something below fails, so a broken deploy does
# not also leave the app dark.
trap 'php artisan up || true' EXIT

echo "==> Fetching code"
git pull --ff-only

echo "==> PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Front-end assets"
npm ci
npm run build

echo "==> Database"
# --force because there is no terminal to confirm at on a server.
php artisan migrate --force

echo "==> Caching config, routes and views"
php artisan optimize

echo "==> Reloading ${PHP_FPM}"
# Reload, not restart: it finishes in-flight requests first. Needs the sudoers
# entry from the setup notes, or run this script with sudo -E.
sudo systemctl reload "${PHP_FPM}"

echo "==> Done"
