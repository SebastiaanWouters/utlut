# Production Deployment Checklist

## Environment Configuration

### Required Environment Variables

Ensure these are set in your production `.env` file:

```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Security
APP_KEY=base64:your-generated-key-here
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

# Database
DB_CONNECTION=mysql  # or your production database
DB_HOST=your-db-host
DB_DATABASE=your-database
DB_USERNAME=your-username
DB_PASSWORD=your-password

# Logging
LOG_CHANNEL=daily
LOG_LEVEL=info

# Cache (recommended: redis for production)
CACHE_STORE=redis
CACHE_PREFIX=sundo-cache-

# Queue (recommended: redis for production)
QUEUE_CONNECTION=redis

# Session (recommended: redis for production)
SESSION_DRIVER=redis

# Trust Proxies (if behind load balancer)
TRUST_PROXIES=*  # or specific IPs: 192.168.1.1,10.0.0.0/8

# Security Headers (optional, enabled by default in production)
ENABLE_SECURITY_HEADERS=true

# Services
NAGA_API_KEY=your-naga-api-key
NAGA_API_URL=https://api.naga.ac
```

## Pre-Deployment Steps

1. **Generate Application Key**
   ```bash
   php artisan key:generate
   ```

2. **Run Database Migrations**
   ```bash
   php artisan migrate --force
   ```

3. **Cache Configuration** (IMPORTANT for performance)
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

4. **Optimize Autoloader**
   ```bash
   composer install --optimize-autoloader --no-dev
   ```

5. **Build Frontend Assets**
   ```bash
   npm ci
   npm run build
   ```

6. **Set Proper Permissions**
   ```bash
   chmod -R 775 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   ```

## Production Optimizations

### Queue Worker

Set up a queue worker process (using Supervisor, systemd, or your hosting provider's solution):

```bash
php artisan queue:work --tries=3 --timeout=90
```

### Scheduled Tasks

Add Laravel scheduler to cron (runs every minute):

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### Log Rotation

Logs are automatically rotated daily (14 days retention by default). Adjust `LOG_DAILY_DAYS` in `.env` if needed.

## Security Features Implemented

✅ **API Rate Limiting**
- Token endpoint: 10 requests per minute
- General API endpoints: 60 requests per minute
- TTS endpoint: 5 requests per minute

✅ **Security Headers**
- X-Content-Type-Options: nosniff
- X-Frame-Options: SAMEORIGIN
- X-XSS-Protection: 1; mode=block
- Referrer-Policy: strict-origin-when-cross-origin
- Strict-Transport-Security: (HTTPS only)

✅ **Session Security**
- Secure cookies in production
- HTTP-only cookies
- SameSite protection

✅ **Error Handling**
- Debug mode disabled in production
- Sensitive exceptions not reported
- Proper error logging

## Monitoring & Maintenance

### Health Check Endpoint

The application includes a health check endpoint at `/up` for monitoring.

### Log Locations

- Application logs: `storage/logs/laravel.log`
- Daily logs: `storage/logs/laravel-YYYY-MM-DD.log`
- Browser logs: `storage/logs/browser.log`

### Cache Management

Clear caches when needed:
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## Performance Recommendations

1. **Use Redis** for cache, sessions, and queues in production
2. **Enable OPcache** in PHP for better performance
3. **Use CDN** for static assets if possible
4. **Enable HTTP/2** on your web server
5. **Use database connection pooling** if available
6. **Monitor queue processing** and scale workers as needed

## Troubleshooting

### If assets aren't loading:
```bash
npm run build
php artisan view:clear
```

### If configuration changes aren't taking effect:
```bash
php artisan config:clear
php artisan config:cache
```

### If routes aren't working:
```bash
php artisan route:clear
php artisan route:cache
```

