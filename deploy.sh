#!/usr/bin/env bash
# Bringt den aktuellen Stand von origin/main auf den Server bei cyon.
#
#   ./deploy.sh
#
# Die Assets werden lokal gebaut und hochgeladen, damit auf dem Server kein
# Node nötig ist. Auf dem Server liegt die App in ~/projektor; der
# Document Root der Subdomain zeigt per Symlink auf ~/projektor/public.
set -euo pipefail

HOST="${DEPLOY_HOST:-jifetaje@s120.cyon.net}"
APP_DIR="${DEPLOY_DIR:-projektor}"
PHP="/usr/local/bin/php84"

cd "$(dirname "$0")"

if [[ -n "$(git status --porcelain)" ]]; then
    echo "Es gibt nicht committete Änderungen. Bitte zuerst committen." >&2
    exit 1
fi
if [[ "$(git rev-parse HEAD)" != "$(git rev-parse origin/main)" ]]; then
    echo "Der lokale Stand entspricht nicht origin/main. Bitte zuerst pushen." >&2
    exit 1
fi

echo "→ Assets bauen"
npm run build

echo "→ Code auf dem Server aktualisieren"
ssh "$HOST" "cd $APP_DIR && git fetch --quiet origin && git reset --hard --quiet origin/main"

echo "→ Assets hochladen"
rsync -az --delete public/build/ "$HOST:$APP_DIR/public/build/"

echo "→ Abhängigkeiten, Migrations und Caches"
ssh "$HOST" "cd $APP_DIR \
    && $PHP ~/bin/composer.phar install --no-dev --optimize-autoloader --no-interaction --quiet \
    && $PHP artisan migrate --force \
    && $PHP artisan optimize"

echo "✓ Fertig: $(git log -1 --format='%h %s')"
