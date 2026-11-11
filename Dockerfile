FROM php:8.2-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/web

RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev libzip-dev unzip zip \
    && docker-php-ext-install pdo_sqlite zip \
    && a2enmod rewrite \
    && sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
    && printf '<Directory /var/www/html/web>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/sitepro.conf \
    && a2enconf sitepro \
    && sed -ri 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf \
    && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:10000>/' /etc/apache2/sites-available/000-default.conf \
    && printf 'upload_max_filesize=5M\npost_max_size=6M\n' > /usr/local/etc/php/conf.d/uploads.ini \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress --no-scripts \
    && mkdir -p runtime web/assets \
    && chown -R www-data:www-data runtime web/assets

EXPOSE 10000
