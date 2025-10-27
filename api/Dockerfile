FROM php:8.2-apache

# Устанавливаем системные пакеты
RUN apt-get update && apt-get upgrade -y

# Устанавливаем PostgreSQL клиент и зависимости
RUN apt-get install -y \
    libpq-dev \
    curl \
    wget \
    netcat-openbsd \
    && rm -rf /var/lib/apt/lists/*

# Устанавливаем PHP расширения для PostgreSQL
RUN docker-php-ext-install pdo pdo_pgsql

# Включение mod_rewrite для Apache
RUN a2enmod rewrite

# Настраиваем Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

WORKDIR /var/www/html

# Копируем файлы приложения
COPY . .

# Устанавливаем правильные права
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html
