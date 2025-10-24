FROM php:8.2-apache

# Установка PostgreSQL расширений
RUN apt-get update && apt-get install -y \
    libpq-dev \
    curl \
    && docker-php-ext-install pdo pdo_pgsql

# Включение mod_rewrite для Apache
RUN a2enmod rewrite

WORKDIR /var/www/html
