#!/usr/bin/env bash
# Prepara o container do cron e entrega o controle ao crond.
set -euo pipefail

umask 0000

cd /var/www/html

log() { echo "[cron-entrypoint] $*"; }

if [ ! -f .env ]; then
    log ".env não existe — copiando de .env.example"
    cp .env.example .env
fi

# O container do Apache também roda `composer install`; quem chegar primeiro
# instala, o outro encontra vendor/ pronto (os dois montam o mesmo código).
if [ ! -f vendor/autoload.php ]; then
    log "instalando dependências (composer install)"
    composer install --no-interaction --prefer-dist --no-progress
fi

mkdir -p storage/cache storage/locks storage/logs
touch storage/logs/cron.log
chmod -R ug+rwX storage || true

# O cron exige dono root e 0644 no arquivo de agendamento — o COPY do
# Dockerfile já deixa assim, isto só reafirma caso alguém monte outro.
chmod 0644 /etc/cron.d/middleware

log "agenda ativa:"
grep -E '^[^#]' /etc/cron.d/middleware | sed 's/^/  /' || true

# A saída de cada execução vai para storage/logs/cron.log (ver crontab).
# Acompanhar em tempo real:
#     docker compose exec cron tail -f storage/logs/cron.log
log "iniciando: $*"
exec "$@"
