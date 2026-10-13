# 🎓 Traders Point Academy

A professional Laravel-based video learning platform with integrated payment processing, progress tracking, and certificate generation.

## ✨ Features

- 📹 **Video Learning Platform**: Organized courses with categories
- 💳 **Payment Integration**: Razorpay payment gateway for paid courses
- 🎟️ **Coupon System**: Discount codes with usage limits and expiration
- 📊 **Progress Tracking**: Automatic video playback position saving
- 🏆 **Certificates**: PDF certificates on course completion with verification
- 👤 **User Dashboard**: Personal library with purchased/free content
- 🛡️ **Admin Panel**: Complete CRUD for videos, categories, coupons, and orders
- 🔐 **Secure Video Streaming**: Time-limited, session-bound video URLs
- ✉️ **Email Receipts**: Automated purchase confirmation emails
- 🔒 **Authentication**: Laravel Breeze with email verification

## 🚀 Quick Start (Development)

```bash
# Clone repository
git clone https://github.com/your-repo/traders-point.git
cd traders-point

# Install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database setup
# Create database 'traders_point' in MySQL
php artisan migrate
php artisan db:seed

# Storage setup
php artisan storage:link

# Build assets
npm run build

# Start development server
php artisan serve
```

Visit: `http://localhost:8000`

## 📦 Production Deployment

**⚠️ IMPORTANT**: Read `PRODUCTION-CHECKLIST.md` before deploying!

### Quick Production Setup

```bash
# 1. Clone and install
git clone https://github.com/your-repo/traders-point.git
cd traders-point
composer install --optimize-autoloader --no-dev
npm ci --production

# 2. Configure environment
cp .env.production.example .env
php artisan key:generate
# Edit .env with production settings

# 3. Database
php artisan migrate --force
php artisan db:seed

# 4. Optimize
npm run build
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Setup queue worker (see PRODUCTION-CHECKLIST.md)
```

### Critical Production Steps

1. ✅ **Remove test credentials** from `.env`
2. ✅ **Use Razorpay LIVE keys** (not test keys)
3. ✅ **Set `APP_DEBUG=false`**
4. ✅ **Configure real SMTP** for emails
5. ✅ **Setup queue workers** (emails won't send otherwise!)
6. ✅ **Enable HTTPS** with valid SSL certificate
7. ✅ **Configure webhook secret** in Razorpay dashboard

📚 **Full guide**: See `PRODUCTION-CHECKLIST.md`

## 🔧 Requirements

- PHP 8.2+
- MySQL 8.0+
- Composer
- Node.js 18+ & NPM
- HTTPS (production)

### PHP Extensions Required

- BCMath
- Ctype
- Fileinfo
- JSON
- Mbstring
- OpenSSL
- PDO
- Tokenizer
- XML
- GD (for PDF generation)

## 📚 Documentation

- **[Production Checklist](PRODUCTION-CHECKLIST.md)** - Complete deployment guide
- **[Monitoring Guide](MONITORING.md)** - Health checks and monitoring setup
- **[Project Blueprint](PROJECT-BLUEPRINT.md)** - Architecture and features

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run with coverage
php artisan test --coverage
```

## 🔐 Security

- ✅ HTTPS enforcement in production
- ✅ CSRF protection on all forms
- ✅ Rate limiting on sensitive endpoints
- ✅ Secure video streaming (signed URLs)
- ✅ Session encryption enabled
- ✅ Security headers middleware
- ✅ Webhook signature verification
- ✅ SQL injection prevention (Eloquent ORM)
- ✅ XSS protection (Blade escaping)

## 🛠️ Tech Stack

**Backend**:
- Laravel 12
- PHP 8.2
- MySQL
- Razorpay PHP SDK

**Frontend**:
- Vite
- Tailwind CSS  
- Alpine.js
- Blade Templates

**Infrastructure**:
- Supervisor (queue workers)
- Nginx/Apache
- Redis (optional, recommended)

## 📁 Project Structure

```
app/
├── Actions/           # Business logic (FulfilPaidOrder)
├── Enums/            # Enumerations (OrderStatus, UserRole, VideoStatus)
├── Http/
│   ├── Controllers/  # Route controllers
│   └── Middleware/   # Custom middleware
├── Mail/             # Mailable classes (PurchaseReceipt)
├── Models/           # Eloquent models
├── Services/         # External services (RazorpayGateway)
└── Support/          # Helper classes (StreamToken, VideoEmbed)

resources/
├── css/              # Tailwind CSS
├── js/               # Alpine.js components
└── views/            # Blade templates

database/
├── migrations/       # Database schema
└── seeders/          # Test data

tests/
├── Feature/          # Integration tests
└── Unit/             # Unit tests
```

## 🔑 Environment Variables

Key variables to configure (see `.env.production.example`):

```env
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database
DB_DATABASE=traders_point
DB_USERNAME=your_user
DB_PASSWORD=strong_password

# Mail (choose one provider)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password

# Razorpay (LIVE keys!)
RAZORPAY_KEY=rzp_live_XXXXXXXXXX
RAZORPAY_SECRET=LIVE_SECRET_HERE
RAZORPAY_WEBHOOK_SECRET=whsec_XXXXXXXXXX
```

## 🎯 Admin Access

After seeding, admin credentials will be in `database/seeders/AdminUserSeeder.php`.

Default test user (if seeded):
- Email: `test@example.com`
- Password: `password`

**🔒 Change default passwords in production!**

## 📝 License

[Your License Here]

## 🤝 Support

- Documentation: See docs in this repository
- Issues: [GitHub Issues](https://github.com/your-repo/traders-point/issues)
- Email: support@yourdomain.com

## 🎓 Credits

Built with Laravel, Razorpay, Tailwind CSS, and ❤️

---

**Important Files**:
- `PRODUCTION-CHECKLIST.md` - Must-read before going live
- `MONITORING.md` - Setup monitoring and alerts
- `.env.production.example` - Production environment template
- `deploy-production.sh` - Automated deployment script
- `supervisor-queue-worker.conf` - Queue worker configuration
