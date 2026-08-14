#!/usr/bin/env bash
# Preparação da aplicação na subida do container:
#   .env -> dependências -> diretórios graváveis -> banco -> migrations.
# Tudo idempotente: subir de novo não quebra nem duplica dado.
set -euo pipefail

# Arquivos que a aplicação gerar (cache, trava, log) nascem graváveis por
# qualquer usuário — ver a nota sobre bind mount no Dockerfile.
umask 0000

cd /var/www/html

log() { echo "[entrypoint] $*"; }

if [ ! -f .env ]; then
    log ".env não existe — copiando de .env.example"
    cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
    log "instalando dependências (composer install)"
    composer install --no-interaction --prefer-dist --no-progress
fi

mkdir -p storage/cache storage/locks storage/logs
chmod -R ug+rwX storage || true

# Aguarda o banco responder. O banco é externo (fora do compose), então não há
# healthcheck para depender: o teste é abrir uma conexão PDO de verdade.
#
# Note que o teste NÃO é "a tabela migrations existe" — ela justamente não
# existe no primeiro boot, que é quando mais precisamos migrar.
wait_for_db() {
    for _ in $(seq 1 30); do
        if php -r '
            require "app/bootstrap.php";
            try { App\Core\Database::pdo(); exit(0); } catch (Throwable $e) { exit(1); }
        ' >/dev/null 2>&1; then
            return 0
        fi
        sleep 2
    done
    return 1
}

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    log "aguardando o banco responder"
    if wait_for_db; then
        log "aplicando migrations e seeds"
        php database/migrate.php --seed
    else
        log "AVISO: banco não respondeu — subindo sem migrar (rode 'php database/migrate.php --seed' depois)"
    fi
fi

log "pronto: $*"
exec "$@"
