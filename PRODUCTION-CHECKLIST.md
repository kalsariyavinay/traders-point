# 🚀 Production Deployment Checklist - Traders Point Academy

## ⚠️ CRITICAL - Must Complete Before Going Live

### 1. Environment Configuration

- [ ] **Copy `.env.example` to `.env` on production server**
- [ ] **Update APP_ENV to `production`**
- [ ] **Set APP_DEBUG to `false`**
- [ ] **Update APP_URL to your actual domain** (e.g., `https://traderspoint.com`)
- [ ] **Generate new APP_KEY**: `php artisan key:generate`

### 2. Database Configuration

- [ ] **Set strong DB_PASSWORD** (not empty!)
- [ ] **Verify database connection works**
- [ ] **Run migrations**: `php artisan migrate --force`
- [ ] **Seed initial data**: `php artisan db:seed` (if needed)
- [ ] **Backup database regularly** (setup cron for mysqldump)

### 3. Mail Configuration (CRITICAL for receipts)

Current: Using `log` driver (emails not sent)

**Choose one option:**

**Option A: Gmail SMTP**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password  # Generate from Google Account settings
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Traders Point Academy"
```

**Option B: AWS SES**
```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Traders Point Academy"
```

**Option C: Mailgun**
```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=mg.yourdomain.com
MAILGUN_SECRET=your-mailgun-api-key
MAILGUN_ENDPOINT=api.mailgun.net
MAIL_FROM_ADDRESS=noreply@yourdomain.com
```

- [ ] **Configure mail settings in .env**
- [ ] **Test email sending**: `php artisan tinker` then `Mail::raw('Test', fn($m) => $m->to('test@example.com')->subject('Test'))`

### 4. Razorpay Payment Gateway

**CRITICAL: Remove test credentials!**

Current `.env` has TEST KEYS that are **NOT SECURE**:
```
RAZORPAY_KEY=rzp_test_SjcUYDIk0UamLl  ❌ REPLACE
RAZORPAY_SECRET=LMjMSsEyAfcKwYivDtJPZ0bK  ❌ REPLACE
```

**Steps:**
1. [ ] **Login to Razorpay Dashboard** → Go to Live Mode
2. [ ] **Generate Live API Keys** (Settings → API Keys)
3. [ ] **Update .env with LIVE keys**:
   ```env
   RAZORPAY_KEY=rzp_live_XXXXXXXXXX
   RAZORPAY_SECRET=LIVE_SECRET_HERE
   ```
4. [ ] **Setup Webhook** in Razorpay Dashboard:
   - URL: `https://yourdomain.com/razorpay/webhook`
   - Events: Select "payment.captured"
   - Generate webhook secret and add to .env:
     ```env
     RAZORPAY_WEBHOOK_SECRET=whsec_XXXXXXXXXX
     ```
5. [ ] **Test payment flow** with small amount (₹1)
6. [ ] **Verify webhook delivery** in Razorpay dashboard

### 5. HTTPS Configuration

