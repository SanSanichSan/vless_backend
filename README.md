# Global Shield - Backend

Backend репозиторий для API api.globalshield.ru.

## Архитектура баз данных

- **Remnawave DB** (`remnawave-db:5432`) - База для VPN сервиса Remnawave
- **Payment DB** (`payment-db:5432`) - База для платежного API и пользователей

## Структура проекта

- `api/` - PHP API endpoints
  - `config.php` - Основная конфигурация
  - `create-invoice.php` - Создание счетов
  - `health-check.php` - Health check endpoint
  - `Dockerfile` - Конфигурация PHP контейнера
- `docker-compose.yml` - Конфигурация Docker
- `Caddyfile` - Конфигурация веб-сервера
- `php.ini` - Настройки PHP
- `init-remnawave.sql` - Инициализация базы Remnawave
- `init-payment.sql` - Инициализация базы Payment API
- `.env` - Переменные окружения

## Быстрый старт

1. Клонируйте репозиторий
2. Настройте переменные в `.env` (особенно пароли БД)
3. Запустите все сервисы: `docker-compose up -d`

## API Endpoints

- `POST /create-invoice.php` - Создание платежного счета
- `GET /health-check.php` - Проверка здоровья сервиса

## Базы данных

### Remnawave Database
- Назначение: Хранение данных VPN сервиса
- Порт: 6767
- Автоматическое создание таблиц Remnawave

### Payment Database  
- Назначение: Платежи, пользователи, аудит-логи
- Порт: 6768
- Таблицы: payments, users, audit_log

## Сервисы

- `remnawave-db` - PostgreSQL для Remnawave
- `payment-db` - PostgreSQL для Payment API
- `remnawave` - Основной VPN сервис
- `remnawave-redis` - Redis кэш
- `remnawave-subscription-page` - Страница подписок
- `payment-api` - PHP API для платежей
- `caddy` - Веб-сервер и reverse proxy

## Безопасность

- Разделенные базы данных для изоляции
- Все API endpoints защищены CORS
- Аудит-логирование всех действий
- Индивидуальные учетные данные для каждой БД
