# 🚀 Complete Deployment Guide - Traders Point Academy

This is your **step-by-step guide** to deploy Traders Point Academy to production.

---

## 📋 Pre-Deployment Checklist

Before starting deployment, ensure you have:

- [ ] **Production server** (VPS/Cloud with Ubuntu/Debian/CentOS)
- [ ] **Domain name** configured and pointing to server IP
- [ ] **SSL Certificate** (Let's Encrypt recommended)
- [ ] **Razorpay account** with Live API access
- [ ] **Email service** (Gmail/AWS SES/Mailgun account)
- [ ] **Database** (MySQL 8.0+ installed)
- [ ] **Server requirements**:
  - PHP 8.2+
  - Composer
  - Node.js 18+
  - Nginx or Apache
  - Supervisor (for queue workers)
  - Git

---

## 🎯 Deployment Steps

### Step 1: Server Preparation

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-mbstring \
    php8.2-xml php8.2-bcmath php8.2-curl php8.2-gd php8.2-zip \
    mysql-server nginx git composer nodejs npm supervisor

# Install npm (if not included)
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs

# Verify installations
php -v
composer -V
node -v
npm -v
```

### Step 2: MySQL Database Setup

```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Create database and user
sudo mysql
```

```sql
CREATE DATABASE traders_point CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'traders_point_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON traders_point.* TO 'traders_point_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Step 3: Clone and Setup Application

```bash
# Navigate to web root
cd /var/www

# Clone repository (replace with your repo URL)
sudo git clone https://github.com/your-username/traders-point.git
cd traders-point

# Set ownership
sudo chown -R www-data:www-data /var/www/traders-point
sudo chmod -R 755 /var/www/traders-point

# Install PHP dependencies
composer install --optimize-autoloader --no-dev

# Install Node dependencies and build assets
npm ci --production
npm run build
```

### Step 4: Environment Configuration

```bash
# Copy production environment template
cp .env.production.example .env

# Generate application key
php artisan key:generate

# Edit environment file
nano .env
```

**Update these values in `.env`**:

```env
APP_NAME="Traders Point Academy"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=traders_point
DB_USERNAME=traders_point_user
DB_PASSWORD=YOUR_STRONG_DATABASE_PASSWORD

# Choose your mail provider and configure
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# Razorpay LIVE credentials
RAZORPAY_KEY=rzp_live_XXXXXXXXXX
RAZORPAY_SECRET=YOUR_LIVE_SECRET
RAZORPAY_WEBHOOK_SECRET=whsec_XXXXXXXXXX

SESSION_ENCRYPT=true
SESSION_DOMAIN=yourdomain.com
```

### Step 5: Database Migration

```bash
# Run migrations
php artisan migrate --force

# Seed initial data (admin user, categories)
php artisan db:seed

# Create storage link
php artisan storage:link
```

### Step 6: File Permissions

```bash
# Set correct permissions
sudo chown -R www-data:www-data /var/www/traders-point
sudo chmod -R 755 /var/www/traders-point
sudo chmod -R 775 /var/www/traders-point/storage
sudo chmod -R 775 /var/www/traders-point/bootstrap/cache
```

### Step 7: Nginx Configuration

```bash
# Create Nginx configuration
sudo nano /etc/nginx/sites-available/traders-point
```

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    
    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;

    # SSL Certificate (Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    root /var/www/traders-point/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Logging
    access_log /var/log/nginx/traders-point-access.log;
    error_log /var/log/nginx/traders-point-error.log;

    # Max upload size
    client_max_body_size 128M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to sensitive files
    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~ /\.(env|git|composer|npm) {
        deny all;
    }

    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/traders-point /etc/nginx/sites-enabled/

# Test configuration
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx
```

### Step 8: SSL Certificate (Let's Encrypt)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx -y

# Obtain certificate
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# Verify auto-renewal
sudo certbot renew --dry-run
```

### Step 9: Queue Worker Setup

```bash
# Copy supervisor configuration
sudo cp /var/www/traders-point/supervisor-queue-worker.conf \
    /etc/supervisor/conf.d/traders-point.conf

# Edit configuration
sudo nano /etc/supervisor/conf.d/traders-point.conf
```

Update paths in the file:
```ini
command=php /var/www/traders-point/artisan queue:work --sleep=3 --tries=3 --max-time=3600
stdout_logfile=/var/www/traders-point/storage/logs/queue-worker.log
```

```bash
# Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start traders-point-worker:*

# Verify workers are running
sudo supervisorctl status
```

### Step 10: Laravel Optimization

```bash
cd /var/www/traders-point

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Optimize composer autoloader
composer dump-autoload --optimize
```

### Step 11: Setup Razorpay Webhook

1. **Login to Razorpay Dashboard**: https://dashboard.razorpay.com
2. **Switch to Live Mode** (toggle at top)
3. **Go to Settings → Webhooks**
4. **Add New Webhook**:
   - URL: `https://yourdomain.com/razorpay/webhook`
   - Active Events: Select `payment.captured`
   - Secret: Generate and copy to `.env` as `RAZORPAY_WEBHOOK_SECRET`
5. **Test webhook** using Razorpay's test tool

### Step 12: Database Backups

```bash
# Copy backup script
sudo cp /var/www/traders-point/backup-database.sh /usr/local/bin/
sudo chmod +x /usr/local/bin/backup-database.sh

# Edit script with your database credentials
sudo nano /usr/local/bin/backup-database.sh

# Create backup directory
sudo mkdir -p /backups/database
sudo chown www-data:www-data /backups/database

# Add to crontab (daily at 2 AM)
sudo crontab -e
```

Add this line:
```
0 2 * * * /usr/local/bin/backup-database.sh >> /var/log/database-backup.log 2>&1
```

### Step 13: Monitoring Setup

```bash
# Install monitoring tools
sudo apt install htop iotop iftop -y

# Setup log rotation
sudo nano /etc/logrotate.d/traders-point
```

```
/var/www/traders-point/storage/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        systemctl reload php8.2-fpm
    endscript
}
```

### Step 14: Final Verification

```bash
# Check application status
cd /var/www/traders-point
php artisan about

# Verify queue workers
ps aux | grep queue:work

# Check logs for errors
tail -f storage/logs/laravel.log

# Verify web server
curl -I https://yourdomain.com
```

---

## ✅ Post-Deployment Testing

### 1. Basic Functionality

- [ ] Visit `https://yourdomain.com` - Homepage loads
- [ ] Click around - All links work
- [ ] Check SSL - Green padlock in browser

### 2. User Authentication

- [ ] Register new account
- [ ] Receive verification email
- [ ] Verify email (click link)
- [ ] Login successfully
- [ ] Test password reset

### 3. Video Features

- [ ] Browse categories
- [ ] View free video details
- [ ] Play free video (streaming works)
- [ ] Progress saves while watching

### 4. Payment Flow

- [ ] Select paid video
- [ ] Apply coupon code (if available)
- [ ] Click purchase
- [ ] Razorpay modal opens
- [ ] Complete test payment (₹1)
- [ ] Receive purchase receipt email
- [ ] Video appears in dashboard
- [ ] Can watch purchased video

### 5. Certificate System

- [ ] Complete all videos in a category
- [ ] Certificate becomes available
- [ ] Download certificate PDF
- [ ] Verify certificate works (public verification page)

### 6. Admin Panel

- [ ] Login as admin
- [ ] Dashboard shows statistics
- [ ] Create/edit video
- [ ] Create/edit category
- [ ] Create/edit coupon
- [ ] View orders list

---

## 🔧 Common Issues & Solutions

### Issue: 500 Internal Server Error

**Check:**
```bash
# View error logs
tail -f /var/www/traders-point/storage/logs/laravel.log
tail -f /var/log/nginx/traders-point-error.log

# Check permissions
ls -la /var/www/traders-point/storage
ls -la /var/www/traders-point/bootstrap/cache

# Fix if needed
sudo chown -R www-data:www-data /var/www/traders-point/storage
sudo chmod -R 775 /var/www/traders-point/storage
```

### Issue: Email Not Sending

**Check:**
```bash
# Verify queue worker is running
sudo supervisorctl status

# Check queue logs
tail -f /var/www/traders-point/storage/logs/queue-worker.log

# Test email manually
php artisan tinker
>>> Mail::raw('Test', fn($m)=>$m->to('test@example.com')->subject('Test'))

# Check Laravel logs
tail -f /var/www/traders-point/storage/logs/laravel.log
```

### Issue: Payment Not Working

**Check:**
1. Verify using **LIVE** Razorpay keys (not test)
2. Check webhook is configured in Razorpay dashboard
3. Verify webhook secret in `.env`
4. Check Laravel logs for payment errors

```bash
tail -f storage/logs/laravel.log | grep -i razorpay
```

### Issue: Video Not Streaming

**Check:**
```bash
# Verify storage link
ls -la /var/www/traders-point/public/storage

# Recreate if missing
php artisan storage:link

# Check video file permissions
ls -la /var/www/traders-point/storage/app/private/videos/

# Fix permissions
sudo chown -R www-data:www-data /var/www/traders-point/storage
```

---

## 📊 Monitoring Commands

```bash
# Check application health
curl https://yourdomain.com/up

# Check queue workers
sudo supervisorctl status

# View recent errors
grep "ERROR" /var/www/traders-point/storage/logs/laravel-$(date +%Y-%m-%d).log

# Check disk space
df -h

# Monitor PHP-FPM
sudo systemctl status php8.2-fpm

# Monitor Nginx
sudo systemctl status nginx

# View active connections
sudo netstat -ant | grep :443 | wc -l
```

---

## 🔄 Updating Production

When you need to deploy code updates:

```bash
cd /var/www/traders-point

# Put in maintenance mode
php artisan down --render="errors::503" --retry=60

# Pull latest code
git pull origin main

# Update dependencies
composer install --optimize-autoloader --no-dev
npm ci --production
npm run build

# Run migrations
php artisan migrate --force

# Clear and cache
php artisan config:clear
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue workers
php artisan queue:restart
sudo supervisorctl restart traders-point-worker:*

# Bring back online
php artisan up
```

Or use the automated script:
```bash
bash /var/www/traders-point/deploy-production.sh
```

---

## 🆘 Emergency Procedures

### Rollback to Previous Version

```bash
cd /var/www/traders-point
php artisan down
git reset --hard HEAD~1
composer install --no-dev
php artisan migrate:rollback
php artisan config:cache
php artisan up
```

### Clear All Caches

```bash
cd /var/www/traders-point
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear
sudo systemctl restart php8.2-fpm
sudo systemctl reload nginx
```

### Restore Database Backup

```bash
# List backups
ls -lh /backups/database/

# Restore specific backup
gunzip < /backups/database/traders_point_YYYYMMDD_HHMMSS.sql.gz | \
    mysql -u traders_point_user -p traders_point
```

---

## 📞 Support Resources

- **Laravel Docs**: https://laravel.com/docs
- **Razorpay Docs**: https://razorpay.com/docs/
- **Project Issues**: GitHub Issues
- **Emergency Contact**: [Your contact info]

---

## ✅ Deployment Complete!

Once all steps are complete and tests pass, your application is live! 🎉

**Don't forget to**:
- [ ] Setup uptime monitoring (UptimeRobot, Pingdom)
- [ ] Configure error tracking (Sentry, Flare)
- [ ] Schedule regular backups
- [ ] Monitor logs daily
- [ ] Keep dependencies updated

**Congratulations on your deployment! 🚀**
