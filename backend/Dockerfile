FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git \
    curl \
    firebird-dev \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install bcmath pdo_firebird pdo_mysql pdo_pgsql zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
COPY artisan phpunit.xml.dist .env.example ./
COPY vendor ./vendor
COPY bootstrap ./bootstrap
COPY config ./config
COPY public ./public
COPY routes ./routes
COPY backend ./backend
COPY storage ./storage

RUN composer dump-autoload --no-interaction --optimize

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=80"]
