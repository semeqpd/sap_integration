FROM php:8.4-apache
# mod_rewrite: o front controller depende dele (public/.htaccess e vhost.conf).
RUN a2enmod rewrite
# Extensões: pdo_mysql (banco), bcmath (dinheiro), intl (formatação), zip
# (composer), opcache (performance). As libs -dev saem depois do build.
# pdo_sqlite já vem na imagem oficial — é o banco da suíte de testes.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libzip-dev default-mysql-client \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath intl zip opcache \
    && apt-get purge -y libicu-dev libzip-dev \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/apache/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/apache/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint
WORKDIR /var/www/html
EXPOSE 80
ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
