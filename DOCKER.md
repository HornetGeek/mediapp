# Docker Run Guide

## 1) Start containers

```bash
docker compose up -d --build
```

This starts:
- `app` (PHP-FPM 8.3 + Composer + required extensions)
- `web` (Nginx on `http://localhost:8080`)
- `db` (MySQL 8 on host port `3307`)
- `node` (Vite dev server on `http://localhost:5173`)

## 2) Prepare Laravel inside containers

```bash
docker compose exec app composer install
docker compose exec app cp .env.example .env
```

Update your `.env` database section to:

```env
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=laravel
```

Then run:

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

## 3) Access app

- App: `http://localhost:8080`
- Vite: `http://localhost:5173`
- Seeded login: `superadmin@superadmin.com` / `123456`

## Useful commands

```bash
docker compose logs -f app
docker compose logs -f web
docker compose logs -f node
docker compose down
docker compose down -v
```
