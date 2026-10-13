# 📊 Monitoring & Health Checks

## Health Check Endpoint

Laravel 11 includes a built-in health check endpoint:

**URL**: `https://yourdomain.com/up`

**Response (Healthy)**:
```json
{"status": "ok"}
```
Status Code: 200

**Response (Unhealthy)**:
Status Code: 503

### Uptime Monitoring

Configure your uptime monitoring service to check this endpoint:

**Recommended Services**:
- **UptimeRobot** (Free): https://uptimerobot.com
- **Pingdom**: https://www.pingdom.com
- **Better Uptime**: https://betteruptime.com
- **StatusCake**: https://www.statuscake.com

**Configuration**:
- Monitor URL: `https://yourdomain.com/up`
- Check Interval: 5 minutes
- Alert on: HTTP 503 or connection timeout
- Alert channels: Email, SMS, Slack

---

## Application Monitoring

### 1. Error Tracking (Recommended)

**Flare** (Laravel-specific, excellent):
```bash
composer require spatie/laravel-ignition spatie/flare-client-php
```

**Sentry** (Popular, feature-rich):
```bash
composer require sentry/sentry-laravel
```

Configuration:
```env
SENTRY_LARAVEL_DSN=https://xxxxx@sentry.io/xxxxx
SENTRY_TRACES_SAMPLE_RATE=0.2
```

**Bugsnag** (Alternative):
```bash
composer require bugsnag/bugsnag-laravel
```

### 2. Application Performance Monitoring (APM)

**New Relic** (Production-grade):
- Install New Relic PHP agent
- Monitors response times, database queries, external APIs
- Free tier available

**Laravel Telescope** (Development/Staging only):
```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

⚠️ **DO NOT use Telescope in production!** It stores sensitive data and slows down the app.

---

## Queue Monitoring

### Check Queue Status

```bash
# View queue statistics
php artisan queue:monitor

# List failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry {id}

# Retry all failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

### Supervisor Monitoring

```bash
# Check if workers are running
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart traders-point-worker:*

# View worker logs
tail -f /path/to/storage/logs/queue-worker.log
```

### Queue Alerts

Create a cron job to alert on failed jobs:

```bash
# Add to crontab
*/15 * * * * cd /path/to/project && php artisan queue:failed --json | jq '. | length' | xargs -I {} bash -c '[ {} -gt 0 ] && echo "⚠️ {} failed jobs in queue!" | mail -s "Queue Alert" admin@example.com'
```

---

## Payment Monitoring

### Razorpay Dashboard

Monitor via Razorpay Dashboard:
- **Failed Payments**: Check daily
- **Webhook Delivery**: Ensure webhooks are being received
- **Settlement Status**: Track daily settlements

### Application Monitoring

Check payment logs:
```bash
# View recent payment logs
tail -f storage/logs/laravel.log | grep -i razorpay

# Count failed payments today
grep "payment.*failed" storage/logs/laravel-$(date +%Y-%m-%d).log | wc -l
```

### Payment Failure Alerts

Create custom alert script (`check-payment-failures.sh`):
```bash
#!/bin/bash
FAILURES=$(grep -c "payment.*failed" storage/logs/laravel-$(date +%Y-%m-%d).log)
if [ $FAILURES -gt 5 ]; then
    echo "⚠️ $FAILURES payment failures detected today!" | mail -s "Payment Alert" admin@example.com
fi
```

Add to crontab:
```
0 */4 * * * /path/to/check-payment-failures.sh
```

---

## Database Monitoring

### Connection Pool

Monitor active connections:
```sql
SHOW PROCESSLIST;
SHOW STATUS LIKE 'Threads_connected';
SHOW STATUS LIKE 'Max_used_connections';
```

### Slow Queries

Enable slow query log in MySQL:
```ini
# Add to my.cnf
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow-query.log
long_query_time = 2
```

Analyze slow queries:
```bash
mysqldumpslow /var/log/mysql/slow-query.log
```

### Disk Space

Monitor database size:
```sql
SELECT 
    table_schema AS 'Database',
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.TABLES
WHERE table_schema = 'traders_point'
GROUP BY table_schema;
```

---

## Log Monitoring

### Important Logs

**Application Logs**:
```bash
tail -f storage/logs/laravel.log
```

**Queue Worker Logs**:
```bash
tail -f storage/logs/queue-worker.log
```

**Web Server Logs** (Nginx):
```bash
tail -f /var/log/nginx/error.log
tail -f /var/log/nginx/access.log
```

**PHP-FPM Logs**:
```bash
tail -f /var/log/php8.2-fpm.log
```

### Log Analysis

Search for errors:
```bash
# Count errors by type
grep "ERROR" storage/logs/laravel-$(date +%Y-%m-%d).log | cut -d' ' -f5 | sort | uniq -c | sort -rn

# Find 500 errors
grep "500" storage/logs/laravel-$(date +%Y-%m-%d).log

# Monitor in real-time
tail -f storage/logs/laravel.log | grep -i "error\|exception\|fail"
```

### Log Rotation

