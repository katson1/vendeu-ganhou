FROM composer:2.8 AS dependencies

WORKDIR /app

COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts

FROM php:8.3-cli-alpine

RUN docker-php-ext-install pdo_mysql

WORKDIR /app

COPY --from=dependencies /app/vendor /app/vendor
COPY backend/ /app/

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
