# Mesma base PHP do container do Apache, sem Apache: aqui roda só o cron.
FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        cron git unzip libicu-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath intl zip opcache \
    && apt-get purge -y libicu-dev libzip-dev \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/apache/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/cron/crontab /etc/cron.d/middleware
COPY docker/cron/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod 0644 /etc/cron.d/middleware && chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html
COPY . /var/www/html
RUN composer install --no-interaction --prefer-dist --no-progress

ENTRYPOINT ["entrypoint"]

# Primeiro plano: o cron é o processo principal do container, então o Docker
# reinicia o container se ele morrer e `docker compose logs` mostra a saída.
CMD ["cron", "-f"]
