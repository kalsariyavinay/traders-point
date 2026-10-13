#!/bin/bash

###############################################################################
# Production Deployment Script for Traders Point Academy
# Run this script after pulling latest code on production server
###############################################################################

set -e  # Exit on error

echo "🚀 Starting Production Deployment..."

# 1. Put application in maintenance mode
echo "📝 Enabling maintenance mode..."
php artisan down --render="errors::503" --retry=60

# 2. Pull latest code
echo "📥 Pulling latest code..."
git pull origin main

# 3. Install/Update Composer dependencies (optimized)
echo "📦 Installing Composer dependencies..."
composer install --optimize-autoloader --no-dev --no-interaction

# 4. Install/Update NPM dependencies
echo "📦 Installing NPM dependencies..."
npm ci --production

# 5. Build frontend assets
echo "🎨 Building frontend assets..."
npm run build

# 6. Run database migrations
echo "🗄️  Running database migrations..."
php artisan migrate --force

# 7. Clear all caches
echo "🧹 Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 8. Optimize for production
echo "⚡ Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 9. Create storage link if not exists
echo "🔗 Ensuring storage link exists..."
php artisan storage:link

# 10. Restart queue workers
echo "♻️  Restarting queue workers..."
php artisan queue:restart

# 11. Bring application back online
echo "✅ Disabling maintenance mode..."
php artisan up

echo "🎉 Deployment completed successfully!"
echo ""
echo "📋 Post-deployment checklist:"
echo "   - Verify application is accessible"
echo "   - Check queue workers are running"
echo "   - Monitor error logs for any issues"
echo "   - Test payment flow"
echo "   - Test video streaming"
