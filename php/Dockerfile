# Site da Analise de Inventario: PHP 8.3 + Apache servindo php/public
FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libzip-dev libpng-dev libfreetype6-dev libjpeg62-turbo-dev unzip \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install zip gd \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/app/public \
    INVENTARIO_DADOS=/var/www/app/storage
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
 && printf 'upload_max_filesize=50M\npost_max_size=60M\nmemory_limit=448M\nmax_execution_time=300\n' > /usr/local/etc/php/conf.d/inventario.ini

WORKDIR /var/www/app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize \
 && mkdir -p storage/saidas storage/uploads \
 && chown -R www-data:www-data storage

# o Render informa a porta em $PORT
CMD sed -i "s/Listen 80/Listen ${PORT:-80}/" /etc/apache2/ports.conf \
 && sed -i "s/:80>/:${PORT:-80}>/" /etc/apache2/sites-available/000-default.conf \
 && apache2-foreground