Configure logrotate (`/etc/logrotate.d/traders-point`):
```
/path/to/storage/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        # Reload PHP-FPM to release old log files
        systemctl reload php8.2-fpm
    endscript
}
```

---

## Server Resource Monitoring

### CPU & Memory

```bash
# Current usage
htop

# Memory usage by process
ps aux --sort=-%mem | head -n 10

# PHP-FPM pool status (if configured)
curl http://localhost/php-fpm-status
```

### Disk Space

```bash
# Check disk usage
df -h

# Find large files
du -h /path/to/storage | sort -rh | head -n 20

# Check inode usage
df -i
```

### Network

```bash
# Check open connections
netstat -ant | grep :80 | wc -l
netstat -ant | grep :443 | wc -l

# Monitor bandwidth
iftop
```

---

## Automated Monitoring Script

Create `health-check.sh`:
```bash
#!/bin/bash

echo "=== Traders Point Academy Health Check ==="
echo "Date: $(date)"
echo ""

# 1. Application reachable
echo "1. Application Status:"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" https://yourdomain.com/up)
if [ $HTTP_CODE -eq 200 ]; then
    echo "   ✅ Application is up (HTTP $HTTP_CODE)"
else
    echo "   ❌ Application is down (HTTP $HTTP_CODE)"
    # Send alert
fi

# 2. Queue workers running
echo "2. Queue Workers:"
WORKERS=$(ps aux | grep "queue:work" | grep -v grep | wc -l)
if [ $WORKERS -gt 0 ]; then
    echo "   ✅ $WORKERS queue workers running"
else
    echo "   ❌ No queue workers running!"
    # Send alert
fi

# 3. Failed jobs
echo "3. Failed Jobs:"
cd /path/to/project
FAILED=$(php artisan queue:failed --json 2>/dev/null | jq '. | length')
if [ $FAILED -eq 0 ]; then
    echo "   ✅ No failed jobs"
else
    echo "   ⚠️  $FAILED failed jobs in queue"
fi

# 4. Disk space
echo "4. Disk Space:"
DISK_USAGE=$(df -h /path/to/project | tail -1 | awk '{print $5}' | sed 's/%//')
if [ $DISK_USAGE -lt 80 ]; then
    echo "   ✅ Disk usage: ${DISK_USAGE}%"
else
    echo "   ⚠️  Disk usage: ${DISK_USAGE}% (High!)"
fi

# 5. Recent errors
echo "5. Recent Errors (last hour):"
ERROR_COUNT=$(grep -c "ERROR" storage/logs/laravel-$(date +%Y-%m-%d).log 2>/dev/null || echo 0)
echo "   Errors in last 24h: $ERROR_COUNT"

echo ""
echo "=== Health Check Complete ==="
```

Add to crontab:
```
*/5 * * * * /path/to/health-check.sh >> /var/log/traders-point-health.log 2>&1
```

---

## Alerting Channels

### Email Alerts

Configure in Laravel:
```php
// config/mail.php - already configured via .env
```

### Slack Alerts

1. Create Incoming Webhook in Slack
2. Add to `.env`:
   ```env
   LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/xxx/yyy/zzz
   ```
3. Configure in `config/logging.php`:
   ```php
   'slack' => [
       'driver' => 'slack',
       'url' => env('LOG_SLACK_WEBHOOK_URL'),
       'username' => 'Traders Point Bot',
       'emoji' => ':boom:',
       'level' => 'critical',
   ],
   ```

### SMS Alerts (Twilio)

```bash
composer require twilio/sdk
```

Create alert script using Twilio API.

---

## Key Metrics to Monitor

### Daily Checklist

- [ ] Application uptime (99.9%+ target)
- [ ] Queue workers running
- [ ] No failed jobs (or investigate failures)
- [ ] No payment failures
- [ ] Error rate < 1%
- [ ] Database size trending
- [ ] Disk space < 80%
- [ ] Backup completed successfully

### Weekly Checklist

- [ ] Review slow query log
- [ ] Check certificate expiration
- [ ] Review user growth
- [ ] Analyze popular videos
- [ ] Check revenue trends
- [ ] Review and archive old logs

### Monthly Checklist

- [ ] Security updates applied
- [ ] SSL certificate renewed (if needed)
- [ ] Database optimization
- [ ] Backup restoration test
- [ ] Review and optimize queries
- [ ] Cost analysis (hosting, services)

---

## Dashboard Recommendations

**Free/Open Source**:
- **Grafana** + **Prometheus**: Comprehensive metrics
- **Netdata**: Real-time server monitoring
- **Monit**: Simple process monitoring

**Commercial**:
- **Datadog**: Full-stack monitoring
- **New Relic**: APM + Infrastructure
- **Logtail**: Log management
- **PagerDuty**: Incident management

---

## Support Contact

For critical issues:
- Email: admin@yourdomain.com
- Slack: #production-alerts
- Phone: +91-XXXXXXXXXX

**Incident Response SLA**:
- Critical (Site down): < 15 minutes
- High (Payment issues): < 1 hour  
- Medium (Feature broken): < 4 hours
- Low (UI bugs): < 24 hours