- [ ] **Obtain SSL certificate** (Let's Encrypt, Cloudflare, or paid)
- [ ] **Configure web server** (Nginx/Apache) to use HTTPS
- [ ] **Force HTTPS** - Already configured in AppServiceProvider
- [ ] **Update APP_URL** to use `https://`
- [ ] **Test certificate** at https://www.ssllabs.com/ssltest/

### 6. File Storage & Uploads

- [ ] **Run storage link**: `php artisan storage:link`
- [ ] **Set correct permissions**:
  ```bash
  chmod -R 755 storage bootstrap/cache
  chown -R www-data:www-data storage bootstrap/cache
  ```
- [ ] **Test file upload** (video + thumbnail)
- [ ] **Verify video streaming works**
- [ ] **Consider S3/CloudFront** for better performance (optional)

### 7. Queue Workers (CRITICAL for emails)

Emails are queued and won't send without a queue worker!

**Option A: Supervisor (Recommended for Linux)**
1. [ ] **Copy supervisor config**:
   ```bash
   sudo cp supervisor-queue-worker.conf /etc/supervisor/conf.d/traders-point.conf
   ```
2. [ ] **Edit paths** in the config file
3. [ ] **Start supervisor**:
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start traders-point-worker:*
   ```
4. [ ] **Verify running**: `sudo supervisorctl status`

**Option B: Systemd Service (Alternative)**
1. [ ] Create systemd service file
2. [ ] Enable and start service
3. [ ] Monitor with `systemctl status`

**Option C: Cron (Not recommended but works)**
```cron
* * * * * cd /path/to/project && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
```

### 8. Caching & Performance

- [ ] **Enable OPcache** in PHP:
  ```ini
  ; Add to php.ini
  opcache.enable=1
  opcache.memory_consumption=128
  opcache.interned_strings_buffer=8
  opcache.max_accelerated_files=10000
  opcache.revalidate_freq=2
  ```
- [ ] **Run optimization commands**:
  ```bash
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan event:cache
  composer install --optimize-autoloader --no-dev
  ```
- [ ] **Consider Redis** for cache/sessions (optional but recommended)

### 9. Session Configuration

- [ ] **Update SESSION_DOMAIN** in .env to your domain (without https://)
- [ ] **Set SESSION_ENCRYPT=true**
- [ ] **Verify session storage works** (database is current driver)

### 10. Security Hardening

- [ ] **Disable directory listing** in web server
- [ ] **Hide server version** (Apache/Nginx)
- [ ] **Set security headers**:
  ```nginx
  # Nginx example
  add_header X-Frame-Options "SAMEORIGIN";
  add_header X-Content-Type-Options "nosniff";
  add_header X-XSS-Protection "1; mode=block";
  add_header Referrer-Policy "strict-origin-when-cross-origin";
  ```
- [ ] **Verify .env file is NOT accessible** via browser
- [ ] **Disable unused PHP functions** (if possible)
- [ ] **Setup firewall** (UFW on Ubuntu, firewalld on CentOS)
- [ ] **Regular security updates**: `apt update && apt upgrade`

### 11. Logging & Monitoring

- [ ] **Configure log rotation** (logrotate):
  ```bash
  /path/to/project/storage/logs/*.log {
      daily
      rotate 14
      compress
      delaycompress
      notifempty
      create 0640 www-data www-data
      sharedscripts
  }
  ```
- [ ] **Monitor disk space** for log files
- [ ] **Setup error tracking** (Sentry, Bugsnag, or Flare)
- [ ] **Monitor payment failures** (check logs daily)
- [ ] **Setup uptime monitoring** (UptimeRobot, Pingdom)

### 12. Backup Strategy

- [ ] **Database backups** (daily cron):
  ```bash
  # Add to crontab
  0 2 * * * mysqldump -u root -p'password' traders_point | gzip > /backup/db-$(date +\%Y\%m\%d).sql.gz
  ```
- [ ] **File backups** (storage/app/private/videos)
- [ ] **Code backups** (Git repository)
- [ ] **Test restore procedure** (at least once!)

### 13. Frontend Assets

- [ ] **Build assets**: `npm run build`
- [ ] **Verify manifest exists**: `public/build/manifest.json`
- [ ] **Test CSS/JS loading** on live site
- [ ] **Consider CDN** for static assets (optional)

### 14. Testing Checklist

Test everything before announcing launch:

- [ ] **User registration** works
- [ ] **Email verification** works (check spam folder)
- [ ] **Login/Logout** works
- [ ] **Password reset** works (email delivery)
- [ ] **Browse free videos** works
- [ ] **Watch free video** (streaming)
- [ ] **Purchase paid video** (test payment ₹1)
- [ ] **Receive purchase receipt** email
- [ ] **Watch purchased video**
- [ ] **Video progress** saves correctly
- [ ] **Certificate generation** works (after completing category)
- [ ] **Certificate verification** page works
- [ ] **Admin login** works
- [ ] **Admin can create/edit** videos, categories, coupons
- [ ] **Coupon codes** work at checkout
- [ ] **Admin dashboard** shows correct stats

### 15. DNS & Domain

- [ ] **Configure DNS** A record to point to server IP
- [ ] **Configure web server** virtual host
- [ ] **Verify domain resolves**: `nslookup yourdomain.com`
- [ ] **Test from multiple locations**

### 16. Legal & Compliance

- [ ] **Terms of Service** page (if needed)
- [ ] **Privacy Policy** page (if needed)
- [ ] **Refund Policy** (Razorpay requirement)
- [ ] **Contact Information** visible
- [ ] **GST compliance** (if applicable in India)

---

## 🔧 Quick Production Setup Commands

```bash
# 1. Clone repository
git clone https://github.com/your-repo/traders-point.git
cd traders-point

# 2. Install dependencies
composer install --optimize-autoloader --no-dev
npm ci --production

# 3. Environment setup
cp .env.example .env
php artisan key:generate
# Edit .env with production settings

# 4. Database setup
php artisan migrate --force
php artisan db:seed

# 5. Storage setup
php artisan storage:link
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 6. Build frontend
npm run build

# 7. Optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer dump-autoload --optimize

# 8. Start queue worker (if using supervisor)
sudo supervisorctl start traders-point-worker:*
```

---

## 📊 Monitoring Commands

```bash
# Check application status
php artisan about

# View recent logs
tail -f storage/logs/laravel.log

# Check queue status
php artisan queue:monitor

# Check failed jobs
php artisan queue:failed

# Clear all caches (if something breaks)
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## 🆘 Troubleshooting

### Email not sending
1. Check `.env` MAIL_* settings
2. Test: `php artisan tinker` → `Mail::raw('test', fn($m)=>$m->to('test@example.com')->subject('test'))`
3. Check logs: `tail -f storage/logs/laravel.log`
4. Verify queue worker running: `ps aux | grep queue:work`

### Payment not working
1. Verify Razorpay LIVE keys in `.env`
2. Check webhook URL in Razorpay dashboard
3. Test webhook: `php artisan tinker` → `app(App\Services\RazorpayGateway::class)->isWebhookConfigured()`
4. Monitor: `tail -f storage/logs/laravel.log | grep razorpay`

### Video not streaming
1. Verify `php artisan storage:link` was run
2. Check file permissions: `ls -la storage/app/private/videos/`
3. Verify APP_URL is correct in `.env`
4. Test signed URL generation

### 500 Error
1. Check logs: `tail -f storage/logs/laravel.log`
2. Verify file permissions
3. Clear caches: `php artisan config:clear && php artisan cache:clear`
4. Check web server error logs

---

## 📞 Support Resources

- **Laravel Docs**: https://laravel.com/docs
- **Razorpay Docs**: https://razorpay.com/docs/
- **Project Issues**: [Your GitHub repo issues page]

---

**Last Updated**: Run this checklist before every production deployment!
