# Deploy Runbook (Production - Bare Metal)

Server assumptions:
- Host: `srv964496`
- App path: `/home/mediapps/htdocs/mediapps.online`
- PHP CLI: `/usr/bin/php8.4`
- Stack: Nginx + PHP-FPM + MySQL + Redis (non-Docker)

## Standard Redeploy Steps

```bash
ssh root@srv964496
cd /home/mediapps/htdocs/mediapps.online

# 1) Pull latest code
git fetch --all --prune
git checkout main
git pull origin main

# 2) Enter maintenance mode
/usr/bin/php8.4 artisan down

# 3) Install/update dependencies
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

# 4) Run migrations
/usr/bin/php8.4 artisan migrate --force

# 5) Ensure public uploads are web-accessible
/usr/bin/php8.4 artisan storage:link

# 6) Rebuild caches
/usr/bin/php8.4 artisan optimize:clear
/usr/bin/php8.4 artisan config:cache
/usr/bin/php8.4 artisan route:cache
/usr/bin/php8.4 artisan view:cache

# 7) Restart queue workers (if used)
/usr/bin/php8.4 artisan queue:restart

# 8) Exit maintenance mode
/usr/bin/php8.4 artisan up
```

Production `.env` should use `APP_URL=https://mediapps.online` without a trailing slash.

## Post-Deploy Verification

```bash
cd /home/mediapps/htdocs/mediapps.online
/usr/bin/php8.4 artisan schedule:list
/usr/bin/php8.4 artisan schedule:run
ls -la public/storage
ls -la storage/app/public/banner-ads
tail -n 200 storage/logs/laravel.log
tail -n 200 storage/logs/cron.log
```

## Required Cron Entry

```cron
* * * * * cd /home/mediapps/htdocs/mediapps.online && /usr/bin/php8.4 artisan schedule:run >> storage/logs/cron.log 2>&1
```

## Quick Rollback

```bash
cd /home/mediapps/htdocs/mediapps.online
/usr/bin/php8.4 artisan down
git fetch --tags
git checkout <previous_release_tag>
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
/usr/bin/php8.4 artisan migrate --force
/usr/bin/php8.4 artisan optimize:clear
/usr/bin/php8.4 artisan up
```
